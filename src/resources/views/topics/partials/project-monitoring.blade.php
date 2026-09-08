<section id="project-monitoring" class="space-y-5">
    @php
        $projectStatus = $topic->project_status ?: 'ongoing';
        $schedule = app(\App\Services\MonitoringQuarterService::class);
        $window = $schedule->reportingWindow($topic);
        $terminalDate = $schedule->terminalOpensAt($topic);
        $terminalOpen = $schedule->canSubmitTerminal($topic);
        $openPeriod = $monitoringQuarterRows->first(fn ($row) => $row['reporting_date'] !== null);
        $nextPeriod = $monitoringQuarterRows->first(fn ($row) => $row['reporting_date'] === null);
        $canReport = ! Auth::user()->isUsingWorkspace('research_head') && $topic->isMonitoringAvailable() && $topic->isAccessibleTo(Auth::user());
    @endphp
    <header class="rounded-xl border border-gray-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h3 class="text-lg font-bold text-gray-950 dark:text-white">Project monitoring</h3>
                <p class="mt-1 text-sm text-gray-600 dark:text-slate-300">Report every three months. Submit the terminal report after the project ends.</p>
            </div>
            <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-700 dark:bg-slate-800 dark:text-slate-200">{{ ucfirst($projectStatus) }}</span>
        </div>
        <dl class="mt-5 grid gap-4 border-t border-gray-100 pt-4 dark:border-slate-800 sm:grid-cols-3">
            <div><dt class="text-xs text-gray-500">Monitoring starts</dt><dd class="mt-1 text-sm font-semibold dark:text-white">{{ $window['start']->format('M j, Y') }}</dd></div>
            <div><dt class="text-xs text-gray-500">Project ends</dt><dd class="mt-1 text-sm font-semibold dark:text-white">{{ $window['end']->format('M j, Y') }}</dd></div>
            <div><dt class="text-xs text-gray-500">Next period opens</dt><dd class="mt-1 text-sm font-semibold dark:text-white">{{ $nextPeriod ? $nextPeriod['opens_at']->format('M j, Y') : 'All periods have ended' }}</dd></div>
        </dl>
        @if ($topic->hasIssuedNoticeToProceed())
            <a href="{{ route('topics.show', $topic) }}#notice-to-proceed" class="mt-4 inline-block text-xs font-semibold text-red-700 dark:text-red-300">View Notice to Proceed and signed papers</a>
        @endif
        @if ($topic->isCompletedProject())
            <p class="mt-4 text-sm text-gray-600 dark:text-slate-300">Project completed. Reports remain available as read-only records.</p>
        @endif
    </header>

    <x-monitoring-quarter-overview :quarter-rows="$monitoringQuarterRows" :topic="$topic" />

    <section class="divide-y divide-gray-200 rounded-xl border border-gray-200 bg-white dark:divide-slate-700 dark:border-slate-700 dark:bg-slate-900" aria-label="Narrative reports">
        @foreach (['progress' => 'Progress report', 'terminal' => 'Terminal report'] as $reportType => $reportLabel)
            @php
                $available = $reportType === 'terminal' ? $terminalOpen : $openPeriod !== null;
            @endphp
            <article class="flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h4 class="text-sm font-bold text-gray-950 dark:text-white">{{ $reportLabel }}</h4>
                    <p class="mt-1 text-sm text-gray-500 dark:text-slate-400">{{ $reportType === 'terminal' ? 'Summarize the full project, final results, and accomplishments.' : 'Add narrative accomplishments and photo documentation for an ended reporting period.' }}</p>
                    @if (! $available)
                        <p class="mt-2 text-xs font-medium text-gray-600 dark:text-slate-300">Opens {{ ($reportType === 'terminal' ? $terminalDate : ($nextPeriod['opens_at'] ?? $terminalDate))->format('M j, Y') }}</p>
                    @endif
                </div>
                @if ($canReport && $available)
                    <a href="{{ route('project-narrative-reports.create', ['topic' => $topic, 'report_type' => $reportType]) }}" class="inline-flex shrink-0 justify-center rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50 dark:border-slate-600 dark:text-red-300">Open {{ strtolower($reportLabel) }}</a>
                @elseif ($canReport)
                    <button type="button" disabled class="shrink-0 rounded-lg bg-gray-100 px-4 py-2 text-sm text-gray-400 dark:bg-slate-800">Not open yet</button>
                @endif
            </article>
        @endforeach
    </section>

    @if (Auth::user()->isUsingWorkspace('research_head') && ! $topic->isCompletedProject())
        <details class="rounded-xl border border-gray-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900">
            <summary class="cursor-pointer text-sm font-semibold dark:text-white">Manage project status</summary>
            <p class="mt-3 text-sm text-gray-500">Mark complete after the terminal report has been reviewed.</p>
            <form method="POST" action="{{ route('research_head.projects.update-status', $topic) }}" class="mt-3 flex flex-wrap items-end gap-3">@csrf @method('PATCH')
                <label class="text-sm dark:text-white">Status<select name="project_status" class="mt-1 block rounded-lg border-gray-300 text-sm dark:bg-slate-800">@foreach (['ongoing', 'delayed', 'completed'] as $value)<option value="{{ $value }}" @selected($projectStatus === $value)>{{ ucfirst($value) }}</option>@endforeach</select></label>
                <button class="rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white">Save status</button>
            </form>
        </details>
    @endif
    <div class="space-y-5">

        <div class="space-y-4">
            <div class="flex items-center justify-between gap-3">
                <div><p class="text-sm font-black text-gray-900">Monitoring tool versions</p><p class="mt-1 text-xs text-gray-400">Submitted work plan and budget utilization records.</p></div>
            </div>
            @forelse ($topic->progressReports as $report)
                @php
                    $isCurrentVersion = $report->nextVersion === null;
                    $reviewStatusClass = match ($report->review_status) {
                        'reviewed' => 'bg-green-50 text-green-700',
                        'revision_requested' => 'bg-red-50 text-red-700',
                        default => 'bg-amber-50 text-amber-700',
                    };
                @endphp
                <article id="monitoring-tool-{{ $report->id }}" class="scroll-mt-32 rounded-xl border border-gray-200 p-4">
                    <div class="flex flex-wrap justify-between gap-3">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-sm font-black text-gray-900">{{ $report->quarter_label }} Monitoring Tool · {{ $report->version_label }}</p>
                                <span class="rounded-full px-2 py-1 text-[10px] font-black uppercase {{ $isCurrentVersion ? 'bg-red-50 text-red-700' : 'bg-gray-100 text-gray-500' }}">{{ $isCurrentVersion ? 'Current submission' : 'Historical version' }}</span>
                            </div>
                            <p class="mt-1 text-[11px] text-gray-400">{{ $report->reporting_period_label }} · Submitted {{ $report->submitted_at?->format('M d, Y g:i A') ?? $report->created_at->format('M d, Y g:i A') }} by {{ $report->submitter->name }}</p>
                        </div>
                        <div class="flex flex-wrap items-start gap-2">
                            <span class="rounded-full bg-gray-100 px-2 py-1 text-[10px] font-black uppercase text-gray-600">{{ $report->progress_percentage }}% complete</span>
                            <span class="rounded-full px-2 py-1 text-[10px] font-black uppercase {{ $reviewStatusClass }}">{{ str_replace('_', ' ', $report->review_status) }}</span>
                        </div>
                    </div>
                    <div class="mt-3 h-2 overflow-hidden rounded-full bg-gray-100"><div class="h-full rounded-full bg-red-600" style="width: {{ $report->progress_percentage }}%"></div></div>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2"><div><p class="text-[10px] font-black uppercase text-gray-400">Accomplishments</p><p class="mt-1 whitespace-pre-line text-xs leading-5 text-gray-600">{{ $report->accomplishments }}</p></div><div><p class="text-[10px] font-black uppercase text-gray-400">Issues or delays</p><p class="mt-1 whitespace-pre-line text-xs leading-5 text-gray-600">{{ $report->issues ?: 'None reported.' }}</p></div></div>
                    <div class="mt-3 flex flex-wrap gap-4">
                        @if (is_array($report->work_plan) && is_array($report->budget_utilization))
                            <a href="{{ route('project-progress.monitoring-tool', $report) }}" class="inline-flex text-xs font-bold text-red-700">Download official monitoring tool</a>
                        @endif
                        @if ($report->attachment_path)<a href="{{ route('project-progress.download', $report) }}" class="inline-flex text-xs font-bold text-red-700">Download supporting attachment</a>@endif
                        @if (! Auth::user()->isUsingWorkspace('research_head') && $isCurrentVersion && $report->review_status === 'revision_requested')
                            <a href="{{ route('project-progress.create', ['topic' => $topic, 'revise_monitoring_report' => $report->id]) }}" class="inline-flex text-xs font-bold text-red-700">Revise this {{ $report->quarter_label }} submission</a>
                        @endif
                    </div>
                    @if ($report->research_head_remarks)<div class="mt-3 rounded-xl bg-gray-50 p-3"><p class="text-[10px] font-black uppercase text-gray-400">Research Head remarks @if ($report->reviewer) · {{ $report->reviewer->name }} @endif</p><p class="mt-1 text-xs text-gray-600">{{ $report->research_head_remarks }}</p></div>@endif
                    @if (Auth::user()->isUsingWorkspace('research_head') && ! $topic->isCompletedProject() && $isCurrentVersion)
                        <form method="POST" action="{{ route('research_head.progress-reports.review', $report) }}" class="mt-4 grid gap-2 sm:grid-cols-[180px_1fr_auto]">@csrf @method('PATCH')<select name="review_status" class="rounded-xl border-gray-200 text-xs font-bold"><option value="reviewed" @selected($report->review_status === 'reviewed')>Mark reviewed</option><option value="revision_requested" @selected($report->review_status === 'revision_requested')>Request revision</option></select><input name="research_head_remarks" value="{{ $report->research_head_remarks }}" maxlength="5000" class="rounded-xl border-gray-200 text-xs" placeholder="Remarks"><button class="rounded-xl bg-red-600 px-4 py-2 text-xs font-bold text-white">Save review</button></form>
                    @endif
                </article>
            @empty
                <div class="rounded-xl bg-gray-50 py-8 text-center"><p class="text-sm font-bold text-gray-700">No monitoring tools yet</p><p class="mt-1 text-xs text-gray-400">The first faculty submission will appear here.</p></div>
            @endforelse
        </div>

        <div class="space-y-4 border-t border-gray-100 pt-5">
            <div class="flex items-center justify-between gap-3">
                <div><p class="text-sm font-black text-gray-900">Progress and terminal report history</p><p class="mt-1 text-xs text-gray-400">Submitted narratives, final results, and photo documentation.</p></div>
            </div>
            @forelse ($topic->narrativeReports as $report)
                <article class="rounded-xl border border-gray-200 p-4">
                    <div class="flex flex-wrap justify-between gap-3">
                        <div>
                            <p class="text-sm font-black text-gray-900">{{ $report->report_label }}@if ($report->report_type === 'terminal' && isset($report->terminal_data['version_number'])) · Version {{ $report->terminal_data['version_number'] }}@endif</p>
                            <p class="mt-1 text-[11px] text-gray-400">{{ $report->submission_date->format('M d, Y') }} · {{ $report->submitter->name }}</p>
                        </div>
                        <span class="rounded-full bg-gray-100 px-2 py-1 text-[10px] font-black uppercase text-gray-600">{{ str_replace('_', ' ', $report->review_status) }}</span>
                    </div>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div><p class="text-[10px] font-black uppercase text-gray-400">Monitoring-period accomplishment</p><p class="mt-1 whitespace-pre-line text-xs leading-5 text-gray-600">{{ $report->accomplishment_summary }}</p></div>
                        <div><p class="text-[10px] font-black uppercase text-gray-400">Funding agency</p><p class="mt-1 text-xs leading-5 text-gray-600">{{ $report->funding_agency }}</p></div>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-4">
                        <a href="{{ route('project-narrative-reports.download', $report) }}" class="inline-flex text-xs font-bold text-red-700">Download {{ strtolower($report->report_label) }}</a>
                        @foreach ($report->photos ?? [] as $photoIndex => $photo)
                            <a href="{{ route('project-narrative-reports.photos.download', [$report, $photoIndex]) }}" class="inline-flex text-xs font-bold text-red-700">Photo {{ $photoIndex + 1 }}: {{ $photo['caption'] }}</a>
                        @endforeach
                    </div>
                    @if ($report->research_head_remarks)<div class="mt-3 rounded-xl bg-gray-50 p-3"><p class="text-[10px] font-black uppercase text-gray-400">Research Head remarks</p><p class="mt-1 text-xs text-gray-600">{{ $report->research_head_remarks }}</p></div>@endif
                    @if (Auth::user()->isUsingWorkspace('research_head') && ! $topic->isCompletedProject())
                        <form method="POST" action="{{ route('research_head.narrative-progress-reports.review', $report) }}" class="mt-4 grid gap-2 sm:grid-cols-[180px_1fr_auto]">@csrf @method('PATCH')<select name="review_status" class="rounded-xl border-gray-200 text-xs font-bold"><option value="reviewed" @selected($report->review_status === 'reviewed')>Mark reviewed</option><option value="revision_requested" @selected($report->review_status === 'revision_requested')>Request revision</option></select><input name="research_head_remarks" value="{{ $report->research_head_remarks }}" maxlength="5000" class="rounded-xl border-gray-200 text-xs" placeholder="Remarks"><button class="rounded-xl bg-red-600 px-4 py-2 text-xs font-bold text-white">Save review</button></form>
                    @endif
                </article>
            @empty
                <div class="rounded-xl bg-gray-50 py-8 text-center"><p class="text-sm font-bold text-gray-700">No progress reports yet</p><p class="mt-1 text-xs text-gray-400">The first narrative progress report will appear here.</p></div>
            @endforelse
        </div>
    </div>
</section>
