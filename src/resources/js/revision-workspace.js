import { activeProposalPaperAutoSave, finishProposalPaperAutoSave } from './proposal-paper-autosave.js';
import { findRevisionTarget, focusRevisionTarget } from './revision-target-focus.js';
import { proposalPreviewWorkspace } from './proposal-preview-workspace.js';
import { initializeCommentResponseLocations, normalizeLocationText, readPdfPassages } from './comment-response-location.js';

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

export async function previewRevisionEditor(editor) {
    if (!editor?.generatePreview) throw new Error('The revision editor is still loading. Try again in a moment.');
    if (editor.previewLoading) throw new Error('A preview is already being prepared. Try again when it finishes.');
    await editor.generatePreview();
    if (!editor.previewHtml) {
        throw new Error(editor.previewError || editor.validationMessage || 'The revised paper preview could not be generated.');
    }
    return editor.previewHtml;
}

export function fitRevisionPaperPreview(frame, zoom = 100, fit = 'width') {
    if (!frame.hasAttribute('srcdoc')) return;
    proposalPreviewWorkspace().applyProposalPreviewZoom.call({
        $refs: { previewFrame: frame }, previewZoom: zoom, previewFit: fit,
    });
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
    const target = targetId ? findRevisionTarget(documentRoot, targetId) : null;
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
        'methodology[specific_methods]',
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
    const rows = ['entries', 'items', 'staff', 'custom_mooe_items', 'custom_co_items', 'people', 'specific_method_objectives'].flatMap((key) => {
        if (!Array.isArray(sourceData[key])) return [];
        if (key === 'specific_method_objectives' && sourceData[key].length === 0) return [];
        const count = useSource
            ? sourceData[key].length
            : new Set(controls.flatMap((control) => {
                const match = String(control.name || '').match(new RegExp('^' + key + '\\[(\\d+)\\]'));
                return match ? [match[1]] : [];
            })).size;
        return [['__structure__:' + key, '', String(count)]];
    });
    const methodGroups = Array.isArray(sourceData.specific_method_objectives) ? sourceData.specific_method_objectives : [];
    methodGroups.forEach((group, index) => {
        const prefix = `specific_method_objectives[${index}][methods]`;
        const count = useSource ? (group.methods || []).length
            : new Set(controls.flatMap((control) => {
                if (!control.name?.startsWith(prefix + '[')) return [];
                return [control.name.slice(prefix.length).match(/^\[(\d+)\]/)?.[1]].filter(Boolean);
            })).size;
        rows.push(['__structure__:' + prefix, '', String(count)]);
    });
    return rows;
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

export function revisionChangedPassages(documentRoot, targetId, originalSource) {
    const { controls, scope, wholeDocument } = revisionControls(documentRoot, targetId);
    const paragraphs = (value) => {
        const template = documentRoot.createElement('template');
        template.innerHTML = String(value || '');
        template.content.querySelectorAll('br').forEach((element) => element.replaceWith('\n'));
        template.content.querySelectorAll('p, li, td, th, h1, h2, h3, h4, h5, h6').forEach((element) => element.append('\n\n'));
        return template.content.textContent.split(/\n\s*\n/).map((text) => text.trim()).filter(Boolean);
    };
    const labelText = (element) => {
        if (!element) return '';
        const label = element.cloneNode(true);
        label.querySelectorAll('input, textarea, select, [contenteditable]').forEach((control) => control.remove());
        return label.textContent.replace(/\s+/g, ' ').trim();
    };
    const changes = controls.flatMap((control) => {
        if (revisionControlIsWorkspaceMetadata(control) || ['hidden', 'file'].includes(control.type)) return [];
        const source = revisionSourceValue(originalSource, control.name);
        if (!source.found || revisionControlFingerprint(control) === revisionSourceControlFingerprint(control, source.value)) return [];
        const context = labelText(control.labels?.[0])
            || labelText((control.closest?.('[data-revision-section]') || scope)?.querySelector?.('legend, h3, h4'));
        if (['checkbox', 'radio'].includes(control.type)) {
            // Checked and unchecked options both remain printed in the generated paper.
            const text = labelText(control.labels?.[0]) || control.value;
            const heading = labelText(control.closest('fieldset')?.querySelector('legend'));
            return text ? [{ text, context: heading }] : [];
        }
        if (control.tagName === 'SELECT') {
            return [...control.selectedOptions].map((option) => ({ text: option.textContent.trim(), context }));
        }
        if (control.type === 'number') {
            const value = Number(control.value);
            return control.value ? [...new Set([control.value, value.toLocaleString('en-US'), value.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })])]
                .map((text) => ({ text, context })) : (context ? [{ text: context }] : []);
        }
        const previous = paragraphs(source.value).map(normalizeLocationText);
        const current = paragraphs(control.value);
        const changed = current.filter((text) => !previous.includes(normalizeLocationText(text)));
        if (changed.length) return changed;
        // Anchor a deletion to the paragraph now occupying that place, or the preceding one at the end.
        const removedIndex = previous.findIndex((text) => !current.some((paragraph) => normalizeLocationText(paragraph) === text));
        const surviving = current[Math.min(Math.max(0, removedIndex), current.length - 1)];
        return surviving ? [{ text: surviving, context }] : (context ? [{ text: context }] : []);
    });
    if (changes.length || !wholeDocument) return changes;
    const previousRows = revisionStructureFingerprint(controls, originalSource, true);
    const currentRows = revisionStructureFingerprint(controls, originalSource, false);
    const removedGroup = previousRows.find((row, index) => row[2] > currentRows[index][2]);
    if (!removedGroup) return [];
    const group = removedGroup[0].replace('__structure__:', '');
    const surviving = controls.find((control) => control.name?.startsWith(group + '[') && control.value
        && !revisionControlIsWorkspaceMetadata(control) && !['hidden', 'checkbox', 'radio', 'file'].includes(control.type));
    const context = labelText(surviving?.labels?.[0])
        || labelText((surviving?.closest?.('[data-revision-section]') || scope)?.querySelector?.('legend, h3, h4'));
    const text = surviving ? paragraphs(surviving.value)[0] : context;
    return text ? [{ text, context }] : [];
}

