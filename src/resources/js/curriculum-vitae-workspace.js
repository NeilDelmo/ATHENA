import { proposalPaperPreviewWorkspace } from './proposal-paper-workspace';

export function curriculumVitaePaperWorkspace() {
    const previewWorkspace = proposalPaperPreviewWorkspace();

    return {
        ...previewWorkspace,
        previewViewingCvPackage: false,

        focusCurriculumVitaePreview() {
            this.previewFocusSection = `cv-person-${this.activeCvPersonIndex + 1}`;
            if (!this.previewPaneOpen || this.previewViewingCvPackage) return;

            const previewDocument = this.$refs.previewFrame?.contentDocument;
            previewDocument?.querySelector(`[data-proposal-preview-section="${this.previewFocusSection}"]`)
                ?.scrollIntoView({ block: 'start' });
        },

        showProposalPreview() {
            this.previewViewingCvPackage = false;
            previewWorkspace.showProposalPreview.call(this);
            this.$nextTick(() => this.focusCurriculumVitaePreview());
        },

        showCurriculumVitaePackagePreview() {
            if (this.$el.closest('[data-revision-embedded]')) return;

            this.previewViewingCvPackage = true;
            previewWorkspace.showProposalPreview.call(this);
            if (!this.previewFullscreen) this.expandProposalPaperPreview();
            this.$nextTick(() => this.$refs.previewFrame?.contentWindow?.scrollTo(0, 0));
        },

        proposalPreviewLoaded() {
            previewWorkspace.proposalPreviewLoaded.call(this);
            this.$nextTick(() => {
                if (this.previewViewingCvPackage) {
                    this.$refs.previewFrame?.contentWindow?.scrollTo(0, 0);
                } else {
                    this.focusCurriculumVitaePreview();
                }
            });
        },

        returnToProposalPaperEditor() {
            this.previewViewingCvPackage = false;
            previewWorkspace.returnToProposalPaperEditor.call(this);
            this.$nextTick(() => this.focusCurriculumVitaePreview());
        },
    };
}
