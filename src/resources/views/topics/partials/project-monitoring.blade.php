<section id="project-monitoring" class="space-y-5">
    @php
        $storedProjectStatus = $topic->project_status ?: 'ongoing';
        $latestProgressPercentage = $topic->progressReports->first()?->progress_percentage;
        $projectStatus = $topic->monitoringStatusForProgress($latestProgressPercentage);
        $projectStatusLabel = $topic->monitoringStatusLabelForProgress($latestProgressPercentage);
        $schedule = app(\App\Services\MonitoringQuarterService::class);
        $window = $schedule->reportingWindow($topic);
        $terminalDate = $schedule->terminalOpensAt($topic);
        $terminalOpen = $schedule->canSubmitTerminal($topic);
        $openPeriod = $monitoringQuarterRows->first(fn ($row) => $row['reporting_date'] !== null);
        $nextPeriod = $monitoringQuarterRows->first(fn ($row) => $row['reporting_date'] === null);
        $canReport = ! Auth::user()->isUsingWorkspace('research_head') && $topic->isMonitoringAvailable() && $topic->isAccessibleTo(Auth::user());
        $latestTerminalReportId = $topic->narrativeReports
            ->where('report_type', 'terminal')
            ->sortByDesc('id')
            ->first()?->id;
    @endphp
    <header class="rounded-xl border border-gray-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h3 class="text-lg font-bold text-gray-950 dark:text-white">Project monitoring</h3>
                <p class="mt-1 text-sm text-gray-600 dark:text-slate-300">Report every three months. Submit the terminal report after the project ends.</p>
            </div>
            <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $projectStatus === 'completion_pending' ? 'bg-violet-50 text-violet-700 dark:bg-violet-950 dark:text-violet-200' : 'bg-gray-100 text-gray-700 dark:bg-slate-800 dark:text-slate-200' }}">{{ $projectStatusLabel }}</span>
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
            <p class="mt-4 text-sm text-gray-600 dark:text-slate-300">Project completed. Reports remain available as read-only records. Conference activity and publication tracking continue in Conferences &amp; Publications.</p>
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
            <p class="mt-3 text-sm text-gray-500">Completion requires 100% progress, a reviewed Terminal Report, and its fully signed PDF.</p>
            @error('project_status')<p class="mt-2 text-sm font-semibold text-red-700">{{ $message }}</p>@enderror
            <form method="POST" action="{{ route('research_head.projects.update-status', $topic) }}" class="mt-3 flex flex-wrap items-end gap-3">@csrf @method('PATCH')
                <label class="text-sm dark:text-white">Status<select name="project_status" class="mt-1 block rounded-lg border-gray-300 text-sm dark:bg-slate-800">@foreach (['ongoing', 'delayed', 'completed'] as $value)<option value="{{ $value }}" @selected($storedProjectStatus === $value)>{{ ucfirst($value) }}</option>@endforeach</select></label>
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
                            <span class="rounded-full px-2 py-1 text-[10px] font-black uppercase {{ $reviewStatusClass }}">{{ $report->review_status_label }}</span>
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
                            <a href="{{ route('project-progress.create', ['topic' => $topic, 'revise_monitoring_report' => $report->id]) }}" class="inline-flex text-xs font-bold text-red-700">Correct this {{ $report->quarter_label }} submission</a>
                        @endif
                    </div>
                    @if ($report->research_head_remarks)<div class="mt-3 rounded-xl bg-gray-50 p-3"><p class="text-[10px] font-black uppercase text-gray-400">Research Head remarks @if ($report->reviewer) · {{ $report->reviewer->name }} @endif</p><p class="mt-1 text-xs text-gray-600">{{ $report->research_head_remarks }}</p></div>@endif
                    @if (Auth::user()->isUsingWorkspace('research_head') && ! $topic->isCompletedProject() && $isCurrentVersion)
                        <form method="POST" action="{{ route('research_head.progress-reports.review', $report) }}" class="mt-4 grid gap-2 sm:grid-cols-[180px_1fr_auto]">@csrf @method('PATCH')<select name="review_status" class="rounded-xl border-gray-200 text-xs font-bold"><option value="reviewed" @selected($report->review_status === 'reviewed')>Mark reviewed</option><option value="revision_requested" @selected($report->review_status === 'revision_requested')>Request corrections</option></select><input name="research_head_remarks" value="{{ $report->research_head_remarks }}" maxlength="5000" class="rounded-xl border-gray-200 text-xs" placeholder="Describe the corrections needed"><button class="rounded-xl bg-red-600 px-4 py-2 text-xs font-bold text-white">Save review</button></form>
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
                @php
                    $isTerminalReport = $report->report_type === 'terminal';
                    $isLatestTerminalReport = $isTerminalReport && $report->id === $latestTerminalReportId;
                    $signedCopy = $report->signedCopy();
                @endphp
                <article class="rounded-xl border border-gray-200 p-4">
                    <div class="flex flex-wrap justify-between gap-3">
                        <div>
                            <p class="text-sm font-black text-gray-900">{{ $report->report_label }}@if ($report->report_type === 'terminal' && isset($report->terminal_data['version_number'])) · Version {{ $report->terminal_data['version_number'] }}@endif</p>
                            <p class="mt-1 text-[11px] text-gray-400">{{ $report->submission_date->format('M d, Y') }} · {{ $report->submitter->name }}</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <span class="rounded-full bg-gray-100 px-2 py-1 text-[10px] font-black uppercase text-gray-600">{{ $report->review_status_label }}</span>
                            @if ($isTerminalReport)
                                <span class="rounded-full px-2 py-1 text-[10px] font-black uppercase {{ $signedCopy ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">{{ $signedCopy ? 'Signed copy recorded' : 'Signed copy required' }}</span>
                            @endif
                        </div>
                    </div>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div><p class="text-[10px] font-black uppercase text-gray-400">Monitoring-period accomplishment</p><p class="mt-1 whitespace-pre-line text-xs leading-5 text-gray-600">{{ $report->accomplishment_summary }}</p></div>
                        <div><p class="text-[10px] font-black uppercase text-gray-400">Funding agency</p><p class="mt-1 text-xs leading-5 text-gray-600">{{ $report->funding_agency }}</p></div>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-4">
                        <a href="{{ route('project-narrative-reports.download', $report) }}" class="inline-flex text-xs font-bold text-red-700">Download {{ strtolower($report->report_label) }}</a>
                        @if ($signedCopy)
                            <a href="{{ route('project-narrative-reports.signed-copy.download', $report) }}" class="inline-flex text-xs font-bold text-emerald-700">Download signed Terminal Report</a>
                        @endif
                        @foreach ($report->photos ?? [] as $photoIndex => $photo)
                            <a href="{{ route('project-narrative-reports.photos.download', [$report, $photoIndex]) }}" class="inline-flex text-xs font-bold text-red-700">Photo {{ $photoIndex + 1 }}: {{ $photo['caption'] }}</a>
                        @endforeach
                    </div>
                    @if ($report->research_head_remarks)<div class="mt-3 rounded-xl bg-gray-50 p-3"><p class="text-[10px] font-black uppercase text-gray-400">Research Head remarks</p><p class="mt-1 text-xs text-gray-600">{{ $report->research_head_remarks }}</p></div>@endif
                    @if (Auth::user()->isUsingWorkspace('research_head') && ! $topic->isCompletedProject())
                        <form method="POST" action="{{ route('research_head.narrative-progress-reports.review', $report) }}" class="mt-4 grid gap-2 sm:grid-cols-[180px_1fr_auto]">@csrf @method('PATCH')<select name="review_status" class="rounded-xl border-gray-200 text-xs font-bold"><option value="reviewed" @selected($report->review_status === 'reviewed')>Mark reviewed</option><option value="revision_requested" @selected($report->review_status === 'revision_requested')>Request corrections</option></select><input name="research_head_remarks" value="{{ $report->research_head_remarks }}" maxlength="5000" class="rounded-xl border-gray-200 text-xs" placeholder="Describe the corrections needed"><button class="rounded-xl bg-red-600 px-4 py-2 text-xs font-bold text-white">Save review</button></form>
                    @endif
                    @if (Auth::user()->isUsingWorkspace('research_head') && ! $topic->isCompletedProject() && $isLatestTerminalReport && $report->review_status === \App\Models\ProjectNarrativeReport::STATUS_REVIEWED)
                        <form method="POST" action="{{ route('research_head.narrative-progress-reports.signed-copy.store', $report) }}" enctype="multipart/form-data" class="mt-4 border-l-4 border-emerald-700 bg-emerald-50/70 p-4">
                            @csrf
                            <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                                <label class="block min-w-0 flex-1 text-sm font-bold text-emerald-950">
                                    {{ $signedCopy ? 'Replace signed Terminal Report' : 'Fully signed Terminal Report' }}
                                    <span class="mt-1 block text-xs font-medium text-emerald-800">Upload the final PDF bearing the required signatures before project completion.</span>
                                    <input name="signed_report" type="file" accept=".pdf,application/pdf" required class="mt-2 block w-full rounded-lg border border-emerald-300 bg-white p-2 text-xs text-gray-700 file:mr-3 file:rounded-md file:border-0 file:bg-emerald-100 file:px-3 file:py-2 file:text-xs file:font-bold file:text-emerald-900">
                                </label>
                                <button class="inline-flex min-h-10 shrink-0 items-center justify-center rounded-lg bg-emerald-800 px-4 py-2 text-xs font-black text-white hover:bg-emerald-900">{{ $signedCopy ? 'Replace signed PDF' : 'Record signed PDF' }}</button>
                            </div>
                            @error('signed_report')<p class="mt-2 text-sm font-semibold text-red-700">{{ $message }}</p>@enderror
                        </form>
                    @endif
                </article>
            @empty
                <div class="rounded-xl bg-gray-50 py-8 text-center"><p class="text-sm font-bold text-gray-700">No progress reports yet</p><p class="mt-1 text-xs text-gray-400">The first narrative progress report will appear here.</p></div>
            @endforelse
        </div>
    </div>
</section>
