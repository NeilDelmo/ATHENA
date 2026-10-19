import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { chromium, expect } from '@playwright/test';

const app = readFileSync(resolve('resources/js/app.js'), 'utf8');
const factoryStart = app.indexOf("Alpine.data('proposalDraftDetailedProposal'");
const factoryEnd = app.indexOf('\n}));', factoryStart) + '\n}));'.length;
const semantic = app.slice(app.indexOf('function sanitizedSemanticHtml('), app.indexOf('\nconst semanticEditorObserver'));
const moduleSource = (path) => readFileSync(resolve(path), 'utf8').replace(/^import .+;\r?\n/gm, '').replace(/^export /gm, '');
const components = execFileSync('php', ['-r', String.raw`
    require 'vendor/autoload.php'; $app = require 'bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    echo Illuminate\Support\Facades\Blade::render('<x-detailed-proposal-writing-toolbar /><x-proposal-source-workspace />');
`], { encoding: 'utf8', env: { ...process.env, APP_ENV: 'testing' } });
const manifest = JSON.parse(readFileSync(resolve('public/build/manifest.json'), 'utf8'));
const styles = [manifest['resources/css/app.css'].file, ...(manifest['resources/js/app.js'].css || [])]
    .map((file) => readFileSync(resolve('public/build', file), 'utf8')).join('\n');
const sources = [
    { id: 7, literature_source_id: 70, title: 'Community Monitoring', authors: 'M. Santos', year: 2024, reference: 'M. Santos, "Community Monitoring," Coastal Journal, 2024.' },
    { id: 12, literature_source_id: 120, title: 'Habitat Recovery', authors: 'A. Reyes', year: 2025, reference: 'A. Reyes, "Habitat Recovery," Ecology Journal, 2025.' },
];

async function openEditor(browser, width = 1440, dark = false) {
    const page = await browser.newPage({ viewport: { width, height: 900 } });
    const initializationErrors = [];
    page.on('pageerror', (error) => initializationErrors.push(error.message));
    await page.setContent(`<html class="${dark ? 'dark' : ''}"><style>${styles}[x-cloak]{display:none!important}</style><body class="bg-white p-4 dark:bg-slate-950" style="padding-top:10rem">
        <main data-detailed-proposal-workspace data-proposal-paper-workspace x-data="citationFixture"
            @proposal-cite-selection.window="openCitationPicker($event.detail)"
            @proposal-open-sources.window="openSourceWorkspaceFromToolbar($event.detail)">
            ${components}<form x-ref="form" class="proposal-edit-pane" data-detailed-proposal-autosave-form>
                <label for="related-literature">Review of Related Literature</label>
                <textarea id="related-literature" name="related_literature" data-semantic-editor x-model="relatedLiterature"></textarea>
                <label for="references">References</label>
                <textarea id="references" name="references" data-semantic-editor x-model="references"></textarea>
                <input id="literature-citations" type="hidden" name="literature_citations" :value="JSON.stringify(literatureCitations)">
            </form>
        </main></body></html>`);
    await page.addScriptTag({ content: `
        const uploadRequest = (...args) => fetch(...args);
        ${moduleSource('resources/js/proposal-semantic-editor.js')}
        ${moduleSource('resources/js/proposal-source-workspace.js')}
        ${moduleSource('resources/js/proposal-preview-workspace.js')}
        ${moduleSource('resources/js/proposal-paper-workspace.js')}
        ${moduleSource('resources/js/detailed-proposal-workspace.js')}
        ${semantic}
        document.addEventListener('alpine:init', () => {
            ${app.slice(factoryStart, factoryEnd)}
            Alpine.data('citationFixture', () => {
                const state = window.citationFactory({ literatureLibrarySearchUrl: '/library', literatureLibrarySaveUrl: '/library', literatureSearchUrl: '/search', literatureAttachUrlTemplate: '/proposal/sources/__literature_source__' });
                state.init = function () {
                    window.citationState = this;
                    this.literatureSources = ${JSON.stringify(sources)};
                    this.relatedLiterature = '<p>Community participation supports monitoring.</p>';
                    this.detailedProposalFingerprint = () => '';
                    this.triggerDetailedProposalAutoSave = () => {};
                    this.$nextTick(() => {
                        initializeSemanticEditors();
                        this.startDetailedProposalAutoSave();
                        this.writingToolsCleanup = initializeDetailedProposalWritingTools(this.$el, { openImagePicker() {} });
                    });
                };
                state.destroy = function () { this.writingToolsCleanup?.(); this.destroySourceWorkspace(); };
                return state;
            });
        });
    `.replace("Alpine.data('proposalDraftDetailedProposal',", 'window.citationFactory = (') });
    await page.addScriptTag({ path: resolve('node_modules/alpinejs/dist/cdn.js') });
    await page.waitForFunction(() => document.getElementById('related-literature')._semanticEditor, null, { timeout: 5000 })
        .catch((error) => assert.fail(initializationErrors.join('\n') || error.message));
    assert.deepEqual(initializationErrors, []);
    await page.locator('#related-literature').evaluate((field) => field._semanticEditor.focus());
    return page;
}

