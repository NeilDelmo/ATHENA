<?php if (isset($component)) { $__componentOriginal9f64f32e90b9102968f2bc548315018c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9f64f32e90b9102968f2bc548315018c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.modal','data' => ['name' => 'assistant-source-preview','maxWidth' => '4xl','focusable' => true,'class' => '!z-[150] sm:!py-[5vh]','dataAssistantSourcePreview' => true,'xEffect' => 'if (!show) $store.researchAssistant.sourcePreviewOpen = false']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('modal'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'assistant-source-preview','maxWidth' => '4xl','focusable' => true,'class' => '!z-[150] sm:!py-[5vh]','data-assistant-source-preview' => true,'x-effect' => 'if (!show) $store.researchAssistant.sourcePreviewOpen = false']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

    <template x-if="show && $store.researchAssistant.sourcePreview">
        <?php if (isset($component)) { $__componentOriginal705afa55b28306e08e85a5bbc410f198 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal705afa55b28306e08e85a5bbc410f198 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.submitted-report-document','data' => ['name' => 'assistant-source-preview','title' => 'Source document','previewUrl' => '','downloadUrl' => '','viewerLabel' => 'Source PDF','loadingLabel' => 'Loading source…','searchLabel' => 'Search source','previewUrlExpression' => '$store.researchAssistant.sourcePreview.pdfUrl','downloadUrlExpression' => '$store.researchAssistant.sourcePreview.downloadUrl','titleExpression' => '$store.researchAssistant.sourcePreview.title','descriptionExpression' => '$store.researchAssistant.sourcePreview.description','initialPageExpression' => '$store.researchAssistant.sourcePreview.page','searchable' => true,'expanded' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('submitted-report-document'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'assistant-source-preview','title' => 'Source document','preview-url' => '','download-url' => '','viewer-label' => 'Source PDF','loading-label' => 'Loading source…','search-label' => 'Search source','preview-url-expression' => '$store.researchAssistant.sourcePreview.pdfUrl','download-url-expression' => '$store.researchAssistant.sourcePreview.downloadUrl','title-expression' => '$store.researchAssistant.sourcePreview.title','description-expression' => '$store.researchAssistant.sourcePreview.description','initial-page-expression' => '$store.researchAssistant.sourcePreview.page','searchable' => true,'expanded' => true]); ?>
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
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\components\research-assistant-source-preview.blade.php ENDPATH**/ ?>