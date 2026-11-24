<?php
    $submitted = $report?->submitted_at !== null;
    $latestSubmission = $report?->submissions->first();
    $returned = ! $submitted && $latestSubmission?->returned_at !== null;
    $activities = collect(['presentations', 'publications', 'seminars', 'manual_research', 'ip_assets'])->mapWithKeys(fn (string $section): array => [
        $section => collect(old($section, $report?->{$section} ?? []))->filter(fn (mixed $row): bool => is_array($row))->map(fn (array $row): array => [
            'id' => (string) Illuminate\Support\Str::uuid(), ...($section === 'ip_assets' ? ['title' => '', 'type' => '', 'inventors' => '', 'date' => '', 'remarks' => '', 'evidence_url' => ''] : ['research_title' => '', 'project_id' => '']), ...$row,
        ])->values()->all(),
    ])->all();
    $rowProofs = collect($report?->evidence ?? [])->map(fn (array $image, int $index): array => [
        'section' => $image['section'] ?? null, 'row_id' => $image['row_id'] ?? null,
        'token' => hash('sha256', $image['path']), 'filename' => $image['original_filename'] ?? basename($image['path']),
        'caption' => old('saved_evidence_captions.'.hash('sha256', $image['path']), $image['caption'] ?? ''),
        'url' => route('research_coordinator.reports.evidence', ['report' => $report, 'index' => $index]),
        'preview_url' => route('research_coordinator.reports.evidence', ['report' => $report, 'index' => $index, 'preview' => 1]),
    ])->values()->all();
    $nextQuarter = $period['end']->addDay()->startOfDay();
    $inputClass = 'w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand focus:ring-brand dark:border-slate-700 dark:bg-slate-950 dark:text-white';
?>

