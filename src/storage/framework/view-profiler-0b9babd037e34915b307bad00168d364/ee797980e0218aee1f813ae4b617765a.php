<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['topic', 'documentType', 'fileRevisions' => collect(), 'stagedFile' => null, 'required' => false, 'commentResponseRows' => collect(), 'documentTypes' => collect()]));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Illuminate\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter((['topic', 'documentType', 'fileRevisions' => collect(), 'stagedFile' => null, 'required' => false, 'commentResponseRows' => collect(), 'documentTypes' => collect()]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
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
?>

<article
    data-revision-document="<?php echo e($documentType); ?>"
    data-revision-label="<?php echo e($label); ?>"
    <?php if($singleReply): ?> data-revision-single-reply <?php endif; ?>
    <?php if($stagedFile): ?>
        data-revision-staged-pdf-url="<?php echo e(route('faculty.proposal-drafts.revision-files.show', [$stagedFile->proposal_draft_id, $stagedFile])); ?>"
    <?php endif; ?>
    data-revision-preview-upload-url="<?php echo e(route('faculty.topics.revision.preview', $topic)); ?>"
    data-topic-file-dropzone="<?php echo e($inputName); ?>"
    <?php if(! $canEmbed): ?>
        x-data="fileDropzone({ accept: <?php echo \Illuminate\Support\Js::from($accept)->toHtml() ?>, maxBytes: 26214400, multiple: <?php echo \Illuminate\Support\Js::from($multiple)->toHtml() ?> })"
        @paste="paste($event)"
    <?php endif; ?>
    class="min-w-0 overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900"
>
    <div class="flex flex-wrap items-center justify-between gap-4 p-4">
        <div class="flex min-w-0 items-center gap-3">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-red-50 text-[#7A0019] dark:bg-red-950/40 dark:text-red-300" aria-hidden="true">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3.75h7.5l3 3v13.5H6.75V3.75Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M14.25 3.75v3h3" /></svg>
            </span>
            <div class="min-w-0">
                <h4 class="truncate text-sm font-bold text-gray-950 dark:text-white"><?php echo e($label); ?></h4>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($required): ?>
                <span hidden data-revision-document-state data-modified="false" data-addressed="<?php echo e($noChangeAddressed ? 'true' : 'false'); ?>" data-reviewed="false"></span>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($required): ?>
            <div class="flex shrink-0 items-center gap-3">
                <span data-revision-resolved-cue <?php if(! $noChangeAddressed): ?> hidden <?php endif; ?> class="text-emerald-700 dark:text-emerald-400" title="Revision action recorded for this paper">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path stroke-linecap="round" stroke-linejoin="round" d="m8 12 2.5 2.5L16 9" /></svg>
                    <span class="sr-only">Revision action recorded</span>
                </span>
                <button type="button" data-revision-open class="inline-flex min-h-10 shrink-0 items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 text-xs font-bold text-slate-700 transition hover:border-slate-300 hover:text-[#7A0019] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#7A0019] dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200">Revise paper<svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14 4h6v6M20 4 4 20M9 4H4v16h16v-5" /></svg></button>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($required): ?>
        <dialog data-revision-dialog aria-labelledby="revision-dialog-title-<?php echo e($documentType); ?>" class="revision-dialog bg-white text-gray-900 dark:bg-slate-900 dark:text-white">
            <header class="revision-dialog-header">
                <div class="min-w-0">
                    <h3 id="revision-dialog-title-<?php echo e($documentType); ?>" class="truncate text-lg font-bold"><?php echo e($label); ?></h3>
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
                    <section class="revision-feedback" data-revision-reference id="revision-reference-<?php echo e($documentType); ?>" aria-label="Paper reference">
                        <header class="revision-reference-heading">
                            <div class="revision-paper-tabs" role="group" aria-label="Paper version">
                                <button type="button" data-revision-preview-close aria-pressed="true">Submitted</button>
                                <button type="button" data-revision-preview-open aria-controls="revision-preview-<?php echo e($documentType); ?>" aria-pressed="false" aria-expanded="false">Revised</button>
                            </div>
                            <div class="flex items-center justify-between gap-2"><p class="revision-reference-hint text-sm text-slate-500 dark:text-slate-400">Click the paper to enlarge it.</p><button type="button" data-revision-reference-dismiss>Minimize preview</button></div>
                        </header>
                        <div data-revision-submitted-panel data-revision-paper-open role="button" tabindex="0" aria-label="Enlarge submitted <?php echo e($label); ?>" aria-controls="revision-reference-<?php echo e($documentType); ?>" aria-expanded="false" class="revision-frame-shell">
                            <div data-revision-pdf-loading role="status" class="revision-frame-loading">
                                <span class="revision-loading-spinner" aria-hidden="true"></span>
                                <span>Loading submitted paper…</span>
                            </div>
                            <iframe data-revision-pdf-frame title="Submitted <?php echo e($label); ?> with reviewer highlights" class="revision-pdf-frame"></iframe>
                            <p data-revision-pdf-unavailable hidden class="p-5 text-sm">The submitted PDF is unavailable. Use the reviewer feedback to update this paper.</p>
                        </div>
                        <div data-revision-preview-panel id="revision-preview-<?php echo e($documentType); ?>" hidden class="revision-preview-panel">
                            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 p-3 dark:border-slate-700">
                                <p data-revision-preview-status role="status" aria-live="polite" class="text-xs text-slate-500 dark:text-slate-400">Current revision</p>
                                <select data-revision-preview-file hidden aria-label="Replacement file to preview" class="w-full rounded-lg border-slate-300 text-xs dark:border-slate-700 dark:bg-slate-950"></select>
                                <button type="button" data-revision-preview-refresh class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200">Refresh preview</button>
                            </div>
                            <p data-revision-preview-stale hidden role="status" class="bg-amber-50 p-3 text-xs text-amber-800 dark:bg-amber-950/40 dark:text-amber-200">You have newer edits. Refresh to compare them.</p>
                            <p data-revision-preview-error hidden role="alert" class="p-3 text-xs text-red-700 dark:text-red-300"></p>
                            <div data-revision-paper-open role="button" tabindex="0" aria-label="Enlarge revised <?php echo e($label); ?>" aria-controls="revision-reference-<?php echo e($documentType); ?>" aria-expanded="false" class="revision-preview-paper">
                                <iframe data-revision-preview-frame hidden title="Revised <?php echo e($label); ?> preview" class="revision-preview-frame"></iframe>
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
                    <section class="revision-editor-panel" aria-label="<?php echo e($label); ?> revision editor">
                        <div class="revision-editor-heading">
                            <p class="text-base font-semibold text-slate-700 dark:text-slate-200">Your paper</p>
                            <div class="revision-editor-actions">
                                <button type="button" data-revision-reference-toggle aria-expanded="false">Show paper</button>
                                <button type="button" data-revision-feedback-toggle aria-expanded="false" aria-controls="revision-feedback-<?php echo e($documentType); ?>">Feedback &amp; reply<span class="revision-feedback-count"><?php echo e($feedbackItems->count()); ?></span></button>
                            </div>
                        </div>
                        <p data-revision-keep-notice hidden class="border-b border-slate-200 bg-slate-50 px-4 py-3 text-xs text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">The submitted paper will be kept. Choose Revise this paper to continue editing.</p>
                        <div data-revision-editor-content class="revision-editor-content">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canEmbed): ?>
                            <p data-revision-editor-status role="status" class="border-b border-gray-200 px-4 py-2 text-xs text-gray-500 dark:border-slate-700 dark:text-slate-400">Loading editor…</p>
                            <div class="revision-frame-shell">
                                <div data-revision-editor-loading role="status" class="revision-frame-loading">
                                    <span class="revision-loading-spinner" aria-hidden="true"></span>
                                    <span>Loading revision editor…</span>
                                </div>
                                <iframe data-revision-editor-frame data-revision-editor-src="<?php echo e($editorUrl); ?>" title="Edit <?php echo e($label); ?> alongside Research Head feedback" class="revision-editor-frame"></iframe>
                            </div>
                        <?php else: ?>
                            <h4 class="px-4 pt-4 text-sm font-bold">Upload your revised <?php echo e($label); ?></h4>
                            <?php if (isset($component)) { $__componentOriginalc78365cc78a20fda82a5818134fbb280 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc78365cc78a20fda82a5818134fbb280 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-revision-upload','data' => ['inputName' => $inputName,'accept' => $accept,'multiple' => $multiple,'required' => $required,'stagedFile' => $stagedFile,'label' => $label,'documentType' => $documentType,'fileErrors' => $fileErrors]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-revision-upload'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['input-name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($inputName),'accept' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($accept),'multiple' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($multiple),'required' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($required),'staged-file' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($stagedFile),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($label),'document-type' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($documentType),'file-errors' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($fileErrors)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalc78365cc78a20fda82a5818134fbb280)): ?>
<?php $attributes = $__attributesOriginalc78365cc78a20fda82a5818134fbb280; ?>
<?php unset($__attributesOriginalc78365cc78a20fda82a5818134fbb280); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc78365cc78a20fda82a5818134fbb280)): ?>
<?php $component = $__componentOriginalc78365cc78a20fda82a5818134fbb280; ?>
<?php unset($__componentOriginalc78365cc78a20fda82a5818134fbb280); ?>
<?php endif; ?>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    </section>
                    <aside class="revision-feedback-and-response" data-revision-feedback-panel id="revision-feedback-<?php echo e($documentType); ?>" aria-label="Reviewer feedback and your response">
                        <header class="revision-response-heading">
                            <h4>Feedback &amp; reply</h4>
                            <button type="button" data-revision-feedback-dismiss aria-label="Close feedback panel"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18" /></svg></button>
                        </header>
                        <div class="revision-comment-strip" data-revision-feedback>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($feedbackItems->isNotEmpty()): ?>
                                <label for="revision-comment-<?php echo e($documentType); ?>" class="sr-only">Reviewer comment</label>
                                <select id="revision-comment-<?php echo e($documentType); ?>" data-revision-comment <?php if($feedbackItems->count() === 1): ?> hidden <?php endif; ?> class="w-full rounded-lg border-slate-300 text-xs dark:border-slate-700 dark:bg-slate-950">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $feedbackItems; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $feedback): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                        <option value="<?php echo e($feedback['id']); ?>" data-annotation-id="<?php echo e($feedback['annotation_id']); ?>" data-pdf-url="<?php echo e($feedback['pdf_url']); ?>"><?php echo e($loop->iteration); ?> of <?php echo e($feedbackItems->count()); ?> · <?php echo e($feedback['label']); ?></option>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                </select>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $feedbackItems; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $feedback): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                    <div data-revision-comment-body="<?php echo e($feedback['id']); ?>" <?php if(! $loop->first): ?> hidden <?php endif; ?> class="revision-comment-body">
                                        <div class="revision-comment-context">
                                            <p><?php echo e($feedback['reviewer']); ?> <span>· Comment <?php echo e($loop->iteration); ?> of <?php echo e($feedbackItems->count()); ?></span></p>
                                            <p><?php echo e($feedback['location']); ?></p>
                                        </div>
                                        <span hidden data-revision-modification-status="<?php echo e($feedback['id']); ?>" data-annotation-id="<?php echo e($feedback['annotation_id']); ?>" data-modified="false" data-reviewed="false"></span>
                                        <blockquote class="revision-reviewer-comment whitespace-pre-line"><?php echo e($feedback['comment'] ?: $feedback['note']); ?></blockquote>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($feedback['quote']): ?>
                                            <p class="text-xs leading-5 text-slate-500 dark:text-slate-400"><span class="font-semibold">In the submitted paper:</span> “<?php echo e($feedback['quote']); ?>”</p>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($feedback['response']): ?>
                                            <?php if (isset($component)) { $__componentOriginal674ad0dd6f4ee476d1b43d29ba44864c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal674ad0dd6f4ee476d1b43d29ba44864c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-revision-response','data' => ['item' => $feedback['response'],'documentType' => $documentType,'documentTypes' => $documentTypes]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-revision-response'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['item' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($feedback['response']),'document-type' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($documentType),'document-types' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($documentTypes)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal674ad0dd6f4ee476d1b43d29ba44864c)): ?>
