<div data-proposal-workspace-toolbar data-line-item-budget-writing-toolbar :inert="previewFullscreen" class="proposal-writing-toolbar" role="group" aria-label="Line-Item Budget tools">
    <div class="proposal-writing-toolbar-row" role="toolbar" aria-label="Budget sections and preview">
        <p class="proposal-writing-target">Project total: <span>Php <span data-line-item-budget-toolbar-total x-text="formatMoney(projectTotal())"></span></span></p>
        <div class="proposal-writing-buttons">
            <button type="button" @click="focusLineItemBudgetSection('mooe')" aria-controls="line-item-budget-section-mooe">MOOE</button>
            <button type="button" @click="focusLineItemBudgetSection('co')" aria-controls="line-item-budget-section-co">Capital Outlays</button>
            <button type="button" data-proposal-preview-toggle @click="previewPaneOpen ? closeProposalPreview() : showProposalPreview()" :aria-expanded="previewPaneOpen" aria-controls="line-item-budget-preview-panel" x-text="previewPaneOpen ? 'Hide preview' : 'Preview paper'"></button>
        </div>
    </div>
    <p class="proposal-writing-hint">Budget changes save automatically. <span x-show="budgetCeiling > 0">Limit: Php <span x-text="formatMoney(budgetCeiling)"></span>. <span x-show="isOverBudget()" x-cloak class="budget-writing-limit-warning">Over the limit by Php <span x-text="formatMoney(budgetOverage())"></span>; saved as a draft.</span></span></p>
</div>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views/components/line-item-budget-writing-toolbar.blade.php ENDPATH**/ ?>