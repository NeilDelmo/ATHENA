<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['period', 'returned' => false]));

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

foreach (array_filter((['period', 'returned' => false]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php if (isset($component)) { $__componentOriginal90e243942d22f6a4722cd614e5732fed = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal90e243942d22f6a4722cd614e5732fed = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-writing-toolbar','data' => ['toolbarId' => 'quarterly-report-tools','dataQuarterlyReportWritingToolbar' => true,'ariaLabel' => 'Quarterly report tools']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-writing-toolbar'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['toolbar-id' => 'quarterly-report-tools','data-quarterly-report-writing-toolbar' => true,'aria-label' => 'Quarterly report tools']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

     <?php $__env->slot('context', null, []); ?> 
        <p class="proposal-writing-target">Editing: <span>Q<?php echo e($period['quarter']); ?> <?php echo e($period['year']); ?> accomplishment report</span></p>
     <?php $__env->endSlot(); ?>

    <div class="proposal-writing-toolbar-row" role="toolbar" aria-label="Quarterly report actions">
        <div class="proposal-writing-buttons">
            <button type="submit" form="quarterly-report-form" name="intent" value="save">Save draft</button>
            <button type="submit" form="quarterly-report-form" name="intent" value="preview">Save and preview</button>
            <button type="submit" form="quarterly-report-form" name="intent" value="download">Save and download Word</button>
            <button type="submit" form="quarterly-report-form" name="intent" value="submit" <?php if(now()->lessThanOrEqualTo($period['end'])): echo 'disabled'; endif; ?> class="!border-red-700 !bg-red-700 !text-white hover:!bg-red-800"><?php echo e($returned ? 'Resubmit quarterly report' : 'Submit quarterly report'); ?></button>
        </div>
    </div>
    <div class="proposal-writing-toolbar-row" role="toolbar" aria-label="Other accomplishments formatting" data-document-formatting-toolbar>
        <div class="proposal-writing-buttons">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = ['bold' => ['Bold', 'bold'], 'italic' => ['Italic', 'italic'], 'underline' => ['Underline', 'underline'], 'insertUnorderedList' => ['Bullets', 'bullets'], 'insertOrderedList' => ['Numbered list', 'numbered-list'], 'undo' => ['Undo', 'undo'], 'redo' => ['Redo', 'redo'], 'removeFormat' => ['Clear formatting', 'removeFormat']]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $command => [$label, $icon]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <button type="button" data-document-writing-command="<?php echo e($command); ?>" @mousedown.prevent @click="formatWriting('<?php echo e($command); ?>')" :disabled="!activeWritingField" aria-label="<?php echo e($label); ?>" title="<?php echo e($label); ?>" class="inline-flex items-center gap-1.5"><?php if (isset($component)) { $__componentOriginal7a9485575b48ddf8197622ac7859a0fe = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal7a9485575b48ddf8197622ac7859a0fe = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-writing-icon','data' => ['name' => $icon]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-writing-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($icon)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal7a9485575b48ddf8197622ac7859a0fe)): ?>
<?php $attributes = $__attributesOriginal7a9485575b48ddf8197622ac7859a0fe; ?>
<?php unset($__attributesOriginal7a9485575b48ddf8197622ac7859a0fe); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal7a9485575b48ddf8197622ac7859a0fe)): ?>
<?php $component = $__componentOriginal7a9485575b48ddf8197622ac7859a0fe; ?>
<?php unset($__componentOriginal7a9485575b48ddf8197622ac7859a0fe); ?>
<?php endif; ?><span><?php echo e($label); ?></span></button>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </div>
    </div>
    <p class="proposal-writing-hint">Select text in Other Accomplishments to format it. Preview and Word download save your current entries first.</p>
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
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\components\quarterly-report-writing-toolbar.blade.php ENDPATH**/ ?>