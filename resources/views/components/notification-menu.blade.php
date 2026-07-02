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

    <div x-cloak x-show="toast" x-transition class="fixed right-5 top-20 z-[60] w-80 max-w-[calc(100vw-2rem)] rounded-2xl border border-gray-200 bg-white p-4 shadow-2xl dark:border-slate-700 dark:bg-slate-900">
        <p class="text-xs font-black text-gray-900 dark:text-white" x-text="toast?.title"></p>
        <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-slate-400" x-text="toast?.message"></p>
    </div>
</div>
