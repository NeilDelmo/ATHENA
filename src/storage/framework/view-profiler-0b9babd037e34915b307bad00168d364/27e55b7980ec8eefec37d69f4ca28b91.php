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

     <?php $__env->slot('header', null, []); ?> <?php if (isset($component)) { $__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.page-header','data' => ['variant' => 'hero','title' => 'Secretary dashboard','subtitle' => now()->format('l, F j, Y')]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('page-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['variant' => 'hero','title' => 'Secretary dashboard','subtitle' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(now()->format('l, F j, Y'))]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>
 <?php $__env->slot('actions', null, []); ?> <a href="<?php echo e(route('research_secretary.reports.index')); ?>" class="secretary-button-primary"><?php if (isset($component)) { $__componentOriginal2cc340f7d439e305c6ca7bbe0688c9ad = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2cc340f7d439e305c6ca7bbe0688c9ad = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.report-library-icon','data' => ['name' => 'list','class' => 'h-5 w-5']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('report-library-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'list','class' => 'h-5 w-5']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2cc340f7d439e305c6ca7bbe0688c9ad)): ?>
<?php $attributes = $__attributesOriginal2cc340f7d439e305c6ca7bbe0688c9ad; ?>
<?php unset($__attributesOriginal2cc340f7d439e305c6ca7bbe0688c9ad); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2cc340f7d439e305c6ca7bbe0688c9ad)): ?>
<?php $component = $__componentOriginal2cc340f7d439e305c6ca7bbe0688c9ad; ?>
<?php unset($__componentOriginal2cc340f7d439e305c6ca7bbe0688c9ad); ?>
<?php endif; ?> Review reports <?php if (isset($component)) { $__componentOriginal2cc340f7d439e305c6ca7bbe0688c9ad = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2cc340f7d439e305c6ca7bbe0688c9ad = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.report-library-icon','data' => ['name' => 'open','class' => 'h-4 w-4']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('report-library-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'open','class' => 'h-4 w-4']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2cc340f7d439e305c6ca7bbe0688c9ad)): ?>
<?php $attributes = $__attributesOriginal2cc340f7d439e305c6ca7bbe0688c9ad; ?>
<?php unset($__attributesOriginal2cc340f7d439e305c6ca7bbe0688c9ad); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2cc340f7d439e305c6ca7bbe0688c9ad)): ?>
<?php $component = $__componentOriginal2cc340f7d439e305c6ca7bbe0688c9ad; ?>
<?php unset($__componentOriginal2cc340f7d439e305c6ca7bbe0688c9ad); ?>
<?php endif; ?></a> <?php $__env->endSlot(); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e)): ?>
<?php $attributes = $__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e; ?>
<?php unset($__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e)): ?>
<?php $component = $__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e; ?>
<?php unset($__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e); ?>
<?php endif; ?> <?php $__env->endSlot(); ?>
    <?php ($attentionTotal = $reportReviewTasks->count() + $signingProjects->total()); ?>
    <div class="secretary-workspace" data-secretary-workspace="dashboard" data-dashboard-layout="secretary-overview">
        <section aria-label="Office workload">
            <dl class="grid grid-cols-2 gap-px overflow-hidden rounded-xl border border-brand bg-white/20 sm:grid-cols-4">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = [['Items that need attention', $attentionTotal, 'history'], ['Report reviews', $reportReviewTasks->count(), 'list'], ['Awaiting release', $signingProjects->total(), 'submit'], ['Projects monitored', $projectCount, 'folder-open']]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$label, $count, $icon]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <div class="min-w-0 bg-brand px-4 py-4 text-white">
                        <dt class="flex items-start justify-between gap-3 text-sm font-medium leading-5"><span><?php echo e($label); ?></span><?php if (isset($component)) { $__componentOriginal2cc340f7d439e305c6ca7bbe0688c9ad = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2cc340f7d439e305c6ca7bbe0688c9ad = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.report-library-icon','data' => ['name' => $icon,'class' => 'h-5 w-5']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('report-library-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($icon),'class' => 'h-5 w-5']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2cc340f7d439e305c6ca7bbe0688c9ad)): ?>
