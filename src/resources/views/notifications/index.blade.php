<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Notification inbox" subtitle="Find invitations, project team activity, proposal reviews, research-call reminders, and project updates in one place.">
            @if ($unreadCount > 0)
                <x-slot:actions>
                    <form method="POST" action="{{ route('notifications.read-all') }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="inline-flex h-12 items-center justify-center rounded-xl bg-red-600 px-5 text-sm font-black text-white transition hover:bg-red-500 focus:outline-none focus:ring-2 focus:ring-red-400 focus:ring-offset-2 focus:ring-offset-gray-950">Mark all read</button>
                    </form>
                </x-slot:actions>
            @endif
        </x-page-header>
    </x-slot>
    <div
        x-data="{
            category: 'all',
            inboxView: 'activity',
            pendingActions: @js($pendingActions),
            readState: 'all',
            query: '',
            matches(item) {
                const categoryMatches = this.category === 'all' || item.dataset.category === this.category;
                const readMatches = this.readState === 'all' || item.dataset.readState === this.readState;
                const searchMatches = item.dataset.search.includes(this.query.trim().toLowerCase());
                return categoryMatches && readMatches && searchMatches;
            },
            visibleCount() {
                const items = this.$refs.notificationList?.querySelectorAll('[data-notification-item]') || [];
                return Array.from(items).filter((item) => this.matches(item)).length;
            },
        }"
        class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8"
        data-notification-inbox
        x-on:athena-workspace-actions.window="if ($event.detail.workspace === @js(auth()->user()->activeWorkspace())) pendingActions = $event.detail.actions"
    >
        <section class="overflow-hidden rounded-3xl bg-gray-950 text-white shadow-xl dark:border dark:border-slate-700">
            <div class="relative px-6 py-7 sm:px-8 lg:flex lg:items-center lg:justify-between lg:gap-8">
                <div class="absolute inset-y-0 right-0 hidden w-72 bg-gradient-to-l from-red-700/30 to-transparent lg:block"></div>
                <div class="relative mt-6 flex flex-wrap items-center gap-3 lg:mt-0 lg:justify-end">
                    <div class="rounded-2xl border border-white/15 bg-white/10 px-4 py-3">
                        <p class="text-[10px] font-black uppercase tracking-wider text-gray-400">Needs your action</p>
                        <p class="mt-0.5 text-2xl font-black text-white" data-pending-action-count x-text="pendingActions.length">{{ count($pendingActions) }}</p>
                    </div>
                    <div class="rounded-2xl border border-white/15 bg-white/10 px-4 py-3 backdrop-blur">
                        <p class="text-[10px] font-black uppercase tracking-wider text-gray-400">Unread notifications</p>
                        <p class="mt-0.5 text-2xl font-black text-white">{{ $unreadCount }}</p>
                    </div>
                    <div class="rounded-2xl border border-white/15 bg-white/10 px-4 py-3 backdrop-blur">
                        <p class="text-[10px] font-black uppercase tracking-wider text-gray-400">Related update groups</p>
                        <p class="mt-0.5 text-2xl font-black text-white">{{ $notificationItems->count() }}</p>
                    </div>
                </div>
            </div>
        </section>

        <div class="mt-6 flex flex-wrap gap-2" aria-label="Inbox view">
            <button type="button" @click="inboxView = 'actions'" :aria-pressed="inboxView === 'actions'" :class="inboxView === 'actions' ? 'bg-brand text-white' : 'bg-white text-slate-700 dark:bg-slate-900 dark:text-slate-200'" class="min-h-11 rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand dark:border-slate-700">Needs your action <span class="ml-1 tabular-nums" x-text="pendingActions.length">{{ count($pendingActions) }}</span></button>
            <button type="button" @click="inboxView = 'activity'" :aria-pressed="inboxView === 'activity'" :class="inboxView === 'activity' ? 'bg-brand text-white' : 'bg-white text-slate-700 dark:bg-slate-900 dark:text-slate-200'" class="min-h-11 rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand dark:border-slate-700">All activity</button>
        </div>
        <section x-show="inboxView === 'actions'" x-cloak data-workspace-action-items class="mt-4 overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900" aria-labelledby="workspace-action-heading">
            <header class="border-b border-slate-200 px-5 py-4 dark:border-slate-700"><h2 id="workspace-action-heading" class="font-bold text-slate-950 dark:text-white">Needs your action</h2><p class="mt-1 text-sm leading-6 text-slate-600 dark:text-slate-300">Current work for your {{ auth()->user()->activeWorkspaceLabel() }} workspace. Reading a notification does not complete it; finished work leaves this list automatically.</p></header>
            <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                <template x-for="action in pendingActions" :key="action.key">
                    <li><a :href="action.url" :data-workspace-action="action.key" class="flex min-h-16 items-center justify-between gap-4 px-5 py-4 hover:bg-red-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-inset focus-visible:outline-brand dark:hover:bg-red-950/30"><span class="min-w-0"><span class="block text-sm font-semibold text-brand dark:text-red-200" x-text="action.label"></span><span class="mt-1 block break-words text-sm leading-6 text-slate-700 dark:text-slate-300" x-text="action.title"></span></span><span aria-hidden="true" class="shrink-0 text-brand dark:text-red-300">→</span></a></li>
                </template>
                <li x-show="pendingActions.length === 0" class="px-5 py-10 text-center"><p class="font-semibold text-slate-900 dark:text-white">No work awaiting your action</p><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Your notification history remains in All activity.</p></li>
            </ul>
        </section>

        <div x-show="inboxView === 'activity'" class="mt-6 grid gap-6 lg:grid-cols-[17rem_minmax(0,1fr)]">
            <aside class="min-w-0 rounded-2xl border border-gray-200 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-900 lg:sticky lg:top-24 lg:self-start" aria-label="Notification categories">
                <p class="px-3 pb-2 pt-1 text-[10px] font-black uppercase tracking-[0.18em] text-gray-400">Inbox categories</p>
                <div class="flex gap-2 overflow-x-auto pb-1 lg:flex-col lg:overflow-visible">
                    <button
                        type="button"
                        @click="category = 'all'"
                        class="flex min-w-max items-center justify-between gap-4 rounded-xl px-3 py-2.5 text-left text-sm font-bold transition lg:w-full"
                        :class="category === 'all' ? 'bg-gray-950 text-white dark:bg-white dark:text-gray-950' : 'text-gray-600 hover:bg-gray-100 dark:text-slate-300 dark:hover:bg-slate-800'"
                    >
                        <span class="flex items-center gap-2.5">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 9v.906a2.25 2.25 0 01-2.25 2.25h-5.25a2.25 2.25 0 00-2.25 2.25 2.25 2.25 0 01-2.25 2.25H4.5a2.25 2.25 0 01-2.25-2.25V9m19.5 0A2.25 2.25 0 0019.5 6.75h-15A2.25 2.25 0 002.25 9m19.5 0v9a2.25 2.25 0 01-2.25 2.25h-15A2.25 2.25 0 012.25 18V9" /></svg>
                            Inbox
                        </span>
                        <span class="rounded-full bg-white/15 px-2 py-0.5 text-[10px] font-black dark:bg-gray-950/10">{{ $notificationItems->count() }}</span>
                    </button>

                    @foreach ($categories as $category)
                        <button
                            type="button"
                            @click="category = '{{ $category['key'] }}'"
                            class="flex min-w-max items-center justify-between gap-4 rounded-xl px-3 py-2.5 text-left text-sm font-bold transition lg:w-full"
                            :class="category === '{{ $category['key'] }}' ? 'bg-red-50 text-red-700 ring-1 ring-red-100 dark:bg-red-950/30 dark:text-red-300 dark:ring-red-900/50' : 'text-gray-600 hover:bg-gray-100 dark:text-slate-300 dark:hover:bg-slate-800'"
                            title="{{ $category['description'] }}"
                        >
                            <span class="flex items-center gap-2.5">
                                <span class="h-2.5 w-2.5 rounded-full {{ match ($category['key']) {
                                    'invitations' => 'bg-red-600',
                                    'collaboration' => 'bg-gray-950 dark:bg-white',
                                    'reviews' => 'bg-amber-500',
                                    'research_calls' => 'bg-blue-500',
                                    'projects' => 'bg-emerald-500',
                                    default => 'bg-gray-400',
                                } }}"></span>
                                {{ $category['label'] }}
                            </span>
                            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-black text-gray-500 dark:bg-slate-800 dark:text-slate-400">{{ $category['count'] }}</span>
                        </button>
                    @endforeach
                </div>
            </aside>

            <section class="min-w-0">
                <div class="rounded-2xl border border-gray-200 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-900 sm:p-4">
                    <div class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
                        <div class="flex rounded-xl bg-gray-100 p-1 dark:bg-slate-800" aria-label="Read status filter">
                            @foreach (['all' => 'All', 'unread' => 'Unread', 'read' => 'Read'] as $value => $label)
                                <button
                                    type="button"
                                    @click="readState = '{{ $value }}'"
                                    class="flex-1 rounded-lg px-4 py-2 text-xs font-black transition sm:flex-none"
                                    :class="readState === '{{ $value }}' ? 'bg-white text-gray-950 shadow-sm dark:bg-slate-700 dark:text-white' : 'text-gray-500 hover:text-gray-800 dark:text-slate-400 dark:hover:text-white'"
                                >{{ $label }}</button>
                            @endforeach
                        </div>
                        <label class="relative block min-w-0 flex-1 xl:max-w-md">
                            <span class="sr-only">Search notifications</span>
                            <svg class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35m1.6-5.4a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                            <input x-model.debounce.150ms="query" type="search" placeholder="Search notifications" class="w-full rounded-xl border-gray-200 bg-white py-2.5 pl-10 pr-4 text-sm font-semibold text-gray-800 placeholder:text-gray-400 focus:border-red-500 focus:ring-red-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                        </label>
                    </div>
                </div>

                <div x-ref="notificationList" class="mt-4 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
                    @forelse ($notificationItems as $item)
                        @php
                            $readStateDot = $item['read_at'] ? 'bg-slate-300 dark:bg-slate-600' : 'bg-red-600 dark:bg-red-400';
                        @endphp
                        <article
                            data-notification-item
                            data-notification-category="{{ $item['category'] }}"
                            data-category="{{ $item['category'] }}"
                            data-read-state="{{ $item['read_at'] ? 'read' : 'unread' }}"
                            data-search="{{ $item['search_text'] }}"
                            x-show="matches($el)"
                            x-transition.opacity
                        >
                            <form method="POST" action="{{ route('notifications.open', $item['id']) }}">
                            @csrf
                            @foreach ($item['notification_ids'] as $notificationId)
                                <input type="hidden" name="notification_ids[]" value="{{ $notificationId }}">
                            @endforeach
                            <button
                                type="submit"
                                class="group flex w-full items-start gap-3 border-b border-slate-100 px-4 py-4 text-left transition-colors last:border-0 dark:border-slate-800 sm:gap-4 sm:px-5 {{ $item['read_at'] ? 'bg-white hover:bg-slate-50 dark:bg-slate-900 dark:hover:bg-slate-800/70' : 'bg-red-50/40 hover:bg-red-50 dark:bg-red-950/10 dark:hover:bg-red-950/20' }}"
                            >
                                <span class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full {{ $readStateDot }}"></span>
                                <span class="min-w-0 flex-1">
                                    <span class="flex flex-wrap items-center gap-2">
                                        <span class="text-sm {{ $item['read_at'] ? 'font-semibold text-slate-600 dark:text-slate-300' : 'font-bold text-slate-900 dark:text-white' }}">{{ $item['data']['title'] ?? 'ATHENA notification' }}</span>
                                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[9px] font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-400">{{ $item['category_label'] }}</span>
                                        <span class="text-xs font-semibold {{ $item['needs_action'] ? 'text-red-700 dark:text-red-300' : 'text-slate-500 dark:text-slate-400' }}">{{ $item['needs_action'] ? 'Needs your action' : 'Information' }}</span>
                                    </span>
                                    <span class="mt-1.5 block text-sm leading-6 {{ $item['read_at'] ? 'text-slate-500 dark:text-slate-400' : 'text-slate-600 dark:text-slate-300' }}">{{ $item['data']['message'] ?? '' }}</span>
                                    <span class="mt-2 flex flex-wrap items-center gap-2 text-[10px] font-bold uppercase tracking-wider text-gray-400">
                                        <time datetime="{{ $item['created_at_iso'] }}">{{ $item['created_at'] }}</time>
                                        <span>{{ $item['update_count'] }} {{ Str::plural('update', $item['update_count']) }} · {{ $item['unread_count'] }} unread notifications</span>
                                    </span>
                                </span>
                                <span class="mt-1 hidden shrink-0 items-center gap-1 text-xs font-black text-red-600 transition group-hover:translate-x-0.5 dark:text-red-400 sm:flex">
                                    Open
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
                                </span>
                            </button>
                            </form>
                            @foreach ($item['actions'] as $action)
                                <a href="{{ $action['url'] }}" class="mx-5 mb-3 inline-flex min-h-11 items-center rounded-lg px-3 text-sm font-semibold text-red-700 hover:bg-red-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-red-600 dark:text-red-300 dark:hover:bg-red-950/30">{{ $action['label'] }}</a>
                            @endforeach
                            @if ($item['update_count'] > 1)
                                <details class="border-b border-slate-100 px-5 pb-4 dark:border-slate-800" data-notification-history>
                                    <summary class="disclosure-summary py-3 text-sm font-semibold text-slate-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-red-600 dark:text-slate-200">View all {{ $item['update_count'] }} updates</summary>
                                    <ol class="space-y-3 border-l border-slate-200 pl-4 dark:border-slate-700">
                                        @foreach ($item['history'] as $update)
                                            <li>
                                                <form method="POST" action="{{ route('notifications.open', $update['id']) }}">
                                                    @csrf
                                                    <button type="submit" class="w-full rounded-lg p-2 text-left hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-red-600 dark:hover:bg-slate-800">
                                                        <span class="block text-sm font-semibold text-slate-900 dark:text-white">{{ $update['data']['title'] ?? 'ATHENA notification' }}</span>
                                                        <span class="mt-1 block text-sm leading-6 text-slate-600 dark:text-slate-300">{{ $update['data']['message'] ?? '' }}</span>
                                                        <span class="mt-1 block text-xs text-slate-500 dark:text-slate-400"><time datetime="{{ $update['created_at_iso'] }}">{{ $update['created_at'] }}</time> · {{ $update['read_at'] ? 'Read' : 'Unread' }} · Open update</span>
                                                    </button>
                                                </form>
                                            </li>
                                        @endforeach
                                    </ol>
                                </details>
                            @endif
                        </article>
                    @empty
                        <div class="px-6 py-16 text-center">
                            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-100 text-gray-400 dark:bg-slate-800 dark:text-slate-500">
                                <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" /></svg>
                            </div>
                            <p class="mt-4 text-base font-black text-gray-900 dark:text-white">Your inbox is clear</p>
                            <p class="mt-1 text-sm text-gray-500 dark:text-slate-400">New ATHENA activity will appear here.</p>
                        </div>
                    @endforelse

                    @if ($notificationItems->isNotEmpty())
                        <div x-cloak x-show="visibleCount() === 0" class="px-6 py-16 text-center">
                            <p class="text-base font-black text-gray-900 dark:text-white">No matching notifications</p>
                            <p class="mt-1 text-sm text-gray-500 dark:text-slate-400">Try another category, read status, or search term.</p>
                            <button type="button" @click="category = 'all'; readState = 'all'; query = ''" class="mt-4 text-sm font-black text-red-600 hover:text-red-700 dark:text-red-400">Clear filters</button>
                        </div>
                    @endif
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
