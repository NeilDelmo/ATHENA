<div class="space-y-5 text-slate-900 dark:text-slate-100" data-research-head-overview>
    @php
        $filterParams = array_filter(['academicYear' => $academicYear, 'fromDate' => $fromDate, 'toDate' => $toDate]);
        $analyticsUrl = route('research_head.analytics', $filterParams);
        $selectedStage = $pipeline === 'awaiting_review' ? 'Awaiting your review' : ($analytics['pipeline']->firstWhere('key', $pipeline)['label'] ?? 'All review stages');
    @endphp

    <section class="grid grid-cols-2 gap-3 md:grid-cols-3" aria-label="Priority KPIs" data-dashboard-priority-kpis>
        <a href="#received-proposals" wire:click="showReviewQueue" class="col-span-2 flex items-center justify-between gap-4 rounded-xl bg-brand px-5 py-5 text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand md:col-span-1 md:flex-col md:items-start xl:flex-row xl:items-center">
            <span><span class="block text-sm font-semibold">Awaiting your review</span><span class="mt-1 block text-xs text-rose-100">Open the review queue</span></span>
            <strong class="text-4xl font-bold tracking-tight tabular-nums">{{ $analytics['kpis']['review'] }}</strong>
        </a>
        <a wire:navigate href="{{ route('research_head.analytics', [...$filterParams, 'projectStatus' => 'delayed']) }}#active-projects" class="flex flex-col items-start justify-between gap-4 rounded-xl border xl:flex-row xl:items-center border-slate-200 bg-white px-5 py-5 hover:border-amber-400 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand dark:border-slate-800 dark:bg-slate-900">
            <span><span class="block text-sm font-semibold">Delayed / overdue</span><span class="mt-1 block text-xs rh-muted">Projects needing follow-up</span></span>
            <strong class="text-4xl font-bold tracking-tight tabular-nums {{ $analytics['kpis']['delayed'] ? 'text-amber-700 dark:text-amber-300' : '' }}">{{ $analytics['kpis']['delayed'] }}</strong>
        </a>
        <a wire:navigate href="{{ route('research_head.analytics', [...$filterParams, 'projectStatus' => 'active']) }}#active-projects" class="flex flex-col items-start justify-between gap-4 rounded-xl border xl:flex-row xl:items-center border-slate-200 bg-white px-5 py-5 hover:border-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand dark:border-slate-800 dark:bg-slate-900">
            <span><span class="block text-sm font-semibold">Active projects</span><span class="mt-1 block text-xs rh-muted">Research in progress</span></span>
            <strong class="text-4xl font-bold tracking-tight tabular-nums">{{ $analytics['kpis']['active'] }}</strong>
        </a>
    </section>

    <section aria-label="Overview filters" class="border-b border-slate-200 pb-5 dark:border-slate-800">
        <form wire:submit="applyFilters" class="flex flex-wrap items-start justify-between gap-3">
            <div class="flex min-w-0 flex-wrap items-center gap-3">
                <label for="overview-year" class="text-sm font-semibold">Academic year</label>
                <select id="overview-year" wire:model.live="academicYear" class="rh-control w-full sm:w-52">
                    <option value="">All academic years</option>
                    @foreach ($academicYears as $year)<option value="{{ $year }}">{{ $year }}</option>@endforeach
                </select>
            </div>
            <details class="w-full sm:w-auto" data-dashboard-date-filters @if ($fromDate || $toDate) open @endif>
                <summary class="cursor-pointer rounded-lg py-3 text-sm font-semibold text-slate-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand dark:text-slate-300">{{ $fromDate || $toDate ? 'Date range applied' : 'Filter by submission date' }}</summary>
                <div class="mt-2 flex flex-wrap items-end gap-3">
                    <div class="min-w-0 flex-1 basis-36 sm:basis-auto sm:flex-none"><label for="overview-from" class="block text-xs font-semibold rh-muted">First submitted from</label><input id="overview-from" type="date" wire:model="fromDate" class="rh-control mt-1.5 w-full"></div>
                    <div class="min-w-0 flex-1 basis-36 sm:basis-auto sm:flex-none"><label for="overview-to" class="block text-xs font-semibold rh-muted">Through</label><input id="overview-to" type="date" wire:model="toDate" class="rh-control mt-1.5 w-full"></div>
                    <button class="rh-button" wire:loading.attr="disabled">Apply</button>
                    <button type="button" wire:click="resetAnalyticsFilters" class="rh-button-secondary">Reset</button>
                </div>
            </details>
            @if ($academicYear && ! $fromDate && ! $toDate)
                <button type="button" wire:click="resetAnalyticsFilters" class="rh-button-secondary">Reset filters</button>
            @endif
        </form>
        @foreach (['academicYear', 'fromDate', 'toDate', 'submissionMonth'] as $field)
            @error($field)<p role="alert" class="mt-2 text-sm text-red-700 dark:text-red-300">{{ $message }}</p>@enderror
        @endforeach
    </section>

    <div class="grid items-start gap-5 xl:grid-cols-[minmax(0,1.8fr)_minmax(0,1fr)]" data-dashboard-compact-queues>
        <div class="min-w-0" data-dashboard-review-workspace>
            <section id="received-proposals" class="rh-panel !rounded-xl !shadow-none scroll-mt-40" aria-labelledby="inbox-heading">
                <div class="flex flex-wrap items-center justify-between gap-3 px-5 pb-4 pt-5">
                    <div><h2 id="inbox-heading" class="text-xl font-bold tracking-tight">Review queue</h2><p class="mt-1 text-sm rh-muted">{{ $topics->total() }} {{ str('proposal')->plural($topics->total()) }} · {{ $selectedStage }}</p></div>
                    <a wire:navigate href="{{ route('research_head.proposal-submissions.index') }}" class="inline-flex min-h-[44px] items-center text-sm font-semibold text-brand hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand dark:text-rose-300">View all proposals</a>
                </div>
                <div class="flex flex-wrap items-center gap-2 border-b border-slate-100 px-5 pb-4 dark:border-slate-800">
                    <label for="overview-search" class="sr-only">Search proposals</label><input id="overview-search" type="search" wire:model.live.debounce.300ms="search" placeholder="Search proposals or faculty" class="rh-control min-w-0 flex-1 !rounded-lg !bg-slate-50 dark:!bg-slate-950">
                    @if ($pipeline)<button type="button" wire:click="clearPipeline" class="rh-button-secondary !rounded-lg">All stages</button>@endif
                </div>
                <div class="divide-y divide-slate-100 dark:divide-slate-800" data-dashboard-queue="proposals">
                    @forelse ($topics as $topic)
                        <a href="{{ route('topics.show', $topic) }}{{ $topic->hasIssuedNoticeToProceed() ? '#project-monitoring' : '#proposal-review' }}" class="group flex items-start gap-3 px-5 py-5 hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-inset focus-visible:outline-brand dark:hover:bg-slate-800/60" wire:key="overview-proposal-{{ $topic->id }}">
                            <span class="min-w-0 flex-1">
                                <strong class="block break-words text-base font-semibold leading-6 group-hover:text-brand dark:group-hover:text-rose-300">{{ $topic->title }}</strong>
                                <span class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs rh-muted"><span>{{ $topic->user?->name }}</span><span class="rounded-md bg-slate-100 px-2 py-1 font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-300">{{ $topic->researchHeadQueueStatusLabel($topic->latestVersion) }}</span></span>
                            </span>
                            <svg class="mt-1 h-5 w-5 shrink-0 text-slate-400 group-hover:text-brand dark:group-hover:text-rose-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="m9 5 7 7-7 7" stroke-linecap="round" stroke-linejoin="round" /></svg>
                        </a>
                    @empty
                        <div class="px-5 py-12 text-center"><p class="font-semibold">No proposals found.</p><p class="mt-2 text-sm rh-muted">Try changing the search or filters.</p></div>
                    @endforelse
                </div>
                @if ($topics->hasPages())<div class="border-t border-slate-100 px-5 py-4 dark:border-slate-800">{{ $topics->links('livewire::simple-tailwind', ['scrollTo' => '#received-proposals']) }}</div>@endif
            </section>


        </div>

        <aside class="min-w-0 space-y-5 xl:col-start-2 xl:row-span-2 xl:row-start-1" aria-label="Research priorities">
            <section id="needs-attention" class="rh-panel !rounded-xl !shadow-none scroll-mt-40" aria-labelledby="attention-heading">
                <div class="rh-panel-heading !gap-2"><h2 id="attention-heading" class="text-base font-bold">Needs attention</h2><span class="rounded-md bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-800 dark:bg-amber-950/40 dark:text-amber-200">{{ $attentionItems->total() }} {{ str('issue')->plural($attentionItems->total()) }}</span></div>
                <div class="divide-y divide-slate-100 dark:divide-slate-800" data-dashboard-queue="attention">
                    @forelse ($attentionItems as $item)
                        <a href="{{ $item['url'] }}" class="flex items-start justify-between gap-3 px-5 py-4 hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-inset focus-visible:outline-brand dark:hover:bg-slate-800/60" wire:key="overview-issue-{{ $item['id'] }}-{{ $item['type'] }}">
                            <span class="min-w-0"><strong class="block break-words text-sm font-semibold leading-5">{{ $item['title'] }}</strong><span class="mt-1.5 block text-xs text-amber-800 dark:text-amber-200">{{ $item['type'] === 'head_review' ? 'Ready for your review' : $item['issue'] }}</span></span>
                            @if ($item['days'] !== null)<span class="shrink-0 text-right"><strong class="block text-sm font-semibold tabular-nums">{{ $item['days'] }}d</strong><span class="text-xs rh-muted">{{ $item['basis'] === 'overdue' ? 'overdue' : 'elapsed' }}</span></span>@endif
                        </a>
                    @empty
                        <div class="px-5 py-8"><p class="text-sm font-semibold">No items need attention.</p><p class="mt-1 text-xs rh-muted">Follow-up items will appear here when recorded.</p></div>
                    @endforelse
                </div>
                @if ($attentionItems->hasPages())<div class="border-t border-slate-100 px-5 py-3 dark:border-slate-800">{{ $attentionItems->links('livewire::simple-tailwind', ['scrollTo' => '#needs-attention']) }}</div>@endif
            </section>

            <section class="rh-panel !rounded-xl !shadow-none" aria-labelledby="deadlines-heading">
                <div class="rh-panel-heading"><h2 id="deadlines-heading" class="text-base font-bold">Upcoming deadlines</h2><a wire:navigate href="{{ route('research_head.calendar') }}" class="inline-flex min-h-[44px] items-center text-xs font-semibold text-brand hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand dark:text-rose-300">Calendar</a></div>
                <div class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($deadlines->take(3) as $event)
                        <a href="{{ $event['url'] }}" class="flex items-center gap-3 px-5 py-4 hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-inset focus-visible:outline-brand dark:hover:bg-slate-800/60">
                            <x-date-chip :date="\Illuminate\Support\Carbon::parse($event['at'])" compact :weekday="false" :relative="false" tone="rose" />
                            <span class="min-w-0"><strong class="block text-sm font-semibold">{{ $event['title'] }}</strong><span class="mt-1 block break-words text-xs rh-muted">{{ $event['context'] }}</span></span>
                        </a>
                    @empty
                        <p class="px-5 py-6 text-sm rh-muted">No official deadlines in the next 14 days.</p>
                    @endforelse
                </div>
            </section>

            <section class="px-1" aria-labelledby="annual-plan-heading">
                <div class="flex items-center justify-between gap-3"><h2 id="annual-plan-heading" class="text-base font-bold">Annual targets</h2><a wire:navigate href="{{ $analyticsUrl }}#annual-targets" class="inline-flex min-h-[44px] items-center text-xs font-semibold text-brand hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand dark:text-rose-300">Manage targets</a></div>
                @if ($academicYear && $analytics['targetMatches'])
                    <dl class="mt-2 divide-y divide-slate-200 dark:divide-slate-800">
                        @foreach ($analytics['targets'] as $metric)
                            <div class="flex items-start justify-between gap-3 py-3 text-sm"><dt class="rh-muted">{{ $metric['label'] }}</dt><dd class="shrink-0 font-semibold tabular-nums">{{ $metric['actual'] ?? '—' }} / {{ $metric['target'] ?? 'Not set' }}</dd></div>
                        @endforeach
                    </dl>
                @else
                    <p class="mt-1 text-xs leading-5 rh-muted">{{ $academicYear ? 'Set year dates and targets, or select the full academic year.' : 'Choose an academic year to see its targets.' }}</p>
                @endif
            </section>
        </aside>

            <section class="xl:col-start-1 xl:row-start-2 rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" aria-labelledby="pipeline-heading">
                <div class="flex items-center justify-between gap-3"><h2 id="pipeline-heading" class="text-base font-bold">Proposal pipeline</h2><span class="text-xs rh-muted">{{ $analytics['pipeline']->sum('count') }} total</span></div>
                <p class="mt-1 text-xs rh-muted">Select a stage to filter the review queue.</p>
                <div class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-3" data-dashboard-stage-filters>
                    @foreach ($analytics['pipeline'] as $stage)
                        <button type="button" wire:click="setPipeline('{{ $stage['key'] }}')" aria-pressed="{{ $pipeline === $stage['key'] ? 'true' : 'false' }}" aria-label="Show {{ $stage['label'] }} proposals: {{ $stage['count'] }}"
                            @class([
                                'flex min-h-[64px] items-center justify-between gap-3 rounded-lg border px-3 py-3 text-left focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand',
                                'border-brand bg-brand-wash text-brand dark:border-rose-400 dark:bg-rose-950/40 dark:text-rose-200' => $pipeline === $stage['key'],
                                'border-slate-200 hover:border-slate-400 dark:border-slate-700 dark:hover:border-slate-500' => $pipeline !== $stage['key'],
                            ])>
                            <span class="text-xs font-semibold leading-5">{{ $stage['label'] }}</span><strong class="text-xl font-bold tabular-nums">{{ $stage['count'] }}</strong>
                        </button>
                    @endforeach
                </div>
                @if ($analytics['pipeline']->sum('count') === 0)<p class="mt-3 text-sm rh-muted">No data yet.</p>@endif
            </section>
    </div>

    <section class="grid gap-4 border-t border-slate-200 pt-5 dark:border-slate-800 sm:grid-cols-3" aria-label="Research summary">
        <a wire:navigate href="{{ route('research_head.analytics', [...$filterParams, 'projectStatus' => 'completed']) }}#active-projects" class="flex items-center justify-between gap-4 rounded-lg p-2 hover:bg-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand dark:hover:bg-slate-900">
            <span class="text-xs font-semibold rh-muted">Completed projects</span><strong class="text-xl font-bold tabular-nums">{{ $analytics['kpis']['completed'] }}</strong>
        </a>
        <a wire:navigate href="{{ route('research_head.faculty-directory.index') }}" class="flex items-center justify-between gap-4 rounded-lg p-2 hover:bg-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand dark:hover:bg-slate-900">
            <span class="text-xs font-semibold rh-muted">Faculty in research</span><strong class="text-xl font-bold tabular-nums">{{ $analytics['kpis']['faculty'] }}</strong>
        </a>
        <a wire:navigate href="{{ $analyticsUrl }}#reported-budget" class="flex items-center justify-between gap-4 rounded-lg p-2 hover:bg-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand dark:hover:bg-slate-900">
            <span class="text-xs font-semibold rh-muted">Reported budget utilization</span><strong class="text-xl font-bold tabular-nums">{{ $analytics['budget']['percentage'] !== null ? number_format($analytics['budget']['percentage'], 1).'%' : '—' }}</strong>
        </a>
    </section>
</div>
