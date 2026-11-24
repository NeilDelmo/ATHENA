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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.page-header','data' => ['title' => $proposalDraft->project_title,'subtitle' => 'Last saved '.$proposalDraft->updated_at->diffForHumans().($proposalDraft->researchManual ? ' · Research manual: '.$proposalDraft->researchManual->title.' ('.$proposalDraft->researchManual->edition.')' : '')]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('page-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($proposalDraft->project_title),'subtitle' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute('Last saved '.$proposalDraft->updated_at->diffForHumans().($proposalDraft->researchManual ? ' · Research manual: '.$proposalDraft->researchManual->title.' ('.$proposalDraft->researchManual->edition.')' : ''))]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

             <?php $__env->slot('actions', null, []); ?> 
                <?php if (isset($component)) { $__componentOriginal5426bd0bea02df2e6dd2a60e50fa4c01 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal5426bd0bea02df2e6dd2a60e50fa4c01 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.back-link','data' => ['fixed' => true,'href' => ''.e(route('faculty.proposal-drafts.index')).'']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('back-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['fixed' => true,'href' => ''.e(route('faculty.proposal-drafts.index')).'']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>
Back to saved drafts <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal5426bd0bea02df2e6dd2a60e50fa4c01)): ?>
<?php $attributes = $__attributesOriginal5426bd0bea02df2e6dd2a60e50fa4c01; ?>
<?php unset($__attributesOriginal5426bd0bea02df2e6dd2a60e50fa4c01); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal5426bd0bea02df2e6dd2a60e50fa4c01)): ?>
<?php $component = $__componentOriginal5426bd0bea02df2e6dd2a60e50fa4c01; ?>
<?php unset($__componentOriginal5426bd0bea02df2e6dd2a60e50fa4c01); ?>
<?php endif; ?>
                <button type="button" data-proposal-readiness-continue data-url="<?php echo e(route('faculty.proposal-drafts.review', $proposalDraft)); ?>" class="inline-flex min-h-11 w-full shrink-0 items-center justify-center rounded-lg bg-red-600 px-4 py-2.5 text-base font-bold text-white transition hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 dark:hover:bg-red-500 sm:w-auto"><?php echo e(auth()->user()->can('submit', $proposalDraft) ? 'Review & turn in' : 'Review working draft'); ?></button>
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
        $submittedTopic = $proposalDraft->topic;
        $canEditDraft = auth()->user()->can('update', $proposalDraft);
        $editableChecklist = $checklist->reject(fn (array $item): bool => $item['paper']['mode'] === 'automatic');
        $automaticChecklist = $checklist->filter(fn (array $item): bool => $item['paper']['mode'] === 'automatic');
        $completedPaperCount = $editableChecklist
            ->filter(fn (array $item): bool => $item['complete'] && ! $item['needs_attention'])
            ->count();
        $paperCount = $editableChecklist->count();
        $initialProposalTab = in_array(session('proposal_tab'), ['details', 'attachments', 'collaborators'], true)
            ? session('proposal_tab')
            : null;
        $memberInvitationHasErrors = $errors->hasAny(['email', 'name']);
        $teamRoleHasErrors = $errors->hasAny(['member_id', 'project_role']);
    ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($submittedTopic && $submittedTopic->status !== 'revision_requested'): ?>
        <section data-submitted-package-workspace class="rounded-xl border border-blue-200 bg-blue-50 p-5 dark:border-blue-900 dark:bg-blue-950/30">
            <h2 class="font-bold text-gray-950 dark:text-white"><?php echo e($submittedTopic->canUpdateBeforeReview() ? 'Update submitted proposal' : 'Proposal under review'); ?></h2>
            <p class="mt-2 text-sm leading-6 text-gray-700 dark:text-slate-200"><?php echo e($submittedTopic->canUpdateBeforeReview() ? 'These changes are private until you submit the next version. Your earlier submitted PDFs stay in Versions. Editing closes when the Research Head opens the submission.' : 'The Research Head has opened this proposal. Editing and submission are locked until revisions are requested. Your saved working copy is preserved.'); ?></p>
            <a href="<?php echo e(route('topics.show', $submittedTopic)); ?>" class="mt-3 secondary-action">View submitted versions</a>
        </section>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <div
        data-workspace-palette="red-black-white"
        class="mx-auto max-w-7xl space-y-5 px-4 py-6 sm:px-6 lg:px-8"
        x-data="{
            activeProposalTab: <?php echo \Illuminate\Support\Js::from(($memberInvitationHasErrors || $teamRoleHasErrors) ? 'collaborators' : $initialProposalTab)->toHtml() ?> || (
                window.location.hash === '#required-pdf-attachments'
                    ? 'attachments'
                    : window.location.hash === '#proposal-collaborators'
                        ? 'collaborators'
                        : 'details'
            ),
        }"
        @hashchange.window="activeProposalTab = window.location.hash === '#required-pdf-attachments' ? 'attachments' : window.location.hash === '#proposal-collaborators' ? 'collaborators' : 'details'"
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

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('warning')): ?>
            <?php if (isset($component)) { $__componentOriginal06a6d047fcfbc4adc19f6beb6fa14107 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal06a6d047fcfbc4adc19f6beb6fa14107 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-alert','data' => ['type' => 'warning']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-alert'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'warning']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>
<?php echo e(session('warning')); ?> <?php echo $__env->renderComponent(); ?>
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

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('workload_warning')): ?>
            <?php if (isset($component)) { $__componentOriginal06a6d047fcfbc4adc19f6beb6fa14107 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal06a6d047fcfbc4adc19f6beb6fa14107 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-alert','data' => ['type' => 'warning']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-alert'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'warning']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>
