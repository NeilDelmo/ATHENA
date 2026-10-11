@props(['report', 'headingId' => null])

@php
    $compactStatus = match ($report->review_status) {
        'reviewed' => 'Reviewed',
        'revision_requested' => 'Corrections requested',
        default => 'Awaiting review',
    };
@endphp

<article id="narrative-report-{{ $report->id }}" data-narrative-report-summary data-narrative-history-entry="{{ $report->id }}" {{ $attributes->class(['scroll-mt-36 flex flex-col gap-3 rounded-xl border border-slate-200 bg-white px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6 dark:border-slate-700 dark:bg-slate-900']) }}>
    <div class="min-w-0">
        <h4 @if ($headingId) id="{{ $headingId }}" @endif class="text-base font-semibold text-slate-950 dark:text-white">{{ $report->report_label }}</h4>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $report->submission_date->format('M j, Y') }} · {{ $report->submitter->name }}</p>
        @if ($report->reporting_period_label)<p class="mt-1 text-sm font-medium text-slate-600 dark:text-slate-300">{{ $report->reporting_period_label }} · Version {{ $report->version_number }}</p>@endif
    </div>
    <div class="flex flex-wrap items-center gap-3 sm:shrink-0 sm:justify-end">
        <x-report-review-status data-narrative-report-status :status="$report->review_status" :label="$compactStatus" :description="$report->review_status_label" />
        @if ($report->report_type === 'terminal' && ($report->hasSignedCopy() || $report->review_status === \App\Models\ProjectNarrativeReport::STATUS_REVIEWED))
            <span class="text-sm text-gray-600 dark:text-slate-300">{{ $report->hasSignedCopy() ? 'Signed terminal PDF uploaded' : 'Signed terminal PDF needed' }}</span>
        @endif
        <a href="{{ route('project-narrative-reports.show', $report) }}" aria-label="Open {{ strtolower($report->report_label) }} submitted {{ $report->submission_date->format('M j, Y') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800">Open report</a>
    </div>
</article>
