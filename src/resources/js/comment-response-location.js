import { loadPdfJs } from './pdf-annotation-workspace.js';

export const normalizeLocationText = (text) => String(text || '').normalize('NFKC')
    .replace(/\u00ad/g, '').replace(/[\u2010\u2011]/g, '-').replace(/\s+/g, ' ').trim().toLowerCase();

// PDF text does not always retain Word paragraph tags. Use visible line spacing,
// indentation and column boundaries, and let faculty review the detected blocks.
export function pdfPageParagraphs(items, pageNumber) {
    const lines = [];
    for (const item of items.filter((item) => item.text?.trim()).sort((a, b) => a.y - b.y || a.x - b.x)) {
        let line = lines.find((line) => Math.abs(line.y - item.y) < Math.max(2, item.height * 0.2));
        if (!line) { line = { y: item.y, height: item.height, items: [] }; lines.push(line); }
        line.height = Math.max(line.height, item.height);
        line.items.push(item);
    }
    const blocks = [];
    let previous = null;
    for (const line of lines.sort((a, b) => a.y - b.y)) {
        const segments = [];
        for (const item of line.items.sort((a, b) => a.x - b.x)) {
            const last = segments.at(-1);
            if (!last || item.x - last.right > Math.max(24, line.height * 2)) {
                segments.push({ x: item.x, right: item.x + item.width, text: item.text });
            } else { last.text += ' ' + item.text; last.right = item.x + item.width; }
        }
        for (const segment of segments) {
            const newParagraph = !previous || segments.length > 1 || previous.columns > 1
                || line.y - previous.y > Math.max(line.height, previous.height) * 1.65
                || segment.x - previous.x > line.height * 0.7
                || Math.abs(line.height - previous.height) > 2
                || /^(?:[•●▪]|\d+[.)]|[a-z][.)])\s/i.test(segment.text);
            if (newParagraph) blocks.push({ page: pageNumber, paragraph: blocks.length + 1, text: segment.text });
            else blocks.at(-1).text += ' ' + segment.text;
            previous = { ...segment, y: line.y, height: line.height, columns: segments.length };
        }
    }
    return blocks;
}

export function matchingPassages(passages, texts) {
    const matches = new Set();
    for (const hint of texts) {
        const query = normalizeLocationText(typeof hint === 'object' ? hint?.text : hint);
        if (!query || !/[\p{L}\p{N}]/u.test(query)) continue;
        const numeric = /^[\d\s.,%₱$+-]+$/.test(query);
        const numericValue = (value) => Number(value.replace(/[,₱$\s]/g, ''));
        const includes = (value, needle) => {
            let start = value.indexOf(needle);
            while (start >= 0) {
                const before = value[start - 1] || '';
                const after = value[start + needle.length] || '';
                if (!/[\p{L}\p{N}]/u.test(before) && !/[\p{L}\p{N}]/u.test(after)) return true;
                start = value.indexOf(needle, start + 1);
            }
            return false;
        };
        let candidates = passages.filter((passage) => {
            const text = normalizeLocationText(passage.text);
            return numeric
                ? text === query || (/^[\d\s.,₱$+-]+$/.test(text) && numericValue(text) === numericValue(query))
                : query.length < 2 ? text === query : includes(text, query);
        });
        const contexts = (Array.isArray(hint?.context) ? hint.context : [hint?.context])
            .map(normalizeLocationText).filter(Boolean);
        if (candidates.length > 1 && contexts.length) {
            const contextual = candidates.filter((candidate) => {
                const index = passages.indexOf(candidate);
                if (contexts.some((context) => includes(normalizeLocationText(candidate.text), context))) return true;
                for (let previous = index - 1; previous >= 0; previous--) {
                    const anchor = passages[previous];
                    if (anchor.page !== candidate.page) break;
                    const text = normalizeLocationText(anchor.text);
                    if (contexts.some((context) => includes(text, context))) return true;
                    if (/^(?:[ivxlcdm]+\.|\d+\.)\s/i.test(text) || /:\s*$/.test(text)) break;
                }
                return false;
            });
            if (contextual.length) candidates = contextual;
        }
        candidates.forEach((passage) => matches.add(passage));
    }
    return [...matches];
}

