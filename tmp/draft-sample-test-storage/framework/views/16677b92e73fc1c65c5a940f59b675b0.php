<?php
    $visibleNotifications = Auth::user()->visibleNotifications();
    $notificationItems = $visibleNotifications->take(15)->map(fn ($notification) => [
        'id' => $notification->id,
        'data' => $notification->data,
        'read_at' => $notification->read_at?->toIso8601String(),
        'created_at' => $notification->created_at->diffForHumans(),
    ])->values();
    $unreadNotificationCount = $visibleNotifications->whereNull('read_at')->count();
?>

<div
    x-data="notificationMenu({
        notifications: <?php echo e(Js::from($notificationItems)); ?>,
        unreadCount: <?php echo e($unreadNotificationCount); ?>,
        workspace: <?php echo e(Js::from(Auth::user()->activeWorkspace())); ?>,
        indexUrl: <?php echo e(Js::from(route('notifications.index'))); ?>,
        openUrl: <?php echo e(Js::from(route('notifications.open', '__ID__'))); ?>,
        readUrl: <?php echo e(Js::from(route('notifications.read', '__ID__'))); ?>,
        readAllUrl: <?php echo e(Js::from(route('notifications.read-all'))); ?>,
    })"
    class="relative"
    data-notification-menu
    @click.outside="open = false"
    @keydown.escape.window="open = false"
>
    <button
        type="button"
        @click="open = !open; if (open) refresh()"
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
        x-transition
        class="fixed left-4 right-4 top-[7.5rem] z-[60] w-auto overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl dark:border-slate-700 dark:bg-slate-900 sm:absolute sm:left-auto sm:right-0 sm:top-full sm:mt-3 sm:w-[24rem] sm:max-w-[calc(100vw-2rem)]"
        role="dialog"
        aria-label="Notifications"
    >
        <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-3.5 dark:border-slate-800">
            <div>
                <p class="text-sm font-bold text-slate-900 dark:text-white">Notifications</p>
                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400" x-text="unreadCount === 0 ? 'All caught up' : `${unreadCount} unread`"></p>
            </div>
            <div class="flex shrink-0 items-center gap-2">
                <button x-show="unreadCount > 0" @click="markAllRead" type="button" class="rounded-lg px-2 py-1.5 text-xs font-semibold text-red-700 transition hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-950/30">Mark all read</button>
                <button @click="open = false" type="button" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 focus:outline-none focus:ring-2 focus:ring-red-500 dark:hover:bg-slate-800 dark:hover:text-slate-100" aria-label="Close notifications">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.3" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m6 6 12 12M18 6 6 18" /></svg>
                </button>
            </div>
        </div>

        <div class="max-h-[min(26rem,calc(100vh-12rem))] divide-y divide-slate-100 overflow-y-auto overscroll-contain dark:divide-slate-800">
            <template x-if="notifications.length === 0">
                <div class="px-6 py-10 text-center">
                    <p class="text-sm font-bold text-gray-700 dark:text-slate-200">No notifications yet</p>
                    <p class="mt-1 text-xs text-gray-400">Proposal activity will appear here.</p>
                </div>
            </template>

            <template x-for="item in notifications" :key="item.id">
                <form method="POST" :action="notificationOpenUrl(item)" @submit="openNotification($event, item)">
                    <?php echo csrf_field(); ?>
                    <button
                        type="submit"
                        class="group flex w-full items-start gap-3 px-4 py-3.5 text-left transition-colors focus:outline-none focus-visible:bg-slate-100 dark:focus-visible:bg-slate-800"
                        :class="item.read_at
                            ? 'bg-white hover:bg-slate-50 dark:bg-slate-900 dark:hover:bg-slate-800/70'
                            : 'bg-red-50/40 hover:bg-red-50 dark:bg-red-950/10 dark:hover:bg-red-950/20'"
                    >
                        <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full" :class="item.read_at ? 'bg-slate-300 dark:bg-slate-600' : 'bg-red-600 dark:bg-red-400'"></span>
                        <span class="min-w-0 flex-1">
                            <span class="flex items-start justify-between gap-3">
                                <span class="min-w-0 text-[13px] leading-5" :class="item.read_at ? 'font-semibold text-slate-600 dark:text-slate-300' : 'font-bold text-slate-900 dark:text-white'" x-text="item.data.title"></span>
                                <span class="shrink-0 pt-0.5 text-[10px] text-slate-400 dark:text-slate-500" x-text="item.created_at"></span>
                            </span>
                            <span class="mt-0.5 block line-clamp-2 text-xs leading-5" :class="item.read_at ? 'text-slate-500 dark:text-slate-400' : 'text-slate-600 dark:text-slate-300'" x-text="item.data.message"></span>
                            <span x-show="item.data.action_url && !item.data.action_completed" class="mt-1.5 inline-block text-[10px] font-semibold text-red-700 dark:text-red-300">Review invitation</span>
                        </span>
                    </button>
                </form>
            </template>
        </div>

        <div class="border-t border-slate-100 bg-white p-2 dark:border-slate-800 dark:bg-slate-900">
            <a href="<?php echo e(route('notifications.index')); ?>" @click="open = false" class="flex items-center justify-between rounded-xl px-3 py-2.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 hover:text-red-700 dark:text-slate-200 dark:hover:bg-slate-800 dark:hover:text-red-300">
                <span>View notification inbox</span>
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
            </a>
        </div>
    </div>

</div>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views/components/notification-menu.blade.php ENDPATH**/ ?>