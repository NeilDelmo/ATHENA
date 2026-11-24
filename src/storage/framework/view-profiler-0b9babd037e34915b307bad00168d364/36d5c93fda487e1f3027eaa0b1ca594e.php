<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['attentionCounts' => []]));

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

foreach (array_filter((['attentionCounts' => []]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<?php
    $links = [
        ['Dashboard', 'research_coordinator.dashboard', 'M3 3h7v7H3zM14 3h7v7h-7zM3 14h7v7H3zM14 14h7v7h-7z'],
        ['LREC proposals', 'research_coordinator.proposals.index', 'M9 12l2 2 4-4M7 3h10l3 3v15H4V3h3m7 0v5h6'],
        ['Quarterly Reports', 'research_coordinator.reports.index', 'M8 3H5v18h14V3h-3M8 2h8v4H8zM8 11h8M8 15h8'],
        ['Faculty Members', 'research_coordinator.members.index', 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m20 0v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75M13 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z'],
        ['Research targets', 'research_coordinator.targets.index', 'M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0ZM17 12a5 5 0 1 1-10 0 5 5 0 0 1 10 0ZM13 12a1 1 0 1 1-2 0 1 1 0 0 1 2 0Z'],
        ['Post-implementation', 'research-outcomes.index', 'm8 12 3 3 5-6M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'],
    ];
    $linkClass = 'relative flex min-h-11 w-full items-center gap-3 rounded-xl px-4 py-2.5 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-100 hover:text-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand dark:text-slate-400 dark:hover:bg-slate-900 dark:hover:text-white';
    $activeClass = '!bg-brand-wash !text-brand !font-semibold before:absolute before:left-0 before:top-1/2 before:h-5 before:w-1 before:-translate-y-1/2 before:rounded-r-full before:bg-brand dark:!bg-rose-950/40 dark:!text-rose-200';
?>
<nav aria-label="Research Coordinator navigation" data-research-office-navigation class="space-y-1">
    <p x-show="$store.sidebar.open" class="mb-3 px-4 text-xs font-semibold uppercase tracking-wider text-slate-400">Research office</p>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $links; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$label, $routeName, $icon]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
        <?php ($active = $routeName === 'research-outcomes.index' ? request()->routeIs('research-outcomes.*') : request()->routeIs(str_replace('.index', '.*', $routeName))); ?>
        <a wire:navigate <?php if($routeName === 'research-outcomes.index'): ?> wire:current="<?php echo e($activeClass); ?>" <?php else: ?> wire:current.exact="<?php echo e($activeClass); ?>" <?php endif; ?>
            href="<?php echo e(route($routeName)); ?>" <?php if($active): ?> aria-current="page" <?php endif; ?>
            <?php if($routeName === 'research-outcomes.index'): ?> data-sidebar-attention-url="<?php echo e(route('sidebar-attention.open', 'research_outcomes')); ?>" <?php endif; ?>
            @click="if (window.innerWidth < 640) $store.sidebar.setOpen(false)" :class="$store.sidebar.open ? '' : '!justify-center !gap-0 !px-0'"
            aria-label="<?php echo e($label); ?>" title="<?php echo e($label); ?>" class="<?php echo e($linkClass); ?> <?php echo e($active ? $activeClass : ''); ?>">
            <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" aria-hidden="true"><path d="<?php echo e($icon); ?>" stroke-linecap="round" stroke-linejoin="round" /></svg>
            <span x-show="$store.sidebar.open" class="whitespace-nowrap"><?php echo e($label); ?></span>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($routeName === 'research-outcomes.index'): ?><?php if (isset($component)) { $__componentOriginal062ce1d70d70f82019a083841a7f4bf2 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal062ce1d70d70f82019a083841a7f4bf2 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.sidebar-attention-badge','data' => ['count' => $attentionCounts['research_outcomes'] ?? 0]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('sidebar-attention-badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['count' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($attentionCounts['research_outcomes'] ?? 0)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal062ce1d70d70f82019a083841a7f4bf2)): ?>
<?php $attributes = $__attributesOriginal062ce1d70d70f82019a083841a7f4bf2; ?>
<?php unset($__attributesOriginal062ce1d70d70f82019a083841a7f4bf2); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal062ce1d70d70f82019a083841a7f4bf2)): ?>
<?php $component = $__componentOriginal062ce1d70d70f82019a083841a7f4bf2; ?>
<?php unset($__componentOriginal062ce1d70d70f82019a083841a7f4bf2); ?>
<?php endif; ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </a>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
</nav>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\components\research-coordinator-navigation.blade.php ENDPATH**/ ?>