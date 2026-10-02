@props(['quarterRows', 'topic', 'canReport' => false, 'legacyReports' => collect()])

<section id="progress-reports" class="overflow-hidden rounded-2xl border border-red-200 bg-white shadow-sm dark:border-red-900 dark:bg-slate-900" aria-labelledby="progress-reports-heading">
    <header class="border-b border-red-200 bg-red-50 px-5 py-5 dark:border-red-900 dark:bg-red-950/30 sm:px-6">
        <h3 id="progress-reports-heading" class="text-xl font-bold text-red-800 dark:text-red-300">Progress reports</h3>
        <p class="mt-2 text-base leading-7 text-slate-600 dark:text-slate-300">One Progress Report for each Monitoring Tool quarter, covering the same reporting period. Submit both after the quarter ends.</p>
    </header>
    <div class="overflow-x-auto" data-progress-schedule-table>
        <table class="min-w-full divide-y divide-slate-200 text-left dark:divide-slate-800">
            <caption class="sr-only">Quarterly Progress Report periods and available actions</caption>
            <thead class="bg-white dark:bg-slate-950/50">
                <tr class="text-sm font-semibold text-slate-600 dark:text-slate-300">
                    <th scope="col" class="w-24 px-5 py-3 sm:px-6">Quarter</th>
                    <th scope="col" class="min-w-[16rem] px-5 py-3">Reporting period</th>
                    <th scope="col" class="min-w-[11rem] px-5 py-3">Status</th>
                    <th scope="col" class="min-w-[13rem] px-5 py-3 text-right sm:px-6">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($quarterRows as $row)
                    @php($report = $row['report'])
                    <tr data-progress-quarter="{{ $row['quarter'] }}" @if ($report) id="narrative-report-{{ $report->id }}" data-narrative-history-entry="{{ $report->id }}" @endif class="align-middle hover:bg-slate-50/70 dark:hover:bg-slate-950/30">
                        <th scope="row" class="px-5 py-4 sm:px-6"><span class="inline-flex h-11 min-w-11 items-center justify-center rounded-xl bg-red-700 px-3 text-base font-bold text-white">{{ $row['label'] }}</span></th>
                        <td class="px-5 py-4">
                            <p class="whitespace-nowrap text-base font-semibold text-slate-900 dark:text-white">{{ $row['period'] }}</p>
                            @if (! $row['submission_open'])<p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Submission opens {{ $row['opens_at']->format('M j, Y') }}</p>@endif
                        </td>
                        <td class="px-5 py-4"><span data-narrative-report-status class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-sm font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ $row['status'] }}@if ($report)<span class="sr-only">{{ $report->review_status_label }}</span>@endif</span></td>
                        <td class="px-5 py-4 sm:px-6">
                            <div class="flex min-w-max flex-wrap items-center justify-end gap-2">
                                @if ($report)
                                    <a href="{{ route('project-narrative-reports.show', $report) }}" aria-label="Open {{ $row['label'] }} progress report" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-bold text-slate-700 hover:bg-red-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-red-950/30">Open report</a>
                                @endif
                                @if ($canReport && $row['drafting_date'] && ! $topic->isCompletedProject())
                                    <a href="{{ route('project-narrative-reports.create', ['topic' => $topic, 'report_type' => 'progress', 'reporting_date' => $row['drafting_date']]) }}" aria-label="{{ $report?->isPrepared() ? 'Review' : ($report ? 'Revise' : 'Fill') }} {{ $row['label'] }} progress report" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-red-700 px-3 py-2 text-sm font-bold text-white hover:bg-red-800 focus-visible:ring-2 focus-visible:ring-red-600 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-900">{{ $report?->isPrepared() ? 'Review and submit' : ($report ? 'Revise report' : ($row['submission_open'] ? 'Start report' : 'Fill draft')) }}</a>
                                @elseif (! $report)
                                    <span class="text-sm text-slate-500 dark:text-slate-400">{{ $row['submission_open'] ? 'No report yet' : 'Not open yet' }}</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-6 py-10 text-center text-sm text-slate-500 dark:text-slate-400">No reporting periods are scheduled. Check the approved project dates.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($legacyReports->isNotEmpty())
        <div class="space-y-3 border-t border-slate-200 p-5 dark:border-slate-800">
            <p class="text-sm text-slate-600 dark:text-slate-300">Earlier Progress Reports without a recorded quarter</p>
            @foreach ($legacyReports as $report)
                <x-narrative-report-summary :report="$report" />
            @endforeach
        </div>
    @endif
</section>
