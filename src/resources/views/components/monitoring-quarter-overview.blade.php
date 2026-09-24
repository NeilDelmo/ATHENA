@props(['quarterRows', 'topic' => null])
<section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900" aria-labelledby="quarterly-reporting-schedule-heading">
    <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800 sm:px-6">
        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-red-700 dark:text-red-300">Reporting calendar</p>
        <h4 id="quarterly-reporting-schedule-heading" class="mt-1 text-base font-black text-slate-950 dark:text-white">Quarterly reporting schedule</h4>
        <p class="mt-1 max-w-3xl text-sm leading-6 text-slate-500 dark:text-slate-400">Each report covers three months from the monitoring start date. Submission opens after the period ends; the final period may be shorter.</p>
    </div>

    <div class="overflow-x-auto" data-monitoring-schedule-table>
        <table class="min-w-full divide-y divide-slate-200 text-left dark:divide-slate-800">
            <caption class="sr-only">Quarterly project monitoring periods and available actions</caption>
            <thead class="bg-slate-50/80 dark:bg-slate-950/50">
                <tr class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">
                    <th scope="col" class="w-24 px-5 py-3 sm:px-6">Quarter</th>
                    <th scope="col" class="min-w-[16rem] px-5 py-3">Reporting period</th>
                    <th scope="col" class="min-w-[11rem] px-5 py-3">Status</th>
                    <th scope="col" class="min-w-[13rem] px-5 py-3 text-right sm:px-6">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($quarterRows as $row)
                    @php
                        $report = $row['report'];
                        $canSubmit = $topic && ! auth()->user()->isUsingWorkspace('research_head') && $topic->isMonitoringAvailable() && $topic->isAccessibleTo(auth()->user()) && $row['reporting_date'];
                        $statusClass = match (true) {
                            $report?->review_status === 'reviewed' => 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:ring-emerald-900',
                            $report?->review_status === 'revision_requested' => 'bg-red-50 text-red-700 ring-red-200 dark:bg-red-950/40 dark:text-red-300 dark:ring-red-900',
                            $report?->isPrepared() => 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:ring-amber-900',
                            $report !== null => 'bg-sky-50 text-sky-700 ring-sky-200 dark:bg-sky-950/40 dark:text-sky-300 dark:ring-sky-900',
                            $row['reporting_date'] !== null => 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:ring-amber-900',
                            default => 'bg-slate-100 text-slate-600 ring-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700',
                        };
                    @endphp
                    <tr class="align-middle transition hover:bg-slate-50/70 dark:hover:bg-slate-950/30">
                        <th scope="row" class="px-5 py-4 sm:px-6">
                            <span class="inline-flex h-9 min-w-9 items-center justify-center rounded-lg bg-slate-100 px-2 text-xs font-black text-slate-700 ring-1 ring-inset ring-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:ring-slate-700">{{ $row['label'] }}</span>
                        </th>
                        <td class="px-5 py-4">
                            <p class="whitespace-nowrap text-sm font-bold text-slate-900 dark:text-white">{{ $row['period'] }}</p>
                            @if (! $row['reporting_date'] && isset($row['opens_at']))
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Opens {{ $row['opens_at']->format('M j, Y') }}</p>
                            @endif
                        </td>
                        <td class="px-5 py-4">
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-wide ring-1 ring-inset {{ $statusClass }}">{{ $row['status'] }}</span>
                        </td>
                        <td class="px-5 py-4 sm:px-6">
                            <div class="flex min-w-max flex-wrap items-center justify-end gap-2">
                                @if ($report)
                                    <a data-monitoring-action href="#monitoring-tool-{{ $report->id }}" class="inline-flex min-h-9 items-center justify-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-black text-slate-700 transition hover:border-red-200 hover:bg-red-50 hover:text-red-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-600 focus-visible:ring-offset-2 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-red-900 dark:hover:bg-red-950/30 dark:hover:text-red-300 dark:focus-visible:ring-offset-slate-900">View report</a>
                                    @if ($canSubmit && $report->isPrepared() && $report->submitted_by === auth()->id())
                                        <a data-monitoring-action href="{{ route('project-progress.create', ['topic' => $topic, 'reporting_date' => $report->reporting_date->toDateString(), 'revise_monitoring_report' => $report->supersedes_report_id]) }}" class="inline-flex min-h-9 items-center justify-center rounded-lg bg-red-700 px-3 py-2 text-xs font-black text-white transition hover:bg-red-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-600 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-900">Review and submit</a>
                                    @elseif ($canSubmit && $report->review_status === 'revision_requested' && ! $report->nextVersion)
                                        <a data-monitoring-action href="{{ route('project-progress.create', ['topic' => $topic, 'revise_monitoring_report' => $report->id]) }}" class="inline-flex min-h-9 items-center justify-center rounded-lg bg-red-700 px-3 py-2 text-xs font-black text-white transition hover:bg-red-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-600 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-900">Revise report</a>
                                    @endif
                                @elseif ($canSubmit)
                                    <a data-monitoring-action href="{{ route('project-progress.create', ['topic' => $topic, 'reporting_date' => $row['reporting_date']]) }}" class="inline-flex min-h-9 items-center justify-center rounded-lg bg-red-700 px-3 py-2 text-xs font-black text-white transition hover:bg-red-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-600 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-900">Start report</a>
                                @elseif (! $row['reporting_date'])
                                    <button type="button" disabled class="inline-flex min-h-9 cursor-not-allowed items-center justify-center rounded-lg border border-slate-200 bg-slate-100 px-3 py-2 text-xs font-bold text-slate-400 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-500">Not open yet</button>
                                @else
                                    <span class="text-xs font-semibold text-slate-400 dark:text-slate-500">No action available</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-6 py-10 text-center text-sm text-slate-500 dark:text-slate-400">No reporting periods are scheduled. Check the approved project dates.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
