<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['url', 'blockers' => [], 'readyToPrepare' => false, 'readyToSubmit' => false]));

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

foreach (array_filter((['url', 'blockers' => [], 'readyToPrepare' => false, 'readyToSubmit' => false]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    $hasFacultyBlocker = collect($blockers)->contains('owner', 'faculty');
    $status = $readyToSubmit ? 'ready' : ($readyToPrepare ? 'prepare' : ($hasFacultyBlocker || $blockers === [] ? 'incomplete' : 'office'));
    $labels = ['incomplete' => 'Complete these fields', 'office' => 'Awaiting Research Office setup', 'prepare' => 'Content complete · PDFs need preparation', 'ready' => 'Ready to turn in'];
?>

<section data-proposal-readiness data-proposal-readiness-url="<?php echo e($url); ?>" data-proposal-readiness-state="<?php echo e(json_encode(['status' => $status, 'blockers' => $blockers], JSON_THROW_ON_ERROR)); ?>" aria-live="polite" class="rounded-lg border border-slate-200 bg-white p-4 text-sm dark:border-slate-700 dark:bg-slate-900">
    <p data-proposal-readiness-label class="font-semibold text-slate-950 dark:text-white"><?php echo e($labels[$status]); ?></p>
    <ul data-proposal-readiness-issues class="mt-2 max-h-48 space-y-1 overflow-y-auto text-sm leading-6 text-slate-600 dark:text-slate-300" aria-label="Remaining submission requirements" <?php if($blockers === []): ?> hidden <?php endif; ?>>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $blockers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $blocker): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <li><?php echo e($blocker['owner'] === 'research_office' ? 'Research Office action required: ' : ''); ?><?php echo e($blocker['message']); ?></li>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
    </ul>
</section>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\components\proposal-submission-readiness.blade.php ENDPATH**/ ?>