export async function readPdfPassages(source, label, pdfLoader = loadPdfJs) {
    const pdfJs = await pdfLoader();
    const options = typeof source?.arrayBuffer === 'function'
        ? { data: new Uint8Array(await source.arrayBuffer()) }
        : { url: source, withCredentials: true };
    const task = pdfJs.getDocument(options);
    try {
        const pdf = await task.promise;
        const passages = [];
        for (let pageNumber = 1; pageNumber <= pdf.numPages; pageNumber++) {
            const page = await pdf.getPage(pageNumber);
            const viewport = page.getViewport({ scale: 1 });
            const content = await page.getTextContent();
            const items = content.items.filter((item) => typeof item.str === 'string').map((item) => {
                const transform = pdfJs.Util.transform(viewport.transform, item.transform);
                return { text: item.str, x: transform[4], y: transform[5], width: item.width, height: Math.hypot(transform[2], transform[3]) || item.height || 10 };
            });
            passages.push(...pdfPageParagraphs(items, pageNumber).map((passage) => ({ ...passage, label })));
            page.cleanup();
        }
        return passages;
    } finally { await task.destroy(); }
}

export function initializeCommentResponseLocations(form, { readDocuments, suggestedTexts = () => [], fingerprint = () => '' }) {
    const fields = [...form.querySelectorAll('[data-comment-response-location]')];
    if (!fields.length) return null;
    const documents = new Map();
    let documentFingerprint = null;
    let request = 0;
    let pending = null;
    let pendingRequest = 0;
    let pendingDocumentType = null;
    const noChange = (field) => Boolean(field.querySelector('[data-comment-response-no-change]')?.checked);
    const fixedType = (field) => field.dataset.responseDocument || field.querySelector('[data-location-document]')?.value || '';
    const availableTypes = () => [...form.querySelectorAll('[data-revision-document]')].filter((card) => (
        !card.querySelector('[data-revision-no-change]')?.checked
        && card.querySelector('[data-revision-document-state]')?.dataset.modified !== 'false'
    )).map((card) => card.dataset.revisionDocument);
    const fieldTypes = (field) => fixedType(field) ? [fixedType(field)] : availableTypes();
    const setStatus = (field, message, state) => {
        const status = field.querySelector('[data-location-status]');
        if (status) status.textContent = message;
        field.dataset.locationState = state;
        const retry = field.querySelector('[data-location-retry]');
        if (retry) { retry.hidden = state !== 'error'; retry.disabled = state === 'pending'; }
    };
    const clear = (field, forget = false) => {
        field.querySelector('[data-comment-response-page]').value = '';
        field.querySelector('[data-comment-response-paragraph]').value = '';
        const confirmation = field.querySelector('[data-location-confirm]');
        if (confirmation) { confirmation.replaceChildren(); confirmation.hidden = true; }
        if (forget) {
            ['locationAutomatic', 'locationText', 'locationDocument', 'locationLabel', 'locationConfirmed', 'locationFingerprint', 'locationNeighbors']
                .forEach((key) => delete field.dataset[key]);
        }
    };
    const apply = (field, passage, documentType, confirmed = false) => {
        if (noChange(field)) return;
        field.querySelector('[data-comment-response-page]').value = String(passage.page);
        field.querySelector('[data-comment-response-paragraph]').value = String(passage.paragraph);
        field.dataset.locationAutomatic = 'true';
        field.dataset.locationText = passage.text;
        field.dataset.locationDocument = documentType;
        field.dataset.locationLabel = passage.label || '';
        field.dataset.locationFingerprint = fingerprint();
        field.dataset.locationConfirmed = String(confirmed);
        const passages = documents.get(documentType)?.passages || [];
        const index = passages.indexOf(passage);
        field.dataset.locationNeighbors = JSON.stringify([passages[index - 1]?.text || '', passages[index + 1]?.text || ''].map(normalizeLocationText));
        const confirmation = field.querySelector('[data-location-confirm]');
        if (confirmation) { confirmation.replaceChildren(); confirmation.hidden = true; }
        setStatus(field, `Location added automatically · Page ${passage.page}, paragraph ${passage.paragraph}.`, 'detected');
    };
    const confirm = (field, candidates) => {
        clear(field);
        const container = field.querySelector('[data-location-confirm]');
        if (!container) {
            setStatus(field, 'ATHENA could not identify the changed text yet. Finish the paper and try again.', 'error');
            return;
        }
        container.hidden = false;
        candidates.forEach(({ passage, type }) => {
            const button = form.ownerDocument.createElement('button');
            button.type = 'button';
            button.className = 'revision-location-candidate';
            button.dataset.locationCandidate = type;
            const text = form.ownerDocument.createElement('span');
            text.textContent = passage.text.slice(0, 240);
            const location = form.ownerDocument.createElement('small');
            location.textContent = `${passage.label || type.replaceAll('_', ' ')} · Page ${passage.page}, paragraph ${passage.paragraph}`;
            button.append(text, location);
            button.addEventListener('click', () => apply(field, passage, type, true));
            container.append(button);
        });
        setStatus(field, 'This change appears in more than one place. Confirm the matching text below; ATHENA will add its location.', 'confirm');
    };
    const populate = (field) => {
        if (noChange(field)) {
            clear(field, true);
            setStatus(field, 'No paper change needed for this reply.', 'no_change');
            return;
        }
        if (documentFingerprint !== null && documentFingerprint !== fingerprint()) {
            clear(field, true);
            setStatus(field, 'The location will update automatically when you finish this paper.', 'idle');
            return;
        }
        const types = fieldTypes(field);
        const candidates = [];
        let hasHints = false;
        for (const type of types) {
            const passages = documents.get(type)?.passages || [];
            if (field.dataset.locationConfirmed === 'true' && field.dataset.locationDocument === type
                && field.dataset.locationFingerprint === fingerprint()) {
                const matches = passages.filter((passage, index) => (
                    normalizeLocationText(passage.text) === normalizeLocationText(field.dataset.locationText)
                    && JSON.stringify([passages[index - 1]?.text || '', passages[index + 1]?.text || ''].map(normalizeLocationText)) === field.dataset.locationNeighbors
                ));
                if (matches.length === 1) { apply(field, matches[0], type, true); return; }
            }
        }
        const hints = types.flatMap((type) => suggestedTexts(field, type) || []);
        hasHints = hints.length > 0;
        for (const hint of hints) {
            const matches = types.flatMap((type) => matchingPassages(documents.get(type)?.passages || [], [hint])
                .map((passage) => ({ type, passage })));
            if (matches.length === 1) { apply(field, matches[0].passage, matches[0].type); return; }
            candidates.push(...matches);
        }
        if (candidates.length) {
            confirm(field, candidates.filter((candidate, index) => candidates.findIndex((other) => other.type === candidate.type && other.passage === candidate.passage) === index));
        } else if (!hasHints && types.some((type) => documents.get(type)?.passages?.length)) {
            confirm(field, types.flatMap((type) => (documents.get(type)?.passages || []).map((passage) => ({ type, passage }))));
            setStatus(field, 'Confirm the text your reply refers to below; ATHENA will add its location.', 'confirm');
        } else {
            clear(field);
            const read = types.some((type) => documents.has(type));
            setStatus(field, read
                ? 'ATHENA could not read the changed text in this PDF. Try preparing the paper again.'
                : 'The page and paragraph will be added automatically when you finish this paper.', read ? 'error' : 'idle');
        }
    };
    const refresh = async (prepared = null, documentType = null) => {
        if (pending) {
            const outdated = prepared !== null || pendingRequest !== request || pendingDocumentType !== documentType;
            await pending;
            if (outdated) return refresh(prepared, documentType);
            return;
        }
        const current = ++request;
        const activeFields = documentType ? fields.filter((field) => !fixedType(field) || fixedType(field) === documentType) : fields;
        const types = documentType
            ? (activeFields.some((field) => !noChange(field)) ? [documentType] : [])
            : [...new Set(activeFields.filter((field) => !noChange(field)).flatMap(fieldTypes))];
        const startingFingerprint = fingerprint();
        if (documentFingerprint !== null && documentFingerprint !== startingFingerprint) documents.clear();
        if (prepared === null && types.every((type) => documents.get(type)?.passages?.length) && documentFingerprint === startingFingerprint) {
            activeFields.forEach(populate);
            return;
        }
        activeFields.forEach((field) => { if (!noChange(field)) setStatus(field, 'Adding the changed location from your revised PDF…', 'pending'); });
        const operation = (async () => {
            try {
                types.forEach((type) => documents.delete(type));
                const results = types.length ? await readDocuments(types, prepared) : new Map();
                if (current !== request || startingFingerprint !== fingerprint()) {
                    if (current === request) activeFields.forEach((field) => {
                        clear(field, true);
                        setStatus(field, 'The paper changed while its location was being added. Finish the paper again to update it.', 'error');
                    });
                    return;
                }
                for (const [type, result] of results) documents.set(type, result);
                documentFingerprint = startingFingerprint;
                activeFields.forEach(populate);
            } catch (error) {
                if (current !== request) return;
                types.forEach((type) => documents.delete(type));
                activeFields.forEach((field) => {
                    if (noChange(field)) return;
                    clear(field);
                    setStatus(field, error.message || 'The revised PDF could not be read. Try preparing the paper again.', 'error');
                });
            }
        })();
        pending = operation;
        pendingRequest = current;
        pendingDocumentType = documentType;
        await operation;
        if (pending === operation) pending = null;
    };
    const invalidate = (type) => {
        request += 1;
        documents.delete(type);
        fields.forEach((field) => {
            if (fixedType(field) && fixedType(field) !== type) return;
            clear(field, true);
            setStatus(field, noChange(field) ? 'No paper change needed for this reply.'
                : 'The location will update automatically when you finish this paper.', noChange(field) ? 'no_change' : 'idle');
        });
    };
    fields.forEach((field) => {
        field.querySelector('[data-location-retry]')?.addEventListener('click', () => void refresh(null, fixedType(field) || null));
        field.querySelectorAll('[data-comment-response-action], [data-comment-response-no-change]').forEach((action) => action.addEventListener('change', () => populate(field)));
        if (noChange(field)) populate(field);
    });
    form.addEventListener('revision-paper-reviewed', (event) => { void refresh(null, event.detail.documentType); });
    form.addEventListener('revision-document-changed', (event) => invalidate(event.detail.documentType));
    return {
        refresh, invalidate,
        async settle() {
            while (pending) await pending;
        },
        async verify(prepared) {
            await refresh(prepared);
            const missing = fields.find((field) => !noChange(field) && (
                field.dataset.locationState !== 'detected'
                || !field.querySelector('[data-comment-response-page]').value
                || !field.querySelector('[data-comment-response-paragraph]').value
            ));
            if (missing) {
                const label = documents.get(fixedType(missing) || missing.dataset.locationDocument || fieldTypes(missing)[0])?.passages?.[0]?.label || 'the revised paper';
                const error = new Error(missing.dataset.locationState === 'confirm'
                    ? `Confirm where this change appears in ${label}.`
                    : 'The revised paper could not be read. Try preparing it again.');
                error.revisionDocumentType = fixedType(missing) || missing.dataset.locationDocument || fieldTypes(missing)[0];
                error.revisionResponseKey = missing.dataset.responseKey;
                throw error;
            }
        },
    };
}
