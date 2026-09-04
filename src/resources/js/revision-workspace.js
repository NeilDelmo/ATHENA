import { activeProposalPaperAutoSave, finishProposalPaperAutoSave } from './proposal-paper-autosave.js';
import { focusRevisionTarget } from './revision-target-focus.js';

export function isEmbeddedRevisionEditor() {
    return Boolean(document.querySelector('[data-revision-embedded] [data-revision-editor-context]'));
}

export const REVISION_OPERATION_TIMEOUT_MS = 150_000;
export const REVISION_SUBMISSION_TIMEOUT_MS = 180_000;

export function createRevisionSubmissionWatchdog(timeoutMs, onTimeout) {
    let cancelled = false;
    const timeoutId = globalThis.setTimeout(() => {
        cancelled = true;
        onTimeout();
    }, timeoutMs);

    return {
        get cancelled() {
            return cancelled;
        },
        stop() {
            globalThis.clearTimeout(timeoutId);
        },
    };
}

async function waitForRevisionOperation(operation, timeoutMs, timeoutMessage) {
    let timeoutId;
    const timeout = new Promise((resolve, reject) => {
        timeoutId = globalThis.setTimeout(() => reject(new Error(timeoutMessage)), timeoutMs);
    });

    try {
        return await Promise.race([operation, timeout]);
    } finally {
        globalThis.clearTimeout(timeoutId);
    }
}

function revisionEditorError(editor, messageOrError) {
    const error = messageOrError instanceof Error ? messageOrError : new Error(messageOrError);
    error.revisionDocumentType = editor.documentType || null;

    return error;
}

// Saving source data invalidates generated files, so finish every save before preparing any file.
export async function prepareRevisionEditors(editors, {
    timeoutMs = REVISION_OPERATION_TIMEOUT_MS,
    onProgress = () => {},
} = {}) {
    for (const editor of editors) {
        if (typeof editor.validate === 'function' && !editor.validate()) {
            throw revisionEditorError(
                editor,
                editor.label + ' has highlighted required fields. Complete them before submitting.',
            );
        }
        onProgress({ phase: 'saving', label: editor.label });
        let saved;
        try {
            saved = await waitForRevisionOperation(
                editor.save(),
                timeoutMs,
                'Saving ' + editor.label + ' timed out. Check your connection and try submitting again.',
            );
        } catch (error) {
            throw revisionEditorError(editor, error);
        }
        if (!saved) {
            throw revisionEditorError(
                editor,
                'Could not save ' + editor.label + '. Check the message in its editor and try again.',
            );
        }
    }
    const files = [];
    for (const editor of editors) {
        onProgress({ phase: 'preparing', label: editor.label });
        let file;
        try {
            file = await waitForRevisionOperation(
                editor.prepare(),
                timeoutMs,
                'Preparing ' + editor.label + ' timed out. Your revision was not sent. Check your connection and try again.',
            );
        } catch (error) {
            throw revisionEditorError(editor, error);
        }
        if (!file?.filename || !file?.draft_id) {
            throw revisionEditorError(
                editor,
                'Could not prepare ' + editor.label + '. Check the highlighted fields or message in its editor and try again.',
            );
        }
        files.push(file);
    }
    return files;
}

export function embeddedRevisionFileSaved(payload) {
    const root = document.querySelector('[data-paper-editor]');
    const version = root?.querySelector('[name="document_version"]');
    if (version && Number.isInteger(payload.document_version)) version.value = String(payload.document_version);
}

export function revisionControlFingerprint(control) {
    if (!control) return '__missing__';
    const type = String(control.type || '').toLowerCase();
    if (type === 'checkbox' || type === 'radio') return control.checked ? 'checked:' + String(control.value ?? '') : 'unchecked';
    if (control.multiple && control.options) {
        return [...control.options].filter((option) => option.selected).map((option) => option.value).join('\u001f');
    }
    if (control.isContentEditable) return String(control.innerHTML ?? '');
    return String(control.value ?? control.textContent ?? '');
}

