<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['quarterRows', 'topic' => null]));

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

foreach (array_filter((['quarterRows', 'topic' => null]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<section class="overflow-hidden rounded-2xl border border-red-200 bg-white shadow-sm dark:border-red-900 dark:bg-slate-900" aria-labelledby="quarterly-reporting-schedule-heading">
    <div class="border-b border-red-200 bg-red-50 px-5 py-5 dark:border-red-900 dark:bg-red-950/30 sm:px-6">
        <h4 id="quarterly-reporting-schedule-heading" class="text-xl font-bold text-red-800 dark:text-red-300">Monitoring Tool</h4>
        <p class="mt-2 max-w-3xl text-base leading-7 text-slate-600 dark:text-slate-300">Submit a Monitoring Tool for each three-month reporting period after it ends. The final period may be shorter.</p>
    </div>

    <div class="overflow-x-auto" data-monitoring-schedule-table>
        <table class="min-w-full divide-y divide-slate-200 text-left dark:divide-slate-800">
            <caption class="sr-only">Quarterly Monitoring Tool periods and available actions</caption>
            <thead class="bg-white dark:bg-slate-950/50">
                <tr class="text-sm font-semibold text-slate-600 dark:text-slate-300">
                    <th scope="col" class="w-24 px-5 py-3 sm:px-6">Quarter</th>
                    <th scope="col" class="min-w-[16rem] px-5 py-3">Reporting period</th>
                    <th scope="col" class="min-w-[11rem] px-5 py-3">Status</th>
                    <th scope="col" class="min-w-[13rem] px-5 py-3 text-right sm:px-6">Actions</th>
                </tr>
            </thead>
            <tbody
                x-data="{ openReportId: window.location.hash.startsWith('#monitoring-tool-') ? Number(window.location.hash.replace('#monitoring-tool-', '')) : null }"
                class="divide-y divide-slate-100 dark:divide-slate-800"
            >
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $quarterRows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <?php
                        $report = $row['report'];
                        $canSubmit = $topic && ! auth()->user()->isUsingWorkspace('research_head') && $topic->isMonitoringAvailable() && $topic->isAccessibleTo(auth()->user()) && $row['reporting_date'];
                        $canDraft = $topic && ! auth()->user()->isUsingWorkspace('research_head') && $topic->isMonitoringAvailable() && $topic->isAccessibleTo(auth()->user()) && ($row['drafting_date'] ?? null);
                        $statusClass = match (true) {
                            $report?->review_status === 'reviewed' => 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:ring-emerald-900',
                            $report?->review_status === 'revision_requested' => 'bg-red-50 text-red-700 ring-red-200 dark:bg-red-950/40 dark:text-red-300 dark:ring-red-900',
                            $report?->isPrepared() => 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:ring-amber-900',
                            $report !== null => 'bg-sky-50 text-sky-700 ring-sky-200 dark:bg-sky-950/40 dark:text-sky-300 dark:ring-sky-900',
                            $row['reporting_date'] !== null => 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:ring-amber-900',
                            default => 'bg-slate-100 text-slate-600 ring-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700',
                        };
                    ?>
                    <tr class="align-middle transition hover:bg-slate-50/70 dark:hover:bg-slate-950/30">
                        <th scope="row" class="px-5 py-4 sm:px-6">
                            <span class="inline-flex h-11 min-w-11 items-center justify-center rounded-xl bg-red-700 px-3 text-base font-bold text-white shadow-sm ring-1 ring-inset ring-red-800 dark:bg-red-700 dark:ring-red-600"><?php echo e($row['label']); ?></span>
                        </th>
                        <td class="px-5 py-4">
                            <p class="whitespace-nowrap text-base font-semibold text-slate-900 dark:text-white"><?php echo e($row['period']); ?></p>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $row['reporting_date'] && isset($row['opens_at'])): ?>
                                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Opens <?php echo e($row['opens_at']->format('M j, Y')); ?></p>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </td>
                        <td class="px-5 py-4">
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-sm font-semibold ring-1 ring-inset <?php echo e($statusClass); ?>"><?php echo e($row['status']); ?></span>
                        </td>
                        <td class="px-5 py-4 sm:px-6">
                            <div class="flex min-w-max flex-wrap items-center justify-end gap-2">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($report): ?>
                                    <button
                                        type="button"
                                        x-on:click="openReportId = openReportId === <?php echo e($report->id); ?> ? null : <?php echo e($report->id); ?>; if (openReportId === <?php echo e($report->id); ?>) { $nextTick(() => document.getElementById('monitoring-tool-<?php echo e($report->id); ?>')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' })) }"
                                        data-monitoring-action
                                        aria-controls="monitoring-tool-<?php echo e($report->id); ?>"
                                        x-bind:aria-expanded="openReportId === <?php echo e($report->id); ?>"
                                        class="inline-flex min-h-11 items-center justify-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-bold text-slate-700 transition hover:border-red-200 hover:bg-red-50 hover:text-red-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-600 focus-visible:ring-offset-2 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-red-900 dark:hover:bg-red-950/30 dark:hover:text-red-300 dark:focus-visible:ring-offset-slate-900"
                                    >
                                        View tool
                                        <svg class="h-3.5 w-3.5 transition-transform" x-bind:class="openReportId === <?php echo e($report->id); ?> && 'rotate-180'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" /></svg>
                                    </button>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canSubmit && $report->isPrepared() && $report->submitted_by === auth()->id()): ?>
                                        <a data-monitoring-action href="<?php echo e(route('project-progress.create', ['topic' => $topic, 'reporting_date' => $report->reporting_date->toDateString(), 'revise_monitoring_report' => $report->supersedes_report_id])); ?>" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-red-700 px-3 py-2 text-sm font-bold text-white transition hover:bg-red-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-600 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-900">Review and submit</a>
                                    <?php elseif($canSubmit && $report->review_status === 'revision_requested' && ! $report->nextVersion): ?>
                                        <a data-monitoring-action href="<?php echo e(route('project-progress.create', ['topic' => $topic, 'revise_monitoring_report' => $report->id])); ?>" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-red-700 px-3 py-2 text-sm font-bold text-white transition hover:bg-red-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-600 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-900">Revise tool</a>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php elseif($canDraft): ?>
                                    <a data-monitoring-action href="<?php echo e(route('project-progress.create', ['topic' => $topic, 'reporting_date' => $row['drafting_date']])); ?>" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-red-700 px-3 py-2 text-sm font-bold text-white transition hover:bg-red-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-600 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-900"><?php echo e($canSubmit ? 'Start tool' : 'Fill draft'); ?></a>
                                <?php elseif(! $row['reporting_date']): ?>
                                    <button type="button" disabled class="inline-flex min-h-11 cursor-not-allowed items-center justify-center rounded-lg border border-slate-200 bg-slate-100 px-3 py-2 text-sm font-bold text-slate-400 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-500">Not open yet</button>
                                <?php else: ?>
                                    <span class="text-sm font-semibold text-slate-400 dark:text-slate-500">No action available</span>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($report): ?>
                        <tr
                            id="monitoring-tool-<?php echo e($report->id); ?>"
                            x-cloak
                            x-show="openReportId === <?php echo e($report->id); ?>"
                            x-transition:enter="transition ease-out duration-200 motion-reduce:transition-none"
                            x-transition:enter-start="-translate-y-2 opacity-0"
                            x-transition:enter-end="translate-y-0 opacity-100"
                            x-transition:leave="transition ease-in duration-150 motion-reduce:transition-none"
                            x-transition:leave-start="translate-y-0 opacity-100"
                            x-transition:leave-end="-translate-y-2 opacity-0"
                            class="bg-slate-50/70 dark:bg-slate-950/30"
                        >
                            <td colspan="4" class="p-0">
                                <div class="border-y border-slate-200 p-5 dark:border-slate-800 sm:px-6">
                                    <?php if (isset($component)) { $__componentOriginal322b68206244453cf35ea37f45ac2df2 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal322b68206244453cf35ea37f45ac2df2 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.monitoring-tool-report-details','data' => ['topic' => $topic,'report' => $report]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('monitoring-tool-report-details'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['topic' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($topic),'report' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($report)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal322b68206244453cf35ea37f45ac2df2)): ?>
<?php $attributes = $__attributesOriginal322b68206244453cf35ea37f45ac2df2; ?>
<?php unset($__attributesOriginal322b68206244453cf35ea37f45ac2df2); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal322b68206244453cf35ea37f45ac2df2)): ?>
<?php $component = $__componentOriginal322b68206244453cf35ea37f45ac2df2; ?>
<?php unset($__componentOriginal322b68206244453cf35ea37f45ac2df2); ?>
<?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    <tr>
                        <td colspan="4" class="px-6 py-10 text-center text-sm text-slate-500 dark:text-slate-400">No reporting periods are scheduled. Check the approved project dates.</td>
                    </tr>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views/components/monitoring-quarter-overview.blade.php ENDPATH**/ ?>