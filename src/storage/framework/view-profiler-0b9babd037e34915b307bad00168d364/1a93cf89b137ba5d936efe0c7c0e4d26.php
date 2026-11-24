<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['label', 'value', 'description', 'icon', 'href' => null, 'priority' => false, 'tone' => null]));

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

foreach (array_filter((['label', 'value', 'description', 'icon', 'href' => null, 'priority' => false, 'tone' => null]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    $facultyTone = $tone === 'brand';
?>

<div <?php echo e($attributes->class(['relative min-w-0 p-4 sm:p-5', 'bg-brand text-white' => $facultyTone, 'rounded-xl border' => ! $facultyTone, 'border-rose-200 bg-brand-wash dark:border-rose-900 dark:bg-rose-950/30' => ! $facultyTone && $priority, 'border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900' => ! $facultyTone && ! $priority, 'hover:border-brand focus-within:ring-2 focus-within:ring-brand focus-within:ring-offset-2 dark:focus-within:ring-offset-slate-950' => $href && ! $facultyTone, 'focus-within:ring-2 focus-within:ring-inset focus-within:ring-white' => $href && $facultyTone])); ?>>
    <dt class="<?php echo \Illuminate\Support\Arr::toCssClasses(['flex items-start justify-between font-medium', 'gap-2 text-[13px] sm:gap-3 sm:text-sm text-white' => $facultyTone, 'gap-3 text-sm text-slate-600 dark:text-slate-300' => ! $facultyTone]); ?>">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($href): ?>
            <a <?php if(! str_starts_with($href, '#')): ?> wire:navigate <?php endif; ?> href="<?php echo e($href); ?>" class="after:absolute after:inset-0 after:rounded-xl focus:outline-none"><?php echo e($label); ?><span class="sr-only">: <?php echo e($value); ?></span></a>
        <?php else: ?>
            <?php echo e($label); ?>

        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <svg class="<?php echo \Illuminate\Support\Arr::toCssClasses(['shrink-0', 'h-6 w-6 text-white' => $facultyTone, 'h-4 w-4 text-[#7A0019] dark:text-red-300' => ! $facultyTone]); ?>" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="<?php echo e($icon); ?>" /></svg>
    </dt>
    <dd class="<?php echo \Illuminate\Support\Arr::toCssClasses(['mt-3 text-3xl font-bold tracking-tight tabular-nums', 'text-white' => $facultyTone, 'text-brand dark:text-rose-300' => ! $facultyTone && $priority, 'text-slate-950 dark:text-white' => ! $facultyTone && ! $priority]); ?>"><?php echo e($value); ?></dd>
    <dd class="<?php echo \Illuminate\Support\Arr::toCssClasses(['mt-2 text-sm leading-5', 'text-white/80' => $facultyTone, 'text-slate-600 dark:text-slate-400' => ! $facultyTone]); ?>"><?php echo e($description); ?></dd>
</div>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\components\dashboard-stat.blade.php ENDPATH**/ ?>