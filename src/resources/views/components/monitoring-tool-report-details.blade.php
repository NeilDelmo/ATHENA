@props(['topic', 'report', 'closable' => false])

@php
    $isCurrentVersion = $report->nextVersion === null;
    $canManageCurrentReport = Auth::user()->isUsingWorkspace('research_head') && ! $topic->isCompletedProject() && $isCurrentVersion;
@endphp

<div class="quarter-report-document" data-monitoring-report-detail>
    <x-quarter-report-header :report="$report" :title="$report->quarter_label.' Monitoring Tool · '.$report->version_label" :is-current-version="$isCurrentVersion" :closable="$closable" />
    <div class="quarter-report-layout">
        <div class="min-w-0 space-y-3">
            <section class="max-w-lg" aria-label="Quarterly progress">
                <div class="flex items-baseline justify-between gap-3 text-sm"><h4 class="font-medium text-slate-900 dark:text-white">Overall project progress</h4><span class="font-semibold tabular-nums text-slate-900 dark:text-white">{{ $report->progress_percentage }}%</span></div>
                <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700" role="progressbar" aria-label="{{ $report->quarter_label }} reported progress" aria-valuenow="{{ $report->progress_percentage }}" aria-valuemin="0" aria-valuemax="100"><div class="h-full rounded-full bg-red-700 dark:bg-red-400" style="width: {{ $report->progress_percentage }}%"></div></div>
            </section>

            @foreach ($report->work_plan ?? [] as $activity)
                @if (count($activity['evidence'] ?? []) > 0)
                    <section class="space-y-2 border-t border-slate-200 pt-4 dark:border-slate-700">
                        <h4 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Evidence · {{ $activity['activity'] }}</h4>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($activity['evidence'] as $file)
                                <a href="{{ route('project-progress.evidence', ['topic' => $topic, 'evidence' => $file['id']]) }}" class="inline-flex min-h-10 items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:border-red-300 hover:text-red-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">{{ $file['name'] }}</a>
                            @endforeach
                        </div>
                    </section>
                @endif
            @endforeach

            @if ($report->attachment_path || (! $topic->isCompletedProject() && ! Auth::user()->isUsingWorkspace('research_head') && $isCurrentVersion && $report->review_status === 'revision_requested'))
                <div class="flex flex-wrap gap-2 border-t border-gray-200 pt-4 dark:border-slate-700">
                    @if ($report->attachment_path)<a href="{{ route('project-progress.download', $report) }}" class="inline-flex min-h-9 items-center justify-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-bold text-gray-700 transition hover:border-red-200 hover:bg-red-50 hover:text-red-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">Download attachment</a>@endif
                    @if (! $topic->isCompletedProject() && ! Auth::user()->isUsingWorkspace('research_head') && $isCurrentVersion && $report->review_status === 'revision_requested')<a href="{{ route('project-progress.create', ['topic' => $topic, 'revise_monitoring_report' => $report->id]) }}" class="inline-flex min-h-9 items-center justify-center rounded-lg bg-red-700 px-3 py-2 text-xs font-bold text-white transition hover:bg-red-800">Correct {{ $report->quarter_label }} submission</a>@endif
                </div>
            @endif

            @if ($report->research_head_remarks && ! $canManageCurrentReport)
                <section class="border-t border-slate-200 pt-3 dark:border-slate-700"><h4 class="text-sm font-medium text-slate-950 dark:text-white">Research Head remarks @if ($report->reviewer)<span class="ml-2 font-normal text-slate-500 dark:text-slate-400">{{ $report->reviewer->name }}</span>@endif</h4><p class="mt-1 max-w-[70ch] whitespace-pre-line break-words text-sm leading-6 text-slate-600 dark:text-slate-300">{{ $report->research_head_remarks }}</p></section>
            @endif

            @if ($canManageCurrentReport)
                <form method="POST" action="{{ route('research_head.progress-reports.review', $report) }}" x-data="{ remarksExpanded: false, remarksText: @js(old('research_head_remarks', $report->research_head_remarks) ?? '') }">
                    @csrf
                    @method('PATCH')
                    <div class="flex flex-col gap-3">
                        <label for="research-head-remarks-{{ $report->id }}" class="block min-w-0 flex-1"><span class="text-xs font-semibold tracking-wider text-gray-700 dark:text-slate-200">Research Head remarks</span><textarea id="research-head-remarks-{{ $report->id }}" name="research_head_remarks" x-model="remarksText" x-bind:rows="remarksExpanded ? Math.max(3, Math.ceil(remarksText.length / 75)) : 1" maxlength="5000" class="mt-2 block min-h-11 w-full resize-none rounded-xl border-gray-200 bg-white py-2.5 text-sm leading-6 text-gray-700 placeholder:text-gray-400 focus:border-red-600 focus:ring-red-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200" placeholder="Add review notes or correction instructions">{{ old('research_head_remarks', $report->research_head_remarks) }}</textarea><button type="button" x-show="remarksText.length > 120" x-on:click="remarksExpanded = ! remarksExpanded" class="mt-1 text-xs font-bold text-red-700 hover:text-red-800 dark:text-red-300" x-text="remarksExpanded ? 'Show less' : 'See more…'"></button></label>
                        <div class="flex shrink-0 gap-2"><select id="review-status-{{ $report->id }}" name="review_status" class="h-11 min-w-0 flex-1 rounded-xl border-gray-200 bg-white text-xs font-semibold text-gray-700 focus:border-red-600 focus:ring-red-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 sm:w-44 sm:flex-none"><option value="reviewed" @selected($report->review_status === 'reviewed')>Mark reviewed</option><option value="revision_requested" @selected($report->review_status === 'revision_requested')>Request report corrections</option></select><button class="inline-flex h-11 flex-1 items-center justify-center rounded-xl bg-red-700 px-4 text-xs font-semibold text-white transition hover:bg-red-800 sm:flex-none">Save review</button></div>
                    </div>
                    @error('research_head_remarks')<p class="mt-2 text-xs font-semibold text-red-700 dark:text-red-300">{{ $message }}</p>@enderror
                </form>
            @endif
        </div>
        <x-submitted-report-document name="monitoring-paper-{{ $report->id }}" modal-name="monitoring-pdf-{{ $report->id }}" title="Submitted monitoring tool paper" :preview-url="route('project-progress.monitoring-tool.view', $report)" :download-url="route('project-progress.monitoring-tool', $report)" :viewer-label="$report->quarter_label.' monitoring tool paper'" />
    </div>
</div>
