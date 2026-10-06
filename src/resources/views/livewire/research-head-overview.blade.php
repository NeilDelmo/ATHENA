<div class="space-y-5 text-slate-900 dark:text-slate-100" data-research-head-overview>
    @php
        $filterParams = array_filter(['academicYear' => $academicYear, 'fromDate' => $fromDate, 'toDate' => $toDate]);
        $analyticsUrl = route('research_head.analytics', $filterParams);
        $selectedStage = $pipeline === 'awaiting_review' ? 'Awaiting your review' : ($analytics['pipeline']->firstWhere('key', $pipeline)['label'] ?? 'All review stages');
    @endphp

    <section class="grid grid-cols-2 gap-3 md:grid-cols-4" aria-label="Priority KPIs" data-dashboard-priority-kpis>
        <a href="#received-proposals" wire:click="showReviewQueue" class="flex flex-col items-start justify-between gap-4 rounded-xl bg-brand px-5 py-5 text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand">
            <span><span class="block text-sm font-semibold">Proposals awaiting review</span><span class="mt-1 block text-sm text-rose-100">Open the review queue <span aria-hidden="true">&rarr;</span></span></span>
            <strong class="text-4xl font-bold tracking-tight tabular-nums">{{ $analytics['kpis']['review'] }}</strong>
        </a>
        <a href="#dashboard-report-reviews" class="flex flex-col items-start justify-between gap-4 rounded-xl border border-red-200 bg-white px-5 py-5 hover:border-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand dark:border-red-900 dark:bg-slate-900">
            <span><span class="block text-sm font-semibold">Reports awaiting review</span><span class="mt-1 block text-sm rh-muted">Open report reviews <span aria-hidden="true">&rarr;</span></span></span>
            <strong class="text-4xl font-bold tracking-tight tabular-nums text-brand dark:text-rose-300">{{ $reportItems->total() }}</strong>
        </a>
        <a wire:navigate href="{{ route('research_head.analytics', [...$filterParams, 'projectStatus' => 'delayed']) }}#active-projects" class="flex flex-col items-start justify-between gap-4 rounded-xl border border-slate-200 bg-white px-5 py-5 hover:border-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand dark:border-slate-800 dark:bg-slate-900">
            <span><span class="block text-sm font-semibold">Delayed / overdue</span><span class="mt-1 block text-sm rh-muted">View projects needing follow-up <span aria-hidden="true">&rarr;</span></span></span>
            <strong class="text-4xl font-bold tracking-tight tabular-nums {{ $analytics['kpis']['delayed'] ? 'text-brand dark:text-rose-300' : '' }}">{{ $analytics['kpis']['delayed'] }}</strong>
        </a>
        <a wire:navigate href="{{ route('research_head.analytics', [...$filterParams, 'projectStatus' => 'active']) }}#active-projects" class="flex flex-col items-start justify-between gap-4 rounded-xl border border-slate-200 bg-white px-5 py-5 hover:border-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand dark:border-slate-800 dark:bg-slate-900">
            <span><span class="block text-sm font-semibold">Active projects</span><span class="mt-1 block text-sm rh-muted">View projects in progress <span aria-hidden="true">&rarr;</span></span></span>
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
            <details class="group w-full sm:w-auto" data-dashboard-date-filters @if ($fromDate || $toDate) open @endif>
                <summary class="rh-button-secondary flex cursor-pointer list-none items-center gap-2 [&::-webkit-details-marker]:hidden">
                    <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M6.75 3v2.25M17.25 3v2.25M3.75 18.75V7.5A2.25 2.25 0 016 5.25h12a2.25 2.25 0 012.25 2.25v11.25M3.75 18.75A2.25 2.25 0 006 21h12a2.25 2.25 0 002.25-2.25M3.75 18.75v-7.5h16.5v7.5" stroke-linecap="round" stroke-linejoin="round" /></svg>
                    <span>{{ $fromDate || $toDate ? 'Date range applied' : 'Filter by submission date' }}</span>
                    <span class="ml-auto text-lg leading-none group-open:hidden" aria-hidden="true">+</span>
                    <span class="ml-auto hidden text-lg leading-none group-open:inline" aria-hidden="true">&minus;</span>
                </summary>
                <div class="mt-2 flex flex-wrap items-end gap-3">
                    <div class="min-w-0 flex-1 basis-36 sm:basis-auto sm:flex-none"><label for="overview-from" class="block text-sm font-semibold rh-muted">First submitted from</label><input id="overview-from" type="date" wire:model="fromDate" class="rh-control mt-1.5 w-full"></div>
                    <div class="min-w-0 flex-1 basis-36 sm:basis-auto sm:flex-none"><label for="overview-to" class="block text-sm font-semibold rh-muted">Through</label><input id="overview-to" type="date" wire:model="toDate" class="rh-control mt-1.5 w-full"></div>
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

    <div class="grid items-start gap-5 xl:grid-cols-2" data-dashboard-compact-queues>
        <div class="min-w-0" data-dashboard-review-workspace>
