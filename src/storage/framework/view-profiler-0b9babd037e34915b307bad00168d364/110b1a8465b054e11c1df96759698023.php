<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['name', 'title', 'description' => null, 'previewUrl', 'downloadUrl', 'viewerLabel' => 'Submitted report PDF', 'searchable' => false, 'initialPageExpression' => null, 'loadingLabel' => 'Loading submitted report…']));

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

foreach (array_filter((['name', 'title', 'description' => null, 'previewUrl', 'downloadUrl', 'viewerLabel' => 'Submitted report PDF', 'searchable' => false, 'initialPageExpression' => null, 'loadingLabel' => 'Loading submitted report…']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php if (isset($component)) { $__componentOriginal9f64f32e90b9102968f2bc548315018c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9f64f32e90b9102968f2bc548315018c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.modal','data' => ['name' => $name,'maxWidth' => '4xl','focusable' => true,'class' => '!z-[140] sm:!py-[5vh]','dataSubmittedReportPreviewModal' => true,'attributes' => $attributes]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('modal'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($name),'maxWidth' => '4xl','focusable' => true,'class' => '!z-[140] sm:!py-[5vh]','data-submitted-report-preview-modal' => true,'attributes' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($attributes)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

    <template x-if="show">
        <?php if (isset($component)) { $__componentOriginal705afa55b28306e08e85a5bbc410f198 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal705afa55b28306e08e85a5bbc410f198 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.submitted-report-document','data' => ['name' => $name,'title' => $title,'description' => $description,'previewUrl' => $previewUrl,'downloadUrl' => $downloadUrl,'viewerLabel' => $viewerLabel,'searchable' => $searchable,'initialPageExpression' => $initialPageExpression,'loadingLabel' => $loadingLabel,'expanded' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('submitted-report-document'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($name),'title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($title),'description' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($description),'preview-url' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($previewUrl),'download-url' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($downloadUrl),'viewer-label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($viewerLabel),'searchable' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($searchable),'initial-page-expression' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($initialPageExpression),'loading-label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($loadingLabel),'expanded' => true]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal705afa55b28306e08e85a5bbc410f198)): ?>
<?php $attributes = $__attributesOriginal705afa55b28306e08e85a5bbc410f198; ?>
<?php unset($__attributesOriginal705afa55b28306e08e85a5bbc410f198); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal705afa55b28306e08e85a5bbc410f198)): ?>
<?php $component = $__componentOriginal705afa55b28306e08e85a5bbc410f198; ?>
<?php unset($__componentOriginal705afa55b28306e08e85a5bbc410f198); ?>
<?php endif; ?>
    </template>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9f64f32e90b9102968f2bc548315018c)): ?>
<?php $attributes = $__attributesOriginal9f64f32e90b9102968f2bc548315018c; ?>
<?php unset($__attributesOriginal9f64f32e90b9102968f2bc548315018c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9f64f32e90b9102968f2bc548315018c)): ?>
<?php $component = $__componentOriginal9f64f32e90b9102968f2bc548315018c; ?>
<?php unset($__componentOriginal9f64f32e90b9102968f2bc548315018c); ?>
<?php endif; ?>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\components\submitted-report-preview.blade.php ENDPATH**/ ?>