function initializeEmbeddedEditor() {
    const context = document.querySelector('[data-revision-editor-context]');
    const root = document.querySelector('[data-paper-editor]');
    if (!context || !root) return;
    const targets = JSON.parse(context.dataset.targets || '{}');
    const originalSource = JSON.parse(context.dataset.originalSource || '{}');
    const state = () => window.Alpine?.$data(root);
    const hasOriginalSource = Object.keys(originalSource).length > 0;
    if (hasOriginalSource && context.dataset.documentType === 'detailed_proposal'
        && !originalSource.specific_method_objectives?.length
        && typeof state()?.legacySpecificMethodGroups === 'function') {
        originalSource.specific_method_objectives = state().legacySpecificMethodGroups(originalSource.methodology?.specific_methods || '');
    }
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
        sourceFingerprint: () => currentFingerprint(null),
        changedPassages(annotationId) {
            return revisionChangedPassages(document, targets[annotationId]?.target, originalSource);
        },
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
        async preview() {
            await window.Alpine.nextTick();
            return previewRevisionEditor(state());
        },
        async focus(annotationId, { canFocus = () => true } = {}) {
            const annotation = targets[annotationId];
            if (!annotation?.target) return false;
            const targetId = annotation.target;
            const editor = state();
            const entryId = Number(targetId.match(/^(?:objective|output|activity|work-plan-editor)-(\d+)$/)?.[1]);
            if (entryId && editor?.entries?.some((entry) => entry.id === entryId)) editor.expandedEntryId = entryId;
            const itemId = Number(targetId.match(/^expense-(?:category|account|sub-account|particulars|unit|quantity|unit-cost|details|purpose)-(\d+)$/)?.[1]);
            if (itemId && editor?.items?.some((item) => item.id === itemId)) editor.expandedItemId = itemId;
            await window.Alpine.nextTick();
            if (!canFocus()) return false;
            const target = findRevisionTarget(document, targetId);
            return target ? focusRevisionTarget(target, targetId, { withinDocument: true, comment: annotation.comment, label: annotation.label }) : false;
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

export function revealRevisionResponseFailure(exception, form, revisionDialogs, workflow) {
    const field = [...form.querySelectorAll('[data-comment-response-location]')]
        .find((candidate) => candidate.dataset.responseKey === exception?.revisionResponseKey);
    if (!field) return false;
    const card = field.closest('[data-revision-document]');
    const body = field.closest('[data-revision-comment-body]');
    workflow?.show(2, false);
    if (card) {
        const selector = card.querySelector('[data-revision-comment]');
        if (selector && body) selector.value = body.dataset.revisionCommentBody;
        revisionDialogs.open(card);
        card.dispatchEvent(new CustomEvent('revision-feedback-reveal'));
    }
    field.scrollIntoView({ block: 'nearest' });
    field.querySelector('button:not([hidden])')?.focus({ preventScroll: true });
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
        status.dataset.addressed = String(noChange.selected ? noChange.complete : modified);
        status.dataset.reviewed = String(reviewed);
        status.textContent = noChange.selected
            ? (noChange.complete ? 'Explained — no file change' : 'Explanation required')
            : (replacementSelected ? 'Replacement selected' : (modified ? 'Draft edited' : 'No draft edits'));
        documentModified ||= modified;
    });
    const documentStatus = card.querySelector('[data-revision-document-state]');
    if (documentStatus) {
        documentStatus.dataset.modified = String(documentModified);
        documentStatus.dataset.addressed = String(noChange.selected ? noChange.complete : documentModified);
        documentStatus.dataset.reviewed = String(reviewed);
        documentStatus.textContent = noChange.selected
            ? (noChange.complete ? 'Explained — no file change' : 'Explanation required')
            : (replacementSelected ? 'Replacement selected' : (documentModified ? 'Draft edited' : 'Action needed'));
        const cue = card.querySelector('[data-revision-resolved-cue]');
        if (cue) cue.hidden = documentStatus.dataset.addressed !== 'true';
    }
    return documentModified;
}

export function revisionDocumentsWithoutResolution(form) {
    return [...form.querySelectorAll('[data-revision-document]')].filter((card) => {
        const frame = card.querySelector('[data-revision-editor-frame]');
        const editor = frame ? revisionEditorForFrame(frame, form.dataset.revisionWorkspace) : null;
        const replacementSelected = (card.querySelector('input[type="file"]')?.files?.length || 0) > 0;
        if (editor?.modificationStates || replacementSelected || revisionNoChangeResolution(card).selected) {
            applyRevisionModificationStates(card, editor?.modificationStates?.() || {}, replacementSelected);
        }
        const status = card.querySelector('[data-revision-document-state]');
        return status && status.dataset.addressed !== 'true';
    });
}

export function synchronizeRevisionNoChangeResponses(form, card, states = null, replacementSelected = false) {
    const explanation = card.querySelector('[data-revision-no-change-explanation]')?.value || '';
    const selected = Boolean(card.querySelector('[data-revision-no-change]')?.checked);
    form.querySelectorAll('[data-comment-response-location]').forEach((field) => {
        const globalResponse = !field.dataset.responseDocument;
        if (!globalResponse && field.dataset.responseDocument !== card.dataset.revisionDocument) return;
        const action = field.querySelector('[data-comment-response-no-change]');
        if (!action) return;
        const stateKey = '__document__';
        const knownState = states && Object.prototype.hasOwnProperty.call(states, stateKey);
        let unchanged = selected || (!replacementSelected && knownState && !states[stateKey]);
        if (globalResponse) {
            const papers = [...form.querySelectorAll('[data-revision-document]')];
            unchanged = papers.length > 0 && papers.every((paper) => paper.querySelector('[data-revision-no-change]')?.checked
                || paper.querySelector('[data-revision-document-state]')?.dataset.modified === 'false');
        } else if (!selected && !knownState && field.dataset.revisionAutomaticAction !== 'true' && !replacementSelected) return;
        field.querySelectorAll('[data-comment-response-page], [data-comment-response-paragraph]').forEach((input) => {
            input.disabled = Boolean(unchanged);
            input.required = false;
        });
        field.dataset.revisionAutomaticAction = String(selected);
        if (action.checked !== Boolean(unchanged)) {
            action.checked = Boolean(unchanged);
            action.dispatchEvent(new Event('change', { bubbles: true }));
        }
    });
    form.querySelectorAll('[data-revision-response-document]').forEach((response) => {
        if (response.dataset.revisionResponseDocument !== card.dataset.revisionDocument) return;
        const previous = response.dataset.revisionAutomaticResponse;
        if (previous === undefined && response.value.trim() !== '') return;
        if (previous !== undefined && response.value !== previous) return;
        response.value = selected ? explanation : '';
        response.dataset.revisionAutomaticResponse = response.value;
    });
}