<section id="received-proposals" class="rh-panel !rounded-xl !shadow-none scroll-mt-40" aria-labelledby="inbox-heading">
                <div class="flex flex-wrap items-center justify-between gap-3 px-5 pb-4 pt-5">
                    <div><h2 id="inbox-heading" class="text-xl font-bold tracking-tight">Proposal reviews</h2><p class="mt-1 text-sm rh-muted">{{ $topics->total() }} {{ str('proposal')->plural($topics->total()) }} · {{ $selectedStage }}</p></div>
                    <a wire:navigate href="{{ route('research_head.proposal-submissions.index') }}" class="rh-button whitespace-nowrap">View all proposals</a>
                </div>
                <div class="flex flex-wrap items-center gap-2 border-b border-slate-100 px-5 pb-4 dark:border-slate-800">
                    <label for="overview-search" class="sr-only">Search proposals</label><input id="overview-search" type="search" wire:model.live.debounce.300ms="search" placeholder="Search proposals or faculty" class="rh-control min-w-0 flex-1 !rounded-lg !bg-slate-50 dark:!bg-slate-950">
                    @if ($pipeline)<button type="button" wire:click="clearPipeline" class="rh-button-secondary !rounded-lg">All stages</button>@endif
                </div>
                <p class="px-5 pb-3 text-sm rh-muted">Oldest waiting submissions first.</p>
                <div class="divide-y divide-slate-100 dark:divide-slate-800" data-dashboard-queue="proposals">
                    @forelse ($topics as $topic)
                        @php
                            $waitingSince = $topic->status_started_at ?? $topic->latestVersion?->created_at;
                            $waitingDays = $waitingSince ? (int) max(0, \Carbon\CarbonImmutable::parse($waitingSince)->diffInDays(now(), false)) : null;
                        @endphp
                        <a href="{{ route('topics.show', $topic) }}{{ $topic->hasIssuedNoticeToProceed() ? '#project-monitoring' : '#proposal-review' }}" class="group flex items-start gap-3 px-5 py-5 hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-inset focus-visible:outline-brand dark:hover:bg-slate-800/60" wire:key="overview-proposal-{{ $topic->id }}">
                            <span class="min-w-0 flex-1">
                                <strong class="block break-words text-base font-semibold leading-6 group-hover:text-brand dark:group-hover:text-rose-300">{{ $topic->title }}</strong>
                                <span class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm rh-muted"><span>{{ $topic->user?->name }}</span><span class="rounded-md bg-slate-100 px-2 py-1 font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-300">{{ $topic->researchHeadQueueStatusLabel($topic->latestVersion) }}</span></span>
                                <span class="mt-2 block text-sm font-medium text-brand dark:text-rose-300">{{ $waitingDays !== null ? $waitingDays.' '.str('day')->plural($waitingDays).($topic->status_started_at ? ' in this stage' : ' since submission') : 'Stage start date unavailable' }}</span>
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
<section id="dashboard-report-reviews" class="rh-panel !rounded-xl !shadow-none scroll-mt-40" aria-labelledby="dashboard-report-heading">
    <div class="flex flex-wrap items-center justify-between gap-3 px-5 pb-4 pt-5">
        <div><h2 id="dashboard-report-heading" class="text-xl font-bold">Report reviews</h2><p class="mt-1 text-sm rh-muted">{{ $reportItems->total() }} awaiting review</p></div>
        <a wire:navigate href="{{ route('research_head.report-reviews.index') }}" class="rh-button whitespace-nowrap">View all reports</a>
    </div>
    <p class="px-5 pb-3 text-sm rh-muted">Oldest submitted reports first.</p>
    <div class="divide-y divide-slate-100 dark:divide-slate-800" data-dashboard-queue="reports">
        @forelse ($reportItems as $report)
            @php
                $reportAnchor = $report->report_type === 'quarterly' ? 'monitoring-tool-'.$report->id : 'narrative-report-'.$report->id;
                $receivedAt = $report->received_at ? \Carbon\CarbonImmutable::parse($report->received_at) : null;
                $waitingDays = $receivedAt ? (int) max(0, $receivedAt->diffInDays(now(), false)) : null;
                $reportLabel = ['quarterly' => 'Monitoring tool', 'progress' => 'Progress report', 'terminal' => 'Terminal report'][$report->report_type];
            @endphp
            <a href="{{ route('topics.show', $report->topic_id) }}#{{ $reportAnchor }}" class="block px-5 py-5 hover:bg-red-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-inset focus-visible:outline-brand dark:hover:bg-red-950/30" wire:key="overview-report-{{ $report->report_type }}-{{ $report->id }}">
                <strong class="block break-words text-base font-semibold leading-6">{{ $report->title }}</strong>
                <span class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-2 text-sm rh-muted"><span class="rounded-md bg-red-50 px-2 py-1 font-medium text-brand dark:bg-red-950/40 dark:text-rose-200">{{ $reportLabel }}</span><span>{{ $report->faculty_name }}</span></span>
                <span class="mt-2 block text-sm font-medium text-brand dark:text-rose-300">{{ $waitingDays !== null ? $waitingDays.' '.str('day')->plural($waitingDays).' waiting' : 'Submission date unavailable' }}@if ($report->report_date) <span class="font-normal rh-muted">· Report dated {{ \Carbon\CarbonImmutable::parse($report->report_date)->format('M j, Y') }}</span>@endif</span>
            </a>
        @empty
            <div class="px-5 py-10 text-center"><p class="text-base font-semibold">No reports awaiting review.</p><p class="mt-2 text-sm rh-muted">Submitted monitoring, progress, and terminal reports appear here.</p></div>
        @endforelse
    </div>
    @if ($reportItems->hasPages())<div class="border-t border-slate-100 px-5 py-4 dark:border-slate-800">{{ $reportItems->links('livewire::simple-tailwind', ['scrollTo' => '#dashboard-report-reviews']) }}</div>@endif