<?php $attributes = $__attributesOriginal2cc340f7d439e305c6ca7bbe0688c9ad; ?>
<?php unset($__attributesOriginal2cc340f7d439e305c6ca7bbe0688c9ad); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2cc340f7d439e305c6ca7bbe0688c9ad)): ?>
<?php $component = $__componentOriginal2cc340f7d439e305c6ca7bbe0688c9ad; ?>
<?php unset($__componentOriginal2cc340f7d439e305c6ca7bbe0688c9ad); ?>
<?php endif; ?></dt>
                        <dd class="mt-2 text-3xl font-bold leading-8 tabular-nums"><?php echo e($count); ?></dd>
                    </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </dl>
        </section>
        <div class="secretary-dashboard-columns" data-dashboard-columns>
            <?php if (isset($component)) { $__componentOriginalc1f3b0c35a500934893020d7e95a5eca = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc1f3b0c35a500934893020d7e95a5eca = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.secretary-report-queue','data' => ['reports' => $reportReviewTasks,'compact' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('secretary-report-queue'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['reports' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($reportReviewTasks),'compact' => true]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalc1f3b0c35a500934893020d7e95a5eca)): ?>
<?php $attributes = $__attributesOriginalc1f3b0c35a500934893020d7e95a5eca; ?>
<?php unset($__attributesOriginalc1f3b0c35a500934893020d7e95a5eca); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc1f3b0c35a500934893020d7e95a5eca)): ?>
<?php $component = $__componentOriginalc1f3b0c35a500934893020d7e95a5eca; ?>
<?php unset($__componentOriginalc1f3b0c35a500934893020d7e95a5eca); ?>
<?php endif; ?>
            <aside class="secretary-office-links" aria-label="Office work">
                <section class="secretary-panel secretary-release-summary" aria-labelledby="secretary-release-heading">
                    <header class="secretary-section-heading"><div><h2 id="secretary-release-heading"><?php if (isset($component)) { $__componentOriginal2cc340f7d439e305c6ca7bbe0688c9ad = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2cc340f7d439e305c6ca7bbe0688c9ad = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.report-library-icon','data' => ['name' => 'submit','class' => 'h-5 w-5']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('report-library-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'submit','class' => 'h-5 w-5']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2cc340f7d439e305c6ca7bbe0688c9ad)): ?>
<?php $attributes = $__attributesOriginal2cc340f7d439e305c6ca7bbe0688c9ad; ?>
<?php unset($__attributesOriginal2cc340f7d439e305c6ca7bbe0688c9ad); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2cc340f7d439e305c6ca7bbe0688c9ad)): ?>
<?php $component = $__componentOriginal2cc340f7d439e305c6ca7bbe0688c9ad; ?>
<?php unset($__componentOriginal2cc340f7d439e305c6ca7bbe0688c9ad); ?>
<?php endif; ?> Notices to Proceed</h2></div><span class="secretary-count"><?php echo e($signingProjects->total()); ?></span></header>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $signingProjects->take(2); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $project): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <article class="secretary-release-row"><h3><?php echo e($project->title); ?></h3><p class="secretary-muted"><?php echo e($project->user?->name); ?></p><p class="secretary-waiting"><?php echo e($project->status_started_at ? ((int) max(0, $project->status_started_at->diffInDays(now(), false))).' days waiting' : 'Clearance date unavailable'); ?></p><a href="<?php echo e(route('topics.show', $project)); ?>#notice-to-proceed" class="secretary-button-secondary"><?php if (isset($component)) { $__componentOriginal2cc340f7d439e305c6ca7bbe0688c9ad = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2cc340f7d439e305c6ca7bbe0688c9ad = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.report-library-icon','data' => ['name' => 'rename','class' => 'h-4 w-4']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('report-library-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'rename','class' => 'h-4 w-4']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2cc340f7d439e305c6ca7bbe0688c9ad)): ?>
<?php $attributes = $__attributesOriginal2cc340f7d439e305c6ca7bbe0688c9ad; ?>
<?php unset($__attributesOriginal2cc340f7d439e305c6ca7bbe0688c9ad); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2cc340f7d439e305c6ca7bbe0688c9ad)): ?>
<?php $component = $__componentOriginal2cc340f7d439e305c6ca7bbe0688c9ad; ?>
<?php unset($__componentOriginal2cc340f7d439e305c6ca7bbe0688c9ad); ?>
<?php endif; ?> <?php echo e($project->hasPreparedNoticeToProceed() ? 'Upload signed documents' : 'Prepare Notice to Proceed'); ?> <?php if (isset($component)) { $__componentOriginal2cc340f7d439e305c6ca7bbe0688c9ad = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2cc340f7d439e305c6ca7bbe0688c9ad = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.report-library-icon','data' => ['name' => 'open','class' => 'h-4 w-4']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('report-library-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'open','class' => 'h-4 w-4']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2cc340f7d439e305c6ca7bbe0688c9ad)): ?>
<?php $attributes = $__attributesOriginal2cc340f7d439e305c6ca7bbe0688c9ad; ?>
<?php unset($__attributesOriginal2cc340f7d439e305c6ca7bbe0688c9ad); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2cc340f7d439e305c6ca7bbe0688c9ad)): ?>
<?php $component = $__componentOriginal2cc340f7d439e305c6ca7bbe0688c9ad; ?>
<?php unset($__componentOriginal2cc340f7d439e305c6ca7bbe0688c9ad); ?>
<?php endif; ?></a></article>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?><div class="secretary-empty"><h3>No documents waiting.</h3><p>Cleared projects appear here for release.</p></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <a href="<?php echo e(route('research_secretary.notices.index')); ?>" class="secretary-panel-footer secretary-button-secondary">View Notices to Proceed <?php if (isset($component)) { $__componentOriginal2cc340f7d439e305c6ca7bbe0688c9ad = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2cc340f7d439e305c6ca7bbe0688c9ad = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.report-library-icon','data' => ['name' => 'open','class' => 'h-4 w-4']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('report-library-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'open','class' => 'h-4 w-4']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2cc340f7d439e305c6ca7bbe0688c9ad)): ?>
<?php $attributes = $__attributesOriginal2cc340f7d439e305c6ca7bbe0688c9ad; ?>
<?php unset($__attributesOriginal2cc340f7d439e305c6ca7bbe0688c9ad); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2cc340f7d439e305c6ca7bbe0688c9ad)): ?>
<?php $component = $__componentOriginal2cc340f7d439e305c6ca7bbe0688c9ad; ?>
<?php unset($__componentOriginal2cc340f7d439e305c6ca7bbe0688c9ad); ?>
<?php endif; ?></a>
                </section>
            </aside>
        </div>
        <div class="grid gap-4 lg:grid-cols-2">
            <a href="<?php echo e(route('research_secretary.budgets.index')); ?>" class="secretary-target-link"><span class="secretary-file-mark" aria-hidden="true"><?php if (isset($component)) { $__componentOriginal2cc340f7d439e305c6ca7bbe0688c9ad = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2cc340f7d439e305c6ca7bbe0688c9ad = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.report-library-icon','data' => ['name' => 'presentation','class' => 'h-5 w-5']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('report-library-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'presentation','class' => 'h-5 w-5']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2cc340f7d439e305c6ca7bbe0688c9ad)): ?>
