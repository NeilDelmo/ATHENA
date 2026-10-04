import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { chromium } from '@playwright/test';
import postcss from 'postcss';
import tailwindcss from 'tailwindcss';
import loadConfig from 'tailwindcss/loadConfig.js';
import initializeRevisionWorkspace, { synchronizeRevisionNoChangeResponses, initializeRevisionPanelResize, initializeRevisionWorkflow, applyRevisionModificationStates, createRevisionSubmissionWatchdog, initializeRevisionDialogs, prepareRevisionEditors, revealRevisionEditorFailure, REVISION_OPERATION_TIMEOUT_MS, REVISION_SUBMISSION_TIMEOUT_MS, revisionControlFingerprint, revisionCurrentSourceFingerprint, revisionDocumentsWithoutResolution, revisionNoChangeResolution, revisionSourceControlFingerprint, revisionSourceFingerprint, revisionEditorForFrame, embeddedRevisionFileSaved } from '../../resources/js/revision-workspace.js';

test('comment response locations require numbers unless no change was made, with independent accessible controls', async () => {
    const blade = readFileSync(new URL('../../resources/views/components/proposal-revision-form.blade.php', import.meta.url), 'utf8');
    const start = blade.indexOf('<fieldset data-comment-response-location');
    const markup = blade.slice(start, blade.indexOf('</fieldset>', start) + '</fieldset>'.length)
        .replace(/@js\([^\n]*\)/, 'false')
        .replace(/@(?:checked|disabled)\([^\n]*\)/g, '')
        .replace(/\s*@if[\s\S]*?@endif/g, '')
        .replaceAll("{{ $item['key'] }}", 'overall')
        .replace(/\{\{[\s\S]*?\}\}/g, '');
    const styles = await postcss([tailwindcss(loadConfig(resolve('tailwind.config.js')))])
        .process(readFileSync(new URL('../../resources/css/app.css', import.meta.url), 'utf8'), { from: undefined });
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        for (const width of [1440, 390]) {
            for (const dark of [false, true]) {
                const page = await browser.newPage({ viewport: { width, height: 1000 } });
                const errors = [];
                page.on('pageerror', (error) => errors.push(error.message));
                await page.setContent(`<html class="${dark ? 'dark' : ''}"><style>${styles.css}</style><body class="bg-white dark:bg-slate-900">
                    <form class="mx-auto max-w-3xl space-y-4 p-5">
                        <article><label>Action and Response<textarea required name="feedback_responses[overall][response]"></textarea></label>${markup}</article>
                        <article><label>Action and Response<textarea required name="feedback_responses[annotation_1][response]"></textarea></label>${markup.replaceAll('overall', 'annotation_1')}</article>
                    </form></body></html>`);
                await page.addScriptTag({ path: resolve('node_modules/alpinejs/dist/cdn.min.js') });
                const form = page.locator('form');
                const first = page.locator('article').first();
                const second = page.locator('article').last();
                const valid = () => form.evaluate((element) => element.checkValidity());
                assert.equal(await valid(), false);
                await first.locator('textarea').fill('Revised recruitment.');
                await second.locator('textarea').fill('The existing schedule already addresses this comment.');
                assert.equal(await valid(), false);
                await first.getByLabel('Page', { exact: true }).fill('4');
                await first.getByLabel('Paragraph', { exact: true }).fill('2');
                assert.equal(await valid(), false);
                await second.getByLabel('No change made', { exact: true }).check();
                await page.waitForFunction(() => document.querySelectorAll('[data-comment-response-page]')[1].disabled);
                assert.equal(await valid(), true);
                assert.equal(await first.locator('[data-comment-response-page]').isDisabled(), false);
                assert.equal(await second.locator('[data-comment-response-paragraph]').isDisabled(), true);
                await second.locator('textarea').fill('');
                assert.equal(await valid(), false);
                await second.locator('textarea').fill('The existing schedule already addresses this comment.');
                await first.getByLabel('No change made', { exact: true }).check();
                await first.getByLabel('No change made', { exact: true }).uncheck();
                await page.waitForFunction(() => !document.querySelector('[data-comment-response-page]').disabled);
                assert.equal(await first.locator('[data-comment-response-page]').inputValue(), '4');
                await first.getByLabel('Page', { exact: true }).fill('0');
                assert.equal(await valid(), false);
                await first.getByLabel('Page', { exact: true }).fill('4');
                await first.getByLabel('Paragraph', { exact: true }).fill('2.5');
                assert.equal(await valid(), false);
                await first.getByLabel('Paragraph', { exact: true }).fill('2');
                assert.equal(await valid(), true);
                const entries = await form.evaluate((element) => [...new FormData(element).entries()]);
                assert.equal(Object.fromEntries(entries)['feedback_responses[overall][page]'], '4');
                assert.equal(Object.fromEntries(entries)['feedback_responses[overall][paragraph]'], '2');
                assert.equal(Object.fromEntries(entries)['feedback_responses[annotation_1][no_change]'], '1');
                assert.equal(entries.some(([key]) => key === 'feedback_responses[annotation_1][page]'), false);
                assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), true);
                const background = await first.locator('[data-comment-response-page]').evaluate((element) => getComputedStyle(element).backgroundColor);
                assert.equal(background, dark ? 'rgb(2, 6, 23)' : 'rgb(255, 255, 255)');
                assert.deepEqual(errors, []);
                await page.close();
            }
        }
    } finally {
        await browser.close();
    }
});

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
    const documentState = { dataset: { modified: 'false', addressed: 'false' } };
    const api = {
        topicId: '3', draftId: 8, documentType: 'work_plan',
        modificationStates: () => ({ __document__: true }),
        async focus(id) { events.push('focus ' + id); return true; },
        async save() { events.push('save ' + field.value); return true; },
        async prepare() { events.push('prepare'); return { filename: 'updated.docx', draft_id: 8 }; },
        release() { events.push('release'); },
    };
    const card = {
        dataset: { revisionDocument: 'work_plan', revisionLabel: 'Work Plan' },
        querySelectorAll: () => [],
        querySelector(selector) {
            if (selector === 'input[type="file"]') return null;
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
    assert.equal(card.querySelector('input[type="file"]'), null);
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
        const editorHandlers = {};
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
        const editorApi = { topicId: '3', documentType: type, focus(id, options) { actions.push(type + ' editor ' + id); this.pendingFocus = options; } };
        const editorFrame = { contentWindow: { athenaRevisionEditor: editorApi }, closest: () => card, addEventListener(name, fn) { editorHandlers[name] = fn; } };
        const nodes = {
            '[data-revision-no-change]': { checked: false },
            '[data-revision-no-change-explanation]': { value: '' },
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
        return { card, dialog, selector, editorFrame, pdfFrame, pdfApi, pdfHandlers, selectionHandlers, editorHandlers, nodes, bodies, pdfLoading, editorLoading };
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
    // A delayed iframe load and previously queued annotation focus cannot interrupt typing.
    const pendingFocus = first.editorFrame.contentWindow.athenaRevisionEditor.pendingFocus;
    document.activeElement = { closest: () => first.nodes['[data-revision-no-change-explanation]'] };
    assert.equal(pendingFocus.canFocus(), false);
    first.nodes['[data-revision-no-change]'].checked = true;
    const focusCount = actions.filter((action) => action.includes(' editor ')).length;
    first.editorHandlers.load();
    first.nodes['[data-revision-no-change-explanation]'].value = 'The existing objectives already address this comment.';
    formHandlers.input({ target: {
        matches: () => true, closest: () => first.card,
    } });
    first.selectionHandlers.change();
    assert.equal(actions.filter((action) => action.includes(' editor ')).length, focusCount);
    first.nodes['[data-revision-no-change]'].checked = false;
    document.activeElement = null;
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

test('draft edit states distinguish edited fields from untouched fields', () => {
    const statuses = [
        { dataset: { annotationId: '11' }, textContent: '' },
        { dataset: { annotationId: '12' }, textContent: '' },
        { dataset: { annotationId: '' }, textContent: '' },
    ];
    const documentStatus = { dataset: {}, textContent: '' };
    const resolvedCue = { hidden: true };
    const card = {
        dataset: { revisionReviewed: 'true' },
        querySelectorAll: () => statuses,
        querySelector: (selector) => selector === '[data-revision-resolved-cue]' ? resolvedCue : documentStatus,
    };
    assert.equal(applyRevisionModificationStates(card, { 11: true, 12: false, __document__: true }), true);
    assert.equal(statuses[0].textContent, 'Draft edited');
    assert.equal(statuses[1].textContent, 'No draft edits');
    assert.equal(statuses[2].textContent, 'Draft edited');
    assert.equal(documentStatus.textContent, 'Draft edited');
    assert.equal(resolvedCue.hidden, false);
    assert.equal(applyRevisionModificationStates(card, {}, true), true);
    assert.ok(statuses.every((status) => status.textContent === 'Replacement selected'));
    assert.equal(documentStatus.textContent, 'Replacement selected');
    assert.equal(resolvedCue.hidden, false);
    assert.equal(applyRevisionModificationStates(card), false);
    assert.equal(resolvedCue.hidden, true);
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

test('submission reads current highlighted edits even when the change notification has not arrived', () => {
    let modified = true;
    const status = { dataset: { addressed: 'false' } };
    const card = {
        dataset: { revisionDocument: 'detailed_proposal' },
        querySelectorAll: () => [],
        querySelector(selector) {
            if (selector === '[data-revision-editor-frame]') return frame;
            if (selector === '[data-revision-document-state]') return status;
            return null;
        },
    };
    const frame = {
        closest: () => card,
        contentWindow: { athenaRevisionEditor: {
            topicId: '7', documentType: 'detailed_proposal',
            modificationStates: () => ({ 13: modified, __document__: modified }),
        } },
    };
    const form = { dataset: { revisionWorkspace: '7' }, querySelectorAll: () => [card] };
    assert.deepEqual(revisionDocumentsWithoutResolution(form), []);
    assert.equal(status.dataset.addressed, 'true');
    modified = false;
    assert.deepEqual(revisionDocumentsWithoutResolution(form), [card]);
    assert.equal(status.dataset.addressed, 'false');
});

test('submission recognizes a replacement selected before its change handler runs', () => {
    const status = { dataset: { addressed: 'false' } };
    const card = {
        dataset: { revisionDocument: 'gad_checklist' },
        querySelectorAll: () => [],
        querySelector(selector) {
            if (selector === 'input[type="file"]') return { files: [{ name: 'revised.pdf' }] };
            if (selector === '[data-revision-document-state]') return status;
            return null;
        },
    };
    const form = { dataset: { revisionWorkspace: '7' }, querySelectorAll: () => [card] };
    assert.deepEqual(revisionDocumentsWithoutResolution(form), []);
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

test('a no-change explanation reveals the paper cue only after it is complete', () => {
    const checkbox = { checked: true };
    const explanation = { value: '' };
    const documentStatus = { dataset: {}, textContent: '' };
    const feedbackStatus = { dataset: { annotationId: '11' }, textContent: '' };
    const resolvedCue = { hidden: true };
    const card = {
        querySelectorAll: () => [feedbackStatus],
        querySelector(selector) {
            if (selector === '[data-revision-no-change]') return checkbox;
            if (selector === '[data-revision-no-change-explanation]') return explanation;
            if (selector === '[data-revision-document-state]') return documentStatus;
            if (selector === '[data-revision-resolved-cue]') return resolvedCue;
            return null;
        },
    };

    assert.deepEqual(revisionNoChangeResolution(card), { selected: true, complete: false });
    assert.equal(applyRevisionModificationStates(card, {}), false);
    assert.equal(documentStatus.dataset.addressed, 'false');
    assert.equal(resolvedCue.hidden, true);
    applyRevisionModificationStates(card, { __document__: true, 11: true });
    assert.equal(documentStatus.dataset.addressed, 'false');
    assert.equal(resolvedCue.hidden, true);
    explanation.value = 'The existing value already satisfies this informational comment.';
    assert.deepEqual(revisionNoChangeResolution(card), { selected: true, complete: true });
    assert.equal(applyRevisionModificationStates(card, {}), false);
    assert.equal(documentStatus.dataset.modified, 'false');
    assert.equal(documentStatus.dataset.addressed, 'true');
    assert.equal(resolvedCue.hidden, false);
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

function workflowFixture({ unresolved = false, responseComplete = true, confirmed = false } = {}) {
    const listeners = {};
    const button = () => ({ hidden: false, addEventListener(type, fn) { this[type] = fn; } });
    const next = button();
    const back = button();
    const progress = {};
    const error = {};
    const confirmation = { checked: confirmed, checkValidity() { return this.checked; }, reportValidity() {}, focus() {} };
    const response = { checkValidity: () => responseComplete, reportValidity() {}, focus() {} };
    const panels = ['Read feedback', 'Revise papers', 'Action and Response', 'Confirm details', 'Submit'].map((label, index) => ({
        dataset: { revisionStep: String(index + 1), revisionStepLabel: label },
        querySelectorAll: () => index === 2 ? [response] : (index === 3 ? [confirmation] : []),
        setAttribute() {}, focus() {}, scrollIntoView() {},
    }));
    const status = { dataset: { addressed: String(!unresolved) } };
    const card = { dataset: { revisionLabel: 'Work Plan' }, querySelector: () => status };
    const form = {
        dataset: { revisionStartStep: '1' },
        querySelectorAll(selector) {
            if (selector === '[data-revision-step]') return panels;
            if (selector === '[data-revision-document]') return [card];
            return [];
        },
        querySelector(selector) {
            return { '[data-revision-progress]': progress, '[data-revision-step-back]': back,
                '[data-revision-step-continue]': next, '[data-revision-step-error]': error,
                '[data-revision-details-confirmed]': confirmation }[selector];
        },
        addEventListener(type, fn) { listeners[type] = fn; },
        dispatchEvent(event) { listeners[event.type]?.(event); return true; },
    };
    return { form, panels, next, back, confirmation, status, listeners, error };
}

test('workflow gates paper actions, then responses, then confirmation before submission', () => {
    const previousWindow = globalThis.window;
    globalThis.window = { location: { search: '', hash: '' } };
    try {
        const fixture = workflowFixture({ unresolved: true });
        const workflow = initializeRevisionWorkflow(fixture.form);
        let opened = 0;
        workflow.setDialogs({ open() { opened++; } });
        assert.deepEqual(fixture.panels.map((panel) => panel.hidden), [false, true, true, true, true]);
        fixture.next.click();
        fixture.next.click();
        assert.equal(opened, 1);
        assert.equal(fixture.panels[1].hidden, false);
        fixture.status.dataset.addressed = 'true';
        fixture.next.click();
        assert.equal(fixture.panels[2].hidden, false);
        fixture.next.click();
        fixture.next.click();
        assert.equal(fixture.panels[3].hidden, false);
        assert.equal(fixture.error.hidden, false);
        fixture.confirmation.checked = true;
        fixture.next.click();
        assert.equal(workflow.canSubmit(), true);
        fixture.listeners.input({ target: { closest: () => ({}) } });
        assert.equal(fixture.confirmation.checked, false);
        assert.equal(workflow.canSubmit(), false);
        assert.equal(fixture.panels[3].hidden, false);
        fixture.back.click();
        assert.equal(fixture.panels[2].hidden, false);
    } finally { globalThis.window = previousWindow; }
});

test('workflow keeps incomplete responses on the response step and Enter cannot submit early', () => {
    const previousWindow = globalThis.window;
    globalThis.window = { location: { search: '', hash: '' } };
    try {
        const fixture = workflowFixture({ responseComplete: false });
        const workflow = initializeRevisionWorkflow(fixture.form);
        assert.equal(workflow.canSubmit(), false);
        assert.equal(fixture.panels[1].hidden, false);
        fixture.next.click();
        fixture.next.click();
        assert.equal(fixture.panels[2].hidden, false);
        assert.match(fixture.error.textContent, /page and paragraph numbers/);
    } finally { globalThis.window = previousWindow; }
});

test('a linked annotation or staged upload returns to papers without bypassing confirmation', () => {
    const previousWindow = globalThis.window;
    try {
        for (const location of [{ search: '?revision_annotation=12', hash: '' }, { search: '', hash: '#review-and-submit' }]) {
            globalThis.window = { location };
            const fixture = workflowFixture();
            initializeRevisionWorkflow(fixture.form);
            assert.equal(fixture.panels[1].hidden, false);
            assert.equal(fixture.confirmation.checked, false);
        }
    } finally { globalThis.window = previousWindow; }
});

test('no-change panel expands upward, shrinks downward and keeps room for the editor', () => {
    const handlers = {};
    const classes = new Set();
    let captured;
    const panel = { style: {}, getBoundingClientRect() { return { height: Number.parseFloat(this.style.height || '200') }; } };
    const handle = {
        addEventListener(name, fn) { handlers[name] = fn; },
        setPointerCapture(id) { captured = id; },
    };
    const card = {
        classList: { add: (name) => classes.add(name), remove: (name) => classes.delete(name) },
        querySelector(selector) {
            return { '[data-revision-resolution-resize]': handle, '[data-revision-resolution-panel]': panel,
                '.revision-editor-panel': { clientHeight: 600 } }[selector];
        },
    };
    initializeRevisionPanelResize(card);
    handlers.pointerdown({ button: 0, pointerId: 7, clientY: 400, preventDefault() {} });
    assert.equal(captured, 7);
    assert.equal(classes.has('revision-panel-resizing'), true);
    handlers.pointermove({ clientY: 300 });
    assert.equal(panel.style.height, '300px');
    handlers.pointermove({ clientY: 550 });
    assert.equal(panel.style.height, '96px');
    handlers.pointermove({ clientY: -1000 });
    assert.equal(panel.style.height, '480px');
    handlers.pointerup();
    assert.equal(classes.has('revision-panel-resizing'), false);
    handlers.pointermove({ clientY: 500 });
    assert.equal(panel.style.height, '480px');
    handlers.keydown({ key: 'ArrowDown', preventDefault() {} });
    assert.equal(panel.style.height, '456px');
    handlers.keydown({ key: 'ArrowUp', preventDefault() {} });
    assert.equal(panel.style.height, '480px');
    handlers.pointerdown({ button: 0, pointerId: 8, clientY: 400, preventDefault() {} });
    handlers.pointercancel();
    assert.equal(classes.has('revision-panel-resizing'), false);
});

test('no-change explanations fill matching replies, track edits and preserve faculty-written responses', () => {
    const checkbox = { checked: true };
    const explanation = { value: 'The existing schedule already addresses the comment.' };
    const card = { dataset: { revisionDocument: 'work_plan' }, querySelector(selector) {
        return selector === '[data-revision-no-change]' ? checkbox : explanation;
    } };
    const reply = (type, value = '') => ({ dataset: { revisionResponseDocument: type }, value });
    const replies = [reply('work_plan'), reply('work_plan'), reply('line_item_budget'), reply(''), reply('work_plan', 'My own explanation.')];
    const form = { querySelectorAll: () => replies };
    synchronizeRevisionNoChangeResponses(form, card);
    assert.equal(replies[0].value, explanation.value);
    assert.equal(replies[1].value, explanation.value);
    assert.equal(replies[2].value, '');
    assert.equal(replies[3].value, '');
    assert.equal(replies[4].value, 'My own explanation.');
    replies[1].value = 'The schedule on page 2 already includes June fieldwork.';
    explanation.value = 'The current schedule remains correct.';
    synchronizeRevisionNoChangeResponses(form, card);
    assert.equal(replies[0].value, explanation.value);
    assert.equal(replies[1].value, 'The schedule on page 2 already includes June fieldwork.');
    checkbox.checked = false;
    synchronizeRevisionNoChangeResponses(form, card);
    assert.equal(replies[0].value, '');
    assert.equal(replies[1].value, 'The schedule on page 2 already includes June fieldwork.');
    checkbox.checked = true;
    synchronizeRevisionNoChangeResponses(form, card);
    assert.equal(replies[0].value, explanation.value);
});
