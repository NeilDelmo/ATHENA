import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import { chromium } from '@playwright/test';
import registerPdfAnnotationWorkspace from '../../resources/js/pdf-annotation-workspace.js';

test('PDF page jumps clamp to the document and retain a pending destination', () => {
    let factory;
    registerPdfAnnotationWorkspace({ data: (_name, callback) => { factory = callback; } });
    const state = factory();
    state.pageCount = 12;
    state.goToPage(99);
    assert.equal(state.currentPage, 12);
    state.goToPage(-2);
    assert.equal(state.currentPage, 1);
    state.goToPage('7');
    state.goToPage('invalid');
    assert.equal(state.currentPage, 7);
});

test('closing a shared PDF viewer prevents a delayed loader from starting the document', async context => {
    const previousWindow = globalThis.window;
    context.after(() => { globalThis.window = previousWindow; });
    globalThis.window = { clearTimeout() {} };
    let factory;
    registerPdfAnnotationWorkspace({ data: (_name, callback) => { factory = callback; } });
    const state = factory();
    state.$refs = {};
    let finishLoading;
    let documentLoads = 0;
    const loading = state.loadPdf(() => new Promise(resolve => { finishLoading = resolve; }));
    state.destroy();
    finishLoading({ getDocument() { documentLoads++; } });
    await loading;
    state.goToPage(3);
    assert.equal(documentLoads, 0);
    assert.equal(state.currentPage, 1);
    assert.equal(state.loadError, '');
});

test('shared PDF search includes later pages, preserves selectable spans, and survives rerendering', { timeout: 30000 }, async () => {
    const source = await readFile(new URL('../../resources/js/pdf-annotation-workspace.js', import.meta.url), 'utf8');
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        const page = await browser.newPage();
        await page.setContent('<style>.pdf-annotation-page{position:relative;flex:none}.textLayer{position:absolute;inset:0;padding:30px;font:20px Arial}#viewer{width:600px;height:500px;overflow:auto}</style><div id="viewer"></div>');
        await page.addScriptTag({ content: source.replaceAll('export default ', '').replaceAll('export ', '') });
        await page.evaluate(() => {
            let factory;
            registerPdfAnnotationWorkspace({ data: (_name, callback) => { factory = callback; } });
            const state = factory({ pdfUrl: '/manual.pdf', searchable: true, initialPage: 3 });
            state.$el = document.querySelector('#viewer');
            state.$refs = { viewer: state.$el };
            let scrollSetup = false;
            state.$nextTick = callback => {
                if (!scrollSetup) { scrollSetup = true; callback?.(); }
                return Promise.resolve();
            };
            const pdf = {
                numPages: 3,
                getPage: async number => ({
                    getViewport: ({ scale }) => ({ width: 600 * scale, height: 800 * scale, scale, userUnit: 1 }),
                    streamTextContent: () => number,
                    render: () => ({ promise: number === 2 && !window.pdfFinished ? new Promise(resolve => { window.finishSecondPage = resolve; }) : Promise.resolve() }),
                }),
            };
            const library = {
                getDocument: () => ({ promise: Promise.resolve(pdf) }),
                TextLayer: class {
                    constructor({ container, textContentSource }) { this.container = container; this.number = textContentSource; }
                    async render() {
                        this.container.innerHTML = this.number === 1 ? '<span>Eth</span><span>ics</span><span> approval before recruitment.</span>'
                            : this.number === 2 ? '<span>Second</span><span style="margin-left:8px">page</span><br><span>ETHICS review</span>'
                            : '<span>ETHICS final evidence.</span>';
                    }
                },
            };
            const loadPdf = state.loadPdf;
            state.loadPdf = () => window.pdfLoading = loadPdf.call(state, async () => library);
            window.state = state;
            window.pdfLibrary = library;
            state.init();
        });
        await page.waitForFunction(() => typeof window.finishSecondPage === 'function');
        assert.equal(await page.evaluate(() => window.state.currentPage), 3, 'The cited page stays queued while later pages render.');
        assert.equal(await page.evaluate(() => window.state.pageCount), 3);
        await page.evaluate(() => {
            window.state.searchQuery = 'ETHICS';
            window.state.findText();
        });
        assert.equal(await page.evaluate(() => window.state.searchStatus), 'Searching…');
        await page.evaluate(async () => {
            window.state.goToPage(3);
            window.finishSecondPage();
            await window.pdfLoading;
            window.pdfFinished = true;
        });
        assert.equal(await page.evaluate(() => window.state.searchStatus), '1 of 3 matches');
        assert.equal(await page.evaluate(() => window.state.currentPage), 3);
        assert.ok(await page.evaluate(() => window.state.$refs.viewer.scrollTop) > 500);
        assert.deepEqual(await page.evaluate(() => window.state.searchMatches.map(match => match.pageNumber)), [1, 2, 3]);
        assert.equal(await page.locator('.textLayer').first().textContent(), 'Ethics approval before recruitment.');
        assert.ok(await page.locator('.pdf-search-highlight').count() >= 3);
        assert.equal(await page.evaluate(() => window.state.searchMatches[0].range.toString()), 'Ethics');
        assert.equal(await page.locator('.pdf-search-highlight').first().evaluate(element => getComputedStyle(element).pointerEvents), 'none');
        await page.evaluate(() => window.state.findText(true, true));
        await page.evaluate(() => new Promise(requestAnimationFrame));
        assert.equal(await page.evaluate(() => window.state.currentPage), 3);
        assert.equal(await page.evaluate(() => window.state.searchStatus), '3 of 3 matches');
        assert.ok(await page.evaluate(() => window.state.$refs.viewer.scrollTop) > 500);
        await page.evaluate(async () => {
            window.state.previewZoom = 110;
            await window.state.renderDocument(window.pdfLibrary);
        });
        assert.equal(await page.evaluate(() => window.state.searchStatus), '3 of 3 matches');
        assert.equal(await page.evaluate(() => window.state.currentPage), 3);
        assert.ok(await page.locator('.pdf-search-highlight[data-search-current="true"]').count() >= 1);
        await page.evaluate(() => window.state.findText(false, true));
        assert.equal(await page.evaluate(() => window.state.searchStatus), '1 of 3 matches');
        await page.evaluate(() => { window.state.searchQuery = 'second page'; window.state.findText(); });
        assert.equal(await page.evaluate(() => window.state.searchStatus), '1 of 1 matches');
        assert.equal(await page.evaluate(() => window.state.currentPage), 2);
        await page.evaluate(() => {
            window.state.$refs.viewer.style.height = '1200px';
            window.state.goToPage(3);
        });
        await page.evaluate(() => new Promise(requestAnimationFrame));
        assert.equal(await page.evaluate(() => window.state.currentPage), 3, 'The final page remains selected when it fits above the end of the viewer.');
        await page.evaluate(() => { window.state.searchQuery = 'absent'; window.state.findText(); });
        assert.equal(await page.evaluate(() => window.state.searchStatus), 'No matches');
        assert.equal(await page.locator('.pdf-search-highlight').count(), 0);
        await page.evaluate(() => { window.state.searchQuery = ''; window.state.findText(); });
        assert.equal(await page.evaluate(() => window.state.searchStatus), '');
        assert.equal(await page.locator('.textLayer span').count(), 7);
        await page.evaluate(() => { window.state.config.searchable = false; window.state.searchQuery = 'ETHICS'; window.state.findText(); });
        assert.equal(await page.locator('.pdf-search-highlight').count(), 0);
    } finally {
        await browser.close();
    }
});
