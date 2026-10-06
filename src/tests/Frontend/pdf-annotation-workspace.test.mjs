import assert from 'node:assert/strict';
import test from 'node:test';
import { readFile } from 'node:fs/promises';
import { chromium } from '@playwright/test';
import registerPdfAnnotationWorkspace, { consolidateTextRectangles, pdfScaleToFit } from '../../resources/js/pdf-annotation-workspace.js';

test('Research Head viewing controls resize the paper without losing draft feedback', async () => {
    const view = await readFile(new URL('../../resources/views/topics/file-annotations.blade.php', import.meta.url), 'utf8');
    const source = await readFile(new URL('../../resources/js/pdf-annotation-workspace.js', import.meta.url), 'utf8');
    const footer = view.match(/<footer data-review-document-zoom[\s\S]*?<\/footer>/)[0];
    const toggle = view.match(/<button type="button" data-review-comments-toggle[\s\S]*?<\/button>/)[0];
    const layout = view.match(/<div data-review-document-layout[^\n]+>/)[0];
    const viewer = view.match(/<div x-ref="viewer"[^\n]+><\/div>/)[0];
    const comments = view.match(/<aside id="review-document-comments"[^\n]+>/)[0];
    const { default: manifest } = await import('../../public/build/manifest.json', { with: { type: 'json' } });
    const css = await readFile(new URL(`../../public/build/${manifest['resources/css/app.css'].file}`, import.meta.url), 'utf8');
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        for (const width of [390, 1440]) {
            for (const dark of [false, true]) {
                const page = await browser.newPage({ viewport: { width, height: 900 } });
                const errors = [];
                page.on('pageerror', (error) => errors.push(error.message));
                await page.setContent(`<html class="${dark ? 'dark' : ''}"><style>${css}</style><body class="p-3 bg-white dark:bg-gray-950"><div x-data="reviewFixture" class="border border-gray-200 dark:border-gray-800 dark:bg-gray-900"><header class="p-3">${toggle}</header>${layout}<main class="min-h-0 min-w-0 overflow-hidden bg-slate-100 dark:bg-slate-950">${viewer}</main>${comments}<h3 class="text-gray-950 dark:text-white">Research Head comments</h3><p class="text-gray-700 dark:text-gray-200">Explain the sample size.</p></aside></div>${footer}</div></body></html>`);
                await page.addScriptTag({ content: source.replaceAll('export default ', '').replaceAll('export ', '') });
                await page.addScriptTag({ content: `document.addEventListener('alpine:init', () => {
                    let factory;
                    registerPdfAnnotationWorkspace({ data: (_name, callback) => { factory = callback; } });
                    Alpine.data('reviewFixture', () => {
                        const state = factory();
                        state.init = function () {
                            this.loading = false;
                            this.draftSelection = { pageNumber: 1, rectangles: [{ x: .1, y: .2, width: .3, height: .1 }] };
                            this.draftComment = 'Keep my unfinished feedback.';
                            this.annotations = [{ id: 22, pageNumber: 1 }];
                            this.selectedAnnotationId = 22;
                            this.jumpToAnnotation = () => {};
                            this.renderDocument = async function () {
                                window.renderedView = { zoom: this.previewZoom, fit: this.previewFit, feedback: this.draftComment };
                            };
                        };
                        return state;
                    });
                });` });
                await page.addScriptTag({ path: new URL('../../node_modules/alpinejs/dist/cdn.js', import.meta.url).pathname.replace(/^\/(\w:)/, '$1') });
                const pane = page.getByLabel('Submitted document');
                const beforeWidth = await pane.evaluate((element) => element.clientWidth);
                const beforeHeight = await pane.evaluate((element) => element.clientHeight);
                await page.getByRole('button', { name: 'Hide comments' }).click();
                assert.equal(await page.locator('#review-document-comments').isVisible(), false);
                assert.equal(width >= 1024
                    ? await pane.evaluate((element) => element.clientWidth) > beforeWidth
                    : await pane.evaluate((element) => element.clientHeight) > beforeHeight, true);
                await page.getByRole('button', { name: 'Zoom in', exact: true }).click();
                assert.equal(await page.locator('output').innerText(), '110%');
                await page.getByRole('button', { name: 'Fit page', exact: true }).click();
                assert.deepEqual(await page.evaluate(() => window.renderedView), { zoom: 100, fit: 'page', feedback: 'Keep my unfinished feedback.' });
                await page.getByRole('button', { name: 'Fit width', exact: true }).click();
                assert.equal(await page.getByRole('button', { name: 'Fit width', exact: true }).getAttribute('aria-pressed'), 'true');
                await page.getByRole('button', { name: 'Show comments' }).click();
                assert.equal(await page.locator('#review-document-comments').isVisible(), true);
                assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), true);
                assert.deepEqual(errors, []);
                await page.close();
            }
        }
    } finally {
        await browser.close();
    }
});

