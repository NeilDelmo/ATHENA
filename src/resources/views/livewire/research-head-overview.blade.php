<div class="space-y-5 text-slate-900 dark:text-slate-100" data-research-head-overview>
    @php
        $filterParams = array_filter(['academicYear' => $academicYear, 'fromDate' => $fromDate, 'toDate' => $toDate]);
        $analyticsUrl = route('research_head.analytics', $filterParams);
        $selectedStage = $pipeline === 'awaiting_review' ? 'Awaiting your review' : ($analytics['pipeline']->firstWhere('key', $pipeline)['label'] ?? 'All review stages');
    @endphp

    <section class="grid grid-cols-2 gap-3 md:grid-cols-4" aria-label="Priority summary" data-dashboard-priority-kpis>
        <a href="#received-proposals" wire:click="showReviewQueue" class="flex flex-col items-start justify-between gap-4 rounded-xl bg-brand px-5 py-5 text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand">
            <span><span class="block text-sm font-semibold">Proposals awaiting review</span><span class="mt-1 block text-sm text-rose-100">Oldest submissions first</span></span>
            <strong class="text-4xl font-bold tracking-tight tabular-nums">{{ $analytics['kpis']['review'] }}</strong>
        </a>
        <a href="#dashboard-report-reviews" class="flex flex-col items-start justify-between gap-4 rounded-xl border border-red-200 bg-white px-5 py-5 hover:border-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand dark:border-red-900 dark:bg-slate-900">
            <span><span class="block text-sm font-semibold">Reports awaiting review</span><span class="mt-1 block text-sm rh-muted">Monitoring, progress, terminal</span></span>
            <strong class="text-4xl font-bold tracking-tight tabular-nums text-brand dark:text-rose-300">{{ $reportItems->total() }}</strong>
        </a>
        <a wire:navigate href="{{ route('research_head.analytics', [...$filterParams, 'projectStatus' => 'delayed']) }}#active-projects" class="flex flex-col items-start justify-between gap-4 rounded-xl border border-slate-200 bg-white px-5 py-5 hover:border-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand dark:border-slate-800 dark:bg-slate-900">
            <span><span class="block text-sm font-semibold">Delayed / overdue</span><span class="mt-1 block text-sm rh-muted">Schedule or milestone passed</span></span>
            <strong class="text-4xl font-bold tracking-tight tabular-nums {{ $analytics['kpis']['delayed'] ? 'text-brand dark:text-rose-300' : '' }}">{{ $analytics['kpis']['delayed'] }}</strong>
        </a>
        <a wire:navigate href="{{ route('research_head.analytics', [...$filterParams, 'projectStatus' => 'active']) }}#active-projects" class="flex flex-col items-start justify-between gap-4 rounded-xl border border-slate-200 bg-white px-5 py-5 hover:border-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand dark:border-slate-800 dark:bg-slate-900">
            <span><span class="block text-sm font-semibold">Active projects</span><span class="mt-1 block text-sm rh-muted">Notice to Proceed issued</span></span>
            <strong class="text-4xl font-bold tracking-tight tabular-nums">{{ $analytics['kpis']['active'] }}</strong>
        </a>
    </section>

    <livewire:research-target-board />

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
                    <svg class="ml-auto h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true">
                        <path d="M5 12h14" />
                        <path d="M12 5v14" class="group-open:hidden" />
                    </svg>
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
        @if ($academicYear || $fromDate || $toDate)<p class="mt-3 text-xs rh-muted" data-dashboard-scope>Counts follow first-submission filters. Calendar deadlines remain institution-wide.</p>@endif
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
                    <label for="overview-stage" class="sr-only">Review stage</label>
                    <select id="overview-stage" wire:model.live="pipeline" class="rh-control w-full !rounded-lg sm:w-48">
                        <option value="" @selected($pipeline === '')>All review stages</option>
                        <option value="awaiting_review" @selected($pipeline === 'awaiting_review')>Awaiting your review</option>
                        @foreach ($analytics['pipeline'] as $stage)<option value="{{ $stage['key'] }}" @selected($pipeline === $stage['key'])>{{ $stage['label'] }}</option>@endforeach
                    </select>
                    @if ($pipeline)<button type="button" wire:click="clearPipeline" class="rh-button-secondary !rounded-lg">All stages</button>@endif
                </div>
                <p class="px-5 pb-3 text-sm rh-muted">Oldest waiting submissions first.</p>
                <div class="divide-y divide-slate-100 dark:divide-slate-800" data-dashboard-queue="proposals">
                    @forelse ($topics as $topic)
                        @php
                            $waitingSince = $topic->status_started_at ?? $topic->latestVersion?->created_at;
                            $waitingDays = $waitingSince ? (int) max(0, \Carbon\CarbonImmutable::parse($waitingSince)->diffInDays(now(), false)) : null;
                        @endphp
                        <a href="{{ route('topics.show', $topic) }}{{ $topic->hasIssuedNoticeToProceed() ? '#project-monitoring' : '#proposal-review' }}" aria-label="{{ match ($topic->status) { 'resubmitted' => 'Review revision', 'ready_for_signature', 'approved' => 'View signing status', default => 'Review proposal' } }}: {{ $topic->title }}" class="group flex items-start gap-3 px-5 py-5 transition-colors hover:bg-rose-50/60 focus-visible:outline focus-visible:outline-2 focus-visible:outline-inset focus-visible:outline-brand dark:hover:bg-slate-800/60" wire:key="overview-proposal-{{ $topic->id }}">
                            <span class="min-w-0 flex-1">
                                <strong class="block break-words text-base font-semibold leading-6 group-hover:text-brand dark:group-hover:text-rose-300">{{ $topic->title }}</strong>
                                <span class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm rh-muted"><span>{{ $topic->user?->name }}</span><span class="rounded-md bg-slate-100 px-2 py-1 font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-300">{{ $topic->researchHeadQueueStatusLabel($topic->latestVersion) }}</span></span>
                                <span class="mt-2 block text-sm font-medium text-brand dark:text-rose-300">{{ $waitingDays !== null ? $waitingDays.' '.str('day')->plural($waitingDays).($topic->status_started_at ? ' in this stage' : ' since submission') : 'Stage start date unavailable' }}</span>
                            </span>
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-rose-50 text-brand transition-colors group-hover:bg-rose-100 dark:bg-rose-950/40 dark:text-rose-300 dark:group-hover:bg-rose-950/70" aria-hidden="true"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M15 5.5 18.5 9M4 20l4.5-1L20 7.5a2.5 2.5 0 0 0-3.5-3.5L5 15.5 4 20Z" stroke-linecap="round" stroke-linejoin="round" /></svg></span>
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
            <a href="{{ route('topics.show', $report->topic_id) }}#{{ $reportAnchor }}" aria-label="Review {{ strtolower($reportLabel) }}: {{ $report->title }}" class="group flex items-start gap-3 px-5 py-5 transition-colors hover:bg-rose-50/60 focus-visible:outline focus-visible:outline-2 focus-visible:outline-inset focus-visible:outline-brand dark:hover:bg-red-950/30" wire:key="overview-report-{{ $report->report_type }}-{{ $report->id }}">
                <span class="min-w-0 flex-1">
                <strong class="block break-words text-base font-semibold leading-6">{{ $report->title }}</strong>
                <span class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-2 text-sm rh-muted"><span class="rounded-md bg-red-50 px-2 py-1 font-medium text-brand dark:bg-red-950/40 dark:text-rose-200">{{ $reportLabel }}</span><span>{{ $report->faculty_name }}</span></span>
                <span class="mt-2 block text-sm font-medium text-brand dark:text-rose-300">{{ $waitingDays !== null ? $waitingDays.' '.str('day')->plural($waitingDays).' waiting' : 'Submission date unavailable' }}@if ($report->report_date) <span class="font-normal rh-muted">· Report dated {{ \Carbon\CarbonImmutable::parse($report->report_date)->format('M j, Y') }}</span>@endif</span>
                </span>
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-rose-50 text-brand transition-colors group-hover:bg-rose-100 dark:bg-rose-950/40 dark:text-rose-300 dark:group-hover:bg-rose-950/70" aria-hidden="true"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-6-6Zm0 0v6h6M8 13h8m-8 4h5" stroke-linecap="round" stroke-linejoin="round" /></svg></span>
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
                            <span class="min-w-0"><strong class="block text-sm font-semibold">{{ $event['title'] }}</strong><span class="mt-1 block break-words text-sm rh-muted">{{ $event['context'] }}</span><x-dashboard-deadline :date="$event['at']" class="mt-2" /></span>
                        </a>
                    @empty
                        <p class="px-5 py-6 text-sm rh-muted">No official deadlines in the next 14 days.</p>
                    @endforelse
                </div>
            </section>
    </aside>

    <section aria-labelledby="dashboard-reporting-heading" class="border-t border-slate-200 pt-5 dark:border-slate-800" data-dashboard-reporting-links>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 id="dashboard-reporting-heading" class="text-xl font-bold">Planning & reporting</h2>
        </div>
        <div class="mt-4 grid gap-px overflow-hidden rounded-xl border border-slate-200 bg-slate-200 sm:grid-cols-2 dark:border-slate-800 dark:bg-slate-800">
            @foreach ([
                ['Research targets', route('research_head.targets.index'), 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Zm0 4a5 5 0 1 0 0 10 5 5 0 0 0 0-10Zm0 4a1 1 0 1 0 0 2 1 1 0 0 0 0-2Z'],
                ['College reports', route('research_head.quarterly-reports.index'), 'M9 5H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-3M9 3h6v4H9V3Zm-1 9h8m-8 4h5'],
                ['Quarterly monitoring', route('research_head.rms-quarterly-reports.index'), 'M6 3v3m12-3v3M3 10h18M5 5h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Zm2 9h3m4 0h3m-10 3h3'],
                ['Analytics', $analyticsUrl, 'M3 3v18h18M7 13v4m5-8v8m5-12v12'],
            ] as [$label, $url, $iconPath])
                <a wire:navigate href="{{ $url }}" class="group flex min-w-0 items-center gap-3 bg-white p-5 transition-colors hover:bg-rose-50/60 focus-visible:outline focus-visible:outline-2 focus-visible:outline-inset focus-visible:outline-brand dark:bg-slate-900 dark:hover:bg-slate-800">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-rose-50 text-brand transition-colors group-hover:bg-rose-100 dark:bg-rose-950/40 dark:text-rose-300 dark:group-hover:bg-rose-950/70" aria-hidden="true"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="{{ $iconPath }}" stroke-linecap="round" stroke-linejoin="round" /></svg></span>
                    <span class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $label }}</span>
                </a>
            @endforeach
        </div>
        <div class="mt-4 grid gap-4 sm:grid-cols-2" aria-label="Research summary">
        <a wire:navigate href="{{ route('research_head.analytics', [...$filterParams, 'projectStatus' => 'completed']) }}#active-projects" class="group flex items-center gap-3 rounded-xl bg-white px-5 py-4 transition-colors hover:bg-rose-50/60 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand dark:bg-slate-900 dark:hover:bg-slate-800">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-rose-50 text-brand dark:bg-rose-950/40 dark:text-rose-300" aria-hidden="true"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><circle cx="12" cy="12" r="9" /><path d="m8 12 3 3 5-6" stroke-linecap="round" stroke-linejoin="round" /></svg></span>
            <span class="min-w-0 flex-1 text-sm font-semibold">Completed projects</span><strong class="text-xl font-bold tabular-nums">{{ $analytics['kpis']['completed'] }}</strong>
        </a>
        <a wire:navigate href="{{ route('research_head.faculty-directory.index') }}" class="group flex items-center gap-3 rounded-xl bg-white px-5 py-4 transition-colors hover:bg-rose-50/60 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand dark:bg-slate-900 dark:hover:bg-slate-800">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-rose-50 text-brand dark:bg-rose-950/40 dark:text-rose-300" aria-hidden="true"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m20 0v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" stroke-linecap="round" stroke-linejoin="round" /><circle cx="9" cy="7" r="4" /></svg></span>
            <span class="min-w-0 flex-1 text-sm font-semibold">Faculty in active research</span><strong class="text-xl font-bold tabular-nums">{{ $analytics['kpis']['faculty'] }}</strong>
        </a>
        </div>
    </section>
</div>
