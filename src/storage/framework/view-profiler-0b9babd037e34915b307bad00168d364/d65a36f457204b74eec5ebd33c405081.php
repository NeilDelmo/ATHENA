<?php if (isset($component)) { $__componentOriginal90e243942d22f6a4722cd614e5732fed = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal90e243942d22f6a4722cd614e5732fed = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-writing-toolbar','data' => ['toolbarId' => 'curriculum-vitae-tools','dataCurriculumVitaeWritingToolbar' => true,'ariaLabel' => 'Curriculum Vitae tools']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-writing-toolbar'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['toolbar-id' => 'curriculum-vitae-tools','data-curriculum-vitae-writing-toolbar' => true,'aria-label' => 'Curriculum Vitae tools']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

     <?php $__env->slot('context', null, []); ?> 
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
     <?php $__env->endSlot(); ?>

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
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\components\curriculum-vitae-writing-toolbar.blade.php ENDPATH**/ ?>