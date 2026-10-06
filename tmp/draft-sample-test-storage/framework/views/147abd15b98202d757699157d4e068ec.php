<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['topic', 'pendingFileRevisions', 'stagedRevisionFiles', 'displayProjectCost', 'commentResponseRows' => []]));

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

foreach (array_filter((['topic', 'pendingFileRevisions', 'stagedRevisionFiles', 'displayProjectCost', 'commentResponseRows' => []]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
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
        \App\Services\CommentResponseFeedback::FORM_RESEARCH_HEAD => 'Research Head feedback',
        \App\Services\CommentResponseFeedback::FORM_CO_EVALUATOR => 'Co-evaluator feedback',
    ];
?>

<form
    id="submit-revision"
    action="<?php echo e(route('faculty.topics.resubmit', $topic)); ?>"
    method="POST"
    enctype="multipart/form-data"
    novalidate
    aria-labelledby="faculty-revision-heading"
    data-faculty-revision-required
    data-revision-workspace="<?php echo e($topic->id); ?>"
    data-revision-start-step="<?php echo e($metadataHasErrors ? 3 : ($revisionErrors->any() ? 2 : 1)); ?>"
    data-confirm-title="Submit this revision to the Research Head?"
    data-confirm-text="Edited papers will generate PDFs for the new version. Uploaded replacements and unchanged papers will be included too."
    data-confirm-button="Submit revision"
    data-confirm-icon="question"
    @invalid.capture="if ($event.target.closest('[data-revision-proposal-details-fields]')) window.dispatchEvent(new CustomEvent('open-revision-proposal-details'))"
    class="space-y-4"
>
    <?php echo csrf_field(); ?>
    <?php echo method_field('PATCH'); ?>
    <input type="hidden" name="redirect_to" value="topic">
    <input type="hidden" name="topic_tab" value="review">
    <input type="hidden" name="revision_draft_id" value="<?php echo e($topic->revisionDraft?->id); ?>">
    <h2 id="faculty-revision-heading" class="sr-only">Prepare the corrected project</h2>

    <nav data-revision-progress-navigation aria-label="Revision workflow" class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900 sm:p-5">
        <p data-revision-progress role="status" aria-live="polite" aria-atomic="true" class="text-sm font-semibold text-slate-900 dark:text-slate-100">Step 1 of 4 · Read feedback</p>
        <ol class="mt-4 grid grid-cols-4" aria-label="Revision steps">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = ['Read feedback', 'Revise and respond', 'Confirm details', 'Submit']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $stepLabel): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <li data-revision-progress-step="<?php echo e($loop->iteration); ?>" data-state="<?php echo e($loop->first ? 'current' : 'upcoming'); ?>" <?php if($loop->first): ?> aria-current="step" <?php endif; ?> class="group relative flex min-w-0 flex-col items-center gap-3 rounded-lg px-1 py-3 data-[state=current]:bg-brand-wash dark:data-[state=current]:bg-rose-950/40 md:px-3">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($loop->last)): ?>
                        <span data-revision-progress-connector aria-hidden="true" class="absolute left-[calc(50%+1.25rem)] right-[calc(-50%+1.25rem)] top-7 z-10 h-px bg-slate-200 group-data-[state=complete]:bg-brand dark:bg-slate-700 dark:group-data-[state=complete]:bg-rose-400"></span>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <span data-revision-progress-mark aria-hidden="true" class="relative z-20 flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-slate-300 bg-white text-xs font-semibold text-slate-500 group-data-[state=current]:border-brand group-data-[state=current]:bg-brand group-data-[state=current]:text-white group-data-[state=complete]:border-brand group-data-[state=complete]:text-brand dark:border-slate-600 dark:bg-slate-900 dark:text-slate-400 dark:group-data-[state=current]:border-rose-400 dark:group-data-[state=current]:bg-rose-400 dark:group-data-[state=current]:text-slate-950 dark:group-data-[state=complete]:border-rose-400 dark:group-data-[state=complete]:text-rose-300"><?php echo e($loop->iteration); ?></span>
                    <span data-revision-progress-label class="sr-only text-center text-xs leading-5 text-slate-500 group-data-[state=current]:font-bold group-data-[state=current]:text-brand group-data-[state=complete]:text-slate-800 dark:text-slate-400 dark:group-data-[state=current]:text-rose-200 dark:group-data-[state=complete]:text-slate-200 md:not-sr-only md:min-h-10"><?php echo e($stepLabel); ?></span>
                    <span data-revision-progress-description class="sr-only"><?php echo e($loop->first ? 'Current step' : 'Upcoming'); ?></span>
                </li>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </ol>
    </nav>

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
            <span class="inline-flex w-fit rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-500 dark:bg-slate-800 dark:text-slate-300"><?php echo e(count($commentResponseRows)); ?> <?php echo e(Str::plural('comment', count($commentResponseRows))); ?></span>
        </header>

        <div class="space-y-4 p-5 sm:p-6">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($commentResponseRows !== []): ?>
                <input type="hidden" name="feedback_review_id" value="<?php echo e($latestRevisionReview?->id); ?>">
                <div class="space-y-4">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $commentResponseGroups; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $source => $sourceRows): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <section data-comment-response-source="<?php echo e($source); ?>" class="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-700" aria-label="<?php echo e($commentResponseLabels[$source] ?? 'Reviewer feedback'); ?> responses">
                            <div class="flex flex-col gap-3 border-b border-slate-200 bg-slate-50/70 px-4 py-3 dark:border-slate-700 dark:bg-slate-800/50 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                                <div>
                                    <h4 class="text-sm font-bold text-slate-900 dark:text-white"><?php echo e($commentResponseLabels[$source] ?? 'Reviewer feedback'); ?></h4>
                                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400"><?php echo e($sourceRows->count()); ?> <?php echo e(Str::plural('comment', $sourceRows->count())); ?></p>
                                </div>
                                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('generateCommentResponseForm', $topic)): ?>
                                    <button type="button" data-comment-response-preview aria-haspopup="dialog" @click="$dispatch('open-modal', 'revision-comment-response-<?php echo e($topic->id); ?>-<?php echo e($source); ?>')" class="inline-flex min-h-11 w-fit items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:text-slate-950 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#7A0019] focus-visible:ring-offset-2 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-slate-500 dark:hover:text-white dark:focus-visible:ring-offset-slate-900"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2 12s3-7 10-7 10 7-3 7-10 7S2 12 2 12Z" /><circle cx="12" cy="12" r="3" /></svg>Preview Comment Response Paper</button>
                                <?php endif; ?>
                            </div>
                            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $sourceRows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                    <article class="space-y-4 p-4 sm:p-5" data-revision-feedback-item>
                                        <header class="space-y-1">
                                            <p class="text-xs leading-5 text-slate-500 dark:text-slate-400"><?php echo e($item['location']); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($item['stage'])): ?> · <?php echo e(\App\Services\CommentResponseFeedback::STAGE_LABELS[$item['stage']] ?? ''); ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></p>
                                        </header>
                                        <blockquote class="whitespace-pre-line rounded-r-lg border-l-[3px] border-slate-300 bg-slate-50 px-4 py-3 text-sm leading-6 text-slate-800 dark:border-slate-600 dark:bg-slate-800/50 dark:text-slate-100"><?php echo e($item['comment']); ?></blockquote>

                                    </article>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            </div>
                        </section>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </div>
            <?php else: ?>
                <p class="rounded-xl bg-slate-50 px-4 py-5 text-center text-sm text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">No written reviewer comments were recorded for this revision round.</p>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
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
                    <p class="mt-1 text-sm leading-6 text-slate-500 dark:text-slate-400"><?php echo e($revisionGroups->isEmpty() ? 'No paper changes were requested.' : 'Open a paper to edit it and reply to its reviewer comments in the same workspace.'); ?></p>
                </div>
            </div>
        </header>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $revisionErrors->get('feedback_responses*'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $feedbackErrors): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = (array) $feedbackErrors; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $feedbackError): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <p role="alert" class="mx-5 mb-3 rounded-lg bg-red-50 p-3 text-sm text-red-700 dark:bg-red-950/30 dark:text-red-300"><?php echo e($feedbackError); ?></p>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        <div id="requested-papers-content" class="space-y-3 border-t border-slate-100 p-5 dark:border-slate-800 sm:p-6" data-requested-revision-files>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $revisionGroups; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $documentType => $fileRevisions): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <?php if (isset($component)) { $__componentOriginal04ac61af147909ed39a4334998920602 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal04ac61af147909ed39a4334998920602 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-revision-document','data' => ['topic' => $topic,'documentType' => $documentType,'fileRevisions' => $fileRevisions,'stagedFile' => $stagedRevisionFiles->get($documentType),'required' => true,'commentResponseRows' => collect($commentResponseRows)->filter(fn ($item) => ($responseDocumentTypes[$item['key']] ?? null) === $documentType),'documentTypes' => $revisionGroups->keys()]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-revision-document'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['topic' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($topic),'document-type' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($documentType),'file-revisions' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($fileRevisions),'staged-file' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($stagedRevisionFiles->get($documentType)),'required' => true,'comment-response-rows' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(collect($commentResponseRows)->filter(fn ($item) => ($responseDocumentTypes[$item['key']] ?? null) === $documentType)),'document-types' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($revisionGroups->keys())]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal04ac61af147909ed39a4334998920602)): ?>