<?php echo e(session('workload_warning')); ?> <?php echo $__env->renderComponent(); ?>
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

                <p class="font-bold">Some information still needs attention.</p>
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

        <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white p-1.5 shadow-sm dark:border-slate-800 dark:bg-slate-900" role="tablist" aria-label="Proposal workspace sections">
            <nav class="flex min-w-max gap-1">
                <button id="project-details-tab-button" type="button" role="tab" aria-controls="project-details-tab" :aria-selected="activeProposalTab === 'details'" @click="window.location.hash = 'project-details'" :class="activeProposalTab === 'details' ? 'bg-gray-950 text-white shadow-sm dark:bg-white dark:text-gray-950' : 'text-gray-500 hover:bg-gray-100 hover:text-gray-950 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white'" class="border border-slate-400 dark:border-slate-500 flex min-h-11 items-center gap-2 rounded-lg px-4 py-2.5 text-base font-bold transition">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 5.25A2.25 2.25 0 0 1 6.25 3h11.5A2.25 2.25 0 0 1 20 5.25v13.5A2.25 2.25 0 0 1 17.75 21H6.25A2.25 2.25 0 0 1 4 18.75V5.25Z" /><path stroke-linecap="round" d="M8 8h8M8 12h8M8 16h5" /></svg>
                    Project Details
                </button>
                <button id="required-pdf-attachments-tab-button" type="button" role="tab" aria-controls="required-pdf-attachments-tab" :aria-selected="activeProposalTab === 'attachments'" @click="window.location.hash = 'required-pdf-attachments'" :class="activeProposalTab === 'attachments' ? 'bg-gray-950 text-white shadow-sm dark:bg-white dark:text-gray-950' : 'text-gray-500 hover:bg-gray-100 hover:text-gray-950 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white'" class="border border-slate-400 dark:border-slate-500 flex min-h-11 items-center gap-2 rounded-lg px-4 py-2.5 text-base font-bold transition">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3.75h10.5A2.25 2.25 0 0 1 19.5 6v14.25H4.5V6a2.25 2.25 0 0 1 2.25-2.25Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 8.25h7.5M8.25 12h7.5M8.25 15.75h4.5" /></svg>
                    Required PDF attachments
                </button>
                <button id="proposal-collaborators-tab-button" type="button" role="tab" aria-controls="proposal-collaborators-tab" :aria-selected="activeProposalTab === 'collaborators'" @click="window.location.hash = 'proposal-collaborators'" :class="activeProposalTab === 'collaborators' ? 'bg-gray-950 text-white shadow-sm dark:bg-white dark:text-gray-950' : 'text-gray-500 hover:bg-gray-100 hover:text-gray-950 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white'" class="border border-slate-400 dark:border-slate-500 flex min-h-11 items-center gap-2 rounded-lg px-4 py-2.5 text-base font-bold transition">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75h-9A2.25 2.25 0 0 1 5.25 16.5v-9A2.25 2.25 0 0 1 7.5 5.25h9a2.25 2.25 0 0 1 2.25 2.25v9a2.25 2.25 0 0 1-2.25 2.25Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6M12 9v6" /></svg>
                    Project team
                </button>
            </nav>
        </div>

        <section id="project-details-tab" x-show="activeProposalTab === 'details'" x-cloak role="tabpanel" aria-labelledby="project-details-tab-button">
            <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_18rem]">
                <div data-paper-editor data-proposal-paper="details" data-proposal-readiness-url="<?php echo e(route('faculty.proposal-drafts.review', $proposalDraft)); ?>" data-paper-dirty="<?php echo e($errors->any() ? 'true' : 'false'); ?>" data-project-details-autosave="true" data-paper-edit-url="<?php echo e(route('faculty.proposal-drafts.show', $proposalDraft)); ?>" data-paper-exit-url="<?php echo e(route('faculty.proposal-drafts.show', $proposalDraft)); ?>" x-data="proposalDraftProjectDetails({ initialDuration: <?php echo \Illuminate\Support\Js::from($initialDuration)->toHtml() ?>, initialStart: <?php echo \Illuminate\Support\Js::from($initialPlannedStart)->toHtml() ?>, initialEnd: <?php echo \Illuminate\Support\Js::from($initialPlannedEnd)->toHtml() ?>, initialComplete: <?php echo \Illuminate\Support\Js::from($projectDetailsComplete)->toHtml() ?>, autoSave: true })" class="space-y-4">
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-collaboration-monitor','data' => ['loadedVersion' => (int) old('draft_version', $proposalDraft->lock_version),'stateUrl' => route('faculty.proposal-drafts.edit-state', [$proposalDraft, 'details', 0]),'reloadUrl' => route('faculty.proposal-drafts.show', $proposalDraft),'label' => 'project details']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-collaboration-monitor'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['loaded-version' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute((int) old('draft_version', $proposalDraft->lock_version)),'state-url' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('faculty.proposal-drafts.edit-state', [$proposalDraft, 'details', 0])),'reload-url' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('faculty.proposal-drafts.show', $proposalDraft)),'label' => 'project details']); ?>
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

                    <section aria-labelledby="project-details-heading" class="overflow-visible rounded-xl border border-gray-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div class="border-b border-gray-200 px-5 py-5 dark:border-slate-800 sm:px-6">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 id="project-details-heading" class="text-lg font-black text-gray-950 dark:text-white">Project Details</h3>
                                <span role="status" aria-live="polite" :class="projectDetailsComplete ? 'border-gray-300 bg-gray-100 text-gray-700 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200' : 'border-red-200 bg-red-50 text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200'" x-text="projectDetailsComplete ? 'Complete' : 'Incomplete'" class="rounded-full border px-2.5 py-1 text-[10px] font-black uppercase tracking-wider"><?php echo e($projectDetailsComplete ? 'Complete' : 'Incomplete'); ?></span>
                            </div>
                            <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-slate-400">Shared information used automatically in Attachment A and the submitted proposal record.</p>
                        </div>


                        <form data-paper-form <?php if($canEditDraft): ?> data-project-details-autosave-form <?php endif; ?> action="<?php echo e(route('faculty.proposal-drafts.details.update', $proposalDraft)); ?>" method="POST" class="px-5 pb-5 pt-6 sm:px-6 sm:pb-6">
                            <?php echo csrf_field(); ?>
                            <?php echo method_field('PUT'); ?>
                            <fieldset <?php if(! $canEditDraft): echo 'disabled'; endif; ?> <?php if(! $canEditDraft): ?> inert data-proposal-editing-locked <?php endif; ?> class="space-y-6">
                            <input type="hidden" name="draft_version" value="<?php echo e(old('draft_version', $proposalDraft->lock_version)); ?>">
                            <p data-proposal-required-legend class="text-xs text-gray-600 dark:text-slate-400"><span class="font-bold text-red-700 dark:text-red-400" aria-hidden="true">*</span> Required for submission.</p>

                            <div>
                                <label for="project_title" class="block text-xs font-black uppercase tracking-wider text-gray-600 dark:text-slate-300">Project Title <?php if (isset($component)) { $__componentOriginal4f074808deca33fb39dbf7707c9b77e2 = $component; } ?>
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
                                <input id="project_title" name="project_title" type="text" value="<?php echo e(old('project_title', $proposalDraft->project_title)); ?>" maxlength="255" required autofocus class="mt-2 block w-full rounded-lg border-gray-300 text-sm text-gray-900 shadow-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-800 dark:text-white">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['project_title'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="mt-2 text-xs font-semibold text-red-600"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>

                            <div class="grid gap-5 md:grid-cols-3">
                                <div>
                                    <label for="duration_months" class="block text-xs font-black uppercase tracking-wider text-gray-600 dark:text-slate-300">Total Duration <?php if (isset($component)) { $__componentOriginal4f074808deca33fb39dbf7707c9b77e2 = $component; } ?>
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
                                    <div class="relative mt-2"><input id="duration_months" name="duration_months" type="number" min="1" max="<?php echo e(config('work_plan.max_duration_months')); ?>" x-model.number="durationMonths" required class="block w-full rounded-lg border-gray-300 pr-20 text-sm text-gray-900 shadow-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-800 dark:text-white"><span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-xs font-bold text-gray-500 dark:text-slate-400">months</span></div>
                                    <p class="mt-2 text-[11px] text-gray-500 dark:text-slate-400">Attachment A adds one M1-M12 sheet for each 12-month project period.</p>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['duration_months'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="mt-2 text-xs font-semibold text-red-600"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </div>
                                <?php if (isset($component)) { $__componentOriginalfa4345465c63d4c716c273b9af64af46 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalfa4345465c63d4c716c273b9af64af46 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-planned-date','data' => ['field' => 'start','researchCall' => $proposalDraft->researchCall,'minimumDate' => $minimumProjectDate]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-planned-date'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['field' => 'start','research-call' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($proposalDraft->researchCall),'minimum-date' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($minimumProjectDate)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalfa4345465c63d4c716c273b9af64af46)): ?>
<?php $attributes = $__attributesOriginalfa4345465c63d4c716c273b9af64af46; ?>
<?php unset($__attributesOriginalfa4345465c63d4c716c273b9af64af46); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalfa4345465c63d4c716c273b9af64af46)): ?>
<?php $component = $__componentOriginalfa4345465c63d4c716c273b9af64af46; ?>
<?php unset($__componentOriginalfa4345465c63d4c716c273b9af64af46); ?>
<?php endif; ?>
                                <?php if (isset($component)) { $__componentOriginalfa4345465c63d4c716c273b9af64af46 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalfa4345465c63d4c716c273b9af64af46 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-planned-date','data' => ['field' => 'end','researchCall' => $proposalDraft->researchCall,'minimumDate' => $minimumProjectDate]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-planned-date'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['field' => 'end','research-call' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($proposalDraft->researchCall),'minimum-date' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($minimumProjectDate)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalfa4345465c63d4c716c273b9af64af46)): ?>
