<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['item']));

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

foreach (array_filter((['item']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<dl class="report-library-details">
    <div><dt>Status</dt><dd class="<?php echo \Illuminate\Support\Arr::toCssClasses(['font-medium', 'text-emerald-700 dark:text-emerald-300' => $item['status'] === 'Submitted', 'text-amber-800 dark:text-amber-300' => in_array($item['status'], ['Correction needed', 'Returned for correction'])]); ?>"><?php echo e($item['status']); ?></dd></div>
    <div><dt>Reports</dt><dd class="tabular-nums"><?php echo e($item['report_count']); ?></dd></div>
    <div><dt>Last submitted</dt><dd><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item['submitted_at']): ?><time datetime="<?php echo e($item['submitted_at']); ?>"><?php echo e($item['submitted_label']); ?></time><?php else: ?><span aria-label="No submission">—</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></dd></div>
    <div><dt>Submitted by</dt><dd><?php echo e($item['submitted_by'] ?? '—'); ?></dd></div>
</dl>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\components\report-library-details.blade.php ENDPATH**/ ?>