function revisionFieldPath(name) {
    return String(name || '').replace(/\[\]$/, '').match(/^[^[]+|\[([^\]]+)\]/g)
        ?.map((part) => part.replace(/^\[|\]$/g, '')) || [];
}

function revisionSourceValue(sourceData, name) {
    const path = revisionFieldPath(name);
    if (path.length === 0
        || ['_token', '_method', 'document_version', 'draft_version', 'save_as_draft'].includes(path[0])
        || path[0].endsWith('_present')) {
        return { found: false };
    }
    let value = sourceData;
    for (const key of path) {
        if (value === null || typeof value !== 'object' || !(key in value)) return { found: true, value: null };
        value = value[key];
    }
    return { found: true, value };
}

function revisionControls(documentRoot, targetId) {
    const target = targetId ? documentRoot.getElementById(targetId) : null;
    const form = documentRoot.querySelector('[data-paper-form]');
    let scope = target || form;
    if (!scope) return { controls: [], scope: null };
    let controls = [];
    if (scope.matches?.('input, textarea, select, [contenteditable="true"]')) controls.push(scope);
    controls.push(...(scope.querySelectorAll?.('input, textarea, select, [contenteditable="true"]') || []));
    if (target?._semanticEditor) controls.push(target._semanticEditor);
    if (target && controls.every((control) => !control.name)) {
        controls.push(...(target.parentElement?.querySelectorAll?.('input[name], textarea[name], select[name]') || []));
    }
    if (target && controls.length === 0) {
        scope = form;
        controls = [...(form?.querySelectorAll?.('input, textarea, select, [contenteditable="true"]') || [])];
    }
    return {
        controls: [...new Set(controls)].filter((control) => !control.disabled && control.type !== 'submit'),
        scope,
        wholeDocument: !target || scope === form,
    };
}

function revisionControlIsWorkspaceMetadata(control) {
    return control.type === 'hidden' && [
        'staff',
        'literature_research_history',
        'literature_citations',
    ].includes(control.name);
}

export function revisionSourceControlFingerprint(control, sourceValue) {
    const type = String(control.type || '').toLowerCase();
    if (type === 'checkbox' || type === 'radio') {
        const selected = Array.isArray(sourceValue)
            ? sourceValue.map(String).includes(String(control.value ?? ''))
            : String(sourceValue ?? '') === String(control.value ?? '');
        return selected ? 'checked:' + String(control.value ?? '') : 'unchecked';
    }
    if (control.multiple && control.options) {
        const selectedValues = Array.isArray(sourceValue) ? sourceValue.map(String) : [];
        return [...control.options].filter((option) => selectedValues.includes(String(option.value)))
            .map((option) => option.value).join('\u001f');
    }
    return String(sourceValue ?? '');
}

function revisionStructureFingerprint(controls, sourceData, useSource) {
    return ['entries', 'items', 'staff', 'custom_mooe_items', 'custom_co_items', 'people'].flatMap((key) => {
        if (!Array.isArray(sourceData[key])) return [];
        const count = useSource
            ? sourceData[key].length
            : new Set(controls.flatMap((control) => {
                const match = String(control.name || '').match(new RegExp('^' + key + '\\[(\\d+)\\]'));
                return match ? [match[1]] : [];
            })).size;
        return [['__structure__:' + key, '', String(count)]];
    });
}

export function revisionSourceFingerprint(documentRoot, targetId, sourceData) {
    const { controls, wholeDocument } = revisionControls(documentRoot, targetId);
    const fingerprints = controls.flatMap((control) => {
        if (revisionControlIsWorkspaceMetadata(control)) return [];
        const source = revisionSourceValue(sourceData, control.name);
        return source.found ? [[control.name || '', control.id || '', revisionSourceControlFingerprint(control, source.value)]] : [];
    });
    if (wholeDocument) fingerprints.push(...revisionStructureFingerprint(controls, sourceData, true));
    return fingerprints.length > 0 ? JSON.stringify(fingerprints) : '__missing__';
}