<?php $attributes = $__attributesOriginal674ad0dd6f4ee476d1b43d29ba44864c; ?>
<?php unset($__attributesOriginal674ad0dd6f4ee476d1b43d29ba44864c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal674ad0dd6f4ee476d1b43d29ba44864c)): ?>
<?php $component = $__componentOriginal674ad0dd6f4ee476d1b43d29ba44864c; ?>
<?php unset($__componentOriginal674ad0dd6f4ee476d1b43d29ba44864c); ?>
<?php endif; ?>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </div>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                        <div data-revision-resolution-panel class="revision-resolution-panel">
                            <fieldset>
                                <legend class="sr-only">What will you do with this paper?</legend>
                                <div class="revision-action-options">
                                    <label>
                                        <input type="radio" data-revision-edit-paper name="revision_resolutions[<?php echo e($documentType); ?>][action]" value="" <?php if(! $noChangeSelected): echo 'checked'; endif; ?>>
                                        <span>Revise this paper</span>
                                    </label>
                                    <label>
                                        <input type="radio" data-revision-no-change name="revision_resolutions[<?php echo e($documentType); ?>][action]" value="no_change" <?php if($noChangeSelected): echo 'checked'; endif; ?>>
                                        <span>Keep submitted paper</span>
                                    </label>
                                </div>
                                <div data-revision-no-change-details <?php if(! $noChangeSelected): ?> hidden <?php endif; ?> class="mt-3 space-y-2">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($singleReply): ?>
                                        <input type="hidden" name="revision_resolutions[<?php echo e($documentType); ?>][explanation]" data-revision-no-change-explanation value="<?php echo e($noChangeExplanation); ?>">
                                    <?php else: ?>
                                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-200">
                                            Why keep this paper? <span class="font-normal text-slate-500">(required)</span>
                                            <textarea name="revision_resolutions[<?php echo e($documentType); ?>][explanation]" data-revision-no-change-explanation rows="2" maxlength="1000" <?php if($noChangeSelected): echo 'required'; endif; ?> placeholder="Explain how the submitted paper already addresses the feedback." class="mt-2 block w-full rounded-lg border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-950 dark:text-white"><?php echo e($noChangeExplanation); ?></textarea>
                                        </label>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($noChangeError): ?>
                                        <p class="text-xs text-red-700 dark:text-red-300"><?php echo e($noChangeError); ?></p>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($singleReply)): ?>
                                        <p class="text-xs text-slate-500 dark:text-slate-400">This explanation fills unanswered comments. You can still edit each response.</p>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </div>
                            </fieldset>
                        </div>
                    </aside>
                </div>
            </div>
        </dialog>
    <?php else: ?>
        <?php if (isset($component)) { $__componentOriginalc78365cc78a20fda82a5818134fbb280 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc78365cc78a20fda82a5818134fbb280 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-revision-upload','data' => ['inputName' => $inputName,'accept' => $accept,'multiple' => $multiple,'required' => $required,'stagedFile' => $stagedFile,'label' => $label,'documentType' => $documentType,'fileErrors' => $fileErrors]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-revision-upload'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['input-name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($inputName),'accept' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($accept),'multiple' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($multiple),'required' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($required),'staged-file' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($stagedFile),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($label),'document-type' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($documentType),'file-errors' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($fileErrors)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalc78365cc78a20fda82a5818134fbb280)): ?>
<?php $attributes = $__attributesOriginalc78365cc78a20fda82a5818134fbb280; ?>
<?php unset($__attributesOriginalc78365cc78a20fda82a5818134fbb280); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc78365cc78a20fda82a5818134fbb280)): ?>
<?php $component = $__componentOriginalc78365cc78a20fda82a5818134fbb280; ?>
<?php unset($__componentOriginalc78365cc78a20fda82a5818134fbb280); ?>
<?php endif; ?>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</article>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\components\proposal-revision-document.blade.php ENDPATH**/ ?>