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
        $revisionRequestedTopics = $topics->where('status', 'revision_requested');
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
    ?>

     <?php $__env->slot('header', null, []); ?> 
        <?php if (isset($component)) { $__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.page-header','data' => ['variant' => 'hero','eyebrow' => 'Faculty workspace','title' => 'Faculty','subtitle' => 'Welcome back, '.Auth::user()->name.'. Manage your drafts, review feedback, and submitted proposals.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('page-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['variant' => 'hero','eyebrow' => 'Faculty workspace','title' => 'Faculty','subtitle' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute('Welcome back, '.Auth::user()->name.'. Manage your drafts, review feedback, and submitted proposals.')]); ?>
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

        <section aria-labelledby="proposal-overview-heading">
            <h2 id="proposal-overview-heading" class="sr-only">Proposal overview</h2>
            <p class="sr-only">A quick view of your research pipeline.</p>
            <dl class="grid grid-cols-2 gap-4 xl:grid-cols-4">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = [
                    ['Drafts', $proposalDraftCount, 'Projects in progress', 'M4 4h10l6 6v10H4V4Zm10 0v6h6M8 14h8M8 17h5'],
                    ['Submitted', $topics->count(), 'Your submitted proposals', 'M4 4h16v16H4V4Zm4 5h8M8 12h8M8 15h5'],
                    ['Under review', $underReviewTopics->count(), 'Moving through the review process', 'M12 6v6l4 2m5-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'],
                    ['Needs revision', $revisionRequestedTopics->count(), 'Feedback ready for your response', 'M12 9v4m0 3h.01M12 3 2 20h20L12 3Z'],
                ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$label, $count, $description, $icon]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <?php if (isset($component)) { $__componentOriginal44031904ce819b19d1f07f77d709c2c1 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal44031904ce819b19d1f07f77d709c2c1 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.dashboard-stat','data' => ['label' => $label,'value' => $count,'description' => $description,'icon' => $icon]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('dashboard-stat'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($label),'value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($count),'description' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($description),'icon' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($icon)]); ?>
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
            <div class="flex items-start gap-3 rounded-3xl border border-rose-100 bg-white px-4 py-3 text-sm text-gray-700 shadow-bubble-sm dark:border-red-950/70 dark:bg-slate-950 dark:text-gray-300">
                <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-[#7A0019] dark:bg-red-400" aria-hidden="true"></span>
                <p class="font-semibold"><?php echo e(session('success')); ?></p>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->resubmission->any()): ?>
            <div class="rounded-3xl border border-rose-200 border-l-4 border-l-[#7A0019] bg-rose-50 px-4 py-3 text-sm text-[#7A0019] dark:border-red-950 dark:border-l-red-500 dark:bg-red-950/30 dark:text-red-200">
                <p class="font-semibold">Please review your submission.</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $errors->resubmission->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <li><?php echo e($error); ?></li>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </ul>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <div data-dashboard-columns class="min-w-0 space-y-6">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($researchCallCarouselItems->isNotEmpty()): ?>
                    <div class="dashboard-panel">
                        <div class="dashboard-panel-heading">
                            <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Research Office announcements</h2>
                            <a href="<?php echo e(route('research-calls.index')); ?>" class="text-xs font-semibold text-[#7A0019] hover:underline dark:text-red-300">View research calls</a>
                        </div>
                        <?php echo $__env->make('faculty.partials.research-call-carousel', ['researchCallCarouselItems' => $researchCallCarouselItems], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($revisionRequestedTopics->isNotEmpty()): ?>
            <section data-revision-worklist aria-labelledby="revision-worklist-heading" class="space-y-3">
                <div class="flex items-center gap-3">
                    <h2 id="revision-worklist-heading" class="text-base font-semibold text-gray-950 dark:text-white">Revisions to address</h2>
                    <span class="flex h-6 min-w-6 items-center justify-center rounded-full bg-gray-100 px-2 text-xs font-semibold tabular-nums text-gray-600 dark:bg-gray-800 dark:text-gray-300"><?php echo e($revisionRequestedTopics->count()); ?></span>
                </div>
                <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-950">
                    <div class="divide-y divide-gray-200 dark:divide-gray-800">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $revisionRequestedTopics; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $topic): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <?php
                                $latestRevisionReview = $topic->reviews->where('decision', 'revision_requested')->last() ?? $topic->reviews->last();
                            ?>
                            <article data-revision-task class="grid min-w-0 gap-5 p-5 sm:p-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)] lg:gap-8">
                                <div class="flex min-w-0 flex-col items-start gap-3">
                                    <span class="inline-flex items-center gap-2 text-xs font-semibold text-[#7A0019] dark:text-red-300"><span class="h-1.5 w-1.5 rounded-full bg-current" aria-hidden="true"></span>Changes requested</span>
                                    <h3 class="max-w-prose break-words text-base font-semibold leading-6 text-gray-950 dark:text-white"><?php echo e($topic->title); ?></h3>
                                    <p class="text-xs leading-5 text-gray-500 dark:text-gray-400"><?php echo e($topic->researchCall?->title ?? 'Independent submission'); ?></p>
                                    <a href="<?php echo e(route('faculty.topics.revision', $topic)); ?>" class="mt-1 inline-flex min-h-11 items-center justify-center rounded-lg bg-[#7A0019] px-4 py-2.5 text-xs font-semibold text-white hover:bg-red-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#7A0019] dark:bg-red-700 dark:hover:bg-red-600 dark:focus-visible:outline-red-400">Revise and resubmit proposal</a>
                                </div>
                                <div class="min-w-0 border-t border-gray-200 pt-4 dark:border-gray-800 lg:border-l lg:border-t-0 lg:pl-8 lg:pt-0">
                                    <p class="text-xs font-semibold text-gray-700 dark:text-gray-300">Reviewer feedback</p>
                                    <p class="mt-3 max-w-prose whitespace-pre-line break-words text-sm leading-6 text-gray-600 dark:text-gray-400"><?php echo e($latestRevisionReview?->comment ?: 'The Research Office requested changes to this proposal.'); ?></p>
                                    <p class="mt-3 text-xs leading-5 text-gray-400 dark:text-gray-500">Open the revision workspace to review comments and update your papers.</p>
                                </div>
                            </article>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </div>
                </div>
            </section>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $isFacultyResearcher): ?>
            <section id="recent-drafts" aria-labelledby="recent-drafts-heading">
            <div class="flex items-end justify-between gap-4">
                <div>
                    <h3 id="recent-drafts-heading" class="text-base font-semibold text-gray-950 dark:text-white">Continue working</h3>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Your two most recently edited projects.</p>
                </div>
                <a href="<?php echo e(route('faculty.proposal-drafts.index')); ?>" class="shrink-0 text-xs font-semibold text-[#7A0019] transition hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-[#7A0019] focus:ring-offset-2 dark:text-red-300 dark:hover:text-red-200 dark:focus:ring-red-400 dark:focus:ring-offset-gray-950">View all drafts</a>
            </div>

            <div class="mt-3 grid gap-3 md:grid-cols-2">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $recentProposalDrafts->take(2); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $proposalDraft): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <?php
                        $progress = $proposalDraftProgress->get($proposalDraft->getKey());
                    ?>
                    <article class="group flex flex-col rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition-colors hover:bg-gray-50/80 dark:border-gray-800 dark:bg-gray-950 dark:hover:bg-gray-900/50">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex flex-wrap gap-2">
                                <span class="rounded-full bg-gray-950 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wider text-white dark:bg-white dark:text-gray-950">Draft</span>
                                <span class="rounded-full bg-gray-100 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wider text-gray-600 ring-1 ring-inset ring-gray-200 dark:bg-gray-900 dark:text-gray-300 dark:ring-gray-800"><?php echo e($proposalDraft->isOwnedBy(Auth::user()) ? 'Owner' : 'Team member'); ?></span>
                            </div>
                            <span class="shrink-0 text-[11px] font-medium text-gray-400">Edited <?php echo e($proposalDraft->updated_at->diffForHumans()); ?></span>
                        </div>

                        <h4 class="mt-4 line-clamp-2 text-base font-semibold leading-6 text-gray-950 dark:text-white"><?php echo e($proposalDraft->project_title ?: 'Untitled proposal'); ?></h4>
                        <p class="mt-1 line-clamp-1 text-xs text-gray-500 dark:text-gray-400"><?php echo e($proposalDraft->researchCall?->title ?? 'Draft in progress'); ?></p>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($proposalDraft->isOwnedBy(Auth::user()))): ?>
                            <p class="mt-1 text-[11px] font-semibold text-gray-400">Shared by <?php echo e($proposalDraft->owner->name); ?></p>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                        <div class="mt-5">
                            <div class="flex items-center justify-between text-[11px] font-bold text-gray-600 dark:text-gray-300">
                                <span>Project progress</span>
                                <span><?php echo e($progress['completed']); ?>/<?php echo e($progress['total']); ?> papers</span>
                            </div>
                            <div role="progressbar" aria-label="Project completion" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo e($progress['percentage']); ?>" class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                <div class="h-full rounded-full bg-[#7A0019] dark:bg-red-400" style="width: <?php echo e($progress['percentage']); ?>%"></div>
                            </div>
                        </div>

                        <div class="mt-auto flex items-center justify-between gap-4 pt-5">
                            <span class="text-[11px] font-bold text-gray-400"><?php echo e($progress['percentage']); ?>% complete</span>
                            <a href="<?php echo e(route('faculty.proposal-drafts.show', $proposalDraft)); ?>" class="inline-flex items-center gap-1.5 rounded-xl bg-gray-950 px-4 py-2.5 text-xs font-semibold text-white transition hover:bg-[#7A0019] focus:outline-none focus:ring-2 focus:ring-[#7A0019] focus:ring-offset-2 dark:bg-white dark:text-gray-950 dark:hover:bg-red-200 dark:focus:ring-red-400 dark:focus:ring-offset-gray-950">
                                Resume draft
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6" /></svg>
                            </a>
                        </div>
                    </article>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    <div class="rounded-2xl border border-gray-200 bg-white px-6 py-12 text-center shadow-sm dark:border-gray-800 dark:bg-gray-950 md:col-span-2">
                        <div class="mx-auto flex h-11 w-11 items-center justify-center rounded-full border border-gray-200 text-[#7A0019] dark:border-gray-800 dark:text-red-300">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                        </div>
                        <h4 class="mt-4 text-sm font-semibold text-gray-950 dark:text-white">No proposal drafts yet</h4>
                        <p class="mx-auto mt-1 max-w-md text-xs leading-5 text-gray-500 dark:text-gray-400">Start a proposal and complete each required paper. You can submit anytime.</p>
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
<?php /**PATH C:\laragon\www\athena-app\src\resources\views/faculty/dashboard.blade.php ENDPATH**/ ?>