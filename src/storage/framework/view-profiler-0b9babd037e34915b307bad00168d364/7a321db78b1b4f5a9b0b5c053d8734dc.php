<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['sortOptions' => [], 'showTriggerIcon' => false]));

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

foreach (array_filter((['sortOptions' => [], 'showTriggerIcon' => false]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<div <?php echo e($attributes->merge(['class' => 'flex flex-wrap items-center gap-2 text-base leading-6 text-slate-500 dark:text-slate-400'])); ?>>
    <span class="hidden sm:inline">Ctrl + scroll to resize · Right-click for actions</span>
    <button type="button" class="workspace-secondary" aria-haspopup="menu" :aria-expanded="viewMenuOpen" :disabled="$data.saving || $data.stale" @click="openViewMenu($event)"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showTriggerIcon): ?><?php if (isset($component)) { $__componentOriginal2cc340f7d439e305c6ca7bbe0688c9ad = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2cc340f7d439e305c6ca7bbe0688c9ad = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.report-library-icon','data' => ['name' => 'actions','class' => '!h-5 !w-5']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('report-library-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'actions','class' => '!h-5 !w-5']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2cc340f7d439e305c6ca7bbe0688c9ad)): ?>
<?php $attributes = $__attributesOriginal2cc340f7d439e305c6ca7bbe0688c9ad; ?>
<?php unset($__attributesOriginal2cc340f7d439e305c6ca7bbe0688c9ad); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2cc340f7d439e305c6ca7bbe0688c9ad)): ?>
<?php $component = $__componentOriginal2cc340f7d439e305c6ca7bbe0688c9ad; ?>
<?php unset($__componentOriginal2cc340f7d439e305c6ca7bbe0688c9ad); ?>
<?php endif; ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?><span>View</span></button>
</div>
<template x-teleport="body">
    <div x-ref="libraryViewMenu" x-cloak x-show="viewMenuOpen" role="menu" aria-label="View" :aria-label="menuLabel"
        :style="{ left: viewMenuX + 'px', top: viewMenuY + 'px' }"
        @click.stop @click.outside="closeViewMenu(false)" @keydown.stop="handleViewMenuKey($event)"
        @scroll.window.capture="if (viewMenuReady && !$refs.libraryViewMenu.contains($event.target)) closeViewMenu(false)" @resize.window="closeViewMenu(false)" @open-modal.window="closeViewMenu(false)"
        class="fixed z-[200] max-h-[calc(100dvh-1rem)] w-64 max-w-[calc(100vw-1rem)] overflow-y-auto rounded-lg border border-slate-200 bg-white p-1.5 text-base text-slate-900 shadow-lg dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
        <template x-for="action in menuActions" :key="action.id">
            <div>
                <template x-if="!action.href">
                    <button type="button" role="menuitem" :disabled="action.disabled || $data.saving || $data.stale" :aria-keyshortcuts="action.shortcut?.replace('Ctrl+', 'Control+') || null" @click="activateMenuAction(action)"
                        class="flex min-h-11 w-full items-center justify-between gap-3 rounded-md px-3 py-2 text-left hover:bg-slate-100 focus-visible:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand disabled:cursor-not-allowed disabled:opacity-40 dark:hover:bg-slate-800 dark:focus-visible:bg-slate-800">
                        <span class="flex items-center gap-3"><?php if (isset($component)) { $__componentOriginal14adc2b21dae2c2506e26ab73d500f08 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal14adc2b21dae2c2506e26ab73d500f08 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.report-library-action-icon','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('report-library-action-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal14adc2b21dae2c2506e26ab73d500f08)): ?>
<?php $attributes = $__attributesOriginal14adc2b21dae2c2506e26ab73d500f08; ?>
<?php unset($__attributesOriginal14adc2b21dae2c2506e26ab73d500f08); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal14adc2b21dae2c2506e26ab73d500f08)): ?>
<?php $component = $__componentOriginal14adc2b21dae2c2506e26ab73d500f08; ?>
<?php unset($__componentOriginal14adc2b21dae2c2506e26ab73d500f08); ?>
<?php endif; ?><span x-text="action.label"></span></span><span x-show="action.shortcut" aria-hidden="true" class="text-sm text-slate-500 dark:text-slate-400" x-text="action.shortcut"></span>
                    </button>
                </template>
                <template x-if="action.href">
                    <a role="menuitem" :href="action.href" :download="action.download || null" @click="closeViewMenu()" class="flex min-h-11 w-full items-center gap-3 rounded-md px-3 py-2 hover:bg-slate-100 focus-visible:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand dark:hover:bg-slate-800 dark:focus-visible:bg-slate-800"><?php if (isset($component)) { $__componentOriginal14adc2b21dae2c2506e26ab73d500f08 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal14adc2b21dae2c2506e26ab73d500f08 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.report-library-action-icon','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('report-library-action-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal14adc2b21dae2c2506e26ab73d500f08)): ?>
<?php $attributes = $__attributesOriginal14adc2b21dae2c2506e26ab73d500f08; ?>
<?php unset($__attributesOriginal14adc2b21dae2c2506e26ab73d500f08); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal14adc2b21dae2c2506e26ab73d500f08)): ?>
<?php $component = $__componentOriginal14adc2b21dae2c2506e26ab73d500f08; ?>
<?php unset($__componentOriginal14adc2b21dae2c2506e26ab73d500f08); ?>
<?php endif; ?><span x-text="action.label"></span></a>
                </template>
            </div>
        </template>
        <div x-show="menuActions.length" role="separator" class="my-1 border-t border-slate-200 dark:border-slate-700"></div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($sortOptions): ?>
            <p class="px-3 py-2 font-semibold" role="presentation">Sort by</p>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $sortOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $field => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <button type="button" role="menuitemradio" :aria-checked="sortKey === '<?php echo e($field); ?>'" :disabled="saving || stale" @click="setSort('<?php echo e($field); ?>')" class="flex min-h-11 w-full items-center gap-3 rounded-md px-3 py-2 text-left hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand disabled:opacity-50 dark:hover:bg-slate-800">
                    <?php if (isset($component)) { $__componentOriginal2cc340f7d439e305c6ca7bbe0688c9ad = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2cc340f7d439e305c6ca7bbe0688c9ad = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.report-library-icon','data' => ['name' => 'sort-asc','class' => '!h-5 !w-5']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('report-library-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'sort-asc','class' => '!h-5 !w-5']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2cc340f7d439e305c6ca7bbe0688c9ad)): ?>
