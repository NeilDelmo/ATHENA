@props(['topic', 'report', 'quarter' => null, 'progressPercentage' => null, 'closable' => false])

@php($quarter = $quarter ?? $report->reporting_quarter)

<div class="quarter-report-document" data-progress-report-detail>
    <x-quarter-report-header :report="$report" :title="'Q'.$quarter.' Progress Report · Version '.($report->version_number ?? 1)" :closable="$closable" />
    <div class="quarter-report-layout">
        <div class="min-w-0 space-y-5">
            <section class="max-w-lg" aria-label="Recorded project progress">
                @if ($progressPercentage !== null)
                    <div class="flex items-baseline justify-between gap-3 text-base"><h4 class="font-medium text-slate-900 dark:text-white">Recorded project progress</h4><span class="font-semibold tabular-nums text-slate-900 dark:text-white">{{ $progressPercentage }}%</span></div>
                    <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700" role="progressbar" aria-label="Q{{ $quarter }} reported progress" aria-valuenow="{{ $progressPercentage }}" aria-valuemin="0" aria-valuemax="100"><div class="h-full rounded-full bg-red-700 dark:bg-red-400" style="width: {{ $progressPercentage }}%"></div></div>
                    <p class="mt-2 text-base leading-6 text-slate-500 dark:text-slate-400">From the Q{{ $quarter }} Monitoring Tool for this reporting period</p>
                @else
                    <p class="text-base text-slate-500 dark:text-slate-400">No monitoring progress recorded for this quarter.</p>
                @endif
            </section>

            @if (str_starts_with($report->accomplishment_summary ?? '', 'UI DEMONSTRATION'))
                <p class="text-base text-slate-500 dark:text-slate-400">Sample report · Illustrative figures and findings.</p>
            @endif

            @if ($report->research_head_remarks)
                <section class="border-t border-slate-200 pt-3 dark:border-slate-700"><h4 class="text-base font-medium text-slate-950 dark:text-white">{{ $report->review_stage === 'secretary' && $report->review_status === 'pending' ? 'Research Head concerns · Research Office Secretary to resolve' : 'Research Head remarks' }} @if ($report->reviewer)<span class="ml-2 font-normal text-slate-500 dark:text-slate-400">{{ $report->reviewer->name }}</span>@endif</h4><p class="mt-1 max-w-[70ch] whitespace-pre-line break-words text-base leading-6 text-slate-600 dark:text-slate-300">{{ $report->research_head_remarks }}</p></section>
            @endif

            <x-implementation-report-secretary-review :topic="$topic" :report="$report" />
            <x-implementation-report-head-review :topic="$topic" :report="$report" />

            <div class="pt-1">
                <a data-progress-report-reader-link href="{{ route('project-narrative-reports.show', $report) }}" aria-label="Read full Q{{ $quarter }} progress report" class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-base font-medium text-slate-700 hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">
                    Report details
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m9 5 7 7-7 7" /></svg>
                </a>
            </div>
        </div>
        <x-submitted-report-document name="progress-paper-{{ $report->id }}" modal-name="narrative-pdf-{{ $report->id }}" title="Submitted progress report paper" :preview-url="route('project-narrative-reports.view', $report)" :download-url="route('project-narrative-reports.download', $report)" :viewer-label="'Q'.$quarter.' progress report paper'" />
    </div>
</div>
