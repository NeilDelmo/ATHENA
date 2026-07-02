

import './echo';
import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.data('notificationMenu', (config) => ({
    open: false,
    notifications: config.notifications,
    unreadCount: config.unreadCount,
    toast: null,
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
            this.notifications = payload.notifications;
            this.unreadCount = payload.unread_count;
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
        }

        this.toast = item.data;
        window.setTimeout(() => {
            if (this.toast === item.data) this.toast = null;
        }, 6000);
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

Alpine.start();

const authenticatedUserId = document.body.dataset.authUserId;

if (authenticatedUserId && window.Echo) {
    window.Echo.private(`App.Models.User.${authenticatedUserId}`)
        .notification((notification) => {
            window.dispatchEvent(new CustomEvent('athena-notification', { detail: notification }));
        });
}
