@props(['report', 'title', 'isCurrentVersion' => true, 'closable' => false])

@php
    $isMonitoringTool = $report instanceof \App\Models\ProjectProgressReport;
    $modalName = ($isMonitoringTool ? 'monitoring-pdf-' : 'narrative-pdf-').$report->id;
    $downloadUrl = route($isMonitoringTool ? 'project-progress.monitoring-tool' : 'project-narrative-reports.download', $report);
    $previewUrl = route($isMonitoringTool ? 'project-progress.monitoring-tool.view' : 'project-narrative-reports.view', $report);
    $submittedDate = $isMonitoringTool ? ($report->submitted_at ?? $report->created_at) : $report->submission_date;
    $status = $report->review_stage !== null ? $report->review_status_label : match ($report->review_status) {
        'reviewed' => 'Reviewed',
        'revision_requested' => 'Corrections requested',
        default => 'Pending review',
    };
@endphp

<header data-quarter-report-heading class="quarter-report-heading">
    <div class="min-w-0">
        <div class="flex flex-wrap items-center gap-2">
            <h4 class="text-xl font-semibold tracking-tight text-slate-950 dark:text-white">{{ $title }}</h4>
            <x-report-review-status :status="$report->review_status" :label="$status" :description="$report->review_status_label" />
            <span class="{{ $isCurrentVersion ? 'sr-only' : 'text-sm text-slate-500 dark:text-slate-400' }}">{{ $isCurrentVersion ? 'Current submission' : 'Historical version' }}</span>
        </div>
        <p class="mt-2 text-base leading-7 text-slate-600 dark:text-slate-300">Submitted by {{ $report->submitter->name }} · {{ $submittedDate?->format('M j, Y') }}</p>
    </div>
    <div data-quarter-report-actions class="quarter-report-actions">
        <button type="button" aria-label="Preview submitted PDF" title="Preview PDF" aria-haspopup="dialog" @click="$dispatch('open-modal', '{{ $modalName }}')" class="inline-flex min-h-12 items-center justify-center gap-2 rounded-lg bg-red-700 px-4 py-3 text-base font-semibold text-white hover:bg-red-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700 dark:bg-red-600 dark:hover:bg-red-500">
            <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.5-7.5 9.75-7.5 9.75 7.5 9.75 7.5-3.5 7.5-9.75 7.5S2.25 12 2.25 12Z" /><circle cx="12" cy="12" r="3" /></svg>
            Preview PDF
        </button>
        <a href="{{ $downloadUrl }}" class="quarter-report-download" aria-label="Download {{ $isMonitoringTool ? 'monitoring tool' : 'progress report' }} PDF" title="Download PDF">
            <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m-4-4 4 4 4-4M4 16v4h16v-4" /></svg>
            <span>Download<span class="hidden sm:inline"> PDF</span></span>
        </a>
    </div>
    @if ($closable)
        <button type="button" data-close-quarter-report @click="$dispatch('close-quarter-report')" aria-label="Close report" title="Close report" class="quarter-report-close">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="m6 6 12 12M6 18 18 6" /></svg>
        </button>
    @endif
</header>

<x-submitted-report-preview :name="$modalName" :title="$title" :description="$report->reporting_period_label" :preview-url="$previewUrl" :download-url="$downloadUrl" :viewer-label="$title.' PDF'" data-quarter-pdf-preview-modal :data-report-kind="$isMonitoringTool ? 'monitoring' : 'progress'" />