export function synchronizeSingleRevisionReply(card, restoreSaved = false) {
    if (!card.hasAttribute('data-revision-single-reply')) return;
    const response = card.querySelector('[data-revision-response-document]');
    const explanation = card.querySelector('[data-revision-no-change-explanation]');
    if (!response || !explanation) return;
    const keeping = Boolean(card.querySelector('[data-revision-no-change]')?.checked);
    response.dataset.revisionOriginalPlaceholder ??= response.placeholder;
    response.maxLength = keeping ? 1000 : 5000;
    response.placeholder = keeping ? 'Explain why the submitted paper already addresses this comment.' : response.dataset.revisionOriginalPlaceholder;
    if (!keeping) return;
    if (restoreSaved && !response.value.trim() && explanation.value.trim()) response.value = explanation.value;
    explanation.value = response.value;
}

export function initializeRevisionPanelResize(card) {
    const handle = card.querySelector('[data-revision-resolution-resize]');
    const panel = card.querySelector('[data-revision-resolution-panel]');
    if (!handle || !panel) return;
    let drag = null;
    const resize = (height) => {
        const available = card.querySelector('.revision-editor-panel').clientHeight;
        panel.style.height = `${Math.round(Math.max(96, Math.min(Math.max(96, available - 120), height)))}px`;
    };
    handle.addEventListener('pointerdown', (event) => {
        if (event.button !== 0) return;
        event.preventDefault();
        drag = { y: event.clientY, height: panel.getBoundingClientRect().height };
        handle.setPointerCapture(event.pointerId);
        card.classList.add('revision-panel-resizing');
    });
    handle.addEventListener('pointermove', (event) => {
        if (drag) resize(drag.height + drag.y - event.clientY);
    });
    const stop = () => { drag = null; card.classList.remove('revision-panel-resizing'); };
    handle.addEventListener('pointerup', stop);
    handle.addEventListener('pointercancel', stop);
    handle.addEventListener('lostpointercapture', stop);
    handle.addEventListener('keydown', (event) => {
        if (!['ArrowUp', 'ArrowDown'].includes(event.key)) return;
        event.preventDefault();
        resize(panel.getBoundingClientRect().height + (event.key === 'ArrowUp' ? 24 : -24));
    });
}

export async function revisionPaperPreview(card, topicId, fileIndex = 0) {
    const submittedUrl = card.querySelector('[data-revision-comment]')?.selectedOptions[0]?.dataset.pdfUrl;
    if (revisionNoChangeResolution(card).selected) {
        if (!submittedUrl) throw new Error('The submitted paper is unavailable for preview.');
        return { url: submittedUrl, label: 'Submitted paper · No file change needed' };
    }
    const file = card.querySelector('input[type="file"]')?.files?.[fileIndex];
    if (file) {
        if (file.type !== 'application/pdf' && !/\.pdf$/i.test(file.name)) {
            const data = new FormData();
            data.append('paper', file);
            const response = await fetch(card.dataset.revisionPreviewUploadUrl, {
                method: 'POST', body: data,
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': card.closest('form')?.querySelector('[name="_token"]')?.value || '',
                },
            });
            if (!response.ok) {
                const payload = await response.json();
                throw new Error(Object.values(payload.errors || {}).flat().join(' ') || payload.message || 'The replacement preview could not be created.');
            }
            return { url: URL.createObjectURL(await response.blob()), objectUrl: true, label: 'Replacement paper · ' + file.name };
        }
        return { url: URL.createObjectURL(new Blob([file], { type: 'application/pdf' })), objectUrl: true, label: 'Replacement PDF · ' + file.name };
    }
    const frame = card.querySelector('[data-revision-editor-frame]');
    if (frame) {
        const editor = revisionEditorForFrame(frame, topicId);
        if (!editor?.preview) throw new Error('The revision editor is still loading. Try again in a moment.');
        return { html: await waitForRevisionOperation(editor.preview(), REVISION_OPERATION_TIMEOUT_MS, 'The paper preview timed out. Return to your revision and try again.'), label: 'Current revision · Includes your latest edits' };
    }
    if (!submittedUrl) throw new Error('Select a replacement PDF to preview this paper.');
    return { url: submittedUrl, label: 'Submitted paper · No replacement selected' };
}

