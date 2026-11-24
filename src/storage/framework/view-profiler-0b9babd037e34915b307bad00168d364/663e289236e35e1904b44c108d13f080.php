<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['toolbarId', 'toggle' => false]));

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

foreach (array_filter((['toolbarId', 'toggle' => false]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<div data-proposal-workspace-toolbar :inert="previewFullscreen" :class="{ 'proposal-writing-toolbar-closed': !writingToolbarOpen }" <?php echo e($attributes->class(['proposal-writing-toolbar'])); ?> role="group">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($toggle): ?>
        <button type="button" data-writing-toolbar-open data-writing-toolbar-toggle x-show="!writingToolbarOpen" x-cloak @click="showWritingToolbar()" aria-controls="<?php echo e($toolbarId); ?>-panel" :aria-expanded="writingToolbarOpen">Show tools</button>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <div <?php if($toggle): ?> id="<?php echo e($toolbarId); ?>-panel" data-writing-toolbar-panel class="proposal-writing-toolbar-panel" x-show="writingToolbarOpen" x-cloak <?php endif; ?>>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($toggle): ?>
            <div id="<?php echo e($toolbarId); ?>-controls" data-writing-toolbar-controls x-show="writingToolbarOpen"><?php echo e($slot); ?></div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <div class="proposal-writing-toolbar-heading">
            <div x-show="writingToolbarOpen" class="proposal-writing-toolbar-context"><?php echo e($context); ?></div>
            <button type="button" data-writing-toolbar-close x-show="writingToolbarOpen" @click="closeWritingToolbar()" aria-label="<?php echo e($toggle ? 'Close writing tools' : 'Hide tools'); ?>" aria-controls="<?php echo e($toolbarId); ?>-controls" :aria-expanded="writingToolbarOpen">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18" /></svg>
            </button>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($toggle)): ?>
                <button type="button" data-writing-toolbar-open x-show="!writingToolbarOpen" x-cloak @click="showWritingToolbar()" aria-controls="<?php echo e($toolbarId); ?>-controls" :aria-expanded="writingToolbarOpen">Show tools</button>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($toggle)): ?>
            <div id="<?php echo e($toolbarId); ?>-controls" data-writing-toolbar-controls x-show="writingToolbarOpen"><?php echo e($slot); ?></div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
</div>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\components\proposal-writing-toolbar.blade.php ENDPATH**/ ?>