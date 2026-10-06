<?php
    $workspaceDefinitions = \App\Models\User::workspaceDefinitions();
    $workspaces = [
        'faculty' => $workspaceDefinitions[\App\Models\User::WORKSPACE_FACULTY],
        'research_coordinator' => $workspaceDefinitions[\App\Models\User::WORKSPACE_RESEARCH_OFFICE],
    ];
    $currentWorkspace = match ($user->activeWorkspace()) {
        \App\Models\User::WORKSPACE_RESEARCH_OFFICE => 'research_coordinator',
        \App\Models\User::WORKSPACE_FACULTY => 'faculty',
        default => null,
    };
?>

<?php if (isset($component)) { $__componentOriginal69dc84650370d1d4dc1b42d016d7226b = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal69dc84650370d1d4dc1b42d016d7226b = $attributes; } ?>
<?php $component = App\View\Components\GuestLayout::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('guest-layout'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\GuestLayout::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

    <?php if (isset($component)) { $__componentOriginal190e1f0ba47ce933707162cb1d8ed04f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal190e1f0ba47ce933707162cb1d8ed04f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.workspace-selector','data' => ['user' => $user,'workspaces' => $workspaces,'action' => route('role-selection.store'),'field' => 'role','currentWorkspace' => $currentWorkspace]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('workspace-selector'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['user' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($user),'workspaces' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($workspaces),'action' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('role-selection.store')),'field' => 'role','current-workspace' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($currentWorkspace)]); ?>
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
<?php endif; ?>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal69dc84650370d1d4dc1b42d016d7226b)): ?>
<?php $attributes = $__attributesOriginal69dc84650370d1d4dc1b42d016d7226b; ?>
<?php unset($__attributesOriginal69dc84650370d1d4dc1b42d016d7226b); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal69dc84650370d1d4dc1b42d016d7226b)): ?>
<?php $component = $__componentOriginal69dc84650370d1d4dc1b42d016d7226b; ?>
<?php unset($__componentOriginal69dc84650370d1d4dc1b42d016d7226b); ?>
<?php endif; ?>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views/auth/select-role.blade.php ENDPATH**/ ?>