<x-app-layout>
    <div
        x-data="{
            category: 'all',
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
    >
        <section class="overflow-hidden rounded-3xl bg-gray-950 text-white shadow-xl dark:border dark:border-slate-700">
            <div class="relative px-6 py-7 sm:px-8 lg:flex lg:items-center lg:justify-between lg:gap-8">
                <div class="absolute inset-y-0 right-0 hidden w-72 bg-gradient-to-l from-red-700/30 to-transparent lg:block"></div>
                <div class="relative">
                    <p class="text-xs font-black uppercase tracking-[0.22em] text-red-400">ATHENA activity center</p>
                    <h1 class="mt-2 text-3xl font-black tracking-tight sm:text-4xl">Notification inbox</h1>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-gray-300">Find invitations, accepted collaborations, proposal reviews, research-call reminders, and project updates in one place.</p>
                </div>
                <div class="relative mt-6 flex flex-wrap items-center gap-3 lg:mt-0 lg:justify-end">
                    <div class="rounded-2xl border border-white/15 bg-white/10 px-4 py-3 backdrop-blur">
                        <p class="text-[10px] font-black uppercase tracking-wider text-gray-400">Unread</p>
                        <p class="mt-0.5 text-2xl font-black text-white">{{ $unreadCount }}</p>
                    </div>
                    <div class="rounded-2xl border border-white/15 bg-white/10 px-4 py-3 backdrop-blur">
                        <p class="text-[10px] font-black uppercase tracking-wider text-gray-400">All activity</p>
                        <p class="mt-0.5 text-2xl font-black text-white">{{ $notificationItems->count() }}</p>
                    </div>
                    @if ($unreadCount > 0)
                        <form method="POST" action="{{ route('notifications.read-all') }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="inline-flex h-12 items-center justify-center rounded-xl bg-red-600 px-5 text-sm font-black text-white transition hover:bg-red-500 focus:outline-none focus:ring-2 focus:ring-red-400 focus:ring-offset-2 focus:ring-offset-gray-950">Mark all read</button>
                        </form>
                    @endif
                </div>
            </div>
        </section>

        <div class="mt-6 grid gap-6 lg:grid-cols-[17rem_minmax(0,1fr)]">
            <aside class="rounded-2xl border border-gray-200 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-900 lg:sticky lg:top-24 lg:self-start" aria-label="Notification categories">
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
                            $categoryDot = match ($item['category']) {
                                'invitations' => 'bg-red-600',
                                'collaboration' => 'bg-gray-950 dark:bg-white',
                                'reviews' => 'bg-amber-500',
                                'research_calls' => 'bg-blue-500',
                                'projects' => 'bg-emerald-500',
                                default => 'bg-gray-400',
                            };
                        @endphp
                        <form
                            method="POST"
                            action="{{ route('notifications.open', $item['id']) }}"
                            data-notification-item
                            data-notification-category="{{ $item['category'] }}"
                            data-category="{{ $item['category'] }}"
                            data-read-state="{{ $item['read_at'] ? 'read' : 'unread' }}"
                            data-search="{{ $item['search_text'] }}"
                            x-show="matches($el)"
                            x-transition.opacity
                        >
                            @csrf
                            <button
                                type="submit"
                                class="group flex w-full items-start gap-3 border-b border-gray-100 px-4 py-4 text-left transition last:border-0 sm:gap-4 sm:px-5 {{ $item['read_at'] ? 'bg-gray-100/90 hover:bg-gray-200/90 dark:border-slate-800 dark:bg-slate-950/70 dark:hover:bg-slate-800/80' : 'bg-white shadow-[inset_4px_0_0_0_#dc2626] hover:bg-red-50 dark:border-slate-800 dark:bg-slate-900 dark:shadow-[inset_4px_0_0_0_#ef4444] dark:hover:bg-red-950/20' }}"
                            >
                                <span class="mt-1.5 h-3 w-3 shrink-0 rounded-full {{ $item['read_at'] ? 'bg-gray-300 dark:bg-slate-600' : $categoryDot }}"></span>
                                <span class="min-w-0 flex-1">
                                    <span class="flex flex-wrap items-center gap-2">
                                        <span class="text-sm {{ $item['read_at'] ? 'font-bold text-gray-500 dark:text-slate-400' : 'font-black text-gray-950 dark:text-white' }}">{{ $item['data']['title'] ?? 'ATHENA notification' }}</span>
                                        <span class="rounded-full px-2 py-0.5 text-[9px] font-black uppercase tracking-wider {{ $item['read_at'] ? 'bg-gray-200 text-gray-500 dark:bg-slate-800 dark:text-slate-400' : 'bg-red-100 text-red-700 dark:bg-red-950/50 dark:text-red-300' }}">{{ $item['category_label'] }}</span>
                                        @if (! $item['read_at'])
                                            <span class="rounded-full bg-red-600 px-2 py-0.5 text-[9px] font-black uppercase tracking-wider text-white">New</span>
                                        @endif
                                    </span>
                                    <span class="mt-1.5 block text-sm leading-6 {{ $item['read_at'] ? 'text-gray-400 dark:text-slate-500' : 'text-gray-600 dark:text-slate-300' }}">{{ $item['data']['message'] ?? '' }}</span>
                                    <span class="mt-2 flex flex-wrap items-center gap-2 text-[10px] font-bold uppercase tracking-wider text-gray-400">
                                        <time datetime="{{ $item['created_at_iso'] }}">{{ $item['created_at'] }}</time>
                                        @if (($item['data']['action_url'] ?? null) && ! ($item['data']['action_completed'] ?? false))
                                            <span class="text-red-600 dark:text-red-400">Action required</span>
                                        @endif
                                    </span>
                                </span>
                                <span class="mt-1 hidden shrink-0 items-center gap-1 text-xs font-black text-red-600 transition group-hover:translate-x-0.5 dark:text-red-400 sm:flex">
                                    Open
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
                                </span>
                            </button>
                        </form>
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