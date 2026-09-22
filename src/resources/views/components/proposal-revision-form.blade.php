@props(['topic', 'pendingFileRevisions', 'stagedRevisionFiles', 'displayProjectCost', 'commentResponseRows' => []])

@php
    $revisionErrors = $errors->getBag('resubmission');
    $revisionGroups = $pendingFileRevisions->groupBy('document_type');
    $latestRevisionReview = $topic->reviews->where('decision', 'revision_requested')->sortByDesc('id')->first();
    $metadataHasErrors = $revisionErrors->hasAny(['title', 'description', 'estimated_budget', 'estimated_duration_months']);
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
    data-confirm-title="Submit this revision to the Research Head?"
    data-confirm-text="Your updated details and replacement files will be saved as a new version. Unchanged files carry forward automatically."
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
    <h2 id="faculty-revision-heading" class="sr-only">Prepare the corrected proposal package</h2>

    <section id="revision-feedback" data-revision-intro class="scroll-mt-28 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900" aria-labelledby="comment-response-heading">
        <header class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 dark:border-slate-800 sm:flex-row sm:items-start sm:justify-between sm:px-6">
            <div class="flex min-w-0 items-start gap-3">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-[#eef3f8] text-[#1f3b57] dark:bg-slate-800 dark:text-slate-200" aria-hidden="true">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 11.5a8.4 8.4 0 0 1-8.6 8.4A9 9 0 0 1 8 18.8L3 20l1.3-3.9a8.3 8.3 0 0 1-1.1-4.1A8.4 8.4 0 0 1 12 3.5a8.4 8.4 0 0 1 9 8Z" /></svg>
                </span>
                <div>
                    <h3 id="comment-response-heading" class="text-base font-black text-slate-950 dark:text-white">1. Reviewer feedback</h3>
                    <p class="mt-1 text-sm leading-6 text-slate-500 dark:text-slate-400">Respond to every recorded comment before sending the revised package.</p>
                </div>
            </div>
            <span class="inline-flex w-fit rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-500 dark:bg-slate-800 dark:text-slate-300">{{ count($commentResponseRows) }} {{ Str::plural('comment', count($commentResponseRows)) }}</span>
        </header>

        <div class="space-y-5 p-5 sm:p-6">
            @foreach ($revisionErrors->get('feedback_responses*') as $feedbackErrors)
                @foreach ((array) $feedbackErrors as $feedbackError)
                    <p class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm font-semibold text-red-700 dark:border-red-900 dark:bg-red-950/30 dark:text-red-300">{{ $feedbackError }}</p>
                @endforeach
            @endforeach

            @can('generateCommentResponseForm', $topic)
                @if ($commentResponseGroups->isNotEmpty())
                    <div class="grid gap-3 sm:grid-cols-2" aria-label="Comment-Response Form downloads">
                        @foreach ($commentResponseGroups as $source => $sourceRows)
                            @php($isCoEvaluatorForm = $source === \App\Services\CommentResponseFeedback::FORM_CO_EVALUATOR)
                            <section data-comment-response-source="{{ $source }}" class="flex flex-col justify-between gap-4 rounded-xl border border-slate-200 bg-slate-50/70 p-4 dark:border-slate-700 dark:bg-slate-800/60">
                                <div class="flex items-start gap-3">
                                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg {{ $isCoEvaluatorForm ? 'bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300' : 'bg-red-50 text-[#7A0019] dark:bg-red-950/40 dark:text-red-300' }}" aria-hidden="true">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3.75h7.5l3 3v13.5H6.75V3.75Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M14.25 3.75v3h3M9.5 11h5M9.5 14.5h5" /></svg>
                                    </span>
                                    <div>
                                        <h4 class="text-sm font-bold text-slate-900 dark:text-white">{{ $isCoEvaluatorForm ? 'Co-evaluator Comment-Response Form' : 'Research Head Comment-Response Form' }}</h4>
                                        <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">{{ $sourceRows->count() }} recorded {{ Str::plural('comment', $sourceRows->count()) }}</p>
                                    </div>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <a href="{{ route('faculty.topics.comment-response-form.preview', ['topic' => $topic, 'source' => $source, 'review' => $latestRevisionReview?->id]) }}" target="_blank" rel="noopener" class="inline-flex min-h-9 items-center justify-center rounded-lg border border-slate-200 bg-white px-3 text-xs font-bold text-slate-700 transition hover:border-slate-300">Preview</a>
                                    <a href="{{ route('faculty.topics.comment-response-form.pdf', ['topic' => $topic, 'source' => $source, 'review' => $latestRevisionReview?->id]) }}" target="_blank" rel="noopener" class="inline-flex min-h-9 items-center justify-center rounded-lg bg-[#7A0019] px-3 text-xs font-bold text-white transition hover:bg-[#650015]">Open PDF</a>
                                </div>
                            </section>
                        @endforeach
                    </div>
                @endif
            @endcan

            @if ($commentResponseRows !== [])
                <input type="hidden" name="feedback_review_id" value="{{ $latestRevisionReview?->id }}">
                <div class="space-y-5">
                    @foreach ($commentResponseGroups as $source => $sourceRows)
                        @php($isCoEvaluatorForm = $source === \App\Services\CommentResponseFeedback::FORM_CO_EVALUATOR)
                        <section class="space-y-2" aria-label="{{ $commentResponseLabels[$source] ?? 'Reviewer feedback' }} responses">
                            <div class="flex items-center gap-2 px-1">
                                <span class="h-2 w-2 rounded-full {{ $isCoEvaluatorForm ? 'bg-blue-600' : 'bg-[#7A0019]' }}" aria-hidden="true"></span>
                                <h4 class="text-xs font-black uppercase tracking-[0.08em] text-slate-500 dark:text-slate-400">{{ $commentResponseLabels[$source] ?? 'Reviewer feedback' }}</h4>
                            </div>
                            @foreach ($sourceRows as $item)
                                @php($hasResponse = filled(old('feedback_responses.'.$item['key'].'.response', $item['response'])))
                                <article
                                    x-data="{ open: @js($loop->first), answered: @js($hasResponse) }"
                                    class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900"
                                    data-revision-feedback-item
                                >
                                    <button type="button" @click="open = !open" :aria-expanded="open" class="flex w-full items-center gap-3 px-3 py-3 text-left transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[#7A0019] dark:hover:bg-slate-800 sm:px-4">
                                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-[10px] font-black {{ $isCoEvaluatorForm ? 'bg-blue-100 text-blue-800 dark:bg-blue-950/50 dark:text-blue-300' : 'bg-red-100 text-[#7A0019] dark:bg-red-950/50 dark:text-red-300' }}">{{ $isCoEvaluatorForm ? 'CE' : 'RH' }}</span>
                                        <span class="min-w-0 flex-1 sm:flex sm:items-baseline sm:gap-2">
                                            <strong class="block shrink-0 text-xs text-slate-900 dark:text-white">{{ $item['reviewer'] }}</strong>
                                            <span class="block truncate text-xs text-slate-500 dark:text-slate-400">{{ $item['location'] }} — {{ $item['comment'] }}</span>
                                        </span>
                                        <span class="hidden shrink-0 rounded-full px-2 py-1 text-[10px] font-bold sm:inline-flex" :class="answered ? 'bg-green-50 text-green-700 dark:bg-green-950/40 dark:text-green-300' : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'" x-text="answered ? 'Answered' : 'Not yet answered'"></span>
                                        <svg :class="open ? 'rotate-180' : ''" class="h-4 w-4 shrink-0 text-slate-400 transition-transform motion-reduce:transition-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" /></svg>
                                    </button>
                                    <div x-show="open" x-cloak x-transition class="space-y-4 border-t border-slate-100 px-4 py-4 dark:border-slate-800 sm:px-5">
                                        <blockquote class="whitespace-pre-line rounded-lg bg-[#eef3f8] px-4 py-3 text-sm leading-6 text-slate-800 dark:bg-slate-800 dark:text-slate-100">{{ $item['comment'] }}</blockquote>
                                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-200">
                                            Your response
                                            <textarea x-ref="response" @input="answered = $event.target.value.trim().length > 0" name="feedback_responses[{{ $item['key'] }}][response]" rows="3" maxlength="5000" required placeholder="Explain what you changed and why" class="mt-2 block w-full rounded-lg border-slate-200 text-sm leading-6 focus:border-[#7A0019] focus:ring-[#7A0019] dark:border-slate-700 dark:bg-slate-950 dark:text-white">{{ old('feedback_responses.'.$item['key'].'.response', $item['response']) }}</textarea>
                                        </label>
                                    </div>
                                </article>
                            @endforeach
                        </section>
                    @endforeach
                </div>
            @else
                <p class="rounded-xl bg-slate-50 px-4 py-5 text-center text-sm text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">No written reviewer comments were recorded for this revision round.</p>
            @endif
        </div>
    </section>

    <section id="revision-papers" x-data="{ open: true }" class="scroll-mt-28 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900" aria-labelledby="revision-papers-heading">
        <header class="flex items-start justify-between gap-4 px-5 py-4 sm:px-6">
            <div class="flex min-w-0 items-start gap-3">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-[#eef3f8] text-[#1f3b57] dark:bg-slate-800 dark:text-slate-200" aria-hidden="true">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3.75h7.5l3 3v13.5H6.75V3.75Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M14.25 3.75v3h3M9.5 11h5M9.5 14.5h5" /></svg>
                </span>
                <div>
                    <h3 id="revision-papers-heading" class="text-base font-black text-slate-950 dark:text-white">2. Requested papers</h3>
                    <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-500 dark:text-slate-400">Address every requested file by editing it, uploading a replacement, or explaining why no file change is needed. Files that were not requested carry forward automatically.</p>
                </div>
            </div>
            <button type="button" @click="open = !open" :aria-expanded="open" aria-controls="requested-papers-content" class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-500 transition hover:border-slate-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#7A0019] dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300">
                <svg :class="open ? 'rotate-180' : ''" class="h-4 w-4 transition-transform motion-reduce:transition-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" /></svg>
                <span class="sr-only">Toggle requested papers</span>
            </button>
        </header>
        <div id="requested-papers-content" x-show="open" x-cloak x-transition class="space-y-3 border-t border-slate-100 p-5 dark:border-slate-800 sm:p-6" data-requested-revision-files>
            @forelse ($revisionGroups as $documentType => $fileRevisions)
                <x-proposal-revision-document :topic="$topic" :document-type="$documentType" :file-revisions="$fileRevisions" :staged-file="$stagedRevisionFiles->get($documentType)" :required="true" />
            @empty
                <p class="rounded-xl bg-slate-50 px-4 py-5 text-center text-sm text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">No specific paper replacement was requested. Respond to the comments and confirm the proposal details below.</p>
            @endforelse
        </div>
    </section>

    <section
        id="revision-details"
        data-revision-proposal-details
        data-initially-open="{{ $metadataHasErrors ? 'true' : 'false' }}"
        x-data="{ open: @js($metadataHasErrors) }"
        @open-revision-proposal-details.window="open = true"
        class="scroll-mt-28 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900"
        aria-labelledby="proposal-details-heading"
    >
        <button type="button" data-revision-proposal-details-button @click="open = !open" :aria-expanded="open" aria-controls="proposal-details-fields" class="flex w-full items-start justify-between gap-4 px-5 py-4 text-left transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[#7A0019] dark:hover:bg-slate-800 sm:px-6">
            <span class="flex min-w-0 items-start gap-3">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-[#eef3f8] text-[#1f3b57] dark:bg-slate-800 dark:text-slate-200" aria-hidden="true"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 6.75h15M4.5 12h15M4.5 17.25h9" /></svg></span>
                <span><span id="proposal-details-heading" class="block text-base font-black text-slate-950 dark:text-white">3. Proposal details</span><span class="mt-1 block text-sm font-normal leading-6 text-slate-500 dark:text-slate-400">Confirm that the proposal information is correct and up to date.</span></span>
            </span>
            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300" aria-hidden="true"><svg :class="open ? 'rotate-180' : ''" class="h-4 w-4 transition-transform motion-reduce:transition-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" /></svg></span>
        </button>
        <div id="proposal-details-fields" data-revision-proposal-details-fields x-show="open" x-cloak x-transition class="grid gap-4 border-t border-slate-100 p-5 dark:border-slate-800 sm:p-6 md:grid-cols-2">
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-200">Project title<input name="title" value="{{ old('title', $topic->title) }}" required class="mt-2 block w-full rounded-lg border-slate-200 text-sm focus:border-[#7A0019] focus:ring-[#7A0019] dark:border-slate-700 dark:bg-slate-950 dark:text-white"></label>
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-200">Total project cost<input name="estimated_budget" type="number" min="0" max="{{ $topic->researchCall?->budgetCeiling() ?? \App\Models\ResearchCall::MAXIMUM_BUDGET }}" step="0.01" value="{{ old('estimated_budget', $displayProjectCost) }}" required class="mt-2 block w-full rounded-lg border-slate-200 text-sm focus:border-[#7A0019] focus:ring-[#7A0019] dark:border-slate-700 dark:bg-slate-950 dark:text-white"></label>
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 md:col-span-2">Description<textarea name="description" rows="3" class="mt-2 block w-full rounded-lg border-slate-200 text-sm leading-6 focus:border-[#7A0019] focus:ring-[#7A0019] dark:border-slate-700 dark:bg-slate-950 dark:text-white">{{ old('description', $topic->description) }}</textarea></label>
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-200">Duration in months<input name="estimated_duration_months" type="number" min="1" max="120" value="{{ old('estimated_duration_months', $topic->estimated_duration_months) }}" required class="mt-2 block w-full rounded-lg border-slate-200 text-sm focus:border-[#7A0019] focus:ring-[#7A0019] dark:border-slate-700 dark:bg-slate-950 dark:text-white"></label>
        </div>
    </section>

    <section id="review-and-submit" class="scroll-mt-28 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
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
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-200">Summary of changes <span class="font-normal text-slate-400">(optional)</span><textarea name="change_summary" rows="3" maxlength="2000" placeholder="Briefly explain what you updated" class="mt-2 block w-full rounded-lg border-slate-200 text-sm leading-6 focus:border-[#7A0019] focus:ring-[#7A0019] dark:border-slate-700 dark:bg-slate-950 dark:text-white">{{ old('change_summary') }}</textarea></label>
            <div data-revision-submit-error role="alert" hidden class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200"><p class="font-bold">Revision not sent</p><p data-revision-submit-error-message class="mt-1"></p></div>
        </div>
    </section>

    <div data-revision-submit-overlay role="status" aria-live="assertive" aria-hidden="true" aria-label="Revision submission progress" tabindex="-1" class="fixed inset-0 z-[70] hidden items-center justify-center bg-slate-950/70 p-4 backdrop-blur-sm">
        <div class="w-full max-w-md rounded-2xl border border-white/20 bg-white p-6 text-center shadow-2xl dark:border-slate-700 dark:bg-slate-900">
            <span class="mx-auto block h-10 w-10 animate-spin rounded-full border-4 border-red-100 border-t-red-700 dark:border-slate-700 dark:border-t-red-400" aria-hidden="true"></span>
            <p data-revision-submit-title class="mt-4 text-lg font-black text-gray-950 dark:text-white">Preparing your revision</p>
            <p data-revision-submit-status class="mt-2 text-base leading-7 text-gray-600 dark:text-slate-300">Saving your edits and generating the requested PDFs…</p>
            <p class="mt-3 text-sm font-semibold leading-6 text-gray-500 dark:text-slate-400">This should finish shortly. If a step takes too long, the submission will stop and let you try again.</p>
        </div>
    </div>
</form>