<?php $attributes = $__attributesOriginalfa4345465c63d4c716c273b9af64af46; ?>
<?php unset($__attributesOriginalfa4345465c63d4c716c273b9af64af46); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalfa4345465c63d4c716c273b9af64af46)): ?>
<?php $component = $__componentOriginalfa4345465c63d4c716c273b9af64af46; ?>
<?php unset($__componentOriginalfa4345465c63d4c716c273b9af64af46); ?>
<?php endif; ?>
                            </div>

                            <div>
                                <label for="project_leader" class="block text-xs font-black uppercase tracking-wider text-gray-600 dark:text-slate-300">Project Leader <?php if (isset($component)) { $__componentOriginal4f074808deca33fb39dbf7707c9b77e2 = $component; } ?>
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
                                <input id="project_leader" name="project_leader" type="text" list="proposal-workspace-people" value="<?php echo e(old('project_leader', $proposalDraft->project_leader ?: $proposalDraft->owner->name)); ?>" maxlength="120" required class="mt-2 block w-full rounded-lg border-gray-300 text-sm text-gray-900 shadow-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-800 dark:text-white">
                                <datalist id="proposal-workspace-people"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $workspacePeople; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $workspacePerson): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><option value="<?php echo e($workspacePerson['name']); ?>"><?php echo e($workspacePerson['email']); ?></option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?></datalist>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['project_leader'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="mt-2 text-xs font-semibold text-red-600"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>

                            <noscript>
                                <div class="flex justify-end border-t border-gray-200 pt-5 dark:border-slate-800">
                                    <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-red-600 px-5 py-3 text-sm font-bold text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2">Save project details</button>
                                </div>
                            </noscript>
                            </fieldset>
                        </form>
                    </section>
                </div>

                <aside>
                    <section aria-labelledby="workspace-overview-heading" class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div class="border-b border-gray-200 px-4 py-4 dark:border-slate-800">
                            <h3 id="workspace-overview-heading" class="text-sm font-black text-gray-950 dark:text-white">Proposal summary</h3>
                        </div>
                        <div aria-labelledby="package-progress-heading" class="p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h4 id="package-progress-heading" class="text-sm font-black text-gray-950 dark:text-white">Project progress</h4>
                                <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-slate-400"><?php echo e($completedPaperCount); ?> of <?php echo e($paperCount); ?> proposal papers ready</p>
                            </div>
                            <span class="shrink-0 rounded-full border px-2 py-1 text-[9px] font-black <?php echo e($completedPaperCount === $paperCount && $projectDetailsComplete ? 'border-gray-300 bg-gray-100 text-gray-700 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200' : 'border-red-200 bg-red-50 text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200'); ?>"><?php echo e($completedPaperCount === $paperCount && $projectDetailsComplete ? 'Ready' : 'In progress'); ?></span>
                        </div>
                        <div class="mt-4 h-1.5 overflow-hidden rounded-full bg-gray-100 dark:bg-slate-800" aria-hidden="true">
                            <div class="h-full rounded-full bg-red-600" style="width: <?php echo e($paperCount === 0 ? 0 : ($completedPaperCount / $paperCount) * 100); ?>%"></div>
                        </div>
                        </div>

                        <div aria-labelledby="recent-activity-heading" class="border-t border-gray-200 p-4 dark:border-slate-800">
                        <div class="flex items-start justify-between gap-2">
                            <h4 id="recent-activity-heading" class="text-sm font-black text-gray-950 dark:text-white">Recent activity</h4>
                        </div>
                        <div class="mt-3 divide-y divide-gray-100 border-y border-gray-100 dark:divide-slate-800 dark:border-slate-800">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $recentActivity; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $activity): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <article class="py-3">
                                    <p class="text-xs font-black leading-5 text-gray-900 dark:text-white"><?php echo e($activity->displaySummary()); ?></p>
                                    <p class="mt-1 text-[10px] text-gray-500 dark:text-slate-400"><?php echo e($activity->creator?->name ?? 'ATHENA'); ?> &middot; <?php echo e($activity->created_at->diffForHumans()); ?></p>
                                </article>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                <p class="py-5 text-center text-xs text-gray-500 dark:text-slate-400">Paper activity will appear after the first save or upload.</p>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                        </div>
                    </section>
                </aside>
            </div>
        </section>

        <section id="required-pdf-attachments-tab" x-show="activeProposalTab === 'attachments'" x-cloak role="tabpanel" aria-labelledby="required-pdf-attachments-tab-button">
            <div class="mb-4">
                <h3 id="required-papers-heading" class="text-lg font-black text-gray-950 dark:text-white">Required PDF attachments</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-slate-400">Complete the five proposal papers here. The GAD Checklist and Initial Screening Form are added automatically from Project Details.</p>
            </div>

            <div data-editable-proposal-papers x-data="proposalAssessmentPreview()" @resize.window.debounce.150ms="resizeProposalPaperPreview()" class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div :inert="previewFullscreen">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $editableChecklist; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <?php
                        $paper = $item['paper'];
                        $template = filled($paper['template_slug']) ? $templates->get($paper['template_slug']) : null;
                        $sampleDefinition = filled($paper['sample_slug']) ? config('proposal_samples.'.$paper['sample_slug']) : null;
                        $sampleAvailable = is_array($sampleDefinition)
                            && isset($sampleDefinition['path'])
                            && \Illuminate\Support\Facades\Storage::disk('local')->exists($sampleDefinition['path']);
                        $paperRoute = match ($paper['slug']) {
                            'detailed-proposal' => route('faculty.proposal-drafts.detailed-proposal.edit', $proposalDraft),
                            'work-plan' => route('faculty.proposal-drafts.work-plan.edit', $proposalDraft),
                            'line-item-budget' => route('faculty.proposal-drafts.line-item-budget.edit', $proposalDraft),
                            'expense-breakdown' => route('faculty.proposal-drafts.expense-breakdown.edit', $proposalDraft),
                            'curriculum-vitae' => route('faculty.proposal-drafts.curriculum-vitae.edit', $proposalDraft),
                            'gad-checklist' => route('faculty.proposal-drafts.gad-checklist.show', $proposalDraft),
                            'initial-screening-form' => route('faculty.proposal-drafts.initial-screening-form.show', $proposalDraft),
                            default => route('faculty.proposal-drafts.papers.edit', [$proposalDraft, $paper['slug']]),
                        };
                        $paperAction = $paper['workspace_button_label'] ?? 'Open '.$paper['label'];
                        $paperPreview = $submissionFilesPrepared ? [
                            'label' => $paper['label'],
                            'previewUrl' => route('faculty.proposal-drafts.submission-files.download', [$proposalDraft, $paper['slug']]),
                        ] : null;
                    ?>
                    <article data-proposal-paper="<?php echo e($paper['slug']); ?>" class="grid min-w-0 grid-cols-[3rem_minmax(0,1fr)] gap-4 border-b border-gray-200 p-5 last:border-b-0 dark:border-slate-800 sm:px-6 lg:grid-cols-[3rem_minmax(0,1fr)_18rem] lg:items-center">
                        <?php if (isset($component)) { $__componentOriginale5c1214596bdde4f44557aa43e4ebc13 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginale5c1214596bdde4f44557aa43e4ebc13 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.document-file-icon','data' => ['label' => 'PDF','class' => 'shrink-0']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('document-file-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => 'PDF','class' => 'shrink-0']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginale5c1214596bdde4f44557aa43e4ebc13)): ?>
