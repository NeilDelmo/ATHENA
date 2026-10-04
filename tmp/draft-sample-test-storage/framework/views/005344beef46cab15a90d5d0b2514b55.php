<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['fixed' => false]));

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

foreach (array_filter((['fixed' => false]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<div
    data-monitoring-action-dock
    <?php if($fixed): ?> data-monitoring-action-dock-fixed <?php endif; ?>
    <?php echo e($attributes->class([
        'flex flex-col gap-2 print:hidden sm:flex-row sm:flex-wrap sm:items-center sm:justify-end',
        'fixed inset-x-4 bottom-4 z-40 max-h-[calc(100dvh-2rem)] overflow-y-auto rounded-2xl border border-gray-200 bg-white/95 p-3 shadow-2xl ring-1 ring-black/5 backdrop-blur sm:inset-x-auto sm:right-6 sm:max-w-[calc(100vw-3rem)] dark:border-slate-700 dark:bg-slate-900/95 dark:ring-white/10' => $fixed,
        'justify-end' => ! $fixed,
        '[&>a]:w-full [&>button]:w-full [&>form]:w-full [&>form>button]:w-full sm:[&>a]:w-auto sm:[&>button]:w-auto sm:[&>form]:w-auto' => true,
    ])); ?>

>
    <?php echo e($slot); ?>

</div>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views/components/monitoring-action-dock.blade.php ENDPATH**/ ?>