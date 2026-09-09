export function proposalPreviewWorkspace() {
    return {
        previewPaneOpen: true,
        previewTab: 'edit',
        previewFullscreen: false,
        previewZoom: 100,
        previewStale: false,
        previewRevision: 0,

        showProposalPreview() {
            this.previewPaneOpen = true;
            this.previewTab = 'preview';
            if (!this.previewHtml && !this.previewLoading) this.generatePreview();
        },

        markProposalPreviewStale() {
            this.previewRevision += 1;
            if (this.previewHtml) this.previewStale = true;
        },

        setProposalPreviewZoom(value) {
            this.previewZoom = Math.max(50, Math.min(150, Number(value) || 100));
            this.applyProposalPreviewZoom();
        },

        applyProposalPreviewZoom() {
            const body = this.$refs.previewFrame?.contentDocument?.body;
            if (body) body.style.zoom = String(this.previewZoom / 100);
        },

        proposalPreviewLoaded() {
            this.previewReady = Boolean(this.previewHtml);
            this.applyProposalPreviewZoom();
        },

        toggleProposalPreviewFullscreen() {
            this.previewFullscreen = !this.previewFullscreen;
            if (this.previewFullscreen) {
                this.previewPaneOpen = true;
                this.previewTab = 'preview';
            }
        },
    };
}
