<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['status' => 'unsubmitted', 'label', 'description' => null, 'compact' => false]));

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

foreach (array_filter((['status' => 'unsubmitted', 'label', 'description' => null, 'compact' => false]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    $displayLabel = $compact ? match ($label) {
        'Reviewed' => 'Accepted',
        'Awaiting Research Head review' => 'Head review',
        'Awaiting Research Office Secretary review' => 'Office review',
        'Overdue' => 'Missing',
        'Targets completed' => 'Completed',
        'Targets still incomplete' => 'Incomplete',
        'Corrections requested', 'Report corrections requested' => 'Corrections',
        'Awaiting review', 'Pending review', 'Submitted', 'Submitted – awaiting review', 'Resubmitted – awaiting review' => 'Pending',
        'No tool on file', 'No report on file' => 'No file',
        'PDF prepared', 'PDF prepared · Overdue' => 'Prepared',
        'Not submitted' => 'Missing',
        default => str_starts_with($label, 'Due ') ? 'Missing' : $label,
    } : $label;
?>

<span <?php echo e($attributes->class(['report-review-status'])); ?> data-status="<?php echo e($status); ?>" data-overdue="<?php echo e(str_contains($label, 'Overdue') ? 'true' : 'false'); ?>" title="<?php echo e($description ?? $label); ?>">
    <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($status === 'reviewed'): ?>
            <path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6" />
        <?php elseif($status === 'revision_requested'): ?>
            <circle cx="12" cy="12" r="9" /><path stroke-linecap="round" d="M12 7v6m0 4h.01" />
        <?php elseif(in_array($status, ['pending', 'submitted'])): ?>
            <circle cx="12" cy="12" r="9" /><path stroke-linecap="round" d="M12 7v5l3 2" />
        <?php else: ?>
            <path stroke-linecap="round" d="M6 12h12" />
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </svg>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($displayLabel !== $label): ?>
        <span class="sr-only"><?php echo e($label); ?></span><span aria-hidden="true"><?php echo e($displayLabel); ?></span>
    <?php else: ?>
        <span><?php echo e($label); ?></span>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</span>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\components\report-review-status.blade.php ENDPATH**/ ?>