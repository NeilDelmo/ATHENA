let pdfJsPromise;

function loadPdfJs() {
    pdfJsPromise ??= Promise.all([
        import('pdfjs-dist'),
        import('pdfjs-dist/build/pdf.worker.mjs?url'),
    ]).then(([pdfJs, workerModule]) => {
        pdfJs.GlobalWorkerOptions.workerSrc = workerModule.default;

        return pdfJs;
    });

    return pdfJsPromise;
}

function clamp(value, minimum = 0, maximum = 1) {
    return Math.min(maximum, Math.max(minimum, value));
}

function normalizeRectangle(rectangle, pageBounds) {
    const left = clamp((rectangle.left - pageBounds.left) / pageBounds.width);
    const top = clamp((rectangle.top - pageBounds.top) / pageBounds.height);
    const right = clamp((rectangle.right - pageBounds.left) / pageBounds.width);
    const bottom = clamp((rectangle.bottom - pageBounds.top) / pageBounds.height);

    return {
        x: left,
        y: top,
        width: right - left,
        height: bottom - top,
    };
}

function validationMessage(payload, fallback) {
    const messages = Object.values(payload?.errors || {}).flat();

    return messages.length > 0 ? messages.join(' ') : (payload?.message || fallback);
}

export default function registerPdfAnnotationWorkspace(Alpine) {
    Alpine.data('pdfAnnotationWorkspace', () => {
        let pdfDocument = null;
        let pageElements = new Map();
        let areaPointer = null;
        let renderToken = 0;

        return {
            config: {},
            annotations: [],
            revisionCandidates: [],
            canAnnotate: false,
            requestRevisionUrl: '',
            mode: 'text',
            scale: 1.15,
            loading: true,
            loadError: '',
            selectionToolbarVisible: false,
            pendingSelection: null,
            draftSelection: null,
            draftComment: '',
            selectedAnnotationId: null,
            saving: false,
            saveError: '',

            get modeInstruction() {
                if (!this.canAnnotate) return 'Select a comment in the sidebar to locate its highlight.';

                return this.mode === 'area'
                    ? 'Drag a box around a table, image, or scanned passage.'
                    : 'Drag across text, then choose Highlight & comment.';
            },

            init() {
                try {
                    this.config = JSON.parse(this.$el.dataset.pdfAnnotationConfig || '{}');
                } catch {
                    this.loadError = 'The annotation workspace configuration is invalid.';
                    this.loading = false;

                    return;
                }

                this.annotations = Array.isArray(this.config.annotations) ? this.config.annotations : [];
                this.revisionCandidates = Array.isArray(this.config.revisionCandidates) ? this.config.revisionCandidates : [];
                this.canAnnotate = Boolean(this.config.canAnnotate);
                this.requestRevisionUrl = this.config.requestRevisionUrl || '';
                this.loadPdf();
            },

            setMode(mode) {
                if (!this.canAnnotate || !['text', 'area'].includes(mode)) return;

                this.mode = mode;
                this.cancelPendingSelection();
                this.cancelDraft();
                window.getSelection()?.removeAllRanges();
            },

            async loadPdf() {
                this.loading = true;
                this.loadError = '';

                try {
                    const pdfJs = await loadPdfJs();
                    pdfDocument = await pdfJs.getDocument({
                        url: this.config.pdfUrl,
                        withCredentials: true,
                    }).promise;
                    await this.renderDocument(pdfJs);
                } catch (error) {
                    this.loadError = error instanceof Error
                        ? error.message
                        : 'The submitted PDF could not be rendered.';
                } finally {
                    this.loading = false;
                }
            },

            async renderDocument(pdfJs = null) {
                if (!pdfDocument || !this.$refs.viewer) return;

                const activeRender = ++renderToken;
                const library = pdfJs || await loadPdfJs();
                this.$refs.viewer.replaceChildren();
                pageElements = new Map();

                for (let pageNumber = 1; pageNumber <= pdfDocument.numPages; pageNumber += 1) {
                    if (activeRender !== renderToken) return;

                    const page = await pdfDocument.getPage(pageNumber);
                    const viewport = page.getViewport({ scale: this.scale });
                    const pageElement = document.createElement('section');
                    pageElement.className = 'pdf-annotation-page';
                    pageElement.dataset.pageNumber = String(pageNumber);
                    pageElement.style.width = `${viewport.width}px`;
                    pageElement.style.height = `${viewport.height}px`;
                    pageElement.style.setProperty('--total-scale-factor', String(viewport.scale * viewport.userUnit));
                    pageElement.style.setProperty('--scale-round-x', '1px');
                    pageElement.style.setProperty('--scale-round-y', '1px');

                    const canvas = document.createElement('canvas');
                    const pixelRatio = window.devicePixelRatio || 1;
                    canvas.width = Math.floor(viewport.width * pixelRatio);
                    canvas.height = Math.floor(viewport.height * pixelRatio);
                    canvas.style.width = `${viewport.width}px`;
                    canvas.style.height = `${viewport.height}px`;
                    pageElement.append(canvas);

                    await page.render({
                        canvasContext: canvas.getContext('2d'),
                        viewport,
                        transform: pixelRatio === 1 ? null : [pixelRatio, 0, 0, pixelRatio, 0, 0],
                    }).promise;

                    const textLayerElement = document.createElement('div');
                    textLayerElement.className = 'textLayer';
                    pageElement.append(textLayerElement);
                    const textLayer = new library.TextLayer({
                        textContentSource: page.streamTextContent({
                            includeMarkedContent: true,
                            disableNormalization: true,
                        }),
                        container: textLayerElement,
                        viewport,
                    });
                    await textLayer.render();

                    const annotationLayer = document.createElement('div');
                    annotationLayer.className = 'pdf-annotation-overlay';
                    pageElement.append(annotationLayer);
                    pageElement.addEventListener('pointerdown', (event) => this.startAreaSelection(event, pageElement));

                    this.$refs.viewer.append(pageElement);
                    pageElements.set(pageNumber, pageElement);
                    this.renderAnnotationsForPage(pageNumber);
                }
            },

            async changeZoom(amount) {
                const nextScale = clamp(this.scale + amount, 0.7, 2);
                if (nextScale === this.scale) return;

                this.scale = Number(nextScale.toFixed(2));
                this.cancelPendingSelection();
                this.cancelDraft();
                await this.renderDocument();
            },

            captureTextSelection() {
                if (!this.canAnnotate || this.mode !== 'text') return;

                const selection = window.getSelection();
                if (!selection || selection.isCollapsed || selection.rangeCount === 0) return;

                const range = selection.getRangeAt(0);
                const startPage = range.startContainer.parentElement?.closest('[data-page-number]');
                const endPage = range.endContainer.parentElement?.closest('[data-page-number]');

                if (!startPage || startPage !== endPage) {
                    window.Swal?.fire({
                        icon: 'info',
                        title: 'Select one page at a time',
                        text: 'Create a separate revision comment for each PDF page.',
                    });
                    selection.removeAllRanges();

                    return;
                }

                const pageBounds = startPage.getBoundingClientRect();
                const rectangles = Array.from(range.getClientRects())
                    .filter((rectangle) => rectangle.width > 1 && rectangle.height > 1)
                    .filter((rectangle) => {
                        const centerX = rectangle.left + (rectangle.width / 2);
                        const centerY = rectangle.top + (rectangle.height / 2);

                        return centerX >= pageBounds.left && centerX <= pageBounds.right
                            && centerY >= pageBounds.top && centerY <= pageBounds.bottom;
                    })
                    .slice(0, 100)
                    .map((rectangle) => normalizeRectangle(rectangle, pageBounds))
                    .filter((rectangle) => rectangle.width > 0 && rectangle.height > 0);

                if (rectangles.length === 0) return;

                const lastRectangle = range.getBoundingClientRect();
                this.pendingSelection = {
                    type: 'text',
                    pageNumber: Number(startPage.dataset.pageNumber),
                    selectedText: selection.toString().trim().slice(0, 5000),
                    rectangles,
                };
                this.selectionToolbarVisible = true;
                this.$nextTick(() => {
                    const toolbar = this.$refs.selectionToolbar;
                    if (!toolbar) return;

                    const left = clamp(lastRectangle.left + (lastRectangle.width / 2), 120, window.innerWidth - 120);
                    const top = clamp(lastRectangle.bottom + 10, 12, window.innerHeight - 70);
                    toolbar.style.left = `${left}px`;
                    toolbar.style.top = `${top}px`;
                });
            },

            beginTextComment() {
                if (!this.pendingSelection) return;

                this.draftSelection = this.pendingSelection;
                this.pendingSelection = null;
                this.selectionToolbarVisible = false;
                window.getSelection()?.removeAllRanges();
                this.$nextTick(() => this.$refs.commentInput?.focus());
            },

            cancelPendingSelection() {
                this.pendingSelection = null;
                this.selectionToolbarVisible = false;
            },

            startAreaSelection(event, pageElement) {
                if (!this.canAnnotate || this.mode !== 'area' || event.button !== 0) return;
                if (event.target.closest('.pdf-annotation-mark')) return;

                event.preventDefault();
                window.getSelection()?.removeAllRanges();
                this.cancelPendingSelection();

                const bounds = pageElement.getBoundingClientRect();
                const startX = clamp(event.clientX - bounds.left, 0, bounds.width);
                const startY = clamp(event.clientY - bounds.top, 0, bounds.height);
                const preview = document.createElement('div');
                preview.className = 'pdf-annotation-area-preview';
                pageElement.append(preview);
                areaPointer = { pageElement, bounds, startX, startY, preview };

                const move = (moveEvent) => this.moveAreaSelection(moveEvent);
                const finish = (upEvent) => {
                    window.removeEventListener('pointermove', move);
                    window.removeEventListener('pointerup', finish);
                    this.finishAreaSelection(upEvent);
                };
                window.addEventListener('pointermove', move);
                window.addEventListener('pointerup', finish, { once: true });
            },

            moveAreaSelection(event) {
                if (!areaPointer) return;

                const currentX = clamp(event.clientX - areaPointer.bounds.left, 0, areaPointer.bounds.width);
                const currentY = clamp(event.clientY - areaPointer.bounds.top, 0, areaPointer.bounds.height);
                const left = Math.min(areaPointer.startX, currentX);
                const top = Math.min(areaPointer.startY, currentY);
                areaPointer.preview.style.left = `${left}px`;
                areaPointer.preview.style.top = `${top}px`;
                areaPointer.preview.style.width = `${Math.abs(currentX - areaPointer.startX)}px`;
                areaPointer.preview.style.height = `${Math.abs(currentY - areaPointer.startY)}px`;
            },

            finishAreaSelection(event) {
                if (!areaPointer) return;

                const { pageElement, bounds, startX, startY, preview } = areaPointer;
                const currentX = clamp(event.clientX - bounds.left, 0, bounds.width);
                const currentY = clamp(event.clientY - bounds.top, 0, bounds.height);
                const left = Math.min(startX, currentX);
                const top = Math.min(startY, currentY);
                const width = Math.abs(currentX - startX);
                const height = Math.abs(currentY - startY);
                preview.remove();
                areaPointer = null;

                if (width < 6 || height < 6) return;

                this.draftSelection = {
                    type: 'area',
                    pageNumber: Number(pageElement.dataset.pageNumber),
                    selectedText: '',
                    rectangles: [{
                        x: left / bounds.width,
                        y: top / bounds.height,
                        width: width / bounds.width,
                        height: height / bounds.height,
                    }],
                };
                this.$nextTick(() => this.$refs.commentInput?.focus());
            },

            cancelDraft() {
                this.draftSelection = null;
                this.draftComment = '';
                this.saveError = '';
            },

            async saveAnnotation() {
                if (!this.draftSelection || !this.draftComment.trim() || this.saving) return;

                this.saving = true;
                this.saveError = '';

                try {
                    const response = await fetch(this.config.storeUrl, {
                        method: 'POST',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': this.config.csrfToken,
                        },
                        body: JSON.stringify({
                            annotation_type: this.draftSelection.type,
                            page_number: this.draftSelection.pageNumber,
                            selected_text: this.draftSelection.selectedText || null,
                            rectangles: this.draftSelection.rectangles,
                            comment: this.draftComment.trim(),
                        }),
                    });
                    const payload = await response.json();

                    if (!response.ok) {
                        throw new Error(validationMessage(payload, 'The highlight could not be saved.'));
                    }

                    this.annotations.push(payload);
                    this.adjustRevisionCandidate(1);
                    this.renderAnnotationsForPage(payload.pageNumber);
                    this.cancelDraft();
                    this.selectedAnnotationId = payload.id;
                } catch (error) {
                    this.saveError = error instanceof Error ? error.message : 'The highlight could not be saved.';
                } finally {
                    this.saving = false;
                }
            },

            async deleteAnnotation(annotation) {
                if (!this.canAnnotate || annotation.state !== 'draft') return;

                const confirmation = await window.Swal.fire({
                    icon: 'warning',
                    title: 'Delete this highlight?',
                    text: 'The comment will be removed from this draft revision request.',
                    showCancelButton: true,
                    confirmButtonText: 'Delete highlight',
                    confirmButtonColor: '#dc2626',
                });
                if (!confirmation.isConfirmed) return;

                const url = this.config.destroyUrlTemplate.replace('__ANNOTATION__', annotation.id);
                const response = await fetch(url, {
                    method: 'DELETE',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': this.config.csrfToken,
                    },
                });

                if (!response.ok) {
                    await window.Swal.fire({
                        icon: 'error',
                        title: 'Highlight not deleted',
                        text: 'The highlight may already be part of a sent revision request.',
                    });

                    return;
                }

                this.annotations = this.annotations.filter((item) => item.id !== annotation.id);
                this.adjustRevisionCandidate(-1);
                this.renderAnnotationsForPage(annotation.pageNumber);
            },

            adjustRevisionCandidate(amount) {
                const fileId = Number(this.config.fileId);
                const existing = this.revisionCandidates.find((candidate) => Number(candidate.fileId) === fileId);

                if (existing) {
                    existing.annotationCount += amount;
                    if (existing.annotationCount <= 0) {
                        this.revisionCandidates = this.revisionCandidates.filter((candidate) => Number(candidate.fileId) !== fileId);
                    }

                    return;
                }

                if (amount > 0) {
                    this.revisionCandidates.push({
                        fileId,
                        label: this.config.fileLabel,
                        annotationCount: amount,
                    });
                }
            },

            renderAnnotationsForPage(pageNumber) {
                const pageElement = pageElements.get(Number(pageNumber));
                const overlay = pageElement?.querySelector('.pdf-annotation-overlay');
                if (!overlay) return;

                overlay.replaceChildren();
                this.annotations
                    .filter((annotation) => Number(annotation.pageNumber) === Number(pageNumber))
                    .forEach((annotation) => {
                        annotation.rectangles.forEach((rectangle) => {
                            const mark = document.createElement('button');
                            mark.type = 'button';
                            mark.className = `pdf-annotation-mark pdf-annotation-mark-${annotation.state}`;
                            mark.style.left = `${rectangle.x * 100}%`;
                            mark.style.top = `${rectangle.y * 100}%`;
                            mark.style.width = `${rectangle.width * 100}%`;
                            mark.style.height = `${rectangle.height * 100}%`;
                            mark.title = annotation.comment;
                            mark.setAttribute('aria-label', `Revision comment on page ${annotation.pageNumber}: ${annotation.comment}`);
                            mark.addEventListener('click', () => this.selectAnnotation(annotation));
                            overlay.append(mark);
                        });
                    });
            },

            selectAnnotation(annotation) {
                this.selectedAnnotationId = annotation.id;
            },

            jumpToAnnotation(annotation) {
                this.selectAnnotation(annotation);
                pageElements.get(Number(annotation.pageNumber))?.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start',
                });
            },

            annotationStateLabel(annotation) {
                return {
                    draft: 'Draft',
                    requested: 'Revision requested',
                    resolved: 'Resolved by new version',
                }[annotation.state] || 'Comment';
            },
        };
    });
}
