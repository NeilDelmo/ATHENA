@php
    $callTabs = [
        'active' => ['label' => 'Active', 'heading' => 'Active calls', 'calls' => $activeCalls],
        'upcoming' => ['label' => 'Upcoming', 'heading' => 'Upcoming calls', 'calls' => $upcomingCalls],
        'previous' => ['label' => 'Previous', 'heading' => 'Previous calls', 'calls' => $previousCalls],
    ];
    $allCalls = $activeCalls->concat($upcomingCalls)->concat($previousCalls)->values();
    $submittedForm = old('research_call_form');
    $initialPanel = $errors->any()
        ? ($submittedForm && $submittedForm !== 'create' ? 'edit-'.$submittedForm : 'create')
        : null;
    $initialTab = 'active';

    if ($submittedForm && $submittedForm !== 'create') {
        $submittedCall = $allCalls->firstWhere('id', (int) $submittedForm);
        $initialTab = match ($submittedCall?->lifecycleStatus()) {
            'open' => 'active',
            'draft', 'scheduled' => 'upcoming',
            default => 'previous',
        };
    }
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-[10px] font-black uppercase tracking-[0.22em] text-red-700 dark:text-red-300">Research Office</p>
                <h2 class="mt-1 text-2xl font-black tracking-tight text-slate-950 dark:text-white">Research Calls</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Manage calls, submission windows, funding limits, and published schedules.</p>
            </div>
            <a href="{{ route('announcement-images.index') }}" class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-black text-slate-700 shadow-sm transition hover:border-red-200 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-red-900 dark:hover:text-red-300 dark:focus:ring-offset-slate-950">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10.34 15.84 9.1 20.2a1.5 1.5 0 0 1-2.86-.9l1.13-3.75M5.5 14.5h-1A2.5 2.5 0 0 1 2 12v-1a2.5 2.5 0 0 1 2.5-2.5h1l9-4v14l-9-4Zm9-6a3 3 0 0 1 0 6" /></svg>
                Announcement studio
            </a>
        </div>
    </x-slot>

    <div
        x-data="{ activeTab: '{{ $initialTab }}', panel: @js($initialPanel), search: '' }"
        x-on:keydown.escape.window="panel = null"
        class="mx-auto max-w-[1600px] space-y-5"
        data-research-call-palette="red-black-white"
    >
        @if (session('success'))
            <div class="flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800 dark:border-emerald-900/70 dark:bg-emerald-950/30 dark:text-emerald-200" role="status">
                <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 4.5 4.5 10.5-10.5" /></svg>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900" aria-labelledby="research-call-workspace-title">
            <div class="relative overflow-hidden border-b border-rose-100 bg-gradient-to-br from-white via-rose-50/80 to-white px-5 py-6 text-slate-950 dark:border-slate-800 dark:from-slate-900 dark:via-red-950/25 dark:to-slate-900 dark:text-white sm:px-7">
                <div class="pointer-events-none absolute inset-y-0 right-0 w-2/5 bg-[radial-gradient(circle_at_center,rgba(185,28,28,0.28),transparent_68%)]" aria-hidden="true"></div>
                <div class="relative flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                    <div class="max-w-2xl">
                        <div class="flex items-center gap-2 text-[10px] font-black uppercase tracking-[0.22em] text-[#7A0019] dark:text-red-300"><span class="h-px w-7 bg-red-600"></span>Management workspace</div>
                        <h3 id="research-call-workspace-title" class="mt-3 text-2xl font-black tracking-tight sm:text-3xl">Research call directory</h3>
                        <p class="mt-2 max-w-xl text-sm leading-6 text-slate-600 dark:text-slate-300">Publish a call once, then track its schedule, proposal volume, and status from one organized workspace.</p>
                    </div>
                    <button type="button" x-on:click="panel = 'create'" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-red-600 px-5 py-3 text-sm font-black text-white shadow-lg shadow-red-950/20 transition hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 focus:ring-offset-white dark:focus:ring-offset-slate-900">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M12 5v14M5 12h14" /></svg>
                        Create new call
                    </button>
                </div>
            </div>

            <dl class="grid divide-y divide-slate-200 bg-slate-50/80 dark:divide-slate-800 dark:bg-slate-950/35 sm:grid-cols-2 sm:divide-x sm:divide-y-0 xl:grid-cols-4">
                @foreach ([
                    ['Open now', $activeCalls->count(), 'Currently accepting proposals', 'text-emerald-700 dark:text-emerald-300'],
                    ['Upcoming', $upcomingCalls->count(), 'Draft or scheduled calls', 'text-blue-700 dark:text-blue-300'],
                    ['Previous', $previousCalls->count(), 'Closed and ended calls', 'text-slate-700 dark:text-slate-200'],
                    ['Budget ceiling', 'PHP '.number_format($institutionalBudgetCeiling, 2), 'Fixed per proposal', 'text-red-700 dark:text-red-300'],
                ] as [$label, $value, $description, $valueClass])
                    <div class="px-5 py-4 sm:px-6">
                        <dt class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">{{ $label }}</dt>
                        <dd class="mt-1 text-xl font-black tracking-tight {{ $valueClass }}">{{ $value }}</dd>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $description }}</p>
                    </div>
                @endforeach
            </dl>
        </section>

        <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900" aria-label="Research call records">
            <div class="flex flex-col gap-4 border-b border-slate-200 px-4 py-4 dark:border-slate-800 sm:px-6 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex min-w-0 flex-1 gap-1 overflow-x-auto" role="tablist" aria-label="Research call status">
                    @foreach ($callTabs as $tabKey => $tab)
                        <button
                            type="button"
                            role="tab"
                            x-on:click="activeTab = '{{ $tabKey }}'"
                            x-bind:aria-selected="activeTab === '{{ $tabKey }}'"
                            x-bind:class="activeTab === '{{ $tabKey }}' ? 'bg-[#7A0019] text-white shadow-sm dark:bg-red-600 dark:text-white' : 'text-slate-500 hover:bg-rose-50 hover:text-[#7A0019] dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white'"
                            class="inline-flex shrink-0 items-center gap-2 rounded-xl px-4 py-2.5 text-xs font-black transition focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 dark:focus:ring-offset-slate-900"
                        >
                            {{ $tab['label'] }}
                            <span x-bind:class="activeTab === '{{ $tabKey }}' ? 'bg-white/15 dark:bg-slate-950/10' : 'bg-slate-100 dark:bg-slate-800'" class="rounded-full px-2 py-0.5 text-[10px]">{{ $tab['calls']->count() }}</span>
                        </button>
                    @endforeach
                </div>

                <label class="relative block w-full lg:max-w-xs">
                    <span class="sr-only">Search research calls</span>
                    <svg class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7" /><path stroke-linecap="round" d="m20 20-3.5-3.5" /></svg>
                    <input x-model.debounce.200ms="search" type="search" placeholder="Search calls or academic year" class="block w-full rounded-xl border-slate-300 bg-slate-50 py-2.5 pl-10 pr-3 text-sm font-semibold text-slate-900 placeholder:text-slate-400 focus:border-red-600 focus:ring-red-600 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                </label>
            </div>

            <div class="hidden grid-cols-[minmax(0,2.1fr)_minmax(15rem,1.1fr)_minmax(11rem,.8fr)_8rem_minmax(13rem,.9fr)] gap-5 border-b border-slate-200 bg-slate-50/80 px-6 py-3 text-[10px] font-black uppercase tracking-[0.14em] text-slate-400 dark:border-slate-800 dark:bg-slate-950/45 lg:grid">
                <span>Research call</span><span>Submission schedule</span><span>Allocation</span><span>Status</span><span class="text-right">Actions</span>
            </div>

            @foreach ($callTabs as $tabKey => $tab)
                <section x-show="activeTab === '{{ $tabKey }}'" x-cloak role="tabpanel" aria-label="{{ $tab['heading'] }}">
                    <div class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($tab['calls'] as $call)
                            @php
                                $lifecycleStatus = $call->lifecycleStatus();
                                [$statusLabel, $statusClass, $statusDot] = match ($lifecycleStatus) {
                                    'open' => ['Open', 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-950/35 dark:text-emerald-300 dark:ring-emerald-900', 'bg-emerald-500'],
                                    'scheduled' => ['Scheduled', 'bg-blue-50 text-blue-700 ring-blue-200 dark:bg-blue-950/35 dark:text-blue-300 dark:ring-blue-900', 'bg-blue-500'],
                                    'draft' => ['Draft', 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-950/35 dark:text-amber-300 dark:ring-amber-900', 'bg-amber-500'],
                                    'closed' => ['Closed', 'bg-slate-100 text-slate-600 ring-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700', 'bg-slate-400'],
                                    default => ['Ended', 'bg-slate-100 text-slate-600 ring-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700', 'bg-slate-400'],
                                };
                                $canReopen = $call->status === 'closed' && $call->closes_at->isFuture();
                                $nextStatus = $call->status === 'open' ? 'closed' : 'open';
                                $actionLabel = match (true) {
                                    $call->status === 'draft' => 'Publish schedule',
                                    $call->status === 'open' && $lifecycleStatus !== 'ended' => 'Close early',
                                    $canReopen => 'Reopen call',
                                    default => null,
                                };
                            @endphp
                            <article
                                x-show="!search || @js(Str::lower($call->title.' '.$call->academic_year.' '.($call->term ?? ''))).includes(search.toLowerCase())"
                                class="grid gap-5 px-4 py-5 transition hover:bg-slate-50/70 dark:hover:bg-slate-950/25 sm:px-6 lg:grid-cols-[minmax(0,2.1fr)_minmax(15rem,1.1fr)_minmax(11rem,.8fr)_8rem_minmax(13rem,.9fr)] lg:items-center"
                            >
                                <div class="flex min-w-0 items-start gap-4">
                                    <div class="flex h-16 w-12 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-slate-200 bg-slate-100 text-slate-400 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                                        @if ($call->reference_image_path)
                                            <img src="{{ route('research-calls.reference-image', $call) }}" alt="Poster for {{ $call->title }}" class="h-full w-full object-contain">
                                        @else
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3.75h7.5L18 7.5v12.75H6.75V3.75Z" /><path stroke-linecap="round" d="M9.5 12h5M9.5 15h5" /></svg>
                                        @endif
                                    </div>
                                    <div class="min-w-0">
                                        <h4 class="truncate text-sm font-black text-slate-950 dark:text-white">{{ $call->title }}</h4>
                                        <p class="mt-1 text-xs font-bold text-slate-500 dark:text-slate-400">{{ $call->academic_year }}{{ $call->term ? ' · '.$call->term : '' }}</p>
                                        <p class="mt-2 line-clamp-2 text-xs leading-5 text-slate-500 dark:text-slate-400">{{ $call->description ?: 'No additional guidelines.' }}</p>
                                    </div>
                                </div>

                                <dl class="grid grid-cols-2 gap-3 text-xs lg:grid-cols-1 lg:gap-2">
                                    <div><dt class="font-bold text-slate-400">Opens</dt><dd class="mt-0.5 font-semibold text-slate-700 dark:text-slate-200">{{ $call->opens_at->format('M d, Y · h:i A') }}</dd></div>
                                    <div><dt class="font-bold text-slate-400">Closes</dt><dd class="mt-0.5 font-semibold text-slate-700 dark:text-slate-200">{{ $call->closes_at->format('M d, Y · h:i A') }}</dd></div>
                                </dl>

                                <dl class="grid grid-cols-2 gap-3 text-xs lg:grid-cols-1 lg:gap-2">
                                    <div><dt class="font-bold text-slate-400">Maximum budget</dt><dd class="mt-0.5 font-black text-slate-800 dark:text-slate-100">PHP {{ number_format($call->budgetCeiling(), 2) }}</dd></div>
                                    <div><dt class="font-bold text-slate-400">Submissions</dt><dd class="mt-0.5 font-semibold text-slate-700 dark:text-slate-200">{{ $call->topics_count }} {{ Str::plural('proposal', $call->topics_count) }}</dd></div>
                                </dl>

                                <div>
                                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-wide ring-1 ring-inset {{ $statusClass }}"><span class="h-1.5 w-1.5 rounded-full {{ $statusDot }}"></span>{{ $statusLabel }}</span>
                                </div>

                                <div class="flex flex-wrap items-center gap-2 lg:justify-end">
                                    <button type="button" x-on:click="panel = 'edit-{{ $call->id }}'" class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-black text-slate-700 transition hover:border-red-200 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-red-900 dark:hover:text-red-300">Edit research call</button>
                                    @if ($actionLabel)
                                        <form method="POST" action="{{ route('research-calls.update-status', $call) }}" @if ($call->status === 'open') onsubmit="return confirm('Close this research call before its scheduled end date? It will no longer appear as an open research call, but faculty can still submit independent proposals.')" @endif>
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="{{ $nextStatus }}">
                                            <button class="inline-flex items-center justify-center rounded-lg px-3 py-2 text-xs font-black transition {{ $call->status === 'open' ? 'bg-red-600 text-white hover:bg-red-700' : 'bg-[#7A0019] text-white hover:bg-red-800 dark:bg-red-600 dark:hover:bg-red-700' }}">{{ $actionLabel }}</button>
                                        </form>
                                    @elseif ($lifecycleStatus === 'ended')
                                        <span class="text-right text-[10px] font-semibold leading-4 text-slate-400">The submission period ended automatically.</span>
                                    @endif
                                </div>
                            </article>
                        @empty
                            <div class="px-6 py-14 text-center">
                                <span class="mx-auto flex h-11 w-11 items-center justify-center rounded-2xl bg-slate-100 text-slate-400 dark:bg-slate-800"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3.75h7.5L18 7.5v12.75H6.75V3.75Z" /><path stroke-linecap="round" d="M9.5 12h5M9.5 15h5" /></svg></span>
                                <p class="mt-3 text-sm font-black text-slate-700 dark:text-slate-200">No {{ strtolower($tab['heading']) }} yet</p>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Calls in this lifecycle stage will appear here.</p>
                            </div>
                        @endforelse
                    </div>
                </section>
            @endforeach
        </section>

        <div x-show="panel !== null" x-cloak class="pointer-events-none fixed inset-0 z-[80]" role="presentation">
            <button type="button" x-on:click="panel = null" data-research-call-editor-backdrop class="pointer-events-auto absolute inset-0 bg-slate-950/60 backdrop-blur-sm xl:hidden" aria-label="Close research call editor"></button>
            <section
                x-show="panel !== null"
                x-transition:enter="transition ease-out duration-200 motion-reduce:transition-none"
                x-transition:enter-start="translate-y-4 scale-95 opacity-0"
                x-transition:enter-end="translate-y-0 scale-100 opacity-100"
                x-transition:leave="transition ease-in duration-150 motion-reduce:transition-none"
                x-transition:leave-start="translate-y-0 scale-100 opacity-100"
                x-transition:leave-end="translate-y-4 scale-95 opacity-0"
                data-research-call-editor-panel
                class="pointer-events-auto absolute inset-x-2 bottom-2 top-2 flex origin-bottom-right flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl dark:border-slate-700 dark:bg-slate-900 sm:inset-x-auto sm:bottom-4 sm:right-4 sm:top-4 sm:w-[min(64rem,calc(100vw-2rem))]"
                role="dialog"
                x-bind:aria-modal="window.matchMedia('(max-width: 1279px)').matches ? 'true' : null"
                aria-labelledby="research-call-editor-title"
            >
                <header class="flex shrink-0 items-center justify-between gap-4 border-b border-slate-200 bg-white px-5 py-4 dark:border-slate-800 dark:bg-slate-900 sm:px-6">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-red-700 dark:text-red-300">Research call editor</p>
                        <h3 id="research-call-editor-title" class="mt-1 text-lg font-black text-slate-950 dark:text-white" x-text="panel === 'create' ? 'Create a new research call' : 'Edit research call'"></h3>
                    </div>
                    <button type="button" x-on:click="panel = null" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-600 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white" aria-label="Close editor">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18" /></svg>
                    </button>
                </header>
                <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain">
                    <div x-show="panel === 'create'">
                        @include('research_calls.partials.form', ['researchCall' => null])
                    </div>
                    @foreach ($allCalls as $call)
                        <div x-show="panel === 'edit-{{ $call->id }}'">
                            @include('research_calls.partials.form', ['researchCall' => $call])
                        </div>
                    @endforeach
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
