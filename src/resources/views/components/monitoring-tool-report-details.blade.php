@props(['topic', 'report'])

@php
    $isCurrentVersion = $report->nextVersion === null;
    $reviewStatusClass = match ($report->review_status) {
        'reviewed' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-200',
        'revision_requested' => 'bg-red-50 text-red-700 dark:bg-red-950/50 dark:text-red-200',
        default => 'bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-200',
    };
    $canManageCurrentReport = Auth::user()->isUsingWorkspace('research_head') && ! $topic->isCompletedProject() && $isCurrentVersion;
@endphp

<div class="space-y-5">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <p class="text-base font-black text-gray-950 dark:text-white">{{ $report->quarter_label }} Monitoring Tool · {{ $report->version_label }}</p>
                <span class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase {{ $isCurrentVersion ? 'bg-red-50 text-red-700 dark:bg-red-950/50 dark:text-red-200' : 'bg-gray-100 text-gray-500 dark:bg-slate-800 dark:text-slate-300' }}">{{ $isCurrentVersion ? 'Current submission' : 'Historical version' }}</span>
            </div>
            <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-slate-400">{{ $report->reporting_period_label }} · Submitted {{ $report->submitted_at?->format('M d, Y g:i A') ?? $report->created_at->format('M d, Y g:i A') }} by {{ $report->submitter->name }}</p>
        </div>
        <div class="flex shrink-0 flex-wrap items-center gap-2">
            <span class="rounded-full bg-gray-100 px-2.5 py-1.5 text-[10px] font-black uppercase text-gray-700 dark:bg-slate-800 dark:text-slate-200">{{ $report->progress_percentage }}% complete</span>
            <span class="rounded-full px-2.5 py-1.5 text-[10px] font-black uppercase {{ $reviewStatusClass }}">{{ $report->review_status_label }}</span>
            <a href="{{ route('project-progress.monitoring-tool', $report) }}" class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-600 transition hover:border-red-200 hover:bg-red-50 hover:text-red-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:border-red-900 dark:hover:bg-red-950/30 dark:hover:text-red-300" aria-label="Download monitoring tool" title="Download monitoring tool">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0 4-4m-4 4-4-4M4.5 15.75v2.625A1.125 1.125 0 0 0 5.625 19.5h12.75a1.125 1.125 0 0 0 1.125-1.125V15.75" /></svg>
            </a>
        </div>
    </div>

    <div class="h-3 overflow-hidden rounded-full bg-red-100 ring-1 ring-inset ring-red-200 dark:bg-red-950/50 dark:ring-red-900" aria-label="{{ $report->progress_percentage }} percent complete"><div class="h-full rounded-full bg-red-600 bg-gradient-to-r from-red-300 via-red-500 to-red-700 shadow-[inset_0_1px_0_rgba(255,255,255,0.35)] dark:from-red-800 dark:via-red-600 dark:to-red-400" style="width: {{ $report->progress_percentage }}%"></div></div>

    <div class="grid gap-3 sm:grid-cols-2">
        <section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-800/60"><p class="text-[10px] font-black uppercase tracking-wider text-gray-700 dark:text-slate-200">Accomplishments</p><p class="mt-2 whitespace-pre-line text-sm leading-6 text-gray-700 dark:text-slate-300">{{ $report->accomplishments }}</p></section>
        <section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-800/60"><p class="text-[10px] font-black uppercase tracking-wider text-gray-700 dark:text-slate-200">Issues or delays</p><p class="mt-2 whitespace-pre-line text-sm leading-6 text-gray-700 dark:text-slate-300">{{ $report->issues ?: 'None reported.' }}</p></section>
    </div>

    @if ($report->attachment_path || (! Auth::user()->isUsingWorkspace('research_head') && $isCurrentVersion && $report->review_status === 'revision_requested'))
        <div class="flex flex-wrap gap-2 border-t border-gray-200 pt-4 dark:border-slate-700">
            @if ($report->attachment_path)<a href="{{ route('project-progress.download', $report) }}" class="inline-flex min-h-9 items-center justify-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-bold text-gray-700 transition hover:border-red-200 hover:bg-red-50 hover:text-red-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">Download attachment</a>@endif
            @if (! Auth::user()->isUsingWorkspace('research_head') && $isCurrentVersion && $report->review_status === 'revision_requested')<a href="{{ route('project-progress.create', ['topic' => $topic, 'revise_monitoring_report' => $report->id]) }}" class="inline-flex min-h-9 items-center justify-center rounded-lg bg-red-700 px-3 py-2 text-xs font-bold text-white transition hover:bg-red-800">Correct {{ $report->quarter_label }} submission</a>@endif
        </div>
    @endif

    @if ($report->research_head_remarks && ! $canManageCurrentReport)
        <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-800/60"><p class="text-[10px] font-black uppercase tracking-wider text-gray-700 dark:text-slate-200">Research Head remarks @if ($report->reviewer) · {{ $report->reviewer->name }} @endif</p><p class="mt-2 whitespace-pre-line text-sm leading-6 text-gray-700 dark:text-slate-300">{{ $report->research_head_remarks }}</p></div>
    @endif

    @if ($canManageCurrentReport)
        <form method="POST" action="{{ route('research_head.progress-reports.review', $report) }}" x-data="{ remarksExpanded: false, remarksText: @js(old('research_head_remarks', $report->research_head_remarks)) }">
            @csrf
            @method('PATCH')
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end">
                <label for="research-head-remarks-{{ $report->id }}" class="block min-w-0 flex-1"><span class="text-[10px] font-black uppercase tracking-wider text-gray-700 dark:text-slate-200">Research Head remarks</span><textarea id="research-head-remarks-{{ $report->id }}" name="research_head_remarks" x-model="remarksText" x-bind:rows="remarksExpanded ? Math.max(3, Math.ceil(remarksText.length / 75)) : 1" maxlength="5000" class="mt-2 block min-h-11 w-full resize-none rounded-xl border-gray-200 bg-white py-2.5 text-sm leading-6 text-gray-700 placeholder:text-gray-400 focus:border-red-600 focus:ring-red-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200" placeholder="Add review notes or correction instructions">{{ old('research_head_remarks', $report->research_head_remarks) }}</textarea><button type="button" x-show="remarksText.length > 120" x-on:click="remarksExpanded = ! remarksExpanded" class="mt-1 text-xs font-bold text-red-700 hover:text-red-800 dark:text-red-300" x-text="remarksExpanded ? 'Show less' : 'See more…'"></button></label>
                <div class="flex shrink-0 gap-2"><select id="review-status-{{ $report->id }}" name="review_status" class="h-11 min-w-0 flex-1 rounded-xl border-gray-200 bg-white text-xs font-black text-gray-700 focus:border-red-600 focus:ring-red-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 sm:w-44 sm:flex-none"><option value="reviewed" @selected($report->review_status === 'reviewed')>Mark reviewed</option><option value="revision_requested" @selected($report->review_status === 'revision_requested')>Request corrections</option></select><button class="inline-flex h-11 flex-1 items-center justify-center rounded-xl bg-red-700 px-4 text-xs font-black text-white transition hover:bg-red-800 sm:flex-none">Save review</button></div>
            </div>
            @error('research_head_remarks')<p class="mt-2 text-xs font-semibold text-red-700 dark:text-red-300">{{ $message }}</p>@enderror
        </form>
    @endif
</div>