async function cite(page, title, atStart = false, locator = '') {
    await page.locator('#related-literature').evaluate((field, start) => {
        const editor = field._semanticEditor;
        editor.focus();
        const range = document.createRange();
        let node = start ? editor.querySelector('p').firstChild : editor;
        if (!start) while (node.lastChild) node = node.lastChild;
        range.selectNodeContents(node);
        if (typeof start === 'number') {
            range.setStart(editor.querySelector('p').firstChild, start);
            range.collapse(true);
        } else if (start !== 'selection') range.collapse(start);
        const selection = window.getSelection();
        selection.removeAllRanges(); selection.addRange(range);
        field._semanticApi.saveSelection();
    }, atStart);
    await page.getByRole('button', { name: 'Insert citation', exact: true }).first().click();
    const dialog = page.getByRole('dialog', { name: 'Insert citation', exact: true });
    await expect(dialog).toBeVisible();
    await expect(dialog.getByRole('complementary')).toBeHidden();
    await dialog.locator('article').filter({ has: page.getByRole('heading', { name: title, exact: true }) })
        .getByRole('button', { name: 'Choose source', exact: true }).click();
    if (locator) await dialog.getByLabel('Page or section (optional)', { exact: true }).fill(locator);
    await dialog.getByRole('button', { name: 'Insert citation', exact: true }).click();
    await expect(dialog).toBeHidden();
}

test('toolbar citations share IEEE references, renumber in reading order, and follow undo, redo and deletion', async () => {
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        for (const width of [1440, 390]) {
            const page = await openEditor(browser, width);
            const errors = [];
            page.on('pageerror', (error) => errors.push(error.message));
            await cite(page, 'Community Monitoring', 'selection', 'p. 6');
            const markers = page.locator('.semantic-rich-text-editor [data-proposal-citation]');
            await expect(markers).toHaveText([' [1, p. 6]']);
            assert.match(await page.locator('#related-literature').evaluate((field) => field._semanticEditor.querySelector('p').innerHTML), /data-proposal-citation="7"/);
            await cite(page, 'Habitat Recovery', true);
            await expect(markers).toHaveText([' [1]', ' [2, p. 6]']);
            await cite(page, 'Community Monitoring');
            await expect(markers).toHaveCount(3);
            assert.equal(await page.locator('#references').evaluate((field) => field._semanticEditor.querySelectorAll('p').length), 2);
            await page.locator('[data-writing-command="undo"]').click();
            await expect(markers).toHaveCount(2);
            await page.locator('[data-writing-command="redo"]').click();
            await expect(markers).toHaveCount(3);
            await page.locator('#related-literature').evaluate((field) => {
                field._semanticEditor.focus();
                const range = document.createRange();
                range.selectNode(field._semanticEditor.querySelector('[data-proposal-citation="12"]'));
                const selection = window.getSelection(); selection.removeAllRanges(); selection.addRange(range);
            });
            await page.keyboard.press('Backspace');
            await expect(markers).toHaveText([' [1, p. 6]', ' [1]']);
            await expect(page.locator('#references')).toHaveValue(/\[1\].*Community Monitoring/);
            assert.doesNotMatch(await page.locator('#references').inputValue(), /Habitat Recovery/);
            for (let index = 0; index < 2; index++) {
                await page.locator('#related-literature').evaluate((field) => {
                    field._semanticEditor.focus();
                    const range = document.createRange();
                    range.selectNode(field._semanticEditor.querySelector('[data-proposal-citation="7"]'));
                    const selection = window.getSelection(); selection.removeAllRanges(); selection.addRange(range);
                });
                await page.keyboard.press('Backspace');
            }
            await expect(markers).toHaveCount(0);
            await expect(page.getByRole('textbox', { name: 'References', exact: true })).toHaveText('');
            assert.match(await page.locator('#related-literature').inputValue(), /Community participation supports monitoring/);
            assert.deepEqual(errors, []);
            await page.close();
        }
    } finally { await browser.close(); }
});