<?php $attributes = $__attributesOriginal2cc340f7d439e305c6ca7bbe0688c9ad; ?>
<?php unset($__attributesOriginal2cc340f7d439e305c6ca7bbe0688c9ad); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2cc340f7d439e305c6ca7bbe0688c9ad)): ?>
<?php $component = $__componentOriginal2cc340f7d439e305c6ca7bbe0688c9ad; ?>
<?php unset($__componentOriginal2cc340f7d439e305c6ca7bbe0688c9ad); ?>
<?php endif; ?></span><span><strong>Budget monitoring</strong><small>View project budgets and reported disbursements.</small></span><?php if (isset($component)) { $__componentOriginal2cc340f7d439e305c6ca7bbe0688c9ad = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2cc340f7d439e305c6ca7bbe0688c9ad = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.report-library-icon','data' => ['name' => 'open','class' => 'h-5 w-5']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('report-library-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'open','class' => 'h-5 w-5']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2cc340f7d439e305c6ca7bbe0688c9ad)): ?>
<?php $attributes = $__attributesOriginal2cc340f7d439e305c6ca7bbe0688c9ad; ?>
<?php unset($__attributesOriginal2cc340f7d439e305c6ca7bbe0688c9ad); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2cc340f7d439e305c6ca7bbe0688c9ad)): ?>
<?php $component = $__componentOriginal2cc340f7d439e305c6ca7bbe0688c9ad; ?>
<?php unset($__componentOriginal2cc340f7d439e305c6ca7bbe0688c9ad); ?>
<?php endif; ?></a>
            <a href="<?php echo e(route('research_secretary.targets.index')); ?>" class="secretary-target-link"><span class="secretary-file-mark" aria-hidden="true"><?php if (isset($component)) { $__componentOriginal2cc340f7d439e305c6ca7bbe0688c9ad = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2cc340f7d439e305c6ca7bbe0688c9ad = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.report-library-icon','data' => ['name' => 'calendar','class' => 'h-5 w-5']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('report-library-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'calendar','class' => 'h-5 w-5']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2cc340f7d439e305c6ca7bbe0688c9ad)): ?>
<?php $attributes = $__attributesOriginal2cc340f7d439e305c6ca7bbe0688c9ad; ?>
<?php unset($__attributesOriginal2cc340f7d439e305c6ca7bbe0688c9ad); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2cc340f7d439e305c6ca7bbe0688c9ad)): ?>
<?php $component = $__componentOriginal2cc340f7d439e305c6ca7bbe0688c9ad; ?>
<?php unset($__componentOriginal2cc340f7d439e305c6ca7bbe0688c9ad); ?>
<?php endif; ?></span><span><strong>Research targets</strong><small>Annual college targets and recorded accomplishments.</small></span><?php if (isset($component)) { $__componentOriginal2cc340f7d439e305c6ca7bbe0688c9ad = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2cc340f7d439e305c6ca7bbe0688c9ad = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.report-library-icon','data' => ['name' => 'open','class' => 'h-5 w-5']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('report-library-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'open','class' => 'h-5 w-5']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2cc340f7d439e305c6ca7bbe0688c9ad)): ?>
<?php $attributes = $__attributesOriginal2cc340f7d439e305c6ca7bbe0688c9ad; ?>
<?php unset($__attributesOriginal2cc340f7d439e305c6ca7bbe0688c9ad); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2cc340f7d439e305c6ca7bbe0688c9ad)): ?>
<?php $component = $__componentOriginal2cc340f7d439e305c6ca7bbe0688c9ad; ?>
<?php unset($__componentOriginal2cc340f7d439e305c6ca7bbe0688c9ad); ?>
<?php endif; ?></a>
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
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\research_secretary\dashboard.blade.php ENDPATH**/ ?>