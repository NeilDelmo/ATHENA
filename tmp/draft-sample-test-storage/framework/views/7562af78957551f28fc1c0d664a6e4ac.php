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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.page-header','data' => ['title' => $paper['label'],'subtitle' => 'Build the official BatStateU-FO-RES-02 Work Plan from structured inputs.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('page-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($paper['label']),'subtitle' => 'Build the official BatStateU-FO-RES-02 Work Plan from structured inputs.']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

             <?php $__env->slot('actions', null, []); ?> 
                <span class="rounded-full px-3 py-1 text-xs font-semibold <?php echo e($workPlanDocument?->completed_at ? 'bg-green-100 text-green-800' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'); ?>"><?php echo e($workPlanDocument?->completed_at ? 'Complete' : ($workPlanDocument ? 'In progress' : 'Not started')); ?></span>
                <?php if (isset($component)) { $__componentOriginal5426bd0bea02df2e6dd2a60e50fa4c01 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal5426bd0bea02df2e6dd2a60e50fa4c01 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.back-link','data' => ['fixed' => true,'dataPaperCancelExit' => true,'href' => ''.e(route('faculty.proposal-drafts.show', $proposalDraft)).'#required-pdf-attachments']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('back-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['fixed' => true,'data-paper-cancel-exit' => true,'href' => ''.e(route('faculty.proposal-drafts.show', $proposalDraft)).'#required-pdf-attachments']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>
Exit editor <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal5426bd0bea02df2e6dd2a60e50fa4c01)): ?>
<?php $attributes = $__attributesOriginal5426bd0bea02df2e6dd2a60e50fa4c01; ?>
<?php unset($__attributesOriginal5426bd0bea02df2e6dd2a60e50fa4c01); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal5426bd0bea02df2e6dd2a60e50fa4c01)): ?>
<?php $component = $__componentOriginal5426bd0bea02df2e6dd2a60e50fa4c01; ?>
<?php unset($__componentOriginal5426bd0bea02df2e6dd2a60e50fa4c01); ?>
<?php endif; ?>
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

    <?php
        $projectDetailsComplete = app(\App\Support\ProposalDraftReadiness::class)->projectDetailsAreComplete($proposalDraft);
        $initialEntries = $sourceData['entries'] ?? [];
        $sampleDefinition = config('proposal_samples.'.$paper['sample_slug']);
        $sampleAvailable = is_array($sampleDefinition)
            && isset($sampleDefinition['path'])
            && \Illuminate\Support\Facades\Storage::disk('local')->exists($sampleDefinition['path']);
    ?>

    <div
        class="work-plan-writing-workspace mx-auto w-full space-y-6 px-4 py-8 sm:px-6 lg:px-8"
        data-proposal-paper-workspace
        data-work-plan-workspace
        @focusin="focusWorkPlanEntry($event)"
        data-paper-editor
        data-paper-draft-save="true"
        data-work-plan-autosave="true"
        data-paper-project-details-complete="<?php echo e($projectDetailsComplete ? 'true' : 'false'); ?>"
        data-paper-dirty="<?php echo e($errors->any() ? 'true' : 'false'); ?>"
        data-paper-edit-url="<?php echo e(route('faculty.proposal-drafts.work-plan.edit', $proposalDraft)); ?>"
        data-paper-exit-url="<?php echo e(route('faculty.proposal-drafts.show', $proposalDraft)); ?>#required-pdf-attachments"
        x-data="proposalDraftWorkPlan({
            initialEntries: <?php echo \Illuminate\Support\Js::from($initialEntries)->toHtml() ?>,
            objectivesLinked: true,
            maxEntries: <?php echo \Illuminate\Support\Js::from(config('work_plan.max_objectives'))->toHtml() ?>,
            durationMonths: <?php echo \Illuminate\Support\Js::from($proposalDraft->duration_months ?: 12)->toHtml() ?>,
            previewUrl: <?php echo \Illuminate\Support\Js::from(route('faculty.proposal-drafts.work-plan.preview', $proposalDraft))->toHtml() ?>,
            downloadUrl: <?php echo \Illuminate\Support\Js::from(route('faculty.proposal-drafts.work-plan.download', $proposalDraft))->toHtml() ?>,
            updateUrl: <?php echo \Illuminate\Support\Js::from(route('faculty.proposal-drafts.work-plan.update', $proposalDraft))->toHtml() ?>,
            csrfToken: <?php echo \Illuminate\Support\Js::from(csrf_token())->toHtml() ?>,
            revisionUploadUrl: <?php echo \Illuminate\Support\Js::from($proposalDraft->topic_id ? route('faculty.proposal-drafts.revision-files.store', $proposalDraft) : null)->toHtml() ?>,
            revisionDocumentType: <?php echo \Illuminate\Support\Js::from($paper['document_type'])->toHtml() ?>,
            revisionAttachmentLabel: <?php echo \Illuminate\Support\Js::from($paper['label'])->toHtml() ?>,
            revisionReviewUrl: <?php echo \Illuminate\Support\Js::from($proposalDraft->topic_id ? route('faculty.topics.revision', $proposalDraft->topic_id).'#review-and-submit' : null)->toHtml() ?>,
            revisionTarget: <?php echo \Illuminate\Support\Js::from(request()->query('revision_target'))->toHtml() ?>,
        })"
    >
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('success')): ?>
            <?php if (isset($component)) { $__componentOriginal06a6d047fcfbc4adc19f6beb6fa14107 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal06a6d047fcfbc4adc19f6beb6fa14107 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-alert','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-alert'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>
<?php echo e(session('success')); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal06a6d047fcfbc4adc19f6beb6fa14107)): ?>
<?php $attributes = $__attributesOriginal06a6d047fcfbc4adc19f6beb6fa14107; ?>
<?php unset($__attributesOriginal06a6d047fcfbc4adc19f6beb6fa14107); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal06a6d047fcfbc4adc19f6beb6fa14107)): ?>
<?php $component = $__componentOriginal06a6d047fcfbc4adc19f6beb6fa14107; ?>
<?php unset($__componentOriginal06a6d047fcfbc4adc19f6beb6fa14107); ?>
<?php endif; ?>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?>
            <?php if (isset($component)) { $__componentOriginal06a6d047fcfbc4adc19f6beb6fa14107 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal06a6d047fcfbc4adc19f6beb6fa14107 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-alert','data' => ['type' => 'error']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-alert'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'error']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

                <p class="font-bold">The Work Plan could not be saved.</p>
                <ul class="mt-1 list-disc space-y-1 pl-5"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><li><?php echo e($error); ?></li><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?></ul>
             <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal06a6d047fcfbc4adc19f6beb6fa14107)): ?>
