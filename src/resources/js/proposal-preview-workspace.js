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
        previewFit: 'width',
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
            this.previewZoom = Math.max(50, Math.min(150, Math.round(Number(value) * 100) / 100 || 100));
            this.applyProposalPreviewZoom();
        },

        decreaseProposalPreviewZoom() {
            this.setProposalPreviewZoom(this.previewZoom - 10);
        },

        increaseProposalPreviewZoom() {
            this.setProposalPreviewZoom(this.previewZoom + 10);
        },

        fitProposalPreview(mode = 'page') {
            this.previewFit = mode;
            this.previewZoom = 100;
            this.applyProposalPreviewZoom();
        },

        bindProposalPreviewWheel(frame = this.$refs.previewFrame, isExpanded = () => this.previewFullscreen || (this.previewPaneOpen && this.$refs.previewPanel?.matches('.proposal-preview-pane')), onZoom = (amount) => this.setProposalPreviewZoom(this.previewZoom + amount), getZoom = () => this.previewZoom) {
            const previewDocument = frame?.contentDocument;
            if (typeof previewDocument?.addEventListener !== 'function' || frame.proposalPreviewWheelBinding?.document === previewDocument) return;
            this.unbindProposalPreviewWheel(frame);
            let wheelDelta = 0;
            let gestureZoom = null;
            let gestureScale = 1;
            const handler = (event) => {
                if (event.defaultPrevented || !event.ctrlKey || event.shiftKey || !event.deltaY || Math.abs(event.deltaX) > Math.abs(event.deltaY)) {
                    wheelDelta = 0;
                    return;
                }
                // The containing revision preview owns zoom, including PDFs inside its iframe.
                event.stopPropagation();
                if (!isExpanded()) return;
                event.preventDefault();
                if (gestureZoom !== null) return;
                if (event.deltaMode === 0 && Math.abs(event.deltaY) < 40) {
                    wheelDelta = 0;
                    onZoom(getZoom() * (Math.exp(-event.deltaY * .01) - 1));
                    return;
                }
                const units = event.deltaMode === 1 ? 16 : event.deltaMode === 2 ? frame.clientHeight || 800 : 1;
                if (Math.sign(wheelDelta) !== Math.sign(event.deltaY)) wheelDelta = 0;
                wheelDelta += event.deltaY * units;
                if (Math.abs(wheelDelta) < 40) return;
                onZoom(wheelDelta < 0 ? 10 : -10);
                wheelDelta = 0;
            };
            const start = (event) => {
                gestureZoom = null;
                if (event.defaultPrevented || !Number.isFinite(event.scale) || event.scale <= 0) return;
                event.stopPropagation();
                if (!isExpanded()) return;
                event.preventDefault();
                gestureZoom = getZoom();
                gestureScale = event.scale;
            };
            const change = (event) => {
                if (event.defaultPrevented || gestureZoom === null || !Number.isFinite(event.scale) || event.scale <= 0) return;
                event.stopPropagation();
                if (!isExpanded()) { gestureZoom = null; return; }
                event.preventDefault();
                onZoom(gestureZoom * event.scale / gestureScale - getZoom());
            };
            const end = (event) => {
                if (gestureZoom !== null && !event.defaultPrevented) {
                    event.stopPropagation();
                    event.preventDefault();
                }
                gestureZoom = null;
            };
            const handlers = { wheel: handler, gesturestart: start, gesturechange: change, gestureend: end };
            for (const [type, callback] of Object.entries(handlers)) {
                previewDocument.addEventListener(type, callback, { passive: false, capture: true });
            }
            frame.proposalPreviewWheelBinding = {
                document: previewDocument,
                cleanup() {
                    for (const [type, callback] of Object.entries(handlers)) previewDocument.removeEventListener(type, callback, { capture: true });
                },
            };
        },

        unbindProposalPreviewWheel(frame = this.$refs?.previewFrame) {
            frame?.proposalPreviewWheelBinding?.cleanup();
            if (frame) delete frame.proposalPreviewWheelBinding;
        },

        applyProposalPreviewZoom() {
            const frame = this.$refs.previewFrame;
            const previewDocument = frame?.contentDocument;
            const body = previewDocument?.body;
            const documentElement = previewDocument?.documentElement;

            if (!body || !documentElement) return;

            body.style.zoom = '1';
            body.style.overflowX = 'auto';
            body.style.marginInline = 'auto';
            documentElement.style.overflowX = 'auto';

            const viewportWidth = Number(frame.clientWidth || documentElement.clientWidth || 0);
            const paperWidth = Number(body.scrollWidth || body.offsetWidth || 0);
            const requestedScale = this.previewZoom / 100;
            let fitScale = viewportWidth > 0 && paperWidth > 0
                ? Math.min(1, viewportWidth / paperWidth)
                : 1;

            if (this.previewFit === 'page') {
                const sheet = body.querySelector?.('.gad-page, .assessment-page, .sheet, .paper, [class$="-sheet"]')
                    || body.querySelector?.('main, article');
                const sheetStyle = sheet && frame.contentWindow?.getComputedStyle(sheet);
                const bodyStyle = frame.contentWindow?.getComputedStyle(body);
                // Continuous forms can span many printed pages. Fit one physical sheet.
                const pageHeight = sheet?.matches?.('.gad-page, .assessment-page')
                    ? sheet.offsetHeight
                    : Number.parseFloat(sheetStyle?.minHeight) || sheet?.offsetHeight || body.scrollHeight;
                const spacing = (Number.parseFloat(bodyStyle?.paddingTop) || 0)
                    + (Number.parseFloat(bodyStyle?.paddingBottom) || 0)
                    + (Number.parseFloat(bodyStyle?.marginTop) || 0)
                    + (Number.parseFloat(bodyStyle?.marginBottom) || 0);
                const viewportHeight = Number(frame.clientHeight || documentElement.clientHeight || 0);
                if (viewportHeight > 0 && pageHeight > 0) {
                    fitScale = Math.min(fitScale, viewportHeight / (pageHeight + spacing));
                }
            }

            body.style.zoom = String(requestedScale * fitScale);
        },

        proposalPreviewLoaded() {
            this.previewReady = Boolean(this.previewHtml);
            this.bindProposalPreviewWheel();
            this.applyProposalPreviewZoom();
        },

        toggleProposalPreviewFullscreen() {
            this.stopProposalPreviewDrag();
            this.previewFullscreen = !this.previewFullscreen;
            this.previewFit = 'width';
            this.previewZoom = 100;
            if (this.previewFullscreen) {
                this.previewPaneOpen = true;
                this.previewTab = 'preview';
            }
            this.$nextTick?.(() => this.applyProposalPreviewZoom());
        },
    };
}
