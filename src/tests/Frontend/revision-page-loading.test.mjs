import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { chromium } from '@playwright/test';
import { loadRevisionEditorFrame } from '../../resources/js/revision-workspace.js';

function commentResponsePaper() {
    return `<!DOCTYPE html><html><head><meta charset="utf-8"><style>
        html, body { margin: 0; padding: 0; }
        body { font: 14px Arial, sans-serif; }
        .comment-response-sheet { box-sizing: border-box; width: 816px; min-height: 1056px; padding: 40px; background: white; }
    </style></head><body><main class="comment-response-sheet">
        <h1>COMMENT RESPONSE FORM</h1><p>Project: Revision loading regression</p>
        <h2>Reviewer comments</h2><p>Clarify the proposed methods and schedule.</p>
        <p>Clarify the sampling method.</p><h2>Action and response</h2>
    </main></body></html>`;
}

function deferredCard() {
    const attributes = new Map();
    let loads = 0;
    const frame = {
        dataset: { revisionEditorSrc: '/editor/work-plan' },
        contentWindow: { unsavedText: 'Keep this edit' },
        getAttribute: (name) => attributes.get(name),
        setAttribute(name, value) { attributes.set(name, value); loads++; },
    };
    const nodes = {
        '[data-revision-editor-frame]': frame,
        '[data-revision-editor-loading]': { hidden: true },
        '[data-revision-no-change]': { checked: false },
        'input[type="file"]': { files: [] },
    };
    return { frame, nodes, querySelector: (selector) => nodes[selector], get loads() { return loads; } };
}

test('opening an editor starts one request and reopening preserves the mounted editor', () => {
    const card = deferredCard();
    assert.equal(card.loads, 0);
    assert.equal(loadRevisionEditorFrame(card), true);
    assert.equal(card.frame.getAttribute('src'), '/editor/work-plan');
    assert.equal(card.nodes['[data-revision-editor-loading]'].hidden, false);
    assert.equal(loadRevisionEditorFrame(card), false);
    assert.equal(card.loads, 1);
    assert.equal(card.frame.contentWindow.unsavedText, 'Keep this edit');
});

test('keeping the submitted paper or selecting a replacement does not start an unused editor', () => {
    const card = deferredCard();
    card.nodes['[data-revision-no-change]'].checked = true;
    assert.equal(loadRevisionEditorFrame(card), false);
    card.nodes['[data-revision-no-change]'].checked = false;
    card.nodes['input[type="file"]'].files = [{}];
    assert.equal(loadRevisionEditorFrame(card), false);
    assert.equal(card.loads, 0);
    card.nodes['input[type="file"]'].files = [];
    assert.equal(loadRevisionEditorFrame(card), true);
});