export function revisionCurrentSourceFingerprint(documentRoot, targetId, sourceData) {
    const { controls, wholeDocument } = revisionControls(documentRoot, targetId);
    const fingerprints = controls.flatMap((control) => {
        if (revisionControlIsWorkspaceMetadata(control)) return [];
        const source = revisionSourceValue(sourceData, control.name);
        return source.found ? [[control.name || '', control.id || '', revisionControlFingerprint(control)]] : [];
    });
    if (wholeDocument) fingerprints.push(...revisionStructureFingerprint(controls, sourceData, false));
    return fingerprints.length > 0 ? JSON.stringify(fingerprints) : '__missing__';
}

export function revisionTargetFingerprint(documentRoot, targetId) {
    const { controls, scope } = revisionControls(documentRoot, targetId);
    return controls.length > 0
        ? JSON.stringify(controls.map((control) => [
            control.name || '', control.id || '', revisionControlFingerprint(control),
        ]))
        : String(scope?.textContent || '').trim() || '__missing__';
}

function initializeEmbeddedEditor() {
    const context = document.querySelector('[data-revision-editor-context]');
    const root = document.querySelector('[data-paper-editor]');
    if (!context || !root) return;
    const targets = JSON.parse(context.dataset.targets || '{}');
    const originalSource = JSON.parse(context.dataset.originalSource || '{}');
    const state = () => window.Alpine?.$data(root);
    const hasOriginalSource = Object.keys(originalSource).length > 0;
    const baselineFingerprint = (targetId) => hasOriginalSource
        ? revisionSourceFingerprint(document, targetId, originalSource)
        : revisionTargetFingerprint(document, targetId);
    const currentFingerprint = (targetId) => hasOriginalSource
        ? revisionCurrentSourceFingerprint(document, targetId, originalSource)
        : revisionTargetFingerprint(document, targetId);
    const initialFingerprints = new Map([
        ['__document__', baselineFingerprint(null)],
        ...Object.entries(targets).map(([annotationId, annotation]) => [
            annotationId,
            baselineFingerprint(annotation.target),
        ]),
    ]);
    let notifyFrame = null;
    const modificationStates = () => Object.fromEntries([
        ['__document__', currentFingerprint(null) !== initialFingerprints.get('__document__')],
        ...Object.entries(targets).map(([annotationId, annotation]) => [
            annotationId,
            currentFingerprint(annotation.target) !== initialFingerprints.get(annotationId),
        ]),
    ]);

    window.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !document.querySelector('dialog[open], .swal2-container')) {
            window.frameElement?.closest('[data-revision-dialog]')?.close();
        }
    });

    window.athenaRevisionEditor = {
        topicId: context.dataset.topicId,
        draftId: Number(context.dataset.draftId),
        documentType: context.dataset.documentType,
        label: context.dataset.documentType.replaceAll('_', ' '),
        onChange: null,
        modificationStates,
        validate() {
            const editor = state();

            return Boolean(editor) && (typeof editor.validateForm !== 'function' || editor.validateForm());
        },
        focusInvalid() {
            return state()?.validateForm?.() ?? true;
        },
        async save() {
            return finishProposalPaperAutoSave({
                state: state(),
                form: root.querySelector('[data-paper-form]'),
                configuration: activeProposalPaperAutoSave(root.dataset),
            });
        },
        async pauseForUpload() {
            const editor = state();
            if (!editor) return () => {};
            const wasBlocked = editor.autoSaveBlocked;
            window.clearTimeout(editor.autoSaveTimer);
            editor.autoSaveBlocked = true;
            const deadline = Date.now() + 15000;
            while (editor.autoSaveInFlight && Date.now() < deadline) {
                await new Promise((resolve) => window.setTimeout(resolve, 50));
            }
            if (editor.autoSaveInFlight) {
                editor.autoSaveBlocked = wasBlocked;
                throw new Error('An editor is still saving. Wait a moment before submitting.');
            }
            return () => { editor.autoSaveBlocked = wasBlocked; };
        },
        async prepare() {
            const editor = state();
            if (!editor || editor.autoSaveBlocked) return null;
            return editor.downloadDocument();
        },
        async focus(annotationId) {
            const annotation = targets[annotationId];
            if (!annotation?.target) return false;
            const targetId = annotation.target;
            const editor = state();
            const entryId = Number(targetId.match(/^(?:objective|output|activity|work-plan-editor)-(\d+)$/)?.[1]);
            if (entryId && editor?.entries?.some((entry) => entry.id === entryId)) editor.expandedEntryId = entryId;
            const itemId = Number(targetId.match(/^expense-(?:category|account|sub-account|particulars|unit|quantity|unit-cost|details|purpose)-(\d+)$/)?.[1]);
            if (itemId && editor?.items?.some((item) => item.id === itemId)) editor.expandedItemId = itemId;
            await window.Alpine.nextTick();
            const target = document.getElementById(targetId);
            return target ? focusRevisionTarget(target, targetId, { withinDocument: true }) : false;
        },
        release() {
            root.dataset.paperSubmitting = 'true';
            const editor = state();
            if (editor) editor.submitting = true;
        },
    };
    const notifyChanges = () => {
        window.cancelAnimationFrame(notifyFrame);
        notifyFrame = window.requestAnimationFrame(() => window.athenaRevisionEditor?.onChange?.(modificationStates()));
    };
    root.addEventListener('input', notifyChanges);
    root.addEventListener('change', notifyChanges);
    new MutationObserver(notifyChanges).observe(root, { childList: true, subtree: true });
}

