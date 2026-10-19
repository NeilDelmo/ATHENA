@props(['topic', 'report', 'closable' => false])

@php
    $isCurrentVersion = $report->nextVersion === null;
    $canManageCurrentReport = Auth::user()->isUsingWorkspace('research_head')
        && ! $topic->isCompletedProject()
        && $report->isAvailableToResearchHead()
        && $report->review_status === 'pending'
        && $isCurrentVersion;
    $canCorrectReport = Auth::user()->isUsingWorkspace('faculty_researcher')
        && $topic->isAccessibleTo(Auth::user())
        && ! $topic->isCompletedProject()
        && $isCurrentVersion
        && $report->review_status === 'revision_requested';
@endphp

<div class="quarter-report-document" data-monitoring-report-detail data-live-report-key="{{ \App\Services\TopicLiveStatus::reportKey($report) }}" data-live-report-scope="{{ \App\Services\TopicLiveStatus::reportScope($topic, $report) }}">
    <x-quarter-report-header :report="$report" :title="$report->quarter_label.' Monitoring Tool · '.$report->version_label" :is-current-version="$isCurrentVersion" :closable="$closable" />
    <div class="quarter-report-layout">
        <div class="min-w-0 space-y-5">
            <section class="max-w-lg" aria-label="Recorded project progress">
                <div class="flex items-baseline justify-between gap-3 text-base"><h4 class="font-medium text-slate-900 dark:text-white">Recorded project progress</h4><span class="font-semibold tabular-nums text-slate-900 dark:text-white">{{ $report->progress_percentage }}%</span></div>
                <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700" role="progressbar" aria-label="{{ $report->quarter_label }} reported progress" aria-valuenow="{{ $report->progress_percentage }}" aria-valuemin="0" aria-valuemax="100"><div class="h-full rounded-full bg-red-700 dark:bg-red-400" style="width: {{ $report->progress_percentage }}%"></div></div>
                <p class="mt-2 text-base leading-6 text-slate-500 dark:text-slate-400">As of {{ $report->reporting_date->format('M j, Y') }} · Saved in this report</p>
            </section>

            @foreach ($report->work_plan ?? [] as $activity)
                @if (count($activity['evidence'] ?? []) > 0)
                    <section class="space-y-2 border-t border-slate-200 pt-4 dark:border-slate-700">
                        <h4 class="text-base font-semibold text-slate-800 dark:text-slate-100">Evidence · {{ $activity['activity'] }}</h4>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($activity['evidence'] as $file)
                                <a href="{{ route('project-progress.evidence', ['topic' => $topic, 'evidence' => $file['id']]) }}" class="inline-flex min-h-10 items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-base font-medium text-slate-700 hover:border-red-300 hover:text-red-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">{{ $file['name'] }}</a>
                            @endforeach
                        </div>
                    </section>
                @endif
            @endforeach

            @if ($report->attachment_path || $canCorrectReport)
                <div class="flex flex-wrap gap-2 border-t border-gray-200 pt-4 dark:border-slate-700">
                    @if ($report->attachment_path)<a href="{{ route('project-progress.download', $report) }}" class="inline-flex min-h-9 items-center justify-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-base font-bold text-gray-700 transition hover:border-red-200 hover:bg-red-50 hover:text-red-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">Download attachment</a>@endif
                    @if ($canCorrectReport)<a href="{{ route('project-progress.create', ['topic' => $topic, 'revise_monitoring_report' => $report->id]) }}" class="inline-flex min-h-9 items-center justify-center rounded-lg bg-red-700 px-3 py-2 text-base font-bold text-white transition hover:bg-red-800">Correct {{ $report->quarter_label }} submission</a>@endif
                </div>
            @endif

            @if ($report->research_head_remarks && ! $canManageCurrentReport)
                <section class="border-t border-slate-200 pt-3 dark:border-slate-700"><h4 class="text-base font-medium text-slate-950 dark:text-white">{{ $report->review_stage === 'secretary' && $report->review_status === 'pending' ? 'Research Head concerns · Research Office Secretary to resolve' : 'Research Head remarks' }} @if ($report->reviewer)<span class="ml-2 font-normal text-slate-500 dark:text-slate-400">{{ $report->reviewer->name }}</span>@endif</h4><p class="mt-1 max-w-[70ch] whitespace-pre-line break-words text-base leading-6 text-slate-600 dark:text-slate-300">{{ $report->research_head_remarks }}</p></section>
            @endif

            <x-implementation-report-secretary-review :topic="$topic" :report="$report" />

            <x-implementation-report-head-review :topic="$topic" :report="$report" />
        </div>
        <x-submitted-report-document name="monitoring-paper-{{ $report->id }}" modal-name="monitoring-pdf-{{ $report->id }}" title="Submitted monitoring tool paper" :preview-url="route('project-progress.monitoring-tool.view', $report)" :download-url="route('project-progress.monitoring-tool', $report)" :viewer-label="$report->quarter_label.' monitoring tool paper'" />
    </div>
</div>
