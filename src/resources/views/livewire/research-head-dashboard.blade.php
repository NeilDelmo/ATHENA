<div class="space-y-4 text-slate-900" wire:key="research-head-dashboard">
    <section data-dashboard-section-navigation class="sticky top-[128px] z-20 flex flex-col gap-3 rounded-lg border border-slate-200 bg-white/95 px-4 py-3 shadow-md backdrop-blur lg:flex-row lg:items-center lg:justify-between" aria-labelledby="proposal-pipeline-heading">
        <div>
            <h3 id="proposal-pipeline-heading" class="text-sm font-bold text-slate-950">Proposal pipeline</h3>
            <p class="mt-0.5 text-xs text-slate-500">Current workload, review activity, project delivery, and deadlines.</p>
        </div>
        <nav class="flex flex-wrap gap-1 rounded-md bg-slate-100 p-1 text-xs font-semibold" aria-label="Dashboard sections">
            <a href="#research-calendar" class="shrink-0 whitespace-nowrap rounded px-3 py-1.5 text-[#800000] hover:bg-white">Calendar</a>
            <a href="#needs-attention" class="shrink-0 whitespace-nowrap rounded px-3 py-1.5 text-slate-600 hover:bg-white hover:text-[#800000]">Needs attention</a>
            <a href="#active-projects" class="shrink-0 whitespace-nowrap rounded px-3 py-1.5 text-slate-600 hover:bg-white hover:text-[#800000]">Active projects</a>
            <a href="#received-proposals" class="shrink-0 whitespace-nowrap rounded px-3 py-1.5 text-slate-600 hover:bg-white hover:text-[#800000]">Proposal inbox</a>
        </nav>
    </section>

    <section class="grid grid-cols-2 gap-3 xl:grid-cols-4" aria-label="Research pipeline summary">
        @php
            $statCards = [
                ['awaiting_review', 'Awaiting your review', 'M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z', 'border-amber-200 bg-amber-50 text-amber-800'],
                ['revision_requested', 'Awaiting faculty revision', 'M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.862 4.487z', 'border-sky-200 bg-sky-50 text-sky-800'],
                ['deadlines', 'Deadlines in 14 days', 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5', 'border-rose-200 bg-rose-50 text-[#800000]'],
                ['approved', 'Active projects', 'M15.59 14.37a6 6 0 01-5.84 7.38v-4.8m5.84-2.58a14.98 14.98 0 006.16-12.12A14.98 14.98 0 009.631 8.41m5.96 5.96a14.926 14.926 0 01-5.841 2.58m-.119-8.54a6 6 0 00-7.381 5.84h4.8m2.581-5.84a14.927 14.927 0 00-2.58 5.84', 'border-emerald-200 bg-emerald-50 text-emerald-800'],
            ];
        @endphp
        @foreach ($statCards as [$key, $label, $icon, $tone])
            @if ($key === 'deadlines')
                <a href="#dashboard-deadlines" class="group rounded-lg border border-slate-200 bg-white p-4 text-left shadow-sm transition hover:border-rose-300 hover:shadow-md">
            @else
                <button type="button" wire:click="setPipeline('{{ $key }}')" aria-pressed="{{ $pipeline === $key ? 'true' : 'false' }}" class="group rounded-lg border p-4 text-left shadow-sm transition hover:shadow-md {{ $pipeline === $key ? 'border-[#800000] bg-[#800000] text-white' : 'border-slate-200 bg-white' }}">
            @endif
                    <span class="flex items-start justify-between gap-3">
                        <span class="inline-flex h-9 w-9 items-center justify-center rounded-md border {{ $pipeline === $key && $key !== 'deadlines' ? 'border-white/20 bg-white/10 text-white' : $tone }}">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}" /></svg>
                        </span>
                        <strong class="text-2xl font-bold tabular-nums">{{ $summary[$key] }}</strong>
                    </span>
                    <span class="mt-3 block text-xs font-semibold {{ $pipeline === $key && $key !== 'deadlines' ? 'text-rose-100' : 'text-slate-600' }}">{{ $label }}</span>
            @if ($key === 'deadlines')
                </a>
            @else
                </button>
            @endif
        @endforeach
    </section>

    <div id="research-calendar" class="scroll-mt-64">
        <livewire:dashboard-calendar />
    </div>

    <div class="grid grid-cols-1 items-start gap-4 xl:grid-cols-12">
        <div class="contents">
            <section id="received-proposals" class="order-3 scroll-mt-64 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm xl:col-span-12" aria-labelledby="received-proposals-heading">
                <div class="flex flex-col gap-3 border-b border-slate-200 bg-slate-50/80 px-4 py-3 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-[#800000]">Decision queue</p>
                        <h3 id="received-proposals-heading" class="mt-0.5 text-sm font-bold text-slate-950">Received proposal inbox</h3>
                        <p class="mt-0.5 text-[11px] text-slate-500">{{ $topics->total() }} {{ str('proposal')->plural($topics->total()) }}{{ $attention === 'repeat' ? ' · repeated revision requests' : '' }}</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2" aria-label="Inbox controls">
                        <span class="sr-only">Inbox controls</span>
                        <label class="sr-only" for="proposal-search">Search proposals</label>
                        <input id="proposal-search" wire:model.live.debounce.300ms="search" type="search" placeholder="Search proposal or faculty" class="w-full rounded-md border-slate-200 bg-white py-2 text-xs shadow-sm focus:border-[#800000] focus:ring-[#800000] sm:w-52">
                        <label class="sr-only" for="proposal-status">Review status</label>
                        <select id="proposal-status" wire:model.live="status" class="max-w-48 rounded-md border-slate-200 bg-white py-2 pl-3 pr-8 text-xs font-semibold shadow-sm focus:border-[#800000] focus:ring-[#800000]">
                            <option value="">All statuses</option>
                            @foreach ($stageLabels + ['approved' => 'Approved', 'rejected' => 'Rejected'] as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @if ($search !== '' || $pipeline || $status || $attention)
                            <button wire:click="$set('search', ''); clearPipeline()" type="button" class="rounded-md border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-[#800000] hover:bg-rose-50">Reset</button>
                        @endif
                    </div>
                </div>

                <div class="w-full">
                    <table class="w-full table-fixed text-left">
                        <colgroup>
                            <col class="w-[35%]">
                            <col class="w-[23%]">
                            <col class="w-[14%]">
                            <col class="w-[17%]">
                            <col class="w-[11%]">
                        </colgroup>
                        <thead class="border-b border-slate-200 bg-slate-50 text-[10px] font-bold uppercase tracking-wider text-slate-500">
                            <tr>
                                <th class="whitespace-nowrap px-4 py-2.5">Proposal and lead</th>
                                <th class="whitespace-nowrap px-4 py-2.5">Research call</th>
                                <th class="whitespace-nowrap px-4 py-2.5">Version</th>
                                <th class="whitespace-nowrap px-4 py-2.5">Status</th>
                                <th class="whitespace-nowrap px-4 py-2.5 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-xs">
                            @forelse ($topics as $topic)
                                @php
                                    $version = $topic->latestVersion;
                                    $label = $topic->workflowStatusLabel();
                                    $action = match (true) {
                                        $topic->isAwaitingNoticeToProceed() => 'Issue NTP',
                                        $topic->status === 'ready_for_signature' => 'Sign',
                                        in_array($topic->status, ['approved', 'rejected']) => 'Open',
                                        default => 'Review',
                                    };
                                    $badgeTone = match ($topic->status) {
                                        'approved' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
                                        'rejected' => 'border-slate-200 bg-slate-100 text-slate-600',
                                        'revision_requested' => 'border-rose-200 bg-rose-50 text-rose-800',
                                        'resubmitted' => 'border-sky-200 bg-sky-50 text-sky-800',
                                        'ready_for_signature' => 'border-violet-200 bg-violet-50 text-violet-800',
                                        default => 'border-amber-200 bg-amber-50 text-amber-800',
                                    };
                                @endphp
                                <tr wire:key="proposal-row-{{ $topic->id }}" class="transition hover:bg-slate-50/80">
                                    <td class="overflow-hidden px-4 py-3">
                                        <a href="{{ route('topics.show', $topic) }}#proposal-review" class="block truncate font-semibold text-slate-950 hover:text-[#800000]">{{ $topic->title }}</a>
                                        <span class="mt-0.5 block truncate text-[11px] text-slate-500">{{ $topic->user?->name }} · {{ $version?->created_at?->format('M d, Y') ?? 'No submission date' }}</span>
                                    </td>
                                    <td class="overflow-hidden px-4 py-3"><span class="block truncate whitespace-nowrap text-slate-600">{{ $topic->researchCall?->title ?? 'Independent submission' }}</span></td>
                                    <td class="overflow-hidden px-4 py-3"><span class="inline-flex max-w-full truncate whitespace-nowrap rounded border border-slate-200 bg-slate-50 px-1.5 py-1 font-mono text-[10px] text-slate-700">{{ $version ? 'v'.$version->version_number.' · '.$version->files_count.' files' : '—' }}</span></td>
                                    <td class="overflow-hidden px-4 py-3"><span class="inline-flex max-w-full truncate whitespace-nowrap rounded border px-2 py-1 text-[10px] font-bold {{ $badgeTone }}">{{ $label }}</span></td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right"><a href="{{ route('topics.show', $topic) }}#proposal-review" class="inline-flex whitespace-nowrap rounded-md border border-slate-200 bg-white px-2.5 py-1.5 font-semibold text-slate-700 hover:border-[#800000] hover:text-[#800000]">{{ $action }}</a></td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-4 py-10 text-center text-sm text-slate-500">No proposals found. Try changing the search or filters.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($topics->hasPages())
                    <div class="border-t border-slate-200 bg-slate-50/60 px-4 py-3">{{ $topics->links() }}</div>
                @endif
            </section>

            <section id="active-projects" class="order-1 scroll-mt-64 rounded-lg border border-slate-200 bg-white p-4 shadow-sm xl:col-span-8 2xl:col-span-9" aria-labelledby="project-completion-heading">
                <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 pb-3">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-[#800000]">Implementation tracking</p>
                        <h3 id="project-completion-heading" class="mt-0.5 text-sm font-bold text-slate-950">Project completion</h3>
                        <p class="mt-0.5 text-[11px] text-slate-500">Latest monitoring-tool progress for active approved projects.</p>
                    </div>
                    <a href="{{ route('research_head.projects.index') }}" class="rounded-md border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-[#800000] hover:border-[#800000]">Open monitoring &rarr;</a>
                </div>
                <div class="grid gap-3 pt-3 md:grid-cols-2">
                    @forelse ($activeProjects as $project)
                        @php
                            $completion = min(100, max(0, (int) ($project->latestProgressReport?->progress_percentage ?? 0)));
                            $monitoringStatus = $project->monitoringStatusForProgress($project->latestProgressReport?->progress_percentage);
                            $monitoringStatusLabel = $project->monitoringStatusLabelForProgress($project->latestProgressReport?->progress_percentage);
                            $statusTone = match ($monitoringStatus) {
                                'completion_pending' => 'border-violet-200 bg-violet-50 text-violet-800',
                                'delayed' => 'border-rose-200 bg-rose-50 text-rose-800',
                                default => 'border-slate-200 bg-slate-100 text-slate-700',
                            };
                        @endphp
                        <a href="{{ route('topics.show', $project) }}#project-monitoring" class="rounded-md border border-slate-200 p-3.5 transition hover:border-rose-300 hover:shadow-sm">
                            <span class="flex items-start justify-between gap-3">
                                <span class="min-w-0"><span class="block truncate text-xs font-bold text-slate-950">{{ $project->title }}</span><span class="mt-0.5 block truncate text-[11px] text-slate-500">Lead: {{ $project->user?->name }}</span></span>
                                <span class="shrink-0 rounded border px-2 py-1 text-[10px] font-bold {{ $statusTone }}">{{ $monitoringStatusLabel }}</span>
                            </span>
                            <span class="mt-3 flex items-center justify-between text-[11px]"><span class="text-slate-500">{{ $project->latestProgressReport ? 'Completion milestone' : 'No monitoring tool submitted' }}</span><strong class="text-slate-900">{{ $completion }}%</strong></span>
                            <span class="mt-1.5 block h-2 overflow-hidden rounded-full border border-slate-200 bg-slate-100" role="progressbar" aria-label="{{ $project->title }} completion" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $completion }}"><span class="block h-full rounded-full {{ $monitoringStatus === 'delayed' ? 'bg-amber-500' : 'bg-[#800000]' }}" style="width: {{ $completion }}%"></span></span>
                            <span class="mt-3 block border-t border-slate-100 pt-2 text-[11px] text-slate-500">Approved budget: <strong class="font-mono text-slate-700">₱{{ number_format((float) $project->estimated_budget, 2) }}</strong></span>
                        </a>
                    @empty
                        <p class="rounded-md border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-center text-xs text-slate-500 md:col-span-2">No active projects are currently being monitored.</p>
                    @endforelse
                </div>
            </section>

            <div class="order-2 space-y-4 xl:col-span-8 2xl:col-span-9">
                <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm" aria-label="Submissions by week">
                    <div class="border-b border-slate-100 pb-2">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-900">Submissions by week</h3>
                        <p class="mt-0.5 text-[11px] text-slate-500">New submissions received each week · last 8 weeks</p>
                    </div>
                    <div class="mt-3 flex h-32 items-end gap-2" role="img" aria-label="Submissions by week: {{ $analytics['weeks']->map(fn ($week) => 'week of '.$week['label'].': '.$week['count'])->join('; ') }}">
                        @foreach ($analytics['weeks'] as $week)
                            <div class="flex h-full min-w-0 flex-1 flex-col justify-end text-center" title="Week of {{ $week['label'] }} ({{ $week['start'] }} to {{ $week['end'] }}): {{ $week['count'] }} submissions">
                                <span class="mb-1 text-[9px] font-bold tabular-nums text-slate-600">{{ $week['count'] }}</span>
                                <div class="mx-auto w-full max-w-7 rounded-t bg-rose-700" style="height: {{ max(5, ($week['count'] / $analytics['chartMax']) * 68) }}px"></div>
                                <span class="mt-1.5 text-[9px] font-medium"><span class="block leading-none text-slate-400">Week of</span><span class="mt-1 block leading-none text-slate-500">{{ $week['label'] }}</span></span>
                            </div>
                        @endforeach
                    </div>
                    <p class="mt-3 border-t border-slate-100 pt-2 text-[11px] text-slate-500"><strong class="text-[#800000]">{{ $analytics['recentTotal'] }} {{ str('submission')->plural($analytics['recentTotal']) }}</strong> in the last 4 weeks, compared with <strong class="text-slate-700">{{ $analytics['previousTotal'] }}</strong> during the previous 4 weeks.</p>
                </section>

                <div class="grid gap-4 lg:grid-cols-2">
                    <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm" aria-labelledby="proposal-status-overview-heading">
                        <div class="flex items-start justify-between gap-3 border-b border-slate-100 pb-2">
                            <div>
                                <h3 id="proposal-status-overview-heading" class="text-xs font-bold uppercase tracking-wider text-slate-900">Proposal status overview</h3>
                                <p class="mt-0.5 text-[11px] text-slate-500">Current proposals grouped by status.</p>
                            </div>
                            <span class="shrink-0 rounded bg-slate-100 px-2 py-1 text-[10px] font-bold tabular-nums text-slate-600">{{ $analytics['pipelineTotal'] }} total</span>
                        </div>
                        <div class="mt-3 space-y-3">
                            @foreach ($analytics['pipelineFunnel'] as $stage)
                                @php
                                    $stageShare = $analytics['pipelineTotal'] > 0 ? (int) round(($stage['count'] / $analytics['pipelineTotal']) * 100) : 0;
                                    $stageBarTone = match ($stage['key']) {
                                        'approved' => 'bg-emerald-500',
                                        'revision_requested' => 'bg-sky-500',
                                        'ready_for_signature' => 'bg-violet-500',
                                        'rejected' => 'bg-slate-400',
                                        default => 'bg-[#800000]',
                                    };
                                @endphp
                                <div>
                                    <div class="flex items-center justify-between gap-3 text-[11px]"><span class="font-semibold text-slate-700">{{ $stage['label'] }}</span><strong class="tabular-nums text-slate-900">{{ $stage['count'] }} {{ str('proposal')->plural($stage['count']) }}</strong></div>
                                    <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-slate-100" role="progressbar" aria-label="{{ $stage['label'] }} share" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $stageShare }}"><span class="block h-full rounded-full {{ $stageBarTone }}" style="width: {{ $stageShare }}%"></span></div>
                                    <p class="mt-1 text-[10px] text-slate-400">{{ $stageShare }}% of current proposals</p>
                                </div>
                            @endforeach
                        </div>
                    </section>

                    @php
                        $budget = $analytics['budgetUtilization'];
                    @endphp
                    <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm" aria-labelledby="budget-utilization-heading">
                        <div class="flex items-start justify-between gap-3 border-b border-slate-100 pb-2">
                            <div>
                                <h3 id="budget-utilization-heading" class="text-xs font-bold uppercase tracking-wider text-slate-900">Budget utilization</h3>
                                <p class="mt-0.5 text-[11px] text-slate-500">Latest reported spending for each approved implementation project.</p>
                            </div>
                            <span class="shrink-0 rounded border border-slate-200 bg-slate-50 px-2 py-1 text-[10px] font-semibold text-slate-600">{{ $budget['project_count'] }} {{ $budget['project_count'] === 1 ? 'project' : 'projects' }}</span>
                        </div>
                        @if ($budget['project_count'] > 0)
                            <div class="mt-3 max-h-80 space-y-3 overflow-y-auto pr-1">
                                @foreach ($budget['projects'] as $project)
                                    @php
                                        $projectPercentage = (float) $project['percentage'];
                                        $projectBarPercentage = min(100, max(0, $projectPercentage));
                                        $projectBarTone = $projectPercentage > 100 ? 'bg-rose-600' : 'bg-[#800000]';
                                        $projectReportLabel = $project['has_utilization'] ? 'Latest report' : ($project['has_report'] ? 'No utilization reported' : 'No report yet');
                                    @endphp
                                    <div class="rounded-md border border-slate-100 bg-slate-50 p-3">
                                        <div class="flex items-start justify-between gap-3">
                                            <span class="min-w-0 truncate text-[11px] font-semibold text-slate-700" title="{{ $project['title'] }}">{{ $project['title'] }}</span>
                                            <strong class="shrink-0 tabular-nums text-slate-900">{{ number_format($projectPercentage, 1) }}%</strong>
                                        </div>
                                        <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-slate-200" role="progressbar" aria-label="{{ $project['title'] }} budget utilization" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $projectBarPercentage }}"><span class="block h-full rounded-full {{ $projectBarTone }}" style="width: {{ $projectBarPercentage }}%"></span></div>
                                        <div class="mt-1.5 flex items-center justify-between gap-3 text-[10px] text-slate-500"><span>₱{{ number_format((float) $project['reported_utilized'], 2) }} / ₱{{ number_format((float) $project['approved_budget'], 2) }}</span><span>{{ $projectReportLabel }}</span></div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="mt-3 rounded-md border border-dashed border-slate-300 bg-slate-50 px-3 py-4 text-center text-xs text-slate-500">No approved implementation budgets are available yet.</p>
                        @endif
                    </section>
                </div>
            </div>
        </div>

        <aside class="order-1 space-y-4 xl:col-span-4 xl:row-span-2 2xl:col-span-3" aria-label="Research operations side panel">
            <section id="needs-attention" class="scroll-mt-64 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm" aria-labelledby="needs-attention-heading">
                <div class="border-b border-slate-200 bg-slate-50/80 px-4 py-3">
                    <div class="flex items-center justify-between gap-3"><h3 id="needs-attention-heading" class="text-xs font-bold uppercase tracking-wider text-slate-900">Needs attention</h3><span class="rounded border border-rose-200 bg-rose-50 px-2 py-1 text-[10px] font-bold text-rose-800">{{ count($analytics['attention']) }} waiting</span></div>
                    <p class="mt-1 text-[11px] text-slate-500">Oldest waiting proposals appear first.</p>
                </div>
                <div class="divide-y divide-slate-100">
                    @forelse ($analytics['attention'] as $item)
                        <a href="{{ route('topics.show', $item['topic']) }}#proposal-review" class="flex items-start justify-between gap-3 px-4 py-3 transition hover:bg-slate-50">
                            <span class="min-w-0"><span class="block truncate text-xs font-semibold text-slate-900">{{ $item['topic']->title }}</span><span class="mt-0.5 block truncate text-[11px] text-slate-500">{{ $stageLabels[$item['topic']->status] }} · {{ $item['topic']->user?->name }}</span>@if ($item['past_revision_deadline'])<span class="mt-1 inline-flex rounded bg-rose-50 px-1.5 py-0.5 text-[9px] font-bold text-rose-800">Past revision deadline</span>@endif</span>
                            <span class="shrink-0 rounded border border-slate-200 bg-slate-50 px-2 py-1 font-mono text-[10px] font-bold text-slate-700">{{ $item['days'] !== null ? $item['days'].'d' : '—' }}</span>
                        </a>
                    @empty
                        <p class="px-4 py-6 text-center text-xs text-slate-500">No proposals waiting for action.</p>
                    @endforelse
                </div>
            </section>

            <section id="dashboard-deadlines" class="scroll-mt-28 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm" aria-labelledby="deadlines-heading">
                <div class="border-b border-slate-200 bg-slate-50/80 px-4 py-3"><h3 id="deadlines-heading" class="text-xs font-bold uppercase tracking-wider text-slate-900">Official deadlines</h3><p class="mt-1 text-[11px] text-slate-500">Next 14 days</p></div>
                <div class="divide-y divide-slate-100">
                    @forelse ($deadlines->take(4) as $deadline)
                        @php $when = \Illuminate\Support\Carbon::parse($deadline['at']); @endphp
                        <a href="{{ $deadline['url'] }}" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50"><span class="flex w-9 shrink-0 flex-col items-center rounded bg-[#800000] px-1 py-1 text-white"><span class="text-[8px] font-bold uppercase text-rose-200">{{ $when->format('M') }}</span><span class="text-sm font-bold leading-4">{{ $when->format('d') }}</span></span><span class="min-w-0"><span class="block truncate text-xs font-semibold text-slate-900">{{ $deadline['title'] }}</span><span class="block truncate text-[11px] text-slate-500">{{ $deadline['context'] }} · {{ $when->diffForHumans(short: true) }}</span></span></a>
                    @empty
                        <p class="px-4 py-5 text-center text-xs text-slate-500">No official deadlines in the next 14 days.</p>
                    @endforelse
                </div>
            </section>
        </aside>
    </div>
</div>
