<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['days', 'monthLabel', 'selectedDate', 'expanded' => false, 'large' => false, 'monochrome' => false]));

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

foreach (array_filter((['days', 'monthLabel', 'selectedDate', 'expanded' => false, 'large' => false, 'monochrome' => false]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<div>
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h4 class="text-xl font-semibold tracking-tight text-slate-900 dark:text-white"><?php echo e($monthLabel); ?></h4>
        <div class="flex items-center gap-1 rounded-lg border border-slate-200 bg-white p-1 dark:border-slate-700 dark:bg-slate-900">
            <button type="button" wire:click="moveMonth(-1)" aria-label="Previous month" class="inline-flex h-11 w-11 items-center justify-center rounded-md text-2xl text-slate-600 hover:bg-red-50 hover:text-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand dark:text-slate-300 dark:hover:bg-red-950/30">&lsaquo;</button>
            <button type="button" wire:click="today" class="min-h-11 rounded-md px-3 text-sm font-semibold text-slate-700 hover:bg-red-50 hover:text-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand dark:text-slate-300 dark:hover:bg-red-950/30">Today</button>
            <button type="button" wire:click="moveMonth(1)" aria-label="Next month" class="inline-flex h-11 w-11 items-center justify-center rounded-md text-2xl text-slate-600 hover:bg-red-50 hover:text-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand dark:text-slate-300 dark:hover:bg-red-950/30">&rsaquo;</button>
        </div>
    </div>
    <div class="overflow-hidden rounded-lg border border-slate-200 dark:border-slate-700">
        <div class="grid grid-cols-7 bg-brand text-center text-sm font-medium text-white">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $weekday): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <span class="py-3"><?php echo e($weekday); ?></span>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </div>
        <div class="grid grid-cols-7">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $days; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $day): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <button type="button" wire:click="selectDate('<?php echo e($day['date']); ?>')" aria-label="<?php echo e($day['date']); ?>, <?php echo e($day['events']->count()); ?> events" aria-pressed="<?php echo e($selectedDate === $day['date'] ? 'true' : 'false'); ?>" aria-current="<?php echo e($day['today'] ? 'date' : 'false'); ?>" class="flex min-w-0 flex-col items-center border-b border-r border-slate-200 bg-white p-1.5 text-base hover:bg-slate-50 focus-visible:z-10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand dark:border-slate-700 dark:bg-slate-950 dark:hover:bg-slate-900 <?php echo e($large || $expanded ? 'min-h-20 sm:min-h-28 sm:items-start sm:p-2' : 'min-h-12 justify-center'); ?> <?php echo e($selectedDate === $day['date'] ? 'ring-2 ring-inset ring-brand dark:ring-red-400' : ''); ?> <?php echo e($day['current'] ? 'text-slate-900 dark:text-slate-100' : 'text-slate-400 dark:text-slate-500'); ?>">
                    <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full tabular-nums <?php echo e($day['today'] ? 'bg-brand font-semibold text-white' : ''); ?>"><?php echo e($day['number']); ?></span>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($large || $expanded) && $day['events']->isNotEmpty()): ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $day['events']->take(2); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $dayEvent): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <span title="<?php echo e($dayEvent['title']); ?>" class="mt-1 hidden w-full truncate rounded-sm border-l-2 border-brand bg-red-50 px-1.5 py-1 text-left text-sm font-medium text-brand dark:border-red-400 dark:bg-red-950/30 dark:text-red-200 sm:block"><?php echo e($dayEvent['title']); ?></span>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        <span class="mt-1 flex items-center gap-1 text-sm text-brand dark:text-red-300 sm:hidden"><span class="h-1.5 w-1.5 rounded-full bg-brand dark:bg-red-400" aria-hidden="true"></span><?php echo e($day['events']->count()); ?></span>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($day['events']->count() > 2): ?>
                            <span class="mt-1 hidden text-sm text-slate-500 dark:text-slate-400 sm:block">+<?php echo e($day['events']->count() - 2); ?> more</span>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php elseif($day['events']->isNotEmpty()): ?>
                        <span aria-hidden="true" class="mt-1 h-1.5 w-1.5 rounded-full bg-brand dark:bg-red-400"></span>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </button>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </div>
    </div>
</div>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views/components/dashboard-calendar-grid.blade.php ENDPATH**/ ?>