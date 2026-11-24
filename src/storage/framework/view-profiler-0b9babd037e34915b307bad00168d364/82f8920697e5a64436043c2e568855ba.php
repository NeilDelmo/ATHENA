<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['proposals', 'coordinator', 'compact' => false]));

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

foreach (array_filter((['proposals', 'coordinator', 'compact' => false]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<section id="office-lrec-proposals" data-coordinator-lrec-queue class="secretary-panel" aria-labelledby="office-lrec-heading">
    <header class="secretary-section-heading">
        <div><p class="secretary-eyebrow"><?php echo e($compact ? 'Your committee worklist' : 'College review queue'); ?></p><h2 id="office-lrec-heading">LREC proposals</h2></div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($compact): ?><a href="<?php echo e(route('research_coordinator.proposals.index')); ?>" class="secretary-button-secondary">View all <?php echo e($proposals->total()); ?> <span aria-hidden="true">→</span></a><?php else: ?><span class="secretary-count"><?php echo e($proposals->total()); ?> in your college</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </header>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $coordinator->college): ?>
        <div class="secretary-empty"><h3>Your account needs an assigned college</h3><p>Set your college in your account profile to see its LREC proposals.</p></div>
    <?php else: ?>
        <div data-coordinator-priority-list>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $compact ? $proposals->take(4) : $proposals; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $proposal): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <?php
                    $waitingSince = $proposal->status_started_at;
                    $waitingDays = $waitingSince ? (int) max(0, $waitingSince->diffInDays(now(), false)) : null;
                    $actionable = $proposal->status === \App\Models\TopicProposal::STATUS_LREC_REVIEW;
                ?>
                <article class="secretary-report-row">
                    <span class="secretary-file-mark" aria-hidden="true"><?php if (isset($component)) { $__componentOriginal2cc340f7d439e305c6ca7bbe0688c9ad = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2cc340f7d439e305c6ca7bbe0688c9ad = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.report-library-icon','data' => ['name' => ''.e($actionable ? 'publication' : 'history').'','class' => 'h-5 w-5']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('report-library-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => ''.e($actionable ? 'publication' : 'history').'','class' => 'h-5 w-5']); ?>
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
<?php endif; ?></span>
                    <div class="min-w-0 flex-1">
                        <div class="secretary-row-meta"><span><?php echo e($proposal->workflowStatusLabel($proposal->latestVersion)); ?></span><span>Version <?php echo e($proposal->latestVersion?->version_number ?? '—'); ?></span></div>
                        <h3><?php echo e($proposal->title); ?></h3>
                        <p class="secretary-muted"><?php echo e($proposal->user?->name); ?></p>
                        <p class="secretary-waiting"><?php echo e($waitingDays !== null ? $waitingDays.' '.str('day')->plural($waitingDays).' in this stage' : 'Stage start date unavailable'); ?></p>
                    </div>
                    <div class="secretary-row-actions"><a href="<?php echo e(route('topics.show', $proposal)); ?>#proposal-review" class="<?php echo e($actionable ? 'secretary-button-primary' : 'secretary-button-secondary'); ?>"><?php echo e($actionable ? 'Record comments' : 'View review status'); ?> <span aria-hidden="true">→</span></a></div>
                </article>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                <div class="secretary-empty"><span class="secretary-empty-icon" aria-hidden="true">✓</span><h3><?php echo e(request()->filled('q') || request()->filled('status') ? 'No proposals match your filters.' : 'No proposals awaiting LREC follow-up.'); ?></h3><p><?php echo e(request()->filled('q') || request()->filled('status') ? 'Try another title, project leader, or review status.' : 'Proposals from your college appear here when they reach LREC.'); ?></p></div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $compact && $proposals->hasPages()): ?><footer class="border-t border-slate-100 p-5 dark:border-slate-800"><?php echo e($proposals->links()); ?></footer><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</section>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\components\coordinator-lrec-queue.blade.php ENDPATH**/ ?>