</section>
    </div>
    <aside class="grid min-w-0 items-start gap-5 lg:grid-cols-2" aria-label="Research priorities">
<section id="needs-attention" class="rh-panel !rounded-xl !shadow-none scroll-mt-40" aria-labelledby="attention-heading">
                <div class="rh-panel-heading !gap-2"><h2 id="attention-heading" class="text-base font-bold">Needs attention</h2><span class="rounded-md bg-red-50 px-2 py-1 text-sm font-semibold text-brand dark:bg-red-950/40 dark:text-rose-200">{{ $attentionItems->total() }} {{ str('issue')->plural($attentionItems->total()) }}</span></div>
                <div class="divide-y divide-slate-100 dark:divide-slate-800" data-dashboard-queue="attention">
                    @forelse ($attentionItems as $item)
                        <a href="{{ $item['url'] }}" class="flex items-start justify-between gap-3 px-5 py-4 hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-inset focus-visible:outline-brand dark:hover:bg-slate-800/60" wire:key="overview-issue-{{ $item['id'] }}-{{ $item['type'] }}-{{ $item['report_id'] ?? 0 }}">
                            <span class="min-w-0"><strong class="block break-words text-base font-semibold leading-6">{{ $item['title'] }}</strong><span class="mt-1.5 block text-sm text-brand dark:text-rose-200">{{ $item['type'] === 'head_review' ? 'Ready for your review' : $item['issue'] }}</span></span>
                            @if ($item['days'] !== null)<span class="shrink-0 text-right"><strong class="block text-sm font-semibold tabular-nums">{{ $item['days'] }} {{ str('day')->plural($item['days']) }}</strong><span class="text-sm rh-muted">{{ match ($item['basis']) { 'overdue' => 'overdue', 'since reporting opened' => 'since open', 'since report' => 'since report', default => 'waiting' } }}</span></span>@endif
                        </a>
                    @empty
                        <div class="px-5 py-8"><p class="text-sm font-semibold">No items need attention.</p><p class="mt-1 text-sm rh-muted">Follow-up items will appear here when recorded.</p></div>
                    @endforelse
                </div>
                @if ($attentionItems->hasPages())<div class="border-t border-slate-100 px-5 py-3 dark:border-slate-800">{{ $attentionItems->links('livewire::simple-tailwind', ['scrollTo' => '#needs-attention']) }}</div>@endif
            </section>
