<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['topic', 'preparedReport' => null, 'narrativeReportDraft' => null, 'standalone' => false, 'progressDefaults' => [], 'terminalDefaults' => [], 'terminalEvidence' => [], 'quarterOptions' => [], 'selectedReportingDate' => null]));

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

foreach (array_filter((['topic', 'preparedReport' => null, 'narrativeReportDraft' => null, 'standalone' => false, 'progressDefaults' => [], 'terminalDefaults' => [], 'terminalEvidence' => [], 'quarterOptions' => [], 'selectedReportingDate' => null]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<?php
    $reportType = $preparedReport?->report_type ?? old('report_type', request('report_type', data_get($narrativeReportDraft?->source_data, 'report_type', 'progress')));
    $reportLabel = $reportType === 'terminal' ? 'Terminal report' : 'Progress report';
    $schedule = app(\App\Services\MonitoringQuarterService::class);
    $submissionOpen = $reportType === 'terminal' ? $schedule->canSubmitTerminal($topic) : ($selectedReportingDate && $schedule->canSubmitForDate($topic, $selectedReportingDate));
    $submissionOpensAt = $reportType === 'terminal' ? $schedule->terminalOpensAt($topic)->format('M j, Y') : ($selectedReportingDate ? $schedule->forDate($selectedReportingDate, $topic)['opens_at']->format('M j, Y') : '');
?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($preparedReport): ?>
    <section class="rounded-2xl border border-red-200 bg-red-50 p-5 dark:border-red-950 dark:bg-slate-950">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->narrativeProgress->has('preparation')): ?>
            <p class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-xs font-semibold text-red-700"><?php echo e($errors->narrativeProgress->first('preparation')); ?></p>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-sm font-black text-gray-950 dark:text-white"><?php echo e($reportLabel); ?> PDF prepared</p>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($preparedReport->reporting_period_label): ?><p class="mt-2 text-base font-semibold text-red-700 dark:text-red-300"><?php echo e($preparedReport->reporting_period_label); ?> · Version <?php echo e($preparedReport->version_number); ?></p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <p class="mt-1 max-w-2xl text-sm leading-6 text-gray-700 dark:text-slate-300">Review this exact stored PDF before sending it to the Research Head. To change its contents or figures, discard it and prepare a new file.</p>
                <p class="mt-2 text-[11px] font-semibold text-red-700 dark:text-red-300">Prepared <?php echo e($preparedReport->prepared_at?->format('M d, Y g:i A')); ?></p>
            </div>
            <?php if (isset($component)) { $__componentOriginalec45bcd7a9ee78c80c834ee90004f9d9 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalec45bcd7a9ee78c80c834ee90004f9d9 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.monitoring-action-dock','data' => ['fixed' => $standalone]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('monitoring-action-dock'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['fixed' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($standalone)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($standalone): ?>
                    <?php if (isset($component)) { $__componentOriginal5426bd0bea02df2e6dd2a60e50fa4c01 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal5426bd0bea02df2e6dd2a60e50fa4c01 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.back-link','data' => ['dataPaperCancelExit' => true,'href' => ''.e(route('research.show', $topic)).'#project-monitoring']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('back-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['data-paper-cancel-exit' => true,'href' => ''.e(route('research.show', $topic)).'#project-monitoring']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>
Exit monitoring <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal5426bd0bea02df2e6dd2a60e50fa4c01)): ?>
<?php $attributes = $__attributesOriginal5426bd0bea02df2e6dd2a60e50fa4c01; ?>
<?php unset($__attributesOriginal5426bd0bea02df2e6dd2a60e50fa4c01); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal5426bd0bea02df2e6dd2a60e50fa4c01)): ?>
<?php $component = $__componentOriginal5426bd0bea02df2e6dd2a60e50fa4c01; ?>
<?php unset($__componentOriginal5426bd0bea02df2e6dd2a60e50fa4c01); ?>
<?php endif; ?>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <a href="<?php echo e(route('project-narrative-reports.download', $preparedReport)); ?>" class="inline-flex min-h-12 items-center justify-center rounded-xl border border-gray-300 bg-white px-5 py-3 text-sm font-bold text-gray-900 shadow-sm transition hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-600 focus-visible:ring-offset-2 dark:border-slate-600 dark:bg-slate-900 dark:text-white dark:hover:bg-slate-800">Download prepared PDF</a>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(Auth::id() === $topic->user_id): ?>
                <form method="POST" action="<?php echo e(route('project-narrative-reports.submit-prepared', [$topic, $preparedReport])); ?>">
                    <?php echo csrf_field(); ?>
                    <button class="inline-flex min-h-12 items-center justify-center rounded-xl bg-red-700 px-5 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-red-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-600 focus-visible:ring-offset-2">Submit to Research Head</button>
                </form>
                <?php else: ?>
                    <p class="text-sm font-semibold text-red-700 dark:text-red-300">Only the project leader can submit this report.</p>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(in_array(Auth::id(), [$topic->user_id, $preparedReport->submitted_by], true)): ?>
                <form method="POST" action="<?php echo e(route('project-narrative-reports.discard-prepared', [$topic, $preparedReport])); ?>">
                    <?php echo csrf_field(); ?>
                    <?php echo method_field('DELETE'); ?>
                    <button class="inline-flex min-h-12 items-center justify-center rounded-xl border border-red-200 bg-white px-5 py-3 text-sm font-bold text-red-700 shadow-sm transition hover:bg-red-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-600 focus-visible:ring-offset-2 dark:border-red-900 dark:bg-slate-900 dark:text-red-300 dark:hover:bg-red-950">Discard</button>
                </form>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
             <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalec45bcd7a9ee78c80c834ee90004f9d9)): ?>
