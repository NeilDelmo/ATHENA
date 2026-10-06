export function researchAssistantHistory(initialHistory = [], fetchHistory = (...args) => fetch(...args)) {
    return {
        history: initialHistory,
        historyLoaded: initialHistory.length > 0,
        historyLoading: false,
        historyLoadPromise: null,
        historyLoadError: '',

        async loadHistory() {
            if (this.historyLoaded) return this.history;
            if (this.historyLoadPromise) return this.historyLoadPromise;
            const url = this.historyUrl();
            if (!url) return this.history;

            this.historyLoading = true;
            this.historyLoadError = '';
            this.historyLoadPromise = (async () => {
                try {
                    const response = await fetchHistory(url, {
                        credentials: 'same-origin',
                        headers: { Accept: 'application/json' },
                    });
                    if (!response.ok) throw new Error('History could not be loaded.');
                    const payload = await response.json();
                    if (!Array.isArray(payload.conversations)) throw new Error('History could not be loaded.');

                    const currentIds = new Set(this.history.map((item) => Number(item.id)));
                    this.history = [
                        ...this.history,
                        ...payload.conversations.filter((item) => !currentIds.has(Number(item.id))),
                    ].sort((first, second) => String(second.updated_at || '').localeCompare(String(first.updated_at || '')));
                    this.historyLoaded = true;
                    if (!this.historySearchQuery.trim()) this.historySearchResults = this.history;
                } catch {
                    this.historyLoadError = 'History could not be loaded. Open history again to retry.';
                } finally {
                    this.historyLoading = false;
                    this.historyLoadPromise = null;
                }

                return this.history;
            })();

            return this.historyLoadPromise;
        },
    };
}
