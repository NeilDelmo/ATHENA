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

    <?php
        $historySubject = $archived ? $topic : $proposalDraft;
        $indexRoute = $archived ? 'topics.draft-history.index' : 'faculty.proposal-drafts.history.index';
        $backRoute = $archived
            ? route('topics.show', $topic)
            : route('faculty.proposal-drafts.show', $proposalDraft).'#required-pdf-attachments';
        $subjectTitle = $archived ? $topic->title : $proposalDraft->project_title;
    ?>

     <?php $__env->slot('header', null, []); ?> 
        <?php if (isset($component)) { $__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.page-header','data' => ['title' => $archived ? 'Submitted draft record' : 'Recovery history','subtitle' => $subjectTitle]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('page-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($archived ? 'Submitted draft record' : 'Recovery history'),'subtitle' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($subjectTitle)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

             <?php $__env->slot('actions', null, []); ?> 
                <?php if (isset($component)) { $__componentOriginal5426bd0bea02df2e6dd2a60e50fa4c01 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal5426bd0bea02df2e6dd2a60e50fa4c01 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.back-link','data' => ['fixed' => true,'href' => ''.e($backRoute).'']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('back-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['fixed' => true,'href' => ''.e($backRoute).'']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>
Back to <?php echo e($archived ? 'submitted proposal' : 'project'); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal5426bd0bea02df2e6dd2a60e50fa4c01)): ?>
<?php $attributes = $__attributesOriginal5426bd0bea02df2e6dd2a60e50fa4c01; ?>
<?php unset($__attributesOriginal5426bd0bea02df2e6dd2a60e50fa4c01); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal5426bd0bea02df2e6dd2a60e50fa4c01)): ?>
<?php $component = $__componentOriginal5426bd0bea02df2e6dd2a60e50fa4c01; ?>
<?php unset($__componentOriginal5426bd0bea02df2e6dd2a60e50fa4c01); ?>
<?php endif; ?>
                <span class="inline-flex w-fit rounded-full bg-gray-100 px-3 py-1.5 text-xs font-black text-gray-700 dark:bg-slate-800 dark:text-slate-200"><?php echo e($versions->total()); ?> <?php echo e(Str::plural('recovery point', $versions->total())); ?></span>
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

    <div class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
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

                <p class="font-black">The recovery point could not be restored.</p>
                <p class="mt-1"><?php echo e($errors->first()); ?></p>
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

        <section data-submitted-record-preservation aria-labelledby="history-explanation-heading" class="rounded-2xl border border-red-200 bg-red-50 p-5 shadow-sm dark:border-red-900 dark:bg-red-950/30 sm:p-6">
            <h3 id="history-explanation-heading" class="text-base font-black text-gray-950 dark:text-white"><?php echo e($archived ? 'This submitted record is preserved' : 'Recovery is automatic'); ?></h3>
            <p class="mt-2 text-sm leading-6 text-gray-700 dark:text-slate-300">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($archived): ?>
                    This read-only record was kept with the submitted proposal. It includes the saved papers and PDFs that were available when the proposal was turned in.
                <?php else: ?>
                    Your working draft saves continuously. ATHENA keeps recovery points about every 30 minutes of active editing, before a restore, and when you turn in the proposal. Redundant automatic points are kept to a small useful set.
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </p>
        </section>

        <div class="grid gap-6 lg:grid-cols-[14rem_minmax(0,1fr)] lg:items-start">
            <nav aria-label="Filter recovery history by paper" class="flex flex-col items-start gap-2">
                <a href="<?php echo e(route($indexRoute, $historySubject)); ?>" class="inline-flex w-full items-center rounded-xl border px-3 py-2 text-xs font-bold <?php echo e($selectedPaper === null ? 'border-gray-950 bg-gray-950 text-white dark:border-white dark:bg-white dark:text-gray-950' : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800'); ?>">All papers</a>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $papers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $paper): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <a href="<?php echo e(route($indexRoute, [$historySubject, 'paper' => $paper['slug']])); ?>" class="inline-flex w-full items-center rounded-xl border px-3 py-2 text-xs font-bold <?php echo e(($selectedPaper['slug'] ?? null) === $paper['slug'] ? 'border-gray-950 bg-gray-950 text-white dark:border-white dark:bg-white dark:text-gray-950' : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800'); ?>"><?php echo e($paper['label']); ?></a>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </nav>

            <div class="min-w-0 space-y-6">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($versions->isEmpty()): ?>
                    <section class="rounded-2xl border border-dashed border-gray-300 bg-white p-8 text-center shadow-sm dark:border-slate-700 dark:bg-slate-900">
                        <h3 class="text-base font-black text-gray-900 dark:text-white">No recovery points yet</h3>
                        <p class="mt-2 text-sm text-gray-500 dark:text-slate-400">Your working draft still saves automatically. A recovery point appears after meaningful active editing or when a paper is uploaded.</p>
                    </section>
                <?php else: ?>
                    <section aria-label="Proposal paper recovery points" class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="divide-y divide-gray-100 dark:divide-slate-800">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $versions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $version): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <?php
                            $currentDocument = $currentDocuments->get($version->document_type.':'.$version->position);
                            $currentVersion = $currentVersions->get($version->document_type.':'.$version->position, 0);
                            $matchesWorkingDraft = ! $archived && $currentDocument !== null && $version->version_number === $currentVersion;
                            $changes = collect($version->changes ?? []);
                            $pointLabel = match ($version->action) {
                                \App\Models\ProposalDraftDocumentVersion::ACTION_CHECKPOINT => 'Automatic',
                                \App\Models\ProposalDraftDocumentVersion::ACTION_PRE_RESTORE => 'Before restore',
                                \App\Models\ProposalDraftDocumentVersion::ACTION_RESTORED => 'Restored',
                                \App\Models\ProposalDraftDocumentVersion::ACTION_SUBMITTED => 'Submitted',
                                \App\Models\ProposalDraftDocumentVersion::ACTION_REMOVED => 'Removed',
                                default => $archived ? 'Archived' : 'Saved',
                            };
                        ?>
                        <article class="p-5 sm:p-6">
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                <div class="flex min-w-0 gap-4">
                                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl <?php echo e($version->action === \App\Models\ProposalDraftDocumentVersion::ACTION_REMOVED ? 'bg-red-700' : 'bg-gray-950 dark:bg-white'); ?> text-white dark:text-gray-950" aria-hidden="true">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m5-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                                    </span>
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h3 class="text-sm font-black text-gray-900 dark:text-white"><?php echo e($version->label()); ?></h3>
                                            <span class="rounded-full bg-gray-100 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-gray-700 dark:bg-slate-800 dark:text-slate-200"><?php echo e($pointLabel); ?></span>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($matchesWorkingDraft): ?>
                                                <span class="rounded-full bg-red-100 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-red-800 dark:bg-red-950/60 dark:text-red-200">Matches working draft</span>
                                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        </div>

                                        <p class="mt-2 text-sm font-bold text-gray-800 dark:text-slate-100"><?php echo e($version->displaySummary()); ?></p>

                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($version->hasStoredFile()): ?>
                                            <p class="mt-1 break-all text-xs font-semibold text-gray-700 dark:text-slate-300"><?php echo e($version->original_filename); ?></p>
                                            <p class="mt-1 text-xs text-gray-500 dark:text-slate-400"><?php echo e($version->file_size ? \Illuminate\Support\Number::fileSize($version->file_size) : 'Size unavailable'); ?> &middot; PDF attachment</p>
                                        <?php else: ?>
                                            <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">Structured form data saved for PDF generation during Turn in.</p>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(filled($version->change_note)): ?>
                                            <blockquote class="mt-3 rounded-xl border-l-4 border-red-300 bg-red-50 px-4 py-3 text-sm leading-6 text-red-950 dark:border-red-700 dark:bg-red-950/40 dark:text-red-100">
                                                <span class="font-black">Details:</span> <?php echo e($version->change_note); ?>

                                            </blockquote>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($version->restoredFrom): ?>
                                            <p class="mt-3 text-xs font-semibold text-red-700 dark:text-red-300">Restored from an earlier recovery point.</p>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                                        <p class="mt-3 text-xs text-gray-500 dark:text-slate-400">
                                            Saved by <span class="font-bold text-gray-700 dark:text-slate-200"><?php echo e($version->creator?->name ?? 'ATHENA'); ?></span>
                                            <span aria-hidden="true">&middot;</span>
                                            <time datetime="<?php echo e($version->created_at->toIso8601String()); ?>" title="<?php echo e($version->created_at->format('M j, Y g:i A')); ?>"><?php echo e($version->created_at->format('M j, Y g:i A')); ?></time>
                                        </p>
                                    </div>
                                </div>

                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($version->hasStoredFile()): ?>
                                    <a href="<?php echo e($archived ? route('topics.draft-history.download', [$topic, $version]) : route('faculty.proposal-drafts.history.download', [$proposalDraft, $version])); ?>" class="inline-flex w-full shrink-0 items-center justify-center rounded-xl border border-gray-300 px-4 py-2.5 text-xs font-bold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800 sm:w-auto">Download PDF</a>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>

                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($changes->isNotEmpty()): ?>
                                <details class="mt-4 rounded-xl border border-gray-200 bg-gray-50 dark:border-slate-700 dark:bg-slate-800/70">
                                    <summary class="cursor-pointer px-4 py-3 text-xs font-black text-gray-700 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-red-600 dark:text-slate-200">See <?php echo e($changes->count()); ?> <?php echo e(Str::plural('change', $changes->count())); ?></summary>
                                    <div class="overflow-x-auto border-t border-gray-200 dark:border-slate-700">
                                        <table class="min-w-full divide-y divide-gray-200 text-left text-xs dark:divide-slate-700">
                                            <thead class="bg-white text-[10px] font-black uppercase tracking-wider text-gray-500 dark:bg-slate-900 dark:text-slate-400"><tr><th class="px-4 py-3">Field</th><th class="px-4 py-3">Before</th><th class="px-4 py-3">After</th></tr></thead>
                                            <tbody class="divide-y divide-gray-200 dark:divide-slate-700">
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $changes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $change): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                                    <tr><th class="px-4 py-3 font-bold text-gray-800 dark:text-slate-100"><?php echo e($change['label']); ?></th><td class="max-w-xs whitespace-pre-wrap px-4 py-3 text-gray-500 dark:text-slate-400"><?php echo e($change['before']); ?></td><td class="max-w-xs whitespace-pre-wrap px-4 py-3 font-semibold text-gray-800 dark:text-slate-200"><?php echo e($change['after']); ?></td></tr>
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </details>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $archived && ! $matchesWorkingDraft && ! in_array($version->document_type, \App\Models\ProposalVersionFile::GENERATED_ASSESSMENT_FORM_TYPES, true)): ?>
                                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('update', $proposalDraft)): ?>
                                    <details class="mt-4 rounded-xl border border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-950/30">
                                        <summary class="cursor-pointer px-4 py-3 text-xs font-black text-red-800 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-red-600 dark:text-red-200">Restore this recovery point</summary>
                                        <form
                                            method="POST"
                                            action="<?php echo e(route('faculty.proposal-drafts.history.restore', [$proposalDraft, $version])); ?>"
                                            class="space-y-3 border-t border-red-200 p-4 dark:border-red-900"
                                            data-proposal-confirm
                                            data-confirm-title="Restore this recovery point?"
                                            data-confirm-text="ATHENA will first preserve your current working draft as another recovery point. You can restore it again later."
                                            data-confirm-button="Restore recovery point"
                                            data-confirm-icon="question"
                                        >
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="document_version" value="<?php echo e(old('document_version', $currentVersion)); ?>">
                                            <p class="text-xs leading-5 text-red-950 dark:text-red-100">This replaces the current working draft for this paper. Your current state is saved first, so it remains recoverable.</p>
                                            <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl bg-red-600 px-4 py-2.5 text-xs font-bold text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 sm:w-auto">Restore this point</button>
                                        </form>
                                    </details>
                                <?php endif; ?>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </article>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </div>
                    </section>

                    <?php echo e($versions->links()); ?>

                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>
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
<?php /**PATH C:\laragon\www\athena-app\src\resources\views/faculty/proposal-drafts/history.blade.php ENDPATH**/ ?>