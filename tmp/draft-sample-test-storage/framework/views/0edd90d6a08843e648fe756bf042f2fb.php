<?php
    $receivedOnly = $receivedOnly ?? false;
?>
<?php
    $submissionRoute = $receivedOnly ? 'research_head.received-submissions.index' : 'research_head.proposal-submissions.index';
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.page-header','data' => ['class' => ($receivedOnly ? '' : 'proposal-reviews-header ').'[&_h1]:text-3xl [&_p]:text-base [&_p]:leading-6','title' => $receivedOnly ? 'Received submissions' : 'Proposal reviews','subtitle' => $receivedOnly ? 'Find incoming projects and revisions, newest first.' : 'Review current projects and track the next decision.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('page-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(($receivedOnly ? '' : 'proposal-reviews-header ').'[&_h1]:text-3xl [&_p]:text-base [&_p]:leading-6'),'title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($receivedOnly ? 'Received submissions' : 'Proposal reviews'),'subtitle' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($receivedOnly ? 'Find incoming projects and revisions, newest first.' : 'Review current projects and track the next decision.')]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

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

    <div class="space-y-5" data-proposal-submissions <?php if(! $receivedOnly): ?> data-proposal-reviews <?php endif; ?>>
        <?php if (isset($component)) { $__componentOriginal629ab6ff68dfa7c57a7ddb83c9ae9302 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal629ab6ff68dfa7c57a7ddb83c9ae9302 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.kpi-strip','data' => ['class' => '[&_dt]:text-sm [&_dt]:leading-5 [&_dd]:!text-[28px] [&_svg]:h-4 [&_svg]:w-4','dataSubmissionSummary' => true,'items' => [
            ['label' => 'Proposal records', 'value' => \Illuminate\Support\Number::format($summary['proposals']), 'icon' => 'folder'],
            ['label' => 'Active queue', 'value' => \Illuminate\Support\Number::format($summary['active']), 'icon' => 'clock'],
            ['label' => 'All submissions', 'value' => \Illuminate\Support\Number::format($summary['total']), 'icon' => 'layers'],
            ['label' => 'Initial submissions', 'value' => \Illuminate\Support\Number::format($summary['initial']), 'icon' => 'file-plus'],
            ['label' => 'Revisions received', 'value' => \Illuminate\Support\Number::format($summary['revision']), 'icon' => 'refresh'],
        ]]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('kpi-strip'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => '[&_dt]:text-sm [&_dt]:leading-5 [&_dd]:!text-[28px] [&_svg]:h-4 [&_svg]:w-4','data-submission-summary' => true,'items' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute([
            ['label' => 'Proposal records', 'value' => \Illuminate\Support\Number::format($summary['proposals']), 'icon' => 'folder'],
            ['label' => 'Active queue', 'value' => \Illuminate\Support\Number::format($summary['active']), 'icon' => 'clock'],
            ['label' => 'All submissions', 'value' => \Illuminate\Support\Number::format($summary['total']), 'icon' => 'layers'],
            ['label' => 'Initial submissions', 'value' => \Illuminate\Support\Number::format($summary['initial']), 'icon' => 'file-plus'],
            ['label' => 'Revisions received', 'value' => \Illuminate\Support\Number::format($summary['revision']), 'icon' => 'refresh'],
        ])]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal629ab6ff68dfa7c57a7ddb83c9ae9302)): ?>