for (const fitWidth of [false, true]) {
test(`the ${fitWidth ? 'embedded' : 'Research Head'} PDF keeps pages and highlights correct when fitting and zooming`, async () => {
    const source = await readFile(new URL('../../resources/js/pdf-annotation-workspace.js', import.meta.url), 'utf8');
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        const page = await browser.newPage();
        await page.setContent('<div id="viewer" style="width:600px;height:800px;overflow:auto"></div>');
        await page.addScriptTag({ content: source.replaceAll('export default ', '').replaceAll('export ', '') });
        await page.evaluate((fitWidth) => {
            let factory;
            registerPdfAnnotationWorkspace({ data: (_name, callback) => { factory = callback; } });
            const state = factory();
            state.$refs = { viewer: document.querySelector('#viewer') };
            state.$nextTick = (callback) => callback();
            state.annotations = [{ id: 22, pageNumber: 2 }];
            state.focusAnnotationId = 22;
            state.config = { pdfUrl: '/paper.pdf', fitWidth };
            window.renderedAnnotations = [];
            state.renderAnnotationsForPage = (number) => window.renderedAnnotations.push(number);
            state.jumpToAnnotation = (annotation) => { window.highlightedPage = annotation.pageNumber; };
            const pdf = {
                numPages: 2,
                getPage: async (number) => ({
                    getViewport: ({ scale }) => ({ width: 600 * scale, height: 800 * scale, scale, userUnit: 1 }),
                    streamTextContent: () => number,
                    render: () => ({ promise: number === 2 && !window.finishedPdf ? new Promise((resolve) => { window.finishSecondPage = resolve; }) : Promise.resolve() }),
                }),
            };
            const library = {
                getDocument: () => ({ promise: Promise.resolve(pdf) }),
                TextLayer: class {
                    constructor({ container, textContentSource }) { this.container = container; this.number = textContentSource; }
                    async render() { this.container.textContent = `Page ${this.number} text`; }
                },
            };
            window.state = state;
            window.pdfLibrary = library;
            window.finishedPdf = false;
            state.loadPdf(async () => library).then(() => { window.finishedPdf = true; });
        }, fitWidth);
        await page.waitForFunction(() => typeof window.finishSecondPage === 'function');
        assert.equal(await page.locator('#viewer .pdf-annotation-page').count(), 1);
        assert.equal(await page.locator('#viewer .textLayer').textContent(), 'Page 1 text');
        assert.equal(await page.evaluate(() => window.state.loading), false);
        assert.equal(await page.evaluate(() => window.finishedPdf), false);
        assert.deepEqual(await page.evaluate(() => window.renderedAnnotations), [1]);
        assert.equal(await page.evaluate(() => window.highlightedPage), undefined);
        await page.evaluate(() => window.finishSecondPage());
        await page.waitForFunction(() => window.finishedPdf);
        assert.equal(await page.locator('#viewer .pdf-annotation-page').count(), 2);
        assert.deepEqual(await page.evaluate(() => window.renderedAnnotations), [1, 2]);
        assert.equal(await page.evaluate(() => window.highlightedPage), 2);
        assert.equal(await page.evaluate(() => window.state.loadError), '');
        // A full-width window must still fit the height, and zoom must retain both pages and annotations.
        await page.evaluate(async () => {
            document.querySelector('#viewer').style.width = '1200px';
            window.state.previewFit = 'page';
            await window.state.renderDocument(window.pdfLibrary);
        });
        assert.equal(await page.locator('#viewer .pdf-annotation-page').first().evaluate((element) => element.offsetHeight), 800);
        await page.evaluate(async () => {
            window.state.previewZoom = 90;
            await window.state.renderDocument(window.pdfLibrary);
        });
        assert.equal(await page.locator('#viewer .pdf-annotation-page').first().evaluate((element) => element.offsetHeight), 720);
        assert.equal(await page.locator('#viewer .textLayer').count(), 2);
        assert.deepEqual(await page.evaluate(() => window.renderedAnnotations), [1, 2, 1, 2, 1, 2]);
        await page.evaluate(async () => {
            window.state.previewZoom = 100;
            window.state.previewFit = 'width';
            await window.state.renderDocument(window.pdfLibrary);
        });
        assert.equal(await page.locator('#viewer .pdf-annotation-page').first().evaluate((element) => element.offsetHeight), 1600);
    } finally {
        await browser.close();
    }
});
}

