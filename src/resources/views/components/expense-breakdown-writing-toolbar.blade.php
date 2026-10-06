<x-proposal-writing-toolbar toolbar-id="expense-breakdown-tools" data-expense-breakdown-writing-toolbar aria-label="Expense Breakdown tools">
    <x-slot:context><p class="proposal-writing-target">Project total: <span>Php <span x-text="formatMoney(grandTotal())"></span></span></p></x-slot:context>
    <div class="proposal-writing-buttons" role="toolbar" aria-label="Expense preview">
        <button type="button" data-proposal-preview-toggle @click="previewPaneOpen ? closeProposalPreview() : showProposalPreview()" :aria-expanded="previewPaneOpen" aria-controls="expense-breakdown-preview-panel" x-text="previewPaneOpen ? 'Hide preview' : 'Preview paper'"></button>
    </div>
</x-proposal-writing-toolbar>
