export function researchAssistantHistory(initialHistory = [], fetchHistory = (...args) => fetch(...args), browser = globalThis) {
    return {
        history: initialHistory,
        historyLoaded: initialHistory.length > 0,
        historyLoading: false,
        historyLoadPromise: null,
        historyLoadError: '',
        historyLoadSequence: 0,
        conversationAccountScope: null,
        conversationPageScope: null,

        conversationReady: false,
        conversationFresh: false,
        conversationRestorePromise: null,
        conversationRestoreSequence: 0,
        conversationSwitchSequence: 0,
        historyRestoring: false,
        conversationNotice: '',

        conversationScope(context = this.contextPayload(false) || {}) {
            if (context.report_type) return ['report', context.report_type, context.report_id || '', context.topic_id || '', context.source_key || ''].join(':');
            if (Number(context.proposal_draft_id) > 0) return `draft:${Number(context.proposal_draft_id)}`;
            return Number(context.topic_id) > 0 ? `topic:${Number(context.topic_id)}` : 'general';
        },

        conversationStoragePrefix() {
            const data = browser.document?.body?.dataset;
            return data?.authUserId ? `athena:chat:${data.authUserId}:${data.activeWorkspace || ''}` : '';
        },

        retainedConversation(scope = this.conversationScope()) {
            try {
                const prefix = this.conversationStoragePrefix();
                const entry = prefix ? JSON.parse(browser.sessionStorage.getItem(`${prefix}:active:${scope}`) || 'null') : null;
                return entry && typeof entry === 'object' && !Array.isArray(entry) ? entry : null;
            } catch { return null; }
        },

        rememberConversation() {
            if (!this.conversationReady || this.historyRestoring
                || (this.conversationAccountScope && this.conversationAccountScope !== this.conversationStoragePrefix())) return;
            try {
                const prefix = this.conversationStoragePrefix();
                if (!prefix) return;
                const scope = this.conversationScope();
                const view = this.workspaceOpen ? 'workspace' : (this.drawerOpen ? 'drawer' : 'closed');
                const entry = { id: this.currentConversationId || null, fresh: this.conversationFresh, view };
                browser.sessionStorage.setItem(`${prefix}:active:${scope}`, JSON.stringify(entry));
                if (scope !== 'general' && (entry.id || this.conversationPageScope === 'general')) {
                    browser.sessionStorage.setItem(`${prefix}:active:general`, JSON.stringify({ ...entry,
                        context: this.contextPayload(false), label: this.conversationContextLabel,
                        view: this.conversationPageScope === 'general' ? view : (this.retainedConversation('general')?.view || 'closed'),
                    }));
                }
                const pending = this.messages.at(-1)?.role === 'user';
                browser.sessionStorage.setItem(`${prefix}:draft:${entry.id || `new:${scope}`}`, JSON.stringify({
                    text: this.draft.slice(0, 8000),
                    action: pending ? this.retryAction : null,
                    context: pending ? this.retryContext : null,
                }));
            } catch { /* Saved messages remain available from the server when browser storage is unavailable. */ }
        },

        restoreConversationView() {
            const view = this.retainedConversation()?.view;
            this.workspaceOpen = view === 'workspace';
            this.drawerOpen = view === 'drawer';
            this.syncPageScroll?.();
        },

        restoreConversationDraft() {
            let entry = null;
            try {
                const prefix = this.conversationStoragePrefix();
                if (prefix) entry = JSON.parse(browser.sessionStorage.getItem(`${prefix}:draft:${this.currentConversationId || `new:${this.conversationScope()}`}`) || 'null');
            } catch { /* Chat restoration does not require browser storage. */ }
            this.draft = typeof entry?.text === 'string' ? entry.text.slice(0, 8000) : '';
            this.retryAction = null;
            this.retryContext = null;
            if (this.messages.at(-1)?.role === 'user') {
                this.retryAction = entry?.action && typeof entry.action === 'object' ? entry.action : null;
                this.retryContext = entry?.context && typeof entry.context === 'object' ? entry.context : null;
                this.setError('Response interrupted', 'Your question is saved. Choose Retry to try again.');
            }
            this.resetComposers?.();
        },

        async restoreConversation(force = false) {
            const accountScope = this.conversationStoragePrefix();
            if (this.conversationAccountScope && this.conversationAccountScope !== accountScope) return false;
            this.conversationAccountScope = accountScope;
            if (this.conversationReady && !force) return true;
            if (this.conversationRestorePromise) return this.conversationRestorePromise;
            if (!this.historyUrl()) { this.conversationReady = true; return true; }
            const scope = this.conversationScope();
            const prefix = this.conversationStoragePrefix();
            const selection = this.retainedConversation(scope);
            if (selection?.fresh && !selection.id) {
                if (scope === 'general' && selection.context) {
                    this.applyConversationContext(selection.context);
                    this.conversationContextLabel = typeof selection.label === 'string' ? selection.label.slice(0, 300) : '';
                }
                this.conversationFresh = true;
                this.restoreConversationDraft();
                this.conversationReady = true;
                return true;
            }
            const sequence = ++this.conversationRestoreSequence;
            this.historyRestoring = true;
            const request = (async () => {
                try {
                    const url = new URL(this.historyUrl(), browser.location.origin);
                    if (url.origin !== browser.location.origin) throw new Error('Invalid chat history endpoint.');
                    url.searchParams.set('resume', '1');
                    const context = this.contextPayload(false) || {};
                    for (const field of ['topic_id', 'proposal_draft_id', 'report_type', 'report_id', 'source_key']) {
                        if (context[field]) url.searchParams.set(`context[${field}]`, context[field]);
                    }
                    if (Number.isSafeInteger(Number(selection?.id)) && Number(selection.id) > 0) url.searchParams.set('conversation_id', selection.id);
                    const response = await fetchHistory(url.href, { credentials: 'same-origin', headers: { Accept: 'application/json' }, cache: 'no-store' });
                    const payload = await response.json().catch(() => ({}));
                    if (sequence !== this.conversationRestoreSequence || prefix !== this.conversationStoragePrefix() || scope !== this.conversationScope()) return false;
                    if (response.redirected || [401, 419].includes(response.status)) throw new Error('Your session has expired. Sign in again to reopen the saved chat.');
                    if (!response.ok || String(payload.account_id) !== browser.document.body.dataset.authUserId
                        || payload.workspace !== (browser.document.body.dataset.activeWorkspace || '')) throw new Error(payload.message || 'The saved chat could not be loaded.');
                    if (payload.conversation) {
                        this.hydrateConversation(payload.conversation, scope !== 'general');
                        this.upsertHistory?.(payload.conversation);
                    } else {
                        this.messages = [];
                        this.currentConversationId = null;
                        this.conversationVersion = null;
                    }
                    this.error = ''; this.errorTitle = '';
                    this.conversationFresh = false;
                    this.restoreConversationDraft();
                    this.conversationReady = true;
                    this.scrollToLatest?.();
                    return true;
                } catch (error) {
                    if (sequence === this.conversationRestoreSequence && prefix === this.conversationStoragePrefix()) {
                        this.setError('Saved chat unavailable', error instanceof Error ? error.message : 'The saved chat could not be loaded. Retry to restore it.');
                    }
                    return false;
                } finally {
                    if (sequence === this.conversationRestoreSequence) {
                        this.historyRestoring = false;
                        this.conversationRestorePromise = null;
                        this.rememberConversation();
                    }
                }
            })();
            this.conversationRestorePromise = request;
            return request;
        },

        async ensureConversationContext(context = null) {
            const sequence = ++this.conversationSwitchSequence;
            if (!await this.restoreConversation() || sequence !== this.conversationSwitchSequence) return false;
            if (!context || this.conversationScope(context) === this.conversationScope()) return true;
            this.rememberConversation();
            this.stop();
            const hadConversation = this.hasConversation();
            if (hadConversation && !await this.saveConversation()) return false;
            if (sequence !== this.conversationSwitchSequence) return false;
            this.clearConversation({ remember: false });
            this.applyConversationContext(context);
            this.conversationReady = this.conversationScope() === 'general';
            this.conversationFresh = this.conversationReady;
            if (this.conversationReady) { this.restoreConversationDraft(); this.rememberConversation(); return true; }
            return this.restoreConversation();
        },

        async loadHistory() {
            if (this.historyLoaded) return this.history;
            if (this.historyLoadPromise) return this.historyLoadPromise;
            const url = this.historyUrl();
            if (!url) return this.history;

            const sequence = ++this.historyLoadSequence;
            const prefix = this.conversationStoragePrefix();
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

                    if (sequence !== this.historyLoadSequence || prefix !== this.conversationStoragePrefix()) return this.history;
                    const currentIds = new Set(this.history.map((item) => Number(item.id)));
                    this.history = [
                        ...this.history,
                        ...payload.conversations.filter((item) => !currentIds.has(Number(item.id))),
                    ].sort((first, second) => String(second.updated_at || '').localeCompare(String(first.updated_at || '')));
                    this.historyLoaded = true;
                    if (!this.historySearchQuery.trim()) this.historySearchResults = this.history;
                } catch {
                    if (sequence === this.historyLoadSequence && prefix === this.conversationStoragePrefix()) this.historyLoadError = 'History could not be loaded. Open history again to retry.';
                } finally {
                    if (sequence === this.historyLoadSequence) {
                        this.historyLoading = false;
                        this.historyLoadPromise = null;
                    }
                }

                return this.history;
            })();

            return this.historyLoadPromise;
        },
    };
}