test('citation picker saves and cites library, academic, and manual sources with one final action', async () => {
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        for (const width of [390, 1440]) for (const dark of [false, true]) {
            const page = await openEditor(browser, width, dark);
            const errors = [];
            page.on('pageerror', (error) => errors.push(error.message));
            await page.evaluate(() => {
                window.sourceCalls = [];
                window.fetch = async (url, options = {}) => {
                    const body = options.body ? JSON.parse(options.body) : {};
                    sourceCalls.push({ url, body });
                    const source = (id, title) => ({ id, title, authors: 'A. Author', year: 2025, reference: `A. Author, "${title}," 2025.` });
                    let payload;
                    if (String(url).startsWith('/library?')) payload = { sources: [source(21, 'Shared publication')] };
                    else if (url === '/search') payload = { results: [source(22, 'Academic publication')] };
                    else if (url === '/library') payload = { source: source(body.title === 'Manual publication' ? 23 : 22, body.title) };
                    else if (String(url).startsWith('/proposal/sources/')) {
                        const id = Number(String(url).split('/').pop());
                        payload = { source: { ...source(id + 10, { 21: 'Shared publication', 22: 'Academic publication', 23: 'Manual publication' }[id]), literature_source_id: id } };
                    } else throw new Error(`Unexpected request ${url}`);
                    return { ok: true, status: 200, json: async () => payload };
                };
            });
            const openPicker = async () => {
                await page.locator('#related-literature').evaluate((field) => {
                    field._semanticEditor.focus();
                    const range = document.createRange(); range.selectNodeContents(field._semanticEditor); range.collapse(false);
                    const selection = window.getSelection(); selection.removeAllRanges(); selection.addRange(range);
                    field._semanticApi.saveSelection();
                });
                await page.getByRole('button', { name: 'Insert citation', exact: true }).first().click();
                return page.getByRole('dialog', { name: 'Insert citation', exact: true });
            };
            let dialog = await openPicker();
            await dialog.getByRole('button', { name: 'Shared library', exact: true }).click();
            await dialog.getByRole('button', { name: 'Choose source', exact: true }).click();
            await expect(dialog.getByRole('button', { name: 'Add to proposal', exact: true })).toBeHidden();
            await dialog.getByLabel('Page or section (optional)', { exact: true }).fill('p. 3');
            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
            if (process.env.SOURCE_WORKSPACE_SCREENSHOT_DIRECTORY) await page.screenshot({ path: resolve(process.env.SOURCE_WORKSPACE_SCREENSHOT_DIRECTORY, `citation-picker-${width}-${dark ? 'dark' : 'light'}.png`) });
            await dialog.getByRole('button', { name: 'Insert citation', exact: true }).click();
            await expect(dialog).toBeHidden();
            dialog = await openPicker();
            await dialog.getByRole('button', { name: 'Academic search', exact: true }).click();
            await dialog.getByRole('searchbox', { name: 'Search academic publications', exact: true }).fill('Academic publication');
            await dialog.getByRole('button', { name: 'Search', exact: true }).click();
            await dialog.getByRole('button', { name: 'Choose source', exact: true }).click();
            await dialog.getByRole('button', { name: 'Insert citation', exact: true }).click();
            await expect(dialog).toBeHidden();
            dialog = await openPicker();
            await dialog.getByRole('button', { name: 'Add source', exact: true }).click();
            await dialog.getByLabel('Add source using', { exact: true }).selectOption('manual');
            await dialog.getByLabel('Publication title', { exact: true }).fill('Manual publication');
            await dialog.getByLabel('Page or section (optional)', { exact: true }).fill('p. 9');
            await dialog.getByRole('button', { name: 'Save and cite', exact: true }).click();
            await expect(dialog).toBeHidden();
            await expect(page.locator('.semantic-rich-text-editor [data-proposal-citation]')).toHaveText([' [1, p. 3]', ' [2]', ' [3, p. 9]']);
            await expect(page.locator('#references')).toHaveValue(/\[3\].*Manual publication/);
            assert.equal(await page.evaluate(() => sourceCalls.filter(call => call.url.startsWith('/proposal/sources/')).length), 3);
            await page.evaluate(() => citationState.openSourceWorkspace('paper'));
            const research = page.getByRole('dialog', { name: 'Sources', exact: true });
            await expect(research.getByRole('button', { name: 'Insert citation', exact: true })).toBeHidden();
            assert.equal(await page.evaluate(() => citationState.citationPickerSelection), null);
            await research.getByRole('button', { name: 'Close Sources', exact: true }).click();
            assert.deepEqual(errors, []);
            await page.close();
        }
    } finally { await browser.close(); }
});

