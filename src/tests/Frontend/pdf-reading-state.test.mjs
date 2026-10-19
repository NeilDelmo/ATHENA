import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import { chromium } from '@playwright/test';
import { pdfReadingState, synchronizePdfReadingAccount } from '../../resources/js/pdf-annotation-workspace.js';

const storageKey = 'athena-pdf-reading-state:v1';
const position = { page: 3, x: 0, y: .35, zoom: 125, fit: 'page' };

function storageFixture(context) {
    const previous = { window: globalThis.window, document: globalThis.document };
    const values = new Map();
    let user = '4';
    globalThis.document = { querySelector: () => ({ dataset: { authUserId: user } }) };
    globalThis.window = { location: { href: 'https://athena.test/manuals' }, localStorage: {
        getItem: key => values.get(key) || null,
        setItem: (key, value) => values.set(key, value),
        removeItem: key => values.delete(key),
    } };
    context.after(() => Object.assign(globalThis, previous));
    return { values, account(value) { user = value; } };
}

test('PDF reading state separates file URLs, editions and accounts and clears after logout', context => {
    const fixture = storageFixture(context);
    const state = pdfReadingState('/manual/v1.pdf', 'fingerprint-1');
    state.write(position);
    assert.equal(pdfReadingState('/manual/v1.pdf#page=1', 'fingerprint-1').read().page, 3);
    assert.equal(pdfReadingState('/manual/v2.pdf', 'fingerprint-1').read(), null);
    assert.equal(pdfReadingState('/manual/v1.pdf', 'fingerprint-2').read(), null);
    fixture.account('5');
    assert.equal(pdfReadingState('/manual/v1.pdf', 'fingerprint-1').read(), null);
    state.write({ ...position, page: 7 });
    assert.equal(JSON.parse(fixture.values.get(storageKey)).documents[0].page, 3);
    synchronizePdfReadingAccount();
    assert.equal(fixture.values.has(storageKey), false);
    pdfReadingState('/manual/v1.pdf').write(position);
    fixture.account(null);
    synchronizePdfReadingAccount();
    assert.equal(fixture.values.has(storageKey), false);
    assert.equal(pdfReadingState('/manual/v1.pdf').read(), null);
});

test('invalid, expired, oversized and unavailable PDF reading storage is ignored', context => {
    const fixture = storageFixture(context);
    const state = pdfReadingState('/manual.pdf');
    for (const raw of ['{broken', 'null', '[]', '{"user":"4","documents":{}}']) {
        fixture.values.set(storageKey, raw);
        assert.equal(state.read(), null);
    }
    state.write(position);
    const stored = JSON.parse(fixture.values.get(storageKey));
    for (const invalid of [{ savedAt: 0 }, { savedAt: Date.now() + 86400000 }, { page: -1 }, { page: '3' }, { x: 2 }, { zoom: 500 }, { fit: 'invalid' }]) {
        fixture.values.set(storageKey, JSON.stringify({ ...stored, documents: [{ ...stored.documents[0], ...invalid }] }));
        assert.equal(state.read(), null);
    }
    assert.equal(pdfReadingState('blob:https://athena.test/transient').read(), null);
    for (let index = 0; index < 30; index++) pdfReadingState(`/manual/${index}.pdf`).write(position);
    assert.equal(JSON.parse(fixture.values.get(storageKey)).documents.length, 20);
    Object.defineProperty(window, 'localStorage', { get() { throw new Error('Storage denied'); } });
    assert.equal(state.read(), null);
    assert.doesNotThrow(() => state.write(position));
    assert.doesNotThrow(() => synchronizePdfReadingAccount());
});

