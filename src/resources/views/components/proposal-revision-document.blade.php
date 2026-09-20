@props(['topic', 'documentType', 'fileRevisions' => collect(), 'stagedFile' => null, 'required' => false])

@php
    $multiple = $documentType === 'curriculum_vitae';
    $inputName = $multiple ? 'curricula_vitae' : $documentType;
    $accept = $documentType === 'expense_breakdown' ? '.pdf' : '.doc,.docx,.pdf';
    $label = app(\App\Support\ProposalPaperCatalog::class)->label($documentType) ?? str($documentType)->replace('_', ' ')->title();
    $revisionTargetCatalog = app(\App\Support\ProposalRevisionTargetCatalog::class);
    $paper = app(\App\Support\ProposalPaperCatalog::class)->forDocumentType($documentType);
    $canEmbed = $required && $topic->research_call_id
        && ($paper['mode'] ?? null) === 'generated'
        && (! $multiple || ($fileRevisions->count() === 1 && $fileRevisions->first()->file?->position === 0));
    $editorUrl = $canEmbed ? route('faculty.proposal-drafts.revision', [
        'topic' => $topic, 'document_type' => $documentType, 'revision_embed' => 1,
    ]) : null;
    $fileErrors = $errors->getBag('resubmission')->get($inputName.'*');
    $noChangeSelected = old('revision_resolutions.'.$documentType.'.action') === 'no_change';
    $noChangeExplanation = old('revision_resolutions.'.$documentType.'.explanation', '');
    $noChangeAddressed = $noChangeSelected && filled($noChangeExplanation);
    $noChangeError = $errors->getBag('resubmission')->first('revision_resolutions.'.$documentType.'.explanation');
    $feedbackItems = collect();
    foreach ($fileRevisions as $fileRevision) {
        $revisionFile = $fileRevision->file;
        $annotationVersion = $topic->versions->firstWhere('id', $revisionFile?->proposal_version_id);
        $pdfUrl = $revisionFile && $annotationVersion
            ? route('topics.versions.files.annotations.index', [$topic, $annotationVersion, $revisionFile]).'?revision_embed=1'
            : null;
        $annotations = $fileRevision->annotations->sortBy([['page_number', 'asc'], ['id', 'asc']])->values();
        foreach ($annotations->isEmpty() ? collect([null]) : $annotations as $annotation) {
            $feedbackItems->push([
                'id' => $annotation?->id ?? 'file-'.$fileRevision->id,
                'annotation_id' => $annotation?->id,
                'pdf_url' => $pdfUrl,
                'label' => $annotation
                    ? $annotation->feedbackLabel().' · Page '.$annotation->page_number.' · '.($revisionTargetCatalog->labelFor($revisionFile, $annotation->editor_target) ?? 'Paper feedback')
                    : $fileRevision->original_filename,
                'note' => $fileRevision->revision_note,
                'quote' => $annotation?->selected_text,
                'comment' => $annotation?->comment,
            ]);
        }
    }
@endphp

<article
    data-revision-document="{{ $documentType }}"
    data-revision-label="{{ $label }}"
    data-topic-file-dropzone="{{ $inputName }}"
    x-data="fileDropzone({ accept: @js($accept), maxBytes: 26214400, multiple: @js($multiple) })"
    @paste="paste($event)"
    class="min-w-0 overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900"