<?php $attributes = $__attributesOriginalec45bcd7a9ee78c80c834ee90004f9d9; ?>
<?php unset($__attributesOriginalec45bcd7a9ee78c80c834ee90004f9d9); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalec45bcd7a9ee78c80c834ee90004f9d9)): ?>
<?php $component = $__componentOriginalec45bcd7a9ee78c80c834ee90004f9d9; ?>
<?php unset($__componentOriginalec45bcd7a9ee78c80c834ee90004f9d9); ?>
<?php endif; ?>
        </div>
    </section>
<?php else: ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($reportType === 'terminal'): ?>
    <?php if (isset($component)) { $__componentOriginalb23793e5b8c67057da863dd8cc4d9ce9 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalb23793e5b8c67057da863dd8cc4d9ce9 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.terminal-report-form','data' => ['topic' => $topic,'draft' => $narrativeReportDraft,'defaults' => $terminalDefaults,'evidence' => $terminalEvidence,'standalone' => $standalone]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('terminal-report-form'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['topic' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($topic),'draft' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($narrativeReportDraft),'defaults' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($terminalDefaults),'evidence' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($terminalEvidence),'standalone' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($standalone)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalb23793e5b8c67057da863dd8cc4d9ce9)): ?>
<?php $attributes = $__attributesOriginalb23793e5b8c67057da863dd8cc4d9ce9; ?>
<?php unset($__attributesOriginalb23793e5b8c67057da863dd8cc4d9ce9); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalb23793e5b8c67057da863dd8cc4d9ce9)): ?>
<?php $component = $__componentOriginalb23793e5b8c67057da863dd8cc4d9ce9; ?>
<?php unset($__componentOriginalb23793e5b8c67057da863dd8cc4d9ce9); ?>
<?php endif; ?>
<?php else: ?>
<?php
    $proposalDraft = $topic->revisionDraft;
    $draftData = is_array($narrativeReportDraft?->source_data) ? $narrativeReportDraft->source_data : [];
    $researcherNames = collect([$proposalDraft?->project_leader ?: $topic->user->name])
        ->merge($proposalDraft?->members?->pluck('name') ?? collect())
        ->filter()
        ->unique()
        ->implode("\n");
    $approvedStart = $proposalDraft?->planned_start ?? $topic->notice_to_proceed_issued_at?->copy()->startOfDay();
    $approvedEnd = $proposalDraft?->planned_end ?? $approvedStart?->copy()->addMonths((int) $topic->estimated_duration_months);
    $draftData = array_replace($progressDefaults, $draftData);
    $approvedObjectives = (bool) ($progressDefaults['objectives_from_work_plan'] ?? false);
    $draftData = app(\App\Support\ProgressReportData::class)->normalize($topic, [...$draftData, 'accomplishments' => old('accomplishments', $draftData['accomplishments'] ?? [])]);
    $blankAccomplishment = ['objective' => '', 'target' => '', 'actual' => '', 'activities' => ''];
    $approvedActivities = collect($progressDefaults['accomplishments'] ?? [])->pluck('activities', 'objective');
    $accomplishmentRows = collect($draftData['accomplishments'] ?? [])
        ->map(function ($row) use ($blankAccomplishment, $approvedActivities) {
            $row = array_merge($blankAccomplishment, is_array($row) ? $row : []);
            $row['activities'] = $row['activities'] ?: $approvedActivities->get($row['objective'], '');

            return $row;
        })
        ->values()->all();
    if ($accomplishmentRows === []) {
        $accomplishmentRows = [$blankAccomplishment];
    }
    $figureRows = old('figures', $draftData['figures'] ?? []);
    if ($figureRows === []) {
        foreach (array_keys($draftData) as $key) {
            if (preg_match('/^photo_caption_(\d+)$/', $key, $matches) === 1) {
                $index = (int) $matches[1];
                $figureRows[] = ['caption' => $draftData[$key] ?? '', 'section' => $draftData['photo_section_'.$index] ?? 'results_discussion', 'after_paragraph' => $draftData['photo_after_paragraph_'.$index] ?? 0];
            }
        }
    }
    $figureRows = collect($figureRows)->map(fn ($row) => is_array($row) ? \Illuminate\Support\Arr::only($row, ['caption', 'section', 'after_paragraph']) : [])->values()->all();
    $defaultSubmissionDate = array_key_exists('submission_date', $draftData) ? $draftData['submission_date'] : now()->toDateString();
    $defaultTrackingNumber = array_key_exists('tracking_number', $draftData) ? $draftData['tracking_number'] : '';
    $defaultResearchers = array_key_exists('researchers', $draftData) ? $draftData['researchers'] : $researcherNames;
    $defaultImplementationStart = array_key_exists('implementation_start', $draftData) ? $draftData['implementation_start'] : $approvedStart?->toDateString();
    $defaultImplementationEnd = array_key_exists('implementation_end', $draftData) ? $draftData['implementation_end'] : $approvedEnd?->toDateString();
    $defaultFundingAgency = array_key_exists('funding_agency', $draftData) ? $draftData['funding_agency'] : 'Batangas State University';
    $defaultPreparedByDate = array_key_exists('prepared_by_date_signed', $draftData) ? $draftData['prepared_by_date_signed'] : '';