<section class="rh-panel !rounded-xl !shadow-none" aria-labelledby="deadlines-heading">
                <div class="rh-panel-heading"><h2 id="deadlines-heading" class="text-base font-bold">Upcoming deadlines</h2><a wire:navigate href="{{ route('research_head.calendar') }}" class="rh-button-secondary whitespace-nowrap">Calendar</a></div>
                <p class="px-5 pt-4 text-sm rh-muted">Institution-wide deadlines for the next 14 days.</p>
                <div class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($deadlines->take(3) as $event)
                        <a href="{{ $event['url'] }}" class="flex items-center gap-3 px-5 py-4 hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-inset focus-visible:outline-brand dark:hover:bg-slate-800/60">
                            <x-date-chip :date="\Illuminate\Support\Carbon::parse($event['at'])" compact :weekday="false" :relative="false" tone="rose" />
                            <span class="min-w-0"><strong class="block text-sm font-semibold">{{ $event['title'] }}</strong><span class="mt-1 block break-words text-sm rh-muted">{{ $event['context'] }}</span></span>
                        </a>
                    @empty
                        <p class="px-5 py-6 text-sm rh-muted">No official deadlines in the next 14 days.</p>
                    @endforelse
                </div>
            </section>
    </aside>

    <section class="grid min-w-0 gap-5 lg:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)]" aria-label="Research activity and project health" data-dashboard-visual-summary>
        <section class="rh-panel !rounded-xl !shadow-none p-5" aria-labelledby="overview-activity-heading">
            @php
                $recentMonths = $analytics['trend']->take(-6)->values();
                $activityMax = max(1, $recentMonths->max(fn ($month) => max($month['new'], $month['revision'])));
                $activityTotal = $recentMonths->sum('new') + $recentMonths->sum('revision');
            @endphp
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div><h2 id="overview-activity-heading" class="text-base font-bold">Submission activity</h2><p class="mt-1 text-sm rh-muted">New proposals and revision submissions · recent months in the selected period</p></div>
                <a wire:navigate href="{{ $analyticsUrl }}" class="rh-button-secondary whitespace-nowrap">View analytics</a>
            </div>
            <div class="mt-4 flex flex-wrap gap-4 text-sm rh-muted"><span class="inline-flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-sm bg-brand dark:bg-rose-400" aria-hidden="true"></span>New proposals</span><span class="inline-flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-sm bg-slate-400" aria-hidden="true"></span>Revision submissions</span></div>
            @if ($activityTotal > 0)
                <div class="mt-5 flex min-w-0 items-end gap-2" data-dashboard-activity-chart>
                    @foreach ($recentMonths as $month)
                        <a wire:navigate href="{{ route('research_head.analytics', [...$filterParams, 'submissionMonth' => $month['key']]) }}#received-proposals" class="min-w-0 flex-1 rounded-lg px-1 pb-2 pt-1 hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand dark:hover:bg-slate-800" aria-label="{{ $month['label'] }}: {{ $month['new'] }} new proposals and {{ $month['revision'] }} revision submissions" title="{{ $month['label'] }}: {{ $month['new'] }} new, {{ $month['revision'] }} revisions">
                            <span class="flex h-36 items-end justify-center gap-1 border-b border-slate-200 dark:border-slate-700" aria-hidden="true"><span class="w-4 rounded-t bg-brand dark:bg-rose-400" style="height: {{ 100 * $month['new'] / $activityMax }}%"></span><span class="w-4 rounded-t bg-slate-400" style="height: {{ 100 * $month['revision'] / $activityMax }}%"></span></span>
                            <span class="mt-2 block text-center text-sm font-medium rh-muted">{{ \Illuminate\Support\Carbon::parse($month['key'].'-01')->format('M') }}</span><span class="mt-1 block text-center text-sm font-semibold tabular-nums">{{ $month['new'] }} / {{ $month['revision'] }}</span>
                        </a>
                    @endforeach
                </div>
                <p class="mt-3 text-sm rh-muted">{{ $activityTotal }} submissions. Counts show new / revised. Select a month to inspect proposals.</p>
            @else
                <div class="mt-5 flex min-h-36 items-center justify-center rounded-lg border border-dashed border-slate-200 p-4 dark:border-slate-700"><p class="text-center text-sm rh-muted">{{ $analytics['periodAvailable'] ? 'No submission activity recorded in these months.' : 'Choose academic-year dates to see submission activity.' }}</p></div>
            @endif
        </section>
        <section class="rh-panel !rounded-xl !shadow-none p-5" aria-labelledby="overview-health-heading">
            @php
                $projectTotal = $analytics['projectStatuses']->sum('count');
                $projectColors = ['ongoing' => '#bf6a7b', 'delayed' => '#7a0019', 'awaiting' => '#e8a9b5', 'completed' => '#64748b'];
                $ringOffset = 0;
            @endphp
            <h2 id="overview-health-heading" class="text-base font-bold">Project health</h2>
            <p class="mt-1 text-sm rh-muted">Issued projects in the selected scope</p>
            <div class="mt-5 flex flex-col items-stretch gap-5 sm:flex-row sm:items-center">
                <div class="relative h-36 w-36 shrink-0 self-center sm:self-auto" role="img" aria-label="{{ $projectTotal }} issued projects. {{ $analytics['projectStatuses']->map(fn ($state) => $state['label'].': '.$state['count'])->join('; ') }}">
                    <svg viewBox="0 0 100 100" class="h-full w-full -rotate-90" fill="none" aria-hidden="true">
                        <circle cx="50" cy="50" r="40" stroke-width="10" class="stroke-slate-100 dark:stroke-slate-800" />
                        @foreach ($analytics['projectStatuses'] as $state)
                            @php($share = 100 * $state['count'] / max(1, $projectTotal))
                            @if ($share > 0)<circle cx="50" cy="50" r="40" stroke-width="10" pathLength="100" stroke="{{ $projectColors[$state['key']] }}" stroke-dasharray="{{ $share }} {{ 100 - $share }}" stroke-dashoffset="{{ -$ringOffset }}" />@endif
                            @php($ringOffset += $share)
                        @endforeach
                    </svg>
                    <div class="absolute inset-0 flex flex-col items-center justify-center"><strong class="text-3xl font-bold tabular-nums">{{ $projectTotal }}</strong><span class="text-sm rh-muted">projects</span></div>
                </div>
                <div class="min-w-0 flex-1 space-y-2">
                    @foreach ($analytics['projectStatuses'] as $state)
                        <a wire:navigate href="{{ route('research_head.analytics', [...$filterParams, 'projectStatus' => $state['key']]) }}#active-projects" class="flex min-h-10 items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand dark:border-slate-700 dark:hover:bg-slate-800">
                            <span class="h-2.5 w-2.5 shrink-0 rounded-sm" style="background-color: {{ $projectColors[$state['key']] }}" aria-hidden="true"></span><span class="min-w-0 flex-1">{{ $state['label'] }}</span><strong class="tabular-nums">{{ $state['count'] }}</strong>
                        </a>
                    @endforeach
                </div>
            </div>
            @if ($projectTotal === 0)<p class="mt-4 text-sm rh-muted">Projects appear here once their signed Notice to Proceed is issued.</p>@endif
        </section>
    </section>

    <div class="grid items-start gap-5 xl:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