<?php $attributes = $__attributesOriginale5c1214596bdde4f44557aa43e4ebc13; ?>
<?php unset($__attributesOriginale5c1214596bdde4f44557aa43e4ebc13); ?>
<?php endif; ?>
<?php if (isset($__componentOriginale5c1214596bdde4f44557aa43e4ebc13)): ?>
<?php $component = $__componentOriginale5c1214596bdde4f44557aa43e4ebc13; ?>
<?php unset($__componentOriginale5c1214596bdde4f44557aa43e4ebc13); ?>
<?php endif; ?>
                        <div class="min-w-0">
                            <p class="mb-1 text-sm text-gray-500 dark:text-slate-400">Paper <?php echo e(str_pad((string) $paper['order'], 2, '0', STR_PAD_LEFT)); ?></p>
                            <div class="flex flex-wrap items-center gap-2">
                                <h4 class="break-words text-base font-bold leading-6 text-gray-950 dark:text-white"><?php echo e($paper['label']); ?></h4>
                                <span class="rounded-full border px-2.5 py-1 text-sm font-semibold <?php echo e($item['needs_attention'] ? 'border-red-200 bg-red-50 text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200' : ($item['complete'] ? 'border-gray-300 bg-gray-100 text-gray-700 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200' : ($item['status'] === 'In progress' ? 'border-red-200 bg-red-50 text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200' : 'border-gray-200 bg-white text-gray-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-400'))); ?>"><?php echo e($item['status']); ?></span>
                            </div>
                            <p class="mt-1 text-sm leading-6 text-gray-500 dark:text-slate-400"><?php echo e($paper['description']); ?></p>

                            <div class="mt-2 text-sm text-gray-600 dark:text-slate-300">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($paper['mode'] === 'automatic'): ?>
                                    <p class="font-semibold">PDF prepared automatically from Project Details when the proposal is turned in.</p>
                                <?php elseif($item['documents']->isNotEmpty()): ?>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($paper['mode'] === 'generated'): ?>
                                        <p class="break-all font-semibold"><?php echo e($item['submission_filename']); ?></p>
                                        <p class="mt-0.5 text-sm leading-6 text-gray-500 dark:text-slate-400"><?php echo e($submissionFilesPrepared ? 'Prepared PDF' : 'Saved form · Submission PDFs not fully prepared'); ?> &middot; Saved <?php echo e($item['documents']->first()->updated_at->diffForHumans()); ?></p>
                                    <?php elseif($paper['multiple']): ?>
                                        <p class="font-semibold"><?php echo e($item['count']); ?> <?php echo e(Str::plural('file', $item['count'])); ?> staged</p>
                                    <?php else: ?>
                                        <p class="break-all font-semibold"><?php echo e($item['documents']->first()->original_filename); ?></p>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php else: ?>
                                    <p>No file or form data saved yet.</p>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>

                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($template || $sampleAvailable): ?>
                                <div class="mt-3 flex flex-wrap gap-3">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($template): ?><a href="<?php echo e(route('proposal-templates.download', $template)); ?>" class="secondary-action">Download template</a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($sampleAvailable): ?><a href="<?php echo e(route('proposal-samples.show', $paper['sample_slug'])); ?>" target="_blank" rel="noopener" class="secondary-action">View sample</a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                        <div class="col-span-2 grid min-w-0 grid-cols-1 gap-2 sm:grid-cols-2 lg:col-span-1 lg:grid-cols-1">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($paperPreview): ?>
                                <button type="button" data-proposal-paper-preview-trigger @click="openAssessmentPreview(<?php echo \Illuminate\Support\Js::from($paperPreview)->toHtml() ?>)" aria-label="Preview <?php echo e($paper['label']); ?>" aria-haspopup="dialog" aria-controls="draft-paper-preview-panel" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-red-200 px-4 py-2.5 text-base font-semibold text-red-700 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 dark:border-red-900 dark:text-red-300 dark:hover:bg-red-950/40">Preview PDF</button>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canEditDraft): ?>
                                <a href="<?php echo e($paperRoute); ?>" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-base font-bold text-gray-900 transition hover:border-red-600 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 dark:border-slate-600 dark:bg-slate-800 dark:text-white dark:hover:border-red-600 dark:hover:text-red-300" aria-label="<?php echo e($paperAction); ?>"><?php echo e($paperAction); ?></a>
                            <?php else: ?>
                                <span class="text-sm font-semibold text-gray-500 dark:text-slate-400">Editing locked</span>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    </article>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </div>
                <?php if (isset($component)) { $__componentOriginal10a0e39c04a9eacf5d69b3b1628f0121 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal10a0e39c04a9eacf5d69b3b1628f0121 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-paper-preview','data' => ['panelId' => 'draft-paper-preview-panel','previewLabel' => 'Prepared proposal paper preview','frameTitle' => 'Prepared proposal paper preview']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-paper-preview'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['panel-id' => 'draft-paper-preview-panel','preview-label' => 'Prepared proposal paper preview','frame-title' => 'Prepared proposal paper preview']); ?>
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

            <section data-automatic-assessment-forms data-automatic-assessment-preview x-data="proposalAssessmentPreview()" @resize.window.debounce.150ms="resizeProposalPaperPreview()" class="mt-5 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900" aria-labelledby="automatic-assessment-forms-heading">
                <div :inert="previewFullscreen">
                <div class="border-b border-gray-100 px-5 py-4 dark:border-slate-800 sm:px-6">
                    <h4 id="automatic-assessment-forms-heading" class="text-base font-bold text-gray-950 dark:text-white">Assessment forms added automatically</h4>
                    <p class="mt-1 text-sm leading-6 text-gray-500 dark:text-slate-400">No faculty answers or file uploads are needed. These two blank forms are generated from Project Details and included when the proposal is turned in.</p>
                </div>
                <div class="divide-y divide-gray-100 dark:divide-slate-800">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $automaticChecklist; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <?php
                            $formRoutes = 'faculty.proposal-drafts.'.$item['paper']['slug'];
                            $formPreview = [
                                'label' => $item['paper']['label'],
                                'previewUrl' => route($formRoutes.'.preview', $proposalDraft),
                                'downloadUrl' => route($formRoutes.'.download', $proposalDraft),
                            ];
                        ?>
                        <div class="grid min-w-0 grid-cols-[3rem_minmax(0,1fr)] gap-4 px-5 py-4 sm:px-6 lg:grid-cols-[3rem_minmax(0,1fr)_auto] lg:items-center">
                            <?php if (isset($component)) { $__componentOriginale5c1214596bdde4f44557aa43e4ebc13 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginale5c1214596bdde4f44557aa43e4ebc13 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.document-file-icon','data' => ['label' => 'PDF','class' => 'shrink-0']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('document-file-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => 'PDF','class' => 'shrink-0']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginale5c1214596bdde4f44557aa43e4ebc13)): ?>
