import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { existsSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { chromium } from '@playwright/test';
import { matchingPassages, readPdfPassages } from '../../resources/js/comment-response-location.js';

test('the real revision page and editor automatically locate a reply in the official PDF and send it', { timeout: 120_000 }, async () => {
    const fixturePath = resolve('../tmp/revision-automation.json');
    const env = { ...process.env };
    const installedOffice = 'C:/Program Files/LibreOffice/program/soffice.com';
    if (!env.LIBREOFFICE_BINARY && existsSync(installedOffice)) env.LIBREOFFICE_BINARY = installedOffice;
    if (!process.env.ATHENA_REUSE_REVISION_AUTOMATION_FIXTURE) {
        execFileSync('php', ['artisan', 'test', '--compact', 'tests/Feature/DetailedProposalBuilderTest.php', '--filter=automatic revision replies use'], {
            cwd: resolve('.'), encoding: 'utf8', env: { ...env, ATHENA_REVISION_AUTOMATION_FIXTURE: fixturePath },
        });
    }
    const fixture = JSON.parse(readFileSync(fixturePath, 'utf8'));
    const pdf = Buffer.from(fixture.pdf, 'base64');
    const pdfJs = await import('pdfjs-dist/legacy/build/pdf.mjs');
    const passages = await readPdfPassages(new Blob([pdf], { type: 'application/pdf' }), 'Detailed Research Proposal', async () => pdfJs);
    const expectedLocations = matchingPassages(passages, [{ text: 'SDG4: Quality Education', context: 'III. Sustainable Development Goal:' }]);
    assert.equal(expectedLocations.length, 1, 'The official paper must have one identifiable SDG4 entry');
    const expected = expectedLocations[0];
    const origin = 'http://athena-automation.test';
    const html = (name) => fixture[name].replaceAll('http://localhost', origin);
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
        page.setDefaultTimeout(15_000);
        const errors = [];
        const saves = [];
        const downloads = [];
        const staged = [];
        let finalSubmission;
        let documentVersion = fixture.documentVersion;
        let draftVersion = fixture.draftVersion;
        page.on('pageerror', (error) => errors.push(error.message));
        await page.route('**/*', async (route) => {
            const request = route.request();
            const url = new URL(request.url());
            if (url.pathname.startsWith('/build/')) {
                const path = resolve('public', url.pathname.slice(1));
                await route.fulfill({ body: readFileSync(path), contentType: path.endsWith('.css') ? 'text/css' : path.endsWith('.woff2') ? 'font/woff2' : 'text/javascript' });
            } else if (url.hostname === 'fonts.bunny.net') {
                await route.fulfill({ contentType: 'text/css', body: '' });
            } else if (url.pathname.endsWith('/comment-response-form/preview')) {
                await route.fulfill({ contentType: 'text/html', body: html('commentPaper') });
            } else if (url.pathname.endsWith('/detailed-proposal/download')) {
                downloads.push(request.postDataBuffer());
                await route.fulfill({ contentType: 'application/pdf', body: pdf, headers: { 'Content-Disposition': 'attachment; filename="detailed-proposal.pdf"' } });
            } else if (url.pathname.endsWith('/detailed-proposal/preview')) {
                await route.fulfill({ contentType: 'text/html', body: html('paperPreview') });
            } else if (url.pathname.endsWith('/revision-files') && request.method() === 'POST') {
                staged.push(request.postDataBuffer());
                await route.fulfill({ json: { draft_id: fixture.draftId, document_type: 'detailed_proposal', document_version: ++documentVersion, filename: 'detailed-proposal.pdf' } });
            } else if (url.pathname.endsWith('/detailed-proposal') && request.method() !== 'GET') {
                saves.push(request.postDataBuffer());
                await route.fulfill({ json: { document_version: ++documentVersion, draft_version: ++draftVersion, saved_as_draft: false, completion_errors: [], methodology_images: [] } });
            } else if (request.isNavigationRequest() && request.frame() !== page.mainFrame()) {
                await route.fulfill({ contentType: 'text/html', body: url.searchParams.has('document_type') || url.pathname.endsWith('/detailed-proposal')
                    ? html('editor') : '<!DOCTYPE html><body><script>window.athenaRevisionPdf = { focus() {} };</script></body>' });
            } else if (request.isNavigationRequest() && request.method() === 'POST') {
                finalSubmission = request.postDataBuffer().toString();
                await route.fulfill({ contentType: 'text/html', body: '<!DOCTYPE html><h1>Revision received</h1>' });
            } else if (request.isNavigationRequest()) {
                await route.fulfill({ contentType: 'text/html', body: html('parent') });
            } else {
                await route.fulfill({ json: { notifications: [], unread_count: 0 } });
            }
        });
        await page.goto(`${origin}/faculty/topics/${fixture.topicId}/revision`);
        await page.waitForFunction(() => document.querySelector('[data-revision-workspace]')?.dataset.revisionInitialized === 'true');
        assert.deepEqual(errors, []);
        assert.equal(downloads.length, 0);
        await page.locator('[data-revision-step-continue]').click();
        const card = page.locator('[data-revision-document="detailed_proposal"]');
        const location = card.locator('[data-comment-response-location]');
        await card.locator('[data-revision-open]').click();
        const frame = await card.locator('[data-revision-editor-frame]').elementHandle().then((element) => element.contentFrame());
        await frame.waitForFunction(() => Boolean(window.athenaRevisionEditor));
        assert.deepEqual(errors, []);
        assert.equal(await frame.evaluate(() => window.athenaRevisionEditor.modificationStates().__document__), false, 'Merely opening the paper must not count as editing it');
        const method = frame.locator('textarea[name="specific_method_objectives[0][methods][0][description]"]');
        const originalMethod = await method.inputValue();
        await method.fill(originalMethod + ' Include participant interviews.');
        assert.equal(await frame.evaluate(() => window.athenaRevisionEditor.modificationStates().__document__), true);
        await method.fill(originalMethod);
        assert.equal(await frame.evaluate(() => window.athenaRevisionEditor.modificationStates().__document__), false);
        assert.equal(await location.locator('select, input[type="number"], input:not([type="hidden"]):not([hidden])').count(), 0);
        await frame.locator('input[name="sdgs[]"][value="4"]').check();
        const reply = 'Added Quality Education to reflect the education and training outcomes.';
        await card.locator('[data-revision-response-document]').fill(reply);
        assert.equal(await frame.evaluate(() => window.athenaRevisionEditor.modificationStates().__document__), true);
        await card.locator('[data-revision-close]').click();
        await page.waitForFunction(() => ['detected', 'error', 'confirm'].includes(document.querySelector('[data-comment-response-location]').dataset.locationState));
        assert.equal(await location.getAttribute('data-location-state'), 'detected', await location.locator('[data-location-status]').textContent());
        assert.equal(await location.locator('[data-comment-response-page]').inputValue(), String(expected.page));
        assert.equal(await location.locator('[data-comment-response-paragraph]').inputValue(), String(expected.paragraph));
        assert.equal(downloads.length, 1);
        assert.equal(staged.length, 1);
        assert.match(saves.at(-1).toString(), /name="sdgs\[\]"\r?\n\r?\n4/);
        const firstDownloadCount = downloads.length;
        await card.locator('[data-revision-open]').click();
        await card.locator('[data-revision-close]').click();
        await page.waitForFunction(() => document.querySelector('[data-comment-response-location]').dataset.locationState === 'detected');
        assert.equal(downloads.length, firstDownloadCount, 'Reopening without changes must reuse the detected location');

        await card.locator('[data-revision-open]').click();
        await card.locator('[data-revision-no-change]').check();
        assert.equal(await location.locator('[data-comment-response-no-change]').isChecked(), true);
        assert.equal(await location.locator('[data-comment-response-page]').isDisabled(), true);
        assert.equal(await card.locator('[data-revision-response-document]').inputValue(), reply);
        await card.locator('[data-revision-close]').click();
        assert.equal(downloads.length, firstDownloadCount, 'Keeping the submitted paper must not generate an unused PDF');
        await card.locator('[data-revision-open]').click();
        await card.locator('[data-revision-edit-paper]').check();
        await card.locator('[data-revision-close]').click();
        await page.waitForFunction(() => document.querySelector('[data-comment-response-location]').dataset.locationState === 'detected');
        assert.equal(await location.locator('[data-comment-response-no-change]').isChecked(), false);
        assert.equal(await location.locator('[data-comment-response-page]').isDisabled(), false);

        await page.locator('[data-revision-step-continue]').click();
        await page.locator('[data-revision-details-confirmed]').check();
        await page.locator('[data-revision-step-continue]').click();
        await page.locator('[data-revision-submit-button]').click();
        await page.locator('.swal2-confirm').click();
        await page.getByRole('heading', { name: 'Revision received' }).waitFor();
        assert.deepEqual(errors, []);
        const field = (name) => finalSubmission.match(new RegExp(`name="${name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}"\\r?\\n\\r?\\n([^\\r\\n]*)`))?.[1];
        const key = `feedback_responses[annotation_${fixture.annotationId}]`;
        assert.equal(field(`${key}[response]`), reply);
        assert.equal(field(`${key}[no_change]`), '0');
        assert.equal(field(`${key}[page]`), String(expected.page));
        assert.equal(field(`${key}[paragraph]`), String(expected.paragraph));
        assert.equal(downloads.length, firstDownloadCount + 1, 'Submission verifies the location against the final generated PDF');
        assert.equal(staged.length, downloads.length);
        execFileSync('php', ['artisan', 'test', '--compact', 'tests/Feature/DetailedProposalBuilderTest.php', '--filter=automatic revision replies use'], {
            cwd: resolve('.'), encoding: 'utf8', env: { ...env, ATHENA_REVISION_AUTOMATION_REPLY: JSON.stringify({
                response: field(`${key}[response]`), no_change: Number(field(`${key}[no_change]`)),
                page: Number(field(`${key}[page]`)), paragraph: Number(field(`${key}[paragraph]`)),
            }) },
        });
    } finally {
        await browser.close();
    }
});
