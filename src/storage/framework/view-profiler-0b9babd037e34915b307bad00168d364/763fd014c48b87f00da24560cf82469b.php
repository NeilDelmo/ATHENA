<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'topic',
    'preparedReport' => null,
    'revisionReport' => null,
    'monitoringDraft' => null,
    'draftRecoveryState' => null,
    'standalone' => false,
    'quarterOptions' => [],
    'selectedReportingDate' => null,
    'approvedWorkPlanByPeriod' => [],
    'approvedWorkPlanAvailable' => false,
    'selectedPeriodKey' => null,
    'selectedReportNumber' => null,
    'monitoringReportCount' => null,
    'initialWorkPlanRows' => [],
    'previousProgressByPeriod' => [],
]));

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

foreach (array_filter(([
    'topic',
    'preparedReport' => null,
    'revisionReport' => null,
    'monitoringDraft' => null,
    'draftRecoveryState' => null,
    'standalone' => false,
    'quarterOptions' => [],
    'selectedReportingDate' => null,
    'approvedWorkPlanByPeriod' => [],
    'approvedWorkPlanAvailable' => false,
    'selectedPeriodKey' => null,
    'selectedReportNumber' => null,
    'monitoringReportCount' => null,
    'initialWorkPlanRows' => [],
    'previousProgressByPeriod' => [],
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<?php
    $restoreMonitoringInput = session()->hasOldInput('work_plan');
    $old = fn ($key, $fallback = null) => $restoreMonitoringInput ? old($key, $fallback) : $fallback;
    $draftVersion = (int) $old('draft_version', $monitoringDraft?->lock_version ?? 0);
    $draftId = (int) $old('draft_id', $monitoringDraft?->id ?? 0);
    $schedule = app(\App\Services\MonitoringQuarterService::class);
    $submissionOpen = $selectedReportingDate && $schedule->canSubmitForDate($topic, $selectedReportingDate);
    $submissionOpensAt = $selectedReportingDate ? $schedule->forDate($selectedReportingDate, $topic)['opens_at']->format('M j, Y') : '';
?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($preparedReport): ?>
    <section class="rounded-2xl border border-red-200 bg-red-50 p-5 dark:border-red-950 dark:bg-slate-950">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->has('preparation')): ?>
            <p class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-xs font-semibold text-red-700"><?php echo e($errors->first('preparation')); ?></p>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-sm font-black text-gray-950 dark:text-white"><?php echo e($preparedReport->quarter_label); ?> <?php echo e($preparedReport->version_label); ?> Monitoring Tool PDF prepared</p>
                <p class="mt-1 max-w-2xl text-xs leading-5 text-gray-700 dark:text-slate-300">Review this exact stored PDF before sending it to the Research Office Secretary. The secretary checks it and handles corrections before forwarding it to the Research Head. To change its contents, discard it and prepare a new file.</p>
                <p class="mt-2 text-[11px] font-semibold text-red-700 dark:text-red-300">Prepared <?php echo e($preparedReport->prepared_at?->format('M d, Y g:i A')); ?></p>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($topic->research_secretary_id): ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($preparedReport->hasPreparedBudget()): ?>
                        <p class="mt-3 inline-flex items-center rounded-full bg-emerald-100 px-3 py-1 text-[11px] font-black text-emerald-800">Budget confirmed by <?php echo e($preparedReport->budgetPreparer?->name); ?></p>
                    <?php else: ?>
                        <p class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-800"><?php echo e($topic->researchSecretary?->name ?? 'The assigned project secretary'); ?> has priority for budget utilization, but any authorized project member may complete it.</p>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
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
                <a href="<?php echo e(route('project-progress.monitoring-tool', $preparedReport)); ?>" class="inline-flex min-h-12 items-center justify-center rounded-xl border border-gray-300 bg-white px-5 py-3 text-sm font-bold text-gray-900 shadow-sm transition hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-700 focus-visible:ring-offset-2 dark:border-slate-600 dark:bg-slate-900 dark:text-white dark:hover:bg-slate-800">Download prepared PDF</a>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($topic->research_secretary_id && ! $preparedReport->hasPreparedBudget()): ?>
                    <a href="<?php echo e(route('project-budget.edit', [$topic, $preparedReport])); ?>" class="inline-flex min-h-12 items-center justify-center rounded-xl border border-amber-300 bg-amber-50 px-5 py-3 text-sm font-bold text-amber-900 shadow-sm transition hover:bg-amber-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-700 focus-visible:ring-offset-2">Complete budget utilization</a>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(Auth::id() === $topic->user_id): ?>
                <form method="POST" action="<?php echo e(route('project-progress.submit-prepared', [$topic, $preparedReport])); ?>">
                    <?php echo csrf_field(); ?>
                    <button <?php if($topic->research_secretary_id && ! $preparedReport->hasPreparedBudget()): echo 'disabled'; endif; ?> class="inline-flex min-h-12 items-center justify-center rounded-xl bg-red-700 px-5 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-red-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-700 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-45">Submit to Research Office Secretary</button>
                </form>
                <?php else: ?>
                    <p class="text-sm font-semibold text-red-700 dark:text-red-300">Only the project leader can submit this report.</p>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(in_array(Auth::id(), [$topic->user_id, $preparedReport->submitted_by], true)): ?>
                <form method="POST" action="<?php echo e(route('project-progress.discard-prepared', [$topic, $preparedReport])); ?>" onsubmit="return confirm('Discard this prepared PDF? You will need to prepare it again.')">
                    <?php echo csrf_field(); ?>
                    <?php echo method_field('DELETE'); ?>
                    <button class="inline-flex min-h-12 items-center justify-center rounded-xl border border-red-200 bg-white px-5 py-3 text-sm font-bold text-red-700 shadow-sm transition hover:bg-red-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-700 focus-visible:ring-offset-2 dark:border-red-900 dark:bg-slate-900 dark:text-red-300 dark:hover:bg-red-950">Discard</button>
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

<?php
    $draftData = is_array($monitoringDraft?->source_data) ? $monitoringDraft->source_data : [];
    $defaultWorkPlan = $initialWorkPlanRows !== [] ? $initialWorkPlanRows : [[
        'activity' => '',
        'percent_weight' => '',
        'physical_target' => '',
        'target_completion_date' => '',
        'actual_accomplishment' => '',
        'accomplished_percentage' => '',
        'findings' => '',
    ]];
    $defaultBudget = $draftData['budget_utilization'] ?? $revisionReport?->budget_utilization ?? collect(['Purchase Request', 'Cash Advance', 'Request of Payment'])
        ->map(fn ($type) => [
            'type' => $type,
            'details' => '',
            'amount_requested' => '0',
            'actual_amount' => '0',
            'remarks' => '',
        ])->all();
    $workPlanRows = $old('work_plan', $defaultWorkPlan);
    $workPlanRows = is_array($workPlanRows) && $workPlanRows !== [] ? array_values(array_filter($workPlanRows, 'is_array')) : $defaultWorkPlan;
    $workPlanRows = $workPlanRows !== [] ? $workPlanRows : $defaultWorkPlan;
    $budgetRows = $old('budget_utilization', $defaultBudget);
    $budgetRows = is_array($budgetRows) ? $budgetRows : $defaultBudget;
    $defaultReportingDate = array_key_exists('reporting_date', $draftData)
        ? $draftData['reporting_date']
        : $revisionReport?->reporting_date?->toDateString() ?? $selectedReportingDate ?? now()->toDateString();
    $defaultTrackingNumber = array_key_exists('tracking_number', $draftData)
        ? $draftData['tracking_number']
        : $revisionReport?->tracking_number;
    $defaultPreparedByDate = array_key_exists('prepared_by_date_signed', $draftData)
        ? $draftData['prepared_by_date_signed']
        : $revisionReport?->prepared_by_date_signed?->toDateString();
?>

<section
    class="rounded-xl bg-white dark:bg-slate-900"
    data-proposal-paper-workspace
    data-monitoring-paper-workspace
    data-monitoring-tool-autosave="true"
    x-data="monitoringToolForm({
        entries: <?php echo \Illuminate\Support\Js::from($workPlanRows)->toHtml() ?>,
        previousProgressByPeriod: <?php echo \Illuminate\Support\Js::from($previousProgressByPeriod)->toHtml() ?>,
        evidenceUrlTemplate: <?php echo \Illuminate\Support\Js::from(route('project-progress.evidence', ['topic' => $topic, 'evidence' => '__EVIDENCE__']))->toHtml() ?>,
        previewUrl: <?php echo \Illuminate\Support\Js::from(route('project-progress.preview', $topic))->toHtml() ?>,
        previewTitle: 'Monitoring Tool preview',
        draftSaveUrl: <?php echo \Illuminate\Support\Js::from(route('project-progress.draft', $topic))->toHtml() ?>,
        initialDraftVersion: <?php echo \Illuminate\Support\Js::from($draftVersion)->toHtml() ?>,
        csrfToken: <?php echo \Illuminate\Support\Js::from(csrf_token())->toHtml() ?>,
        initialReportingDate: <?php echo \Illuminate\Support\Js::from($defaultReportingDate)->toHtml() ?>,
        approvedWorkPlanAvailable: <?php echo \Illuminate\Support\Js::from($approvedWorkPlanAvailable)->toHtml() ?>,
        initialPeriodKey: <?php echo \Illuminate\Support\Js::from($selectedPeriodKey)->toHtml() ?>,
        reportCount: <?php echo \Illuminate\Support\Js::from($monitoringReportCount)->toHtml() ?>,
        submissionOpen: <?php echo \Illuminate\Support\Js::from((bool) $submissionOpen)->toHtml() ?>,
        submissionOpensAt: <?php echo \Illuminate\Support\Js::from($submissionOpensAt)->toHtml() ?>,
        scheduleCheckedOn: <?php echo \Illuminate\Support\Js::from(now()->toDateString())->toHtml() ?>,
    })"
>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $standalone): ?>
    <header class="flex items-center justify-between gap-3 px-5 py-4 text-sm font-black text-gray-950 dark:text-white">
        <span>
            <?php echo e($revisionReport ? 'Correct '.$revisionReport->quarter_label.' Monitoring Tool' : 'Submit monitoring tool'); ?>

            <span class="mt-1 block text-xs font-normal text-red-700 dark:text-red-300">BatStateU-REC-RES-03 · Revision 03<?php echo e($revisionReport ? ' · '.$revisionReport->version_label.' is retained as the original submitted report' : ''); ?></span>
        </span>
        <span class="rounded-full bg-gray-950 px-3 py-1 text-[10px] font-black uppercase text-white shadow-sm dark:bg-white dark:text-gray-950">Open form</span>
    </header>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <?php if (isset($component)) { $__componentOriginal365c40492913100a7be7d48ba061239f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal365c40492913100a7be7d48ba061239f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-collaboration-monitor','data' => ['loadedVersion' => $draftVersion,'stateUrl' => route('project-report-drafts.edit-state', ['topic' => $topic, 'kind' => 'monitoring', 'reportingDate' => $defaultReportingDate, 'source' => $revisionReport?->id ?? 0]),'stateScope' => $draftRecoveryState['scope'] ?? null,'reloadUrl' => route('project-progress.create', ['topic' => $topic, 'reporting_date' => $defaultReportingDate, 'revise_monitoring_report' => $revisionReport?->id]),'label' => 'Monitoring Tool','reportRecovery' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-collaboration-monitor'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['loaded-version' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($draftVersion),'state-url' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('project-report-drafts.edit-state', ['topic' => $topic, 'kind' => 'monitoring', 'reportingDate' => $defaultReportingDate, 'source' => $revisionReport?->id ?? 0])),'state-scope' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($draftRecoveryState['scope'] ?? null),'reload-url' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('project-progress.create', ['topic' => $topic, 'reporting_date' => $defaultReportingDate, 'revise_monitoring_report' => $revisionReport?->id])),'label' => 'Monitoring Tool','report-recovery' => true]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal365c40492913100a7be7d48ba061239f)): ?>
