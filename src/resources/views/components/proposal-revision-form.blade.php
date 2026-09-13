@props(['topic', 'pendingFileRevisions', 'stagedRevisionFiles', 'displayProjectCost', 'commentResponseRows' => []])

@php
    $revisionErrors = $errors->getBag('resubmission');
    $revisionGroups = $pendingFileRevisions->groupBy('document_type');
    $latestRevisionReview = $topic->reviews->where('decision', 'revision_requested')->sortByDesc('id')->first();
    $metadataHasErrors = $revisionErrors->hasAny(['title', 'description', 'estimated_budget', 'estimated_duration_months']);
    $commentResponseGroups = collect($commentResponseRows)->groupBy('form_source');
    $commentResponseLabels = [
        \App\Services\CommentResponseFeedback::FORM_RESEARCH_HEAD => 'Research Head Comment-Response Form',
        \App\Services\CommentResponseFeedback::FORM_CO_EVALUATOR => 'Co-evaluator Comment-Response Form',
    ];
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
    @invalid.capture="if ($event.target.closest('[data-revision-proposal-details-fields]')) window.dispatchEvent(new CustomEvent('open-revision-proposal-details'))"
    class="space-y-7"
>
    @csrf
    @method('PATCH')
    <input type="hidden" name="redirect_to" value="topic">
    <input type="hidden" name="topic_tab" value="review">
    <input type="hidden" name="revision_draft_id" value="{{ $topic->revisionDraft?->id }}">

    <header class="relative overflow-hidden rounded-2xl border border-red-200 bg-gradient-to-br from-red-50 via-white to-white p-5 shadow-sm dark:border-red-950 dark:from-red-950/40 dark:via-slate-950 dark:to-slate-950 sm:p-7">
        <div class="absolute inset-y-0 left-0 w-1.5 bg-red-700" aria-hidden="true"></div>
        <div class="max-w-4xl">
            <h3 id="faculty-revision-heading" class="font-serif text-2xl font-bold tracking-tight text-gray-950 dark:text-white sm:text-3xl">Revisions requested</h3>
            <p class="mt-3 text-base leading-7 text-gray-700 dark:text-slate-200">
            @if ($pendingFileRevisions->isNotEmpty())
                Update {{ $pendingFileRevisions->count() }} {{ Str::plural('file', $pendingFileRevisions->count()) }} and submit {{ $pendingFileRevisions->count() === 1 ? 'it' : 'them' }} for another review.
            @else
                Review the feedback and submit your updated proposal.
            @endif
            Open a document to see its PDF and editor side by side. Address every requested file by modifying it, uploading a replacement, or explaining why the comment needs no file change. Files that were not requested carry forward automatically.
            </p>
            @if ($latestRevisionReview?->comment)
                <blockquote class="mt-4 whitespace-pre-line border-l-4 border-red-300 pl-4 text-base leading-7 text-gray-800 dark:border-red-800 dark:text-slate-100">{{ $latestRevisionReview->comment }}</blockquote>
            @endif
        </div>
    </header>

    @can('generateCommentResponseForm', $topic)
        <div class="grid gap-4 lg:grid-cols-2" aria-label="Comment-Response Form downloads">
            @foreach ($commentResponseGroups as $source => $sourceRows)
                @php($isCoEvaluatorForm = $source === \App\Services\CommentResponseFeedback::FORM_CO_EVALUATOR)
                <section
                    data-comment-response-source="{{ $source }}"
                    class="flex flex-col justify-between gap-5 overflow-hidden rounded-2xl border {{ $isCoEvaluatorForm ? 'border-blue-200 bg-blue-50/60 dark:border-blue-900 dark:bg-blue-950/20' : 'border-red-200 bg-red-50/60 dark:border-red-900 dark:bg-red-950/20' }} p-5"
                >
                    <div class="flex items-start gap-4">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl {{ $isCoEvaluatorForm ? 'bg-blue-700 text-white dark:bg-blue-500 dark:text-blue-950' : 'bg-red-700 text-white' }}" aria-hidden="true">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3.75h7.5l3 3v13.5H6.75V3.75Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M14.25 3.75v3h3M9.5 11h5M9.5 14.5h5" /></svg>
                        </span>
                        <div>
                            <h4 class="font-serif text-lg font-bold text-gray-950 dark:text-white">{{ $commentResponseLabels[$source] ?? 'Comment-Response Form' }}</h4>
                            <p class="mt-2 text-base leading-7 text-gray-700 dark:text-slate-200">
                            {{ $source === \App\Services\CommentResponseFeedback::FORM_CO_EVALUATOR
                                ? 'A separate paper containing only the Narrative Evaluation extracted from the completed Initial Screening Form.'
                                : 'A separate paper containing the Research Head’s review comments and your responses.' }}
                            </p>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                        <a href="{{ route('faculty.topics.comment-response-form.preview', ['topic' => $topic, 'source' => $source, 'review' => $latestRevisionReview?->id]) }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-gray-300 bg-white px-3 py-2.5 text-sm font-bold text-gray-800 transition hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-700 dark:border-slate-700 dark:bg-slate-900 dark:text-white">Preview</a>
                        <a href="{{ route('faculty.topics.comment-response-form.pdf', ['topic' => $topic, 'source' => $source, 'review' => $latestRevisionReview?->id]) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-red-700 px-3 py-2.5 text-sm font-bold text-white transition hover:bg-red-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-700 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-950">Download PDF</a>
                        <a href="{{ route('faculty.topics.comment-response-form.download', ['topic' => $topic, 'source' => $source, 'review' => $latestRevisionReview?->id]) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-gray-300 bg-white px-3 py-2.5 text-sm font-bold text-gray-800 transition hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-700 dark:border-slate-700 dark:bg-slate-900 dark:text-white">Word file</a>
                    </div>
                </section>
            @endforeach
        </div>
    @endcan

    @if ($commentResponseRows !== [])
        <input type="hidden" name="feedback_review_id" value="{{ $latestRevisionReview?->id }}">
        <section class="space-y-5" aria-labelledby="comment-response-heading">
            <div class="max-w-4xl">
                <h4 id="comment-response-heading" class="font-serif text-2xl font-bold tracking-tight text-gray-950 dark:text-white">Respond to the review comments</h4>
                <p class="mt-2 text-base leading-7 text-gray-700 dark:text-slate-200">Your answers are placed into the matching Comment-Response Form when you submit. Use Remarks for revised page and paragraph references, or explain when a reference does not apply.</p>
            </div>
            @foreach ($revisionErrors->get('feedback_responses*') as $feedbackErrors)
                @foreach ((array) $feedbackErrors as $feedbackError)<p class="text-sm text-red-700">{{ $feedbackError }}</p>@endforeach
            @endforeach
            @foreach ($commentResponseGroups as $source => $sourceRows)
                @php($isCoEvaluatorForm = $source === \App\Services\CommentResponseFeedback::FORM_CO_EVALUATOR)
                <section class="space-y-4 rounded-2xl border-l-4 {{ $isCoEvaluatorForm ? 'border-blue-600 bg-blue-50/50 dark:bg-blue-950/15' : 'border-red-700 bg-red-50/50 dark:bg-red-950/15' }} p-4 sm:p-5" aria-label="{{ $commentResponseLabels[$source] ?? 'Comment-Response Form' }} responses">
                    <div class="flex items-center gap-3">
                        <span class="h-3 w-3 rounded-full {{ $isCoEvaluatorForm ? 'bg-blue-600' : 'bg-red-700' }}" aria-hidden="true"></span>
                        <div>
                            <h5 class="font-serif text-xl font-bold text-gray-950 dark:text-white">{{ $commentResponseLabels[$source] ?? 'Comment-Response Form' }}</h5>
                            <p class="mt-1 text-sm leading-6 text-gray-600 dark:text-slate-300">These responses stay in this paper only.</p>
                        </div>
                    </div>
                    @foreach ($sourceRows as $item)
                        <article class="grid gap-5 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900 lg:grid-cols-[minmax(15rem,0.85fr)_minmax(0,1.4fr)] lg:p-6">
                            <div class="flex items-start gap-3">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full {{ $isCoEvaluatorForm ? 'bg-blue-700' : 'bg-red-700' }} text-sm font-bold text-white">{{ $loop->iteration }}</span>
                                <div>
                                    <p class="text-base font-bold text-gray-950 dark:text-white">{{ $item['reviewer'] }}</p>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-slate-400">{{ $item['location'] }}</p>
                                    <blockquote class="mt-3 whitespace-pre-line border-l-2 border-gray-200 pl-3 text-base leading-7 text-gray-800 dark:border-slate-700 dark:text-slate-100">{{ $item['comment'] }}</blockquote>
                                </div>
                            </div>
                            <div class="space-y-4">
                                <label class="block text-base font-semibold text-gray-800 dark:text-slate-100">Action and response<textarea name="feedback_responses[{{ $item['key'] }}][response]" rows="4" maxlength="5000" required class="mt-2 block w-full rounded-xl border-gray-300 text-base leading-7 focus:border-red-700 focus:ring-red-700 dark:border-slate-700 dark:bg-slate-950 dark:text-white">{{ old('feedback_responses.'.$item['key'].'.response', $item['response']) }}</textarea></label>
                                <label class="block text-base font-semibold text-gray-800 dark:text-slate-100">Remarks / revised page and paragraph<input name="feedback_responses[{{ $item['key'] }}][remarks]" maxlength="300" value="{{ old('feedback_responses.'.$item['key'].'.remarks', $item['remarks']) }}" class="mt-2 block min-h-11 w-full rounded-xl border-gray-300 text-base focus:border-red-700 focus:ring-red-700 dark:border-slate-700 dark:bg-slate-950 dark:text-white"></label>
                            </div>
                        </article>
                    @endforeach
                </section>
            @endforeach
        </section>
    @endif

    <div class="space-y-4" data-requested-revision-files>
        @foreach ($revisionGroups as $documentType => $fileRevisions)
            <x-proposal-revision-document :topic="$topic" :document-type="$documentType" :file-revisions="$fileRevisions" :staged-file="$stagedRevisionFiles->get($documentType)" :required="true" />
        @endforeach
    </div>

    <section
        data-revision-proposal-details
        data-initially-open="{{ $metadataHasErrors ? 'true' : 'false' }}"
        x-data="{ open: @js($metadataHasErrors) }"
        @open-revision-proposal-details.window="open = true"
        class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
        aria-labelledby="proposal-details-heading"
    >
        <button
            type="button"
            data-revision-proposal-details-button
            @click="open = !open"
            :aria-expanded="open"
            aria-controls="proposal-details-fields"
            class="flex min-h-16 w-full items-center justify-between gap-4 px-5 py-4 text-left transition hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-red-700 dark:hover:bg-slate-800 sm:px-6"
        >
            <span class="flex items-start gap-4">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-gray-100 text-gray-700 dark:bg-slate-800 dark:text-slate-200" aria-hidden="true">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 6.75h15M4.5 12h15M4.5 17.25h9" /></svg>
                </span>
                <span>
                    <span id="proposal-details-heading" class="block font-serif text-xl font-bold text-gray-950 dark:text-white">Proposal details</span>
                    <span class="mt-1 block text-base font-normal leading-6 text-gray-600 dark:text-slate-300">Edit the title, cost, description, or duration when the review requires it.</span>
                </span>
            </span>
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-600 shadow-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-300" aria-hidden="true">
                <svg :class="open ? 'rotate-180' : ''" class="h-5 w-5 transition-transform duration-200 motion-reduce:transition-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"></path></svg>
            </span>
        </button>
        <div id="proposal-details-fields" data-revision-proposal-details-fields x-show="open" x-cloak x-transition class="grid gap-5 border-t border-gray-100 p-5 dark:border-slate-800 md:grid-cols-2 sm:p-6">
            <label class="block text-base font-semibold text-gray-700 dark:text-slate-200">Project title<input name="title" value="{{ old('title', $topic->title) }}" required class="mt-2 block w-full rounded-xl border-gray-300 text-base dark:border-slate-700 dark:bg-slate-950 dark:text-white"></label>
            <label class="block text-base font-semibold text-gray-700 dark:text-slate-200">Total project cost<input name="estimated_budget" type="number" min="0" max="{{ $topic->researchCall?->budgetCeiling() ?? \App\Models\ResearchCall::MAXIMUM_BUDGET }}" step="0.01" value="{{ old('estimated_budget', $displayProjectCost) }}" required class="mt-2 block w-full rounded-xl border-gray-300 text-base dark:border-slate-700 dark:bg-slate-950 dark:text-white"></label>
            <label class="block text-base font-semibold text-gray-700 dark:text-slate-200 md:col-span-2">Description<textarea name="description" rows="3" class="mt-2 block w-full rounded-xl border-gray-300 text-base dark:border-slate-700 dark:bg-slate-950 dark:text-white">{{ old('description', $topic->description) }}</textarea></label>
            <label class="block text-base font-semibold text-gray-700 dark:text-slate-200">Duration in months<input name="estimated_duration_months" type="number" min="1" max="120" value="{{ old('estimated_duration_months', $topic->estimated_duration_months) }}" required class="mt-2 block w-full rounded-xl border-gray-300 text-base dark:border-slate-700 dark:bg-slate-950 dark:text-white"></label>
        </div>
    </section>

    <div id="review-and-submit" class="space-y-4 border-t border-gray-200 pt-5 dark:border-slate-800">
        <label class="block text-base font-semibold text-gray-800 dark:text-slate-100">
            Summary of changes <span class="font-normal text-gray-500 dark:text-slate-400">(optional)</span>
            <textarea name="change_summary" rows="3" maxlength="2000" placeholder="Briefly explain what you updated" class="mt-2 block w-full rounded-xl border-gray-300 text-base leading-7 focus:border-red-700 focus:ring-red-700 dark:border-slate-700 dark:bg-slate-950 dark:text-white">{{ old('change_summary') }}</textarea>
        </label>
        <div data-revision-submit-error role="alert" hidden class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">
            <p class="font-bold">Revision not sent</p>
            <p data-revision-submit-error-message class="mt-1"></p>
        </div>
        <div class="flex justify-end">
            <button type="submit" data-revision-submit-button class="inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-red-700 px-6 py-3 text-base font-bold text-white transition hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-red-700 focus:ring-offset-2 disabled:pointer-events-none disabled:cursor-wait disabled:bg-gray-400 disabled:text-white disabled:opacity-100 dark:disabled:bg-slate-700 dark:disabled:text-slate-300 dark:focus:ring-offset-slate-900 sm:w-auto">
                <span data-revision-submit-button-spinner hidden class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white" aria-hidden="true"></span>
                <span data-revision-submit-button-label>Submit revision</span>
            </button>
        </div>
    </div>

    <div data-revision-submit-overlay role="status" aria-live="assertive" aria-hidden="true" aria-label="Revision submission progress" tabindex="-1" class="fixed inset-0 z-[70] hidden items-center justify-center bg-slate-950/70 p-4 backdrop-blur-sm">
        <div class="w-full max-w-md rounded-2xl border border-white/20 bg-white p-6 text-center shadow-2xl dark:border-slate-700 dark:bg-slate-900">
            <span class="mx-auto block h-10 w-10 animate-spin rounded-full border-4 border-red-100 border-t-red-700 dark:border-slate-700 dark:border-t-red-400" aria-hidden="true"></span>
            <p data-revision-submit-title class="mt-4 text-lg font-black text-gray-950 dark:text-white">Preparing your revision</p>
            <p data-revision-submit-status class="mt-2 text-base leading-7 text-gray-600 dark:text-slate-300">Saving your edits and generating the requested PDFs…</p>
            <p class="mt-3 text-sm font-semibold leading-6 text-gray-500 dark:text-slate-400">This should finish shortly. If a step takes too long, the submission will stop and let you try again.</p>
        </div>
    </div>
</form>
