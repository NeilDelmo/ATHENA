@props(['quarterRows', 'progressQuarterRows', 'topic', 'canReport' => false])

@php
    $canViewProgressReport = Auth::user()->isUsingWorkspace('research_head') || $topic->isAccessibleTo(Auth::user());
@endphp

<section id="progress-reports" class="quarterly-reports overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900" aria-labelledby="quarterly-reporting-schedule-heading">
    <header id="quarterly-reports" class="bg-brand px-5 py-4 text-white sm:px-6">
        <div>
            <h4 id="quarterly-reporting-schedule-heading" class="text-lg font-semibold">Quarterly reports</h4>
            @if (! $topic->isCompletedProject())
                <p class="mt-1 text-base leading-6 text-red-100">Submit both reports during the final week of each quarter. Reports are due on the last day and overdue the next day. The final period may be shorter.</p>
            @else
                <p class="mt-1 text-base leading-6 text-red-100">Open a report to read the submitted paper.</p>
            @endif
        </div>
    </header>

    <div data-monitoring-schedule-table data-progress-schedule-table>
        <table class="w-full table-fixed text-left">
            <caption class="sr-only">Quarterly Monitoring Tool and Progress Report periods and available actions</caption>
            <thead class="border-y border-slate-200 bg-slate-100 text-sm text-slate-600 dark:border-slate-700 dark:bg-slate-950/50 dark:text-slate-300">
                <tr>
                    <th scope="col" class="w-2/5 px-5 py-3 font-medium sm:px-6">Reporting period</th>
                    <th scope="col" class="w-[30%] px-1 py-3 font-medium"><span class="quarterly-report-rail">Monitoring Tool</span></th>
                    <th scope="col" class="w-[30%] px-1 py-3 font-medium"><span class="quarterly-report-rail">Progress Report</span></th>
                </tr>
            </thead>
            <tbody
                x-data="{
                    openReportId: /^#(monitoring-tool|narrative-report)-\d+$/.test(window.location.hash) ? window.location.hash.slice(1) : null,
                    closeReport() {
                        const trigger = $el.querySelector('button[aria-controls=&quot;' + this.openReportId + '&quot;]');
                        if (window.location.hash === '#' + this.openReportId) {
                            history.replaceState(history.state, '', '#project-monitoring');
                        }
                        this.openReportId = null;
                        $nextTick(() => trigger?.focus());
                    },
                    toggleReport(id) {
                        if (this.openReportId === id) { this.closeReport(); }
                        else { this.openReportId = id; }
                    }
                }"
                x-on:close-quarter-report.stop="closeReport()"
                x-on:keydown.escape="if (openReportId && ! $event.target.closest('[data-quarter-pdf-preview-modal]')) { $event.stopPropagation(); closeReport(); }"
                x-on:hashchange.window="if (/^#(monitoring-tool|narrative-report)-\d+$/.test(window.location.hash)) { openReportId = window.location.hash.slice(1) }"
            >
                @forelse ($quarterRows as $row)
                    @php
                        $report = ($canReport ? $topic->preparedProgressReports->first(fn ($preparedReport) => $preparedReport->reporting_date->betweenIncluded($row['start'], $row['end'])) : null) ?? $row['report'];
                        $progressRow = $progressQuarterRows->firstWhere('quarter', $row['quarter']);
                        $progressReport = $progressRow['report'] ?? null;
                        $overallProgress = $row['report']?->isSubmitted() ? $row['report']->progress_percentage : null;
                        $quarterProgress = ($row['has_approved_work_plan'] ?? false) ? ($row['quarter_completion'] ?? null) : $overallProgress;
                        $progressLabel = ($row['has_approved_work_plan'] ?? false) ? 'quarter targets' : 'project progress';
                        $canSubmit = $canReport && $row['reporting_date'];
                        $canDraft = $canReport && ($row['drafting_date'] ?? null);
                        $toolStatus = $topic->isCompletedProject() && $report === null ? 'No tool on file' : ($report?->isPrepared() ? 'PDF prepared'.($report->supersedes_report_id === null && $row['end']->isPast() ? ' · Overdue' : '') : $row['status']);
                        $progressStatus = $topic->isCompletedProject() && $progressReport === null ? 'No report on file' : ($progressRow['status'] ?? 'No report on file');
                    @endphp
                    <tr data-reporting-quarter="{{ $row['quarter'] }}" class="border-t border-slate-200 bg-slate-50/70 dark:border-slate-700 dark:bg-slate-900">
                        <th scope="row" class="px-5 py-3 align-top font-normal sm:px-6">
                            <p data-reporting-period class="flex min-h-11 items-center gap-3 text-base leading-6 text-slate-900 dark:text-white"><span class="quarterly-report-quarter">{{ $row['label'] }}</span><span>{{ $row['period'] }}</span></p>
                            @if (! $topic->isCompletedProject())
                                <p data-reporting-deadline class="ml-[3.25rem] mt-1 text-sm text-slate-600 dark:text-slate-400">Due {{ $row['end']->format('M j, Y') }}</p>
                            @endif
                            @if ($quarterProgress !== null)
                                <div class="ml-[3.25rem] mt-2 flex max-w-64 items-center gap-3">
                                    <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700" role="progressbar" aria-label="{{ $row['label'] }} {{ $progressLabel }}" aria-valuenow="{{ $quarterProgress }}" aria-valuemin="0" aria-valuemax="100"><div class="h-full rounded-full bg-slate-700 dark:bg-slate-300" style="width: {{ $quarterProgress }}%"></div></div>
                                    <span class="shrink-0 text-xs tabular-nums text-slate-600 dark:text-slate-300">{{ $quarterProgress }}% {{ $progressLabel }}</span>
                                </div>
                                @if (($row['has_approved_work_plan'] ?? false) && $quarterProgress < 100 && now()->greaterThan($row['end']))
                                    <div class="ml-[3.25rem] mt-2"><x-report-review-status status="revision_requested" label="Targets overdue" /></div>
                                @endif
                            @elseif ($overallProgress !== null && ($row['has_approved_work_plan'] ?? false))
                                <span class="ml-[3.25rem] mt-1 block text-xs text-slate-500 dark:text-slate-400">No targets due this quarter</span>
                            @endif
                            @if (! $topic->isCompletedProject())
                                <span class="sr-only">{{ $row['reporting_date'] ? 'Open for submission' : 'Submission opens '.$row['opens_at']->format('M j, Y') }}</span>
                            @endif
                        </th>
                        <td data-monitoring-quarter="{{ $row['quarter'] }}" class="px-1 py-3 align-top">
                            <div class="quarterly-report-rail"><span class="quarterly-report-mobile-label">Monitoring Tool</span>
                            <div data-report-controls class="flex flex-wrap items-center justify-start gap-1">
                                @if ($report)
                                    @if (! $report->isPrepared())
                                    <button type="button" x-on:click="toggleReport('monitoring-tool-{{ $report->id }}')" data-monitoring-action aria-controls="monitoring-tool-{{ $report->id }}" x-bind:aria-expanded="openReportId === 'monitoring-tool-{{ $report->id }}'" aria-label="View {{ $row['label'] }} monitoring tool: {{ $toolStatus }}" title="{{ $row['label'] }} Monitoring Tool · {{ $toolStatus }}" class="monitoring-report-control quarterly-report-primary">
                                        <span>View tool</span>
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-6-6Zm0 0v6h6M8 17v-3m4 3v-5m4 5v-2" /></svg>
                                        <x-report-review-status data-monitoring-report-status :compact="true" :status="$report?->review_status ?? 'unsubmitted'" :label="$toolStatus" />
                                    </button>
                                    @endif
                                    @if ($canSubmit && $report->isPrepared() && $report->submitted_by === auth()->id())
                                        <a data-monitoring-action href="{{ route('project-progress.create', ['topic' => $topic, 'reporting_date' => $report->reporting_date->toDateString(), 'revise_monitoring_report' => $report->supersedes_report_id]) }}" title="Review and submit {{ $row['label'] }} monitoring tool" aria-label="Review and submit {{ $row['label'] }} monitoring tool" class="monitoring-report-control quarterly-report-primary"><span>Review and submit</span><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16V4m-4 4 4-4 4 4M5 14v6h14v-6" /></svg><x-report-review-status data-monitoring-report-status :compact="true" :status="$report?->review_status ?? 'unsubmitted'" :label="$toolStatus" /></a>
                                    @elseif ($canSubmit && $report->review_status === 'revision_requested' && ! $report->nextVersion)
                                        <a data-monitoring-action href="{{ route('project-progress.create', ['topic' => $topic, 'revise_monitoring_report' => $report->id]) }}" title="Revise {{ $row['label'] }} monitoring tool" aria-label="Revise {{ $row['label'] }} monitoring tool" class="monitoring-report-control"><span>Revise tool</span><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m16 4 4 4M4 20l5-1L20 8a3 3 0 0 0-4-4L5 15l-1 5Z" /></svg></a>
                                    @endif
                                    @if ($canReport && $report->isPrepared())
                                        <a href="{{ route('project-budget.edit', [$topic, $report]) }}" title="{{ $report->hasPreparedBudget() ? 'Review budget' : 'Complete budget' }}" aria-label="{{ $report->hasPreparedBudget() ? 'Review budget' : 'Complete budget' }}" class="monitoring-report-control {{ $canSubmit && $report->submitted_by === auth()->id() ? '' : 'quarterly-report-primary' }}"><span>{{ $report->hasPreparedBudget() ? 'Review budget' : 'Complete budget' }}</span><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m3-10h-4a2 2 0 0 0 0 4h2a2 2 0 0 1 0 4H9" /></svg>@if (! $canSubmit || $report->submitted_by !== auth()->id())<x-report-review-status data-monitoring-report-status :compact="true" :status="$report?->review_status ?? 'unsubmitted'" :label="$toolStatus" />@endif</a>
                                    @endif
                                    @if ($report->isPrepared() && ! $canReport)
                                        <span class="monitoring-report-control quarterly-report-primary" aria-disabled="true" role="img" aria-label="{{ $row['label'] }} monitoring tool unavailable" title="{{ $toolStatus }}"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-6-6Zm0 0v6h6M8 17v-3m4 3v-5m4 5v-2" /></svg><x-report-review-status data-monitoring-report-status :compact="true" :status="$report?->review_status ?? 'unsubmitted'" :label="$toolStatus" /></span>
                                    @endif
                                @elseif ($canDraft)
                                    <a data-monitoring-action href="{{ route('project-progress.create', ['topic' => $topic, 'reporting_date' => $row['drafting_date']]) }}" title="{{ $canSubmit ? 'Start tool' : 'Fill draft' }} · {{ $row['label'] }} Monitoring Tool" aria-label="{{ $canSubmit ? 'Start' : 'Draft' }} {{ $row['label'] }} monitoring tool" class="monitoring-report-control quarterly-report-primary"><span>{{ $canSubmit ? 'Start tool' : 'Fill draft' }}</span><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-6-6Zm0 0v6h6M8 15h8m-4-4v8" /></svg><x-report-review-status data-monitoring-report-status :compact="true" :status="$report?->review_status ?? 'unsubmitted'" :label="$toolStatus" /></a>
                                @else
                                    <span class="monitoring-report-control quarterly-report-primary" aria-disabled="true" role="img" aria-label="{{ $row['label'] }} monitoring tool unavailable" title="{{ $toolStatus }}"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-6-6Zm0 0v6h6M8 17v-3m4 3v-5m4 5v-2" /></svg><x-report-review-status data-monitoring-report-status :compact="true" :status="$report?->review_status ?? 'unsubmitted'" :label="$toolStatus" /></span>
                                @endif
                            </div></div>
                        </td>
                        <td data-progress-quarter="{{ $row['quarter'] }}" class="px-1 py-3 align-top">
                            <div class="quarterly-report-rail"><span class="quarterly-report-mobile-label">Progress Report</span>
                            <div data-report-controls class="flex flex-wrap items-center justify-start gap-1">
                                @if ($progressReport && ! $progressReport->isPrepared() && $canViewProgressReport)
                                    <button type="button" x-on:click="toggleReport('narrative-report-{{ $progressReport->id }}')" data-progress-action aria-controls="narrative-report-{{ $progressReport->id }}" x-bind:aria-expanded="openReportId === 'narrative-report-{{ $progressReport->id }}'" aria-label="View {{ $row['label'] }} progress report: {{ $progressStatus }}" title="{{ $row['label'] }} Progress Report · {{ $progressStatus }}" class="monitoring-report-control quarterly-report-primary"><span>Open report</span><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-6-6Zm0 0v6h6M8 13h8m-8 4h5" /></svg><x-report-review-status data-narrative-report-status :compact="true" :status="$progressReport?->review_status ?? 'unsubmitted'" :label="$progressStatus" :description="$progressReport?->review_status_label" /></button>
                                @endif
                                @if ($canReport && ($progressRow['drafting_date'] ?? null))
                                    <a href="{{ route('project-narrative-reports.create', ['topic' => $topic, 'report_type' => 'progress', 'reporting_date' => $progressRow['drafting_date']]) }}" aria-label="{{ $progressReport?->isPrepared() ? 'Review' : ($progressReport ? 'Revise' : 'Fill') }} {{ $row['label'] }} progress report" title="{{ $progressReport?->isPrepared() ? 'Review and submit' : ($progressReport ? 'Revise report' : ($progressRow['submission_open'] ? 'Start report' : 'Fill draft')) }} · {{ $row['label'] }} Progress Report" class="monitoring-report-control {{ $progressReport && ! $progressReport->isPrepared() && $canViewProgressReport ? '' : 'quarterly-report-primary' }}"><span>{{ $progressReport?->isPrepared() ? 'Review and submit' : ($progressReport ? 'Revise report' : ($progressRow['submission_open'] ? 'Start report' : 'Fill draft')) }}</span><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m16 4 4 4M4 20l5-1L20 8a3 3 0 0 0-4-4L5 15l-1 5Z" /></svg>@if (! $progressReport || $progressReport->isPrepared() || ! $canViewProgressReport)<x-report-review-status data-narrative-report-status :compact="true" :status="$progressReport?->review_status ?? 'unsubmitted'" :label="$progressStatus" :description="$progressReport?->review_status_label" />@endif</a>
                                @elseif (! $progressReport || ! $canViewProgressReport)
                                    <span class="monitoring-report-control quarterly-report-primary" aria-disabled="true" role="img" aria-label="{{ $row['label'] }} progress report unavailable" title="{{ $progressStatus }}"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-6-6Zm0 0v6h6M8 13h8m-8 4h5" /></svg><x-report-review-status data-narrative-report-status :compact="true" :status="$progressReport?->review_status ?? 'unsubmitted'" :label="$progressStatus" :description="$progressReport?->review_status_label" /></span>
                                @endif
                            </div></div>
                        </td>
                    </tr>
                    @if ($report && ! $report->isPrepared())
                        <tr id="monitoring-tool-{{ $report->id }}" x-cloak x-show="openReportId === 'monitoring-tool-{{ $report->id }}'" class="bg-slate-100 dark:bg-slate-950/40">
                            <td colspan="3" class="border-t border-slate-200 p-3 dark:border-slate-700 sm:p-4">
                                <x-monitoring-tool-report-details :topic="$topic" :report="$report" :closable="true" />
                            </td>
                        </tr>
                    @endif
                    @if ($progressReport && ! $progressReport->isPrepared() && $canViewProgressReport)
                        <tr id="narrative-report-{{ $progressReport->id }}" data-narrative-history-entry="{{ $progressReport->id }}" x-cloak x-show="openReportId === 'narrative-report-{{ $progressReport->id }}'" class="bg-slate-100 dark:bg-slate-950/40">
                            <td colspan="3" class="border-t border-slate-200 p-3 dark:border-slate-700 sm:p-4">
                                <x-progress-report-details :report="$progressReport" :progress-percentage="$overallProgress" :closable="true" />
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr><td colspan="3" class="px-6 py-8 text-sm text-slate-500 dark:text-slate-400">No reporting periods are scheduled. Check the approved project dates.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
