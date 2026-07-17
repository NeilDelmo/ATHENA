
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

function currentPaperEditor() {
    return document.querySelector('[data-paper-editor]');
}

function paperEditorHasUnsavedChanges(editor) {
    return editor?.dataset.paperDirty === 'true';
}

function confirmPaperEditorNavigation(editor, message) {
    return !paperEditorHasUnsavedChanges(editor) || window.confirm(message);
}

function navigateFromPaperEditor(editor, destination, message) {
    if (!destination || !confirmPaperEditorNavigation(editor, message)) return;

    window.location.assign(destination);
}

function submitPaperEditor(editor, submitterSelector) {
    const form = editor.querySelector('[data-paper-form]');
    const submitter = editor.querySelector(submitterSelector);

    if (form instanceof HTMLFormElement && submitter instanceof HTMLElement && !submitter.hasAttribute('disabled')) {
        form.requestSubmit(submitter);
    }
}

document.addEventListener('input', (event) => {
    const editor = event.target instanceof Element ? event.target.closest('[data-paper-editor]') : null;

    if (editor) editor.dataset.paperDirty = 'true';
});

document.addEventListener('change', (event) => {
    const editor = event.target instanceof Element ? event.target.closest('[data-paper-editor]') : null;

    if (editor) editor.dataset.paperDirty = 'true';
});

document.addEventListener('submit', (event) => {
    const editor = event.target instanceof Element ? event.target.closest('[data-paper-editor]') : null;

    if (!editor) return;

    queueMicrotask(() => {
        if (!event.defaultPrevented) editor.dataset.paperDirty = 'false';
    });
});

document.addEventListener('click', (event) => {
    const action = event.target instanceof Element
        ? event.target.closest('[data-paper-discard], [data-paper-cancel-exit]')
        : null;

    if (!action) return;

    const editor = action.closest('[data-paper-editor]');
    const message = action.matches('[data-paper-discard]')
        ? 'Discard unsaved changes and reload this paper?'
        : 'Discard unsaved changes and return to the proposal package?';

    if (!confirmPaperEditorNavigation(editor, message)) event.preventDefault();
});

document.addEventListener('keydown', (event) => {
    const editor = currentPaperEditor();

    if (!editor || event.defaultPrevented || event.isComposing || event.repeat) return;

    const commandKey = event.ctrlKey || event.metaKey;
    const key = event.key.toLowerCase();

    if (commandKey && !event.altKey && !event.shiftKey && key === 's') {
        event.preventDefault();
        submitPaperEditor(editor, '[data-paper-save]');

        return;
    }

    if (commandKey && !event.altKey && !event.shiftKey && key === 'enter') {
        event.preventDefault();
        submitPaperEditor(editor, '[data-paper-save-exit]');

        return;
    }

    if (commandKey && event.altKey && !event.shiftKey && key === 'r') {
        event.preventDefault();
        navigateFromPaperEditor(
            editor,
            editor.dataset.paperEditUrl,
            'Discard unsaved changes and reload this paper?',
        );

        return;
    }

    if (commandKey && event.altKey && !event.shiftKey && key === 'x') {
        event.preventDefault();
        navigateFromPaperEditor(
            editor,
            editor.dataset.paperExitUrl,
            'Discard unsaved changes and return to the proposal package?',
        );
    }
});

const assistantContexts = Array.isArray(window.athenaResearchAssistantContexts)
    ? window.athenaResearchAssistantContexts
    : [];
const activeAssistantContextId = window.athenaResearchAssistantActiveContextId ?? null;
const defaultAssistantContextId = assistantContexts.some((context) => Number(context.id) === Number(activeAssistantContextId))
    ? Number(activeAssistantContextId)
    : Number(assistantContexts[0]?.id || 0);
const researchAssistantMessageLimit = 8000;

function compactAssistantText(value, maxLength = 280) {
    const normalized = String(value ?? '').replace(/\s+/g, ' ').trim();

    if (normalized.length <= maxLength) return normalized;

    return `${normalized.slice(0, Math.max(1, maxLength - 1)).trimEnd()}…`;
}

function escapeAssistantHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function formatAssistantMarkdown(value) {
    const codeSpans = [];
    let formatted = escapeAssistantHtml(value).replace(/`([^`\n]+)`/g, (_, code) => {
        const index = codeSpans.push(code) - 1;

        return `\uE000${index}\uE001`;
    });

    formatted = formatted
        .replace(/\*\*([^*\n]+)\*\*/g, '<strong>$1</strong>')
        .replace(/__([^_\n]+)__/g, '<strong>$1</strong>')
        .replace(/(^|[^*])\*([^*\n]+)\*(?!\*)/g, '$1<em>$2</em>')
        .replace(/(^|[^\w])_([^_\n]+)_(?!\w)/g, '$1<em>$2</em>')
        .replace(/\uE000(\d+)\uE001/g, (_, index) => `<code class="rounded bg-gray-100 px-1 py-0.5 font-mono text-[0.9em] dark:bg-slate-800">${codeSpans[Number(index)]}</code>`)
        .replace(/\n/g, '<br>');

    return formatted;
}

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
    requestSequence: 0,
    returnFocusElement: null,
    copiedMessageId: null,
    copiedConversation: false,
    nextMessageId: 2,
    contextOptions: assistantContexts,
    contextEnabled: Boolean(activeAssistantContextId),
    selectedContextId: defaultAssistantContextId,
    activePromptGroup: researchAssistantPromptGroups[0].key,
    promptGroups: researchAssistantPromptGroups,
    quickPrompts: researchAssistantPromptGroups.flatMap((group) => group.prompts),
    messages: [],

    isOverlayViewport() {
        return !window.matchMedia('(min-width: 1280px)').matches;
    },

    syncPageScroll() {
        document.documentElement.classList.toggle(
            'overflow-hidden',
            this.drawerOpen && this.isOverlayViewport(),
        );
    },

    toggleDrawer(trigger = null) {
        if (this.drawerOpen) {
            this.closeDrawer();
            return;
        }

        this.openDrawer(trigger);
    },

    openDrawer(trigger = null) {
        this.returnFocusElement = trigger instanceof HTMLElement ? trigger : document.activeElement;
        this.drawerOpen = true;
        this.syncPageScroll();
        window.setTimeout(() => document.getElementById('research-assistant-drawer-message')?.focus(), 220);
    },

    closeDrawer() {
        this.drawerOpen = false;
        this.syncPageScroll();
        window.setTimeout(() => this.returnFocusElement?.focus?.());
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

    starterPrompts() {
        return this.promptGroups.map((group) => ({
            label: group.label,
            prompt: group.prompts[0],
        }));
    },

    hasConversation() {
        return this.messages.some((message) => message.role === 'user');
    },

    renderMessage(content) {
        return formatAssistantMarkdown(content);
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

    async sendPrompt(prompt) {
        this.draft = prompt;
        await this.send();
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
        const content = this.draft.trim().slice(0, researchAssistantMessageLimit);
        if (!content || this.isLoading || this.retryAfter > 0) return;

        this.messages.push({ id: this.nextMessageId++, role: 'user', content });
        this.draft = '';
        this.resetComposers();
        this.error = '';
        this.errorTitle = '';
        this.scrollToLatest();

        await this.requestReply();
    },

    async requestReply() {
        const chatUrl = document.body.dataset.researchAssistantUrl;
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

        if (this.isLoading) return;

        if (!chatUrl) {
            this.setError('Assistant unavailable', 'The chat endpoint is unavailable on this page. Refresh and try again.');
            return;
        }

        if (!csrfToken) {
            this.setError('Session unavailable', 'The security token is missing. Refresh the page before sending another message.');
            return;
        }

        const requestSequence = ++this.requestSequence;
        const abortController = new AbortController();
        this.isLoading = true;
        this.error = '';
        this.abortController = abortController;
        this.scrollToLatest();

        try {
            const response = await fetch(chatUrl, {
                method: 'POST',
                credentials: 'same-origin',
                signal: abortController.signal,
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
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

            if (typeof payload.reply !== 'string' || !payload.reply.trim()) {
                this.setError('Invalid response', 'Athena returned an incomplete response. Please retry your message.');
                return;
            }

            this.messages.push({
                id: this.nextMessageId++,
                role: 'assistant',
                content: payload.reply.trim(),
                model: payload.model,
                sources: Array.isArray(payload.sources) ? payload.sources : [],
            });
            this.copiedConversation = false;
        } catch (error) {
            if (error.name !== 'AbortError') {
                this.setError('Network interrupted', error instanceof Error && error.message
                    ? error.message
                    : 'A network error interrupted the request.');
            }
        } finally {
            if (requestSequence === this.requestSequence) {
                this.isLoading = false;
                this.abortController = null;
                this.scrollToLatest();
            }
        }
    },

    contextMessages() {
        return this.messages
            .filter((message) => ['user', 'assistant'].includes(message.role) && message.context !== false)
            .slice(-8)
            .map(({ role, content }) => ({
                role,
                content: String(content).slice(0, researchAssistantMessageLimit),
            }));
    },

    async retry() {
        if (this.isLoading || this.retryAfter > 0 || !this.contextMessages().length) return;
        await this.requestReply();
    },

    stop() {
        this.requestSequence += 1;
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

            if (Array.isArray(message.sources) && message.sources.length) {
                lines.push(`Grounded sources: ${message.sources.map((source) => `${source.reference} - ${source.title}`).join('; ')}`);
            }

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

    newConversation() {
        if (this.hasConversation() && !window.confirm('Start a new chat? The current conversation is not saved.')) {
            return;
        }

        this.clearConversation();
    },

    clearConversation() {
        this.stop();
        this.messages = [];
        this.draft = '';
        this.resetComposers();
        this.error = '';
        this.errorTitle = '';
        this.retryAfter = 0;
        this.copiedMessageId = null;
        this.copiedConversation = false;
        this.scrollToLatest();
    },

    resizeComposer(event) {
        const composer = event?.currentTarget;
        if (!composer) return;

        composer.style.height = 'auto';
        composer.style.height = `${Math.min(composer.scrollHeight, 176)}px`;
    },

    resetComposers() {
        window.setTimeout(() => {
            document.querySelectorAll('[data-assistant-composer]').forEach((composer) => {
                composer.style.height = 'auto';
            });
        });
    },

    scrollToLatest() {
        window.setTimeout(() => {
            document.querySelectorAll('[data-assistant-messages]').forEach((container) => {
                if (container.offsetParent !== null) {
                    container.scrollTop = container.scrollHeight;
                }
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
        const resultLines = this.results.slice(0, 6).map((result, index) => {
            const year = result.year ? ` (${result.year})` : '';
            const citations = Number.isInteger(result.citation_count) ? `; citations: ${result.citation_count}` : '';
            const doi = result.doi ? `; DOI: ${compactAssistantText(result.doi, 100)}` : '';

            return [
                `${index + 1}. ${compactAssistantText(result.title, 180)}${year}`,
                `Authors: ${compactAssistantText(result.authors || 'Authors not listed', 180)}`,
                `Source: ${compactAssistantText(result.source, 80)}${result.venue ? `, ${compactAssistantText(result.venue, 120)}` : ''}${citations}${doi}`,
                `Description: ${compactAssistantText(result.description, 320)}`,
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
    activeQuery: '',
    lastSearchQuery: '',
    results: [],
    failedSources: [],
    isLoading: false,
    hasSearched: false,
    elapsedSeconds: 0,
    loadingTimer: null,
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

        this.results = [];
        this.activeQuery = query;
        this.lastSearchQuery = query;
        this.startLoadingTimer();

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
            this.stopLoadingTimer();
        }
    },

    startLoadingTimer() {
        this.stopLoadingTimer();
        this.elapsedSeconds = 0;
        this.isLoading = true;
        this.loadingTimer = window.setInterval(() => {
            this.elapsedSeconds += 1;
        }, 1000);
    },

    stopLoadingTimer() {
        if (this.loadingTimer !== null) {
            window.clearInterval(this.loadingTimer);
            this.loadingTimer = null;
        }
    },

    loadingMessage() {
        if (this.elapsedSeconds >= 8) {
            return 'Still searching \u2014 the source is taking a little longer';
        }

        if (this.elapsedSeconds >= 2) {
            return 'Scanning public conference listings';
        }

        return 'Connecting to the conference source';
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
        const resultLines = this.results.slice(0, 6).map((result, index) => {
            const relevance = Number.isInteger(result.relevance_score) ? `; relevance: ${result.relevance_score}%` : '';
            const scope = result.scope_label ? `; scope: ${compactAssistantText(result.scope_label, 80)}` : '';
            const matchedTerms = Array.isArray(result.matched_keywords) && result.matched_keywords.length
                ? `; matched terms: ${compactAssistantText(result.matched_keywords.join(', '), 160)}`
                : '';

            return [
                `${index + 1}. ${compactAssistantText(result.title, 180)}`,
                `Source: ${compactAssistantText(result.source, 80)}${scope}${relevance}${matchedTerms}${result.location ? `; location: ${compactAssistantText(result.location, 100)}` : ''}${result.deadline ? `; deadline: ${compactAssistantText(result.deadline, 80)}` : ''}${result.event_date ? `; event date: ${compactAssistantText(result.event_date, 80)}` : ''}`,
                `Description: ${compactAssistantText(result.description, 320)}`,
                result.url ? `Link: ${compactAssistantText(result.url, 240)}` : '',
            ].filter(Boolean).join('\n');
        }).join('\n\n');

        return [
            'Help me compare these scraped conference publishing opportunities.',
            `Research topic/title: ${this.lastSearchQuery || this.query.trim()}`,
            '',
            resultLines,
            '',
            'Please suggest which venues look most relevant, what deadline risks to check, and what details I should verify on the official CFP pages. Do not invent conference details.',
        ].join('\n');
    },

    clear() {
        this.stopLoadingTimer();
        this.query = '';
        this.activeQuery = '';
        this.lastSearchQuery = '';
        this.results = [];
        this.failedSources = [];
        this.error = '';
        this.hasSearched = false;
        this.elapsedSeconds = 0;
        this.isLoading = false;
    },
});

Alpine.data('datePicker', (config = {}) => ({
    value: normalizeIsoDate(config.initialValue),
    min: normalizeIsoDate(config.min) || '1900-01-01',
    max: normalizeIsoDate(config.max) || '2100-12-31',
    required: Boolean(config.required),
    isOpen: false,
    suppressFocusOpen: false,
    viewMonth: new Date().getMonth(),
    viewYear: new Date().getFullYear(),
    panelId: `athena-date-picker-${Math.random().toString(36).slice(2, 10)}`,
    monthSelectId: `athena-date-picker-month-${Math.random().toString(36).slice(2, 10)}`,
    yearInputId: `athena-date-picker-year-${Math.random().toString(36).slice(2, 10)}`,
    months: ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
    weekdays: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],

    get formattedValue() {
        const date = localDateFromIso(this.value);

        if (!date) return '';

        return new Intl.DateTimeFormat(undefined, {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
        }).format(date);
    },

    get minYear() {
        return this.min ? Number(this.min.slice(0, 4)) : 1900;
    },

    get maxYear() {
        return this.max ? Number(this.max.slice(0, 4)) : 2100;
    },

    get todaySelectable() {
        return this.isSelectable(isoDateFromLocal(new Date()));
    },

    get calendarDays() {
        const safeYear = this.boundedViewYear();
        const safeMonth = Number.isInteger(Number(this.viewMonth)) ? Number(this.viewMonth) : new Date().getMonth();
        const firstDay = new Date(safeYear, safeMonth, 1);
        const calendarStart = new Date(safeYear, safeMonth, 1 - firstDay.getDay());
        const today = isoDateFromLocal(new Date());

        return Array.from({ length: 42 }, (_, index) => {
            const date = new Date(calendarStart);
            date.setDate(calendarStart.getDate() + index);
            const iso = isoDateFromLocal(date);

            return {
                iso,
                dayNumber: date.getDate(),
                label: new Intl.DateTimeFormat(undefined, {
                    weekday: 'long',
                    month: 'long',
                    day: 'numeric',
                    year: 'numeric',
                }).format(date),
                isCurrentMonth: date.getMonth() === safeMonth,
                isToday: iso === today,
                isSelected: iso === this.value,
                selectable: this.isSelectable(iso),
            };
        });
    },

    open() {
        const preferredDate = localDateFromIso(this.value) ?? new Date();
        const visibleDate = this.clampDate(preferredDate);

        this.viewMonth = visibleDate.getMonth();
        this.viewYear = visibleDate.getFullYear();
        this.isOpen = true;
    },

    handleFocus() {
        if (this.suppressFocusOpen) {
            this.suppressFocusOpen = false;
            return;
        }

        this.open();
    },

    close() {
        this.isOpen = false;
    },

    closeAndFocus() {
        this.close();
        this.suppressFocusOpen = true;
        this.$nextTick(() => {
            this.$refs.display?.focus({ preventScroll: true });
            this.$nextTick(() => {
                this.suppressFocusOpen = false;
            });
        });
    },

    toggle() {
        if (this.isOpen) {
            this.closeAndFocus();
            return;
        }

        this.open();
    },

    selectDate(iso) {
        if (!this.isSelectable(iso)) return;

        this.value = iso;
        this.closeAndFocus();
    },

    selectToday() {
        this.selectDate(isoDateFromLocal(new Date()));
    },

    clear() {
        if (this.required) return;

        this.value = '';
        this.closeAndFocus();
    },

    changeMonth(offset) {
        if (!this.canChangeMonth(offset)) return;

        const nextMonth = new Date(this.boundedViewYear(), Number(this.viewMonth) + offset, 1);
        this.viewMonth = nextMonth.getMonth();
        this.viewYear = nextMonth.getFullYear();
    },

    canChangeMonth(offset) {
        const target = new Date(this.boundedViewYear(), Number(this.viewMonth) + offset, 1);
        const monthStart = isoDateFromLocal(target);
        const monthEnd = isoDateFromLocal(new Date(target.getFullYear(), target.getMonth() + 1, 0));

        return (!this.min || monthEnd >= this.min) && (!this.max || monthStart <= this.max);
    },

    clampViewYear() {
        const fallbackYear = localDateFromIso(this.value)?.getFullYear() ?? new Date().getFullYear();
        const hasRequestedYear = String(this.viewYear).trim() !== '' && Number.isInteger(Number(this.viewYear));
        const requestedYear = hasRequestedYear ? Number(this.viewYear) : fallbackYear;

        this.viewYear = Math.min(this.maxYear, Math.max(this.minYear, requestedYear));
    },

    boundedViewYear() {
        const requestedYear = Number(this.viewYear);
        const fallbackYear = localDateFromIso(this.value)?.getFullYear() ?? new Date().getFullYear();
        const safeYear = Number.isInteger(requestedYear) ? requestedYear : fallbackYear;

        return Math.min(this.maxYear, Math.max(this.minYear, safeYear));
    },

    clampDate(date) {
        const iso = isoDateFromLocal(date);

        if (this.min && iso < this.min) return localDateFromIso(this.min);
        if (this.max && iso > this.max) return localDateFromIso(this.max);

        return date;
    },

    isSelectable(iso) {
        return Boolean(iso) && (!this.min || iso >= this.min) && (!this.max || iso <= this.max);
    },
}));

function normalizeIsoDate(value) {
    if (typeof value !== 'string') return '';

    return localDateFromIso(value) ? value : '';
}

function localDateFromIso(value) {
    if (typeof value !== 'string') return null;

    const match = value.match(/^(\d{4})-(\d{2})-(\d{2})$/);

    if (!match) return null;

    const [, year, month, day] = match.map(Number);
    const date = new Date(year, month - 1, day);

    if (date.getFullYear() !== year || date.getMonth() !== month - 1 || date.getDate() !== day) return null;

    return date;
}

function isoDateFromLocal(date) {
    const year = String(date.getFullYear()).padStart(4, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

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

Alpine.data('workPlanWizard', (config = {}) => ({
    step: 1,
    nextEntryId: 0,
    entries: [],
    maxEntries: Number(config.maxEntries || 20),
    months: Array.from({ length: 12 }, (_, index) => index + 1),
    durationMonths: Number(config.initialDuration) || '',
    monthErrorIndexes: [],
    validationMessage: '',
    previewHtml: '',
    previewError: '',
    previewLoading: false,
    previewReady: false,
    downloadError: '',
    downloadLoading: false,

    init() {
        const initialEntries = Array.isArray(config.initialEntries) ? config.initialEntries : [];

        if (initialEntries.length === 0) {
            this.addEntry();

            return;
        }

        this.entries = initialEntries
            .slice(0, this.maxEntries)
            .map((entry) => this.newEntry(entry));
        this.limitMonthsToDuration();
    },

    newEntry(values = {}) {
        this.nextEntryId += 1;

        return {
            id: this.nextEntryId,
            objective: String(values.objective || ''),
            expectedOutput: String(values.expected_output || ''),
            activity: String(values.activity || ''),
            months: Array.isArray(values.months)
                ? values.months.map((month) => Number(month))
                : [],
        };
    },

    addEntry() {
        if (this.entries.length >= this.maxEntries) return;

        this.entries.push(this.newEntry());
        this.monthErrorIndexes = [];
    },

    removeEntry(index) {
        if (this.entries.length === 1) return;

        this.entries.splice(index, 1);
        this.monthErrorIndexes = [];
    },

    clearMonthError(index) {
        if (this.entries[index]?.months.length === 0) return;

        this.monthErrorIndexes = this.monthErrorIndexes.filter((entryIndex) => entryIndex !== index);
    },

    isMonthWithinDuration(month) {
        const duration = Number(this.durationMonths);

        return Number.isInteger(duration) && duration >= 1 && month <= duration;
    },

    limitMonthsToDuration() {
        const duration = Number(this.durationMonths);

        if (!Number.isInteger(duration) || duration < 1) return;

        this.entries.forEach((entry) => {
            entry.months = entry.months.filter((month) => month <= duration);
        });
        this.monthErrorIndexes = [];
    },

    stepTitle() {
        return {
            1: 'Project details',
            2: 'Work Plan and signatories',
            3: 'Required documents',
            4: 'Review and submit',
        }[this.step];
    },

    shortStepTitle(stepNumber) {
        return {
            1: 'Details',
            2: 'Work Plan',
            3: 'Documents',
            4: 'Review',
        }[stepNumber];
    },

    goToStep(stepNumber) {
        if (stepNumber >= this.step) return;

        this.step = stepNumber;
        this.validationMessage = '';
        this.scrollToWizard();
    },

    nextStep() {
        if (!this.validateStep(this.step)) return;

        this.step = Math.min(3, this.step + 1);
        this.validationMessage = '';
        this.scrollToWizard();
    },

    previousStep() {
        this.step = Math.max(1, this.step - 1);
        this.validationMessage = '';
        this.scrollToWizard();
    },

    validateStep(stepNumber) {
        const panel = this.$refs.form.querySelector(`[data-work-plan-step="${stepNumber}"]`);
        const fields = Array.from(panel?.querySelectorAll('input, textarea, select') || []);
        const invalidField = fields.find((field) => !field.checkValidity());

        if (invalidField) {
            invalidField.reportValidity();
            invalidField.focus();

            return false;
        }

        if (stepNumber !== 2) return true;

        const invalidEntryIndex = this.entries.findIndex((entry) => entry.months.length === 0);

        if (invalidEntryIndex === -1) {
            this.monthErrorIndexes = [];

            return true;
        }

        this.monthErrorIndexes = [invalidEntryIndex];
        this.$nextTick(() => {
            this.$refs.form
                .querySelector(`[name="entries[${invalidEntryIndex}][months][]"]`)
                ?.focus();
        });

        return false;
    },

    async generatePreview() {
        if (this.step < 4 && !this.validateStep(this.step)) return;

        this.step = 4;
        this.validationMessage = '';
        this.previewError = '';
        this.downloadError = '';
        this.previewLoading = true;
        this.previewReady = false;
        this.scrollToWizard();

        try {
            const response = await fetch(config.previewUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': config.csrfToken,
                },
                body: this.workPlanFormData(),
            });

            if (response.status === 422) {
                const payload = await response.json();
                const firstField = Object.keys(payload.errors || {})[0] || '';

                this.validationMessage = Object.values(payload.errors || {}).flat().join(' ')
                    || 'Please review the information you entered.';
                this.previewHtml = '';
                this.step = this.stepForField(firstField);
                this.scrollToWizard();

                return;
            }

            if (!response.ok) throw new Error('The preview service is unavailable. Please try again.');

            this.previewHtml = await response.text();
        } catch (error) {
            this.previewHtml = '';
            this.previewError = error instanceof Error
                ? error.message
                : 'The preview service is unavailable. Please try again.';
        } finally {
            this.previewLoading = false;
        }
    },

    stepForField(field) {
        if (field.startsWith('entries.')) return 2;
        if (field.startsWith('prepared_') || field.startsWith('verified_')) return 2;

        return 1;
    },

    workPlanFormData() {
        const formData = new FormData(this.$refs.form);

        [
            'research_call_id',
            'detailed_proposal',
            'document',
            'work_plan',
            'line_item_budget',
            'expense_breakdown',
            'curricula_vitae[]',
            'gad_checklist',
        ].forEach((field) => formData.delete(field));

        return formData;
    },

    async downloadDocument() {
        this.downloadError = '';
        this.downloadLoading = true;

        try {
            const response = await fetch(config.downloadUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'X-CSRF-TOKEN': config.csrfToken,
                },
                body: this.workPlanFormData(),
            });

            if (response.status === 422) {
                const payload = await response.json();
                const firstField = Object.keys(payload.errors || {})[0] || '';

                this.validationMessage = Object.values(payload.errors || {}).flat().join(' ')
                    || 'Please review the Work Plan information.';
                this.step = this.stepForField(firstField);
                this.scrollToWizard();

                return;
            }

            if (!response.ok) throw new Error('The Word file could not be generated. Please try again.');

            const disposition = response.headers.get('Content-Disposition') || '';
            const filenameMatch = disposition.match(/filename="?([^";]+)"?/i);
            const filename = filenameMatch?.[1] || 'attachment-a-work-plan.docx';
            const downloadUrl = URL.createObjectURL(await response.blob());
            const link = document.createElement('a');

            link.href = downloadUrl;
            link.download = filename;
            document.body.appendChild(link);
            link.click();
            link.remove();
            URL.revokeObjectURL(downloadUrl);
        } catch (error) {
            this.downloadError = error instanceof Error
                ? error.message
                : 'The Word file could not be generated. Please try again.';
        } finally {
            this.downloadLoading = false;
        }
    },

    printPreview() {
        if (!this.previewReady || !this.$refs.previewFrame?.contentWindow) return;

        this.$refs.previewFrame.contentWindow.focus();
        this.$refs.previewFrame.contentWindow.print();
    },

    scrollToWizard() {
        const behavior = window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth';

        this.$root.scrollIntoView({ behavior, block: 'start' });
    },
}));

Alpine.data('proposalDraftWorkPlan', (config = {}) => ({
    nextEntryId: 0,
    entries: [],
    maxEntries: Number(config.maxEntries || 20),
    months: Array.from({ length: 12 }, (_, index) => index + 1),
    durationMonths: Number(config.durationMonths || 12),
    monthErrorIndexes: [],
    monthConflictIndexes: [],
    validationMessage: '',
    previewHtml: '',
    previewError: '',
    previewLoading: false,
    previewReady: false,
    downloadError: '',
    downloadLoading: false,

    init() {
        const initialEntries = Array.isArray(config.initialEntries) ? config.initialEntries : [];

        this.entries = initialEntries.length > 0
            ? initialEntries.slice(0, this.maxEntries).map((entry) => this.newEntry(entry))
            : [this.newEntry()];
        this.limitMonthsToDuration();
    },

    newEntry(values = {}) {
        this.nextEntryId += 1;

        return {
            id: this.nextEntryId,
            objective: String(values.objective || ''),
            expectedOutput: String(values.expected_output || ''),
            activity: String(values.activity || ''),
            months: Array.isArray(values.months)
                ? values.months.map((month) => Number(month))
                : [],
        };
    },

    addEntry() {
        if (!this.canAddEntry()) return;

        this.entries.push(this.newEntry());
        this.monthErrorIndexes = [];
        this.monthConflictIndexes = [];
    },

    removeEntry(index) {
        if (this.entries.length === 1) return;

        this.entries.splice(index, 1);
        this.monthErrorIndexes = [];
        this.monthConflictIndexes = [];
    },

    clearMonthError(index) {
        if (this.entries[index]?.months.length === 0) return;

        this.monthErrorIndexes = this.monthErrorIndexes.filter((entryIndex) => entryIndex !== index);
        this.monthConflictIndexes = [];
        this.validationMessage = '';
    },

    isMonthWithinDuration(month) {
        return Number.isInteger(this.durationMonths)
            && this.durationMonths >= 1
            && month <= this.durationMonths;
    },

    canAddEntry() {
        const usedMonths = new Set(this.entries.flatMap((entry) => entry.months));

        return this.entries.length < Math.min(this.maxEntries, this.durationMonths)
            && usedMonths.size < this.durationMonths;
    },

    monthOwnerIndex(entryIndex, month) {
        return this.entries.findIndex((entry, index) => (
            index !== entryIndex && entry.months.includes(month)
        ));
    },

    isMonthSelectable(entryIndex, month) {
        if (!this.isMonthWithinDuration(month)) return false;
        if (this.entries[entryIndex]?.months.includes(month)) return true;

        return this.monthOwnerIndex(entryIndex, month) === -1;
    },

    monthOwnerLabel(entryIndex, month) {
        const ownerIndex = this.monthOwnerIndex(entryIndex, month);

        return ownerIndex === -1 ? '' : `O${ownerIndex + 1}`;
    },

    monthSelectionTitle(entryIndex, month) {
        if (!this.isMonthWithinDuration(month)) {
            return `M${month} is outside the project duration.`;
        }

        const ownerIndex = this.monthOwnerIndex(entryIndex, month);

        return ownerIndex === -1
            ? `Assign M${month} to Objective ${entryIndex + 1}`
            : `M${month} is assigned to Objective ${ownerIndex + 1}`;
    },

    monthConflicts() {
        const monthOwners = new Map();
        const conflicts = [];

        this.entries.forEach((entry, entryIndex) => {
            entry.months.forEach((month) => {
                if (monthOwners.has(month) && monthOwners.get(month) !== entryIndex) {
                    conflicts.push({
                        entryIndex,
                        month,
                        ownerIndex: monthOwners.get(month),
                    });

                    return;
                }

                monthOwners.set(month, entryIndex);
            });
        });

        return conflicts;
    },

    limitMonthsToDuration() {
        this.entries.forEach((entry) => {
            entry.months = entry.months.filter((month) => this.isMonthWithinDuration(month));
        });
        this.monthErrorIndexes = [];
        this.monthConflictIndexes = [];
    },

    validateForm() {
        this.validationMessage = '';
        const fields = Array.from(this.$refs.form?.querySelectorAll('input, textarea, select') || []);
        const invalidField = fields.find((field) => !field.checkValidity());

        if (invalidField) {
            invalidField.reportValidity();
            invalidField.focus();

            return false;
        }

        const invalidEntryIndex = this.entries.findIndex((entry) => entry.months.length === 0);

        if (invalidEntryIndex !== -1) {
            this.monthErrorIndexes = [invalidEntryIndex];
            this.validationMessage = 'Select at least one scheduled month for every objective.';
            this.$nextTick(() => {
                this.$refs.form
                    ?.querySelector(`[name="entries[${invalidEntryIndex}][months][]"]`)
                    ?.focus();
            });

            return false;
        }

        this.monthErrorIndexes = [];
        const conflicts = this.monthConflicts();

        if (conflicts.length === 0) {
            this.monthConflictIndexes = [];

            return true;
        }

        const firstConflict = conflicts[0];
        this.monthConflictIndexes = [...new Set(conflicts.map((conflict) => conflict.entryIndex))];
        this.validationMessage = `M${firstConflict.month} is already assigned to Objective ${firstConflict.ownerIndex + 1}. Each month can be assigned to only one objective.`;
        this.$nextTick(() => {
            this.$refs.form
                ?.querySelector(`[name="entries[${firstConflict.entryIndex}][months][]"][value="${firstConflict.month}"]`)
                ?.focus();
        });

        return false;
    },

    workPlanFormData() {
        const formData = new FormData(this.$refs.form);

        formData.delete('_method');

        return formData;
    },

    async generatePreview() {
        if (!this.validateForm()) return;

        this.previewError = '';
        this.downloadError = '';
        this.previewLoading = true;
        this.previewReady = false;

        try {
            const response = await fetch(config.previewUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': config.csrfToken,
                },
                body: this.workPlanFormData(),
            });

            if (response.status === 422) {
                const payload = await response.json();

                this.validationMessage = Object.values(payload.errors || {}).flat().join(' ')
                    || 'Please review the Work Plan information.';
                this.previewHtml = '';

                return;
            }

            if (!response.ok) throw new Error('The preview service is unavailable. Please try again.');

            this.previewHtml = await response.text();
        } catch (error) {
            this.previewHtml = '';
            this.previewError = error instanceof Error
                ? error.message
                : 'The preview service is unavailable. Please try again.';
        } finally {
            this.previewLoading = false;
        }
    },

    async downloadDocument() {
        if (!this.validateForm()) return;

        this.downloadError = '';
        this.downloadLoading = true;

        try {
            const response = await fetch(config.downloadUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'X-CSRF-TOKEN': config.csrfToken,
                },
                body: this.workPlanFormData(),
            });

            if (response.status === 422) {
                const payload = await response.json();

                this.validationMessage = Object.values(payload.errors || {}).flat().join(' ')
                    || 'Please review the Work Plan information.';

                return;
            }

            if (!response.ok) throw new Error('The Word file could not be generated. Please try again.');

            const disposition = response.headers.get('Content-Disposition') || '';
            const filenameMatch = disposition.match(/filename="?([^";]+)"?/i);
            const filename = filenameMatch?.[1] || 'attachment-a-work-plan.docx';
            const downloadUrl = URL.createObjectURL(await response.blob());
            const link = document.createElement('a');

            link.href = downloadUrl;
            link.download = filename;
            document.body.appendChild(link);
            link.click();
            link.remove();
            URL.revokeObjectURL(downloadUrl);
        } catch (error) {
            this.downloadError = error instanceof Error
                ? error.message
                : 'The Word file could not be generated. Please try again.';
        } finally {
            this.downloadLoading = false;
        }
    },

    printPreview() {
        if (!this.previewReady || !this.$refs.previewFrame?.contentWindow) return;

        this.$refs.previewFrame.contentWindow.focus();
        this.$refs.previewFrame.contentWindow.print();
    },
}));

Alpine.data('proposalDraftLineItemBudget', (config = {}) => ({
    nextId: 0,
    staff: [],
    customMooeItems: [],
    customCoItems: [],
    amounts: {},
    leaderCampus: '',
    leaderCollege: '',
    overrideMooe: false,
    overrideCo: false,
    overrideProject: false,
    mooeOverride: '',
    coOverride: '',
    projectOverride: '',
    levelOfCall: '',
    approvalBody: '',
    resolutionNumber: '',
    resolutionYear: '',
    validationMessage: '',
    previewHtml: '',
    previewError: '',
    previewLoading: false,
    previewReady: false,
    downloadError: '',
    downloadLoading: false,

    init() {
        const data = config.initialData && typeof config.initialData === 'object' ? config.initialData : {};
        this.leaderCampus = String(data.leader_campus ?? config.defaultCampus ?? 'ARASOF-Nasugbu');
        this.leaderCollege = String(data.leader_college ?? '');
        this.amounts = data.amounts && typeof data.amounts === 'object' ? { ...data.amounts } : {};
        this.staff = Array.isArray(data.staff) && data.staff.length
            ? data.staff.map((member) => this.newStaff(member))
            : [this.newStaff()];
        this.customMooeItems = Array.isArray(data.custom_mooe_items)
            ? data.custom_mooe_items.map((item) => this.newBudgetItem(item))
            : [];
        this.customCoItems = Array.isArray(data.custom_co_items)
            ? data.custom_co_items.map((item) => this.newBudgetItem(item))
            : [];
        this.mooeOverride = data.mooe_total_override ?? '';
        this.coOverride = data.co_total_override ?? '';
        this.projectOverride = data.project_total_override ?? '';
        this.overrideMooe = this.hasValue(this.mooeOverride);
        this.overrideCo = this.hasValue(this.coOverride);
        this.overrideProject = this.hasValue(this.projectOverride);
        this.levelOfCall = String(data.level_of_call ?? '');
        this.approvalBody = String(data.approval_body ?? '');
        this.resolutionNumber = String(data.resolution_number ?? '');
        this.resolutionYear = String(data.resolution_year ?? '');
    },

    newStaff(values = {}) {
        this.nextId += 1;

        return {
            id: this.nextId,
            name: String(values.name ?? ''),
            campus: String(values.campus ?? config.defaultCampus ?? 'ARASOF-Nasugbu'),
            college: String(values.college ?? ''),
        };
    },

    newBudgetItem(values = {}) {
        this.nextId += 1;

        return {
            id: this.nextId,
            particular: String(values.particular ?? ''),
            amount: values.amount ?? '',
        };
    },

    addStaff() {
        this.staff.push(this.newStaff());
    },

    removeStaff(index) {
        this.staff.splice(index, 1);

        if (this.staff.length === 0) this.staff.push(this.newStaff());
    },

    addCustomItem(section) {
        const property = section === 'mooe' ? 'customMooeItems' : 'customCoItems';
        this[property].push(this.newBudgetItem());
    },

    removeCustomItem(section, index) {
        const property = section === 'mooe' ? 'customMooeItems' : 'customCoItems';
        this[property].splice(index, 1);
    },

    hasValue(value) {
        return value !== '' && value !== null && value !== undefined;
    },

    numeric(value) {
        if (!this.hasValue(value)) return 0;

        const number = Number(value);

        return Number.isFinite(number) ? number : 0;
    },

    computedSectionTotal(section) {
        const itemKeys = (config.sections?.[section]?.items || []).map((item) => item.key);
        const customItems = section === 'mooe' ? this.customMooeItems : this.customCoItems;

        return itemKeys.reduce((total, key) => total + this.numeric(this.amounts[key]), 0)
            + customItems.reduce((total, item) => total + this.numeric(item.amount), 0);
    },

    sectionTotal(section) {
        if (section === 'mooe' && this.overrideMooe) return this.numeric(this.mooeOverride);
        if (section === 'co' && this.overrideCo) return this.numeric(this.coOverride);

        return this.computedSectionTotal(section);
    },

    projectTotal() {
        return this.overrideProject
            ? this.numeric(this.projectOverride)
            : this.sectionTotal('mooe') + this.sectionTotal('co');
    },

    formatMoney(value) {
        return new Intl.NumberFormat('en-PH', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }).format(this.numeric(value));
    },

    validateForm() {
        this.validationMessage = '';
        const fields = Array.from(this.$refs.form?.querySelectorAll('input, textarea, select') || []);
        const invalidField = fields.find((field) => !field.disabled && !field.checkValidity());

        if (!invalidField) return true;

        invalidField.reportValidity();
        invalidField.focus();

        return false;
    },

    formData() {
        const formData = new FormData(this.$refs.form);
        formData.delete('_method');

        return formData;
    },

    async generatePreview() {
        if (!this.validateForm()) return;

        this.previewError = '';
        this.downloadError = '';
        this.previewLoading = true;
        this.previewReady = false;

        try {
            const response = await fetch(config.previewUrl, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': config.csrfToken },
                body: this.formData(),
            });

            if (response.status === 422) {
                const payload = await response.json();
                this.validationMessage = Object.values(payload.errors || {}).flat().join(' ')
                    || 'Please review the Line-Item Budget information.';
                this.previewHtml = '';

                return;
            }

            if (!response.ok) throw new Error('The preview could not be generated. Please try again.');
            this.previewHtml = await response.text();
        } catch (error) {
            this.previewHtml = '';
            this.previewError = error instanceof Error ? error.message : 'The preview could not be generated.';
        } finally {
            this.previewLoading = false;
        }
    },

    async downloadDocument() {
        if (!this.validateForm()) return;

        this.downloadError = '';
        this.downloadLoading = true;

        try {
            const response = await fetch(config.downloadUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'X-CSRF-TOKEN': config.csrfToken,
                },
                body: this.formData(),
            });

            if (response.status === 422) {
                const payload = await response.json();
                this.validationMessage = Object.values(payload.errors || {}).flat().join(' ')
                    || 'Please review the Line-Item Budget information.';

                return;
            }

            if (!response.ok) throw new Error('The Word file could not be generated. Please try again.');
            const disposition = response.headers.get('Content-Disposition') || '';
            const filenameMatch = disposition.match(/filename="?([^";]+)"?/i);
            const filename = filenameMatch?.[1] || 'attachment-b-line-item-budget.docx';
            const downloadUrl = URL.createObjectURL(await response.blob());
            const link = document.createElement('a');
            link.href = downloadUrl;
            link.download = filename;
            document.body.appendChild(link);
            link.click();
            link.remove();
            URL.revokeObjectURL(downloadUrl);
        } catch (error) {
            this.downloadError = error instanceof Error ? error.message : 'The Word file could not be generated.';
        } finally {
            this.downloadLoading = false;
        }
    },

    printPreview() {
        if (!this.previewReady || !this.$refs.previewFrame?.contentWindow) return;

        this.$refs.previewFrame.contentWindow.focus();
        this.$refs.previewFrame.contentWindow.print();
    },
}));

Alpine.data('proposalDraftCurriculumVitae', (config = {}) => ({
    nextId: 0,
    people: [],
    validationMessage: '',
    previewHtml: '',
    previewError: '',
    previewLoading: false,
    previewReady: false,
    downloadError: '',
    downloadLoading: false,

    init() {
        const initialPeople = Array.isArray(config.initialPeople) ? config.initialPeople : [];
        this.people = initialPeople.length > 0
            ? initialPeople.map((person) => this.newPerson(person))
            : [this.newPerson()];
    },

    newPerson(values = {}) {
        this.nextId += 1;
        const person = {
            id: this.nextId,
            last_name: String(values.last_name ?? ''),
            first_name: String(values.first_name ?? ''),
            middle_name: String(values.middle_name ?? ''),
            agency: String(values.agency ?? ''),
            gender: String(values.gender ?? ''),
            birthday: String(values.birthday ?? ''),
            street: String(values.street ?? ''),
            barangay: String(values.barangay ?? ''),
            municipality: String(values.municipality ?? ''),
            province: String(values.province ?? ''),
            landline: String(values.landline ?? ''),
            cellphone: String(values.cellphone ?? ''),
            email: String(values.email ?? ''),
        };

        Object.keys(config.sections || {}).forEach((sectionKey) => {
            const rows = Array.isArray(values[sectionKey]) ? values[sectionKey] : [];
            person[sectionKey] = rows.map((row) => this.newSectionRow(sectionKey, row));
        });

        return person;
    },

    newSectionRow(sectionKey, values = {}) {
        this.nextId += 1;
        const row = { id: this.nextId };

        (config.sections?.[sectionKey]?.fields || []).forEach((field) => {
            row[field.key] = values[field.key] ?? '';
        });

        return row;
    },

    addPerson() {
        this.people.push(this.newPerson());
        this.$nextTick(() => this.focusPerson(this.people.length - 1));
    },

    removePerson(index) {
        if (this.people.length === 1) return;

        this.people.splice(index, 1);
    },

    personLabel(person) {
        const name = [person.first_name, person.middle_name, person.last_name]
            .map((part) => String(part || '').trim())
            .filter(Boolean)
            .join(' ');

        return name || 'New team member';
    },

    focusPerson(index) {
        const target = this.$root.querySelector(`[data-person-index="${index}"]`);

        if (!target) return;

        const behavior = window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth';
        target.scrollIntoView({ behavior, block: 'start' });
    },

    addSectionRow(personIndex, sectionKey) {
        this.people[personIndex][sectionKey].push(this.newSectionRow(sectionKey));
    },

    removeSectionRow(personIndex, sectionKey, rowIndex) {
        this.people[personIndex][sectionKey].splice(rowIndex, 1);
    },

    validateForm() {
        this.validationMessage = '';
        const fields = Array.from(this.$refs.form?.querySelectorAll('input, textarea, select') || []);
        const invalidField = fields.find((field) => !field.disabled && !field.checkValidity());

        if (!invalidField) return true;

        invalidField.closest('details')?.setAttribute('open', '');
        invalidField.focus();
        invalidField.reportValidity();

        return false;
    },

    formData() {
        const formData = new FormData(this.$refs.form);
        formData.delete('_method');

        return formData;
    },

    async generatePreview() {
        if (!this.validateForm()) return;

        this.previewError = '';
        this.downloadError = '';
        this.previewLoading = true;
        this.previewReady = false;

        try {
            const response = await fetch(config.previewUrl, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': config.csrfToken },
                body: this.formData(),
            });

            if (response.status === 422) {
                const payload = await response.json();
                this.validationMessage = Object.values(payload.errors || {}).flat().join(' ')
                    || 'Please review the Curriculum Vitae information.';
                this.previewHtml = '';

                return;
            }

            if (!response.ok) throw new Error('The CV preview could not be generated. Please try again.');
            this.previewHtml = await response.text();
        } catch (error) {
            this.previewHtml = '';
            this.previewError = error instanceof Error ? error.message : 'The CV preview could not be generated.';
        } finally {
            this.previewLoading = false;
        }
    },

    async downloadDocument() {
        if (!this.validateForm()) return;

        this.downloadError = '';
        this.downloadLoading = true;

        try {
            const response = await fetch(config.downloadUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'X-CSRF-TOKEN': config.csrfToken,
                },
                body: this.formData(),
            });

            if (response.status === 422) {
                const payload = await response.json();
                this.validationMessage = Object.values(payload.errors || {}).flat().join(' ')
                    || 'Please review the Curriculum Vitae information.';

                return;
            }

            if (!response.ok) throw new Error('The Word file could not be generated. Please try again.');
            const disposition = response.headers.get('Content-Disposition') || '';
            const filenameMatch = disposition.match(/filename="?([^";]+)"?/i);
            const filename = filenameMatch?.[1] || 'attachment-c-curriculum-vitae.docx';
            const downloadUrl = URL.createObjectURL(await response.blob());
            const link = document.createElement('a');
            link.href = downloadUrl;
            link.download = filename;
            document.body.appendChild(link);
            link.click();
            link.remove();
            URL.revokeObjectURL(downloadUrl);
        } catch (error) {
            this.downloadError = error instanceof Error ? error.message : 'The Word file could not be generated.';
        } finally {
            this.downloadLoading = false;
        }
    },

    printPreview() {
        if (!this.previewReady || !this.$refs.previewFrame?.contentWindow) return;

        this.$refs.previewFrame.contentWindow.focus();
        this.$refs.previewFrame.contentWindow.print();
    },
}));

Alpine.start();
