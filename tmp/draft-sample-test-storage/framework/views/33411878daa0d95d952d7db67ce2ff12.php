<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['quarterRows', 'topic', 'canReport' => false, 'legacyReports' => collect()]));

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

foreach (array_filter((['quarterRows', 'topic', 'canReport' => false, 'legacyReports' => collect()]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<section id="progress-reports" class="overflow-hidden rounded-2xl border border-red-200 bg-white shadow-sm dark:border-red-900 dark:bg-slate-900" aria-labelledby="progress-reports-heading">
    <header class="border-b border-red-200 bg-red-50 px-5 py-5 dark:border-red-900 dark:bg-red-950/30 sm:px-6">
        <h3 id="progress-reports-heading" class="text-xl font-bold text-red-800 dark:text-red-300">Progress reports</h3>
        <p class="mt-2 text-base leading-7 text-slate-600 dark:text-slate-300">One Progress Report for each Monitoring Tool quarter, covering the same reporting period. Submit both after the quarter ends.</p>
    </header>
    <div class="overflow-x-auto" data-progress-schedule-table>
        <table class="min-w-full divide-y divide-slate-200 text-left dark:divide-slate-800">
            <caption class="sr-only">Quarterly Progress Report periods and available actions</caption>
            <thead class="bg-white dark:bg-slate-950/50">
                <tr class="text-sm font-semibold text-slate-600 dark:text-slate-300">
                    <th scope="col" class="w-24 px-5 py-3 sm:px-6">Quarter</th>
                    <th scope="col" class="min-w-[16rem] px-5 py-3">Reporting period</th>
                    <th scope="col" class="min-w-[11rem] px-5 py-3">Status</th>
                    <th scope="col" class="min-w-[13rem] px-5 py-3 text-right sm:px-6">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $quarterRows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <?php ($report = $row['report']); ?>
                    <tr data-progress-quarter="<?php echo e($row['quarter']); ?>" <?php if($report): ?> id="narrative-report-<?php echo e($report->id); ?>" data-narrative-history-entry="<?php echo e($report->id); ?>" <?php endif; ?> class="align-middle hover:bg-slate-50/70 dark:hover:bg-slate-950/30">
                        <th scope="row" class="px-5 py-4 sm:px-6"><span class="inline-flex h-11 min-w-11 items-center justify-center rounded-xl bg-red-700 px-3 text-base font-bold text-white"><?php echo e($row['label']); ?></span></th>
                        <td class="px-5 py-4">
                            <p class="whitespace-nowrap text-base font-semibold text-slate-900 dark:text-white"><?php echo e($row['period']); ?></p>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $row['submission_open']): ?><p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Submission opens <?php echo e($row['opens_at']->format('M j, Y')); ?></p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </td>
                        <td class="px-5 py-4"><span data-narrative-report-status class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-sm font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200"><?php echo e($row['status']); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($report): ?><span class="sr-only"><?php echo e($report->review_status_label); ?></span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></span></td>
                        <td class="px-5 py-4 sm:px-6">
                            <div class="flex min-w-max flex-wrap items-center justify-end gap-2">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($report): ?>
                                    <a href="<?php echo e(route('project-narrative-reports.show', $report)); ?>" aria-label="Open <?php echo e($row['label']); ?> progress report" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-bold text-slate-700 hover:bg-red-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-red-950/30">Open report</a>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canReport && $row['drafting_date'] && ! $topic->isCompletedProject()): ?>
                                    <a href="<?php echo e(route('project-narrative-reports.create', ['topic' => $topic, 'report_type' => 'progress', 'reporting_date' => $row['drafting_date']])); ?>" aria-label="<?php echo e($report?->isPrepared() ? 'Review' : ($report ? 'Revise' : 'Fill')); ?> <?php echo e($row['label']); ?> progress report" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-red-700 px-3 py-2 text-sm font-bold text-white hover:bg-red-800 focus-visible:ring-2 focus-visible:ring-red-600 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-900"><?php echo e($report?->isPrepared() ? 'Review and submit' : ($report ? 'Revise report' : ($row['submission_open'] ? 'Start report' : 'Fill draft'))); ?></a>
                                <?php elseif(! $report): ?>
                                    <span class="text-sm text-slate-500 dark:text-slate-400"><?php echo e($row['submission_open'] ? 'No report yet' : 'Not open yet'); ?></span>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    <tr><td colspan="4" class="px-6 py-10 text-center text-sm text-slate-500 dark:text-slate-400">No reporting periods are scheduled. Check the approved project dates.</td></tr>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($legacyReports->isNotEmpty()): ?>
        <div class="space-y-3 border-t border-slate-200 p-5 dark:border-slate-800">
            <p class="text-sm text-slate-600 dark:text-slate-300">Earlier Progress Reports without a recorded quarter</p>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $legacyReports; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $report): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <?php if (isset($component)) { $__componentOriginalde63ddbd7b1a4c9a5204bf6b1c30a15a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalde63ddbd7b1a4c9a5204bf6b1c30a15a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.narrative-report-summary','data' => ['report' => $report]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('narrative-report-summary'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['report' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($report)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalde63ddbd7b1a4c9a5204bf6b1c30a15a)): ?>
<?php $attributes = $__attributesOriginalde63ddbd7b1a4c9a5204bf6b1c30a15a; ?>
<?php unset($__attributesOriginalde63ddbd7b1a4c9a5204bf6b1c30a15a); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalde63ddbd7b1a4c9a5204bf6b1c30a15a)): ?>
<?php $component = $__componentOriginalde63ddbd7b1a4c9a5204bf6b1c30a15a; ?>
<?php unset($__componentOriginalde63ddbd7b1a4c9a5204bf6b1c30a15a); ?>
<?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</section>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views/components/progress-quarter-overview.blade.php ENDPATH**/ ?>