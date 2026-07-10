
import Alpine from 'alpinejs';

window.Alpine = Alpine;

const themeStorageKey = 'athena-theme';
const legacyThemeKeys = ['athena-auth-theme', 'theme'];
const themeMediaQuery = window.matchMedia('(prefers-color-scheme: dark)');

function storedThemePreference() {
    try {
        const theme = localStorage.getItem(themeStorageKey);
        return ['dark', 'light'].includes(theme) ? theme : null;
    } catch {
        return null;
    }
}

function clearLegacyThemePreferences() {
    try {
        legacyThemeKeys.forEach((key) => localStorage.removeItem(key));
    } catch {
        // Local storage may be blocked; theme still follows the system default.
    }
}

function resolvedTheme() {
    return storedThemePreference() ?? (themeMediaQuery.matches ? 'dark' : 'light');
}

function applyTheme(theme = resolvedTheme()) {
    document.documentElement.classList.toggle('dark', theme === 'dark');
    document.documentElement.style.colorScheme = theme;
}

function updateThemeToggleLabels() {
    const isDark = document.documentElement.classList.contains('dark');

    document.querySelectorAll('[data-theme-toggle]').forEach((toggle) => {
        toggle.setAttribute('aria-pressed', String(isDark));
        toggle.setAttribute('title', isDark ? 'Use light theme' : 'Use dark theme');
    });
}

function initializeThemeToggles() {
    clearLegacyThemePreferences();
    applyTheme();

    document.querySelectorAll('[data-theme-toggle]').forEach((toggle) => {
        if (toggle.dataset.themeToggleReady === 'true') return;

        toggle.dataset.themeToggleReady = 'true';
        toggle.addEventListener('click', () => {
            const nextTheme = document.documentElement.classList.contains('dark') ? 'light' : 'dark';

            try {
                localStorage.setItem(themeStorageKey, nextTheme);
            } catch {
                // The visible theme can still switch even when it cannot persist.
            }

            clearLegacyThemePreferences();
            applyTheme(nextTheme);
            updateThemeToggleLabels();
        });
    });

    updateThemeToggleLabels();
}

themeMediaQuery.addEventListener('change', () => {
    if (storedThemePreference()) return;

    applyTheme();
    updateThemeToggleLabels();
});

initializeThemeToggles();
document.addEventListener('livewire:navigated', initializeThemeToggles);

const assistantContexts = Array.isArray(window.athenaResearchAssistantContexts)
    ? window.athenaResearchAssistantContexts
    : [];
const activeAssistantContextId = window.athenaResearchAssistantActiveContextId ?? null;
const defaultAssistantContextId = assistantContexts.some((context) => Number(context.id) === Number(activeAssistantContextId))
    ? Number(activeAssistantContextId)
    : Number(assistantContexts[0]?.id || 0);
const researchAssistantPromptGroups = [
    {
        key: 'planning',
        label: 'Planning',
        prompts: [
            'Refine my research question',
            'Turn my topic into SMART objectives',
            'Outline my proposal',
            'Suggest keywords for literature search',
        ],
    },
    {
        key: 'methods',
        label: 'Methods',
        prompts: [
            'Recommend a methodology',
            'Identify variables and indicators',
            'Draft a data collection plan',
            'Spot ethical considerations',
        ],
    },
    {
        key: 'revision',
        label: 'Revision',
        prompts: [
            'Summarize reviewer comments into a revision plan',
            'Draft a response to evaluator comments',
            'Rewrite this section for clarity',
            'Check if my objectives match my methods',
        ],
    },
    {
        key: 'writing',
        label: 'Writing',
        prompts: [
            'Draft significance of the study',
            'Improve my abstract',
            'Make my title more specific',
            'Create a chapter outline',
        ],
    },
];

