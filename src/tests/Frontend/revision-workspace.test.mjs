import assert from 'node:assert/strict';
import test from 'node:test';
import initializeRevisionWorkspace, { applyRevisionModificationStates, createRevisionSubmissionWatchdog, initializeRevisionDialogs, prepareRevisionEditors, revealRevisionEditorFailure, REVISION_OPERATION_TIMEOUT_MS, REVISION_SUBMISSION_TIMEOUT_MS, revisionControlFingerprint, revisionCurrentSourceFingerprint, revisionDocumentsWithoutResolution, revisionNoChangeResolution, revisionSourceControlFingerprint, revisionSourceFingerprint, revisionEditorForFrame, embeddedRevisionFileSaved } from '../../resources/js/revision-workspace.js';

test('all edits are saved before any replacement is prepared so later saves cannot invalidate earlier files', async () => {
    const files = new Map();
    const events = [];
    const editors = ['proposal', 'budget', 'work plan'].map((label) => ({
        label,
        async save() {
            files.clear();
            events.push('save ' + label);
            return true;
        },
        async prepare() {
            events.push('prepare ' + label);
            const file = { filename: label + '.pdf', draft_id: 7 };
            files.set(label, file);
            return file;
        },
    }));
    const results = await prepareRevisionEditors(editors, {
        onProgress: ({ phase, label }) => events.push(phase + ' ' + label),
    });
    assert.equal(results.length, 3);
    assert.equal(files.size, 3);
    assert.deepEqual(events, [
        'saving proposal', 'save proposal',
        'saving budget', 'save budget',
        'saving work plan', 'save work plan',
        'preparing proposal', 'prepare proposal',
        'preparing budget', 'prepare budget',
        'preparing work plan', 'prepare work plan',
    ]);
});

test('a stalled document preparation times out instead of leaving submission loading forever', async () => {
    await assert.rejects(prepareRevisionEditors([{
        label: 'Detailed Research Proposal',
        save: async () => true,
        prepare: async () => new Promise(() => {}),
    }], { timeoutMs: 5 }), /Preparing Detailed Research Proposal timed out/);
});

test('revision preparation allows the server PDF conversion window to finish', () => {
    assert.equal(REVISION_OPERATION_TIMEOUT_MS, 150_000);
    assert.equal(REVISION_SUBMISSION_TIMEOUT_MS, 180_000);
    assert.ok(REVISION_SUBMISSION_TIMEOUT_MS > REVISION_OPERATION_TIMEOUT_MS);
});

test('the submission watchdog cancels a preparation that stalls outside document generation', async () => {
    let timedOut = false;
    const watchdog = createRevisionSubmissionWatchdog(5, () => { timedOut = true; });

    await new Promise((resolve) => globalThis.setTimeout(resolve, 10));

    assert.equal(watchdog.cancelled, true);
    assert.equal(timedOut, true);
    watchdog.stop();
});

test('an invalid editor is identified before saving so its highlighted field can be revealed', async () => {
    let saved = false;
    let prepared = false;
    const input = { text: 'My unsaved revision' };
    await assert.rejects(prepareRevisionEditors([{
        documentType: 'detailed_proposal',
        label: 'Detailed Research Proposal',
        validate: () => false,
        save: async () => { saved = true; },
        prepare: async () => { prepared = true; },
    }]), (error) => {
        assert.match(error.message, /highlighted required fields/);
        assert.equal(error.revisionDocumentType, 'detailed_proposal');

        return true;
    });
    assert.equal(saved, false);
    assert.equal(prepared, false);
    assert.equal(input.text, 'My unsaved revision');
});

test('a revision validation failure opens the matching editor and focuses its first invalid field', () => {
    const events = [];
    const card = { dataset: { revisionDocument: 'detailed_proposal' } };
    const editor = {
        topicId: '3',
        documentType: 'detailed_proposal',
        focusInvalid() { events.push('focus invalid'); },
    };
    const frame = {
        contentWindow: { athenaRevisionEditor: editor },
        closest: () => card,
    };
    const error = new Error('Complete highlighted fields.');
    error.revisionDocumentType = 'detailed_proposal';

    assert.equal(revealRevisionEditorFailure(
        error,
        [frame],
        '3',
        { open(openedCard) { assert.equal(openedCard, card); events.push('open editor'); } },
        (callback) => callback(),
    ), true);
    assert.deepEqual(events, ['open editor', 'focus invalid']);
});

test('failed generation stops the package rather than accepting an older staged file', async () => {
    let nextFilePrepared = false;
    await assert.rejects(prepareRevisionEditors([
        { label: 'proposal', save: async () => true, prepare: async () => undefined },
        { label: 'budget', save: async () => true, prepare: async () => { nextFilePrepared = true; } },
    ]), /Could not prepare proposal/);
    assert.equal(nextFilePrepared, false);
});

