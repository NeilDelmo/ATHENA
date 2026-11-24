<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['projects']));

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

foreach (array_filter((['projects']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<section id="notice-release-queue" data-notice-to-proceed-queue <?php echo e($attributes->class(['dashboard-panel scroll-mt-32'])); ?> aria-labelledby="notice-release-queue-heading">
    <div class="dashboard-panel-heading">
        <div>
            <h2 id="notice-release-queue-heading" class="text-lg font-bold text-slate-950 dark:text-white">Notices to Proceed</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Projects cleared by the Research Head. Prepare the notice and upload signed documents for release.</p>
        </div>
        <span class="text-sm font-semibold tabular-nums text-slate-600 dark:text-slate-300"><?php echo e($projects->total()); ?> awaiting release</span>
    </div>
    <div class="divide-y divide-slate-100 dark:divide-slate-800">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $projects; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $project): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <?php
                $waitingSince = $project->status_started_at;
                $waitingDays = $waitingSince ? (int) max(0, $waitingSince->diffInDays(now(), false)) : null;
            ?>
            <article class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <h3 class="font-bold text-slate-950 dark:text-white"><?php echo e($project->title); ?></h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400"><?php echo e($project->user?->name); ?> · <?php echo e($project->user?->college); ?> · <?php echo e($project->hasPreparedNoticeToProceed() ? 'Notice details saved' : 'Ready for preparation'); ?></p>
                    <p class="mt-2 text-sm font-medium tabular-nums text-brand dark:text-rose-300"><?php echo e($waitingDays !== null ? $waitingDays.' '.str('day')->plural($waitingDays).' waiting' : 'Clearance date unavailable'); ?></p>
                </div>
                <a href="<?php echo e(route('topics.show', $project)); ?>#notice-to-proceed" class="dashboard-action shrink-0"><?php echo e($project->hasPreparedNoticeToProceed() ? 'Upload signed documents' : 'Prepare Notice to Proceed'); ?></a>
            </article>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            <p class="px-5 py-6 text-sm text-slate-500 dark:text-slate-400">No projects are awaiting a Notice to Proceed.</p>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($projects->hasPages()): ?>
        <div class="border-t border-slate-100 px-5 py-4 dark:border-slate-800"><?php echo e($projects->links()); ?></div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</section>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\components\notice-to-proceed-queue.blade.php ENDPATH**/ ?>