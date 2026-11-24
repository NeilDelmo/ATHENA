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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.page-header','data' => ['title' => $paper['label'],'subtitle' => $proposalDraft->project_title]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('page-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($paper['label']),'subtitle' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($proposalDraft->project_title)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

             <?php $__env->slot('actions', null, []); ?> 
                <span data-detailed-proposal-completion-status class="text-base text-slate-700 dark:text-slate-200"><?php echo e($detailedProposalComplete ? 'Complete' : ($detailedProposalDocument ? 'In progress' : 'Not started')); ?></span>
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
        $initialData = array_replace_recursive($sourceData, old());
        $sdgs = config('detailed_proposal.sdgs');
        $expectedOutputs = config('detailed_proposal.expected_outputs');
        $methodologyFields = config('detailed_proposal.methodology');
        $professionalTitles = config('detailed_proposal.professional_titles');
        $sectionHeadings = config('detailed_proposal.section_headings');
    ?>

    <div
        class="detailed-proposal-writing-workspace mx-auto w-full space-y-6 px-4 py-8 sm:px-6 lg:px-8"
        data-detailed-proposal-workspace
        data-proposal-draft-id="<?php echo e($proposalDraft->id); ?>"
        data-proposal-paper-workspace
        data-paper-editor
        data-proposal-paper="detailed-proposal"
        data-proposal-readiness-url="<?php echo e(route('faculty.proposal-drafts.review', $proposalDraft)); ?>"
        data-paper-draft-save="true"
        data-detailed-proposal-autosave="true"
        data-proposal-requirements-on-demand
        data-paper-project-details-complete="<?php echo e($projectDetailsComplete ? 'true' : 'false'); ?>"
        data-paper-dirty="<?php echo e($errors->any() ? 'true' : 'false'); ?>"
        data-paper-edit-url="<?php echo e(route('faculty.proposal-drafts.detailed-proposal.edit', $proposalDraft)); ?>"
        data-paper-exit-url="<?php echo e(route('faculty.proposal-drafts.show', $proposalDraft)); ?>#required-pdf-attachments"
        x-on:proposal-cite-selection.window="openCitationPicker($event.detail)"
        x-on:proposal-open-sources.window="openSourceWorkspaceFromToolbar($event.detail)"
        x-on:athena-insert-literature.window="applyAssistantLiterature($event.detail)"
        x-on:athena-apply-proposal-change.window="$event.detail.promise = applyAssistantProposalChange($event.detail)"
        x-data="proposalDraftDetailedProposal({
            proposalDraftId: <?php echo \Illuminate\Support\Js::from($proposalDraft->id)->toHtml() ?>,
            initialData: <?php echo \Illuminate\Support\Js::from($initialData)->toHtml() ?>,
            recheckCompletion: <?php echo \Illuminate\Support\Js::from($detailedProposalDocument !== null && $detailedProposalDocument->completed_at === null)->toHtml() ?>,
            detailedProposalStarted: <?php echo \Illuminate\Support\Js::from($detailedProposalDocument !== null)->toHtml() ?>,
            detailedProposalComplete: <?php echo \Illuminate\Support\Js::from($detailedProposalComplete)->toHtml() ?>,
            completionErrors: <?php echo \Illuminate\Support\Js::from($completionErrors)->toHtml() ?>,
            requirementsReviewed: <?php echo \Illuminate\Support\Js::from($errors->any())->toHtml() ?>,
            literatureSources: <?php echo \Illuminate\Support\Js::from($literatureSources)->toHtml() ?>,
            initialLiteratureSourceId: <?php echo \Illuminate\Support\Js::from($initialLiteratureSourceId)->toHtml() ?>,
            initialLiteratureAction: <?php echo \Illuminate\Support\Js::from($initialLiteratureAction)->toHtml() ?>,
            workspacePeople: <?php echo \Illuminate\Support\Js::from($workspacePeople)->toHtml() ?>,
            projectLeader: <?php echo \Illuminate\Support\Js::from($proposalDraft->project_leader)->toHtml() ?>,
            proposalTitle: <?php echo \Illuminate\Support\Js::from($proposalDraft->project_title)->toHtml() ?>,
            expectedOutputKeys: <?php echo \Illuminate\Support\Js::from(array_keys($expectedOutputs))->toHtml() ?>,
            methodologyKeys: <?php echo \Illuminate\Support\Js::from(array_keys($methodologyFields))->toHtml() ?>,
            methodologySections: <?php echo \Illuminate\Support\Js::from($methodologyFields)->toHtml() ?>,
            figureSections: <?php echo \Illuminate\Support\Js::from(\Illuminate\Support\Arr::except(config('detailed_proposal.image_sections'), ['introduction']))->toHtml() ?>,
            methodologyImageUrlTemplate: <?php echo \Illuminate\Support\Js::from(route('faculty.proposal-drafts.detailed-proposal.methodology-images.show', [$proposalDraft, '__image_id__', 'preview' => 1]))->toHtml() ?>,
            literatureSearchUrl: <?php echo \Illuminate\Support\Js::from(route('research-support.literature-search'))->toHtml() ?>,
            literatureLibrarySearchUrl: <?php echo \Illuminate\Support\Js::from(route('research-support.literature-library.index'))->toHtml() ?>,
            literatureLibrarySaveUrl: <?php echo \Illuminate\Support\Js::from(route('research-support.literature-library.store'))->toHtml() ?>,
            literatureAttachUrlTemplate: <?php echo \Illuminate\Support\Js::from(route('faculty.proposal-drafts.literature-sources.store', [$proposalDraft, '__literature_source__']))->toHtml() ?>,
            literatureDraftUpdateUrlTemplate: <?php echo \Illuminate\Support\Js::from(route('faculty.proposal-drafts.literature-drafts.update', [$proposalDraft, '__proposal_literature_source__']))->toHtml() ?>,
            literatureSynthesisUrl: <?php echo \Illuminate\Support\Js::from(route('research-support.literature-synthesis'))->toHtml() ?>,
            literatureFullTextPreviewUrl: <?php echo \Illuminate\Support\Js::from(route('research-support.literature-full-text-preview'))->toHtml() ?>,
            literatureEvidenceBase: <?php echo \Illuminate\Support\Js::from(str_replace('/__link__/evidence', '', route('faculty.proposal-drafts.literature-evidence.show', [$proposalDraft, '__link__'])))->toHtml() ?>,
            literatureEvidenceAssistanceUrl: <?php echo \Illuminate\Support\Js::from(route('faculty.proposal-drafts.literature-evidence-assistance', $proposalDraft))->toHtml() ?>,
            literatureMetadataUrl: <?php echo \Illuminate\Support\Js::from(route('research-support.literature-metadata'))->toHtml() ?>,
            previewUrl: <?php echo \Illuminate\Support\Js::from(route('faculty.proposal-drafts.detailed-proposal.preview', $proposalDraft))->toHtml() ?>,
            downloadUrl: <?php echo \Illuminate\Support\Js::from(route('faculty.proposal-drafts.detailed-proposal.download', $proposalDraft))->toHtml() ?>,
            updateUrl: <?php echo \Illuminate\Support\Js::from(route('faculty.proposal-drafts.detailed-proposal.update', $proposalDraft))->toHtml() ?>,
            proposalChangesUrl: <?php echo \Illuminate\Support\Js::from(route('research-support.proposal-changes.resolve'))->toHtml() ?>,
            csrfToken: <?php echo \Illuminate\Support\Js::from(csrf_token())->toHtml() ?>,
            revisionUploadUrl: <?php echo \Illuminate\Support\Js::from($proposalDraft->topic_id ? route('faculty.proposal-drafts.revision-files.store', $proposalDraft) : null)->toHtml() ?>,
            revisionDocumentType: <?php echo \Illuminate\Support\Js::from($paper['document_type'])->toHtml() ?>,
            revisionAttachmentLabel: <?php echo \Illuminate\Support\Js::from($paper['label'])->toHtml() ?>,
            revisionReviewUrl: <?php echo \Illuminate\Support\Js::from($proposalDraft->topic_id ? route('faculty.topics.revision', $proposalDraft->topic_id).'#review-and-submit' : null)->toHtml() ?>,
        })"
    >
        <?php if (isset($component)) { $__componentOriginal319c2ed83bc05541d477f47890dd11b4 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal319c2ed83bc05541d477f47890dd11b4 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-pdf-preparation','data' => ['proposalDraft' => $proposalDraft]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-pdf-preparation'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['proposal-draft' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($proposalDraft)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal319c2ed83bc05541d477f47890dd11b4)): ?>
<?php $attributes = $__attributesOriginal319c2ed83bc05541d477f47890dd11b4; ?>
<?php unset($__attributesOriginal319c2ed83bc05541d477f47890dd11b4); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal319c2ed83bc05541d477f47890dd11b4)): ?>
<?php $component = $__componentOriginal319c2ed83bc05541d477f47890dd11b4; ?>
<?php unset($__componentOriginal319c2ed83bc05541d477f47890dd11b4); ?>
<?php endif; ?>

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

                <p class="font-bold">The Detailed Research Proposal could not be saved.</p>
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
        <div x-show="proposalChangeNotice || proposalChangeError" x-cloak class="rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-900" aria-live="polite" data-proposal-change-status>
            <p x-show="proposalChangeNotice" class="text-slate-700 dark:text-slate-200" x-text="proposalChangeNotice"></p>
            <p x-show="proposalChangeError" role="alert" class="text-red-800 dark:text-red-200" x-text="proposalChangeError"></p>
        </div>
        <?php if (isset($component)) { $__componentOriginal0cce39fa08f6913d6374a37beaeb3f47 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal0cce39fa08f6913d6374a37beaeb3f47 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.paper-editor-submit-status','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('paper-editor-submit-status'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal0cce39fa08f6913d6374a37beaeb3f47)): ?>