test('a frame from another proposal or document cannot supply the revision', () => {
    const frame = {
        contentWindow: { athenaRevisionEditor: { topicId: '3', documentType: 'work_plan' } },
        closest: () => ({ dataset: { revisionDocument: 'work_plan' } }),
    };
    assert.equal(revisionEditorForFrame(frame, 3), frame.contentWindow.athenaRevisionEditor);
    assert.equal(revisionEditorForFrame(frame, 4), null);
    frame.contentWindow.athenaRevisionEditor.documentType = 'detailed_proposal';
    assert.equal(revisionEditorForFrame(frame, 3), null);
    Object.defineProperty(frame, 'contentWindow', { get() { throw new Error('Cross-origin document'); } });
    assert.equal(revisionEditorForFrame(frame, 3), null);
});

test('staging a generated file advances the editor version for retries and further saves', (t) => {
    const oldDocument = globalThis.document;
    t.after(() => { globalThis.document = oldDocument; });
    const version = { value: '4' };
    globalThis.document = { querySelector: () => ({ querySelector: () => version }) };
    embeddedRevisionFileSaved({ document_version: 5 });
    assert.equal(version.value, '5');
});

test('submit operates on the mounted editor without replacing the page or requiring a manual upload', async (t) => {
    const original = Object.fromEntries(['document', 'HTMLFormElement', 'window'].map((key) => [key, globalThis[key]]));
    t.after(() => Object.assign(globalThis, original));
    const handlers = {};
    const events = [];
    const upload = { required: true, files: [] };
    const status = { hidden: true, textContent: '' };
    const error = { hidden: true, scrollIntoView() {} };
    const errorMessage = { textContent: '' };
    const overlayClasses = new Set(['hidden']);
    const overlayAttributes = { 'aria-hidden': 'true' };
    const overlay = {
        classList: {
            add: (...classes) => classes.forEach((name) => overlayClasses.add(name)),
            contains: (name) => overlayClasses.has(name),
            remove: (...classes) => classes.forEach((name) => overlayClasses.delete(name)),
        },
        focus() { events.push('focus progress'); },
        setAttribute(name, value) { overlayAttributes[name] = value; },
    };
    const overlayTitle = { textContent: '' };
    const submitButton = { disabled: false };
    const submitButtonSpinner = { hidden: true };
    const submitButtonLabel = { textContent: 'Submit revision' };
    const field = { value: 'Changed activity' };
    const documentState = { dataset: { modified: 'true', addressed: 'true' } };
    const api = {
        topicId: '3', draftId: 8, documentType: 'work_plan',
        async focus(id) { events.push('focus ' + id); return true; },
        async save() { events.push('save ' + field.value); return true; },
        async prepare() { events.push('prepare'); return { filename: 'updated.docx', draft_id: 8 }; },
        release() { events.push('release'); },
    };
    const card = {
        dataset: { revisionDocument: 'work_plan', revisionLabel: 'Work Plan' },
        querySelectorAll: () => [],
        querySelector(selector) {
            if (selector === 'input[type="file"]') return upload;
            if (selector === '[data-revision-editor-frame]') return frame;
            if (selector === '[data-revision-editor-status]') return status;
            if (selector === '[data-revision-document-state]') return documentState;
            return null;
        },
    };
    const frame = { contentWindow: { athenaRevisionEditor: api }, closest: () => card, addEventListener() {} };
    let confirmationCount = 0;
    const form = {
        dataset: { revisionWorkspace: '3' },
        elements: { revision_draft_id: { value: '' } },
        querySelector(selector) {
            if (selector === '[data-revision-submit-status]') return status;
            if (selector === '[data-revision-submit-error]') return error;
            if (selector === '[data-revision-submit-error-message]') return errorMessage;
            if (selector === '[data-revision-submit-overlay]') return overlay;
            if (selector === '[data-revision-submit-title]') return overlayTitle;
            if (selector === '[data-revision-submit-button]') return submitButton;
            if (selector === '[data-revision-submit-button-spinner]') return submitButtonSpinner;
            if (selector === '[data-revision-submit-button-label]') return submitButtonLabel;
            if (selector === ':invalid') return null;
            return null;
        },
        querySelectorAll: (selector) => selector === '[data-revision-editor-frame]' ? [frame] : (selector === '[data-revision-document]' ? [card] : []),
        addEventListener: (name, callback) => { handlers[name] = callback; },
        checkValidity: () => true,
        reportValidity() {},
        setAttribute() {}, removeAttribute() {},
    };
    globalThis.document = {
        querySelector: () => null,
        querySelectorAll: () => [form],
    };
    globalThis.window = {
        location: { search: '' },
        requestAnimationFrame(callback) { callback(); },
    };
    globalThis.HTMLFormElement = { prototype: { submit() { events.push('submit'); } } };
    initializeRevisionWorkspace(async () => {
        confirmationCount += 1;
        assert.equal(submitButton.disabled, true);
        assert.equal(submitButtonSpinner.hidden, false);
        assert.equal(submitButtonLabel.textContent, 'Checking revision…');
        assert.equal(overlayClasses.has('hidden'), true);
        return true;
    });
    assert.equal(upload.required, false);
    assert.equal(form.elements.revision_draft_id.value, '8');
    assert.equal(field.value, 'Changed activity');
    assert.equal(frame.contentWindow.athenaRevisionEditor, api);
    const firstSubmission = handlers.submit({ preventDefault() {} });
    assert.equal(form.dataset.revisionSubmitting, 'true');
    assert.equal(submitButton.disabled, true);
    assert.equal(submitButtonSpinner.hidden, false);
    assert.equal(submitButtonLabel.textContent, 'Checking revision…');
    await handlers.submit({ preventDefault() {} });
    await firstSubmission;
    assert.deepEqual(events, ['focus progress', 'save Changed activity', 'prepare', 'release', 'submit']);
    assert.equal(error.hidden, true);
    assert.equal(overlayClasses.has('hidden'), false);
    assert.equal(overlayClasses.has('flex'), true);
    assert.equal(overlayAttributes['aria-hidden'], 'false');
    assert.equal(overlayTitle.textContent, 'Sending your revision');
    assert.equal(status.textContent, 'The files are ready. Sending the new proposal version to the Research Head…');
    assert.equal(submitButton.disabled, true);
    assert.equal(submitButtonSpinner.hidden, false);
    assert.equal(submitButtonLabel.textContent, 'Sending revision…');
    assert.equal(confirmationCount, 1);
    await handlers.submit({ preventDefault() {} });
    assert.equal(events.filter((event) => event === 'submit').length, 1);
});