Alpine.store('researchAssistant', {
    drawerOpen: false,
    draft: '',
    isLoading: false,
    error: '',
    errorTitle: '',
    retryAfter: 0,
    abortController: null,
    copiedMessageId: null,
    copiedConversation: false,
    nextMessageId: 2,
    contextOptions: assistantContexts,
    contextEnabled: Boolean(activeAssistantContextId),
    selectedContextId: defaultAssistantContextId,
    activePromptGroup: researchAssistantPromptGroups[0].key,
    promptGroups: researchAssistantPromptGroups,
    quickPrompts: researchAssistantPromptGroups.flatMap((group) => group.prompts),
    messages: [
        {
            id: 1,
            role: 'assistant',
            content: 'Hi! I am Athena, your research support assistant. Tell me what you are studying, or choose a prompt below to get started.',
            context: false,
        },
    ],

    openDrawer() {
        this.drawerOpen = true;
        document.documentElement.classList.add('overflow-hidden');
        window.setTimeout(() => document.getElementById('research-assistant-drawer-message')?.focus(), 220);
    },

    closeDrawer() {
        this.drawerOpen = false;
        document.documentElement.classList.remove('overflow-hidden');
    },

    openWithContext(contextId) {
        const normalizedId = Number(contextId);

        if (this.contextOptions.some((context) => Number(context.id) === normalizedId)) {
            this.selectedContextId = normalizedId;
            this.contextEnabled = true;
        }

        this.openDrawer();
    },

    activePromptGroupData() {
        return this.promptGroups.find((group) => group.key === this.activePromptGroup) || this.promptGroups[0];
    },

    activePrompts() {
        return this.activePromptGroupData()?.prompts || [];
    },

    setPromptGroup(groupKey) {
        if (this.promptGroups.some((group) => group.key === groupKey)) {
            this.activePromptGroup = groupKey;
        }
    },

    usePrompt(prompt) {
        this.draft = prompt;

        window.setTimeout(() => {
            const input = this.drawerOpen
                ? document.getElementById('research-assistant-drawer-message')
                : document.getElementById('research-assistant-message');
            input?.focus();
        });
    },

    hasContextOptions() {
        return this.contextOptions.length > 0;
    },

    selectedContext() {
        return this.contextOptions.find((context) => Number(context.id) === Number(this.selectedContextId)) || null;
    },

    contextPayload() {
        if (!this.contextEnabled || !this.selectedContextId) return null;

        return {
            topic_id: Number(this.selectedContextId),
        };
    },

    setError(title, message) {
        this.errorTitle = title;
        this.error = message;
    },

    async send() {
        const content = this.draft.trim();
        if (!content || this.isLoading || this.retryAfter > 0) return;

        this.messages.push({ id: this.nextMessageId++, role: 'user', content });
        this.draft = '';
        this.error = '';
        this.errorTitle = '';
        this.scrollToLatest();

        await this.requestReply();
    },

    async requestReply() {
        const chatUrl = document.body.dataset.researchAssistantUrl;
        if (!chatUrl || this.isLoading) return;

        this.isLoading = true;
        this.error = '';
        this.abortController = new AbortController();
        this.scrollToLatest();

        try {
            const response = await fetch(chatUrl, {
                method: 'POST',
                credentials: 'same-origin',
                signal: this.abortController.signal,
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({
                    messages: this.contextMessages(),
                    context: this.contextPayload(),
                }),
            });

            const payload = await response.json().catch(() => ({}));

            if (!response.ok) {
                if (response.status === 419) {
                    this.setError('Session expired', 'Refresh the page, then send your message again.');
                    return;
                }

                if (response.status === 429) {
                    this.startRetryCountdown(Number(payload.retry_after) || 10);
                    this.setError('Too many requests', payload.message || 'Athena is receiving too many requests. Wait a moment, then retry.');
                    return;
                }

                if (response.status === 503) {
                    this.setError('Assistant unavailable', payload.message || 'Athena AI is not configured or cannot be reached right now.');
                    return;
                }

                if (response.status === 403) {
                    this.setError('Context unavailable', payload.message || 'That proposal context is not available for your account.');
                    return;
                }

                if (response.status === 422) {
                    this.setError('Check your message', payload.message || 'Athena needs a valid message before it can respond.');
                    return;
                }

                this.setError('Athena could not respond', payload.message || 'Please try again.');
                return;
            }

            this.messages.push({
                id: this.nextMessageId++,
                role: 'assistant',
                content: payload.reply,
                model: payload.model,
            });
            this.copiedConversation = false;
        } catch (error) {
            if (error.name !== 'AbortError') {
                this.setError('Network interrupted', error.message || 'A network error interrupted the request.');
            }
        } finally {
            this.isLoading = false;
            this.abortController = null;
            this.scrollToLatest();
        }
    },

    contextMessages() {
        return this.messages
            .filter((message) => ['user', 'assistant'].includes(message.role) && message.context !== false)
            .slice(-8)
            .map(({ role, content }) => ({ role, content }));
    },

    async retry() {
        if (this.isLoading || this.retryAfter > 0 || !this.contextMessages().length) return;
        await this.requestReply();
    },

    stop() {
        this.abortController?.abort();
        this.abortController = null;
        this.isLoading = false;
    },

    startRetryCountdown(seconds) {
        this.retryAfter = Math.max(1, seconds);

        const timer = window.setInterval(() => {
            this.retryAfter -= 1;
            if (this.retryAfter <= 0) window.clearInterval(timer);
        }, 1000);
    },

    async copyMessage(message) {
        try {
            await navigator.clipboard.writeText(message.content);
            this.copiedMessageId = message.id;
            window.setTimeout(() => {
                if (this.copiedMessageId === message.id) this.copiedMessageId = null;
            }, 1500);
        } catch {
            this.setError('Copy failed', 'The response could not be copied automatically.');
        }
    },

    transcriptText() {
        const selectedContext = this.contextEnabled ? this.selectedContext() : null;
        const lines = ['Athena Research Assistant transcript'];

        if (selectedContext) {
            lines.push(`Proposal context: ${selectedContext.label} (${selectedContext.status})`);
        }

        lines.push('');

        this.messages.forEach((message) => {
            const speaker = message.role === 'user' ? 'You' : 'Athena';
            lines.push(`${speaker}:`);
            lines.push(message.content);
            lines.push('');
        });

        return lines.join('\n').trim();
    },

    async copyConversation() {
        try {
            await navigator.clipboard.writeText(this.transcriptText());
            this.copiedConversation = true;
            window.setTimeout(() => {
                this.copiedConversation = false;
            }, 1500);
        } catch {
            this.setError('Copy failed', 'The chat could not be copied automatically.');
        }
    },

    exportConversation() {
        try {
            const blob = new Blob([this.transcriptText()], { type: 'text/plain;charset=utf-8' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');

            link.href = url;
            link.download = `athena-research-assistant-${new Date().toISOString().slice(0, 10)}.txt`;
            document.body.appendChild(link);
            link.click();
            link.remove();
            URL.revokeObjectURL(url);
        } catch {
            this.setError('Export failed', 'The chat transcript could not be exported.');
        }
    },

    clearConversation() {
        this.stop();
        this.messages = [{
            id: this.nextMessageId++,
            role: 'assistant',
            content: 'New conversation started. What would you like help with?',
            context: false,
        }];
        this.draft = '';
        this.error = '';
        this.errorTitle = '';
        this.retryAfter = 0;
        this.copiedMessageId = null;
        this.copiedConversation = false;
        this.scrollToLatest();
    },

    scrollToLatest() {
        window.setTimeout(() => {
            document.querySelectorAll('[data-assistant-messages]').forEach((container) => {
                container.scrollTop = container.scrollHeight;
            });
        });
    },
});

Alpine.store('literatureSearch', {
    query: '',
    filters: {
        year_from: '',
        year_to: '',
        min_citations: '',
        open_access: false,
    },
    results: [],
    failedSources: [],
    isLoading: false,
    hasSearched: false,
    error: '',

    async search() {
        const query = this.query.trim();
        this.hasSearched = true;
        this.error = '';
        this.failedSources = [];

        if (query.length < 3) {
            this.results = [];
            this.error = 'Enter at least 3 characters to search for related literature.';
            return;
        }

        const searchUrl = document.body.dataset.literatureSearchUrl;

        if (!searchUrl || this.isLoading) return;

        this.isLoading = true;

        try {
            const response = await fetch(searchUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify(this.searchPayload(query)),
            });

            const payload = await response.json().catch(() => ({}));
            this.failedSources = Array.isArray(payload.failed_sources) ? payload.failed_sources : [];

            if (!response.ok) {
                this.results = [];

                if (response.status === 419) {
                    this.error = 'Refresh the page, then try the search again.';
                    return;
                }

                if (response.status === 422) {
                    this.error = payload.errors?.query?.[0]
                        || payload.errors?.year_from?.[0]
                        || payload.errors?.year_to?.[0]
                        || payload.errors?.min_citations?.[0]
                        || payload.message
                        || 'Enter a valid topic, title, or filter.';
                    return;
                }

                if (response.status === 429) {
                    this.error = 'Too many searches were sent. Please wait a moment, then retry.';
                    return;
                }

                this.error = payload.message || 'The literature search could not be completed right now.';
                return;
            }

            this.results = Array.isArray(payload.results) ? payload.results : [];
        } catch (error) {
            this.results = [];
            this.error = error.message || 'A network error interrupted the literature search.';
        } finally {
            this.isLoading = false;
        }
    },

    searchPayload(query) {
        const payload = { query };
        const yearFrom = this.numberOrNull(this.filters.year_from);
        const yearTo = this.numberOrNull(this.filters.year_to);
        const minCitations = this.numberOrNull(this.filters.min_citations);

        if (yearFrom !== null) payload.year_from = yearFrom;
        if (yearTo !== null) payload.year_to = yearTo;
        if (minCitations !== null) payload.min_citations = minCitations;
        if (this.filters.open_access) payload.open_access = true;

        return payload;
    },

    numberOrNull(value) {
        if (value === '' || value === null || value === undefined) return null;

        const number = Number(value);

        return Number.isFinite(number) ? number : null;
    },

    filterSummary() {
        const yearFrom = this.numberOrNull(this.filters.year_from);
        const yearTo = this.numberOrNull(this.filters.year_to);
        const minCitations = this.numberOrNull(this.filters.min_citations);
        const parts = [];

        if (yearFrom !== null || yearTo !== null) {
            parts.push(`years ${yearFrom ?? 'any'}-${yearTo ?? 'present'}`);
        }

        if (minCitations !== null) {
            parts.push(`at least ${minCitations} citations`);
        }

        if (this.filters.open_access) {
            parts.push('open access only');
        }

        return parts.length ? parts.join(', ') : 'no filters';
    },

    askAthena() {
        if (!this.results.length) return;

        const assistant = Alpine.store('researchAssistant');

        if (!assistant || assistant.isLoading) return;

        assistant.draft = this.athenaPrompt();
        assistant.send();

        window.setTimeout(() => {
            document.getElementById('assistant-heading')?.scrollIntoView({
                behavior: 'smooth',
                block: 'start',
            });
        });
    },

    athenaPrompt() {
        const resultLines = this.results.slice(0, 8).map((result, index) => {
            const year = result.year ? ` (${result.year})` : '';
            const citations = Number.isInteger(result.citation_count) ? `; citations: ${result.citation_count}` : '';
            const doi = result.doi ? `; DOI: ${result.doi}` : '';

            return [
                `${index + 1}. ${result.title}${year}`,
                `Authors: ${result.authors || 'Authors not listed'}`,
                `Source: ${result.source}${result.venue ? `, ${result.venue}` : ''}${citations}${doi}`,
                `Description: ${result.description}`,
            ].join('\n');
        }).join('\n\n');

        return [
            'Help me organize these real RRL search results.',
            `Search topic/title: ${this.query.trim()}`,
            `Filters: ${this.filterSummary()}`,
            '',
            resultLines,
            '',
            'Please identify common themes, possible gaps, and a practical RRL outline. Do not invent additional citations.',
        ].join('\n');
    },

    clear() {
        this.query = '';
        this.filters = {
            year_from: '',
            year_to: '',
            min_citations: '',
            open_access: false,
        };
        this.results = [];
        this.failedSources = [];
        this.error = '';
        this.hasSearched = false;
    },
});

Alpine.store('conferenceSearch', {
    query: '',
    results: [],
    failedSources: [],
    isLoading: false,
    hasSearched: false,
    error: '',

    async search() {
        const query = this.query.trim();
        this.hasSearched = true;
        this.error = '';
        this.failedSources = [];

        if (query.length < 3) {
            this.results = [];
            this.error = 'Enter at least 3 characters to scrape conference listings.';
            return;
        }

        const searchUrl = document.body.dataset.conferenceSearchUrl;

        if (!searchUrl || this.isLoading) return;

        this.isLoading = true;

        try {
            const response = await fetch(searchUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ query }),
            });

            const payload = await response.json().catch(() => ({}));
            this.failedSources = Array.isArray(payload.failed_sources) ? payload.failed_sources : [];

            if (!response.ok) {
                this.results = [];

                if (response.status === 419) {
                    this.error = 'Refresh the page, then try the conference search again.';
                    return;
                }

                if (response.status === 422) {
                    this.error = payload.errors?.query?.[0] || payload.message || 'Enter a valid conference topic or title.';
                    return;
                }

                if (response.status === 429) {
                    this.error = 'Too many conference searches were sent. Please wait a moment, then retry.';
                    return;
                }

                this.error = payload.message || 'The conference scraper could not be completed right now.';
                return;
            }

            this.results = Array.isArray(payload.results) ? payload.results : [];
        } catch (error) {
            this.results = [];
            this.error = error.message || 'A network error interrupted the conference scraper.';
        } finally {
            this.isLoading = false;
        }
    },

    askAthena() {
        if (!this.results.length) return;

        const assistant = Alpine.store('researchAssistant');

        if (!assistant || assistant.isLoading) return;

        assistant.draft = this.athenaPrompt();
        assistant.send();

        window.setTimeout(() => {
            document.getElementById('assistant-heading')?.scrollIntoView({
                behavior: 'smooth',
                block: 'start',
            });
        });
    },

    athenaPrompt() {
        const resultLines = this.results.slice(0, 8).map((result, index) => {
            const relevance = Number.isInteger(result.relevance_score) ? `; relevance: ${result.relevance_score}%` : '';
            const scope = result.scope_label ? `; scope: ${result.scope_label}` : '';
            const matchedTerms = Array.isArray(result.matched_keywords) && result.matched_keywords.length
                ? `; matched terms: ${result.matched_keywords.join(', ')}`
                : '';

            return [
                `${index + 1}. ${result.title}`,
                `Source: ${result.source}${scope}${relevance}${matchedTerms}${result.location ? `; location: ${result.location}` : ''}${result.deadline ? `; deadline: ${result.deadline}` : ''}${result.event_date ? `; event date: ${result.event_date}` : ''}`,
                `Description: ${result.description}`,
                result.url ? `Link: ${result.url}` : '',
            ].filter(Boolean).join('\n');
        }).join('\n\n');

        return [
            'Help me compare these scraped conference publishing opportunities.',
            `Research topic/title: ${this.query.trim()}`,
            '',
            resultLines,
            '',
            'Please suggest which venues look most relevant, what deadline risks to check, and what details I should verify on the official CFP pages. Do not invent conference details.',
        ].join('\n');
    },

    clear() {
        this.query = '';
        this.results = [];
        this.failedSources = [];
        this.error = '';
        this.hasSearched = false;
    },
});

