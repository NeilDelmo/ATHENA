<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['quarterRows', 'progressQuarterRows', 'topic', 'canReport' => false]));

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

foreach (array_filter((['quarterRows', 'progressQuarterRows', 'topic', 'canReport' => false]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    $canViewProgressReport = Auth::user()->isUsingWorkspace('research_head')
        || $topic->canReviewImplementationReports(Auth::user())
        || $topic->isAccessibleTo(Auth::user());
?>

<section id="progress-reports" class="quarterly-reports overflow-hidden border-t border-slate-200 dark:border-slate-700" aria-labelledby="quarterly-reporting-schedule-heading">
    <header id="quarterly-reports" class="flex items-center gap-3 bg-slate-100 px-5 py-4 dark:bg-slate-800/60 sm:px-6">
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-brand/10 bg-brand-wash text-brand dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-200" aria-hidden="true"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 3v4m8-4v4M4 10h16M5 5h14a1 1 0 0 1 1 1v14H4V6a1 1 0 0 1 1-1Zm3 9 3 3 5-5"/></svg></span>
        <div class="min-w-0">
            <h4 id="quarterly-reporting-schedule-heading" class="text-xl font-semibold text-slate-950 dark:text-white">Quarterly reports</h4>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canReport && ! $topic->isCompletedProject()): ?>
                <p class="mt-1 text-sm leading-6 text-slate-600 dark:text-slate-400">Submit both documents each quarter.</p>
            <?php elseif($topic->isCompletedProject()): ?>
                <p class="mt-1 text-sm leading-6 text-slate-600 dark:text-slate-400">Accepted reports and submitted documents.</p>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    </header>

    <div data-monitoring-schedule-table data-progress-schedule-table>
        <table class="w-full table-fixed text-left">
            <caption class="sr-only">Quarterly Monitoring Tool and Progress Report periods and available actions</caption>
            <thead class="border-y border-slate-200 bg-slate-200/60 text-sm text-slate-600 dark:border-slate-700 dark:bg-slate-950/50 dark:text-slate-300">
                <tr>
                    <th scope="col" class="w-[28%] px-3 py-3 text-base font-semibold">Reporting period</th>
                    <th scope="col" class="w-[20%] px-2 py-3 text-base font-semibold">Deadline</th>
                    <th scope="col" class="w-[14%] px-2 py-3 text-base font-semibold">Targets</th>
                    <th scope="col" class="w-[19%] px-2 py-3 text-base font-semibold"><span class="quarterly-report-rail">Monitoring Tool<span class="sr-only">Activities &amp; budget</span></span></th>
                    <th scope="col" class="w-[19%] px-2 py-3 text-base font-semibold"><span class="quarterly-report-rail">Progress Report<span class="sr-only">Findings &amp; evidence</span></span></th>
                </tr>
            </thead>
            <tbody
                x-data="{
                    openReportId: /^#(monitoring-tool|narrative-report)-\d+$/.test(window.location.hash) ? window.location.hash.slice(1) : null,
                    closeReport() {
                        const trigger = $el.querySelector('button[aria-controls=&quot;' + this.openReportId + '&quot;]');
                        if (window.location.hash === '#' + this.openReportId) {
                            history.replaceState(history.state, '', '#project-monitoring');
                        }
                        this.openReportId = null;
                        $nextTick(() => trigger?.focus());
                    },
                    toggleReport(id) {
                        if (this.openReportId === id) { this.closeReport(); }
                        else { this.openReportId = id; }
                    }
                }"
                x-on:close-quarter-report.stop="closeReport()"
                x-on:keydown.escape="if (openReportId && ! $event.target.closest('[data-quarter-pdf-preview-modal]')) { $event.stopPropagation(); closeReport(); }"
                x-on:hashchange.window="if (/^#(monitoring-tool|narrative-report)-\d+$/.test(window.location.hash)) { openReportId = window.location.hash.slice(1) }"
            >
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $quarterRows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <?php
                        $report = ($canReport ? $topic->preparedProgressReports->first(fn ($preparedReport) => $preparedReport->reporting_date->betweenIncluded($row['start'], $row['end'])) : null) ?? $row['report'];
                        $progressRow = $progressQuarterRows->firstWhere('quarter', $row['quarter']);
                        $progressReport = $progressRow['report'] ?? null;
                        $overallProgress = $row['report']?->isSubmitted() ? $row['report']->progress_percentage : null;
                        $canSubmit = $canReport && $row['reporting_date'];
                        $canOpenPreparedTool = $canSubmit && $report?->isPrepared() && in_array(auth()->id(), [$report->submitted_by, $topic->user_id], true);
                        $canDraft = $canReport && ($row['drafting_date'] ?? null);
                        $toolStatus = $topic->isCompletedProject() && $report === null ? 'No tool on file' : ($report?->isPrepared() ? 'PDF prepared'.($report->supersedes_report_id === null && $row['end']->isPast() ? ' · Overdue' : '') : $row['status']);
                        $progressStatus = $topic->isCompletedProject() && $progressReport === null ? 'No report on file' : ($progressRow['status'] ?? 'No report on file');
                        $quarterOverdue = ! $topic->isCompletedProject() && (str_contains($toolStatus, 'Overdue') || str_contains($progressStatus, 'Overdue'));
                    ?>
                    <tr data-reporting-quarter="<?php echo e($row['quarter']); ?>" class="<?php echo \Illuminate\Support\Arr::toCssClasses(['border-t border-slate-200 dark:border-slate-800', 'bg-white dark:bg-slate-900' => $loop->odd, 'bg-slate-50 dark:bg-slate-800/30' => $loop->even]); ?>">
                        <th scope="row" class="px-3 py-2 align-middle font-normal">
                            <p data-reporting-period class="flex min-h-8 items-center gap-2 text-base leading-6 text-slate-900 dark:text-white"><span class="quarterly-report-quarter"><?php echo e($row['label']); ?></span><span><?php echo e($row['start']->format($row['start']->year === $row['end']->year ? 'M j' : 'M j, Y')); ?> – <?php echo e($row['end']->format('M j, Y')); ?></span></p>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $topic->isCompletedProject()): ?>
                                <span class="sr-only"><?php echo e($row['reporting_date'] ? 'Open for submission' : 'Submission opens '.$row['opens_at']->format('M j, Y')); ?></span>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </th>
                        <td data-quarter-deadline class="px-2 py-2 align-middle">
                            <span class="quarterly-report-mobile-label">Deadline</span>
                            <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                <p data-reporting-deadline class="whitespace-nowrap text-base text-slate-600 dark:text-slate-400"><span class="sr-only">Deadline </span><?php echo e($row['end']->format('M j, Y')); ?></p>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($quarterOverdue): ?><span data-quarter-overdue class="text-sm font-semibold text-red-700 dark:text-red-300" title="Both documents are required. At least one has not been submitted.">Overdue</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                        </td>
                        <td data-quarter-targets class="px-2 py-2 align-middle">
                            <span class="quarterly-report-mobile-label">Targets</span>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($row['has_approved_work_plan'] ?? false) && now()->greaterThan($row['end'])): ?>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($row['quarter_completion'] ?? null) !== null): ?>
                                    <?php if (isset($component)) { $__componentOriginal8c86773791c5117d75f41d0a03e4fbae = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal8c86773791c5117d75f41d0a03e4fbae = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.report-review-status','data' => ['compact' => true,'status' => ($row['has_overdue_targets'] ?? false) ? 'revision_requested' : 'reviewed','label' => ($row['has_overdue_targets'] ?? false) ? 'Targets still incomplete' : 'Targets completed']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('report-review-status'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['compact' => true,'status' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(($row['has_overdue_targets'] ?? false) ? 'revision_requested' : 'reviewed'),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(($row['has_overdue_targets'] ?? false) ? 'Targets still incomplete' : 'Targets completed')]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal8c86773791c5117d75f41d0a03e4fbae)): ?>
<?php $attributes = $__attributesOriginal8c86773791c5117d75f41d0a03e4fbae; ?>
<?php unset($__attributesOriginal8c86773791c5117d75f41d0a03e4fbae); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal8c86773791c5117d75f41d0a03e4fbae)): ?>
<?php $component = $__componentOriginal8c86773791c5117d75f41d0a03e4fbae; ?>
<?php unset($__componentOriginal8c86773791c5117d75f41d0a03e4fbae); ?>
<?php endif; ?>
                                <?php elseif($overallProgress !== null): ?>
                                    <span class="text-base text-slate-500 dark:text-slate-400">None due</span>
                                <?php else: ?>
                                    <span class="text-base text-slate-500 dark:text-slate-400" aria-label="No target assessment recorded">—</span>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <?php else: ?>
                                <span class="text-base text-slate-500 dark:text-slate-400" aria-label="No target assessment recorded">—</span>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </td>
                        <td data-monitoring-quarter="<?php echo e($row['quarter']); ?>" data-live-quarter-kind="monitoring" data-live-quarter="<?php echo e($row['quarter']); ?>" data-live-report-key="<?php echo e($report ? \App\Services\TopicLiveStatus::reportKey($report) : ''); ?>" data-live-report-scope="<?php echo e($report ? \App\Services\TopicLiveStatus::reportScope($topic, $report) : ''); ?>" class="px-2 py-2 align-middle">
                            <div class="quarterly-report-rail"><span class="quarterly-report-mobile-label">Monitoring Tool</span>
                            <div data-report-controls class="flex flex-wrap items-center justify-start gap-1">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($report): ?>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $report->isPrepared()): ?>
                                    <button type="button" x-on:click="toggleReport('monitoring-tool-<?php echo e($report->id); ?>')" data-monitoring-action aria-controls="monitoring-tool-<?php echo e($report->id); ?>" x-bind:aria-expanded="openReportId === 'monitoring-tool-<?php echo e($report->id); ?>'" aria-label="View <?php echo e($row['label']); ?> monitoring tool: <?php echo e($toolStatus); ?>" title="<?php echo e($row['label']); ?> Monitoring Tool · <?php echo e($toolStatus); ?>" class="monitoring-report-control quarterly-report-primary">
                                        <span>View</span>
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                                        <?php if (isset($component)) { $__componentOriginal8c86773791c5117d75f41d0a03e4fbae = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal8c86773791c5117d75f41d0a03e4fbae = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.report-review-status','data' => ['dataMonitoringReportStatus' => true,'compact' => true,'status' => $report?->review_status ?? 'unsubmitted','label' => $toolStatus]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('report-review-status'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['data-monitoring-report-status' => true,'compact' => true,'status' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($report?->review_status ?? 'unsubmitted'),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($toolStatus)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal8c86773791c5117d75f41d0a03e4fbae)): ?>
<?php $attributes = $__attributesOriginal8c86773791c5117d75f41d0a03e4fbae; ?>
<?php unset($__attributesOriginal8c86773791c5117d75f41d0a03e4fbae); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal8c86773791c5117d75f41d0a03e4fbae)): ?>
<?php $component = $__componentOriginal8c86773791c5117d75f41d0a03e4fbae; ?>
<?php unset($__componentOriginal8c86773791c5117d75f41d0a03e4fbae); ?>
<?php endif; ?>
                                    </button>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canOpenPreparedTool): ?>
                                        <a data-monitoring-action href="<?php echo e(route('project-progress.create', ['topic' => $topic, 'reporting_date' => $report->reporting_date->toDateString(), 'revise_monitoring_report' => $report->supersedes_report_id])); ?>" title="Review and submit <?php echo e($row['label']); ?> monitoring tool" aria-label="Review and submit <?php echo e($row['label']); ?> monitoring tool" class="monitoring-report-control quarterly-report-primary"><span>Submit</span><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16V4m-4 4 4-4 4 4M5 14v6h14v-6" /></svg><?php if (isset($component)) { $__componentOriginal8c86773791c5117d75f41d0a03e4fbae = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal8c86773791c5117d75f41d0a03e4fbae = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.report-review-status','data' => ['dataMonitoringReportStatus' => true,'compact' => true,'status' => $report?->review_status ?? 'unsubmitted','label' => $toolStatus]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('report-review-status'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['data-monitoring-report-status' => true,'compact' => true,'status' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($report?->review_status ?? 'unsubmitted'),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($toolStatus)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal8c86773791c5117d75f41d0a03e4fbae)): ?>
