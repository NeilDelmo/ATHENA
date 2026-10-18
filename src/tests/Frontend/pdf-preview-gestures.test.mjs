import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import { chromium } from '@playwright/test';
import registerPdfAnnotationWorkspace from '../../resources/js/pdf-annotation-workspace.js';

function preview(context, config = {}) {
    const originalWindow = globalThis.window;
    const originalObserver = globalThis.ResizeObserver;
    globalThis.window = { location: { search: '' }, addEventListener() {}, setTimeout, clearTimeout };
    globalThis.ResizeObserver = class { observe() {} disconnect() {} };
    const viewer = Object.assign(new EventTarget(), { clientWidth: 600, clientHeight: 800 });
    let factory;
    registerPdfAnnotationWorkspace({ data: (_name, callback) => { factory = callback; } });
    const state = factory({ maxZoom: 400, ...config });
    state.$el = { dataset: {} };
    state.$refs = { viewer };
    state.$nextTick = callback => callback?.();
    state.loadPdf = () => { state.loading = false; };
    const captures = [];
    const renders = [];
    state.capturePaperViewAnchor = (x, y) => {
        const anchor = { x, y };
        captures.push(anchor);
        return anchor;
    };
    state.refreshPaperView = anchor => { renders.push({ zoom: state.previewZoom, anchor }); };
    state.init();
    context.after(() => {
        state.destroy();
        globalThis.window = originalWindow;
        globalThis.ResizeObserver = originalObserver;
    });
    const dispatch = (type, properties = {}) => {
        const event = Object.assign(new Event(type, { cancelable: true }), properties);
        viewer.dispatchEvent(event);
        return event;
    };
    const wheel = (properties = {}) => dispatch('wheel', {
        ctrlKey: true, shiftKey: false, deltaX: 0, deltaY: -4,
        deltaMode: 0, clientX: 120, clientY: 240, ...properties,
    });
    return { state, viewer, captures, renders, dispatch, wheel };
}

test('all full PDF previews accept small trackpad pinch deltas in both directions', context => {
    const { state, wheel } = preview(context);
    assert.equal(wheel().defaultPrevented, true);
    const enlarged = state.previewZoom;
    assert.ok(enlarged > 100 && enlarged < 110, 'A small pinch changes zoom without waiting for a mouse-wheel threshold.');
    assert.equal(wheel({ deltaY: 4 }).defaultPrevented, true);
    assert.ok(state.previewZoom < enlarged);
    assert.ok(Math.abs(state.previewZoom - 100) <= 1);
});

test('tiny trackpad pinch deltas accumulate while ordinary scrolling stays native', context => {
    const { state, wheel } = preview(context);
    for (const properties of [{ ctrlKey: false }, { shiftKey: true }, { deltaX: 12, deltaY: 2 }, { deltaY: 0 }]) {
        assert.equal(wheel(properties).defaultPrevented, false);
    }
    assert.equal(state.previewZoom, 100);
    for (let index = 0; index < 12; index++) wheel({ deltaY: -0.1 });
    assert.ok(state.previewZoom > 100 && state.previewZoom < 110);
});

for (const [name, config] of [['thumbnails', { firstPageOnly: true }], ['explicitly disabled viewers', { wheelZoom: false }]]) {
    test(`${name} leave browser pinch handling alone`, context => {
        const { state, wheel, dispatch } = preview(context, config);
        assert.equal(wheel().defaultPrevented, false);
        assert.equal(dispatch('gesturestart', { scale: 1, clientX: 120, clientY: 240 }).defaultPrevented, false);
        dispatch('gesturechange', { scale: 1.4 });
        assert.equal(state.previewZoom, 100);
    });
}

test('native Safari pinch scales from its starting zoom and resets between gestures', context => {
    const { state, dispatch, captures } = preview(context);
    assert.equal(dispatch('gesturestart', { scale: 1, clientX: 120, clientY: 240 }).defaultPrevented, true);
    assert.equal(dispatch('gesturechange', { scale: 1.2 }).defaultPrevented, true);
    assert.equal(state.previewZoom, 120);
    dispatch('gesturechange', { scale: 1.5 });
    assert.equal(state.previewZoom, 150);
    dispatch('gestureend', { scale: 1.5 });
    dispatch('gesturestart', { scale: 1, clientX: 300, clientY: 400 });
    dispatch('gesturechange', { scale: 0.8 });
    assert.equal(state.previewZoom, 120);
    dispatch('gestureend', { scale: 0.8 });
    assert.ok(captures.length >= 2);
    assert.deepEqual(captures[0], { x: 120, y: 240 });
});

test('pinch gestures cannot change loading or failed previews and obey zoom limits', context => {
    const { state, wheel, dispatch } = preview(context);
    for (const issue of ['loading', 'loadError']) {
        state[issue] = issue === 'loading' ? true : 'PDF unavailable.';
        wheel();
        dispatch('gesturestart', { scale: 1 });
        dispatch('gesturechange', { scale: 2 });
        dispatch('gestureend', { scale: 2 });
        assert.equal(state.previewZoom, 100);
        state[issue] = issue === 'loading' ? false : '';
    }
    dispatch('gesturestart', { scale: 1 });
    dispatch('gesturechange', { scale: 20 });
    assert.equal(state.previewZoom, 400);
    dispatch('gesturechange', { scale: 0.01 });
    assert.equal(state.previewZoom, 50);
});

test('pinch rendering retains the cursor anchor and close cancels pending work', async context => {
    const { state, wheel, dispatch, renders, captures } = preview(context);
    wheel();
    wheel({ deltaY: -2, clientX: 220, clientY: 340 });
    await new Promise(resolve => setTimeout(resolve, 110));
    assert.equal(renders.length, 1);
    assert.equal(captures.length, 1);
    assert.deepEqual(renders[0].anchor, { x: 120, y: 240 });
    wheel();
    state.destroy();
    const closedZoom = state.previewZoom;
    assert.equal(wheel().defaultPrevented, false);
    assert.equal(dispatch('gesturestart', { scale: 1 }).defaultPrevented, false);
    dispatch('gesturechange', { scale: 2 });
    assert.equal(state.previewZoom, closedZoom);
    await new Promise(resolve => setTimeout(resolve, 110));
    assert.equal(renders.length, 1);
});
