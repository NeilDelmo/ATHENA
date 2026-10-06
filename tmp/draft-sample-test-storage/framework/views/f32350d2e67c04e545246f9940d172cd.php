<?php if (isset($component)) { $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54 = $attributes; } ?>
<?php $component = App\View\Components\AppLayout::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('app-layout'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\AppLayout::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

    <?php
        $pendingDecisionQuery = $isResearchHead && request()->query('decision') === 'revision_requested'
            ? '?decision=revision_requested'
            : '';
        $proposalWorkspaceUrl = $isResearchHead
            ? route('topics.show', $topic).$pendingDecisionQuery.'#file-review-card-'.$file->id
            : route('faculty.topics.revision', $topic);
    ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(request()->boolean('revision_embed')): ?>
        <?php if (isset($component)) { $__componentOriginal13c1f2a64fc7e3eb9a4f045c0d853353 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal13c1f2a64fc7e3eb9a4f045c0d853353 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-revision-pdf','data' => ['configuration' => $annotationConfiguration]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-revision-pdf'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['configuration' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($annotationConfiguration)]); ?>
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
    <?php else: ?>
         <?php $__env->slot('header', null, []); ?> 
            <?php if (isset($component)) { $__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.page-header','data' => ['title' => 'Review document','subtitle' => $file->label().' · Version '.$version->version_number.' · '.$file->original_filename]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('page-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Review document','subtitle' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($file->label().' · Version '.$version->version_number.' · '.$file->original_filename)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

                 <?php $__env->slot('actions', null, []); ?> 
                    <?php if (isset($component)) { $__componentOriginal5426bd0bea02df2e6dd2a60e50fa4c01 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal5426bd0bea02df2e6dd2a60e50fa4c01 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.back-link','data' => ['fixed' => true,'href' => ''.e($proposalWorkspaceUrl).'']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('back-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['fixed' => true,'href' => ''.e($proposalWorkspaceUrl).'']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>
Back to review <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal5426bd0bea02df2e6dd2a60e50fa4c01)): ?>
<?php $attributes = $__attributesOriginal5426bd0bea02df2e6dd2a60e50fa4c01; ?>
<?php unset($__attributesOriginal5426bd0bea02df2e6dd2a60e50fa4c01); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal5426bd0bea02df2e6dd2a60e50fa4c01)): ?>
<?php $component = $__componentOriginal5426bd0bea02df2e6dd2a60e50fa4c01; ?>
<?php unset($__componentOriginal5426bd0bea02df2e6dd2a60e50fa4c01); ?>
<?php endif; ?>
                 <?php $__env->endSlot(); ?>
             <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e)): ?>