<?php $attributes = $__attributesOriginal0cce39fa08f6913d6374a37beaeb3f47; ?>
<?php unset($__attributesOriginal0cce39fa08f6913d6374a37beaeb3f47); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal0cce39fa08f6913d6374a37beaeb3f47)): ?>
<?php $component = $__componentOriginal0cce39fa08f6913d6374a37beaeb3f47; ?>
<?php unset($__componentOriginal0cce39fa08f6913d6374a37beaeb3f47); ?>
<?php endif; ?>
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
        <p x-show="resumingDetailedProposal && !detailedProposalComplete" x-cloak data-proposal-resume class="text-base leading-6 text-slate-700 dark:text-slate-200">Continue your proposal. You can review the remaining fields whenever you’re ready.</p>
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 pb-4 dark:border-slate-700" data-proposal-writing-status>
        <div x-show="showDetailedProposalSaveStatus" x-cloak>
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
        </div>
            <button type="button" @click="checkDetailedProposalRequirements()" data-proposal-check-requirements :aria-expanded="showCompletionChecklist" aria-controls="proposal-completion-checklist" x-text="resumingDetailedProposal ? 'Review remaining fields' : 'Check requirements'" class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-base font-semibold text-slate-700 hover:border-slate-400 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#7A0019] dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200">Check requirements</button>
        </div>
        <p x-show="requirementsReviewed" x-cloak data-proposal-requirements-result role="status" aria-live="polite" class="text-base leading-6 text-slate-700 dark:text-slate-200" x-text="detailedProposalRequirementsResult"></p>
        <section x-show="showCompletionChecklist" id="proposal-completion-checklist" x-cloak data-proposal-completion-checklist class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900" aria-labelledby="proposal-completion-heading" aria-live="polite">
            <h3 id="proposal-completion-heading" class="text-lg font-semibold text-slate-900 dark:text-white">Remaining requirements</h3>
            <p class="mt-1 max-w-prose text-base leading-6 text-slate-600 dark:text-slate-300">You can leave and continue later once your changes are saved. Select a requirement to go to that field.</p>
            <div class="mt-5 grid gap-6 sm:grid-cols-2">
                <template x-for="group in detailedProposalRequirementGroups" :key="group.title">
                    <div>
                        <h4 class="text-base font-semibold text-slate-900 dark:text-white" x-text="group.title"></h4>
                        <ul class="mt-2 space-y-1">
                            <template x-for="requirement in group.requirements" :key="requirement.field">
                                <li><button type="button" x-on:click="focusProposalRequirement(requirement.field)" class="w-full text-left leading-6 secondary-action justify-start" x-text="requirement.messages.join(' ')"></button></li>
                            </template>
                        </ul>
                    </div>
                </template>
            </div>
        </section>
        <?php if (isset($component)) { $__componentOriginal365c40492913100a7be7d48ba061239f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal365c40492913100a7be7d48ba061239f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-collaboration-monitor','data' => ['loadedVersion' => (int) old('document_version', $detailedProposalDocument?->lock_version ?? 0),'stateUrl' => route('faculty.proposal-drafts.edit-state', [$proposalDraft, $paper['document_type'], 0]),'reloadUrl' => route('faculty.proposal-drafts.detailed-proposal.edit', $proposalDraft),'label' => $paper['label']]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-collaboration-monitor'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['loaded-version' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute((int) old('document_version', $detailedProposalDocument?->lock_version ?? 0)),'state-url' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('faculty.proposal-drafts.edit-state', [$proposalDraft, $paper['document_type'], 0])),'reload-url' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('faculty.proposal-drafts.detailed-proposal.edit', $proposalDraft)),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($paper['label'])]); ?>
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


        <?php if (isset($component)) { $__componentOriginal1b5ac9d9e680aba9536265bed4ee9ad6 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1b5ac9d9e680aba9536265bed4ee9ad6 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.detailed-proposal-writing-toolbar','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('detailed-proposal-writing-toolbar'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal1b5ac9d9e680aba9536265bed4ee9ad6)): ?>
<?php $attributes = $__attributesOriginal1b5ac9d9e680aba9536265bed4ee9ad6; ?>
<?php unset($__attributesOriginal1b5ac9d9e680aba9536265bed4ee9ad6); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal1b5ac9d9e680aba9536265bed4ee9ad6)): ?>
<?php $component = $__componentOriginal1b5ac9d9e680aba9536265bed4ee9ad6; ?>
<?php unset($__componentOriginal1b5ac9d9e680aba9536265bed4ee9ad6); ?>
<?php endif; ?>
        <?php if (isset($component)) { $__componentOriginal41f1f671eb2bfb10a1c2b567383f48ca = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal41f1f671eb2bfb10a1c2b567383f48ca = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-source-workspace','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-source-workspace'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal41f1f671eb2bfb10a1c2b567383f48ca)): ?>
<?php $attributes = $__attributesOriginal41f1f671eb2bfb10a1c2b567383f48ca; ?>
<?php unset($__attributesOriginal41f1f671eb2bfb10a1c2b567383f48ca); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal41f1f671eb2bfb10a1c2b567383f48ca)): ?>
<?php $component = $__componentOriginal41f1f671eb2bfb10a1c2b567383f48ca; ?>
<?php unset($__componentOriginal41f1f671eb2bfb10a1c2b567383f48ca); ?>
<?php endif; ?>
        <div class="proposal-preview-workspace proposal-writing-columns" :class="{ 'proposal-writing-preview-hidden': !previewPaneOpen }" @resize.window.debounce.150ms="resizeProposalPaperPreview()">
        <div data-proposal-official-form-source :inert="previewFullscreen" class="proposal-edit-pane space-y-6" aria-label="Proposal editing form">
            <p data-proposal-required-legend class="text-xs text-gray-600 dark:text-slate-400"><span class="font-bold text-red-700 dark:text-red-400" aria-hidden="true">*</span> Required for submission.</p>
        <section data-revision-section="section-project-information" data-revision-shared-summary class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex justify-end">
                <a href="<?php echo e(route('faculty.proposal-drafts.details.edit', $proposalDraft)); ?>" class="inline-flex shrink-0 rounded-xl border border-red-200 px-3 py-2 text-xs font-bold text-red-700 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2">Edit shared details</a>
            </div>
            <dl class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="sm:col-span-2 lg:col-span-4"><dt class="text-[10px] font-black uppercase tracking-wider text-gray-500"><?php echo e($sectionHeadings['project-information']); ?></dt><dd class="mt-1 text-sm font-semibold text-gray-900"><?php echo e($proposalDraft->project_title); ?></dd></div>
                <div><dt class="text-[10px] font-black uppercase tracking-wider text-gray-500">Project Leader</dt><dd class="mt-1 text-sm font-semibold uppercase text-gray-900"><?php echo e($proposalDraft->project_leader); ?></dd></div>
                <div><dt class="text-[10px] font-black uppercase tracking-wider text-gray-500">MOOE from Attachment B</dt><dd class="mt-1 text-sm font-semibold text-gray-900">Php <?php echo e(number_format($budgetTotals['mooe_total'], 2)); ?></dd></div>
                <div><dt class="text-[10px] font-black uppercase tracking-wider text-gray-500">Capital Outlay from Attachment B</dt><dd class="mt-1 text-sm font-semibold text-gray-900">Php <?php echo e(number_format($budgetTotals['co_total'], 2)); ?></dd></div>
                <div><dt class="text-[10px] font-black uppercase tracking-wider text-gray-500">Official form</dt><dd class="mt-1 text-sm font-semibold text-gray-900">BatStateU-FO-RES-02 Rev. 04</dd></div>
            </dl>
        </section>

        <form data-paper-form data-detailed-proposal-autosave-form x-ref="form" action="<?php echo e(route('faculty.proposal-drafts.detailed-proposal.update', $proposalDraft)); ?>" method="POST" enctype="multipart/form-data" class="space-y-6" novalidate>
            <?php echo csrf_field(); ?>
            <?php echo method_field('PUT'); ?>
            <input type="hidden" name="document_version" value="<?php echo e(old('document_version', $detailedProposalDocument?->lock_version ?? 0)); ?>">
            <input type="hidden" name="draft_version" value="<?php echo e(old('draft_version', $proposalDraft->lock_version)); ?>">
            <input type="hidden" name="save_as_draft" value="0" data-paper-save-mode>
            <input x-ref="methodologyImagePicker" type="file" accept="image/jpeg,image/png,image/gif,image/bmp" multiple class="sr-only" x-on:change="addMethodologyImages($event.target.files, methodologyImageTarget)">
            <input type="hidden" name="methodology_images_present" value="1">
            <input type="hidden" name="staff" value="">
            <input type="hidden" name="sdgs" value="">
            <input type="hidden" name="expected_outputs" value="">
            <input type="hidden" name="literature_research_history" x-bind:value="JSON.stringify(literatureSearchHistory)">
            <input id="literature-citations" type="hidden" name="literature_citations" x-bind:value="JSON.stringify(literatureCitations)">

            <section data-revision-section="section-research-agenda" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <h3 class="text-base font-black text-gray-900">Research alignment</h3>
                <div class="mt-5">
                    <label for="research-agenda" class="block text-xs font-black uppercase tracking-wider text-gray-600"><?php echo e($sectionHeadings['research-agenda']); ?> <?php if (isset($component)) { $__componentOriginal4f074808deca33fb39dbf7707c9b77e2 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4f074808deca33fb39dbf7707c9b77e2 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.required-mark','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('required-mark'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal4f074808deca33fb39dbf7707c9b77e2)): ?>
<?php $attributes = $__attributesOriginal4f074808deca33fb39dbf7707c9b77e2; ?>
<?php unset($__attributesOriginal4f074808deca33fb39dbf7707c9b77e2); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal4f074808deca33fb39dbf7707c9b77e2)): ?>
<?php $component = $__componentOriginal4f074808deca33fb39dbf7707c9b77e2; ?>
<?php unset($__componentOriginal4f074808deca33fb39dbf7707c9b77e2); ?>
<?php endif; ?></label>
                    <input id="research-agenda" name="research_agenda" type="text" required maxlength="500" x-model="researchAgenda" placeholder="Type the applicable BatStateU research agenda" class="mt-2 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600">
                </div>
                <fieldset data-revision-section="section-sdgs" data-detailed-proposal-validation-group="sdgs" tabindex="-1" class="mt-6">
                    <legend class="text-xs font-black uppercase tracking-wider text-gray-600"><?php echo e($sectionHeadings['sdgs']); ?> <?php if (isset($component)) { $__componentOriginal4f074808deca33fb39dbf7707c9b77e2 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4f074808deca33fb39dbf7707c9b77e2 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.required-mark','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('required-mark'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal4f074808deca33fb39dbf7707c9b77e2)): ?>
