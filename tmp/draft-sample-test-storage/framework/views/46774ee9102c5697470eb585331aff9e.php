<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['type' => 'success']));

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

foreach (array_filter((['type' => 'success']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    $styles = match ($type) {
        'error' => 'border-red-200 bg-red-50 text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200',
        'warning' => 'border-red-600 bg-red-50 text-red-950 dark:bg-red-950/40 dark:text-red-100',
        default => 'border-red-600 bg-gray-50 text-gray-950 dark:bg-slate-800/70 dark:text-white',
    };

    $icon = match ($type) {
        'error' => 'error',
        'warning' => 'warning',
        default => 'success',
    };
?>

<div
    data-proposal-alert
    data-alert-icon="<?php echo e($icon); ?>"
    role="<?php echo e($type === 'error' ? 'alert' : 'status'); ?>"
    <?php echo e($attributes->class(['border-l-4 px-4 py-3 text-sm', $styles, 'font-semibold' => $type !== 'error'])); ?>

>
    <?php echo e($slot); ?>

</div>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views/components/proposal-alert.blade.php ENDPATH**/ ?>