<section class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" aria-labelledby="pipeline-heading">
                <div class="flex items-center justify-between gap-3"><h2 id="pipeline-heading" class="text-base font-bold">Proposal pipeline</h2><span class="text-sm rh-muted">{{ $analytics['pipeline']->sum('count') }} total</span></div>
                <p class="mt-1 text-sm rh-muted">Compare proposal counts by stage. Click a row to filter the review queue.</p>
                <div class="mt-4 grid gap-3 sm:grid-cols-2" data-dashboard-stage-filters data-dashboard-pipeline-chart>
                    @foreach ($analytics['pipeline'] as $stage)
                        <button type="button" wire:click="setPipeline('{{ $stage['key'] }}')" aria-pressed="{{ $pipeline === $stage['key'] ? 'true' : 'false' }}" aria-label="Show {{ $stage['label'] }} proposals: {{ $stage['count'] }}"
                            @class([
                                'grid min-h-[56px] w-full grid-cols-[minmax(0,1fr)_2rem] items-center gap-x-3 gap-y-2 rounded-lg border px-3 py-3 text-left focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand',
                                'border-brand bg-brand-wash text-brand dark:border-rose-400 dark:bg-rose-950/40 dark:text-rose-200' => $pipeline === $stage['key'],
                                'border-slate-200 hover:border-slate-400 dark:border-slate-700 dark:hover:border-slate-500' => $pipeline !== $stage['key'],
                            ])>
                            <span class="text-sm font-semibold leading-5">{{ $stage['label'] }} @if ($pipeline === $stage['key'])<span class="ml-1">Selected</span>@endif</span><strong class="text-lg font-bold tabular-nums text-right">{{ $stage['count'] }}</strong>
                            <span class="col-span-2 block h-2 overflow-hidden rounded bg-slate-100 dark:bg-slate-800" aria-hidden="true"><span class="block h-full rounded bg-brand dark:bg-rose-400" style="width: {{ 100 * $stage['count'] / max(1, $analytics['pipeline']->max('count')) }}%"></span></span>
                        </button>
                    @endforeach
                </div>
                @if ($analytics['pipeline']->sum('count') === 0)<p class="mt-3 text-sm rh-muted">No data yet.</p>@endif
            </section>