<?php $attributes = $__attributesOriginal4f074808deca33fb39dbf7707c9b77e2; ?>
<?php unset($__attributesOriginal4f074808deca33fb39dbf7707c9b77e2); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal4f074808deca33fb39dbf7707c9b77e2)): ?>
<?php $component = $__componentOriginal4f074808deca33fb39dbf7707c9b77e2; ?>
<?php unset($__componentOriginal4f074808deca33fb39dbf7707c9b77e2); ?>
<?php endif; ?> <span class="font-normal normal-case text-gray-500">(Check all applicable SDG)</span></legend>
                    <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $sdgs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $number => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <label class="flex items-start gap-3 rounded-xl border border-gray-200 p-3 text-sm text-gray-800 hover:bg-gray-50">
                                <input name="sdgs[]" type="checkbox" value="<?php echo e($number); ?>" x-model.number="sdgs" class="mt-0.5 rounded border-gray-300 text-red-600 focus:ring-red-600">
                                <span><strong>SDG<?php echo e($number); ?>:</strong> <?php echo e($label); ?></span>
                            </label>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </div>
                </fieldset>
            </section>

            <section data-revision-section="section-project-team" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-6">
                <div>
                    <h3 class="text-base font-black text-gray-900 dark:text-white"><?php echo e($sectionHeadings['project-team']); ?></h3>
                    <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-slate-400">Add a professional title such as Asst Prof. or Dr. when applicable.</p>
                </div>

                <div class="mt-5 grid gap-4 border-t border-gray-100 pt-5 dark:border-slate-800 lg:grid-cols-[minmax(0,1fr)_17rem]">
                    <div class="rounded-2xl border border-red-100 bg-red-50/50 p-4 dark:border-red-950/80 dark:bg-red-950/20 sm:p-5">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <h4 class="text-sm font-black text-gray-900 dark:text-white">Add from the project team</h4>
                                <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-slate-400">Search the project team and add staff to this proposal.</p>
                            </div>
                            <span class="w-fit rounded-full bg-white px-2.5 py-1 text-[10px] font-black text-gray-600 ring-1 ring-gray-200 dark:bg-slate-900 dark:text-slate-300 dark:ring-slate-700" x-text="`${availableWorkspacePeople().length} available`"></span>
                        </div>

                        <div class="relative mt-4" x-on:click.outside="workspacePickerOpen = false">
                            <label for="workspace-person-search" class="sr-only">Search project team</label>
                            <div class="relative">
                                <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 1 0 3.474 9.765l3.63 3.63a.75.75 0 0 0 1.06-1.06l-3.629-3.63A5.5 5.5 0 0 0 9 3.5ZM5 9a4 4 0 1 1 8 0 4 4 0 0 1-8 0Z" clip-rule="evenodd" /></svg>
                                <input id="workspace-person-search" type="search" autocomplete="off" placeholder="Search project team" x-model="workspacePersonQuery" x-on:focus="workspacePickerOpen = true" x-on:input="workspacePickerOpen = true" x-on:keydown.escape="workspacePickerOpen = false" role="combobox" aria-autocomplete="list" x-bind:aria-expanded="workspacePickerOpen" aria-controls="workspace-person-options" class="block w-full rounded-xl border-gray-300 bg-white py-2.5 pl-9 pr-3 text-sm shadow-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                            </div>
                            <div id="workspace-person-options" x-show="workspacePickerOpen" x-transition.origin.top x-cloak role="listbox" class="absolute z-30 mt-2 max-h-72 w-full overflow-y-auto rounded-2xl border border-gray-200 bg-white p-2 shadow-xl shadow-gray-900/10 dark:border-slate-700 dark:bg-slate-800">
                                <template x-for="person in filteredWorkspacePeople()" :key="person.key">
                                    <button type="button" role="option" x-on:click="addWorkspacePerson(person.key)" class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left transition hover:bg-red-50 focus:bg-red-50 focus:outline-none dark:hover:bg-red-950/40 dark:focus:bg-red-950/40">
                                        <span class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full bg-red-100 text-xs font-black text-red-700 ring-1 ring-red-200 dark:bg-red-950 dark:text-red-200 dark:ring-red-900">
                                            <img x-show="person.avatar" x-bind:src="person.avatar" x-bind:alt="personDisplayName(person.name)" x-on:error="person.avatar = ''" class="h-full w-full object-cover">
                                            <span x-show="!person.avatar" x-text="personInitials(person.name)"></span>
                                        </span>
                                        <span class="min-w-0 flex-1">
                                            <span class="block truncate text-sm font-bold uppercase text-gray-900 dark:text-white" x-text="personDisplayName(person.name)"></span>
                                            <span class="block truncate text-xs text-gray-500 dark:text-slate-400" x-text="person.email"></span>
                                        </span>
                                        <svg class="h-4 w-4 shrink-0 text-red-600 dark:text-red-300" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                                    </button>
                                </template>
                                <div x-show="filteredWorkspacePeople().length === 0" class="px-3 py-5 text-center">
                                    <p class="text-sm font-bold text-gray-700 dark:text-slate-200">No available project team member matches your search.</p>
                                    <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-slate-400">Members already on the project staff list are hidden.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col justify-between rounded-2xl border border-dashed border-gray-300 bg-gray-50 p-4 dark:border-slate-700 dark:bg-slate-800/60 sm:p-5">
                        <div>
                            <h4 class="text-sm font-black text-gray-900 dark:text-white">External project staff</h4>
                            <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-slate-400">Enter the external staff member&rsquo;s optional professional title, name, email, and 11-digit contact number manually.</p>
                        </div>
                        <button type="button" x-on:click="addStaff" class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-xs font-bold text-gray-700 transition hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-gray-600 focus:ring-offset-2 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                            Add external project staff
                        </button>
                    </div>
                </div>

                <div class="mt-5 rounded-2xl border border-gray-200 bg-gray-50 p-4 dark:border-slate-700 dark:bg-slate-800/60 sm:p-5">
                    <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between sm:gap-4"><div><p class="text-xs font-black uppercase tracking-wider text-gray-600 dark:text-slate-300">Project leader</p><p class="mt-1 text-xs leading-5 text-gray-500 dark:text-slate-400">This information is used across the proposal papers and prepared-by block.</p></div></div>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div class="flex min-w-0 flex-col">
                        <label for="leader-title" class="flex h-5 items-center justify-between gap-2 text-[10px] font-black uppercase tracking-wider text-gray-600 dark:text-slate-300"><span>Professional title</span><span class="rounded-full border border-gray-300 bg-white px-2 py-0.5 text-[9px] normal-case tracking-normal text-gray-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-400">Optional</span></label>
                        <input id="leader-title" name="leader_title" type="text" maxlength="50" list="detailed-proposal-professional-titles" x-model="leaderTitle" placeholder="e.g. Asst Prof." aria-describedby="leader-title-help" class="mt-1.5 block h-11 w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                        <p id="leader-title-help" class="mt-1.5 text-[10px] leading-4 text-gray-500 dark:text-slate-400">Leave blank when no professional title applies.</p>
                    </div>
                    <div class="flex min-w-0 flex-col">
                        <label for="leader-name" class="flex h-5 items-center justify-between gap-2 text-[10px] font-black uppercase tracking-wider text-gray-600 dark:text-slate-300"><span>IV. Project Leader: <?php if (isset($component)) { $__componentOriginal4f074808deca33fb39dbf7707c9b77e2 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4f074808deca33fb39dbf7707c9b77e2 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.required-mark','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('required-mark'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal4f074808deca33fb39dbf7707c9b77e2)): ?>
<?php $attributes = $__attributesOriginal4f074808deca33fb39dbf7707c9b77e2; ?>
<?php unset($__attributesOriginal4f074808deca33fb39dbf7707c9b77e2); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal4f074808deca33fb39dbf7707c9b77e2)): ?>
<?php $component = $__componentOriginal4f074808deca33fb39dbf7707c9b77e2; ?>
<?php unset($__componentOriginal4f074808deca33fb39dbf7707c9b77e2); ?>
<?php endif; ?></span></label>
                        <input id="leader-name" name="project_leader" type="text" required maxlength="120" list="detailed-proposal-member-names" x-model="projectLeader" x-on:change="syncProjectLeader()" placeholder="Type or choose a project team member" aria-describedby="leader-name-help" class="mt-1.5 block h-11 w-full rounded-xl border-gray-300 text-sm uppercase shadow-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                        <p id="leader-name-help" class="mt-1.5 text-[10px] leading-4 text-gray-500 dark:text-slate-400">Changes also update Project Details and the prepared-by name.</p>
                    </div>
                    <div class="flex min-w-0 flex-col"><label for="leader-email" class="flex h-5 items-center text-[10px] font-black uppercase tracking-wider text-gray-500 dark:text-slate-300">Email Address <?php if (isset($component)) { $__componentOriginal4f074808deca33fb39dbf7707c9b77e2 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4f074808deca33fb39dbf7707c9b77e2 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.required-mark','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('required-mark'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal4f074808deca33fb39dbf7707c9b77e2)): ?>
<?php $attributes = $__attributesOriginal4f074808deca33fb39dbf7707c9b77e2; ?>
<?php unset($__attributesOriginal4f074808deca33fb39dbf7707c9b77e2); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal4f074808deca33fb39dbf7707c9b77e2)): ?>
<?php $component = $__componentOriginal4f074808deca33fb39dbf7707c9b77e2; ?>
<?php unset($__componentOriginal4f074808deca33fb39dbf7707c9b77e2); ?>
<?php endif; ?></label><input id="leader-email" name="leader_email" type="email" required maxlength="255" x-model="leaderEmail" class="mt-1.5 block h-11 w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-900 dark:text-white"></div>
                    <div class="flex min-w-0 flex-col"><label for="leader-contact" class="flex h-5 items-center text-[10px] font-black uppercase tracking-wider text-gray-500 dark:text-slate-300">Contact Number <?php if (isset($component)) { $__componentOriginal4f074808deca33fb39dbf7707c9b77e2 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4f074808deca33fb39dbf7707c9b77e2 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.required-mark','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('required-mark'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal4f074808deca33fb39dbf7707c9b77e2)): ?>
