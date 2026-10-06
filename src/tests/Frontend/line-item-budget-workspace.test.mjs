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
const start = app.indexOf("Alpine.data('proposalDraftLineItemBudget',");
const controller = app.slice(start, app.indexOf('Alpine.data(', start + 1));
const focusHelper = app.slice(app.indexOf('function focusNewFormEntry('), app.indexOf('\nthemeMediaQuery.addEventListener', app.indexOf('function focusNewFormEntry(')));
const preview = read('resources/js/proposal-preview-workspace.js').replaceAll('export ', '');
const workspace = read('resources/js/proposal-paper-workspace.js').replace(/^import .+;\n/gm, '').replaceAll('export ', '');
const previewView = read('resources/views/components/proposal-paper-preview.blade.php')
    .replace(/^@props\([^\n]+\)\n/, '')
    .replaceAll('{{ $panelId }}', 'line-item-budget-preview-panel')
    .replaceAll('{{ $previewLabel }}', 'Line-Item Budget preview')
    .replaceAll('{{ $frameTitle }}', 'Attachment B Line-Item Budget preview');
const toolbar = execFileSync('php', ['-r', String.raw`
    require 'vendor/autoload.php';
    $app = require 'bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    echo view('components.line-item-budget-writing-toolbar')->render();
`], { encoding: 'utf8', env: { ...process.env, APP_ENV: 'testing' } });
const edit = read('resources/views/faculty/proposal-drafts/line-item-budget/edit.blade.php');
const definition = read('config/line_item_budget.php');
const parseItems = (source) => [...source.matchAll(/\['key' => '([^']+)', 'label' => '([^']+)', 'level' => (\d)\]/g)]
    .map(([, key, label, level]) => ({ key, label, level: Number(level) }));
const sections = {
    mooe: { items: parseItems(definition.slice(definition.indexOf("'mooe' => ["), definition.indexOf("'co' => ["))) },
    co: { items: parseItems(definition.slice(definition.indexOf("'co' => ["))) },
};
const bladeValues = (source, values) => source.replace(/{{\s*(.*?)\s*}}/g, (_, expression) => {
    assert.ok(Object.hasOwn(values, expression), `Missing fixture value for ${expression}`);
    return values[expression];
});
const sectionView = (marker) => {
    const index = edit.indexOf(marker);
    assert.ok(index !== -1);
    return edit.slice(index, edit.indexOf('</section>', index) + '</section>'.length);
};
const team = sectionView('<section data-revision-section="section-project-team"')
    .replace(/@foreach[^\n]*\n[\s\S]*?@endforeach/g, '');
const office = sectionView('<section data-revision-section="section-research-office"')
    .replace(/<x-proposal-signatory-summary[^>]+\/>/, '');
const totals = sectionView('<section data-revision-section="section-totals"')
    .replaceAll("{{ config('line_item_budget.maximum_amount') }}", '999999999.99');
const categoryTemplate = sectionView('<section id="line-item-budget-section-{{ $sectionKey }}"');
const renderCategory = (key) => {
    const fixedRows = categoryTemplate.match(/@foreach \(\$sections\[\$sectionKey\]\['items'\] as \$item\)\n([\s\S]*?)@endforeach/);
    assert.ok(fixedRows);
    const withRows = categoryTemplate.replace(fixedRows[0], sections[key].items.map((item) => bladeValues(fixedRows[1], {
        "$item['key']": item.key,
        "$item['label']": item.label,
        "$item['level'] ? 'pl-6' : 'font-semibold'": item.level ? 'pl-6' : 'font-semibold',
        "config('line_item_budget.maximum_amount')": '999999999.99',
    })).join(''));
    return bladeValues(withRows, {
        '$sectionKey': key,
        '$sectionHeading': key === 'mooe' ? 'I. Maintenance and Other Operating Expenses (MOOE)' : 'II. Capital Outlays (CO)',
        '$customProperty': key === 'mooe' ? 'customMooeItems' : 'customCoItems',
        'strtoupper($sectionKey)': key.toUpperCase(),
        "config('line_item_budget.maximum_amount')": '999999999.99',
        "$sectionKey === 'mooe' ? 'Total MOOE' : 'Total Capital Outlays'": key === 'mooe' ? 'Total MOOE' : 'Total Capital Outlays',
        "$sectionKey === 'mooe' ? 'overrideMooe' : 'overrideCo'": key === 'mooe' ? 'overrideMooe' : 'overrideCo',
        "$sectionKey === 'mooe' ? 'mooeOverride' : 'coOverride'": key === 'mooe' ? 'mooeOverride' : 'coOverride',
    });
};
const positionBudgetForScreenshot = async (page) => {
    await page.evaluate(() => {
        const section = document.querySelector('#line-item-budget-section-mooe');
        window.scrollTo({
            top: scrollY + section.getBoundingClientRect().top - parseFloat(getComputedStyle(section).scrollMarginTop),
            behavior: 'instant',
        });
    });
};

