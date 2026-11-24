<?php if (isset($component)) { $__componentOriginal8c86773791c5117d75f41d0a03e4fbae = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal8c86773791c5117d75f41d0a03e4fbae = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.report-review-status','data' => ['status' => $report->review_status,'label' => $label,'compact' => $compact]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('report-review-status'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['status' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($report->review_status),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($label),'compact' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($compact)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal8c86773791c5117d75f41d0a03e4fbae)): ?>
<?php $attributes = $__attributesOriginal8c86773791c5117d75f41d0a03e4fbae; ?>
<?php unset($__attributesOriginal8c86773791c5117d75f41d0a03e4fbae); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal8c86773791c5117d75f41d0a03e4fbae)): ?>
<?php $component = $__componentOriginal8c86773791c5117d75f41d0a03e4fbae; ?>
<?php unset($__componentOriginal8c86773791c5117d75f41d0a03e4fbae); ?>
<?php endif; ?>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\topics\partials\live-report-status.blade.php ENDPATH**/ ?>