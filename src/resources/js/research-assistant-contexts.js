export function researchAssistantContexts(fetchContexts = (...args) => fetch(...args)) {
    const scope = () => {
        const data = document.body.dataset;
        return [data.authUserId, data.activeWorkspace, data.researchAssistantContextsUrl].join(':');
    };

    return {
        contextOptionsLoaded: false,
        contextOptionsLoading: false,
        contextOptionsError: '',
        contextOptionsLoadPromise: null,
        contextOptionsVersion: 0,

        resetContextOptions() {
            this.contextOptionsVersion++;
            this.contextOptionsLoaded = false;
            this.contextOptionsLoading = false;
            this.contextOptionsError = '';
            this.contextOptionsLoadPromise = null;
        },

        async loadContextOptions() {
            const endpoint = document.body.dataset.researchAssistantContextsUrl;
            if (!endpoint || this.contextOptionsLoaded) return true;
            if (this.contextOptionsLoadPromise) return this.contextOptionsLoadPromise;

            const version = ++this.contextOptionsVersion;
            const requestScope = scope();
            this.contextOptionsLoading = true;
            this.contextOptionsError = '';
            this.contextOptionsLoadPromise = Promise.resolve().then(async () => {
                try {
                    const url = new URL(endpoint, window.location.href);
                    if (url.origin !== window.location.origin) throw new Error('Invalid project endpoint.');
                    const activeTopic = Number(window.athenaResearchAssistantActiveContextId || 0);
                    if (activeTopic > 0) url.searchParams.set('active_topic_id', activeTopic);
                    const response = await fetchContexts(url.href, {
                        credentials: 'same-origin', headers: { Accept: 'application/json' }, cache: 'no-store',
                    });
                    if (!response.ok) throw new Error('Projects could not be loaded.');
                    const payload = await response.json();
                    if (version !== this.contextOptionsVersion || requestScope !== scope()) return false;
                    if (!Array.isArray(payload.contexts)
                        || String(payload.account_id) !== document.body.dataset.authUserId
                        || payload.workspace !== document.body.dataset.activeWorkspace) {
                        throw new Error('The account or workspace changed.');
                    }

                    this.contextOptions = payload.contexts;
                    if (!this.selectedContextId) this.selectedContextId = Number(payload.contexts[0]?.id || 0);
                    else if (!payload.contexts.some(context => Number(context.id) === Number(this.selectedContextId)) && !this.reviewContext?.topic_id) {
                        this.selectedContextId = 0;
                        this.contextEnabled = false;
                    }
                    this.contextOptionsLoaded = true;
                    return true;
                } catch {
                    if (version === this.contextOptionsVersion && requestScope === scope()) {
                        this.contextOptionsError = 'Projects could not be loaded. Try again.';
                    }
                    return false;
                } finally {
                    if (version === this.contextOptionsVersion) {
                        this.contextOptionsLoading = false;
                        this.contextOptionsLoadPromise = null;
                    }
                }
            });
            return this.contextOptionsLoadPromise;
        },
    };
}
