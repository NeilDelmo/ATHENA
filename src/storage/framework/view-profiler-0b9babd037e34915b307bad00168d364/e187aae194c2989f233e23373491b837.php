<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'date',
    'weekday' => false,
    'time' => false,
    'relative' => false,
    'year' => false,
    'compact' => false,
    'tone' => 'rose',
]));

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

foreach (array_filter(([
    'date',
    'weekday' => false,
    'time' => false,
    'relative' => false,
    'year' => false,
    'compact' => false,
    'tone' => 'rose',
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    $carbon = $date instanceof \Illuminate\Support\Carbon ? $date : \Illuminate\Support\Carbon::parse($date);
    $meta = collect([
        $weekday ? $carbon->format('D') : null,
        $time && ! $carbon->isMidnight() ? $carbon->format('g:i A') : null,
        $relative ? $carbon->diffForHumans(short: true) : null,
    ])->filter()->implode(' · ');
    $tones = [
        'rose' => 'border-rose-100 bg-rose-50/90 text-[#7A0019] dark:border-red-900/50 dark:bg-red-950/50 dark:text-red-300',
        'amber' => 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900/60 dark:bg-amber-950/45 dark:text-amber-200',
        'blue' => 'border-sky-200 bg-sky-50 text-sky-800 dark:border-sky-900/60 dark:bg-sky-950/45 dark:text-sky-200',
        'stone' => 'border-stone-200 bg-stone-50 text-stone-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-400',
    ];
?>

<span <?php echo e($attributes->merge(['class' => 'inline-flex items-center gap-2 align-middle'])); ?>>
    <span class="<?php echo e($compact ? 'px-1.5 py-1' : 'px-2.5 py-1.5'); ?> inline-flex flex-col items-center justify-center rounded-2xl border leading-none shadow-bubble-sm <?php echo e($tones[$tone] ?? $tones['rose']); ?>">
        <span class="text-[8px] font-black uppercase tracking-[0.18em]"><?php echo e($carbon->format('M')); ?></span>
        <span class="<?php echo e($compact ? 'text-xs' : 'text-sm'); ?> mt-0.5 font-black tabular-nums text-gray-950 dark:text-white"><?php echo e($carbon->format('j')); ?></span>
    </span>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(filled($meta) || $year): ?>
        <span class="min-w-0 text-[11px] font-semibold leading-tight text-gray-500 dark:text-gray-400">
            <?php echo e($meta); ?>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($year && $carbon->year !== now()->year): ?>
                <span class="block text-[10px] font-bold text-[#7A0019] dark:text-red-300"><?php echo e($carbon->format('Y')); ?></span>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </span>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</span>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\components\date-chip.blade.php ENDPATH**/ ?>