import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { chromium } from '@playwright/test';
import postcss from 'postcss';
import tailwindcss from 'tailwindcss';
import loadConfig from 'tailwindcss/loadConfig.js';

const read = (path) => readFileSync(new URL(`../../${path}`, import.meta.url), 'utf8').replaceAll('\r\n', '\n');
const app = read('resources/js/app.js');
const start = app.indexOf("Alpine.data('proposalDraftExpenseBreakdown',");
const controller = app.slice(start, app.indexOf('Alpine.data(', start + 1));
const focusHelper = app.slice(app.indexOf('function focusNewFormEntry('), app.indexOf('\nthemeMediaQuery.addEventListener', app.indexOf('function focusNewFormEntry(')));
const preview = read('resources/js/proposal-preview-workspace.js').replaceAll('export ', '');
const workspace = read('resources/js/proposal-paper-workspace.js').replace(/^import .+;\n/gm, '').replaceAll('export ', '');
const autosave = read('resources/js/proposal-paper-autosave.js').replaceAll('export ', '');
const edit = read('resources/views/faculty/proposal-drafts/expense-breakdown/edit.blade.php');
const editorStart = edit.indexOf('<section data-revision-section="section-project-information"');
const editor = edit.slice(editorStart, edit.indexOf('\n        <div x-show="previewError || downloadError"', editorStart));
const previewView = read('resources/views/components/proposal-paper-preview.blade.php')
    .replace(/^@props\([^\n]+\)\n/, '')
    .replaceAll('{{ $panelId }}', 'expense-breakdown-preview-panel')
    .replaceAll('{{ $previewLabel }}', 'Estimated Expense Breakdown preview')
    .replaceAll('{{ $frameTitle }}', 'Estimated Expense Breakdown preview');
const items = [{
    category: 'mooe', account: 'Communication Expenses', sub_account: 'Telephone Expenses',
    particulars: 'Prepaid Card', details: 'Prepaid Call Card', purpose: 'For communication purposes',
    unit: 'pc', quantity: 12, unit_cost: 300,
}];

