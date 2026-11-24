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
        $isFacultyResearcher = Auth::user()->isUsingWorkspace('faculty_researcher');
        $revisionRequestedTopics = $topics->where('status', 'revision_requested')
            ->sortBy(fn ($topic) => $topic->review_stage !== 'lrec'
                ? ($topic->researchCall?->paper_revisions_end_date?->toDateString() ?? '9999-12-31')
                : '9999-12-31');
        $underReviewTopics = $topics->whereIn('status', [
            'pending',
            'expert_review',
            'for_final_decision',
            'lrec_queued',
            'lrec_review',
            'gad_review',
            'resubmitted',
            'ready_for_signature',
        ]);
        $revisionReviewChecks = $assistantReviewSuggestions->whereNotNull('context.topic_id')->keyBy('context.topic_id');
        $draftReviewChecks = $assistantReviewSuggestions->whereNotNull('context.proposal_draft_id')->keyBy('context.proposal_draft_id');
    ?>

     <?php $__env->slot('header', null, []); ?> 
        <?php if (isset($component)) { $__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.page-header','data' => ['dataWorkspaceHeaderBanner' => true,'dataDashboardHeader' => true,'title' => 'Dashboard']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('page-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['data-workspace-header-banner' => true,'data-dashboard-header' => true,'title' => 'Dashboard']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

             <?php $__env->slot('actions', null, []); ?> 
                <div class="flex w-full sm:w-auto">
                    <a href="<?php echo e(route('faculty.proposal-drafts.create')); ?>" class="dashboard-action w-full sm:w-auto">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                        New proposal
                    </a>
                </div>
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

    <div class="space-y-5" data-dashboard-palette="red-black-white" data-dashboard-layout="faculty-overview">
        <?php echo $__env->make('faculty.partials.research-call-carousel', ['researchCallCarouselItems' => $researchCallCarouselItems], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

        <section aria-labelledby="proposal-overview-heading">
            <h2 id="proposal-overview-heading" class="sr-only">Proposal overview</h2>
            <dl class="grid grid-cols-2 gap-px overflow-hidden rounded-xl border border-brand bg-white/20 xl:grid-cols-4">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = [
                    ['Needs revision', $revisionRequestedTopics->count(), 'Changes requested', route('faculty.submissions', ['category' => 'active', 'status' => 'revision_requested']), 'M12 9v4m0 3h.01M12 3 2 20h20L12 3Z'],
                    ['Drafts', $proposalDraftCount, 'Proposal preparation', route('faculty.proposal-drafts.index'), 'M4 4h10l6 6v10H4V4Zm10 0v6h6M8 14h8M8 17h5'],
                    ['Submitted', $topics->count(), 'Across all statuses', route('faculty.submissions', ['category' => 'all']), 'M4 4h16v16H4V4Zm4 5h8M8 12h8M8 15h5'],
                    ['Under review', $underReviewTopics->count(), 'Review and final approval', route('faculty.submissions', ['category' => 'active', 'status' => 'review']), 'M12 6v6l4 2m5-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'],
                ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$label, $count, $description, $href, $icon]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <?php if (isset($component)) { $__componentOriginal44031904ce819b19d1f07f77d709c2c1 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal44031904ce819b19d1f07f77d709c2c1 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.dashboard-stat','data' => ['label' => $label,'value' => $count,'description' => $description,'icon' => $icon,'href' => $href,'tone' => 'brand','priority' => $label === 'Needs revision' && $count > 0]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('dashboard-stat'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($label),'value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($count),'description' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($description),'icon' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($icon),'href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($href),'tone' => 'brand','priority' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($label === 'Needs revision' && $count > 0)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal44031904ce819b19d1f07f77d709c2c1)): ?>
<?php $attributes = $__attributesOriginal44031904ce819b19d1f07f77d709c2c1; ?>
<?php unset($__attributesOriginal44031904ce819b19d1f07f77d709c2c1); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal44031904ce819b19d1f07f77d709c2c1)): ?>
<?php $component = $__componentOriginal44031904ce819b19d1f07f77d709c2c1; ?>
<?php unset($__componentOriginal44031904ce819b19d1f07f77d709c2c1); ?>
<?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </dl>
        </section>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('success')): ?>
            <div class="flex items-start gap-3 rounded-3xl border border-stone-200 bg-white px-4 py-3 text-sm text-gray-700 shadow-bubble-sm dark:border-stone-700 dark:bg-stone-900 dark:text-gray-300">
                <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-[#7A0019] dark:bg-red-400" aria-hidden="true"></span>
                <p class="font-semibold"><?php echo e(session('success')); ?></p>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->resubmission->any()): ?>
            <div class="rounded-3xl border border-stone-200 border-l-4 border-l-[#7A0019] bg-white px-4 py-3 text-sm text-[#7A0019] dark:border-stone-700 dark:border-l-red-400 dark:bg-stone-900 dark:text-red-200">
                <p class="font-semibold">Please review your submission.</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $errors->resubmission->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <li><?php echo e($error); ?></li>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </ul>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <div data-dashboard-columns class="min-w-0 space-y-6">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($revisionRequestedTopics->isNotEmpty()): ?>
            <section data-revision-worklist aria-labelledby="revision-worklist-heading" class="space-y-3">
                <div class="flex items-center gap-3">
                    <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center text-brand dark:text-red-300" aria-hidden="true">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-6M9 3h6v4H9V3Zm3 12 2.5-.5L21 8l-2-2-6.5 6.5L12 15Z" /></svg>
                    </span>
                    <h2 id="revision-worklist-heading" class="text-base font-semibold text-brand dark:text-red-300">Revisions to address</h2>
                    <span class="flex h-6 min-w-6 items-center justify-center rounded-full bg-brand px-2 text-xs font-semibold tabular-nums text-white dark:bg-red-800"><?php echo e($revisionRequestedTopics->count()); ?></span>
                </div>
                <p class="text-xs leading-5 text-stone-600 dark:text-stone-400">Address the requested changes, or ask Athena to help you plan your revisions.</p>
                <div class="overflow-hidden rounded-xl border border-stone-200 bg-white dark:border-stone-700 dark:bg-stone-900">
                    <div class="divide-y divide-stone-200 dark:divide-stone-700">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $revisionRequestedTopics; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $topic): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <?php
                                $latestRevisionReview = $topic->reviews->where('decision', 'revision_requested')->last();
                                $revisionDeadline = $topic->review_stage !== 'lrec' ? $topic->researchCall?->paper_revisions_end_date : null;
                            ?>
                            <article data-revision-task data-topic-id="<?php echo e($topic->id); ?>" class="min-w-0 p-5 transition-colors hover:bg-stone-50 dark:hover:bg-stone-800/50">
                                <div class="flex min-w-0 flex-col items-start gap-2">
                                    <div class="flex min-w-0 items-start gap-3">
                                        <span class="inline-flex h-6 w-6 shrink-0 items-center justify-center text-brand dark:text-red-300" aria-hidden="true">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 3h10l6 6v12H4V3Zm10 0v6h6M8 13h8M8 17h5" /></svg>
                                        </span>
                                        <h3 class="max-w-prose break-words text-base font-semibold leading-6 text-gray-950 dark:text-white"><?php echo e($topic->title); ?></h3>
                                    </div>
                                    <p class="text-xs leading-5 text-gray-600 dark:text-gray-400"><?php echo e($topic->researchCall?->title ?? 'Independent submission'); ?></p>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($revisionDeadline): ?>
                                        <?php if (isset($component)) { $__componentOriginala2b55bd53737b58ae21d3ddce75d52a2 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginala2b55bd53737b58ae21d3ddce75d52a2 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.dashboard-deadline','data' => ['date' => $revisionDeadline->copy()->endOfDay()]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('dashboard-deadline'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['date' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($revisionDeadline->copy()->endOfDay())]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginala2b55bd53737b58ae21d3ddce75d52a2)): ?>
<?php $attributes = $__attributesOriginala2b55bd53737b58ae21d3ddce75d52a2; ?>
<?php unset($__attributesOriginala2b55bd53737b58ae21d3ddce75d52a2); ?>
<?php endif; ?>
<?php if (isset($__componentOriginala2b55bd53737b58ae21d3ddce75d52a2)): ?>
<?php $component = $__componentOriginala2b55bd53737b58ae21d3ddce75d52a2; ?>
<?php unset($__componentOriginala2b55bd53737b58ae21d3ddce75d52a2); ?>
<?php endif; ?>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <div data-reviewer-feedback class="mt-1 min-w-0">
                                        <p class="flex items-center gap-1.5 text-xs font-semibold text-brand dark:text-red-300"><svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8 9h8M8 13h5M5 3h14a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2h-8l-6 4v-4H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z" /></svg>Requested change</p>
                                        <p class="mt-1 max-w-prose whitespace-pre-line break-words text-sm leading-6 text-stone-700 dark:text-stone-300"><?php echo e($latestRevisionReview?->comment ?: 'Open the revision page to see the requested changes.'); ?></p>
                                    </div>
                                    <div class="mt-2 flex flex-wrap items-center gap-3">
                                        <a href="<?php echo e(route('faculty.topics.revision', $topic)); ?>" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-brand px-4 py-2 text-xs font-semibold text-white hover:bg-brand-soft focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand dark:bg-red-700 dark:hover:bg-red-600 dark:focus-visible:outline-red-400"><svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m16 4 4 4-12 12H4v-4L16 4ZM13 7l4 4" /></svg>Revise and resubmit proposal</a>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($revisionReviewChecks->has($topic->id)): ?>
                                            <?php if (isset($component)) { $__componentOriginal90b2708f9948773d33a4da52984d77bf = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal90b2708f9948773d33a4da52984d77bf = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.dashboard-assistant-action','data' => ['item' => $revisionReviewChecks->get($topic->id),'label' => 'Get revision help','variant' => 'outline','help' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('dashboard-assistant-action'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['item' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($revisionReviewChecks->get($topic->id)),'label' => 'Get revision help','variant' => 'outline','help' => true]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal90b2708f9948773d33a4da52984d77bf)): ?>
<?php $attributes = $__attributesOriginal90b2708f9948773d33a4da52984d77bf; ?>
<?php unset($__attributesOriginal90b2708f9948773d33a4da52984d77bf); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal90b2708f9948773d33a4da52984d77bf)): ?>
<?php $component = $__componentOriginal90b2708f9948773d33a4da52984d77bf; ?>
<?php unset($__componentOriginal90b2708f9948773d33a4da52984d77bf); ?>
<?php endif; ?>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </div>
                                </div>
                            </article>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </div>
                </div>
            </section>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $isFacultyResearcher): ?>
            <section id="recent-drafts" aria-labelledby="recent-drafts-heading">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center text-brand dark:text-red-300" aria-hidden="true"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7a2 2 0 0 1 2-2h5l2 2h7a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7Z" /></svg></span>
                    <div>
                        <h2 id="recent-drafts-heading" class="text-base font-semibold text-brand dark:text-red-300">Drafts</h2>
                        <p class="mt-1 text-xs text-stone-600 dark:text-stone-400">Resume one of your recent drafts.</p>
                    </div>
                </div>
                <a href="<?php echo e(route('faculty.proposal-drafts.index')); ?>" class="inline-flex min-h-11 shrink-0 items-center justify-center gap-2 rounded-lg border border-stone-300 bg-stone-50 px-4 py-2 text-xs font-semibold text-brand transition-colors hover:border-brand hover:bg-stone-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand dark:border-stone-700 dark:bg-stone-900 dark:text-red-300 dark:hover:border-red-400 dark:hover:bg-stone-800 dark:focus-visible:outline-red-400">View all drafts<svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6" /></svg></a>
            </div>

            <div data-dashboard-draft-list class="mt-3 divide-y divide-stone-200 overflow-hidden rounded-xl border border-stone-200 bg-white dark:divide-stone-700 dark:border-stone-700 dark:bg-stone-900">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $recentProposalDrafts->take(2); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $proposalDraft): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <?php
                        $progress = $proposalDraftProgress->get($proposalDraft->getKey());
                    ?>
                    <article data-dashboard-draft data-draft-id="<?php echo e($proposalDraft->id); ?>" class="grid min-w-0 gap-4 p-5 transition-colors hover:bg-stone-50 dark:hover:bg-stone-800/50 lg:grid-cols-[minmax(0,1fr)_8rem_auto] lg:items-center">
                        <div class="flex min-w-0 items-start gap-3">
                            <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center text-brand dark:text-red-300" aria-hidden="true"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 3h10l6 6v12H4V3Zm10 0v6h6M8 13h8M8 17h5" /></svg></span>
                            <div class="min-w-0 space-y-2">
                                <h3 class="break-words text-sm font-semibold leading-6 text-stone-950 dark:text-white"><?php echo e($proposalDraft->project_title ?: 'Untitled proposal'); ?></h3>
                                <p class="flex flex-wrap gap-x-3 gap-y-1 text-xs leading-5 text-stone-600 dark:text-stone-400">
                                    <span><?php echo e($proposalDraft->isOwnedBy(Auth::user()) ? 'Project leader' : 'Project staff'); ?></span>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($proposalDraft->isOwnedBy(Auth::user()))): ?>
                                        <span>Shared by <?php echo e($proposalDraft->owner->name); ?></span>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <span>Edited <?php echo e($proposalDraft->updated_at->diffForHumans()); ?></span>
                                </p>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($proposalDraft->researchCall): ?>
                                    <p class="text-xs leading-5 text-stone-600 dark:text-stone-400"><?php echo e($proposalDraft->researchCall->title); ?></p>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                        </div>
                        <div>
                            <p class="flex items-center gap-1.5 text-xs font-medium text-brand dark:text-red-300"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>Papers ready</p>
                            <p class="mt-1 text-sm font-semibold text-brand dark:text-red-300"><?php echo e($progress['completed']); ?> of <?php echo e($progress['total']); ?> <span class="sr-only">papers ready</span></p>
                        </div>
                        <div class="flex flex-wrap items-center gap-3">
                            <a href="<?php echo e(route('faculty.proposal-drafts.show', $proposalDraft)); ?>" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-brand px-4 py-2 text-xs font-semibold text-white transition-colors hover:bg-brand-soft focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand dark:bg-red-700 dark:hover:bg-red-600 dark:focus-visible:outline-red-400">
                                Resume draft
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6" /></svg>
                            </a>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($draftReviewChecks->has($proposalDraft->id)): ?>
                                <?php if (isset($component)) { $__componentOriginal90b2708f9948773d33a4da52984d77bf = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal90b2708f9948773d33a4da52984d77bf = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.dashboard-assistant-action','data' => ['item' => $draftReviewChecks->get($proposalDraft->id),'label' => 'Improve this draft','variant' => 'outline','help' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('dashboard-assistant-action'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['item' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($draftReviewChecks->get($proposalDraft->id)),'label' => 'Improve this draft','variant' => 'outline','help' => true]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal90b2708f9948773d33a4da52984d77bf)): ?>
<?php $attributes = $__attributesOriginal90b2708f9948773d33a4da52984d77bf; ?>
<?php unset($__attributesOriginal90b2708f9948773d33a4da52984d77bf); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal90b2708f9948773d33a4da52984d77bf)): ?>
<?php $component = $__componentOriginal90b2708f9948773d33a4da52984d77bf; ?>
<?php unset($__componentOriginal90b2708f9948773d33a4da52984d77bf); ?>
<?php endif; ?>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    </article>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    <div class="px-6 py-8 text-center">
                        <div class="mx-auto flex h-11 w-11 items-center justify-center rounded-full bg-brand text-white dark:bg-red-800">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                        </div>
                        <h4 class="mt-4 text-sm font-semibold text-gray-950 dark:text-white">No proposal drafts yet</h4>
                        <p class="mx-auto mt-1 max-w-md text-xs leading-5 text-gray-500 dark:text-gray-400">Start a proposal and prepare the required papers.</p>
                                                    <a href="<?php echo e(route('faculty.proposal-drafts.create')); ?>" class="mt-4 inline-flex items-center justify-center rounded-xl bg-[#7A0019] px-4 py-2.5 text-xs font-semibold text-white transition hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-[#7A0019] focus:ring-offset-2 dark:bg-red-700 dark:hover:bg-red-600 dark:focus:ring-red-400 dark:focus:ring-offset-gray-950">Create first proposal</a>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </section>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        </div>
    </div>
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
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\faculty\dashboard.blade.php ENDPATH**/ ?>