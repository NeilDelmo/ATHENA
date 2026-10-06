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

     <?php $__env->slot('header', null, []); ?> 
        <?php if (isset($component)) { $__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.page-header','data' => ['title' => 'Submitted proposals','subtitle' => 'Follow each proposal through review, revision, and approval.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('page-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Submitted proposals','subtitle' => 'Follow each proposal through review, revision, and approval.']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

             <?php $__env->slot('actions', null, []); ?> <a wire:navigate href="<?php echo e(route('faculty.proposal-drafts.create')); ?>" class="dashboard-action">New Proposal</a> <?php $__env->endSlot(); ?>
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
    <div class="space-y-5">
    <nav data-submission-categories aria-label="Proposal categories" class="flex flex-wrap gap-2">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <a wire:navigate href="<?php echo e(route('faculty.submissions', ['category' => $key])); ?>"
                <?php if($category === $key): ?> aria-current="page" <?php endif; ?>
                class="<?php echo \Illuminate\Support\Arr::toCssClasses([
                    'inline-flex min-h-11 items-center gap-2 rounded-lg border px-3 py-2 text-sm font-semibold focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand',
                    'border-brand bg-brand text-white' => $category === $key,
                    'border-slate-200 bg-white text-slate-600 hover:border-slate-400 hover:text-slate-950 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-slate-500 dark:hover:text-white' => $category !== $key,
                ]); ?>">
                <?php echo e($label); ?> <span class="text-xs tabular-nums opacity-80"><?php echo e($categoryCounts[$key]); ?></span>
            </a>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
    </nav>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($category === 'monitoring'): ?>
        <p class="max-w-3xl text-sm leading-6 text-slate-600 dark:text-slate-400">These projects are in monitoring. You can view their submitted proposals here. Manage monitoring and reports in the Faculty Researcher workspace.</p>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <section data-faculty-submissions class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900" aria-label="Your submitted proposals">
        <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-4 py-4 dark:border-slate-800 sm:px-6">
            <h2 class="text-sm font-semibold text-slate-900 dark:text-white"><?php echo e($categories[$category]); ?></h2>
            <span class="text-xs text-slate-500 dark:text-slate-400"><?php echo e($topics->total()); ?> <?php echo e(str('proposal')->plural($topics->total())); ?></span>
        </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $topics; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $topic): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <?php
                $latestVersion = $topic->versions->sortByDesc('version_number')->first();
                $topicCategory = $topic->submissionCategory();
                $statusLabel = $topic->isCompletedProject() ? 'Research completed' : ($topicCategory === 'monitoring' ? 'In monitoring' : ($topic->status === 'rejected' ? 'Proposal rejected' : $topic->workflowStatusLabel($latestVersion)));
            ?>
            <article data-submitted-proposal="<?php echo e($topic->id); ?>" data-submission-category="<?php echo e($topicCategory); ?>" class="flex flex-col gap-4 border-b border-slate-100 px-4 py-5 last:border-b-0 dark:border-slate-800 lg:flex-row lg:items-center lg:justify-between sm:px-6">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-3">
                        <h3 class="text-base font-semibold text-slate-950 dark:text-white"><?php echo e($topic->title); ?></h3>
                        <span class="<?php echo \Illuminate\Support\Arr::toCssClasses([
                            'rounded-full px-3 py-1 text-xs font-medium',
                            'bg-amber-50 text-amber-800 dark:bg-amber-950/40 dark:text-amber-200' => $topicCategory === 'revision',
                            'bg-emerald-50 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200' => $topicCategory === 'monitoring',
                            'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' => ! in_array($topicCategory, ['revision', 'monitoring'], true),
                        ]); ?>"><?php echo e($statusLabel); ?></span>
                    </div>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400"><?php echo e($topic->researchCall?->title ?? 'Independent submission'); ?></p>
                    <p class="mt-1 text-xs text-slate-400">Submitted <?php echo e($topic->created_at->format('M j, Y')); ?></p>
                </div>
                <div class="flex shrink-0 flex-wrap items-center gap-2 lg:max-w-sm lg:justify-end">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($topic->status === 'revision_requested'): ?>
                        <a wire:navigate href="<?php echo e(route('faculty.topics.revision', $topic)); ?>" class="dashboard-action" data-revision-action-required>Revise proposal</a>
                        <a wire:navigate href="<?php echo e(route('topics.show', $topic)); ?>" class="inline-flex min-h-11 items-center rounded-lg px-3 text-xs font-semibold text-slate-600 hover:text-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand dark:text-slate-300">View proposal</a>
                    <?php else: ?>
                        <a wire:navigate href="<?php echo e(route('topics.show', $topic)); ?>" class="dashboard-action">View proposal</a>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </article>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            <div class="px-6 py-16 text-center">
                <h3 class="text-base font-semibold text-slate-900 dark:text-white"><?php echo e($category === 'active' ? 'No active proposals' : 'No proposals in this category'); ?></h3>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($category === 'active'): ?>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Complete a draft proposal to submit new work, or view your projects in the other categories.</p>
                    <a wire:navigate href="<?php echo e(route('faculty.proposal-drafts.index')); ?>" class="dashboard-action mt-5">Open draft proposals</a>
                <?php else: ?>
                    <a wire:navigate href="<?php echo e(route('faculty.submissions')); ?>" class="dashboard-action mt-5">View active proposals</a>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </section>
    <div class="mt-5"><?php echo e($topics->links()); ?></div>
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
<?php /**PATH C:\laragon\www\athena-app\src\resources\views/faculty/submissions.blade.php ENDPATH**/ ?>