<?php $attributes = $__attributesOriginale5c1214596bdde4f44557aa43e4ebc13; ?>
<?php unset($__attributesOriginale5c1214596bdde4f44557aa43e4ebc13); ?>
<?php endif; ?>
<?php if (isset($__componentOriginale5c1214596bdde4f44557aa43e4ebc13)): ?>
<?php $component = $__componentOriginale5c1214596bdde4f44557aa43e4ebc13; ?>
<?php unset($__componentOriginale5c1214596bdde4f44557aa43e4ebc13); ?>
<?php endif; ?>
                            <div class="min-w-0">
                                <p class="break-words text-base font-bold text-gray-900 dark:text-white"><?php echo e($item['paper']['label']); ?></p>
                                <p class="mt-0.5 text-sm leading-6 text-gray-500 dark:text-slate-400"><?php echo e($item['complete'] ? 'Added automatically to your submission' : 'Complete Project Details to prepare this form'); ?></p>
                            </div>
                            <button type="button" data-assessment-preview-trigger @click="openAssessmentPreview(<?php echo \Illuminate\Support\Js::from($formPreview)->toHtml() ?>)" aria-label="Preview <?php echo e($item['paper']['label']); ?>" aria-haspopup="dialog" aria-controls="assessment-form-preview-panel" class="col-span-2 inline-flex min-h-11 items-center justify-center rounded-lg border border-gray-300 px-4 py-2.5 text-base font-bold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-600 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800 lg:col-span-1">Preview form</button>
                        </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </div>
                </div>
                <?php if (isset($component)) { $__componentOriginal10a0e39c04a9eacf5d69b3b1628f0121 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal10a0e39c04a9eacf5d69b3b1628f0121 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-paper-preview','data' => ['panelId' => 'assessment-form-preview-panel','previewLabel' => 'Assessment form preview','frameTitle' => 'Assessment form preview']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-paper-preview'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['panel-id' => 'assessment-form-preview-panel','preview-label' => 'Assessment form preview','frame-title' => 'Assessment form preview']); ?>
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
            </section>

        </section>

        <section id="proposal-collaborators-tab" x-show="activeProposalTab === 'collaborators'" x-cloak role="tabpanel" aria-labelledby="proposal-collaborators-tab-button" class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-col gap-4 border-b border-gray-200 p-5 dark:border-slate-800 sm:p-6 xl:flex-row xl:items-start xl:justify-between">
                <div class="flex min-w-0 items-start gap-3">
                    <span data-team-section-icon aria-hidden="true" class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-300">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m20 0v-2a4 4 0 0 0-3-3.87M15 3.13a4 4 0 0 1 0 7.75" /><circle cx="9" cy="7" r="4" /></svg>
                    </span>
                    <div class="min-w-0">
                        <h3 id="workspace-members-heading" class="text-2xl font-bold text-gray-900 dark:text-white">Project team</h3>
                        <p class="mt-1 max-w-3xl text-base leading-7 text-gray-600 dark:text-slate-300">The project leader and project staff form the research team from proposal preparation through project completion. Only the project leader can submit the proposal.</p>
                    </div>
                </div>
                <div class="flex shrink-0 flex-wrap items-center gap-2">
                    <span class="inline-flex w-fit rounded-full bg-gray-100 px-3 py-1.5 text-sm font-semibold text-gray-700 dark:bg-slate-800 dark:text-slate-200"><?php echo e(1 + $proposalDraft->members->count()); ?> <?php echo e(Str::plural('member', 1 + $proposalDraft->members->count())); ?></span>
                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('manageMembers', $proposalDraft)): ?>
                        <button type="button" data-open-collaborator-modal x-on:click="$dispatch('open-modal', 'proposal-collaborator-invitation')" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-red-600 px-4 py-2.5 text-base font-semibold text-white transition hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 dark:focus:ring-offset-slate-900">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m18-11v6m-3-3h6" /><circle cx="9" cy="7" r="4" /></svg>
                            Add project staff
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <div data-project-team-roster class="divide-y divide-gray-200 dark:divide-slate-800">
                <article data-project-team-member="owner" class="grid min-w-0 gap-3 p-5 sm:grid-cols-[minmax(0,1fr)_auto] sm:p-6">
                    <div class="flex min-w-0 items-start gap-3 sm:gap-4">
                        <?php if (isset($component)) { $__componentOriginalaa6ddd3b8ee0acee5a2d1d7ac5c7e40e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalaa6ddd3b8ee0acee5a2d1d7ac5c7e40e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.user-avatar','data' => ['user' => $proposalDraft->owner,'class' => 'h-12 w-12 rounded-full bg-red-700 text-lg']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('user-avatar'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['user' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($proposalDraft->owner),'class' => 'h-12 w-12 rounded-full bg-red-700 text-lg']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalaa6ddd3b8ee0acee5a2d1d7ac5c7e40e)): ?>
<?php $attributes = $__attributesOriginalaa6ddd3b8ee0acee5a2d1d7ac5c7e40e; ?>
<?php unset($__attributesOriginalaa6ddd3b8ee0acee5a2d1d7ac5c7e40e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalaa6ddd3b8ee0acee5a2d1d7ac5c7e40e)): ?>
<?php $component = $__componentOriginalaa6ddd3b8ee0acee5a2d1d7ac5c7e40e; ?>
<?php unset($__componentOriginalaa6ddd3b8ee0acee5a2d1d7ac5c7e40e); ?>
<?php endif; ?>
                        <div class="min-w-0">
                            <h4 data-team-member-name class="break-words text-lg font-bold leading-7 text-gray-900 dark:text-white"><?php echo e($proposalDraft->owner->name); ?></h4>
                            <p data-team-member-email class="mt-1 break-all text-base leading-6 text-gray-600 dark:text-slate-300"><?php echo e($proposalDraft->owner->email); ?></p>
                            <p class="mt-2 text-base leading-6 text-gray-600 dark:text-slate-300">Project leader responsible for the team and proposal submission.</p>
                        </div>
                    </div>
                    <span data-team-member-status class="inline-flex h-fit w-fit items-center gap-1.5 rounded-full bg-red-50 px-3 py-1 text-sm font-semibold text-red-700 dark:bg-red-950/40 dark:text-red-200">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m12 3 8 4v5c0 4-3 7-8 9-5-2-8-5-8-9V7l8-4Zm-3 9 2 2 4-4" /></svg>
                        Project leader
                    </span>
                </article>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $proposalDraft->members; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $member): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <article data-project-team-member="<?php echo e($member->id); ?>" class="grid min-w-0 gap-3 p-5 sm:grid-cols-[minmax(0,1fr)_auto] sm:p-6">
                        <div class="flex min-w-0 items-start gap-3 sm:gap-4">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($member->user): ?>
                                <?php if (isset($component)) { $__componentOriginalaa6ddd3b8ee0acee5a2d1d7ac5c7e40e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalaa6ddd3b8ee0acee5a2d1d7ac5c7e40e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.user-avatar','data' => ['user' => $member->user,'class' => 'h-12 w-12 rounded-full bg-slate-600 text-lg dark:bg-slate-700']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('user-avatar'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['user' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($member->user),'class' => 'h-12 w-12 rounded-full bg-slate-600 text-lg dark:bg-slate-700']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalaa6ddd3b8ee0acee5a2d1d7ac5c7e40e)): ?>
<?php $attributes = $__attributesOriginalaa6ddd3b8ee0acee5a2d1d7ac5c7e40e; ?>
<?php unset($__attributesOriginalaa6ddd3b8ee0acee5a2d1d7ac5c7e40e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalaa6ddd3b8ee0acee5a2d1d7ac5c7e40e)): ?>
<?php $component = $__componentOriginalaa6ddd3b8ee0acee5a2d1d7ac5c7e40e; ?>
<?php unset($__componentOriginalaa6ddd3b8ee0acee5a2d1d7ac5c7e40e); ?>
<?php endif; ?>
                            <?php else: ?>
                                <span aria-hidden="true" data-team-pending-avatar class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4" /><path stroke-linecap="round" d="M4 21v-2a8 8 0 0 1 16 0v2" /></svg>
                                </span>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <div class="min-w-0">
                                <h4 data-team-member-name class="break-words text-lg font-bold leading-7 text-gray-900 dark:text-white"><?php echo e($member->user?->name ?? $member->name); ?></h4>
                                <p data-team-member-email class="mt-1 break-all text-base leading-6 text-gray-600 dark:text-slate-300"><?php echo e($member->user?->email ?? $member->email); ?></p>
                                <p class="mt-2 text-base leading-6 <?php echo e($member->isAccepted() ? 'text-gray-600 dark:text-slate-300' : 'text-amber-800 dark:text-amber-200'); ?>"><?php echo e($member->isAccepted() ? 'Can edit proposal papers. Only the project leader can submit.' : ($member->isLinked() ? 'Waiting for the invited staff member to accept the invitation.' : 'Waiting for this exact email to sign in to ATHENA.')); ?></p>
                                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('manageMembers', $proposalDraft)): ?>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($emailStatus = $member->invitationEmail?->statusMessage()): ?>
                                        <p data-invitation-email-status class="mt-1 text-sm text-gray-600 dark:text-slate-300"><?php echo e($emailStatus); ?></p>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="flex h-fit flex-wrap items-center gap-2 sm:max-w-56 sm:justify-end">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($member->isProjectSecretary()): ?>
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-3 py-1 text-sm font-semibold text-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true"><rect x="6" y="4" width="12" height="17" rx="2" /><path stroke-linecap="round" stroke-linejoin="round" d="M9 3h6v4H9V3Zm0 10h6m-6 4h4" /></svg>
                                        Project Secretary
                                    </span>
                                <?php elseif($member->isAccepted()): ?>
                                    <span class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-sm font-semibold text-gray-700 dark:bg-slate-800 dark:text-slate-200">Project staff</span>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <span data-team-member-status class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-sm font-semibold <?php echo e($member->isAccepted() ? 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200' : 'bg-amber-50 text-amber-800 dark:bg-amber-950/40 dark:text-amber-200'); ?>">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($member->isAccepted()): ?>
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path stroke-linecap="round" stroke-linejoin="round" d="m8 12 3 3 5-6" /></svg>
                                    <?php else: ?>
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path stroke-linecap="round" d="M12 7v5l3 2" /></svg>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <?php echo e($member->isAccepted() ? 'Confirmed' : ($member->isLinked() ? 'Invitation pending' : 'Pending sign-in')); ?>

                                </span>
                        </div>
                        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('manageMembers', $proposalDraft)): ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $member->isAccepted() || $proposalDraft->topic_id === null): ?>
                            <div class="flex flex-wrap items-center gap-2 sm:col-span-2 sm:pl-16">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $member->isAccepted()): ?>
                                <form action="<?php echo e(route('faculty.proposal-drafts.members.invitation', [$proposalDraft, $member])); ?>" method="POST">
                                    <?php echo csrf_field(); ?>
                                    <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-gray-300 px-3 py-2 text-base font-semibold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-600 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800">Resend invitation</button>
                                </form>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <form action="<?php echo e(route('faculty.proposal-drafts.members.destroy', [$proposalDraft, $member])); ?>" method="POST" data-proposal-confirm data-confirm-title="Remove project staff?" data-confirm-text="This person will immediately lose access to the proposal workspace. You can invite them again if needed." data-confirm-button="Remove project staff">
                                    <?php echo csrf_field(); ?>
                                    <?php echo method_field('DELETE'); ?>
                                    <button type="submit" class="secondary-action">Remove</button>
                                </form>
                            </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php endif; ?>
                    </article>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </div>

            <div class="border-t border-gray-200 bg-gray-50 p-5 dark:border-slate-800 dark:bg-slate-950/40 sm:p-6">
                <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(20rem,28rem)] lg:items-start">
                    <div class="flex min-w-0 items-start gap-3">
                        <span data-secretary-section-icon aria-hidden="true" class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><rect x="6" y="4" width="12" height="17" rx="2" /><path stroke-linecap="round" stroke-linejoin="round" d="M9 3h6v4H9V3Zm0 10h6m-6 4h4" /></svg>
                        </span>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gray-600 dark:text-slate-400">Project roles</p>
                            <h4 class="mt-1 text-xl font-bold text-gray-950 dark:text-white">Project Secretary</h4>
                            <p class="mt-2 max-w-2xl text-base leading-7 text-gray-600 dark:text-slate-300">The secretary monitors budget utilization and receives related reminders. Other authorized project staff can also complete this work.</p>
                        </div>
                    </div>

                    <div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($projectSecretaryMember): ?>
                            <div class="flex items-center gap-3 rounded-xl border border-amber-200 bg-amber-50 p-3 dark:border-amber-900 dark:bg-amber-950/30">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($projectSecretaryMember->user): ?>
                                    <?php if (isset($component)) { $__componentOriginalaa6ddd3b8ee0acee5a2d1d7ac5c7e40e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalaa6ddd3b8ee0acee5a2d1d7ac5c7e40e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.user-avatar','data' => ['user' => $projectSecretaryMember->user,'class' => 'h-12 w-12 rounded-full bg-amber-700 text-lg']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('user-avatar'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['user' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($projectSecretaryMember->user),'class' => 'h-12 w-12 rounded-full bg-amber-700 text-lg']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalaa6ddd3b8ee0acee5a2d1d7ac5c7e40e)): ?>
<?php $attributes = $__attributesOriginalaa6ddd3b8ee0acee5a2d1d7ac5c7e40e; ?>
<?php unset($__attributesOriginalaa6ddd3b8ee0acee5a2d1d7ac5c7e40e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalaa6ddd3b8ee0acee5a2d1d7ac5c7e40e)): ?>
<?php $component = $__componentOriginalaa6ddd3b8ee0acee5a2d1d7ac5c7e40e; ?>
<?php unset($__componentOriginalaa6ddd3b8ee0acee5a2d1d7ac5c7e40e); ?>
<?php endif; ?>
                                <?php else: ?>
                                    <span aria-hidden="true" class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-amber-700 text-lg font-bold text-white"><?php echo e(mb_substr($projectSecretaryMember->name, 0, 1)); ?></span>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <span class="min-w-0"><span class="block break-words text-base font-bold text-gray-950 dark:text-white"><?php echo e($projectSecretaryMember->user?->name ?? $projectSecretaryMember->name); ?></span><span class="mt-1 block break-all text-base text-gray-600 dark:text-slate-300"><?php echo e($projectSecretaryMember->user?->email ?? $projectSecretaryMember->email); ?></span></span>
                            </div>
                        <?php else: ?>
                            <div class="rounded-xl border border-dashed border-amber-300 bg-amber-50 px-4 py-3 text-base font-semibold leading-6 text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200">No Project Secretary has been assigned.</div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('manageMembers', $proposalDraft)): ?>
                            <div x-data="researchSecretaryPicker({ candidates: <?php echo \Illuminate\Support\Js::from($projectRoleCandidates)->toHtml() ?>, selectedId: <?php echo \Illuminate\Support\Js::from(old('member_id', $projectSecretaryMember?->id))->toHtml() ?> })" class="relative mt-3" data-project-role-picker>
                                <form x-ref="form" method="POST" action="<?php echo e(route('faculty.proposal-drafts.member-roles.update', $proposalDraft)); ?>">
                                    <?php echo csrf_field(); ?>
                                    <?php echo method_field('PATCH'); ?>
                                    <input type="hidden" name="project_role" value="secretary">
                                    <input type="hidden" name="member_id" :value="selectedId || ''">
                                </form>
                                <div class="flex flex-wrap gap-2">
                                    <button type="button" x-ref="trigger" :aria-expanded="open.toString()" aria-expanded="false" aria-controls="draft-project-secretary-options-<?php echo e($proposalDraft->id); ?>" aria-haspopup="listbox" @click="open = !open; if (open) $nextTick(() => $refs.search.focus())" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-gray-950 px-4 py-2 text-base font-semibold text-white hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 dark:bg-white dark:text-slate-950 dark:focus:ring-offset-slate-900"><?php echo e($projectSecretaryMember ? 'Change secretary' : 'Select project staff'); ?></button>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($projectSecretaryMember): ?>
                                        <button type="button" @click="clearSelection" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-base font-semibold text-gray-700 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800 dark:focus:ring-offset-slate-900">Remove role</button>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </div>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['member_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="mt-2 text-base font-semibold text-red-700 dark:text-red-300"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['project_role'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="mt-2 text-base font-semibold text-red-700 dark:text-red-300"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                                <div id="draft-project-secretary-options-<?php echo e($proposalDraft->id); ?>" x-show="open" x-transition.origin.top x-cloak @click.outside="open = false" @keydown.escape.stop="open = false; $refs.trigger.focus()" class="mt-3 w-full rounded-xl border border-gray-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-900">
                                    <label class="sr-only" for="draft-project-secretary-search-<?php echo e($proposalDraft->id); ?>">Search confirmed project staff</label>
                                    <input x-ref="search" id="draft-project-secretary-search-<?php echo e($proposalDraft->id); ?>" x-model="query" type="search" autocomplete="off" placeholder="Search confirmed project staff" class="block w-full min-h-11 rounded-lg border-gray-200 text-base focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-950 dark:text-white">
                                    <div class="mt-2 max-h-64 space-y-1 overflow-y-auto" role="listbox" aria-label="Confirmed project staff">
                                        <template x-for="candidate in filteredCandidates()" :key="candidate.id">
                                            <button type="button" role="option" :aria-selected="selectedId == candidate.id ? 'true' : 'false'" @click="select(candidate.id)" class="flex min-h-11 w-full items-center gap-3 rounded-lg px-2.5 py-2 text-left hover:bg-red-50 focus:bg-red-50 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-red-600 dark:hover:bg-slate-800 dark:focus:bg-slate-800">
                                                <span class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full bg-red-100 text-base font-bold text-red-700 dark:bg-red-950/40 dark:text-red-200"><img x-show="candidate.avatar" :src="candidate.avatar" alt="" x-on:error="candidate.avatar = ''" class="h-full w-full object-cover"><span x-show="!candidate.avatar" x-text="initials(candidate.name)"></span></span>
                                                <span class="min-w-0 flex-1"><span class="block break-words text-base font-semibold text-gray-900 dark:text-white" x-text="candidate.name"></span><span class="mt-0.5 block break-all text-base text-gray-600 dark:text-slate-300" x-text="candidate.email"></span><span x-show="candidate.college" class="mt-0.5 block text-sm text-gray-500 dark:text-slate-400" x-text="candidate.college"></span></span>
                                            </button>
                                        </template>
                                        <p x-show="filteredCandidates().length === 0" class="px-3 py-5 text-center text-base leading-6 text-gray-600 dark:text-slate-300">Staff must accept the project invitation before receiving a project role.</p>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <p class="mt-3 text-base leading-6 text-gray-600 dark:text-slate-300">Only the project leader can assign team roles.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </section>
    </div>

    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('manageMembers', $proposalDraft)): ?>
        <?php if (isset($component)) { $__componentOriginal9f64f32e90b9102968f2bc548315018c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9f64f32e90b9102968f2bc548315018c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.modal','data' => ['name' => 'proposal-collaborator-invitation','show' => $memberInvitationHasErrors,'maxWidth' => 'xl','focusable' => true,'role' => 'dialog','ariaModal' => 'true','ariaLabelledby' => 'team-invitation-heading']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('modal'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'proposal-collaborator-invitation','show' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($memberInvitationHasErrors),'maxWidth' => 'xl','focusable' => true,'role' => 'dialog','aria-modal' => 'true','aria-labelledby' => 'team-invitation-heading']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

            <div
                x-data="proposalDraftMembers({ candidates: <?php echo \Illuminate\Support\Js::from($memberCandidates)->toHtml() ?> })"
                data-collaborator-invitation-modal
                data-opened-from-validation="<?php echo e($memberInvitationHasErrors ? 'true' : 'false'); ?>"
                class="bg-white dark:bg-slate-900"
            >
                <div class="flex items-start justify-between gap-4 border-b border-gray-200 px-5 py-4 dark:border-slate-700 sm:px-6">
                    <div>
                        <h2 id="team-invitation-heading" class="flex items-center gap-2 text-xl font-bold text-gray-900 dark:text-white">
                            <svg class="h-6 w-6 shrink-0 text-red-700 dark:text-red-300" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m18-11v6m-3-3h6" /><circle cx="9" cy="7" r="4" /></svg>
                            Add project staff
                        </h2>
                        <p class="mt-2 text-base leading-6 text-gray-600 dark:text-slate-300">Invite project staff using the exact BatStateU Google account they use for ATHENA. Assign their project role after they accept.</p>
                    </div>
                    <button type="button" x-on:click="$dispatch('close-modal', 'proposal-collaborator-invitation')" class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg text-gray-500 transition hover:bg-gray-100 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-red-600 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white" aria-label="Close project staff invitation" title="Close">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6 6 18" /></svg>
                    </button>
                </div>

                <form action="<?php echo e(route('faculty.proposal-drafts.members.store', $proposalDraft)); ?>" method="POST">
                    <?php echo csrf_field(); ?>
                    <div class="space-y-5 px-5 py-5 sm:px-6">
                        <div>
                            <label for="workspace-member-email" class="block text-base font-semibold text-gray-700 dark:text-slate-300">BatStateU Google email</label>
                            <div class="relative mt-2" data-collaborator-account-picker x-on:click.outside="closePicker()">
                                <input
                                    id="workspace-member-email"
                                    name="email"
                                    type="email"
                                    x-model="email"
                                    x-on:focus="openPicker()"
                                    x-on:input="handleEmailInput()"
                                    x-on:keydown="handleEmailKeydown($event)"
                                    x-bind:aria-expanded="pickerOpen"
                                    x-bind:aria-activedescendant="pickerOpen && highlightedIndex >= 0 ? `workspace-member-option-${highlightedIndex}` : null"
                                    aria-autocomplete="list"
                                    aria-controls="workspace-member-account-options"
                                    role="combobox"
                                    value="<?php echo e(old('email')); ?>"
                                    maxlength="255"
                                    required
                                    autocomplete="off"
                                    placeholder="name@g.batstate-u.edu.ph"
                                    class="block w-full min-h-11 rounded-lg border-gray-300 py-2.5 pl-3 pr-11 text-base shadow-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-800 dark:text-white"
                                >
                                <button type="button" x-on:click="pickerOpen ? closePicker() : openPicker()" class="absolute inset-y-0 right-0 inline-flex w-11 items-center justify-center rounded-r-xl text-gray-400 transition hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-red-600 dark:text-slate-400 dark:hover:text-white" aria-label="Show ATHENA accounts" x-bind:aria-expanded="pickerOpen" aria-controls="workspace-member-account-options">
                                    <svg class="h-4 w-4 transition" x-bind:class="{ 'rotate-180': pickerOpen }" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" /></svg>
                                </button>

                                <div
                                    id="workspace-member-account-options"
                                    x-show="pickerOpen"
                                    x-cloak
                                    x-transition.origin.top
                                    role="listbox"
                                    aria-label="ATHENA accounts"
                                    class="absolute z-30 mt-2 max-h-64 w-full overflow-y-auto rounded-2xl border border-gray-200 bg-white p-1.5 shadow-xl shadow-gray-900/10 dark:border-slate-700 dark:bg-slate-800"
                                >
                                    <template x-for="(candidate, index) in filteredCandidates" x-bind:key="candidate.email">
                                        <button
                                            type="button"
                                            x-bind:id="`workspace-member-option-${index}`"
                                            role="option"
                                            x-bind:aria-selected="highlightedIndex === index"
                                            x-on:mouseenter="highlightedIndex = index"
                                            x-on:click="selectCandidate(candidate)"
                                            x-bind:class="highlightedIndex === index ? 'bg-red-50 dark:bg-red-950/40' : 'hover:bg-gray-50 dark:hover:bg-slate-700/70'"
                                            class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left transition"
                                        >
                                            <span class="relative flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full bg-red-100 text-base font-bold text-red-700 ring-1 ring-red-200 dark:bg-red-950 dark:text-red-200 dark:ring-red-900">
                                                <img x-show="candidate.avatar" x-bind:src="candidate.avatar" x-bind:alt="candidate.name" x-on:error="candidate.avatar = ''" class="h-full w-full object-cover">
                                                <span x-show="!candidate.avatar" x-text="candidateInitials(candidate.name)"></span>
                                            </span>
                                            <span class="min-w-0 flex-1">
                                                <span class="block break-words text-base font-semibold text-gray-900 dark:text-white" x-text="candidate.name"></span>
                                                <span class="block break-all text-sm text-gray-600 dark:text-slate-300" x-text="candidate.email"></span>
                                            </span>
                                            <svg class="h-4 w-4 shrink-0 text-red-600 opacity-0" x-bind:class="{ 'opacity-100': matchedAccount?.email === candidate.email }" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6" /></svg>
                                        </button>
                                    </template>

                                    <div x-show="filteredCandidates.length === 0" class="px-3 py-4 text-center">
                                        <p class="text-base font-semibold text-gray-700 dark:text-slate-200">No matching ATHENA account</p>
                                        <p class="mt-1 text-sm leading-6 text-gray-600 dark:text-slate-300">You can still invite the exact BatStateU Google email they will use to sign in.</p>
                                    </div>
                                </div>
                            </div>
                            <p class="mt-2 text-sm leading-6 text-gray-600 dark:text-slate-300">Choose an ATHENA account or enter the exact institutional email they will use to sign in.</p>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="mt-2 text-base font-semibold text-red-700 dark:text-red-300"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>

                        <div>
                            <label for="workspace-member-name" class="block text-base font-semibold text-gray-700 dark:text-slate-300">Staff member name</label>
                            <input id="workspace-member-name" name="name" type="text" x-model="name" value="<?php echo e(old('name')); ?>" maxlength="255" required placeholder="Full name" class="mt-2 block w-full min-h-11 rounded-lg border-gray-300 text-base shadow-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-800 dark:text-white">
                            <p class="mt-2 text-sm leading-6 text-gray-600 dark:text-slate-300" x-text="matchedAccount ? 'Pulled from the linked ATHENA account.' : 'Used until their ATHENA account is linked.'"></p>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="mt-2 text-base font-semibold text-red-700 dark:text-red-300"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    </div>

                    <div class="flex flex-col-reverse gap-2 border-t border-gray-200 bg-gray-50 px-5 py-4 dark:border-slate-700 dark:bg-slate-800/70 sm:flex-row sm:justify-end sm:px-6">
                        <button type="button" x-on:click="$dispatch('close-modal', 'proposal-collaborator-invitation')" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-base font-semibold text-gray-700 transition hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">Cancel</button>
                        <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-red-600 px-5 py-2.5 text-base font-semibold text-white transition hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2">Send invitation</button>
                    </div>
                </form>
            </div>
         <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9f64f32e90b9102968f2bc548315018c)): ?>