<?php $attributes = $__attributesOriginal8c86773791c5117d75f41d0a03e4fbae; ?>
<?php unset($__attributesOriginal8c86773791c5117d75f41d0a03e4fbae); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal8c86773791c5117d75f41d0a03e4fbae)): ?>
<?php $component = $__componentOriginal8c86773791c5117d75f41d0a03e4fbae; ?>
<?php unset($__componentOriginal8c86773791c5117d75f41d0a03e4fbae); ?>
<?php endif; ?></a>
                                    <?php elseif($canSubmit && $report->review_status === 'revision_requested' && ! $report->nextVersion): ?>
                                        <a data-monitoring-action href="<?php echo e(route('project-progress.create', ['topic' => $topic, 'revise_monitoring_report' => $report->id])); ?>" title="Revise <?php echo e($row['label']); ?> monitoring tool" aria-label="Revise <?php echo e($row['label']); ?> monitoring tool" class="monitoring-report-control quarterly-report-secondary"><span>Revise tool</span><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m16 4 4 4M4 20l5-1L20 8a3 3 0 0 0-4-4L5 15l-1 5Z" /></svg></a>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canReport && $report->isPrepared()): ?>
                                        <a href="<?php echo e(route('project-budget.edit', [$topic, $report])); ?>" title="<?php echo e($report->hasPreparedBudget() ? 'Review budget' : 'Complete budget'); ?>" aria-label="<?php echo e($report->hasPreparedBudget() ? 'Review budget' : 'Complete budget'); ?>" class="monitoring-report-control <?php echo e($canOpenPreparedTool ? 'quarterly-report-secondary' : 'quarterly-report-primary'); ?>"><span>Budget</span><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m3-10h-4a2 2 0 0 0 0 4h2a2 2 0 0 1 0 4H9" /></svg><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $canOpenPreparedTool): ?><?php if (isset($component)) { $__componentOriginal8c86773791c5117d75f41d0a03e4fbae = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal8c86773791c5117d75f41d0a03e4fbae = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.report-review-status','data' => ['dataMonitoringReportStatus' => true,'compact' => true,'status' => $report?->review_status ?? 'unsubmitted','label' => $toolStatus]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('report-review-status'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['data-monitoring-report-status' => true,'compact' => true,'status' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($report?->review_status ?? 'unsubmitted'),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($toolStatus)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal8c86773791c5117d75f41d0a03e4fbae)): ?>