<section class="px-1" aria-labelledby="annual-plan-heading">
                <div class="flex items-center justify-between gap-3"><h2 id="annual-plan-heading" class="text-base font-bold">Annual targets</h2><a wire:navigate href="{{ $analyticsUrl }}#annual-targets" class="inline-flex min-h-[44px] items-center text-sm font-semibold text-brand hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand dark:text-rose-300">Manage targets</a></div>
                @if ($academicYear && $analytics['targetMatches'])
                    <dl class="mt-2 divide-y divide-slate-200 dark:divide-slate-800">
                        @foreach ($analytics['targets'] as $metric)
                            <div class="flex items-start justify-between gap-3 py-3 text-sm"><dt class="rh-muted">{{ $metric['label'] }}</dt><dd class="shrink-0 font-semibold tabular-nums">{{ $metric['actual'] ?? '—' }} / {{ $metric['target'] ?? 'Not set' }}</dd></div>
                        @endforeach
                    </dl>
                @else
                    <p class="mt-1 text-sm leading-5 rh-muted">{{ $academicYear ? 'Set year dates and targets, or select the full academic year.' : 'Choose an academic year to see its targets.' }}</p>
                @endif
            </section>
    </div>
    <section class="grid gap-4 border-t border-slate-200 pt-5 dark:border-slate-800 sm:grid-cols-3" aria-label="Research summary">
        <a wire:navigate href="{{ route('research_head.analytics', [...$filterParams, 'projectStatus' => 'completed']) }}#active-projects" class="flex items-center justify-between gap-4 rounded-lg p-2 hover:bg-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand dark:hover:bg-slate-900">
            <span><span class="block text-sm font-semibold rh-muted">Completed projects</span><span class="mt-1 block text-sm font-semibold text-brand dark:text-rose-300">View completed projects &rarr;</span></span><strong class="text-xl font-bold tabular-nums">{{ $analytics['kpis']['completed'] }}</strong>
        </a>
        <a wire:navigate href="{{ route('research_head.faculty-directory.index') }}" class="flex items-center justify-between gap-4 rounded-lg p-2 hover:bg-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand dark:hover:bg-slate-900">
            <span><span class="block text-sm font-semibold rh-muted">Faculty in research</span><span class="mt-1 block text-sm font-semibold text-brand dark:text-rose-300">View faculty directory &rarr;</span></span><strong class="text-xl font-bold tabular-nums">{{ $analytics['kpis']['faculty'] }}</strong>
        </a>
        <a wire:navigate href="{{ $analyticsUrl }}#reported-budget" class="flex items-center justify-between gap-4 rounded-lg p-2 hover:bg-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand dark:hover:bg-slate-900">
            <span><span class="block text-sm font-semibold rh-muted">Reported budget utilization</span><span class="mt-1 block text-sm font-semibold text-brand dark:text-rose-300">View budget breakdown &rarr;</span></span><strong class="text-xl font-bold tabular-nums">{{ $analytics['budget']['percentage'] !== null ? number_format($analytics['budget']['percentage'], 1).'%' : '—' }}</strong>
        </a>
    </section>
</div>
