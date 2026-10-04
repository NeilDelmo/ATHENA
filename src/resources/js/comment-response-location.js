import { loadPdfJs } from './pdf-annotation-workspace.js';

export const normalizeLocationText = (text) => String(text || '').normalize('NFKC')
    .replace(/\u00ad/g, '').replace(/\s+/g, ' ').trim().toLowerCase();

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
    const queries = [...new Set(texts.map(normalizeLocationText).filter((text) => text.length >= 12))];
    return passages.filter((passage) => queries.some((query) => normalizeLocationText(passage.text).includes(query)));
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
    let request = 0;
    let pending = null;
    let pendingRequest = 0;
    const noChange = (field) => field.querySelector('[data-comment-response-no-change]').checked;
    const status = (field, message) => { field.querySelector('[data-location-status]').textContent = message; };
    const clear = (field) => {
        if (field.dataset.locationAutomatic !== 'true') return;
        field.querySelector('[data-comment-response-page]').value = '';
        field.querySelector('[data-comment-response-paragraph]').value = '';
    };
    const apply = (field, passage, documentType) => {
        if (noChange(field)) return;
        field.querySelector('[data-comment-response-page]').value = String(passage.page);
        field.querySelector('[data-comment-response-paragraph]').value = String(passage.paragraph);
        field.dataset.locationAutomatic = 'true';
        delete field.dataset.locationManual;
        field.dataset.locationText = passage.text;
        field.dataset.locationDocument = documentType;
        field.dataset.locationLabel = passage.label;
        status(field, `${passage.label} · Page ${passage.page}, paragraph ${passage.paragraph}. Check the detected paragraph before submitting.`);
    };
    const populate = (field, automatic = true) => {
        const type = field.querySelector('[data-location-document]').value;
        const passages = documents.get(type)?.passages || [];
        const select = field.querySelector('[data-location-passage]');
        select.replaceChildren(new Option(passages.length ? 'Choose the changed passage' : 'No readable revised text available', ''));
        passages.forEach((passage, index) => select.add(new Option(`${passage.label} · Page ${passage.page}, paragraph ${passage.paragraph} · ${passage.text.slice(0, 160)}`, String(index))));
        if (noChange(field)) return;
        if (field.dataset.locationManual === 'true') { status(field, 'Manual location entered. Check it against the final revised PDF.'); return; }
        let matches = [];
        if (field.dataset.locationAutomatic === 'true' && field.dataset.locationDocument === type) {
            matches = passages.filter((passage) => passage.label === field.dataset.locationLabel && normalizeLocationText(passage.text) === normalizeLocationText(field.dataset.locationText));
        } else if (automatic) matches = matchingPassages(passages, suggestedTexts(field, type));
        if (matches.length === 1) {
            select.value = String(passages.indexOf(matches[0]));
            apply(field, matches[0], type);
        } else {
            clear(field);
            status(field, passages.length ? 'Choose the passage that contains your change. ATHENA will fill both numbers.' : 'No readable text found. Upload a PDF with selectable text, or enter the location manually.');
        }
    };
    const refresh = async (prepared = null) => {
        if (pending && !prepared) {
            const outdated = pendingRequest !== request;
            await pending;
            if (outdated) return refresh();
            return;
        }
        const current = ++request;
        const types = [...new Set(fields.filter((field) => !noChange(field)).map((field) => field.querySelector('[data-location-document]').value).filter(Boolean))];
        fields.forEach((field) => {
            field.querySelector('[data-location-refresh]').disabled = true;
            if (!noChange(field)) status(field, 'Reading the current revised PDFs…');
        });
        const startingFingerprint = fingerprint();
        const operation = (async () => {
            try {
                const results = await readDocuments(types, prepared);
                if (current !== request || startingFingerprint !== fingerprint()) {
                    if (current === request) fields.forEach((field) => { clear(field); status(field, 'The paper changed while it was being read. Find page and paragraph again.'); });
                    return;
                }
                for (const [type, result] of results) documents.set(type, result);
                fields.forEach((field) => populate(field));
            } catch (error) {
                if (current !== request) return;
                fields.forEach((field) => { if (!noChange(field)) { clear(field); status(field, error.message || 'The revised paper could not be read. Try again or enter the numbers manually.'); } });
            } finally {
                if (current === request) fields.forEach((field) => { field.querySelector('[data-location-refresh]').disabled = false; });
            }
        })();
        pending = operation;
        pendingRequest = current;
        await operation;
        if (pending === operation) pending = null;
    };
    const invalidate = (type) => {
        request += 1;
        documents.delete(type);
        fields.forEach((field) => {
            field.querySelector('[data-location-refresh]').disabled = false;
            if (field.querySelector('[data-location-document]').value !== type) return;
            clear(field);
            field.querySelector('[data-location-passage]').replaceChildren(new Option('Read the updated revised paper', ''));
            status(field, 'The paper changed. Find page and paragraph again to update the location.');
        });
    };
    fields.forEach((field) => {
        const documentSelect = field.querySelector('[data-location-document]');
        if (!documentSelect.value && documentSelect.options.length === 2) documentSelect.selectedIndex = 1;
        field.querySelector('[data-location-refresh]').addEventListener('click', () => void refresh());
        field.querySelector('[data-location-document]').addEventListener('change', () => {
            clear(field);
            delete field.dataset.locationText;
            delete field.dataset.locationAutomatic;
            delete field.dataset.locationManual;
            populate(field, false);
            if (!documents.has(field.querySelector('[data-location-document]').value)) void refresh();
        });
        field.querySelector('[data-location-passage]').addEventListener('change', (event) => {
            const type = field.querySelector('[data-location-document]').value;
            const passage = event.target.value === '' ? null : documents.get(type)?.passages[Number(event.target.value)];
            if (passage) apply(field, passage, type);
            else { clear(field); delete field.dataset.locationText; }
        });
        field.querySelector('[data-comment-response-no-change]').addEventListener('change', () => { if (!noChange(field)) populate(field); });
        ['[data-comment-response-page]', '[data-comment-response-paragraph]'].forEach((selector) => field.querySelector(selector).addEventListener('input', () => {
            delete field.dataset.locationAutomatic;
            delete field.dataset.locationText;
            field.dataset.locationManual = 'true';
            field.querySelector('[data-location-passage]').value = '';
            status(field, 'Manual location entered. Check it against the final revised PDF.');
        }));
    });
    form.addEventListener('revision-step-changed', (event) => { if (event.detail.step === 3) void refresh(); });
    form.addEventListener('revision-document-changed', (event) => invalidate(event.detail.documentType));
    if (form.querySelector('[data-revision-step="3"]')?.hidden === false) void refresh();
    return {
        refresh, invalidate,
        async verify(prepared) {
            await refresh(prepared);
            const missing = fields.find((field) => !noChange(field) && field.dataset.locationAutomatic === 'true'
                && (!field.querySelector('[data-comment-response-page]').value || !field.querySelector('[data-comment-response-paragraph]').value));
            if (missing) throw new Error('A changed passage could not be located in the final revised PDF. Return to Action and Response and select its current passage.');
        },
    };
}