<?php $attributes = $__attributesOriginal365c40492913100a7be7d48ba061239f; ?>
<?php unset($__attributesOriginal365c40492913100a7be7d48ba061239f); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal365c40492913100a7be7d48ba061239f)): ?>
<?php $component = $__componentOriginal365c40492913100a7be7d48ba061239f; ?>
<?php unset($__componentOriginal365c40492913100a7be7d48ba061239f); ?>
<?php endif; ?>

    <?php if (isset($component)) { $__componentOriginalde3464cfcc5b37a0fa7f7df5e8159e63 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalde3464cfcc5b37a0fa7f7df5e8159e63 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.monitoring-writing-toolbar','data' => ['topic' => $topic,'reportLabel' => 'Monitoring Tool','panelId' => 'monitoring-preview-'.e($topic->id).'','formId' => 'monitoring-form-'.e($topic->id).'','saveMethod' => 'saveMonitoringDraft','standalone' => $standalone,'class' => 'mb-4']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('monitoring-writing-toolbar'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['topic' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($topic),'report-label' => 'Monitoring Tool','panel-id' => 'monitoring-preview-'.e($topic->id).'','form-id' => 'monitoring-form-'.e($topic->id).'','save-method' => 'saveMonitoringDraft','standalone' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($standalone),'class' => 'mb-4']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalde3464cfcc5b37a0fa7f7df5e8159e63)): ?>
<?php $attributes = $__attributesOriginalde3464cfcc5b37a0fa7f7df5e8159e63; ?>
<?php unset($__attributesOriginalde3464cfcc5b37a0fa7f7df5e8159e63); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalde3464cfcc5b37a0fa7f7df5e8159e63)): ?>
<?php $component = $__componentOriginalde3464cfcc5b37a0fa7f7df5e8159e63; ?>
<?php unset($__componentOriginalde3464cfcc5b37a0fa7f7df5e8159e63); ?>
<?php endif; ?>

    <div class="proposal-preview-workspace proposal-writing-columns" :class="{ 'proposal-writing-preview-hidden': !previewPaneOpen }" @resize.window.debounce.150ms="resizeProposalPaperPreview()">
    <div class="proposal-edit-pane min-w-0" :inert="previewFullscreen">
    <form
        id="monitoring-form-<?php echo e($topic->id); ?>"
        x-ref="form"
        data-monitoring-tool-autosave-form
        data-assistant-report-type="monitoring-tool"
        data-assistant-report-key="monitoring-tool:topic:<?php echo e($topic->id); ?>:<?php echo e($revisionReport ? 'revision:'.$revisionReport->id : 'new'); ?>"
        method="POST"
        action="<?php echo e(route('project-progress.prepare', $topic)); ?>"
        enctype="multipart/form-data"
        class="space-y-8 bg-white p-4 sm:p-6 dark:bg-slate-900 <?php echo e($standalone ? '' : 'border-t border-red-200 dark:border-red-950'); ?>"
        @submit="prepareMonitoringPdf($event)"
    >
        <?php echo csrf_field(); ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($revisionReport): ?>
            <input type="hidden" name="source_report_id" value="<?php echo e($revisionReport->id); ?>">
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <input type="hidden" name="draft_version" value="<?php echo e($draftVersion); ?>">
        <input type="hidden" name="draft_id" value="<?php echo e($draftId); ?>">
        <input type="hidden" name="recovery_scope" value="<?php echo e($draftRecoveryState['scope'] ?? ''); ?>">
        <input type="hidden" name="progress_mode" value="evidence">
        <?php if (isset($component)) { $__componentOriginale80005aab43afed1ddd7610096b09357 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginale80005aab43afed1ddd7610096b09357 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.assistant-report-editor','data' => ['context' => ['report_type' => 'monitoring-tool', 'topic_id' => $topic->id, 'source_key' => $revisionReport ? 'revision:'.$revisionReport->id : 'new']]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('assistant-report-editor'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['context' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(['report_type' => 'monitoring-tool', 'topic_id' => $topic->id, 'source_key' => $revisionReport ? 'revision:'.$revisionReport->id : 'new'])]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginale80005aab43afed1ddd7610096b09357)): ?>
<?php $attributes = $__attributesOriginale80005aab43afed1ddd7610096b09357; ?>
<?php unset($__attributesOriginale80005aab43afed1ddd7610096b09357); ?>
<?php endif; ?>
<?php if (isset($__componentOriginale80005aab43afed1ddd7610096b09357)): ?>
<?php $component = $__componentOriginale80005aab43afed1ddd7610096b09357; ?>
<?php unset($__componentOriginale80005aab43afed1ddd7610096b09357); ?>
<?php endif; ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?>
            <div role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-xs text-red-700">
                <p class="font-black">Please correct the highlighted monitoring fields.</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <li><?php echo e($error); ?></li>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </ul>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <p data-report-submission-lock x-show="!submissionOpen" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200">You can fill, save, and preview this draft now. Official PDF preparation and submission open <span class="font-semibold" x-text="submissionOpensAt"><?php echo e($submissionOpensAt); ?></span>, during the final week of the reporting period.</p>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-950">
            <div class="grid gap-px bg-slate-200 dark:bg-slate-700 sm:grid-cols-3">
                <div class="bg-white p-4 dark:bg-slate-900 sm:col-span-2">
                    <p class="text-xs font-semibold text-slate-500 dark:text-slate-400">Research project</p>
                    <p class="mt-1 break-words text-base font-semibold text-slate-900 dark:text-white"><?php echo e($topic->title); ?></p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400"><?php echo e($topic->user->name); ?> · ₱<?php echo e(number_format((float) $topic->estimated_budget, 2)); ?> · <?php echo e($topic->estimated_duration_months); ?> months</p>
                </div>
                <div class="bg-white p-4 dark:bg-slate-900">
                    <p class="text-xs font-semibold text-slate-500 dark:text-slate-400">Reporting sequence</p>
                    <p class="mt-1 text-lg font-black tabular-nums text-slate-950 dark:text-white">
                        Report <span x-text="currentReportNumber()"><?php echo e($selectedReportNumber); ?></span>
                        <span class="text-sm font-semibold text-slate-400">of <?php echo e($monitoringReportCount); ?></span>
                    </p>
                </div>
            </div>
            <div class="grid gap-4 p-4 sm:grid-cols-2 sm:items-end">
                <div>
                    <label for="monitoring-reporting-date" class="text-sm font-semibold text-slate-800 dark:text-slate-100">Reporting Period</label>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($revisionReport): ?>
                        <p id="monitoring-reporting-date" class="mt-2 text-sm font-semibold text-slate-950 dark:text-white"><?php echo e($revisionReport->quarter_label); ?> · <?php echo e($revisionReport->reporting_period_label); ?></p>
                        <input type="hidden" name="reporting_date" value="<?php echo e($defaultReportingDate); ?>">
                    <?php else: ?>
                        <select
                            id="monitoring-reporting-date"
                            name="reporting_date"
                            required
                            autocomplete="off"
                            @input.stop
                            @change="selectReportingPeriod($event)"
                            :aria-busy="switchingPeriod"
                            class="mt-2 block w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-900 dark:text-white"
                        >
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $quarterOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $quarter): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <?php
                                    $periodKey = $quarter['year'].'-'.$quarter['quarter'];
                                    $selected = $periodKey === $selectedPeriodKey;
                                ?>
                                <option data-editor-url="<?php echo e(route('project-progress.create', ['topic' => $topic, 'reporting_date' => $quarter['reporting_date']])); ?>" value="<?php echo e($selected ? $defaultReportingDate : $quarter['reporting_date']); ?>" <?php if($selected): echo 'selected'; endif; ?>><?php echo e($quarter['label']); ?> · <?php echo e($quarter['period']); ?></option>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </select>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs leading-5 text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-100" x-show="hasApprovedEntries()">
                    <p class="font-bold">From your approved work plan</p>
                    <p>Evidence is required for reported accomplishments before submission. Submitted evidence appears in the project folder and the College Accomplishment Report supporting documentation. Progress is calculated against each approved target and its activity weight.</p>
                </div>
            </div>
        </section>

        <section class="space-y-4">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold text-slate-950 dark:text-white">Approved Activities & Progress</h2>
                    <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-500 dark:text-slate-400" x-text="hasApprovedEntries() ? 'Review each planned activity and record what you accomplished this quarter.' : 'Describe each activity, its expected output, and what you accomplished this quarter.'"></p>
                </div>
                <button
                    type="button"
                    @click="addEntry"
                    x-show="!hasApprovedEntries()"
                    :disabled="entries.length >= 11"
                    class="min-h-11 rounded-xl bg-red-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-red-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-700 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-40"
                >Add Activity</button>
            </div>

            <div x-ref="activityList" class="space-y-5">
                <template x-for="(entry, index) in entries" :key="`${currentPeriodKey}-${entry.source_work_plan_index ?? index}`">
                    <article data-monitoring-activity class="grid scroll-mt-40 overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900 xl:grid-cols-2">
                        <div class="border-b border-slate-200 bg-slate-50 p-4 sm:p-5 dark:border-slate-700 dark:bg-slate-950/70 xl:border-b-0 xl:border-r">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-lg bg-slate-900 px-3 py-1.5 text-sm font-bold text-white dark:bg-white dark:text-slate-950">Activity <span x-text="index + 1"></span></span>
                                        <span x-show="isApprovedEntry(entry)" class="rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/50 dark:text-emerald-200">Approved plan</span>
                                        <span x-show="isApprovedEntry(entry)" class="text-xs font-semibold text-slate-500" x-text="monthLabel(entry.work_plan_months)"></span>
                                        <span data-monitoring-activity-overdue x-show="isEntryOverdue(entry)" x-cloak role="status" class="rounded-full border border-amber-300 bg-amber-50 px-2.5 py-1 text-xs font-bold text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200">Past target date</span>
                                    </div>
                                    <template x-if="isApprovedEntry(entry)">
                                        <div class="mt-3 space-y-3">
                                            <div>
                                                <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Objective</p>
                                                <p class="mt-1 break-words text-sm font-bold leading-6 text-slate-950 dark:text-white" x-text="entry.objective"></p>
                                            </div>
                                            <div class="grid gap-3 sm:grid-cols-2">
                                                <div>
                                                    <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Activity</p>
                                                    <p class="mt-1 break-words text-sm leading-6 text-slate-700 dark:text-slate-200" x-text="entry.activity"></p>
                                                </div>
                                                <div>
                                                    <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Expected Output</p>
                                                    <p class="mt-1 break-words text-sm leading-6 text-slate-700 dark:text-slate-200" x-text="entry.physical_target"></p>
                                                </div>
                                            </div>
                                            <div class="flex flex-wrap gap-x-5 gap-y-1 text-xs font-semibold text-slate-500 dark:text-slate-400">
                                                <span><span class="text-slate-400">Project Weight:</span> <span class="tabular-nums" x-text="`${entry.percent_weight}%`"></span></span>
                                                <span><span class="text-slate-400">Target Date:</span> <span class="tabular-nums" x-text="formatPlanDate(entry.target_completion_date)"></span></span>
                                            </div>

                                            <input type="hidden" :name="`work_plan[${index}][source_work_plan_index]`" :value="entry.source_work_plan_index">
                                            <input type="hidden" :name="`work_plan[${index}][objective]`" :value="entry.objective">
                                            <input type="hidden" :name="`work_plan[${index}][activity]`" :value="entry.activity">
                                            <input type="hidden" :name="`work_plan[${index}][percent_weight]`" :value="entry.percent_weight">
                                            <input type="hidden" :name="`work_plan[${index}][physical_target]`" :value="entry.physical_target">
                                            <input type="hidden" :name="`work_plan[${index}][target_completion_date]`" :value="entry.target_completion_date">
                                            <template x-for="month in entry.work_plan_months" :key="month">
                                                <input type="hidden" :name="`work_plan[${index}][work_plan_months][]`" :value="month">
                                            </template>
                                        </div>
                                    </template>
                                    <template x-if="!isApprovedEntry(entry)">
                                        <div class="mt-3 grid gap-4 sm:grid-cols-2">
                                            <label class="text-sm font-medium text-slate-700 dark:text-slate-200 sm:col-span-2">Activity
                                                <textarea :name="`work_plan[${index}][activity]`" x-model="entry.activity" rows="2" maxlength="1500" required autocomplete="off" placeholder="Example: Conduct field interviews with 20 participants…" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-950 dark:text-white"></textarea>
                                            </label>
                                            <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Project Weight (%)
                                                <input type="number" inputmode="decimal" :name="`work_plan[${index}][percent_weight]`" x-model="entry.percent_weight" @input="updateEntryProgress(entry)" min="0" max="100" step="0.01" required autocomplete="off" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-950 dark:text-white">
                                            </label>
                                            <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Target Completion Date
                                                <input type="date" :name="`work_plan[${index}][target_completion_date]`" x-model="entry.target_completion_date" required autocomplete="off" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-950 dark:text-white">
                                            </label>
                                            <label class="text-sm font-medium text-slate-700 dark:text-slate-200 sm:col-span-2">Expected Output
                                                <textarea :name="`work_plan[${index}][physical_target]`" x-model="entry.physical_target" @input="updateActivityTarget(entry)" rows="2" maxlength="500" required autocomplete="off" placeholder="Example: Interview dataset with 20 responses…" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-950 dark:text-white"></textarea>
                                            </label>
                                        </div>
                                    </template>
                                </div>
                                <button type="button" @click="removeEntry(index)" x-show="!isApprovedEntry(entry) && entries.length > 1" class="secondary-action">Remove</button>
                            </div>
                        </div>

                        <div class="grid content-start gap-4 p-4 sm:p-5 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <h3 class="text-sm font-bold text-slate-950 dark:text-white">Progress this quarter</h3>
                                <p class="mt-1 text-sm leading-6 text-slate-500 dark:text-slate-400">Describe the completed work and attach its evidence.</p>
                            </div>
                            <label class="text-sm font-semibold text-slate-800 dark:text-slate-100 sm:col-span-2">Actual Accomplishment
                                <textarea :name="`work_plan[${index}][actual_accomplishment]`" x-model="entry.actual_accomplishment" rows="3" maxlength="500" required autocomplete="off" placeholder="State the measurable result completed during this reporting period…" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-950 dark:text-white"></textarea>
                            </label>
                            <div class="space-y-3 sm:col-span-2" data-monitoring-evidence>
                                <input type="hidden" :name="`work_plan[${index}][activity_id]`" :value="entry.activity_id || ''">
                                <label class="block text-sm font-semibold text-slate-800 dark:text-slate-100" :for="`activity-evidence-${index}`">Evidence of completed work (required before submission)</label>
                                <input :id="`activity-evidence-${index}`" :name="`activity_evidence[${evidenceKey(entry, index)}][]`" type="file" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png" @change="setEvidenceFiles(entry, $event)" class="block w-full rounded-lg border border-slate-300 text-sm text-slate-600 file:mr-3 file:cursor-pointer file:border-0 file:bg-slate-100 file:px-4 file:py-3 file:font-semibold file:text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-600 dark:border-slate-600 dark:text-slate-300 dark:file:bg-slate-800 dark:file:text-white">
                                <p class="text-sm leading-6 text-slate-500 dark:text-slate-400" x-text="Number(entry.target_units || 1) > 1 ? 'Enter the total completed quantity supported by these files. Extra files do not increase progress.' : 'Attach proof that this activity’s approved output is complete. The milestone is counted once, regardless of file count.'"></p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">Up to 5 files per activity · PDF, Word, Excel, JPG or PNG · 10 MB each</p>
                                <p x-show="entry.evidenceError" x-text="entry.evidenceError" role="alert" class="text-sm font-semibold text-red-700 dark:text-red-300"></p>
                                <template x-for="file in (entry.evidence || [])" :key="file.id">
                                    <div class="flex items-center gap-3 rounded-lg bg-slate-50 px-3 py-2 dark:bg-slate-950">
                                        <input type="hidden" :name="`work_plan[${index}][evidence_ids][]`" :value="file.id">
                                        <a :href="evidenceUrl(file.id)" x-text="file.name" class="min-w-0 flex-1 break-words secondary-action justify-start text-left"></a>
                                        <button type="button" @click="removeEvidence(entry, file.id)" :aria-label="`Remove ${file.name}`" title="Remove evidence" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-slate-300 bg-white text-slate-600 hover:border-red-300 hover:text-red-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="m6 6 12 12M6 18 18 6" /></svg></button>
                                    </div>
                                </template>
                                <p x-show="entry.pendingEvidenceCount" class="text-sm text-slate-500 dark:text-slate-400">New evidence saves with your draft.</p>
                                <label x-show="Number(entry.target_units || 1) > 1" class="block text-sm font-semibold text-slate-800 dark:text-slate-100">Total completed <span class="font-normal text-slate-500" x-text="`(of ${entry.target_units} ${entry.progress_unit})`"></span>
                                    <input :name="`work_plan[${index}][completed_units]`" type="number" inputmode="numeric" x-model="entry.completed_units" @input="updateEntryProgress(entry)" min="0" :max="entry.target_units || 1" step="1" required autocomplete="off" class="mt-1 block w-full rounded-xl border-slate-300 text-base shadow-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-950 dark:text-white">
                                </label>
                                <input type="hidden" :name="`work_plan[${index}][accomplished_percentage]`" :value="entry.accomplished_percentage">
                                <div class="flex items-baseline justify-between gap-3 border-t border-slate-200 pt-3 dark:border-slate-700" role="status" aria-live="polite">
                                    <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">Calculated completion</span>
                                    <span class="text-xl font-bold tabular-nums text-slate-950 dark:text-white"><span x-text="Number(entry.completion || 0).toFixed(2)"></span>%</span>
                                </div>
                                <p class="text-sm text-slate-500 dark:text-slate-400"><span x-text="Number(entry.accomplished_percentage || 0).toFixed(2)"></span>% toward overall project progress</p>
                            </div>
                            <label class="text-sm font-semibold text-slate-800 dark:text-slate-100 sm:col-span-2">Findings or Challenges <span class="font-normal text-slate-400">(optional)</span>
                                <textarea :name="`work_plan[${index}][findings]`" x-model="entry.findings" rows="2" maxlength="500" autocomplete="off" placeholder="Describe a blocker, variance, or finding that needs attention…" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-950 dark:text-white"></textarea>
                            </label>
                        </div>
                    </article>
                </template>
            </div>

            <div x-show="!hasApprovedEntries()" class="flex flex-wrap items-center justify-between gap-3">
                <p role="status" aria-live="polite" class="text-sm text-slate-500 dark:text-slate-400"><span x-text="entries.length"></span> of 11 activities <span x-show="entries.length >= 11">· Activity limit reached</span></p>
                <button type="button" @click="addEntry" :disabled="entries.length >= 11" class="min-h-11 rounded-xl border border-red-200 bg-red-50 px-4 py-2.5 text-sm font-bold text-red-700 hover:bg-red-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-700 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-40 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">Add Activity</button>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-4 dark:border-slate-700 dark:bg-slate-950">
                <p class="text-sm font-semibold text-slate-500 dark:text-slate-400">Overall project progress, including earlier quarters</p>
                <p class="text-lg font-black tabular-nums text-slate-950 dark:text-white"><span x-text="totalProjectProgress().toFixed(2)"></span>%</p>
            </div>
        </section>

        <section class="space-y-3">
            <div>
                <h2 class="text-lg font-bold text-gray-900 dark:text-white">Spending this quarter</h2>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($topic->research_secretary_id): ?>
                    <p class="mt-1 text-xs text-gray-500">The selected project secretary gets priority for this financial section. After preparing the Monitoring Tool, any authorized project member can complete it if needed.</p>
                <?php else: ?>
                    <p class="mt-1 text-xs text-gray-500">Open only the request types you used. Leave amounts at zero when there was no spending.</p>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            <div class="space-y-3">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($topic->research_secretary_id): ?>
                    <div class="flex items-center gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-900 dark:bg-amber-950/30">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-full bg-white text-sm font-black text-amber-800 ring-1 ring-amber-200">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($topic->researchSecretary?->avatar): ?>
                                <img src="<?php echo e($topic->researchSecretary->avatar); ?>" alt="" class="h-full w-full object-cover">
                            <?php else: ?>
                                <?php echo e(collect(explode(' ', $topic->researchSecretary?->name ?? 'RS'))->filter()->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->implode('')); ?>

                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </span>
                        <div class="min-w-0">
                            <p class="text-xs font-black uppercase tracking-wider text-amber-800">Priority project secretary</p>
                            <p class="truncate text-sm font-bold text-gray-950 dark:text-white"><?php echo e($topic->researchSecretary?->name); ?></p>
                            <p class="truncate text-xs text-gray-500"><?php echo e($topic->researchSecretary?->email); ?></p>
                        </div>
                    </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $budgetRows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $budget): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <?php
                            $budget = is_array($budget) ? $budget : [];
                            $budgetType = $budget['type'] ?? ($defaultBudget[$index]['type'] ?? 'Request');
                        ?>
                        <input type="hidden" name="budget_utilization[<?php echo e($index); ?>][type]" value="<?php echo e($budgetType); ?>">
                        <input type="hidden" name="budget_utilization[<?php echo e($index); ?>][details]" value="<?php echo e($budget['details'] ?? ''); ?>">
                        <input type="hidden" name="budget_utilization[<?php echo e($index); ?>][amount_requested]" value="<?php echo e($budget['amount_requested'] ?? 0); ?>">
                        <input type="hidden" name="budget_utilization[<?php echo e($index); ?>][actual_amount]" value="<?php echo e($budget['actual_amount'] ?? 0); ?>">
                        <input type="hidden" name="budget_utilization[<?php echo e($index); ?>][remarks]" value="<?php echo e($budget['remarks'] ?? ''); ?>">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                <?php else: ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $budgetRows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $budget): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <?php
                        $budget = is_array($budget) ? $budget : [];
                        $budgetType = $budget['type'] ?? ($defaultBudget[$index]['type'] ?? 'Request');
                    ?>
                    <details class="rounded-xl border border-gray-200 p-4 dark:border-slate-700" <?php if((float) ($budget['amount_requested'] ?? 0) > 0 || filled($budget['details'] ?? null)): ?> open <?php endif; ?>>
                        <input type="hidden" name="budget_utilization[<?php echo e($index); ?>][type]" value="<?php echo e($budgetType); ?>">
                        <summary class="disclosure-summary cursor-pointer rounded-lg text-sm font-semibold text-gray-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-700 focus-visible:ring-offset-2 dark:text-slate-200"><?php echo e($budgetType); ?></summary>
                        <div class="mt-3 grid gap-4 sm:grid-cols-2">
                            <label class="text-sm font-medium text-gray-700 dark:text-slate-200 sm:col-span-2">Details of request
                                <textarea name="budget_utilization[<?php echo e($index); ?>][details]" rows="2" maxlength="300" autocomplete="off" class="mt-1 block w-full rounded-lg border-gray-300 text-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-700 dark:bg-slate-950 dark:text-white"><?php echo e($budget['details'] ?? ''); ?></textarea>
                            </label>
                            <label class="text-sm font-medium text-gray-700 dark:text-slate-200">Amount requested (PHP)
                                <input type="number" inputmode="decimal" name="budget_utilization[<?php echo e($index); ?>][amount_requested]" value="<?php echo e($budget['amount_requested'] ?? 0); ?>" min="0" step="0.01" required autocomplete="off" class="mt-1 block w-full rounded-lg border-gray-300 text-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                            </label>
                            <label class="text-sm font-medium text-gray-700 dark:text-slate-200">Amount spent (PHP)
                                <input type="number" inputmode="decimal" name="budget_utilization[<?php echo e($index); ?>][actual_amount]" value="<?php echo e($budget['actual_amount'] ?? 0); ?>" min="0" step="0.01" required autocomplete="off" class="mt-1 block w-full rounded-lg border-gray-300 text-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                            </label>
                            <label class="text-sm font-medium text-gray-700 dark:text-slate-200 sm:col-span-2">Remarks or challenges <span class="font-normal text-gray-400">(optional)</span>
                                <textarea name="budget_utilization[<?php echo e($index); ?>][remarks]" rows="2" maxlength="300" autocomplete="off" class="mt-1 block w-full rounded-lg border-gray-300 text-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-700 dark:bg-slate-950 dark:text-white"><?php echo e($budget['remarks'] ?? ''); ?></textarea>
                            </label>
                        </div>
                    </details>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </section>

        <details class="rounded-xl border border-gray-200 p-4 dark:border-slate-700">
            <summary class="disclosure-summary cursor-pointer rounded-lg text-sm font-semibold text-gray-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-700 focus-visible:ring-offset-2 dark:text-slate-100">Supporting Details <span class="font-normal text-gray-500">(optional)</span></summary>
        <div class="mt-4 grid gap-4 rounded-xl bg-gray-50 p-4 dark:bg-slate-950 sm:grid-cols-2">
            <div>
                <label for="prepared_by_date_signed" class="text-sm font-medium text-gray-700 dark:text-slate-200">Date signed by project leader <span class="font-normal text-gray-400">(optional)</span></label>
                <?php if (isset($component)) { $__componentOriginal37e12294b28f0bd91a733acab9bb06c5 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal37e12294b28f0bd91a733acab9bb06c5 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.date-picker','data' => ['id' => 'prepared_by_date_signed','name' => 'prepared_by_date_signed','value' => $old('prepared_by_date_signed', $defaultPreparedByDate),'restoreOldInput' => false,'max' => now()->toDateString(),'class' => 'mt-1']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('date-picker'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['id' => 'prepared_by_date_signed','name' => 'prepared_by_date_signed','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($old('prepared_by_date_signed', $defaultPreparedByDate)),'restore-old-input' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(false),'max' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(now()->toDateString()),'class' => 'mt-1']); ?>
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
            <label class="text-sm font-medium text-gray-700 dark:text-slate-200">Supporting attachment <span class="font-normal text-gray-400">(optional)</span>
                <input type="file" name="attachment" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png" class="mt-1 block w-full rounded-xl border border-gray-200 bg-white p-2 text-xs">
            </label>
        </div>

            <label class="block text-sm font-medium text-gray-700 dark:text-slate-200">Tracking Number<input name="tracking_number" maxlength="100" autocomplete="off" value="<?php echo e($old('tracking_number', $defaultTrackingNumber)); ?>" class="mt-2 block w-full rounded-lg border-gray-300 text-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-700 dark:bg-slate-950 dark:text-white" placeholder="Example: REC-2026-001…"></label>
        </details>

        <p class="border-t border-gray-100 pt-5 text-sm text-gray-500">Saved drafts are shared with your project team. Reports reach reviewers after submission.</p>
        <p x-show="!submissionOpen" class="text-sm font-semibold text-amber-900 dark:text-amber-200">Official PDF preparation opens <span x-text="submissionOpensAt"><?php echo e($submissionOpensAt); ?></span>. Save or preview your draft now.</p>

    </form>
    </div>
    <?php if (isset($component)) { $__componentOriginal10a0e39c04a9eacf5d69b3b1628f0121 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal10a0e39c04a9eacf5d69b3b1628f0121 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-paper-preview','data' => ['panelId' => 'monitoring-preview-'.e($topic->id).'','previewLabel' => 'Monitoring Tool preview','frameTitle' => 'Monitoring tool document preview']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-paper-preview'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['panel-id' => 'monitoring-preview-'.e($topic->id).'','preview-label' => 'Monitoring Tool preview','frame-title' => 'Monitoring tool document preview']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal10a0e39c04a9eacf5d69b3b1628f0121)): ?>
<?php $attributes = $__attributesOriginal10a0e39c04a9eacf5d69b3b1628f0121; ?>
<?php unset($__attributesOriginal10a0e39c04a9eacf5d69b3b1628f0121); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal10a0e39c04a9eacf5d69b3b1628f0121)): ?>
<?php $component = $__componentOriginal10a0e39c04a9eacf5d69b3b1628f0121; ?>
<?php unset($__componentOriginal10a0e39c04a9eacf5d69b3b1628f0121); ?>
<?php endif; ?>
    </div>
</section>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\components\monitoring-tool-form.blade.php ENDPATH**/ ?>