<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['activities' => []]));

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

foreach (array_filter((['activities' => []]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($activities !== []): ?>
    <section data-monitoring-work-plan-alert role="alert" <?php echo e($attributes->class(['rounded-xl border border-slate-200 bg-white px-5 py-4 text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 sm:px-6'])); ?>>
        <h3 class="text-base font-semibold">Work Plan targets need attention</h3>
        <p class="mt-1 text-sm leading-6 text-slate-600 dark:text-slate-300"><?php echo e(count($activities)); ?> <?php echo e(Str::plural('activity', count($activities))); ?> <?php echo e(count($activities) === 1 ? 'is' : 'are'); ?> past the approved target date. Record progress and explain delays in the Monitoring Tool.</p>
        <details class="monitoring-disclosure group mt-2">
            <summary>
                <span>View affected objectives and activities</span>
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M5 12h14" /><path class="group-open:hidden" stroke-linecap="round" d="M12 5v14" /></svg>
            </summary>
            <ul class="mt-2 divide-y divide-slate-100 border-t border-slate-100 dark:divide-slate-800 dark:border-slate-800">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $activities; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $activity): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <li data-overdue-work-plan-index="<?php echo e($activity['source_work_plan_index']); ?>" class="grid min-w-0 gap-1 py-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:gap-x-6">
                        <p class="break-words text-sm font-semibold"><?php echo e($activity['objective']); ?></p>
                        <p class="break-words text-sm leading-6 text-slate-600 dark:text-slate-300 sm:col-start-1"><?php echo e($activity['activity']); ?></p>
                        <p class="text-xs leading-6 text-slate-600 dark:text-slate-300 sm:col-start-2 sm:row-span-2 sm:row-start-1 sm:self-center">Due <?php echo e(\Carbon\CarbonImmutable::parse($activity['target_completion_date'])->format('M j, Y')); ?> · <?php echo e($activity['progress_recorded'] ? $activity['completion_percentage'].'% complete' : 'No submitted progress yet'); ?></p>
                    </li>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </ul>
        </details>
    </section>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\components\monitoring-work-plan-alert.blade.php ENDPATH**/ ?>