<?php $attributes = $__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e; ?>
<?php unset($__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e)): ?>
<?php $component = $__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e; ?>
<?php unset($__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e); ?>
<?php endif; ?>
         <?php $__env->endSlot(); ?>

        <div x-data="pdfAnnotationWorkspace" data-pdf-annotation-config='<?php echo json_encode($annotationConfiguration, 15, 512) ?>' @resize.window="positionCommentComposer()" @scroll.window.capture="positionCommentComposer()" class="mx-auto max-w-[1600px] space-y-4">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($commentResponseLinks !== []): ?>
                <section data-comment-response-access aria-label="Comment Response paper" class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-red-200 bg-red-50 p-4 dark:border-red-900 dark:bg-red-950/30 sm:p-5">
                    <div class="max-w-3xl">
                        <h3 class="text-base font-bold text-gray-950 dark:text-white">Highlights → Comment Response paper</h3>
                        <p class="mt-1 text-sm leading-6 text-gray-700 dark:text-gray-300"><?php echo e($canAnnotate ? 'Saved highlights feed the Comment Response paper. Preview the draft here or from any Highlight page; sending the revision request shares the selected papers’ comments with Faculty.' : 'These comments belong to the Comment Response paper for the review below, together with the Faculty responses.'); ?></p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $commentResponseLinks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $link): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <button type="button" data-comment-response-preview-button aria-haspopup="dialog" @click="$dispatch('open-modal', 'highlight-comment-response-<?php echo e($loop->index); ?>')" <?php if($link['draft']): ?> :disabled="saving || !!deletingAnnotationId" <?php endif; ?> class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm font-bold text-gray-800 shadow-sm transition hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-700 focus-visible:ring-offset-2 disabled:cursor-wait disabled:opacity-50 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100 dark:hover:bg-gray-800 dark:focus-visible:ring-offset-gray-950">
                                <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7S2 12 2 12Z" /><circle cx="12" cy="12" r="3" /></svg>
                                <?php echo e($link['label']); ?>

                            </button>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </div>
                </section>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $canAnnotate && $isResearchHead): ?>
                <p class="rounded-xl bg-gray-100 px-4 py-3 text-base leading-7 text-gray-700 dark:bg-gray-900 dark:text-gray-300">This review is locked. Saved comments remain available, but cannot be changed after the decision is sent.</p>
            <?php elseif(! $isResearchHead): ?>
                <p class="rounded-xl bg-gray-100 px-4 py-3 text-base leading-7 text-gray-700 dark:bg-gray-900 dark:text-gray-300">Select a numbered comment to find the part that needs revision. Open the paper or its linked field to make your changes.</p>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($topic->user_id === Auth::id() && $topic->status === 'revision_requested'): ?>
                    <a href="<?php echo e($proposalWorkspaceUrl); ?>" class="inline-flex rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white">Revise <?php echo e($file->label()); ?></a>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <div x-show="paperFocusOpen" x-cloak @click="closePaperFocus()" class="fixed inset-0 z-[90] bg-gray-950/70" aria-hidden="true"></div>
            <div
                x-ref="paperFocusPanel"
                :class="paperFocusOpen ? 'fixed inset-2 z-[100] flex flex-col shadow-2xl sm:inset-5' : ''"
                :role="paperFocusOpen ? 'dialog' : null"
                :aria-modal="paperFocusOpen ? 'true' : null"
                :aria-label="paperFocusOpen ? 'Focused PDF review workspace' : null"
                @keydown.escape.window="if (!draftSelection && !document.querySelector('[data-comment-response-preview-content]')) closePaperFocus()"
                class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900"
            >
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-3 py-3 dark:border-gray-800 sm:px-4">
                    <div data-annotation-tools-guide>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canAnnotate): ?>
                            <p class="text-base font-bold text-gray-950 dark:text-white"><?php echo e($topic->review_stage === 'lrec' ? 'Drag over the part that needs revision, then add the LREC feedback.' : 'Drag over the part that needs revision, then add a Research Head comment.'); ?></p>
                            <p class="mt-1 text-sm leading-6 text-gray-500 dark:text-gray-400" x-text="draftSelection ? modeInstruction : 'Comments remain drafts until you send the revision request.'"></p>
                        <?php else: ?>
                            <p class="text-base text-gray-600 dark:text-gray-300" x-text="modeInstruction"></p>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $commentResponseLinks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $link): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <button x-show="paperFocusOpen" x-cloak type="button" data-comment-response-preview-button aria-haspopup="dialog" @click="$dispatch('open-modal', 'highlight-comment-response-<?php echo e($loop->index); ?>')" <?php if($link['draft']): ?> :disabled="saving || !!deletingAnnotationId" <?php endif; ?> class="inline-flex min-h-11 items-center justify-center rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm font-bold text-gray-800 transition hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-700 focus-visible:ring-offset-2 disabled:cursor-wait disabled:opacity-50 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100 dark:hover:bg-gray-800 dark:focus-visible:ring-offset-gray-950"><?php echo e($link['label']); ?></button>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        <button x-ref="paperFocusClose" type="button" @click="paperFocusOpen ? closePaperFocus() : openPaperFocus()" class="inline-flex min-h-11 shrink-0 items-center rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm font-bold text-gray-800 transition hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-700 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100" x-text="paperFocusOpen ? 'Exit focus' : 'Expand paper'">Expand paper</button>
                    </div>
                </div>

                <div :class="paperFocusOpen ? 'min-h-0 flex-1' : 'h-[76dvh] min-h-[32rem]'" class="grid grid-rows-[minmax(14rem,1fr)_auto] lg:grid-cols-[minmax(0,1fr)_380px] lg:grid-rows-1">
                    <main class="min-h-0 min-w-0 overflow-hidden rounded-bl-2xl bg-slate-100 dark:bg-slate-950">
                        <div x-show="loading" class="p-8 text-center text-sm text-gray-600">Loading submitted PDF…</div>
                        <div x-show="loadError" x-cloak role="alert" class="m-4 rounded-xl bg-red-50 p-4 text-sm text-red-800" x-text="loadError"></div>
                        <div x-ref="viewer" :data-active-reviewer="activeReviewer" tabindex="0" aria-label="Submitted document" @mouseup="captureTextSelection" :class="{ 'pdf-annotation-area-mode': mode !== 'text' }" class="pdf-annotation-viewer flex h-full flex-col items-center gap-5 overflow-auto overscroll-contain p-3 sm:p-5"></div>
                    </main>

                    <aside class="max-h-[34dvh] overflow-y-auto border-t border-gray-200 p-4 dark:border-gray-800 lg:max-h-none lg:border-l lg:border-t-0">
                        <div class="mb-5 flex items-center gap-3 rounded-2xl border border-red-200 bg-red-50 p-4 dark:border-red-900 dark:bg-red-950/30" aria-label="Active reviewer">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-full bg-red-700 text-sm font-bold text-white">
                                <template x-if="config.researchHeadAvatar"><img :src="config.researchHeadAvatar" alt="" class="h-full w-full object-cover" x-on:error="config.researchHeadAvatar = null"></template>
                                <span x-show="!config.researchHeadAvatar" x-text="reviewerInitials(config.researchHeadName || 'Research Head')"></span>
                            </span>
                            <div class="min-w-0"><p class="font-serif text-lg font-bold text-red-950 dark:text-red-100"><?php echo e($topic->review_stage === 'lrec' ? 'LREC comments' : 'Research Head comments'); ?></p><p class="truncate text-sm text-red-800 dark:text-red-200" x-text="config.isLrecReview ? 'Recorded by ' + config.researchHeadName : (config.researchHeadName || 'Research Head')"></p></div>
                        </div>
                        <div class="mb-3 flex items-center justify-between gap-2">
                            <h3 id="annotation-comments-heading" class="font-serif text-lg font-bold text-gray-950 dark:text-white">Comments <span class="ml-1 font-sans text-sm font-semibold text-gray-500" x-text="reviewerCommentCount"></span></h3>
                            <span class="rounded-full bg-gray-100 px-2.5 py-1 text-sm font-semibold text-gray-600 dark:bg-gray-800 dark:text-gray-300" x-show="canAnnotate">Draft review</span>
                        </div>
                        <div class="space-y-3" aria-labelledby="annotation-comments-heading">
                            <template x-for="(annotation, index) in annotations" :key="annotation.id">
                                <article :data-comment-id="annotation.id" @click="jumpToAnnotation(annotation)" @keydown.enter.self.prevent="jumpToAnnotation(annotation)" tabindex="0" :aria-label="'Comment ' + (index + 1) + ', page ' + annotation.pageNumber" :class="selectedAnnotationId === annotation.id ? 'border-red-400 ring-1 ring-red-200' : 'border-gray-200 dark:border-gray-700'" class="rounded-2xl border bg-white p-4 shadow-sm focus:outline-none focus:ring-2 focus:ring-red-500 dark:bg-gray-900">
                                    <div class="flex items-center gap-2">
                                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-red-700 text-sm font-bold text-white" x-text="index + 1"></span>
                                        <button type="button" @click.stop="jumpToAnnotation(annotation)" class="min-h-9 rounded-lg px-1 text-sm font-semibold text-gray-600 hover:text-red-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-700 dark:text-gray-300">Page <span x-text="annotation.pageNumber"></span></button>
                                    </div>
                                    <div data-annotation-actions x-show="canAnnotate && annotation.canEdit && annotation.state === 'draft'" x-cloak class="mt-3 flex flex-wrap gap-2">
                                        <button data-edit-annotation type="button" :aria-label="'Edit comment ' + (index + 1)" @click.stop="editAnnotation(annotation)" :disabled="!!draftSelection || saving || !!deletingAnnotationId" class="inline-flex min-h-11 items-center rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-100 disabled:opacity-40 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">Edit</button>
                                        <button data-remove-annotation type="button" :aria-label="'Delete comment ' + (index + 1)" @click.stop="deleteAnnotation(annotation)" :disabled="saving || !!deletingAnnotationId" class="inline-flex min-h-11 items-center rounded-lg border border-red-200 px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-50 disabled:opacity-40 dark:border-red-900 dark:text-red-300 dark:hover:bg-red-950">Delete</button>
                                    </div>
                                    <blockquote x-show="annotation.selectedText" class="mt-3 line-clamp-3 border-l-2 border-red-200 pl-3 text-sm leading-6 text-gray-500 dark:text-gray-400" x-text="annotation.selectedText"></blockquote>
                                    <p class="mt-3 whitespace-pre-line break-words text-base leading-7 text-gray-900 dark:text-gray-100" x-text="annotation.comment"></p>
                                    <p x-show="annotation.editorTargetLabel" class="mt-2 text-sm text-gray-500" x-text="annotation.editorTargetLabel"></p>
                                    <p class="mt-3 text-sm leading-6 text-gray-500 dark:text-gray-400"><span class="font-semibold" x-text="annotation.feedbackLabel"></span><span x-show="annotation.feedbackAuthor"> · <span x-text="annotation.feedbackAuthor"></span></span><span class="block" x-text="annotation.createdAt"></span></p>
                                    <p x-show="annotation.state !== 'draft'" class="mt-2 text-sm text-gray-500" x-text="annotation.state === 'resolved' ? 'Resolved by a new version' : 'Sent · locked'"></p>
                                    <a x-show="!isResearchHead && annotation.state === 'requested' && revisionUrl" :href="annotationEditUrl(annotation)" @click.stop class="mt-3 inline-flex min-h-11 items-center rounded-xl bg-red-700 px-4 py-2.5 text-sm font-bold text-white">
                                        <span x-text="annotation.editorTargetLabel ? 'Edit ' + annotation.editorTargetLabel : 'Revise this paper'"></span>
                                    </a>
                                </article>
                            </template>
                            <p x-show="reviewerCommentCount === 0" class="py-8 text-center text-sm leading-6 text-gray-500 dark:text-gray-400"><?php echo e($topic->review_stage === 'lrec' ? 'No LREC comments yet.' : 'No Research Head comments yet.'); ?></p>
                        </div>
                    </aside>
                </div>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canAnnotate): ?>
                    <section x-ref="commentComposer" x-show="draftSelection" x-cloak role="dialog" aria-labelledby="revision-comment-title" @keydown.escape.stop.prevent="cancelDraft()" @keydown.ctrl.enter.prevent="saveAnnotation()" @keydown.meta.enter.prevent="saveAnnotation()" class="fixed z-[120] w-[410px] max-w-[calc(100vw-1.5rem)] overflow-y-auto overscroll-contain rounded-2xl border border-gray-200 bg-white p-5 shadow-2xl dark:border-gray-700 dark:bg-gray-900">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 id="revision-comment-title" class="font-serif text-xl font-bold text-gray-950 dark:text-white" x-text="editingAnnotationId ? 'Edit comment' : 'Revision comment'"></h3>
                                <p class="mt-1 text-sm text-gray-500">Page <span x-text="draftSelection?.pageNumber"></span> · <span x-text="draftSelection?.type === 'pin' ? 'Pinned location' : 'Highlighted location'"></span></p>
                            </div>
                            <button type="button" @click="cancelDraft()" :disabled="saving" aria-label="Close comment editor" class="rounded px-2 text-xl text-gray-500 disabled:opacity-40">×</button>
                        </div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($topic->review_stage === 'lrec')): ?>
                            <p class="mt-2 text-sm font-semibold text-gray-700 dark:text-gray-200">Research Head · <span x-text="config.researchHeadName || ''"></span></p>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <p x-show="draftSectionLabel" x-text="draftSectionLabel" class="mt-2 text-sm font-semibold text-red-700 dark:text-red-300"></p>
                        <blockquote x-show="draftSelection?.selectedText" class="mt-3 max-h-20 overflow-auto border-l-2 border-red-200 pl-3 text-sm leading-6 text-gray-500" x-text="draftSelection?.selectedText"></blockquote>
                        <label class="mt-4 block text-base font-semibold text-gray-800 dark:text-gray-100">What needs to change?
                            <textarea x-ref="commentInput" x-model="draftComment" :disabled="saving" rows="4" maxlength="5000" class="mt-2 block w-full resize-y rounded-xl border-gray-300 text-base leading-7 focus:border-red-700 focus:ring-red-700 dark:border-gray-700 dark:bg-gray-950 dark:text-white" placeholder="Describe the revision needed…"></textarea>
                        </label>
                        <p x-show="saveError" role="alert" class="mt-2 text-sm text-red-700 dark:text-red-300" x-text="saveError"></p>
                        <div class="mt-4 flex justify-end gap-2">
                            <button type="button" @click="cancelDraft()" :disabled="saving" class="min-h-11 rounded-xl px-4 py-2 text-base font-semibold text-gray-600 disabled:opacity-40 dark:text-gray-300">Cancel</button>
                            <button type="button" @click="saveAnnotation()" :disabled="saving || !draftComment.trim()" class="min-h-11 rounded-xl bg-red-700 px-5 py-2 text-base font-bold text-white disabled:cursor-not-allowed disabled:opacity-40" x-text="saving ? 'Saving…' : (editingAnnotationId ? 'Save changes' : 'Add comment')"></button>
                        </div>
                    </section>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $commentResponseLinks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $link): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <?php if (isset($component)) { $__componentOriginal9f64f32e90b9102968f2bc548315018c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9f64f32e90b9102968f2bc548315018c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.modal','data' => ['name' => 'highlight-comment-response-'.e($loop->index).'','maxWidth' => '6xl','focusable' => true,'class' => '!z-[140]','dataCommentResponsePreviewModal' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('modal'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'highlight-comment-response-'.e($loop->index).'','maxWidth' => '6xl','focusable' => true,'class' => '!z-[140]','data-comment-response-preview-modal' => true]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

                    <template x-if="show">
                        <section data-comment-response-preview-content role="dialog" aria-modal="true" aria-labelledby="highlight-comment-response-heading-<?php echo e($loop->index); ?>">
                            <header class="flex items-center justify-between gap-4 border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                                <h3 id="highlight-comment-response-heading-<?php echo e($loop->index); ?>" class="text-base font-bold text-gray-950 dark:text-white"><?php echo e($link['label']); ?></h3>
                                <button type="button" @click="$dispatch('close')" class="inline-flex min-h-11 shrink-0 items-center rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm font-bold text-gray-800 hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-700 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100 dark:hover:bg-gray-800">Close preview</button>
                            </header>
                            <?php if (isset($component)) { $__componentOriginal13c1f2a64fc7e3eb9a4f045c0d853353 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal13c1f2a64fc7e3eb9a4f045c0d853353 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-revision-pdf','data' => ['configuration' => ['pdfUrl' => $link['url'], 'annotations' => [], 'canAnnotate' => false],'loadingLabel' => 'Loading Comment Response paper…','viewerLabel' => 'Comment Response paper','class' => '!h-[75dvh]']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-revision-pdf'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['configuration' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(['pdfUrl' => $link['url'], 'annotations' => [], 'canAnnotate' => false]),'loading-label' => 'Loading Comment Response paper…','viewer-label' => 'Comment Response paper','class' => '!h-[75dvh]']); ?>
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
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $attributes = $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $component = $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views/topics/file-annotations.blade.php ENDPATH**/ ?>