<?php if (isset($component)) { $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54 = $attributes; } ?>
<?php $component = App\View\Components\AppLayout::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('app-layout'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\AppLayout::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

     <?php $__env->slot('header', null, []); ?> 
        <?php if (isset($component)) { $__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.page-header','data' => ['variant' => 'hero','eyebrow' => 'Research Coordinator','title' => 'Quarterly Accomplishment Reports','subtitle' => ($coordinator->college ?: 'Your college').' · One general report each calendar quarter.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('page-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['variant' => 'hero','eyebrow' => 'Research Coordinator','title' => 'Quarterly Accomplishment Reports','subtitle' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(($coordinator->college ?: 'Your college').' · One general report each calendar quarter.')]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

             <?php $__env->slot('actions', null, []); ?> 
                <a href="<?php echo e(route('research_coordinator.reports.index', ['year' => $year, 'quarter' => $quarter, 'preview' => 1])); ?>" class="dashboard-action">Preview saved report</a>
                <a href="<?php echo e(route('research_coordinator.reports.template')); ?>" class="dashboard-action">Download blank template</a>
             <?php $__env->endSlot(); ?>
         <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e)): ?>
<?php $attributes = $__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e; ?>
<?php unset($__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e)): ?>
<?php $component = $__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e; ?>
<?php unset($__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e); ?>
<?php endif; ?>
     <?php $__env->endSlot(); ?>

    <div data-quarterly-reports data-proposal-paper-workspace x-data="reportPaperPreview(<?php echo \Illuminate\Support\Js::from([
        'previewUrl' => $submitted ? route('research_coordinator.reports.print', ['year' => $year, 'quarter' => $quarter, 'embedded' => 1]) : route('research_coordinator.reports.preview'),
        'title' => 'Quarterly accomplishment report', 'readOnly' => $submitted, 'openOnLoad' => request()->boolean('preview'),
    ])->toHtml() ?>)" @input="markProposalPreviewStale()" @change="markProposalPreviewStale()" @resize.window.debounce.150ms="resizeProposalPaperPreview()">
        <div class="proposal-preview-workspace proposal-writing-columns" :class="{ 'proposal-writing-preview-hidden': !previewPaneOpen }">
        <div class="proposal-edit-pane space-y-6" :inert="previewFullscreen">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('status')): ?>
            <p role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200"><?php echo e(session('status')); ?></p>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('report_download_url')): ?>
            <div class="flex flex-wrap items-center gap-3 text-sm text-slate-600 dark:text-slate-300"><p>Your download should start automatically.</p><a href="<?php echo e(session('report_download_url')); ?>" x-init="window.location.assign($el.href)" class="dashboard-action">Download Word again</a></div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($returned): ?>
            <section role="status" class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200">
                <h2 class="font-semibold">Returned for correction</h2>
                <p class="mt-1 whitespace-pre-line"><?php echo e($latestSubmission->return_reason); ?></p>
                <p class="mt-2">Update this draft and resubmit it. The previous submission remains available in the history.</p>
            </section>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?>
            <div role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">
                <p class="font-semibold">Check the report before saving.</p>
                <ul class="mt-2 list-inside list-disc">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <li><?php echo e($error); ?></li>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </ul>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <section aria-label="Reporting quarters" class="space-y-4">
            <form method="GET" action="<?php echo e(route('research_coordinator.reports.index')); ?>" class="flex flex-wrap items-end gap-3">
                <div>
                    <?php if (isset($component)) { $__componentOriginale3da9d84bb64e4bc2eeebaafabfb2581 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginale3da9d84bb64e4bc2eeebaafabfb2581 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.input-label','data' => ['for' => 'report-year','value' => 'Reporting year']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('input-label'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['for' => 'report-year','value' => 'Reporting year']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginale3da9d84bb64e4bc2eeebaafabfb2581)): ?>
<?php $attributes = $__attributesOriginale3da9d84bb64e4bc2eeebaafabfb2581; ?>
<?php unset($__attributesOriginale3da9d84bb64e4bc2eeebaafabfb2581); ?>
<?php endif; ?>
<?php if (isset($__componentOriginale3da9d84bb64e4bc2eeebaafabfb2581)): ?>
<?php $component = $__componentOriginale3da9d84bb64e4bc2eeebaafabfb2581; ?>
<?php unset($__componentOriginale3da9d84bb64e4bc2eeebaafabfb2581); ?>
<?php endif; ?>
                    <input id="report-year" name="year" type="number" min="2000" max="2100" value="<?php echo e($year); ?>" required class="mt-1 w-32 rounded-lg border-gray-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                </div>
                <input type="hidden" name="quarter" value="<?php echo e($quarter); ?>">
                <button type="submit" class="dashboard-action">View year</button>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($nextQuarter->year <= 2100): ?>
                    <a href="<?php echo e(route('research_coordinator.reports.index', ['year' => $nextQuarter->year, 'quarter' => $nextQuarter->quarter])); ?>" class="dashboard-action">Open next quarter (Q<?php echo e($nextQuarter->quarter); ?> <?php echo e($nextQuarter->year); ?>)</a>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </form>
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $quarters; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $quarterRow): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <a wire:navigate href="<?php echo e(route('research_coordinator.reports.index', ['year' => $year, 'quarter' => $quarterRow['quarter']])); ?>"
                        <?php if($quarterRow['quarter'] === $quarter): ?> aria-current="page" <?php endif; ?>
                        class="<?php echo \Illuminate\Support\Arr::toCssClasses(['dashboard-panel block border p-4 transition hover:border-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand', 'border-brand ring-1 ring-brand' => $quarterRow['quarter'] === $quarter]); ?>">
                        <span class="flex items-center justify-between gap-2"><span class="font-bold text-gray-950 dark:text-white">Q<?php echo e($quarterRow['quarter']); ?></span><span class="text-xs font-semibold text-brand dark:text-rose-300"><?php echo e($quarterRow['status']); ?></span></span>
                        <span class="mt-2 block text-sm text-slate-500 dark:text-slate-400"><?php echo e($quarterRow['start']->format('M')); ?> – <?php echo e($quarterRow['end']->format('M Y')); ?></span>
                    </a>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </div>
        </section>

        <section class="dashboard-panel flex flex-wrap items-start justify-between gap-4 p-5" aria-labelledby="quarter-report-title">
            <div>
                <h2 id="quarter-report-title" class="text-xl font-bold text-gray-950 dark:text-white">Accomplishment Report in Research</h2>
                <p class="mt-1 font-medium text-gray-700 dark:text-slate-300"><?php echo e($period['start']->format('F')); ?> to <?php echo e($period['end']->format('F Y')); ?> · Q<?php echo e($quarter); ?></p>
                <p class="mt-2 max-w-3xl text-sm text-slate-500 dark:text-slate-400"><?php echo e($submitted ? 'Submitted '.$report->submitted_at->format('M j, Y').'. This report preserves the records included at submission.' : 'Section II fills from your college’s projects with an issued Notice to Proceed. Add unmonitored or externally funded research manually below. Enter participation activities manually and attach proof to every row in a, b, and c.'); ?></p>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($submitted)): ?>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Faculty research refreshes from project records whenever you open a draft. Your manual entries are saved for the selected quarter.</p>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Final submission opens <?php echo e($period['end']->addDay()->startOfDay()->format('F j, Y')); ?>. Faculty project approvals continue through their existing workflow.</p>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($submitted): ?>
            <div class="flex flex-wrap gap-2">
                <button type="button" @click="showProposalPreview(); expandProposalPaperPreview()" aria-controls="quarterly-report-preview-panel" class="dashboard-action">Print preview</button>
                <a href="<?php echo e(route('research_coordinator.reports.download', ['year' => $year, 'quarter' => $quarter])); ?>" class="dashboard-action">Download Word</a>
            </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </section>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($hasSourceChanges): ?>
            <div role="status" class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200">
                <p class="font-semibold">Automatic records changed since this draft was saved.</p>
                <p class="mt-1">Section II includes the latest faculty research. Review the updates, then save your draft.</p>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <form id="quarterly-report-form" data-report-preview-form method="POST" action="<?php echo e(route('research_coordinator.reports.store')); ?>" enctype="multipart/form-data" class="space-y-6"
            data-proposal-paper-workspace data-document-writing-workspace
            data-assistant-report-type="college-quarterly" data-assistant-report-key="<?php echo e($report ? 'college-quarterly:'.$report->id : ''); ?>" data-assistant-report-save-mode="manual"
            x-data="quarterlyReportForm(<?php echo e(Illuminate\Support\Js::from([...$activities, 'rowProofs' => $rowProofs, 'removedProofs' => old('remove_evidence', [])])); ?>)"
            @focusin="focusWritingField($event)" @formdata="serialize($event)">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="reporting_year" value="<?php echo e($year); ?>">
            <input type="hidden" name="reporting_quarter" value="<?php echo e($quarter); ?>">
            <input type="hidden" name="draft_token" value="<?php echo e(session()->hasOldInput() ? old('draft_token', '') : ($report ?? new \App\Models\CollegeQuarterlyReport)->draftToken()); ?>">

            <?php if (isset($component)) { $__componentOriginale80005aab43afed1ddd7610096b09357 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginale80005aab43afed1ddd7610096b09357 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.assistant-report-editor','data' => ['context' => ['report_type' => 'college-quarterly', 'report_id' => $report?->id],'editable' => ! $submitted]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('assistant-report-editor'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['context' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(['report_type' => 'college-quarterly', 'report_id' => $report?->id]),'editable' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(! $submitted)]); ?>
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

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($submitted)): ?>
                <?php if (isset($component)) { $__componentOriginalad30c642d3faf2d819a0145863c772b0 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalad30c642d3faf2d819a0145863c772b0 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.quarterly-report-writing-toolbar','data' => ['period' => $period,'returned' => $returned]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('quarterly-report-writing-toolbar'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['period' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($period),'returned' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($returned)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalad30c642d3faf2d819a0145863c772b0)): ?>
<?php $attributes = $__attributesOriginalad30c642d3faf2d819a0145863c772b0; ?>
<?php unset($__attributesOriginalad30c642d3faf2d819a0145863c772b0); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalad30c642d3faf2d819a0145863c772b0)): ?>
<?php $component = $__componentOriginalad30c642d3faf2d819a0145863c772b0; ?>
<?php unset($__componentOriginalad30c642d3faf2d819a0145863c772b0); ?>
<?php endif; ?>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <h2 class="text-lg font-bold text-gray-950 dark:text-white">I. Participation in Research Activities</h2>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($submitted): ?>
                <?php if (isset($component)) { $__componentOriginalb7c19b4af1a7b4c4c35273dfe4dcc698 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalb7c19b4af1a7b4c4c35273dfe4dcc698 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.college-quarterly-report-table','data' => ['title' => 'a. Presentation','columns' => ['Event/Activity', 'Research Title', 'Date', 'Place', 'Personnel(s) involved'],'rows' => $reportData['presentations'] ?? [],'emptyMessage' => 'No presentations reported for this quarter.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('college-quarterly-report-table'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'a. Presentation','columns' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(['Event/Activity', 'Research Title', 'Date', 'Place', 'Personnel(s) involved']),'rows' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($reportData['presentations'] ?? []),'empty-message' => 'No presentations reported for this quarter.']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalb7c19b4af1a7b4c4c35273dfe4dcc698)): ?>