<?php $attributes = $__attributesOriginal629ab6ff68dfa7c57a7ddb83c9ae9302; ?>
<?php unset($__attributesOriginal629ab6ff68dfa7c57a7ddb83c9ae9302); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal629ab6ff68dfa7c57a7ddb83c9ae9302)): ?>
<?php $component = $__componentOriginal629ab6ff68dfa7c57a7ddb83c9ae9302; ?>
<?php unset($__componentOriginal629ab6ff68dfa7c57a7ddb83c9ae9302); ?>
<?php endif; ?>

        <form method="GET" action="<?php echo e(route($submissionRoute)); ?>" class="grid gap-2 sm:grid-cols-2 xl:grid-cols-[minmax(0,1fr)_220px_260px_auto]">
            <label class="sr-only" for="proposal-submission-search">Search proposal submissions</label>
            <input id="proposal-submission-search" name="search" type="search" value="<?php echo e($search); ?>" placeholder="Search proposal, faculty, or research call..." class="block w-full rounded-xl border-gray-200 text-base focus:border-gray-500 focus:ring-gray-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white dark:placeholder:text-slate-500">
            <label class="sr-only" for="proposal-submission-type">Submission type</label>
            <select id="proposal-submission-type" name="type" class="block w-full rounded-xl border-gray-200 text-base focus:border-gray-500 focus:ring-gray-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                <option value="">All submission types</option>
                <option value="initial" <?php if($submissionType === 'initial'): echo 'selected'; endif; ?>>Initial submissions</option>
                <option value="revision" <?php if($submissionType === 'revision'): echo 'selected'; endif; ?>>Revisions</option>
                <option value="update" <?php if($submissionType === 'update'): echo 'selected'; endif; ?>>Submission updates</option>
            </select>
            <label class="sr-only" for="proposal-submission-status">Active review stage</label>
            <select id="proposal-submission-status" name="status" class="block w-full rounded-xl border-gray-200 text-base focus:border-gray-500 focus:ring-gray-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                <option value="">All active review stages</option>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = [
                    'pending' => 'New submission / Needs review',
                    'gad_assessment' => 'GAD assessment',
                    'co_evaluator_review' => 'Co-evaluator review',
                    'lrec_queued' => 'Awaiting LREC presentation',
                    'lrec_review' => 'LREC review',
                    'revision_requested' => 'Revision requested',
                    'resubmitted' => 'New revision / Needs review',
                    'ready_for_signature' => 'Final signing',
                ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <option value="<?php echo e($value); ?>" <?php if($status === $value): echo 'selected'; endif; ?>><?php echo e($label); ?></option>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </select>
            <div class="flex gap-2">
                <button type="submit" class="rounded-xl bg-red-600 px-4 py-2 text-sm font-bold text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2 dark:bg-red-800 dark:hover:bg-red-700 dark:focus:ring-red-400 dark:focus:ring-offset-slate-900">Filter</button>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($search !== '' || $submissionType !== '' || $status !== ''): ?>
                    <a href="<?php echo e(route($submissionRoute)); ?>" class="inline-flex min-h-11 items-center rounded-lg border border-gray-200 px-3 text-sm font-semibold text-gray-600 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-gray-500 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">Clear</a>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </form>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $receivedOnly): ?>
        <div x-data="{ workflowOpen: false }" data-submission-workflow-reference>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-gray-500 dark:text-slate-400">Use the workflow as a reference for queue labels and what the faculty needs to do next.</p>
                <button type="button" @click="workflowOpen = ! workflowOpen" :aria-expanded="workflowOpen.toString()" aria-expanded="false" aria-controls="submission-workflow-guide" data-submission-workflow-toggle class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl border border-red-200 bg-white px-3 py-2 text-base font-bold text-red-700 shadow-sm transition hover:bg-red-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand dark:border-red-900 dark:bg-slate-900 dark:text-red-200 dark:hover:bg-red-950/40 dark:focus-visible:ring-red-400">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8.25" /><path stroke-linecap="round" d="M12 10.5v5m0-8.25h.01" /></svg>
                    <span x-text="workflowOpen ? 'Hide workflow' : 'Show workflow'">Show workflow</span>
                </button>
            </div>
            <div x-cloak x-show="workflowOpen" class="[&_.text-xs]:text-sm [&_.text-sm]:text-base [&_.text-sm]:leading-6">
                <?php if (isset($component)) { $__componentOriginal30d528a6ab0933c570934791a85cd659 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal30d528a6ab0933c570934791a85cd659 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-workflow','data' => ['id' => 'submission-workflow-guide']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-workflow'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['id' => 'submission-workflow-guide']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal30d528a6ab0933c570934791a85cd659)): ?>
