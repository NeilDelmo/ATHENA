<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['item', 'label' => null, 'tone' => null, 'variant' => 'filled', 'help' => false]));

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

foreach (array_filter((['item', 'label' => null, 'tone' => null, 'variant' => 'filled', 'help' => false]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    $actionLabel = $label ?? $item['label'];
?>

<button
    type="button"
    data-review-suggestion="<?php echo e(Illuminate\Support\Js::encode($item)); ?>"
    <?php if($help): ?> data-proposal-help <?php endif; ?>
    @click="$store.researchAssistant.<?php echo e($help ? 'openProposalHelp' : 'startSuggestedReview'); ?>(JSON.parse($el.dataset.reviewSuggestion), $el)"
    :disabled="$store.researchAssistant.reviewLaunchBusy()"
    aria-controls="athena-assistant-workspace"
    aria-label="<?php echo e($actionLabel); ?> for <?php echo e($item['target_label']); ?> with Athena"
    <?php echo e($attributes->class(['inline-flex min-h-11 shrink-0 items-center gap-2 rounded-lg border px-3 py-2 text-xs font-semibold focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand disabled:cursor-wait disabled:opacity-50 dark:focus-visible:outline-rose-400', 'border-brand bg-white text-brand hover:bg-stone-50 dark:border-red-400 dark:bg-stone-900 dark:text-red-300 dark:hover:border-red-300 dark:hover:bg-stone-800' => $variant === 'outline', 'border-brand bg-brand text-white hover:border-brand-soft hover:bg-brand-soft dark:border-brand-soft dark:bg-brand dark:text-white dark:hover:border-brand-soft dark:hover:bg-brand-soft' => $variant !== 'outline' && $tone === 'brand', 'border-slate-200 text-brand hover:border-brand hover:bg-brand-wash dark:border-slate-700 dark:text-rose-300 dark:hover:border-rose-400 dark:hover:bg-rose-950/30' => $variant !== 'outline' && $tone !== 'brand'])); ?>

>
    <?php if (isset($component)) { $__componentOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.assistant-icon','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('assistant-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b)): ?>
<?php $attributes = $__attributesOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b; ?>
<?php unset($__attributesOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b)): ?>
<?php $component = $__componentOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b; ?>
<?php unset($__componentOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b); ?>
<?php endif; ?>
    <?php echo e($actionLabel); ?>

</button>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\components\dashboard-assistant-action.blade.php ENDPATH**/ ?>