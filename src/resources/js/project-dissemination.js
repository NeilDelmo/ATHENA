export function projectDissemination(config) {
    return {
        endpoints: config.endpoints,
        profile: config.profile || null,
        query: config.query || '',
        scope: '',
        openOnly: true,
        conferenceResults: [],
        conferenceSearched: false,
        authorQuery: config.authorName || '',
        institution: '',
        scholarUrl: config.profile?.scholar_url || '',
        authors: [],
        authorSearched: false,
        selectedAuthor: null,
        authorConfirmed: false,
        papers: [],
        samplePapers: [],
        papersSearched: false,
        page: 1,
        hasMore: false,
        doi: '',
        lookupDoi: null,
        busy: '',
        error: '',
        message: '',
        tab: config.initialTab || (window.location.hash === '#publications' ? 'publications' : 'conferences'),
        conferenceDraft: config.conferenceDraft,
        async request(action, data = {}) {
            const response = await fetch(this.endpoints[action], {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify(data),
            });
            const payload = await response.json().catch(() => ({}));
            if (!response.ok) {
                throw new Error(Object.values(payload.errors || {}).flat().join(' ') || payload.message || 'The request could not be completed. Reload the page and try again.');
            }
            return payload;
        },
        async run(action, callback) {
            if (this.busy) return;
            this.busy = action;
            this.error = '';
            this.message = '';
            try {
                await callback();
            } catch (error) {
                this.error = error.message || 'The request could not be completed.';
            } finally {
                this.busy = '';
            }
        },
        searchConferences() {
            return this.run('conferences', async () => {
                this.conferenceResults = [];
                this.conferenceSearched = false;
                const data = await this.request('conferenceSearch', { query: this.query, scope: this.scope || null, open_only: this.openOnly });
                this.conferenceResults = data.results;
                this.conferenceSearched = true;
            });
        },
        chooseConference(item) {
            this.conferenceDraft = {
                ...this.conferenceDraft,
                candidate_key: item.candidate_key,
                title: item.title,
                url: item.url,
                official_url: '',
                location: item.location || '',
                submission_deadline: item.submission_deadline || '',
                event_date: item.event_starts_on || '',
                attendance_mode: item.scope === 'online' ? 'online' : '',
                fees: '',
                publication_details: '',
            };
            this.$nextTick(() => this.$refs.conferenceForm.scrollIntoView({ behavior: 'smooth', block: 'center' }));
        },
        searchAuthors() {
            return this.run('authors', async () => {
                this.authors = [];
                this.selectedAuthor = null;
                this.authorSearched = false;
                const data = await this.request('authors', { query: this.authorQuery, institution: this.institution || null });
                this.authors = data.results;
                this.authorSearched = true;
            });
        },
        previewAuthor(author) {
            return this.run('preview', async () => {
                this.selectedAuthor = author;
                this.authorConfirmed = false;
                this.samplePapers = [];
                const data = await this.request('papers', { author_id: author.id, page: 1 });
                this.samplePapers = data.results.slice(0, 5);
            });
        },
        confirmAuthor() {
            if (!this.authorConfirmed || !this.selectedAuthor) return;
            return this.run('profile', async () => {
                const data = await this.request('profile', { author_id: this.selectedAuthor.id, confirmed: true, scholar_url: this.scholarUrl || null });
                this.profile = data.profile;
                this.selectedAuthor = null;
                this.authors = [];
                this.papers = [];
                this.papersSearched = false;
                this.message = 'Author profile linked. Select Find my papers to review publications.';
            });
        },
        saveScholar() {
            return this.run('scholar', async () => {
                const data = await this.request('profile', { scholar_url: this.scholarUrl || null });
                this.profile = data.profile;
                this.message = 'Google Scholar profile link saved.';
            });
        },
        loadPapers(page = 1) {
            return this.run('papers', async () => {
                const data = await this.request('papers', { page });
                this.papers = data.results;
                this.page = data.page;
                this.hasMore = data.has_more;
                this.papersSearched = true;
                this.lookupDoi = null;
            });
        },
        lookupPaper() {
            return this.run('doi', async () => {
                this.papers = [];
                this.papersSearched = false;
                const data = await this.request('doi', { doi: this.doi });
                this.papers = [data.paper];
                this.lookupDoi = data.paper.doi;
                this.papersSearched = true;
                this.hasMore = false;
                this.page = 1;
            });
        },
        importPaper(paper, confirmed) {
            if (!confirmed) return;
            return this.run('import', async () => {
                const data = await this.request('import', { work_id: paper.openalex_id, confirmed: true, lookup_doi: this.lookupDoi });
                paper.saved = true;
                this.message = data.message + ' Refresh this page to see the updated project list.';
            });
        },
    };
}
