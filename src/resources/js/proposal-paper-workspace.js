import { proposalPreviewWorkspace } from './proposal-preview-workspace';

export function proposalPaperPreviewWorkspace({ initiallyOpen = false, workspaceSelector = '[data-proposal-paper-workspace]' } = {}) {
    const previewWorkspace = proposalPreviewWorkspace();
    return {
        ...previewWorkspace,
        previewRefreshTimer: null,
        previewDisposed: false,
        previewReturnFocus: null,
        previewFocusSection: '',
        previewReadOnly: false,
        previewTitle: 'Paper preview',
        previewDownloadUrl: '',
        writingToolbarOpen: true,

        closeWritingToolbar() {
            this.writingToolbarOpen = false;
            this.$nextTick(() => this.$el.closest(workspaceSelector)?.querySelector('[data-writing-toolbar-open]')?.focus({ preventScroll: true }));
        },

        showWritingToolbar() {
            this.writingToolbarOpen = true;
            this.$nextTick(() => this.$el.closest(workspaceSelector)?.querySelector('[data-writing-toolbar-close]')?.focus({ preventScroll: true }));
        },

        applyProposalPreviewZoom() {
            previewWorkspace.applyProposalPreviewZoom.call({
                $refs: this.$refs,
                previewZoom: this.previewFullscreen ? this.previewZoom : 100,
                previewFit: this.previewFullscreen ? this.previewFit : 'width',
            });
        },

        focusProposalPaperPreview(fieldId) {
            if (fieldId) this.previewFocusSection = fieldId;
            if (this.previewFullscreen || !this.previewPaneOpen) return;
            const previewDocument = this.$refs.previewFrame?.contentDocument;
            const section = [...(previewDocument?.querySelectorAll('[data-proposal-preview-section]') || [])]
                .find((element) => element.dataset.proposalPreviewSection === this.previewFocusSection);
            section?.scrollIntoView({ block: 'start' });
        },

        initializeProposalPaperPreview() {
            if (!this.$el.matches(workspaceSelector)) return;
            const toolbar = this.$el.querySelector('[data-proposal-workspace-toolbar]');
            if (toolbar) {
                if (this.$el.closest('[data-revision-embedded]')) this.$el.prepend(toolbar);
                this.previewToolbarObserver = new ResizeObserver(() => {
                    this.$el.style.setProperty('--proposal-toolbar-height', `${toolbar.getBoundingClientRect().height}px`);
                });
                this.previewToolbarObserver.observe(toolbar);
            }
            if (this.$el.closest('[data-revision-embedded]')) return;
            if (this.$refs.previewFrame) {
                this.previewFrameObserver = new ResizeObserver(() => {
                    if (this.previewReady) this.applyProposalPreviewZoom();
                });
                this.previewFrameObserver.observe(this.$refs.previewFrame);
            }
            this.previewPaneOpen = initiallyOpen && window.matchMedia('(min-width: 1024px)').matches;
            if (this.previewPaneOpen) this.scheduleProposalPaperPreview(250);
        },

        scheduleProposalPaperPreview(delay = 1200) {
            window.clearTimeout(this.previewRefreshTimer);
            if (this.previewDisposed || !this.previewPaneOpen || this.$el?.closest?.('[data-revision-embedded]')) return;

            this.previewRefreshTimer = window.setTimeout(() => {
                if (!this.previewLoading) this.generatePreview();
            }, delay);
        },

        markProposalPreviewStale() {
            this.previewRevision += 1;
            if (this.previewHtml) this.previewStale = true;
            this.scheduleProposalPaperPreview();
        },

        showProposalPreview() {
            if (this.$el?.closest?.('[data-revision-embedded]')) return;
            this.previewPaneOpen = true;
            if (!window.matchMedia('(min-width: 1024px)').matches) this.expandProposalPaperPreview();
            if ((!this.previewHtml || this.previewStale) && !this.previewLoading) this.generatePreview();
            this.$nextTick?.(() => {
                this.applyProposalPreviewZoom();
                this.focusProposalPaperPreview();
            });
        },

        closeProposalPreview() {
            window.clearTimeout(this.previewRefreshTimer);
            this.returnToProposalPaperEditor();
            this.previewPaneOpen = false;
        },

        expandProposalPaperPreview() {
            this.previewReturnFocus = document.activeElement;
            this.previewPaneOpen = true;
            this.previewFullscreen = true;
            this.previewFit = 'page';
            this.previewZoom = 100;
            this.$nextTick?.(() => {
                this.$refs.previewClose?.focus();
                this.applyProposalPreviewZoom();
            });
        },

        returnToProposalPaperEditor() {
            this.previewFullscreen = false;
            this.previewFit = 'width';
            this.previewZoom = 100;
            if (!window.matchMedia('(min-width: 1024px)').matches) this.previewPaneOpen = false;
            this.$nextTick?.(() => {
                this.applyProposalPreviewZoom();
                this.focusProposalPaperPreview();
                this.previewReturnFocus?.focus?.();
            });
        },

        resizeProposalPaperPreview() {
            if (!this.previewFullscreen && !window.matchMedia('(min-width: 1024px)').matches) this.previewPaneOpen = false;
            this.applyProposalPreviewZoom();
        },

        proposalPreviewLoaded() {
            this.previewReady = Boolean(this.previewHtml);
            this.bindProposalPreviewWheel();
            if (!this.previewFrameObserver && typeof ResizeObserver !== 'undefined') {
                this.previewFrameObserver = new ResizeObserver(() => {
                    if (this.previewReady) this.applyProposalPreviewZoom();
                });
                this.previewFrameObserver.observe(this.$refs.previewFrame);
            }
            this.$nextTick(() => {
                this.applyProposalPreviewZoom();
                this.focusProposalPaperPreview();
            });
            this.$refs.previewFrame?.contentDocument?.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && this.previewFullscreen) this.returnToProposalPaperEditor();
                if (event.key === 'Tab' && this.previewFullscreen) {
                    const controls = this.proposalPaperPreviewControls();
                    const frameIndex = controls.indexOf(this.$refs.previewFrame);
                    event.preventDefault();
                    controls[frameIndex + (event.shiftKey ? -1 : 1)]?.focus();
                }
            });
        },

        proposalPaperPreviewControls() {
            return [...this.$refs.previewPanel.querySelectorAll('button:not(:disabled), a[href], iframe')]
                .filter((control) => control.getClientRects().length);
        },

        trapProposalPaperPreviewFocus(event) {
            if (!this.previewFullscreen || event.key !== 'Tab') return;
            const controls = this.proposalPaperPreviewControls();
            const first = controls[0];
            const last = controls.at(-1);
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last?.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first?.focus();
            }
        },

        destroyProposalPaperPreview() {
            this.previewDisposed = true;
            this.previewToolbarObserver?.disconnect();
            this.previewFrameObserver?.disconnect();
            window.clearTimeout(this.previewRefreshTimer);
            this.stopProposalPreviewDrag();
        },
    };
}