<?php $attributes = $__attributesOriginal9f64f32e90b9102968f2bc548315018c; ?>
<?php unset($__attributesOriginal9f64f32e90b9102968f2bc548315018c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9f64f32e90b9102968f2bc548315018c)): ?>
<?php $component = $__componentOriginal9f64f32e90b9102968f2bc548315018c; ?>
<?php unset($__componentOriginal9f64f32e90b9102968f2bc548315018c); ?>
<?php endif; ?>
    <?php endif; ?>

    <?php if (isset($component)) { $__componentOriginal9f64f32e90b9102968f2bc548315018c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9f64f32e90b9102968f2bc548315018c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.modal','data' => ['name' => 'proposal-review','maxWidth' => '6xl','focusable' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('modal'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'proposal-review','maxWidth' => '6xl','focusable' => true]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

        <div class="flex max-h-[calc(100vh-3rem)] flex-col">
            <div class="flex shrink-0 items-start justify-between gap-4 border-b border-gray-200 bg-white px-5 py-4 sm:px-6">
                <div>
                    <h2 class="text-lg font-black text-gray-900">Review and Turn In</h2>
                    <p class="mt-1 text-xs text-gray-500">Review your proposal papers before submitting. The assessment forms are included automatically.</p>
                </div>
                <button type="button" x-on:click="$dispatch('close-modal', 'proposal-review')" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl text-gray-500 transition hover:bg-gray-100 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-red-600" aria-label="Close review and turn in" title="Close review and turn in">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6 6 18" /></svg>
                </button>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto bg-gray-50 px-4 py-5 sm:px-6 sm:py-6">
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

                        <p class="font-black">This project cannot be turned in yet.</p>
                        <ul class="mt-2 list-disc space-y-1 pl-5"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><li><?php echo e($error); ?></li><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?></ul>
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

                <div class="space-y-6">
                    <?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('proposal-draft-review-package', ['proposal-draft' => $proposalDraft,'in-modal' => true,'lazy' => true]);

$__keyOuter = $__key ?? null;

$__key = null;
$__componentSlots = [];

$__key ??= \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::generateKey('lw-2546067776-0', $__key);

$__html = app('livewire')->mount($__name, $__params, $__key, $__componentSlots);

echo $__html;

unset($__html);
unset($__key);
$__key = $__keyOuter;
unset($__keyOuter);
unset($__name);
unset($__params);
unset($__componentSlots);
unset($__split);
?>
                </div>
            </div>
        </div>
     <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9f64f32e90b9102968f2bc548315018c)): ?>
<?php $attributes = $__attributesOriginal9f64f32e90b9102968f2bc548315018c; ?>
<?php unset($__attributesOriginal9f64f32e90b9102968f2bc548315018c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9f64f32e90b9102968f2bc548315018c)): ?>
<?php $component = $__componentOriginal9f64f32e90b9102968f2bc548315018c; ?>
<?php unset($__componentOriginal9f64f32e90b9102968f2bc548315018c); ?>
<?php endif; ?>
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
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\faculty\proposal-drafts\show.blade.php ENDPATH**/ ?>