test('expense edits retain calculations and autosave with the Detailed Proposal docked preview', async () => {
    const rendered = JSON.parse(execFileSync('php', ['-r', `
        require 'vendor/autoload.php';
        $app = require 'bootstrap/app.php';
        $app->make(\\Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
        $input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
        $draft = (new \\App\\Models\\ProposalDraft)->forceFill(['id' => 100, 'project_title' => 'Community Survey']);
        echo json_encode([
            'toolbar' => view('components.expense-breakdown-writing-toolbar')->render(),
            'editor' => \\Illuminate\\Support\\Facades\\Blade::render($input['editor'], [
                'proposalDraft' => $draft, 'expenseBreakdownDocument' => null, 'sampleAvailable' => false,
                'budgetCeiling' => 100000,
            ]),
            'accounts' => config('expense_breakdown.accounts'),
            'preview' => \\Illuminate\\Support\\Facades\\Blade::render($input['preview'], [
                'expenseBreakdown' => \\App\\Support\\ExpenseBreakdownData::fromValidated([
                    'project_title' => 'Community Survey', 'items' => $input['items'],
                ]),
            ]),
        ], JSON_THROW_ON_ERROR);
    `], {
        input: JSON.stringify({ editor, items, preview: read('resources/views/faculty/expense-breakdowns/preview.blade.php')
            .replace("@vite('resources/css/expense-breakdown-print.css')", `<style>${read('resources/css/expense-breakdown-print.css')}</style>`) }),
        encoding: 'utf8', env: { ...process.env, APP_ENV: 'testing' }, maxBuffer: 3 * 1024 * 1024,
    }));
    const styles = await postcss([tailwindcss(loadConfig(resolve('tailwind.config.js')))])
        .process(read('resources/css/app.css'), { from: undefined });
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        for (const width of [1440, 1024, 390]) {
            for (const dark of [false, true]) {
                const page = await browser.newPage({ viewport: { width, height: 1000 }, reducedMotion: 'reduce' });
                const errors = [];
                page.on('pageerror', (error) => errors.push(error.message));
                const config = {
                    initialData: { items }, accountCatalog: rendered.accounts, budgetCeiling: 100000,
                    previewUrl: '/preview', updateUrl: '/save', downloadUrl: '/download', csrfToken: 'test',
                };
                await page.setContent(`<html class="${dark ? 'dark' : ''}"><style>${styles.css}\n${read('resources/css/proposal-paper-workspace.css')}</style><body data-app-shell>
                    <div data-app-content-shell style="padding-left:${width >= 640 ? 280 : 76}px">
                        <div style="position:sticky;top:0;height:120px">Application header</div>
                        <main class="mx-auto w-full space-y-6 px-4 py-8 sm:px-6 lg:px-8" data-proposal-paper-workspace data-expense-breakdown-workspace data-paper-project-details-complete="true" x-data='proposalDraftExpenseBreakdown(${JSON.stringify(config)})'>
                            <div data-proposal-autosave-status><span data-proposal-autosave-message>Changes save automatically.</span><span data-proposal-autosave-indicator></span></div>
                            ${rendered.toolbar}
                            <div class="proposal-preview-workspace proposal-writing-columns" :class="{ 'proposal-writing-preview-hidden': !previewPaneOpen }" @resize.window.debounce.150ms="resizeProposalPaperPreview()">
                                <div class="proposal-edit-pane space-y-6" :inert="previewFullscreen">${rendered.editor}</div>${previewView}
                            </div>
                            <button type="button" x-show="!previewPaneOpen" @click="showProposalPreview()" class="proposal-writing-preview-launcher">Preview paper</button>
                        </main>
                    </div></body></html>`);
                await page.addScriptTag({ content: `${preview}\n${workspace}\n${autosave}\n${focusHelper}
                    window.savedBodies=[];window.previewBodies=[];window.previewFailure=false;window.previewDelay=20;window.downloadRequests=0;
                    window.fetch=async(url,options)=>{
                        if(url==='/save') {
                            window.savedBodies.push([...options.body.entries()]);
                            return {ok:true,status:200,json:async()=>({document_version:window.savedBodies.length,saved_as_draft:options.body.get('save_as_draft')==='1'})};
                        }
                        if(url==='/download') {window.downloadRequests++;throw new Error('Unexpected download');}
                        window.previewBodies.push([...options.body.entries()]);
                        await new Promise(resolve=>setTimeout(resolve,window.previewDelay));
                        if(window.previewFailure) return {ok:false,status:422,json:async()=>({errors:{items:['Please review this expense item.']}})};
                        return {ok:true,status:200,text:async()=>${JSON.stringify(rendered.preview)}+'<p>Current cost: '+options.body.get('items[0][unit_cost]')+'</p>'};
                    };
                    const isEmbeddedRevisionEditor=()=>false;
                    document.addEventListener('alpine:init',()=>{
                        const register=Alpine.data.bind(Alpine);
                        Alpine.data=(name,factory)=>register(name,(config)=>{
                            const state=factory(config);const initialize=state.init;
                            state.init=function(){window.expenseState=this;initialize.call(this)};
                            return state;
                        });
                        ${controller}
                    });
                ` });
                await page.addScriptTag({ path: resolve('node_modules/alpinejs/dist/cdn.min.js') });
                await page.locator('#expense-unit-cost-1').waitFor();
                assert.deepEqual(errors, []);
                if (width >= 1024) {
                    await page.waitForFunction(() => window.expenseState.previewReady && !window.expenseState.previewLoading);
                    assert.equal(await page.locator('.proposal-preview-dock-expanded').count(), 0);
                    const pane = await page.locator('.proposal-edit-pane').boundingBox();
                    const dock = await page.locator('.proposal-preview-dock').boundingBox();
                    assert.ok(dock.x >= pane.x + pane.width, 'Preview must sit beside the editor');
                } else {
                    assert.equal(await page.locator('.proposal-preview-dock').isVisible(), false);
                    assert.equal(await page.evaluate(() => window.previewBodies.length), 0);
                }
                assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
                await page.locator('#expense-unit-cost-1').fill('400');
                await page.waitForFunction(() => window.savedBodies.at(-1)?.some(([name,value]) => name==='items[0][unit_cost]' && value==='400') && !window.expenseState.autoSaveInFlight);
                assert.equal(await page.evaluate(() => window.expenseState.grandTotal()), 4800);
                if (width < 1024) await page.locator('.proposal-writing-preview-launcher').click();
                await page.waitForFunction(() => !window.expenseState.previewStale && window.expenseState.previewHtml.includes('Current cost: 400'));
                await page.waitForFunction(() => {
                    const frame = window.expenseState.$refs.previewFrame;
                    return frame?.contentDocument?.body?.textContent.includes('Current cost: 400')
                        && Number(frame.contentDocument.body.style.zoom) > 0
                        && Number(frame.contentDocument.body.style.zoom) < 1;
                }, null, { timeout: 10000 }).catch(async (error) => {
                    assert.fail(`${width}/${dark ? 'dark' : 'light'}: ${error.message}; ${JSON.stringify(await page.locator('iframe').evaluate((frame) => ({ text: frame.contentDocument.body.textContent.slice(-100), zoom: frame.contentDocument.body.style.zoom, width: frame.clientWidth, viewport: frame.contentDocument.documentElement.clientWidth, paper: frame.contentDocument.body.scrollWidth, mode: frame.contentDocument.compatMode })))}`);
                });
                assert.equal(await page.evaluate(() => window.previewBodies.at(-1).some(([name]) => name==='_method')), false);
                const paperSize = await page.locator('iframe').evaluate((frame) => ({
                    width: frame.contentDocument.querySelector('.expense-breakdown-sheet').offsetWidth,
                    zoom: Number(frame.contentDocument.body.style.zoom),
                }));
                assert.equal(paperSize.width, 816, 'The official paper must retain its width instead of reflowing the table');
                assert.ok(paperSize.zoom < 1, `The full paper must scale to the available preview width at ${width}px (${dark ? 'dark' : 'light'})`);
                if (process.env.ATHENA_EDITOR_VISUAL_DIR && width >= 1024) await page.screenshot({ path: resolve(process.env.ATHENA_EDITOR_VISUAL_DIR, `expense-breakdown-dock-${width}-${dark ? 'dark' : 'light'}.png`) });

                if (width >= 1024) await page.getByRole('button', { name: 'Enlarge the paper preview' }).click();
                await page.locator('.proposal-preview-dock-expanded').waitFor();
                assert.equal(await page.locator('.proposal-edit-pane').evaluate((element) => element.inert), true);
                await page.getByRole('button', { name: 'Zoom in', exact: true }).click();
                assert.equal(await page.locator('.proposal-preview-zoom output').textContent(), '110%');
                if (process.env.ATHENA_EDITOR_VISUAL_DIR) await page.screenshot({ path: resolve(process.env.ATHENA_EDITOR_VISUAL_DIR, `expense-breakdown-full-${width}-${dark ? 'dark' : 'light'}.png`) });
                await page.evaluate(() => { window.printCalls=0; window.expenseState.$refs.previewFrame.contentWindow.print=()=>window.printCalls++; });
                await page.getByRole('button', { name: 'Print', exact: true }).click();
                assert.equal(await page.evaluate(() => window.printCalls), 1);
                await page.emulateMedia({ media: 'print' });
                assert.equal(await page.locator('iframe').evaluate((frame) => getComputedStyle(frame.contentDocument.body).zoom), '1');
                await page.emulateMedia({ media: 'screen' });
                await page.getByRole('button', { name: 'Minimize preview', exact: true }).focus();
                await page.keyboard.press('Shift+Tab');
                assert.equal(await page.getByRole('button', { name: 'Fit width', exact: true }).evaluate((element) => element===document.activeElement), true);
                await page.keyboard.press('Escape');
                await page.waitForFunction(() => !window.expenseState.previewFullscreen
                    && document.activeElement === window.expenseState.previewReturnFocus);
                const focusTarget = width >= 1024 ? page.getByRole('button', { name: 'Enlarge the paper preview' }) : page.locator('.proposal-writing-preview-launcher');
                assert.equal(await focusTarget.evaluate((element) => element===document.activeElement), true);
                if (width < 1024) await page.locator('.proposal-writing-preview-launcher').click();

                const previousPreview = await page.evaluate(() => window.expenseState.previewHtml);
                await page.evaluate(() => { window.previewFailure=true; });
                await page.getByRole('button', { name: 'Refresh preview', exact: true }).click();
                await page.waitForFunction(() => !window.expenseState.previewLoading && window.expenseState.validationMessage);
                assert.equal(await page.evaluate(() => window.expenseState.previewHtml), previousPreview);
                assert.equal(await page.locator('.proposal-preview-error').textContent(), 'Please review this expense item.');
                await page.evaluate(() => { window.previewFailure=false; window.previewDelay=100; window.pendingPreview=window.expenseState.generatePreview(); });
                await page.evaluate(() => {
                    window.expenseState.items[0].unit_cost='450';
                    window.expenseState.triggerExpenseBreakdownAutoSave();
                });
                await page.waitForFunction(() => !window.expenseState.previewStale && window.expenseState.previewHtml.includes('Current cost: 450'));

                await page.evaluate(() => window.expenseState.closeProposalPreview());
                await page.getByRole('button', { name: 'Add another expense item', exact: true }).click();
                assert.equal(await page.locator('#expense-particulars-2').evaluate((element) => element===document.activeElement), true);
                await page.getByRole('button', { name: 'Remove', exact: true }).last().click();
                assert.equal(await page.evaluate(() => window.expenseState.items.length), 1);
                await page.locator('#expense-unit-cost-1').fill('10000');
                await page.waitForFunction(() => window.savedBodies.at(-1)?.some(([name,value])=>name==='save_as_draft'&&value==='1') && !window.expenseState.autoSaveInFlight);
                await page.evaluate(() => window.expenseState.downloadDocument());
                assert.equal(await page.evaluate(() => window.downloadRequests), 0);
                assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
                if (process.env.ATHENA_EDITOR_VISUAL_DIR) await page.screenshot({ path: resolve(process.env.ATHENA_EDITOR_VISUAL_DIR, `expense-breakdown-${width}-${dark ? 'dark' : 'light'}.png`) });
                await page.evaluate(() => {
                    document.body.classList.add('revision-embedded');
                    document.querySelector('main').parentElement.setAttribute('data-revision-embedded', '');
                    window.expenseState.showProposalPreview();
                });
                assert.equal(await page.evaluate(() => window.expenseState.previewPaneOpen || window.expenseState.previewFullscreen), false);
                assert.equal(await page.locator('.proposal-writing-preview-launcher').isVisible(), false);
                assert.deepEqual(errors, []);
                await page.close();
            }
        }
    } finally {
        await browser.close();
    }
});
