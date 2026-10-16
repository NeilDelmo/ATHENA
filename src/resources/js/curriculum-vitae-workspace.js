import { proposalPaperPreviewWorkspace } from './proposal-paper-workspace';

export function curriculumVitaePaperWorkspace() {
    const previewWorkspace = proposalPaperPreviewWorkspace({ initiallyOpen: true });

    return {
        ...previewWorkspace,

        focusCurriculumVitaePreview() {
            this.previewFocusSection = `cv-person-${this.activeCvPersonIndex + 1}`;
            if (!this.previewPaneOpen) return;

            const previewDocument = this.$refs.previewFrame?.contentDocument;
            const section = previewDocument?.querySelector(`[data-proposal-preview-section="${this.previewFocusSection}"]`);
            if (section) {
                // Keep preview scrolling from moving the surrounding editor page.
                previewDocument.defaultView.scrollBy({ top: section.getBoundingClientRect().top, behavior: 'instant' });
            }
        },

        showProposalPreview() {
            previewWorkspace.showProposalPreview.call(this);
            this.$nextTick(() => this.focusCurriculumVitaePreview());
        },

        proposalPreviewLoaded() {
            previewWorkspace.proposalPreviewLoaded.call(this);
            this.$nextTick(() => this.focusCurriculumVitaePreview());
        },

        returnToProposalPaperEditor() {
            previewWorkspace.returnToProposalPaperEditor.call(this);
            this.$nextTick(() => this.focusCurriculumVitaePreview());
        },
    };
}
