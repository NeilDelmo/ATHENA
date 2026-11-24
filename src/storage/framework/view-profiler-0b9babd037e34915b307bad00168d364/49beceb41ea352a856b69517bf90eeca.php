<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'loadedVersion' => 0,
    'stateUrl',
    'reloadUrl',
    'label' => 'paper',
    'reportRecovery' => false,
    'stateScope' => null,
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
    'loadedVersion' => 0,
    'stateUrl',
    'reloadUrl',
    'label' => 'paper',
    'reportRecovery' => false,
    'stateScope' => null,
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    if (request()->boolean('revision_embed')) {
        $reloadUrl .= (str_contains($reloadUrl, '?') ? '&' : '?').'revision_embed=1';
    }
    $showSaveConfirmation = filled(session('success'))
        && \Illuminate\Support\Str::contains((string) session('success'), 'saved');
?>

<section
    <?php if($reportRecovery): ?> data-report-version-monitor <?php else: ?> data-proposal-version-monitor <?php endif; ?>
    data-loaded-version="<?php echo e($loadedVersion); ?>"
    data-state-url="<?php echo e($stateUrl); ?>"
    <?php if($stateScope): ?> data-state-scope="<?php echo e($stateScope); ?>" <?php endif; ?>
    data-document-label="<?php echo e($label); ?>"
    class="contents"
    aria-label="Saved version"
>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showSaveConfirmation): ?>
        <div data-proposal-save-confirmation role="status" class="border-l-4 border-red-600 bg-gray-50 p-4 text-sm text-gray-950 dark:bg-slate-800/70 dark:text-white">
            <p class="font-black"><?php echo e(session('success')); ?></p>
            <p class="mt-1 text-xs leading-5">Saved just now. You can continue editing.</p>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <div data-proposal-stale-warning hidden role="alert" class="border-l-4 border-red-600 bg-red-50 p-4 text-sm text-red-950 dark:bg-red-950/40 dark:text-red-100 sm:p-5">
        <p class="font-black">The saved version has changed</p>
        <p data-proposal-stale-message class="mt-1 leading-6">This <?php echo e($label); ?> changed after you opened it.</p>
        <p class="mt-1 text-xs leading-5">Copy any unsaved text you want to keep before loading the latest saved work. Image and attachment files must be selected again.</p>
        <div class="mt-3 flex flex-wrap gap-2">
            <a data-proposal-load-latest href="<?php echo e($reloadUrl); ?>" class="inline-flex rounded-lg bg-gray-950 px-4 py-2.5 text-xs font-bold text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 dark:bg-white dark:text-gray-950">Load latest saved work</a>
        </div>
    </div>
</section>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\components\proposal-collaboration-monitor.blade.php ENDPATH**/ ?>