<?php $attributes = $__attributesOriginal4f074808deca33fb39dbf7707c9b77e2; ?>
<?php unset($__attributesOriginal4f074808deca33fb39dbf7707c9b77e2); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal4f074808deca33fb39dbf7707c9b77e2)): ?>
<?php $component = $__componentOriginal4f074808deca33fb39dbf7707c9b77e2; ?>
<?php unset($__componentOriginal4f074808deca33fb39dbf7707c9b77e2); ?>
<?php endif; ?></label><input id="leader-contact" name="leader_contact" type="tel" required maxlength="11" inputmode="numeric" pattern="[0-9]{11}" autocomplete="tel" x-model="leaderContact" x-on:input="leaderContact = normalizeContactNumber($event.target.value); $event.target.value = leaderContact" class="mt-1.5 block h-11 w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-900 dark:text-white"></div>
                    </div>
                </div>

                <div class="mt-5 border-t border-gray-100 pt-5 dark:border-slate-800">
                    <div class="flex items-center justify-between gap-3"><div><p class="text-xs font-black uppercase tracking-wider text-gray-600 dark:text-slate-300">Project Staff (s):</p><p class="mt-1 text-xs text-gray-500 dark:text-slate-400">Each staff member can have an optional professional title.</p></div><span class="rounded-full bg-gray-100 px-2.5 py-1 text-[10px] font-black text-gray-600 dark:bg-slate-800 dark:text-slate-300" x-text="`${staff.length} ${staff.length === 1 ? 'member' : 'members'}`"></span></div>
                    <div class="mt-4 space-y-3">
                    <p x-show="staff.length === 0" class="rounded-xl bg-gray-50 px-4 py-3 text-xs leading-5 text-gray-500 dark:bg-slate-800/60 dark:text-slate-400">No project staff added yet. Choose someone from the project team or add external project staff above.</p>
                    <template x-for="(member, index) in staff" :key="member.id">
                        <div class="grid gap-3 rounded-xl border border-gray-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900 sm:grid-cols-2">
                            <div class="flex items-center justify-between gap-3 sm:col-span-2">
                                <p class="text-base font-semibold text-gray-900 dark:text-white" x-text="'Staff member ' + (index + 1)">Staff member</p>
                                <button type="button" x-on:click="removeStaff(index)" :aria-label="'Remove ' + (member.name || 'staff member ' + (index + 1))" class="secondary-action shrink-0">Remove</button>
                            </div>
                            <div class="flex min-w-0 flex-col"><label class="flex h-5 items-center justify-between gap-2 text-[10px] font-black uppercase tracking-wider text-gray-500 dark:text-slate-300" :for="`staff-title-${member.id}`"><span>Professional title</span><span class="rounded-full border border-gray-300 px-2 py-0.5 text-[9px] normal-case tracking-normal text-gray-500 dark:border-slate-600 dark:text-slate-400">Optional</span></label><input :id="`staff-title-${member.id}`" :name="`staff[${index}][title]`" type="text" maxlength="50" list="detailed-proposal-professional-titles" x-model="member.title" placeholder="e.g. Dr." class="mt-1.5 block h-11 w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-800 dark:text-white"></div>
                            <div class="flex min-w-0 flex-col"><label class="flex h-5 items-center text-[10px] font-black uppercase tracking-wider text-gray-500 dark:text-slate-300" :for="`staff-name-${member.id}`">Project Staff (s): <?php if (isset($component)) { $__componentOriginal4f074808deca33fb39dbf7707c9b77e2 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4f074808deca33fb39dbf7707c9b77e2 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.required-mark','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('required-mark'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal4f074808deca33fb39dbf7707c9b77e2)): ?>
<?php $attributes = $__attributesOriginal4f074808deca33fb39dbf7707c9b77e2; ?>
<?php unset($__attributesOriginal4f074808deca33fb39dbf7707c9b77e2); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal4f074808deca33fb39dbf7707c9b77e2)): ?>
<?php $component = $__componentOriginal4f074808deca33fb39dbf7707c9b77e2; ?>
<?php unset($__componentOriginal4f074808deca33fb39dbf7707c9b77e2); ?>
<?php endif; ?></label><input :id="`staff-name-${member.id}`" :name="`staff[${index}][name]`" type="text" required maxlength="255" list="detailed-proposal-member-names" x-model="member.name" x-on:change="syncStaff(member)" placeholder="Full name" class="mt-1.5 block h-11 w-full rounded-xl border-gray-300 text-sm uppercase shadow-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-800 dark:text-white"></div>
                            <div class="flex min-w-0 flex-col"><label class="flex h-5 items-center text-[10px] font-black uppercase tracking-wider text-gray-500 dark:text-slate-300" :for="`staff-email-${member.id}`">Email Address <?php if (isset($component)) { $__componentOriginal4f074808deca33fb39dbf7707c9b77e2 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4f074808deca33fb39dbf7707c9b77e2 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.required-mark','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('required-mark'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal4f074808deca33fb39dbf7707c9b77e2)): ?>
<?php $attributes = $__attributesOriginal4f074808deca33fb39dbf7707c9b77e2; ?>
<?php unset($__attributesOriginal4f074808deca33fb39dbf7707c9b77e2); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal4f074808deca33fb39dbf7707c9b77e2)): ?>
<?php $component = $__componentOriginal4f074808deca33fb39dbf7707c9b77e2; ?>
<?php unset($__componentOriginal4f074808deca33fb39dbf7707c9b77e2); ?>
<?php endif; ?></label><input :id="`staff-email-${member.id}`" :name="`staff[${index}][email]`" type="email" required maxlength="255" x-model="member.email" placeholder="name@example.com" class="mt-1.5 block h-11 w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-800 dark:text-white"></div>
                            <div class="flex min-w-0 flex-col"><label class="flex h-5 items-center text-[10px] font-black uppercase tracking-wider text-gray-500 dark:text-slate-300" :for="`staff-contact-${member.id}`">Contact Number <?php if (isset($component)) { $__componentOriginal4f074808deca33fb39dbf7707c9b77e2 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4f074808deca33fb39dbf7707c9b77e2 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.required-mark','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('required-mark'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal4f074808deca33fb39dbf7707c9b77e2)): ?>
<?php $attributes = $__attributesOriginal4f074808deca33fb39dbf7707c9b77e2; ?>
<?php unset($__attributesOriginal4f074808deca33fb39dbf7707c9b77e2); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal4f074808deca33fb39dbf7707c9b77e2)): ?>
<?php $component = $__componentOriginal4f074808deca33fb39dbf7707c9b77e2; ?>
<?php unset($__componentOriginal4f074808deca33fb39dbf7707c9b77e2); ?>
<?php endif; ?></label><input :id="`staff-contact-${member.id}`" :name="`staff[${index}][contact]`" type="tel" required maxlength="11" inputmode="numeric" pattern="[0-9]{11}" autocomplete="tel" x-model="member.contact" x-on:input="member.contact = normalizeContactNumber($event.target.value); $event.target.value = member.contact" placeholder="09XXXXXXXXX" class="mt-1.5 block h-11 w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-800 dark:text-white"></div>
                        </div>
                    </template>
                    </div>
                </div>
                <datalist id="detailed-proposal-member-names"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $workspacePeople; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $person): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><option value="<?php echo e($person['name']); ?>"><?php echo e($person['email']); ?></option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?></datalist>
                <datalist id="detailed-proposal-professional-titles"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $professionalTitles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $title): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><option value="<?php echo e($title); ?>"></option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?></datalist>
            </section>

            <section data-revision-section="section-proponent" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <h3 class="text-base font-black text-gray-900"><?php echo e($sectionHeadings['proponent']); ?></h3>
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div><label for="proponent-department" class="block text-xs font-black uppercase tracking-wider text-gray-600">Department <span class="font-normal normal-case text-gray-400">Optional</span></label><input id="proponent-department" name="proponent_department" type="text" maxlength="255" x-model="proponentDepartment" placeholder="Leave blank if not applicable" class="mt-2 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600"></div>
                    <div><label for="proponent-college" class="block text-xs font-black uppercase tracking-wider text-gray-600">College <?php if (isset($component)) { $__componentOriginal4f074808deca33fb39dbf7707c9b77e2 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4f074808deca33fb39dbf7707c9b77e2 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.required-mark','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('required-mark'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal4f074808deca33fb39dbf7707c9b77e2)): ?>
<?php $attributes = $__attributesOriginal4f074808deca33fb39dbf7707c9b77e2; ?>
<?php unset($__attributesOriginal4f074808deca33fb39dbf7707c9b77e2); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal4f074808deca33fb39dbf7707c9b77e2)): ?>
<?php $component = $__componentOriginal4f074808deca33fb39dbf7707c9b77e2; ?>
<?php unset($__componentOriginal4f074808deca33fb39dbf7707c9b77e2); ?>
<?php endif; ?> <span class="font-normal normal-case text-gray-400">From your profile</span></label><input id="proponent-college" name="proponent_college" type="text" required maxlength="255" x-model="proponentCollege" class="mt-2 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600"></div>
                    <div><label for="proponent-campus" class="block text-xs font-black uppercase tracking-wider text-gray-600">Campus <?php if (isset($component)) { $__componentOriginal4f074808deca33fb39dbf7707c9b77e2 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4f074808deca33fb39dbf7707c9b77e2 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.required-mark','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('required-mark'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal4f074808deca33fb39dbf7707c9b77e2)): ?>