<?php $attributes = $__attributesOriginal06a6d047fcfbc4adc19f6beb6fa14107; ?>
<?php unset($__attributesOriginal06a6d047fcfbc4adc19f6beb6fa14107); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal06a6d047fcfbc4adc19f6beb6fa14107)): ?>
<?php $component = $__componentOriginal06a6d047fcfbc4adc19f6beb6fa14107; ?>
<?php unset($__componentOriginal06a6d047fcfbc4adc19f6beb6fa14107); ?>
<?php endif; ?>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <div x-show="validationMessage" x-cloak role="alert" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800" x-text="validationMessage"></div>

        <?php if (isset($component)) { $__componentOriginal35106a2c647ef3bd554b372324bfe098 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal35106a2c647ef3bd554b372324bfe098 = $attributes; } ?>
<?php $component = App\View\Components\ProposalRevisionContext::resolve(['proposalDraft' => $proposalDraft,'documentType' => $paper['document_type']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-revision-context'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\ProposalRevisionContext::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal35106a2c647ef3bd554b372324bfe098)): ?>
<?php $attributes = $__attributesOriginal35106a2c647ef3bd554b372324bfe098; ?>
<?php unset($__attributesOriginal35106a2c647ef3bd554b372324bfe098); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal35106a2c647ef3bd554b372324bfe098)): ?>
<?php $component = $__componentOriginal35106a2c647ef3bd554b372324bfe098; ?>
<?php unset($__componentOriginal35106a2c647ef3bd554b372324bfe098); ?>
<?php endif; ?>
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
        <?php if (isset($component)) { $__componentOriginal365c40492913100a7be7d48ba061239f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal365c40492913100a7be7d48ba061239f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-collaboration-monitor','data' => ['loadedVersion' => (int) old('document_version', $workPlanDocument?->lock_version ?? 0),'stateUrl' => route('faculty.proposal-drafts.edit-state', [$proposalDraft, $paper['document_type'], 0]),'reloadUrl' => route('faculty.proposal-drafts.work-plan.edit', $proposalDraft),'label' => $paper['label']]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-collaboration-monitor'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['loaded-version' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute((int) old('document_version', $workPlanDocument?->lock_version ?? 0)),'state-url' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('faculty.proposal-drafts.edit-state', [$proposalDraft, $paper['document_type'], 0])),'reload-url' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('faculty.proposal-drafts.work-plan.edit', $proposalDraft)),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($paper['label'])]); ?>
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

        <?php if (isset($component)) { $__componentOriginalb166750fc1b08ee44e679b8d084e40ec = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalb166750fc1b08ee44e679b8d084e40ec = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.work-plan-writing-toolbar','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('work-plan-writing-toolbar'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalb166750fc1b08ee44e679b8d084e40ec)): ?>
