function sourceText(value) {
    return typeof value === 'string' || typeof value === 'number' ? String(value).trim() : '';
}

function sourceAuthors(value) {
    return Array.isArray(value) ? value.map((author) => sourceText(author?.name ?? author)).filter(Boolean).join(', ') : sourceText(value);
}

export function sourceWorkspaceSafeUrl(value) {
    const candidate = sourceText(value);
    if (!/^https?:\/\//i.test(candidate)) return '';
    try {
        const url = new URL(candidate);
        return ['http:', 'https:'].includes(url.protocol) && !url.username && !url.password ? url.href : '';
    } catch {
        return '';
    }
}

function emptySourceForm() {
    return { title: '', authors: '', year: '', venue: '', volume: '', issue: '', pages: '', publisher: '', doi: '', url: '', description: '', type: 'article', source: 'Manual entry' };
}

function importSource(fields, format) {
    const year = sourceText(fields.year).match(/\b(?:19|20)\d{2}\b/)?.[0] || '';
    const authorNames = (Array.isArray(fields.authors) ? fields.authors : sourceText(fields.authors).split(/\s+and\s+/i))
        .map((name) => sourceText(name).replace(/[{}]/g, ''))
        .map((name) => name.includes(',') ? name.split(',').reverse().map((part) => part.trim()).join(' ') : name)
        .filter(Boolean);
    return {
        ...emptySourceForm(), ...fields, year,
        title: sourceText(fields.title).replace(/[{}]/g, ''),
        authors: authorNames.join(', '),
        url: sourceWorkspaceSafeUrl(fields.url),
        source: `${format} import`,
    };
}

export function parseSourceImport(input, format) {
    const text = sourceText(input);
    if (format === 'ris') {
        const sources = [];
        let fields = {}, authors = [], lastKey = '';
        for (const line of text.split(/\r?\n/)) {
            const field = line.match(/^([A-Z0-9]{2})\s*-\s?(.*)$/);
            if (!field) {
                if (lastKey && line.trim()) fields[lastKey] = `${fields[lastKey] || ''} ${line.trim()}`;
                continue;
            }
            const [, key, value] = field;
            if (key === 'TY') { fields = {}; authors = []; }
            if (['AU', 'A1'].includes(key)) authors.push(value);
            else fields[key] = value;
            lastKey = key;
            if (key === 'ER') {
                const source = importSource({ title: fields.TI || fields.T1, authors, year: fields.PY || fields.Y1, venue: fields.JO || fields.JF || fields.T2, doi: fields.DO, url: fields.UR, description: fields.AB, volume: fields.VL, issue: fields.IS, pages: [fields.SP, fields.EP].filter(Boolean).join('-'), publisher: fields.PB }, 'RIS');
                if (source.title) sources.push(source);
                fields = {}; authors = []; lastKey = '';
            }
        }
        return sources;
    }
    if (format !== 'bibtex') return [];

    const sources = [];
    const entry = /@([\w-]+)\s*[{(]\s*[^,\s]+\s*,/g;
    let match;
    while ((match = entry.exec(text))) {
        let cursor = entry.lastIndex;
        const fields = {};
        while (cursor < text.length) {
            while (/[\s,]/.test(text[cursor] || '')) cursor++;
            if (/[})]/.test(text[cursor] || '')) break;
            const key = text.slice(cursor).match(/^([\w-]+)\s*=\s*/);
            if (!key) break;
            cursor += key[0].length;
            let value = '';
            if (text[cursor] === '{') {
                cursor++;
                let depth = 1;
                while (cursor < text.length && depth > 0) {
                    const character = text[cursor++];
                    if (character === '{') depth++;
                    if (character === '}') depth--;
                    if (depth > 0) value += character;
                }
            } else if (text[cursor] === '"') {
                cursor++;
                while (cursor < text.length) {
                    const character = text[cursor++];
                    if (character === '"' && text[cursor - 2] !== '\\') break;
                    value += character;
                }
            } else {
                while (cursor < text.length && !/[,})]/.test(text[cursor])) value += text[cursor++];
            }
            fields[key[1].toLowerCase()] = sourceText(value);
        }
        entry.lastIndex = Math.max(cursor + 1, entry.lastIndex);
        const source = importSource({ title: fields.title, authors: fields.author, year: fields.year, venue: fields.journal || fields.booktitle, doi: fields.doi, url: fields.url, description: fields.abstract, volume: fields.volume, issue: fields.number, pages: fields.pages, publisher: fields.publisher, type: match[1] }, 'BibTeX');
        if (source.title) sources.push(source);
    }
    return sources;
}