<?php $attributes = $__attributesOriginal4f074808deca33fb39dbf7707c9b77e2; ?>
<?php unset($__attributesOriginal4f074808deca33fb39dbf7707c9b77e2); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal4f074808deca33fb39dbf7707c9b77e2)): ?>
<?php $component = $__componentOriginal4f074808deca33fb39dbf7707c9b77e2; ?>
<?php unset($__componentOriginal4f074808deca33fb39dbf7707c9b77e2); ?>
<?php endif; ?></label><input id="proponent-campus" name="proponent_campus" type="text" required maxlength="255" x-model="proponentCampus" class="mt-2 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600"></div>
                    <div data-revision-section="section-cooperating-agency"><label for="cooperating-agency" class="block text-xs font-black uppercase tracking-wider text-gray-600"><?php echo e($sectionHeadings['cooperating-agency']); ?> <span class="font-normal normal-case text-gray-400">Optional</span></label><input id="cooperating-agency" name="cooperating_agency" type="text" maxlength="500" x-model="cooperatingAgency" class="mt-2 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600"></div>
                </div>
            </section>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = [
                'executive_brief' => [$sectionHeadings['executive-brief'], 'Summarize the proposed project and its intended contribution.'],
                'rationale' => [$sectionHeadings['rationale'], 'Include available statistics related to the problem.'],
            ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $field => [$label, $help]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <section data-revision-section="section-<?php echo e(str_replace('_', '-', $field)); ?>" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                    <label for="<?php echo e(str_replace('_', '-', $field)); ?>" class="block text-base font-black text-gray-900"><?php echo e($label); ?> <?php if (isset($component)) { $__componentOriginal4f074808deca33fb39dbf7707c9b77e2 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4f074808deca33fb39dbf7707c9b77e2 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.required-mark','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('required-mark'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal4f074808deca33fb39dbf7707c9b77e2)): ?>
<?php $attributes = $__attributesOriginal4f074808deca33fb39dbf7707c9b77e2; ?>
<?php unset($__attributesOriginal4f074808deca33fb39dbf7707c9b77e2); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal4f074808deca33fb39dbf7707c9b77e2)): ?>
<?php $component = $__componentOriginal4f074808deca33fb39dbf7707c9b77e2; ?>
<?php unset($__componentOriginal4f074808deca33fb39dbf7707c9b77e2); ?>
<?php endif; ?></label>
                    <p class="mt-1 text-xs text-gray-500"><?php echo e($help); ?></p>
                    <?php if (isset($component)) { $__componentOriginal73759d737dd90a230e4d720a803b57d5 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal73759d737dd90a230e4d720a803b57d5 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-figure-input','data' => ['section' => $field,'compact' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-figure-input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['section' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($field),'compact' => true]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal73759d737dd90a230e4d720a803b57d5)): ?>
<?php $attributes = $__attributesOriginal73759d737dd90a230e4d720a803b57d5; ?>
<?php unset($__attributesOriginal73759d737dd90a230e4d720a803b57d5); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal73759d737dd90a230e4d720a803b57d5)): ?>
<?php $component = $__componentOriginal73759d737dd90a230e4d720a803b57d5; ?>
<?php unset($__componentOriginal73759d737dd90a230e4d720a803b57d5); ?>
<?php endif; ?>
                    <textarea id="<?php echo e(str_replace('_', '-', $field)); ?>" name="<?php echo e($field); ?>" rows="<?php echo e($field === 'rationale' ? 14 : 9); ?>" required maxlength="<?php echo e(config('detailed_proposal.maximum_narrative_length')); ?>" x-model="<?php echo e(\Illuminate\Support\Str::camel($field)); ?>" data-semantic-editor class="mt-4 block w-full rounded-xl border-gray-300 text-sm leading-6 shadow-sm focus:border-red-600 focus:ring-red-600"></textarea>
                </section>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>

            <section data-revision-section="section-objectives" id="specific-objectives" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <input type="hidden" name="specific_objectives_present" value="1">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h3 class="text-base font-black text-gray-900"><?php echo e($sectionHeadings['objectives']); ?></h3>
                        <p class="mt-1 text-xs text-gray-500">Enter at least one specific objective in the required fields below. The general objective is optional. Numbering is generated automatically.</p>
                    </div>
                    <button type="button" x-on:click="addSpecificObjective" class="inline-flex shrink-0 rounded-xl border border-red-200 px-4 py-2 text-xs font-bold text-red-700 hover:bg-red-50">Add specific objective</button>
                </div>
                <label for="general-objective" class="mt-5 block text-xs font-black uppercase tracking-wider text-gray-600">General objective <span class="font-normal normal-case text-gray-400">Optional</span></label>
                <textarea id="general-objective" name="general_objective" rows="4" maxlength="<?php echo e(config('detailed_proposal.maximum_narrative_length')); ?>" x-model="generalObjective" data-semantic-editor class="mt-2 block w-full rounded-xl border-gray-300 text-sm leading-6 shadow-sm focus:border-red-600 focus:ring-red-600"></textarea>
                <div class="mt-5 space-y-3" data-specific-objectives-fields>
                    <h4 class="text-xs font-black uppercase tracking-wider text-gray-600">Specific objectives <?php if (isset($component)) { $__componentOriginal4f074808deca33fb39dbf7707c9b77e2 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4f074808deca33fb39dbf7707c9b77e2 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.required-mark','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('required-mark'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal4f074808deca33fb39dbf7707c9b77e2)): ?>
<?php $attributes = $__attributesOriginal4f074808deca33fb39dbf7707c9b77e2; ?>
<?php unset($__attributesOriginal4f074808deca33fb39dbf7707c9b77e2); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal4f074808deca33fb39dbf7707c9b77e2)): ?>
<?php $component = $__componentOriginal4f074808deca33fb39dbf7707c9b77e2; ?>
<?php unset($__componentOriginal4f074808deca33fb39dbf7707c9b77e2); ?>
<?php endif; ?> <span class="font-normal normal-case text-gray-500">At least one</span></h4>
                    <p class="text-xs leading-5 text-gray-500">Describe the concrete results or tasks your project will achieve. Text in General objective does not fill these fields.</p>
                    <p x-show="requirementsReviewed && completionErrors.specific_objectives" x-cloak class="text-xs font-semibold text-red-700 dark:text-red-300" x-text="(completionErrors.specific_objectives || []).join(' ')"></p>
                    <template x-for="(objective, index) in specificObjectives" :key="objective.id">
                        <article class="rounded-xl border border-gray-200 p-4">
                            <div class="flex items-center justify-between gap-3">
                                <label class="text-xs font-black uppercase tracking-wider text-gray-600" :for="`specific-objective-${objective.id}`"><span x-text="`${index + 1}. Specific objective`"></span> <?php if (isset($component)) { $__componentOriginal4f074808deca33fb39dbf7707c9b77e2 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4f074808deca33fb39dbf7707c9b77e2 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.required-mark','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('required-mark'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal4f074808deca33fb39dbf7707c9b77e2)): ?>
<?php $attributes = $__attributesOriginal4f074808deca33fb39dbf7707c9b77e2; ?>
<?php unset($__attributesOriginal4f074808deca33fb39dbf7707c9b77e2); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal4f074808deca33fb39dbf7707c9b77e2)): ?>
<?php $component = $__componentOriginal4f074808deca33fb39dbf7707c9b77e2; ?>
<?php unset($__componentOriginal4f074808deca33fb39dbf7707c9b77e2); ?>
<?php endif; ?></label>
                                <div class="flex gap-1">
                                    <button type="button" x-on:click="moveSpecificObjective(index, -1)" :disabled="index === 0" class="secondary-action">Up</button>
                                    <button type="button" x-on:click="moveSpecificObjective(index, 1)" :disabled="index === specificObjectives.length - 1" class="secondary-action">Down</button>
                                    <button type="button" x-on:click="removeSpecificObjective(index)" class="secondary-action">Remove</button>
                                </div>
                            </div>
                            <textarea :id="`specific-objective-${objective.id}`" :name="`specific_objectives[${index}][description]`" rows="3" required maxlength="<?php echo e(config('detailed_proposal.maximum_narrative_length')); ?>" x-model="objective.description" placeholder="e.g. Identify the needs of the target community." class="mt-2 block w-full rounded-xl border-gray-300 text-sm leading-6 shadow-sm focus:border-red-600 focus:ring-red-600"></textarea>
                        </article>
                    </template>
                </div>
            </section>

            <section data-revision-section="section-expected-outputs" id="expected-outputs" data-detailed-proposal-validation-group="expected-outputs" tabindex="-1" class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm sm:p-5">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Expected outputs <?php if (isset($component)) { $__componentOriginal4f074808deca33fb39dbf7707c9b77e2 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4f074808deca33fb39dbf7707c9b77e2 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.required-mark','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('required-mark'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal4f074808deca33fb39dbf7707c9b77e2)): ?>
<?php $attributes = $__attributesOriginal4f074808deca33fb39dbf7707c9b77e2; ?>
<?php unset($__attributesOriginal4f074808deca33fb39dbf7707c9b77e2); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal4f074808deca33fb39dbf7707c9b77e2)): ?>
<?php $component = $__componentOriginal4f074808deca33fb39dbf7707c9b77e2; ?>
<?php unset($__componentOriginal4f074808deca33fb39dbf7707c9b77e2); ?>
<?php endif; ?></h3>
                <div class="mt-3 grid items-start gap-2 sm:grid-cols-2" data-expected-output-list>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = [0, 1]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $column): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <div class="flex min-w-0 flex-col gap-2">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $expectedOutputs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <?php if($loop->index % 2 !== $column) continue; ?>
                                <div data-output-category="<?php echo e($key); ?>" class="overflow-hidden rounded-lg border border-gray-300 bg-white dark:border-slate-600 dark:bg-slate-900">
                                    <header class="flex items-center justify-between gap-3 bg-gray-50 px-3 py-1.5 dark:bg-slate-800">
                                        <div class="flex min-w-0 items-center gap-2.5">
                                            <?php if (isset($component)) { $__componentOriginal7a9485575b48ddf8197622ac7859a0fe = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal7a9485575b48ddf8197622ac7859a0fe = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-writing-icon','data' => ['name' => $key,'dataOutputCategoryIcon' => true,'class' => 'h-4 w-4 shrink-0 text-[#800020] dark:text-red-300']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-writing-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($key),'data-output-category-icon' => true,'class' => 'h-4 w-4 shrink-0 text-[#800020] dark:text-red-300']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal7a9485575b48ddf8197622ac7859a0fe)): ?>
<?php $attributes = $__attributesOriginal7a9485575b48ddf8197622ac7859a0fe; ?>
<?php unset($__attributesOriginal7a9485575b48ddf8197622ac7859a0fe); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal7a9485575b48ddf8197622ac7859a0fe)): ?>
<?php $component = $__componentOriginal7a9485575b48ddf8197622ac7859a0fe; ?>
<?php unset($__componentOriginal7a9485575b48ddf8197622ac7859a0fe); ?>
<?php endif; ?>
                                            <h4 class="text-xs font-semibold text-gray-900 dark:text-slate-100"><?php echo e($label); ?> <span x-show="expectedOutputs.<?php echo e($key); ?>.length" x-cloak><?php if (isset($component)) { $__componentOriginal4f074808deca33fb39dbf7707c9b77e2 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4f074808deca33fb39dbf7707c9b77e2 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.required-mark','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('required-mark'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal4f074808deca33fb39dbf7707c9b77e2)): ?>