<?php $attributes = $__attributesOriginalb7c19b4af1a7b4c4c35273dfe4dcc698; ?>
<?php unset($__attributesOriginalb7c19b4af1a7b4c4c35273dfe4dcc698); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalb7c19b4af1a7b4c4c35273dfe4dcc698)): ?>
<?php $component = $__componentOriginalb7c19b4af1a7b4c4c35273dfe4dcc698; ?>
<?php unset($__componentOriginalb7c19b4af1a7b4c4c35273dfe4dcc698); ?>
<?php endif; ?>
                <?php if (isset($component)) { $__componentOriginalb7c19b4af1a7b4c4c35273dfe4dcc698 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalb7c19b4af1a7b4c4c35273dfe4dcc698 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.college-quarterly-report-table','data' => ['title' => 'b. Publication','columns' => ['Journal Name/Conference', 'Research Title', 'Date', 'Personnel(s) involved'],'rows' => $reportData['publications'] ?? [],'emptyMessage' => 'No publications reported for this quarter.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('college-quarterly-report-table'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'b. Publication','columns' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(['Journal Name/Conference', 'Research Title', 'Date', 'Personnel(s) involved']),'rows' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($reportData['publications'] ?? []),'empty-message' => 'No publications reported for this quarter.']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalb7c19b4af1a7b4c4c35273dfe4dcc698)): ?>
