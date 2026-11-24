<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['report', 'headingId' => null]));

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

foreach (array_filter((['report', 'headingId' => null]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    $compactStatus = $report->review_stage !== null ? $report->review_status_label : match ($report->review_status) {
        'reviewed' => 'Reviewed',
        'revision_requested' => 'Corrections requested',
        default => 'Awaiting review',
    };
?>

<article id="narrative-report-<?php echo e($report->id); ?>" data-narrative-report-summary data-narrative-history-entry="<?php echo e($report->id); ?>" <?php echo e($attributes->class(['scroll-mt-36 flex flex-col gap-3 rounded-xl border border-slate-200 bg-white px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6 dark:border-slate-700 dark:bg-slate-900'])); ?>>
    <div class="min-w-0">
        <h4 <?php if($headingId): ?> id="<?php echo e($headingId); ?>" <?php endif; ?> class="text-base font-semibold text-slate-950 dark:text-white"><?php echo e($report->report_label); ?></h4>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400"><?php echo e($report->submission_date->format('M j, Y')); ?> · <?php echo e($report->submitter->name); ?></p>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($report->reporting_period_label): ?><p class="mt-1 text-sm font-medium text-slate-600 dark:text-slate-300"><?php echo e($report->reporting_period_label); ?> · Version <?php echo e($report->version_number); ?></p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
    <div class="flex flex-wrap items-center gap-3 sm:shrink-0 sm:justify-end">
        <?php if (isset($component)) { $__componentOriginal8c86773791c5117d75f41d0a03e4fbae = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal8c86773791c5117d75f41d0a03e4fbae = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.report-review-status','data' => ['dataNarrativeReportStatus' => true,'status' => $report->review_status,'label' => $compactStatus,'description' => $report->review_status_label]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('report-review-status'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['data-narrative-report-status' => true,'status' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($report->review_status),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($compactStatus),'description' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($report->review_status_label)]); ?>
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
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($report->report_type === 'terminal' && ($report->hasSignedCopy() || $report->review_status === \App\Models\ProjectNarrativeReport::STATUS_REVIEWED)): ?>
            <span class="text-sm text-gray-600 dark:text-slate-300"><?php echo e($report->hasSignedCopy() ? 'Signed terminal PDF uploaded' : 'Signed terminal PDF needed'); ?></span>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <a href="<?php echo e(route('project-narrative-reports.show', $report)); ?>" aria-label="Open <?php echo e(strtolower($report->report_label)); ?> submitted <?php echo e($report->submission_date->format('M j, Y')); ?>" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800">Open report</a>
    </div>
</article>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\components\narrative-report-summary.blade.php ENDPATH**/ ?>