<?php $attributes = $__attributesOriginal4f074808deca33fb39dbf7707c9b77e2; ?>
<?php unset($__attributesOriginal4f074808deca33fb39dbf7707c9b77e2); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal4f074808deca33fb39dbf7707c9b77e2)): ?>
<?php $component = $__componentOriginal4f074808deca33fb39dbf7707c9b77e2; ?>
<?php unset($__componentOriginal4f074808deca33fb39dbf7707c9b77e2); ?>
<?php endif; ?></span></h4>
                                        </div>
                                        <button type="button" x-on:click="addExpectedOutput('<?php echo e($key); ?>')" aria-label="Add <?php echo e($label); ?> output" title="Add <?php echo e($label); ?> output" class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-[#800020] text-white transition hover:bg-[#600018] focus:outline-none focus-visible:ring-2 focus-visible:ring-red-700 focus-visible:ring-offset-2 dark:bg-red-700 dark:hover:bg-red-600">
                                            <svg class="h-4 w-4" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M8 3v10M3 8h10" stroke-linecap="round"/></svg>
                                        </button>
                                    </header>
                                    <div x-show="expectedOutputs.<?php echo e($key); ?>.length" x-cloak class="flex flex-col gap-2 border-t border-gray-200 p-2.5 dark:border-slate-700">
                                        <template x-for="(output, index) in expectedOutputs.<?php echo e($key); ?>" :key="output.id">
                                            <div class="relative">
                                                <label :for="`expected-output-<?php echo e($key); ?>-${output.id}`" class="sr-only"><?php echo e($label); ?> <span x-text="index + 1"></span> <?php if (isset($component)) { $__componentOriginal4f074808deca33fb39dbf7707c9b77e2 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4f074808deca33fb39dbf7707c9b77e2 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.required-mark','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('required-mark'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal4f074808deca33fb39dbf7707c9b77e2)): ?>
<?php $attributes = $__attributesOriginal4f074808deca33fb39dbf7707c9b77e2; ?>
<?php unset($__attributesOriginal4f074808deca33fb39dbf7707c9b77e2); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal4f074808deca33fb39dbf7707c9b77e2)): ?>
<?php $component = $__componentOriginal4f074808deca33fb39dbf7707c9b77e2; ?>
<?php unset($__componentOriginal4f074808deca33fb39dbf7707c9b77e2); ?>
<?php endif; ?></label>
                                                <textarea :id="`expected-output-<?php echo e($key); ?>-${output.id}`" :name="`expected_outputs[<?php echo e($key); ?>][${index}][description]`" rows="2" required maxlength="<?php echo e(config('detailed_proposal.maximum_narrative_length')); ?>" x-model="output.description" aria-label="<?php echo e($label); ?> description" class="block w-full rounded-md border-gray-300 pr-10 text-sm leading-5 focus:border-red-700 focus:ring-red-700 dark:border-slate-600 dark:bg-slate-900 dark:text-white"></textarea>
                                                <button type="button" x-on:click="removeExpectedOutput('<?php echo e($key); ?>', index)" class="absolute right-1 top-1 inline-flex h-7 w-7 items-center justify-center rounded text-gray-500 transition hover:bg-gray-100 hover:text-red-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-600 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-red-400" :aria-label="`Remove <?php echo e($label); ?> output ${index + 1}`" title="Remove output">
                                                    <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M3 4h10M6 4V2.5h4V4M5 6.5v5M8 6.5v5M11 6.5v5M4 4l.7 10h6.6L12 4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                                </button>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </div>
            </section>

            <section data-revision-section="section-literature" x-ref="introductionSection" class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm sm:p-5 dark:border-slate-700 dark:bg-slate-900">
                <input type="hidden" name="introduction" x-model="introduction">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0 flex-1 basis-64">
                        <label for="related-literature" class="text-base font-semibold text-gray-900 dark:text-white">Review of related literature <?php if (isset($component)) { $__componentOriginal4f074808deca33fb39dbf7707c9b77e2 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4f074808deca33fb39dbf7707c9b77e2 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.required-mark','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('required-mark'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal4f074808deca33fb39dbf7707c9b77e2)): ?>
<?php $attributes = $__attributesOriginal4f074808deca33fb39dbf7707c9b77e2; ?>
<?php unset($__attributesOriginal4f074808deca33fb39dbf7707c9b77e2); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal4f074808deca33fb39dbf7707c9b77e2)): ?>
<?php $component = $__componentOriginal4f074808deca33fb39dbf7707c9b77e2; ?>
<?php unset($__componentOriginal4f074808deca33fb39dbf7707c9b77e2); ?>
<?php endif; ?></label>
                        <p class="mt-2 text-xs text-gray-500 dark:text-slate-400">Include at least ten relevant studies or literature sources.</p>
                    </div>
                    <div class="flex max-w-full flex-wrap items-center justify-end gap-2">
                        <button type="button" @click="openSourceWorkspace('search')" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-gray-300 px-3 py-2 text-base font-semibold text-gray-800 transition hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-700 focus-visible:ring-offset-2 dark:border-slate-600 dark:text-slate-100 dark:hover:bg-slate-800 dark:focus-visible:ring-offset-slate-900">
                            <?php if (isset($component)) { $__componentOriginal7a9485575b48ddf8197622ac7859a0fe = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal7a9485575b48ddf8197622ac7859a0fe = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-writing-icon','data' => ['name' => 'publication','class' => 'h-4 w-4 text-[#800020] dark:text-red-300']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-writing-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'publication','class' => 'h-4 w-4 text-[#800020] dark:text-red-300']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal7a9485575b48ddf8197622ac7859a0fe)): ?>
<?php $attributes = $__attributesOriginal7a9485575b48ddf8197622ac7859a0fe; ?>
<?php unset($__attributesOriginal7a9485575b48ddf8197622ac7859a0fe); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal7a9485575b48ddf8197622ac7859a0fe)): ?>
<?php $component = $__componentOriginal7a9485575b48ddf8197622ac7859a0fe; ?>
<?php unset($__componentOriginal7a9485575b48ddf8197622ac7859a0fe); ?>
<?php endif; ?>
                            <span>Find sources</span>
                        </button>
                        <?php if (isset($component)) { $__componentOriginaldf8083d4a852c446488d8d384bbc7cbe = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginaldf8083d4a852c446488d8d384bbc7cbe = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.dropdown','data' => ['align' => 'right','width' => '48','contentClasses' => 'py-1 bg-white dark:bg-slate-900']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('dropdown'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['align' => 'right','width' => '48','content-classes' => 'py-1 bg-white dark:bg-slate-900']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

                             <?php $__env->slot('trigger', null, []); ?> 
                                <button type="button" x-ref="rrlAssistanceTrigger" :aria-expanded="open" aria-controls="rrl-assistance-actions" :disabled="rrlRevisionPending || proposalChangeApplying || autoSaveBlocked" @keydown.escape.stop.prevent="open = false" aria-label="Use AI for RRL" title="Draft or revise RRL with AI" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-transparent bg-[#800020] px-3 py-2 text-base font-semibold text-white transition hover:bg-[#600018] focus:outline-none focus-visible:ring-2 focus-visible:ring-red-700 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-red-700 dark:hover:bg-red-600 dark:focus-visible:ring-offset-slate-900">
                                    <?php if (isset($component)) { $__componentOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.assistant-icon','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('assistant-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b)): ?>
<?php $attributes = $__attributesOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b; ?>
<?php unset($__attributesOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b)): ?>
<?php $component = $__componentOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b; ?>
<?php unset($__componentOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b); ?>
<?php endif; ?>
                                    <span>Use AI</span>
                                </button>
                             <?php $__env->endSlot(); ?>
                             <?php $__env->slot('content', null, []); ?> 
                                <div id="rrl-assistance-actions" role="group" aria-label="RRL assistance" @keydown.escape.stop.prevent="open = false; $refs.rrlAssistanceTrigger.focus()">
                                    <button type="button" @click="openRrlChat()" class="flex min-h-11 w-full items-center gap-2 px-4 py-2 text-left text-base text-gray-900 hover:bg-gray-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-red-700 dark:text-white dark:hover:bg-slate-800"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 11.5a8.4 8.4 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.4 8.4 0 0 1-3.8-.9L3 21l1.9-5.7a8.4 8.4 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.4 8.4 0 0 1 3.8-.9h.5a8.5 8.5 0 0 1 8 8v.5Z" /></svg><span>Open chat</span></button>
                                    <button type="button" @click="requestRrlRevision('check')" :disabled="rrlRevisionPending || proposalChangeApplying || autoSaveBlocked" class="flex min-h-11 w-full items-center gap-2 px-4 py-2 text-left text-base text-gray-900 hover:bg-gray-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-red-700 disabled:opacity-50 dark:text-white dark:hover:bg-slate-800"><?php if (isset($component)) { $__componentOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.assistant-icon','data' => ['class' => 'h-4 w-4']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('assistant-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'h-4 w-4']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b)): ?>
<?php $attributes = $__attributesOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b; ?>
<?php unset($__attributesOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b)): ?>
<?php $component = $__componentOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b; ?>
<?php unset($__componentOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b); ?>
<?php endif; ?><span>Check my RRL</span></button>
                                    <button type="button" @click="requestRrlRevision('draft')" :disabled="rrlRevisionPending || proposalChangeApplying || autoSaveBlocked" class="flex min-h-11 w-full items-center gap-2 px-4 py-2 text-left text-base text-gray-900 hover:bg-gray-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-red-700 disabled:opacity-50 dark:text-white dark:hover:bg-slate-800"><?php if (isset($component)) { $__componentOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.assistant-icon','data' => ['class' => 'h-4 w-4']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('assistant-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'h-4 w-4']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b)): ?>
<?php $attributes = $__attributesOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b; ?>
<?php unset($__attributesOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b)): ?>
<?php $component = $__componentOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b; ?>
<?php unset($__componentOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b); ?>
<?php endif; ?><span>Draft RRL</span></button>
                                    <button type="button" @click="requestRrlRevision('revise')" :disabled="rrlRevisionPending || proposalChangeApplying || autoSaveBlocked" class="flex min-h-11 w-full items-center gap-2 px-4 py-2 text-left text-base text-gray-900 hover:bg-gray-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-red-700 disabled:opacity-50 dark:text-white dark:hover:bg-slate-800"><?php if (isset($component)) { $__componentOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.assistant-icon','data' => ['class' => 'h-4 w-4']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('assistant-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'h-4 w-4']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b)): ?>
<?php $attributes = $__attributesOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b; ?>
<?php unset($__attributesOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b)): ?>
<?php $component = $__componentOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b; ?>
<?php unset($__componentOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b); ?>
<?php endif; ?><span>Revise RRL</span></button>
                                </div>
                             <?php $__env->endSlot(); ?>
                         <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginaldf8083d4a852c446488d8d384bbc7cbe)): ?>
