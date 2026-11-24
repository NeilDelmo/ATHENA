<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['date']));

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

foreach (array_filter((['date']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    $deadline = \Carbon\CarbonImmutable::parse($date)->timezone(config('app.timezone'));
    $today = \Carbon\CarbonImmutable::now(config('app.timezone'));
    $days = (int) $today->startOfDay()->diffInDays($deadline->startOfDay(), false);
    $label = match (true) {
        $days < 0 => abs($days).' '.str('day')->plural(abs($days)).' overdue',
        $days === 0 => $deadline->isPast() ? 'Overdue today' : 'Due today',
        $days === 1 => 'Due tomorrow',
        default => 'Due in '.$days.' days',
    };
?>

<span <?php echo e($attributes->class(['inline-flex flex-wrap items-baseline gap-x-2 gap-y-1 text-sm'])); ?>>
    <span class="<?php echo \Illuminate\Support\Arr::toCssClasses(['font-semibold tabular-nums', 'text-brand dark:text-rose-300' => $days <= 3, 'text-slate-700 dark:text-slate-200' => $days > 3]); ?>"><?php echo e($label); ?></span>
    <time datetime="<?php echo e($deadline->toIso8601String()); ?>" class="text-slate-600 dark:text-slate-400"><?php echo e($deadline->format('M j, Y')); ?></time>
</span>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\components\dashboard-deadline.blade.php ENDPATH**/ ?>