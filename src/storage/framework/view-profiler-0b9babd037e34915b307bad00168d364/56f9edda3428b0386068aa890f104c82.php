<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['eyebrow', 'title', 'description']));

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

foreach (array_filter((['eyebrow', 'title', 'description']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<div data-workspace-header-banner <?php echo e($attributes->class(['flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between'])); ?>>
    <div class="min-w-0">
        <p class="text-[11px] font-semibold text-[#7A0019] dark:text-red-300"><?php echo e($eyebrow); ?></p>
        <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-950 dark:text-white"><?php echo e($title); ?></h1>
        <p class="mt-1 text-sm leading-6 text-slate-500 dark:text-slate-400"><?php echo e($description); ?></p>
    </div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($actions)): ?>
        <div class="flex shrink-0 flex-wrap items-center gap-2"><?php echo e($actions); ?></div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\components\dashboard-heading.blade.php ENDPATH**/ ?>