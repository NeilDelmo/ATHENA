@php
    $notificationItems = Auth::user()->notifications()->latest()->limit(15)->get()->map(fn ($notification) => [
        'id' => $notification->id,
        'data' => $notification->data,
        'read_at' => $notification->read_at?->toIso8601String(),
        'created_at' => $notification->created_at->diffForHumans(),
    ])->values();
@endphp

<div
    x-data="notificationMenu({
        notifications: {{ Js::from($notificationItems) }},
        unreadCount: {{ Auth::user()->unreadNotifications()->count() }},
        indexUrl: {{ Js::from(route('notifications.index')) }},
        readUrl: {{ Js::from(route('notifications.read', '__ID__')) }},
        readAllUrl: {{ Js::from(route('notifications.read-all')) }},
    })"
    class="relative"
>
    <button
        type="button"
        @click="open = !open"
        class="relative inline-flex h-9 w-9 cursor-pointer items-center justify-center rounded-xl text-gray-500 transition duration-150 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-red-500 dark:text-slate-300 dark:hover:bg-slate-800"
        aria-label="Notifications"
        :aria-expanded="open"
        title="Notifications"
    >
        <span
            x-cloak
            x-show="unreadCount > 0"
            x-text="unreadCount > 99 ? '99+' : unreadCount"
            class="absolute -right-1 -top-1 flex min-h-5 min-w-5 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-black text-white ring-2 ring-white dark:ring-slate-900"
        ></span>
        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
        </svg>
    </button>

    <div
        x-cloak
        x-show="open"
        @click.outside="open = false"
        x-transition
        class="absolute right-0 z-50 mt-2 w-[22rem] max-w-[calc(100vw-2rem)] overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-2xl dark:border-slate-700 dark:bg-slate-900"
    >
        <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3 dark:border-slate-800">
            <div>
                <p class="text-sm font-black text-gray-900 dark:text-white">Notifications</p>
                <p class="text-[11px] font-semibold text-gray-400" x-text="unreadCount ? `${unreadCount} unread` : 'You are all caught up'"></p>
            </div>
            <button x-show="unreadCount > 0" @click="markAllRead" type="button" class="text-[11px] font-bold text-red-600 hover:text-red-700">Mark all read</button>
        </div>

        <div class="max-h-96 overflow-y-auto">
            <template x-if="notifications.length === 0">
                <div class="px-6 py-10 text-center">
                    <p class="text-sm font-bold text-gray-700 dark:text-slate-200">No notifications yet</p>
                    <p class="mt-1 text-xs text-gray-400">Proposal activity will appear here.</p>
                </div>
            </template>

            <template x-for="item in notifications" :key="item.id">
                <button @click="openNotification(item)" type="button" class="flex w-full gap-3 border-b border-gray-100 px-4 py-3 text-left transition last:border-0 hover:bg-gray-50 dark:border-slate-800 dark:hover:bg-slate-800/70" :class="item.read_at ? '' : 'bg-red-50/60 dark:bg-red-950/20'">
                    <span class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full" :class="levelClass(item.data.level)"></span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-xs font-black text-gray-800 dark:text-slate-100" x-text="item.data.title"></span>
                        <span class="mt-1 block text-xs leading-5 text-gray-500 dark:text-slate-400" x-text="item.data.message"></span>
                        <span class="mt-1 block text-[10px] font-semibold text-gray-400" x-text="item.created_at"></span>
                    </span>
                    <span x-show="!item.read_at" class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-red-600"></span>
                </button>
            </template>
        </div>
    </div>

    <div x-cloak class="pointer-events-none fixed right-4 top-20 z-[60] flex w-96 max-w-[calc(100vw-2rem)] flex-col gap-3" aria-live="polite" aria-atomic="false">
        <template x-for="toast in toasts" :key="toast.id">
            <div
                x-transition:enter="transition duration-300 ease-out"
                x-transition:enter-start="translate-x-6 opacity-0"
                x-transition:enter-end="translate-x-0 opacity-100"
                x-transition:leave="transition duration-200 ease-in"
                x-transition:leave-start="translate-x-0 opacity-100"
                x-transition:leave-end="translate-x-6 opacity-0"
                class="pointer-events-auto overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-2xl dark:border-slate-700 dark:bg-slate-900"
                role="status"
            >
                <div class="flex gap-3 p-4">
                    <span class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full" :class="levelClass(toast.item.data.level)"></span>
                    <button type="button" @click="openNotification(toast.item); dismissToast(toast.id)" class="min-w-0 flex-1 text-left">
                        <span class="block text-xs font-black text-gray-900 dark:text-white" x-text="toast.item.data.title"></span>
                        <span class="mt-1 block text-xs leading-5 text-gray-500 dark:text-slate-400" x-text="toast.item.data.message"></span>
                        <span class="mt-2 block text-[10px] font-bold uppercase tracking-wider text-red-600">Open notification</span>
                    </button>
                    <button type="button" @click="dismissToast(toast.id)" class="-mr-1 -mt-1 h-7 w-7 shrink-0 rounded-lg text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-slate-800" aria-label="Dismiss notification">
                        <svg class="mx-auto h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                <div class="h-1 origin-left animate-[shrink_7s_linear_forwards] bg-red-600"></div>
            </div>
        </template>
    </div>
</div>
