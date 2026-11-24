<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['report', 'canReview' => false, 'canSecretaryReview' => false, 'canRecordSignedCopy' => false]));

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

foreach (array_filter((['report', 'canReview' => false, 'canSecretaryReview' => false, 'canRecordSignedCopy' => false]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    $isTerminalReport = $report->report_type === 'terminal';
    $signedCopy = $report->signedCopy();
    $photos = collect($report->photos ?? []);
    $statusLabel = $report->review_status_label;
    $sections = ['introduction' => 'Introduction', 'rationale' => 'Rationale', 'objectives' => 'Objectives', 'methodology' => 'Methodology', 'results_discussion' => 'Results and Discussion'];
    $reportId = 'narrative-report-'.$report->id;
    $figureNumber = 1;
    $isDemo = str_starts_with($report->accomplishment_summary ?? '', 'UI DEMONSTRATION');
    $hasSecretaryReview = ($report->secretary_remarks || $canSecretaryReview || $errors->has('review'));
?>

<article id="<?php echo e($reportId); ?>" <?php echo e($attributes->class(['report-reader scroll-mt-36 rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900'])); ?> data-narrative-report-card data-live-report-key="<?php echo e(\App\Services\TopicLiveStatus::reportKey($report)); ?>" data-live-report-scope="<?php echo e(\App\Services\TopicLiveStatus::reportScope($report->topic, $report)); ?>">
    <header data-report-reader-heading class="flex flex-col gap-4 border-b border-slate-200 px-5 py-5 dark:border-slate-700 sm:px-8 lg:flex-row lg:items-start lg:justify-between">
        <div class="min-w-0">
            <h2 class="text-lg font-semibold text-slate-950 dark:text-white"><?php echo e($report->report_label); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($report->reporting_quarter): ?><span class="ml-2 font-normal text-slate-500 dark:text-slate-400">Q<?php echo e($report->reporting_quarter); ?></span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></h2>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($report->period_start && $report->period_end): ?><p class="mt-1 text-sm text-slate-600 dark:text-slate-300"><?php echo e($report->period_start->format('M j, Y')); ?> – <?php echo e($report->period_end->format('M j, Y')); ?> <span class="ml-2 text-slate-400">v<?php echo e($report->version_number); ?></span></p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <p class="mt-1 text-sm leading-6 text-slate-500 dark:text-slate-400"><?php echo e($report->submitter->name); ?> · <?php echo e($report->submission_date->format('M j, Y')); ?></p>
            <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm leading-6 text-slate-500 dark:text-slate-400" data-narrative-report-status>
                <p data-live-report-status="<?php echo e(\App\Services\TopicLiveStatus::reportKey($report)); ?>" data-live-report-style="plain" class="inline-flex items-center gap-2"><span data-live-report-indicator aria-hidden="true" class="h-1.5 w-1.5 rounded-full <?php echo e($report->review_status === 'reviewed' ? 'bg-emerald-500' : ($report->review_status === 'revision_requested' ? 'bg-red-500' : 'bg-amber-400')); ?>"></span><span data-live-report-label><?php echo e($statusLabel); ?></span></p>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isTerminalReport && ($signedCopy || $report->review_status === \App\Models\ProjectNarrativeReport::STATUS_REVIEWED)): ?>
                    <p><?php echo e($signedCopy ? 'Signed terminal PDF uploaded' : 'Signed terminal PDF needed'); ?></p>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>
        <div data-report-reader-actions class="flex shrink-0 flex-wrap items-center gap-2">
            <button type="button" @click="$dispatch('open-modal', 'narrative-pdf-<?php echo e($report->id); ?>')" aria-label="Preview PDF" title="Preview PDF" aria-haspopup="dialog" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800">
                <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.5-7.5 9.75-7.5 9.75 7.5 9.75 7.5-3.5 7.5-9.75 7.5S2.25 12 2.25 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                <span class="sr-only sm:not-sr-only">Preview PDF</span>
            </button>
            <a href="<?php echo e(route('project-narrative-reports.download', $report)); ?>" aria-label="Download <?php echo e(strtolower($report->report_label)); ?> PDF" title="Download PDF" class="monitoring-report-control">
                <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m-4-4 4 4 4-4M4 16v4h16v-4"/></svg>
                <span class="sr-only">Download PDF</span>
            </a>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($signedCopy): ?>
                <a href="<?php echo e(route('project-narrative-reports.signed-copy.download', $report)); ?>" aria-label="Download signed Terminal Report" class="inline-flex min-h-11 items-center rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-red-700 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800">Signed PDF</a>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    </header>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isDemo): ?>
        <p class="border-b border-slate-100 px-5 py-2 text-xs text-slate-500 dark:border-slate-800 dark:text-slate-400 sm:px-8">Sample report · Illustrative figures and findings.</p>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <div class="report-reader-layout">
        <nav data-report-reader-navigation aria-label="<?php echo e($report->report_label); ?> sections" class="report-reader-navigation">
            <a href="#<?php echo e($reportId); ?>-accomplishments">Accomplishments</a>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $sections; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $field => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(filled($report->$field) || $photos->where('section', $field)->isNotEmpty()): ?>
                    <a href="#<?php echo e($reportId); ?>-<?php echo e($field); ?>"><?php echo e($label); ?></a>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canReview || $report->research_head_remarks): ?><a href="#<?php echo e($reportId); ?>-review"><?php echo e($canReview ? 'Review' : 'Remarks'); ?></a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($hasSecretaryReview): ?><a href="#<?php echo e($reportId); ?>-secretary-review">Research Office Secretary review</a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </nav>
        <div class="min-w-0" data-narrative-report-content>
            <div class="px-5 py-5 sm:px-8">
                <dl class="grid gap-x-8 gap-y-3 text-sm sm:grid-cols-2">
                    <div><dt class="text-sm text-gray-500 dark:text-slate-400">Project duration</dt><dd class="mt-1 font-medium text-gray-900 dark:text-slate-100"><?php echo e($report->implementation_start?->format('M j, Y')); ?> – <?php echo e($report->implementation_end?->format('M j, Y')); ?></dd></div>
                    <div><dt class="text-sm text-gray-500 dark:text-slate-400">Funding agency</dt><dd class="mt-1 font-medium text-gray-900 dark:text-slate-100"><?php echo e($report->funding_agency); ?></dd></div>
                </dl>
            </div>
            <div class="report-reader-document divide-y divide-slate-100 px-5 dark:divide-slate-800 sm:px-8">
                <section id="<?php echo e($reportId); ?>-accomplishments" class="scroll-mt-36 space-y-5 py-7" aria-labelledby="<?php echo e($reportId); ?>-accomplishments-heading">
                    <h3 id="<?php echo e($reportId); ?>-accomplishments-heading" class="text-base font-semibold text-slate-950 dark:text-white">Accomplishments</h3>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($report->accomplishments): ?>
                        <table class="block w-full table-fixed text-left text-sm leading-7 sm:table" data-report-accomplishments>
                            <thead class="hidden border-b border-slate-200 text-slate-500 dark:border-slate-700 dark:text-slate-400 sm:table-header-group"><tr><th scope="col" class="w-1/4 pb-3 pr-4 font-medium">Objective</th><th scope="col" class="w-1/4 pb-3 pr-4 font-medium">Target</th><th scope="col" class="w-1/2 pb-3 font-medium">Accomplished</th></tr></thead>
                            <tbody class="block divide-y divide-slate-100 dark:divide-slate-800 sm:table-row-group">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $report->accomplishments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                    <tr class="block space-y-4 py-5 sm:table-row sm:space-y-0 sm:py-0">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = ['objective' => 'Objective', 'target' => 'Target accomplishment', 'actual' => 'Actual accomplishment']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                            <td class="block break-words align-top sm:table-cell sm:py-4 sm:pr-4"><span class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400 sm:hidden"><?php echo e($key === 'actual' ? 'Accomplished' : ($key === 'target' ? 'Target' : $label)); ?></span><span class="whitespace-pre-line text-slate-700 dark:text-slate-200"><?php echo e($row[$key] ?? ''); ?></span></td>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                    </tr>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="max-w-[75ch] whitespace-pre-line text-base leading-8 text-gray-700 dark:text-slate-200"><?php echo e($report->accomplishment_summary ?: 'See the submitted PDF for this report’s accomplishments.'); ?></p>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </section>
                <?php if (isset($component)) { $__componentOriginal674d833ab194c512dd1e01e249e3e7e2 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal674d833ab194c512dd1e01e249e3e7e2 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.progress-report-evidence','data' => ['report' => $report,'class' => 'py-7']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('progress-report-evidence'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['report' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($report),'class' => 'py-7']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal674d833ab194c512dd1e01e249e3e7e2)): ?>
<?php $attributes = $__attributesOriginal674d833ab194c512dd1e01e249e3e7e2; ?>
<?php unset($__attributesOriginal674d833ab194c512dd1e01e249e3e7e2); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal674d833ab194c512dd1e01e249e3e7e2)): ?>
<?php $component = $__componentOriginal674d833ab194c512dd1e01e249e3e7e2; ?>
<?php unset($__componentOriginal674d833ab194c512dd1e01e249e3e7e2); ?>
<?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $sections; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $field => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <?php
                        $paragraphs = preg_split('/\n\s*\n/u', app(\App\Support\ProgressReportData::class)->plain($report->$field ?? '')) ?: [''];
                        $sectionFigures = $photos->filter(fn ($photo) => ($photo['section'] ?? 'results_discussion') === $field);
                    ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(filled($report->$field) || $sectionFigures->isNotEmpty()): ?>
                        <section id="<?php echo e($reportId); ?>-<?php echo e($field); ?>" class="scroll-mt-36 space-y-5 py-7" aria-labelledby="<?php echo e($reportId); ?>-<?php echo e($field); ?>-heading" data-report-section="<?php echo e($field); ?>">
                            <h3 id="<?php echo e($reportId); ?>-<?php echo e($field); ?>-heading" class="text-base font-semibold text-slate-950 dark:text-white"><?php echo e($label); ?></h3>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $paragraphs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $paragraphIndex => $paragraph): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(filled($paragraph)): ?><p class="max-w-3xl whitespace-pre-line break-words text-base leading-8 text-gray-700 dark:text-slate-200"><?php echo e($paragraph); ?></p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $sectionFigures; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $photoIndex => $photo): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                    <?php
                                        $position = (int) ($photo['after_paragraph'] ?? 0);
                                        $position = $position <= 0 ? count($paragraphs) : min($position, count($paragraphs));
                                    ?>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($position === $paragraphIndex + 1): ?>
                                        <?php ($currentFigureNumber = $figureNumber++); ?>
                                        <?php if (isset($component)) { $__componentOriginal075c3e7af34dc2ef01a71436f37ea7a7 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal075c3e7af34dc2ef01a71436f37ea7a7 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.narrative-report-figure','data' => ['report' => $report,'photo' => $photo,'photoIndex' => $photoIndex,'figureNumber' => $currentFigureNumber]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('narrative-report-figure'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['report' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($report),'photo' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($photo),'photo-index' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($photoIndex),'figure-number' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($currentFigureNumber)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal075c3e7af34dc2ef01a71436f37ea7a7)): ?>
<?php $attributes = $__attributesOriginal075c3e7af34dc2ef01a71436f37ea7a7; ?>
<?php unset($__attributesOriginal075c3e7af34dc2ef01a71436f37ea7a7); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal075c3e7af34dc2ef01a71436f37ea7a7)): ?>
<?php $component = $__componentOriginal075c3e7af34dc2ef01a71436f37ea7a7; ?>
<?php unset($__componentOriginal075c3e7af34dc2ef01a71436f37ea7a7); ?>
<?php endif; ?>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </section>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($photos->where('section', 'cover')->isNotEmpty()): ?>
                    <section class="space-y-5 py-7" aria-label="Project illustration">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $photos->where('section', 'cover'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $photoIndex => $photo): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <?php ($currentFigureNumber = $figureNumber++); ?>
                                        <?php if (isset($component)) { $__componentOriginal075c3e7af34dc2ef01a71436f37ea7a7 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal075c3e7af34dc2ef01a71436f37ea7a7 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.narrative-report-figure','data' => ['report' => $report,'photo' => $photo,'photoIndex' => $photoIndex,'figureNumber' => $currentFigureNumber]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('narrative-report-figure'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['report' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($report),'photo' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($photo),'photo-index' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($photoIndex),'figure-number' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($currentFigureNumber)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal075c3e7af34dc2ef01a71436f37ea7a7)): ?>
<?php $attributes = $__attributesOriginal075c3e7af34dc2ef01a71436f37ea7a7; ?>
<?php unset($__attributesOriginal075c3e7af34dc2ef01a71436f37ea7a7); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal075c3e7af34dc2ef01a71436f37ea7a7)): ?>
<?php $component = $__componentOriginal075c3e7af34dc2ef01a71436f37ea7a7; ?>
<?php unset($__componentOriginal075c3e7af34dc2ef01a71436f37ea7a7); ?>
<?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </section>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($hasSecretaryReview): ?>
            <section id="<?php echo e($reportId); ?>-secretary-review" class="scroll-mt-36 border-t border-slate-100 px-5 py-7 dark:border-slate-800 sm:px-8" aria-label="Research Office Secretary review">
                <?php if (isset($component)) { $__componentOriginal7a3a3f1f6991c7f09c0281b748d8aafa = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal7a3a3f1f6991c7f09c0281b748d8aafa = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.implementation-report-secretary-review','data' => ['topic' => $report->topic,'report' => $report]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('implementation-report-secretary-review'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['topic' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($report->topic),'report' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($report)]); ?>
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
            </section>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canReview || $report->research_head_remarks): ?>
            <section id="<?php echo e($reportId); ?>-review" class="scroll-mt-36 border-t border-slate-100 px-5 py-7 dark:border-slate-800 sm:px-8" aria-label="Research Head review">
                <div class="space-y-5">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canReview): ?>
                        <?php if (isset($component)) { $__componentOriginal0d993c4296834f40caa3a752b34cb270 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal0d993c4296834f40caa3a752b34cb270 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.implementation-report-head-review','data' => ['topic' => $report->topic,'report' => $report]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('implementation-report-head-review'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['topic' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($report->topic),'report' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($report)]); ?>
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
                    <?php else: ?>
                        <h3 class="text-base font-semibold text-slate-950 dark:text-white"><?php echo e($report->review_stage === 'secretary' && $report->review_status === 'pending' ? 'Research Head concerns · Research Office Secretary to resolve' : 'Research Head remarks'); ?></h3>
                        <p class="whitespace-pre-line break-words text-base leading-7 text-gray-900 dark:text-slate-100"><?php echo e($report->research_head_remarks); ?></p>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </section>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canRecordSignedCopy): ?>
        <form method="POST" action="<?php echo e(route('research_head.narrative-progress-reports.signed-copy.store', $report)); ?>" enctype="multipart/form-data" class="space-y-4 border-t border-slate-100 px-5 py-7 dark:border-slate-800 sm:px-8">
            <?php echo csrf_field(); ?>
            <div class="space-y-1">
                <h5 class="text-lg font-semibold text-brand dark:text-red-200">Signed terminal report</h5>
                <p class="text-sm leading-6 text-gray-600 dark:text-slate-300">Upload the PDF with all required signatures.</p>
            </div>
            <div class="flex flex-col gap-3 lg:flex-row lg:items-end">
                <div class="min-w-0 flex-1 space-y-2">
                    <label for="narrative-signed-report-<?php echo e($report->id); ?>" class="block text-sm font-medium text-brand dark:text-red-200"><?php echo e($signedCopy ? 'Replace signed PDF' : 'Signed PDF'); ?></label>
                    <input id="narrative-signed-report-<?php echo e($report->id); ?>" name="signed_report" type="file" accept=".pdf,application/pdf" required class="block w-full rounded-lg border border-red-300 bg-white p-3 text-sm text-gray-700 file:mr-3 file:rounded-md file:border-0 file:bg-red-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-brand dark:border-red-800 dark:bg-slate-950 dark:text-slate-200 dark:file:bg-red-950 dark:file:text-red-200">
                </div>
                <button type="submit" class="inline-flex min-h-12 shrink-0 items-center justify-center rounded-lg bg-brand px-5 py-3 text-sm font-semibold text-white hover:bg-brand-soft focus:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-900"><?php echo e($signedCopy ? 'Replace signed PDF' : 'Record signed PDF'); ?></button>
            </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['signed_report'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="text-sm font-medium text-red-700 dark:text-red-300"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </form>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canRecordSignedCopy && $signedCopy): ?>
        <form method="POST" action="<?php echo e(route('research_head.projects.update-status', $report->topic)); ?>" x-data="projectStatusManager('completed')" @submit.prevent="submitStatus($event)" class="space-y-3 border-t border-slate-100 px-5 py-5 dark:border-slate-800 sm:px-8" data-terminal-project-completion>
            <?php echo csrf_field(); ?>
            <?php echo method_field('PATCH'); ?>
            <input type="hidden" name="project_status" value="completed">
            <input type="hidden" name="completion_confirmed" value="0">
            <p class="text-sm leading-6 text-slate-600 dark:text-slate-300">Close project monitoring. Publications and presentations remain available under Post-implementation.</p>
            <button type="submit" :disabled="submitting" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white hover:bg-brand-soft disabled:opacity-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand">Mark project completed</button>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['project_status'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p role="alert" class="text-sm text-red-700 dark:text-red-300"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['completion_confirmed'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p role="alert" class="text-sm text-red-700 dark:text-red-300"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </form>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    </div>
</article>

<?php if (isset($component)) { $__componentOriginal66cb43065a62ebef588707752044cfef = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal66cb43065a62ebef588707752044cfef = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.submitted-report-preview','data' => ['name' => 'narrative-pdf-'.e($report->id).'','title' => $report->report_label.' preview','description' => $report->reporting_period_label,'previewUrl' => route('project-narrative-reports.view', $report),'downloadUrl' => route('project-narrative-reports.download', $report),'dataNarrativePdfPreviewModal' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('submitted-report-preview'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'narrative-pdf-'.e($report->id).'','title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($report->report_label.' preview'),'description' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($report->reporting_period_label),'preview-url' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('project-narrative-reports.view', $report)),'download-url' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('project-narrative-reports.download', $report)),'data-narrative-pdf-preview-modal' => true]); ?>
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
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\components\narrative-report-history-item.blade.php ENDPATH**/ ?>