<?php $attributes = $__attributesOriginal04ac61af147909ed39a4334998920602; ?>
<?php unset($__attributesOriginal04ac61af147909ed39a4334998920602); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal04ac61af147909ed39a4334998920602)): ?>
<?php $component = $__componentOriginal04ac61af147909ed39a4334998920602; ?>
<?php unset($__componentOriginal04ac61af147909ed39a4334998920602); ?>
<?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                <p class="rounded-xl bg-slate-50 px-4 py-5 text-center text-sm text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">Your current papers will carry forward. Reply to the feedback below, then confirm the proposal details.</p>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($generalResponseRows->isNotEmpty()): ?>
            <div class="space-y-4 border-t border-slate-100 p-5 dark:border-slate-800 sm:p-6" data-revision-general-responses>
                <h4 class="text-sm font-semibold text-slate-900 dark:text-white">Overall feedback</h4>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $generalResponseRows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <article class="space-y-3 rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                        <p class="text-xs text-slate-500 dark:text-slate-400"><?php echo e($item['location']); ?></p>
                        <blockquote class="whitespace-pre-line rounded-lg bg-slate-50 p-3 text-sm leading-6 text-slate-800 dark:bg-slate-800 dark:text-slate-100"><?php echo e($item['comment']); ?></blockquote>
                        <?php if (isset($component)) { $__componentOriginal674ad0dd6f4ee476d1b43d29ba44864c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal674ad0dd6f4ee476d1b43d29ba44864c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-revision-response','data' => ['item' => $item,'documentTypes' => $revisionGroups->keys()]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-revision-response'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['item' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($item),'document-types' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($revisionGroups->keys())]); ?>
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
                    </article>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
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
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-200">Project title<input name="title" value="<?php echo e(old('title', $topic->title)); ?>" required class="mt-2 block w-full rounded-lg border-slate-200 text-sm focus:border-[#7A0019] focus:ring-[#7A0019] dark:border-slate-700 dark:bg-slate-950 dark:text-white"></label>
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-200">Total project cost<input name="estimated_budget" type="number" min="0" max="<?php echo e($topic->researchCall?->budgetCeiling() ?? \App\Models\ResearchCall::MAXIMUM_BUDGET); ?>" step="0.01" value="<?php echo e(old('estimated_budget', $displayProjectCost)); ?>" required class="mt-2 block w-full rounded-lg border-slate-200 text-sm focus:border-[#7A0019] focus:ring-[#7A0019] dark:border-slate-700 dark:bg-slate-950 dark:text-white"></label>
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 md:col-span-2">Description<textarea name="description" rows="3" class="mt-2 block w-full rounded-lg border-slate-200 text-sm leading-6 focus:border-[#7A0019] focus:ring-[#7A0019] dark:border-slate-700 dark:bg-slate-950 dark:text-white"><?php echo e(old('description', $topic->description)); ?></textarea></label>
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-200">Duration in months<input name="estimated_duration_months" type="number" min="1" max="120" value="<?php echo e(old('estimated_duration_months', $topic->estimated_duration_months)); ?>" required class="mt-2 block w-full rounded-lg border-slate-200 text-sm focus:border-[#7A0019] focus:ring-[#7A0019] dark:border-slate-700 dark:bg-slate-950 dark:text-white"></label>
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
                <div class="sm:col-span-2"><dt class="text-slate-500 dark:text-slate-400">Project title</dt><dd data-revision-summary-title class="mt-1 font-semibold text-slate-900 dark:text-white"><?php echo e(old('title', $topic->title)); ?></dd></div>
                <div><dt class="text-slate-500 dark:text-slate-400">Paper revisions</dt><dd class="mt-1 font-semibold text-slate-900 dark:text-white"><?php echo e($revisionGroups->count()); ?> <?php echo e(Str::plural('paper', $revisionGroups->count())); ?> addressed</dd></div>
                <div><dt class="text-slate-500 dark:text-slate-400">Comment responses</dt><dd class="mt-1 font-semibold text-slate-900 dark:text-white"><?php echo e(count($commentResponseRows)); ?> <?php echo e(Str::plural('response', count($commentResponseRows))); ?> completed</dd></div>
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

<?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('generateCommentResponseForm', $topic)): ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $commentResponseGroups; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $source => $sourceRows): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
        <?php if (isset($component)) { $__componentOriginal9f64f32e90b9102968f2bc548315018c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9f64f32e90b9102968f2bc548315018c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.modal','data' => ['name' => 'revision-comment-response-'.e($topic->id).'-'.e($source).'','maxWidth' => '6xl','focusable' => true,'class' => '!z-[140]','dataFacultyCommentResponsePreviewModal' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('modal'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'revision-comment-response-'.e($topic->id).'-'.e($source).'','maxWidth' => '6xl','focusable' => true,'class' => '!z-[140]','data-faculty-comment-response-preview-modal' => true]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

            <template x-if="show">
                <section role="dialog" aria-modal="true" aria-labelledby="revision-comment-response-heading-<?php echo e($topic->id); ?>-<?php echo e($source); ?>">
                    <header class="flex items-start justify-between gap-4 border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                        <div>
                            <h3 id="revision-comment-response-heading-<?php echo e($topic->id); ?>-<?php echo e($source); ?>" class="text-base font-bold text-gray-950 dark:text-white"><?php echo e($source === \App\Services\CommentResponseFeedback::FORM_CO_EVALUATOR ? 'Co-evaluator' : 'Research Head'); ?> Comment Response paper</h3>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Responses entered on this page are included after you submit the revision.</p>
                        </div>
                        <button type="button" @click="$dispatch('close')" class="inline-flex min-h-11 shrink-0 items-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-bold text-gray-800 hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-700 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100 dark:hover:bg-gray-800">Close preview</button>
                    </header>
                    <?php if (isset($component)) { $__componentOriginal13c1f2a64fc7e3eb9a4f045c0d853353 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal13c1f2a64fc7e3eb9a4f045c0d853353 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-revision-pdf','data' => ['configuration' => ['pdfUrl' => route('faculty.topics.comment-response-form.pdf', ['topic' => $topic, 'source' => $source, 'review' => $latestRevisionReview?->id]), 'annotations' => [], 'canAnnotate' => false],'loadingLabel' => 'Loading Comment Response paper…','viewerLabel' => 'Comment Response paper','class' => '!h-[75dvh]']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-revision-pdf'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['configuration' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(['pdfUrl' => route('faculty.topics.comment-response-form.pdf', ['topic' => $topic, 'source' => $source, 'review' => $latestRevisionReview?->id]), 'annotations' => [], 'canAnnotate' => false]),'loading-label' => 'Loading Comment Response paper…','viewer-label' => 'Comment Response paper','class' => '!h-[75dvh]']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal13c1f2a64fc7e3eb9a4f045c0d853353)): ?>
<?php $attributes = $__attributesOriginal13c1f2a64fc7e3eb9a4f045c0d853353; ?>
<?php unset($__attributesOriginal13c1f2a64fc7e3eb9a4f045c0d853353); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal13c1f2a64fc7e3eb9a4f045c0d853353)): ?>
<?php $component = $__componentOriginal13c1f2a64fc7e3eb9a4f045c0d853353; ?>
<?php unset($__componentOriginal13c1f2a64fc7e3eb9a4f045c0d853353); ?>
<?php endif; ?>
                </section>
            </template>
         <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9f64f32e90b9102968f2bc548315018c)): ?>
<?php $attributes = $__attributesOriginal9f64f32e90b9102968f2bc548315018c; ?>
<?php unset($__attributesOriginal9f64f32e90b9102968f2bc548315018c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9f64f32e90b9102968f2bc548315018c)): ?>
<?php $component = $__componentOriginal9f64f32e90b9102968f2bc548315018c; ?>
<?php unset($__componentOriginal9f64f32e90b9102968f2bc548315018c); ?>
<?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
<?php endif; ?>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views/components/proposal-revision-form.blade.php ENDPATH**/ ?>