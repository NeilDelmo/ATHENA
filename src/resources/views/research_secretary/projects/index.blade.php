<x-app-layout>
    <x-slot name="header">
        <x-page-header variant="hero"
            eyebrow="Administrative workspace"
            title="Research Office Secretary Dashboard"
            subtitle="Review Monitoring Tools and Progress Reports, handle corrections, and forward checked reports to the Research Head."
        />
    </x-slot>

    @php
        $preparedReports = $projects->sum(fn ($project) => $project->preparedProgressReports->count());
        $completedBudgets = $projects->sum(fn ($project) => $project->preparedProgressReports->filter->hasPreparedBudget()->count());
        $budgetTasks = $projects->flatMap(fn ($project) => $project->preparedProgressReports
            ->reject->hasPreparedBudget()
            ->map(fn ($report) => ['project' => $project, 'report' => $report]))
            ->sortBy(fn ($task) => $task['report']->prepared_at ?? $task['report']->created_at)
            ->values();
    @endphp

    <div class="space-y-5" data-dashboard-layout="secretary-overview">
        <section aria-label="Secretary priorities">
            <dl class="grid grid-cols-2 gap-3 xl:grid-cols-4">
                <x-dashboard-stat label="Awaiting release" :value="$signingProjects->total()" description="Prepare notices and signed documents" href="#notice-release-queue" :priority="$signingProjects->total() > 0" icon="M4 4h10l6 6v10H4V4Zm10 0v6h6M8 14h8M8 17h5" />
                <x-dashboard-stat label="Awaiting budget" :value="$budgetTasks->count()" description="Monitoring Tools needing financial entry" href="#budget-worklist" :priority="$budgetTasks->isNotEmpty()" icon="M12 6v6l4 2m5-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                <x-dashboard-stat label="Reports to review" :value="$reportReviewTasks->count()" description="Check reports and resolve returned concerns" href="#implementation-report-reviews" :priority="$reportReviewTasks->isNotEmpty()" icon="m5 12 4 4L19 6" />
                <x-dashboard-stat label="Assigned portfolio" :value="$projects->count()" description="Research projects assigned to you" href="#assigned-projects" icon="M3 7h6l2 2h10v11H3V7Zm0 0V4h6l2 3h10v2" />
            </dl>
        </section>

        <livewire:research-target-board />

        <section id="implementation-report-reviews" class="dashboard-panel scroll-mt-32" aria-labelledby="implementation-report-reviews-heading" data-secretary-report-queue>
            <div class="dashboard-panel-heading">
                <div><h2 id="implementation-report-reviews-heading" class="text-lg font-bold text-slate-950 dark:text-white">Implementation reports to review</h2><p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Check submissions from your college and resolve concerns returned by the Research Head.</p></div>
                <span class="text-sm font-semibold tabular-nums text-slate-600 dark:text-slate-300">{{ $reportReviewTasks->count() }} waiting</span>
            </div>
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($reportReviewTasks as $report)
                    @php
                        $isMonitoringTool = $report instanceof \App\Models\ProjectProgressReport;
                        $reportUrl = route('topics.show', $report->topic).'#'.($isMonitoringTool ? 'monitoring-tool-' : 'narrative-report-').$report->id;
                        $previewUrl = route($isMonitoringTool ? 'project-progress.monitoring-tool.view' : 'project-narrative-reports.view', $report);
                    @endphp
                    <article class="flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between" data-secretary-report-kind="{{ $isMonitoringTool ? 'monitoring' : 'progress' }}">
                        <div class="min-w-0">
                            <h3 class="break-words font-semibold text-slate-950 dark:text-white">{{ $report->topic->title }}</h3>
                            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ $isMonitoringTool ? 'Monitoring Tool' : 'Progress Report' }} · {{ $report->reporting_period_label ?: $report->reporting_date?->format('M j, Y') }} · Version {{ $report->version_number }} · {{ $report->submitter->name }}</p>
                            <p class="mt-1 text-sm {{ $report->research_head_remarks ? 'font-semibold text-brand dark:text-rose-300' : 'text-slate-500 dark:text-slate-400' }}">{{ $report->research_head_remarks ? 'Returned by Research Head · resolve concerns before forwarding' : 'Awaiting Research Office Secretary review' }}</p>
                        </div>
                        <div class="flex shrink-0 flex-wrap gap-2">
                            <a href="{{ $previewUrl }}" target="_blank" rel="noopener" class="monitoring-secondary-action" aria-label="Preview submitted {{ $isMonitoringTool ? 'Monitoring Tool' : 'Progress Report' }} PDF in a new tab">Preview PDF</a>
                            <a href="{{ $reportUrl }}" class="dashboard-action">Review report</a>
                        </div>
                    </article>
                @empty
                    <div class="px-5 py-8"><p class="font-semibold text-slate-900 dark:text-white">No reports awaiting your review.</p><p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Submitted Monitoring Tools and Progress Reports appear here before they are forwarded to the Research Head.</p></div>
                @endforelse
            </div>
        </section>

        <div class="grid items-start gap-5 xl:grid-cols-2" data-dashboard-columns>
        <x-notice-to-proceed-queue :projects="$signingProjects" />
        <section id="budget-worklist" class="dashboard-panel scroll-mt-32" aria-labelledby="budget-worklist-heading">
            <div class="dashboard-panel-heading">
                <div><h2 id="budget-worklist-heading" class="text-lg font-bold text-slate-950 dark:text-white">Budgets to complete</h2><p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Tasks from your separate project team assignments. Oldest prepared Monitoring Tools first.</p></div>
                <span class="text-sm font-semibold tabular-nums text-slate-600 dark:text-slate-300">{{ $budgetTasks->count() }} waiting</span>
            </div>
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($budgetTasks as $task)
                    @php
                        $report = $task['report'];
                        $waitingSince = $report->prepared_at ?? $report->created_at;
                        $waitingDays = $waitingSince ? (int) max(0, $waitingSince->diffInDays(now(), false)) : null;
                    @endphp
                    <article class="flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <h3 class="break-words font-semibold text-slate-950 dark:text-white">{{ $task['project']->title }}</h3>
                            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ $report->quarter_label }} · {{ $report->version_label }} · {{ $report->submitter->name }}</p>
                            <p class="mt-2 text-sm font-medium tabular-nums text-brand dark:text-rose-300">{{ $waitingDays !== null ? $waitingDays.' '.str('day')->plural($waitingDays).' waiting' : 'Preparation date unavailable' }}</p>
                        </div>
                        <a href="{{ route('project-budget.edit', [$task['project'], $report]) }}" class="dashboard-action shrink-0">Complete budget</a>
                    </article>
                @empty
                    <div class="px-5 py-8"><p class="font-semibold text-slate-900 dark:text-white">No budgets waiting for entry.</p><p class="mt-1 text-sm text-slate-600 dark:text-slate-400">A budget becomes editable after the researcher prepares the quarterly Monitoring Tool.</p></div>
                @endforelse
            </div>
        </section>
        </div>

        <details id="assigned-projects" class="dashboard-panel group scroll-mt-32">
            <summary class="dashboard-panel-heading min-h-11 cursor-pointer focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">
                <span class="inline-flex items-center gap-2 text-base font-semibold text-slate-950 dark:text-white">
                    <svg class="h-5 w-5 shrink-0 group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6" stroke-linecap="round" stroke-linejoin="round" /></svg>
                    Assigned projects
                </span>
                <span class="text-sm text-slate-600 dark:text-slate-400">{{ $projects->count() }} {{ str('project')->plural($projects->count()) }} · {{ $completedBudgets }} of {{ $preparedReports }} budgets confirmed</span>
            </summary>

            <div class="divide-y divide-gray-100 dark:divide-slate-800">
                @forelse ($projects as $project)
                    <article class="grid gap-5 p-5 sm:p-6 lg:grid-cols-[minmax(0,1fr)_minmax(18rem,0.8fr)]">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-md bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ str($project->project_status ?? 'ongoing')->headline() }}</span>
                                <span class="text-sm text-slate-600 dark:text-slate-400">Approved budget · {{ $project->estimated_budget !== null ? '₱'.number_format((float) $project->estimated_budget, 2) : 'Not recorded' }}</span>
                            </div>
                            <h3 class="mt-3 break-words text-base font-semibold leading-6 text-gray-950 dark:text-white">{{ $project->title }}</h3>
                            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Project leader · {{ $project->user->name }}</p>
                        </div>

                        <div class="space-y-2">
                            @forelse ($project->preparedProgressReports as $report)
                                <a href="{{ route('project-budget.edit', [$project, $report]) }}" class="flex min-h-11 flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 p-3 hover:border-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand dark:border-slate-700">
                                    <span class="min-w-0"><span class="block text-sm font-semibold text-gray-950 dark:text-white">{{ $report->quarter_label }} · {{ $report->version_label }}</span><span class="mt-1 block text-sm text-slate-600 dark:text-slate-400">Prepared by {{ $report->submitter->name }}</span></span>
                                    <span class="text-sm font-semibold text-brand dark:text-rose-300">{{ $report->hasPreparedBudget() ? 'Review budget' : 'Complete budget' }}</span>
                                </a>
                            @empty
                                <p class="text-sm leading-6 text-slate-600 dark:text-slate-400">No prepared quarterly report is waiting for financial entry.</p>
                            @endforelse
                        </div>
                    </article>
                @empty
                    <div class="px-6 py-16 text-center">
                        <p class="text-base font-semibold text-gray-800 dark:text-white">No projects assigned</p>
                        <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Projects appear here when your project group assigns you as secretary.</p>
                    </div>
                @endforelse
            </div>
        </details>
    </div>
</x-app-layout>