test('full-screen document switching preserves frames, synchronizes feedback and restores page scrolling', async (t) => {
    const original = { document: globalThis.document, window: globalThis.window };
    t.after(() => Object.assign(globalThis, original));
    globalThis.document = { body: { style: { overflow: 'auto' } } };
    globalThis.window = { location: { search: '' } };
    const actions = [];
    const formHandlers = {};
    const pendingClose = [];
    const makeCard = (type) => {
        const dialogHandlers = {};
        const pdfHandlers = {};
        const selectionHandlers = {};
        const options = ['11', '12'].map((id) => ({
            value: id, dataset: { annotationId: id, pdfUrl: '/pdf/' + type },
        }));
        const selector = {
            value: '11', options,
            get selectedOptions() { return options.filter((option) => option.value === this.value); },
            addEventListener(name, fn) { selectionHandlers[name] = fn; },
        };
        const bodies = options.map((option) => ({ dataset: { revisionCommentBody: option.value }, hidden: false }));
        const dialog = {
            open: false,
            showModal() { this.open = true; },
            close() { this.open = false; pendingClose.push(() => dialogHandlers.close()); },
            addEventListener(name, fn) { dialogHandlers[name] = fn; },
        };
        const pdfApi = { focus(id) { actions.push(type + ' PDF ' + id); } };
        const pdfFrame = {
            src: '', contentWindow: { athenaRevisionPdf: pdfApi },
            getAttribute() { return this.src; },
            addEventListener(name, fn) { pdfHandlers[name] = fn; },
        };
        const pdfLoading = { hidden: false };
        const editorLoading = { hidden: false };
        const editorApi = { topicId: '3', documentType: type, focus(id) { actions.push(type + ' editor ' + id); } };
        const editorFrame = { contentWindow: { athenaRevisionEditor: editorApi }, closest: () => card, addEventListener() {} };
        const nodes = {
            '[data-revision-dialog]': dialog,
            '[data-revision-pdf-frame]': pdfFrame,
            '[data-revision-editor-frame]': editorFrame,
            '[data-revision-pdf-loading]': pdfLoading,
            '[data-revision-editor-loading]': editorLoading,
            '[data-revision-comment]': selector,
            '[data-revision-pdf-unavailable]': {},
            '[data-revision-previous]': {},
            '[data-revision-next]': {},
            '[data-revision-open]': { focus() { actions.push('restore focus'); } },
        };
        const card = {
            dataset: { revisionDocument: type },
            querySelector: (key) => nodes[key],
            querySelectorAll: () => bodies,
        };
        return { card, dialog, selector, editorFrame, pdfFrame, pdfApi, pdfHandlers, selectionHandlers, bodies, pdfLoading, editorLoading };
    };
    const first = makeCard('work_plan');
    const second = makeCard('line_item_budget');
    const form = {
        querySelectorAll: () => [first.card, second.card],
        addEventListener(name, fn) { formHandlers[name] = fn; },
    };
    const workspace = initializeRevisionDialogs(form, '3');
    workspace.open(first.card);
    assert.equal(first.dialog.open, true);
    assert.equal(first.card.dataset.revisionReviewed, 'true');
    assert.equal(first.pdfFrame.src, '/pdf/work_plan');
    assert.equal(first.pdfLoading.hidden, false);
    assert.equal(first.editorLoading.hidden, true);
    first.pdfHandlers.load();
    assert.equal(first.pdfLoading.hidden, true);
    assert.equal(first.bodies[0].hidden, false);
    assert.equal(first.bodies[1].hidden, true);
    first.selector.value = '12';
    first.selectionHandlers.change();
    assert.equal(first.bodies[1].hidden, false);
    assert.ok(actions.includes('work_plan PDF 12'));
    assert.ok(actions.includes('work_plan editor 12'));
    first.pdfApi.onSelect('11');
    assert.equal(first.selector.value, '11');
    const sameEditor = first.editorFrame.contentWindow.athenaRevisionEditor;
    workspace.open(second.card);
    pendingClose.shift()();
    assert.equal(first.dialog.open, false);
    assert.equal(second.dialog.open, true);
    assert.equal(document.body.style.overflow, 'hidden');
    workspace.close(second.card);
    pendingClose.shift()();
    assert.equal(document.body.style.overflow, 'auto');
    workspace.open(first.card);
    assert.equal(first.editorFrame.contentWindow.athenaRevisionEditor, sameEditor);
    assert.equal(first.pdfFrame.src, '/pdf/work_plan');
    workspace.close(first.card);
    pendingClose.shift()();
    assert.equal(document.body.style.overflow, 'auto');
});