Alpine.data('notificationMenu', (config) => ({
    open: false,
    notifications: config.notifications,
    unreadCount: config.unreadCount,
    toasts: [],
    knownNotificationIds: config.notifications.map((item) => item.id),
    poller: null,

    init() {
        window.addEventListener('athena-notification', (event) => this.receive(event.detail));
        this.poller = window.setInterval(() => this.refresh(), 30000);
    },

    async refresh() {
        try {
            const response = await fetch(config.indexUrl, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            if (!response.ok) return;

            const payload = await response.json();
            const newNotifications = payload.notifications
                .filter((item) => !this.knownNotificationIds.includes(item.id))
                .reverse();

            this.notifications = payload.notifications;
            this.unreadCount = payload.unread_count;

            newNotifications.forEach((item) => {
                this.knownNotificationIds.push(item.id);
                this.showToast(item);
            });
        } catch {
            // Reverb or a temporary network failure should never break the app shell.
        }
    },

    receive(notification) {
        const item = {
            id: notification.id,
            data: notification.data || {
                title: notification.title,
                message: notification.message,
                url: notification.url,
                level: notification.level,
                topic_id: notification.topic_id,
            },
            read_at: null,
            created_at: 'Just now',
        };

        if (!this.notifications.some((existing) => existing.id === item.id)) {
            this.notifications.unshift(item);
            this.notifications = this.notifications.slice(0, 15);
            this.unreadCount += 1;
            this.knownNotificationIds.push(item.id);
            this.showToast(item);
        }
    },

    showToast(item) {
        if (this.toasts.some((toast) => toast.id === item.id)) return;

        this.toasts.push({
            id: item.id,
            item,
        });

        window.setTimeout(() => this.dismissToast(item.id), 7000);
    },

    dismissToast(id) {
        this.toasts = this.toasts.filter((toast) => toast.id !== id);
    },

    async request(url) {
        return fetch(url, {
            method: 'PATCH',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
        });
    },

    async markAllRead() {
        const response = await this.request(config.readAllUrl);
        if (!response.ok) return;

        this.notifications = this.notifications.map((item) => ({ ...item, read_at: item.read_at || new Date().toISOString() }));
        this.unreadCount = 0;
    },

    async openNotification(item) {
        if (!item.read_at) {
            const response = await this.request(config.readUrl.replace('__ID__', item.id));
            if (response.ok) {
                item.read_at = new Date().toISOString();
                this.unreadCount = Math.max(0, this.unreadCount - 1);
            }
        }

        if (item.data.url) window.location.assign(item.data.url);
    },

    levelClass(level) {
        return {
            success: 'bg-green-500',
            warning: 'bg-amber-500',
            danger: 'bg-red-600',
            info: 'bg-blue-500',
        }[level] || 'bg-gray-400';
    },
}));

Alpine.data('fileDropzone', (config = {}) => ({
    dragging: false,
    dragDepth: 0,
    files: [],
    message: '',

    init() {
        this.syncFiles();
    },

    browse() {
        this.$refs.input.click();
    },

    syncFiles(validate = false) {
        const selected = Array.from(this.$refs.input.files || []);

        if (!validate) {
            this.files = selected;
            return;
        }

        const candidates = config.multiple
            ? this.uniqueFiles([...this.files, ...selected])
            : selected;
        const accepted = candidates.filter((file) => this.accepts(file) && file.size <= config.maxBytes);
        const rejectedCount = candidates.length - accepted.length;

        if (rejectedCount > 0) {
            this.setFiles(accepted);
            this.message = `${rejectedCount} ${rejectedCount === 1 ? 'file was' : 'files were'} not added. Use ${this.acceptedTypes()} files under ${this.formatSize(config.maxBytes)}.`;
            return;
        }

        this.files = selected;
        this.message = '';
    },

    dragEnter() {
        this.dragDepth += 1;
        this.dragging = true;
    },

    dragLeave() {
        this.dragDepth = Math.max(0, this.dragDepth - 1);

        if (this.dragDepth === 0) this.dragging = false;
    },

    drop(event) {
        this.dragDepth = 0;
        this.dragging = false;

        const incoming = Array.from(event.dataTransfer?.files || []);
        const accepted = incoming.filter((file) => this.accepts(file) && file.size <= config.maxBytes);
        const rejectedCount = incoming.length - accepted.length;
        const rejectionMessage = rejectedCount > 0
            ? `${rejectedCount} ${rejectedCount === 1 ? 'file was' : 'files were'} not added. Use ${this.acceptedTypes()} files under ${this.formatSize(config.maxBytes)}.`
            : '';

        if (accepted.length === 0) {
            this.message = rejectionMessage;
            return;
        }

        const nextFiles = config.multiple
            ? this.uniqueFiles([...this.files, ...accepted])
            : [accepted[0]];

        this.setFiles(nextFiles);
        this.message = rejectionMessage;
    },

    remove(index) {
        this.setFiles(this.files.filter((_, fileIndex) => fileIndex !== index));
        this.message = '';
    },

    setFiles(files) {
        const transfer = new DataTransfer();

        files.forEach((file) => transfer.items.add(file));
        this.$refs.input.files = transfer.files;
        this.files = Array.from(transfer.files);
    },

    uniqueFiles(files) {
        return files.filter((file, index, allFiles) => (
            allFiles.findIndex((candidate) => (
                candidate.name === file.name
                && candidate.size === file.size
                && candidate.lastModified === file.lastModified
            )) === index
        ));
    },

    accepts(file) {
        if (!config.accept) return true;

        const fileName = file.name.toLowerCase();

        return config.accept
            .split(',')
            .map((type) => type.trim().toLowerCase())
            .some((type) => type.startsWith('.') ? fileName.endsWith(type) : file.type === type);
    },

    acceptedTypes() {
        return config.accept
            .split(',')
            .map((type) => type.trim().replace(/^\./, '').toUpperCase())
            .join(', ');
    },

    extension(fileName) {
        const parts = fileName.split('.');

        return parts.length > 1 ? parts.pop().toUpperCase() : 'FILE';
    },

    formatSize(bytes) {
        if (bytes === 0) return '0 B';

        const units = ['B', 'KB', 'MB', 'GB'];
        const unitIndex = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
        const size = bytes / (1024 ** unitIndex);

        return `${size >= 10 || unitIndex === 0 ? size.toFixed(0) : size.toFixed(1)} ${units[unitIndex]}`;
    },
}));

Alpine.start();