test('draft review comments expose Edit and Delete directly while sent comments stay locked', async () => {
    const source = await readFile(new URL('../../resources/views/topics/file-annotations.blade.php', import.meta.url), 'utf8');
    const actions = source.match(/<div data-annotation-actions[\s\S]*?<\/div>/)[0];
    const alpine = await readFile(new URL('../../node_modules/alpinejs/dist/cdn.min.js', import.meta.url), 'utf8');
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        const page = await browser.newPage();
        await page.setContent(`<style>[x-cloak]{display:none!important}</style><div x-data="{ canAnnotate: true, annotation: {canEdit: true, state: 'draft'}, index: 0, draftSelection: null, saving: false, deletingAnnotationId: null, editAnnotation() { window.lastAction = 'edit' }, deleteAnnotation() { window.lastAction = 'delete' } }">${actions}</div>`);
        await page.addScriptTag({ content: alpine });
        const edit = page.getByRole('button', { name: 'Edit comment 1', exact: true });
        const remove = page.getByRole('button', { name: 'Delete comment 1', exact: true });
        await edit.click();
        assert.equal(await page.evaluate(() => window.lastAction), 'edit');
        await remove.click();
        assert.equal(await page.evaluate(() => window.lastAction), 'delete');
        await page.evaluate(() => {
            window.Alpine.$data(document.querySelector('[x-data]')).annotation.state = 'requested';
        });
        await page.waitForFunction(() => document.querySelector('[data-annotation-actions]').style.display === 'none');
        assert.equal(await edit.isVisible(), false);
        assert.equal(await remove.isVisible(), false);
    } finally {
        await browser.close();
    }
});

test('duplicate PDF text-layer rectangles keep the tighter highlight', () => {
    const rectangles = consolidateTextRectangles([
        { x: 0.1, y: 0.2, width: 0.45, height: 0.04 },
        { x: 0.1, y: 0.2, width: 0.18, height: 0.04 },
    ]);

    assert.deepEqual(rectangles, [
        { x: 0.1, y: 0.2, width: 0.18, height: 0.04 },
    ]);
});

test('adjacent fragments on the same line become one precise highlight', () => {
    const rectangles = consolidateTextRectangles([
        { x: 0.1, y: 0.2, width: 0.08, height: 0.03 },
        { x: 0.185, y: 0.201, width: 0.1, height: 0.029 },
    ]);

    assert.equal(rectangles.length, 1);
    assert.equal(rectangles[0].x, 0.1);
    assert.ok(Math.abs(rectangles[0].width - 0.185) < Number.EPSILON * 2);
});

