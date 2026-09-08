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

export function pinRectangle(clientX, clientY, bounds) {
    return {
        x: clamp((clientX - bounds.left) / bounds.width, 0, 0.999),
        y: clamp((clientY - bounds.top) / bounds.height, 0, 0.999),
        width: 0.001,
        height: 0.001,
    };
}

export function commentComposerPosition(anchor, viewport, height = 300) {
    const width = Math.min(352, viewport.width - 24);
    const maxHeight = viewport.width < 768 ? viewport.height * 0.55 : viewport.height - 24;
    const visibleHeight = Math.min(height, maxHeight);
    if (viewport.width < 768) {
        return { left: 12, top: Math.max(12, viewport.height - visibleHeight - 12), width, maxHeight };
    }
    const rightFits = anchor.right + width + 12 <= viewport.width - 12;
    const leftFits = anchor.left - width - 12 >= 12;
    const left = rightFits ? anchor.right + 12 : (leftFits ? anchor.left - width - 12 : anchor.left);
    const top = rightFits || leftFits ? anchor.top
        : (anchor.bottom + visibleHeight + 12 <= viewport.height ? anchor.bottom + 12 : anchor.top - visibleHeight - 12);
    return {
        left: clamp(left, 12, viewport.width - width - 12),
        top: clamp(top, 12, Math.max(12, viewport.height - visibleHeight - 12)),
        width,
        maxHeight,
    };
}

