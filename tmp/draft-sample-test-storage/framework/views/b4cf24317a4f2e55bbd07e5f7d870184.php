<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['events', 'empty' => 'No events on this date.', 'monochrome' => false]));

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

foreach (array_filter((['events', 'empty' => 'No events on this date.', 'monochrome' => false]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<div class="space-y-3" data-calendar-events>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $events; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
        <?php
            $when = \Illuminate\Support\Carbon::parse($item['at']);
            $allDay = str_contains($item['display_at'], 'All day');
            $label = $item['kind'] === 'personal' ? 'Personal reminder' : ($item['deadline'] ? 'Deadline' : 'Official date');
        ?>
        <button type="button" wire:click="openEvent('<?php echo e($item['id']); ?>')" class="group flex w-full min-w-0 items-start gap-3 rounded-lg border border-slate-200 bg-white p-3 text-left hover:border-red-300 hover:bg-red-50/30 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand dark:border-slate-700 dark:bg-slate-950 dark:hover:border-red-800 dark:hover:bg-red-950/20">
            <span class="flex w-12 shrink-0 flex-col overflow-hidden rounded-md border border-slate-200 text-center dark:border-slate-700" aria-label="<?php echo e($when->format('M j, Y')); ?>">
                <span class="bg-brand py-1 text-sm font-medium text-white"><?php echo e($when->format('M')); ?></span>
                <span class="bg-white py-1.5 text-xl font-semibold tabular-nums text-slate-900 dark:bg-slate-900 dark:text-white"><?php echo e($when->format('j')); ?></span>
            </span>
            <span class="min-w-0 flex-1">
                <span class="block break-words text-base font-semibold leading-6 text-slate-900 group-hover:text-brand dark:text-white dark:group-hover:text-red-300"><?php echo e($item['title']); ?></span>
                <span class="mt-1 block break-words text-sm leading-5 text-slate-500 dark:text-slate-400"><?php echo e($item['context']); ?></span>
                <span class="mt-2 block text-sm leading-5 text-slate-600 dark:text-slate-300"><?php echo e($allDay ? 'All day' : $when->format('D · g:i A')); ?> · <?php echo e($when->diffForHumans(short: true)); ?></span>
                <span class="mt-2 inline-flex rounded border border-slate-200 px-2 py-0.5 text-sm font-medium text-brand dark:border-slate-700 dark:text-red-300"><?php echo e($label); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item['draft']): ?> · Draft <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></span>
            </span>
        </button>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        <p class="rounded-lg border border-slate-200 bg-white px-4 py-5 text-sm leading-6 text-slate-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-400"><?php echo e($empty); ?></p>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views/components/dashboard-calendar-events.blade.php ENDPATH**/ ?>