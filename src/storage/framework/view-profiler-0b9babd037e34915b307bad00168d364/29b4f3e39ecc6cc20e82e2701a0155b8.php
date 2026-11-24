<div data-proposal-review-package x-data="proposalAssessmentPreview()" @resize.window.debounce.150ms="resizeProposalPaperPreview()">
    <div :inert="previewFullscreen">
        <div class="mb-5">
            <?php if (isset($component)) { $__componentOriginal8d66b06c35093cf744d1f2b53857c718 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal8d66b06c35093cf744d1f2b53857c718 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-submission-readiness','data' => ['url' => route('faculty.proposal-drafts.review', $proposalDraft),'blockers' => $readinessBlockers,'readyToPrepare' => $readyToPrepare,'readyToSubmit' => $readyToSubmit]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-submission-readiness'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['url' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('faculty.proposal-drafts.review', $proposalDraft)),'blockers' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($readinessBlockers),'ready-to-prepare' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($readyToPrepare),'ready-to-submit' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($readyToSubmit)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal8d66b06c35093cf744d1f2b53857c718)): ?>
<?php $attributes = $__attributesOriginal8d66b06c35093cf744d1f2b53857c718; ?>
<?php unset($__attributesOriginal8d66b06c35093cf744d1f2b53857c718); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal8d66b06c35093cf744d1f2b53857c718)): ?>
<?php $component = $__componentOriginal8d66b06c35093cf744d1f2b53857c718; ?>
<?php unset($__componentOriginal8d66b06c35093cf744d1f2b53857c718); ?>
<?php endif; ?>
            <?php if (isset($component)) { $__componentOriginal319c2ed83bc05541d477f47890dd11b4 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal319c2ed83bc05541d477f47890dd11b4 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-pdf-preparation','data' => ['proposalDraft' => $proposalDraft,'class' => 'mt-3']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-pdf-preparation'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['proposal-draft' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($proposalDraft),'class' => 'mt-3']); ?>
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
        </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($statusMessage !== ''): ?>
            <div role="status" class="mb-6 border-l-4 border-green-600 bg-green-50 px-5 py-4 text-sm text-green-950">
                <p class="font-black">Submission PDFs prepared</p>
                <p class="mt-1"><?php echo e($statusMessage); ?></p>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?>
            <div role="alert" class="mb-6 border-l-4 border-red-600 bg-red-50 px-5 py-4 text-sm text-red-950">
                <p class="font-black">This project cannot be turned in yet.</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <li><?php echo e($error); ?></li>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </ul>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <div class="space-y-6">
            <?php echo $__env->make('faculty.proposal-drafts._review-package', ['inModal' => $inModal], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        </div>
    </div>
    <?php if (isset($component)) { $__componentOriginal10a0e39c04a9eacf5d69b3b1628f0121 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal10a0e39c04a9eacf5d69b3b1628f0121 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-paper-preview','data' => ['panelId' => 'review-paper-preview-panel','previewLabel' => 'Proposal paper preview','frameTitle' => 'Proposal paper content preview']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-paper-preview'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['panel-id' => 'review-paper-preview-panel','preview-label' => 'Proposal paper preview','frame-title' => 'Proposal paper content preview']); ?>
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
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\livewire\proposal-draft-review-package.blade.php ENDPATH**/ ?>