export function matchRevisionSection(sections, selection) {
    const scores = new Map();
    for (const section of sections) {
        if (Number(section.pageNumber) !== Number(selection.pageNumber)) continue;
        const overlap = selection.rectangles.reduce((sum, rectangle) => sum + intersectionArea(section, rectangle), 0);
        const previous = scores.get(section.id);
        scores.set(section.id, { section, area: (previous?.area || 0) + overlap });
    }
    return [...scores.values()].filter((item) => item.area > 0)
        .sort((first, second) => second.area - first.area)[0]?.section || null;
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
        let viewportChange = null;

        return {
            config: {},
            annotations: [],
            revisionCandidates: [],
            editorTargets: [],
            sections: [],
            draftSectionLabel: '',
            revisionUrl: '',
            isResearchHead: false,
            canAnnotate: false,
            mode: 'area',
            editingAnnotationId: null,
            commentMenuId: null,
            deletingAnnotationId: null,
            scale: 1.15,
            paperFocusOpen: false,
            loading: true,
            loadError: '',
            selectionToolbarVisible: false,
            pendingSelection: null,
            draftSelection: null,
            draftComment: '',
            activeReviewer: 'research_head',
            coEvaluatorName: '',
            confirmedCoEvaluatorName: '',
            draftFeedbackSource: 'research_head',
            draftCoEvaluatorName: '',
            draftEditorTarget: '',
            selectedAnnotationId: null,
            saving: false,
            saveError: '',

            get reviewerCommentCount() {
                return this.annotations.filter((item) => (item.feedbackSource || 'research_head') === this.activeReviewer).length;
            },

            get reviewerReady() {
                return this.activeReviewer !== 'co_evaluator' || (this.confirmedCoEvaluatorName !== '' && this.coEvaluatorName.trim() === this.confirmedCoEvaluatorName);
            },

            confirmReviewer() {
                if (this.saving || this.draftSelection || !this.coEvaluatorName.trim()) return;
                this.coEvaluatorName = this.coEvaluatorName.trim();
                this.confirmedCoEvaluatorName = this.coEvaluatorName;
                this.$nextTick(() => this.$refs.viewer?.focus({ preventScroll: true }));
            },

            editReviewerName() {
                if (this.saving || this.draftSelection) return;
                this.confirmedCoEvaluatorName = '';
                this.$nextTick(() => this.$refs.reviewerNameInput?.focus());
            },

            reviewerInitials(name) {
                return String(name || '').trim().split(/\s+/).slice(0, 2).map((word) => word[0] || '').join('').toUpperCase() || 'CE';
            },

            switchReviewer(source) {
                if (this.draftSelection || this.saving || !['research_head', 'co_evaluator'].includes(source)) return;
                this.activeReviewer = source;
                this.commentMenuId = null;
            },

            get modeInstruction() {
                if (!this.canAnnotate) return 'Select a comment in the sidebar to locate its highlight.';
                if (!this.reviewerReady) return 'Confirm the co-evaluator’s name in the sidebar to unlock highlighting.';

                if (this.draftSelection) return 'Finish or cancel your comment before marking another location.';
                return {
                    area: 'Draw a box around the table, image, or passage that needs revision.',
                    pin: 'Click a spot on the paper to leave a revision comment.',
                    text: 'Select the words that need revision to add a comment.',
                }[this.mode];
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
                this.coEvaluatorName = this.config.coEvaluatorName || this.annotations.findLast((item) => item.coEvaluatorName)?.coEvaluatorName || '';
                if (this.annotations.length && this.annotations.every((item) => item.feedbackSource === 'co_evaluator')) this.activeReviewer = 'co_evaluator';
                this.revisionCandidates = Array.isArray(this.config.revisionCandidates) ? this.config.revisionCandidates : [];
                this.editorTargets = Array.isArray(this.config.editorTargets) ? this.config.editorTargets : [];
                this.sections = Array.isArray(this.config.sections) ? this.config.sections : [];
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
                if (!this.config.fitWidth) {
                    this.$nextTick(() => {
                        resizeObserver = new ResizeObserver(() => {
                            const width = this.$refs.viewer.clientWidth;
                            if (width <= 0 || width === viewerWidth) return;
                            viewerWidth = width;
                            window.clearTimeout(resizeTimer);
                            resizeTimer = window.setTimeout(() => {
                                this.renderDocument().catch((error) => { this.loadError = error.message; });
                            }, 150);
                        });
                        resizeObserver.observe(this.$refs.viewer);
                    });
                }
                viewportChange = () => this.positionCommentComposer();
                window.visualViewport?.addEventListener('resize', viewportChange);
                window.visualViewport?.addEventListener('scroll', viewportChange);
                this.loadPdf();
            },

            destroy() {
                resizeObserver?.disconnect();
                window.visualViewport?.removeEventListener('resize', viewportChange);
                window.visualViewport?.removeEventListener('scroll', viewportChange);
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
                if (!this.canAnnotate || this.draftSelection || this.saving || !['text', 'area', 'pin'].includes(mode)) return;

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
                if (!pdfDocument || !this.$refs.viewer || this.$refs.viewer.clientWidth === 0) return;

                const activeRender = ++renderToken;
                const library = pdfJs || await loadPdfJs();
                if (activeRender !== renderToken) return;
                const viewer = this.$refs.viewer;
                viewerWidth = viewer.clientWidth;
                const viewerStyle = window.getComputedStyle(viewer);
                const availableWidth = viewerWidth
                    - (Number.parseFloat(viewerStyle?.paddingLeft) || 0)
                    - (Number.parseFloat(viewerStyle?.paddingRight) || 0);
                const nextPages = document.createDocumentFragment();
                const nextPageElements = new Map();

                for (let pageNumber = 1; pageNumber <= pdfDocument.numPages; pageNumber += 1) {
                    if (activeRender !== renderToken) return;

                    const page = await pdfDocument.getPage(pageNumber);
                    const baseViewport = page.getViewport({ scale: 1 });
                    const scale = pdfScaleToFit(baseViewport.width, availableWidth);
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

                    nextPages.append(pageElement);
                    nextPageElements.set(pageNumber, pageElement);
                }
                if (activeRender !== renderToken) return;
                const scrollTop = viewer.scrollTop;
                const scrollLeft = viewer.scrollLeft;
                viewer.replaceChildren(nextPages);
                pageElements = nextPageElements;
                viewer.scrollTop = scrollTop;
                viewer.scrollLeft = scrollLeft;
                pageElements.forEach((_page, pageNumber) => this.renderAnnotationsForPage(pageNumber));
                if (this.draftSelection) {
                    this.renderSelectionPreview(this.draftSelection);
                    this.$nextTick(() => this.positionCommentComposer());
                }
            },

            captureTextSelection() {
                if (!this.canAnnotate || !this.reviewerReady || this.draftSelection || this.saving || this.mode !== 'text') return;

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

                const draft = {
                    type: 'text',
                    pageNumber: Number(startPage.dataset.pageNumber),
                    selectedText: selection.toString().trim().slice(0, 5000),
                    rectangles,
                };
                selection.removeAllRanges();
                this.openCommentComposer(draft);
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
                    preview.dataset.feedbackSource = this.draftSelection ? this.draftFeedbackSource : this.activeReviewer;
                    if (selection.type === 'pin') {
                        preview.classList.add('pdf-annotation-pin');
                        preview.textContent = '+';
                    }
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
                if (!this.canAnnotate || !this.reviewerReady || this.draftSelection || this.saving || event.button !== 0) return;
                if (this.mode === 'pin') {
                    if (event.target.closest('.pdf-annotation-mark')) return;
                    event.preventDefault();
                    this.openCommentComposer({
                        type: 'pin',
                        pageNumber: Number(pageElement.dataset.pageNumber),
                        selectedText: '',
                        rectangles: [pinRectangle(event.clientX, event.clientY, pageElement.getBoundingClientRect())],
                    });
                    return;
                }
                if (this.mode !== 'area') return;
                if (event.target.closest('.pdf-annotation-mark')) return;

                event.preventDefault();
                window.getSelection()?.removeAllRanges();
                this.cancelPendingSelection();

                const bounds = pageElement.getBoundingClientRect();
                const startX = clamp(event.clientX - bounds.left, 0, bounds.width);
                const startY = clamp(event.clientY - bounds.top, 0, bounds.height);
                const preview = document.createElement('div');
                preview.className = 'pdf-annotation-area-preview';
                preview.dataset.feedbackSource = this.activeReviewer;
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

                this.openCommentComposer({
                    type: 'area',
                    pageNumber: Number(pageElement.dataset.pageNumber),
                    selectedText: '',
                    rectangles: [{
                        x: left / bounds.width,
                        y: top / bounds.height,
                        width: width / bounds.width,
                        height: height / bounds.height,
                    }],
                });
            },

            openCommentComposer(selection, annotation = null) {
                if (!annotation && !this.reviewerReady) return;
                if (this.saving || (this.draftSelection && this.draftComment.trim())) return;
                this.editingAnnotationId = annotation?.id ?? null;
                this.draftSelection = selection;
                this.draftComment = annotation?.comment || '';
                this.draftFeedbackSource = annotation?.feedbackSource || (annotation ? 'research_head' : this.activeReviewer);
                this.draftCoEvaluatorName = annotation?.coEvaluatorName || this.coEvaluatorName.trim();
                const section = matchRevisionSection(this.sections, selection);
                this.draftEditorTarget = annotation?.editorTarget || section?.id || '';
                this.draftSectionLabel = annotation?.editorTargetLabel || section?.label || '';
                this.saveError = '';
                this.commentMenuId = null;
                this.renderSelectionPreview(selection);
                this.$nextTick(() => {
                    this.positionCommentComposer();
                    this.$refs.commentInput?.focus({ preventScroll: true });
                });
            },

            positionCommentComposer() {
                if (!this.draftSelection || !this.$refs.commentComposer) return;
                const page = pageElements.get(Number(this.draftSelection.pageNumber));
                if (!page) return;
                const bounds = page.getBoundingClientRect();
                const rectangle = this.draftSelection.rectangles[0];
                const anchor = {
                    left: bounds.left + rectangle.x * bounds.width,
                    right: bounds.left + (rectangle.x + rectangle.width) * bounds.width,
                    top: bounds.top + rectangle.y * bounds.height,
                    bottom: bounds.top + (rectangle.y + rectangle.height) * bounds.height,
                };
                const viewport = window.visualViewport;
                const position = commentComposerPosition(anchor, {
                    width: viewport?.width || window.innerWidth,
                    height: viewport?.height || window.innerHeight,
                }, this.$refs.commentComposer.scrollHeight);
                Object.assign(this.$refs.commentComposer.style, {
                    left: (position.left + (viewport?.offsetLeft || 0)) + 'px',
                    top: (position.top + (viewport?.offsetTop || 0)) + 'px',
                    width: position.width + 'px',
                    maxHeight: position.maxHeight + 'px',
                });
            },

            editAnnotation(annotation) {
                if (!this.canAnnotate || !annotation.canEdit || annotation.state !== 'draft' || this.saving || this.draftSelection) return;
                this.jumpToAnnotation(annotation);
                this.openCommentComposer({
                    type: annotation.type,
                    pageNumber: annotation.pageNumber,
                    selectedText: annotation.selectedText,
                    rectangles: annotation.rectangles,
                }, annotation);
            },

            cancelDraft(force = false) {
                if (this.saving && !force) return;
                this.editingAnnotationId = null;
                this.draftSelection = null;
                this.draftComment = '';
                this.draftEditorTarget = '';
                this.saveError = '';
                this.clearSelectionPreview();
            },

            async saveAnnotation() {
                if (!this.draftSelection || !this.draftComment.trim() || this.saving) return;
                if (!this.canAnnotate) return;

                if (this.draftFeedbackSource === 'co_evaluator' && !this.draftCoEvaluatorName.trim()) {
                    this.saveError = 'Enter the co-evaluator’s name before adding their feedback.';
                    return;
                }
                this.saving = true;
                this.saveError = '';

                try {
                    const editingId = this.editingAnnotationId;
                    const url = editingId
                        ? this.config.updateUrlTemplate.replace('__ANNOTATION__', editingId)
                        : this.config.storeUrl;
                    const response = await fetch(url, {
                        method: editingId ? 'PATCH' : 'POST',
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
                            feedback_source: this.draftFeedbackSource,
                            co_evaluator_name: this.draftFeedbackSource === 'co_evaluator' ? this.draftCoEvaluatorName.trim() : null,
                            editor_target: this.draftEditorTarget === '__paper__'
                                ? null
                                : (this.draftEditorTarget || null),
                        }),
                    });
                    const payload = await response.json();

                    if (!response.ok) {
                        throw new Error(validationMessage(payload, 'The comment could not be saved.'));
                    }

                    if (editingId) {
                        this.annotations = this.annotations.map((item) => item.id === editingId ? payload : item);
                    } else {
                        if (this.draftFeedbackSource === 'co_evaluator') this.coEvaluatorName = this.draftCoEvaluatorName.trim();
                        this.annotations.push(payload);
                        this.adjustRevisionCandidate(1);
                    }
                    this.renderAnnotationsForPage(payload.pageNumber);
                    this.cancelDraft(true);
                    this.selectAnnotation(payload);
                } catch (error) {
                    this.saveError = error instanceof Error ? error.message : 'The comment could not be saved.';
                } finally {
                    this.saving = false;
                }
            },

            async deleteAnnotation(annotation) {
                if (!this.canAnnotate || !annotation.canEdit || annotation.state !== 'draft' || this.saving || this.deletingAnnotationId) return;
                this.commentMenuId = null;
                const confirmation = await window.Swal.fire({
                    icon: 'warning',
                    title: 'Delete this draft comment?',
                    text: 'Its highlight or pin will also be removed.',
                    showCancelButton: true,
                    confirmButtonText: 'Delete comment',
                    confirmButtonColor: '#dc2626',
                });
                if (!confirmation.isConfirmed) return;
                this.deletingAnnotationId = annotation.id;
                try {
                    const response = await fetch(this.config.destroyUrlTemplate.replace('__ANNOTATION__', annotation.id), {
                        method: 'DELETE',
                        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': this.config.csrfToken },
                    });
                    if (!response.ok) throw new Error('This comment could not be deleted. It may already have been sent.');
                    this.annotations = this.annotations.filter((item) => item.id !== annotation.id);
                    if (this.selectedAnnotationId === annotation.id) this.selectedAnnotationId = null;
                    if (this.editingAnnotationId === annotation.id) this.cancelDraft();
                    this.adjustRevisionCandidate(-1);
                    pageElements.forEach((_page, number) => this.renderAnnotationsForPage(number));
                } catch (error) {
                    await window.Swal.fire({ icon: 'error', title: 'Comment not deleted', text: error.message || 'Please try again.' });
                } finally {
                    this.deletingAnnotationId = null;
                }
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
                        annotation.rectangles.forEach((rectangle, rectangleIndex) => {
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
                            const number = this.annotations.findIndex((item) => item.id === annotation.id) + 1;
                            if (annotation.type === 'pin') {
                                mark.classList.add('pdf-annotation-pin');
                                mark.textContent = String(number);
                            } else if (rectangleIndex === 0) {
                                const badge = document.createElement('span');
                                badge.className = 'pdf-annotation-number';
                                badge.textContent = String(number);
                                mark.append(badge);
                            }
                            mark.dataset.feedbackSource = annotation.feedbackSource || 'research_head';
                            mark.title = (annotation.feedbackLabel || (annotation.feedbackSource === 'co_evaluator' ? 'Co-evaluator' : 'Research Head')) + ': ' + annotation.comment;
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
                if (!this.draftSelection) this.activeReviewer = annotation.feedbackSource || 'research_head';
                if (previouslySelected && Number(previouslySelected.pageNumber) !== Number(annotation.pageNumber)) {
                    this.renderAnnotationsForPage(previouslySelected.pageNumber);
                }
                this.renderAnnotationsForPage(annotation.pageNumber);
                if (this.config.fitWidth && notify) window.athenaRevisionPdf?.onSelect?.(annotation.id);
                if (!this.config.fitWidth && this.$el) {
                    this.$nextTick(() => {
                        const card = this.$el.querySelector('[data-comment-id="' + Number(annotation.id) + '"]');
                        card?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                    });
                }
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