<?php $attributes = $__attributesOriginalb7c19b4af1a7b4c4c35273dfe4dcc698; ?>
<?php unset($__attributesOriginalb7c19b4af1a7b4c4c35273dfe4dcc698); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalb7c19b4af1a7b4c4c35273dfe4dcc698)): ?>
<?php $component = $__componentOriginalb7c19b4af1a7b4c4c35273dfe4dcc698; ?>
<?php unset($__componentOriginalb7c19b4af1a7b4c4c35273dfe4dcc698); ?>
<?php endif; ?>
                <?php if (isset($component)) { $__componentOriginalb7c19b4af1a7b4c4c35273dfe4dcc698 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalb7c19b4af1a7b4c4c35273dfe4dcc698 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.college-quarterly-report-table','data' => ['title' => 'c. Seminar/Training','columns' => ['Event/Activity', 'Date', 'Place', 'Personnel(s) involved'],'rows' => $report?->activityRows('seminars') ?? [],'emptyMessage' => 'No seminars or training reported for this quarter.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('college-quarterly-report-table'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'c. Seminar/Training','columns' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(['Event/Activity', 'Date', 'Place', 'Personnel(s) involved']),'rows' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($report?->activityRows('seminars') ?? []),'empty-message' => 'No seminars or training reported for this quarter.']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalb7c19b4af1a7b4c4c35273dfe4dcc698)): ?>
<?php $attributes = $__attributesOriginalb7c19b4af1a7b4c4c35273dfe4dcc698; ?>
<?php unset($__attributesOriginalb7c19b4af1a7b4c4c35273dfe4dcc698); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalb7c19b4af1a7b4c4c35273dfe4dcc698)): ?>
<?php $component = $__componentOriginalb7c19b4af1a7b4c4c35273dfe4dcc698; ?>
<?php unset($__componentOriginalb7c19b4af1a7b4c4c35273dfe4dcc698); ?>
<?php endif; ?>
            <?php else: ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = ['presentations' => 'a. Presentation', 'publications' => 'b. Publication', 'seminars' => 'c. Seminar/Training']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $section => $title): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <?php if (isset($component)) { $__componentOriginal19c87c30b9fbee436c666b0107e0e670 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal19c87c30b9fbee436c666b0107e0e670 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.college-quarterly-report-activities','data' => ['section' => $section,'title' => $title,'period' => $period,'projects' => $evidenceProjects]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('college-quarterly-report-activities'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['section' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($section),'title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($title),'period' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($period),'projects' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($evidenceProjects)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal19c87c30b9fbee436c666b0107e0e670)): ?>
