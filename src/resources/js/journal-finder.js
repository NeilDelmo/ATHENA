function compactJournalText(value, maximumLength) {
    return String(value || '').replace(/\s+/g, ' ').trim().slice(0, maximumLength);
}

export function journalFinder(config = {}) {
    return {
        endpoint: config.endpoint,
        query: config.query || '',
        context: config.context || '',
        openAccessOnly: false,
        recentYears: 10,
        results: [],
        relatedArticles: 0,
        methodology: '',
        checkedAt: '',
        hasSearched: false,
        isLoading: false,
        error: '',

        async search() {
            const query = this.query.trim();
            this.error = '';
            this.hasSearched = true;

            if (query.length < 3) {
                this.results = [];
                this.error = 'Enter at least 3 characters describing the paper or research topic.';
                return;
            }

            if (!this.endpoint || this.isLoading) return;

            this.isLoading = true;
            this.results = [];

            try {
                const response = await fetch(this.endpoint, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({
                        query,
                        context: this.context.trim() || null,
                        open_access: this.openAccessOnly,
                        recent_years: Number(this.recentYears),
                    }),
                });
                const payload = await response.json().catch(() => ({}));

                if (!response.ok) {
                    if (response.status === 419) {
                        throw new Error('Your session expired. Refresh the page, then search again.');
                    }

                    const validationMessage = Object.values(payload.errors || {}).flat()[0];
                    throw new Error(validationMessage || payload.message || 'The journal search could not be completed.');
                }

                this.results = Array.isArray(payload.results) ? payload.results : [];
                this.relatedArticles = Number(payload.related_articles || 0);
                this.methodology = payload.methodology || '';
                this.checkedAt = payload.checked_at || '';
            } catch (error) {
                this.results = [];
                this.error = error.message || 'A network error interrupted the journal search.';
            } finally {
                this.isLoading = false;
            }
        },

        fitClasses(score) {
            if (score >= 75) return 'bg-emerald-50 text-emerald-800 ring-emerald-600/20 dark:bg-emerald-950/40 dark:text-emerald-200';
            if (score >= 55) return 'bg-blue-50 text-blue-800 ring-blue-600/20 dark:bg-blue-950/40 dark:text-blue-200';
            return 'bg-amber-50 text-amber-800 ring-amber-600/20 dark:bg-amber-950/40 dark:text-amber-200';
        },

        formatNumber(value) {
            return new Intl.NumberFormat().format(Number(value || 0));
        },

        askAthena() {
            if (!this.results.length) return;

            const assistant = window.Alpine?.store('researchAssistant');
            if (!assistant || assistant.isLoading) return;

            const journals = this.results.slice(0, 6).map((journal, index) => {
                const evidence = (journal.sample_articles || [])
                    .map((article) => `- ${compactJournalText(article.title, 180)} (${article.year || 'year unknown'})`)
                    .join('\n');

                return [
                    `${index + 1}. ${compactJournalText(journal.name, 180)} — ${journal.fit_score}% ${journal.fit_label}`,
                    `Publisher: ${compactJournalText(journal.publisher || 'not listed', 120)}; ISSN: ${journal.issn || 'not listed'}; open access: ${journal.is_open_access ? 'yes' : 'not confirmed'}`,
                    `Why recommended: ${compactJournalText((journal.reasons || []).join(' '), 500)}`,
                    evidence ? `Related evidence:\n${evidence}` : '',
                ].filter(Boolean).join('\n');
            }).join('\n\n');

            assistant.draft = [
                'Compare these evidence-based journal recommendations for my paper.',
                `Paper/topic: ${compactJournalText(this.query, 500)}`,
                this.context.trim() ? `Abstract/keywords: ${compactJournalText(this.context, 1200)}` : '',
                '',
                journals,
                '',
                'Recommend a shortlist and explain fit, possible tradeoffs, and what I must verify on each official journal website. Do not invent acceptance rates, fees, indexing, or submission requirements.',
            ].filter((line) => line !== '').join('\n');
            assistant.send();
        },

        clear() {
            this.query = '';
            this.context = '';
            this.openAccessOnly = false;
            this.recentYears = 10;
            this.results = [];
            this.relatedArticles = 0;
            this.methodology = '';
            this.checkedAt = '';
            this.hasSearched = false;
            this.error = '';
        },
    };
}
