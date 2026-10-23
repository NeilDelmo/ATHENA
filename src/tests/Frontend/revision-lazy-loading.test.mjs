import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { chromium, expect } from '@playwright/test';

test('revision tools load only where needed and protect submission across slow chunks and navigation', { timeout: 60000 }, async () => {
    const manifest = JSON.parse(readFileSync(resolve('public/build/manifest.json'), 'utf8'));
    const appEntry = manifest['resources/js/app.js'];
    const revisionFile = manifest['resources/js/revision-workspace.js']?.file;
    const assets = new Set(Object.values(manifest).map(entry => entry.file));
    const origin = 'https://athena-revision-loading.test';
    let revisionRequests = 0;
    let submissions = 0;
    let holdChunk = true;
    let failChunk = false;
    let releaseChunk;
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        const page = await browser.newPage();
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.route(`${origin}/**`, async route => {
            const path = new URL(route.request().url()).pathname.slice(1);
            if (assets.has(path)) {
                if (path === revisionFile) {
                    revisionRequests++;
                    if (failChunk) return route.abort();
                    if (holdChunk) await new Promise(resolve => { releaseChunk = resolve; });
                }
                return route.fulfill({ body: readFileSync(resolve('public/build', path)), contentType: path.endsWith('.css') ? 'text/css' : 'text/javascript', headers: { 'Cache-Control': 'no-store' } });
            }
            if (path === 'send-revision') {
                submissions++;
                return route.fulfill({ body: '<h1>Revision submitted</h1>', contentType: 'text/html' });
            }
            const embedded = path === 'embedded';
            const revision = path === 'revision' || path === 'revision-with-editor';
            return route.fulfill({ contentType: 'text/html', body: `<!DOCTYPE html><html><head>
                <meta name="csrf-token" content="test"><meta name="app-url" content="${origin}">
                <script data-navigate-once>window.livewireScriptConfig = { csrf: 'test', uri: '/livewire/update', progressBar: '' };</script>
                <script type="module" src="/${appEntry.file}"></script>
            </head><body x-data data-auth-user-id="1" ${embedded ? 'data-revision-embedded' : 'data-app-shell'}>
                <h1>${embedded ? 'Embedded editor' : revision ? 'Revision workspace' : 'Dashboard'}</h1>
                ${embedded ? `<div data-revision-editor-context hidden data-topic-id="7" data-draft-id="8" data-document-type="work_plan"
                        data-targets='{}' data-original-source='{"answer":"Original"}'></div>
                    <div data-paper-editor x-data="{ autoSaveInFlight: false, autoSaveBlocked: false }">
                        <form data-paper-form><label>Editor answer<input name="answer" value="Original"></label>
                            <input name="document_version" type="hidden" value="3"></form>
                    </div>` : `<a wire:navigate href="/revision">Open revision</a><a wire:navigate href="/dashboard">Open dashboard</a>`}
                ${revision ? `<form data-revision-workspace="7" data-revision-start-step="1" action="/send-revision" method="post" novalidate>
                    <input name="revision_draft_id" type="hidden"><input name="title" value="Revised proposal">
                    <input name="estimated_budget" value="100"><input name="estimated_duration_months" value="12">
                    <p data-revision-progress></p><p data-revision-step-error hidden></p>
                    <section data-revision-step="1" data-revision-step-label="Read feedback">Feedback</section>
                    <section data-revision-step="2" data-revision-step-label="Revise and respond" hidden>Responses</section>
                    <section data-revision-step="3" data-revision-step-label="Confirm details" hidden><input type="checkbox" data-revision-details-confirmed></section>
                    <section data-revision-step="4" data-revision-step-label="Submit" hidden>Review</section>
                    <button type="button" data-revision-step-back>Back</button><button type="button" data-revision-step-continue>Continue</button>
                    <button type="submit" data-revision-submit-button><span data-revision-submit-button-spinner hidden></span><span data-revision-submit-button-label>Submit revision</span></button>
                    <div data-revision-submit-error hidden><p data-revision-submit-error-message></p></div>
                    <div data-revision-submit-overlay class="hidden"><p data-revision-submit-title></p><p data-revision-submit-status></p></div>
                    ${path === 'revision-with-editor' ? `<section data-revision-document="work_plan" data-revision-label="Work Plan">
                        <iframe title="Work Plan editor" data-revision-editor-frame></iframe><p data-revision-editor-status></p><p data-revision-editor-loading hidden>Loading editor</p>
                    </section>` : ''}
                </form>` : ''}
            </body></html>` });
        });

        await page.goto(`${origin}/dashboard`);
        await page.waitForFunction(() => window.Alpine?.store('researchAssistant'));
        assert.equal(revisionRequests, 0);
        await page.getByRole('link', { name: 'Open revision', exact: true }).click();
        await page.getByRole('heading', { name: 'Revision workspace', exact: true }).waitFor();
        await expect.poll(() => revisionRequests).toBe(1);
        await page.locator('[data-revision-workspace]').evaluate(form => form.requestSubmit());
        await expect(page.locator('[data-revision-submit-error-message]')).toContainText('loading');
        await page.waitForTimeout(50);
        assert.equal(submissions, 0, 'an unloaded revision workflow must never submit natively');
        await page.getByRole('link', { name: 'Open dashboard', exact: true }).click();
        await page.getByRole('heading', { name: 'Dashboard', exact: true }).waitFor();
        holdChunk = false;
        const chunkLoaded = page.waitForResponse(`${origin}/${revisionFile}`);
        releaseChunk();
        await chunkLoaded;
        await page.getByRole('link', { name: 'Open revision', exact: true }).click();
        await expect(page.locator('[data-revision-workspace]')).toHaveAttribute('data-revision-initialized', 'true');
        await page.evaluate(() => document.dispatchEvent(new Event('livewire:navigated')));
        await page.locator('[data-revision-step-continue]').click();
        await expect(page.locator('[data-revision-progress]')).toHaveText('Step 2 of 4 - Revise and respond');
        assert.equal(revisionRequests, 1, 'navigation reuses the loaded revision module');

        await page.goto(`${origin}/revision-with-editor`);
        await expect(page.locator('[data-revision-workspace]')).toHaveAttribute('data-revision-initialized', 'true');
        holdChunk = true;
        const beforeFrame = revisionRequests;
        await page.locator('iframe').evaluate(frame => {
            window.editorReadyEvents = 0;
            frame.addEventListener('revision-editor-ready', () => { window.editorReadyEvents++; });
            frame.src = '/embedded';
        });
        await expect.poll(() => revisionRequests).toBe(beforeFrame + 1);
        const editor = page.frameLocator('iframe');
        await expect(editor.getByRole('heading', { name: 'Embedded editor', exact: true })).toBeVisible();
        await expect(page.locator('[data-revision-editor-status]')).not.toContainText('could not load');
        await expect(page.locator('[data-revision-editor-loading]')).toBeVisible();
        holdChunk = false;
        releaseChunk();
        await expect(page.locator('[name="revision_draft_id"]')).toHaveValue('8');
        await expect(page.locator('[data-revision-editor-status]')).toContainText('Changes save as you type');
        await expect(page.locator('[data-revision-editor-loading]')).toBeHidden();
        assert.equal(await page.evaluate(() => window.editorReadyEvents), 1);
        const frame = page.frames().find(frame => frame.url().endsWith('/embedded'));
        await frame.evaluate(() => {
            window.initialRevisionApi = window.athenaRevisionEditor;
            window.changeNotifications = 0;
            window.athenaRevisionEditor.onChange = () => { window.changeNotifications++; };
            document.dispatchEvent(new Event('livewire:navigated'));
        });
        await expect.poll(() => frame.evaluate(() => window.athenaRevisionEditor === window.initialRevisionApi)).toBe(true);
        await editor.getByLabel('Editor answer').fill('Changed answer');
        await expect.poll(() => frame.evaluate(() => window.changeNotifications)).toBe(1);
        assert.equal(await frame.evaluate(() => window.athenaRevisionEditor.modificationStates().__document__), true);
        assert.equal(await page.evaluate(() => window.editorReadyEvents), 1);

        failChunk = true;
        const beforeEditorFailure = revisionRequests;
        await page.locator('iframe').evaluate(frame => { frame.src = '/embedded?failure=1'; });
        await expect.poll(() => revisionRequests).toBe(beforeEditorFailure + 1);
        await expect(page.locator('[data-revision-editor-status]')).toContainText('could not load');
        await expect(page.locator('[data-revision-editor-loading]')).toBeHidden();
        const beforeFailure = revisionRequests;
        await page.goto(`${origin}/revision`);
        await expect.poll(() => revisionRequests).toBe(beforeFailure + 1);
        await page.locator('[data-revision-workspace]').evaluate(form => form.requestSubmit());
        await expect(page.locator('[data-revision-submit-error-message]')).toContainText('Reload');
        await page.waitForTimeout(50);
        assert.equal(submissions, 0, 'failed chunks must keep revision submission blocked');
        assert.deepEqual(errors, []);
    } finally {
        releaseChunk?.();
        await browser.close();
    }
});