<?php $attributes = $__attributesOriginal30d528a6ab0933c570934791a85cd659; ?>
<?php unset($__attributesOriginal30d528a6ab0933c570934791a85cd659); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal30d528a6ab0933c570934791a85cd659)): ?>
<?php $component = $__componentOriginal30d528a6ab0933c570934791a85cd659; ?>
<?php unset($__componentOriginal30d528a6ab0933c570934791a85cd659); ?>
<?php endif; ?>
            </div>
        </div>

        <section aria-labelledby="active-proposal-queue-heading" class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-5 py-4 dark:border-slate-800">
                <div>
                    <h3 id="active-proposal-queue-heading" class="text-xl font-black text-gray-900 dark:text-white">Active proposal queue</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-slate-400">One latest submission per proposal. New submissions are marked in red.</p>
                </div>
                <span class="shrink-0 text-sm tabular-nums text-gray-500 dark:text-slate-400"><?php echo e($activeProposals->total()); ?> active</span>
            </div>
            <div data-proposal-queue-layout="rows" class="overflow-hidden rounded-b-xl border-x border-b border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900">
                <div aria-hidden="true" class="hidden grid-cols-[minmax(0,1fr)_220px_180px_196px] items-center gap-4 border-b border-brand bg-brand px-4 py-3 text-sm font-semibold text-white dark:border-red-900 dark:bg-brand dark:text-white xl:grid">
                    <span>Proposal and faculty</span><span>Review stage</span><span>Latest submission</span><span class="text-right">Action</span>
                </div>
                <div class="divide-y divide-gray-100 dark:divide-slate-800">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $activeProposals; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $proposal): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <?php
                            $latestSubmission = $proposal->latestVersion;
                            $isNewlyReceivedVersion = in_array($proposal->status, ['pending', 'resubmitted'], true) && ! $proposal->latestVersionHasBeenViewedByResearchHead();
                            $statusLabel = $proposal->researchHeadQueueStatusLabel($latestSubmission);
                            $receivedAt = $latestSubmission?->created_at ?? $proposal->created_at;
                            $isRevisedSubmission = $latestSubmission?->submission_type === 'revision';
                        ?>
                        <article data-proposal-id="<?php echo e($proposal->id); ?>" data-proposal-state="<?php echo e($isNewlyReceivedVersion ? 'new' : 'opened'); ?>"
                            class="<?php echo \Illuminate\Support\Arr::toCssClasses([
                                'grid grid-cols-[minmax(0,1fr)_auto] gap-3 border-l-2 px-4 py-3 bg-white hover:bg-slate-50 dark:bg-slate-900 dark:hover:bg-slate-800/50 xl:grid-cols-[minmax(0,1fr)_220px_180px_196px] xl:items-center xl:gap-4',
                                'border-l-red-600 dark:border-l-red-400' => $isNewlyReceivedVersion,
                                'border-l-transparent' => ! $isNewlyReceivedVersion,
                            ]); ?>">
                            <div class="col-span-2 min-w-0 xl:col-span-1">
                                <h4 class="break-words text-base font-semibold leading-6 text-gray-900 dark:text-white"><?php echo e($proposal->title); ?></h4>
                                <p class="mt-1 text-sm text-gray-500 dark:text-slate-400"><?php echo e($proposal->user->name); ?></p>
                            </div>
                            <div class="col-span-2 min-w-0 xl:col-span-1">
                                <span class="sr-only">Review stage:</span>
                                <span data-proposal-status-label="<?php echo e($statusLabel); ?>" class="<?php echo \Illuminate\Support\Arr::toCssClasses([
                                    'inline-flex max-w-full rounded-md px-2 py-1 text-sm font-medium leading-5',
                                    'bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-300' => $isNewlyReceivedVersion,
                                    'border border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200' => ! $isNewlyReceivedVersion,
                                ]); ?>"><?php echo e($statusLabel); ?></span>
                            </div>
                            <div class="col-span-2 min-w-0 text-sm text-gray-500 dark:text-slate-400 xl:col-span-1">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($latestSubmission): ?>
                                    <p class="font-medium text-gray-700 dark:text-slate-200"><?php echo e($latestSubmission->submission_type === 'update' ? 'Updated submission' : ($isRevisedSubmission ? 'Revised submission' : 'Initial submission')); ?> · Version <?php echo e($latestSubmission->version_number); ?></p>
                                <?php else: ?>
                                    <p>Submitted proposal record</p>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <time datetime="<?php echo e($receivedAt?->toIso8601String()); ?>" title="<?php echo e($receivedAt?->format('M j, Y g:i A')); ?>" class="mt-1 block"><?php echo e($receivedAt?->diffForHumans()); ?></time>
                            </div>
                            <div class="col-span-2 text-right xl:col-span-1">
                                <a href="<?php echo e(route('topics.show', $proposal)); ?>" aria-label="<?php echo e($proposal->status === 'revision_requested' ? 'Revision record for ' : 'Review: '); ?><?php echo e($proposal->title); ?>" class="inline-flex min-h-11 w-48 whitespace-nowrap items-center justify-center gap-2 rounded-lg bg-brand px-4 text-base font-semibold text-white hover:bg-brand-soft focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand dark:focus-visible:outline-red-400">
                                    <?php echo e($proposal->status === 'revision_requested' ? 'Revision record' : 'Review'); ?><span aria-hidden="true">→</span>
                                </a>
                            </div>

                        </article>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        <div class="px-4 py-8 text-center">
                            <h4 class="text-base font-semibold text-gray-900 dark:text-white">No active proposals in the queue</h4>
                            <p class="mt-1 text-sm text-gray-500 dark:text-slate-400">New and revised submissions will appear here. Approved projects remain in Project Monitoring.</p>
                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($activeProposals->hasPages()): ?>
                <div class="border-t border-gray-100 px-5 py-4 dark:border-slate-800"><?php echo e($activeProposals->links()); ?></div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </section>

        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <<?php echo e($receivedOnly ? 'section' : 'details'); ?> id="submission-history" data-submission-history <?php if($search !== '' || $submissionType !== '' || $status !== '' || request()->has('page')): ?> open <?php endif; ?> class="group/history">
            <<?php echo e($receivedOnly ? 'div' : 'summary'); ?> class="mb-3 flex min-h-14 cursor-pointer list-none flex-wrap items-center justify-between gap-3 rounded-lg focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand dark:focus-visible:outline-red-400 sm:flex-nowrap [&::-webkit-details-marker]:hidden">
                <div class="border-l-4 border-brand pl-3 dark:border-red-400">
                    <h3 id="proposal-submission-records-heading" class="text-xl font-bold text-gray-900 dark:text-white"><?php echo e($receivedOnly ? 'Received submissions' : 'Submission history'); ?></h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-slate-400">All submitted versions, newest first.</p>
                </div>
                <span class="flex shrink-0 items-center gap-3 text-sm tabular-nums text-gray-500 dark:text-slate-400">
                    <?php echo e($submissions->total()); ?> submissions
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $receivedOnly): ?><span class="rounded-lg border border-slate-200 bg-white px-3 py-2 font-semibold text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"><span class="group-open/history:hidden">Show history</span><span class="hidden group-open/history:inline">Hide history</span></span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </span>
            </<?php echo e($receivedOnly ? 'div' : 'summary'); ?>>
            <div data-submission-history-layout="rows" class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900">
                <div aria-hidden="true" class="hidden grid-cols-[minmax(0,1fr)_200px_170px_130px_196px] items-center gap-4 border-b border-brand bg-brand px-4 py-3 text-sm font-semibold text-white dark:border-red-900 xl:grid">
                    <span>Proposal and faculty</span><span>Current review stage</span><span>Submission</span><span>Received</span><span class="text-right">Action</span>
                </div>
                <div class="divide-y divide-gray-100 dark:divide-slate-800">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $submissions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $submission): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <?php
                            $isRevision = $submission->submission_type === 'revision';
                            $fileCount = $submission->package_files_count ?: ($submission->file_path ? 1 : 0);
                            $historyStatusLabel = $submission->topic->researchHeadQueueStatusLabel($submission->topic->latestVersion);
                        ?>
                        <article data-submission-id="<?php echo e($submission->id); ?>" class="grid min-w-0 grid-cols-[minmax(0,1fr)_auto] gap-3 border-l-2 border-l-transparent bg-white px-4 py-3 hover:bg-slate-50 dark:bg-slate-900 dark:hover:bg-slate-800/50 xl:grid-cols-[minmax(0,1fr)_200px_170px_130px_196px] xl:items-center xl:gap-4">
                            <div class="col-span-2 min-w-0 xl:col-span-1">
                                <h4 class="break-words text-base font-semibold leading-6 text-gray-900 dark:text-white"><?php echo e($submission->title); ?></h4>
                                <p class="mt-1 text-sm text-gray-500 dark:text-slate-400"><?php echo e($submission->topic->user->name); ?></p>
                                <details class="group/package mt-1 text-sm text-gray-500 dark:text-slate-400">
                                    <summary class="inline-flex min-h-8 cursor-pointer list-none items-center gap-2 rounded font-medium hover:text-gray-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-gray-500 dark:hover:text-white [&::-webkit-details-marker]:hidden"><span class="group-open/package:hidden" aria-hidden="true">+</span><span class="hidden group-open/package:inline" aria-hidden="true">&minus;</span>Submission details</summary>
                                    <div class="space-y-1 py-2 leading-6">
                                        <p class="break-all"><?php echo e($submission->topic->user->email); ?></p>
                                        <p><?php echo e($submission->topic->researchCall?->title ?? 'Research call unavailable'); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($submission->topic->researchCall?->academic_year): ?> · AY <?php echo e($submission->topic->researchCall->academic_year); ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></p>
                                        <p>Submitted by <?php echo e($submission->submitter?->name ?? 'Former user'); ?></p>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($submission->change_summary): ?>
                                            <p><span class="font-semibold">Changes:</span> <?php echo e($submission->change_summary); ?></p>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </div>
                                </details>
                            </div>
                            <div class="col-span-2 min-w-0 xl:col-span-1">
                                <span class="sr-only">Current review stage:</span>
                                <span data-proposal-history-status-label="<?php echo e($historyStatusLabel); ?>" class="inline-flex rounded-md border border-slate-200 bg-slate-50 px-2 py-1 text-sm font-medium leading-5 text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"><?php echo e($historyStatusLabel); ?></span>
                            </div>
                            <div class="col-span-2 min-w-0 text-sm text-gray-500 dark:text-slate-400 xl:col-span-1">
                                <span class="sr-only">Submission:</span>
                                <p class="font-medium text-gray-700 dark:text-slate-200"><?php echo e($submission->submission_type === 'update' ? 'Submission update' : ($isRevision ? 'Revision' : 'Initial submission')); ?> · Version <?php echo e($submission->version_number); ?></p>
                                <p class="mt-1"><?php echo e($fileCount); ?> <?php echo e(Str::plural('document', $fileCount)); ?></p>
                            </div>
                            <div class="col-span-2 text-sm text-gray-500 dark:text-slate-400 xl:col-span-1">
                                <span class="sr-only">Received:</span>
                                <time datetime="<?php echo e($submission->created_at->toIso8601String()); ?>" class="whitespace-nowrap"><?php echo e($submission->created_at->format('M j, Y')); ?></time>
                                <p class="mt-1"><?php echo e($submission->created_at->format('g:i A')); ?></p>
                            </div>
                            <div class="col-span-2 text-right xl:col-span-1">
                                <a href="<?php echo e(route('topics.show', $submission->topic)); ?>#version-history" aria-label="View history for <?php echo e($submission->title); ?>, version <?php echo e($submission->version_number); ?>" class="inline-flex min-h-11 w-48 whitespace-nowrap items-center justify-center gap-2 rounded-lg bg-brand px-4 text-base font-semibold text-white hover:bg-brand-soft focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand dark:focus-visible:outline-red-400">View history <span aria-hidden="true">→</span></a>
                            </div>
                        </article>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        <div class="px-4 py-8 text-center"><h4 class="text-base font-semibold text-gray-900 dark:text-white">No proposal submissions found</h4><p class="mt-1 text-sm text-gray-500 dark:text-slate-400">Try changing the search or filters.</p></div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($submissions->hasPages()): ?>
                <div class="mt-3"><?php echo e($submissions->links()); ?></div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </<?php echo e($receivedOnly ? 'section' : 'details'); ?>>
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
<?php /**PATH C:\laragon\www\athena-app\src\resources\views/research_head/proposal-submissions/index.blade.php ENDPATH**/ ?>