<?php $attributes = $__attributesOriginal8c86773791c5117d75f41d0a03e4fbae; ?>
<?php unset($__attributesOriginal8c86773791c5117d75f41d0a03e4fbae); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal8c86773791c5117d75f41d0a03e4fbae)): ?>
<?php $component = $__componentOriginal8c86773791c5117d75f41d0a03e4fbae; ?>
<?php unset($__componentOriginal8c86773791c5117d75f41d0a03e4fbae); ?>
<?php endif; ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></a>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($report->isPrepared() && ! $canReport): ?>
                                        <span class="monitoring-report-control quarterly-report-primary" aria-disabled="true" role="img" aria-label="<?php echo e($row['label']); ?> monitoring tool unavailable" title="<?php echo e($toolStatus); ?>"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-6-6Zm0 0v6h6M8 17v-3m4 3v-5m4 5v-2" /></svg><?php if (isset($component)) { $__componentOriginal8c86773791c5117d75f41d0a03e4fbae = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal8c86773791c5117d75f41d0a03e4fbae = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.report-review-status','data' => ['dataMonitoringReportStatus' => true,'compact' => true,'status' => $report?->review_status ?? 'unsubmitted','label' => $toolStatus]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('report-review-status'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['data-monitoring-report-status' => true,'compact' => true,'status' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($report?->review_status ?? 'unsubmitted'),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($toolStatus)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal8c86773791c5117d75f41d0a03e4fbae)): ?>
