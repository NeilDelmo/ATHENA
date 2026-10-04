<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
        <meta name="app-url" content="<?php echo e(url('/')); ?>">
        <title>Revision editor · <?php echo e(config('app.name')); ?></title>
        <?php echo $__env->make('partials.theme-script', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <?php if (isset($component)) { $__componentOriginal38a24e6aeb8692b58428ce9665902ac0 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal38a24e6aeb8692b58428ce9665902ac0 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.app-fonts','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('app-fonts'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal38a24e6aeb8692b58428ce9665902ac0)): ?>
<?php $attributes = $__attributesOriginal38a24e6aeb8692b58428ce9665902ac0; ?>
<?php unset($__attributesOriginal38a24e6aeb8692b58428ce9665902ac0); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal38a24e6aeb8692b58428ce9665902ac0)): ?>
<?php $component = $__componentOriginal38a24e6aeb8692b58428ce9665902ac0; ?>
<?php unset($__componentOriginal38a24e6aeb8692b58428ce9665902ac0); ?>
<?php endif; ?>
        <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>
        <?php echo \Livewire\Mechanisms\FrontendAssets\FrontendAssets::styles(); ?>

    </head>
    <body class="revision-embedded bg-white font-sans text-gray-900 antialiased dark:bg-slate-900 dark:text-white" data-revision-embedded data-auth-user-id="<?php echo e(Auth::id()); ?>">
        <main><?php echo e($slot); ?></main>
        <?php echo \Livewire\Mechanisms\FrontendAssets\FrontendAssets::scriptConfig(); ?>

    </body>
</html>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views/layouts/revision-editor.blade.php ENDPATH**/ ?>