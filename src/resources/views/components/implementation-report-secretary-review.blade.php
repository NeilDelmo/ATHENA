@props(['topic', 'report'])

@php
    $isMonitoringTool = $report instanceof \App\Models\ProjectProgressReport;
    $reviewId = ($isMonitoringTool ? 'monitoring' : 'progress').'-secretary-review-'.$report->id;
    $canReview = $topic->canReviewImplementationReports(Auth::user())
        && $topic->isMonitoringAvailable()
        && $report->isAwaitingSecretaryReview()
        && $report->isLatestSubmittedVersion();
@endphp

@error('review')<p role="alert" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-base font-medium text-red-700 dark:border-red-900 dark:bg-red-950/30 dark:text-red-300">{{ $message }}</p>@enderror

@if ($report->secretary_remarks)
    <section class="rounded-xl border border-slate-200 bg-slate-50 p-5 dark:border-slate-700 dark:bg-slate-950/50" aria-label="Research Office Secretary remarks">
        <h4 class="flex items-center gap-2 text-base font-semibold text-slate-950 dark:text-white"><svg class="h-5 w-5 shrink-0 text-red-700 dark:text-red-300" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 3h6v4H9V3ZM9 5H5v16h14V5h-4M8 14l3 3 5-6"/></svg>Research Office Secretary {{ $report->review_status === 'revision_requested' ? 'correction instructions' : 'review notes' }}</h4>
        @if ($report->secretaryReviewer)<p class="mt-2 text-base text-slate-500 dark:text-slate-400">{{ $report->secretaryReviewer->name }} @if ($report->secretary_reviewed_at) · {{ $report->secretary_reviewed_at->format('M j, Y') }} @endif</p>@endif
        <p class="mt-3 max-w-[65ch] whitespace-pre-line break-words text-base leading-7 text-slate-600 dark:text-slate-300">{{ $report->secretary_remarks }}</p>
    </section>
@endif

@if ($canReview)
    <form method="POST" action="{{ route($isMonitoringTool ? 'research_secretary.monitoring-reports.review' : 'research_secretary.narrative-reports.review', $report) }}" class="space-y-4 border-t border-slate-200 pt-4 dark:border-slate-700" x-data="{ decision: @js(old('decision', 'forward')) }" data-secretary-report-review>
        @csrf
        @method('PATCH')
        <div>
            <h4 class="text-base font-semibold text-slate-950 dark:text-white">Research Office Secretary review</h4>
            <p class="mt-1 text-base leading-6 text-slate-600 dark:text-slate-300">Check the submitted paper and its supporting evidence. Forward a satisfactory report to the Research Head, or return it to the project leader with correction instructions.</p>
        </div>
        <label for="{{ $reviewId }}-decision" class="block text-base font-semibold text-slate-700 dark:text-slate-200">Decision
            <select id="{{ $reviewId }}-decision" name="decision" x-model="decision" required class="mt-2 block min-h-11 w-full rounded-lg border-slate-300 bg-white text-base text-slate-900 focus:border-brand focus:ring-brand dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                <option value="forward">Forward to Research Head</option>
                <option value="revision_requested">Return to project leader for corrections</option>
            </select>
        </label>
        @error('decision')<p class="text-base font-medium text-red-700 dark:text-red-300">{{ $message }}</p>@enderror
        <label for="{{ $reviewId }}-remarks" class="block text-base font-semibold text-slate-700 dark:text-slate-200">Review notes or correction instructions
            <textarea id="{{ $reviewId }}-remarks" name="secretary_remarks" rows="4" maxlength="5000" x-bind:required="decision === 'revision_requested'" placeholder="Explain any corrections needed before this report can be forwarded." class="mt-2 block w-full rounded-lg border-slate-300 bg-white text-base leading-7 text-slate-900 focus:border-brand focus:ring-brand dark:border-slate-600 dark:bg-slate-900 dark:text-white">{{ old('secretary_remarks', $report->secretary_remarks) }}</textarea>
        </label>
        @error('secretary_remarks')<p class="text-base font-medium text-red-700 dark:text-red-300">{{ $message }}</p>@enderror
        <p class="text-base text-slate-500 dark:text-slate-400">Correction instructions are required when returning a report.</p>
        <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-brand px-4 py-2 text-base font-semibold text-white hover:bg-brand-soft focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand">Save secretary review</button>
    </form>
@endif
