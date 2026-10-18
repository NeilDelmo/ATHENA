export function researchManualReader(config, openOnLoad = false) {
    let guidanceRequest;
    let destroyed = false;
    let hashChanged;
    let pageRequested;

    return {
        manualPage: 1,
        question: '',
        reply: '',
        sources: [],
        guidanceEdition: config.edition,
        guidanceLoading: false,
        guidanceError: '',

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
            const page = new URLSearchParams(window.location.hash.replace(/^#/, '')).get('page');
            if (page === null || !Number.isFinite(Number(page))) return false;
            this.openPreview(page);
            return true;
        },

        openPreview(page = 1) {
            if (destroyed || !Number.isFinite(Number(page))) return;
            this.manualPage = Math.max(1, Math.min(config.page_count, Math.trunc(Number(page))));
            this.$dispatch('open-modal', 'research-manual-preview-' + config.version);
            this.$nextTick(() => this.$dispatch('pdf-preview-page', { url: config.pdf_url, page: this.manualPage }));
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
                this.reply = result.reply;
                this.sources = result.sources || [];
                this.guidanceEdition = result.edition;
            } catch (error) {
                if (!destroyed && error.name !== 'AbortError') this.guidanceError = error.message || 'Guidance could not be checked. Please try again.';
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