?>

<section
    class="overflow-hidden rounded-2xl border border-red-200 bg-red-50/50 dark:border-red-950 dark:bg-slate-950"
    data-narrative-progress-autosave="true"
    x-data="narrativeProgressReportForm({
        initialAccomplishments: <?php echo \Illuminate\Support\Js::from($accomplishmentRows)->toHtml() ?>,
        initialFigures: <?php echo \Illuminate\Support\Js::from($figureRows)->toHtml() ?>,
        previewUrl: <?php echo \Illuminate\Support\Js::from(route('project-narrative-reports.preview', $topic))->toHtml() ?>,
        draftSaveUrl: <?php echo \Illuminate\Support\Js::from(route('project-narrative-reports.draft', $topic))->toHtml() ?>,
        initialDraftVersion: <?php echo \Illuminate\Support\Js::from((int) ($narrativeReportDraft?->lock_version ?? 0))->toHtml() ?>,
        csrfToken: <?php echo \Illuminate\Support\Js::from(csrf_token())->toHtml() ?>,
        submissionOpen: <?php echo \Illuminate\Support\Js::from((bool) $submissionOpen)->toHtml() ?>,
        submissionOpensAt: <?php echo \Illuminate\Support\Js::from($submissionOpensAt)->toHtml() ?>,
    })"
