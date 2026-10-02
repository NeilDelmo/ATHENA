@props(['report'])

@php
    $compactStatus = match ($report->review_status) {
        'reviewed' => 'Reviewed',
        'revision_requested' => 'Corrections requested',
        default => 'Awaiting review',
    };
@endphp

<article id="narrative-report-{{ $report->id }}" data-narrative-report-summary data-narrative-history-entry="{{ $report->id }}" {{ $attributes->class(['scroll-mt-36 flex flex-col gap-3 rounded-xl border border-red-200 bg-white px-5 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-red-900 dark:bg-slate-900']) }}>
    <div class="min-w-0">
        <h4 class="text-base font-bold text-gray-950 dark:text-white">{{ $report->report_label }} <span class="font-normal text-gray-600 dark:text-slate-300">· {{ $report->submission_date->format('M j, Y') }}</span></h4>
        <p class="mt-1 text-sm text-gray-600 dark:text-slate-300">{{ $report->submitter->name }}</p>
    </div>
    <div class="flex flex-wrap items-center gap-3 sm:shrink-0 sm:justify-end">
        <span data-narrative-report-status title="{{ $report->review_status_label }}" class="rounded-full bg-red-50 px-3 py-1.5 text-sm font-medium text-brand dark:bg-red-950/40 dark:text-red-200"><span class="sr-only">{{ $report->review_status_label }}</span><span aria-hidden="true">{{ $compactStatus }}</span></span>
        @if ($report->report_type === 'terminal' && ($report->hasSignedCopy() || $report->review_status === \App\Models\ProjectNarrativeReport::STATUS_REVIEWED))
            <span class="text-sm text-gray-600 dark:text-slate-300">{{ $report->hasSignedCopy() ? 'Signed terminal PDF uploaded' : 'Signed terminal PDF needed' }}</span>
        @endif
        <a href="{{ route('project-narrative-reports.show', $report) }}" aria-label="Open {{ strtolower($report->report_label) }} submitted {{ $report->submission_date->format('M j, Y') }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-brand px-4 py-2 text-base font-semibold text-white hover:bg-brand-soft focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-900">Open report <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m9 5 7 7-7 7"/></svg></a>
    </div>
</article>