export function normalizeSourceEvidence(payload = {}) {
    const document = payload.document && typeof payload.document === 'object' ? payload.document : null;
    return {
        source: payload.source || null,
        document: document ? {
            name: sourceText(document.name), url: sourceWorkspaceSafeUrl(document.url),
            notice: sourceText(document.notice), coverage: document.coverage || {},
            pages: (Array.isArray(document.pages) ? document.pages : []).filter((page) => Number.isSafeInteger(Number(page.number)) && Number(page.number) > 0)
                .map((page) => ({ number: Number(page.number), text: sourceText(page.text) })),
        } : null,
        passages: (Array.isArray(payload.passages) ? payload.passages : []).filter((passage) => /^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i.test(sourceText(passage.id)))
            .map((passage) => ({ id: String(passage.id), quote: sourceText(passage.quote), note: sourceText(passage.note), page: Number(passage.page) > 0 ? Number(passage.page) : null, kind: passage.kind === 'note' ? 'note' : 'quote', origin: passage.origin === 'pdf' ? 'pdf' : 'manual' })),
    };
}

export function proposalSourceWorkspace(config = {}) {
    return {
        sourceWorkspaceOpen: false,
        sourceWorkspaceTab: 'paper',
        sourceWorkspaceView: 'list',
        sourceWorkspaceCitationMode: false,
        sourceWorkspaceQuery: '',
        sourceWorkspaceLibraryResults: [],
        sourceWorkspaceSearchResults: [],
        sourceWorkspaceSearchPerformed: false,
        sourceWorkspaceSearchFilters: { year_from: '', year_to: '', open_access: false },
        sourceWorkspaceLoading: false,
        sourceWorkspaceSaving: false,
        sourceWorkspaceAiLoading: false,
        sourceWorkspaceError: '',
        sourceWorkspaceNotice: '',
        sourceWorkspaceVersion: 0,
        sourceWorkspaceActiveSourceId: 0,
        sourceWorkspaceEvidenceCache: {},
        sourceWorkspaceSelectedPassages: [],
        sourceWorkspacePage: 0,
        sourceWorkspaceQuote: '',
        sourceWorkspaceNote: '',
        sourceWorkspacePassagePage: null,
        sourceWorkspaceAddMode: 'identifier',
        sourceWorkspaceIdentifier: '',
        sourceWorkspaceImportText: '',
        sourceWorkspaceImports: [],
        sourceWorkspaceImportIndex: 0,
        sourceWorkspaceSourceForm: emptySourceForm(),
        sourceWorkspacePendingFile: null,
        sourceWorkspacePendingLinkedSource: null,
        sourceWorkspaceAiMode: 'explain',
        sourceWorkspaceClaim: '',
        sourceWorkspaceInstruction: '',
        sourceWorkspaceDraft: { paragraph: '', evidence: [], can_insert: false },
        sourceWorkspaceDraftSignature: '',
        sourceWorkspaceDraftMode: '',
        sourceWorkspaceDestination: 'related-literature',
        sourceWorkspaceDraftInserted: false,
        sourceWorkspaceReturnFocus: null,
        sourceWorkspacePreviousOverflow: '',
        sourceWorkspaceScrollLocked: false,

        sourceWorkspaceBusy() {
            return this.sourceWorkspaceLoading || this.sourceWorkspaceSaving || this.sourceWorkspaceAiLoading;
        },

        sourceWorkspacePassageLabel(passage) {
            if (passage.kind === 'note') return 'Researcher reading note';
            return `${passage.origin === 'pdf' ? 'PDF excerpt' : 'Researcher supplied excerpt'}${passage.page ? ` · Page ${passage.page}` : ''}`;
        },

        lockSourceWorkspaceScroll() {
            if (typeof document === 'undefined' || this.sourceWorkspaceScrollLocked) return;
            this.sourceWorkspacePreviousOverflow = document.body.style.overflow;
            document.body.style.overflow = 'hidden';
            document.body.dataset.sourceWorkspaceScrollLock = 'true';
            this.sourceWorkspaceScrollLocked = true;
        },

        unlockSourceWorkspaceScroll() {
            if (typeof document === 'undefined' || !this.sourceWorkspaceScrollLocked) return;
            const otherModal = [...document.querySelectorAll('[aria-modal="true"],dialog[open]')]
                .some((element) => !element.hasAttribute('data-source-workspace') && element.getClientRects().length && getComputedStyle(element).display !== 'none');
            document.body.style.overflow = otherModal ? 'hidden' : (this.sourceWorkspacePreviousOverflow === 'hidden' ? '' : this.sourceWorkspacePreviousOverflow);
            delete document.body.dataset.sourceWorkspaceScrollLock;
            this.sourceWorkspaceScrollLocked = false;
        },

        destroySourceWorkspace() {
            this.sourceWorkspaceOpen = false;
            this.sourceWorkspaceVersion++;
            this.unlockSourceWorkspaceScroll();
        },

        sourceWorkspaceAssistanceSignature() {
            return JSON.stringify({ mode: this.sourceWorkspaceAiMode, claim: this.sourceWorkspaceClaim, instruction: this.sourceWorkspaceInstruction,
                passages: this.sourceWorkspaceSelectedPassages.map((selected) => {
                    const passage = this.sourceWorkspaceEvidenceCache[selected.source_link_id]?.passages.find((item) => item.id === selected.passage_id);
                    return passage ? { source: selected.source_link_id, ...passage } : { source: selected.source_link_id, missing: selected.passage_id };
                }) });
        },

        invalidateSourceWorkspaceDraft() {
            this.sourceWorkspaceDraftSignature = '';
            if (this.sourceWorkspaceDraft) this.sourceWorkspaceDraft.can_insert = false;
        },

        removeSourceWorkspaceSelectedPassage(sourceId, passageId) {
            if (this.sourceWorkspaceBusy()) return;
            this.sourceWorkspaceSelectedPassages = this.sourceWorkspaceSelectedPassages.filter((item) => !(item.source_link_id === Number(sourceId) && item.passage_id === String(passageId)));
            this.invalidateSourceWorkspaceDraft();
        },

        async openSourceWorkspace(tab = 'paper', citationMode = false) {
            this.sourceWorkspaceReturnFocus = typeof document !== 'undefined' ? document.activeElement : null;
            this.sourceWorkspaceOpen = true;
            this.lockSourceWorkspaceScroll();
            this.sourceWorkspaceCitationMode = Boolean(citationMode);
            this.sourceWorkspaceView = 'list';
            this.sourceWorkspaceError = '';
            this.sourceWorkspaceNotice = '';
            this.sourceWorkspaceClaim = sourceText(this.citationPickerSelection?.selectedText);
            await this.setSourceWorkspaceTab(tab);
            this.$nextTick?.(() => this.$refs?.sourceWorkspaceClose?.focus());
        },

        closeSourceWorkspace() {
            if (this.sourceWorkspaceSaving || this.sourceWorkspaceAiLoading) return false;
            this.sourceWorkspaceOpen = false;
            this.sourceWorkspaceVersion++;
            this.sourceWorkspaceLoading = false;
            this.unlockSourceWorkspaceScroll();
            this.sourceWorkspaceReturnFocus?.focus?.();
            return true;
        },

        trapSourceWorkspaceFocus(event) {
            if (event.key !== 'Tab' || !this.sourceWorkspaceOpen) return;
            const panel = this.$refs?.sourceWorkspacePanel;
            const controls = [...(panel?.querySelectorAll('button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),a[href],[tabindex="0"]') || [])]
                .filter((element) => element.getClientRects().length);
            if (!controls.length) return;
            const first = controls[0], last = controls[controls.length - 1];
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
        },

        async setSourceWorkspaceTab(tab) {
            if (this.sourceWorkspaceSaving || this.sourceWorkspaceAiLoading) return;
            this.sourceWorkspaceVersion++;
            this.sourceWorkspaceLoading = false;
            this.sourceWorkspaceTab = ['paper', 'library', 'search', 'add'].includes(tab) ? tab : 'paper';
            this.sourceWorkspaceView = 'list';
            this.sourceWorkspaceError = '';
            this.sourceWorkspaceNotice = '';
            if (this.sourceWorkspaceTab === 'library' && !this.sourceWorkspaceLibraryResults.length) await this.searchSourceWorkspaceLibrary();
        },

        sourceWorkspaceUrl(value) { return sourceWorkspaceSafeUrl(value); },
        sourceWorkspaceMetadata(source) { return [sourceAuthors(source?.authors), sourceText(source?.year)].filter(Boolean).join(' · ') || 'Publication details not supplied'; },
        sourceWorkspaceSelectedClaim() { return sourceText(this.citationPickerSelection?.selectedText); },
        sourceWorkspaceSources() {
            if (this.sourceWorkspaceTab === 'library') return this.sourceWorkspaceLibraryResults;
            if (this.sourceWorkspaceTab === 'search') return this.sourceWorkspaceSearchResults;
            return this.literatureSources || [];
        },
        sourceWorkspaceSourceKey(source) { return sourceText(source?.id || source?.doi || source?.url || source?.title); },
        sourceWorkspaceLinkedSource(source) {
            if (this.sourceWorkspaceTab === 'paper') return source;
            if (this.sourceWorkspaceTab === 'library') return (this.literatureSources || []).find((item) => Number(item.literature_source_id) === Number(source.id)) || null;
            return this.linkedLiteratureSourceForResult?.(source) || (this.literatureSources || []).find((item) =>
                (source.doi && item.doi === source.doi) || (source.url && item.url === source.url) || (item.title && item.title === source.title)) || null;
        },
        sourceWorkspaceEndpoint(sourceId, suffix = 'evidence') {
            return `${String(config.evidenceBase || '').replace(/\/$/, '')}/${Number(sourceId)}/${suffix}`;
        },
        async sourceWorkspaceRequest(url, options = {}) {
            if (!url) throw new Error('This source action is unavailable. Refresh the editor and try again.');
            const response = await (config.request || globalThis.fetch)(url, {
                credentials: 'same-origin', ...options,
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': config.csrfToken || '', 'X-Requested-With': 'XMLHttpRequest', ...(options.body && !options.multipart ? { 'Content-Type': 'application/json' } : {}), ...options.headers },
            });
            if (response.status === 419 || response.redirected) throw new Error('Your session expired. Refresh the editor before trying again.');
            const payload = await response.json().catch(() => { throw new Error('The source service returned an unexpected response. Try again.'); });
            if (!response.ok) throw new Error(Object.values(payload?.errors || {}).flat()[0] || payload.message || 'This source action could not be completed.');
            return payload;
        },
        async searchSourceWorkspaceLibrary() {
            if (this.sourceWorkspaceBusy()) return false;
            const version = ++this.sourceWorkspaceVersion;
            this.sourceWorkspaceLoading = true;
            this.sourceWorkspaceError = '';
            try {
                const separator = String(config.libraryUrl || '').includes('?') ? '&' : '?';
                const payload = await this.sourceWorkspaceRequest(`${config.libraryUrl || ''}${separator}query=${encodeURIComponent(this.sourceWorkspaceQuery.trim())}`);
                if (version !== this.sourceWorkspaceVersion) return false;
                this.sourceWorkspaceLibraryResults = Array.isArray(payload.sources) ? payload.sources : [];
                return true;
            } catch (error) {
                if (version === this.sourceWorkspaceVersion) this.sourceWorkspaceError = error.message;
                return false;
            } finally { if (version === this.sourceWorkspaceVersion) this.sourceWorkspaceLoading = false; }
        },
        async searchSourceWorkspaceAcademic() {
            if (this.sourceWorkspaceBusy() || this.sourceWorkspaceQuery.trim().length < 3) return false;
            const version = ++this.sourceWorkspaceVersion;
            this.sourceWorkspaceLoading = true;
            this.sourceWorkspaceError = '';
            this.sourceWorkspaceSearchPerformed = true;
            try {
                const context = this.suggestedLiteratureContext?.().map((item) => sourceText(item.value)).filter(Boolean).join('\n') || sourceText(this.proposalTitle);
                const filters = this.sourceWorkspaceSearchFilters;
                const payload = await this.sourceWorkspaceRequest(config.searchUrl, { method: 'POST', body: JSON.stringify({ query: this.sourceWorkspaceQuery.trim(), context: context.slice(0, 6000) || undefined, year_from: filters.year_from ? Number(filters.year_from) : undefined, year_to: filters.year_to ? Number(filters.year_to) : undefined, open_access: Boolean(filters.open_access) }) });
                if (version !== this.sourceWorkspaceVersion) return false;
                this.sourceWorkspaceSearchResults = Array.isArray(payload.results) ? payload.results : [];
                this.sourceWorkspaceNotice = [payload.provider_notice, payload.search_notice].filter(Boolean).join(' ');
                return true;
            } catch (error) {
                if (version === this.sourceWorkspaceVersion) this.sourceWorkspaceError = error.message;
                return false;
            } finally { if (version === this.sourceWorkspaceVersion) this.sourceWorkspaceLoading = false; }
        },
        async sourceWorkspaceLinkLibrary(source) {
            const existing = (this.literatureSources || []).find((item) => Number(item.literature_source_id) === Number(source.id));
            if (existing) return existing;
            if (this.linkExistingLibrarySource) return this.linkExistingLibrarySource(source);
            const url = String(config.linkUrlTemplate || '').replace('__literature_source__', encodeURIComponent(String(source.id)));
            const payload = await this.sourceWorkspaceRequest(url, { method: 'POST', body: JSON.stringify({ research_context: ['Sources workspace'] }) });
            if (!payload.source?.id) throw new Error('The source could not be linked to this paper.');
            this.upsertLiteratureSource(payload.source);
            return payload.source;
        },
        async sourceWorkspaceEnsureLinked(source) {
            const existing = this.sourceWorkspaceLinkedSource(source);
            if (existing) return existing;
            if (this.sourceWorkspaceTab === 'library') return this.sourceWorkspaceLinkLibrary(source);
            if (this.ensureSuggestedLiteratureLinked) {
                const linked = await this.ensureSuggestedLiteratureLinked(source);
                if (!linked) throw new Error(this.literatureSearchError || 'The academic source could not be saved to this paper.');
                return linked;
            }
            const payload = await this.sourceWorkspaceRequest(config.storeUrl, { method: 'POST', body: JSON.stringify({ ...source, authors: sourceAuthors(source.authors), source: source.source || 'Academic search' }) });
            if (!payload.source?.id) throw new Error('The source could not be saved.');
            return this.sourceWorkspaceLinkLibrary(payload.source);
        },
        async saveSourceWorkspaceSource(source) {
            if (this.sourceWorkspaceBusy()) return false;
            this.sourceWorkspaceSaving = true;
            this.sourceWorkspaceError = '';
            try {
                const linked = await this.sourceWorkspaceEnsureLinked(source);
                this.sourceWorkspaceNotice = 'Source saved to this paper. You can cite it or open its reader.';
                return linked;
            } catch (error) { this.sourceWorkspaceError = error.message; return false; }
            finally { this.sourceWorkspaceSaving = false; }
        },
        async citeSourceWorkspaceSource(source) {
            if (this.sourceWorkspaceBusy() || !this.sourceWorkspaceCanCite?.()) return false;
            this.sourceWorkspaceSaving = true;
            this.sourceWorkspaceError = '';
            try {
                const linked = await this.sourceWorkspaceEnsureLinked(source);
                const inserted = await this.citeSelectedText(linked);
                if (!inserted) throw new Error(this.citationPickerError || 'Return to the paper and select a supported claim or place the cursor, then insert its citation.');
                this.sourceWorkspaceSaving = false;
                this.closeSourceWorkspace();
                return true;
            } catch (error) { this.sourceWorkspaceError = error.message; return false; }
            finally { this.sourceWorkspaceSaving = false; }
        },
        sourceWorkspaceActiveSource() { return (this.literatureSources || []).find((source) => Number(source.id) === Number(this.sourceWorkspaceActiveSourceId)) || null; },
        sourceWorkspaceEvidence() { return this.sourceWorkspaceEvidenceCache[this.sourceWorkspaceActiveSourceId] || { document: null, passages: [] }; },
        sourceWorkspaceCurrentPage() { return this.sourceWorkspaceEvidence().document?.pages?.[this.sourceWorkspacePage] || null; },
        sourceWorkspaceHasPdfText() { return Number(this.sourceWorkspaceEvidence().document?.coverage?.characters || 0) > 0; },
        sourceWorkspaceCoverageLabel() {
            const coverage = this.sourceWorkspaceEvidence().document?.coverage;
            if (!coverage) return '';
            return `${Number(coverage.extracted_pages || 0)} pages extracted${coverage.limited ? `; limited to ${Number(coverage.page_limit || 0)} pages and ${Number(coverage.character_limit || 0).toLocaleString()} characters` : ''}.`;
        },
        sourceWorkspaceAcceptEvidence(sourceId, payload) {
            this.invalidateSourceWorkspaceDraft();
            const evidence = normalizeSourceEvidence(payload);
            this.sourceWorkspaceEvidenceCache = { ...this.sourceWorkspaceEvidenceCache, [sourceId]: evidence };
            if (evidence.source?.id) this.upsertLiteratureSource(evidence.source);
            const passageIds = evidence.passages.map((passage) => passage.id);
            this.sourceWorkspaceSelectedPassages = this.sourceWorkspaceSelectedPassages
                .filter((selected) => selected.source_link_id !== Number(sourceId) || passageIds.includes(selected.passage_id))
                .map((selected) => {
                    if (selected.source_link_id !== Number(sourceId)) return selected;
                    const passage = evidence.passages.find((item) => item.id === selected.passage_id);
                    return { ...selected, quote: passage.quote, note: passage.note, page: passage.page, kind: passage.kind, origin: passage.origin };
                });
            return evidence;
        },
        async readSourceWorkspaceSource(source) {
            if (this.sourceWorkspaceBusy()) return false;
            this.sourceWorkspaceSaving = true;
            this.sourceWorkspaceError = '';
            const version = ++this.sourceWorkspaceVersion;
            try {
                const linked = await this.sourceWorkspaceEnsureLinked(source);
                const payload = await this.sourceWorkspaceRequest(this.sourceWorkspaceEndpoint(linked.id));
                if (version !== this.sourceWorkspaceVersion) return false;
                this.sourceWorkspaceAcceptEvidence(linked.id, payload);
                this.sourceWorkspaceActiveSourceId = Number(linked.id);
                this.sourceWorkspacePage = 0;
                this.sourceWorkspaceQuote = '';
                this.sourceWorkspaceNote = '';
                this.sourceWorkspacePassagePage = null;
                this.sourceWorkspaceView = 'reader';
                return true;
            } catch (error) { this.sourceWorkspaceError = error.message; return false; }
            finally { this.sourceWorkspaceSaving = false; }
        },
        captureSourceWorkspaceSelection(event) {
            const page = this.sourceWorkspaceCurrentPage();
            const selection = globalThis.getSelection?.();
            const reader = event?.currentTarget;
            if (!page || !selection || selection.isCollapsed || !reader?.contains(selection.anchorNode) || !reader.contains(selection.focusNode)) return false;
            const quote = selection.toString().trim();
            if (!quote || !page.text.includes(quote)) return false;
            this.sourceWorkspaceQuote = quote;
            this.sourceWorkspacePassagePage = page.number;
            this.sourceWorkspaceError = '';
            return true;
        },
        sourceWorkspaceCanSavePassage() {
            return !this.sourceWorkspaceBusy() && Boolean(this.sourceWorkspaceActiveSourceId)
                && this.sourceWorkspaceNote.trim().length <= 2000
                && ((this.sourceWorkspaceQuote.length >= 10 && this.sourceWorkspaceQuote.length <= 3000
                    && (!this.sourceWorkspaceHasPdfText() || Boolean(this.sourceWorkspacePassagePage)))
                    || (!this.sourceWorkspaceQuote && this.sourceWorkspaceNote.trim().length >= 3));
        },
        async saveSourceWorkspacePassage() {
            if (!this.sourceWorkspaceCanSavePassage()) return false;
            this.sourceWorkspaceSaving = true;
            this.sourceWorkspaceError = '';
            const sourceId = this.sourceWorkspaceActiveSourceId;
            try {
                const payload = await this.sourceWorkspaceRequest(this.sourceWorkspaceEndpoint(sourceId, 'passages'), { method: 'POST', body: JSON.stringify({ quote: this.sourceWorkspaceQuote, note: this.sourceWorkspaceNote.trim(), page: this.sourceWorkspaceQuote ? this.sourceWorkspacePassagePage : null, kind: this.sourceWorkspaceQuote ? 'quote' : 'note' }) });
                this.sourceWorkspaceAcceptEvidence(sourceId, payload);
                this.sourceWorkspaceQuote = '';
                this.sourceWorkspaceNote = '';
                this.sourceWorkspacePassagePage = null;
                this.sourceWorkspaceNotice = 'Excerpt or reading note saved to this source.';
                return true;
            } catch (error) { this.sourceWorkspaceError = error.message; return false; }
            finally { this.sourceWorkspaceSaving = false; }
        },
        async deleteSourceWorkspacePassage(passage) {
            if (this.sourceWorkspaceBusy()) return false;
            this.sourceWorkspaceSaving = true;
            this.sourceWorkspaceError = '';
            try {
                const payload = await this.sourceWorkspaceRequest(this.sourceWorkspaceEndpoint(this.sourceWorkspaceActiveSourceId, `passages/${encodeURIComponent(String(passage.id))}`), { method: 'DELETE' });
                this.sourceWorkspaceAcceptEvidence(this.sourceWorkspaceActiveSourceId, payload);
                return true;
            } catch (error) { this.sourceWorkspaceError = error.message; return false; }
            finally { this.sourceWorkspaceSaving = false; }
        },
        sourceWorkspacePassageSelected(sourceId, passageId) { return this.sourceWorkspaceSelectedPassages.some((item) => item.source_link_id === Number(sourceId) && item.passage_id === String(passageId)); },
        toggleSourceWorkspacePassage(sourceId, passage) {
            if (this.sourceWorkspaceBusy()) return false;
            const selected = this.sourceWorkspacePassageSelected(sourceId, passage.id);
            if (selected) {
                this.removeSourceWorkspaceSelectedPassage(sourceId, passage.id);
                return true;
            }
            const sourceIds = new Set(this.sourceWorkspaceSelectedPassages.map((item) => item.source_link_id));
            if (this.sourceWorkspaceSelectedPassages.length >= 12 || (!sourceIds.has(Number(sourceId)) && sourceIds.size >= 5)) {
                this.sourceWorkspaceError = 'Choose at most 12 passages from up to 5 sources.';
                return false;
            }
            const source = (this.literatureSources || []).find((item) => Number(item.id) === Number(sourceId));
            if (!source || !this.sourceWorkspaceEvidenceCache[sourceId]?.passages.some((item) => item.id === passage.id)) return false;
            this.sourceWorkspaceSelectedPassages = [...this.sourceWorkspaceSelectedPassages, { source_link_id: Number(sourceId), passage_id: String(passage.id), quote: sourceText(passage.quote), note: sourceText(passage.note), page: passage.page, kind: passage.kind, origin: passage.origin, title: source.title }];
            this.invalidateSourceWorkspaceDraft();
            return true;
        },
        sourceWorkspaceCanAssist() {
            const selected = this.sourceWorkspaceSelectedPassages;
            return !this.sourceWorkspaceBusy() && selected.length > 0 && selected.length <= 12
                && new Set(selected.map((item) => item.source_link_id)).size <= 5
                && selected.reduce((count, item) => count + sourceText(item.quote).length + sourceText(item.note).length, 0) <= 18000
                && (this.sourceWorkspaceAiMode === 'explain' || selected.some((item) => item.kind === 'quote' && item.quote))
                && (this.sourceWorkspaceAiMode !== 'support' || this.sourceWorkspaceClaim.trim().length >= 3);
        },
        async prepareSourceWorkspaceAssistance() {
            if (!this.sourceWorkspaceCanAssist()) return false;
            const evidence = [];
            for (const selected of this.sourceWorkspaceSelectedPassages) {
                let source = evidence.find((item) => item.source_link_id === selected.source_link_id);
                if (!source) { source = { source_link_id: selected.source_link_id, passage_ids: [] }; evidence.push(source); }
                source.passage_ids.push(selected.passage_id);
            }
            this.sourceWorkspaceAiLoading = true;
            this.sourceWorkspaceError = '';
            this.sourceWorkspaceDraftInserted = false;
            const signature = this.sourceWorkspaceAssistanceSignature();
            try {
                const payload = await this.sourceWorkspaceRequest(config.assistanceUrl, { method: 'POST', body: JSON.stringify({ mode: this.sourceWorkspaceAiMode, claim: this.sourceWorkspaceClaim.trim() || undefined, instruction: this.sourceWorkspaceInstruction.trim() || undefined, evidence }) });
                if (!sourceText(payload.draft?.paragraph) || !Array.isArray(payload.draft?.evidence)) throw new Error('The assistant returned an incomplete draft. Try again.');
                this.sourceWorkspaceDraft = { paragraph: sourceText(payload.draft.paragraph), evidence: payload.draft.evidence, can_insert: payload.draft.can_insert === true && payload.supported !== false };
                this.sourceWorkspaceDraftSignature = signature;
                this.sourceWorkspaceDraftMode = payload.mode || this.sourceWorkspaceAiMode;
                this.sourceWorkspaceNotice = sourceText(payload.notice);
                this.sourceWorkspaceView = 'draft';
                return true;
            } catch (error) { this.sourceWorkspaceError = error.message; return false; }
            finally { this.sourceWorkspaceAiLoading = false; }
        },
        sourceWorkspaceCanInsertDraft() {
            return !this.sourceWorkspaceBusy() && this.sourceWorkspaceDraftMode === 'synthesize' && !this.sourceWorkspaceDraftInserted
                && this.sourceWorkspaceDraft?.can_insert === true
                && this.sourceWorkspaceDraftSignature === this.sourceWorkspaceAssistanceSignature()
                && sourceText(this.sourceWorkspaceDraft?.paragraph).length >= 40 && sourceText(this.sourceWorkspaceDraft?.paragraph).length <= 5000;
        },
        async insertReviewedSourceWorkspaceDraft() {
            if (!this.sourceWorkspaceCanInsertDraft()) return false;
            this.sourceWorkspaceSaving = true;
            this.sourceWorkspaceError = '';
            try {
                const inserted = await this.insertSourceWorkspaceDraft(this.sourceWorkspaceDraft, this.sourceWorkspaceDestination);
                if (!inserted) throw new Error('The reviewed draft could not be inserted. Choose an available destination and try again.');
                this.sourceWorkspaceDraftInserted = true;
                this.sourceWorkspaceNotice = 'Reviewed text and its source citations were added to the paper.';
                return true;
            } catch (error) { this.sourceWorkspaceError = error.message; return false; }
            finally { this.sourceWorkspaceSaving = false; }
        },
        setSourceWorkspaceAddMode(mode) {
            if (this.sourceWorkspaceBusy()) return;
            this.sourceWorkspaceAddMode = mode;
            this.sourceWorkspaceSourceForm = emptySourceForm();
            this.sourceWorkspaceImports = [];
            this.sourceWorkspaceImportIndex = 0;
            this.sourceWorkspacePendingFile = null;
            this.sourceWorkspacePendingLinkedSource = null;
            this.sourceWorkspaceError = '';
            this.sourceWorkspaceNotice = '';
        },
        async lookupSourceWorkspaceIdentifier() {
            if (this.sourceWorkspaceBusy() || !this.sourceWorkspaceIdentifier.trim()) return false;
            const identifier = this.sourceWorkspaceIdentifier.trim();
            const doi = identifier.replace(/^https?:\/\/(?:dx\.)?doi\.org\//i, '').replace(/^doi:\s*/i, '');
            if (!/^10\.\d{4,9}\/\S+$/i.test(doi)) {
                const url = sourceWorkspaceSafeUrl(identifier);
                if (!url) { this.sourceWorkspaceError = 'Enter a DOI or a complete HTTP or HTTPS publication URL.'; return false; }
                this.sourceWorkspaceSourceForm = { ...emptySourceForm(), url };
                this.sourceWorkspaceNotice = 'Enter the publication title and details from this URL before saving.';
                return true;
            }
            this.sourceWorkspaceLoading = true;
            this.sourceWorkspaceError = '';
            try {
                const payload = await this.sourceWorkspaceRequest(config.metadataUrl, { method: 'POST', body: JSON.stringify({ identifier: doi }) });
                if (!payload.source?.title) throw new Error('No publication metadata was returned. Enter its details manually.');
                this.sourceWorkspaceSourceForm = { ...emptySourceForm(), ...payload.source, authors: sourceAuthors(payload.source.authors), year: sourceText(payload.source.year) };
                this.sourceWorkspaceNotice = sourceText(payload.notice) || 'Review the publication details before saving.';
                return true;
            } catch (error) { this.sourceWorkspaceError = error.message; return false; }
            finally { this.sourceWorkspaceLoading = false; }
        },
        parseSourceWorkspaceImport() {
            if (this.sourceWorkspaceBusy()) return false;
            this.sourceWorkspaceImports = parseSourceImport(this.sourceWorkspaceImportText, this.sourceWorkspaceAddMode);
            this.sourceWorkspaceImportIndex = 0;
            this.sourceWorkspaceError = this.sourceWorkspaceImports.length ? '' : 'No publication titles were found. Check the import format or add the source manually.';
            if (this.sourceWorkspaceImports.length) {
                this.sourceWorkspaceSourceForm = { ...this.sourceWorkspaceImports[0] };
                this.sourceWorkspaceNotice = `${this.sourceWorkspaceImports.length} publication entries read. Review and save each source.`;
            }
            return this.sourceWorkspaceImports.length > 0;
        },
        selectSourceWorkspaceImport() { this.sourceWorkspaceSourceForm = { ...this.sourceWorkspaceImports[Number(this.sourceWorkspaceImportIndex)] }; },
        async readSourceWorkspaceImportFile(event) {
            const file = event?.target?.files?.[0];
            if (!file || file.size > 1000000) { this.sourceWorkspaceError = 'Choose a BibTeX or RIS text file under 1 MB.'; return false; }
            this.sourceWorkspaceImportText = await file.text();
            return this.parseSourceWorkspaceImport();
        },
        chooseSourceWorkspacePdf(event) {
            const file = event?.target?.files?.[0];
            if (!file || !/\.pdf$/i.test(file.name) || file.size > 8 * 1024 * 1024) { this.sourceWorkspaceError = 'Choose a PDF under 8 MB.'; return false; }
            this.sourceWorkspacePendingFile = file;
            this.sourceWorkspaceSourceForm = { ...emptySourceForm(), title: file.name.replace(/\.pdf$/i, '') };
            this.sourceWorkspaceError = '';
            this.sourceWorkspaceNotice = 'Review the publication title and authors, then save the source and attach its PDF.';
            return true;
        },
        async sourceWorkspaceUploadDocument(sourceId, file) {
            const body = new FormData();
            body.append('file', file);
            const payload = await this.sourceWorkspaceRequest(this.sourceWorkspaceEndpoint(sourceId, 'document'), { method: 'POST', body, multipart: true });
            this.sourceWorkspaceAcceptEvidence(sourceId, payload);
            return payload;
        },
        async uploadSourceWorkspaceReaderPdf(event) {
            const file = event?.target?.files?.[0];
            if (this.sourceWorkspaceBusy() || !file) return false;
            if (!/\.pdf$/i.test(file.name) || file.size > 8 * 1024 * 1024) { this.sourceWorkspaceError = 'Choose a PDF under 8 MB.'; return false; }
            this.sourceWorkspaceSaving = true;
            this.sourceWorkspaceError = '';
            try {
                await this.sourceWorkspaceUploadDocument(this.sourceWorkspaceActiveSourceId, file);
                this.sourceWorkspacePage = 0;
                this.sourceWorkspaceNotice = 'PDF attached. Read the extracted pages or open the original document.';
                return true;
            } catch (error) { this.sourceWorkspaceError = error.message; return false; }
            finally { this.sourceWorkspaceSaving = false; }
        },
        async saveSourceWorkspaceMetadata() {
            if (this.sourceWorkspaceBusy() || !this.sourceWorkspaceSourceForm.title.trim()) return false;
            const form = this.sourceWorkspaceSourceForm;
            if (form.url && !sourceWorkspaceSafeUrl(form.url)) { this.sourceWorkspaceError = 'Use a complete HTTP or HTTPS source URL.'; return false; }
            this.sourceWorkspaceSaving = true;
            this.sourceWorkspaceError = '';
            try {
                let linked = this.sourceWorkspacePendingLinkedSource;
                if (!linked) {
                    const metadata = { ...form, title: form.title.trim(), authors: sourceAuthors(form.authors), year: form.year ? Number(form.year) : null, source: form.source || 'Manual entry', url: form.url ? sourceWorkspaceSafeUrl(form.url) : null };
                    const payload = await this.sourceWorkspaceRequest(config.storeUrl, { method: 'POST', body: JSON.stringify(metadata) });
                    if (!payload.source?.id) throw new Error('The source metadata could not be saved.');
                    linked = await this.sourceWorkspaceLinkLibrary(payload.source);
                    if (this.sourceWorkspacePendingFile) this.sourceWorkspacePendingLinkedSource = linked;
                }
                if (this.sourceWorkspacePendingFile) {
                    await this.sourceWorkspaceUploadDocument(linked.id, this.sourceWorkspacePendingFile);
                    this.sourceWorkspacePendingFile = null;
                    this.sourceWorkspacePendingLinkedSource = null;
                }
                this.sourceWorkspaceNotice = 'Source saved and linked to this paper.';
                const imported = this.sourceWorkspaceImports[Number(this.sourceWorkspaceImportIndex)];
                if (imported) imported._saved = true;
                return linked;
            } catch (error) { this.sourceWorkspaceError = error.message; return false; }
            finally { this.sourceWorkspaceSaving = false; }
        },
    };
}
