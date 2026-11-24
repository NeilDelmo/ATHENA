<?php if (isset($component)) { $__componentOriginal4ee2d78daac54495a91eea801e6e4127 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4ee2d78daac54495a91eea801e6e4127 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.project-budget-monitoring','data' => ['projects' => $projects,'budgetMonitoring' => $budgetMonitoring,'workspace' => 'research_secretary']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('project-budget-monitoring'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['projects' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($projects),'budget-monitoring' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($budgetMonitoring),'workspace' => 'research_secretary']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal4ee2d78daac54495a91eea801e6e4127)): ?>
<?php $attributes = $__attributesOriginal4ee2d78daac54495a91eea801e6e4127; ?>
<?php unset($__attributesOriginal4ee2d78daac54495a91eea801e6e4127); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal4ee2d78daac54495a91eea801e6e4127)): ?>
<?php $component = $__componentOriginal4ee2d78daac54495a91eea801e6e4127; ?>
<?php unset($__componentOriginal4ee2d78daac54495a91eea801e6e4127); ?>
<?php endif; ?>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\research_secretary\budgets.blade.php ENDPATH**/ ?>