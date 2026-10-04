<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'title' => null,
    'subtitle' => null,
    'container' => false,
    'variant' => 'default',
    'eyebrow' => null,
]));

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

foreach (array_filter(([
    'title' => null,
    'subtitle' => null,
    'container' => false,
    'variant' => 'default',
    'eyebrow' => null,
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($container): ?>
    <header data-page-header-container <?php echo e($attributes->class(['athena-page-header relative overflow-hidden border-b border-gray-100 bg-white transition-colors duration-300 dark:border-slate-800 dark:bg-slate-900'])); ?>>
        <div class="relative z-[1] w-full py-6 px-4 sm:px-6 lg:px-8">
            <?php echo e($slot); ?>

        </div>
    </header>
<?php elseif(in_array($variant, ['hero', 'banner'], true)): ?>
    <div data-page-header-variant="<?php echo e($variant); ?>" data-workspace-header-banner <?php echo e($attributes->class(['flex flex-col gap-4 border-l-4 border-[#800000] !bg-transparent px-5 py-4 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between dark:!bg-transparent'])); ?>>
        <div class="min-w-0 sm:min-w-[min(100%,16rem)] sm:flex-1">
            <p class="text-sm font-bold uppercase tracking-[0.2em] text-[#800000] dark:text-red-300"><?php echo e($eyebrow); ?></p>
            <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl dark:text-white"><?php echo e($title); ?></h2>
            <p class="mt-1 max-w-3xl text-base leading-6 text-slate-500 dark:text-slate-400"><?php echo e($subtitle); ?></p>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($actions)): ?>
            <div class="max-w-full shrink-0"><?php echo e($actions); ?></div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
<?php else: ?>
    <div data-page-header-variant="simple" <?php echo e($attributes->class(['flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between'])); ?>>
        <div class="min-w-0 sm:min-w-[min(100%,16rem)] sm:flex-1">
            <h1 class="text-3xl font-black tracking-tight text-slate-950 dark:text-white"><?php echo e($title); ?></h1>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($subtitle): ?>
                <p class="mt-1 text-base leading-6 text-slate-500 dark:text-slate-400"><?php echo e($subtitle); ?></p>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($actions)): ?>
            <div class="flex w-full max-w-full shrink-0 flex-col gap-2 sm:w-auto sm:flex-row sm:flex-wrap sm:items-center sm:justify-end">
                <?php echo e($actions); ?>

            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views/components/page-header.blade.php ENDPATH**/ ?>