test('centered picker edits a citation source and locator with native undo and keeps prose', async () => {
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        const page = await openEditor(browser);
        await cite(page, 'Community Monitoring', false, 'p. 6');
        const marker = page.locator('.semantic-rich-text-editor [data-proposal-citation]');
        await marker.click();
        const dialog = page.getByRole('dialog', { name: 'Edit citation', exact: true });
        await expect(dialog).toBeVisible();
        const box = await dialog.locator('section[x-ref="sourceWorkspacePanel"]').boundingBox();
        assert.ok(Math.abs(box.x + box.width / 2 - 720) < 2);
        assert.ok(box.width < 800);
        await expect(dialog.getByLabel('Page or section (optional)', { exact: true })).toHaveValue('p. 6');
        await dialog.locator('article').filter({ has: page.getByRole('heading', { name: 'Habitat Recovery', exact: true }) })
            .getByRole('button', { name: 'Choose source', exact: true }).click();
        await dialog.getByLabel('Page or section (optional)', { exact: true }).fill('p. 12');
        await dialog.getByRole('button', { name: 'Update citation', exact: true }).click();
        await expect(dialog).toBeHidden();
        await expect(marker).toHaveCount(1);
        await expect(marker).toHaveText(' [1, p. 12]');
        await expect(marker).toHaveAttribute('data-proposal-citation', '12');
        await page.locator('[data-writing-command="undo"]').click();
        await expect(marker).toHaveAttribute('data-proposal-citation', '7');
        await expect(marker).toHaveText(' [1, p. 6]');
        await expect(page.locator('#related-literature')).toHaveValue(/Community participation supports monitoring\./);
    } finally { await browser.close(); }
});

test('citations from another proposal do not shift this proposal reference numbers', async () => {
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        const page = await openEditor(browser);
        await page.locator('#related-literature').evaluate((field) => {
            field.value = '<p>Imported wording <span data-proposal-citation="99999"> [8]</span>.</p>'
                + '<p>Current proposal wording <span data-proposal-citation="7"> [3]</span>.</p>';
            field.dispatchEvent(new Event('input', { bubbles: true }));
        });

        await expect(page.locator('.semantic-rich-text-editor [data-proposal-citation="7"]')).toHaveText(' [1]');
        await expect(page.locator('#references')).toHaveValue(/<p data-proposal-managed-reference="7">\[1\] M\. Santos/);
        await expect(page.locator('#related-literature')).toHaveValue(/Imported wording.*data-proposal-citation="99999".*Current proposal wording/s);
        assert.deepEqual(await page.evaluate(() => citationState.citationReferenceSourceIds()), [7]);
        assert.equal(await page.locator('#references').evaluate((field) => field._semanticEditor.querySelectorAll('p').length), 1);
    } finally { await browser.close(); }
});

test('citations refreshed from reviewed text remain keyboard editable and cancellation restores focus', async () => {
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        const page = await openEditor(browser);
        await page.locator('#related-literature').evaluate((field) => {
            field.value = '<p>Reviewed wording <span data-proposal-citation="7" data-proposal-locator="p. 4"> [1, p. 4]</span></p>';
            field.dispatchEvent(new Event('input', { bubbles: true }));
        });
        const marker = page.getByRole('button', { name: 'Edit citation [1, p. 4]', exact: true });
        await marker.focus();
        await page.keyboard.press('Enter');
        const dialog = page.getByRole('dialog', { name: 'Edit citation', exact: true });
        await expect(dialog).toBeVisible();
        await expect(dialog.getByLabel('Page or section (optional)', { exact: true })).toHaveValue('p. 4');
        await page.keyboard.press('Escape');
        await expect(dialog).toBeHidden();
        await expect(marker).toBeFocused();
        assert.equal(await page.evaluate(() => citationState.citationPickerSelection), null);
    } finally { await browser.close(); }
});

test('citations stay inline at paragraph boundaries and leave the cursor ready for typing', async () => {
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        for (const offset of [0, 10, 44]) {
            const page = await openEditor(browser);
            await cite(page, 'Community Monitoring', offset);
            const paragraph = page.getByRole('textbox', { name: 'Review of Related Literature', exact: true }).locator('p');
            await expect(paragraph.locator('[data-proposal-citation="7"]')).toHaveCount(1);
            await page.keyboard.insertText('Additional wording.');
            await expect(paragraph).toContainText('Additional wording.');
            assert.equal(await page.locator('#related-literature').evaluate((field) => field._semanticEditor.querySelector('[data-proposal-citation]').contentEditable), 'false');
            await page.close();
        }
    } finally { await browser.close(); }
});

test('citation synchronization preserves manually written references that mention a linked source title', async () => {
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        const page = await openEditor(browser);
        await page.evaluate(() => {
            citationState.references = '<p>My corrected Community Monitoring reference, pp. 14–18.</p>'
                + '<p>M. Santos, &quot;Community Monitoring,&quot; Coastal Journal, 2024.</p>';
            citationState.synchronizeLiteratureCitations();
        });
        await expect(page.locator('#references')).toHaveValue('<p>My corrected Community Monitoring reference, pp. 14–18.</p>'
            + '<p>M. Santos, &quot;Community Monitoring,&quot; Coastal Journal, 2024.</p>');
    } finally { await browser.close(); }
});