test('change tracking distinguishes edited values, checked controls and reverted fields', () => {
    const text = { type: 'text', value: 'June' };
    assert.equal(revisionControlFingerprint(text), 'June');
    text.value = 'July';
    assert.equal(revisionControlFingerprint(text), 'July');
    text.value = 'June';
    assert.equal(revisionControlFingerprint(text), 'June');
    assert.equal(revisionControlFingerprint({ type: 'checkbox', checked: false, value: '8' }), 'unchecked');
    assert.equal(revisionControlFingerprint({ type: 'checkbox', checked: true, value: '8' }), 'checked:8');
    assert.equal(revisionSourceControlFingerprint({ type: 'text' }, 'June'), 'June');
    assert.equal(revisionSourceControlFingerprint({ type: 'text' }, null), '');
    assert.equal(revisionSourceControlFingerprint({ type: 'checkbox', value: '8' }, [1, 8, 12]), 'checked:8');
    assert.equal(revisionSourceControlFingerprint({ type: 'checkbox', value: '9' }, [1, 8, 12]), 'unchecked');
});

test('linked feedback and document badges distinguish review from actual changes', () => {
    const statuses = [
        { dataset: { annotationId: '11' }, textContent: '' },
        { dataset: { annotationId: '12' }, textContent: '' },
        { dataset: { annotationId: '' }, textContent: '' },
    ];
    const documentStatus = { dataset: {}, textContent: '' };
    const card = {
        dataset: { revisionReviewed: 'true' },
        querySelectorAll: () => statuses,
        querySelector: () => documentStatus,
    };
    assert.equal(applyRevisionModificationStates(card, { 11: true, 12: false, __document__: true }), true);
    assert.equal(statuses[0].textContent, 'Changes detected');
    assert.equal(statuses[1].textContent, 'Reviewed — no change detected');
    assert.equal(statuses[2].textContent, 'Changes detected');
    assert.equal(documentStatus.textContent, 'Changes detected');
    assert.equal(applyRevisionModificationStates(card, {}, true), true);
    assert.ok(statuses.every((status) => status.textContent === 'Replacement selected'));
    assert.equal(documentStatus.textContent, 'Replacement selected');
});