>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $standalone): ?>
    <header class="flex items-center justify-between gap-3 px-5 py-4 text-lg font-bold text-gray-950 dark:text-white">
        <span>
            Submit <?php echo e(strtolower($reportLabel)); ?>

            <span class="mt-1 block text-xs font-normal text-red-700 dark:text-red-300">BatStateU-REC-RES-02 · Revision 02</span>
        </span>
    </header>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <form x-ref="form" data-narrative-progress-autosave-form method="POST" action="<?php echo e(route('project-narrative-reports.prepare', $topic)); ?>" enctype="multipart/form-data" class="space-y-6 border-t border-red-200 bg-white p-5 dark:border-red-950 dark:bg-slate-900 <?php echo e($standalone ? 'pb-44 sm:pb-32' : ''); ?>" @submit="if (!submissionOpen) { $event.preventDefault() } else { submitting = true }">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="draft_version" value="<?php echo e($narrativeReportDraft?->lock_version ?? 0); ?>">
        <input type="hidden" name="report_type" value="<?php echo e($reportType); ?>">
        <div class="rounded-xl border border-red-200 bg-white p-4 dark:border-red-900 dark:bg-slate-900">
            <label for="progress-reporting-date" class="block text-base font-semibold text-gray-950 dark:text-white">Reporting quarter</label>
            <p class="mt-1 text-sm leading-6 text-gray-600 dark:text-slate-300">Use the same three-month period as the Monitoring Tool. Submission opens after the period ends; the final period may be shorter.</p>
            <select id="progress-reporting-date" @change="submissionOpen = $event.target.selectedOptions[0]?.dataset.submissionOpen === 'true'; submissionOpensAt = $event.target.selectedOptions[0]?.dataset.opensAt || ''" name="reporting_date" required class="mt-3 block min-h-11 w-full rounded-lg border-gray-300 text-base focus:border-red-700 focus:ring-red-700 dark:border-slate-600 dark:bg-slate-950 dark:text-white">
                <option value="">Choose a quarter</option>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $quarterOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $period): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <option data-submission-open="<?php echo e($schedule->canSubmitForDate($topic, $period['reporting_date']) ? 'true' : 'false'); ?>" data-opens-at="<?php echo e($period['opens_at']->format('M j, Y')); ?>" value="<?php echo e($period['reporting_date']); ?>" <?php if(old('reporting_date', $selectedReportingDate) === $period['reporting_date']): echo 'selected'; endif; ?>><?php echo e($period['label']); ?> · <?php echo e($period['period']); ?><?php echo e($period['report']?->review_status === 'revision_requested' ? ' · Corrections requested' : ''); ?></option>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </select>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['reporting_date', 'narrativeProgress'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="mt-2 text-sm text-red-700 dark:text-red-300"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->narrativeProgress->any()): ?>
            <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-xs text-red-700">
                <p class="font-black">Please correct the highlighted progress-report fields.</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $errors->narrativeProgress->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <li><?php echo e($error); ?></li>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </ul>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <?php if (isset($component)) { $__componentOriginale42ee907cd5bebce7126d7c9b69e1d93 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginale42ee907cd5bebce7126d7c9b69e1d93 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-autosave-status','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-autosave-status'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginale42ee907cd5bebce7126d7c9b69e1d93)): ?>
<?php $attributes = $__attributesOriginale42ee907cd5bebce7126d7c9b69e1d93; ?>
<?php unset($__attributesOriginale42ee907cd5bebce7126d7c9b69e1d93); ?>
<?php endif; ?>
<?php if (isset($__componentOriginale42ee907cd5bebce7126d7c9b69e1d93)): ?>
<?php $component = $__componentOriginale42ee907cd5bebce7126d7c9b69e1d93; ?>
<?php unset($__componentOriginale42ee907cd5bebce7126d7c9b69e1d93); ?>
<?php endif; ?>

        <p class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm leading-6 text-gray-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">Changes save privately as a draft. Review the reused proposal content and write this period’s accomplishments and results. Select figure files before preparing the PDF; uploads are not kept in text drafts.</p>
        <p data-report-submission-lock x-show="!submissionOpen" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200">Fill and save this draft now. PDF preparation and submission open <span x-text="submissionOpensAt"><?php echo e($submissionOpensAt); ?></span>.</p>

        <div class="grid gap-3 rounded-xl bg-gray-50 p-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="sm:col-span-2">
                <p class="text-sm font-medium text-gray-400">Research project title</p>
                <p class="mt-1 text-base font-bold text-gray-800"><?php echo e($topic->title); ?></p>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-400">Project leader</p>
                <p class="mt-1 text-base font-bold text-gray-800"><?php echo e($topic->user->name); ?></p>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-400">Approved budget</p>
                <p class="mt-1 text-base font-bold text-gray-800">₱<?php echo e(number_format((float) $topic->estimated_budget, 2)); ?></p>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="progress_submission_date" class="text-sm font-semibold text-gray-600">Submission date</label>
                <?php if (isset($component)) { $__componentOriginal37e12294b28f0bd91a733acab9bb06c5 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal37e12294b28f0bd91a733acab9bb06c5 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.date-picker','data' => ['id' => 'progress_submission_date','name' => 'submission_date','value' => old('submission_date', $defaultSubmissionDate),'max' => now()->toDateString(),'required' => true,'class' => 'mt-1']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('date-picker'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['id' => 'progress_submission_date','name' => 'submission_date','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(old('submission_date', $defaultSubmissionDate)),'max' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(now()->toDateString()),'required' => true,'class' => 'mt-1']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal37e12294b28f0bd91a733acab9bb06c5)): ?>