<?php $attributes = $__attributesOriginalb166750fc1b08ee44e679b8d084e40ec; ?>
<?php unset($__attributesOriginalb166750fc1b08ee44e679b8d084e40ec); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalb166750fc1b08ee44e679b8d084e40ec)): ?>
<?php $component = $__componentOriginalb166750fc1b08ee44e679b8d084e40ec; ?>
<?php unset($__componentOriginalb166750fc1b08ee44e679b8d084e40ec); ?>
<?php endif; ?>
        <div class="proposal-preview-workspace proposal-writing-columns" :class="{ 'proposal-writing-preview-hidden': !previewPaneOpen }" @resize.window.debounce.150ms="resizeProposalPaperPreview()">
        <div class="proposal-edit-pane space-y-6" :inert="previewFullscreen" aria-label="Work Plan editing form">

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($projectDetailsComplete)): ?>
            <div role="alert" class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">
                <p class="font-black">Complete Project Details first</p>
                <p class="mt-1 leading-6">Project title, duration, planned dates, and project leader are required before Attachment A can be previewed or generated. You can still save your progress as a draft.</p>
                <a href="<?php echo e(route('faculty.proposal-drafts.details.edit', $proposalDraft)); ?>" class="mt-3 inline-flex rounded-xl bg-amber-900 px-4 py-2.5 text-xs font-bold text-white focus:outline-none focus:ring-2 focus:ring-amber-900 focus:ring-offset-2">Complete Project Details</a>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <section data-revision-section="section-project-information" data-revision-shared-summary class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div><h3 class="text-base font-black text-gray-900">Shared project information</h3><p class="mt-1 text-xs text-gray-500">Edit these values from Project Details; they are applied automatically to the paper.</p></div>
                <div class="flex gap-2">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($sampleAvailable): ?><a href="<?php echo e(route('proposal-samples.show', $paper['sample_slug'])); ?>" target="_blank" rel="noopener" class="inline-flex rounded-xl border border-gray-300 px-3 py-2 text-xs font-bold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-600 focus:ring-offset-2">View sample</a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <a href="<?php echo e(route('faculty.proposal-drafts.details.edit', $proposalDraft)); ?>" class="inline-flex rounded-xl border border-red-200 px-3 py-2 text-xs font-bold text-red-700 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2">Edit details</a>
                </div>
            </div>
            <dl class="mt-5 grid gap-4 border-t border-gray-100 pt-5 sm:grid-cols-2">
                <div class="sm:col-span-2"><dt class="text-[10px] font-black uppercase tracking-wider text-gray-500">Project Title</dt><dd class="mt-1 text-sm font-semibold text-gray-900"><?php echo e($proposalDraft->project_title); ?></dd></div>
                <div><dt class="text-[10px] font-black uppercase tracking-wider text-gray-500">Duration</dt><dd class="mt-1 text-sm font-semibold text-gray-900"><?php echo e($proposalDraft->duration_months ? $proposalDraft->duration_months.' months' : 'Not provided'); ?></dd></div>
                <div><dt class="text-[10px] font-black uppercase tracking-wider text-gray-500">Planned Start</dt><dd class="mt-1 text-sm font-semibold text-gray-900"><?php echo e($proposalDraft->planned_start?->format('M j, Y') ?? 'Not provided'); ?></dd></div>
                <div><dt class="text-[10px] font-black uppercase tracking-wider text-gray-500">Planned End</dt><dd class="mt-1 text-sm font-semibold text-gray-900"><?php echo e($proposalDraft->planned_end?->format('M j, Y') ?? 'Not provided'); ?></dd></div>
                <div class="sm:col-span-2"><dt class="text-[10px] font-black uppercase tracking-wider text-gray-500">Project Leader / Prepared by</dt><dd class="mt-1 text-sm font-semibold text-gray-900"><?php echo e($proposalDraft->project_leader ?: 'Not provided'); ?></dd></div>
            </dl>
        </section>

        <form data-paper-form data-work-plan-autosave-form x-ref="form" action="<?php echo e(route('faculty.proposal-drafts.work-plan.update', $proposalDraft)); ?>" method="POST" class="space-y-6" novalidate>
            <?php echo csrf_field(); ?>
            <?php echo method_field('PUT'); ?>
            <input type="hidden" name="document_version" value="<?php echo e(old('document_version', $workPlanDocument?->lock_version ?? 0)); ?>">
            <input type="hidden" name="save_as_draft" value="0" data-paper-save-mode>

            <section data-revision-section="section-schedule" aria-labelledby="work-plan-objectives-heading" class="space-y-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h3 id="work-plan-objectives-heading" class="text-lg font-black text-gray-900">Objectives and Gantt schedule</h3>
                        <p class="mt-1 text-sm text-gray-500">Specific objectives come from your Detailed Proposal. Add activities, expected outputs, and active months for each one. Each month can belong to only one objective.</p>
                        <a href="<?php echo e(route('faculty.proposal-drafts.detailed-proposal.edit', $proposalDraft)); ?>" class="mt-2 inline-flex min-h-10 items-center text-sm font-semibold text-red-700 underline underline-offset-4 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-600">Edit objectives in Detailed Proposal</a>
                        <p class="mt-1 text-xs text-gray-400">The generated paper automatically expands each row to fit the longest objective, output, or activity text.</p>
                    </div>
                </div>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($linkedObjectives === []): ?>
                    <p class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200">Save your specific objectives in the Detailed Proposal first. They will appear here automatically.</p>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <template x-for="(entry, index) in entries" :key="entry.id">
                    <article x-bind:data-repeatable-entry="`work-plan-entry-${entry.id}`" :data-work-plan-entry-id="entry.id" x-bind:class="isEntryExpanded(entry) ? 'border-red-200 bg-white' : 'border-gray-200 bg-gray-50'" class="work-plan-writing-entry rounded-xl border p-4 transition-colors sm:p-5">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <p class="text-xs font-black uppercase tracking-wider text-gray-500">Objective <span x-text="index + 1"></span></p>
                                <h4 class="mt-1 truncate text-sm font-black text-gray-900" x-text="entrySummary(entry)"></h4>
                                <p x-show="!isEntryExpanded(entry)" x-cloak class="mt-1 text-xs text-gray-500" x-text="entryScheduleSummary(entry)"></p>
                            </div>
                            <div class="flex shrink-0 items-center gap-2">
                                <button type="button" x-on:click="toggleEntry(entry)" x-bind:aria-expanded="isEntryExpanded(entry)" x-bind:aria-controls="`work-plan-editor-${entry.id}`" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-bold text-gray-700 hover:border-red-300 hover:bg-red-50 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-600">
                                    <span x-show="isEntryExpanded(entry)">Collapse</span>
                                    <span x-show="!isEntryExpanded(entry)" x-cloak>Edit</span>
                                </button>
                            </div>
                        </div>

                        <div x-bind:id="`work-plan-editor-${entry.id}`" x-show="isEntryExpanded(entry)" x-cloak x-transition class="mt-5">
                            <div class="work-plan-writing-fields grid gap-4">
                                <div class="work-plan-writing-objective">
                                    <label class="block text-xs font-black uppercase tracking-wider text-gray-600" x-bind:for="`objective-${entry.id}`">Objective from Detailed Proposal</label>
                                    <textarea x-bind:id="`objective-${entry.id}`" x-bind:name="`entries[${index}][objective]`" x-bind:data-work-plan-objective-input="entry.id" x-model="entry.objective" rows="4" readonly required class="mt-2 block w-full rounded-xl border-gray-200 bg-gray-50 text-sm text-gray-900 shadow-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-700 dark:bg-slate-800 dark:text-white"></textarea>
                                </div>
                                <div>
                                    <label class="block text-xs font-black uppercase tracking-wider text-gray-600" x-bind:for="`output-${entry.id}`">Expected Output <span class="text-red-600">Required</span></label>
                                    <textarea x-bind:id="`output-${entry.id}`" x-bind:name="`entries[${index}][expected_output]`" x-model="entry.expectedOutput" rows="4" maxlength="500" required class="mt-2 block w-full rounded-xl border-gray-300 text-sm text-gray-900 shadow-sm focus:border-red-600 focus:ring-red-600"></textarea>
                                </div>
                                <div>
                                    <label class="block text-xs font-black uppercase tracking-wider text-gray-600" x-bind:for="`activity-${entry.id}`">Activities or Workplan <span class="text-red-600">Required</span></label>
                                    <textarea x-bind:id="`activity-${entry.id}`" x-bind:name="`entries[${index}][activity]`" x-model="entry.activity" rows="4" maxlength="1500" required class="mt-2 block w-full rounded-xl border-gray-300 text-sm text-gray-900 shadow-sm focus:border-red-600 focus:ring-red-600"></textarea>
                                </div>
                            </div>

                            <fieldset x-bind:data-work-plan-schedule="entry.id" class="mt-5">
                                <legend class="text-xs font-black uppercase tracking-wider text-gray-600">Gantt Schedule <span class="text-red-600">Required</span></legend>
                                <p class="mt-2 text-xs leading-5 text-gray-500">Each 12-month block becomes a matching Attachment A year sheet. Months assigned to another objective are locked until they are removed from that objective.</p>
                                <div class="mt-3 grid gap-4">
                                    <template x-for="yearGroup in yearGroups" :key="yearGroup.year">
                                        <section class="rounded-xl border border-gray-200 bg-gray-50 p-3" x-bind:aria-label="`Year ${yearGroup.year} schedule`">
                                            <div class="flex flex-wrap items-center justify-between gap-2">
                                                <p class="text-xs font-black uppercase tracking-wider text-gray-700" x-text="`Y${yearGroup.year}`"></p>
                                                <p class="text-[10px] font-semibold text-gray-500" x-text="`Project months ${yearGroup.months[0]}-${yearGroup.months[yearGroup.months.length - 1]}`"></p>
                                            </div>
                                            <div class="work-plan-writing-months mt-2 grid gap-2">
                                                <template x-for="month in yearGroup.months" :key="month">
                                                    <label
                                                        class="relative flex cursor-pointer flex-col items-center justify-center rounded-xl border px-2 py-2.5 text-xs font-black transition focus-within:ring-2 focus-within:ring-red-600 focus-within:ring-offset-2"
                                                        x-bind:class="entry.months.includes(month) ? 'border-red-600 bg-red-50 text-red-700' : (isMonthSelectable(index, month) ? 'border-gray-200 bg-white text-gray-600 hover:border-gray-300' : 'cursor-not-allowed border-amber-200 bg-amber-50 text-amber-700')"
                                                        x-bind:title="monthSelectionTitle(index, month)"
                                                    >
                                                        <input type="checkbox" class="sr-only" x-bind:name="`entries[${index}][months][]`" x-bind:value="month" x-model.number="entry.months" x-bind:disabled="!isMonthSelectable(index, month)" x-on:change="clearMonthError(index)">
                                                        <span x-text="`M${localMonthNumber(month)}`"></span>
                                                        <span x-show="yearGroup.year > 1" class="mt-0.5 text-[9px] font-semibold text-gray-500" x-text="`Project M${month}`"></span>
                                                        <span x-show="monthOwnerLabel(index, month)" x-text="monthOwnerLabel(index, month)" class="mt-0.5 text-[9px] font-bold uppercase tracking-wide"></span>
                                                    </label>
                                                </template>
                                            </div>
                                        </section>
                                    </template>
                                </div>
                                <p x-show="monthErrorIndexes.includes(index)" x-cloak class="mt-2 text-xs font-semibold text-red-600">Select at least one month for this objective.</p>
                                <p x-show="monthConflictIndexes.includes(index)" x-cloak class="mt-2 text-xs font-semibold text-red-600">This objective shares a month with an earlier objective. Remove the duplicate month.</p>
                            </fieldset>
                        </div>
                    </article>
                </template>

            </section>

            <?php if (isset($component)) { $__componentOriginalcd93ff08fd3bc5e3afdf57ccf1848309 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalcd93ff08fd3bc5e3afdf57ccf1848309 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-signatory-summary','data' => ['proposalDraft' => $proposalDraft,'paper' => 'work_plan']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-signatory-summary'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['proposal-draft' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($proposalDraft),'paper' => 'work_plan']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalcd93ff08fd3bc5e3afdf57ccf1848309)): ?>