export function revisionEditorForFrame(frame, topicId) {
    try {
        const editor = frame.contentWindow?.athenaRevisionEditor;
        return editor && String(editor.topicId) === String(topicId)
            && editor.documentType === frame.closest('[data-revision-document]').dataset.revisionDocument
            ? editor : null;
    } catch {
        return null;
    }
}

export function revisionNoChangeResolution(card) {
    const selected = Boolean(card.querySelector('[data-revision-no-change]')?.checked);
    const explanation = card.querySelector('[data-revision-no-change-explanation]')?.value?.trim() || '';

    return { selected, complete: selected && explanation.length > 0 };
}

export function revealRevisionEditorFailure(exception, frames, topicId, revisionDialogs, requestFrame = (callback) => callback()) {
    const documentType = exception?.revisionDocumentType;
    if (!documentType) return false;

    const frame = frames.find((candidate) => (
        candidate.closest('[data-revision-document]')?.dataset.revisionDocument === documentType
    ));
    const card = frame?.closest('[data-revision-document]');
    if (!frame || !card) return false;

    revisionDialogs.open(card);
    requestFrame(() => revisionEditorForFrame(frame, topicId)?.focusInvalid?.());

    return true;
}

export function applyRevisionModificationStates(card, states = {}, replacementSelected = false) {
    const noChange = revisionNoChangeResolution(card);
    const reviewed = card.dataset?.revisionReviewed === 'true';
    let documentModified = replacementSelected || Boolean(states.__document__);
    card.querySelectorAll('[data-revision-modification-status]').forEach((status) => {
        const annotationId = status.dataset.annotationId;
        const modified = replacementSelected || Boolean(states[annotationId || '__document__']);
        status.dataset.modified = String(modified);
        status.dataset.addressed = String(noChange.complete || modified);
        status.dataset.reviewed = String(reviewed);
        status.textContent = noChange.selected
            ? (noChange.complete ? 'Explained — no file change' : 'Explanation required')
            : (replacementSelected ? 'Replacement selected' : (modified ? 'Changes detected' : (reviewed ? 'Reviewed — no change detected' : 'Not reviewed yet')));
        documentModified ||= modified;
    });
    const documentStatus = card.querySelector('[data-revision-document-state]');
    if (documentStatus) {
        documentStatus.dataset.modified = String(documentModified);
        documentStatus.dataset.addressed = String(noChange.complete || documentModified);
        documentStatus.dataset.reviewed = String(reviewed);
        documentStatus.textContent = noChange.selected
            ? (noChange.complete ? 'Explained — no file change' : 'Explanation required')
            : (replacementSelected ? 'Replacement selected' : (documentModified ? 'Changes detected' : (reviewed ? 'Reviewed — action required' : 'Review required')));
    }
    return documentModified;
}

