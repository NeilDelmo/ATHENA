<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['topic', 'report', 'quarter' => null, 'progressPercentage' => null, 'closable' => false]));

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

foreach (array_filter((['topic', 'report', 'quarter' => null, 'progressPercentage' => null, 'closable' => false]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php ($quarter = $quarter ?? $report->reporting_quarter); ?>
<?php ($progressPercentage = $report->monitoringReport?->progress_percentage ?? $progressPercentage); ?>

<div class="quarter-report-document" data-progress-report-detail data-live-report-key="<?php echo e(\App\Services\TopicLiveStatus::reportKey($report)); ?>" data-live-report-scope="<?php echo e(\App\Services\TopicLiveStatus::reportScope($topic, $report)); ?>">
    <?php if (isset($component)) { $__componentOriginale530538cef0ecc2ab9b248a7128167db = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginale530538cef0ecc2ab9b248a7128167db = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.quarter-report-header','data' => ['report' => $report,'title' => 'Q'.$quarter.' Progress Report · Version '.($report->version_number ?? 1),'closable' => $closable]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('quarter-report-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['report' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($report),'title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute('Q'.$quarter.' Progress Report · Version '.($report->version_number ?? 1)),'closable' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($closable)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginale530538cef0ecc2ab9b248a7128167db)): ?>
<?php $attributes = $__attributesOriginale530538cef0ecc2ab9b248a7128167db; ?>
<?php unset($__attributesOriginale530538cef0ecc2ab9b248a7128167db); ?>
<?php endif; ?>
<?php if (isset($__componentOriginale530538cef0ecc2ab9b248a7128167db)): ?>
<?php $component = $__componentOriginale530538cef0ecc2ab9b248a7128167db; ?>
<?php unset($__componentOriginale530538cef0ecc2ab9b248a7128167db); ?>
<?php endif; ?>
    <div class="quarter-report-layout">
        <div class="min-w-0 space-y-5">
            <section class="max-w-lg" aria-label="Recorded project progress">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($progressPercentage !== null): ?>
                    <div class="flex items-baseline justify-between gap-3 text-base"><h4 class="font-medium text-slate-900 dark:text-white">Recorded project progress</h4><span class="font-semibold tabular-nums text-slate-900 dark:text-white"><?php echo e($progressPercentage); ?>%</span></div>
                    <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700" role="progressbar" aria-label="Q<?php echo e($quarter); ?> reported progress" aria-valuenow="<?php echo e($progressPercentage); ?>" aria-valuemin="0" aria-valuemax="100"><div class="h-full rounded-full bg-red-700 dark:bg-red-400" style="width: <?php echo e($progressPercentage); ?>%"></div></div>
                    <p class="mt-2 text-base leading-6 text-slate-500 dark:text-slate-400">From the Q<?php echo e($quarter); ?> Monitoring Tool for this reporting period</p>
                <?php else: ?>
                    <p class="text-base text-slate-500 dark:text-slate-400">No monitoring progress recorded for this quarter.</p>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </section>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(str_starts_with($report->accomplishment_summary ?? '', 'UI DEMONSTRATION')): ?>
                <p class="text-base text-slate-500 dark:text-slate-400">Sample report · Illustrative figures and findings.</p>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($report->research_head_remarks): ?>
                <section class="border-t border-slate-200 pt-3 dark:border-slate-700"><h4 class="text-base font-medium text-slate-950 dark:text-white"><?php echo e($report->review_stage === 'secretary' && $report->review_status === 'pending' ? 'Research Head concerns · Research Office Secretary to resolve' : 'Research Head remarks'); ?> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($report->reviewer): ?><span class="ml-2 font-normal text-slate-500 dark:text-slate-400"><?php echo e($report->reviewer->name); ?></span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></h4><p class="mt-1 max-w-[70ch] whitespace-pre-line break-words text-base leading-6 text-slate-600 dark:text-slate-300"><?php echo e($report->research_head_remarks); ?></p></section>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <?php if (isset($component)) { $__componentOriginal7a3a3f1f6991c7f09c0281b748d8aafa = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal7a3a3f1f6991c7f09c0281b748d8aafa = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.implementation-report-secretary-review','data' => ['topic' => $topic,'report' => $report]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('implementation-report-secretary-review'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['topic' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($topic),'report' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($report)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal7a3a3f1f6991c7f09c0281b748d8aafa)): ?>
<?php $attributes = $__attributesOriginal7a3a3f1f6991c7f09c0281b748d8aafa; ?>
<?php unset($__attributesOriginal7a3a3f1f6991c7f09c0281b748d8aafa); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal7a3a3f1f6991c7f09c0281b748d8aafa)): ?>
<?php $component = $__componentOriginal7a3a3f1f6991c7f09c0281b748d8aafa; ?>
<?php unset($__componentOriginal7a3a3f1f6991c7f09c0281b748d8aafa); ?>
<?php endif; ?>
            <?php if (isset($component)) { $__componentOriginal0d993c4296834f40caa3a752b34cb270 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal0d993c4296834f40caa3a752b34cb270 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.implementation-report-head-review','data' => ['topic' => $topic,'report' => $report]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('implementation-report-head-review'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['topic' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($topic),'report' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($report)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal0d993c4296834f40caa3a752b34cb270)): ?>
<?php $attributes = $__attributesOriginal0d993c4296834f40caa3a752b34cb270; ?>
<?php unset($__attributesOriginal0d993c4296834f40caa3a752b34cb270); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal0d993c4296834f40caa3a752b34cb270)): ?>
<?php $component = $__componentOriginal0d993c4296834f40caa3a752b34cb270; ?>
<?php unset($__componentOriginal0d993c4296834f40caa3a752b34cb270); ?>
<?php endif; ?>

            <div class="pt-1">
                <a data-progress-report-reader-link href="<?php echo e(route('project-narrative-reports.show', $report)); ?>" aria-label="Read full Q<?php echo e($quarter); ?> progress report" class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-base font-medium text-slate-700 hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">
                    Report details
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m9 5 7 7-7 7" /></svg>
                </a>
            </div>
        </div>
        <?php if (isset($component)) { $__componentOriginal705afa55b28306e08e85a5bbc410f198 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal705afa55b28306e08e85a5bbc410f198 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.submitted-report-document','data' => ['name' => 'progress-paper-'.e($report->id).'','modalName' => 'narrative-pdf-'.e($report->id).'','title' => 'Submitted progress report paper','previewUrl' => route('project-narrative-reports.view', $report),'downloadUrl' => route('project-narrative-reports.download', $report),'viewerLabel' => 'Q'.$quarter.' progress report paper']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('submitted-report-document'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'progress-paper-'.e($report->id).'','modal-name' => 'narrative-pdf-'.e($report->id).'','title' => 'Submitted progress report paper','preview-url' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('project-narrative-reports.view', $report)),'download-url' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('project-narrative-reports.download', $report)),'viewer-label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute('Q'.$quarter.' progress report paper')]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal705afa55b28306e08e85a5bbc410f198)): ?>
<?php $attributes = $__attributesOriginal705afa55b28306e08e85a5bbc410f198; ?>
<?php unset($__attributesOriginal705afa55b28306e08e85a5bbc410f198); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal705afa55b28306e08e85a5bbc410f198)): ?>
<?php $component = $__componentOriginal705afa55b28306e08e85a5bbc410f198; ?>
<?php unset($__componentOriginal705afa55b28306e08e85a5bbc410f198); ?>
<?php endif; ?>
    </div>
</div>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\components\progress-report-details.blade.php ENDPATH**/ ?>