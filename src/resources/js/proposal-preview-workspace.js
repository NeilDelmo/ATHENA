export function proposalPreviewWorkspace() {
    return {
        previewPaneOpen: false,
        previewTab: 'edit',
        previewFullscreen: false,
        previewZoom: 100,
        previewStale: false,
        previewRevision: 0,

        showProposalPreview() {
            this.previewPaneOpen = true;
            this.previewTab = 'preview';
            if (!this.previewHtml && !this.previewLoading) this.generatePreview();
            this.$nextTick?.(() => this.applyProposalPreviewZoom());
        },

        closeProposalPreview() {
            this.previewPaneOpen = false;
            this.previewFullscreen = false;
            this.previewTab = 'edit';
        },

        markProposalPreviewStale() {
            this.previewRevision += 1;
            if (this.previewHtml) this.previewStale = true;
        },

        setProposalPreviewZoom(value) {
            this.previewZoom = Math.max(50, Math.min(100, Number(value) || 100));
            this.applyProposalPreviewZoom();
        },

        applyProposalPreviewZoom() {
            const frame = this.$refs.previewFrame;
            const previewDocument = frame?.contentDocument;
            const body = previewDocument?.body;
            const documentElement = previewDocument?.documentElement;

            if (!body || !documentElement) return;

            body.style.zoom = '1';
            body.style.overflowX = 'hidden';
            documentElement.style.overflowX = 'hidden';

            const viewportWidth = Number(documentElement.clientWidth || frame.clientWidth || 0);
            const paperWidth = Number(body.scrollWidth || body.offsetWidth || 0);
            const requestedScale = this.previewZoom / 100;
            const fitScale = viewportWidth > 0 && paperWidth > 0
                ? Math.min(1, viewportWidth / paperWidth)
                : 1;

            body.style.zoom = String(Math.min(requestedScale, fitScale));
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
            this.$nextTick?.(() => this.applyProposalPreviewZoom());
        },
    };
}
