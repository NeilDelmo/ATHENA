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

test('a containing preview can consume a pinch without the nested PDF zooming again', context => {
    const { state, viewer } = preview(context);
    for (const [type, properties] of [
        ['wheel', { ctrlKey: true, deltaY: -4, deltaX: 0, deltaMode: 0 }],
        ['gesturestart', { scale: 1 }],
        ['gesturechange', { scale: 1.5 }],
    ]) {
        const event = Object.assign(new Event(type, { cancelable: true }), properties);
        event.preventDefault();
        viewer.dispatchEvent(event);
    }
    assert.equal(state.previewZoom, 100);
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

test('a continuing native pinch keeps its original cursor anchor across rendered frames', async context => {
    const { dispatch, renders } = preview(context);
    dispatch('gesturestart', { scale: 1, clientX: 120, clientY: 240 });
    dispatch('gesturechange', { scale: 1.1 });
    await new Promise(resolve => setTimeout(resolve, 110));
    dispatch('gesturechange', { scale: 1.2 });
    await new Promise(resolve => setTimeout(resolve, 110));
    dispatch('gestureend', { scale: 1.2 });
    assert.equal(renders.length, 2);
    assert.deepEqual(renders.map(render => render.anchor), [
        { x: 120, y: 240 },
        { x: 120, y: 240 },
    ]);
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

test('drawing feedback during an immediate zoom keeps the preview under the pointer', context => {
    const { state } = preview(context);
    const originalDocument = globalThis.document;
    context.after(() => { globalThis.document = originalDocument; });
    const mark = { style: {}, dataset: {} };
    globalThis.document = { createElement: () => mark };
    globalThis.window.getSelection = () => null;
    state.canAnnotate = true;
    state.$el.querySelectorAll = () => [];
    const paper = {
        style: { zoom: '2' },
        getBoundingClientRect: () => ({ left: 100, top: 100, width: 1200, height: 1600 }),
        append() {},
    };
    state.startAreaSelection({ button: 0, clientX: 200, clientY: 300, target: { closest: () => null }, preventDefault() {} }, paper);
    state.moveAreaSelection({ clientX: 400, clientY: 500 });
    assert.deepEqual(mark.style, { left: '50px', top: '100px', width: '100px', height: '100px' });
});

test('trackpad and native gesture events zoom rendered paper without moving the pointed passage', { timeout: 30000 }, async () => {
    const source = await readFile(new URL('../../resources/js/pdf-annotation-workspace.js', import.meta.url), 'utf8');
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        const page = await browser.newPage();
        await page.setContent('<style>#viewer{box-sizing:border-box;width:600px;height:500px;padding:12px;overflow:auto;display:flex;flex-direction:column;gap:16px}.pdf-annotation-page{position:relative;flex:none;margin-inline:auto}canvas{display:block}.textLayer{position:absolute;inset:0}</style><div id="viewer"></div>');
        await page.addScriptTag({ content: source.replaceAll('export default ', '').replaceAll('export ', '') });
        await page.evaluate(async () => {
            let factory;
            registerPdfAnnotationWorkspace({ data: (_name, callback) => { factory = callback; } });
            const library = {
                getDocument: () => ({ promise: Promise.resolve({
                    numPages: 2,
                    getPage: async () => ({
                        getViewport: ({ scale }) => ({ width: 600 * scale, height: 800 * scale, scale, userUnit: 1 }),
                        streamTextContent: () => ({}),
                        render: () => ({ promise: Promise.resolve() }),
                    }),
                }) }),
                TextLayer: class { async render() {} },
            };
            pdfJsPromise = Promise.resolve(library);
            const state = factory({ pdfUrl: '/paper.pdf', fitWidth: true, maxZoom: 400 });
            state.$el = document.querySelector('#viewer');
            state.$refs = { viewer: state.$el };
            state.$nextTick = callback => callback?.();
            const loadPdf = state.loadPdf;
            state.loadPdf = () => window.loadedPaper = loadPdf.call(state, async () => library);
            window.state = state;
            state.init();
            await window.loadedPaper;
            state.$refs.viewer.scrollTop = 300;
        });
        await page.evaluate(() => new Promise(resolve => setTimeout(resolve, 180)));
        const pinchAt = await page.locator('#viewer').evaluate(viewer => {
            const bounds = viewer.getBoundingClientRect();
            return { clientX: bounds.left + 250, clientY: bounds.top + 220 };
        });
        const before = await page.evaluate(point => ({
            anchor: window.state.capturePaperViewAnchor(point.clientX, point.clientY),
            width: document.querySelector('.pdf-annotation-page').getBoundingClientRect().width,
        }), pinchAt);
        assert.equal(await page.locator('#viewer').evaluate((viewer, point) => {
            const event = new WheelEvent('wheel', { cancelable: true, ctrlKey: true, deltaY: -12, ...point });
            viewer.dispatchEvent(event);
            return event.defaultPrevented;
        }, pinchAt), true);
        await page.waitForFunction(width => document.querySelector('.pdf-annotation-page').getBoundingClientRect().width > width, before.width);
        const after = await page.evaluate(point => window.state.capturePaperViewAnchor(point.clientX, point.clientY), pinchAt);
        assert.equal(after.pageNumber, before.anchor.pageNumber);
        assert.ok(Math.abs(after.x - before.anchor.x) < 0.005);
        assert.ok(Math.abs(after.y - before.anchor.y) < 0.005);

        const ordinary = await page.locator('#viewer').evaluate(viewer => {
            const zoom = window.state.previewZoom;
            const event = new WheelEvent('wheel', { cancelable: true, deltaY: 90 });
            viewer.dispatchEvent(event);
            return { prevented: event.defaultPrevented, zoom, after: window.state.previewZoom };
        });
        assert.equal(ordinary.prevented, false);
        assert.equal(ordinary.zoom, ordinary.after);

        const native = await page.locator('#viewer').evaluate((viewer, point) => {
            const startingZoom = window.state.previewZoom;
            for (const [type, scale] of [['gesturestart', 1], ['gesturechange', 1.2], ['gestureend', 1.2]]) {
                const event = Object.assign(new Event(type, { cancelable: true }), { scale, ...point });
                viewer.dispatchEvent(event);
            }
            return { startingZoom, zoom: window.state.previewZoom };
        }, pinchAt);
        assert.ok(Math.abs(native.zoom - native.startingZoom * 1.2) <= 1);
        await page.evaluate(() => window.state.destroy());
    } finally {
        await browser.close();
    }
});

test('a continuing pinch visibly zooms a long PDF before every page is rendered again', { timeout: 30000 }, async () => {
    const source = await readFile(new URL('../../resources/js/pdf-annotation-workspace.js', import.meta.url), 'utf8');
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        const page = await browser.newPage();
        await page.setContent('<style>#viewer{box-sizing:border-box;width:600px;height:500px;padding:12px;overflow:auto;display:flex;flex-direction:column;gap:16px}.pdf-annotation-page{position:relative;flex:none;margin-inline:auto}canvas{display:block}.textLayer{position:absolute;inset:0}</style><div id="viewer"></div>');
        await page.addScriptTag({ content: source.replaceAll('export default ', '').replaceAll('export ', '') });
        await page.evaluate(async () => {
            let factory;
            registerPdfAnnotationWorkspace({ data: (_name, callback) => { factory = callback; } });
            const library = {
                getDocument: () => ({ promise: Promise.resolve({
                    numPages: 127,
                    getPage: async () => ({
                        getViewport: ({ scale }) => ({ width: 600 * scale, height: 800 * scale, scale, userUnit: 1 }),
                        streamTextContent: () => ({}),
                        render: () => ({ promise: new Promise(resolve => setTimeout(resolve, 6)) }),
                    }),
                }) }),
                TextLayer: class { async render() {} },
            };
            pdfJsPromise = Promise.resolve(library);
            const state = factory({ pdfUrl: '/long-manual.pdf', fitWidth: true, maxZoom: 400 });
            state.$el = document.querySelector('#viewer');
            state.$refs = { viewer: state.$el };
            state.$nextTick = callback => callback?.();
            const loadPdf = state.loadPdf;
            state.loadPdf = () => window.loadedPaper = loadPdf.call(state, async () => library);
            window.state = state;
            state.init();
            await window.loadedPaper;
        });
        const duringPinch = await page.evaluate(async () => {
            const viewer = document.querySelector('#viewer');
            const paper = () => viewer.querySelector('.pdf-annotation-page').getBoundingClientRect().width;
            const before = paper();
            const pinch = () => viewer.dispatchEvent(new WheelEvent('wheel', {
                cancelable: true, ctrlKey: true, deltaY: -2, clientX: 250, clientY: 220,
            }));
            pinch();
            const ongoingPinch = setInterval(pinch, 30);
            await new Promise(resolve => setTimeout(resolve, 180));
            const result = { before, during: paper(), zoom: window.state.previewZoom };
            clearInterval(ongoingPinch);
            window.state.destroy();
            return result;
        });
        assert.ok(duringPinch.zoom > 100, 'The gesture changes the requested zoom.');
        assert.ok(duringPinch.during > duringPinch.before,
            `The visible paper must follow an ongoing pinch; requested ${duringPinch.zoom}% but width stayed ${duringPinch.during}px.`);
    } finally {
        await browser.close();
    }
});

test('Edge trusted trackpad pinch zooms the PDF rather than the browser page', {
    timeout: 30000,
    skip: !process.env.PLAYWRIGHT_EDGE_EXECUTABLE,
}, async () => {
    const source = await readFile(new URL('../../resources/js/pdf-annotation-workspace.js', import.meta.url), 'utf8');
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_EDGE_EXECUTABLE });
    try {
        const page = await browser.newPage({ viewport: { width: 900, height: 650 } });
        await page.setContent('<meta name="viewport" content="width=device-width,initial-scale=1"><style>#viewer{box-sizing:border-box;width:600px;height:500px;padding:12px;overflow:auto;display:flex;flex-direction:column;gap:16px}.pdf-annotation-page{position:relative;flex:none;margin-inline:auto}canvas{display:block}.textLayer{position:absolute;inset:0}</style><div id="viewer"></div>');
        await page.addScriptTag({ content: source.replaceAll('export default ', '').replaceAll('export ', '') });
        await page.evaluate(async () => {
            let factory;
            registerPdfAnnotationWorkspace({ data: (_name, callback) => { factory = callback; } });
            const library = {
                getDocument: () => ({ promise: Promise.resolve({
                    numPages: 2,
                    getPage: async () => ({
                        getViewport: ({ scale }) => ({ width: 600 * scale, height: 800 * scale, scale, userUnit: 1 }),
                        streamTextContent: () => ({}),
                        render: () => ({ promise: Promise.resolve() }),
                    }),
                }) }),
                TextLayer: class { async render() {} },
            };
            pdfJsPromise = Promise.resolve(library);
            const state = factory({ pdfUrl: '/edge-paper.pdf', fitWidth: true, maxZoom: 400 });
            state.$el = document.querySelector('#viewer');
            state.$refs = { viewer: state.$el };
            state.$nextTick = callback => callback?.();
            const loadPdf = state.loadPdf;
            state.loadPdf = () => window.loadedPaper = loadPdf.call(state, async () => library);
            window.state = state;
            window.pinchEvents = [];
            document.addEventListener('wheel', event => window.pinchEvents.push({
                trusted: event.isTrusted,
                ctrl: event.ctrlKey,
                prevented: event.defaultPrevented,
            }));
            state.init();
            await window.loadedPaper;
        });
        await page.waitForTimeout(200);
        const before = await page.evaluate(() => ({
            width: document.querySelector('.pdf-annotation-page').getBoundingClientRect().width,
            browserScale: window.visualViewport.scale,
            innerWidth: window.innerWidth,
        }));
        const session = await page.context().newCDPSession(page);
        await session.send('Input.synthesizePinchGesture', {
            x: 250, y: 220, scaleFactor: 1.25, relativeSpeed: 500, gestureSourceType: 'mouse',
        });
        const events = await page.evaluate(() => window.pinchEvents);
        assert.ok(events.some(event => event.trusted && event.ctrl && event.prevented),
            'Edge generates trusted Ctrl+wheel pinch events that the PDF consumes.');
        await page.waitForFunction(width => document.querySelector('.pdf-annotation-page').getBoundingClientRect().width > width, before.width);
        const after = await page.evaluate(() => ({
            zoom: window.state.previewZoom,
            browserScale: window.visualViewport.scale,
            innerWidth: window.innerWidth,
        }));
        assert.ok(after.zoom > 100);
        assert.equal(after.browserScale, before.browserScale);
        assert.equal(after.innerWidth, before.innerWidth);
        await page.evaluate(() => window.state.destroy());
    } finally {
        await browser.close();
    }
});
