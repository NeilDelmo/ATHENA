export function researchManualReader(config, openOnLoad = false) {
    let guidanceRequest;
    let destroyed = false;
    let hashChanged;
    let pageRequested;
    let stopChecks = false;

    return {
        manualPage: null,
        question: '',
        reply: '',
        sources: [],
        guidanceEdition: config.edition,
        guidanceLoading: false,
        guidanceError: '',
        guidanceChecks: [],
        suiteRunning: false,
        suiteProgress: '',

        async runGuidanceChecks() {
            const questions = (config.guidance_questions || []).slice(0, 5);
            if (this.suiteRunning || this.guidanceLoading || !config.guidance_url || !questions.length || destroyed) return;
            this.suiteRunning = true;
            stopChecks = false;
            try {
                for (const [index, question] of questions.entries()) {
                    if (destroyed || stopChecks) break;
                    this.suiteProgress = `Checking question ${index + 1} of ${questions.length}`;
                    await this.askGuidance(question);
                }
            } finally {
                if (!destroyed) {
                    this.suiteRunning = false;
                    this.suiteProgress = stopChecks ? 'Checks stopped. Completed answers remain available for review.' : 'Checks complete. Review each answer and its cited pages.';
                }
            }
        },

        stopGuidanceChecks() {
            stopChecks = true;
            guidanceRequest?.abort();
        },

        rememberGuidance(check) {
            this.guidanceChecks = [...this.guidanceChecks.filter(previous => previous.question !== check.question), check].slice(-10);
        },

        init() {
            hashChanged = () => this.openHashPage();
            pageRequested = (event) => {
                if (event.detail?.version === config.version) this.openPreview(event.detail.page);
            };
            window.addEventListener('hashchange', hashChanged);
            window.addEventListener('research-manual:page', pageRequested);
            this.$nextTick(() => {
                if (!this.openHashPage() && openOnLoad) this.openPreview();
            });
        },

        openHashPage() {
            if (config.read_hash === false) return false;
            const page = new URLSearchParams(window.location.hash.replace(/^#/, '')).get('page');
            if (page === null || !Number.isFinite(Number(page))) return false;
            this.openPreview(page);
            return true;
        },

        openPreview(page = null) {
            if (destroyed || (page !== null && !Number.isFinite(Number(page)))) return;
            this.manualPage = page === null ? null : Math.max(1, Math.min(config.page_count, Math.trunc(Number(page))));
            this.$dispatch('open-modal', 'research-manual-preview-' + config.version);
            if (this.manualPage !== null) this.$nextTick(() => this.$dispatch('pdf-preview-page', { url: config.pdf_url, page: this.manualPage }));
        },

        async askGuidance(question = this.question) {
            if (this.guidanceLoading || !config.guidance_url || !question.trim() || destroyed) return;
            this.question = question;
            this.guidanceLoading = true;
            this.guidanceError = '';
            this.reply = '';
            this.sources = [];
            guidanceRequest = new AbortController();

            try {
                const response = await fetch(config.guidance_url, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                    credentials: 'same-origin',
                    signal: guidanceRequest.signal,
                    body: JSON.stringify({ question: question.trim() }),
                });
                const result = await response.json();
                if (!response.ok) throw new Error(result.message || 'Guidance could not be checked. Please try again.');
                if (destroyed) return;
                if ((result.version && result.version !== config.version) || (result.sources || []).some(source => source.document_version && source.document_version !== config.version)) {
                    throw new Error('This answer belongs to another edition. Reload this edition and try again.');
                }
                this.reply = result.reply;
                this.sources = result.sources || [];
                this.guidanceEdition = result.edition;
                this.rememberGuidance({ question: question.trim(), reply: this.reply, sources: this.sources, edition: this.guidanceEdition, version: config.version, type: result.check_type || (this.sources.length ? 'ai_response' : 'no_evidence'), error: '' });
            } catch (error) {
                if (!destroyed && error.name !== 'AbortError') {
                    this.guidanceError = error.message || 'Guidance could not be checked. Please try again.';
                    this.rememberGuidance({ question: question.trim(), reply: '', sources: [], edition: config.edition, version: config.version, type: 'unavailable', error: this.guidanceError });
                }
            } finally {
                if (!destroyed) this.guidanceLoading = false;
            }
        },

        sourceLabel(source) {
            return `${source.section || source.title} · ${source.page_start === source.page_end ? `Page ${source.page_start}` : `Pages ${source.page_start}–${source.page_end}`}`;
        },

        destroy() {
            destroyed = true;
            window.removeEventListener('hashchange', hashChanged);
            window.removeEventListener('research-manual:page', pageRequested);
            guidanceRequest?.abort();
        },
    };
}