<?php $attributes = $__attributesOriginal37e12294b28f0bd91a733acab9bb06c5; ?>
<?php unset($__attributesOriginal37e12294b28f0bd91a733acab9bb06c5); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal37e12294b28f0bd91a733acab9bb06c5)): ?>
<?php $component = $__componentOriginal37e12294b28f0bd91a733acab9bb06c5; ?>
<?php unset($__componentOriginal37e12294b28f0bd91a733acab9bb06c5); ?>
<?php endif; ?>
            </div>
            <label class="text-sm font-semibold text-gray-600">Tracking number <span class="font-normal text-gray-400">(optional)</span>
                <input type="text" name="tracking_number" value="<?php echo e(old('tracking_number', $defaultTrackingNumber)); ?>" maxlength="100" class="mt-1 block w-full rounded-xl border-gray-200 text-base" placeholder="Enter the official tracking number">
            </label>
        </div>

        <section class="grid gap-4 md:grid-cols-2">
            <label class="text-sm font-semibold text-gray-600 md:col-span-2">II. Researchers
                <textarea name="researchers" rows="3" maxlength="1000" required class="mt-1 block w-full rounded-xl border-gray-200 text-base" placeholder="Enter one researcher per line"><?php echo e(old('researchers', $defaultResearchers)); ?></textarea>
            </label>
            <div>
                <label for="implementation_start" class="text-sm font-semibold text-gray-600">III. Approved implementation start</label>
                <?php if (isset($component)) { $__componentOriginal37e12294b28f0bd91a733acab9bb06c5 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal37e12294b28f0bd91a733acab9bb06c5 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.date-picker','data' => ['id' => 'implementation_start','name' => 'implementation_start','value' => old('implementation_start', $defaultImplementationStart),'required' => true,'class' => 'mt-1']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('date-picker'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['id' => 'implementation_start','name' => 'implementation_start','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(old('implementation_start', $defaultImplementationStart)),'required' => true,'class' => 'mt-1']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal37e12294b28f0bd91a733acab9bb06c5)): ?>
<?php $attributes = $__attributesOriginal37e12294b28f0bd91a733acab9bb06c5; ?>
<?php unset($__attributesOriginal37e12294b28f0bd91a733acab9bb06c5); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal37e12294b28f0bd91a733acab9bb06c5)): ?>
<?php $component = $__componentOriginal37e12294b28f0bd91a733acab9bb06c5; ?>
<?php unset($__componentOriginal37e12294b28f0bd91a733acab9bb06c5); ?>
<?php endif; ?>
            </div>
            <div>
                <label for="implementation_end" class="text-sm font-semibold text-gray-600">III. Approved implementation end</label>
                <?php if (isset($component)) { $__componentOriginal37e12294b28f0bd91a733acab9bb06c5 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal37e12294b28f0bd91a733acab9bb06c5 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.date-picker','data' => ['id' => 'implementation_end','name' => 'implementation_end','value' => old('implementation_end', $defaultImplementationEnd),'required' => true,'class' => 'mt-1']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('date-picker'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['id' => 'implementation_end','name' => 'implementation_end','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(old('implementation_end', $defaultImplementationEnd)),'required' => true,'class' => 'mt-1']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal37e12294b28f0bd91a733acab9bb06c5)): ?>
