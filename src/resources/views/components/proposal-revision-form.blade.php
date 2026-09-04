@props(['topic', 'pendingFileRevisions', 'stagedRevisionFiles', 'displayProjectCost'])

@php
    $revisionErrors = $errors->getBag('resubmission');
    $revisionGroups = $pendingFileRevisions->groupBy('document_type');
    $latestRevisionReview = $topic->reviews->where('decision', 'revision_requested')->sortByDesc('id')->first();
    $metadataHasErrors = $revisionErrors->hasAny(['title', 'description', 'estimated_budget', 'estimated_duration_months']);
@endphp

<form
    id="submit-revision"
    action="{{ route('faculty.topics.resubmit', $topic) }}"
    method="POST"
    enctype="multipart/form-data"
    novalidate
    aria-labelledby="faculty-revision-heading"
    data-faculty-revision-required
    data-revision-workspace="{{ $topic->id }}"
    data-confirm-title="Submit this revision to the Research Head?"
    data-confirm-text="Your updated details and replacement files will be saved as a new version. Unchanged files carry forward automatically."
    data-confirm-button="Submit revision"
    data-confirm-icon="question"
    @invalid.capture="$event.target.closest('details')?.setAttribute('open', '')"
    class="space-y-5"
>
    @csrf
    @method('PATCH')
    <input type="hidden" name="redirect_to" value="topic">
    <input type="hidden" name="topic_tab" value="review">
    <input type="hidden" name="revision_draft_id" value="{{ $topic->revisionDraft?->id }}">

    <header class="space-y-2">
        <h3 id="faculty-revision-heading" class="text-xl font-black text-gray-950 dark:text-white">Revisions requested</h3>
        <p class="text-sm leading-6 text-gray-600 dark:text-slate-300">
            @if ($pendingFileRevisions->isNotEmpty())
                Update {{ $pendingFileRevisions->count() }} {{ Str::plural('file', $pendingFileRevisions->count()) }} and submit {{ $pendingFileRevisions->count() === 1 ? 'it' : 'them' }} for another review.
            @else
                Review the feedback and submit your updated proposal.
            @endif
            Open a document to see its PDF and editor side by side. Address every requested file by modifying it, uploading a replacement, or explaining why the comment needs no file change. Files that were not requested carry forward automatically.
        </p>
        @if ($latestRevisionReview?->comment)
            <p class="whitespace-pre-line border-l-2 border-red-300 pl-3 text-sm leading-6 text-gray-700 dark:border-red-800 dark:text-slate-200">{{ $latestRevisionReview->comment }}</p>
        @endif
    </header>

    <div class="space-y-4" data-requested-revision-files>
        @foreach ($revisionGroups as $documentType => $fileRevisions)
            <x-proposal-revision-document :topic="$topic" :document-type="$documentType" :file-revisions="$fileRevisions" :staged-file="$stagedRevisionFiles->get($documentType)" :required="true" />
        @endforeach
    </div>

    <details data-revision-proposal-details @if ($metadataHasErrors) open @endif class="rounded-xl border border-gray-200 bg-white dark:border-slate-800 dark:bg-slate-900">
        <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-gray-700 hover:text-red-700 focus-visible:outline-red-700 dark:text-slate-200">Proposal details</summary>
        <div class="grid gap-4 border-t border-gray-100 p-4 dark:border-slate-800 md:grid-cols-2">
            <label class="block text-sm font-semibold text-gray-700 dark:text-slate-200">Project title<input name="title" value="{{ old('title', $topic->title) }}" required class="mt-2 block w-full rounded-xl border-gray-300 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white"></label>
            <label class="block text-sm font-semibold text-gray-700 dark:text-slate-200">Total project cost<input name="estimated_budget" type="number" min="0" max="{{ $topic->researchCall?->budgetCeiling() ?? \App\Models\ResearchCall::MAXIMUM_BUDGET }}" step="0.01" value="{{ old('estimated_budget', $displayProjectCost) }}" required class="mt-2 block w-full rounded-xl border-gray-300 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white"></label>
            <label class="block text-sm font-semibold text-gray-700 dark:text-slate-200 md:col-span-2">Description<textarea name="description" rows="3" class="mt-2 block w-full rounded-xl border-gray-300 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">{{ old('description', $topic->description) }}</textarea></label>
            <label class="block text-sm font-semibold text-gray-700 dark:text-slate-200">Duration in months<input name="estimated_duration_months" type="number" min="1" max="120" value="{{ old('estimated_duration_months', $topic->estimated_duration_months) }}" required class="mt-2 block w-full rounded-xl border-gray-300 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white"></label>
        </div>
    </details>

    <div id="review-and-submit" class="space-y-4 border-t border-gray-200 pt-5 dark:border-slate-800">
        <label class="block text-sm font-semibold text-gray-700 dark:text-slate-200">
            Summary of changes <span class="font-normal text-gray-500 dark:text-slate-400">(optional)</span>
            <textarea name="change_summary" rows="2" maxlength="2000" placeholder="Briefly explain what you updated" class="mt-2 block w-full rounded-xl border-gray-300 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">{{ old('change_summary') }}</textarea>
        </label>
        <div data-revision-submit-error role="alert" hidden class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">
            <p class="font-bold">Revision not sent</p>
            <p data-revision-submit-error-message class="mt-1"></p>
        </div>
        <div class="flex justify-end">
            <button type="submit" data-revision-submit-button class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-red-700 px-5 py-3 text-sm font-bold text-white transition hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-red-700 focus:ring-offset-2 disabled:pointer-events-none disabled:cursor-wait disabled:bg-gray-400 disabled:text-white disabled:opacity-100 dark:disabled:bg-slate-700 dark:disabled:text-slate-300 dark:focus:ring-offset-slate-900 sm:w-auto">
                <span data-revision-submit-button-spinner hidden class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white" aria-hidden="true"></span>
                <span data-revision-submit-button-label>Submit revision</span>
            </button>
        </div>
    </div>

    <div data-revision-submit-overlay role="status" aria-live="assertive" aria-hidden="true" aria-label="Revision submission progress" tabindex="-1" class="fixed inset-0 z-[70] hidden items-center justify-center bg-slate-950/70 p-4 backdrop-blur-sm">
        <div class="w-full max-w-md rounded-2xl border border-white/20 bg-white p-6 text-center shadow-2xl dark:border-slate-700 dark:bg-slate-900">
            <span class="mx-auto block h-10 w-10 animate-spin rounded-full border-4 border-red-100 border-t-red-700 dark:border-slate-700 dark:border-t-red-400" aria-hidden="true"></span>
            <p data-revision-submit-title class="mt-4 text-lg font-black text-gray-950 dark:text-white">Preparing your revision</p>
            <p data-revision-submit-status class="mt-2 text-sm leading-6 text-gray-600 dark:text-slate-300">Saving your edits and generating the requested PDFs…</p>
            <p class="mt-3 text-xs font-semibold text-gray-500 dark:text-slate-400">This should finish shortly. If a step takes too long, the submission will stop and let you try again.</p>
        </div>
    </div>
</form>
