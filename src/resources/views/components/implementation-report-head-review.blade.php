@props(['topic', 'report'])

@php
    $isMonitoringTool = $report instanceof \App\Models\ProjectProgressReport;
    $acceptedTerminal = ! $isMonitoringTool && $report->report_type === 'terminal' && $report->review_status === 'reviewed';
    $defaultDecision = $acceptedTerminal ? 'revision_requested' : 'reviewed';
    $canReview = Auth::user()->isUsingWorkspace('research_head')
        && ! $topic->isCompletedProject()
        && $report->isSubmitted()
        && $report->isAvailableToResearchHead()
        && ($report->review_status === 'pending' || $acceptedTerminal)
        && $report->isLatestSubmittedVersion();
    $reviewId = 'head-review-'.($isMonitoringTool ? 'monitoring-' : 'progress-').$report->id;
@endphp

@if ($canReview)
    <section class="rounded-xl border border-red-100 bg-red-50/50 p-5 dark:border-red-900 dark:bg-red-950/20" aria-labelledby="{{ $reviewId }}-heading" data-head-report-review>
        <div class="flex items-center gap-3 text-red-800 dark:text-red-200">
            <svg class="h-6 w-6 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H5v16h14V5h-4M9 3h6v4H9V3Zm-1 12 3 3 5-6"/></svg>
            <h4 id="{{ $reviewId }}-heading" class="text-lg font-semibold">Research Head decision</h4>
        </div>
        <p class="mt-2 text-base leading-7 text-slate-600 dark:text-slate-300">{{ $report->forwarded_at ? 'The Research Office Secretary has checked and forwarded this report.' : 'Preview the submitted report before recording your decision.' }}</p>
        <form method="POST" action="{{ route($isMonitoringTool ? 'research_head.progress-reports.review' : 'research_head.narrative-progress-reports.review', $report) }}" class="mt-5 space-y-5" x-data="{ decision: @js(old('review_status', $defaultDecision)) }" @if (! $isMonitoringTool) data-narrative-report-review @endif>
            @csrf
            @method('PATCH')
            <fieldset class="space-y-3">
                <legend class="sr-only">Review decision</legend>
                @unless ($acceptedTerminal)
                <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
                    <input type="radio" name="review_status" value="reviewed" x-model="decision" @checked(old('review_status', 'reviewed') === 'reviewed') class="mt-1 h-5 w-5 shrink-0 border-slate-300 text-red-700 focus:ring-red-600">
                    <span><span class="block text-base font-semibold text-slate-950 dark:text-white">Accept report</span><span class="mt-1 block text-base leading-6 text-slate-600 dark:text-slate-300">The report is satisfactory. Complete its review.</span></span>
                </label>
                @endunless
                <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
                    <input type="radio" name="review_status" value="revision_requested" x-model="decision" @checked(old('review_status', $defaultDecision) === 'revision_requested') class="mt-1 h-5 w-5 shrink-0 border-slate-300 text-red-700 focus:ring-red-600">
                    <span><span class="block text-base font-semibold text-slate-950 dark:text-white">Send concerns to Research Office Secretary</span><span class="mt-1 block text-base leading-6 text-slate-600 dark:text-slate-300">The secretary coordinates any corrections with the Faculty Researcher.</span></span>
                </label>
            </fieldset>
            @if ($acceptedTerminal && $report->hasSignedCopy())
                <p class="text-sm text-slate-600 dark:text-slate-300">Returning concerns removes the signed copy. A new fully signed PDF is required after the corrected report is accepted.</p>
            @endif
            <label for="{{ $reviewId }}-notes" class="block text-base font-semibold text-slate-800 dark:text-slate-100">
                <span x-text="decision === 'revision_requested' ? 'Concerns for the secretary' : 'Review notes (optional)'">Review notes (optional)</span>
                <textarea id="{{ $reviewId }}-notes" name="research_head_remarks" rows="3" maxlength="5000" x-bind:required="decision === 'revision_requested'" class="mt-2 block w-full rounded-lg border-slate-300 bg-white text-base font-normal leading-7 focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-900 dark:text-white" placeholder="Explain your decision or the concern that needs attention.">{{ old('research_head_remarks', $report->research_head_remarks) }}</textarea>
            </label>
            @error('research_head_remarks')<p role="alert" class="text-base text-red-700 dark:text-red-300">{{ $message }}</p>@enderror
            @error('review_status')<p role="alert" class="text-base text-red-700 dark:text-red-300">{{ $message }}</p>@enderror
            <button type="submit" class="inline-flex min-h-12 items-center justify-center gap-2 rounded-lg bg-red-700 px-5 py-3 text-base font-semibold text-white hover:bg-red-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6"/></svg>
                <span x-text="decision === 'revision_requested' ? 'Return concerns to secretary' : 'Accept report'">Accept report</span>
            </button>
        </form>
    </section>
@endif
