<?php
    $pages = [
        'research_head.dashboard' => 'Dashboard',
        'research_head.calendar' => 'Calendar',
        'research_head.analytics' => 'Analytics',
    ];
?>
<nav aria-label="Research workspace pages" data-research-head-page-navigation
     <?php echo e($attributes->class(['inline-grid max-w-full grid-cols-3 gap-1 rounded-xl border border-slate-200 bg-slate-50 p-1 dark:border-slate-700 dark:bg-slate-950'])); ?>>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $pages; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $routeName => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
        <?php ($active = request()->routeIs($routeName)); ?>
        <a wire:navigate href="<?php echo e(route($routeName)); ?>"
           <?php if($active): ?> aria-current="page" <?php endif; ?>
           class="<?php echo \Illuminate\Support\Arr::toCssClasses([
               'inline-flex min-h-[44px] items-center justify-center rounded-lg px-2 text-sm font-semibold transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand sm:px-4',
               'bg-brand text-white shadow-sm' => $active,
               'text-slate-600 hover:bg-white hover:text-brand dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white' => ! $active,
           ]); ?>"><?php echo e($label); ?></a>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
</nav>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\components\research-head-page-navigation.blade.php ENDPATH**/ ?>