export function initializeRevisionPreviews(form, topicId) {
    form.querySelectorAll('[data-revision-document]').forEach((card) => {
        const panel = card.querySelector('[data-revision-preview-panel]');
        if (!panel) return;
        const content = card.querySelector('[data-revision-submitted-panel]');
        const reference = card.querySelector('[data-revision-reference]');
        const paperTriggers = [...card.querySelectorAll('[data-revision-paper-open]')];
        const paperDocuments = new WeakSet();
        const zoomControls = card.querySelector('[data-revision-preview-zoom]');
        const zoomValue = card.querySelector('[data-revision-zoom-value]');
        let zoom = 100;
        let fit = 'width';
        const frame = card.querySelector('[data-revision-preview-frame]');
        const submittedFrame = card.querySelector('[data-revision-pdf-frame]');
        const openButton = card.querySelector('[data-revision-preview-open]');
        const closeButton = card.querySelector('[data-revision-preview-close]');
        const refreshButton = card.querySelector('[data-revision-preview-refresh]');
        const status = card.querySelector('[data-revision-preview-status]');
        const error = card.querySelector('[data-revision-preview-error]');
        const stale = card.querySelector('[data-revision-preview-stale]');
        const fileSelector = card.querySelector('[data-revision-preview-file]');
        const applyZoom = () => {
            fitRevisionPaperPreview(frame, zoom, fit);
            for (const paperFrame of [submittedFrame, frame]) {
                try { paperFrame?.contentWindow?.athenaRevisionPdf?.setZoom?.(zoom, fit); }
                catch { /* Native PDF viewers provide their own zoom controls. */ }
            }
            zoomValue.textContent = `${zoom}%`;
            card.querySelector('[data-revision-zoom-out]').disabled = zoom <= 50;
            card.querySelector('[data-revision-zoom-in]').disabled = zoom >= 150;
            card.querySelector('[data-revision-fit-page]')?.setAttribute('aria-pressed', String(fit === 'page'));
            card.querySelector('[data-revision-fit-width]')?.setAttribute('aria-pressed', String(fit === 'width'));
        };
        frame.addEventListener('load', applyZoom);
        for (const paperFrame of [submittedFrame, frame]) {
            paperFrame?.addEventListener('revision-pdf-ready', applyZoom);
        }
        submittedFrame?.addEventListener('load', applyZoom);
        if (typeof ResizeObserver !== 'undefined') {
            new ResizeObserver(() => fitRevisionPaperPreview(frame, zoom, fit)).observe(frame);
        }
        let objectUrl = null;
        let request = 0;
        const reset = () => {
            frame.hidden = true;
            frame.closest?.('[data-revision-paper-open]')?.classList.remove('revision-native-paper-preview');
            frame.removeAttribute('src');
            frame.removeAttribute('srcdoc');
            if (objectUrl) URL.revokeObjectURL(objectUrl);
            objectUrl = null;
        };
        const close = () => {
            request += 1;
            panel.hidden = true;
            content.hidden = false;
            closeButton.setAttribute('aria-pressed', 'true');
            openButton.setAttribute('aria-pressed', 'false');
            if (zoomControls) zoomControls.hidden = false;
            openButton.setAttribute('aria-expanded', 'false');
            refreshButton.disabled = false;
            reset();
        };
        const preview = async () => {
            const currentRequest = ++request;
            panel.hidden = false;
            content.hidden = true;
            closeButton.setAttribute('aria-pressed', 'false');
            openButton.setAttribute('aria-pressed', 'true');
            openButton.setAttribute('aria-expanded', 'true');
            refreshButton.disabled = true;
            error.hidden = true;
            stale.hidden = true;
            status.textContent = 'Preparing paper preview…';
            if (zoomControls) zoomControls.hidden = true;
            reset();
            try {
                const result = await revisionPaperPreview(card, topicId, Number(fileSelector.value || 0));
                if (currentRequest !== request) {
                    if (result.objectUrl) URL.revokeObjectURL(result.url);
                    return;
                }
                objectUrl = result.objectUrl ? result.url : null;
                if (result.html) frame.srcdoc = result.html;
                else frame.src = result.url;
                frame.closest?.('[data-revision-paper-open]')?.classList.toggle('revision-native-paper-preview', result.objectUrl === true);
                if (zoomControls) zoomControls.hidden = !result.html && result.objectUrl === true;
                frame.hidden = false;
                status.textContent = result.label;
                applyZoom();
            } catch (exception) {
                if (currentRequest !== request) return;
                error.textContent = exception instanceof Error ? exception.message : 'The preview could not be loaded. Please try again.';
                error.hidden = false;
                status.textContent = 'Preview unavailable';
            } finally {
                if (currentRequest === request) refreshButton.disabled = false;
            }
        };
        openButton.addEventListener('click', () => {
            const files = [...(card.querySelector('input[type="file"]')?.files || [])];
            fileSelector.replaceChildren(...files.map((file, index) => {
                const option = document.createElement('option');
                option.value = String(index);
                option.textContent = file.name;
                return option;
            }));
            fileSelector.hidden = files.length < 2 || revisionNoChangeResolution(card).selected;
            void preview();
        });
        fileSelector.addEventListener('change', preview);
        refreshButton.addEventListener('click', preview);
        closeButton.addEventListener('click', close);
        const setExpanded = (expanded) => {
            reference?.classList.toggle('revision-reference-expanded', expanded);
            fit = expanded ? 'page' : 'width';
            zoom = 100;
            applyZoom();
            paperTriggers.forEach((trigger) => trigger.setAttribute('aria-expanded', String(expanded)));
            for (const paperFrame of [frame, card.querySelector('[data-revision-pdf-frame]')]) {
                try {
                    if (paperFrame?.contentDocument?.body) paperFrame.contentDocument.body.style.cursor = expanded ? 'auto' : 'zoom-in';
                } catch { /* Uploaded PDFs may use the browser's own viewer. */ }
            }
        };
        const collapse = () => setExpanded(false);
        const expandPaper = () => {
            if (reference.classList.contains('revision-reference-expanded')) return;
            setExpanded(true);
            card.querySelector('[data-revision-reference-dismiss]')?.focus({ preventScroll: true });
        };
        const bindPaperFrame = (paperFrame) => {
            try {
                const paperDocument = paperFrame?.contentDocument;
                if (!paperDocument?.body || paperDocuments.has(paperDocument)) return;
                paperDocuments.add(paperDocument);
                proposalPreviewWorkspace().bindProposalPreviewWheel(
                    paperFrame,
                    () => reference.classList.contains('revision-reference-expanded'),
                    (amount) => resizeZoom(amount),
                );
                paperDocument.body.style.cursor = reference.classList.contains('revision-reference-expanded') ? 'auto' : 'zoom-in';
                paperDocument.addEventListener('keydown', (event) => {
                    if (event.key !== 'Escape' || !reference.classList.contains('revision-reference-expanded')) return;
                    event.preventDefault();
                    event.stopImmediatePropagation();
                    collapse();
                    card.querySelector('[data-revision-reference-toggle]')?.focus({ preventScroll: true });
                }, true);
                paperDocument.addEventListener('click', (event) => {
                    if (event.target.closest?.('button, a, input, textarea, select, [data-annotation-id]')) return;
                    if (paperDocument.getSelection()?.toString()) return;
                    expandPaper();
                });
            } catch { /* Native PDF viewers still offer keyboard expansion from the paper container. */ }
        };
        paperTriggers.forEach((trigger) => {
            trigger.addEventListener('click', (event) => {
                if (event.target.closest?.('button, a, input, textarea, select')) return;
                expandPaper();
            });
            trigger.addEventListener('keydown', (event) => {
                if (event.target !== trigger || !['Enter', ' '].includes(event.key)) return;
                event.preventDefault();
                expandPaper();
            });
        });
        for (const paperFrame of [frame, card.querySelector('[data-revision-pdf-frame]')]) {
            paperFrame?.addEventListener('load', () => bindPaperFrame(paperFrame));
            bindPaperFrame(paperFrame);
        }
        const resizeZoom = (amount) => {
            zoom = Math.max(50, Math.min(150, zoom + amount));
            applyZoom();
        };
        card.querySelector('[data-revision-zoom-out]')?.addEventListener('click', () => resizeZoom(-10));
        card.querySelector('[data-revision-zoom-in]')?.addEventListener('click', () => resizeZoom(10));
        for (const mode of ['page', 'width']) {
            card.querySelector(`[data-revision-fit-${mode}]`)?.addEventListener('click', () => {
                fit = mode;
                zoom = 100;
                applyZoom();
            });
        }
        const dialog = card.querySelector('[data-revision-dialog]');
        dialog.addEventListener('cancel', (event) => {
            if (!reference?.classList.contains('revision-reference-expanded')) return;
            event.preventDefault();
            collapse();
            paperTriggers.find((trigger) => !trigger.closest('[hidden]'))?.focus({ preventScroll: true });
        });
        dialog.addEventListener('close', () => { close(); collapse(); });
        const referenceToggle = card.querySelector('[data-revision-reference-toggle]');
        card.querySelector('[data-revision-reference-dismiss]')?.addEventListener('click', () => {
            collapse();
            reference.classList.remove('revision-reference-mobile-visible');
            referenceToggle?.setAttribute('aria-expanded', 'false');
            if (referenceToggle) referenceToggle.textContent = 'Show paper';
            const returnTarget = window.matchMedia('(max-width: 767px)').matches ? referenceToggle : card.querySelector('[data-revision-editor-frame]');
            returnTarget?.focus({ preventScroll: true });
        });
        referenceToggle?.addEventListener('click', () => {
            const visible = reference.classList.toggle('revision-reference-mobile-visible');
            referenceToggle.setAttribute('aria-expanded', String(visible));
            referenceToggle.textContent = visible ? 'Hide paper' : 'Show paper';
        });
        const editorFrame = card.querySelector('[data-revision-editor-frame]');
        const bindEdits = () => {
            try {
                const markStale = () => { if (!panel.hidden) stale.hidden = false; };
                editorFrame?.contentDocument?.addEventListener('input', markStale);
                editorFrame?.contentDocument?.addEventListener('change', markStale);
            } catch { /* A loading frame may not expose its document yet. */ }
        };
        editorFrame?.addEventListener('load', bindEdits);
        bindEdits();
        card.addEventListener('change', (event) => {
            if (event.target.matches?.('input[type="file"], [data-revision-no-change], [data-revision-edit-paper]')) close();
        });
    });
}

