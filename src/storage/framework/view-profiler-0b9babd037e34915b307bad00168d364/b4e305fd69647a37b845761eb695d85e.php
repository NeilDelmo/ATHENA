<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">
    <head>
        <?php if (isset($component)) { $__componentOriginal82e3f864bb766fbb95cb0a10b750823c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal82e3f864bb766fbb95cb0a10b750823c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.favicon','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('favicon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal82e3f864bb766fbb95cb0a10b750823c)): ?>
<?php $attributes = $__attributesOriginal82e3f864bb766fbb95cb0a10b750823c; ?>
<?php unset($__attributesOriginal82e3f864bb766fbb95cb0a10b750823c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal82e3f864bb766fbb95cb0a10b750823c)): ?>
<?php $component = $__componentOriginal82e3f864bb766fbb95cb0a10b750823c; ?>
<?php unset($__componentOriginal82e3f864bb766fbb95cb0a10b750823c); ?>
<?php endif; ?>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Initial Screening Form Preview</title>
        <?php echo app('Illuminate\Foundation\Vite')(['resources/css/initial-screening-form-print.css', 'resources/js/paper-preview-pagination.js']); ?>
    </head>
    <body class="initial-screening-preview-page">
        <main data-paper-sheet data-paper-fixed class="initial-screening-sheet" aria-label="BatStateU Initial Screening Form">
            <img src="<?php echo e(asset('images/initial-screening-form-preview.png')); ?>" alt="" class="initial-screening-source" aria-hidden="true">
            <span class="initial-screening-project-title"><?php echo e($screeningForm['project_title']); ?></span>
            <span class="initial-screening-project-leader"><?php echo e($screeningForm['project_leader']); ?></span>
            <span class="initial-screening-order-checkmark initial-screening-order-<?php echo e($screeningForm['order_of_submission']); ?>" data-screening-order="<?php echo e($screeningForm['order_of_submission']); ?>" aria-label="Selected order of submission">×</span>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(in_array($screeningForm['level_of_call'] ?? null, ['central_agency', 'constituent_campus'], true)): ?>
                <span class="initial-screening-level-checkmark initial-screening-level-<?php echo e($screeningForm['level_of_call']); ?>" data-screening-level="<?php echo e($screeningForm['level_of_call']); ?>" aria-label="Selected level of call">×</span>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </main>
    </body>
</html>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\faculty\initial-screening-form\preview.blade.php ENDPATH**/ ?>