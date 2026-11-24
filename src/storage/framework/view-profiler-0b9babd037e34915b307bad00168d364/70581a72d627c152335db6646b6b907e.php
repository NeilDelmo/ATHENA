<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['topic', 'report', 'closable' => false]));

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

foreach (array_filter((['topic', 'report', 'closable' => false]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    $isCurrentVersion = $report->nextVersion === null;
    $canManageCurrentReport = Auth::user()->isUsingWorkspace('research_head')
        && ! $topic->isCompletedProject()
        && $report->isAvailableToResearchHead()
        && $report->review_status === 'pending'
        && $isCurrentVersion;
    $canCorrectReport = Auth::user()->isUsingWorkspace('faculty_researcher')
        && $topic->isAccessibleTo(Auth::user())
        && ! $topic->isCompletedProject()
        && $isCurrentVersion
        && $report->review_status === 'revision_requested';
?>

<div class="quarter-report-document" data-monitoring-report-detail data-live-report-key="<?php echo e(\App\Services\TopicLiveStatus::reportKey($report)); ?>" data-live-report-scope="<?php echo e(\App\Services\TopicLiveStatus::reportScope($topic, $report)); ?>">
    <?php if (isset($component)) { $__componentOriginale530538cef0ecc2ab9b248a7128167db = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginale530538cef0ecc2ab9b248a7128167db = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.quarter-report-header','data' => ['report' => $report,'title' => $report->quarter_label.' Monitoring Tool · '.$report->version_label,'isCurrentVersion' => $isCurrentVersion,'closable' => $closable]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('quarter-report-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['report' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($report),'title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($report->quarter_label.' Monitoring Tool · '.$report->version_label),'is-current-version' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($isCurrentVersion),'closable' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($closable)]); ?>
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
                <div class="flex items-baseline justify-between gap-3 text-base"><h4 class="font-medium text-slate-900 dark:text-white">Recorded project progress</h4><span class="font-semibold tabular-nums text-slate-900 dark:text-white"><?php echo e($report->progress_percentage); ?>%</span></div>
                <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700" role="progressbar" aria-label="<?php echo e($report->quarter_label); ?> reported progress" aria-valuenow="<?php echo e($report->progress_percentage); ?>" aria-valuemin="0" aria-valuemax="100"><div class="h-full rounded-full bg-red-700 dark:bg-red-400" style="width: <?php echo e($report->progress_percentage); ?>%"></div></div>
                <p class="mt-2 text-base leading-6 text-slate-500 dark:text-slate-400">As of <?php echo e($report->reporting_date->format('M j, Y')); ?> · Saved in this report</p>
            </section>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $report->work_plan ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $activity): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($activity['evidence'] ?? []) > 0): ?>
                    <section class="space-y-2 border-t border-slate-200 pt-4 dark:border-slate-700">
                        <h4 class="text-base font-semibold text-slate-800 dark:text-slate-100">Evidence · <?php echo e($activity['activity']); ?></h4>
                        <div class="flex flex-wrap gap-2">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $activity['evidence']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $file): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <a href="<?php echo e(route('project-progress.evidence', ['topic' => $topic, 'evidence' => $file['id']])); ?>" class="inline-flex min-h-10 items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-base font-medium text-slate-700 hover:border-red-300 hover:text-red-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"><?php echo e($file['name']); ?></a>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </div>
                    </section>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($report->attachment_path || $canCorrectReport): ?>
                <div class="flex flex-wrap gap-2 border-t border-gray-200 pt-4 dark:border-slate-700">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($report->attachment_path): ?><a href="<?php echo e(route('project-progress.download', $report)); ?>" class="inline-flex min-h-9 items-center justify-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-base font-bold text-gray-700 transition hover:border-red-200 hover:bg-red-50 hover:text-red-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">Download attachment</a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canCorrectReport): ?><a href="<?php echo e(route('project-progress.create', ['topic' => $topic, 'revise_monitoring_report' => $report->id])); ?>" class="inline-flex min-h-9 items-center justify-center rounded-lg bg-red-700 px-3 py-2 text-base font-bold text-white transition hover:bg-red-800">Correct <?php echo e($report->quarter_label); ?> submission</a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($report->research_head_remarks && ! $canManageCurrentReport): ?>
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
        </div>
        <?php if (isset($component)) { $__componentOriginal705afa55b28306e08e85a5bbc410f198 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal705afa55b28306e08e85a5bbc410f198 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.submitted-report-document','data' => ['name' => 'monitoring-paper-'.e($report->id).'','modalName' => 'monitoring-pdf-'.e($report->id).'','title' => 'Submitted monitoring tool paper','previewUrl' => route('project-progress.monitoring-tool.view', $report),'downloadUrl' => route('project-progress.monitoring-tool', $report),'viewerLabel' => $report->quarter_label.' monitoring tool paper']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('submitted-report-document'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'monitoring-paper-'.e($report->id).'','modal-name' => 'monitoring-pdf-'.e($report->id).'','title' => 'Submitted monitoring tool paper','preview-url' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('project-progress.monitoring-tool.view', $report)),'download-url' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('project-progress.monitoring-tool', $report)),'viewer-label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($report->quarter_label.' monitoring tool paper')]); ?>
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
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\components\monitoring-tool-report-details.blade.php ENDPATH**/ ?>