<?php $attributes = $__attributesOriginal8c86773791c5117d75f41d0a03e4fbae; ?>
<?php unset($__attributesOriginal8c86773791c5117d75f41d0a03e4fbae); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal8c86773791c5117d75f41d0a03e4fbae)): ?>
<?php $component = $__componentOriginal8c86773791c5117d75f41d0a03e4fbae; ?>
<?php unset($__componentOriginal8c86773791c5117d75f41d0a03e4fbae); ?>
<?php endif; ?></span>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php elseif($canDraft): ?>
                                    <a data-monitoring-action href="<?php echo e(route('project-progress.create', ['topic' => $topic, 'reporting_date' => $row['drafting_date']])); ?>" title="<?php echo e($canSubmit ? 'Start tool' : 'Fill draft'); ?> · <?php echo e($row['label']); ?> Monitoring Tool" aria-label="<?php echo e($canSubmit ? 'Start' : 'Draft'); ?> <?php echo e($row['label']); ?> monitoring tool" class="monitoring-report-control quarterly-report-primary"><span><?php echo e($canSubmit ? 'Start' : 'Draft'); ?></span><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-6-6Zm0 0v6h6M8 15h8m-4-4v8" /></svg><?php if (isset($component)) { $__componentOriginal8c86773791c5117d75f41d0a03e4fbae = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal8c86773791c5117d75f41d0a03e4fbae = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.report-review-status','data' => ['dataMonitoringReportStatus' => true,'compact' => true,'status' => $report?->review_status ?? 'unsubmitted','label' => $toolStatus]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('report-review-status'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['data-monitoring-report-status' => true,'compact' => true,'status' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($report?->review_status ?? 'unsubmitted'),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($toolStatus)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal8c86773791c5117d75f41d0a03e4fbae)): ?>
