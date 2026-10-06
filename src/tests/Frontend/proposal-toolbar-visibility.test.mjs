import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { chromium } from '@playwright/test';
import postcss from 'postcss';
import tailwindcss from 'tailwindcss';
import loadConfig from 'tailwindcss/loadConfig.js';

const read = (path) => readFileSync(resolve(path), 'utf8').replaceAll('\r\n', '\n');
const previewHelpers = read('resources/js/proposal-preview-workspace.js').replaceAll('export ', '')
    + read('resources/js/proposal-paper-workspace.js').replace(/^import .+;\n/gm, '').replaceAll('export ', '');
const papers = ['detailed-proposal', 'work-plan', 'line-item-budget', 'expense-breakdown', 'curriculum-vitae'];

test('all proposal toolbars close and reopen without losing fields and stay above focused fields in both workspaces', async () => {
    const toolbars = JSON.parse(execFileSync('php', ['-r', String.raw`
        require 'vendor/autoload.php';
        $app = require 'bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        $toolbars = [];
        foreach (json_decode(stream_get_contents(STDIN), true) as $paper) {
            $toolbars[$paper] = view('components.'.$paper.'-writing-toolbar')->render();
        }
        echo json_encode($toolbars, JSON_THROW_ON_ERROR);
    `], { input: JSON.stringify(papers), encoding: 'utf8', env: { ...process.env, APP_ENV: 'testing' } }));
    const styles = await postcss([tailwindcss(loadConfig(resolve('tailwind.config.js')))])
        .process(read('resources/css/app.css'), { from: undefined });
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        for (const paper of papers) {
            for (const embedded of [false, true]) {
                for (const width of [1440, 390]) {
                    for (const dark of [false, true]) {
                        const page = await browser.newPage({ viewport: { width, height: 900 }, reducedMotion: 'reduce' });
                        const errors = [];
                        page.on('pageerror', (error) => errors.push(error.message));
                        await page.setContent(`<html class="${dark ? 'dark' : ''}"><style>${styles.css}\n${read('resources/css/proposal-paper-workspace.css')}</style>
                            <body class="${embedded ? 'revision-embedded' : ''}" ${embedded ? 'data-revision-embedded' : 'data-app-shell'}>
                            ${embedded ? '' : '<header style="height:128px">Application header</header>'}
                            <main data-paper-editor data-proposal-paper-workspace data-${paper}-workspace x-data="toolbarFixture" class="mx-auto p-3">
                                <p data-proposal-writing-status>Draft saved.</p>${toolbars[paper]}
                                <form class="space-y-6" @input="savedValue = answer">
                                    <section data-revision-section="section-start"><label for="first-field">First writing field</label><textarea id="first-field" class="block w-full" rows="5"></textarea></section>
                                    <div style="height:700px"></div>
                                    <section data-revision-section="section-answer"><label for="answer">Research notes</label><textarea id="answer" name="answer" x-model="answer" class="block w-full" rows="5"></textarea></section>
                                    <div style="height:1100px"></div>
                                </form>
                            </main></body></html>`);
                        await page.addScriptTag({ content: `${previewHelpers}
                            document.addEventListener('alpine:init',()=>Alpine.data('toolbarFixture',()=>({
                                ...proposalPaperPreviewWorkspace(), previewHtml:'', previewLoading:false,
                                answer:'Saved research notes.', savedValue:'', activeWorkPlanEntry:null, entries:[],
                                activeCvPersonId:1, activeCvPersonIndex:0, activeCvSection:'personal', cvMemberManagerOpen:false,
                                people:[{id:1,name:'Project leader'}], budgetCeiling:0,
                                projectTotal:()=>1234, grandTotal:()=>1234, formatMoney:value=>Number(value).toFixed(2),
                                isOverBudget:()=>false, budgetOverage:()=>0, curriculumVitaeSectionOptions:()=>[], personLabel:person=>person.name,
                                init(){window.toolbarState=this;this.initializeProposalPaperPreview()},
                                destroy(){this.destroyProposalPaperPreview()}
                            })));` });
                        await page.addScriptTag({ path: resolve('node_modules/alpinejs/dist/cdn.min.js') });
                        const toolbar = page.locator('[data-proposal-workspace-toolbar]');
                        const field = page.getByRole('textbox', { name: 'Research notes', exact: true });
                        await field.fill('Keep my updated research notes.');
                        await field.evaluate((element) => { window.originalField = element; element.scrollIntoView({ block: 'start' }); });
                        const expectedTop = embedded ? 0 : 128;
                        await page.waitForFunction((top) => Math.abs(document.querySelector('[data-proposal-workspace-toolbar]').getBoundingClientRect().top - top) < 1, expectedTop);
                        const expandedBounds = await toolbar.boundingBox();
                        assert.ok((await field.boundingBox()).y >= expandedBounds.y + expandedBounds.height + 8, `${paper}: focused field must remain below the toolbar`);
                        assert.ok((await page.locator('label[for="answer"]').boundingBox()).y >= expandedBounds.y + expandedBounds.height + 8, `${paper}: the focused field label must remain visible`);
                        const close = toolbar.getByRole('button', { name: 'Hide tools', exact: true });
                        assert.ok(await close.evaluate((element) => parseFloat(getComputedStyle(element).borderTopWidth) >= 1), 'Close is a visible bordered button');
                        assert.equal(await close.locator('svg path').getAttribute('d'), 'm6 6 12 12M18 6 6 18');
                        await close.click();
                        const reopen = toolbar.getByRole('button', { name: 'Show tools', exact: true });
                        await reopen.waitFor();
                        assert.equal(await reopen.getAttribute('aria-expanded'), 'false');
                        assert.equal(await toolbar.locator('[data-writing-toolbar-controls]').isVisible(), false);
                        await page.waitForFunction(() => document.activeElement?.hasAttribute('data-writing-toolbar-open'));
                        const collapsedBounds = await toolbar.boundingBox();
                        assert.ok(collapsedBounds.height < expandedBounds.height, 'Closing tools must free editing space');
                        await field.fill('I can keep writing with the tools closed.');
                        assert.equal(await page.evaluate(() => window.toolbarState.savedValue), 'I can keep writing with the tools closed.');
                        await reopen.click();
                        await page.waitForFunction(() => document.activeElement?.hasAttribute('data-writing-toolbar-close')
                            && document.activeElement.getAttribute('aria-expanded') === 'true').catch(async (error) => {
                            assert.fail(`${paper}/${embedded ? 'revision' : 'submission'}/${width}/${dark ? 'dark' : 'light'}: ${error.message}; ${JSON.stringify(await page.evaluate(() => ({ open: window.toolbarState.writingToolbarOpen, focused: document.activeElement?.outerHTML, close: document.querySelector('[data-writing-toolbar-close]')?.outerHTML })))}`);
                        });
                        assert.equal(await close.getAttribute('aria-expanded'), 'true');
                        await page.waitForFunction(() => getComputedStyle(document.querySelector('[data-writing-toolbar-controls]')).display !== 'none');
                        assert.notEqual(await toolbar.locator('[data-writing-toolbar-controls]').evaluate((element) => getComputedStyle(element).display), 'none');
                        assert.equal(await field.inputValue(), 'I can keep writing with the tools closed.');
                        assert.equal(await field.evaluate((element) => element === window.originalField), true);
                        await field.evaluate((element) => element.scrollIntoView({ block: 'start' }));
                        const restoredBounds = await toolbar.boundingBox();
                        assert.ok((await field.boundingBox()).y >= restoredBounds.y + restoredBounds.height + 8, `${paper}/${embedded ? 'revision' : 'submission'}/${width}/${dark ? 'dark' : 'light'}: reopened toolbar must keep the focused field visible`);
                        assert.ok((await page.locator('label[for="answer"]').boundingBox()).y >= restoredBounds.y + restoredBounds.height + 8, `${paper}: reopening tools must keep the focused field label visible`);
                        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
                        assert.deepEqual(errors, []);
                        if (paper === 'detailed-proposal' && process.env.ATHENA_EDITOR_VISUAL_DIR) {
                            await page.screenshot({ path: resolve(process.env.ATHENA_EDITOR_VISUAL_DIR, `closable-toolbar-${embedded ? 'revision' : 'submission'}-${width}-${dark ? 'dark' : 'light'}.png`) });
                        }
                        await page.close();
                    }
                }
            }
        }
    } finally {
        await browser.close();
    }
});
