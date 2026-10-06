<?php
    $revisionFiles = $files->whereNotIn('document_type', [
        \App\Models\ProposalVersionFile::TYPE_HEAD_UPLOAD,
        ...\App\Models\ProposalVersionFile::GENERATED_ASSESSMENT_FORM_TYPES,
    ]);
    $oldRevisionFileIds = old('revision_file_ids');
    $disableUnlessRevision = $disableUnlessRevision ?? false;
    $decisionFormId = $decisionFormId ?? null;
    $showReviewChecks = $showReviewChecks ?? false;
    $readOnlyReview = $readOnlyReview ?? false;
    $latestRevisionRequest = (($prioritizeRevisedFiles ?? false) || $readOnlyReview)
        ? $topic->reviews
            ->where('decision', 'revision_requested')
            ->where('review_stage', $topic->review_stage)
            ->sortByDesc('id')
            ->first()
        : null;
    $sentRevisionFiles = $readOnlyReview
        ? ($latestRevisionRequest?->fileRevisions ?? collect())->keyBy('proposal_version_file_id')
        : collect();
    $sentRevisionIds = $sentRevisionFiles->pluck('id');
    $requestedRevisions = ($latestRevisionRequest?->fileRevisions ?? collect())
        ->whereIn('resolved_by_version_file_id', $revisionFiles->pluck('id'))
        ->keyBy('resolved_by_version_file_id');
    $revisedPapers = $revisionFiles->whereIn('id', $requestedRevisions->keys());
    if ($readOnlyReview || $revisedPapers->isEmpty()) {
        $fileGroups = ['all' => $revisionFiles];
    } else {
        $fileGroups = ['revised' => $revisedPapers, 'other' => $revisionFiles->whereNotIn('id', $requestedRevisions->keys())];
    }
