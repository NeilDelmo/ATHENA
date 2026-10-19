import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';
import { clampProposalPreviewPosition, proposalPreviewWorkspace } from '../../resources/js/proposal-preview-workspace.js';

test('preview starts closed so the official form keeps the full workspace width', () => {
    const state = proposalPreviewWorkspace();
    assert.equal(state.previewPaneOpen, false);
    assert.equal(state.previewTab, 'edit');
});

test('opening preview retains the loaded document and editor position', () => {
    let generated = 0;
    const state = { ...proposalPreviewWorkspace(), previewHtml: '<p>Saved preview</p>', generatePreview() { generated++; } };
    const frame = { contentDocument: { body: { style: {} } } };
    state.$refs = { previewFrame: frame };
    state.previewPaneOpen = false;
    state.showProposalPreview();
    assert.equal(state.previewPaneOpen, true);
    assert.equal(state.previewTab, 'preview');
    assert.equal(generated, 0);
    assert.equal(state.$refs.previewFrame, frame);
    assert.equal(state.previewHtml, '<p>Saved preview</p>');
});

test('opening an empty preview generates once while a request is pending', () => {
    let generated = 0;
    const state = { ...proposalPreviewWorkspace(), previewHtml: '', generatePreview() { generated++; this.previewLoading = true; } };
    state.showProposalPreview();
    state.showProposalPreview();
    assert.equal(generated, 1);
});

test('reopening a preview after edits regenerates once while a request is pending', () => {
    let generated = 0;
    const state = {
        ...proposalPreviewWorkspace(),
        previewHtml: '<p>Old preview</p>',
        generatePreview() { generated++; this.previewLoading = true; },
    };
    state.markProposalPreviewStale();
    state.showProposalPreview();
    state.showProposalPreview();
    assert.equal(generated, 1);
    assert.equal(state.previewPaneOpen, true);
});

test('edits mark an existing preview stale and track changes during generation', () => {
    const state = { ...proposalPreviewWorkspace(), previewHtml: '' };
    state.markProposalPreviewStale();
    assert.equal(state.previewStale, false);
    state.previewHtml = '<p>Preview</p>';
    const revision = state.previewRevision;
    state.markProposalPreviewStale();
    assert.equal(state.previewStale, true);
    assert.notEqual(state.previewRevision, revision);
});

test('zoom is bounded and is reapplied when the preview frame loads', () => {
    const body = { style: {}, scrollWidth: 800 };
    const documentElement = { style: {}, clientWidth: 1000 };
    const state = { ...proposalPreviewWorkspace(), previewHtml: '<p>Preview</p>', $refs: { previewFrame: { contentDocument: { body, documentElement } } } };
    state.setProposalPreviewZoom(75);
    assert.equal(body.style.zoom, '0.75');
    state.setProposalPreviewZoom(500);
    assert.equal(state.previewZoom, 150);
    state.proposalPreviewLoaded();
    assert.equal(body.style.zoom, '1.5');
    assert.equal(state.previewReady, true);
});

test('zoom buttons adjust the preview in ten-percent steps and stop at the limits', () => {
    const body = { style: {}, scrollWidth: 800 };
    const documentElement = { style: {}, clientWidth: 800 };
    const state = { ...proposalPreviewWorkspace(), $refs: { previewFrame: { contentDocument: { body, documentElement } } } };

    state.previewZoom = 70;
    state.decreaseProposalPreviewZoom();
    assert.equal(state.previewZoom, 60);
    state.increaseProposalPreviewZoom();
    assert.equal(state.previewZoom, 70);

    state.previewZoom = 50;
    state.decreaseProposalPreviewZoom();
    assert.equal(state.previewZoom, 50);

    state.previewZoom = 150;
    state.increaseProposalPreviewZoom();
    assert.equal(state.previewZoom, 150);
});

test('legal-size preview paper fits the drawer and enables horizontal scrolling when needed', () => {
    const body = { style: {}, scrollWidth: 864 };
    const documentElement = { style: {}, clientWidth: 704 };
    const state = { ...proposalPreviewWorkspace(), previewHtml: '<p>Preview</p>', $refs: { previewFrame: { contentDocument: { body, documentElement } } } };

    state.proposalPreviewLoaded();

    assert.equal(body.style.zoom, String(704 / 864));
    assert.equal(body.style.overflowX, 'auto');
    assert.equal(documentElement.style.overflowX, 'auto');
});

