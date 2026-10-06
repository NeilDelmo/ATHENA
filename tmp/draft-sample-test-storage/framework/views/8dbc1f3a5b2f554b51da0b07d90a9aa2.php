<div data-proposal-workspace-toolbar data-curriculum-vitae-writing-toolbar :inert="previewFullscreen" class="proposal-writing-toolbar" role="group" aria-label="Curriculum Vitae tools">
    <div class="cv-writing-member-controls">
        <label for="cv-editing-member">Editing member
            <select id="cv-editing-member" x-model.number="activeCvPersonId" @change="focusPerson(activeCvPersonIndex, { focus: false })" aria-controls="cv-members-form">
                <template x-for="(person, index) in people" :key="person.id">
                    <option :value="person.id" x-text="`${index + 1}. ${personLabel(person)}`"></option>
                </template>
            </select>
        </label>
        <label for="cv-editing-section">Section
            <select id="cv-editing-section" x-model="activeCvSection" @change="focusCurriculumVitaeSection({ focus: false })">
                <option value="personal">Personal information</option>
                <template x-for="section in curriculumVitaeSectionOptions()" :key="section.key">
                    <option :value="section.key" x-text="section.label"></option>
                </template>
            </select>
        </label>
    </div>
    <div class="proposal-writing-buttons mt-2" role="toolbar" aria-label="CV members, entries, and preview">
        <button type="button" @click="toggleCurriculumVitaeMemberManager()" :aria-expanded="cvMemberManagerOpen" aria-controls="cv-member-manager">Add member</button>
        <button type="button" x-show="activeCvSection !== 'personal'" x-cloak @click="addSectionRow(activeCvPersonIndex, activeCvSection)">Add entry</button>
        <button type="button" data-proposal-preview-toggle @click="previewPaneOpen ? closeProposalPreview() : showProposalPreview()" :aria-expanded="previewPaneOpen" aria-controls="curriculum-vitae-preview-panel" x-text="previewPaneOpen ? 'Hide preview' : 'Preview paper'"></button>
        <button type="button" data-proposal-preview-toggle @click="showCurriculumVitaePackagePreview()" aria-controls="curriculum-vitae-preview-panel">Review team CVs</button>
    </div>
    <p class="proposal-writing-hint">Every member’s CV saves automatically. The side preview follows the member you are editing.</p>
</div>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views/components/curriculum-vitae-writing-toolbar.blade.php ENDPATH**/ ?>