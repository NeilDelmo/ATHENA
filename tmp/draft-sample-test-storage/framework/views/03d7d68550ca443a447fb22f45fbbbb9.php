<?php if (isset($component)) { $__componentOriginalcd93ff08fd3bc5e3afdf57ccf1848309 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalcd93ff08fd3bc5e3afdf57ccf1848309 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-signatory-summary','data' => ['proposalDraft' => $draft,'paper' => $paper]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-signatory-summary'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['proposal-draft' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($draft),'paper' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($paper)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalcd93ff08fd3bc5e3afdf57ccf1848309)): ?>
<?php $attributes = $__attributesOriginalcd93ff08fd3bc5e3afdf57ccf1848309; ?>
<?php unset($__attributesOriginalcd93ff08fd3bc5e3afdf57ccf1848309); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalcd93ff08fd3bc5e3afdf57ccf1848309)): ?>
<?php $component = $__componentOriginalcd93ff08fd3bc5e3afdf57ccf1848309; ?>
<?php unset($__componentOriginalcd93ff08fd3bc5e3afdf57ccf1848309); ?>
<?php endif; ?><?php /**PATH C:\laragon\www\athena-app\tmp\draft-sample-test-storage\framework\views/0165d2b894abd8a8b596876a789e37f8.blade.php ENDPATH**/ ?>