<?php $attributes = $__attributesOriginal8c86773791c5117d75f41d0a03e4fbae; ?>
<?php unset($__attributesOriginal8c86773791c5117d75f41d0a03e4fbae); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal8c86773791c5117d75f41d0a03e4fbae)): ?>
<?php $component = $__componentOriginal8c86773791c5117d75f41d0a03e4fbae; ?>
<?php unset($__componentOriginal8c86773791c5117d75f41d0a03e4fbae); ?>
<?php endif; ?></a>
                                <?php else: ?>
                                    <span class="monitoring-report-control quarterly-report-primary" aria-disabled="true" role="img" aria-label="<?php echo e($row['label']); ?> monitoring tool unavailable" title="<?php echo e($toolStatus); ?>"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-6-6Zm0 0v6h6M8 17v-3m4 3v-5m4 5v-2" /></svg><?php if (isset($component)) { $__componentOriginal8c86773791c5117d75f41d0a03e4fbae = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal8c86773791c5117d75f41d0a03e4fbae = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.report-review-status','data' => ['dataMonitoringReportStatus' => true,'compact' => true,'status' => $report?->review_status ?? 'unsubmitted','label' => $toolStatus]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('report-review-status'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['data-monitoring-report-status' => true,'compact' => true,'status' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($report?->review_status ?? 'unsubmitted'),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($toolStatus)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal8c86773791c5117d75f41d0a03e4fbae)): ?>