<?php $attributes = $__attributesOriginaldf8083d4a852c446488d8d384bbc7cbe; ?>
<?php unset($__attributesOriginaldf8083d4a852c446488d8d384bbc7cbe); ?>
<?php endif; ?>
<?php if (isset($__componentOriginaldf8083d4a852c446488d8d384bbc7cbe)): ?>
<?php $component = $__componentOriginaldf8083d4a852c446488d8d384bbc7cbe; ?>
<?php unset($__componentOriginaldf8083d4a852c446488d8d384bbc7cbe); ?>
<?php endif; ?>
                    </div>
                </div>
                <?php if (isset($component)) { $__componentOriginal73759d737dd90a230e4d720a803b57d5 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal73759d737dd90a230e4d720a803b57d5 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-figure-input','data' => ['section' => 'related_literature','compact' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-figure-input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['section' => 'related_literature','compact' => true]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal73759d737dd90a230e4d720a803b57d5)): ?>
<?php $attributes = $__attributesOriginal73759d737dd90a230e4d720a803b57d5; ?>
<?php unset($__attributesOriginal73759d737dd90a230e4d720a803b57d5); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal73759d737dd90a230e4d720a803b57d5)): ?>
<?php $component = $__componentOriginal73759d737dd90a230e4d720a803b57d5; ?>
<?php unset($__componentOriginal73759d737dd90a230e4d720a803b57d5); ?>
<?php endif; ?>
                <textarea id="related-literature" name="related_literature" rows="10" required maxlength="<?php echo e(config('detailed_proposal.maximum_narrative_length') * 2 + 2); ?>" x-model="relatedLiterature" data-semantic-editor class="mt-3 block w-full scroll-mt-36 rounded-lg border-gray-300 text-sm leading-6 shadow-sm focus:border-red-700 focus:ring-red-700 dark:border-slate-600 dark:bg-slate-900 dark:text-white"></textarea>
            </section>

            <section data-revision-section="section-methodology" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900 sm:p-6">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h3 class="text-base font-black text-gray-900 dark:text-white"><?php echo e($sectionHeadings['methodology']); ?></h3>
                        <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-slate-400">Add figures to any methodology part. Data Analysis is optional and is omitted from the output when blank.</p>
                    </div>
                </div>
                <div class="mt-5 space-y-5">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $methodologyFields; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <div <?php if($key === 'specific_methods'): ?> data-specific-methods-editor class="specific-methods-editor" <?php endif; ?>>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($key === 'specific_methods'): ?>
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <h4 class="text-sm font-semibold text-gray-900 dark:text-white">Specific methods <?php if (isset($component)) { $__componentOriginal4f074808deca33fb39dbf7707c9b77e2 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4f074808deca33fb39dbf7707c9b77e2 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.required-mark','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('required-mark'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal4f074808deca33fb39dbf7707c9b77e2)): ?>
<?php $attributes = $__attributesOriginal4f074808deca33fb39dbf7707c9b77e2; ?>
<?php unset($__attributesOriginal4f074808deca33fb39dbf7707c9b77e2); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal4f074808deca33fb39dbf7707c9b77e2)): ?>
<?php $component = $__componentOriginal4f074808deca33fb39dbf7707c9b77e2; ?>
<?php unset($__componentOriginal4f074808deca33fb39dbf7707c9b77e2); ?>
<?php endif; ?></h4>
                                    <button type="button" x-on:click="addSpecificMethodGroup()" :disabled="specificMethodGroups.length >= 20" class="secondary-action">
                                        <svg class="h-4 w-4" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M8 3v10M3 8h10" stroke-linecap="round"/></svg>
                                        Add heading
                                    </button>
                                </div>
                            <?php else: ?>
                                <label for="methodology-<?php echo e($key); ?>" class="block text-xs font-black uppercase tracking-wider text-gray-600 dark:text-slate-300">&bull; <?php echo e($label); ?> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($key !== 'data_analysis'): ?><?php if (isset($component)) { $__componentOriginal4f074808deca33fb39dbf7707c9b77e2 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4f074808deca33fb39dbf7707c9b77e2 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.required-mark','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('required-mark'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal4f074808deca33fb39dbf7707c9b77e2)): ?>
<?php $attributes = $__attributesOriginal4f074808deca33fb39dbf7707c9b77e2; ?>
<?php unset($__attributesOriginal4f074808deca33fb39dbf7707c9b77e2); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal4f074808deca33fb39dbf7707c9b77e2)): ?>
<?php $component = $__componentOriginal4f074808deca33fb39dbf7707c9b77e2; ?>
<?php unset($__componentOriginal4f074808deca33fb39dbf7707c9b77e2); ?>
<?php endif; ?><?php else: ?><span class="font-normal normal-case text-gray-400">Optional</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></label>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <?php if (isset($component)) { $__componentOriginal73759d737dd90a230e4d720a803b57d5 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal73759d737dd90a230e4d720a803b57d5 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-figure-input','data' => ['section' => $key,'compact' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-figure-input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['section' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($key),'compact' => true]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal73759d737dd90a230e4d720a803b57d5)): ?>
<?php $attributes = $__attributesOriginal73759d737dd90a230e4d720a803b57d5; ?>
<?php unset($__attributesOriginal73759d737dd90a230e4d720a803b57d5); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal73759d737dd90a230e4d720a803b57d5)): ?>
<?php $component = $__componentOriginal73759d737dd90a230e4d720a803b57d5; ?>
<?php unset($__componentOriginal73759d737dd90a230e4d720a803b57d5); ?>
<?php endif; ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($key === 'specific_methods'): ?>
                                <div id="methodology-specific-methods" class="mt-2">
                                    <input type="hidden" name="methodology[specific_methods]" x-bind:value="specificMethodsDocumentText()">
                                    <div class="specific-methods-grid grid items-start gap-2">
                                        <template x-for="(group, groupIndex) in specificMethodGroups" :key="`specific-method-group-${group.id}`">
                                            <article data-specific-method-group class="min-w-0 overflow-hidden rounded-lg border border-gray-300 bg-white dark:border-slate-600 dark:bg-slate-900" :aria-label="`Methods: ${group.heading || 'Untitled heading'}`">
                                                <header class="flex items-center gap-2 bg-gray-50 px-3 py-1.5 dark:bg-slate-800">
                                                    <span class="shrink-0 text-xs font-semibold text-[#800020] dark:text-red-300" aria-hidden="true" x-text="`${specificMethodGroupLetter(groupIndex)}.`"></span>
                                                    <label :for="`method-heading-${group.id}`" class="sr-only">Method heading <?php if (isset($component)) { $__componentOriginal4f074808deca33fb39dbf7707c9b77e2 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4f074808deca33fb39dbf7707c9b77e2 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.required-mark','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('required-mark'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal4f074808deca33fb39dbf7707c9b77e2)): ?>
<?php $attributes = $__attributesOriginal4f074808deca33fb39dbf7707c9b77e2; ?>
<?php unset($__attributesOriginal4f074808deca33fb39dbf7707c9b77e2); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal4f074808deca33fb39dbf7707c9b77e2)): ?>
<?php $component = $__componentOriginal4f074808deca33fb39dbf7707c9b77e2; ?>
<?php unset($__componentOriginal4f074808deca33fb39dbf7707c9b77e2); ?>
<?php endif; ?></label>
                                                    <textarea :id="`method-heading-${group.id}`" :name="`specific_method_objectives[${groupIndex}][heading]`" rows="1" required maxlength="<?php echo e(config('detailed_proposal.maximum_narrative_length')); ?>" x-model="group.heading" :aria-label="`Method heading ${specificMethodGroupLetter(groupIndex)}`" placeholder="Method heading" class="specific-method-heading block min-w-0 flex-1 rounded border-0 bg-transparent px-0 py-3 text-base font-semibold leading-6 text-gray-900 placeholder:text-gray-500 focus:ring-red-700 dark:text-white dark:placeholder:text-slate-400"></textarea>
                                                    <button type="button" x-on:click="addSpecificMethod(group)" :disabled="group.methods.length >= 20" :aria-label="`Add method to group ${specificMethodGroupLetter(groupIndex)}`" title="Add method" class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-md bg-[#800020] text-white transition hover:bg-[#600018] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-red-700 dark:hover:bg-red-600">
                                                        <svg class="h-4 w-4" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M8 3v10M3 8h10" stroke-linecap="round"/></svg>
                                                    </button>
                                                    <button x-show="specificMethodGroups.length > 1" type="button" x-on:click="removeSpecificMethodGroup(groupIndex)" :aria-label="`Remove method group ${specificMethodGroupLetter(groupIndex)}`" title="Remove heading" class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-md text-gray-500 transition hover:bg-gray-100 hover:text-red-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-red-700 dark:text-slate-400 dark:hover:bg-slate-700 dark:hover:text-red-300">
                                                        <svg class="h-4 w-4" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M3 4h10M6 4V2.5h4V4M5 6.5v5M8 6.5v5M11 6.5v5M4 4l.7 10h6.6L12 4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                                    </button>
                                                </header>
                                                <div class="flex flex-col gap-2 border-t border-gray-200 p-2.5 dark:border-slate-700">
                                                    <template x-for="(method, methodIndex) in group.methods" :key="method.id">
                                                        <div class="grid grid-cols-[1.25rem_minmax(0,1fr)] items-start gap-1">
                                                            <label :for="`method-${group.id}-${method.id}`" class="pt-2 text-xs font-semibold text-gray-500 dark:text-slate-400"><span class="sr-only">Method </span><span x-text="`${methodIndex + 1}.`"></span></label>
                                                            <div class="relative min-w-0">
                                                                <textarea :id="`method-${group.id}-${method.id}`" :name="`specific_method_objectives[${groupIndex}][methods][${methodIndex}][description]`" rows="2" required maxlength="<?php echo e(config('detailed_proposal.maximum_narrative_length')); ?>" x-model="method.description" :aria-label="`Method ${methodIndex + 1} for group ${specificMethodGroupLetter(groupIndex)}`" placeholder="Describe this method" class="specific-method-description block w-full rounded-md border-gray-300 pr-12 text-base leading-6 focus:border-red-700 focus:ring-red-700 dark:border-slate-600 dark:bg-slate-900 dark:text-white dark:placeholder:text-slate-500"></textarea>
                                                                <button x-show="group.methods.length > 1" type="button" x-on:click="removeSpecificMethod(group, methodIndex)" :aria-label="`Remove method ${methodIndex + 1} from group ${specificMethodGroupLetter(groupIndex)}`" title="Remove method" class="absolute right-0 top-0 inline-flex h-11 w-11 items-center justify-center rounded-md text-gray-500 transition hover:bg-gray-100 hover:text-red-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-red-700 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-red-300">
                                                                    <svg class="h-4 w-4" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M3 4h10M6 4V2.5h4V4M5 6.5v5M8 6.5v5M11 6.5v5M4 4l.7 10h6.6L12 4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </template>
                                                    <template x-if="!group.methods.length"><input type="hidden" :name="`specific_method_objectives[${groupIndex}][methods][0][description]`" value=""></template>
                                                    <p x-show="group.methods.length >= 20" x-cloak class="text-xs text-gray-500 dark:text-slate-400">Each heading can contain up to 20 methods.</p>
                                                </div>
                                            </article>
                                        </template>
                                    </div>
                                    <p x-show="specificMethodGroups.length >= 20" x-cloak class="mt-2 text-xs text-gray-500 dark:text-slate-400">You can add up to 20 headings.</p>
                                </div>
                            <?php else: ?>
                                <textarea id="methodology-<?php echo e($key); ?>" name="methodology[<?php echo e($key); ?>]" rows="7" <?php if($key !== 'data_analysis'): echo 'required'; endif; ?> maxlength="<?php echo e(config('detailed_proposal.maximum_narrative_length')); ?>" x-model="methodology.<?php echo e($key); ?>" aria-label="<?php echo e($label); ?> narrative" data-semantic-editor class="mt-3 block w-full rounded-xl border-gray-300 text-sm leading-6 shadow-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-900 dark:text-white"></textarea>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </div>
            </section>

            <section data-revision-section="section-responsibilities" id="responsibilities" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"><div><h3 class="text-base font-black text-gray-900"><?php echo e($sectionHeadings['responsibilities']); ?></h3><p class="mt-1 text-xs text-gray-500">Edited percentages stay fixed; other members share the remainder. Each member needs at least 1%.</p></div><button type="button" x-on:click="addResponsibility" class="self-start shrink-0 rounded-xl border border-gray-300 px-4 py-2.5 text-xs font-bold text-gray-700 hover:bg-gray-50">Add member</button></div>
                <p id="responsibility-allocation-status" role="status" class="mt-3 text-xs leading-5" :class="responsibilityAllocationError() ? 'text-red-700 dark:text-red-300' : 'text-gray-500 dark:text-slate-400'" x-text="responsibilityAllocationError() || `Total: ${responsibilityTotal()}%`"></p>
                <div class="mt-5 space-y-4">
                    <template x-for="(responsibility, index) in responsibilities" :key="responsibility.id">
                        <div class="rounded-xl border border-gray-200 p-4">
                            <input type="hidden" :name="`responsibilities[${index}][percentage_fixed]`" :value="responsibility.percentageFixed ? '1' : '0'">
                            <div class="grid items-end gap-3 sm:grid-cols-[minmax(0,1fr)_9rem_auto]">
                                <div><label class="text-[10px] font-black uppercase tracking-wider text-gray-500" :for="`responsibility-name-${responsibility.id}`">Member name <?php if (isset($component)) { $__componentOriginal4f074808deca33fb39dbf7707c9b77e2 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4f074808deca33fb39dbf7707c9b77e2 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.required-mark','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('required-mark'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal4f074808deca33fb39dbf7707c9b77e2)): ?>
<?php $attributes = $__attributesOriginal4f074808deca33fb39dbf7707c9b77e2; ?>
<?php unset($__attributesOriginal4f074808deca33fb39dbf7707c9b77e2); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal4f074808deca33fb39dbf7707c9b77e2)): ?>
<?php $component = $__componentOriginal4f074808deca33fb39dbf7707c9b77e2; ?>
<?php unset($__componentOriginal4f074808deca33fb39dbf7707c9b77e2); ?>
<?php endif; ?></label><input :id="`responsibility-name-${responsibility.id}`" :name="`responsibilities[${index}][name]`" type="text" required maxlength="255" list="detailed-proposal-member-names" x-model="responsibility.name" class="mt-1.5 block w-full rounded-xl border-gray-300 text-sm uppercase shadow-sm focus:border-red-600 focus:ring-red-600"></div>
                                <div><label class="text-[10px] font-black uppercase tracking-wider text-gray-500" :for="`responsibility-percentage-${responsibility.id}`">Responsibility % <?php if (isset($component)) { $__componentOriginal4f074808deca33fb39dbf7707c9b77e2 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4f074808deca33fb39dbf7707c9b77e2 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.required-mark','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('required-mark'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal4f074808deca33fb39dbf7707c9b77e2)): ?>