<?php $attributes = $__attributesOriginal2cc340f7d439e305c6ca7bbe0688c9ad; ?>
<?php unset($__attributesOriginal2cc340f7d439e305c6ca7bbe0688c9ad); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2cc340f7d439e305c6ca7bbe0688c9ad)): ?>
<?php $component = $__componentOriginal2cc340f7d439e305c6ca7bbe0688c9ad; ?>
<?php unset($__componentOriginal2cc340f7d439e305c6ca7bbe0688c9ad); ?>
<?php endif; ?><span class="flex-1"><?php echo e($label); ?></span><span x-show="sortKey === '<?php echo e($field); ?>'" aria-hidden="true" x-text="sortDirection === 'asc' ? '↑' : '↓'"></span>
                </button>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            <div role="separator" class="my-1 border-t border-slate-200 dark:border-slate-700"></div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <p class="px-3 py-2 font-semibold" role="presentation">View</p>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = ['small' => 'Small icons', 'medium' => 'Medium icons', 'large' => 'Large icons', 'list' => 'Details']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $option => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <?php ($active = $option === 'list' ? "view === 'list'" : "view === 'grid' && size === '".$option."'"); ?>
            <button type="button" role="menuitemradio" data-view-option="<?php echo e($option); ?>" :aria-checked="<?php echo e($active); ?>" :disabled="$data.saving || $data.stale" @click="chooseViewOption('<?php echo e($option); ?>')"
                class="flex min-h-11 w-full items-center gap-3 rounded-md px-3 py-2 text-left hover:bg-slate-100 focus-visible:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand disabled:opacity-50 dark:hover:bg-slate-800 dark:focus-visible:bg-slate-800">
                <?php if (isset($component)) { $__componentOriginal2cc340f7d439e305c6ca7bbe0688c9ad = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2cc340f7d439e305c6ca7bbe0688c9ad = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.report-library-icon','data' => ['name' => $option === 'list' ? 'list' : 'grid','class' => '!h-5 !w-5']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('report-library-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($option === 'list' ? 'list' : 'grid'),'class' => '!h-5 !w-5']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2cc340f7d439e305c6ca7bbe0688c9ad)): ?>
<?php $attributes = $__attributesOriginal2cc340f7d439e305c6ca7bbe0688c9ad; ?>
<?php unset($__attributesOriginal2cc340f7d439e305c6ca7bbe0688c9ad); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2cc340f7d439e305c6ca7bbe0688c9ad)): ?>
<?php $component = $__componentOriginal2cc340f7d439e305c6ca7bbe0688c9ad; ?>
<?php unset($__componentOriginal2cc340f7d439e305c6ca7bbe0688c9ad); ?>
<?php endif; ?>
                <span class="flex-1"><?php echo e($label); ?></span>
                <svg x-show="<?php echo e($active); ?>" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4L19 6" /></svg>
            </button>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
    </div>
</template>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\components\report-library-view-controls.blade.php ENDPATH**/ ?>