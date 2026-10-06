@props(['topic', 'pendingFileRevisions', 'stagedRevisionFiles', 'displayProjectCost', 'commentResponseRows' => []])

@php
    $revisionErrors = $errors->getBag('resubmission');
    $revisionGroups = $pendingFileRevisions->groupBy('document_type');
    $latestRevisionReview = $topic->reviews->where('decision', 'revision_requested')->sortByDesc('id')->first();
    $metadataHasErrors = $revisionErrors->hasAny(['title', 'description', 'estimated_budget', 'estimated_duration_months']);
    $responseDocumentTypes = [];
    foreach ($pendingFileRevisions as $fileRevision) {
        $responseDocumentTypes['file_'.$fileRevision->id] = $fileRevision->document_type;
        foreach ($fileRevision->annotations as $annotation) {
            $responseDocumentTypes['annotation_'.$annotation->id] = $fileRevision->document_type;
        }
    }
    $generalResponseRows = collect($commentResponseRows)->reject(fn ($item) => isset($responseDocumentTypes[$item['key']]));
    $commentResponseGroups = collect($commentResponseRows)->groupBy('form_source');
    $commentResponseLabels = [
        \App\Services\CommentResponseFeedback::FORM_RESEARCH_HEAD => 'Research Head Comment Response paper',
        \App\Services\CommentResponseFeedback::FORM_CO_EVALUATOR => 'Co-evaluator Comment Response paper',
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
    data-revision-start-step="{{ $metadataHasErrors ? 3 : ($revisionErrors->any() ? 2 : 1) }}"
    data-confirm-title="Submit this revision to the Research Head?"
    data-confirm-text="Edited papers will generate PDFs for the new version. Uploaded replacements and unchanged papers will be included too."
    data-confirm-button="Submit revision"
    data-confirm-icon="question"
    @invalid.capture="if ($event.target.closest('[data-revision-proposal-details-fields]')) window.dispatchEvent(new CustomEvent('open-revision-proposal-details'))"
    class="space-y-4"
>
    @csrf
    @method('PATCH')
    <input type="hidden" name="redirect_to" value="topic">
    <input type="hidden" name="topic_tab" value="review">
    <input type="hidden" name="revision_draft_id" value="{{ $topic->revisionDraft?->id }}">
    <h2 id="faculty-revision-heading" class="sr-only">Prepare the corrected project</h2>

    <nav data-revision-progress-navigation aria-label="Revision workflow" class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900 sm:p-5">
        <p data-revision-progress role="status" aria-live="polite" aria-atomic="true" class="text-sm font-semibold text-slate-900 dark:text-slate-100">Step 1 of 4 · Read feedback</p>
        <ol class="mt-4 grid grid-cols-4" aria-label="Revision steps">
            @foreach (['Read feedback', 'Revise and respond', 'Confirm details', 'Submit'] as $stepLabel)
                <li data-revision-progress-step="{{ $loop->iteration }}" data-state="{{ $loop->first ? 'current' : 'upcoming' }}" @if ($loop->first) aria-current="step" @endif class="group relative flex min-w-0 flex-col items-center gap-3 rounded-lg px-1 py-3 data-[state=current]:bg-brand-wash dark:data-[state=current]:bg-rose-950/40 md:px-3">
                    @unless ($loop->last)
                        <span data-revision-progress-connector aria-hidden="true" class="absolute left-[calc(50%+1.25rem)] right-[calc(-50%+1.25rem)] top-7 z-10 h-px bg-slate-200 group-data-[state=complete]:bg-brand dark:bg-slate-700 dark:group-data-[state=complete]:bg-rose-400"></span>
                    @endunless
                    <span data-revision-progress-mark aria-hidden="true" class="relative z-20 flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-slate-300 bg-white text-xs font-semibold text-slate-500 group-data-[state=current]:border-brand group-data-[state=current]:bg-brand group-data-[state=current]:text-white group-data-[state=complete]:border-brand group-data-[state=complete]:text-brand dark:border-slate-600 dark:bg-slate-900 dark:text-slate-400 dark:group-data-[state=current]:border-rose-400 dark:group-data-[state=current]:bg-rose-400 dark:group-data-[state=current]:text-slate-950 dark:group-data-[state=complete]:border-rose-400 dark:group-data-[state=complete]:text-rose-300">{{ $loop->iteration }}</span>
                    <span data-revision-progress-label class="sr-only text-center text-xs leading-5 text-slate-500 group-data-[state=current]:font-bold group-data-[state=current]:text-brand group-data-[state=complete]:text-slate-800 dark:text-slate-400 dark:group-data-[state=current]:text-rose-200 dark:group-data-[state=complete]:text-slate-200 md:not-sr-only md:min-h-10">{{ $stepLabel }}</span>
                    <span data-revision-progress-description class="sr-only">{{ $loop->first ? 'Current step' : 'Upcoming' }}</span>
                </li>
            @endforeach
        </ol>
    </nav>

    <section id="revision-feedback" data-revision-step="1" data-revision-step-label="Read feedback" data-revision-intro class="scroll-mt-28 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900" aria-labelledby="comment-response-heading">
        <header class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 dark:border-slate-800 sm:flex-row sm:items-start sm:justify-between sm:px-6">
            <div class="flex min-w-0 items-start gap-3">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-[#eef3f8] text-[#1f3b57] dark:bg-slate-800 dark:text-slate-200" aria-hidden="true">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 11.5a8.4 8.4 0 0 1-8.6 8.4A9 9 0 0 1 8 18.8L3 20l1.3-3.9a8.3 8.3 0 0 1-1.1-4.1A8.4 8.4 0 0 1 12 3.5a8.4 8.4 0 0 1 9 8Z" /></svg>
                </span>
                <div>
                    <h3 id="comment-response-heading" class="text-lg font-black text-slate-950 dark:text-white">1. Comment Response paper</h3>
                    <p class="mt-1 text-base leading-6 text-slate-500 dark:text-slate-400">Read the reviewer comments on the paper below. Click the paper to enlarge it.</p>
                </div>
            </div>
            <span class="inline-flex w-fit rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-500 dark:bg-slate-800 dark:text-slate-300">{{ count($commentResponseRows) }} {{ Str::plural('comment', count($commentResponseRows)) }}</span>
        </header>

        <div class="space-y-4 p-5 sm:p-6">
            @if ($commentResponseRows !== [])
                <input type="hidden" name="feedback_review_id" value="{{ $latestRevisionReview?->id }}">
                <div class="space-y-4">
                    @foreach ($commentResponseGroups as $source => $sourceRows)
                        <section data-comment-response-source="{{ $source }}" aria-label="{{ $commentResponseLabels[$source] ?? 'Comment Response paper' }}">
                            @can('generateCommentResponseForm', $topic)
                                <dialog open role="region" data-comment-response-paper class="revision-comment-response-paper" aria-labelledby="revision-comment-response-heading-{{ $topic->id }}-{{ $source }}">
                                    <header class="flex items-center justify-between gap-4 border-b border-slate-200 bg-white px-4 py-3 dark:border-slate-700 dark:bg-slate-900 sm:px-5">
                                        <h4 id="revision-comment-response-heading-{{ $topic->id }}-{{ $source }}" class="text-base font-bold text-slate-900 dark:text-white">{{ $commentResponseLabels[$source] ?? 'Comment Response paper' }}</h4>
                                        <button type="button" data-comment-response-paper-close hidden class="min-h-11 shrink-0 rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200">Minimize preview</button>
                                    </header>
                                    <div data-comment-response-paper-open role="button" tabindex="0" aria-haspopup="dialog" aria-expanded="false" aria-label="Enlarge {{ $commentResponseLabels[$source] ?? 'Comment Response paper' }}" class="revision-comment-response-paper-content">
                                        <x-comment-response-paper-preview :url="route('faculty.topics.comment-response-form.preview', ['topic' => $topic, 'source' => $source, 'review' => $latestRevisionReview?->id, 'embedded' => 1])" />
                                    </div>
                                </dialog>
                            @else
                                <p class="rounded-lg bg-slate-50 p-4 text-base text-slate-600 dark:bg-slate-800 dark:text-slate-300">The Comment Response paper is available to the project leader.</p>
                            @endcan
                        </section>
                    @endforeach
                </div>
            @else
                <p class="rounded-xl bg-slate-50 px-4 py-5 text-center text-sm text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">No written reviewer comments were recorded for this revision round.</p>
            @endif
        </div>
    </section>

    <section id="revision-papers" data-revision-step="2" data-revision-step-label="Revise and respond" hidden class="scroll-mt-28 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900" aria-labelledby="revision-papers-heading">
        <header class="flex items-start justify-between gap-4 px-5 py-4 sm:px-6">
            <div class="flex min-w-0 items-start gap-3">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-[#eef3f8] text-[#1f3b57] dark:bg-slate-800 dark:text-slate-200" aria-hidden="true">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3.75h7.5l3 3v13.5H6.75V3.75Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M14.25 3.75v3h3M9.5 11h5M9.5 14.5h5" /></svg>
                </span>
                <div>
                    <h3 id="revision-papers-heading" class="text-base font-black text-slate-950 dark:text-white">2. Revise and respond</h3>
                    <p class="mt-1 text-sm leading-6 text-slate-500 dark:text-slate-400">{{ $revisionGroups->isEmpty() ? 'No paper changes were requested.' : 'Open a paper to edit it and reply to its reviewer comments in the same workspace.' }}</p>
                </div>
            </div>
        </header>
        @foreach ($revisionErrors->get('feedback_responses*') as $feedbackErrors)
            @foreach ((array) $feedbackErrors as $feedbackError)
                <p role="alert" class="mx-5 mb-3 rounded-lg bg-red-50 p-3 text-sm text-red-700 dark:bg-red-950/30 dark:text-red-300">{{ $feedbackError }}</p>
            @endforeach
        @endforeach
        <div id="requested-papers-content" class="space-y-3 border-t border-slate-100 p-5 dark:border-slate-800 sm:p-6" data-requested-revision-files>
            @forelse ($revisionGroups as $documentType => $fileRevisions)
                <x-proposal-revision-document :topic="$topic" :document-type="$documentType" :file-revisions="$fileRevisions" :staged-file="$stagedRevisionFiles->get($documentType)" :required="true" :comment-response-rows="collect($commentResponseRows)->filter(fn ($item) => ($responseDocumentTypes[$item['key']] ?? null) === $documentType)" :document-types="$revisionGroups->keys()" />
            @empty
                <p class="rounded-xl bg-slate-50 px-4 py-5 text-center text-sm text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">Your current papers will carry forward. Reply to the feedback below, then confirm the proposal details.</p>
            @endforelse
        </div>
        @if ($generalResponseRows->isNotEmpty())
            <div class="space-y-4 border-t border-slate-100 p-5 dark:border-slate-800 sm:p-6" data-revision-general-responses>
                <h4 class="text-sm font-semibold text-slate-900 dark:text-white">Overall feedback</h4>
                @foreach ($generalResponseRows as $item)
                    <article class="space-y-3 rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ $item['location'] }}</p>
                        <blockquote class="whitespace-pre-line rounded-lg bg-slate-50 p-3 text-sm leading-6 text-slate-800 dark:bg-slate-800 dark:text-slate-100">{{ $item['comment'] }}</blockquote>
                        <x-proposal-revision-response :item="$item" :document-types="$revisionGroups->keys()" />
                    </article>
                @endforeach
            </div>
        @endif
    </section>

    <section
        id="revision-details"
        data-revision-step="3"
        data-revision-step-label="Confirm details"
        hidden
        data-revision-proposal-details
        data-initially-open="true"
        x-data="{ open: true }"
        @open-revision-proposal-details.window="open = true"
        class="scroll-mt-28 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900"
        aria-labelledby="proposal-details-heading"
    >
        <button type="button" data-revision-proposal-details-button @click="open = !open" :aria-expanded="open" aria-controls="proposal-details-fields" class="flex w-full items-start justify-between gap-4 px-5 py-4 text-left transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[#7A0019] dark:hover:bg-slate-800 sm:px-6">
            <span class="flex min-w-0 items-start gap-3">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-[#eef3f8] text-[#1f3b57] dark:bg-slate-800 dark:text-slate-200" aria-hidden="true"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 6.75h15M4.5 12h15M4.5 17.25h9" /></svg></span>
                <span><span id="proposal-details-heading" class="block text-base font-black text-slate-950 dark:text-white">3. Proposal details</span><span class="mt-1 block text-sm font-normal leading-6 text-slate-500 dark:text-slate-400">Confirm that the proposal information is correct and up to date.</span></span>
            </span>
            <span class="inline-flex min-h-11 shrink-0 items-center rounded-lg border border-slate-300 px-3 py-2 text-xs font-bold text-slate-700 dark:border-slate-600 dark:text-slate-200" x-text="open ? 'Hide details' : 'Edit details'">Edit details</span>
        </button>
        <div id="proposal-details-fields" data-revision-proposal-details-fields x-show="open" x-cloak x-transition class="grid gap-4 border-t border-slate-100 p-5 dark:border-slate-800 sm:p-6 md:grid-cols-2">
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-200">Project title<input name="title" value="{{ old('title', $topic->title) }}" required class="mt-2 block w-full rounded-lg border-slate-200 text-sm focus:border-[#7A0019] focus:ring-[#7A0019] dark:border-slate-700 dark:bg-slate-950 dark:text-white"></label>
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-200">Total project cost<input name="estimated_budget" type="number" min="0" max="{{ $topic->researchCall?->budgetCeiling() ?? \App\Models\ResearchCall::MAXIMUM_BUDGET }}" step="0.01" value="{{ old('estimated_budget', $displayProjectCost) }}" required class="mt-2 block w-full rounded-lg border-slate-200 text-sm focus:border-[#7A0019] focus:ring-[#7A0019] dark:border-slate-700 dark:bg-slate-950 dark:text-white"></label>
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 md:col-span-2">Description<textarea name="description" rows="3" class="mt-2 block w-full rounded-lg border-slate-200 text-sm leading-6 focus:border-[#7A0019] focus:ring-[#7A0019] dark:border-slate-700 dark:bg-slate-950 dark:text-white">{{ old('description', $topic->description) }}</textarea></label>
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-200">Duration in months<input name="estimated_duration_months" type="number" min="1" max="120" value="{{ old('estimated_duration_months', $topic->estimated_duration_months) }}" required class="mt-2 block w-full rounded-lg border-slate-200 text-sm focus:border-[#7A0019] focus:ring-[#7A0019] dark:border-slate-700 dark:bg-slate-950 dark:text-white"></label>
        </div>

        <label class="mx-5 mb-5 flex items-start gap-3 rounded-lg bg-slate-50 p-4 text-sm font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200 sm:mx-6">
            <input type="checkbox" data-revision-details-confirmed required class="mt-0.5 rounded border-slate-300 text-[#7A0019] focus:ring-[#7A0019]">
            I have checked the title, cost, description, and duration and confirm they are correct.
        </label>
    </section>

    <section id="review-and-submit" data-revision-step="4" data-revision-step-label="Submit" hidden class="scroll-mt-28 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <div class="flex flex-col gap-5 p-5 sm:p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="flex min-w-0 items-start gap-3">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-[#eef3f8] text-[#1f3b57] dark:bg-slate-800 dark:text-slate-200" aria-hidden="true"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 19.5 20 12 4.5 4.5 6.7 11l8.3 1-8.3 1-2.2 6.5Z" /></svg></span>
                    <div><h3 class="text-base font-black text-slate-950 dark:text-white">4. Final review and submission</h3><p class="mt-1 text-sm leading-6 text-slate-500 dark:text-slate-400">Create the next proposal version and return it to the Research Head for review.</p></div>
                </div>
                <button type="submit" data-revision-submit-button class="inline-flex min-h-11 shrink-0 items-center justify-center gap-2 rounded-lg bg-[#7A0019] px-5 text-sm font-bold text-white transition hover:bg-[#650015] focus:outline-none focus:ring-2 focus:ring-[#7A0019] focus:ring-offset-2 disabled:pointer-events-none disabled:cursor-wait disabled:bg-slate-300 disabled:text-white disabled:opacity-100 dark:disabled:bg-slate-700 dark:disabled:text-slate-300 dark:focus:ring-offset-slate-900">
                    <span data-revision-submit-button-spinner hidden class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white" aria-hidden="true"></span>
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 19.5 20 12 4.5 4.5 6.7 11l8.3 1-8.3 1-2.2 6.5Z" /></svg>
                    <span data-revision-submit-button-label>Submit for review</span>
                </button>
            </div>
            <dl class="grid gap-4 rounded-xl bg-slate-50 p-4 text-sm dark:bg-slate-800 sm:grid-cols-2">
                <div class="sm:col-span-2"><dt class="text-slate-500 dark:text-slate-400">Project title</dt><dd data-revision-summary-title class="mt-1 font-semibold text-slate-900 dark:text-white">{{ old('title', $topic->title) }}</dd></div>
                <div><dt class="text-slate-500 dark:text-slate-400">Paper revisions</dt><dd class="mt-1 font-semibold text-slate-900 dark:text-white">{{ $revisionGroups->count() }} {{ Str::plural('paper', $revisionGroups->count()) }} addressed</dd></div>
                <div><dt class="text-slate-500 dark:text-slate-400">Comment responses</dt><dd class="mt-1 font-semibold text-slate-900 dark:text-white">{{ count($commentResponseRows) }} {{ Str::plural('response', count($commentResponseRows)) }} completed</dd></div>
                <div><dt class="text-slate-500 dark:text-slate-400">Total project cost</dt><dd data-revision-summary-cost class="mt-1 font-semibold text-slate-900 dark:text-white"></dd></div>
                <div><dt class="text-slate-500 dark:text-slate-400">Duration</dt><dd data-revision-summary-duration class="mt-1 font-semibold text-slate-900 dark:text-white"></dd></div>
            </dl>
            <p class="text-sm leading-6 text-slate-600 dark:text-slate-300">Submitting saves your responses in the Comment Response paper and creates the next proposal version. Papers you did not replace carry forward automatically.</p>
            <div data-revision-submit-error role="alert" hidden class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200"><p class="font-bold">Revision not sent</p><p data-revision-submit-error-message class="mt-1"></p></div>
        </div>
    </section>

    <div class="flex flex-wrap items-center justify-between gap-3 pb-20" data-revision-navigation>
        <button type="button" data-revision-step-back hidden class="min-h-11 rounded-lg border border-slate-300 bg-white px-5 text-sm font-semibold text-slate-700 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200">Back</button>
        <p data-revision-step-error role="alert" hidden class="text-sm font-semibold text-red-700 dark:text-red-300"></p>
        <button type="button" data-revision-step-continue class="ml-auto min-h-11 rounded-lg bg-[#7A0019] px-5 text-sm font-bold text-white hover:bg-[#650015]">Continue to revise and respond</button>
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
