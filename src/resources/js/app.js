
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

Alpine.store('researchAssistant', {
    drawerOpen: false,
    draft: '',
    isLoading: false,
    error: '',
    retryAfter: 0,
    abortController: null,
    copiedMessageId: null,
    nextMessageId: 2,
    quickPrompts: [
        'Refine my research question',
        'Recommend a methodology',
        'Outline my proposal',
    ],
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

    usePrompt(prompt) {
        this.draft = prompt;

        window.setTimeout(() => {
            const input = this.drawerOpen
                ? document.getElementById('research-assistant-drawer-message')
                : document.getElementById('research-assistant-message');
            input?.focus();
        });
    },

    async send() {
        const content = this.draft.trim();
        if (!content || this.isLoading || this.retryAfter > 0) return;

        this.messages.push({ id: this.nextMessageId++, role: 'user', content });
        this.draft = '';
        this.error = '';
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
                body: JSON.stringify({ messages: this.contextMessages() }),
            });

            const payload = await response.json().catch(() => ({}));

            if (!response.ok) {
                if (response.status === 419) {
                    throw new Error('Your session expired. Refresh the page and try again.');
                }

                if (response.status === 429) {
                    this.startRetryCountdown(Number(payload.retry_after) || 10);
                }

                throw new Error(payload.message || 'Athena could not respond. Please try again.');
            }

            this.messages.push({
                id: this.nextMessageId++,
                role: 'assistant',
                content: payload.reply,
                model: payload.model,
            });
        } catch (error) {
            if (error.name !== 'AbortError') {
                this.error = error.message || 'A network error interrupted the request.';
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
            this.error = 'The response could not be copied automatically.';
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
        this.retryAfter = 0;
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
