export function clampProposalPreviewPosition({ left, top, width, height, viewportWidth, viewportHeight, margin = 8 }) {
    return {
        left: Math.min(Math.max(margin, left), Math.max(margin, viewportWidth - width - margin)),
        top: Math.min(Math.max(margin, top), Math.max(margin, viewportHeight - height - margin)),
    };
}

export function proposalPreviewWorkspace() {
    return {
        previewPaneOpen: false,
        previewTab: 'edit',
        previewFullscreen: false,
        previewDragging: false,
        previewZoom: 100,
        previewStale: false,
        previewRevision: 0,

        showProposalPreview() {
            this.previewPaneOpen = true;
            this.previewTab = 'preview';
            if ((!this.previewHtml || this.previewStale) && !this.previewLoading) this.generatePreview();
            this.$nextTick?.(() => this.applyProposalPreviewZoom());
        },

        closeProposalPreview() {
            this.stopProposalPreviewDrag();
            this.previewPaneOpen = false;
            this.previewFullscreen = false;
            this.previewTab = 'edit';
        },

        startProposalPreviewDrag(event) {
            if (
                this.previewFullscreen
                || window.innerWidth < 640
                || event.button !== 0
                || event.target?.closest?.('button, a, input, select, textarea, label')
            ) return;

            const panel = this.$refs.previewPanel;

            if (!panel) return;

            this.stopProposalPreviewDrag();

            const panelRect = panel.getBoundingClientRect();
            const startPointerX = event.clientX;
            const startPointerY = event.clientY;
            const startLeft = panelRect.left;
            const startTop = panelRect.top;

            Object.assign(panel.style, {
                bottom: 'auto',
                height: `${panelRect.height}px`,
                left: `${startLeft}px`,
                right: 'auto',
                top: `${startTop}px`,
                width: `${panelRect.width}px`,
            });

            const movePanel = (pointerEvent) => {
                const position = clampProposalPreviewPosition({
                    left: startLeft + pointerEvent.clientX - startPointerX,
                    top: startTop + pointerEvent.clientY - startPointerY,
                    width: panelRect.width,
                    height: panelRect.height,
                    viewportWidth: window.innerWidth,
                    viewportHeight: window.innerHeight,
                });

                panel.style.left = `${position.left}px`;
                panel.style.top = `${position.top}px`;
            };
            const finishDragging = () => this.stopProposalPreviewDrag();

            this.previewDragging = true;
            this.proposalPreviewDragCleanup = () => {
                window.removeEventListener('pointermove', movePanel);
                window.removeEventListener('pointerup', finishDragging);
                window.removeEventListener('pointercancel', finishDragging);
                this.previewDragging = false;
                this.proposalPreviewDragCleanup = null;
            };

            window.addEventListener('pointermove', movePanel);
            window.addEventListener('pointerup', finishDragging);
            window.addEventListener('pointercancel', finishDragging);
            event.preventDefault();
        },

        stopProposalPreviewDrag() {
            this.proposalPreviewDragCleanup?.();
        },

        constrainProposalPreviewToViewport() {
            const panel = this.$refs.previewPanel;

            if (!panel || this.previewFullscreen || window.innerWidth < 640 || !panel.style.left) return;

            const panelRect = panel.getBoundingClientRect();
            const position = clampProposalPreviewPosition({
                left: panelRect.left,
                top: panelRect.top,
                width: panelRect.width,
                height: panelRect.height,
                viewportWidth: window.innerWidth,
                viewportHeight: window.innerHeight,
            });

            panel.style.left = `${position.left}px`;
            panel.style.top = `${position.top}px`;
        },

        markProposalPreviewStale() {
            this.previewRevision += 1;
            if (this.previewHtml) this.previewStale = true;
        },

        setProposalPreviewZoom(value) {
            this.previewZoom = Math.max(50, Math.min(150, Number(value) || 100));
            this.applyProposalPreviewZoom();
        },

        decreaseProposalPreviewZoom() {
            this.setProposalPreviewZoom(this.previewZoom - 10);
        },

        increaseProposalPreviewZoom() {
            this.setProposalPreviewZoom(this.previewZoom + 10);
        },

        applyProposalPreviewZoom() {
            const frame = this.$refs.previewFrame;
            const previewDocument = frame?.contentDocument;
            const body = previewDocument?.body;
            const documentElement = previewDocument?.documentElement;

            if (!body || !documentElement) return;

            body.style.zoom = '1';
            body.style.overflowX = 'auto';
            documentElement.style.overflowX = 'auto';

            const viewportWidth = Number(documentElement.clientWidth || frame.clientWidth || 0);
            const paperWidth = Number(body.scrollWidth || body.offsetWidth || 0);
            const requestedScale = this.previewZoom / 100;
            const fitScale = viewportWidth > 0 && paperWidth > 0
                ? Math.min(1, viewportWidth / paperWidth)
                : 1;

            body.style.zoom = String(requestedScale * fitScale);
        },

        proposalPreviewLoaded() {
            this.previewReady = Boolean(this.previewHtml);
            this.applyProposalPreviewZoom();
        },

        toggleProposalPreviewFullscreen() {
            this.stopProposalPreviewDrag();
            this.previewFullscreen = !this.previewFullscreen;
            if (this.previewFullscreen) {
                this.previewPaneOpen = true;
                this.previewTab = 'preview';
            }
            this.$nextTick?.(() => this.applyProposalPreviewZoom());
        },
    };
}
