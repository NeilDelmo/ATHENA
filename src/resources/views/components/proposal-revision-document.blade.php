@props(['topic', 'documentType', 'fileRevisions' => collect(), 'stagedFile' => null, 'required' => false, 'commentResponseRows' => collect(), 'documentTypes' => collect()])

@php
    $multiple = $documentType === 'curriculum_vitae';
    $inputName = $multiple ? 'curricula_vitae' : $documentType;
    $accept = $documentType === 'expense_breakdown' ? '.pdf' : '.doc,.docx,.pdf';
    $label = app(\App\Support\ProposalPaperCatalog::class)->label($documentType) ?? str($documentType)->replace('_', ' ')->title();
    $revisionTargetCatalog = app(\App\Support\ProposalRevisionTargetCatalog::class);
    $paper = app(\App\Support\ProposalPaperCatalog::class)->forDocumentType($documentType);
    $canEmbed = $required && ($paper['mode'] ?? null) === 'generated'
        && (! $multiple || ($fileRevisions->count() === 1 && $fileRevisions->first()->file?->position === 0));
    $editorUrl = $canEmbed ? route('faculty.proposal-drafts.revision', [
        'topic' => $topic, 'document_type' => $documentType, 'revision_embed' => 1,
    ]) : null;
    $fileErrors = $errors->getBag('resubmission')->get($inputName.'*');
    $noChangeSelected = old('revision_resolutions.'.$documentType.'.action') === 'no_change';
    $noChangeExplanation = old('revision_resolutions.'.$documentType.'.explanation', '');
    $noChangeAddressed = $noChangeSelected && filled($noChangeExplanation);
    $noChangeError = $errors->getBag('resubmission')->first('revision_resolutions.'.$documentType.'.explanation');
    $responsesByKey = collect($commentResponseRows)->keyBy('key');
    $feedbackItems = collect();
    foreach ($fileRevisions as $fileRevision) {
        $revisionFile = $fileRevision->file;
        $annotationVersion = $topic->versions->firstWhere('id', $revisionFile?->proposal_version_id);
        $pdfUrl = $revisionFile && $annotationVersion
            ? route('topics.versions.files.annotations.index', [$topic, $annotationVersion, $revisionFile]).'?revision_embed=1'
            : null;
        $annotations = $fileRevision->annotations->sortBy([['page_number', 'asc'], ['id', 'asc']])->values();
        if ($annotations->isEmpty() || $responsesByKey->has('file_'.$fileRevision->id)) {
            $annotations->prepend(null);
        }
        foreach ($annotations as $annotation) {
            $feedbackItems->push([
                'id' => $annotation?->id ?? 'file-'.$fileRevision->id,
                'annotation_id' => $annotation?->id,
                'reviewer' => $annotation?->feedbackLabel() ?? 'Reviewer',
                'location' => $annotation
                    ? 'Page '.$annotation->page_number.' · '.($revisionTargetCatalog->labelFor($revisionFile, $annotation->editor_target) ?? 'Paper feedback')
                    : $fileRevision->original_filename,
                'response' => $responsesByKey->get($annotation ? 'annotation_'.$annotation->id : 'file_'.$fileRevision->id),
                'pdf_url' => $pdfUrl,
                'label' => $annotation
                    ? $annotation->feedbackLabel().' · Page '.$annotation->page_number.' · '.($revisionTargetCatalog->labelFor($revisionFile, $annotation->editor_target) ?? 'Paper feedback')
                    : $fileRevision->original_filename,
                'note' => $annotation ? null : $fileRevision->revision_note,
                'quote' => $annotation?->selected_text,
                'comment' => $annotation?->comment,
            ]);
        }
    }
    $singleReply = $feedbackItems->count() === 1 && filled($feedbackItems->first()['response']['key'] ?? null);
@endphp

<article
    data-revision-document="{{ $documentType }}"
    data-revision-label="{{ $label }}"
    @if ($singleReply) data-revision-single-reply @endif
    @if ($stagedFile)
        data-revision-staged-pdf-url="{{ route('faculty.proposal-drafts.revision-files.show', [$stagedFile->proposal_draft_id, $stagedFile]) }}"
    @endif
    data-revision-preview-upload-url="{{ route('faculty.topics.revision.preview', $topic) }}"
    data-topic-file-dropzone="{{ $inputName }}"
    @if (! $canEmbed)
        x-data="fileDropzone({ accept: @js($accept), maxBytes: 26214400, multiple: @js($multiple) })"
        @paste="paste($event)"
    @endif
    class="min-w-0 overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900"
