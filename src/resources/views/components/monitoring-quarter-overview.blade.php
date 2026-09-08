@props(['quarterRows', 'topic' => null])
<section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-slate-700 dark:bg-slate-900">
    <div class="border-b border-gray-100 p-5 dark:border-slate-800">
        <h4 class="text-base font-bold text-gray-950 dark:text-white">Quarterly reporting schedule</h4>
        <p class="mt-1 text-sm text-gray-500 dark:text-slate-400">Each report covers three months from the monitoring start date. Submission opens after the period ends; the final period may be shorter.</p>
    </div>
    <ul role="list" class="divide-y divide-gray-100 dark:divide-slate-800">
        @forelse ($quarterRows as $row)
            @php
                $report = $row['report'];
                $canSubmit = $topic && ! auth()->user()->isUsingWorkspace('research_head') && $topic->isMonitoringAvailable() && $topic->isAccessibleTo(auth()->user()) && $row['reporting_date'];
            @endphp
            <li class="flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-start gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-sm font-bold text-gray-700 dark:bg-slate-800 dark:text-slate-200">{{ $row['label'] }}</span>
                    <div>
                        <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $row['period'] }}</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">{{ $row['status'] }}@if (! $row['reporting_date'] && isset($row['opens_at'])) · Opens {{ $row['opens_at']->format('M j, Y') }}@endif</p>
                    </div>
                </div>
                <div class="flex shrink-0 flex-wrap items-center gap-3">
                    @if ($report)
                        <a href="#monitoring-tool-{{ $report->id }}" class="text-sm font-semibold text-red-700 dark:text-red-300">View report</a>
                        @if ($canSubmit && $report->isPrepared() && $report->submitted_by === auth()->id())
                            <a href="{{ route('project-progress.create', ['topic' => $topic, 'reporting_date' => $report->reporting_date->toDateString(), 'revise_monitoring_report' => $report->supersedes_report_id]) }}" class="text-sm font-semibold text-red-700 dark:text-red-300">Review and submit</a>
                        @elseif ($canSubmit && $report->review_status === 'revision_requested' && ! $report->nextVersion)
                            <a href="{{ route('project-progress.create', ['topic' => $topic, 'revise_monitoring_report' => $report->id]) }}" class="text-sm font-semibold text-red-700 dark:text-red-300">Revise report</a>
                        @endif
                    @elseif ($canSubmit)
                        <a href="{{ route('project-progress.create', ['topic' => $topic, 'reporting_date' => $row['reporting_date']]) }}" class="rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800">Start report</a>
                    @elseif (! $row['reporting_date'])
                        <button type="button" disabled class="rounded-lg bg-gray-100 px-4 py-2 text-sm text-gray-400 dark:bg-slate-800">Not open yet</button>
                    @endif
                </div>
            </li>
        @empty
            <li class="p-5 text-sm text-gray-500">No reporting periods are scheduled. Check the approved project dates.</li>
        @endforelse
    </ul>
</section>