<?php $attributes = $__attributesOriginal37e12294b28f0bd91a733acab9bb06c5; ?>
<?php unset($__attributesOriginal37e12294b28f0bd91a733acab9bb06c5); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal37e12294b28f0bd91a733acab9bb06c5)): ?>
<?php $component = $__componentOriginal37e12294b28f0bd91a733acab9bb06c5; ?>
<?php unset($__componentOriginal37e12294b28f0bd91a733acab9bb06c5); ?>
<?php endif; ?>
            </div>
            <label class="text-sm font-semibold text-gray-600 md:col-span-2">V. Funding agency
                <input type="text" name="funding_agency" value="<?php echo e(old('funding_agency', $defaultFundingAgency)); ?>" maxlength="255" required class="mt-1 block w-full rounded-xl border-gray-200 text-base">
            </label>
        </section>

        <section class="space-y-4">
            <div>
                <p class="text-lg font-bold text-gray-900">VI. Summary of Accomplishment for the Monitoring Period</p>
                <p class="mt-1 text-sm text-gray-600">Compare the planned outputs with what was completed during this monitoring period.</p>
            </div>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($progressDefaults['objectives_from_work_plan'] ?? false): ?>
                <p class="rounded-lg bg-red-50 px-4 py-3 text-sm leading-6 text-red-900 dark:bg-red-950/40 dark:text-red-100">Objectives and target outputs come from the approved work plan. Update the actual accomplishments for this period, including any work not yet started.</p>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <div class="space-y-4">
                <template x-for="(row, index) in accomplishmentRows" :key="row.id">
                    <div class="space-y-3 rounded-xl border border-red-200 p-4 dark:border-red-900">
                        <div class="flex items-center justify-between gap-3">
                            <h4 class="text-base font-semibold text-brand dark:text-red-200" x-text="'Objective ' + (index + 1)"></h4>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($approvedObjectives)): ?>
                                <button type="button" @click="removeAccomplishment(index)" :disabled="accomplishmentRows.length === 1" class="rounded-lg px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-50 disabled:opacity-40 dark:text-red-300">Remove row</button>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                        <p x-show="row.activities" x-text="'Planned activities: ' + row.activities" class="whitespace-pre-line text-sm leading-6 text-gray-600 dark:text-slate-300"></p>
                        <div class="grid gap-4 lg:grid-cols-3">
                            <label class="text-sm font-semibold text-gray-700 dark:text-slate-200">Approved objective
                                <textarea :name="'accomplishments[' + index + '][objective]'" x-model="row.objective" <?php if($approvedObjectives): echo 'readonly'; endif; ?> rows="4" maxlength="1000" required class="mt-2 block w-full rounded-xl border-gray-200 text-base leading-7 dark:border-slate-700 dark:bg-slate-950 dark:text-white"></textarea>
                            </label>
                            <label class="text-sm font-semibold text-gray-700 dark:text-slate-200">Target accomplishment
                                <textarea :name="'accomplishments[' + index + '][target]'" x-model="row.target" <?php if($approvedObjectives): echo 'readonly'; endif; ?> rows="4" maxlength="2000" required class="mt-2 block w-full rounded-xl border-gray-200 text-base leading-7 dark:border-slate-700 dark:bg-slate-950 dark:text-white" placeholder="Expected outputs from the approved work plan"></textarea>
                            </label>
                            <label class="text-sm font-semibold text-gray-700 dark:text-slate-200">Actual accomplishment
                                <textarea :name="'accomplishments[' + index + '][actual]'" x-model="row.actual" rows="4" maxlength="2000" required class="mt-2 block w-full rounded-xl border-gray-200 text-base leading-7 dark:border-slate-700 dark:bg-slate-950 dark:text-white" placeholder="What was completed during this period? Include partial progress or work not yet started."></textarea>
                            </label>
                        </div>
                    </div>
                </template>
            </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($approvedObjectives)): ?>
                <button type="button" @click="addAccomplishment" class="inline-flex min-h-11 items-center rounded-lg border border-red-200 px-4 py-2 text-sm font-semibold text-brand hover:bg-red-50 dark:border-red-900 dark:text-red-200">Add accomplishment row</button>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </section>

        <section class="space-y-4">
            <div>
                <p class="text-lg font-bold text-gray-900">Narrative sections</p>
                <p class="mt-1 text-sm text-gray-600">Introduction, rationale, objectives, and methodology start from the approved detailed proposal. Review the methods used this period and write new results. This progress-report format has no separate RRL section.</p>
            </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = [
                'introduction' => 'VII. Introduction',
                'rationale' => 'VIII. Rationale',
                'objectives' => 'VIII. Objectives',
                'methodology' => 'IX. Methodology',
                'results_discussion' => 'X. Results and Discussion',
            ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $field => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <label class="block text-sm font-semibold text-gray-600"><?php echo e($label); ?>

                    <textarea name="<?php echo e($field); ?>" <?php if($field === 'objectives' && filled($progressDefaults['objectives'])): echo 'readonly'; endif; ?> rows="4" maxlength="<?php echo e(config('detailed_proposal.maximum_narrative_length')); ?>" required class="mt-2 block w-full rounded-xl border-gray-200 text-base leading-7 dark:border-slate-700 dark:bg-slate-950 dark:text-white"><?php echo e($field === 'objectives' && filled($progressDefaults['objectives']) ? $progressDefaults['objectives'] : old($field, $draftData[$field] ?? '')); ?></textarea>
                </label>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </section>

        <section class="space-y-4 rounded-xl border border-red-200 bg-red-50/40 p-4 dark:border-red-900 dark:bg-red-950/20" data-progress-report-figures>
            <div class="space-y-2">
                <h3 class="text-xl font-bold text-brand dark:text-red-200">Figures</h3>
                <p class="text-base leading-7 text-gray-600 dark:text-slate-300">Add diagrams, charts, screenshots, or research photos that support your methods and results. Use a caption for each figure. JPG or PNG, up to 10 MB per file.</p>
                <p class="text-sm text-gray-600 dark:text-slate-300">Add as many figures as the report needs. Figures are optional; files must fit the server’s upload limits.</p>
            </div>
            <p x-show="figureRows.length === 0" class="text-base text-gray-600 dark:text-slate-300">No figures added.</p>
            <template x-for="(figure, index) in figureRows" :key="figure.id">
                <div class="space-y-4 rounded-xl border border-red-200 bg-white p-4 dark:border-red-900 dark:bg-slate-900">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h4 class="text-base font-bold text-brand dark:text-red-200" x-text="'Figure entry ' + (index + 1)"></h4>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" @click="moveFigure(index, -1)" :disabled="index === 0" aria-label="Move figure earlier" class="rounded-lg border border-gray-200 px-3 py-2 text-sm font-semibold disabled:opacity-40 dark:border-slate-700 dark:text-white">Move up</button>
                            <button type="button" @click="moveFigure(index, 1)" :disabled="index === figureRows.length - 1" aria-label="Move figure later" class="rounded-lg border border-gray-200 px-3 py-2 text-sm font-semibold disabled:opacity-40 dark:border-slate-700 dark:text-white">Move down</button>
                            <button type="button" @click="removeFigure(index)" class="rounded-lg px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-50 dark:text-red-300">Remove</button>
                        </div>
                    </div>
                    <img x-show="figure.previewUrl" :src="figure.previewUrl" :alt="figure.caption || 'Selected figure preview'" class="max-h-64 max-w-full rounded-lg object-contain">
                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="text-sm font-semibold text-gray-700 dark:text-slate-200">Figure image
                            <input type="file" :name="'figures[' + index + '][image]'" accept=".jpg,.jpeg,.png" @change="selectFigureFile(figure, $event)" class="mt-2 block w-full rounded-xl border border-gray-200 p-3 text-sm dark:border-slate-700">
                        </label>
                        <label class="text-sm font-semibold text-gray-700 dark:text-slate-200">Caption
                            <input type="text" :name="'figures[' + index + '][caption]'" x-model="figure.caption" :required="!!figure.previewUrl" maxlength="1000" class="mt-2 block w-full rounded-xl border-gray-200 text-base dark:border-slate-700 dark:bg-slate-950 dark:text-white" placeholder="Describe what this figure demonstrates">
                        </label>
                        <label class="text-sm font-semibold text-gray-700 dark:text-slate-200">Place in section
                            <select :name="'figures[' + index + '][section]'" x-model="figure.section" class="mt-2 block w-full rounded-xl border-gray-200 text-base dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                                <option value="methodology">Methodology</option>
                                <option value="results_discussion">Results and Discussion</option>
                            </select>
                        </label>
                        <label class="text-sm font-semibold text-gray-700 dark:text-slate-200">Insert after paragraph
                            <input type="number" :name="'figures[' + index + '][after_paragraph]'" x-model="figure.after_paragraph" min="0" max="100000" class="mt-2 block w-full rounded-xl border-gray-200 text-base dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                            <span class="mt-2 block text-sm font-normal text-gray-600 dark:text-slate-300">Use 0 for the end of the section. Separate paragraphs with a blank line. Figures are numbered in document order.</span>
                        </label>
                    </div>
                </div>
            </template>
            <button type="button" @click="addFigure" class="inline-flex min-h-11 items-center rounded-lg bg-brand px-5 py-2.5 text-base font-semibold text-white hover:bg-brand-soft focus:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2">Add figure</button>
        </section>

        <div class="rounded-xl bg-gray-50 p-4">
            <div>
                <label for="progress_prepared_by_date_signed" class="text-sm font-semibold text-gray-600">Prepared-by date signed <span class="font-normal text-gray-400">(optional)</span></label>
                <?php if (isset($component)) { $__componentOriginal37e12294b28f0bd91a733acab9bb06c5 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal37e12294b28f0bd91a733acab9bb06c5 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.date-picker','data' => ['id' => 'progress_prepared_by_date_signed','name' => 'prepared_by_date_signed','value' => old('prepared_by_date_signed', $defaultPreparedByDate),'max' => now()->toDateString(),'class' => 'mt-1']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('date-picker'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['id' => 'progress_prepared_by_date_signed','name' => 'prepared_by_date_signed','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(old('prepared_by_date_signed', $defaultPreparedByDate)),'max' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(now()->toDateString()),'class' => 'mt-1']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal37e12294b28f0bd91a733acab9bb06c5)): ?>
