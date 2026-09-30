<div class="flex flex-col gap-6 text-base text-slate-900 dark:text-slate-100" wire:key="research-head-dashboard" data-analytics-layout="workbench">
    @php
        $chartAxisMax = max(4, (int) ceil($analytics['trendMax'] / 4) * 4);
        $pipelineMax = max(1, (int) $analytics['pipeline']->max('count'));
        $projectTotal = $analytics['projects']->count();
    @endphp

    <section class="rh-panel p-4" aria-label="Analytics filters">
        <form wire:submit="applyFilters" class="flex flex-wrap items-end gap-3">
            <div class="min-w-40 flex-1">
                <label for="analytics-year" class="block text-sm font-semibold">Academic year</label>
                <select id="analytics-year" wire:model.live="academicYear" class="rh-control mt-1.5 w-full">
                    <option value="">All academic years</option>
                    @foreach ($academicYears as $year)<option value="{{ $year }}">{{ $year }}</option>@endforeach
                </select>
            </div>
            <div class="min-w-40 flex-1"><label for="analytics-from" class="block text-sm font-semibold">First submitted from</label><input id="analytics-from" type="date" wire:model="fromDate" class="rh-control mt-1.5 w-full"></div>
            <div class="min-w-40 flex-1"><label for="analytics-to" class="block text-sm font-semibold">First submitted through</label><input id="analytics-to" type="date" wire:model="toDate" class="rh-control mt-1.5 w-full"></div>
            <button class="rh-button" wire:loading.attr="disabled">Apply</button>
            <button type="button" wire:click="resetAnalyticsFilters" class="rh-button-secondary">Reset</button>
        </form>
        @foreach (['academicYear', 'fromDate', 'toDate', 'submissionMonth'] as $field)
            @error($field)<p role="alert" class="mt-2 text-sm text-red-700 dark:text-red-300">{{ $message }}</p>@enderror
        @endforeach
        @if ($academicYear && ! $analytics['target'])<p class="mt-3 text-sm text-amber-800 dark:text-amber-300">Set this academic year's dates and targets below to complete the annual comparison.</p>@endif
    </section>

    <section class="rh-panel grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-6" aria-label="Research analytics KPIs" data-dashboard-kpi-band>
        @foreach ([['review', 'Awaiting your review', 'awaiting_review'], ['active', 'Active projects', ''], ['delayed', 'Delayed / overdue', ''], ['completed', 'Completed projects', ''], ['faculty', 'Faculty in research', '']] as [$key, $label, $filter])
            @if ($filter)
                <button type="button" wire:click="setPipeline('{{ $filter }}')" aria-pressed="{{ $pipeline === $filter ? 'true' : 'false' }}" class="flex flex-col justify-between gap-3 border-r border-slate-100 p-5 text-left hover:bg-brand-wash focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand dark:border-slate-800 dark:hover:bg-slate-800">
            @else
                <a href="{{ $key === 'faculty' ? route('research_head.faculty-directory.index') : '#active-projects' }}" @if ($key !== 'faculty') wire:click="showProjects('{{ in_array($key, ['active', 'faculty'], true) ? 'active' : $key }}')" @endif class="flex flex-col justify-between gap-3 border-r border-slate-100 p-5 hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800">
            @endif
                <span class="text-sm font-semibold rh-muted">{{ $label }}</span>
                <strong class="text-4xl font-bold tracking-tight tabular-nums {{ $key === 'review' ? 'text-brand dark:text-rose-300' : ($key === 'delayed' && $analytics['kpis'][$key] ? 'text-amber-700 dark:text-amber-300' : '') }}">{{ $analytics['kpis'][$key] }}</strong>
                <span class="inline-flex items-center gap-2 text-xs font-semibold text-brand dark:text-rose-300">{{ $filter ? 'Filter review queue' : ($key === 'faculty' ? 'View faculty directory' : 'View projects') }} <span aria-hidden="true">&rarr;</span></span>
            @if ($filter)</button>@else</a>@endif
        @endforeach
        <a href="#reported-budget" class="flex flex-col justify-between gap-3 p-5 hover:bg-slate-50 dark:hover:bg-slate-800">
            <span class="text-sm font-semibold rh-muted">Reported budget utilization</span>
            <strong class="text-4xl font-bold tracking-tight tabular-nums">{{ $analytics['budget']['percentage'] !== null ? number_format($analytics['budget']['percentage'], 1).'%' : '—' }}</strong>
            <span class="inline-flex items-center gap-2 text-xs font-semibold text-brand dark:text-rose-300">View budget breakdown <span aria-hidden="true">&rarr;</span></span>
        </a>
    </section>

    <div class="grid items-start gap-5 xl:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
        <section class="rh-panel p-5" aria-labelledby="monthly-trend-heading">
            <h3 id="monthly-trend-heading" class="rh-title">Monthly submission trend</h3>
            <p class="mt-1 text-xs font-medium text-brand dark:text-rose-300">Click a month to filter proposals below.</p>
            <p class="mt-1 text-sm rh-muted">{{ $analytics['trendStart'] }} — {{ $analytics['trendEnd'] }}</p>
            <div class="mt-5 flex flex-wrap gap-x-6 gap-y-2 text-base">
                <span class="flex items-center gap-2"><span class="h-3 w-3 rounded-sm bg-brand" aria-hidden="true"></span> New proposals <strong class="tabular-nums">{{ $analytics['trend']->sum('new') }}</strong></span>
                <span class="flex items-center gap-2"><span class="h-3 w-3 rounded-sm bg-slate-400" aria-hidden="true"></span> Revisions <strong class="tabular-nums">{{ $analytics['trend']->sum('revision') }}</strong></span>
            </div>
            @if ($analytics['trend']->sum('new') + $analytics['trend']->sum('revision') > 0)
                <div class="mt-6 flex gap-3" data-submission-chart>
                    <div class="relative h-52 w-9 shrink-0 text-right text-sm tabular-nums rh-muted" aria-label="Proposal count axis">
                        @foreach (range(0, 4) as $tick)<span class="absolute right-0 -translate-y-1/2" style="top: {{ $tick * 25 }}%">{{ (int) ($chartAxisMax * (4 - $tick) / 4) }}</span>@endforeach
                    </div>
                    <div class="min-w-0 flex-1 overflow-x-auto pb-2">
                        <div class="relative" style="min-width: {{ max(280, $analytics['trend']->count() * 52) }}px">
                            <div class="pointer-events-none absolute inset-x-0 top-0 h-52" aria-hidden="true">
                                @foreach (range(0, 4) as $tick)<div class="absolute inset-x-0 border-t border-slate-100 dark:border-slate-800" style="top: {{ $tick * 25 }}%"></div>@endforeach
                            </div>
                            <div class="relative flex gap-2">
                                @foreach ($analytics['trend'] as $month)
                                    <button type="button" wire:click="$set('submissionMonth', '{{ $submissionMonth === $month['key'] ? '' : $month['key'] }}')" class="min-w-12 flex-1 rounded-md px-1 text-center hover:bg-brand-wash dark:hover:bg-rose-950/40 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand {{ $submissionMonth === $month['key'] ? 'bg-brand-wash dark:bg-rose-950/40 ring-1 ring-brand' : '' }}" aria-label="{{ $month['label'] }}: {{ $month['new'] }} new proposals and {{ $month['revision'] }} revisions. Filter inbox." aria-pressed="{{ $submissionMonth === $month['key'] ? 'true' : 'false' }}" title="{{ $month['label'] }}: {{ $month['new'] }} new · {{ $month['revision'] }} revisions">
                                        <span class="flex h-52 items-end justify-center gap-1.5" aria-hidden="true">
                                            <span class="w-4 rounded-t-md bg-brand dark:bg-rose-400 sm:w-5" style="height: {{ 100 * $month['new'] / $chartAxisMax }}%"></span>
                                            <span class="w-4 rounded-t-md bg-slate-400 sm:w-5" style="height: {{ 100 * $month['revision'] / $chartAxisMax }}%"></span>
                                        </span>
                                        <span class="mt-3 block text-sm font-medium rh-muted">{{ $month['label'] }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
                @if ($submissionMonth)
                    @php
                        $selectedMonth = $analytics['trend']->firstWhere('key', $submissionMonth);
                    @endphp
                    @if ($selectedMonth)<p role="status" class="mt-4 text-sm font-semibold text-brand dark:text-rose-300">{{ $selectedMonth['label'] }}: {{ $selectedMonth['new'] }} new proposals · {{ $selectedMonth['revision'] }} revisions</p>@endif
                @else
                    <p class="mt-4 text-sm rh-muted">Select a month to filter the proposal inbox.</p>
                @endif
            @else
                <div class="mt-6 flex min-h-52 items-center justify-center rounded-xl bg-slate-50 px-6 dark:bg-slate-950/50 text-center"><p class="text-base rh-muted">No data yet{{ $analytics['periodAvailable'] ? ' for this period.' : ' — set academic-year dates.' }}</p></div>
            @endif
        </section>
        <section class="rh-panel p-5" aria-labelledby="pipeline-heading">
            <div class="flex items-center justify-between gap-3"><h3 id="pipeline-heading" class="rh-title">Proposal pipeline</h3><span class="rh-badge">{{ $analytics['pipeline']->sum('count') }} total</span></div>
            <p class="mt-2 text-xs rh-muted">Compare stage counts. Click a stage to filter proposals below.</p>
            <div class="mt-4 flex flex-col gap-1">
                @foreach ($analytics['pipeline'] as $stage)
                    <button type="button" wire:click="setPipeline('{{ $stage['key'] }}')" aria-pressed="{{ $pipeline === $stage['key'] ? 'true' : 'false' }}" class="rounded-xl px-2 py-1.5 text-left hover:bg-brand-wash dark:hover:bg-rose-950/40 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand {{ $pipeline === $stage['key'] ? 'bg-brand-wash dark:bg-rose-950/40' : '' }}">
                        <span class="flex items-center justify-between gap-3 text-sm font-semibold"><span>{{ $stage['label'] }} @if ($pipeline === $stage['key'])<span class="ml-1 text-xs text-brand dark:text-rose-300">Selected</span>@endif</span><strong class="text-lg tabular-nums">{{ $stage['count'] }}</strong></span>
                        <span class="mt-2 block h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800" aria-hidden="true"><span class="block h-full rounded-full bg-brand dark:bg-rose-400" style="width: {{ 100 * $stage['count'] / $pipelineMax }}%"></span></span>
                    </button>
                @endforeach
            </div>
            @if ($analytics['pipeline']->sum('count') === 0)<p class="mt-3 text-sm rh-muted">No data yet.</p>@endif
        </section>
    </div>


    <div class="grid items-start gap-5 xl:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]" data-dashboard-performance-grid>
    <section id="annual-targets" class="rh-panel scroll-mt-40 border-t-4 border-t-brand dark:border-t-brand-soft" aria-labelledby="targets-heading">
        <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-100 px-5 py-4 dark:border-slate-800">
            <div><h3 id="targets-heading" class="rh-title">Annual research targets</h3><p class="mt-1 text-sm rh-muted">{{ $academicYear ?: 'Select an academic year to compare achievement with its targets.' }}</p></div>
            <button type="button" wire:click="editTargets" aria-expanded="{{ $editingTargets ? 'true' : 'false' }}" aria-controls="annual-target-editor" class="rh-button">{{ $editingTargets ? 'Close target settings' : ($analytics['target'] ? 'Edit annual targets' : 'Set annual targets') }}</button>
        </div>
        <div class="grid divide-y divide-slate-100 dark:divide-slate-800 sm:grid-cols-3 sm:divide-x sm:divide-y-0" data-annual-achievements>
            @foreach ($analytics['targets'] as $metric)
                @php
                    $achievementPercent = $metric['target'] > 0 && $metric['actual'] !== null ? round(100 * $metric['actual'] / $metric['target'], 1) : null;
                    $targetLabel = ['Research projects', 'Publications', 'Faculty participation'][$loop->index];
                @endphp
                <div class="flex flex-col gap-3 p-5">
                    <h4 class="text-base font-semibold">{{ $targetLabel }}</h4>
                    @if ($achievementPercent !== null)
                        <div class="flex flex-wrap items-center gap-3">
                            <div class="relative h-20 w-20 shrink-0" role="progressbar" aria-label="{{ $targetLabel }} annual target achievement" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ min(100, $achievementPercent) }}" aria-valuetext="{{ $metric['actual'] }} achieved against a target of {{ $metric['target'] }}">
                                <svg class="h-full w-full -rotate-90" viewBox="0 0 100 100" fill="none" aria-hidden="true">
                                    <circle cx="50" cy="50" r="42" stroke-width="7" class="stroke-slate-100 dark:stroke-slate-800" />
                                    @if ($achievementPercent > 0)
                                        <circle cx="50" cy="50" r="42" stroke-width="7" stroke-linecap="round" pathLength="100" stroke-dasharray="100" stroke-dashoffset="{{ 100 - min(100, $achievementPercent) }}" class="stroke-brand dark:stroke-rose-400" />
                                    @endif
                                </svg>
                                <strong class="absolute inset-0 flex items-center justify-center text-3xl font-bold tabular-nums">{{ $metric['actual'] }}</strong>
                            </div>
                            <div class="flex min-w-0 flex-col gap-1 text-sm">
                                <span class="text-base font-semibold">of {{ $metric['target'] }} target</span>
                                <span class="font-semibold text-brand dark:text-rose-300">{{ $achievementPercent }}% achieved</span>
                                <span class="rh-muted">{{ $metric['actual'] >= $metric['target'] ? 'Target met' : ($metric['target'] - $metric['actual']).' to go' }}</span>
                            </div>
                        </div>
                    @else
                        <div class="flex flex-wrap items-baseline gap-2 tabular-nums">
                            <strong class="text-4xl font-bold tracking-tight">{{ $metric['actual'] ?? '—' }}</strong>
                            <span class="text-base rh-muted">{{ $metric['target'] !== null ? 'of '.$metric['target'].' target' : ($metric['actual'] === null ? 'No data yet' : 'achieved') }}</span>
                        </div>
                        <p class="text-sm rh-muted">{{ $metric['target'] === 0 ? 'Zero target set; percentage not applicable.' : ($metric['actual'] === null ? 'Year dates needed' : ($academicYear && $analytics['target'] && ! $analytics['targetMatches'] ? 'Annual target hidden for this date range' : 'No target set')) }}</p>
                    @endif
                </div>
            @endforeach
        </div>
        @if ($academicYear && $analytics['target'] && ! $analytics['targetMatches'])<p class="border-t border-slate-100 dark:border-slate-800 px-6 py-3 text-sm text-amber-800 dark:text-amber-300">Select the full academic year to compare with annual targets.</p>@endif
        @if ($editingTargets)
            <div id="annual-target-editor" class="border-t border-slate-100 dark:border-slate-800 bg-slate-50 p-5 dark:bg-slate-950/50">
                <h4 class="text-lg font-semibold">Set the year's goals</h4>
                <form wire:submit="saveTargets" class="mt-4 grid gap-4 sm:grid-cols-3">
                    @foreach (['academic_year' => ['Academic year', 'text'], 'starts_on' => ['Year starts', 'date'], 'ends_on' => ['Year ends', 'date'], 'projects_target' => ['Research projects target', 'number'], 'publications_target' => ['Publications target', 'number'], 'faculty_target' => ['Faculty participation target', 'number']] as $field => [$label, $type])
                        <div>
                            <label for="target-{{ $field }}" class="block text-sm font-semibold">{{ $label }}</label>
                            <input id="target-{{ $field }}" type="{{ $type }}" wire:model="targetForm.{{ $field }}" @if ($type === 'number') min="0" max="1000000" step="1" @endif @if ($field === 'academic_year') placeholder="e.g. 2026-2027" @endif class="rh-control mt-2 w-full">
                            @error('targetForm.'.$field)<p role="alert" class="mt-2 text-sm text-red-700 dark:text-red-300">{{ $message }}</p>@enderror
                        </div>
                    @endforeach
                    <div class="flex flex-wrap items-center gap-4 sm:col-span-3">
                        <button wire:loading.attr="disabled" class="rh-button">Save year and targets</button>
                        <p class="text-sm rh-muted">Leave unknown targets blank. Use 0 for an intentional zero target.</p>
                    </div>
                </form>
            </div>
        @endif
        @if (session('targets_saved'))<p role="status" class="border-t border-slate-100 dark:border-slate-800 px-6 py-3 text-sm font-semibold text-emerald-700 dark:text-emerald-300">{{ session('targets_saved') }}</p>@endif
    </section>

        <section id="reported-budget" class="rh-panel scroll-mt-40 flex flex-col gap-5 p-5">
            <div><h3 class="rh-title">Reported budget utilization</h3><p class="mt-1 text-sm rh-muted">Latest submitted reports · not official accounting</p></div>
            @if ($analytics['budget']['percentage'] !== null)
                <strong class="text-4xl font-bold tracking-tight tabular-nums text-brand dark:text-rose-300">{{ number_format($analytics['budget']['percentage'], 1) }}%</strong>
                <div class="h-3 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800" role="progressbar" aria-label="Reported budget utilization" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ min(100, max(0, $analytics['budget']['percentage'])) }}" aria-valuetext="{{ $analytics['budget']['percentage'] }} percent reported utilization"><div class="h-full rounded-full bg-brand dark:bg-rose-400" style="width: {{ min(100, max(0, $analytics['budget']['percentage'])) }}%"></div></div>
                <div class="grid grid-cols-2 gap-4 text-sm"><div><p class="rh-muted">Reported spending</p><p class="mt-1 text-lg font-semibold tabular-nums">₱{{ number_format($analytics['budget']['utilized'], 2) }}</p></div><div><p class="rh-muted">Recorded budget</p><p class="mt-1 text-lg font-semibold tabular-nums">₱{{ number_format($analytics['budget']['budget'], 2) }}</p></div></div>
            @else
                <p class="py-6 text-base rh-muted">No data yet — usable budget reports are needed.</p>
            @endif
        </section>
    </div>

    <section class="rh-panel p-5" aria-labelledby="project-status-heading">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h3 id="project-status-heading" class="rh-title">Project status analytics</h3>
            <span class="rh-badge">{{ $projectTotal }} issued projects</span>
        </div>
        <p class="mt-2 text-sm rh-muted">Share of issued projects in each status. Click a row to view the matching projects below.</p>
        <div class="mt-5 space-y-2" data-project-distribution-chart>
            @foreach ($analytics['projectStatuses'] as $stage)
                @php
                    $share = $projectTotal ? round(100 * $stage['count'] / $projectTotal, 1) : 0;
                @endphp
                <button type="button" wire:click="showProjects('{{ $projectStatus === $stage['key'] ? '' : $stage['key'] }}')"
                    aria-pressed="{{ $projectStatus === $stage['key'] ? 'true' : 'false' }}"
                    aria-label="Filter {{ $stage['label'] }} projects: {{ $stage['count'] }} of {{ $projectTotal }}"
                    class="grid w-full gap-2 rounded-lg border p-3 text-left hover:border-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand sm:grid-cols-[15rem_minmax(0,1fr)_5rem] sm:items-center {{ $projectStatus === $stage['key'] ? 'border-brand bg-brand-wash dark:border-rose-400 dark:bg-rose-950/40' : 'border-transparent hover:bg-slate-50 dark:hover:bg-slate-800' }}">
                    <span class="text-sm font-medium">{{ $stage['label'] }} @if ($projectStatus === $stage['key'])<span class="block text-xs font-semibold text-brand dark:text-rose-300">Selected filter</span>@endif</span>
                    <span class="block h-5 overflow-hidden rounded bg-slate-100 dark:bg-slate-800" aria-hidden="true">
                        <span class="block h-full rounded {{ $stage['key'] === 'delayed' ? 'bg-amber-500' : ($stage['key'] === 'completed' ? 'bg-emerald-600' : 'bg-brand dark:bg-rose-400') }}" style="width: {{ $share }}%"></span>
                    </span>
                    <span class="text-sm font-semibold tabular-nums sm:text-right">{{ $stage['count'] }} <span class="text-xs font-normal rh-muted">({{ $share }}%)</span></span>
                </button>
            @endforeach
        </div>
        @if ($projectTotal === 0)<p class="mt-3 text-sm rh-muted">No issued projects in this selection. Shares will appear when a Notice to Proceed is issued.</p>@endif
        @if ($analytics['unknownSchedules'])<p class="mt-3 text-sm text-amber-800 dark:text-amber-300">{{ $analytics['unknownSchedules'] }} active projects have no recorded schedule.</p>@endif
    </section>

    <div class="grid items-start gap-5 xl:grid-cols-2" data-dashboard-compact-queues>
        <section id="needs-attention" class="rh-panel scroll-mt-40" aria-labelledby="attention-heading">
            <div class="rh-panel-heading">
                <h3 id="attention-heading" class="rh-title">Needs attention</h3>
                <span class="rh-badge">{{ $analytics['attention']->count() }} issues</span>
            </div>
            <div class="overflow-x-auto">
                <table class="rh-table" data-dashboard-queue="attention">
                    <thead><tr><th scope="col" class="w-[72%]">Research / issue</th><th scope="col">Waiting</th></tr></thead>
                    <tbody>
                        @forelse ($attentionItems as $item)
                            <tr>
                                <td>
                                    <a href="{{ $item['url'] }}" aria-label="Open {{ $item['title'] }}: {{ $item['issue'] }}" class="block truncate font-semibold hover:text-brand dark:hover:text-rose-300" title="{{ $item['title'] }}">{{ $item['title'] }}</a>
                                    <span class="mt-1 block text-sm text-brand dark:text-rose-300">{{ $item['issue'] }}</span>
                                    <span class="rh-muted block text-sm">{{ $item['status'] }}</span>
                                </td>
                                <td>
                                    <strong class="tabular-nums">{{ $item['days'] !== null ? $item['days'].' days' : '—' }}</strong>
                                    <span class="rh-muted block text-sm">{{ $item['days'] !== null ? $item['basis'] : 'No recorded date' }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="2"><p class="rh-muted py-7 text-center">No data yet or no items need attention in this cohort.</p></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($attentionItems->hasPages())
                <div class="border-t border-slate-100 px-5 py-3 dark:border-slate-800">
                    <p class="rh-muted mb-2 text-sm">{{ $attentionItems->firstItem() }}–{{ $attentionItems->lastItem() }} of {{ $attentionItems->total() }} issues</p>
                    {{ $attentionItems->links('livewire::simple-tailwind', ['scrollTo' => '#needs-attention']) }}
                </div>
            @endif
        </section>

        <section id="received-proposals" class="rh-panel scroll-mt-40" aria-labelledby="inbox-heading">
            <div class="rh-panel-heading">
                <h3 id="inbox-heading" class="rh-title">Received proposal inbox</h3>
                <span class="rh-badge">{{ $topics->total() }} proposals</span>
            </div>
            <div class="flex flex-col gap-2 border-b border-slate-100 px-5 py-3 dark:border-slate-800" aria-label="Inbox controls">
                <div class="grid gap-2 sm:grid-cols-2">
                    <label for="proposal-search" class="sr-only">Search proposals</label>
                    <input id="proposal-search" type="search" wire:model.live.debounce.300ms="search" placeholder="Search proposal or faculty" class="rh-control w-full">
                    <label for="proposal-status" class="sr-only">Review status</label>
                    <select id="proposal-status" wire:model.live="status" class="rh-control w-full">
                        <option value="">All active review stages</option>
                        @foreach ($stageLabels as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                    </select>
                </div>
                @if ($pipeline || $status || $submissionMonth)
                    <div class="flex flex-wrap items-center gap-2">
                        @if ($pipeline || $status)<button type="button" wire:click="clearPipeline" class="rh-button-secondary">Clear stage</button>@endif
                        @if ($submissionMonth)<button type="button" wire:click="$set('submissionMonth', '')" class="rh-button-secondary">Clear month: {{ $submissionMonth }}</button>@endif
                    </div>
                @endif
            </div>
            <div class="overflow-x-auto">
                <table class="rh-table" data-dashboard-queue="proposals">
                    <thead><tr><th scope="col" class="w-[68%]">Proposal and lead</th><th scope="col">Status</th></tr></thead>
                    <tbody>
                        @forelse ($topics as $topic)
                            @php
                                $version = $topic->latestVersion;
                                $proposalUrl = route('topics.show', $topic).($topic->hasIssuedNoticeToProceed() ? '#project-monitoring' : '#proposal-review');
                            @endphp
                            <tr wire:key="proposal-row-{{ $topic->id }}">
                                <td>
                                    <a href="{{ $proposalUrl }}" class="block truncate font-semibold hover:text-brand dark:hover:text-rose-300" title="{{ $topic->title }}">{{ $topic->title }}</a>
                                    <span class="rh-muted block truncate">{{ $topic->user?->name }}</span>
                                    <span class="rh-muted mt-1 block truncate text-sm" title="{{ $topic->researchCall?->title ?? 'Independent submission' }}">{{ $version ? 'v'.$version->version_number.' · '.$version->files_count.' files' : 'No submitted version' }}{{ $version?->created_at ? ' · '.$version->created_at->format('M d, Y') : '' }}</span>
                                </td>
                                <td>
                                    <span class="rh-muted block text-sm">{{ $topic->researchHeadQueueStatusLabel($version) }}</span>
                                    <a href="{{ $proposalUrl }}" aria-label="Open {{ $topic->title }}" class="mt-2 inline-flex min-h-[44px] items-center rounded-lg bg-brand-wash px-3 text-sm font-semibold text-brand hover:bg-rose-100 dark:bg-rose-950/50 dark:text-rose-300">{{ $topic->status === 'ready_for_signature' ? 'Sign' : 'Open' }}</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="2"><p class="rh-muted py-7 text-center">No proposals found. Try changing the search or filters.</p></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($topics->hasPages())
                <div class="border-t border-slate-100 px-5 py-3 dark:border-slate-800">
                    <p class="rh-muted mb-2 text-sm">{{ $topics->firstItem() }}–{{ $topics->lastItem() }} of {{ $topics->total() }} proposals</p>
                    {{ $topics->links('livewire::simple-tailwind', ['scrollTo' => '#received-proposals']) }}
                </div>
            @endif
        </section>
    </div>

    <section id="active-projects" class="rh-panel scroll-mt-40 p-5" aria-labelledby="projects-heading">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h3 id="projects-heading" class="rh-title">Project completion</h3>
            <div class="flex flex-wrap gap-2">
                <label for="project-status" class="sr-only">Project status filter</label>
                <select id="project-status" wire:model.live="projectStatus" class="rh-control">
                    <option value="">All issued projects</option><option value="active">Active projects</option>
                    @foreach ($analytics['projectStatuses'] as $stage)<option value="{{ $stage['key'] }}">{{ $stage['label'] }}</option>@endforeach
                </select>
                <a href="{{ route('research_head.projects.index') }}" class="rh-button-secondary">Open monitoring</a>
            </div>
        </div>
        <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            @forelse ($projectItems as $project)
                <a href="{{ route('topics.show', $project['id']) }}#project-monitoring" class="flex flex-col gap-2 rounded-xl border border-slate-200 p-4 hover:border-brand dark:border-slate-800 dark:hover:border-brand-soft">
                    <div class="flex items-start justify-between gap-2">
                        <strong class="min-w-0 truncate text-base" title="{{ $project['title'] }}">{{ $project['title'] }}</strong>
                        <span class="shrink-0 text-sm font-semibold text-brand dark:text-rose-300">{{ $project['progress'] !== null ? $project['progress'].'%' : '—' }}</span>
                    </div>
                    <span class="rh-muted truncate text-sm">{{ $project['lead'] }}</span>
                    <span class="rh-muted text-sm">{{ $project['progress'] === null ? 'No monitoring tool submitted' : str($project['status'])->headline().' · reported completion' }}</span>
                    <div class="h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800" role="progressbar" aria-label="{{ $project['title'] }} completion" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ min(100, max(0, $project['progress'] ?? 0)) }}"><div class="h-full rounded-full bg-brand dark:bg-rose-400" style="width: {{ min(100, max(0, $project['progress'] ?? 0)) }}%"></div></div>
                    <div class="mt-1 flex flex-wrap justify-between gap-2 text-sm rh-muted">
                        <span>End: {{ $project['deadline'] ?? 'Not recorded' }}</span>
                        <span>{{ $project['percentage'] !== null ? number_format($project['percentage'], 1).'% · Latest report' : 'No utilization reported' }}</span>
                    </div>
                    <span class="rh-muted text-sm">Budget: {{ $project['budget'] !== null ? '₱'.number_format($project['budget'], 2) : 'not recorded' }}</span>
                </a>
            @empty
                <p class="rh-muted py-6 text-center md:col-span-2">No data yet for this project filter.</p>
            @endforelse
        </div>
        @if ($projectItems->hasPages())<div class="mt-4">{{ $projectItems->links('livewire::simple-tailwind', ['scrollTo' => '#active-projects']) }}</div>@endif
    </section>

    <details data-analytics-methodology class="rh-panel p-5 text-sm leading-6 rh-muted">
        <summary class="cursor-pointer font-semibold">How these numbers are calculated</summary>
        <div class="mt-4 grid gap-5 md:grid-cols-2">
            <div><h4 class="font-semibold text-slate-900 dark:text-slate-100">Filters and submissions</h4><p>KPIs and the pipeline show current status of the first-submission cohort. Revisions do not start a new cohort. Academic year uses the research call or saved year dates for independent submissions. Monthly charts count recorded events, including revisions of older proposals; missing legacy events are not invented. Calendar deadlines remain institution-wide.</p></div>
            <div><h4 class="font-semibold text-slate-900 dark:text-slate-100">Workflow and completion</h4><p>Submitted means unopened pending proposals. Approval without an issued Notice to Proceed stays in final signing. Rejected proposals are excluded from the pipeline. Completed projects stay completed; reported 100% awaits a completion decision. Other projects are checked for recorded delays or incomplete weighted milestones, missing reports/review, then ongoing status.</p></div>
            <div><h4 class="font-semibold text-slate-900 dark:text-slate-100">Annual achievement</h4><p>Projects count Notices to Proceed issued in the period. Publications count dated published journal outputs, deduplicated by normalized title; acceptance is not publication. Participation uses currently recorded faculty leads and accepted collaborators on projects issued or scheduled to overlap the period. Historical departures and completion dates are not logged, so this is recorded participation, not historical attendance.</p></div>
            <div><h4 class="font-semibold text-slate-900 dark:text-slate-100">Reported budgets</h4><p>{{ $analytics['budget']['reported'] }} of {{ $analytics['budget']['total'] }} projects have usable reported figures. Only each project's latest submitted monitoring snapshot is included. Reports do not distinguish period-only from cumulative spending, so quarters are not summed. Missing reports are excluded, not counted as zero spending. Remaining-budget alerts are snapshot checks for ended/completed projects, not verified unspent funds.</p></div>
        </div>
        @if ($analytics['undatedPublications'])<p class="mt-4 rounded-xl bg-amber-50 px-4 py-3 text-amber-900 dark:bg-amber-950/30 dark:text-amber-300">{{ $analytics['undatedPublications'] }} publication records have no exact publication date and are excluded from annual achievements.</p>@endif
    </details>
</div>
