<?php if (isset($component)) { $__componentOriginal190e1f0ba47ce933707162cb1d8ed04f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal190e1f0ba47ce933707162cb1d8ed04f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.workspace-selector','data' => ['user' => $user,'workspaces' => $workspaces,'action' => $action,'field' => $field,'currentWorkspace' => $currentWorkspace]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('workspace-selector'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['user' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($user),'workspaces' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($workspaces),'action' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($action),'field' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($field),'current-workspace' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($currentWorkspace)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal190e1f0ba47ce933707162cb1d8ed04f)): ?>
<?php $attributes = $__attributesOriginal190e1f0ba47ce933707162cb1d8ed04f; ?>
<?php unset($__attributesOriginal190e1f0ba47ce933707162cb1d8ed04f); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal190e1f0ba47ce933707162cb1d8ed04f)): ?>
<?php $component = $__componentOriginal190e1f0ba47ce933707162cb1d8ed04f; ?>
<?php unset($__componentOriginal190e1f0ba47ce933707162cb1d8ed04f); ?>
<?php endif; ?><?php /**PATH C:\laragon\www\athena-app\tmp\workspace-switch-views/dde15145ef067ddc02da2a792b4b8942.blade.php ENDPATH**/ ?>