test('submission identifies requested documents that still need a resolution', () => {
    const status = (addressed) => ({ dataset: { addressed: String(addressed) } });
    const card = (addressed) => ({
        querySelector: (selector) => selector === '[data-revision-document-state]' ? status(addressed) : null,
    });
    const unresolvedWorkPlan = card(false);
    const changedBudget = card(true);
    const explainedProposal = card(true);
    const form = {
        querySelectorAll: (selector) => selector === '[data-revision-document]'
            ? [unresolvedWorkPlan, changedBudget, explainedProposal]
            : [],
    };

    assert.deepEqual(revisionDocumentsWithoutResolution(form), [unresolvedWorkPlan]);
});

test('workspace-only version metadata does not create a false change badge', () => {
    const controls = [
        { type: 'text', name: 'project_title', id: 'project-title', value: 'Fruit Drop Detection', disabled: false },
        { type: 'hidden', name: 'draft_version', id: '', value: '6', disabled: false },
        { type: 'hidden', name: 'methodology_images_present', id: '', value: '1', disabled: false },
        { type: 'hidden', name: 'staff', id: '', value: '', disabled: false },
        { type: 'hidden', name: 'literature_research_history', id: '', value: '[]', disabled: false },
        { type: 'hidden', name: 'literature_citations', id: '', value: '[]', disabled: false },
        { type: 'text', name: 'staff[0][name]', id: 'staff-name-1', value: 'Faculty Member', disabled: false },
    ];
    const form = { matches: () => false, querySelectorAll: () => controls };
    const documentRoot = { getElementById: () => null, querySelector: () => form };
    const returnedSource = {
        project_title: 'Fruit Drop Detection',
        staff: [{ name: 'Faculty Member' }],
        literature_research_history: '[{"query":"original"}]',
        literature_citations: '[{"id":"original"}]',
    };

    assert.equal(
        revisionCurrentSourceFingerprint(documentRoot, null, returnedSource),
        revisionSourceFingerprint(documentRoot, null, returnedSource),
    );
});

test('an explanation resolves a comment without pretending the file was modified', () => {
    const checkbox = { checked: true };
    const explanation = { value: 'The existing value already satisfies this informational comment.' };
    const documentStatus = { dataset: {}, textContent: '' };
    const feedbackStatus = { dataset: { annotationId: '11' }, textContent: '' };
    const card = {
        querySelectorAll: () => [feedbackStatus],
        querySelector(selector) {
            if (selector === '[data-revision-no-change]') return checkbox;
            if (selector === '[data-revision-no-change-explanation]') return explanation;
            if (selector === '[data-revision-document-state]') return documentStatus;
            return null;
        },
    };

    assert.deepEqual(revisionNoChangeResolution(card), { selected: true, complete: true });
    assert.equal(applyRevisionModificationStates(card, {}), false);
    assert.equal(documentStatus.dataset.modified, 'false');
    assert.equal(documentStatus.dataset.addressed, 'true');
    assert.equal(documentStatus.textContent, 'Explained — no file change');
    assert.equal(feedbackStatus.textContent, 'Explained — no file change');
});

test('linked-field state compares with the returned proposal after autosaved edits and page reloads', () => {
    const control = {
        type: 'text', name: 'entries[0][activity]', id: 'activity-1', value: 'Moved to July',
        disabled: false, matches: () => true, querySelectorAll: () => [],
    };
    const form = { matches: () => false, querySelectorAll: () => [control] };
    const documentRoot = {
        getElementById: () => control,
        querySelector: () => form,
    };
    const returnedSource = { entries: [{ activity: 'Held in June' }] };
    const baseline = revisionSourceFingerprint(documentRoot, 'activity-1', returnedSource);
    assert.notEqual(revisionCurrentSourceFingerprint(documentRoot, 'activity-1', returnedSource), baseline);
    control.value = 'Held in June';
    assert.equal(revisionCurrentSourceFingerprint(documentRoot, 'activity-1', returnedSource), baseline);
});

test('document state detects an added or removed repeating row', () => {
    const row = (index) => ({
        type: 'text', name: 'entries[' + index + '][activity]', id: 'activity-' + (index + 1),
        value: 'Activity ' + (index + 1), disabled: false,
    });
    const controls = [row(0)];
    const form = { matches: () => false, querySelectorAll: () => controls };
    const documentRoot = { getElementById: () => null, querySelector: () => form };
    const returnedSource = { entries: [{ activity: 'Activity 1' }, { activity: 'Activity 2' }] };
    assert.notEqual(
        revisionCurrentSourceFingerprint(documentRoot, null, returnedSource),
        revisionSourceFingerprint(documentRoot, null, returnedSource),
    );
    controls.push(row(1));
    assert.equal(
        revisionCurrentSourceFingerprint(documentRoot, null, returnedSource),
        revisionSourceFingerprint(documentRoot, null, returnedSource),
    );
});
