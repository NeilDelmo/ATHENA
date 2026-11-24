<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['report', 'title', 'isCurrentVersion' => true, 'closable' => false]));

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

foreach (array_filter((['report', 'title', 'isCurrentVersion' => true, 'closable' => false]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    $isMonitoringTool = $report instanceof \App\Models\ProjectProgressReport;
    $modalName = ($isMonitoringTool ? 'monitoring-pdf-' : 'narrative-pdf-').$report->id;
    $downloadUrl = route($isMonitoringTool ? 'project-progress.monitoring-tool' : 'project-narrative-reports.download', $report);
    $previewUrl = route($isMonitoringTool ? 'project-progress.monitoring-tool.view' : 'project-narrative-reports.view', $report);
    $submittedDate = $isMonitoringTool ? ($report->submitted_at ?? $report->created_at) : $report->submission_date;
    $status = $report->review_stage !== null ? $report->review_status_label : match ($report->review_status) {
        'reviewed' => 'Reviewed',
        'revision_requested' => 'Corrections requested',
        default => 'Pending review',
    };
?>

<header data-quarter-report-heading class="quarter-report-heading">
    <div class="min-w-0">
        <div class="flex flex-wrap items-center gap-2">
            <h4 class="text-xl font-semibold tracking-tight text-slate-950 dark:text-white"><?php echo e($title); ?></h4>
            <?php if (isset($component)) { $__componentOriginal8c86773791c5117d75f41d0a03e4fbae = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal8c86773791c5117d75f41d0a03e4fbae = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.report-review-status','data' => ['dataLiveReportStatus' => \App\Services\TopicLiveStatus::reportKey($report),'status' => $report->review_status,'label' => $status,'description' => $report->review_status_label]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('report-review-status'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['data-live-report-status' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(\App\Services\TopicLiveStatus::reportKey($report)),'status' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($report->review_status),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($status),'description' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($report->review_status_label)]); ?>
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
            <span class="<?php echo e($isCurrentVersion ? 'sr-only' : 'text-sm text-slate-500 dark:text-slate-400'); ?>"><?php echo e($isCurrentVersion ? 'Current submission' : 'Historical version'); ?></span>
        </div>
        <p class="mt-2 text-base leading-7 text-slate-600 dark:text-slate-300">Submitted by <?php echo e($report->submitter->name); ?> · <?php echo e($submittedDate?->format('M j, Y')); ?></p>
    </div>
    <div data-quarter-report-actions class="quarter-report-actions">
        <button type="button" aria-label="Preview submitted PDF" title="Preview PDF" aria-haspopup="dialog" @click="$dispatch('open-modal', '<?php echo e($modalName); ?>')" class="inline-flex min-h-12 items-center justify-center gap-2 rounded-lg bg-red-700 px-4 py-3 text-base font-semibold text-white hover:bg-red-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700 dark:bg-red-600 dark:hover:bg-red-500">
            <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.5-7.5 9.75-7.5 9.75 7.5 9.75 7.5-3.5 7.5-9.75 7.5S2.25 12 2.25 12Z" /><circle cx="12" cy="12" r="3" /></svg>
            Preview PDF
        </button>
        <a href="<?php echo e($downloadUrl); ?>" class="quarter-report-download" aria-label="Download <?php echo e($isMonitoringTool ? 'monitoring tool' : 'progress report'); ?> PDF" title="Download PDF">
            <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m-4-4 4 4 4-4M4 16v4h16v-4" /></svg>
            <span>Download<span class="hidden sm:inline"> PDF</span></span>
        </a>
    </div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($closable): ?>
        <button type="button" data-close-quarter-report @click="$dispatch('close-quarter-report')" aria-label="Close report" title="Close report" class="quarter-report-close">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="m6 6 12 12M6 18 18 6" /></svg>
        </button>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</header>

<?php if (isset($component)) { $__componentOriginal66cb43065a62ebef588707752044cfef = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal66cb43065a62ebef588707752044cfef = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.submitted-report-preview','data' => ['name' => $modalName,'title' => $title,'description' => $report->reporting_period_label,'previewUrl' => $previewUrl,'downloadUrl' => $downloadUrl,'viewerLabel' => $title.' PDF','dataQuarterPdfPreviewModal' => true,'dataReportKind' => $isMonitoringTool ? 'monitoring' : 'progress']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('submitted-report-preview'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($modalName),'title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($title),'description' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($report->reporting_period_label),'preview-url' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($previewUrl),'download-url' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($downloadUrl),'viewer-label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($title.' PDF'),'data-quarter-pdf-preview-modal' => true,'data-report-kind' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($isMonitoringTool ? 'monitoring' : 'progress')]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal66cb43065a62ebef588707752044cfef)): ?>
<?php $attributes = $__attributesOriginal66cb43065a62ebef588707752044cfef; ?>
<?php unset($__attributesOriginal66cb43065a62ebef588707752044cfef); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal66cb43065a62ebef588707752044cfef)): ?>
<?php $component = $__componentOriginal66cb43065a62ebef588707752044cfef; ?>
<?php unset($__componentOriginal66cb43065a62ebef588707752044cfef); ?>
<?php endif; ?>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\components\quarter-report-header.blade.php ENDPATH**/ ?>