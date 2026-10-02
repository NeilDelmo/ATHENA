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
    $commentResponseGroups = collect($commentResponseRows)->groupBy('form_source');
    $commentResponseLabels = [
        \App\Services\CommentResponseFeedback::FORM_RESEARCH_HEAD => 'Research Head feedback',
        \App\Services\CommentResponseFeedback::FORM_CO_EVALUATOR => 'Co-evaluator feedback',
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
    data-revision-start-step="{{ $metadataHasErrors ? 4 : (count($revisionErrors->get('feedback_responses*')) > 0 ? 3 : ($revisionErrors->any() ? 2 : 1)) }}"
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

    <div class="rounded-xl border border-slate-200 bg-white px-5 py-4 dark:border-slate-700 dark:bg-slate-900">
        <p data-revision-progress role="status" aria-live="polite" class="text-sm font-bold text-slate-800 dark:text-slate-100">Step 1 of 5 · Read feedback</p>
        <ol class="mt-3 flex flex-wrap gap-x-5 gap-y-2 text-xs text-slate-500 dark:text-slate-400" aria-label="Revision workflow">
            @foreach (['Read feedback', 'Revise papers', 'Write responses', 'Confirm details', 'Submit'] as $stepLabel)
                <li data-revision-progress-step="{{ $loop->iteration }}" class="flex items-center gap-2"><span data-revision-progress-mark aria-hidden="true">{{ $loop->iteration }}</span>{{ $stepLabel }}</li>
            @endforeach
        </ol>
    </div>

    <section id="revision-feedback" data-revision-step="1" data-revision-step-label="Read feedback" data-revision-intro class="scroll-mt-28 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900" aria-labelledby="comment-response-heading">
        <header class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 dark:border-slate-800 sm:flex-row sm:items-start sm:justify-between sm:px-6">
            <div class="flex min-w-0 items-start gap-3">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-[#eef3f8] text-[#1f3b57] dark:bg-slate-800 dark:text-slate-200" aria-hidden="true">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 11.5a8.4 8.4 0 0 1-8.6 8.4A9 9 0 0 1 8 18.8L3 20l1.3-3.9a8.3 8.3 0 0 1-1.1-4.1A8.4 8.4 0 0 1 12 3.5a8.4 8.4 0 0 1 9 8Z" /></svg>
                </span>
                <div>
                    <h3 id="comment-response-heading" class="text-base font-black text-slate-950 dark:text-white">1. Reviewer feedback</h3>
                    <p class="mt-1 text-sm leading-6 text-slate-500 dark:text-slate-400">Read the comments and preview the Comment Response paper before revising your papers.</p>
                </div>
            </div>
            <span class="inline-flex w-fit rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-500 dark:bg-slate-800 dark:text-slate-300">{{ count($commentResponseRows) }} {{ Str::plural('comment', count($commentResponseRows)) }}</span>
        </header>

        <div class="space-y-4 p-5 sm:p-6">
            @if ($commentResponseRows !== [])
                <input type="hidden" name="feedback_review_id" value="{{ $latestRevisionReview?->id }}">
                <div class="space-y-4">
                    @foreach ($commentResponseGroups as $source => $sourceRows)
                        <section data-comment-response-source="{{ $source }}" class="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-700" aria-label="{{ $commentResponseLabels[$source] ?? 'Reviewer feedback' }} responses">
                            <div class="flex flex-col gap-3 border-b border-slate-200 bg-slate-50/70 px-4 py-3 dark:border-slate-700 dark:bg-slate-800/50 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                                <div>
                                    <h4 class="text-sm font-bold text-slate-900 dark:text-white">{{ $commentResponseLabels[$source] ?? 'Reviewer feedback' }}</h4>
                                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ $sourceRows->count() }} {{ Str::plural('comment', $sourceRows->count()) }}</p>
                                </div>
                                @can('generateCommentResponseForm', $topic)
                                    <button type="button" data-comment-response-preview aria-haspopup="dialog" @click="$dispatch('open-modal', 'revision-comment-response-{{ $topic->id }}-{{ $source }}')" class="inline-flex min-h-11 w-fit items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:text-slate-950 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#7A0019] focus-visible:ring-offset-2 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-slate-500 dark:hover:text-white dark:focus-visible:ring-offset-slate-900"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2 12s3-7 10-7 10 7-3 7-10 7S2 12 2 12Z" /><circle cx="12" cy="12" r="3" /></svg>Preview Comment Response Paper</button>
                                @endcan
                            </div>
                            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                                @foreach ($sourceRows as $item)
                                    <article class="space-y-4 p-4 sm:p-5" data-revision-feedback-item>
                                        <header class="space-y-1">
                                            <p class="text-xs leading-5 text-slate-500 dark:text-slate-400">{{ $item['location'] }}@if (isset($item['stage'])) · {{ \App\Services\CommentResponseFeedback::STAGE_LABELS[$item['stage']] ?? '' }}@endif</p>
                                        </header>
                                        <blockquote class="whitespace-pre-line rounded-r-lg border-l-[3px] border-slate-300 bg-slate-50 px-4 py-3 text-sm leading-6 text-slate-800 dark:border-slate-600 dark:bg-slate-800/50 dark:text-slate-100">{{ $item['comment'] }}</blockquote>

                                    </article>
                                @endforeach
                            </div>
                        </section>
                    @endforeach
                </div>
            @else
                <p class="rounded-xl bg-slate-50 px-4 py-5 text-center text-sm text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">No written reviewer comments were recorded for this revision round.</p>
            @endif
        </div>
    </section>

    <section id="revision-papers" data-revision-step="2" data-revision-step-label="Revise papers" hidden class="scroll-mt-28 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900" aria-labelledby="revision-papers-heading">
        <header class="flex items-start justify-between gap-4 px-5 py-4 sm:px-6">
            <div class="flex min-w-0 items-start gap-3">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-[#eef3f8] text-[#1f3b57] dark:bg-slate-800 dark:text-slate-200" aria-hidden="true">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3.75h7.5l3 3v13.5H6.75V3.75Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M14.25 3.75v3h3M9.5 11h5M9.5 14.5h5" /></svg>
                </span>
                <div>
                    <h3 id="revision-papers-heading" class="text-base font-black text-slate-950 dark:text-white">2. Revise papers</h3>
                    <p class="mt-1 text-sm leading-6 text-slate-500 dark:text-slate-400">{{ $revisionGroups->isEmpty() ? 'No paper changes were requested.' : 'Update each paper, upload a replacement, or explain why no change is needed.' }}</p>
                </div>
            </div>
        </header>
        <div id="requested-papers-content" class="space-y-3 border-t border-slate-100 p-5 dark:border-slate-800 sm:p-6" data-requested-revision-files>
            @forelse ($revisionGroups as $documentType => $fileRevisions)
                <x-proposal-revision-document :topic="$topic" :document-type="$documentType" :file-revisions="$fileRevisions" :staged-file="$stagedRevisionFiles->get($documentType)" :required="true" />
            @empty
                <p class="rounded-xl bg-slate-50 px-4 py-5 text-center text-sm text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">No paper changes requested. Your current papers will carry forward. Respond to the overall feedback, check the proposal details, then submit your revision.</p>
            @endforelse
        </div>
    </section>

    <section id="revision-responses" data-revision-step="3" data-revision-step-label="Write responses" hidden class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900 sm:p-6">
        <h3 class="text-base font-black text-slate-950 dark:text-white">3. Write responses</h3>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Explain what you changed for each comment, or why no change was needed.</p>
        <div class="mt-5 space-y-4">
            @foreach ($revisionErrors->get('feedback_responses*') as $feedbackErrors)
                @foreach ((array) $feedbackErrors as $feedbackError)
                    <p class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm font-semibold text-red-700 dark:border-red-900 dark:bg-red-950/30 dark:text-red-300">{{ $feedbackError }}</p>
                @endforeach
            @endforeach


            @forelse ($commentResponseGroups as $source => $sourceRows)
                <section data-revision-response-source="{{ $source }}" class="space-y-4">
                    <h4 class="text-sm font-bold text-slate-900 dark:text-white">{{ $commentResponseLabels[$source] ?? 'Reviewer feedback' }}</h4>
                    @foreach ($sourceRows as $item)
                        <article class="space-y-3 rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ $item['location'] }}</p>
                            <blockquote class="whitespace-pre-line border-l-2 border-slate-300 pl-3 text-sm leading-6 text-slate-700 dark:text-slate-200">{{ $item['comment'] }}</blockquote>
                                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-200">
                                            Your response <span class="font-normal text-slate-500 dark:text-slate-400">(required)</span>
                                            <textarea data-revision-response-document="{{ $responseDocumentTypes[$item['key']] ?? '' }}" name="feedback_responses[{{ $item['key'] }}][response]" rows="3" maxlength="5000" required placeholder="Explain the change you made and where it can be found, or why no change is needed." class="mt-2 block w-full rounded-lg border-slate-300 text-sm leading-6 focus:border-[#7A0019] focus:ring-[#7A0019] dark:border-slate-600 dark:bg-slate-950 dark:text-white">{{ old('feedback_responses.'.$item['key'].'.response', $item['response']) }}</textarea>
                                        </label>
                        </article>
                    @endforeach
                </section>
            @empty
                <p class="text-sm text-slate-500">No written comments need a response.</p>
            @endforelse
        </div>
    </section>

    <section
        id="revision-details"
        data-revision-step="4"
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
                <span><span id="proposal-details-heading" class="block text-base font-black text-slate-950 dark:text-white">4. Proposal details</span><span class="mt-1 block text-sm font-normal leading-6 text-slate-500 dark:text-slate-400">Confirm that the proposal information is correct and up to date.</span></span>
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

    <section id="review-and-submit" data-revision-step="5" data-revision-step-label="Submit" hidden class="scroll-mt-28 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <div class="flex flex-col gap-5 p-5 sm:p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="flex min-w-0 items-start gap-3">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-[#eef3f8] text-[#1f3b57] dark:bg-slate-800 dark:text-slate-200" aria-hidden="true"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 19.5 20 12 4.5 4.5 6.7 11l8.3 1-8.3 1-2.2 6.5Z" /></svg></span>
                    <div><h3 class="text-base font-black text-slate-950 dark:text-white">5. Final review and submission</h3><p class="mt-1 text-sm leading-6 text-slate-500 dark:text-slate-400">Create the next proposal version and return it to the Research Head for review.</p></div>
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
        <button type="button" data-revision-step-continue class="ml-auto min-h-11 rounded-lg bg-[#7A0019] px-5 text-sm font-bold text-white hover:bg-[#650015]">Continue to revise papers</button>
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

@can('generateCommentResponseForm', $topic)
    @foreach ($commentResponseGroups as $source => $sourceRows)
        <x-modal name="revision-comment-response-{{ $topic->id }}-{{ $source }}" maxWidth="6xl" focusable class="!z-[140]" data-faculty-comment-response-preview-modal>
            <template x-if="show">
                <section role="dialog" aria-modal="true" aria-labelledby="revision-comment-response-heading-{{ $topic->id }}-{{ $source }}">
                    <header class="flex items-start justify-between gap-4 border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                        <div>
                            <h3 id="revision-comment-response-heading-{{ $topic->id }}-{{ $source }}" class="text-base font-bold text-gray-950 dark:text-white">{{ $source === \App\Services\CommentResponseFeedback::FORM_CO_EVALUATOR ? 'Co-evaluator' : 'Research Head' }} Comment Response paper</h3>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Responses entered on this page are included after you submit the revision.</p>
                        </div>
                        <button type="button" @click="$dispatch('close')" class="inline-flex min-h-11 shrink-0 items-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-bold text-gray-800 hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-700 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100 dark:hover:bg-gray-800">Close preview</button>
                    </header>
                    <x-proposal-revision-pdf :configuration="['pdfUrl' => route('faculty.topics.comment-response-form.pdf', ['topic' => $topic, 'source' => $source, 'review' => $latestRevisionReview?->id]), 'annotations' => [], 'canAnnotate' => false]" loading-label="Loading Comment Response paper…" viewer-label="Comment Response paper" class="!h-[75dvh]" />
                </section>
            </template>
        </x-modal>
    @endforeach
@endcan