test('shared PDF reopen restores page position and zoom while citations and thumbnails keep priority', { timeout: 30000 }, async () => {
    const source = await readFile(new URL('../../resources/js/pdf-annotation-workspace.js', import.meta.url), 'utf8');
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        const page = await browser.newPage();
        await page.route('https://athena.test/**', route => route.fulfill({ contentType: 'text/html', body: '<body data-auth-user-id="4"><style>.pdf-annotation-page{position:relative;flex:none}#viewer{width:600px;height:500px;overflow:auto}</style><div id="viewer"></div></body>' }));
        await page.goto('https://athena.test/manuals');
        await page.addScriptTag({ content: source.replaceAll('export default ', '').replaceAll('export ', '') });
        await page.evaluate(() => {
            let factory;
            registerPdfAnnotationWorkspace({ data: (_name, callback) => { factory = callback; } });
            const library = {
                getDocument: () => ({ promise: Promise.resolve({ numPages: 4, fingerprints: ['manual-1'], getPage: async () => ({
                    getViewport: ({ scale }) => ({ width: 600 * scale, height: 800 * scale, scale, userUnit: 1 }),
                    streamTextContent: () => ({}), render: () => ({ promise: Promise.resolve() }),
                }) }) }),
                TextLayer: class { async render() {} },
            };
            loadPdfJs = async () => library;
            window.openViewer = async (configuration = {}) => {
                window.state?.destroy();
                document.querySelector('#viewer').replaceChildren();
                const state = factory({ pdfUrl: '/manual/v1.pdf', ...configuration });
                state.$el = document.querySelector('#viewer');
                state.$refs = { viewer: state.$el };
                state.$nextTick = callback => { callback?.(); return Promise.resolve(); };
                const load = state.loadPdf;
                state.loadPdf = () => window.pdfLoading = load.call(state, async () => library);
                window.state = state;
                state.init();
                await window.pdfLoading;
            };
        });
        await page.evaluate(() => window.openViewer());
        const before = await page.evaluate(async () => {
            await window.state.setPaperZoom(130);
            window.state.goToPage(3);
            window.state.$refs.viewer.scrollTop += 210;
            window.dispatchEvent(new Event('pagehide'));
            return window.state.$refs.viewer.scrollTop;
        });
        await page.evaluate(() => window.openViewer());
        assert.equal(await page.evaluate(() => window.state.previewZoom), 130);
        assert.equal(await page.evaluate(() => window.state.currentPage), 3);
        assert.ok(Math.abs(await page.evaluate(() => window.state.$refs.viewer.scrollTop) - before) < 2);
        await page.evaluate(() => window.openViewer({ initialPage: 2 }));
        assert.equal(await page.evaluate(() => window.state.currentPage), 2);
        const citedTop = await page.evaluate(() => document.querySelector('[data-page-number="2"]').getBoundingClientRect().top - document.querySelector('#viewer').getBoundingClientRect().top);
        assert.ok(Math.abs(citedTop) < 2);
        await page.evaluate(() => window.openViewer({ firstPageOnly: true }));
        assert.equal(await page.locator('.pdf-annotation-page').count(), 1);
        assert.equal(await page.evaluate(() => window.state.previewZoom), 100);
        await page.evaluate(() => window.openViewer({ pdfUrl: '/manual/v2.pdf' }));
        assert.equal(await page.evaluate(() => window.state.previewZoom), 100);
        assert.equal(await page.evaluate(() => window.state.currentPage), 1);
        await page.evaluate(() => { window.state.destroy(); document.body.dataset.authUserId = '5'; });
        await page.evaluate(() => window.openViewer());
        assert.equal(await page.evaluate(() => window.state.previewZoom), 100);
        assert.equal(await page.evaluate(() => window.state.currentPage), 1);
        await page.evaluate(() => { window.state.destroy(); window.state = null; localStorage.clear(); window.dispatchEvent(new Event('pagehide')); document.dispatchEvent(new Event('livewire:navigating')); });
        assert.equal(await page.evaluate(() => localStorage.length), 0, 'Destroyed viewers remove lifecycle listeners.');
    } finally {
        await browser.close();
    }
});
