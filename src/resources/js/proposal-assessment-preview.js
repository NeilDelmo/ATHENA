import { proposalPaperPreviewWorkspace } from './proposal-paper-workspace';
import { loadPdfJs } from './pdf-annotation-workspace';

async function renderAssessmentPdf(contents) {
    const pdfJs = await loadPdfJs();
    const loadingTask = pdfJs.getDocument({ data: new Uint8Array(contents) });
    const pages = [];
    let pageSize = 'auto';

    try {
        const pdf = await loadingTask.promise;
        for (let pageNumber = 1; pageNumber <= pdf.numPages; pageNumber += 1) {
            const page = await pdf.getPage(pageNumber);
            const viewport = page.getViewport({ scale: 2 });
            if (pageNumber === 1) pageSize = `${viewport.width / 2}pt ${viewport.height / 2}pt`;
            const canvas = document.createElement('canvas');
            canvas.width = Math.ceil(viewport.width);
            canvas.height = Math.ceil(viewport.height);
            await page.render({ canvasContext: canvas.getContext('2d'), viewport }).promise;
            pages.push(`<section class="assessment-page" style="width:${viewport.width / 2}pt"><img src="${canvas.toDataURL('image/png')}" alt="Form page ${pageNumber}"></section>`);
            page.cleanup();
        }

        return `<!doctype html><html><head><meta charset="utf-8"><style>
            @page { size: ${pageSize}; margin: 0; }
            body { width: max-content; margin: 0 auto; background: #e5e7eb; }
            .assessment-page { margin: 0 auto 16px; background: white; }
            img { display: block; width: 100%; height: auto; }
            @media print { body { zoom: 1 !important; background: white; } .assessment-page { margin: 0; break-after: page; } .assessment-page:last-child { break-after: auto; } }
        </style></head><body>${pages.join('')}</body></html>`;
    } finally {
        await loadingTask.destroy();
    }
}

export function proposalAssessmentPreview({ initialForm = null } = {}) {
    return {
        ...proposalPaperPreviewWorkspace(),
        previewReadOnly: true,
        previewHtml: '',
        previewLoading: false,
        previewReady: false,
        previewError: '',
        validationMessage: '',
        previewUrl: '',
        previewForm: null,
        previewRequestId: 0,
        previewAbortController: null,
        previewPreviousOverflow: '',

        init() {
            if (initialForm) this.$nextTick(() => this.openAssessmentPreview(initialForm, this.$el.querySelector('[data-assessment-preview-trigger]')));
        },

        openAssessmentPreview(form, trigger = document.activeElement) {
            if (!this.previewPaneOpen) {
                this.previewReturnFocus = trigger;
                this.previewPreviousOverflow = document.body.style.overflow;
                document.body.style.overflow = 'hidden';
            }
            this.previewTitle = form.label;
            this.previewUrl = form.previewUrl;
            this.previewForm = form.requestForm || null;
            this.previewDownloadUrl = form.downloadUrl;
            this.previewZoom = 100;
            this.previewPaneOpen = true;
            this.previewFullscreen = true;
            this.previewFit = 'page';
            this.$nextTick(() => this.$refs.previewClose?.focus({ preventScroll: true }));
            this.generatePreview();
        },

        openPaperPreview(form, trigger = document.activeElement) {
            this.openAssessmentPreview({
                label: form.dataset.previewLabel,
                previewUrl: form.action,
                requestForm: form,
            }, trigger);
        },

        async generatePreview() {
            if (!this.previewPaneOpen || !this.previewUrl) return;
            this.previewAbortController?.abort();
            const requestId = ++this.previewRequestId;
            const controller = new AbortController();
            this.previewAbortController = controller;
            this.previewLoading = true;
            this.previewReady = false;
            this.previewHtml = '';
            this.previewError = '';

            try {
                const response = await fetch(this.previewUrl, {
                    credentials: 'same-origin', signal: controller.signal,
                    ...(this.previewForm ? { method: 'POST', body: new FormData(this.previewForm) } : {}),
                });
                if (!response.ok || response.redirected) throw new Error('Preview unavailable');
                const contentType = response.headers.get('content-type') || '';
                let html;
                if (contentType.includes('application/pdf')) {
                    html = await renderAssessmentPdf(await response.arrayBuffer());
                } else if (contentType.includes('text/html')) {
                    html = await response.text();
                } else {
                    throw new Error('Unsupported preview format');
                }
                if (requestId === this.previewRequestId && this.previewPaneOpen) this.previewHtml = html;
            } catch (error) {
                if (requestId === this.previewRequestId && error.name !== 'AbortError') {
                    console.error('Unable to preview proposal paper.', error);
                    this.previewError = 'The paper could not be loaded. Refresh the preview to try again.';
                }
            } finally {
                if (requestId === this.previewRequestId) this.previewLoading = false;
            }
        },

        closeProposalPreview() {
            this.previewRequestId += 1;
            this.previewAbortController?.abort();
            if (this.previewPaneOpen) document.body.style.overflow = this.previewPreviousOverflow;
            this.previewPaneOpen = false;
            this.previewFullscreen = false;
            this.previewLoading = false;
            this.previewReady = false;
            this.previewHtml = '';
            this.previewForm = null;
            this.$nextTick(() => this.previewReturnFocus?.focus?.({ preventScroll: true }));
        },

        returnToProposalPaperEditor() {
            this.closeProposalPreview();
        },

        printPreview() {
            if (!this.previewReady) return;
            const frame = this.$refs.previewFrame;
            if (!frame?.contentWindow || !frame.contentDocument?.body) return;
            const body = frame.contentDocument.body;
            const previousZoom = body.style.zoom;
            try {
                body.style.zoom = '1';
                frame.contentWindow.focus();
                frame.contentWindow.print();
            } finally {
                body.style.zoom = previousZoom;
            }
        },

        destroy() {
            this.closeProposalPreview();
            this.destroyProposalPaperPreview();
        },
    };
}
