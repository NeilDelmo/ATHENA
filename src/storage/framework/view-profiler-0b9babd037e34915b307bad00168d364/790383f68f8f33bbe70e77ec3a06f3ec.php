<?php if (isset($component)) { $__componentOriginal90e243942d22f6a4722cd614e5732fed = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal90e243942d22f6a4722cd614e5732fed = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-writing-toolbar','data' => ['toolbarId' => 'work-plan-tools','dataWorkPlanWritingToolbar' => true,'ariaLabel' => 'Work Plan tools']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-writing-toolbar'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['toolbar-id' => 'work-plan-tools','data-work-plan-writing-toolbar' => true,'aria-label' => 'Work Plan tools']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

     <?php $__env->slot('context', null, []); ?> <p class="proposal-writing-target">Editing: <span x-text="activeWorkPlanEntry ? `Objective ${entries.indexOf(activeWorkPlanEntry) + 1}` : 'Objectives and schedule'"></span></p> <?php $__env->endSlot(); ?>
    <div class="proposal-writing-toolbar-row" role="toolbar" aria-label="Work Plan editing and preview">

        <div class="proposal-writing-buttons">
            <button type="button" data-proposal-preview-toggle @click="previewPaneOpen ? closeProposalPreview() : showProposalPreview()" :aria-expanded="previewPaneOpen" aria-controls="work-plan-preview-panel" x-text="previewPaneOpen ? 'Hide preview' : 'Preview paper'"></button>
            <button type="button" x-show="activeWorkPlanEntry" x-cloak @click="toggleEntry(activeWorkPlanEntry)" :aria-expanded="activeWorkPlanEntry && isEntryExpanded(activeWorkPlanEntry)" :aria-controls="activeWorkPlanEntry ? `work-plan-editor-${activeWorkPlanEntry.id}` : null" x-text="activeWorkPlanEntry && isEntryExpanded(activeWorkPlanEntry) ? 'Collapse objective' : 'Expand objective'"></button>
        </div>
    </div>
    <p class="proposal-writing-hint">Activities, expected outputs, and scheduled months save automatically. The paper preview opens beside the form on larger screens.</p>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal90e243942d22f6a4722cd614e5732fed)): ?>
<?php $attributes = $__attributesOriginal90e243942d22f6a4722cd614e5732fed; ?>
<?php unset($__attributesOriginal90e243942d22f6a4722cd614e5732fed); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal90e243942d22f6a4722cd614e5732fed)): ?>
<?php $component = $__componentOriginal90e243942d22f6a4722cd614e5732fed; ?>
<?php unset($__componentOriginal90e243942d22f6a4722cd614e5732fed); ?>
<?php endif; ?>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\components\work-plan-writing-toolbar.blade.php ENDPATH**/ ?>