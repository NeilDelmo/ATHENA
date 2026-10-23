import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { chromium, expect } from '@playwright/test';

test('detailed proposal tools load only for their editor and survive delayed chunks and navigation', { timeout: 60000 }, async () => {
    const manifest = JSON.parse(readFileSync(resolve('public/build/manifest.json'), 'utf8'));
    const appEntry = manifest['resources/js/app.js'];
    const editorFile = manifest['resources/js/detailed-proposal-editor.js']?.file;
    assert.ok(editorFile, 'The detailed proposal editor must be a separate lazy chunk');
    const assets = new Set(Object.values(manifest).map(entry => entry.file));
    const origin = 'https://athena-editor-loading.test';
    let editorRequests = 0;
    let holdEditor = true;
    let releaseEditor;
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        const page = await browser.newPage();
        const errors = [];
        const unexpectedRequests = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.route(`${origin}/**`, async route => {
            const path = new URL(route.request().url()).pathname.slice(1);
            if (assets.has(path)) {
                if (path === editorFile) {
                    editorRequests++;
                    if (holdEditor) await new Promise(resolve => { releaseEditor = resolve; });
                }
                return route.fulfill({ body: readFileSync(resolve('public/build', path)), contentType: path.endsWith('.css') ? 'text/css' : 'text/javascript' });
            }
            if (!['editor', 'dashboard'].includes(path)) unexpectedRequests.push(path);
            const editorPage = path === 'editor';
            const embedded = new URL(route.request().url()).searchParams.has('revision_embed');
            return route.fulfill({ contentType: 'text/html', body: `<!doctype html><html><head>
                <meta name="csrf-token" content="test"><meta name="app-url" content="${origin}">
                <style>.hidden { display: none !important; } [x-cloak] { display: none !important; }</style>
                <script data-navigate-once>window.livewireScriptConfig = { csrf: 'test', uri: '/livewire/update', progressBar: '' };</script>
                <script type="module" src="/${appEntry.file}"></script>
            </head><body x-data data-app-shell data-auth-user-id="1" ${embedded ? 'data-revision-embedded' : ''}>
                <h1>${editorPage ? 'Detailed proposal' : 'Dashboard'}</h1>
                <a wire:navigate href="/editor">Open editor</a><a wire:navigate href="/dashboard">Open dashboard</a>
                ${embedded ? `<div data-revision-editor-context data-topic-id="7" data-draft-id="8" data-document-type="detailed_proposal"
                    data-targets='{"13":{"target":"rationale"}}'
                    data-original-source='{"general_objective":"Understand local monitoring.","rationale":"<p>Saved <strong>rationale</strong> content.</p>","specific_objectives":[{"description":"Map community needs."}]}'></div>` : ''}
                ${editorPage ? `<section data-editor-fixture data-detailed-proposal-workspace data-paper-editor data-detailed-proposal-autosave="true" data-paper-dirty="false" data-paper-project-details-complete="true" x-data="proposalDraftDetailedProposal({ projectLeader: 'Ada Santos', initialData: { general_objective: 'Understand local monitoring.', rationale: '<p>Saved <strong>rationale</strong> content.</p>', specific_objectives: [{description: 'Map community needs.'}] } })">
                    <form x-ref="form" data-paper-form data-detailed-proposal-autosave-form action="/save" method="post">
                        <label for="general-objective">General objective</label><textarea id="general-objective" name="general_objective" data-semantic-editor x-model="generalObjective"></textarea>
                        <label for="rationale">Rationale</label><textarea id="rationale" name="rationale" data-semantic-editor x-model="rationale"></textarea>
                        <template x-for="(objective, index) in specificObjectives" :key="objective.id"><textarea :name="'specific_objectives[' + index + '][description]'" x-model="objective.description"></textarea></template>
                    </form>
                    <button type="button" @click="workspacePickerOpen = !workspacePickerOpen">Choose person</button>
                    <p x-show="workspacePickerOpen" x-text="projectLeader"></p>
                </section>` : ''}
            </body></html>` });
        });
        const checkEditor = async () => {
            await expect(page.locator('#general-objective')).toHaveValue('Understand local monitoring.');
            await expect(page.locator('[contenteditable="true"][aria-label="General objective"]')).toHaveText('Understand local monitoring.');
            const rationale = page.locator('[contenteditable="true"][aria-label="Rationale"]');
            await expect(rationale).toHaveText('Saved rationale content.');
            await expect(rationale.locator('strong')).toHaveText('rationale');
            await expect(page.locator('[data-editor-fixture]')).not.toHaveAttribute('inert');
            await page.getByRole('button', { name: 'Choose person', exact: true }).click();
            await expect(page.getByText('Ada Santos', { exact: true })).toBeVisible();
        };

        await page.goto(`${origin}/dashboard`);
        await page.waitForFunction(() => window.Alpine?.store('researchAssistant'));
        assert.equal(editorRequests, 0, 'Ordinary pages must not download detailed proposal tools');
        await page.getByRole('link', { name: 'Open editor', exact: true }).click();
        await expect(page.getByRole('heading', { name: 'Detailed proposal', exact: true })).toBeVisible();
        await expect.poll(() => editorRequests).toBe(1);
        await expect(page.locator('[data-editor-fixture]')).toHaveAttribute('inert', '');
        await page.getByRole('link', { name: 'Open dashboard', exact: true }).click();
        await expect(page.getByRole('heading', { name: 'Dashboard', exact: true })).toBeVisible();
        holdEditor = false;
        const loaded = page.waitForResponse(`${origin}/${editorFile}`);
        releaseEditor();
        await loaded;
        await page.goBack();
        await checkEditor();
        assert.equal(editorRequests, 1, 'History must initialize an editor left while its chunk was pending');
        await page.getByRole('link', { name: 'Open dashboard', exact: true }).click();
        await expect(page.getByRole('heading', { name: 'Dashboard', exact: true })).toBeVisible();
        await page.goBack();
        await checkEditor();
        assert.equal(editorRequests, 1, 'History must restore an initialized editor');

        holdEditor = true;
        await page.goto(`${origin}/editor`);
        await expect.poll(() => editorRequests).toBe(2);
        await expect(page.locator('[data-editor-fixture]')).toHaveAttribute('inert', '');
        holdEditor = false;
        releaseEditor();
        await checkEditor();

        holdEditor = true;
        await page.goto(`${origin}/editor?revision_embed=1`);
        await expect.poll(() => editorRequests).toBe(3);
        await expect(page.locator('[data-revision-editor-context]')).toHaveAttribute('data-revision-tools-state', 'loading');
        assert.equal(await page.evaluate(() => Boolean(window.athenaRevisionEditor)), false, 'Embedded revisions must not snapshot a pending editor');
        releaseEditor();
        await checkEditor();
        await page.waitForFunction(() => window.athenaRevisionEditor);
        assert.deepEqual(await page.evaluate(() => window.athenaRevisionEditor.modificationStates()), { __document__: false, 13: false });
        await page.locator('[contenteditable="true"][aria-label="Rationale"]').fill('A revised rationale.');
        assert.deepEqual(await page.evaluate(() => window.athenaRevisionEditor.modificationStates()), { __document__: true, 13: true });
        assert.deepEqual(unexpectedRequests, [], 'Loading and restoring the editor must not trigger API or autosave requests');
        assert.deepEqual(errors, []);
    } finally {
        releaseEditor?.();
        await browser.close();
    }
});
