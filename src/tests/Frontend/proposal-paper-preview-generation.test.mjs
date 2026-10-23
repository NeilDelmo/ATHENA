import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { runInNewContext } from 'node:vm';
import { proposalPreviewWorkspace } from '../../resources/js/proposal-preview-workspace.js';

const app = (readFileSync(new URL('../../resources/js/app.js', import.meta.url), 'utf8') + '\n' + readFileSync(new URL('../../resources/js/detailed-proposal-editor.js', import.meta.url), 'utf8').replace(/^ {4}/gm, ''));
const workspace = readFileSync(new URL('../../resources/js/proposal-paper-workspace.js', import.meta.url), 'utf8')
    .replace(/^import .*;\r?\n/gm, '')
    .replace(/^export /gm, '');
const detailedWorkspace = readFileSync(new URL('../../resources/js/detailed-proposal-workspace.js', import.meta.url), 'utf8')
    .replace(/^import .*;\r?\n/gm, '')
    .replace(/^export /gm, '');
const papers = [
    'proposalDraftDetailedProposal', 'proposalDraftWorkPlan', 'proposalDraftLineItemBudget',
    'proposalDraftExpenseBreakdown', 'proposalDraftCurriculumVitae',
];
const earlierHtml = '<p>Last good paper</p>';
const currentHtml = '<p>Current paper</p>';
const flushPreview = () => new Promise((resolve) => setImmediate(resolve));

function editor(t, name) {
    t.mock.timers.enable({ apis: ['setTimeout'] });
    const componentStart = app.indexOf(`Alpine.data('${name}',`);
    const methodStart = app.indexOf('    async generatePreview()', componentStart);
    const method = app.slice(methodStart, app.indexOf('    async downloadDocument()', methodStart));
    const requests = [];
    let content = 'Original form';
    const state = runInNewContext(`${workspace}\n${detailedWorkspace}\n(config => ({
        ...${name === 'proposalDraftDetailedProposal' ? 'detailedProposalPreviewWorkspace' : 'proposalPaperPreviewWorkspace'}(),
        ${method}
    }))`, {
        proposalPreviewWorkspace,
        window: { setTimeout, clearTimeout },
        Error,
        fetch: (url, options) => new Promise((resolve, reject) => requests.push({ url, options, resolve, reject })),
    })({ previewUrl: '/preview', csrfToken: 'csrf-test' });
    const formData = () => {
        const form = new FormData();
        form.set('content', content);
        return form;
    };
    Object.assign(state, {
        previewHtml: earlierHtml,
        previewReady: true,
        previewLoading: false,
        previewPaneOpen: true,
        previewStale: true,
        previewError: '',
        validationMessage: '',
        $refs: {},
        $el: { closest: () => null },
        formData,
        workPlanFormData: formData,
    });
    return {
        state,
        requests,
        edit() {
            content = 'Revised form';
            state.markProposalPreviewStale();
        },
    };
}

function htmlResponse(html = currentHtml) {
    return new Response(html, { status: 200, headers: { 'Content-Type': 'text/html' } });
}

function validationResponse() {
    return Response.json({ message: 'Validation failed', errors: { content: ['Content is required.'] } }, { status: 422 });
}

