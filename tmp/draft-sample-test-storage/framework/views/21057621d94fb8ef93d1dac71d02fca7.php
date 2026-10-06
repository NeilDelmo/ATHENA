<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['label', 'value', 'description', 'icon']));

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

foreach (array_filter((['label', 'value', 'description', 'icon']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<div <?php echo e($attributes->class(['min-w-0 rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900'])); ?>>
    <dt class="flex items-center justify-between gap-3 text-xs font-semibold text-slate-600 dark:text-slate-300">
        <?php echo e($label); ?>

        <svg class="h-4 w-4 shrink-0 text-[#7A0019] dark:text-red-300" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="<?php echo e($icon); ?>" /></svg>
    </dt>
    <dd class="mt-3 text-3xl font-bold tracking-tight tabular-nums text-slate-950 dark:text-white"><?php echo e($value); ?></dd>
    <dd class="mt-2 text-xs leading-5 text-slate-500 dark:text-slate-400"><?php echo e($description); ?></dd>
</div>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views/components/dashboard-stat.blade.php ENDPATH**/ ?>