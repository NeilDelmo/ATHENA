<?php if (isset($component)) { $__componentOriginal90e243942d22f6a4722cd614e5732fed = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal90e243942d22f6a4722cd614e5732fed = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-writing-toolbar','data' => ['toolbarId' => 'line-item-budget-tools','dataLineItemBudgetWritingToolbar' => true,'ariaLabel' => 'Line-Item Budget tools']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-writing-toolbar'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['toolbar-id' => 'line-item-budget-tools','data-line-item-budget-writing-toolbar' => true,'aria-label' => 'Line-Item Budget tools']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

     <?php $__env->slot('context', null, []); ?> <p class="proposal-writing-target">Project total: <span>Php <span data-line-item-budget-toolbar-total x-text="formatMoney(projectTotal())"></span></span></p> <?php $__env->endSlot(); ?>
    <div class="proposal-writing-toolbar-row" role="toolbar" aria-label="Budget sections and preview">

        <div class="proposal-writing-buttons">
            <button type="button" @click="focusLineItemBudgetSection('mooe')" aria-controls="line-item-budget-section-mooe">MOOE</button>
            <button type="button" @click="focusLineItemBudgetSection('co')" aria-controls="line-item-budget-section-co">Capital Outlays</button>
            <button type="button" data-proposal-preview-toggle @click="previewPaneOpen ? closeProposalPreview() : showProposalPreview()" :aria-expanded="previewPaneOpen" aria-controls="line-item-budget-preview-panel" x-text="previewPaneOpen ? 'Hide preview' : 'Preview paper'"></button>
        </div>
    </div>
    <p class="proposal-writing-hint">Budget changes save automatically. <span x-show="budgetCeiling > 0">Limit: Php <span x-text="formatMoney(budgetCeiling)"></span>. <span x-show="isOverBudget()" x-cloak class="budget-writing-limit-warning">Over the limit by Php <span x-text="formatMoney(budgetOverage())"></span>; saved as a draft.</span></span></p>
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
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\components\line-item-budget-writing-toolbar.blade.php ENDPATH**/ ?>