<?php $attributes = $__attributesOriginal19c87c30b9fbee436c666b0107e0e670; ?>
<?php unset($__attributesOriginal19c87c30b9fbee436c666b0107e0e670); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal19c87c30b9fbee436c666b0107e0e670)): ?>
<?php $component = $__componentOriginal19c87c30b9fbee436c666b0107e0e670; ?>
<?php unset($__componentOriginal19c87c30b9fbee436c666b0107e0e670); ?>
<?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <div class="space-y-4">
                <?php if (isset($component)) { $__componentOriginalb7c19b4af1a7b4c4c35273dfe4dcc698 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalb7c19b4af1a7b4c4c35273dfe4dcc698 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.college-quarterly-report-table','data' => ['title' => 'II. Research of Faculty Members','columns' => ['Title', 'Duration', 'Personnel(s) involved', 'Status'],'rows' => $submitted ? $reportData['research'] : $automaticResearch,'emptyMessage' => 'No faculty research projects with an issued Notice to Proceed active during this quarter.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('college-quarterly-report-table'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'II. Research of Faculty Members','columns' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(['Title', 'Duration', 'Personnel(s) involved', 'Status']),'rows' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($submitted ? $reportData['research'] : $automaticResearch),'empty-message' => 'No faculty research projects with an issued Notice to Proceed active during this quarter.']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalb7c19b4af1a7b4c4c35273dfe4dcc698)): ?>
<?php $attributes = $__attributesOriginalb7c19b4af1a7b4c4c35273dfe4dcc698; ?>
<?php unset($__attributesOriginalb7c19b4af1a7b4c4c35273dfe4dcc698); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalb7c19b4af1a7b4c4c35273dfe4dcc698)): ?>
<?php $component = $__componentOriginalb7c19b4af1a7b4c4c35273dfe4dcc698; ?>
<?php unset($__componentOriginalb7c19b4af1a7b4c4c35273dfe4dcc698); ?>
<?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($submitted)): ?>
                    <?php if (isset($component)) { $__componentOriginal2423c0e5c2a7a7f070776f3adbee559c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2423c0e5c2a7a7f070776f3adbee559c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.college-quarterly-report-research-entries','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('college-quarterly-report-research-entries'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2423c0e5c2a7a7f070776f3adbee559c)): ?>
<?php $attributes = $__attributesOriginal2423c0e5c2a7a7f070776f3adbee559c; ?>
<?php unset($__attributesOriginal2423c0e5c2a7a7f070776f3adbee559c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2423c0e5c2a7a7f070776f3adbee559c)): ?>
<?php $component = $__componentOriginal2423c0e5c2a7a7f070776f3adbee559c; ?>
<?php unset($__componentOriginal2423c0e5c2a7a7f070776f3adbee559c); ?>
<?php endif; ?>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            <section class="dashboard-panel space-y-4 p-5" aria-labelledby="other-accomplishments-heading">
                <h2 id="other-accomplishments-heading" class="text-lg font-bold text-gray-950 dark:text-white">III. Other Accomplishments</h2>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($submitted): ?>
                    <div class="text-sm text-gray-700 dark:text-slate-300 [&_ol]:list-decimal [&_ul]:list-disc [&_ol]:pl-6 [&_ul]:pl-6 [&_p]:mb-3"><?php echo app(\App\Support\ProposalRichText::class)->sanitize($report->other_accomplishments ?: 'No other accomplishments reported.'); ?></div>
                <?php else: ?>
                    <label for="other-accomplishments" class="block text-sm text-slate-500 dark:text-slate-400">Describe other accomplishments from this quarter, if any.</label>
                    <textarea id="other-accomplishments" name="other_accomplishments" rows="6" maxlength="100000" data-semantic-editor data-semantic-editor-size="large" class="<?php echo e($inputClass); ?>"><?php echo e(old('other_accomplishments', $report?->other_accomplishments)); ?></textarea>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </section>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($submitted): ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! empty($reportData['ip_assets'])): ?>
                    <?php if (isset($component)) { $__componentOriginalb7c19b4af1a7b4c4c35273dfe4dcc698 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalb7c19b4af1a7b4c4c35273dfe4dcc698 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.college-quarterly-report-table','data' => ['title' => 'Intellectual Property Assets','columns' => ['Title', 'Type', 'Inventors / authors', 'Date Submitted', 'Remarks / evidence link'],'rows' => $report->activityRows('ip_assets')]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('college-quarterly-report-table'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Intellectual Property Assets','columns' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(['Title', 'Type', 'Inventors / authors', 'Date Submitted', 'Remarks / evidence link']),'rows' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($report->activityRows('ip_assets'))]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalb7c19b4af1a7b4c4c35273dfe4dcc698)): ?>