test('Attachment B keeps budget calculations and autosave with responsive paper previews and one floating toolbar', async () => {
    const styles = await postcss([tailwindcss(loadConfig(resolve('tailwind.config.js')))])
        .process(read('resources/css/app.css'), { from: undefined });
    const workspaceStyles = read('resources/css/proposal-paper-workspace.css');
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });

    try {
        for (const width of [1440, 1024, 390]) {
            for (const dark of [false, true]) {
                const page = await browser.newPage({ viewport: { width, height: 1000 } });
                const errors = [];
                page.on('pageerror', (error) => errors.push(error.message));
                const config = {
                    sections, budgetCeiling: 100000, updateUrl: '/save', previewUrl: '/preview', downloadUrl: '/download', csrfToken: 'test',
                    workspacePeople: [{ name: 'Workspace Member', college: 'CICS' }],
                    initialData: { amounts: { travelling_expenses: '1000', ict_equipment: '2000' } },
                };
                await page.setContent(`<html class="${dark ? 'dark' : ''}"><style>${styles.css}\n${workspaceStyles}</style><body data-app-shell>
                    <div data-app-content-shell style="padding-left:${width >= 640 ? 280 : 76}px">
                        <div style="position:sticky;top:0;height:120px">Application header</div>
                        <main class="mx-auto w-full space-y-6 px-4 py-8 sm:px-6 lg:px-8" data-proposal-paper-workspace data-line-item-budget-workspace data-paper-project-details-complete="true" x-data='proposalDraftLineItemBudget(${JSON.stringify(config)})'>
                            <h1 class="text-xl font-semibold">Attachment B: Line-Item Budget</h1>
                            <div data-proposal-autosave-status><span data-proposal-autosave-message>Changes save automatically.</span><span data-proposal-autosave-indicator></span></div>
                            ${toolbar}
                            <div class="proposal-preview-workspace proposal-writing-columns" :class="{ 'proposal-writing-preview-hidden': !previewPaneOpen }" @resize.window.debounce.150ms="resizeProposalPaperPreview()">
                                <div class="proposal-edit-pane space-y-6" :inert="previewFullscreen">
                                    <form x-ref="form" method="POST" action="/save" class="space-y-6" data-line-item-budget-autosave-form>
                                        <input type="hidden" name="_method" value="PUT"><input type="hidden" name="document_version" value="0"><input type="hidden" name="save_as_draft" value="0" data-paper-save-mode>
                                        ${team}${renderCategory('mooe')}${renderCategory('co')}${totals}${office}
                                    </form>
                                </div>${previewView}
                            </div>
                            <button type="button" x-show="!previewPaneOpen" @click="showProposalPreview()" class="proposal-writing-preview-launcher">Preview paper</button>
                        </main>
                    </div></body></html>`);
                await page.addScriptTag({ content: `${preview}\n${workspace}\n${focusHelper}
                    window.savedBodies=[];window.previewBodies=[];window.downloadRequests=0;window.previewFailure=false;
                    window.fetch=async(url,options)=>{
                        const body=[...options.body.entries()];
                        if(url==='/save') {
                            window.savedBodies.push(body);
                            return {ok:true,status:200,json:async()=>({document_version:window.savedBodies.length,saved_as_draft:options.body.get('save_as_draft')==='1'})};
                        }
                        if(url==='/download') {window.downloadRequests++;throw new Error('Unexpected download');}
                        window.previewBodies.push(body);
                        await new Promise(resolve=>setTimeout(resolve,30));
                        if(window.previewFailure) return {ok:false,status:422,json:async()=>({errors:{amounts:['Complete Project Details before previewing.']}})};
                        return {ok:true,status:200,text:async()=>'<html><body style="width:794px"><h1>LINE-ITEM BUDGET</h1><p>Travelling: '+options.body.get('amounts[travelling_expenses]')+'</p><div style="height:1200px"></div></body></html>'};
                    };
                    document.addEventListener('alpine:init',()=>{
                        const register=Alpine.data.bind(Alpine);
                        Alpine.data=(name,factory)=>register(name,config=>{
                            const state=factory(config);const initialize=state.init;
                            state.init=function(){window.budgetState=this;initialize.call(this)};
                            return state;
                        });
                        ${controller}
                    });` });
                await page.addScriptTag({ content: read('node_modules/alpinejs/dist/cdn.min.js') });
                const toolbarTotal = page.locator('[data-line-item-budget-toolbar-total]');
                await toolbarTotal.waitFor();
                assert.equal(await toolbarTotal.textContent(), '3,000.00');
                assert.equal(await page.locator('[data-proposal-workspace-toolbar]').count(), 1);
                assert.equal(await page.locator('.proposal-preview-dock').isVisible(), false);

                await page.getByRole('button', { name: 'Capital Outlays', exact: true }).click();
                assert.equal(await page.locator('#amount-machinery_equipment_outlay').evaluate((element) => element === document.activeElement), true);
                await page.getByRole('button', { name: 'MOOE', exact: true }).click();
                await page.locator('#amount-travelling_expenses').fill('1500');
                const mooe = page.locator('#line-item-budget-section-mooe');
                await mooe.getByRole('button', { name: 'Add category or sub-category', exact: true }).click();
                await mooe.getByRole('textbox', { name: 'Custom MOOE particular' }).waitFor();
                assert.equal(await mooe.getByRole('textbox', { name: 'Custom MOOE particular' }).evaluate((element) => element === document.activeElement), true);
                await mooe.getByRole('textbox', { name: 'Custom MOOE particular' }).fill('Community consultation supplies');
                await mooe.getByRole('spinbutton', { name: 'Custom MOOE amount' }).fill('500');
                assert.equal(await toolbarTotal.textContent(), '4,000.00');

                await mooe.getByRole('checkbox', { name: 'Edit this total manually' }).check();
                await page.locator('#mooe-total-override').fill('6000');
                assert.equal(await toolbarTotal.textContent(), '8,000.00');
                await mooe.getByRole('checkbox', { name: 'Edit this total manually' }).uncheck();
                assert.equal(await toolbarTotal.textContent(), '4,000.00');
                await page.getByRole('button', { name: 'Add project staff', exact: true }).click();
                await page.locator('[name="staff[0][name]"]').fill('Workspace Member');
                await page.locator('[name="staff[0][name]"]').dispatchEvent('change');
                assert.equal(await page.locator('[name="staff[0][college]"]').inputValue(), 'CICS');
                await page.waitForFunction(() => window.savedBodies.length > 0 && !window.budgetState.autoSaveInFlight);
                assert.equal(await page.evaluate(() => window.previewBodies.length), 0);
                const saved = await page.evaluate(() => window.savedBodies.at(-1));
                assert.ok(saved.some(([name, value]) => name === 'custom_mooe_items[0][amount]' && value === '500'));
                assert.ok(saved.some(([name, value]) => name === 'staff[0][college]' && value === 'CICS'));
                assert.equal(saved.some(([name]) => name === 'mooe_total_override'), false);

                await page.locator('.proposal-writing-preview-launcher').click();
                await page.waitForFunction(() => window.budgetState.previewReady && !window.budgetState.previewLoading);
                assert.equal(await page.evaluate(() => window.previewBodies.at(-1).some(([name]) => name === '_method')), false);
                if (width >= 1024) {
                    assert.equal(await page.locator('.proposal-preview-dock-expanded').count(), 0);
                    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
                    const editorWidth = await page.locator('.proposal-edit-pane').evaluate((element) => element.getBoundingClientRect().width);
                    assert.ok(editorWidth > (width === 1440 ? 700 : 400));
                    if (process.env.ATHENA_EDITOR_VISUAL_DIR) {
                        await page.getByRole('button', { name: 'MOOE', exact: true }).click();
                        await positionBudgetForScreenshot(page);
                        await page.screenshot({ path: resolve(process.env.ATHENA_EDITOR_VISUAL_DIR, `line-item-budget-workspace-${width}-${dark ? 'dark' : 'light'}.png`) });
                    }
                    await page.getByRole('button', { name: 'Enlarge the paper preview' }).click();
                }
                await page.locator('.proposal-preview-dock-expanded').waitFor();
                assert.equal(await page.locator('.proposal-edit-pane').evaluate((element) => element.inert), true);
                await page.getByRole('button', { name: 'Zoom in', exact: true }).click();
                assert.equal(await page.locator('.proposal-preview-zoom output').textContent(), '110%');
                await page.keyboard.press('Escape');
                await page.waitForFunction(() => !window.budgetState.previewFullscreen);
                const focusTarget = width >= 1024 ? page.getByRole('button', { name: 'Enlarge the paper preview' }) : page.locator('.proposal-writing-preview-launcher');
                assert.equal(await focusTarget.evaluate((element) => element === document.activeElement), true);

                await page.locator('#amount-travelling_expenses').fill('1800');
                if (width < 1024) await page.locator('.proposal-writing-preview-launcher').click();
                await page.waitForFunction(() => !window.budgetState.previewStale && window.budgetState.previewHtml.includes('1800'));
                const lastPreview = await page.evaluate(() => window.budgetState.previewHtml);
                await page.evaluate(() => { window.previewFailure = true; });
                await page.getByRole('button', { name: 'Refresh preview', exact: true }).click();
                await page.waitForFunction(() => !window.budgetState.previewLoading && window.budgetState.validationMessage);
                assert.equal(await page.evaluate(() => window.budgetState.previewHtml), lastPreview);
                assert.equal(await page.locator('.proposal-preview-error').textContent(), 'Complete Project Details before previewing.');
                await page.evaluate(() => { window.previewFailure = false; window.budgetState.closeProposalPreview(); });

                await mooe.getByRole('button', { name: 'Remove', exact: true }).click();
                await mooe.getByRole('textbox', { name: 'Custom MOOE particular' }).waitFor({ state: 'detached' });
                assert.equal(await toolbarTotal.textContent(), '3,800.00');
                await page.getByRole('checkbox', { name: 'Edit project total manually' }).check();
                await page.locator('#project-total-override').fill('100001');
                assert.equal(await toolbarTotal.textContent(), '100,001.00');
                await page.locator('.budget-writing-limit-warning').waitFor({ state: 'visible' });
                assert.equal(await page.locator('.budget-writing-limit-warning').isVisible(), true);
                await page.waitForFunction(() => window.savedBodies.at(-1)?.some(([name,value]) => name === 'project_total_override' && value === '100001') && !window.budgetState.autoSaveInFlight);
                assert.ok(await page.evaluate(() => window.savedBodies.at(-1).some(([name,value]) => name === 'save_as_draft' && value === '1')));
                await page.evaluate(() => window.budgetState.downloadDocument());
                assert.equal(await page.evaluate(() => window.downloadRequests), 0);
                await page.locator('.proposal-writing-preview-launcher').click();
                await page.waitForFunction(() => !window.budgetState.previewLoading && !window.budgetState.previewStale);
                assert.ok(await page.evaluate(() => window.previewBodies.at(-1).some(([name,value]) => name === 'project_total_override' && value === '100001')));
                await page.evaluate(() => window.budgetState.closeProposalPreview());
                assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
                if (process.env.ATHENA_EDITOR_VISUAL_DIR && width < 1024) {
                    await page.getByRole('button', { name: 'MOOE', exact: true }).click();
                    await positionBudgetForScreenshot(page);
                    await page.screenshot({ path: resolve(process.env.ATHENA_EDITOR_VISUAL_DIR, `line-item-budget-workspace-${width}-${dark ? 'dark' : 'light'}.png`) });
                }
                await page.evaluate(() => {
                    document.body.classList.add('revision-embedded');
                    document.querySelector('main').parentElement.setAttribute('data-revision-embedded', '');
                    window.budgetState.showProposalPreview();
                });
                assert.equal(await page.evaluate(() => window.budgetState.previewPaneOpen || window.budgetState.previewFullscreen), false);
                assert.equal(await page.locator('[data-proposal-preview-toggle]').isVisible(), false);
                assert.deepEqual(errors, []);
                await page.close();
            }
        }
    } finally {
        await browser.close();
    }
});