test('Ctrl-wheel zoom is scoped to enlarged previews, preserves ordinary scrolling and rebinds after regeneration', () => {
    const document = new EventTarget();
    const frame = { contentDocument: document };
    const state = { ...proposalPreviewWorkspace(), $refs: { previewFrame: frame }, applyProposalPreviewZoom() {} };
    const wheel = (deltaY, options = {}) => {
        const event = new Event('wheel', { cancelable: true });
        Object.assign(event, { deltaY, deltaX: 0, deltaMode: 0, ...options });
        document.dispatchEvent(event);
        return event.defaultPrevented;
    };
    state.bindProposalPreviewWheel();
    assert.equal(wheel(-120), false);
    assert.equal(state.previewZoom, 100);
    state.previewFullscreen = true;
    state.bindProposalPreviewWheel();
    assert.equal(wheel(-120), false, 'Ordinary wheel scrolling must not be intercepted');
    assert.equal(state.previewZoom, 100);
    assert.equal(wheel(-120, { ctrlKey: true }), true);
    assert.equal(state.previewZoom, 110, 'Repeated frame loads must not duplicate wheel handlers');
    wheel(120, { ctrlKey: true });
    assert.equal(state.previewZoom, 100);
    assert.equal(wheel(-120, { shiftKey: true }), false);
    assert.equal(state.previewZoom, 100);
    for (let index = 0; index < 10; index++) wheel(-120, { ctrlKey: true });
    assert.equal(state.previewZoom, 150);
    for (let index = 0; index < 20; index++) wheel(120, { ctrlKey: true });
    assert.equal(state.previewZoom, 50);
    frame.contentDocument = new EventTarget();
    state.bindProposalPreviewWheel();
    assert.equal(wheel(-120, { ctrlKey: true }), false, 'Regenerated papers detach the previous document listener');
});

test('small trackpad pinch deltas zoom immediately and cancel each other', () => {
    const document = new EventTarget();
    const state = { ...proposalPreviewWorkspace(), previewFullscreen: true, $refs: { previewFrame: { contentDocument: document } }, applyProposalPreviewZoom() {} };
    state.bindProposalPreviewWheel();
    const pinch = deltaY => document.dispatchEvent(Object.assign(new Event('wheel', { cancelable: true }), { ctrlKey: true, deltaMode: 0, deltaY }));
    pinch(-4);
    assert.ok(state.previewZoom > 100 && state.previewZoom < 110);
    pinch(4);
    assert.ok(Math.abs(state.previewZoom - 100) <= 1);
    for (let index = 0; index < 12; index++) pinch(-0.1);
    assert.ok(state.previewZoom > 100);
});

test('Safari pinch uses the initial zoom, yields to handled events and cleans up', () => {
    const document = new EventTarget();
    const frame = { contentDocument: document };
    const state = { ...proposalPreviewWorkspace(), previewFullscreen: true, $refs: { previewFrame: frame }, applyProposalPreviewZoom() {} };
    state.bindProposalPreviewWheel();
    const gesture = (type, scale, handled = false) => {
        const event = Object.assign(new Event(type, { cancelable: true }), { scale });
        if (handled) event.preventDefault();
        document.dispatchEvent(event);
        return event;
    };
    assert.equal(gesture('gesturestart', 1).defaultPrevented, true);
    gesture('gesturechange', 1.2);
    assert.equal(state.previewZoom, 120);
    gesture('gesturechange', 1.4);
    assert.equal(state.previewZoom, 140);
    gesture('gestureend', 1.4);
    gesture('gesturestart', 1);
    gesture('gesturechange', 0.5);
    assert.equal(state.previewZoom, 70);
    gesture('gestureend', 0.5);
    gesture('gesturestart', 1, true);
    gesture('gesturechange', 2, true);
    assert.equal(state.previewZoom, 70);
    state.unbindProposalPreviewWheel();
    assert.equal(gesture('gesturestart', 1).defaultPrevented, false);
    gesture('gesturechange', 2);
    assert.equal(state.previewZoom, 70);
});

test('interactive floating previews accept pinch before entering fullscreen', () => {
    const document = new EventTarget();
    const state = { ...proposalPreviewWorkspace(), previewPaneOpen: true, $refs: { previewFrame: { contentDocument: document }, previewPanel: { matches: selector => selector === '.proposal-preview-pane' } }, applyProposalPreviewZoom() {} };
    state.bindProposalPreviewWheel();
    const event = Object.assign(new Event('wheel', { cancelable: true }), { ctrlKey: true, deltaMode: 0, deltaY: -4 });
    document.dispatchEvent(event);
    assert.equal(event.defaultPrevented, true);
    assert.ok(state.previewZoom > 100);
});