<?php $attributes = $__attributesOriginal37e12294b28f0bd91a733acab9bb06c5; ?>
<?php unset($__attributesOriginal37e12294b28f0bd91a733acab9bb06c5); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal37e12294b28f0bd91a733acab9bb06c5)): ?>
<?php $component = $__componentOriginal37e12294b28f0bd91a733acab9bb06c5; ?>
<?php unset($__componentOriginal37e12294b28f0bd91a733acab9bb06c5); ?>
<?php endif; ?>
            </div>
        </div>

        <?php if (isset($component)) { $__componentOriginalec45bcd7a9ee78c80c834ee90004f9d9 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalec45bcd7a9ee78c80c834ee90004f9d9 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.monitoring-action-dock','data' => ['fixed' => $standalone]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('monitoring-action-dock'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['fixed' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($standalone)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($standalone): ?>
                <?php if (isset($component)) { $__componentOriginal5426bd0bea02df2e6dd2a60e50fa4c01 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal5426bd0bea02df2e6dd2a60e50fa4c01 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.back-link','data' => ['dataPaperCancelExit' => true,'href' => ''.e(route('research.show', $topic)).'#project-monitoring']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('back-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['data-paper-cancel-exit' => true,'href' => ''.e(route('research.show', $topic)).'#project-monitoring']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>
Exit monitoring <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal5426bd0bea02df2e6dd2a60e50fa4c01)): ?>
<?php $attributes = $__attributesOriginal5426bd0bea02df2e6dd2a60e50fa4c01; ?>
<?php unset($__attributesOriginal5426bd0bea02df2e6dd2a60e50fa4c01); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal5426bd0bea02df2e6dd2a60e50fa4c01)): ?>
<?php $component = $__componentOriginal5426bd0bea02df2e6dd2a60e50fa4c01; ?>
<?php unset($__componentOriginal5426bd0bea02df2e6dd2a60e50fa4c01); ?>
<?php endif; ?>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <button type="button" @click="saveNarrativeDraft" :disabled="autoSaveInFlight || autoSaveBlocked" class="min-h-12 rounded-xl border border-gray-300 bg-white px-5 py-3 text-sm font-bold text-gray-900 disabled:opacity-50 dark:border-slate-600 dark:bg-slate-900 dark:text-white">Save draft</button>
            <button type="button" @click="generatePreview" :disabled="!submissionOpen || previewLoading || submitting" class="min-h-12 rounded-xl border border-gray-300 bg-white px-5 py-3 text-sm font-bold text-gray-900 shadow-sm transition hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-600 focus-visible:ring-offset-2 disabled:cursor-wait disabled:opacity-60 dark:border-slate-600 dark:bg-slate-900 dark:text-white dark:hover:bg-slate-800">
                <span x-show="!previewLoading">Preview <?php echo e(strtolower($reportLabel)); ?></span>
                <span x-show="previewLoading" x-cloak>Generating preview...</span>
            </button>
            <button type="submit" :disabled="!submissionOpen || submitting || previewLoading" class="min-h-12 rounded-xl bg-red-700 px-5 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-red-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-600 focus-visible:ring-offset-2 disabled:cursor-wait disabled:opacity-60">
                <span x-show="!submitting">Prepare official PDF</span>
                <span x-show="submitting" x-cloak>Preparing PDF…</span>
            </button>
         <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalec45bcd7a9ee78c80c834ee90004f9d9)): ?>
