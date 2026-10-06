import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { chromium } from '@playwright/test';
import { loadRevisionEditorFrame } from '../../resources/js/revision-workspace.js';

function commentResponsePdf() {
    const stream = 'BT /F1 16 Tf 72 720 Td (COMMENT RESPONSE FORM) Tj /F1 12 Tf 0 -40 Td (Project: Revision loading regression) Tj 0 -32 Td (Reviewer comments) Tj 0 -24 Td (Clarify the proposed methods and schedule.) Tj 0 -24 Td (Clarify the sampling method.) Tj 0 -48 Td (Action and response) Tj ET';
    const objects = [
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [4 0 R] /Count 1 >>',
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R >>',
        `<< /Length ${stream.length} >>\nstream\n${stream}\nendstream`,
    ];
    let pdf = '%PDF-1.7\n';
    const offsets = objects.map((object, index) => {
        const offset = pdf.length;
        pdf += `${index + 1} 0 obj\n${object}\nendobj\n`;
        return offset;
    });
    const xref = pdf.length;
    return Buffer.from(pdf + `xref\n0 6\n0000000000 65535 f \n${offsets.map((offset) => `${String(offset).padStart(10, '0')} 00000 n \n`).join('')}trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n${xref}\n%%EOF`);
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
        env: { ...process.env, ATHENA_REVISION_BROWSER_FIXTURE: fixturePath },
    });
    const html = readFileSync(fixturePath, 'utf8').replaceAll('http://localhost', 'http://athena-revision.test');
    const manifest = JSON.parse(readFileSync(resolve('public/build/manifest.json'), 'utf8'));
    const assets = new Set(Object.values(manifest).map((entry) => entry.file));
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        const page = await browser.newPage();
        const errors = [];
        const editorRequests = [];
        const paperRequests = [];
        page.on('pageerror', (error) => errors.push(error.message));
        await page.route('**/*', async (route) => {
            const url = new URL(route.request().url());
            const asset = url.pathname.replace(/^\/build\//, '');
            if (assets.has(asset) || asset.startsWith('assets/pdf.worker-')) {
                await route.fulfill({ body: readFileSync(resolve('public/build', asset)), contentType: asset.endsWith('.css') ? 'text/css' : 'text/javascript' });
            } else if (url.pathname.endsWith('/comment-response-form/pdf')) {
                paperRequests.push(url);
                await route.fulfill({ contentType: 'application/pdf', body: commentResponsePdf() });
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
        await page.goto('http://athena-revision.test/revision');
        await page.waitForFunction(() => document.querySelector('[data-revision-workspace]')?.dataset.revisionInitialized === 'true');
        assert.deepEqual(editorRequests, []);
        const paper = page.locator('[data-comment-response-paper]');
        const waitForPaperFit = () => page.waitForFunction(() => {
            const viewer = document.querySelector('[data-comment-response-paper] .revision-pdf-pages');
            const sheet = viewer?.querySelector('.pdf-annotation-page');
            if (!sheet) return false;
            const style = getComputedStyle(viewer);
            const available = viewer.clientWidth - parseFloat(style.paddingLeft) - parseFloat(style.paddingRight);
            return Math.abs(sheet.getBoundingClientRect().width - available) < 1;
        });
        await paper.scrollIntoViewIfNeeded();
        await page.waitForFunction(() => {
            const viewer = document.querySelector('[data-comment-response-paper] [data-pdf-annotation-config]');
            return viewer.querySelector('canvas') || window.Alpine.$data(viewer).loadError;
        });
        assert.equal(await paper.locator('[data-pdf-annotation-config]').evaluate((element) => window.Alpine.$data(element).loadError), '');
        await paper.locator('.pdf-annotation-page canvas').waitFor();
        await waitForPaperFit();
        assert.equal(paperRequests.length, 1);
        assert.equal(paperRequests[0].searchParams.get('source'), 'research_head');
        assert.ok(Number(paperRequests[0].searchParams.get('review')) > 0);
        assert.equal(await page.locator('[data-revision-step="1"] blockquote, [data-comment-response-preview]').count(), 0);
        await paper.locator('.pdf-annotation-page').click({ position: { x: 20, y: 20 } });
        assert.equal(await paper.evaluate((element) => element.matches(':modal')), true);
        assert.equal(await page.locator('[data-comment-response-paper-close]').isVisible(), true);
        await page.keyboard.press('Escape');
        assert.equal(await paper.evaluate((element) => element.matches(':modal')), false);
        assert.equal(await paper.getAttribute('open'), '');
        await paper.locator('[data-comment-response-paper-open]').press('Enter');
        assert.equal(await paper.evaluate((element) => element.matches(':modal')), true);
        await paper.locator('[data-comment-response-paper-close]').click();
        assert.equal(await paper.evaluate((element) => element.matches(':modal')), false);
        await paper.locator('.pdf-annotation-page canvas').waitFor();
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
        await paper.locator('.pdf-annotation-page canvas').waitFor();
        assert.equal(paperRequests.length, 1);
        for (const width of [390, 1440]) {
            await page.setViewportSize({ width, height: 1000 });
            for (const dark of [false, true]) {
                await page.evaluate((enabled) => document.documentElement.classList.toggle('dark', enabled), dark);
                await paper.locator('.pdf-annotation-page canvas').waitFor();
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

        const annotationId = await detailed.locator('[data-revision-comment] option[data-annotation-id]').last().getAttribute('data-annotation-id');
        editorRequests.length = 0;
        paperRequests.length = 0;
        await page.goto(`http://athena-revision.test/revision?revision_annotation=${annotationId}`);
        await page.locator('[data-revision-document="detailed_proposal"] [data-revision-dialog][open]').waitFor();
        await page.frameLocator('[data-revision-document="detailed_proposal"] [data-revision-editor-frame]').getByRole('textbox', { name: 'Paper text' }).waitFor();
        assert.deepEqual(editorRequests, ['detailed_proposal']);
        assert.equal(paperRequests.length, 0);
        assert.deepEqual(errors, []);
    } finally {
        await browser.close();
    }
});
