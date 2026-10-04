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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.page-header','data' => ['title' => $paper['label'],'subtitle' => 'Use MOOE, Capital Outlay, or both. Leave any category that does not apply empty; its total will be zero.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('page-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($paper['label']),'subtitle' => 'Use MOOE, Capital Outlay, or both. Leave any category that does not apply empty; its total will be zero.']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

             <?php $__env->slot('actions', null, []); ?> 
                <span class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-wider <?php echo e(($budgetConsistency['available'] ?? false) && ! ($budgetConsistency['consistent'] ?? true) ? 'bg-red-100 text-red-800' : ($lineItemBudgetDocument?->completed_at ? 'bg-green-100 text-green-800' : ($lineItemBudgetDocument ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-600'))); ?>"><?php echo e(($budgetConsistency['available'] ?? false) && ! ($budgetConsistency['consistent'] ?? true) ? 'Needs attention' : ($lineItemBudgetDocument?->completed_at ? 'Complete' : ($lineItemBudgetDocument ? 'In progress' : 'Not started'))); ?></span>
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
        $sections = config('line_item_budget.sections');
        $expenseBreakdownHasDraft = is_array($expenseBreakdownDocument?->source_data)
            && is_array($expenseBreakdownDocument->source_data['items'] ?? null)
            && $expenseBreakdownDocument->source_data['items'] !== [];
    ?>

    <div
        class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8"
        data-paper-editor
        data-paper-draft-save="true"
        data-line-item-budget-autosave="true"
        data-paper-project-details-complete="<?php echo e($projectDetailsComplete ? 'true' : 'false'); ?>"
        data-paper-dirty="<?php echo e($errors->any() ? 'true' : 'false'); ?>"
        data-paper-edit-url="<?php echo e(route('faculty.proposal-drafts.line-item-budget.edit', $proposalDraft)); ?>"
        data-paper-exit-url="<?php echo e(route('faculty.proposal-drafts.show', $proposalDraft)); ?>#required-pdf-attachments"
        x-data="proposalDraftLineItemBudget({
            initialData: <?php echo \Illuminate\Support\Js::from($initialData)->toHtml() ?>,
            sections: <?php echo \Illuminate\Support\Js::from($sections)->toHtml() ?>,
            defaultCampus: <?php echo \Illuminate\Support\Js::from(config('line_item_budget.default_campus'))->toHtml() ?>,
            budgetCeiling: <?php echo \Illuminate\Support\Js::from($budgetCeiling)->toHtml() ?>,
            workspacePeople: <?php echo \Illuminate\Support\Js::from($workspacePeople)->toHtml() ?>,
            previewUrl: <?php echo \Illuminate\Support\Js::from(route('faculty.proposal-drafts.line-item-budget.preview', $proposalDraft))->toHtml() ?>,
            downloadUrl: <?php echo \Illuminate\Support\Js::from(route('faculty.proposal-drafts.line-item-budget.download', $proposalDraft))->toHtml() ?>,
            updateUrl: <?php echo \Illuminate\Support\Js::from(route('faculty.proposal-drafts.line-item-budget.update', $proposalDraft))->toHtml() ?>,
            csrfToken: <?php echo \Illuminate\Support\Js::from(csrf_token())->toHtml() ?>,
            revisionUploadUrl: <?php echo \Illuminate\Support\Js::from($proposalDraft->topic_id ? route('faculty.proposal-drafts.revision-files.store', $proposalDraft) : null)->toHtml() ?>,
            revisionDocumentType: <?php echo \Illuminate\Support\Js::from($paper['document_type'])->toHtml() ?>,
            revisionAttachmentLabel: <?php echo \Illuminate\Support\Js::from($paper['label'])->toHtml() ?>,
            revisionReviewUrl: <?php echo \Illuminate\Support\Js::from($proposalDraft->topic_id ? route('faculty.topics.revision', $proposalDraft->topic_id).'#review-and-submit' : null)->toHtml() ?>,
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

                <p class="font-bold">The Line-Item Budget could not be saved.</p>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-collaboration-monitor','data' => ['loadedVersion' => (int) old('document_version', $lineItemBudgetDocument?->lock_version ?? 0),'stateUrl' => route('faculty.proposal-drafts.edit-state', [$proposalDraft, $paper['document_type'], 0]),'reloadUrl' => route('faculty.proposal-drafts.line-item-budget.edit', $proposalDraft),'historyUrl' => route('faculty.proposal-drafts.history.index', [$proposalDraft, 'paper' => $paper['slug']]),'label' => $paper['label']]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-collaboration-monitor'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['loaded-version' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute((int) old('document_version', $lineItemBudgetDocument?->lock_version ?? 0)),'state-url' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('faculty.proposal-drafts.edit-state', [$proposalDraft, $paper['document_type'], 0])),'reload-url' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('faculty.proposal-drafts.line-item-budget.edit', $proposalDraft)),'history-url' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('faculty.proposal-drafts.history.index', [$proposalDraft, 'paper' => $paper['slug']])),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($paper['label'])]); ?>
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

        <div class="proposal-preview-toolbar flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-900">
            <button type="button" @click="previewPaneOpen ? closeProposalPreview() : showProposalPreview()" :aria-expanded="previewPaneOpen" aria-controls="line-item-budget-preview-panel" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-bold dark:text-white" x-text="previewPaneOpen ? 'Hide preview' : 'Show preview'"></button>
            <span class="text-xs text-slate-500 dark:text-slate-400">The preview stays open while you edit and can be moved or resized.</span>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($projectDetailsComplete)): ?>
            <div role="alert" class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">
                <p class="font-black">Complete Project Details first</p>
                <p class="mt-1 leading-6">Project title, planned dates, and project leader are required before Attachment B can be previewed or generated. You can still save your progress as a draft.</p>
                <a href="<?php echo e(route('faculty.proposal-drafts.details.edit', $proposalDraft)); ?>" class="mt-3 inline-flex rounded-xl bg-amber-900 px-4 py-2.5 text-xs font-bold text-white focus:outline-none focus:ring-2 focus:ring-amber-900 focus:ring-offset-2">Complete Project Details</a>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($expenseBreakdownHasDraft): ?>
            <div role="status" class="rounded-2xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">
                <p class="font-black">Budget amounts synchronized</p>
                <p class="mt-1 leading-6">Matching amounts are refreshed from the saved Estimated Expense Breakdown whenever this paper opens, previews, or saves. Use custom rows or total overrides for intentional adjustments.</p>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <section data-revision-section="section-project-information" data-revision-shared-summary class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h3 class="text-base font-black text-gray-900">Shared project information</h3>
                    <p class="mt-1 text-xs text-gray-500">Program Title stays empty. Project title, duration, dates, and project leader come from Project Details.</p>
                    <p class="mt-2 text-[11px] text-gray-500"><span class="text-red-600" aria-hidden="true">*</span> Required shared project detail.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($sampleAvailable): ?><a href="<?php echo e(route('proposal-samples.show', $paper['sample_slug'])); ?>" target="_blank" rel="noopener" class="inline-flex rounded-xl border border-gray-300 px-3 py-2 text-xs font-bold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-600 focus:ring-offset-2">View sample</a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <a href="<?php echo e(route('faculty.proposal-drafts.details.edit', $proposalDraft)); ?>" class="inline-flex rounded-xl border border-red-200 px-3 py-2 text-xs font-bold text-red-700 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2">Edit details</a>
                </div>
            </div>
            <dl class="mt-5 grid gap-4 border-t border-gray-100 pt-5 sm:grid-cols-2 lg:grid-cols-4">
                <div class="sm:col-span-2 lg:col-span-4"><dt class="text-[10px] font-black uppercase tracking-wider text-gray-500">Project Title <span class="text-red-600" title="Required" aria-label="Required">*</span></dt><dd class="mt-1 text-sm font-normal text-gray-900"><?php echo e($proposalDraft->project_title); ?></dd></div>
                <div><dt class="text-[10px] font-black uppercase tracking-wider text-gray-500">Project Leader <span class="text-red-600" title="Required" aria-label="Required">*</span></dt><dd class="mt-1 text-sm font-semibold text-gray-900"><?php echo e($proposalDraft->project_leader ?: 'Not provided'); ?></dd></div>
                <div class="sm:col-span-2"><dt class="text-[10px] font-black uppercase tracking-wider text-gray-500">Duration on paper <span class="text-red-600" title="Required" aria-label="Required">*</span></dt><dd class="mt-1 text-sm italic text-gray-900"><?php echo e($proposalDraft->planned_start?->format('F j, Y') ?? 'Not provided'); ?> - <?php echo e($proposalDraft->planned_end?->format('F j, Y') ?? 'Not provided'); ?></dd></div>
                <div><dt class="text-[10px] font-black uppercase tracking-wider text-gray-500">Institutional budget limit</dt><dd class="mt-1 text-sm font-semibold text-gray-900">PHP <?php echo e(number_format($budgetCeiling, 2)); ?></dd></div>
            </dl>
        </section>

        <form data-paper-form data-line-item-budget-autosave-form x-ref="form" action="<?php echo e(route('faculty.proposal-drafts.line-item-budget.update', $proposalDraft)); ?>" method="POST" class="space-y-6" novalidate>
            <?php echo csrf_field(); ?>
            <?php echo method_field('PUT'); ?>
            <input type="hidden" name="document_version" value="<?php echo e(old('document_version', $lineItemBudgetDocument?->lock_version ?? 0)); ?>">
            <input type="hidden" name="save_as_draft" value="0" data-paper-save-mode>

            <section data-revision-section="section-project-team" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div><h3 class="text-base font-black text-gray-900">Project leader and staff</h3><p class="mt-1 text-xs text-gray-500">Choose a proposal workspace member to reuse their account name and college, or type an external member manually.</p></div>
                    <button type="button" x-on:click="addStaff" class="inline-flex w-full items-center justify-center rounded-xl border border-red-200 px-4 py-2.5 text-xs font-bold text-red-700 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 sm:w-auto">Add project staff</button>
                </div>

                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div><label for="leader-campus" class="block text-xs font-black uppercase tracking-wider text-gray-600">Project leader campus</label><input id="leader-campus" name="leader_campus" type="text" maxlength="120" x-model="leaderCampus" class="mt-2 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600"></div>
                    <div><label for="leader-college" class="block text-xs font-black uppercase tracking-wider text-gray-600">Project leader college</label><input id="leader-college" name="leader_college" type="text" list="line-item-budget-colleges" maxlength="120" x-model="leaderCollege" placeholder="Select or type a college" class="mt-2 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600"></div>
                </div>

                <div class="mt-5 space-y-3">
                    <template x-for="(member, index) in staff" :key="member.id">
                        <div class="grid gap-3 rounded-xl border border-gray-200 bg-gray-50 p-4 lg:grid-cols-[1fr_1fr_1fr_auto] lg:items-end">
                            <div><label class="block text-[10px] font-black uppercase tracking-wider text-gray-500" :for="`staff-name-${member.id}`">Name</label><input :id="`staff-name-${member.id}`" :name="`staff[${index}][name]`" type="text" list="proposal-workspace-member-names" maxlength="120" x-model="member.name" x-on:change="syncStaff(member)" class="mt-1.5 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600"></div>
                            <div><label class="block text-[10px] font-black uppercase tracking-wider text-gray-500" :for="`staff-campus-${member.id}`">Campus</label><input :id="`staff-campus-${member.id}`" :name="`staff[${index}][campus]`" type="text" maxlength="120" x-model="member.campus" class="mt-1.5 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600"></div>
                            <div><label class="block text-[10px] font-black uppercase tracking-wider text-gray-500" :for="`staff-college-${member.id}`">College</label><input :id="`staff-college-${member.id}`" :name="`staff[${index}][college]`" type="text" list="line-item-budget-colleges" maxlength="120" x-model="member.college" placeholder="Select or type" class="mt-1.5 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600"></div>
                            <button type="button" x-on:click="removeStaff(index)" class="rounded-xl px-3 py-2.5 text-xs font-bold text-red-700 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-600">Remove</button>
                        </div>
                    </template>
                </div>
                <datalist id="line-item-budget-colleges">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = config('line_item_budget.college_options'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $college): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <option value="<?php echo e($college); ?>"></option>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </datalist>
                <datalist id="proposal-workspace-member-names">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $workspacePeople; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $workspacePerson): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <option value="<?php echo e($workspacePerson['name']); ?>"><?php echo e($workspacePerson['email']); ?></option>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </datalist>
            </section>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = ['mooe' => 'I. Maintenance and Other Operating Expenses (MOOE)', 'co' => 'II. Capital Outlays (CO)']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $sectionKey => $sectionHeading): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <?php ($customProperty = $sectionKey === 'mooe' ? 'customMooeItems' : 'customCoItems'); ?>
                <section data-revision-section="section-<?php echo e($sectionKey); ?>" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                        <div><h3 class="text-base font-black text-gray-900"><?php echo e($sectionHeading); ?> <span class="text-xs font-normal text-gray-500">(Optional)</span></h3><p class="mt-1 text-xs text-gray-500">This entire category may be left empty if it does not apply. Empty amounts count as zero. Enter numbers without commas.</p></div>
                        <button type="button" x-on:click="addCustomItem('<?php echo e($sectionKey); ?>')" class="inline-flex w-full items-center justify-center rounded-xl border border-gray-300 px-4 py-2.5 text-xs font-bold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-600 focus:ring-offset-2 sm:w-auto">Add category or sub-category</button>
                    </div>

                    <div class="mt-5 overflow-hidden rounded-xl border border-gray-200">
                        <div class="grid grid-cols-[minmax(0,1fr)_10rem] bg-gray-100 px-4 py-2 text-[10px] font-black uppercase tracking-wider text-gray-600"><span>Particulars</span><span class="text-right">Amount (Php)</span></div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $sections[$sectionKey]['items']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <div class="grid grid-cols-[minmax(0,1fr)_10rem] items-center gap-3 border-t border-gray-100 px-4 py-2.5">
                                <label for="amount-<?php echo e($item['key']); ?>" class="text-sm text-gray-800 <?php echo e($item['level'] ? 'pl-6' : 'font-semibold'); ?>"><?php echo e($item['label']); ?></label>
                                <input id="amount-<?php echo e($item['key']); ?>" name="amounts[<?php echo e($item['key']); ?>]" type="number" min="0" max="<?php echo e(config('line_item_budget.maximum_amount')); ?>" step="0.01" x-model="amounts['<?php echo e($item['key']); ?>']" class="block w-full rounded-lg border-gray-300 text-right text-sm shadow-sm focus:border-red-600 focus:ring-red-600">
                            </div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>

                        <template x-for="(item, index) in <?php echo e($customProperty); ?>" :key="item.id">
                            <div x-bind:data-repeatable-entry="`line-item-budget-custom-${item.id}`" class="grid gap-3 border-t border-gray-100 bg-red-50/40 px-4 py-3 sm:grid-cols-[minmax(0,1fr)_10rem_auto] sm:items-center">
                                <input :id="`custom-<?php echo e($sectionKey); ?>-particular-${item.id}`" :name="`custom_<?php echo e($sectionKey); ?>_items[${index}][particular]`" x-bind:data-line-item-budget-custom-input="item.id" type="text" maxlength="255" x-model="item.particular" aria-label="Custom <?php echo e(strtoupper($sectionKey)); ?> particular" placeholder="Custom category or sub-category" class="block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600">
                                <input :id="`custom-<?php echo e($sectionKey); ?>-amount-${item.id}`" :name="`custom_<?php echo e($sectionKey); ?>_items[${index}][amount]`" type="number" min="0" max="<?php echo e(config('line_item_budget.maximum_amount')); ?>" step="0.01" x-model="item.amount" aria-label="Custom <?php echo e(strtoupper($sectionKey)); ?> amount" class="block w-full rounded-lg border-gray-300 text-right text-sm shadow-sm focus:border-red-600 focus:ring-red-600">
                                <button type="button" x-on:click="removeCustomItem('<?php echo e($sectionKey); ?>', index)" class="rounded-lg px-3 py-2 text-xs font-bold text-red-700 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-red-600">Remove</button>
                            </div>
                        </template>
                    </div>

                    <button type="button" x-on:click="addCustomItem('<?php echo e($sectionKey); ?>')" class="mt-4 inline-flex w-full items-center justify-center rounded-xl border border-dashed border-gray-300 px-4 py-3 text-xs font-bold text-gray-700 hover:border-red-300 hover:bg-red-50 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-600">Add another category or sub-category</button>

                    <div class="mt-4 rounded-xl border border-gray-200 bg-gray-50 p-4">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div><p class="text-xs font-black uppercase tracking-wider text-gray-500"><?php echo e($sectionKey === 'mooe' ? 'Total MOOE' : 'Total Capital Outlays'); ?></p><p class="mt-1 text-xl font-black text-gray-900">Php <span x-text="formatMoney(sectionTotal('<?php echo e($sectionKey); ?>'))"></span></p></div>
                            <label class="inline-flex items-center gap-2 text-xs font-bold text-gray-700"><input type="checkbox" x-model="<?php echo e($sectionKey === 'mooe' ? 'overrideMooe' : 'overrideCo'); ?>" class="rounded border-gray-300 text-red-600 focus:ring-red-600">Edit this total manually</label>
                        </div>
                        <input id="<?php echo e($sectionKey); ?>-total-override" name="<?php echo e($sectionKey); ?>_total_override" type="number" min="0" max="<?php echo e(config('line_item_budget.maximum_amount')); ?>" step="0.01" x-model="<?php echo e($sectionKey === 'mooe' ? 'mooeOverride' : 'coOverride'); ?>" x-bind:disabled="!<?php echo e($sectionKey === 'mooe' ? 'overrideMooe' : 'overrideCo'); ?>" x-show="<?php echo e($sectionKey === 'mooe' ? 'overrideMooe' : 'overrideCo'); ?>" x-cloak aria-label="Manual <?php echo e(strtoupper($sectionKey)); ?> total" class="mt-3 block w-full rounded-xl border-gray-300 text-right text-sm shadow-sm focus:border-red-600 focus:ring-red-600 sm:max-w-xs sm:ml-auto">
                    </div>
                </section>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>

            <section data-revision-section="section-totals" class="rounded-2xl border border-gray-900 bg-gray-900 p-5 text-white shadow-sm sm:p-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div><p class="text-xs font-black uppercase tracking-wider text-gray-300">Total Project Cost</p><p class="mt-1 text-2xl font-black">Php <span x-text="formatMoney(projectTotal())"></span></p></div>
                    <label class="inline-flex items-center gap-2 text-xs font-bold text-gray-200"><input type="checkbox" x-model="overrideProject" class="rounded border-gray-500 text-red-600 focus:ring-red-600">Edit project total manually</label>
                </div>
                <input id="project-total-override" name="project_total_override" type="number" min="0" max="<?php echo e(config('line_item_budget.maximum_amount')); ?>" step="0.01" x-model="projectOverride" x-bind:disabled="!overrideProject" x-show="overrideProject" x-cloak aria-label="Manual project total" class="mt-4 block w-full rounded-xl border-gray-600 bg-gray-800 text-right text-white shadow-sm focus:border-red-500 focus:ring-red-500 sm:max-w-xs sm:ml-auto">
            </section>

            <div x-show="isOverBudget()" x-cloak role="alert" class="rounded-2xl border border-amber-300 bg-amber-50 p-5 text-sm text-amber-950">
                <p class="font-black">Budget limit exceeded</p>
                <p class="mt-1 leading-6">The Line-Item Budget is over the research call limit by <strong>Php <span x-text="formatMoney(budgetOverage())"></span></strong>. Your changes are retained as a draft, and you can still preview and print this working copy. Reduce the total to <strong>Php <span x-text="formatMoney(budgetCeiling)"></span></strong> or less before downloading or completing the paper.</p>
            </div>

            <section data-revision-section="section-research-office" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <div><h3 class="text-base font-black text-gray-900">Research Office section</h3><p class="mt-1 text-xs text-gray-500">Constituent Campus is selected by default. Change the Level of Call if needed to put a cross in its box on the Line-Item Budget, Detailed Proposal, and Initial Screening Form. Approval details may remain blank.</p></div>
                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    <div><label for="level-of-call" class="block text-xs font-black uppercase tracking-wider text-gray-600">Level of call</label><select id="level-of-call" name="level_of_call" x-model="levelOfCall" class="mt-2 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600"><option value="central_agency">Central Agency (VPRDES, President)</option><option value="constituent_campus">Constituent Campus (VCRDES, Chancellor)</option></select></div>
                    <div><label for="approval-body" class="block text-xs font-black uppercase tracking-wider text-gray-600">Approving body</label><select id="approval-body" name="approval_body" x-model="approvalBody" class="mt-2 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600"><option value="">Leave blank</option><option value="research_council">Research Council</option><option value="lrec">Local Research Evaluation Committee</option></select></div>
                    <div><label for="resolution-number" class="block text-xs font-black uppercase tracking-wider text-gray-600">Resolution number</label><input id="resolution-number" name="resolution_number" type="text" maxlength="50" x-model="resolutionNumber" class="mt-2 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600"></div>
                    <div><label for="resolution-year" class="block text-xs font-black uppercase tracking-wider text-gray-600">Resolution year</label><input id="resolution-year" name="resolution_year" type="text" maxlength="10" x-model="resolutionYear" class="mt-2 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600"></div>
                </div>
            <?php if (isset($component)) { $__componentOriginalcd93ff08fd3bc5e3afdf57ccf1848309 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalcd93ff08fd3bc5e3afdf57ccf1848309 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-signatory-summary','data' => ['proposalDraft' => $proposalDraft,'paper' => 'line_item_budget']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-signatory-summary'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['proposal-draft' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($proposalDraft),'paper' => 'line_item_budget']); ?>
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
            </section>

            <noscript>
                <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl bg-red-600 px-5 py-3 text-sm font-bold text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 sm:w-auto">Save Line-Item Budget</button>
            </noscript>
        </form>

        <div x-show="previewError || downloadError" x-cloak role="alert" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><span x-text="previewError || downloadError"></span></div>

        <?php if (isset($component)) { $__componentOriginal45f53eba72ddc2e934dbcbe4dd398776 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal45f53eba72ddc2e934dbcbe4dd398776 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-document-preview','data' => ['panelId' => 'line-item-budget-preview-panel','title' => 'Line-Item Budget preview','description' => 'Review the official Attachment B layout while editing the budget.','frameTitle' => 'Attachment B Line-Item Budget preview']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-document-preview'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['panel-id' => 'line-item-budget-preview-panel','title' => 'Line-Item Budget preview','description' => 'Review the official Attachment B layout while editing the budget.','frame-title' => 'Attachment B Line-Item Budget preview']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal45f53eba72ddc2e934dbcbe4dd398776)): ?>
<?php $attributes = $__attributesOriginal45f53eba72ddc2e934dbcbe4dd398776; ?>
<?php unset($__attributesOriginal45f53eba72ddc2e934dbcbe4dd398776); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal45f53eba72ddc2e934dbcbe4dd398776)): ?>
<?php $component = $__componentOriginal45f53eba72ddc2e934dbcbe4dd398776; ?>
<?php unset($__componentOriginal45f53eba72ddc2e934dbcbe4dd398776); ?>
<?php endif; ?>
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
<?php /**PATH C:\laragon\www\athena-app\src\resources\views/faculty/proposal-drafts/line-item-budget/edit.blade.php ENDPATH**/ ?>