<?php $attributes = $__attributesOriginalec45bcd7a9ee78c80c834ee90004f9d9; ?>
<?php unset($__attributesOriginalec45bcd7a9ee78c80c834ee90004f9d9); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalec45bcd7a9ee78c80c834ee90004f9d9)): ?>
<?php $component = $__componentOriginalec45bcd7a9ee78c80c834ee90004f9d9; ?>
<?php unset($__componentOriginalec45bcd7a9ee78c80c834ee90004f9d9); ?>
<?php endif; ?>

        <p x-show="previewError" x-cloak x-text="previewError" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-xs font-semibold text-red-700"></p>

        <section x-show="previewHtml" x-cloak x-ref="previewSection" class="space-y-3 rounded-2xl border border-gray-200 bg-gray-100 p-3 sm:p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-lg font-bold text-gray-900"><?php echo e($reportLabel); ?> preview</p>
                    <p class="text-sm text-gray-600">This preview is generated from the current form values and has not been submitted.</p>
                </div>
                <button type="button" @click="printPreview" :disabled="!previewReady" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-bold text-gray-700 shadow-sm disabled:opacity-50">Print preview</button>
            </div>
            <iframe x-ref="previewFrame" :srcdoc="previewHtml" @load="hydratePreview" title="Progress report document preview" class="h-[75vh] w-full rounded-xl border border-gray-300 bg-white shadow-inner"></iframe>
        </section>
    </form>
</section>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views/components/progress-report-form.blade.php ENDPATH**/ ?>