<?php $attributes = $__attributesOriginal8c86773791c5117d75f41d0a03e4fbae; ?>
<?php unset($__attributesOriginal8c86773791c5117d75f41d0a03e4fbae); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal8c86773791c5117d75f41d0a03e4fbae)): ?>
<?php $component = $__componentOriginal8c86773791c5117d75f41d0a03e4fbae; ?>
<?php unset($__componentOriginal8c86773791c5117d75f41d0a03e4fbae); ?>
<?php endif; ?></span>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div></div>
                        </td>
                        <td data-progress-quarter="<?php echo e($row['quarter']); ?>" data-live-quarter-kind="progress" data-live-quarter="<?php echo e($row['quarter']); ?>" data-live-report-key="<?php echo e($progressReport ? \App\Services\TopicLiveStatus::reportKey($progressReport) : ''); ?>" data-live-report-scope="<?php echo e($progressReport ? \App\Services\TopicLiveStatus::reportScope($topic, $progressReport) : ''); ?>" class="px-2 py-2 align-middle">
                            <div class="quarterly-report-rail"><span class="quarterly-report-mobile-label">Progress Report</span>
                            <div data-report-controls class="flex flex-wrap items-center justify-start gap-1">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($progressReport && ! $progressReport->isPrepared() && $canViewProgressReport): ?>
                                    <button type="button" x-on:click="toggleReport('narrative-report-<?php echo e($progressReport->id); ?>')" data-progress-action aria-controls="narrative-report-<?php echo e($progressReport->id); ?>" x-bind:aria-expanded="openReportId === 'narrative-report-<?php echo e($progressReport->id); ?>'" aria-label="View <?php echo e($row['label']); ?> progress report: <?php echo e($progressStatus); ?>" title="<?php echo e($row['label']); ?> Progress Report · <?php echo e($progressStatus); ?>" class="monitoring-report-control quarterly-report-primary"><span>View</span><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg><?php if (isset($component)) { $__componentOriginal8c86773791c5117d75f41d0a03e4fbae = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal8c86773791c5117d75f41d0a03e4fbae = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.report-review-status','data' => ['dataNarrativeReportStatus' => true,'compact' => true,'status' => $progressReport?->review_status ?? 'unsubmitted','label' => $progressStatus,'description' => $progressReport?->review_status_label]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('report-review-status'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['data-narrative-report-status' => true,'compact' => true,'status' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($progressReport?->review_status ?? 'unsubmitted'),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($progressStatus),'description' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($progressReport?->review_status_label)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal8c86773791c5117d75f41d0a03e4fbae)): ?>
<?php $attributes = $__attributesOriginal8c86773791c5117d75f41d0a03e4fbae; ?>
<?php unset($__attributesOriginal8c86773791c5117d75f41d0a03e4fbae); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal8c86773791c5117d75f41d0a03e4fbae)): ?>
<?php $component = $__componentOriginal8c86773791c5117d75f41d0a03e4fbae; ?>
<?php unset($__componentOriginal8c86773791c5117d75f41d0a03e4fbae); ?>
<?php endif; ?></button>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canReport && ($progressRow['drafting_date'] ?? null)): ?>
                                    <a href="<?php echo e(route('project-narrative-reports.create', ['topic' => $topic, 'report_type' => 'progress', 'reporting_date' => $progressRow['drafting_date']])); ?>" aria-label="<?php echo e($progressReport?->isPrepared() ? 'Review' : ($progressReport ? 'Revise' : 'Fill')); ?> <?php echo e($row['label']); ?> progress report" title="<?php echo e($progressReport?->isPrepared() ? 'Review and submit' : ($progressReport ? 'Revise report' : ($progressRow['submission_open'] ? 'Start report' : 'Fill draft'))); ?> · <?php echo e($row['label']); ?> Progress Report" class="monitoring-report-control <?php echo e($progressReport && ! $progressReport->isPrepared() && $canViewProgressReport ? 'quarterly-report-secondary' : 'quarterly-report-primary'); ?>"><span><?php echo e($progressReport?->isPrepared() ? 'Submit' : ($progressReport ? 'Revise report' : ($progressRow['submission_open'] ? 'Start' : 'Draft'))); ?></span><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m16 4 4 4M4 20l5-1L20 8a3 3 0 0 0-4-4L5 15l-1 5Z" /></svg><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $progressReport || $progressReport->isPrepared() || ! $canViewProgressReport): ?><?php if (isset($component)) { $__componentOriginal8c86773791c5117d75f41d0a03e4fbae = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal8c86773791c5117d75f41d0a03e4fbae = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.report-review-status','data' => ['dataNarrativeReportStatus' => true,'compact' => true,'status' => $progressReport?->review_status ?? 'unsubmitted','label' => $progressStatus,'description' => $progressReport?->review_status_label]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('report-review-status'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['data-narrative-report-status' => true,'compact' => true,'status' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($progressReport?->review_status ?? 'unsubmitted'),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($progressStatus),'description' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($progressReport?->review_status_label)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal8c86773791c5117d75f41d0a03e4fbae)): ?>