<?php $attributes = $__attributesOriginalb7c19b4af1a7b4c4c35273dfe4dcc698; ?>
<?php unset($__attributesOriginalb7c19b4af1a7b4c4c35273dfe4dcc698); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalb7c19b4af1a7b4c4c35273dfe4dcc698)): ?>
<?php $component = $__componentOriginalb7c19b4af1a7b4c4c35273dfe4dcc698; ?>
<?php unset($__componentOriginalb7c19b4af1a7b4c4c35273dfe4dcc698); ?>
<?php endif; ?>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php else: ?>
                <?php if (isset($component)) { $__componentOriginal1b674bac84e63492b8d33376fddd4ac0 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1b674bac84e63492b8d33376fddd4ac0 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.college-quarterly-report-ip-assets','data' => ['period' => $period]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('college-quarterly-report-ip-assets'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['period' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($period)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal1b674bac84e63492b8d33376fddd4ac0)): ?>
<?php $attributes = $__attributesOriginal1b674bac84e63492b8d33376fddd4ac0; ?>
<?php unset($__attributesOriginal1b674bac84e63492b8d33376fddd4ac0); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal1b674bac84e63492b8d33376fddd4ac0)): ?>
<?php $component = $__componentOriginal1b674bac84e63492b8d33376fddd4ac0; ?>
<?php unset($__componentOriginal1b674bac84e63492b8d33376fddd4ac0); ?>
<?php endif; ?>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <section class="dashboard-panel space-y-4 p-5" aria-labelledby="report-signatories-heading">
                <h2 id="report-signatories-heading" class="text-lg font-bold text-gray-950 dark:text-white">Report signatories</h2>
                <div class="grid gap-6 md:grid-cols-2">
                    <div class="space-y-2 text-sm">
                        <p class="font-semibold text-gray-700 dark:text-slate-300">Prepared and submitted by</p>
                        <p class="font-bold text-gray-950 dark:text-white"><?php echo e($reportData['prepared_by_name'] ?? $report?->preparedBy?->name ?? $coordinator->name); ?></p>
                        <p class="text-slate-500 dark:text-slate-400">Research Coordinator · <?php echo e($coordinator->college); ?></p>
                    </div>
                    <div class="space-y-3">
                        <p class="text-sm font-semibold text-gray-700 dark:text-slate-300">Checked and reviewed by</p>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($submitted): ?>
                            <p class="text-sm text-gray-700 dark:text-slate-300"><?php echo e($report->reviewed_by ?: '________________________'); ?><br><?php echo e($report->reviewed_by_title); ?></p>
                        <?php else: ?>
                            <div><?php if (isset($component)) { $__componentOriginale3da9d84bb64e4bc2eeebaafabfb2581 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginale3da9d84bb64e4bc2eeebaafabfb2581 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.input-label','data' => ['for' => 'reviewed-by','value' => 'Reviewer name (optional)']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('input-label'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['for' => 'reviewed-by','value' => 'Reviewer name (optional)']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginale3da9d84bb64e4bc2eeebaafabfb2581)): ?>
<?php $attributes = $__attributesOriginale3da9d84bb64e4bc2eeebaafabfb2581; ?>
<?php unset($__attributesOriginale3da9d84bb64e4bc2eeebaafabfb2581); ?>
<?php endif; ?>
<?php if (isset($__componentOriginale3da9d84bb64e4bc2eeebaafabfb2581)): ?>
<?php $component = $__componentOriginale3da9d84bb64e4bc2eeebaafabfb2581; ?>
<?php unset($__componentOriginale3da9d84bb64e4bc2eeebaafabfb2581); ?>
<?php endif; ?><input id="reviewed-by" name="reviewed_by" maxlength="255" value="<?php echo e(old('reviewed_by', $report?->reviewed_by)); ?>" class="<?php echo e($inputClass); ?> mt-1"></div>
                            <div><?php if (isset($component)) { $__componentOriginale3da9d84bb64e4bc2eeebaafabfb2581 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginale3da9d84bb64e4bc2eeebaafabfb2581 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.input-label','data' => ['for' => 'reviewed-by-title','value' => 'Reviewer title (optional)']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('input-label'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['for' => 'reviewed-by-title','value' => 'Reviewer title (optional)']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginale3da9d84bb64e4bc2eeebaafabfb2581)): ?>
<?php $attributes = $__attributesOriginale3da9d84bb64e4bc2eeebaafabfb2581; ?>
<?php unset($__attributesOriginale3da9d84bb64e4bc2eeebaafabfb2581); ?>
<?php endif; ?>
<?php if (isset($__componentOriginale3da9d84bb64e4bc2eeebaafabfb2581)): ?>
<?php $component = $__componentOriginale3da9d84bb64e4bc2eeebaafabfb2581; ?>
<?php unset($__componentOriginale3da9d84bb64e4bc2eeebaafabfb2581); ?>
<?php endif; ?><input id="reviewed-by-title" name="reviewed_by_title" maxlength="255" value="<?php echo e(old('reviewed_by_title', $report?->reviewed_by_title)); ?>" class="<?php echo e($inputClass); ?> mt-1"></div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400">Signatures and dates signed remain blank in the Word report for signing.</p>
            </section>

            <section class="dashboard-panel space-y-4 p-5" aria-labelledby="supporting-documentation-heading">
                <h2 id="supporting-documentation-heading" class="text-lg font-bold text-gray-950 dark:text-white">Supporting Documentation</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">Submitted faculty evidence for this quarter appears here and in the accomplishment report’s print preview and Word download. Faculty drafts remain private.</p>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $reportEvidence; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $evidence): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <?php if(! $submitted && isset($evidence['section'])) continue; ?>
                    <?php
                        $evidenceUrl = $evidence['url'] ?? route('research_coordinator.reports.evidence', ['report' => $report, 'index' => $index]);
                        $evidenceLabel = $report?->evidenceLabel($evidence) ?: ($evidence['caption'] ?? 'Supporting documentation '.($index + 1));
                        $isImage = in_array($evidence['mime_type'] ?? 'image/png', ['image/jpeg', 'image/png'], true);
                        $previewUrl = $evidenceUrl.(str_contains($evidenceUrl, '?') ? '&' : '?').'preview=1';
                    ?>
                    <figure class="space-y-2">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isImage): ?>
                            <a href="<?php echo e($evidence['download_url'] ?? $evidenceUrl); ?>" target="_blank" rel="noopener" class="inline-block rounded-lg focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand"><img src="<?php echo e($previewUrl); ?>" alt="<?php echo e($evidenceLabel); ?>" class="max-h-64 rounded-lg object-contain" loading="lazy" decoding="async"></a>
                        <?php else: ?>
                            <a href="<?php echo e($evidenceUrl); ?>" class="dashboard-action !justify-start break-all !text-left"><?php echo e($evidence['original_filename']); ?></a>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <figcaption class="text-sm text-gray-600 dark:text-slate-400"><?php echo e($evidenceLabel); ?></figcaption>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(filled($evidence['folder'] ?? null)): ?>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Evidence folder: <?php echo e($evidence['folder']); ?> / <?php echo e($year); ?> / Q<?php echo e($quarter); ?></p>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $submitted && ($evidence['source'] ?? null) !== 'faculty_report'): ?>
                            <?php ($evidenceCaptionToken = hash('sha256', $evidence['path'])); ?>
                            <div>
                                <label for="saved-evidence-caption-<?php echo e($evidenceCaptionToken); ?>" class="block text-sm font-medium text-gray-700 dark:text-slate-300">Caption</label>
                                <textarea id="saved-evidence-caption-<?php echo e($evidenceCaptionToken); ?>" name="saved_evidence_captions[<?php echo e($evidenceCaptionToken); ?>]" rows="2" maxlength="1000" class="<?php echo e($inputClass); ?> mt-1"><?php echo e(old('saved_evidence_captions.'.$evidenceCaptionToken, $evidence['caption'] ?? '')); ?></textarea>
                                <?php if (isset($component)) { $__componentOriginalf94ed9c5393ef72725d159fe01139746 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf94ed9c5393ef72725d159fe01139746 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.input-error','data' => ['messages' => $errors->get('saved_evidence_captions.'.$evidenceCaptionToken),'class' => 'mt-1']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('input-error'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['messages' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($errors->get('saved_evidence_captions.'.$evidenceCaptionToken)),'class' => 'mt-1']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalf94ed9c5393ef72725d159fe01139746)): ?>
<?php $attributes = $__attributesOriginalf94ed9c5393ef72725d159fe01139746; ?>
<?php unset($__attributesOriginalf94ed9c5393ef72725d159fe01139746); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalf94ed9c5393ef72725d159fe01139746)): ?>
<?php $component = $__componentOriginalf94ed9c5393ef72725d159fe01139746; ?>
<?php unset($__componentOriginalf94ed9c5393ef72725d159fe01139746); ?>
<?php endif; ?>
                            </div>
                            <label class="inline-flex items-center gap-2 text-sm text-red-700 dark:text-red-300"><input type="checkbox" name="remove_evidence[]" value="<?php echo e($evidenceCaptionToken); ?>" <?php if(in_array($evidenceCaptionToken, (array) old('remove_evidence', []), true)): echo 'checked'; endif; ?> class="rounded border-gray-300 text-brand focus:ring-brand"> Remove photo when saving</label>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </figure>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($submitted)): ?>
                    <p id="evidence-help" class="text-sm text-slate-500 dark:text-slate-400">Additional appendix photos are optional and do not replace row proof. JPEG or PNG, up to 5 MB each; up to 10 additional photos. Attach proof directly to each activity above.</p>
                    <label for="evidence-images" class="block text-sm font-medium text-gray-700 dark:text-slate-300">Add photos</label>
                    <input id="evidence-images" type="file" name="evidence_images[]" accept="image/jpeg,image/png" multiple aria-describedby="evidence-help" @change="attachments = Array.from($event.target.files)" class="block max-w-full text-sm text-gray-700 dark:text-slate-300">
                    <template x-for="(attachment, index) in attachments" :key="index">
                        <div>
                            <label :for="`evidence-caption-${index}`" class="block text-sm font-medium text-gray-700 dark:text-slate-300" x-text="`Caption for ${attachment.name}`"></label>
                            <input :id="`evidence-caption-${index}`" :name="`evidence_captions[${index}]`" maxlength="1000" class="<?php echo e($inputClass); ?> mt-1">
                        </div>
                    </template>
                <?php elseif(empty($reportEvidence)): ?>
                    <p class="text-sm text-slate-500 dark:text-slate-400">No supporting evidence attached.</p>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </section>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($submitted)): ?>
                <p class="text-sm text-slate-500 dark:text-slate-400">Use the report toolbar to save, preview, download, or submit. Each row in a, b, and c needs proof before final submission, which preserves this quarter’s entries and faculty research records.</p>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <input type="hidden" name="form_complete" value="1">
        </form>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($report?->submissions->isNotEmpty()): ?>
            <section class="dashboard-panel space-y-4 p-5" aria-labelledby="submission-history-heading">
                <h2 id="submission-history-heading" class="text-lg font-bold text-gray-950 dark:text-white">Submission history</h2>
                <ul class="space-y-3">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $report->submissions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $submission): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <li class="flex flex-wrap items-center justify-between gap-3 text-sm">
                            <span class="text-gray-700 dark:text-slate-300">Version <?php echo e($submission->version_number); ?> · <?php echo e($submission->submitted_at->format('M j, Y g:i A')); ?><?php echo e($submission->returned_at ? ' · Correction requested' : ''); ?></span>
                            <div class="flex flex-wrap gap-2">
                                <a href="<?php echo e(route('college-quarterly-submissions.print', $submission)); ?>" target="_blank" rel="noopener" class="dashboard-action">Preview version <?php echo e($submission->version_number); ?></a>
                                <a href="<?php echo e(route('college-quarterly-submissions.download', $submission)); ?>" class="dashboard-action">Download Word</a>
                            </div>
                        </li>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </ul>
            </section>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
        <?php if (isset($component)) { $__componentOriginal10a0e39c04a9eacf5d69b3b1628f0121 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal10a0e39c04a9eacf5d69b3b1628f0121 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-paper-preview','data' => ['panelId' => 'quarterly-report-preview-panel','previewLabel' => 'Quarterly accomplishment report preview','frameTitle' => 'Quarterly accomplishment report preview']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-paper-preview'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['panel-id' => 'quarterly-report-preview-panel','preview-label' => 'Quarterly accomplishment report preview','frame-title' => 'Quarterly accomplishment report preview']); ?>
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
        <button type="button" x-show="!previewPaneOpen" x-cloak @click="showProposalPreview()" class="proposal-writing-preview-launcher" aria-controls="quarterly-report-preview-panel" :aria-expanded="previewPaneOpen">Preview paper</button>
    </div>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $attributes = $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $component = $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\research_coordinator\reports.blade.php ENDPATH**/ ?>