export function revisionDocumentsWithoutResolution(form) {
    return [...form.querySelectorAll('[data-revision-document]')].filter((card) => {
        const status = card.querySelector('[data-revision-document-state]');
        return status && status.dataset.addressed !== 'true';
    });
}

export function initializeRevisionDialogs(form, topicId) {
    const cards = [...form.querySelectorAll('[data-revision-document]')]
        .filter((card) => card.querySelector('[data-revision-dialog]'));
    let activeCard = null;
    let previousOverflow = '';

    const editorFor = (card) => {
        const frame = card.querySelector('[data-revision-editor-frame]');
        return frame ? revisionEditorForFrame(frame, topicId) : null;
    };
    const pdfFor = (frame) => {
        try { return frame?.contentWindow?.athenaRevisionPdf; } catch { return null; }
    };
    const setFrameLoading = (card, frameName, loading) => {
        const indicator = card.querySelector(`[data-revision-${frameName}-loading]`);
        if (indicator) indicator.hidden = !loading;
    };
    const refreshModificationStates = (card, states = null) => {
        const replacementSelected = (card.querySelector('input[type="file"]')?.files?.length || 0) > 0;
        applyRevisionModificationStates(card, states || editorFor(card)?.modificationStates?.() || {}, replacementSelected);
    };
    const synchronizeNoChange = (card) => {
        const checkbox = card.querySelector('[data-revision-no-change]');
        const explanation = card.querySelector('[data-revision-no-change-explanation]');
        const details = card.querySelector('[data-revision-no-change-details]');
        const upload = card.querySelector('input[type="file"]');
        if (!checkbox || !explanation || !details || !upload) return;
        details.hidden = !checkbox.checked;
        explanation.required = checkbox.checked;
        upload.required = !checkbox.checked && !card.querySelector('[data-revision-editor-frame]');
        refreshModificationStates(card);
    };
    const selectFeedback = (card, syncPdf = true) => {
        const selector = card.querySelector('[data-revision-comment]');
        const option = selector?.selectedOptions[0];
        if (!option) return;
        card.querySelectorAll('[data-revision-comment-body]').forEach((body) => {
            body.hidden = body.dataset.revisionCommentBody !== option.value;
        });
        const frame = card.querySelector('[data-revision-pdf-frame]');
        const url = option.dataset.pdfUrl;
        frame.hidden = !url;
        card.querySelector('[data-revision-pdf-unavailable]').hidden = Boolean(url);
        if (url && syncPdf) {
            if (frame.getAttribute('src') !== url) {
                setFrameLoading(card, 'pdf', true);
                frame.src = url;
            }
            else pdfFor(frame)?.focus(option.dataset.annotationId);
        } else if (!url) {
            setFrameLoading(card, 'pdf', false);
        }
        if (option.dataset.annotationId) void editorFor(card)?.focus(option.dataset.annotationId);
    };
    const close = (card) => card.querySelector('[data-revision-dialog]').close();
    const open = (card) => {
        if (!card) return;
        if (activeCard && activeCard !== card) close(activeCard);
        const dialog = card.querySelector('[data-revision-dialog]');
        if (!dialog.open) {
            if (!activeCard) previousOverflow = document.body.style.overflow;
            document.body.style.overflow = 'hidden';
            activeCard = card;
            dialog.showModal();
        }
        card.dataset.revisionReviewed = 'true';
        refreshModificationStates(card);
        const index = cards.indexOf(card);
        card.querySelector('[data-revision-previous]').disabled = index === 0;
        card.querySelector('[data-revision-next]').disabled = index === cards.length - 1;
        selectFeedback(card);
    };

    cards.forEach((card) => {
        const dialog = card.querySelector('[data-revision-dialog]');
        dialog.addEventListener('close', () => {
            // Switching documents must not unmount or navigate either editor.
            if (activeCard === card) {
                document.body.style.overflow = previousOverflow;
                activeCard = null;
                card.querySelector('[data-revision-open]')?.focus({ preventScroll: true });
            }
        });
        const frame = card.querySelector('[data-revision-pdf-frame]');
        frame.addEventListener('load', () => {
            setFrameLoading(card, 'pdf', false);
            const pdf = pdfFor(frame);
            if (!pdf) return;
            pdf.onSelect = (id) => {
                const selector = card.querySelector('[data-revision-comment]');
                const option = [...(selector?.options || [])].find((item) => item.dataset.annotationId === String(id));
                if (!option) return;
                selector.value = option.value;
                selectFeedback(card, false);
            };
            const selected = card.querySelector('[data-revision-comment]')?.selectedOptions[0];
            pdf.focus(selected?.dataset.annotationId);
        });
        const editorFrame = card.querySelector('[data-revision-editor-frame]');
        const bindEditor = () => {
            const editor = editorFor(card);
            setFrameLoading(card, 'editor', false);
            if (editor) {
                editor.onChange = (states) => refreshModificationStates(card, states);
                refreshModificationStates(card);
            }
            if (dialog.open) selectFeedback(card);
        };
        editorFrame?.addEventListener('load', bindEditor);
        if (editorFrame && editorFor(card)) bindEditor();
        card.querySelector('[data-revision-comment]')?.addEventListener('change', () => selectFeedback(card));
        synchronizeNoChange(card);
    });

    form.addEventListener('click', (event) => {
        const button = event.target.closest('[data-revision-open], [data-revision-close], [data-revision-previous], [data-revision-next]');
        if (!button) return;
        const card = button.closest('[data-revision-document]');
        if (button.hasAttribute('data-revision-open')) open(card);
        else if (button.hasAttribute('data-revision-close')) close(card);
        else open(cards[cards.indexOf(card) + (button.hasAttribute('data-revision-next') ? 1 : -1)]);
    });
    form.addEventListener('change', (event) => {
        const card = event.target.closest?.('[data-revision-document]');
        if (!card) return;
        if (event.target.matches?.('[data-revision-no-change]')) {
            const upload = card.querySelector('input[type="file"]');
            if (event.target.checked && upload?.files?.length) upload.value = '';
            synchronizeNoChange(card);
            return;
        }
        if (!event.target.matches?.('input[type="file"]')) return;
        const noChange = card.querySelector('[data-revision-no-change]');
        if (event.target.files?.length && noChange?.checked) noChange.checked = false;
        synchronizeNoChange(card);
    });
    form.addEventListener('input', (event) => {
        if (!event.target.matches?.('[data-revision-no-change-explanation]')) return;
        const card = event.target.closest('[data-revision-document]');
        if (card) refreshModificationStates(card);
    });
    form.addEventListener('invalid', (event) => {
        const card = event.target.closest('[data-revision-document]');
        if (cards.includes(card)) {
            open(card);
            event.target.closest('details')?.setAttribute('open', '');
        }
    }, true);

    const requestedId = new URLSearchParams(window.location.search).get('revision_annotation');
    if (requestedId) {
        const card = cards.find((item) => [...(item.querySelector('[data-revision-comment]')?.options || [])]
            .some((option) => option.dataset.annotationId === requestedId));
        if (card) {
            const selector = card.querySelector('[data-revision-comment]');
            selector.value = [...selector.options].find((option) => option.dataset.annotationId === requestedId).value;
            open(card);
        }
    }
    return { open, close };
}