<?php $attributes = $__attributesOriginal8c86773791c5117d75f41d0a03e4fbae; ?>
<?php unset($__attributesOriginal8c86773791c5117d75f41d0a03e4fbae); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal8c86773791c5117d75f41d0a03e4fbae)): ?>
<?php $component = $__componentOriginal8c86773791c5117d75f41d0a03e4fbae; ?>
<?php unset($__componentOriginal8c86773791c5117d75f41d0a03e4fbae); ?>
<?php endif; ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></a>
                                <?php elseif(! $progressReport || ! $canViewProgressReport): ?>
                                    <span class="monitoring-report-control quarterly-report-primary" aria-disabled="true" role="img" aria-label="<?php echo e($row['label']); ?> progress report unavailable" title="<?php echo e($progressStatus); ?>"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-6-6Zm0 0v6h6M8 13h8m-8 4h5" /></svg><?php if (isset($component)) { $__componentOriginal8c86773791c5117d75f41d0a03e4fbae = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal8c86773791c5117d75f41d0a03e4fbae = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.report-review-status','data' => ['dataNarrativeReportStatus' => true,'compact' => true,'status' => $progressReport?->review_status ?? 'unsubmitted','label' => $progressStatus,'description' => $progressReport?->review_status_label]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('report-review-status'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['data-narrative-report-status' => true,'compact' => true,'status' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($progressReport?->review_status ?? 'unsubmitted'),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($progressStatus),'description' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($progressReport?->review_status_label)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal8c86773791c5117d75f41d0a03e4fbae)): ?>
<?php $attributes = $__attributesOriginal8c86773791c5117d75f41d0a03e4fbae; ?>
<?php unset($__attributesOriginal8c86773791c5117d75f41d0a03e4fbae); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal8c86773791c5117d75f41d0a03e4fbae)): ?>
<?php $component = $__componentOriginal8c86773791c5117d75f41d0a03e4fbae; ?>
<?php unset($__componentOriginal8c86773791c5117d75f41d0a03e4fbae); ?>
<?php endif; ?></span>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div></div>
                        </td>
                    </tr>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($report && ! $report->isPrepared()): ?>
                        <tr id="monitoring-tool-<?php echo e($report->id); ?>" x-cloak x-show="openReportId === 'monitoring-tool-<?php echo e($report->id); ?>'" class="bg-slate-100 dark:bg-slate-950/40">
                            <td colspan="5" class="border-t border-slate-200 p-3 dark:border-slate-700 sm:p-4">
                                <?php if (isset($component)) { $__componentOriginal322b68206244453cf35ea37f45ac2df2 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal322b68206244453cf35ea37f45ac2df2 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.monitoring-tool-report-details','data' => ['topic' => $topic,'report' => $report,'closable' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('monitoring-tool-report-details'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['topic' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($topic),'report' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($report),'closable' => true]); ?>
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
                            </td>
                        </tr>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($progressReport && ! $progressReport->isPrepared() && $canViewProgressReport): ?>
                        <tr id="narrative-report-<?php echo e($progressReport->id); ?>" data-narrative-history-entry="<?php echo e($progressReport->id); ?>" x-cloak x-show="openReportId === 'narrative-report-<?php echo e($progressReport->id); ?>'" class="bg-slate-100 dark:bg-slate-950/40">
                            <td colspan="5" class="border-t border-slate-200 p-3 dark:border-slate-700 sm:p-4">
                                <?php if (isset($component)) { $__componentOriginal033c31d0ec257830db98f18e54a9cfee = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal033c31d0ec257830db98f18e54a9cfee = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.progress-report-details','data' => ['topic' => $topic,'report' => $progressReport,'quarter' => $row['quarter'],'progressPercentage' => $overallProgress,'closable' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('progress-report-details'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['topic' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($topic),'report' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($progressReport),'quarter' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($row['quarter']),'progress-percentage' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($overallProgress),'closable' => true]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal033c31d0ec257830db98f18e54a9cfee)): ?>
<?php $attributes = $__attributesOriginal033c31d0ec257830db98f18e54a9cfee; ?>
<?php unset($__attributesOriginal033c31d0ec257830db98f18e54a9cfee); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal033c31d0ec257830db98f18e54a9cfee)): ?>
<?php $component = $__componentOriginal033c31d0ec257830db98f18e54a9cfee; ?>
<?php unset($__componentOriginal033c31d0ec257830db98f18e54a9cfee); ?>
<?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    <tr><td colspan="5" class="px-6 py-8 text-sm text-slate-500 dark:text-slate-400">No reporting periods are scheduled. Check the approved project dates.</td></tr>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\components\monitoring-quarter-overview.blade.php ENDPATH**/ ?>