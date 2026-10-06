<div data-proposal-workspace-toolbar data-work-plan-writing-toolbar :inert="previewFullscreen" class="proposal-writing-toolbar" role="group" aria-label="Work Plan tools">
    <div class="proposal-writing-toolbar-row" role="toolbar" aria-label="Work Plan editing and preview">
        <p class="proposal-writing-target">Editing: <span x-text="activeWorkPlanEntry ? `Objective ${entries.indexOf(activeWorkPlanEntry) + 1}` : 'Objectives and schedule'"></span></p>
        <div class="proposal-writing-buttons">
            <button type="button" data-proposal-preview-toggle @click="previewPaneOpen ? closeProposalPreview() : showProposalPreview()" :aria-expanded="previewPaneOpen" aria-controls="work-plan-preview-panel" x-text="previewPaneOpen ? 'Hide preview' : 'Preview paper'"></button>
            <button type="button" x-show="activeWorkPlanEntry" x-cloak @click="toggleEntry(activeWorkPlanEntry)" :aria-expanded="activeWorkPlanEntry && isEntryExpanded(activeWorkPlanEntry)" :aria-controls="activeWorkPlanEntry ? `work-plan-editor-${activeWorkPlanEntry.id}` : null" x-text="activeWorkPlanEntry && isEntryExpanded(activeWorkPlanEntry) ? 'Collapse objective' : 'Expand objective'"></button>
        </div>
    </div>
    <p class="proposal-writing-hint">Activities, expected outputs, and scheduled months save automatically. Open the paper preview when you need it.</p>
</div>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views/components/work-plan-writing-toolbar.blade.php ENDPATH**/ ?>