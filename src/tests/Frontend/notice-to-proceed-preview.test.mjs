import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { runInNewContext } from 'node:vm';
import { proposalPreviewWorkspace } from '../../resources/js/proposal-preview-workspace.js';

const app = readFileSync(new URL('../../resources/js/app.js', import.meta.url), 'utf8');
const source = app.slice(
    app.indexOf("Alpine.data('noticeToProceedForm',"),
    app.indexOf("Alpine.data('workPlanWizard',"),
);
const previewResponse = (html = '<main>Current unsigned notice</main>') => ({
    ok: true,
    headers: { get: () => 'text/html; charset=UTF-8' },
    text: async () => html,
});

function createForm({ response = previewResponse(), valid = true } = {}) {
    const requests = [];
    const listeners = {};
    const form = {
        values: { resolution_number: 'LREC-01' },
        reportValidity: () => valid,
        addEventListener: (name, callback) => { listeners[name] = callback; },
    };
    class FormData {
        constructor(element) { this.values = { ...element.values }; }
        entries() { return Object.entries(this.values); }
    }
    let factory;
    runInNewContext(source, {
        Alpine: { data: (name, callback) => { factory = callback; } },
        proposalPreviewWorkspace,
        FormData,
        HTMLFormElement: Object,
        Error,
        window: { clearTimeout() {} },
        fetch: async (url, options) => {
            requests.push({ url, ...options });
            return typeof response === 'function' ? response() : response;
        },
    });
    const state = factory({ previewUrl: '/notice/preview', csrfToken: 'csrf-token' });
    state.$el = { querySelector: () => form };
    state.$refs = { form };
    state.$nextTick = callback => callback();
    return { state, form, requests, listeners };
}

test('opening the shared panel previews current values without submitting the notice', async () => {
    const { state, requests } = createForm();
    let pending;
    const generate = state.generatePreview.bind(state);
    state.generatePreview = () => { pending = generate(); };
    state.showProposalPreview();
    await pending;

    assert.equal(state.previewPaneOpen, true);
    assert.equal(state.previewHtml, '<main>Current unsigned notice</main>');
    assert.equal(state.previewLoading, false);
    assert.equal(state.submitting, false);
    assert.equal(requests.length, 1);
    assert.equal(requests[0].url, '/notice/preview');
    assert.equal(requests[0].method, 'POST');
    assert.equal(requests[0].body.values.resolution_number, 'LREC-01');
    assert.equal(requests[0].headers['X-CSRF-TOKEN'], 'csrf-token');
});

test('invalid required fields prevent preview generation', async () => {
    const { state, requests } = createForm({ valid: false });
    await state.generatePreview();
    assert.equal(requests.length, 0);
    assert.equal(state.previewLoading, false);
    assert.equal(state.previewReady, false);
});

test('validation failures clear the prior preview and show the returned error', async () => {
    const { state } = createForm({
        response: { status: 422, json: async () => ({ errors: { resolution_number: ['Enter the resolution number.'] } }) },
    });
    state.previewHtml = '<main>Old notice</main>';
    state.previewReady = true;
    await state.generatePreview();
    assert.equal(state.previewHtml, '');
    assert.equal(state.previewReady, false);
    assert.equal(state.previewLoading, false);
    assert.equal(state.previewError, 'Enter the resolution number.');
});

test('failed requests and unexpected responses leave a retryable preview error', async () => {
    for (const response of [
        { ok: false },
        { ...previewResponse(), headers: { get: () => 'application/json' } },
        previewResponse(''),
    ]) {
        const { state } = createForm({ response });
        await state.generatePreview();
        assert.equal(state.previewHtml, '');
        assert.equal(state.previewReady, false);
        assert.equal(state.previewLoading, false);
        assert.match(state.previewError, /could not be generated/);
    }
});

test('edits during generation mark the result stale and refresh uses the corrected details', async () => {
    let resolve;
    let waiting = true;
    const { state, form, requests, listeners } = createForm({
        response: () => waiting ? new Promise(done => { resolve = done; }) : previewResponse('<main>Updated notice</main>'),
    });
    state.triggerNoticeDetailsAutoSave = () => {};
    state.startNoticeDetailsAutoSave();
    const pending = state.generatePreview();
    await state.generatePreview();
    assert.equal(requests.length, 1);
    form.values.resolution_number = 'LREC-02';
    listeners.input();
    resolve(previewResponse());
    await pending;
    assert.equal(state.previewStale, true);

    waiting = false;
    await state.generatePreview();
    assert.equal(requests[1].body.values.resolution_number, 'LREC-02');
    assert.equal(state.previewHtml, '<main>Updated notice</main>');
    assert.equal(state.previewStale, false);
});

test('printing waits for the shared preview frame to finish loading', () => {
    const { state } = createForm();
    const calls = [];
    state.$refs.previewFrame = { contentWindow: {
        focus: () => calls.push('focus'),
        print: () => calls.push('print'),
    } };
    state.printPreview();
    assert.deepEqual(calls, []);
    state.previewHtml = '<main>Notice</main>';
    state.proposalPreviewLoaded();
    state.printPreview();
    assert.deepEqual(calls, ['focus', 'print']);
});