>
    <div class="flex flex-wrap items-center justify-between gap-4 p-4">
        <div class="flex min-w-0 items-center gap-3">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-red-50 text-[#7A0019] dark:bg-red-950/40 dark:text-red-300" aria-hidden="true">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3.75h7.5l3 3v13.5H6.75V3.75Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M14.25 3.75v3h3" /></svg>
            </span>
            <div class="min-w-0">
                <h4 class="truncate text-sm font-bold text-gray-950 dark:text-white">{{ $label }}</h4>
                <p class="mt-0.5 text-xs text-slate-400 dark:text-slate-500">Submitted paper · revision requested</p>
            @if ($required)
                <div class="mt-1 flex flex-wrap items-center gap-2">
                    <span data-revision-document-state data-modified="false" data-addressed="{{ $noChangeAddressed ? 'true' : 'false' }}" data-reviewed="false" class="revision-change-state" title="Shows whether this requested document has been reviewed and how it will be resolved in the resubmission.">{{ $noChangeAddressed ? 'Explained — no file change' : 'Review required' }}</span>
                </div>
            @endif
            </div>
        </div>
        @if ($required)
            <button type="button" data-revision-open class="inline-flex min-h-9 shrink-0 items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 text-xs font-bold text-slate-700 transition hover:border-slate-300 hover:text-[#7A0019] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#7A0019] dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200">Open for review<svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14 4h6v6M20 4 4 20M9 4H4v16h16v-5" /></svg></button>
        @endif
    </div>

    @if ($required)
        <dialog data-revision-dialog aria-labelledby="revision-dialog-title-{{ $documentType }}" class="revision-dialog bg-white text-gray-900 dark:bg-slate-900 dark:text-white">
            <header class="revision-dialog-header">
                <div class="min-w-0">
                    <h3 id="revision-dialog-title-{{ $documentType }}" class="truncate text-base font-bold">{{ $label }}</h3>
                    <p class="text-xs text-gray-500 dark:text-slate-400">Submitted PDF and requested changes · Edit your revision alongside</p>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    <button type="button" data-revision-previous aria-label="Previous document" class="rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-slate-700">Previous</button>
                    <button type="button" data-revision-next aria-label="Next document" class="rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-slate-700">Next</button>
                    <button type="button" data-revision-close class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white dark:bg-slate-700">Done</button>
                </div>
            </header>
            <div data-revision-dialog-submit-error hidden role="alert" class="border-b border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/50 dark:text-red-200">
                <p class="font-bold">This document still needs an action before the revision can be sent.</p>
                <p class="mt-1">Make a change, upload a replacement, or select “No file change needed” and provide an explanation.</p>
            </div>
            <div class="revision-dialog-body">
                <div class="revision-document-workspace">
                    <section class="revision-feedback" aria-label="Reviewer feedback and submitted PDF">
                        <div class="revision-comment-strip" data-revision-feedback>
                            @if ($feedbackItems->isNotEmpty())
                                <label for="revision-comment-{{ $documentType }}" class="sr-only">Requested change</label>
                                <select id="revision-comment-{{ $documentType }}" data-revision-comment class="w-full rounded-lg border-gray-300 text-sm dark:border-slate-700 dark:bg-slate-950">
                                    @foreach ($feedbackItems as $feedback)
                                        <option value="{{ $feedback['id'] }}" data-annotation-id="{{ $feedback['annotation_id'] }}" data-pdf-url="{{ $feedback['pdf_url'] }}">{{ $loop->iteration }} of {{ $feedbackItems->count() }} · {{ $feedback['label'] }}</option>
                                    @endforeach
                                </select>
                                @foreach ($feedbackItems as $feedback)
                                    <div data-revision-comment-body="{{ $feedback['id'] }}" @if (! $loop->first) hidden @endif class="mt-3 space-y-2 text-sm leading-6">
                                        <span
                                            data-revision-modification-status="{{ $feedback['id'] }}"
                                            data-annotation-id="{{ $feedback['annotation_id'] }}"
                                            data-modified="false"
                                            data-reviewed="false"
                                            class="revision-change-state"
                                            title="{{ $feedback['annotation_id'] ? 'Shows whether the exact linked editor field differs from the returned version. It does not judge whether the feedback has been fully addressed.' : 'This is paper-level feedback, so the state changes when any editor field changes.' }}"
                                        >Not reviewed yet</span>
                                        @if ($feedback['note'])
                                            <p class="whitespace-pre-line">{{ $feedback['note'] }}</p>
                                        @endif
                                        @if ($feedback['quote'])
                                            <blockquote class="border-l-2 border-red-300 pl-3 text-xs italic text-gray-500 dark:text-slate-400">{{ $feedback['quote'] }}</blockquote>
                                        @endif
                                        @if ($feedback['comment'])
                                            <p class="whitespace-pre-line">{{ $feedback['comment'] }}</p>
                                        @endif
                                    </div>
                                @endforeach
                            @else
                                <p class="text-sm">Review the requested changes to this document.</p>
                            @endif
                            <div class="mt-4 rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-slate-700 dark:bg-slate-800/60">
                                <label class="flex cursor-pointer items-start gap-2 text-sm font-semibold text-gray-800 dark:text-slate-100">
                                    <input
                                        type="checkbox"
                                        name="revision_resolutions[{{ $documentType }}][action]"
                                        value="no_change"
                                        data-revision-no-change
                                        @checked($noChangeSelected)
                                        class="mt-0.5 rounded border-gray-300 text-red-700 focus:ring-red-700 dark:border-slate-600 dark:bg-slate-900"
                                    >
                                    <span>No file change needed</span>
                                </label>
                                <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-slate-400">Use this when the comment is informational, asks for confirmation, or the current file already satisfies it.</p>
                                <div data-revision-no-change-details @if (! $noChangeSelected) hidden @endif class="mt-3">
                                    <label class="block text-xs font-semibold text-gray-700 dark:text-slate-200">
                                        Explanation
                                        <textarea
                                            name="revision_resolutions[{{ $documentType }}][explanation]"
                                            data-revision-no-change-explanation
                                            rows="3"
                                            maxlength="1000"
                                            @required($noChangeSelected)
                                            placeholder="Explain why the submitted file does not need to change"
                                            class="mt-1 block w-full rounded-lg border-gray-300 text-sm dark:border-slate-600 dark:bg-slate-950 dark:text-white"
                                        >{{ $noChangeExplanation }}</textarea>
                                    </label>
                                    @if ($noChangeError)
                                        <p class="mt-2 text-xs font-semibold text-red-700 dark:text-red-300">{{ $noChangeError }}</p>
                                    @endif
                                    <p class="mt-2 text-xs text-amber-700 dark:text-amber-300">Draft edits to this document will not be included when this option is selected.</p>
                                </div>
                            </div>
                        </div>
                        <div class="revision-frame-shell">
                            <div data-revision-pdf-loading role="status" class="revision-frame-loading">
                                <span class="revision-loading-spinner" aria-hidden="true"></span>
                                <span>Loading submitted paper…</span>
                            </div>
                            <iframe data-revision-pdf-frame title="Submitted {{ $label }} with Research Head highlights" class="revision-pdf-frame"></iframe>
                            <p data-revision-pdf-unavailable hidden class="p-5 text-sm">The submitted PDF is unavailable. Use the feedback above to update this document.</p>
                        </div>
                    </section>
                    <section class="revision-editor-panel" aria-label="{{ $label }} revision editor">
                        @if ($canEmbed)
                            <p data-revision-editor-status role="status" class="border-b border-gray-200 px-4 py-2 text-xs text-gray-500 dark:border-slate-700 dark:text-slate-400">Loading editor…</p>
                            <div class="revision-frame-shell">
                                <div data-revision-editor-loading role="status" class="revision-frame-loading">
                                    <span class="revision-loading-spinner" aria-hidden="true"></span>
                                    <span>Loading revision editor…</span>
                                </div>
                                <iframe data-revision-editor-frame src="{{ $editorUrl }}" title="Edit {{ $label }} alongside Research Head feedback" class="revision-editor-frame"></iframe>
                            </div>
                            <details class="revision-upload-alternative border-t border-gray-200 dark:border-slate-700">
                                <summary class="cursor-pointer px-4 py-2 text-xs font-semibold text-gray-600 dark:text-slate-300">Upload a replacement instead</summary>
                                <p class="px-4 pt-2 text-xs text-gray-500">An uploaded file takes precedence over the editor.</p>
                                <x-proposal-revision-upload :input-name="$inputName" :accept="$accept" :multiple="$multiple" :required="$required" :staged-file="$stagedFile" :label="$label" :document-type="$documentType" :file-errors="$fileErrors" />
                            </details>
                        @else
                            <h4 class="px-4 pt-4 text-sm font-bold">Upload your revised {{ $label }}</h4>
                            <x-proposal-revision-upload :input-name="$inputName" :accept="$accept" :multiple="$multiple" :required="$required" :staged-file="$stagedFile" :label="$label" :document-type="$documentType" :file-errors="$fileErrors" />
                        @endif
                    </section>
                </div>
            </div>
        </dialog>
    @else
        <x-proposal-revision-upload :input-name="$inputName" :accept="$accept" :multiple="$multiple" :required="$required" :staged-file="$stagedFile" :label="$label" :document-type="$documentType" :file-errors="$fileErrors" />
    @endif
</article>