>
    <div class="flex flex-wrap items-center justify-between gap-4 p-4">
        <div class="flex min-w-0 items-center gap-3">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-red-50 text-[#7A0019] dark:bg-red-950/40 dark:text-red-300" aria-hidden="true">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3.75h7.5l3 3v13.5H6.75V3.75Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M14.25 3.75v3h3" /></svg>
            </span>
            <div class="min-w-0">
                <h4 class="truncate text-sm font-bold text-gray-950 dark:text-white">{{ $label }}</h4>
            @if ($required)
                <span hidden data-revision-document-state data-modified="false" data-addressed="{{ $noChangeAddressed ? 'true' : 'false' }}" data-reviewed="false"></span>
            @endif
            </div>
        </div>
        @if ($required)
            <div class="flex shrink-0 items-center gap-3">
                <span data-revision-resolved-cue @if (! $noChangeAddressed) hidden @endif class="text-emerald-700 dark:text-emerald-400" title="Revision action recorded for this paper">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path stroke-linecap="round" stroke-linejoin="round" d="m8 12 2.5 2.5L16 9" /></svg>
                    <span class="sr-only">Revision action recorded</span>
                </span>
                <button type="button" data-revision-open class="inline-flex min-h-10 shrink-0 items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 text-xs font-bold text-slate-700 transition hover:border-slate-300 hover:text-[#7A0019] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#7A0019] dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200">Revise paper<svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14 4h6v6M20 4 4 20M9 4H4v16h16v-5" /></svg></button>
            </div>
        @endif
    </div>

    @if ($required)
        <dialog data-revision-dialog aria-labelledby="revision-dialog-title-{{ $documentType }}" class="revision-dialog bg-white text-gray-900 dark:bg-slate-900 dark:text-white">
            <header class="revision-dialog-header">
                <div class="min-w-0">
                    <h3 id="revision-dialog-title-{{ $documentType }}" class="truncate text-lg font-bold">{{ $label }}</h3>
                    <p class="text-sm text-gray-500 dark:text-slate-400">Compare papers, make your edits, and reply to feedback here.</p>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    <button type="button" data-revision-previous aria-label="Previous document" class="rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-slate-700"><span class="hidden sm:inline">Previous</span><svg class="h-4 w-4 sm:hidden" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m15 18-6-6 6-6" /></svg></button>
                    <button type="button" data-revision-next aria-label="Next document" class="rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-slate-700"><span class="hidden sm:inline">Next</span><svg class="h-4 w-4 sm:hidden" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m9 6 6 6-6 6" /></svg></button>
                    <button type="button" data-revision-close class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white dark:bg-slate-700">Done</button>
                </div>
            </header>
            <div data-revision-dialog-submit-error hidden role="alert" class="border-b border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/50 dark:text-red-200">
                <p class="font-bold">This document still needs an action before the revision can be sent.</p>
                <p class="mt-1">Make a change, upload a replacement, or choose “Keep submitted paper” and provide an explanation.</p>
            </div>
            <div class="revision-dialog-body">
                <div class="revision-document-workspace" data-revision-document-workspace>
                    <section class="revision-feedback" data-revision-reference id="revision-reference-{{ $documentType }}" aria-label="Paper reference">
                        <header class="revision-reference-heading">
                            <div class="revision-paper-tabs" role="group" aria-label="Paper version">
                                <button type="button" data-revision-preview-close aria-pressed="true">Submitted</button>
                                <button type="button" data-revision-preview-open aria-controls="revision-preview-{{ $documentType }}" aria-pressed="false" aria-expanded="false">Revised</button>
                            </div>
                            <div class="flex items-center justify-between gap-2"><p class="revision-reference-hint text-sm text-slate-500 dark:text-slate-400">Click the paper to enlarge it.</p><button type="button" data-revision-reference-dismiss>Minimize preview</button></div>
                        </header>
                        <div data-revision-submitted-panel data-revision-paper-open role="button" tabindex="0" aria-label="Enlarge submitted {{ $label }}" aria-controls="revision-reference-{{ $documentType }}" aria-expanded="false" class="revision-frame-shell">
                            <div data-revision-pdf-loading role="status" class="revision-frame-loading">
                                <span class="revision-loading-spinner" aria-hidden="true"></span>
                                <span>Loading submitted paper…</span>
                            </div>
                            <iframe data-revision-pdf-frame title="Submitted {{ $label }} with reviewer highlights" class="revision-pdf-frame"></iframe>
                            <p data-revision-pdf-unavailable hidden class="p-5 text-sm">The submitted PDF is unavailable. Use the reviewer feedback to update this paper.</p>
                        </div>
                        <div data-revision-preview-panel id="revision-preview-{{ $documentType }}" hidden class="revision-preview-panel">
                            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 p-3 dark:border-slate-700">
                                <p data-revision-preview-status role="status" aria-live="polite" class="text-xs text-slate-500 dark:text-slate-400">Current revision</p>
                                <select data-revision-preview-file hidden aria-label="Replacement file to preview" class="w-full rounded-lg border-slate-300 text-xs dark:border-slate-700 dark:bg-slate-950"></select>
                                <button type="button" data-revision-preview-refresh class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200">Refresh preview</button>
                            </div>
                            <p data-revision-preview-stale hidden role="status" class="bg-amber-50 p-3 text-xs text-amber-800 dark:bg-amber-950/40 dark:text-amber-200">You have newer edits. Refresh to compare them.</p>
                            <p data-revision-preview-error hidden role="alert" class="p-3 text-xs text-red-700 dark:text-red-300"></p>
                            <div data-revision-paper-open role="button" tabindex="0" aria-label="Enlarge revised {{ $label }}" aria-controls="revision-reference-{{ $documentType }}" aria-expanded="false" class="revision-preview-paper">
                                <iframe data-revision-preview-frame hidden title="Revised {{ $label }} preview" class="revision-preview-frame"></iframe>
                            </div>
                        </div>
                        <footer class="revision-reference-footer">
                            <div data-revision-preview-zoom class="flex flex-wrap items-center gap-2" role="group" aria-label="Preview zoom">
                                <button type="button" data-revision-zoom-out aria-label="Zoom out">−</button>
                                <output data-revision-zoom-value aria-live="polite">100%</output>
                                <button type="button" data-revision-zoom-in aria-label="Zoom in">+</button>
                                <button type="button" data-revision-fit-page aria-pressed="false">Fit page</button>
                                <button type="button" data-revision-fit-width aria-pressed="true">Fit width</button>
                            </div>
                        </footer>
                    </section>
                    <section class="revision-editor-panel" aria-label="{{ $label }} revision editor">
                        <div class="revision-editor-heading">
                            <p class="text-base font-semibold text-slate-700 dark:text-slate-200">Your paper</p>
                            <div class="revision-editor-actions">
                                <button type="button" data-revision-reference-toggle aria-expanded="false">Show paper</button>
                                <button type="button" data-revision-feedback-toggle aria-expanded="false" aria-controls="revision-feedback-{{ $documentType }}">Feedback &amp; reply<span class="revision-feedback-count">{{ $feedbackItems->count() }}</span></button>
                            </div>
                        </div>
                        <p data-revision-keep-notice hidden class="border-b border-slate-200 bg-slate-50 px-4 py-3 text-xs text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">The submitted paper will be kept. Choose Revise this paper to continue editing.</p>
                        <div data-revision-editor-content class="revision-editor-content">
                        @if ($canEmbed)
                            <p data-revision-editor-status role="status" class="border-b border-gray-200 px-4 py-2 text-xs text-gray-500 dark:border-slate-700 dark:text-slate-400">Loading editor…</p>
                            <div class="revision-frame-shell">
                                <div data-revision-editor-loading role="status" class="revision-frame-loading">
                                    <span class="revision-loading-spinner" aria-hidden="true"></span>
                                    <span>Loading revision editor…</span>
                                </div>
                                <iframe data-revision-editor-frame data-revision-editor-src="{{ $editorUrl }}" title="Edit {{ $label }} alongside Research Head feedback" class="revision-editor-frame"></iframe>
                            </div>
                        @else
                            <h4 class="px-4 pt-4 text-sm font-bold">Upload your revised {{ $label }}</h4>
                            <x-proposal-revision-upload :input-name="$inputName" :accept="$accept" :multiple="$multiple" :required="$required" :staged-file="$stagedFile" :label="$label" :document-type="$documentType" :file-errors="$fileErrors" />
                        @endif
                        </div>
                    </section>
                    <aside class="revision-feedback-and-response" data-revision-feedback-panel id="revision-feedback-{{ $documentType }}" aria-label="Reviewer feedback and your response">
                        <header class="revision-response-heading">
                            <h4>Feedback &amp; reply</h4>
                            <button type="button" data-revision-feedback-dismiss aria-label="Close feedback panel"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18" /></svg></button>
                        </header>
                        <div class="revision-comment-strip" data-revision-feedback>
                            @if ($feedbackItems->isNotEmpty())
                                <label for="revision-comment-{{ $documentType }}" class="sr-only">Reviewer comment</label>
                                <select id="revision-comment-{{ $documentType }}" data-revision-comment @if ($feedbackItems->count() === 1) hidden @endif class="w-full rounded-lg border-slate-300 text-xs dark:border-slate-700 dark:bg-slate-950">
                                    @foreach ($feedbackItems as $feedback)
                                        <option value="{{ $feedback['id'] }}" data-annotation-id="{{ $feedback['annotation_id'] }}" data-pdf-url="{{ $feedback['pdf_url'] }}">{{ $loop->iteration }} of {{ $feedbackItems->count() }} · {{ $feedback['label'] }}</option>
                                    @endforeach
                                </select>
                                @foreach ($feedbackItems as $feedback)
                                    <div data-revision-comment-body="{{ $feedback['id'] }}" @if (! $loop->first) hidden @endif class="revision-comment-body">
                                        <div class="revision-comment-context">
                                            <p>{{ $feedback['reviewer'] }} <span>· Comment {{ $loop->iteration }} of {{ $feedbackItems->count() }}</span></p>
                                            <p>{{ $feedback['location'] }}</p>
                                        </div>
                                        <span hidden data-revision-modification-status="{{ $feedback['id'] }}" data-annotation-id="{{ $feedback['annotation_id'] }}" data-modified="false" data-reviewed="false"></span>
                                        <blockquote class="revision-reviewer-comment whitespace-pre-line">{{ $feedback['comment'] ?: $feedback['note'] }}</blockquote>
                                        @if ($feedback['quote'])
                                            <p class="text-xs leading-5 text-slate-500 dark:text-slate-400"><span class="font-semibold">In the submitted paper:</span> “{{ $feedback['quote'] }}”</p>
                                        @endif
                                        @if ($feedback['response'])
                                            <x-proposal-revision-response :item="$feedback['response']" :document-type="$documentType" :document-types="$documentTypes" />
                                        @endif
                                    </div>
                                @endforeach
                            @endif
                        </div>
                        <div data-revision-resolution-panel class="revision-resolution-panel">
                            <fieldset>
                                <legend class="sr-only">What will you do with this paper?</legend>
                                <div class="revision-action-options">
                                    <label>
                                        <input type="radio" data-revision-edit-paper name="revision_resolutions[{{ $documentType }}][action]" value="" @checked(! $noChangeSelected)>
                                        <span>Revise this paper</span>
                                    </label>
                                    <label>
                                        <input type="radio" data-revision-no-change name="revision_resolutions[{{ $documentType }}][action]" value="no_change" @checked($noChangeSelected)>
                                        <span>Keep submitted paper</span>
                                    </label>
                                </div>
                                <div data-revision-no-change-details @if (! $noChangeSelected) hidden @endif class="mt-3 space-y-2">
                                    @if ($singleReply)
                                        <input type="hidden" name="revision_resolutions[{{ $documentType }}][explanation]" data-revision-no-change-explanation value="{{ $noChangeExplanation }}">
                                    @else
                                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-200">
                                            Why keep this paper? <span class="font-normal text-slate-500">(required)</span>
                                            <textarea name="revision_resolutions[{{ $documentType }}][explanation]" data-revision-no-change-explanation rows="2" maxlength="1000" @required($noChangeSelected) placeholder="Explain how the submitted paper already addresses the feedback." class="mt-2 block w-full rounded-lg border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-950 dark:text-white">{{ $noChangeExplanation }}</textarea>
                                        </label>
                                    @endif
                                    @if ($noChangeError)
                                        <p class="text-xs text-red-700 dark:text-red-300">{{ $noChangeError }}</p>
                                    @endif
                                    @unless ($singleReply)
                                        <p class="text-xs text-slate-500 dark:text-slate-400">This explanation fills unanswered comments. You can still edit each response.</p>
                                    @endunless
                                </div>
                            </fieldset>
                        </div>
                    </aside>
                </div>
            </div>
        </dialog>
    @else
        <x-proposal-revision-upload :input-name="$inputName" :accept="$accept" :multiple="$multiple" :required="$required" :staged-file="$stagedFile" :label="$label" :document-type="$documentType" :file-errors="$fileErrors" />
    @endif
</article>