<?php $attributes = $__attributesOriginalcd93ff08fd3bc5e3afdf57ccf1848309; ?>
<?php unset($__attributesOriginalcd93ff08fd3bc5e3afdf57ccf1848309); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalcd93ff08fd3bc5e3afdf57ccf1848309)): ?>
<?php $component = $__componentOriginalcd93ff08fd3bc5e3afdf57ccf1848309; ?>
<?php unset($__componentOriginalcd93ff08fd3bc5e3afdf57ccf1848309); ?>
<?php endif; ?>

            <noscript>
                <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl bg-red-600 px-5 py-3 text-sm font-bold text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 sm:w-auto">Save Work Plan</button>
            </noscript>
        </form>

        <div x-show="previewError || downloadError" x-cloak role="alert" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><span x-text="previewError || downloadError"></span></div>

        </div>
        <?php if (isset($component)) { $__componentOriginal10a0e39c04a9eacf5d69b3b1628f0121 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal10a0e39c04a9eacf5d69b3b1628f0121 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-paper-preview','data' => ['panelId' => 'work-plan-preview-panel','previewLabel' => 'Work Plan preview','frameTitle' => 'Attachment A Work Plan preview']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-paper-preview'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['panel-id' => 'work-plan-preview-panel','preview-label' => 'Work Plan preview','frame-title' => 'Attachment A Work Plan preview']); ?>
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
        <button type="button" x-show="!previewPaneOpen" x-cloak @click="showProposalPreview()" aria-controls="work-plan-preview-panel" :aria-expanded="previewPaneOpen" class="proposal-writing-preview-launcher">Preview paper</button>
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
<?php /**PATH C:\laragon\www\athena-app\src\resources\views/faculty/proposal-drafts/work-plan/edit.blade.php ENDPATH**/ ?>