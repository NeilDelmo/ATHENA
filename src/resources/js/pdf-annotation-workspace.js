let pdfJsPromise;

function resolveApplicationAssetUrl(assetUrl) {
    if (!assetUrl?.startsWith('/')) return assetUrl;

    const applicationUrl = document.querySelector('meta[name="app-url"]')?.content;
    if (!applicationUrl) return assetUrl;

    const applicationLocation = new URL(applicationUrl, window.location.origin);
    const applicationPath = applicationLocation.pathname.replace(/\/$/, '');

    if (!applicationPath || assetUrl.startsWith(`${applicationPath}/`)) {
        return assetUrl;
    }

    return `${applicationPath}${assetUrl}`;
}

function loadPdfJs() {
    pdfJsPromise ??= Promise.all([
        import('pdfjs-dist'),
        import('pdfjs-dist/build/pdf.worker.mjs?url'),
    ]).then(([pdfJs, workerModule]) => {
        pdfJs.GlobalWorkerOptions.workerSrc = resolveApplicationAssetUrl(workerModule.default);

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

function rectangleArea(rectangle) {
    return rectangle.width * rectangle.height;
}

function intersectionArea(first, second) {
    const width = Math.max(0, Math.min(first.x + first.width, second.x + second.width) - Math.max(first.x, second.x));
    const height = Math.max(0, Math.min(first.y + first.height, second.y + second.height) - Math.max(first.y, second.y));

    return width * height;
}

export function consolidateTextRectangles(rectangles) {
    const uniqueRectangles = rectangles
        .filter((rectangle) => rectangle.width > 0 && rectangle.height > 0)
        .sort((first, second) => rectangleArea(first) - rectangleArea(second))
        .reduce((unique, rectangle) => {
            const area = rectangleArea(rectangle);
            const overlapsExistingRectangle = unique.some((existing) => {
                const smallerArea = Math.min(area, rectangleArea(existing));

                return smallerArea > 0 && intersectionArea(rectangle, existing) / smallerArea >= 0.9;
            });

            if (!overlapsExistingRectangle) unique.push(rectangle);

            return unique;
        }, [])
        .sort((first, second) => first.y - second.y || first.x - second.x);

    return uniqueRectangles.reduce((merged, rectangle) => {
        const previous = merged.at(-1);
        if (!previous) {
            merged.push({ ...rectangle });

            return merged;
        }

        const overlapTop = Math.max(previous.y, rectangle.y);
        const overlapBottom = Math.min(previous.y + previous.height, rectangle.y + rectangle.height);
        const verticalOverlap = Math.max(0, overlapBottom - overlapTop);
        const minimumHeight = Math.min(previous.height, rectangle.height);
        const horizontalGap = rectangle.x - (previous.x + previous.width);
        const sameLine = minimumHeight > 0 && verticalOverlap / minimumHeight >= 0.75;

        if (sameLine && horizontalGap >= -0.004 && horizontalGap <= 0.012) {
            const right = Math.max(previous.x + previous.width, rectangle.x + rectangle.width);
            const bottom = Math.max(previous.y + previous.height, rectangle.y + rectangle.height);
            previous.x = Math.min(previous.x, rectangle.x);
            previous.y = Math.min(previous.y, rectangle.y);
            previous.width = right - previous.x;
            previous.height = bottom - previous.y;
        } else {
            merged.push({ ...rectangle });
        }

        return merged;
    }, []);
}

function selectedTextClientRectangles(range) {
    const commonAncestor = range.commonAncestorContainer;
    const textNodes = [];

    if (commonAncestor.nodeType === Node.TEXT_NODE) {
        textNodes.push(commonAncestor);
    } else {
        const walker = document.createTreeWalker(commonAncestor, NodeFilter.SHOW_TEXT);
        let textNode = walker.nextNode();

        while (textNode) {
            if (range.intersectsNode(textNode)) textNodes.push(textNode);
            textNode = walker.nextNode();
        }
    }

    return textNodes.flatMap((textNode) => {
        const text = textNode.textContent || '';
        const startOffset = textNode === range.startContainer ? range.startOffset : 0;
        const endOffset = textNode === range.endContainer ? range.endOffset : text.length;

        if (endOffset <= startOffset || !text.slice(startOffset, endOffset).trim()) return [];

        const textRange = document.createRange();
        textRange.setStart(textNode, startOffset);
        textRange.setEnd(textNode, endOffset);

        return Array.from(textRange.getClientRects());
    });
}

function closestPageElement(node) {
    const element = node.nodeType === Node.ELEMENT_NODE ? node : node.parentElement;

    return element?.closest('[data-page-number]') || null;
}

function validationMessage(payload, fallback) {
    const messages = Object.values(payload?.errors || {}).flat();

    return messages.length > 0 ? messages.join(' ') : (payload?.message || fallback);
}

export function pdfScaleToFit(pageWidth, availableWidth) {
    return pageWidth > 0 && availableWidth > 0 ? availableWidth / pageWidth : 1;
}

export default function registerPdfAnnotationWorkspace(Alpine) {
    Alpine.data('pdfAnnotationWorkspace', () => {
        let pdfDocument = null;
        let pageElements = new Map();
        let areaPointer = null;
        let renderToken = 0;
        let paperFocusTrigger = null;
        let bodyOverflowBeforePaperFocus = '';
        let resizeObserver = null;
        let resizeTimer = null;
        let viewerWidth = 0;

        return {
            config: {},
            annotations: [],
            revisionCandidates: [],
            editorTargets: [],
            revisionUrl: '',
            isResearchHead: false,
            canAnnotate: false,
            mode: 'area',
            scale: 1.15,
            paperFocusOpen: false,
            loading: true,
            loadError: '',
            selectionToolbarVisible: false,
            pendingSelection: null,
            draftSelection: null,
            draftComment: '',
            draftEditorTarget: '',
            selectedAnnotationId: null,
            saving: false,
            saveError: '',

            get modeInstruction() {
                if (!this.canAnnotate) return 'Select a comment in the sidebar to locate its highlight.';

                return this.mode === 'area'
                    ? 'Drag a tight box around only the exact passage, table, or image that needs revision.'
                    : 'Drag across exact words. ATHENA will preview only the captured text before you comment.';
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
                this.editorTargets = Array.isArray(this.config.editorTargets) ? this.config.editorTargets : [];
                this.revisionUrl = String(this.config.revisionUrl || '');
                this.isResearchHead = Boolean(this.config.isResearchHead);
                this.canAnnotate = Boolean(this.config.canAnnotate);
                this.focusAnnotationId = this.readFocusAnnotationId();
                if (this.config.fitWidth) {
                    window.athenaRevisionPdf = {
                        onSelect: null,
                        focus: (id) => {
                            const annotation = this.annotations.find((item) => String(item.id) === String(id));
                            if (!annotation) return;
                            this.focusAnnotationId = Number(annotation.id);
                            if (pageElements.has(Number(annotation.pageNumber))) {
                                this.jumpToAnnotation(annotation, false);
                                this.focusAnnotationId = null;
                            }
                        },
                    };
                    this.$nextTick(() => {
                        resizeObserver = new ResizeObserver(() => {
                            const width = this.$refs.viewer.clientWidth;
                            if (width <= 0 || width === viewerWidth) return;
                            viewerWidth = width;
                            window.clearTimeout(resizeTimer);
                            resizeTimer = window.setTimeout(async () => {
                                try {
                                    await this.renderDocument();
                                    const selected = this.annotations.find((item) => Number(item.id) === Number(this.selectedAnnotationId));
                                    if (selected) this.jumpToAnnotation(selected, false);
                                } catch (error) {
                                    this.loadError = error instanceof Error ? error.message : 'The PDF could not be resized.';
                                }
                            }, 150);
                        });
                        resizeObserver.observe(this.$refs.viewer);
                    });
                    window.addEventListener('keydown', (event) => {
                        if (event.key === 'Escape') window.frameElement?.closest('[data-revision-dialog]')?.close();
                    });
                }
                this.loadPdf();
            },

            destroy() {
                resizeObserver?.disconnect();
                window.clearTimeout(resizeTimer);
                renderToken += 1;
            },

            openPaperFocus() {
                if (this.paperFocusOpen) return;

                paperFocusTrigger = document.activeElement;
                bodyOverflowBeforePaperFocus = document.body.style.overflow;
                document.body.style.overflow = 'hidden';
                this.paperFocusOpen = true;
                this.$nextTick(() => this.$refs.paperFocusClose?.focus());
            },

            closePaperFocus() {
                if (!this.paperFocusOpen) return;

                this.paperFocusOpen = false;
                document.body.style.overflow = bodyOverflowBeforePaperFocus;
                this.$nextTick(() => {
                    if (paperFocusTrigger instanceof HTMLElement) {
                        paperFocusTrigger.focus();
                    }
                });
            },

            readFocusAnnotationId() {
                const search = new URLSearchParams(window.location.search);
                const raw = search.get('annotation');
                const parsed = raw ? Number.parseInt(raw, 10) : Number.NaN;

                return Number.isFinite(parsed) ? parsed : null;
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
                    this.focusRequestedAnnotation();
                } catch (error) {
                    this.loadError = error instanceof Error
                        ? error.message
                        : 'The submitted PDF could not be rendered.';
                } finally {
                    this.loading = false;
                }
            },

            focusRequestedAnnotation() {
                if (this.focusAnnotationId === null) return;

                const target = this.annotations.find(
                    (annotation) => Number(annotation.id) === this.focusAnnotationId,
                );

                if (!target) {
                    this.focusAnnotationId = null;

                    return;
                }

                this.jumpToAnnotation(target, !this.config.fitWidth);
                this.focusAnnotationId = null;
            },

            async renderDocument(pdfJs = null) {
                if (!pdfDocument || !this.$refs.viewer || (this.config.fitWidth && this.$refs.viewer.clientWidth === 0)) return;

                const activeRender = ++renderToken;
                const library = pdfJs || await loadPdfJs();
                this.$refs.viewer.replaceChildren();
                pageElements = new Map();

                for (let pageNumber = 1; pageNumber <= pdfDocument.numPages; pageNumber += 1) {
                    if (activeRender !== renderToken) return;

                    const page = await pdfDocument.getPage(pageNumber);
                    const baseViewport = page.getViewport({ scale: 1 });
                    const viewerStyle = this.config.fitWidth ? window.getComputedStyle(this.$refs.viewer) : null;
                    const availableWidth = this.$refs.viewer.clientWidth
                        - (Number.parseFloat(viewerStyle?.paddingLeft) || 0)
                        - (Number.parseFloat(viewerStyle?.paddingRight) || 0);
                    const scale = this.config.fitWidth ? pdfScaleToFit(baseViewport.width, availableWidth) : this.scale;
                    const viewport = page.getViewport({ scale });
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
                    if (activeRender !== renderToken) return;

                    const annotationLayer = document.createElement('div');
                    annotationLayer.className = 'pdf-annotation-overlay';
                    pageElement.append(annotationLayer);
                    pageElement.addEventListener('pointerdown', (event) => this.startAreaSelection(event, pageElement));

                    this.$refs.viewer.append(pageElement);
                    pageElements.set(pageNumber, pageElement);
                    this.renderAnnotationsForPage(pageNumber);
                }
            },

            captureTextSelection() {
                if (!this.canAnnotate || this.mode !== 'text') return;

                const selection = window.getSelection();
                if (!selection || selection.isCollapsed || selection.rangeCount === 0) return;

                const range = selection.getRangeAt(0);
                const startPage = closestPageElement(range.startContainer);
                const endPage = closestPageElement(range.endContainer);

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
                const rectangles = consolidateTextRectangles(selectedTextClientRectangles(range)
                    .filter((rectangle) => rectangle.width > 1 && rectangle.height > 1)
                    .filter((rectangle) => {
                        const centerX = rectangle.left + (rectangle.width / 2);
                        const centerY = rectangle.top + (rectangle.height / 2);

                        return centerX >= pageBounds.left && centerX <= pageBounds.right
                            && centerY >= pageBounds.top && centerY <= pageBounds.bottom;
                    })
                    .map((rectangle) => normalizeRectangle(rectangle, pageBounds))
                    .filter((rectangle) => rectangle.width > 0 && rectangle.height > 0))
                    .slice(0, 100);

                if (rectangles.length === 0) return;

                const lastRectangle = range.getBoundingClientRect();
                this.clearSelectionPreview();
                this.pendingSelection = {
                    type: 'text',
                    pageNumber: Number(startPage.dataset.pageNumber),
                    selectedText: selection.toString().trim().slice(0, 5000),
                    rectangles,
                };
                this.renderSelectionPreview(this.pendingSelection);
                selection.removeAllRanges();
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
                this.$nextTick(() => this.$refs.commentInput?.focus());
            },

            cancelPendingSelection() {
                this.pendingSelection = null;
                this.selectionToolbarVisible = false;
                this.clearSelectionPreview();
            },

            renderSelectionPreview(selection) {
                this.clearSelectionPreview();

                const pageElement = pageElements.get(Number(selection?.pageNumber));
                const overlay = pageElement?.querySelector('.pdf-annotation-overlay');
                if (!overlay) return;

                selection.rectangles.forEach((rectangle) => {
                    const preview = document.createElement('span');
                    preview.className = 'pdf-annotation-pending-mark';
                    preview.style.left = `${rectangle.x * 100}%`;
                    preview.style.top = `${rectangle.y * 100}%`;
                    preview.style.width = `${rectangle.width * 100}%`;
                    preview.style.height = `${rectangle.height * 100}%`;
                    overlay.append(preview);
                });
            },

            clearSelectionPreview() {
                this.$el.querySelectorAll('.pdf-annotation-pending-mark').forEach((preview) => preview.remove());
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
                this.renderSelectionPreview(this.draftSelection);
                this.$nextTick(() => this.$refs.commentInput?.focus());
            },

            cancelDraft() {
                this.draftSelection = null;
                this.draftComment = '';
                this.draftEditorTarget = '';
                this.saveError = '';
                this.clearSelectionPreview();
            },

            async saveAnnotation() {
                if (!this.draftSelection || !this.draftComment.trim() || this.saving) return;
                if (this.editorTargets.length > 0 && !this.draftEditorTarget) return;

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
                            editor_target: this.draftEditorTarget === '__paper__'
                                ? null
                                : (this.draftEditorTarget || null),
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
                    title: 'Remove this highlight?',
                    text: 'This will also remove its comment from your draft revision request.',
                    showCancelButton: true,
                    confirmButtonText: 'Remove highlight',
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
                if (Number(this.selectedAnnotationId) === Number(annotation.id)) {
                    this.selectedAnnotationId = null;
                }
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
                            mark.dataset.annotationId = String(annotation.id);
                            mark.classList.toggle(
                                'pdf-annotation-mark-selected',
                                Number(this.selectedAnnotationId) === Number(annotation.id),
                            );
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

            selectAnnotation(annotation, notify = true) {
                const previouslySelected = this.annotations.find(
                    (item) => Number(item.id) === Number(this.selectedAnnotationId),
                );
                this.selectedAnnotationId = annotation.id;
                if (previouslySelected && Number(previouslySelected.pageNumber) !== Number(annotation.pageNumber)) {
                    this.renderAnnotationsForPage(previouslySelected.pageNumber);
                }
                this.renderAnnotationsForPage(annotation.pageNumber);
                if (this.config.fitWidth && notify) window.athenaRevisionPdf?.onSelect?.(annotation.id);
            },

            jumpToAnnotation(annotation, notify = true) {
                this.selectAnnotation(annotation, notify);
                const pageElement = pageElements.get(Number(annotation.pageNumber));
                const mark = pageElement?.querySelector(`[data-annotation-id="${Number(annotation.id)}"]`);
                const target = mark || pageElement;

                if (this.config.fitWidth && target) {
                    const viewer = this.$refs.viewer;
                    viewer.scrollTo({
                        top: viewer.scrollTop + target.getBoundingClientRect().top - viewer.getBoundingClientRect().top
                            - (mark ? viewer.clientHeight / 2 : 16),
                        behavior: 'smooth',
                    });
                } else {
                    target?.scrollIntoView({
                        behavior: 'smooth',
                        block: mark ? 'center' : 'start',
                        inline: 'center',
                    });
                    mark?.focus({ preventScroll: true });
                }
            },

            annotationStateLabel(annotation) {
                return {
                    draft: 'Draft',
                    requested: 'Revision requested',
                    resolved: 'Resolved by new version',
                }[annotation.state] || 'Comment';
            },

            annotationEditUrl(annotation) {
                if (!this.revisionUrl) return '#';

                const url = new URL(this.revisionUrl, window.location.origin);
                url.searchParams.set('revision_annotation', annotation.id);

                return url.toString();
            },
        };
    });
}