for (const name of papers) {
    test(`${name} displays a successful preview for the current form revision`, async (t) => {
        const { state, requests } = editor(t, name);
        const generation = state.generatePreview();
        assert.equal(state.previewLoading, true);
        assert.equal(requests[0].url, '/preview');
        assert.equal(requests[0].options.method, 'POST');
        assert.equal(requests[0].options.headers['X-CSRF-TOKEN'], 'csrf-test');
        assert.equal(requests[0].options.body.get('content'), 'Original form');
        requests[0].resolve(htmlResponse());
        await generation;
        assert.equal(state.previewHtml, currentHtml);
        assert.equal(state.previewLoading, false);
        assert.equal(state.previewReady, true);
        assert.equal(state.previewStale, false);
        t.mock.timers.tick(5000);
        assert.equal(requests.length, 1);
    });

    test(`${name} preserves its last good paper when an older revision succeeds`, async (t) => {
        const fixture = editor(t, name);
        const generation = fixture.state.generatePreview();
        fixture.edit();
        t.mock.timers.tick(1200);
        assert.equal(fixture.requests.length, 1);
        fixture.requests[0].resolve(htmlResponse('<p>Obsolete form</p>'));
        await generation;
        assert.equal(fixture.state.previewHtml, earlierHtml);
        assert.equal(fixture.state.previewReady, true);
        assert.equal(fixture.state.previewStale, true);
        t.mock.timers.tick(1200);
        assert.equal(fixture.requests.length, 2);
        assert.equal(fixture.requests[1].options.body.get('content'), 'Revised form');
        fixture.requests[1].resolve(htmlResponse());
        await flushPreview();
        assert.equal(fixture.state.previewHtml, currentHtml);
        assert.equal(fixture.state.previewStale, false);
    });

    test(`${name} rejects an older preview when the form changes during its response body download`, async (t) => {
        const fixture = editor(t, name);
        let body;
        const response = new Response(new ReadableStream({ start(controller) { body = controller; } }),
            { status: 200, headers: { 'Content-Type': 'text/html' } });
        const generation = fixture.state.generatePreview();
        fixture.requests[0].resolve(response);
        await flushPreview();
        fixture.edit();
        t.mock.timers.tick(1200);
        body.enqueue(new TextEncoder().encode('<p>Obsolete streamed form</p>'));
        body.close();
        await generation;
        assert.equal(fixture.state.previewHtml, earlierHtml);
        t.mock.timers.tick(1200);
        assert.equal(fixture.requests.length, 2);
        fixture.requests[1].resolve(htmlResponse());
        await flushPreview();
        assert.equal(fixture.state.previewHtml, currentHtml);
    });

    for (const failure of ['validation', 'network']) {
        for (const changed of [false, true]) {
            test(`${name} ${changed ? 'refreshes an edited form' : 'does not retry an unchanged form'} after a ${failure} failure`, async (t) => {
                const fixture = editor(t, name);
                const generation = fixture.state.generatePreview();
                if (changed) fixture.edit();
                t.mock.timers.tick(1200);
                assert.equal(fixture.requests.length, 1);
                if (failure === 'validation') fixture.requests[0].resolve(validationResponse());
                else fixture.requests[0].reject(new Error('Network unavailable'));
                await generation;
                assert.equal(fixture.state.previewHtml, earlierHtml);
                assert.equal(fixture.state.previewReady, true);
                assert.equal(fixture.state.previewLoading, false);
                assert.equal(fixture.state.previewStale, true);
                if (changed) {
                    assert.equal(fixture.state.previewError, '');
                    assert.equal(fixture.state.validationMessage, '');
                }
                t.mock.timers.tick(1200);
                assert.equal(fixture.requests.length, changed ? 2 : 1);
                if (changed) {
                    assert.equal(fixture.requests[1].options.body.get('content'), 'Revised form');
                    fixture.requests[1].resolve(htmlResponse());
                    await flushPreview();
                    assert.equal(fixture.state.previewHtml, currentHtml);
                    assert.equal(fixture.state.previewError, '');
                    assert.equal(fixture.state.validationMessage, '');
                } else {
                    assert.match(failure === 'validation' ? fixture.state.validationMessage : fixture.state.previewError,
                        failure === 'validation' ? /Content is required/ : /Network unavailable/);
                    if (failure === 'validation') {
                        fixture.state.validationMessage = '';
                        assert.equal(fixture.state.previewError, 'Content is required.');
                    }
                }
                t.mock.timers.tick(5000);
                assert.equal(fixture.requests.length, changed ? 2 : 1);
            });
        }
    }

    test(`${name} does not start another request while preview generation is pending`, async (t) => {
        const fixture = editor(t, name);
        const generation = fixture.state.generatePreview();
        fixture.edit();
        await fixture.state.generatePreview();
        t.mock.timers.tick(1200);
        assert.equal(fixture.requests.length, 1);
        assert.equal(fixture.requests[0].options.body.get('content'), 'Original form');
        fixture.requests[0].resolve(htmlResponse());
        await generation;
        assert.equal(fixture.state.previewLoading, false);
    });
}