export default function initializeRevisionWorkspace(confirmSubmission, {
    submissionTimeoutMs = REVISION_SUBMISSION_TIMEOUT_MS,
} = {}) {
    initializeEmbeddedEditor();
    document.querySelectorAll('[data-revision-workspace]').forEach((form) => {
        if (form.dataset.revisionInitialized) return;
        form.dataset.revisionInitialized = 'true';
        const topicId = form.dataset.revisionWorkspace;
        const status = form.querySelector('[data-revision-submit-status]');
        const error = form.querySelector('[data-revision-submit-error]');
        const errorMessage = form.querySelector('[data-revision-submit-error-message]');
        const overlay = form.querySelector('[data-revision-submit-overlay]');
        const overlayTitle = form.querySelector('[data-revision-submit-title]');
        const submitButton = form.querySelector('[data-revision-submit-button]');
        const submitButtonSpinner = form.querySelector('[data-revision-submit-button-spinner]');
        const submitButtonLabel = form.querySelector('[data-revision-submit-button-label]');
        const frames = [...form.querySelectorAll('[data-revision-editor-frame]')];
        let activeSubmissionAttempt = null;
        const showSubmissionProgress = (title, message, buttonLabel) => {
            const shouldFocusOverlay = overlay.classList.contains('hidden');
            overlayTitle.textContent = title;
            status.textContent = message;
            overlay.classList.remove('hidden');
            overlay.classList.add('flex');
            overlay.setAttribute('aria-hidden', 'false');
            submitButton.disabled = true;
            submitButtonSpinner.hidden = false;
            submitButtonLabel.textContent = buttonLabel;
            if (shouldFocusOverlay) overlay.focus({ preventScroll: true });
        };
        const hideSubmissionProgress = () => {
            overlay.classList.add('hidden');
            overlay.classList.remove('flex');
            overlay.setAttribute('aria-hidden', 'true');
            submitButton.disabled = false;
            submitButtonSpinner.hidden = true;
            submitButtonLabel.textContent = 'Submit revision';
        };
        const showSubmissionError = (message) => {
            errorMessage.textContent = message;
            error.hidden = false;
        };
        const lockSubmission = () => {
            if (activeSubmissionAttempt || form.dataset.revisionSubmitting === 'true') return null;
            const attempt = { cancelled: false, watchdog: null };
            activeSubmissionAttempt = attempt;
            form.dataset.revisionSubmitting = 'true';
            form.setAttribute('aria-busy', 'true');
            submitButton.disabled = true;
            submitButtonSpinner.hidden = false;
            submitButtonLabel.textContent = 'Checking revision…';
            return attempt;
        };
        const unlockSubmission = (attempt) => {
            if (activeSubmissionAttempt !== attempt) return;
            attempt.watchdog?.stop();
            activeSubmissionAttempt = null;
            delete form.dataset.revisionSubmitting;
            form.removeAttribute('aria-busy');
            hideSubmissionProgress();
        };
        const waitForSubmissionCue = () => new Promise((resolve) => {
            const requestFrame = window.requestAnimationFrame
                ? (callback) => window.requestAnimationFrame(callback)
                : (callback) => window.setTimeout(callback, 0);
            requestFrame(() => requestFrame(resolve));
        });

        frames.forEach((frame) => {
            const card = frame.closest('[data-revision-document]');
            // In-page editors produce their own attachment; uploads remain an explicit alternative.
            card.querySelector('input[type="file"]').required = false;
            const report = () => {
                const editor = revisionEditorForFrame(frame, topicId);
                const message = card.querySelector('[data-revision-editor-status]');
                message.textContent = editor
                    ? 'Changes save as you type. Submit revision generates and attaches the updated PDF.'
                    : 'Editor could not load. Reload this page, or upload a replacement file.';
                if (editor) {
                    form.elements.revision_draft_id.value = String(editor.draftId);

                }
            };
            frame.addEventListener('load', report);
            if (revisionEditorForFrame(frame, topicId)) report();
        });

        const revisionDialogs = initializeRevisionDialogs(form, topicId);

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const attempt = lockSubmission();
            if (!attempt) return;
            let submitted = false;
            const resumeEditors = [];
            error.hidden = true;
            try {
                await waitForSubmissionCue();
                if (!form.checkValidity()) {
                    const invalidControl = form.querySelector(':invalid');
                    invalidControl?.closest('details')?.setAttribute('open', '');
                    showSubmissionError('Complete the highlighted proposal details before submitting this revision.');
                    form.reportValidity();
                    invalidControl?.focus({ preventScroll: true });
                    return;
                }
                const unresolvedDocuments = revisionDocumentsWithoutResolution(form);
                if (unresolvedDocuments.length > 0) {
                    const firstDocument = unresolvedDocuments[0];
                    showSubmissionError('Address every file requested by the Research Head before submitting. '
                        + firstDocument.dataset.revisionLabel + ' still needs a revision, replacement, or no-change explanation.');
                    const dialogError = firstDocument.querySelector('[data-revision-dialog-submit-error]');
                    if (dialogError) dialogError.hidden = false;
                    revisionDialogs.open(firstDocument);
                    return;
                }
                if (!await confirmSubmission({
                    title: form.dataset.confirmTitle,
                    text: form.dataset.confirmText,
                    confirmButtonText: form.dataset.confirmButton,
                    icon: 'question',
                })) return;
                showSubmissionProgress(
                    'Preparing your revision',
                    'Saving your edits and generating the requested PDFs…',
                    'Preparing revision…',
                );
                attempt.watchdog = createRevisionSubmissionWatchdog(submissionTimeoutMs, () => {
                    if (activeSubmissionAttempt !== attempt) return;
                    attempt.cancelled = true;
                    resumeEditors.splice(0).forEach((resume) => resume());
                    showSubmissionError('Preparing this revision took too long. It was not sent. Reload the page and try again.');
                    unlockSubmission(attempt);
                    error.scrollIntoView({ behavior: 'smooth', block: 'center' });
                });
                for (const frame of frames) {
                    const card = frame.closest('[data-revision-document]');
                    if (revisionNoChangeResolution(card).selected) continue;
                    const editor = revisionEditorForFrame(frame, topicId);
                    if (card.querySelector('input[type="file"]').files.length > 0 && editor) {
                        resumeEditors.push(await editor.pauseForUpload());
                        if (attempt.cancelled) return;
                    }
                }
                const editors = frames.flatMap((frame) => {
                    const card = frame.closest('[data-revision-document]');
                    if (revisionNoChangeResolution(card).selected) return [];
                    if (card.querySelector('input[type="file"]').files.length > 0) return [];
                    const editor = revisionEditorForFrame(frame, topicId);
                    if (!editor) {
                        throw new Error('The ' + card.dataset.revisionLabel + ' editor has not loaded. Wait for it to load, or upload a replacement.');
                    }
                    return [{ ...editor, label: card.dataset.revisionLabel }];
                });
                const files = await prepareRevisionEditors(editors, {
                    onProgress: ({ phase, label }) => showSubmissionProgress(
                        'Preparing your revision',
                        (phase === 'saving' ? 'Saving ' : 'Generating PDF for ') + label + '…',
                        'Preparing revision…',
                    ),
                });
                if (attempt.cancelled) return;
                if (files.length) form.elements.revision_draft_id.value = String(files[0].draft_id);
                // Only the final PATCH sends this revision to the Research Head.
                frames.forEach((frame) => revisionEditorForFrame(frame, topicId)?.release());
                showSubmissionProgress(
                    'Sending your revision',
                    'The files are ready. Sending the new proposal version to the Research Head…',
                    'Sending revision…',
                );
                if (attempt.cancelled) return;
                attempt.watchdog.stop();
                HTMLFormElement.prototype.submit.call(form);
                submitted = true;
            } catch (exception) {
                if (activeSubmissionAttempt === attempt) {
                    showSubmissionError(exception instanceof Error ? exception.message : 'Revision could not be prepared. Your edits remain here.');
                    hideSubmissionProgress();
                    const revealedEditor = revealRevisionEditorFailure(
                        exception,
                        frames,
                        topicId,
                        revisionDialogs,
                        (callback) => window.requestAnimationFrame
                            ? window.requestAnimationFrame(callback)
                            : window.setTimeout(callback, 0),
                    );
                    if (!revealedEditor) error.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            } finally {
                if (!submitted) {
                    resumeEditors.forEach((resume) => resume());
                    unlockSubmission(attempt);
                }
            }
        });
    });
}