?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($revisionFiles->isNotEmpty()): ?>
    <div x-data="{ selectedFiles: {} }" data-revision-file-list data-read-only-review="<?php echo e($readOnlyReview ? 'true' : 'false'); ?>">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showGuidance ?? true): ?>
            <p class="mb-4 text-sm leading-6 text-gray-600 dark:text-gray-300"><?php echo e($topic->review_stage === 'lrec' ? 'Papers with saved highlights are included in the revision request. Select additional papers to update using committee comments as instructions.' : 'Open a document to review it. Saving a highlight with a comment includes that paper when you send the revision request.'); ?></p>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $fileGroups; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $group => $groupFiles): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <?php if($groupFiles->isEmpty()) continue; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($group === 'other'): ?>
                <?php
                    $otherPapersOpen = collect(old('revision_file_ids', []))->intersect($groupFiles->pluck('id'))->isNotEmpty()
                        || $groupFiles->contains(fn ($file) => $file->annotations->whereNull('topic_review_file_revision_id')->isNotEmpty());
                ?>
                <div class="mt-4" x-data="{ otherPapersOpen: <?php echo \Illuminate\Support\Js::from($otherPapersOpen)->toHtml() ?> }" data-other-submitted-papers>
                    <button type="button" @click="otherPapersOpen = !otherPapersOpen" :aria-expanded="otherPapersOpen" aria-controls="other-submitted-papers-<?php echo e($latestVersion->id); ?>" class="inline-flex min-h-11 items-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-red-700 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-200 dark:hover:bg-gray-900">
                        <span x-text="otherPapersOpen ? 'Hide other submitted papers' : 'Show other submitted papers'">Show other submitted papers</span> <span class="ml-1">(<?php echo e($groupFiles->count()); ?>)</span>
                    </button>
                    <div id="other-submitted-papers-<?php echo e($latestVersion->id); ?>" x-show="otherPapersOpen" <?php if(! $otherPapersOpen): ?> x-cloak <?php endif; ?> class="mt-3">
            <?php elseif($group === 'revised'): ?>
                <h4 class="mb-2 text-sm font-semibold text-gray-900 dark:text-white">Papers returned for review (<?php echo e($groupFiles->count()); ?>)</h4>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <ul role="list" class="divide-y divide-gray-200 border-y border-gray-200 dark:divide-gray-800 dark:border-gray-800">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $groupFiles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $file): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <?php
                    $draftAnnotationCount = $file->annotations->whereNull('topic_review_file_revision_id')->count();
                    $reviewCommentCount = $readOnlyReview
                        ? $file->annotations->whereIn('topic_review_file_revision_id', $sentRevisionIds)->count()
                        : $draftAnnotationCount;
                    $fileAvailable = $availableSubmittedFileIds->contains($file->id);
                    $fileViewable = $viewableSubmittedFileIds->contains($file->id);
                    $canSelectHighlightedPdf = $topic->review_stage === 'lrec' || ! $fileViewable || $draftAnnotationCount > 0;
                    $isSelected = $readOnlyReview
                        ? $sentRevisionFiles->has($file->id)
                        : ($draftAnnotationCount > 0
                            || (is_array($oldRevisionFileIds) && in_array($file->id, $oldRevisionFileIds) && $canSelectHighlightedPdf));
                    $fileReviewed = $showReviewChecks && $reviewedSubmittedFileIds->contains($file->id);
                    $annotationUrl = route('topics.versions.files.annotations.index', [$topic, $latestVersion, $file]);

                    if ($disableUnlessRevision) {
                        $annotationUrl .= '?decision=revision_requested';
                    }
                ?>

                <li
                    x-data="{ needsRevision: <?php echo \Illuminate\Support\Js::from($isSelected)->toHtml() ?>, savedHighlightCount: <?php echo \Illuminate\Support\Js::from($reviewCommentCount)->toHtml() ?>, openedForReview: <?php echo \Illuminate\Support\Js::from($fileReviewed)->toHtml() ?>, menuOpen: false }"
                    x-init="selectedFiles[<?php echo e($file->id); ?>] = needsRevision; $watch('needsRevision', value => selectedFiles[<?php echo e($file->id); ?>] = value)"
                    @annotation-saved.window="if (Number($event.detail.fileId) === <?php echo e($file->id); ?>) { savedHighlightCount = Number($event.detail.annotationCount); needsRevision = savedHighlightCount > 0; }"
                    :class="needsRevision ? 'bg-red-50/60 dark:bg-red-950/20' : ''"
                    class="px-2 py-4 transition-colors sm:px-3"
                    id="file-review-card-<?php echo e($file->id); ?>"
                    data-file-review-card="<?php echo e($file->id); ?>"
                >
                    <div class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-4 gap-y-2 sm:grid-cols-[minmax(0,1fr)_11rem_auto]">
                        <div class="flex min-w-0 items-center gap-3">
                            <label
                                <?php if($disableUnlessRevision): ?> x-show="decision === 'revision_requested'" x-cloak <?php endif; ?>
                                class="inline-flex shrink-0 items-center p-1"
                                <?php if($readOnlyReview): ?> title="Revision request already sent" <?php else: ?> :title="savedHighlightCount > 0 ? 'Saved highlights include this paper in the revision request. Remove its draft highlights to exclude it.' : 'Select this paper for revision when instructions are recorded.'" <?php endif; ?>
                            >
                                <input
                                    type="checkbox"
                                    name="revision_file_ids[]"
                                    <?php if($decisionFormId): ?> form="<?php echo e($decisionFormId); ?>" <?php endif; ?>
                                    value="<?php echo e($file->id); ?>"
                                    x-model="needsRevision"
                                    <?php if($isSelected): echo 'checked'; endif; ?>
                                    <?php if($readOnlyReview): ?> disabled <?php else: ?> x-bind:disabled="<?php echo e($disableUnlessRevision ? "decision !== 'revision_requested' || " : ''); ?>savedHighlightCount > 0 || <?php echo e(($fileViewable && $topic->review_stage !== 'lrec') ? 'savedHighlightCount === 0' : 'false'); ?>" <?php endif; ?>
                                    class="h-4 w-4 rounded border-gray-300 text-red-700 focus:ring-red-700 disabled:cursor-not-allowed disabled:opacity-40 dark:border-gray-600 dark:bg-gray-900"
                                >
                                <span class="sr-only"><?php echo e($readOnlyReview ? 'Revision requested for: ' : 'Mark for revision: '); ?><?php echo e($file->label()); ?></span>
                            </label>
                            <h5 class="min-w-0 text-sm font-semibold leading-6 text-gray-900 dark:text-gray-100"><?php echo e($file->label()); ?></h5>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showReviewChecks): ?>
                                <span x-show="openedForReview" <?php if(! $fileReviewed): ?> x-cloak <?php endif; ?> class="inline-flex shrink-0 text-emerald-700 dark:text-emerald-400" title="Opened for review" data-paper-reviewed-cue>
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path stroke-linecap="round" stroke-linejoin="round" d="m8 12 2.5 2.5L16 9" /></svg>
                                    <span class="sr-only">Opened for review</span>
                                </span>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>

                        <div class="col-start-1 row-start-2 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs sm:col-start-2 sm:row-start-1 sm:flex-col sm:items-start">
                            <span x-show="needsRevision" <?php if(! $isSelected): ?> x-cloak <?php endif; ?> class="font-semibold text-red-700 dark:text-red-300" data-file-review-status>Needs revision</span>
                            <span x-show="savedHighlightCount > 0" <?php if($reviewCommentCount === 0): ?> x-cloak <?php endif; ?> class="text-gray-500 dark:text-gray-400" x-text="savedHighlightCount + (savedHighlightCount === 1 ? ' comment' : ' comments')"><?php echo e($reviewCommentCount); ?> <?php echo e($reviewCommentCount === 1 ? 'comment' : 'comments'); ?></span>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $fileAvailable): ?>
                                <span class="text-amber-700 dark:text-amber-300">File unavailable</span>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>

                        <div class="col-start-2 row-span-2 row-start-1 flex items-center gap-1 sm:col-start-3 sm:row-span-1">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($fileViewable): ?>
                                <a href="<?php echo e($annotationUrl); ?>" aria-label="Review <?php echo e($file->label()); ?>" <?php if($showReviewChecks): ?> @click="openedForReview = true" <?php endif; ?> class="inline-flex min-h-11 items-center gap-1.5 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm font-semibold text-red-800 transition hover:bg-red-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-700 focus-visible:ring-offset-2 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200 dark:hover:bg-red-950/70 dark:focus-visible:ring-offset-gray-950" data-review-and-highlight>
                                    Review
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m9 5 7 7-7 7" /></svg>
                                </a>
                            <?php elseif($fileAvailable): ?>
                                <a href="<?php echo e(route('topics.versions.files.download', [$topic, $latestVersion, $file])); ?>" class="inline-flex min-h-10 items-center rounded-lg px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-600 dark:text-red-300 dark:hover:bg-red-950/40" aria-label="Download <?php echo e($file->label()); ?>">Download</a>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                            <div class="relative" @keydown.escape.stop.prevent="menuOpen = false; $refs.moreButton.focus()">
                                <button x-ref="moreButton" type="button" @click="menuOpen = !menuOpen" :aria-expanded="menuOpen" aria-controls="file-review-options-<?php echo e($file->id); ?>" aria-label="More options for <?php echo e($file->label()); ?>" class="inline-flex h-10 w-10 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-red-600 dark:text-gray-400 dark:hover:bg-gray-800">
                                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><circle cx="5" cy="12" r="1.8" /><circle cx="12" cy="12" r="1.8" /><circle cx="19" cy="12" r="1.8" /></svg>
                                </button>
                                <div id="file-review-options-<?php echo e($file->id); ?>" x-show="menuOpen" x-cloak @click.outside="menuOpen = false" @focusout="if (!$el.parentElement.contains($event.relatedTarget)) menuOpen = false" class="absolute bottom-full right-0 z-20 mb-2 w-72 max-w-[calc(100vw-3rem)] rounded-xl border border-gray-200 bg-white p-1.5 shadow-lg dark:border-gray-700 dark:bg-gray-900">
                                    <p class="break-words px-3 py-2 text-xs leading-5 text-gray-500 dark:text-gray-400"><?php echo e($file->original_filename); ?></p>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($fileViewable): ?>
                                        <a href="<?php echo e(route('topics.versions.files.view', [$topic, $latestVersion, $file])); ?>" target="_blank" rel="noopener" @click="menuOpen = false" class="block rounded-lg px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-red-600 dark:text-gray-200 dark:hover:bg-gray-800">Preview PDF <span class="sr-only">(opens in a new tab)</span></a>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($fileAvailable): ?>
                                            <a href="<?php echo e(route('topics.versions.files.download', [$topic, $latestVersion, $file])); ?>" @click="menuOpen = false" class="block rounded-lg px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-red-600 dark:text-gray-200 dark:hover:bg-gray-800">Download</a>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($returnedRevision = $requestedRevisions->get($file->id)): ?>
                        <div class="mt-2 text-xs text-gray-600 dark:text-gray-300">
                            <p class="font-semibold"><?php echo e($returnedRevision->resolution_type === 'no_file_change' ? 'Faculty responded without replacing this paper' : 'Revised paper submitted'); ?></p>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($returnedRevision->faculty_response): ?>
                                <p class="mt-1 whitespace-pre-line"><?php echo e($returnedRevision->faculty_response); ?></p>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $fileViewable && ! $readOnlyReview): ?>
                        <label x-show="needsRevision<?php echo e($disableUnlessRevision ? " && decision === 'revision_requested'" : ''); ?>" x-cloak class="mt-3 block text-sm font-semibold text-gray-700 dark:text-gray-200">
                            Revision instructions <span class="text-red-600 dark:text-red-400">Required</span>
                            <textarea
                                name="revision_file_notes[<?php echo e($file->id); ?>]"
                                <?php if($decisionFormId): ?> form="<?php echo e($decisionFormId); ?>" <?php endif; ?>
                                rows="3"
                                maxlength="2000"
                                :required="needsRevision"
                                <?php if($disableUnlessRevision): ?> x-bind:disabled="decision !== 'revision_requested'" <?php endif; ?>
                                class="mt-2 block w-full rounded-xl border-gray-300 text-sm leading-6 focus:border-red-600 focus:ring-red-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                                placeholder="Name the sheet, cell range, section, or other exact part that must change."
                            ><?php echo e(old('revision_file_notes.'.$file->id)); ?></textarea>
                            <span class="mt-2 block text-xs font-normal leading-5 text-gray-500 dark:text-gray-400">This document cannot be highlighted. Describe the exact location and change needed.</span>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['revision_file_notes.'.$file->id];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><span class="mt-2 block text-xs font-semibold text-red-600"><?php echo e($message); ?></span><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </label>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </li>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </ul>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($group === 'other'): ?>
                    </div>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($readOnlyReview)): ?>
            <p <?php if($disableUnlessRevision): ?> x-show="decision === 'revision_requested'" x-cloak <?php endif; ?> class="mt-3 text-sm text-gray-600 dark:text-gray-300" role="status">
                <span class="font-semibold text-gray-900 dark:text-gray-100" x-text="Object.values(selectedFiles).filter(Boolean).length + (Object.values(selectedFiles).filter(Boolean).length === 1 ? ' document marked for revision.' : ' documents marked for revision.')"></span>
                Saved highlights include their papers automatically. Remove a draft highlight if you no longer want to request that change.
            </p>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
<?php else: ?>
    <p class="rounded-xl bg-gray-50 p-4 text-sm text-gray-600 dark:bg-gray-900 dark:text-gray-300">No submitted files are available for review.</p>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views/topics/partials/revision-file-selector.blade.php ENDPATH**/ ?>