test('the full revision page makes no editor requests until a paper or annotation is opened', async () => {
    const fixturePath = resolve('../tmp/revision-page-loading.html');
    execFileSync('php', ['artisan', 'test', '--compact', 'tests/Feature/RevisionPageLoadingTest.php'], {
        cwd: resolve('.'), encoding: 'utf8',
        env: { ...process.env, ATHENA_TEST_PRESERVE_DATABASE: '1', ATHENA_REVISION_BROWSER_FIXTURE: fixturePath },
    });
    const origin = 'https://athena-revision.test';
    const html = readFileSync(fixturePath, 'utf8').replaceAll('http://localhost', origin);
    const manifest = JSON.parse(readFileSync(resolve('public/build/manifest.json'), 'utf8'));
    const assets = new Set(Object.values(manifest).map((entry) => entry.file));
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        const page = await browser.newPage();
        const errors = [];
        const editorRequests = [];
        const paperRequests = [];
        const pdfRequests = [];
        page.on('pageerror', (error) => errors.push(error.message));
        await page.route('**/*', async (route) => {
            const url = new URL(route.request().url());
            const asset = url.pathname.replace(/^\/build\//, '');
            if (assets.has(asset) || asset.startsWith('assets/pdf.worker-')) {
                if (asset === manifest['resources/js/revision-workspace.js'].file) await new Promise((resolve) => setTimeout(resolve, 250));
                await route.fulfill({ body: readFileSync(resolve('public/build', asset)), contentType: asset.endsWith('.css') ? 'text/css' : 'text/javascript' });
            } else if (url.pathname.endsWith('/comment-response-form/preview')) {
                paperRequests.push(url);
                await route.fulfill({ contentType: 'text/html; charset=utf-8', body: commentResponsePaper() });
            } else if (url.pathname.endsWith('/comment-response-form/pdf')) {
                pdfRequests.push(url);
                await route.fulfill({ status: 500, body: 'Unexpected Comment Response PDF request' });
            } else if (url.hostname === 'fonts.bunny.net') {
                await route.fulfill({ contentType: 'text/css', body: '' });
            } else if (route.request().isNavigationRequest() && route.request().frame() !== page.mainFrame()) {
                const type = url.searchParams.get('document_type');
                const topicId = await page.locator('[data-revision-workspace]').getAttribute('data-revision-workspace');
                if (type) editorRequests.push(type);
                await route.fulfill({ contentType: 'text/html', body: `<!DOCTYPE html><body><input aria-label="Paper text" value="Original paper">
                    <script>
                        window.athenaRevisionEditor = { topicId: '${topicId}', documentType: '${type}', draftId: 99,
                            modificationStates: () => ({}), focus: () => {}, sourceFingerprint: () => 'original' };
                        window.athenaRevisionPdf = { focus: () => {} };
                    </script></body>` });
            } else if (route.request().isNavigationRequest()) {
                await route.fulfill({ contentType: 'text/html', body: html });
            } else {
                await route.fulfill({ json: { notifications: [], unread_count: 0 } });
            }
        });
        await page.goto(`${origin}/revision`);
        await page.waitForFunction(() => document.querySelector('[data-revision-workspace]')?.dataset.revisionInitialized === 'true');
        assert.deepEqual(editorRequests, []);
        const paper = page.locator('[data-comment-response-paper]');
        const viewer = paper.locator('[data-comment-response-preview]');
        const frame = viewer.locator('iframe');
        const waitForPaperFit = () => page.waitForFunction(() => {
            const frame = document.querySelector('[data-comment-response-preview] iframe');
            const sheet = frame?.contentDocument?.querySelector('.comment-response-sheet');
            return sheet && frame.contentDocument.body.style.zoom !== ''
                && Math.abs(sheet.getBoundingClientRect().width - Math.min(816, frame.clientWidth)) < 1;
        });
        await paper.scrollIntoViewIfNeeded();
        await page.waitForFunction(() => {
            const viewer = document.querySelector('[data-comment-response-preview]');
            const state = window.Alpine.$data(viewer);
            return state.previewReady || state.previewError;
        });
        assert.equal(await viewer.evaluate((element) => window.Alpine.$data(element).previewError), '');
        await paper.frameLocator('iframe').getByRole('heading', { name: 'COMMENT RESPONSE FORM', exact: true }).waitFor();
        await waitForPaperFit();
        await frame.evaluate((element) => { window.originalPreviewFrame = element; });
        assert.equal(paperRequests.length, 1);
        assert.equal(paperRequests[0].searchParams.get('source'), 'research_head');
        assert.ok(Number(paperRequests[0].searchParams.get('review')) > 0);
        assert.equal(await page.locator('[data-revision-step="1"] blockquote').count(), 0);
        assert.equal(await viewer.count(), 1);
        assert.equal(pdfRequests.length, 0);
        await viewer.click({ position: { x: 20, y: 20 } });
        assert.equal(await paper.evaluate((element) => element.matches(':modal')), true);
        assert.equal(await page.locator('[data-comment-response-paper-close]').isVisible(), true);
        await page.keyboard.press('Escape');
        assert.equal(await paper.evaluate((element) => element.matches(':modal')), false);
        assert.equal(await paper.getAttribute('open'), '');
        await paper.locator('[data-comment-response-paper-open]').press('Enter');
        assert.equal(await paper.evaluate((element) => element.matches(':modal')), true);
        await paper.locator('[data-comment-response-paper-close]').click();
        assert.equal(await paper.evaluate((element) => element.matches(':modal')), false);
        await waitForPaperFit();
        assert.equal(await frame.evaluate((element) => element === window.originalPreviewFrame), true);
        assert.equal(paperRequests.length, 1);
        await page.locator('[data-revision-step-continue]').click();
        const detailed = page.locator('[data-revision-document="detailed_proposal"]');
        const workPlan = page.locator('[data-revision-document="work_plan"]');
        await detailed.locator('[data-revision-open]').click();
        const originalEditor = detailed.frameLocator('[data-revision-editor-frame]').getByRole('textbox', { name: 'Paper text' });
        await originalEditor.fill('Unsaved edit retained');
        assert.deepEqual(editorRequests, ['detailed_proposal']);
        await detailed.locator('[data-revision-next]').click();
        await workPlan.frameLocator('[data-revision-editor-frame]').getByRole('textbox', { name: 'Paper text' }).waitFor();
        assert.deepEqual(editorRequests, ['detailed_proposal', 'work_plan']);
        await workPlan.locator('[data-revision-previous]').click();
        assert.equal(await originalEditor.inputValue(), 'Unsaved edit retained');
        assert.equal(editorRequests.length, 2);
        assert.deepEqual(errors, []);

        await detailed.locator('[data-revision-close]').click();
        await page.locator('[data-revision-step-back]').click();
        await waitForPaperFit();
        assert.equal(paperRequests.length, 1);
        for (const width of [390, 1440]) {
            await page.setViewportSize({ width, height: 1000 });
            for (const dark of [false, true]) {
                await page.evaluate((enabled) => document.documentElement.classList.toggle('dark', enabled), dark);
                await waitForPaperFit();
                await page.waitForFunction(() => document.documentElement.scrollWidth <= window.innerWidth);
                await paper.locator('[data-comment-response-paper-open]').press('Space');
                assert.equal(await paper.evaluate((element) => element.matches(':modal')), true);
                await page.waitForFunction(() => {
                    const dialog = document.querySelector('[data-comment-response-paper]');
                    const bounds = dialog.getBoundingClientRect();
                    return bounds.left >= 0 && bounds.right <= window.innerWidth && bounds.bottom <= window.innerHeight;
                });
                await waitForPaperFit();
                await page.keyboard.press('Escape');
                await waitForPaperFit();
                if (process.env.REVISION_WORKSPACE_SCREENSHOT_DIRECTORY) {
                    await page.evaluate(() => window.scrollTo(0, 0));
                    await page.screenshot({ fullPage: true, path: resolve(process.env.REVISION_WORKSPACE_SCREENSHOT_DIRECTORY, `revision-comment-response-${width}-${dark ? 'dark' : 'light'}.png`) });
                }
            }
        }
        assert.equal(await frame.evaluate((element) => element === window.originalPreviewFrame), true);
        assert.equal(pdfRequests.length, 0);

        const annotationId = await detailed.locator('[data-revision-comment] option[data-annotation-id]').last().getAttribute('data-annotation-id');
        editorRequests.length = 0;
        paperRequests.length = 0;
        await page.goto(`${origin}/revision?revision_annotation=${annotationId}`);
        await page.locator('[data-revision-document="detailed_proposal"] [data-revision-dialog][open]').waitFor();
        await page.frameLocator('[data-revision-document="detailed_proposal"] [data-revision-editor-frame]').getByRole('textbox', { name: 'Paper text' }).waitFor();
        assert.deepEqual(editorRequests, ['detailed_proposal']);
        assert.equal(paperRequests.length, 0);
        assert.equal(pdfRequests.length, 0);
        assert.deepEqual(errors, []);
    } finally {
        await browser.close();
    }
});
