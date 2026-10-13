function venueUrl(value) {
    try {
        const url = new URL(String(value || ''));
        return ['http:', 'https:'].includes(url.protocol) && !url.username && !url.password ? url.href : '';
    } catch {
        return '';
    }
}

export function disseminationAction(message, action = null) {
    if (action) return ['search_journals', 'search_conferences'].includes(action.type) ? action : null;
    const text = String(message || '').trim().split('\n')[0];
    if (/\bjournal\s+(articles?|papers?)\b/i.test(text)
        || !/^(?:(?:please|can you|could you|would you|help me|i want to|i'd like to)\s+)*(?:find|search|recommend|suggest|shortlist|add|look for)\b/i.test(text)) return null;
    const type = /\bconferences?\b/i.test(text) ? 'search_conferences' : /\bjournals?\b/i.test(text) ? 'search_journals' : null;
    if (!type) return null;
    const subject = text.match(/\b(?:journals?|conferences?)\b\s+(?:for|about|on|related to)\s+(.+?)[?.!]*$/i)?.[1] || '';
    const query = /^(?:(?:my|our|this|the|current|selected)\s+)?(?:project|proposal|research|study|paper|manuscript)(?:\s|$)/i.test(subject)
        ? '' : subject.trim();
    return { type, query };
}

export function disseminationSearchRequest(action, project) {
    if (!project?.dissemination) return null;
    const journal = action.type === 'search_journals';
    const url = project.dissemination[journal ? 'journal_search_url' : 'conference_search_url'];
    if (!url) return null;
    const query = String(action.query || project.label).trim().slice(0, journal ? 500 : 140);
    return { type: action.type, project, url, body: journal ? { query, indexing: 'prefer_scopus' } : { query, open_only: true } };
}

export function disseminationSearchReply(payload, search) {
    if (!Array.isArray(payload.results)) throw new Error('The venue search returned an incomplete response. Please retry.');
    const journal = search.type === 'search_journals';
    const results = (Array.isArray(payload.results) ? payload.results : []).slice(0, 5).map((result, index) => {
        const title = String(journal ? result.name : result.title);
        const url = venueUrl(journal ? result.homepage_url || result.openalex_url : result.url);
        return {
            id: index, title, url, saved: false,
            description: journal ? (result.reasons || []).join(' ') : String(result.description || ''),
            detail: journal
                ? `${result.fit_score ?? '—'}/100 topic fit · ${result.scopus?.label || 'Indexing unverified'}`
                : `${result.location || 'Location unlisted'} · Deadline: ${result.submission_deadline || 'Verify on CFP'} · ${result.deadline_status || 'unknown'}`,
            fields: journal ? {
                journal_name: title, issn: result.issn || null,
                journal_url: venueUrl(result.homepage_url) || null, status: 'shortlisted',
            } : {
                title, url, candidate_key: result.candidate_key,
                location: result.location || null, submission_deadline: result.submission_deadline || null,
                event_date: result.event_starts_on || null, status: 'shortlisted',
            },
        };
    });
    return {
        reply: [
            results.length
                ? `I found ${results.length} ${journal ? 'journal matches' : 'conference opportunities'} for “${search.body.query}”. Review a result, then confirm before adding it to “${search.project.label}”.`
                : `No ${journal ? 'journals' : 'open conference opportunities'} matched “${search.body.query}”. Try more specific subject terms.`,
            ...results.map((result, index) => `${index + 1}. ${result.title} — ${result.detail}`),
            ...(payload.warnings || []),
            'Check the official venue website for current scope, fees, indexing, and deadlines. Adding a shortlist entry does not submit your manuscript.',
        ].join('\n\n'),
        model: 'athena-dissemination-search', sources: [],
        // shortcut: result cards are session-only; persist the packet if saved-chat result actions are needed.
        dissemination: {
            type: journal ? 'journal' : 'conference', project_id: search.project.id, project_title: search.project.label,
            project_url: search.project.dissemination[journal ? 'journal_project_url' : 'conference_project_url'],
            save_url: search.project.dissemination[journal ? 'journal_save_url' : 'conference_save_url'],
            manuscript_title: search.project.label, results, selected_index: null, error: '',
        },
    };
}

export function researchAssistantDissemination() {
    return {
        disseminationSaving: false,

        disseminationStarterPrompts() {
            if (!this.contextEnabled || !this.selectedContext()?.dissemination) return [];
            return [
                { label: 'Find journals', description: 'Compare journal matches and confirm a project shortlist entry.', evidence: 'Uses live journal search results', prompt: 'Find journals for my project.', action: { type: 'search_journals' } },
                { label: 'Find conferences', description: 'Check open calls for papers and save a selected conference.', evidence: 'Uses public conference listings', prompt: 'Find conferences for my project.', action: { type: 'search_conferences' } },
            ];
        },

        focusDisseminationProjectPicker() {
            const picker = [...document.querySelectorAll('[data-assistant-project-picker]')].find((element) => element.offsetParent !== null);
            picker?.querySelector('button')?.click();
        },

        selectedDisseminationResult(message) {
            const packet = message?.dissemination;
            return Number.isInteger(packet?.selected_index) ? packet.results[packet.selected_index] || null : null;
        },

        selectDisseminationResult(message, index) {
            const packet = message?.dissemination;
            if (!packet?.results[index] || packet.results[index].saved || this.disseminationSaving || this.isLoading) return false;
            packet.selected_index = index;
            packet.error = '';
            return true;
        },

        async confirmDisseminationResult(message) {
            const packet = message?.dissemination;
            const result = this.selectedDisseminationResult(message);
            if (!result || result.saved || !packet.save_url || this.disseminationSaving || this.isLoading || this.retryAfter > 0) return false;
            const manuscript = String(packet.manuscript_title || '').trim();
            if (packet.type === 'journal' && (!manuscript || manuscript.length > 500)) {
                packet.error = 'Enter a manuscript title of up to 500 characters.';
                return false;
            }
            const token = document.querySelector('meta[name="csrf-token"]')?.content;
            if (!token) {
                packet.error = 'Refresh the page before saving; your security token is unavailable.';
                return false;
            }
            this.disseminationSaving = true;
            packet.error = '';
            const conversationMessages = this.messages;
            try {
                const response = await fetch(packet.save_url, {
                    method: 'POST', credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                    body: JSON.stringify({ ...result.fields, ...(packet.type === 'journal' ? { manuscript_title: manuscript } : {}) }),
                });
                const payload = await response.json().catch(() => ({}));
                if (!response.ok || !Number.isInteger(payload.id) || payload.id < 1) {
                    throw new Error(Object.values(payload.errors || {}).flat()[0] || payload.message || 'The shortlist entry could not be saved. Refresh and try again.');
                }
                result.saved = true;
                packet.selected_index = null;
                if (this.messages === conversationMessages) {
                    this.messages.push({ id: this.nextMessageId++, role: 'assistant', content: `“${result.title}” is saved in the shortlist for “${packet.project_title}”. No manuscript was submitted.`, sources: [] });
                    this.scrollToLatest();
                    await this.saveConversation();
                }
                return true;
            } catch (error) {
                packet.error = error.message || 'A network error interrupted the save. Check the project shortlist before retrying.';
                return false;
            } finally {
                this.disseminationSaving = false;
            }
        },
    };
}