test('fragments on separate lines stay separate', () => {
    const rectangles = consolidateTextRectangles([
        { x: 0.1, y: 0.2, width: 0.2, height: 0.03 },
        { x: 0.1, y: 0.25, width: 0.2, height: 0.03 },
    ]);

    assert.equal(rectangles.length, 2);
});


test('portrait and landscape PDF pages fit the available pane width at desktop and smaller sizes', () => {
    for (const pageWidth of [595, 842, 1224]) {
        for (const available of [352, 608, 928]) {
            assert.ok(Math.abs(pageWidth * pdfScaleToFit(pageWidth, available) - available) < 0.001);
        }
    }
});

test('PDF highlight selection notifies the editor while parent-driven selection avoids feedback loops', (t) => {
    const original = globalThis.window;
    t.after(() => { globalThis.window = original; });
    const notifications = [];
    globalThis.window = { athenaRevisionPdf: { onSelect: (id) => notifications.push(id) } };
    let factory;
    registerPdfAnnotationWorkspace({ data: (_name, callback) => { factory = callback; } });
    const state = factory();
    state.config = { fitWidth: true };
    const annotation = { id: 11, pageNumber: 2 };
    state.annotations = [annotation];
    state.jumpToAnnotation(annotation, false);
    assert.equal(state.selectedAnnotationId, 11);
    assert.deepEqual(notifications, []);
    state.selectAnnotation(annotation);
    assert.deepEqual(notifications, [11]);
});

function annotationWorkspace() {
    let factory;
    registerPdfAnnotationWorkspace({ data: (_name, callback) => { factory = callback; } });
    const state = factory();
    state.canAnnotate = true;
    state.$el = { querySelectorAll: () => [] };
    state.$refs = {};
    state.$nextTick = (callback) => callback();
    state.selectAnnotation = (annotation) => { state.selectedAnnotationId = annotation.id; };
    state.config = { storeUrl: '/annotations', updateUrlTemplate: '/annotations/__ANNOTATION__', fileId: 1 };
    return state;
}

test('inline papers wait until visible and release their visibility observer when removed', (t) => {
    const originalWindow = globalThis.window;
    const originalObserver = globalThis.IntersectionObserver;
    t.after(() => { globalThis.window = originalWindow; globalThis.IntersectionObserver = originalObserver; });
    globalThis.window = { location: { search: '' }, addEventListener() {}, clearTimeout() {} };
    let callback;
    let observed;
    let disconnections = 0;
    globalThis.IntersectionObserver = class {
        constructor(handler) { callback = handler; }
        observe(element) { observed = element; }
        disconnect() { disconnections++; }
    };
    const state = annotationWorkspace();
    state.$el = { dataset: { pdfAnnotationConfig: JSON.stringify({ fitWidth: true, loadWhenVisible: true }) } };
    state.$nextTick = () => {};
    let loads = 0;
    state.loadPdf = () => { loads++; };
    state.init();
    assert.equal(observed, state.$el);
    assert.equal(loads, 0);
    callback([{ isIntersecting: false }]);
    assert.equal(loads, 0);
    callback([{ isIntersecting: true }]);
    assert.equal(loads, 1);
    assert.equal(disconnections, 1);
    state.destroy();
    assert.equal(disconnections, 2);
});

test('a missing PDF viewer module offers refresh and a later successful load clears the error', async () => {
    const state = annotationWorkspace();
    await state.loadPdf(async () => { throw new TypeError('Failed to fetch dynamically imported module: /build/assets/pdf-old.js'); });
    assert.equal(state.viewerRefreshRequired, true);
    assert.match(state.loadError, /Reload this page/);
    assert.equal(state.loadError.includes('pdf-old.js'), false);
    assert.equal(state.loading, false);

    state.renderDocument = async () => {};
    state.focusRequestedAnnotation = () => {};
    await state.loadPdf(async () => ({ getDocument: () => ({ promise: Promise.resolve({}) }) }));
    assert.equal(state.viewerRefreshRequired, false);
    assert.equal(state.loadError, '');
    assert.equal(state.loading, false);
});

