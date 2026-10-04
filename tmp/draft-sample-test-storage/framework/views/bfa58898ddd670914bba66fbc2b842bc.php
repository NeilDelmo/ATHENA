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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.page-header','data' => ['title' => $paper['label'],'subtitle' => 'Create one official CV form for every member of the research team.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('page-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($paper['label']),'subtitle' => 'Create one official CV form for every member of the research team.']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

             <?php $__env->slot('actions', null, []); ?> 
                <span class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-wider <?php echo e($curriculumVitaeDocument?->completed_at ? 'bg-green-100 text-green-800' : ($curriculumVitaeDocument ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-600')); ?>"><?php echo e($curriculumVitaeDocument?->completed_at ? 'Complete' : ($curriculumVitaeDocument ? 'In progress' : 'Not started')); ?></span>
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
        $initialPeople = old('people', $sourceData['people'] ?? []);
        $sections = config('curriculum_vitae.sections');
        $sampleDefinition = config('proposal_samples.'.$paper['sample_slug']);
        $sampleAvailable = is_array($sampleDefinition)
            && isset($sampleDefinition['path'])
            && \Illuminate\Support\Facades\Storage::disk('local')->exists($sampleDefinition['path']);
    ?>

    <div
        class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8"
        data-paper-editor
        data-paper-draft-save="true"
        data-curriculum-vitae-autosave="true"
        data-paper-dirty="<?php echo e($errors->any() ? 'true' : 'false'); ?>"
        data-paper-edit-url="<?php echo e(route('faculty.proposal-drafts.curriculum-vitae.edit', $proposalDraft)); ?>"
        data-paper-exit-url="<?php echo e(route('faculty.proposal-drafts.show', $proposalDraft)); ?>#required-pdf-attachments"
        x-data="proposalDraftCurriculumVitae({
            initialPeople: <?php echo \Illuminate\Support\Js::from($initialPeople)->toHtml() ?>,
            workspacePeople: <?php echo \Illuminate\Support\Js::from($workspacePeople)->toHtml() ?>,
            sections: <?php echo \Illuminate\Support\Js::from($sections)->toHtml() ?>,
            updateUrl: <?php echo \Illuminate\Support\Js::from(route('faculty.proposal-drafts.curriculum-vitae.update', $proposalDraft))->toHtml() ?>,
            previewUrl: <?php echo \Illuminate\Support\Js::from(route('faculty.proposal-drafts.curriculum-vitae.preview', $proposalDraft))->toHtml() ?>,
            downloadUrl: <?php echo \Illuminate\Support\Js::from(route('faculty.proposal-drafts.curriculum-vitae.download', $proposalDraft))->toHtml() ?>,
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

                <p class="font-bold">The Curriculum Vitae package could not be saved.</p>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-collaboration-monitor','data' => ['loadedVersion' => (int) old('document_version', $curriculumVitaeDocument?->lock_version ?? 0),'stateUrl' => route('faculty.proposal-drafts.edit-state', [$proposalDraft, $paper['document_type'], 0]),'reloadUrl' => route('faculty.proposal-drafts.curriculum-vitae.edit', $proposalDraft),'historyUrl' => route('faculty.proposal-drafts.history.index', [$proposalDraft, 'paper' => $paper['slug']]),'label' => $paper['label']]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-collaboration-monitor'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['loaded-version' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute((int) old('document_version', $curriculumVitaeDocument?->lock_version ?? 0)),'state-url' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('faculty.proposal-drafts.edit-state', [$proposalDraft, $paper['document_type'], 0])),'reload-url' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('faculty.proposal-drafts.curriculum-vitae.edit', $proposalDraft)),'history-url' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('faculty.proposal-drafts.history.index', [$proposalDraft, 'paper' => $paper['slug']])),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($paper['label'])]); ?>
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
            <button type="button" @click="previewPaneOpen ? closeProposalPreview() : showProposalPreview()" :aria-expanded="previewPaneOpen" aria-controls="curriculum-vitae-preview-panel" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-bold dark:text-white" x-text="previewPaneOpen ? 'Hide preview' : 'Show preview'"></button>
            <span class="text-xs text-slate-500 dark:text-slate-400">The preview stays open while you edit and can be moved or resized.</span>
        </div>

        <section data-revision-shared-summary class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h3 class="text-base font-black text-gray-900">Research team CV package</h3>
                    <p class="mt-1 max-w-3xl text-xs leading-5 text-gray-500">Add an account from this proposal workspace to fill in their name and institutional email automatically, or create a blank CV for an unlisted person.</p>
                </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($sampleAvailable): ?><a href="<?php echo e(route('proposal-samples.show', $paper['sample_slug'])); ?>" target="_blank" rel="noopener" class="inline-flex w-full shrink-0 items-center justify-center rounded-xl border border-gray-300 px-3 py-2 text-xs font-bold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-600 focus:ring-offset-2 sm:w-auto">View sample</a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            <div class="mt-5 grid gap-4 border-t border-gray-100 pt-5 lg:grid-cols-[minmax(0,1fr)_17rem]">
                <div class="rounded-2xl border border-red-100 bg-red-50/50 p-4 sm:p-5">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="text-xs font-black uppercase tracking-wider text-red-700">Proposal workspace</p>
                            <h4 class="mt-1 text-sm font-black text-gray-900">Add a workspace member</h4>
                            <p class="mt-1 text-xs leading-5 text-gray-500">Search the project leader and team members. Members with an existing CV are hidden.</p>
                        </div>
                        <span class="w-fit rounded-full bg-white px-2.5 py-1 text-[10px] font-black text-gray-600 ring-1 ring-gray-200" x-text="`${availableWorkspacePeople().length} available`"></span>
                    </div>

                    <div class="relative mt-4" x-on:click.outside="workspacePickerOpen = false">
                        <label for="workspace-cv-person-search" class="sr-only">Search proposal workspace members</label>
                        <div class="relative">
                            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 1 0 3.474 9.765l3.63 3.63a.75.75 0 0 0 1.06-1.06l-3.629-3.63A5.5 5.5 0 0 0 9 3.5ZM5 9a4 4 0 1 1 8 0 4 4 0 0 1-8 0Z" clip-rule="evenodd" /></svg>
                            <input id="workspace-cv-person-search" type="search" autocomplete="off" placeholder="Search workspace members" x-model="workspacePersonQuery" x-on:focus="workspacePickerOpen = true" x-on:input="workspacePickerOpen = true" x-on:keydown.escape="workspacePickerOpen = false" role="combobox" aria-autocomplete="list" x-bind:aria-expanded="workspacePickerOpen" aria-controls="workspace-cv-person-options" class="block w-full rounded-xl border-gray-300 bg-white py-2.5 pl-9 pr-3 text-sm shadow-sm focus:border-red-600 focus:ring-red-600">
                        </div>
                        <div id="workspace-cv-person-options" x-show="workspacePickerOpen" x-transition.origin.top x-cloak role="listbox" class="absolute z-30 mt-2 max-h-72 w-full overflow-y-auto rounded-2xl border border-gray-200 bg-white p-2 shadow-xl shadow-gray-900/10">
                            <template x-for="person in filteredWorkspacePeople()" :key="person.key">
                                <button type="button" role="option" x-on:click="addWorkspacePerson(person.key)" class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left transition hover:bg-red-50 focus:bg-red-50 focus:outline-none">
                                    <span class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full bg-red-100 text-xs font-black text-red-700 ring-1 ring-red-200">
                                        <img x-show="person.avatar" x-bind:src="person.avatar" x-bind:alt="personDisplayName(person.name)" x-on:error="person.avatar = ''" class="h-full w-full object-cover">
                                        <span x-show="!person.avatar" x-text="personInitials(person.name)"></span>
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-sm font-bold uppercase text-gray-900" x-text="personDisplayName(person.name)"></span>
                                        <span class="block truncate text-xs text-gray-500" x-text="person.email"></span>
                                    </span>
                                    <svg class="h-4 w-4 shrink-0 text-red-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                                </button>
                            </template>
                            <div x-show="filteredWorkspacePeople().length === 0" class="px-3 py-5 text-center">
                                <p class="text-sm font-bold text-gray-700">No available workspace member matches your search.</p>
                                <p class="mt-1 text-xs leading-5 text-gray-500">Members already included in this CV package do not appear here.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col justify-between rounded-2xl border border-dashed border-gray-300 bg-gray-50 p-4 sm:p-5">
                    <div>
                        <p class="text-xs font-black uppercase tracking-wider text-gray-600">External team member</p>
                        <h4 class="mt-1 text-sm font-black text-gray-900">Create a blank CV</h4>
                        <p class="mt-1 text-xs leading-5 text-gray-500">Use this for a person who is not yet in the proposal workspace.</p>
                    </div>
                    <button type="button" x-on:click="addPerson" class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-xs font-bold text-gray-700 transition hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-gray-600 focus:ring-offset-2">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                        Add blank CV
                    </button>
                </div>
            </div>

            <div class="mt-5 border-t border-gray-100 pt-5">
                <div class="flex items-center justify-between gap-3"><p class="text-[10px] font-black uppercase tracking-wider text-gray-600">CVs in this package</p><span class="rounded-full bg-gray-100 px-2.5 py-1 text-[10px] font-black text-gray-600" x-text="`${people.length} ${people.length === 1 ? 'member' : 'members'}`"></span></div>
                <div class="mt-3 flex flex-wrap gap-2">
                <template x-for="(person, index) in people" :key="person.id">
                    <button type="button" x-on:click="focusPerson(index)" class="rounded-full bg-gray-100 px-3 py-1.5 text-xs font-bold text-gray-700 transition hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-red-600" x-text="`${index + 1}. ${personLabel(person)}`"></button>
                </template>
                </div>
            </div>
        </section>

        <form data-paper-form data-curriculum-vitae-autosave-form x-ref="form" action="<?php echo e(route('faculty.proposal-drafts.curriculum-vitae.update', $proposalDraft)); ?>" method="POST" class="space-y-6" novalidate>
            <?php echo csrf_field(); ?>
            <?php echo method_field('PUT'); ?>
            <input type="hidden" name="document_version" value="<?php echo e(old('document_version', $curriculumVitaeDocument?->lock_version ?? 0)); ?>">
            <input type="hidden" name="save_as_draft" value="0" data-paper-save-mode>

            <template x-for="(person, personIndex) in people" :key="person.id">
                <article class="space-y-5 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6" :data-person-index="personIndex">
                    <div class="flex flex-col gap-3 border-b border-gray-100 pb-5 sm:flex-row sm:items-start sm:justify-between">
                        <div><p class="text-[10px] font-black uppercase tracking-wider text-red-600">CV <span x-text="personIndex + 1"></span> of <span x-text="people.length"></span></p><h3 class="mt-1 text-lg font-black text-gray-900" x-text="personLabel(person)"></h3></div>
                        <button type="button" x-on:click="removePerson(personIndex)" x-bind:disabled="people.length === 1" class="rounded-xl px-3 py-2 text-xs font-bold text-red-700 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-600 disabled:cursor-not-allowed disabled:opacity-40">Remove member</button>
                    </div>

                    <details :data-revision-section="`section-cv-${personIndex + 1}-personal`" :id="`cv-${person.id}-personal`" open class="rounded-xl border border-gray-200">
                        <summary class="cursor-pointer select-none px-4 py-3 text-sm font-black text-gray-900 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-red-600">Personal Information</summary>
                        <div class="grid gap-4 border-t border-gray-100 p-4 sm:grid-cols-2 lg:grid-cols-3">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = [['last_name', 'Last Name', true], ['first_name', 'First Name', true], ['middle_name', 'Middle Name', false], ['agency', 'Agency', false], ['birthday', 'Birthday', false], ['street', 'Street', false], ['barangay', 'Barangay', false], ['municipality', 'Municipality', false], ['province', 'Province', false], ['landline', 'Landline Number', false], ['cellphone', 'Cellphone Number', false], ['email', 'Email Address', false]]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$key, $label, $required]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <?php ($isContactNumber = in_array($key, ['landline', 'cellphone'], true)); ?>
                                <div>
                                    <label class="block text-[10px] font-black uppercase tracking-wider text-gray-600" :for="`cv-${person.id}-<?php echo e($key); ?>`"><?php echo e($label); ?> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($required): ?><span class="text-red-600">Required</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></label>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($key === 'birthday'): ?>
                                        <?php if (isset($component)) { $__componentOriginal37e12294b28f0bd91a733acab9bb06c5 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal37e12294b28f0bd91a733acab9bb06c5 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.date-picker','data' => ['idExpression' => '`cv-${person.id}-'.e($key).'`','nameExpression' => '`people[${personIndex}]['.e($key).']`','model' => 'person.'.e($key).'','max' => now()->toDateString(),'class' => 'mt-1.5']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('date-picker'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['id-expression' => '`cv-${person.id}-'.e($key).'`','name-expression' => '`people[${personIndex}]['.e($key).']`','model' => 'person.'.e($key).'','max' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(now()->toDateString()),'class' => 'mt-1.5']); ?>
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
                                    <?php else: ?>
                                        <input :id="`cv-${person.id}-<?php echo e($key); ?>`" :name="`people[${personIndex}][<?php echo e($key); ?>]`" type="<?php echo e($key === 'email' ? 'email' : ($isContactNumber ? 'tel' : 'text')); ?>" maxlength="<?php echo e($isContactNumber ? 11 : ($key === 'agency' || $key === 'email' ? 255 : 120)); ?>" <?php if($isContactNumber): ?> inputmode="numeric" pattern="[0-9]{11}" autocomplete="tel" <?php endif; ?> x-model="person.<?php echo e($key); ?>" <?php if($isContactNumber): ?> x-on:input="person.<?php echo e($key); ?> = normalizeContactNumber($event.target.value); $event.target.value = person.<?php echo e($key); ?>" <?php endif; ?> <?php if($required): ?> required <?php endif; ?> class="mt-1.5 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600">
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </div>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-wider text-gray-600" :for="`cv-${person.id}-gender`">Gender</label>
                                <select :id="`cv-${person.id}-gender`" :name="`people[${personIndex}][gender]`" x-model="person.gender" class="mt-1.5 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600"><option value="">Leave blank</option><option value="male">Male</option><option value="female">Female</option></select>
                            </div>
                        </div>
                    </details>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $sections; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $sectionKey => $section): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <details :data-revision-section="`section-cv-${personIndex + 1}-<?php echo e($sectionKey); ?>`" :id="`cv-${person.id}-<?php echo e($sectionKey); ?>`" class="rounded-xl border border-gray-200">
                            <summary class="cursor-pointer select-none px-4 py-3 text-sm font-black text-gray-900 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-red-600"><?php echo e($section['label']); ?> <span class="font-semibold text-gray-400" x-text="`(${person.<?php echo e($sectionKey); ?>.length})`"></span></summary>
                            <div class="space-y-4 border-t border-gray-100 p-4">
                                <div class="flex justify-end"><button type="button" x-on:click="addSectionRow(personIndex, '<?php echo e($sectionKey); ?>')" class="inline-flex w-full items-center justify-center rounded-xl border border-red-200 px-3 py-2 text-xs font-bold text-red-700 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-600 sm:w-auto">Add <?php echo e(Str::singular(strtolower($section['label']))); ?> entry</button></div>
                                <p x-show="person.<?php echo e($sectionKey); ?>.length === 0" class="rounded-xl bg-gray-50 px-4 py-3 text-xs text-gray-500">No entries. Preview and Word output will retain <?php echo e($section['default_rows']); ?> blank rows for this section.</p>
                                <template x-for="(row, rowIndex) in person.<?php echo e($sectionKey); ?>" :key="row.id">
                                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                                        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $section['fields']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $field): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                                <div class="<?php echo e(($field['wide'] ?? false) ? 'sm:col-span-2' : ''); ?>">
                                                    <label class="block text-[10px] font-black uppercase tracking-wider text-gray-600" :for="`cv-${person.id}-<?php echo e($sectionKey); ?>-${row.id}-<?php echo e($field['key']); ?>`"><?php echo e($field['label']); ?></label>
                                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($field['type'] === 'select'): ?>
                                                        <select :id="`cv-${person.id}-<?php echo e($sectionKey); ?>-${row.id}-<?php echo e($field['key']); ?>`" :name="`people[${personIndex}][<?php echo e($sectionKey); ?>][${rowIndex}][<?php echo e($field['key']); ?>]`" <?php if($sectionKey === 'academic_background' && $field['key'] === 'status'): ?> x-bind:value="row.status" x-on:change="updateAcademicStatus(row, $event.target.value)" <?php else: ?> x-model="row.<?php echo e($field['key']); ?>" <?php endif; ?> class="mt-1.5 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600">
                                                            <option value="">Leave blank</option>
                                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $field['options']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $option): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><option value="<?php echo e($option); ?>"><?php echo e($option); ?></option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                                        </select>
                                                    <?php elseif($sectionKey === 'academic_background' && $field['key'] === 'year_end'): ?>
                                                        <template x-if="row.status === 'Ongoing'">
                                                            <input :id="`cv-${person.id}-<?php echo e($sectionKey); ?>-${row.id}-<?php echo e($field['key']); ?>`" type="text" value="Present" disabled class="mt-1.5 block w-full cursor-not-allowed rounded-xl border-gray-200 bg-gray-100 text-sm font-bold text-gray-700 shadow-sm">
                                                        </template>
                                                        <template x-if="row.status !== 'Ongoing'">
                                                            <input :id="`cv-${person.id}-<?php echo e($sectionKey); ?>-${row.id}-<?php echo e($field['key']); ?>`" :name="`people[${personIndex}][<?php echo e($sectionKey); ?>][${rowIndex}][<?php echo e($field['key']); ?>]`" type="number" min="1900" max="2100" step="1" x-model="row.<?php echo e($field['key']); ?>" class="mt-1.5 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600">
                                                        </template>
                                                        <p x-show="row.status === 'Ongoing'" class="mt-1 text-[11px] font-semibold text-gray-500">Ongoing studies automatically end in Present.</p>
                                                    <?php elseif($field['type'] === 'yes_no'): ?>
                                                        <select :id="`cv-${person.id}-<?php echo e($sectionKey); ?>-${row.id}-<?php echo e($field['key']); ?>`" :name="`people[${personIndex}][<?php echo e($sectionKey); ?>][${rowIndex}][<?php echo e($field['key']); ?>]`" x-model="row.<?php echo e($field['key']); ?>" class="mt-1.5 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600"><option value="">Leave blank</option><option value="yes">Yes</option><option value="no">No</option></select>
                                                    <?php elseif($field['type'] === 'suggestions'): ?>
                                                        <input :id="`cv-${person.id}-<?php echo e($sectionKey); ?>-${row.id}-<?php echo e($field['key']); ?>`" :name="`people[${personIndex}][<?php echo e($sectionKey); ?>][${rowIndex}][<?php echo e($field['key']); ?>]`" :list="`cv-${person.id}-<?php echo e($sectionKey); ?>-${row.id}-<?php echo e($field['key']); ?>-options`" type="text" maxlength="500" x-model="row.<?php echo e($field['key']); ?>" data-cv-suggestions class="mt-1.5 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600">
                                                        <datalist :id="`cv-${person.id}-<?php echo e($sectionKey); ?>-${row.id}-<?php echo e($field['key']); ?>-options`">
                                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $field['options']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $option): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><option value="<?php echo e($option); ?>"></option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                                        </datalist>
                                                        <p class="mt-1 text-[11px] font-semibold text-gray-500">Choose a suggested value or type your own.</p>
                                                    <?php elseif($field['type'] === 'date'): ?>
                                                        <?php if (isset($component)) { $__componentOriginal37e12294b28f0bd91a733acab9bb06c5 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal37e12294b28f0bd91a733acab9bb06c5 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.date-picker','data' => ['idExpression' => '`cv-${person.id}-'.e($sectionKey).'-${row.id}-'.e($field['key']).'`','nameExpression' => '`people[${personIndex}]['.e($sectionKey).'][${rowIndex}]['.e($field['key']).']`','model' => 'row.'.e($field['key']).'','class' => 'mt-1.5']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('date-picker'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['id-expression' => '`cv-${person.id}-'.e($sectionKey).'-${row.id}-'.e($field['key']).'`','name-expression' => '`people[${personIndex}]['.e($sectionKey).'][${rowIndex}]['.e($field['key']).']`','model' => 'row.'.e($field['key']).'','class' => 'mt-1.5']); ?>
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
                                                    <?php else: ?>
                                                        <input :id="`cv-${person.id}-<?php echo e($sectionKey); ?>-${row.id}-<?php echo e($field['key']); ?>`" :name="`people[${personIndex}][<?php echo e($sectionKey); ?>][${rowIndex}][<?php echo e($field['key']); ?>]`" type="<?php echo e($field['type'] === 'money' || $field['type'] === 'year' ? 'number' : 'text'); ?>" <?php if($field['type'] === 'money'): ?> min="0" max="999999999.99" step="0.01" <?php elseif($field['type'] === 'year'): ?> min="1900" max="2100" step="1" <?php else: ?> maxlength="500" <?php endif; ?> x-model="row.<?php echo e($field['key']); ?>" class="mt-1.5 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600">
                                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                </div>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                        </div>
                                        <div class="mt-3 flex justify-end"><button type="button" x-on:click="removeSectionRow(personIndex, '<?php echo e($sectionKey); ?>', rowIndex)" class="rounded-lg px-3 py-2 text-xs font-bold text-red-700 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-red-600">Remove entry</button></div>
                                    </div>
                                </template>
                            </div>
                        </details>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </article>
            </template>

            <noscript><button type="submit" class="inline-flex w-full items-center justify-center rounded-xl bg-red-600 px-5 py-3 text-sm font-bold text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 sm:w-auto">Save Curriculum Vitae</button></noscript>
        </form>

        <div x-show="previewError || downloadError" x-cloak role="alert" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><span x-text="previewError || downloadError"></span></div>

        <?php if (isset($component)) { $__componentOriginal45f53eba72ddc2e934dbcbe4dd398776 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal45f53eba72ddc2e934dbcbe4dd398776 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-document-preview','data' => ['panelId' => 'curriculum-vitae-preview-panel','title' => 'Curriculum Vitae package preview','description' => 'Every member begins with a new official CV block.','frameTitle' => 'Attachment C Curriculum Vitae package preview']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-document-preview'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['panel-id' => 'curriculum-vitae-preview-panel','title' => 'Curriculum Vitae package preview','description' => 'Every member begins with a new official CV block.','frame-title' => 'Attachment C Curriculum Vitae package preview']); ?>
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
<?php /**PATH C:\laragon\www\athena-app\src\resources\views/faculty/proposal-drafts/curriculum-vitae/edit.blade.php ENDPATH**/ ?>