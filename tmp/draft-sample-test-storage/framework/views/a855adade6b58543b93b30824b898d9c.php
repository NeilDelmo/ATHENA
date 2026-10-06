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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.page-header','data' => ['title' => $paper['label'],'subtitle' => 'Use MOOE, Capital Outlay, or both. Add only expenses that apply to your project; unused categories total zero.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('page-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($paper['label']),'subtitle' => 'Use MOOE, Capital Outlay, or both. Add only expenses that apply to your project; unused categories total zero.']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

             <?php $__env->slot('actions', null, []); ?> 
                <span class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-wider <?php echo e(($budgetConsistency['available'] ?? false) && ! ($budgetConsistency['consistent'] ?? true) ? 'bg-red-100 text-red-800' : ($expenseBreakdownDocument?->completed_at ? 'bg-green-100 text-green-800' : ($expenseBreakdownDocument ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-600'))); ?>"><?php echo e(($budgetConsistency['available'] ?? false) && ! ($budgetConsistency['consistent'] ?? true) ? 'Needs attention' : ($expenseBreakdownDocument?->completed_at ? 'Complete' : ($expenseBreakdownDocument ? 'In progress' : 'Not started'))); ?></span>
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
        $initialData = array_replace($sourceData, old());
        $sampleDefinition = config('proposal_samples.'.$paper['sample_slug']);
        $sampleAvailable = is_array($sampleDefinition)
            && isset($sampleDefinition['path'])
            && \Illuminate\Support\Facades\Storage::disk('local')->exists($sampleDefinition['path']);
    ?>

    <div
        class="mx-auto w-full space-y-6 px-4 py-8 sm:px-6 lg:px-8"
        data-proposal-paper-workspace
        data-expense-breakdown-workspace
        data-paper-editor
        data-paper-draft-save="true"
        data-expense-breakdown-autosave="true"
        data-paper-project-details-complete="<?php echo e($projectDetailsComplete ? 'true' : 'false'); ?>"
        data-paper-dirty="<?php echo e($errors->any() ? 'true' : 'false'); ?>"
        data-paper-edit-url="<?php echo e(route('faculty.proposal-drafts.expense-breakdown.edit', $proposalDraft)); ?>"
        data-paper-exit-url="<?php echo e(route('faculty.proposal-drafts.show', $proposalDraft)); ?>#required-pdf-attachments"
        x-data="proposalDraftExpenseBreakdown({
            initialData: <?php echo \Illuminate\Support\Js::from($initialData)->toHtml() ?>,
            previewUrl: <?php echo \Illuminate\Support\Js::from(route('faculty.proposal-drafts.expense-breakdown.preview', $proposalDraft))->toHtml() ?>,
            downloadUrl: <?php echo \Illuminate\Support\Js::from(route('faculty.proposal-drafts.expense-breakdown.download', $proposalDraft))->toHtml() ?>,
            updateUrl: <?php echo \Illuminate\Support\Js::from(route('faculty.proposal-drafts.expense-breakdown.update', $proposalDraft))->toHtml() ?>,
            csrfToken: <?php echo \Illuminate\Support\Js::from(csrf_token())->toHtml() ?>,
            revisionUploadUrl: <?php echo \Illuminate\Support\Js::from($proposalDraft->topic_id ? route('faculty.proposal-drafts.revision-files.store', $proposalDraft) : null)->toHtml() ?>,
            revisionDocumentType: <?php echo \Illuminate\Support\Js::from($paper['document_type'])->toHtml() ?>,
            revisionAttachmentLabel: <?php echo \Illuminate\Support\Js::from($paper['label'])->toHtml() ?>,
            revisionReviewUrl: <?php echo \Illuminate\Support\Js::from($proposalDraft->topic_id ? route('faculty.topics.revision', $proposalDraft->topic_id).'#review-and-submit' : null)->toHtml() ?>,
            accountCatalog: <?php echo \Illuminate\Support\Js::from(config('expense_breakdown.accounts'))->toHtml() ?>,
            budgetCeiling: <?php echo \Illuminate\Support\Js::from($budgetCeiling)->toHtml() ?>,
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

                <p class="font-bold">The Estimated Expense Breakdown could not be saved.</p>
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

        <?php if (isset($component)) { $__componentOriginal63eda02c255a6e88714864bdf2b39e97 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal63eda02c255a6e88714864bdf2b39e97 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.budget-consistency-warning','data' => ['comparison' => $budgetConsistency,'proposalDraft' => $proposalDraft]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('budget-consistency-warning'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['comparison' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($budgetConsistency),'proposal-draft' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($proposalDraft)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal63eda02c255a6e88714864bdf2b39e97)): ?>
<?php $attributes = $__attributesOriginal63eda02c255a6e88714864bdf2b39e97; ?>
<?php unset($__attributesOriginal63eda02c255a6e88714864bdf2b39e97); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal63eda02c255a6e88714864bdf2b39e97)): ?>
<?php $component = $__componentOriginal63eda02c255a6e88714864bdf2b39e97; ?>
<?php unset($__componentOriginal63eda02c255a6e88714864bdf2b39e97); ?>
<?php endif; ?>

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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-collaboration-monitor','data' => ['loadedVersion' => (int) old('document_version', $expenseBreakdownDocument?->lock_version ?? 0),'stateUrl' => route('faculty.proposal-drafts.edit-state', [$proposalDraft, $paper['document_type'], 0]),'reloadUrl' => route('faculty.proposal-drafts.expense-breakdown.edit', $proposalDraft),'label' => $paper['label']]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-collaboration-monitor'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['loaded-version' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute((int) old('document_version', $expenseBreakdownDocument?->lock_version ?? 0)),'state-url' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('faculty.proposal-drafts.edit-state', [$proposalDraft, $paper['document_type'], 0])),'reload-url' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('faculty.proposal-drafts.expense-breakdown.edit', $proposalDraft)),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($paper['label'])]); ?>
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

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($projectDetailsComplete)): ?>
            <div role="alert" class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">
                <p class="font-black">Complete Project Details first</p>
                <p class="mt-1 leading-6">The shared project title and required project information are needed before this paper can be previewed or generated. You can still save your progress as a draft.</p>
                <a href="<?php echo e(route('faculty.proposal-drafts.details.edit', $proposalDraft)); ?>" class="mt-3 inline-flex rounded-xl bg-amber-900 px-4 py-2.5 text-xs font-bold text-white focus:outline-none focus:ring-2 focus:ring-amber-900 focus:ring-offset-2">Complete Project Details</a>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <div class="proposal-preview-workspace proposal-writing-columns" :class="{ 'proposal-writing-preview-hidden': !previewPaneOpen }" @resize.window.debounce.150ms="resizeProposalPaperPreview()">
        <div class="proposal-edit-pane space-y-6" :inert="previewFullscreen" aria-label="Estimated Expense Breakdown editing form">
        <section data-revision-section="section-project-information" data-revision-shared-summary class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h3 class="text-base font-black text-gray-900">Shared project information</h3>
                    <p class="mt-1 text-xs leading-5 text-gray-500">The Project Title is taken from Project Details and printed above the official expense table.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($sampleAvailable): ?><a href="<?php echo e(route('proposal-samples.show', $paper['sample_slug'])); ?>" target="_blank" rel="noopener" class="inline-flex rounded-xl border border-gray-300 px-3 py-2 text-xs font-bold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-600 focus:ring-offset-2">View official sample</a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <a href="<?php echo e(route('faculty.proposal-drafts.details.edit', $proposalDraft)); ?>" class="inline-flex rounded-xl border border-red-200 px-3 py-2 text-xs font-bold text-red-700 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2">Edit details</a>
                </div>
            </div>
            <dl class="mt-5 grid gap-4 border-t border-gray-100 pt-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <dt class="text-[10px] font-black uppercase tracking-wider text-gray-500">Project Title <span class="text-red-600" title="Required" aria-label="Required">*</span></dt>
                    <dd class="mt-1 text-sm text-gray-900"><?php echo e($proposalDraft->project_title ?: 'Not provided'); ?></dd>
                </div>
                <div>
                    <dt class="text-[10px] font-black uppercase tracking-wider text-gray-500">Institutional budget limit</dt>
                    <dd class="mt-1 text-sm font-semibold text-gray-900">PHP <?php echo e(number_format($budgetCeiling, 2)); ?></dd>
                </div>
            </dl>
        </section>

        <form data-paper-form data-expense-breakdown-autosave-form x-ref="form" action="<?php echo e(route('faculty.proposal-drafts.expense-breakdown.update', $proposalDraft)); ?>" method="POST" class="space-y-6" novalidate>
            <?php echo csrf_field(); ?>
            <?php echo method_field('PUT'); ?>
            <input type="hidden" name="document_version" value="<?php echo e(old('document_version', $expenseBreakdownDocument?->lock_version ?? 0)); ?>">
            <input type="hidden" name="save_as_draft" value="0" data-paper-save-mode>

            <section data-revision-section="section-expense-items" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h3 class="text-base font-black text-gray-900">Expense items</h3>
                        <p class="mt-1 max-w-3xl text-xs leading-5 text-gray-500">Use MOOE, Capital Outlay, or both. Only add expense items for categories that apply to your project; an unused category has a zero total. Remove any unused expense rows.</p>
                        <p class="mt-2 text-[11px] text-gray-500"><span class="text-red-600" aria-hidden="true">*</span> Required for each entered expense item. At least one expense item overall is required to complete this paper, not one in each category.</p>
                    </div>
                    <button type="button" x-on:click="addItem(true)" class="inline-flex w-full items-center justify-center rounded-xl border border-red-200 px-4 py-2.5 text-xs font-bold text-red-700 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 sm:w-auto">Add expense item</button>
                </div>

                <div class="mt-5 space-y-4">
                    <template x-for="(item, index) in items" :key="item.id">
                        <article x-bind:data-repeatable-entry="`expense-item-${item.id}`" x-bind:class="isItemExpanded(item) ? 'border-red-200 bg-white' : 'border-gray-200 bg-gray-50'" class="expense-writing-item rounded-2xl border p-4 shadow-sm transition-colors sm:p-5">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0">
                                    <p class="text-xs font-black uppercase tracking-wider text-gray-500">Expense item <span x-text="index + 1"></span></p>
                                    <h4 class="mt-1 truncate text-sm font-black text-gray-900" x-text="itemSummary(item)"></h4>
                                    <p class="mt-1 text-xs text-gray-500" x-text="itemGroupingSummary(item)"></p>
                                    <p class="mt-1 text-sm font-black text-gray-900">Php <span x-text="formatMoney(itemTotal(item))"></span></p>
                                </div>
                                <div class="flex shrink-0 items-center gap-2">
                                    <button type="button" x-on:click="toggleItem(item)" x-bind:aria-expanded="isItemExpanded(item)" x-bind:aria-controls="`expense-item-editor-${item.id}`" class="rounded-xl border border-gray-300 bg-white px-3 py-2 text-xs font-bold text-gray-700 hover:border-red-300 hover:bg-red-50 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-600">
                                        <span x-show="isItemExpanded(item)">Collapse</span>
                                        <span x-show="!isItemExpanded(item)" x-cloak>Edit</span>
                                    </button>
                                    <button type="button" x-on:click="removeItem(index)" x-bind:disabled="items.length === 1" class="rounded-xl px-3 py-2 text-xs font-bold text-red-700 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-red-600 disabled:cursor-not-allowed disabled:opacity-40">Remove</button>
                                </div>
                            </div>

                            <div x-bind:id="`expense-item-editor-${item.id}`" x-show="isItemExpanded(item)" x-cloak x-transition class="mt-4">
                                <div class="expense-writing-grouping grid gap-4">
                                <div>
                                    <label class="block text-[10px] font-black uppercase tracking-wider text-gray-600" :for="`expense-category-${item.id}`">Expense type <span class="text-red-600" title="Required" aria-label="Required">*</span></label>
                                    <select :id="`expense-category-${item.id}`" :name="`items[${index}][category]`" x-model="item.category" x-on:change="$nextTick(() => syncGrouping(item, true))" required class="mt-1.5 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = config('expense_breakdown.categories'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $categoryKey => $categoryLabel): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                            <option value="<?php echo e($categoryKey); ?>"><?php echo e($categoryLabel); ?></option>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-black uppercase tracking-wider text-gray-600" :for="`expense-account-${item.id}`">Account <span class="text-red-600" title="Required" aria-label="Required">*</span></label>
                                    <select :id="`expense-account-${item.id}`" x-model="item.account" x-on:change="$nextTick(() => syncGrouping(item))" required class="mt-1.5 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600">
                                        <option value="">Select an official account</option>
                                        <template x-for="account in accountsFor(item)" :key="account.label">
                                            <option :value="account.label" :selected="account.label === item.account" x-text="account.label"></option>
                                        </template>
                                    </select>
                                    <input type="hidden" :name="`items[${index}][account]`" :value="item.account">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-black uppercase tracking-wider text-gray-600" :for="`expense-sub-account-${item.id}`">Sub-account <span class="text-red-600" title="Required" aria-label="Required">*</span></label>
                                    <select :id="`expense-sub-account-${item.id}`" x-model="item.sub_account" required class="mt-1.5 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600">
                                        <option value="">Select an official sub-account</option>
                                        <template x-for="subAccount in subAccountsFor(item)" :key="subAccount.label">
                                            <option :value="subAccount.label" :selected="subAccount.label === item.sub_account" x-text="subAccount.label"></option>
                                        </template>
                                    </select>
                                    <input type="hidden" :name="`items[${index}][sub_account]`" :value="item.sub_account">
                                </div>
                            </div>

                            <template x-if="!isContingency(item)">
                                <div>
                                    <div class="expense-writing-costs mt-4 grid gap-4">
                                        <div>
                                            <label class="block text-[10px] font-black uppercase tracking-wider text-gray-600" :for="`expense-particulars-${item.id}`">Particular/s <span class="text-red-600" title="Required" aria-label="Required">*</span></label>
                                            <input :id="`expense-particulars-${item.id}`" :name="`items[${index}][particulars]`" x-bind:data-expense-item-primary="item.id" type="text" maxlength="255" x-model="item.particulars" required placeholder="e.g. Prepaid Card" class="mt-1.5 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600">
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-black uppercase tracking-wider text-gray-600" :for="`expense-unit-${item.id}`">Unit <span class="text-red-600" title="Required" aria-label="Required">*</span></label>
                                            <input :id="`expense-unit-${item.id}`" :name="`items[${index}][unit]`" type="text" maxlength="50" x-model="item.unit" required placeholder="pc, hours" class="mt-1.5 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600">
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-black uppercase tracking-wider text-gray-600" :for="`expense-quantity-${item.id}`">Qty. <span class="text-red-600" title="Required" aria-label="Required">*</span></label>
                                            <input :id="`expense-quantity-${item.id}`" :name="`items[${index}][quantity]`" type="number" min="0.01" max="<?php echo e(config('expense_breakdown.maximum_quantity')); ?>" step="0.01" x-model="item.quantity" required class="mt-1.5 block w-full rounded-xl border-gray-300 text-right text-sm shadow-sm focus:border-red-600 focus:ring-red-600">
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-black uppercase tracking-wider text-gray-600" :for="`expense-unit-cost-${item.id}`">Unit Cost (Php) <span class="text-red-600" title="Required" aria-label="Required">*</span></label>
                                            <input :id="`expense-unit-cost-${item.id}`" :name="`items[${index}][unit_cost]`" type="number" min="0.01" max="<?php echo e(config('expense_breakdown.maximum_unit_cost')); ?>" step="0.01" x-model="item.unit_cost" required class="mt-1.5 block w-full rounded-xl border-gray-300 text-right text-sm shadow-sm focus:border-red-600 focus:ring-red-600">
                                        </div>
                                    </div>

                                    <div class="expense-writing-details mt-4 grid gap-4">
                                        <div>
                                            <label class="block text-[10px] font-black uppercase tracking-wider text-gray-600" :for="`expense-details-${item.id}`">Descriptions / Specifications / Details <span class="text-red-600" title="Required" aria-label="Required">*</span></label>
                                            <textarea :id="`expense-details-${item.id}`" :name="`items[${index}][details]`" rows="3" maxlength="500" x-model="item.details" required class="mt-1.5 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600"></textarea>
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-black uppercase tracking-wider text-gray-600" :for="`expense-purpose-${item.id}`">Purpose in the project <span class="text-red-600" title="Required" aria-label="Required">*</span></label>
                                            <textarea :id="`expense-purpose-${item.id}`" :name="`items[${index}][purpose]`" rows="3" maxlength="500" x-model="item.purpose" required class="mt-1.5 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600"></textarea>
                                        </div>
                                    </div>
                                </div>
                            </template>

                                <template x-if="isContingency(item)">
                                <div class="expense-writing-contingency mt-4 grid gap-4">
                                    <input type="hidden" :name="`items[${index}][particulars]`" value="N/A">
                                    <input type="hidden" :name="`items[${index}][details]`" value="N/A">
                                    <input type="hidden" :name="`items[${index}][unit]`" value="N/A">
                                    <input type="hidden" :name="`items[${index}][quantity]`" value="1">
                                    <div>
                                        <label class="block text-[10px] font-black uppercase tracking-wider text-gray-600" :for="`expense-purpose-${item.id}`">Purpose in the project <span class="text-red-600" title="Required" aria-label="Required">*</span></label>
                                        <textarea :id="`expense-purpose-${item.id}`" :name="`items[${index}][purpose]`" x-bind:data-expense-item-primary="item.id" rows="3" maxlength="500" x-model="item.purpose" required class="mt-1.5 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600"></textarea>
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-black uppercase tracking-wider text-gray-600" :for="`expense-unit-cost-${item.id}`">Contingency amount (Php) <span class="text-red-600" title="Required" aria-label="Required">*</span></label>
                                        <input :id="`expense-unit-cost-${item.id}`" :name="`items[${index}][unit_cost]`" type="number" min="0.01" max="<?php echo e(config('expense_breakdown.maximum_unit_cost')); ?>" step="0.01" x-model="item.unit_cost" required class="mt-1.5 block w-full rounded-xl border-gray-300 text-right text-sm shadow-sm focus:border-red-600 focus:ring-red-600">
                                    </div>
                                </div>
                                </template>
                            </div>
                        </article>
                    </template>
                </div>

                <button type="button" x-on:click="addItem(true)" class="mt-4 inline-flex w-full items-center justify-center rounded-xl border border-dashed border-gray-300 px-4 py-3 text-xs font-bold text-gray-700 hover:border-red-300 hover:bg-red-50 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-600">Add another expense item</button>
            </section>

            <section data-revision-section="section-totals" class="rounded-2xl border border-red-200 bg-red-50 p-5 shadow-sm sm:p-6">
                <p class="text-xs font-black uppercase tracking-wider text-red-700">Total estimated budget</p>
                <p class="mt-1 text-3xl font-black text-gray-900">Php <span x-text="formatMoney(grandTotal())"></span></p>
                <div class="mt-4 grid gap-3 border-t border-red-200 pt-4 text-sm text-gray-700 sm:grid-cols-2">
                    <p>MOOE: <strong class="text-gray-900">Php <span x-text="formatMoney(categoryTotal('mooe'))"></span></strong></p>
                    <p>Capital Outlay: <strong class="text-gray-900">Php <span x-text="formatMoney(categoryTotal('capital_outlay'))"></span></strong></p>
                </div>
            </section>

            <div x-show="isOverBudget()" x-cloak role="alert" class="rounded-2xl border border-amber-300 bg-amber-50 p-5 text-sm text-amber-950">
                <p class="font-black">Budget limit exceeded</p>
                <p class="mt-1 leading-6">The estimated expense breakdown is over the research call limit by <strong>Php <span x-text="formatMoney(budgetOverage())"></span></strong>. You can still preview and print this working copy, and the values will be retained as a draft. Reduce the total to <strong>Php <span x-text="formatMoney(budgetCeiling)"></span></strong> or less before downloading or completing the paper.</p>
            </div>

            <noscript>
                <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl bg-red-600 px-5 py-3 text-sm font-bold text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 sm:w-auto">Save Expense Breakdown</button>
            </noscript>
        </form>

        <div x-show="previewError || downloadError" x-cloak role="alert" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><span x-text="previewError || downloadError"></span></div>
        </div>

        <?php if (isset($component)) { $__componentOriginal10a0e39c04a9eacf5d69b3b1628f0121 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal10a0e39c04a9eacf5d69b3b1628f0121 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-paper-preview','data' => ['panelId' => 'expense-breakdown-preview-panel','previewLabel' => 'Estimated Expense Breakdown preview','frameTitle' => 'Estimated Expense Breakdown preview']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-paper-preview'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['panel-id' => 'expense-breakdown-preview-panel','preview-label' => 'Estimated Expense Breakdown preview','frame-title' => 'Estimated Expense Breakdown preview']); ?>
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

        <button type="button" x-show="!previewPaneOpen" x-cloak @click="showProposalPreview()" class="proposal-writing-preview-launcher" aria-controls="expense-breakdown-preview-panel" :aria-expanded="previewPaneOpen">Preview paper</button>
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
<?php /**PATH C:\laragon\www\athena-app\src\resources\views/faculty/proposal-drafts/expense-breakdown/edit.blade.php ENDPATH**/ ?>