test('an unavailable report PDF offers retry without asking for a viewer refresh', async () => {
    const state = annotationWorkspace();
    await state.loadPdf(async () => ({ getDocument: () => ({ promise: Promise.reject(new Error('Report PDF not found.')) }) }));
    assert.equal(state.viewerRefreshRequired, false);
    assert.equal(state.loadError, 'Report PDF not found.');
    assert.equal(state.loading, false);
});

test('annotation workspace is always ready for Research Head feedback', () => {
    const state = annotationWorkspace();
    state.annotations = [{ id: 1 }, { id: 2 }];

    assert.equal(state.activeReviewer, 'research_head');
    assert.equal(state.reviewerReady, true);
    assert.equal(state.reviewerCommentCount, 2);
    assert.equal(state.reviewerInitials('Neil Delmo'), 'ND');
});

test('pin placement stays inside the page even at its edges', async () => {
    const { pinRectangle } = await import('../../resources/js/pdf-annotation-workspace.js');
    const rectangle = pinRectangle(1500, -10, { left: 100, top: 100, width: 600, height: 900 });
    assert.equal(rectangle.x, 0.999);
    assert.equal(rectangle.y, 0);
    assert.ok(rectangle.x + rectangle.width <= 1);
    assert.ok(rectangle.y + rectangle.height <= 1);
});

test('comment composer stays inside desktop and phone viewports', async () => {
    const { commentComposerPosition } = await import('../../resources/js/pdf-annotation-workspace.js');
    for (const viewport of [{ width: 1366, height: 768 }, { width: 390, height: 300 }]) {
        const position = commentComposerPosition({ left: 900, right: 1000, top: 700, bottom: 740 }, viewport, 400);
        assert.ok(position.left >= 12);
        assert.ok(position.top >= 12);
        assert.ok(position.left + position.width <= viewport.width - 12);
        assert.ok(position.top + Math.min(400, position.maxHeight) <= viewport.height);
    }
});

test('a marked location can be saved with a comment and no editor field', async (t) => {
    const state = annotationWorkspace();
    state.editorTargets = [{ value: 'activity-1' }];
    state.draftSelection = { type: 'pin', pageNumber: 1, rectangles: [{ x: 0.2, y: 0.3, width: 0.001, height: 0.001 }] };
    state.draftComment = 'Explain the timing.';
    t.mock.method(globalThis, 'fetch', async (_url, options) => {
        const body = JSON.parse(options.body);
        assert.equal(options.method, 'POST');
        assert.equal(body.editor_target, null);
        assert.equal(body.annotation_type, 'pin');
        return Response.json({ id: 1, pageNumber: 1, type: 'pin', comment: body.comment });
    });
    await state.saveAnnotation();
    assert.equal(state.annotations.length, 1);
    assert.equal(state.revisionCandidates[0].annotationCount, 1);
    assert.equal(state.draftSelection, null);
});

test('editing a draft replaces its comment without adding a revision candidate', async (t) => {
    const state = annotationWorkspace();
    state.annotations = [{ id: 4, type: 'area', pageNumber: 1, comment: 'Before' }];
    state.revisionCandidates = [{ fileId: 1, annotationCount: 1 }];
    state.editingAnnotationId = 4;
    state.draftSelection = { type: 'area', pageNumber: 1, rectangles: [] };
    state.draftComment = 'After';
    t.mock.method(globalThis, 'fetch', async (url, options) => {
        assert.equal(url, '/annotations/4');
        assert.equal(options.method, 'PATCH');
        return Response.json({ id: 4, pageNumber: 1, comment: 'After' });
    });
    await state.saveAnnotation();
    assert.equal(state.annotations.length, 1);
    assert.equal(state.annotations[0].comment, 'After');
    assert.equal(state.revisionCandidates[0].annotationCount, 1);
    assert.equal(state.editingAnnotationId, null);
});

