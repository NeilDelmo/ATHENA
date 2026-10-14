import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';
import { proposalPreviewWorkspace } from '../../resources/js/proposal-preview-workspace.js';

const source = readFileSync(new URL('../../resources/js/proposal-paper-workspace.js', import.meta.url), 'utf8')
    .replace(/^import .*;\r?\n/, '')
    .replace('export function ', 'function ');

function preview(t) {
    t.mock.timers.enable({ apis: ['setTimeout'] });
    let requests = 0;
    let embedded = false;
    let desktop = true;
    const context = vm.createContext({
        proposalPreviewWorkspace,
        window: { setTimeout, clearTimeout, matchMedia: () => ({ matches: desktop }) },
    });
    vm.runInContext(`${source}\nglobalThis.workspace = proposalPaperPreviewWorkspace();`, context);
    const state = Object.assign(context.workspace, {
        previewPaneOpen: true,
        previewHtml: '<p>Earlier paper</p>',
        previewReady: true,
        previewStale: true,
        previewLoading: false,
        $refs: {},
        $el: { closest: () => embedded ? {} : null },
        $nextTick: (callback) => callback(),
        generatePreview() {
            requests += 1;
            this.previewPaneOpen = true;
            this.previewLoading = true;
        },
    });
    return {
        state,
        get requests() { return requests; },
        embed() { embedded = true; },
        useMobile() { desktop = false; },
    };
}

test('editing repeatedly requests one preview after the last debounce delay', (t) => {
    const fixture = preview(t);
    fixture.state.markProposalPreviewStale();
    t.mock.timers.tick(1000);
    fixture.state.markProposalPreviewStale();
    t.mock.timers.tick(1199);
    assert.equal(fixture.requests, 0);
    t.mock.timers.tick(1);
    assert.equal(fixture.requests, 1);
});

for (const [label, disable] of [
    ['hidden', ({ state }) => { state.previewPaneOpen = false; }],
    ['disposed', ({ state }) => { state.previewDisposed = true; }],
    ['revision-embedded', (fixture) => fixture.embed()],
]) {
    test(`a ${label} pane does not schedule a preview`, (t) => {
        const fixture = preview(t);
        disable(fixture);
        fixture.state.scheduleProposalPaperPreview();
        t.mock.timers.tick(1200);
        assert.equal(fixture.requests, 0);
    });

    test(`a queued preview does not run after its pane becomes ${label}`, (t) => {
        const fixture = preview(t);
        fixture.state.scheduleProposalPaperPreview();
        disable(fixture);
        t.mock.timers.tick(1200);
        assert.equal(fixture.requests, 0);
    });
}

test('a queued preview skips a paper that became up to date', (t) => {
    const fixture = preview(t);
    fixture.state.scheduleProposalPaperPreview();
    fixture.state.previewStale = false;
    t.mock.timers.tick(1200);
    assert.equal(fixture.requests, 0);
});

test('an empty paper still generates its initial preview', (t) => {
    const fixture = preview(t);
    fixture.state.previewHtml = '';
    fixture.state.previewReady = false;
    fixture.state.previewStale = false;
    fixture.state.scheduleProposalPaperPreview(250);
    t.mock.timers.tick(250);
    assert.equal(fixture.requests, 1);
});

test('a queued refresh does not overlap a pending preview request', (t) => {
    const fixture = preview(t);
    fixture.state.scheduleProposalPaperPreview();
    t.mock.timers.tick(1200);
    fixture.state.markProposalPreviewStale();
    t.mock.timers.tick(1200);
    assert.equal(fixture.requests, 1);
});

for (const method of ['closeProposalPreview', 'destroyProposalPaperPreview']) {
    test(`${method} cancels a queued preview`, (t) => {
        const fixture = preview(t);
        fixture.state.scheduleProposalPaperPreview();
        fixture.state[method]();
        t.mock.timers.tick(1200);
        assert.equal(fixture.requests, 0);
    });
}

for (const method of ['returnToProposalPaperEditor', 'resizeProposalPaperPreview']) {
    test(`${method} does not let a queued refresh reopen the mobile pane`, (t) => {
        const fixture = preview(t);
        fixture.useMobile();
        fixture.state.scheduleProposalPaperPreview();
        fixture.state[method]();
        t.mock.timers.tick(1200);
        assert.equal(fixture.requests, 0);
        assert.equal(fixture.state.previewPaneOpen, false);
    });
}