test('revision iframe gestures use the parent zoom rather than temporary mixin state', () => {
    const document = new EventTarget();
    const frame = { contentDocument: document };
    let zoom = 70;
    proposalPreviewWorkspace().bindProposalPreviewWheel(frame, () => true, amount => { zoom += amount; }, () => zoom);
    document.dispatchEvent(Object.assign(new Event('gesturestart', { cancelable: true }), { scale: 1 }));
    document.dispatchEvent(Object.assign(new Event('gesturechange', { cancelable: true }), { scale: 2 }));
    assert.equal(zoom, 140);
});

test('the standalone Work Plan wizard supports pinch only on its review step', () => {
    const app = readFileSync(new URL('../../resources/js/app.js', import.meta.url), 'utf8');
    const start = app.indexOf("Alpine.data('workPlanWizard',");
    let factory;
    runInNewContext(app.slice(start, app.indexOf('Alpine.data(', start + 1)), {
        Alpine: { data: (_name, callback) => { factory = callback; } }, proposalPreviewWorkspace,
    });
    const document = new EventTarget();
    const state = factory();
    state.$refs = { previewFrame: { contentDocument: document } };
    state.previewHtml = '<main>Work Plan</main>';
    state.proposalPreviewLoaded();
    const pinch = () => document.dispatchEvent(Object.assign(new Event('wheel', { cancelable: true }), { ctrlKey: true, deltaMode: 0, deltaY: -4 }));
    pinch();
    assert.equal(state.previewZoom, 100);
    state.step = 4;
    pinch();
    assert.ok(state.previewZoom > 100);
    document.body = { style: { zoom: '0.8' } };
    let printedZoom;
    state.$refs.previewFrame.contentWindow = { focus() {}, print() { printedZoom = document.body.style.zoom; } };
    state.printPreview();
    assert.equal(printedZoom, '1', 'Printed paper must keep its physical size after pinching');
    assert.equal(document.body.style.zoom, '0.8');
    state.destroy();
    const zoom = state.previewZoom;
    pinch();
    assert.equal(state.previewZoom, zoom);
});

test('full-screen preview opens at readable paper width and can return to editing layout', () => {
    const sheet = { offsetHeight: 1248 };
    const body = { style: {}, scrollWidth: 864, querySelector: () => sheet };
    const frame = {
        clientWidth: 1200,
        clientHeight: 648,
        contentDocument: { body, documentElement: { style: {} } },
        contentWindow: { getComputedStyle: () => ({ minHeight: '1248px' }) },
    };
    const state = { ...proposalPreviewWorkspace(), $refs: { previewFrame: frame }, $nextTick: (callback) => callback() };
    state.toggleProposalPreviewFullscreen();
    assert.equal(state.previewFullscreen, true);
    assert.equal(state.previewPaneOpen, true);
    assert.equal(state.previewFit, 'width');
    assert.equal(body.style.zoom, '1', 'Opening a tall paper must not shrink it to the viewport height');
    state.toggleProposalPreviewFullscreen();
    assert.equal(state.previewFullscreen, false);
    assert.equal(state.previewFit, 'width');
});

test('fit page uses a physical sheet height for a long form and fit width restores readability', () => {
    const sheet = { offsetHeight: 5000 };
    const body = { style: {}, scrollWidth: 864, querySelector: () => sheet };
    const frame = {
        clientHeight: 648,
        contentDocument: { body, documentElement: { style: {}, clientWidth: 1200 } },
        contentWindow: { getComputedStyle: (element) => element === sheet ? { minHeight: '1248px' } : { paddingTop: '24px', paddingBottom: '24px' } },
    };
    const state = { ...proposalPreviewWorkspace(), $refs: { previewFrame: frame } };
    state.fitProposalPreview('page');
    assert.equal(body.style.zoom, '0.5');
    state.increaseProposalPreviewZoom();
    assert.equal(body.style.zoom, '0.55');
    state.fitProposalPreview('width');
    assert.equal(body.style.zoom, '1');
    assert.equal(state.previewZoom, 100);
});

test('closing preview restores the editing state and exits full screen', () => {
    const state = { ...proposalPreviewWorkspace(), previewPaneOpen: true, previewTab: 'preview', previewFullscreen: true };
    state.closeProposalPreview();
    assert.equal(state.previewPaneOpen, false);
    assert.equal(state.previewTab, 'edit');
    assert.equal(state.previewFullscreen, false);
});

test('dragging is clamped so the resizable preview stays inside the viewport', () => {
    assert.deepEqual(clampProposalPreviewPosition({
        left: -120,
        top: 900,
        width: 440,
        height: 500,
        viewportWidth: 1200,
        viewportHeight: 800,
    }), {
        left: 8,
        top: 292,
    });
});