test('failed saves keep the comment draft and allow retry', async (t) => {
    const state = annotationWorkspace();
    state.draftSelection = { type: 'area', pageNumber: 1, rectangles: [] };
    state.draftComment = 'Keep this feedback.';
    t.mock.method(globalThis, 'fetch', async () => Response.json({ message: 'Please retry.' }, { status: 503 }));
    await state.saveAnnotation();
    assert.equal(state.draftComment, 'Keep this feedback.');
    assert.notEqual(state.draftSelection, null);
    assert.equal(state.annotations.length, 0);
    assert.equal(state.saving, false);
    assert.equal(state.saveError, 'Please retry.');
});

test('a new selection cannot overwrite an unfinished comment', () => {
    const state = annotationWorkspace();
    state.draftSelection = { type: 'text', pageNumber: 1 };
    state.draftComment = 'Unsaved feedback';
    state.openCommentComposer({ type: 'pin', pageNumber: 2 });
    assert.equal(state.draftSelection.type, 'text');
    assert.equal(state.draftComment, 'Unsaved feedback');
});


test('section matching uses the greatest overlap on the selected page', async () => {
    const { matchRevisionSection } = await import('../../resources/js/pdf-annotation-workspace.js');
    const sections = [
        { id: 'section-sdgs', pageNumber: 1, x: .1, y: .1, width: .8, height: .2 },
        { id: 'section-project-team', pageNumber: 1, x: .1, y: .3, width: .8, height: .4 },
        { id: 'section-proponent', pageNumber: 2, x: .1, y: .1, width: .8, height: .6 },
    ];
    const selection = { pageNumber: 1, rectangles: [{ x: .6, y: .25, width: .2, height: .2 }] };
    assert.equal(matchRevisionSection(sections, selection).id, 'section-project-team');
    assert.equal(matchRevisionSection(sections, { ...selection, pageNumber: 2 }).id, 'section-proponent');
    assert.equal(matchRevisionSection(sections, { ...selection, pageNumber: 3 }), null);
});

test('Research Head annotation save does not transmit reviewer impersonation fields', async (t) => {
    const state = annotationWorkspace();
    state.draftSelection = {type:'area',pageNumber:1,rectangles:[]};
    state.draftComment = 'Explain the sampling plan.';
    t.mock.method(globalThis, 'fetch', async (_url, options) => {
        const body = JSON.parse(options.body);
        assert.equal('feedback_source' in body, false);
        assert.equal('co_evaluator_name' in body, false);
        return Response.json({id:1,pageNumber:1,feedbackSource:'research_head'});
    });
    await state.saveAnnotation();
    assert.equal(state.annotations.length, 1);
});


test('LREC comments never send reviewer names', async (t) => {
    const state = annotationWorkspace();
    state.config.isLrecReview = true;
    state.draftSelection = { type: 'pin', pageNumber: 1, rectangles: [] };
    state.draftComment = 'Clarify the population.';
    let calls = 0;
    t.mock.method(globalThis, 'fetch', async (_url, options) => {
        calls++;
        const body = JSON.parse(options.body);
        assert.equal(Object.hasOwn(body, 'lrec_reviewer_name'), false);
        return Response.json({ id: 1, pageNumber: 1, comment: body.comment, lrecReviewerName: body.lrec_reviewer_name });
    });
    await state.saveAnnotation();
    assert.equal(calls, 1);
    state.draftSelection = { type: 'pin', pageNumber: 1, rectangles: [] };
    state.draftComment = 'Clarify the population.';
    await state.saveAnnotation();
    assert.equal(calls, 2);
    assert.equal(state.annotations.at(-1).lrecReviewerName, undefined);
});