<?php $attributes = $__attributesOriginal4f074808deca33fb39dbf7707c9b77e2; ?>
<?php unset($__attributesOriginal4f074808deca33fb39dbf7707c9b77e2); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal4f074808deca33fb39dbf7707c9b77e2)): ?>
<?php $component = $__componentOriginal4f074808deca33fb39dbf7707c9b77e2; ?>
<?php unset($__componentOriginal4f074808deca33fb39dbf7707c9b77e2); ?>
<?php endif; ?></label><input :id="`responsibility-percentage-${responsibility.id}`" :name="`responsibilities[${index}][percentage]`" type="number" required min="1" :max="availableResponsibilityPercentage(responsibility)" step="1" :value="responsibility.percentage" @input="setResponsibilityPercentage(responsibility, $event.target.value)" :aria-describedby="`responsibility-share-${responsibility.id} responsibility-allocation-status`" class="mt-1.5 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600"></div>
                                <button type="button" x-on:click="removeResponsibility(index)" x-bind:disabled="responsibilities.length === 1" class="secondary-action">Remove</button>
                            </div>
                            <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs">
                                <p :id="`responsibility-share-${responsibility.id}`" class="text-gray-500 dark:text-slate-400" x-text="`${responsibility.percentageFixed ? 'Fixed' : 'Automatic'} share · Up to ${availableResponsibilityPercentage(responsibility)}% available`"></p>
                                <button x-show="responsibility.percentageFixed" type="button" @click="setResponsibilityAutomatic(responsibility)" :aria-label="`Use automatic percentage for ${responsibility.name || 'member ' + (index + 1)}`" class="secondary-action">Use Auto</button>
                            </div>
                            <label class="mt-3 block text-[10px] font-black uppercase tracking-wider text-gray-500" :for="`responsibility-duties-${responsibility.id}`">Duties and responsibilities <?php if (isset($component)) { $__componentOriginal4f074808deca33fb39dbf7707c9b77e2 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4f074808deca33fb39dbf7707c9b77e2 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.required-mark','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('required-mark'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal4f074808deca33fb39dbf7707c9b77e2)): ?>
<?php $attributes = $__attributesOriginal4f074808deca33fb39dbf7707c9b77e2; ?>
<?php unset($__attributesOriginal4f074808deca33fb39dbf7707c9b77e2); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal4f074808deca33fb39dbf7707c9b77e2)): ?>
<?php $component = $__componentOriginal4f074808deca33fb39dbf7707c9b77e2; ?>
<?php unset($__componentOriginal4f074808deca33fb39dbf7707c9b77e2); ?>
<?php endif; ?></label><textarea :id="`responsibility-duties-${responsibility.id}`" :name="`responsibilities[${index}][duties]`" rows="5" required maxlength="<?php echo e(config('detailed_proposal.maximum_narrative_length')); ?>" x-model="responsibility.duties" data-semantic-editor class="mt-1.5 block w-full rounded-xl border-gray-300 text-sm leading-6 shadow-sm focus:border-red-600 focus:ring-red-600"></textarea>
                        </div>
                    </template>
                </div>
            </section>

            <section data-revision-section="section-work-plan" data-proposal-attachment-reference class="border-l-2 border-slate-200 py-1 pl-3 dark:border-slate-700">
                <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200"><?php echo e($sectionHeadings['work-plan']); ?></h3>
                <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">Provided in Work Plan (Form A).</p>
            </section>

            <section data-revision-section="section-budget" data-proposal-attachment-reference class="border-l-2 border-slate-200 py-1 pl-3 dark:border-slate-700">
                <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200"><?php echo e($sectionHeadings['budget']); ?></h3>
                <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">Provided in Line-Item Budget (Form B).</p>
            </section>

            <section data-revision-section="section-references" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <label for="references" class="block text-base font-black text-gray-900"><?php echo e($sectionHeadings['references']); ?> <?php if (isset($component)) { $__componentOriginal4f074808deca33fb39dbf7707c9b77e2 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4f074808deca33fb39dbf7707c9b77e2 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.required-mark','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('required-mark'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal4f074808deca33fb39dbf7707c9b77e2)): ?>
<?php $attributes = $__attributesOriginal4f074808deca33fb39dbf7707c9b77e2; ?>
<?php unset($__attributesOriginal4f074808deca33fb39dbf7707c9b77e2); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal4f074808deca33fb39dbf7707c9b77e2)): ?>
<?php $component = $__componentOriginal4f074808deca33fb39dbf7707c9b77e2; ?>
<?php unset($__componentOriginal4f074808deca33fb39dbf7707c9b77e2); ?>
<?php endif; ?></label>
                <p class="mt-1 text-xs text-gray-500">Enter one reference per line or separate entries with blank lines.</p>
                <textarea id="references" name="references" rows="12" required maxlength="<?php echo e(config('detailed_proposal.maximum_narrative_length')); ?>" x-model="references" data-semantic-editor class="mt-4 block w-full scroll-mt-36 rounded-xl border-gray-300 text-sm leading-6 shadow-sm focus:border-red-600 focus:ring-red-600"></textarea>
            </section>

            <section data-revision-section="section-curriculum-vitae" data-proposal-attachment-reference class="border-l-2 border-slate-200 py-1 pl-3 dark:border-slate-700">
                <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200"><?php echo e($sectionHeadings['curriculum-vitae']); ?></h3>
                <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">Provided in Curriculum Vitae (Form C).</p>
            </section>

            <?php if (isset($component)) { $__componentOriginalcd93ff08fd3bc5e3afdf57ccf1848309 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalcd93ff08fd3bc5e3afdf57ccf1848309 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-signatory-summary','data' => ['proposalDraft' => $proposalDraft,'paper' => 'detailed_proposal']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-signatory-summary'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['proposal-draft' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($proposalDraft),'paper' => 'detailed_proposal']); ?>
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
                <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl bg-red-600 px-5 py-3 text-sm font-bold text-white hover:bg-red-700 sm:w-auto">Save Detailed Proposal</button>
            </noscript>
        </form>

        <div x-show="previewError || downloadError" x-cloak role="alert" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" x-text="previewError || downloadError"></div>
        </div>
        <?php if (isset($component)) { $__componentOriginal9b856d71d8d94cfb14c310185e332600 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9b856d71d8d94cfb14c310185e332600 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.detailed-proposal-preview','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('detailed-proposal-preview'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9b856d71d8d94cfb14c310185e332600)): ?>
<?php $attributes = $__attributesOriginal9b856d71d8d94cfb14c310185e332600; ?>
<?php unset($__attributesOriginal9b856d71d8d94cfb14c310185e332600); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9b856d71d8d94cfb14c310185e332600)): ?>
<?php $component = $__componentOriginal9b856d71d8d94cfb14c310185e332600; ?>
<?php unset($__componentOriginal9b856d71d8d94cfb14c310185e332600); ?>
<?php endif; ?>
        </div>
        <button type="button" x-show="!previewPaneOpen" x-cloak @click="showProposalPreview()" aria-controls="proposal-preview-panel" :aria-expanded="previewPaneOpen" class="proposal-writing-preview-launcher">Preview paper</button>
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
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\faculty\proposal-drafts\detailed-proposal\edit.blade.php ENDPATH**/ ?>