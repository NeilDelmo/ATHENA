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
                <span class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-wider <?php echo e($documents->count() >= $paper['min_files'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'); ?>"><?php echo e($documents->count() >= $paper['min_files'] ? 'Uploaded' : 'Upload required'); ?></span>
                <a href="<?php echo e(route('faculty.proposal-drafts.history.index', [$proposalDraft, 'paper' => $paper['slug']])); ?>" class="inline-flex h-11 w-full items-center justify-center rounded-xl border border-gray-300 bg-white text-gray-800 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 sm:w-11" aria-label="Open recovery history" title="Recovery history"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m5-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg></a>
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
        $sampleDefinition = filled($paper['sample_slug']) ? config('proposal_samples.'.$paper['sample_slug']) : null;
        $sampleAvailable = is_array($sampleDefinition)
            && isset($sampleDefinition['path'])
            && \Illuminate\Support\Facades\Storage::disk('local')->exists($sampleDefinition['path']);
        $remainingSlots = $paper['multiple']
            ? max(0, $paper['max_files'] - $documents->count())
            : 1;
        $accept = collect($paper['accepted_extensions'])->map(fn ($extension) => '.'.$extension)->implode(',');
        $isExpenseBreakdown = $paper['slug'] === 'expense-breakdown';
        $fileLabel = 'PDF';
        $uploadHeading = $paper['multiple'] && $documents->isNotEmpty()
            ? 'Add completed files'
            : ($documents->isNotEmpty() ? 'Replace the uploaded '.$fileLabel : 'Upload the completed '.$fileLabel);
        $submitLabel = $documents->isNotEmpty() && ! $paper['multiple']
            ? 'Replace '.$fileLabel
            : 'Upload '.($paper['multiple'] ? 'files' : $fileLabel);
    ?>

    <div
        class="mx-auto max-w-6xl space-y-6 px-4 py-8 sm:px-6 lg:px-8"
        data-paper-editor
        data-paper-dirty="<?php echo e($errors->any() ? 'true' : 'false'); ?>"
        data-paper-edit-url="<?php echo e(route('faculty.proposal-drafts.papers.edit', [$proposalDraft, $paper['slug']])); ?>"
        data-paper-exit-url="<?php echo e(route('faculty.proposal-drafts.show', $proposalDraft)); ?>#required-pdf-attachments"
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

                <p class="font-bold">The <?php echo e($fileLabel); ?> could not be changed.</p>
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

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($paper['multiple'])): ?>
            <?php if (isset($component)) { $__componentOriginal365c40492913100a7be7d48ba061239f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal365c40492913100a7be7d48ba061239f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-collaboration-monitor','data' => ['loadedVersion' => (int) old('document_version', $currentVersions->get(0, 0)),'stateUrl' => route('faculty.proposal-drafts.edit-state', [$proposalDraft, $paper['document_type'], 0]),'reloadUrl' => route('faculty.proposal-drafts.papers.edit', [$proposalDraft, $paper['slug']]),'historyUrl' => route('faculty.proposal-drafts.history.index', [$proposalDraft, 'paper' => $paper['slug']]),'label' => $paper['label']]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-collaboration-monitor'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['loaded-version' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute((int) old('document_version', $currentVersions->get(0, 0))),'state-url' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('faculty.proposal-drafts.edit-state', [$proposalDraft, $paper['document_type'], 0])),'reload-url' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('faculty.proposal-drafts.papers.edit', [$proposalDraft, $paper['slug']])),'history-url' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('faculty.proposal-drafts.history.index', [$proposalDraft, 'paper' => $paper['slug']])),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($paper['label'])]); ?>
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
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_21rem] lg:items-start">
            <div class="space-y-6">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($documents->isNotEmpty()): ?>
                    <section aria-labelledby="uploaded-files-heading" class="rounded-2xl border border-green-200 bg-white p-5 shadow-sm sm:p-6">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-wider text-green-700">Attached to this draft</p>
                                <h3 id="uploaded-files-heading" class="mt-1 text-base font-black text-gray-900">PDF attachment</h3>
                            </div>
                            <span class="rounded-full bg-green-100 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-green-800">Attached</span>
                        </div>

                        <div class="mt-4 divide-y divide-gray-100 overflow-hidden rounded-xl border border-gray-200">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $documents; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $document): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <div class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="min-w-0">
                                        <p class="break-all text-sm font-bold text-gray-900"><?php echo e($document->original_filename); ?></p>
                                        <p class="mt-1 text-[11px] text-gray-500"><?php echo e($document->file_size ? number_format($document->file_size / 1024, 1).' KB' : 'Size unavailable'); ?> &middot; Uploaded <?php echo e($document->updated_at->diffForHumans()); ?></p>
                                    </div>
                                    <div class="grid shrink-0 grid-cols-2 gap-2">
                                        <a href="<?php echo e(route('faculty.proposal-drafts.papers.download', [$proposalDraft, $paper['slug'], $document])); ?>" class="inline-flex items-center justify-center rounded-lg border border-gray-300 px-3 py-2 text-xs font-bold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-600 focus:ring-offset-2">Download</a>
                                        <form
                                            action="<?php echo e(route('faculty.proposal-drafts.papers.remove', [$proposalDraft, $paper['slug'], $document])); ?>"
                                            method="POST"
                                            data-proposal-confirm
                                            data-confirm-title="Remove uploaded file?"
                                            data-confirm-text="This file will be removed from the staged proposal paper."
                                            data-confirm-button="Remove file"
                                        >
                                            <?php echo csrf_field(); ?>
                                            <?php echo method_field('DELETE'); ?>
                                            <input type="hidden" name="document_version" value="<?php echo e(old('document_version', $currentVersions->get($document->position, $document->lock_version))); ?>">
                                            <button type="submit" class="inline-flex w-full items-center justify-center rounded-lg border border-red-200 px-3 py-2 text-xs font-bold text-red-700 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2">Remove</button>
                                        </form>
                                    </div>
                                </div>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </div>
                    </section>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <section aria-labelledby="upload-paper-heading" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                    <p class="text-[10px] font-black uppercase tracking-wider text-red-700">PDF upload</p>
                    <h3 id="upload-paper-heading" class="mt-1 text-lg font-black text-gray-900"><?php echo e($uploadHeading); ?></h3>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-gray-600">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isExpenseBreakdown): ?>
                            Complete the official spreadsheet outside ATHENA, export or save it as a PDF, then attach that final PDF here. ATHENA will preserve it exactly as uploaded.
                        <?php else: ?>
                            Complete the required file outside ATHENA, then upload the finished copy here for inclusion in your project.
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </p>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($remainingSlots === 0): ?>
                        <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">The <?php echo e($paper['max_files']); ?>-file limit has been reached. Remove a file before adding another.</div>
                    <?php else: ?>
                        <form data-paper-form action="<?php echo e(route('faculty.proposal-drafts.papers.update', [$proposalDraft, $paper['slug']])); ?>" method="POST" enctype="multipart/form-data" class="mt-6 space-y-5">
                            <?php echo csrf_field(); ?>
                            <?php echo method_field('PUT'); ?>
                            <input type="hidden" name="document_version" value="<?php echo e(old('document_version', $currentVersions->get(0, 0))); ?>">

                            <div>
                                <label for="documents" class="block text-xs font-black uppercase tracking-wider text-gray-700">Choose completed PDF <span class="text-red-600">Required</span></label>
                                <div class="mt-2 rounded-2xl border border-dashed border-gray-300 bg-gray-50 p-4 sm:p-5">
                                    <input id="documents" name="documents[]" type="file" accept="<?php echo e($accept); ?>" <?php if($paper['multiple']): ?> multiple <?php endif; ?> required class="block w-full cursor-pointer rounded-xl border border-gray-300 bg-white p-2.5 text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-gray-900 file:px-3 file:py-2 file:text-xs file:font-bold file:text-white hover:file:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2">
                                    <p class="mt-3 text-[11px] leading-5 text-gray-500">
                                        <?php echo e(collect($paper['accepted_extensions'])->map(fn ($extension) => strtoupper($extension))->implode(' or ')); ?> only &middot; Maximum 25 MB per file
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($paper['multiple']): ?> &middot; Up to <?php echo e($remainingSlots); ?> more <?php echo e(Str::plural('file', $remainingSlots)); ?> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </p>
                                </div>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['documents'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="mt-2 text-xs font-semibold text-red-600"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['documents.*'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="mt-2 text-xs font-semibold text-red-600"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>

                            <div class="flex flex-col gap-3 border-t border-gray-100 pt-5 sm:flex-row sm:justify-end">
                                <button data-paper-save-exit type="submit" name="exit_after_save" value="1" class="inline-flex w-full items-center justify-center rounded-xl bg-red-600 px-5 py-3 text-sm font-bold text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 sm:w-auto"><?php echo e($submitLabel); ?> and exit</button>
                            </div>
                        </form>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </section>
            </div>

            <aside aria-labelledby="upload-guidance-heading" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6 lg:sticky lg:top-6">
                <h3 id="upload-guidance-heading" class="text-base font-black text-gray-900">How this paper works</h3>
                <p class="mt-2 text-sm leading-6 text-gray-600"><?php echo e($paper['description']); ?></p>

                <ol class="mt-5 space-y-4">
                    <li class="flex gap-3">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-gray-900 text-xs font-black text-white">1</span>
                        <div><p class="text-sm font-bold text-gray-900">Get the official format</p><p class="mt-1 text-xs leading-5 text-gray-500">Download the template or use the latest copy supplied by the Research Office.</p></div>
                    </li>
                    <li class="flex gap-3">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-gray-900 text-xs font-black text-white">2</span>
                        <div><p class="text-sm font-bold text-gray-900">Complete and export it</p><p class="mt-1 text-xs leading-5 text-gray-500">Fill in the official spreadsheet using the appropriate desktop application, then export the finished copy as PDF.</p></div>
                    </li>
                    <li class="flex gap-3">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-gray-900 text-xs font-black text-white">3</span>
                        <div><p class="text-sm font-bold text-gray-900">Attach the finished PDF</p><p class="mt-1 text-xs leading-5 text-gray-500">This exact PDF becomes the version included when the proposal is turned in.</p></div>
                    </li>
                </ol>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($template || $sampleAvailable): ?>
                    <div class="mt-6 grid gap-2 border-t border-gray-100 pt-5">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($template): ?><a href="<?php echo e(route('proposal-templates.download', $template)); ?>" class="inline-flex items-center justify-center rounded-xl bg-gray-900 px-4 py-2.5 text-xs font-bold text-white hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-900 focus:ring-offset-2">Download official template</a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($sampleAvailable): ?><a href="<?php echo e(route('proposal-samples.show', $paper['sample_slug'])); ?>" target="_blank" rel="noopener" class="inline-flex items-center justify-center rounded-xl border border-gray-300 px-4 py-2.5 text-xs font-bold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-600 focus:ring-offset-2">View sample</a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </aside>
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
<?php /**PATH C:\laragon\www\athena-app\src\resources\views/faculty/proposal-drafts/papers/edit.blade.php ENDPATH**/ ?>