export function loadRevisionEditorFrame(card) {
    const frame = card.querySelector('[data-revision-editor-frame]');
    const url = frame?.dataset?.revisionEditorSrc;
    if (!url || frame.getAttribute('src')) return false;
    if (revisionNoChangeResolution(card).selected || (card.querySelector('input[type="file"]')?.files?.length || 0) > 0) return false;

    const loading = card.querySelector('[data-revision-editor-loading]');
    if (loading) loading.hidden = false;
    frame.dataset.revisionEditorPendingFocus = 'true';
    frame.setAttribute('src', url);
    return true;
}

export function initializeRevisionDialogs(form, topicId) {
    initializeRevisionPreviews(form, topicId);
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
        const currentStates = states || editorFor(card)?.modificationStates?.() || {};
        applyRevisionModificationStates(card, currentStates, replacementSelected);
        synchronizeRevisionNoChangeResponses(form, card, currentStates, replacementSelected);
        const fingerprint = editorFor(card)?.sourceFingerprint?.();
        if (fingerprint !== undefined && card.dataset.locationSourceFingerprint !== fingerprint) {
            const previous = card.dataset.locationSourceFingerprint;
            card.dataset.locationSourceFingerprint = fingerprint;
            if (previous !== undefined) form.dispatchEvent(new CustomEvent('revision-document-changed', { detail: { documentType: card.dataset.revisionDocument } }));
        }
    };
    const synchronizeNoChange = (card) => {
        const keepPaper = card.querySelector('[data-revision-no-change]');
        const explanation = card.querySelector('[data-revision-no-change-explanation]');
        const details = card.querySelector('[data-revision-no-change-details]');
        const upload = card.querySelector('input[type="file"]');
        if (!keepPaper || !explanation || !details) return;
        details.hidden = !keepPaper.checked;
        explanation.required = keepPaper.checked;
        synchronizeSingleRevisionReply(card, true);
        if (upload) upload.required = !keepPaper.checked && !card.querySelector('[data-revision-editor-frame]');
        const editorContent = card.querySelector('[data-revision-editor-content]');
        if (editorContent) editorContent.inert = keepPaper.checked;
        const notice = card.querySelector('[data-revision-keep-notice]');
        if (notice) notice.hidden = !keepPaper.checked;
        refreshModificationStates(card);
    };
    const dialogIsOpen = (card) => Boolean(card.querySelector('[data-revision-dialog]')?.open);
    const canFocusEditor = (card) => {
        const active = document.activeElement;
        return !revisionNoChangeResolution(card).selected
            && !active?.closest?.('[data-revision-feedback], [data-revision-resolution-panel], .revision-upload-alternative');
    };
    const selectFeedback = (card, syncPdf = true, focusEditor = true) => {
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
        if (focusEditor && option.dataset.annotationId && canFocusEditor(card)) {
            void editorFor(card)?.focus(option.dataset.annotationId, { canFocus: () => dialogIsOpen(card) && canFocusEditor(card) });
        }
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
        loadRevisionEditorFrame(card);
        refreshModificationStates(card);
        const index = cards.indexOf(card);
        card.querySelector('[data-revision-previous]').disabled = index === 0;
        card.querySelector('[data-revision-next]').disabled = index === cards.length - 1;
        selectFeedback(card);
    };

    cards.forEach((card) => {
        initializeRevisionPanelResize(card);
        const dialog = card.querySelector('[data-revision-dialog]');
        const workspace = card.querySelector('[data-revision-document-workspace]');
        const feedbackToggle = card.querySelector('[data-revision-feedback-toggle]');
        const wideScreen = window.matchMedia('(min-width: 1280px)');
        const setFeedbackVisible = (visible) => {
            workspace?.classList.toggle('revision-responses-visible', visible);
            feedbackToggle?.setAttribute('aria-expanded', String(visible));
        };
        setFeedbackVisible(wideScreen.matches || revisionNoChangeResolution(card).selected);
        wideScreen.addEventListener('change', (event) => setFeedbackVisible(event.matches));
        feedbackToggle?.addEventListener('click', () => setFeedbackVisible(!workspace.classList.contains('revision-responses-visible')));
        card.querySelector('[data-revision-feedback-dismiss]')?.addEventListener('click', () => {
            setFeedbackVisible(false);
            feedbackToggle?.focus({ preventScroll: true });
        });
        card.addEventListener('revision-feedback-reveal', () => setFeedbackVisible(true));
        dialog.addEventListener('cancel', (event) => {
            if (event.defaultPrevented || wideScreen.matches || !workspace?.classList.contains('revision-responses-visible')) return;
            event.preventDefault();
            setFeedbackVisible(false);
            feedbackToggle?.focus({ preventScroll: true });
        });
        dialog.addEventListener('close', () => {
            // Switching documents must not unmount or navigate either editor.
            if (activeCard === card) {
                document.body.style.overflow = previousOverflow;
                activeCard = null;
                card.querySelector('[data-revision-open]')?.focus({ preventScroll: true });
                const status = card.querySelector('[data-revision-document-state]');
                if (status?.dataset.modified === 'true' && !revisionNoChangeResolution(card).selected) {
                    form.dispatchEvent(new CustomEvent('revision-paper-reviewed', { detail: { documentType: card.dataset.revisionDocument } }));
                }
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
            if (!editor && !editorFrame?.getAttribute?.('src')) return;
            setFrameLoading(card, 'editor', false);
            if (editor) {
                editor.onChange = (states) => refreshModificationStates(card, states);
                refreshModificationStates(card);
            }
            const focusPending = editorFrame?.dataset?.revisionEditorPendingFocus === 'true';
            if (editorFrame?.dataset) delete editorFrame.dataset.revisionEditorPendingFocus;
            if (dialog.open) selectFeedback(card, true, focusPending && document.activeElement !== editorFrame);
        };
        editorFrame?.addEventListener('load', bindEditor);
        if (editorFrame && editorFor(card)) bindEditor();
        card.querySelector('[data-revision-comment]')?.addEventListener('change', () => selectFeedback(card));
        synchronizeNoChange(card);
        synchronizeRevisionNoChangeResponses(form, card);
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
        if (event.target.matches?.('[data-revision-no-change], [data-revision-edit-paper]')) {
            const upload = card.querySelector('input[type="file"]');
            const keeping = card.querySelector('[data-revision-no-change]')?.checked;
            if (keeping && upload?.files?.length) upload.value = '';
            synchronizeNoChange(card);
            synchronizeRevisionNoChangeResponses(form, card);
            if (!keeping && dialogIsOpen(card)) loadRevisionEditorFrame(card);
            if (keeping) card.querySelector(card.hasAttribute('data-revision-single-reply') ? '[data-revision-response-document]' : '[data-revision-no-change-explanation]')?.focus();
            return;
        }
        if (!event.target.matches?.('input[type="file"]')) return;
        const noChange = card.querySelector('[data-revision-no-change]');
        if (event.target.files?.length && noChange?.checked) {
            noChange.checked = false;
            const revise = card.querySelector('[data-revision-edit-paper]');
            if (revise) revise.checked = true;
        }
        synchronizeNoChange(card);
    });
    form.addEventListener('input', (event) => {
        if (event.target.matches?.('[data-revision-response-document]')) {
            const card = event.target.closest('[data-revision-document]');
            if (card?.hasAttribute('data-revision-single-reply')) {
                synchronizeSingleRevisionReply(card);
                refreshModificationStates(card);
            }
        }
        if (!event.target.matches?.('[data-revision-no-change-explanation]')) return;
        const card = event.target.closest('[data-revision-document]');
        if (card) {
            refreshModificationStates(card);
            synchronizeRevisionNoChangeResponses(form, card);
        }
    });
    form.addEventListener('invalid', (event) => {
        const card = event.target.closest('[data-revision-document]');
        if (cards.includes(card)) {
            const body = event.target.closest('[data-revision-comment-body]');
            if (body) card.querySelector('[data-revision-comment]').value = body.dataset.revisionCommentBody;
            card.dispatchEvent(new CustomEvent('revision-feedback-reveal'));
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

export function initializeRevisionCommentResponsePapers(form) {
    form.querySelectorAll('[data-comment-response-paper]').forEach((paper) => {
        const trigger = paper.querySelector('[data-comment-response-paper-open]');
        const close = paper.querySelector('[data-comment-response-paper-close]');
        const group = paper.closest('[data-comment-response-source]');
        const originalParent = paper.parentNode;
        const originalNextSibling = paper.nextSibling;
        let returnFocus;
        let scrollPosition = 0;
        let bodyOverflow = '';
        const expand = (focusTarget = trigger) => {
            if (paper.matches(':modal')) return;
            returnFocus = focusTarget;
            scrollPosition = window.scrollY;
            bodyOverflow = document.body.style.overflow;
            document.body.style.overflow = 'hidden';
            paper.close();
            if (originalParent.closest('[hidden]')) form.appendChild(paper);
            paper.setAttribute('role', 'dialog');
            paper.showModal();
            trigger.setAttribute('aria-expanded', 'true');
            close.hidden = false;
            paper.querySelector('[data-comment-response-preview] iframe')?.setAttribute('tabindex', '0');
            paper.querySelector('[data-comment-response-preview]')?.dispatchEvent(new CustomEvent('comment-response-paper-view-change', { detail: { expanded: true } }));
            close.focus({ preventScroll: true });
        };
        const restore = () => {
            if (!paper.matches(':modal')) return;
            paper.close();
            if (paper.parentNode !== originalParent) originalParent.insertBefore(paper, originalNextSibling);
            paper.setAttribute('role', 'region');
            paper.show();
            trigger.setAttribute('aria-expanded', 'false');
            close.hidden = true;
            paper.querySelector('[data-comment-response-preview] iframe')?.setAttribute('tabindex', '-1');
            paper.querySelector('[data-comment-response-preview]')?.dispatchEvent(new CustomEvent('comment-response-paper-view-change', { detail: { expanded: false } }));
            document.body.style.overflow = bodyOverflow;
            window.scrollTo({ top: scrollPosition, behavior: 'instant' });
            returnFocus?.focus({ preventScroll: true });
        };
        form.querySelectorAll('[data-comment-response-preview-open]').forEach((button) => {
            if (button.dataset.commentResponsePreviewOpen !== group?.dataset.commentResponseSource) return;
            button.addEventListener('click', () => expand(button));
        });
        trigger.addEventListener('click', (event) => {
            if (event.target.closest('button, a')) return;
            if (event.target.closest('.pdf-annotation-page, [data-comment-response-preview]') && !window.getSelection()?.toString()) expand();
        });
        trigger.addEventListener('keydown', (event) => {
            if (event.target !== trigger || !['Enter', ' '].includes(event.key)) return;
            event.preventDefault();
            expand();
        });
        close.addEventListener('click', restore);
        paper.addEventListener('cancel', (event) => { event.preventDefault(); restore(); });
    });
}

export function initializeRevisionWorkflow(form) {
    const panels = [...form.querySelectorAll('[data-revision-step]')];
    if (!panels.length) return null;
    const progress = form.querySelector('[data-revision-progress]');
    const back = form.querySelector('[data-revision-step-back]');
    const next = form.querySelector('[data-revision-step-continue]');
    const error = form.querySelector('[data-revision-step-error]');
    const confirmation = form.querySelector('[data-revision-details-confirmed]');
    let current = 1;
    let dialogs;
    const show = (step, focus = true) => {
        current = Math.max(1, Math.min(panels.length, Number(step) || 1));
        panels.forEach((panel) => { panel.hidden = Number(panel.dataset.revisionStep) !== current; });
        const panel = panels[current - 1];
        progress.textContent = `Step ${current} of ${panels.length} - ${panel.dataset.revisionStepLabel}`;
        form.querySelectorAll('[data-revision-progress-step]').forEach((item) => {
            const number = Number(item.dataset.revisionProgressStep);
            item.dataset.state = number < current ? 'complete' : (number === current ? 'current' : 'upcoming');
            if (number === current) item.setAttribute('aria-current', 'step');
            else item.removeAttribute('aria-current');
            item.querySelector('[data-revision-progress-mark]').textContent = number < current ? '\u2713' : String(number);
            const description = item.querySelector('[data-revision-progress-description]');
            if (description) description.textContent = number < current ? 'Completed' : (number === current ? 'Current step' : 'Upcoming');
        });
        back.hidden = current === 1;
        next.hidden = current === panels.length;
        next.textContent = `Continue to ${panels[current]?.dataset.revisionStepLabel.toLowerCase() || 'submit'}`;
        error.hidden = true;
        if (current === panels.length) {
            const title = form.querySelector('[data-revision-summary-title]');
            const cost = form.querySelector('[data-revision-summary-cost]');
            const duration = form.querySelector('[data-revision-summary-duration]');
            if (title) title.textContent = form.elements.title.value;
            if (cost) cost.textContent = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(Number(form.elements.estimated_budget.value));
            if (duration) duration.textContent = `${form.elements.estimated_duration_months.value} months`;
        }
        if (focus) {
            panel.setAttribute('tabindex', '-1');
            panel.focus({ preventScroll: true });
            panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
        form.dispatchEvent(new CustomEvent('revision-step-changed', { detail: { step: current } }));
    };
    const validate = (step) => {
        if (step === 2) {
            const unresolved = revisionDocumentsWithoutResolution(form);
            if (unresolved.length) {
                show(2);
                error.textContent = `Address ${unresolved[0].dataset.revisionLabel}: revise it, upload a replacement, or explain why no change is needed.`;
                error.hidden = false;
                dialogs?.open(unresolved[0]);
                return false;
            }
        }
        const controls = [...panels[step - 1].querySelectorAll('input, textarea, select')];
        const invalid = controls.find((control) => !control.checkValidity());
        if (invalid) {
            show(step);
            error.textContent = step === 2 ? 'Write a reply to each reviewer comment.' : 'Check the proposal details and confirm they are correct.';
            error.hidden = false;
            invalid.closest?.('details')?.setAttribute('open', '');
            invalid.reportValidity();
            invalid.focus();
            return false;
        }
        return true;
    };
    next.addEventListener('click', () => { if (validate(current)) show(current + 1); });
    back.addEventListener('click', () => show(current - 1));
    form.addEventListener('input', (event) => {
        if (event.target.closest('[data-revision-proposal-details-fields]')) confirmation.checked = false;
    });
    form.addEventListener('invalid', (event) => {
        const panel = event.target.closest('[data-revision-step]');
        if (panel) show(panel.dataset.revisionStep, false);
    }, true);
    const requestedAnnotation = new URLSearchParams(window.location.search).has('revision_annotation');
    const returnedToSubmit = window.location.hash === '#review-and-submit';
    show(requestedAnnotation || returnedToSubmit ? 2 : form.dataset.revisionStartStep, false);
    return {
        show,
        setDialogs(value) { dialogs = value; },
        canSubmit() {
            if (current !== panels.length) { if (validate(current)) show(current + 1); return false; }
            return panels.slice(1, -1).every((panel) => validate(Number(panel.dataset.revisionStep)));
        },
    };
}

export default function initializeRevisionWorkspace(confirmSubmission, {
    submissionTimeoutMs = REVISION_SUBMISSION_TIMEOUT_MS,
} = {}) {
    initializeEmbeddedEditor();
    document.querySelectorAll('[data-revision-workspace]').forEach((form) => {
        if (form.dataset.revisionInitialized) return;
        form.dataset.revisionInitialized = 'true';
        const topicId = form.dataset.revisionWorkspace;
        initializeRevisionCommentResponsePapers(form);
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
            const upload = card.querySelector('input[type="file"]');
            if (upload) upload.required = false;
            const report = () => {
                const editor = revisionEditorForFrame(frame, topicId);
                if (!editor && !frame.getAttribute?.('src')) return;
                const message = card.querySelector('[data-revision-editor-status]');
                message.textContent = editor
                    ? 'Changes save as you type. Submit revision generates and attaches the updated PDF.'
                    : 'Editor could not load. Reload this page to try again.';
                if (editor) {
                    form.elements.revision_draft_id.value = String(editor.draftId);

                }
            };
            frame.addEventListener('load', report);
            if (revisionEditorForFrame(frame, topicId)) report();
        });

        const workflow = initializeRevisionWorkflow(form);
        const revisionDialogs = initializeRevisionDialogs(form, topicId);
        workflow?.setDialogs(revisionDialogs);
        const responseLocations = initializeCommentResponseLocations(form, {
            fingerprint: () => frames.map((frame) => revisionEditorForFrame(frame, topicId)?.sourceFingerprint?.() || '').join('\u001f')
                + [...form.querySelectorAll('input[type="file"]')].map((input) => [...input.files].map((file) => file.name + ':' + file.size + ':' + file.lastModified).join(',')).join('|'),
            suggestedTexts: (field, type) => {
                const card = [...form.querySelectorAll('[data-revision-document]')].find((card) => card.dataset.revisionDocument === type);
                if ((card?.querySelector('input[type="file"]')?.files?.length || 0) > 0) return [];
                const frame = card?.querySelector('[data-revision-editor-frame]');
                const editor = frame ? revisionEditorForFrame(frame, topicId) : null;
                const linked = editor?.changedPassages?.(field.dataset.responseKey?.replace('annotation_', '')) || [];
                return linked.length ? linked : editor?.changedPassages?.() || [];
            },
            readDocuments: async (types, prepared) => {
                const cards = [...form.querySelectorAll('[data-revision-document]')].filter((card) => types.includes(card.dataset.revisionDocument));
                const editors = cards.flatMap((card) => {
                    if (card.querySelector('input[type="file"]')?.files?.length || revisionNoChangeResolution(card).selected) return [];
                    const frame = card.querySelector('[data-revision-editor-frame]');
                    const editor = frame ? revisionEditorForFrame(frame, topicId) : null;
                    return editor ? [{ ...editor, label: card.dataset.revisionLabel }] : [];
                });
                const generated = prepared || await prepareRevisionEditors(editors);
                const results = new Map();
                for (const card of cards) {
                    const type = card.dataset.revisionDocument;
                    const passages = [];
                    const uploaded = [...(card.querySelector('input[type="file"]')?.files || [])];
                    const sources = uploaded.length ? uploaded : [generated.find((file) => file.document_type === type)?.blob || card.dataset.revisionStagedPdfUrl].filter(Boolean);
                    for (let index = 0; index < sources.length; index++) {
                        let source = sources[index];
                        const label = uploaded[index]?.name || card.dataset.revisionLabel;
                        let objectUrl;
                        try {
                            if (source instanceof Blob && source.type !== 'application/pdf' && !/\.pdf$/i.test(source.name || '')) {
                                const preview = await revisionPaperPreview(card, topicId, index);
                                source = preview.url;
                                if (preview.objectUrl) objectUrl = preview.url;
                            }
                            passages.push(...await readPdfPassages(source, label));
                        } finally { if (objectUrl) URL.revokeObjectURL(objectUrl); }
                    }
                    results.set(type, { passages });
                }
                return results;
            },
        });
        form.querySelectorAll('[data-revision-document] input[type="file"]').forEach((input) => input.addEventListener('change', () => {
            responseLocations?.invalidate(input.closest('[data-revision-document]').dataset.revisionDocument);
        }));

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (workflow && !workflow.canSubmit()) return;
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
                // A background location read may still be generating a paper after Done.
                // Finish it before staging the final files so an older upload cannot win the race.
                await responseLocations?.settle();
                if (attempt.cancelled) return;
                for (const frame of frames) {
                    const card = frame.closest('[data-revision-document]');
                    if (revisionNoChangeResolution(card).selected) continue;
                    const editor = revisionEditorForFrame(frame, topicId);
                    if ((card.querySelector('input[type="file"]')?.files?.length || 0) > 0 && editor) {
                        resumeEditors.push(await editor.pauseForUpload());
                        if (attempt.cancelled) return;
                    }
                }
                const editors = frames.flatMap((frame) => {
                    const card = frame.closest('[data-revision-document]');
                    if (revisionNoChangeResolution(card).selected) return [];
                    if ((card.querySelector('input[type="file"]')?.files?.length || 0) > 0) return [];
                    const editor = revisionEditorForFrame(frame, topicId);
                    if (!editor) {
                        throw new Error('The ' + card.dataset.revisionLabel + ' editor has not loaded. Wait for it to load, or reload this page.');
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
                form.querySelectorAll('[data-revision-document]').forEach((card) => {
                    const frame = card.querySelector('[data-revision-editor-frame]');
                    const editor = frame ? revisionEditorForFrame(frame, topicId) : null;
                    synchronizeRevisionNoChangeResponses(form, card, editor?.modificationStates?.() || {}, Boolean(card.querySelector('input[type="file"]')?.files?.length));
                });
                await responseLocations?.verify(files);
                if (attempt.cancelled) return;
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
                    const revealedResponse = revealRevisionResponseFailure(exception, form, revisionDialogs, workflow);
                    const revealedEditor = !revealedResponse && revealRevisionEditorFailure(
                        exception,
                        frames,
                        topicId,
                        revisionDialogs,
                        (callback) => window.requestAnimationFrame
                            ? window.requestAnimationFrame(callback)
                            : window.setTimeout(callback, 0),
                    );
                    if (!revealedResponse && !revealedEditor) error.scrollIntoView({ behavior: 'smooth', block: 'center' });
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
