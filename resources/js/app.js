
import Alpine from 'alpinejs';

window.Alpine = Alpine;

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
