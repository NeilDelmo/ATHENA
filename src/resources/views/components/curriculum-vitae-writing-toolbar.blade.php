<x-proposal-writing-toolbar toolbar-id="curriculum-vitae-tools" data-curriculum-vitae-writing-toolbar aria-label="Curriculum Vitae tools">
    <x-slot:context>
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
    </x-slot:context>

    <div class="proposal-writing-buttons mt-2" role="group" aria-label="CV editing and preview">
        <div class="proposal-writing-action-group">
            <button type="button" @click="toggleCurriculumVitaeMemberManager()" :aria-expanded="cvMemberManagerOpen" aria-controls="cv-member-manager">Add member</button>
        </div>
        <div class="proposal-writing-action-group" x-show="activeCvSection !== 'personal'" x-cloak>
            <button type="button" @click="addSectionRow(activeCvPersonIndex, activeCvSection)" x-text="curriculumVitaeSectionOptions().find(section => section.key === activeCvSection)?.addLabel || 'Add entry'"></button>
        </div>
        <div class="proposal-writing-action-group">
            <button type="button" data-proposal-preview-toggle @click="previewPaneOpen ? closeProposalPreview() : showProposalPreview()" :aria-expanded="previewPaneOpen" aria-controls="curriculum-vitae-preview-panel" x-text="previewPaneOpen ? 'Hide preview' : 'Preview paper'"></button>
        </div>
    </div>
    <p class="proposal-writing-hint">Choose a member and section to edit. Click the paper preview to enlarge it.</p>
</x-proposal-writing-toolbar>
