<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['topic', 'reportLabel', 'panelId', 'formId', 'saveMethod', 'standalone' => false]));

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

foreach (array_filter((['topic', 'reportLabel', 'panelId', 'formId', 'saveMethod', 'standalone' => false]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php if (isset($component)) { $__componentOriginal90e243942d22f6a4722cd614e5732fed = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal90e243942d22f6a4722cd614e5732fed = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-writing-toolbar','data' => ['toolbarId' => $formId.'-tools','dataMonitoringWritingToolbar' => true,'ariaLabel' => ''.e($reportLabel).' tools','attributes' => $attributes]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-writing-toolbar'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['toolbar-id' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($formId.'-tools'),'data-monitoring-writing-toolbar' => true,'aria-label' => ''.e($reportLabel).' tools','attributes' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($attributes)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

     <?php $__env->slot('context', null, []); ?> 
        <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
            <p class="proposal-writing-target">Editing: <span><?php echo e($reportLabel); ?></span></p>
            <?php if (isset($component)) { $__componentOriginale42ee907cd5bebce7126d7c9b69e1d93 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginale42ee907cd5bebce7126d7c9b69e1d93 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-autosave-status','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-autosave-status'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginale42ee907cd5bebce7126d7c9b69e1d93)): ?>
<?php $attributes = $__attributesOriginale42ee907cd5bebce7126d7c9b69e1d93; ?>
<?php unset($__attributesOriginale42ee907cd5bebce7126d7c9b69e1d93); ?>
<?php endif; ?>
<?php if (isset($__componentOriginale42ee907cd5bebce7126d7c9b69e1d93)): ?>
<?php $component = $__componentOriginale42ee907cd5bebce7126d7c9b69e1d93; ?>
<?php unset($__componentOriginale42ee907cd5bebce7126d7c9b69e1d93); ?>
<?php endif; ?>
        </div>
     <?php $__env->endSlot(); ?>

    <div class="proposal-writing-toolbar-row" role="toolbar" aria-label="<?php echo e($reportLabel); ?> editing and preview">
        <div class="proposal-writing-buttons">
            <button type="button" @click="<?php echo e($saveMethod); ?>()" :disabled="autoSaveInFlight || autoSaveBlocked || submitting">Save draft</button>
            <button
                type="button"
                data-proposal-preview-toggle
                @click="previewPaneOpen ? closeProposalPreview() : showProposalPreview()"
                :disabled="previewLoading || submitting"
                :aria-expanded="previewPaneOpen"
                aria-controls="<?php echo e($panelId); ?>"
                x-text="previewPaneOpen ? 'Hide preview' : 'Preview paper'"
            ></button>
            <button
                type="submit"
                form="<?php echo e($formId); ?>"
                :disabled="!submissionOpen || submitting || previewLoading"
                :title="!submissionOpen ? 'Official PDF preparation opens ' + submissionOpensAt : ''"
                class="!border-red-700 !bg-red-700 !text-white hover:!bg-red-800"
            >
                <span x-show="!submitting">Prepare official PDF</span>
                <span x-show="submitting" x-cloak>Preparing PDF…</span>
            </button>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($standalone): ?>
                <a data-paper-cancel-exit href="<?php echo e(route('research.show', $topic)); ?>#project-monitoring" class="proposal-preview-download inline-flex items-center">Exit monitoring</a>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    </div>
    <p class="proposal-writing-hint">Changes save automatically. Open the paper preview to review your report.</p>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal90e243942d22f6a4722cd614e5732fed)): ?>
<?php $attributes = $__attributesOriginal90e243942d22f6a4722cd614e5732fed; ?>
<?php unset($__attributesOriginal90e243942d22f6a4722cd614e5732fed); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal90e243942d22f6a4722cd614e5732fed)): ?>
<?php $component = $__componentOriginal90e243942d22f6a4722cd614e5732fed; ?>
<?php unset($__componentOriginal90e243942d22f6a4722cd614e5732fed); ?>
<?php endif; ?>

<button
    type="button"
    data-monitoring-preview-launcher
    x-show="!previewPaneOpen"
    x-cloak
    @click="showProposalPreview()"
    :disabled="previewLoading || submitting"
    aria-controls="<?php echo e($panelId); ?>"
    :aria-expanded="previewPaneOpen"
    class="proposal-writing-preview-launcher"
>Preview paper</button>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\components\monitoring-writing-toolbar.blade.php ENDPATH**/ ?>