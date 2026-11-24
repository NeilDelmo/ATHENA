<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['stages' => []]));

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

foreach (array_filter((['stages' => []]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    $knownStages = array_intersect(array_keys(\App\Services\CommentResponseFeedback::STAGE_LABELS), $stages);
    $levels = [
        ['label' => 'Initial Screening', 'active' => count(array_diff($knownStages, ['lrec'])) > 0],
        ['label' => 'Local Research Evaluation', 'active' => in_array('lrec', $knownStages, true)],
    ];
?>

<section <?php echo e($attributes->class(['rounded-xl border border-gray-200 bg-white p-4 text-black dark:border-gray-700 dark:bg-gray-950 dark:text-white'])); ?> data-comment-response-stages aria-label="Level of evaluation done">
    <p class="mb-3 text-xs font-bold uppercase tracking-wide">LEVEL OF EVALUATION DONE:</p>
    <ul class="space-y-1">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $levels; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $level): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <li data-evaluation-level="<?php echo e($loop->index); ?>" data-stage-active="<?php echo e($level['active'] ? 'true' : 'false'); ?>" class="flex items-center gap-3 text-sm">
                <span class="<?php echo \Illuminate\Support\Arr::toCssClasses(['inline-flex h-4 w-4 shrink-0 border border-black', 'bg-black' => $level['active'], 'bg-white' => ! $level['active']]); ?>" aria-hidden="true"></span>
                <span><?php echo e($level['label']); ?><span class="sr-only"><?php echo e($level['active'] ? ' — Selected' : ' — Not selected'); ?></span></span>
            </li>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
    </ul>
</section>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\components\comment-response-stages.blade.php ENDPATH**/ ?>