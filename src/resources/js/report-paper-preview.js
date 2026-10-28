import { proposalPaperPreviewWorkspace } from './proposal-paper-workspace';
import { renderAssessmentPdf } from './proposal-assessment-preview';

export function reportPaperPreview({ previewUrl, title, readOnly = false, openOnLoad = false }) {
    return {
        ...proposalPaperPreviewWorkspace({ initiallyOpen: true }),
        previewTitle: title,
        previewHtml: '',
        previewLoading: false,
        previewReady: false,
        previewError: '',
        validationMessage: '',
        previewRequestId: 0,
        previewAbortController: null,
        previewForm: null,

        init() {
            this.previewForm = this.$el.querySelector('[data-report-preview-form]');
            this.initializeProposalPaperPreview();
            if (openOnLoad) this.$nextTick(() => this.showProposalPreview());
        },

        async generatePreview() {
            if (!this.previewPaneOpen || this.previewDisposed) return;
            this.previewAbortController?.abort();
            const controller = new AbortController();
            const requestId = ++this.previewRequestId;
            const revision = this.previewRevision;
            this.previewAbortController = controller;
            this.previewLoading = true;
            this.previewReady = false;
            this.previewError = '';

            try {
                const body = readOnly ? null : new FormData(this.previewForm);
                if (body) body.set('intent', 'preview');
                const response = await fetch(previewUrl, {
                    credentials: 'same-origin', signal: controller.signal, headers: { Accept: 'application/json' },
                    ...(body ? { method: 'POST', body } : {}),
                });
                if (!response.ok || response.redirected) {
                    const error = response.status === 422 ? Object.values((await response.json()).errors || {}).flat()[0] : null;
                    throw new Error(error || 'The report preview could not be loaded. Retry the preview.');
                }
                const type = response.headers.get('content-type') || '';
                const html = type.includes('application/pdf') ? await renderAssessmentPdf(await response.arrayBuffer())
                    : type.includes('text/html') ? await response.text() : null;
                if (html === null) throw new Error('The report preview format could not be read.');
                if (requestId !== this.previewRequestId || this.previewDisposed || !this.previewPaneOpen) return;
                this.previewStale = revision !== this.previewRevision;
                if (html === this.previewHtml) this.proposalPreviewLoaded();
                else this.previewHtml = html;
            } catch (error) {
                if (requestId === this.previewRequestId && error.name !== 'AbortError' && !this.previewDisposed) this.previewError = error.message;
            } finally {
                if (requestId === this.previewRequestId) {
                    this.previewLoading = false;
                    if (revision !== this.previewRevision) this.scheduleProposalPaperPreview(0);
                }
            }
        },

        printPreview() {
            if (!this.previewReady || this.previewStale || this.previewLoading) return;
            const frame = this.$refs.previewFrame;
            const body = frame?.contentDocument?.body;
            if (!body) return;
            const zoom = body.style.zoom;
            try {
                body.style.zoom = '1';
                frame.contentWindow.focus();
                frame.contentWindow.print();
            } finally {
                body.style.zoom = zoom;
            }
        },

        destroy() {
            this.previewAbortController?.abort();
            this.destroyProposalPaperPreview();
        },
    };
}
