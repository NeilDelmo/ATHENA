import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { chromium } from '@playwright/test';
import postcss from 'postcss';
import tailwindcss from 'tailwindcss';
import loadConfig from 'tailwindcss/loadConfig.js';

const app = readFileSync(new URL('../../resources/js/app.js', import.meta.url), 'utf8');
const start = app.indexOf("Alpine.data('proposalDraftWorkPlan',");
const controller = app.slice(start, app.indexOf('Alpine.data(', start + 1));
const preview = readFileSync(new URL('../../resources/js/proposal-preview-workspace.js', import.meta.url), 'utf8').replaceAll('export ', '');
const workspace = readFileSync(new URL('../../resources/js/proposal-paper-workspace.js', import.meta.url), 'utf8').replace(/^import .+;\r?\n/gm, '').replaceAll('export ', '');
const previewView = readFileSync(new URL('../../resources/views/components/proposal-paper-preview.blade.php', import.meta.url), 'utf8')
    .replace(/^@props\([^\n]+\)\r?\n/, '')
    .replaceAll('{{ $panelId }}', 'work-plan-preview-panel')
    .replaceAll('{{ $previewLabel }}', 'Work Plan preview')
    .replaceAll('{{ $frameTitle }}', 'Attachment A Work Plan preview');
const toolbar = execFileSync('php', ['-r', String.raw`
    require 'vendor/autoload.php';
    $app = require 'bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    echo view('components.work-plan-writing-toolbar')->render();
`], { encoding: 'utf8', env: { ...process.env, APP_ENV: 'testing' } });
const editView = readFileSync(new URL('../../resources/views/faculty/proposal-drafts/work-plan/edit.blade.php', import.meta.url), 'utf8').replaceAll('\r\n', '\n');
const entryView = editView.slice(editView.indexOf('<template x-for="(entry, index) in entries"'), editView.indexOf('\n            </section>\n\n            <x-proposal-signatory-summary'));

test('Work Plan shares responsive preview controls while preserving linked objectives, autosave, and month locks', async () => {
    const styles = await postcss([tailwindcss(loadConfig(resolve('tailwind.config.js')))])
        .process(readFileSync(new URL('../../resources/css/app.css', import.meta.url), 'utf8'), { from: undefined });
    const workspaceStyles = readFileSync(new URL('../../resources/css/proposal-paper-workspace.css', import.meta.url), 'utf8');
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });

    try {
        for (const width of [1440, 1024, 390]) {
            for (const dark of [false, true]) {
                const page = await browser.newPage({ viewport: { width, height: 1000 } });
                const errors = [];
                page.on('pageerror', (error) => errors.push(error.message));
                const config = {
                    objectivesLinked: true, durationMonths: 24, updateUrl: '/save', previewUrl: '/preview', csrfToken: 'test',
                    initialEntries: [
                        { objective: 'Document the coastal baseline', expected_output: 'Baseline dataset', activity: 'Conduct surveys', months: [1, 2] },
                        { objective: 'Evaluate the monitoring workflow', expected_output: 'Evaluation report', activity: 'Analyze results', months: [13, 14] },
                    ],
                };
                await page.setContent(`<html class="${dark ? 'dark' : ''}"><style>${styles.css}\n${workspaceStyles}</style><body data-app-shell>
                    <div data-app-content-shell style="padding-left:${width >= 640 ? 280 : 76}px">
                        <div style="position:sticky;top:0;height:120px">Application header</div>
                        <main class="work-plan-writing-workspace mx-auto w-full space-y-6 px-4 py-8 sm:px-6 lg:px-8" data-proposal-paper-workspace data-work-plan-workspace data-paper-project-details-complete="true" @focusin="focusWorkPlanEntry($event)" x-data='proposalDraftWorkPlan(${JSON.stringify(config)})'>
                            <div data-proposal-autosave-status><span data-proposal-autosave-message>Changes save automatically.</span><span data-proposal-autosave-indicator></span></div>
                            ${toolbar}
                            <div class="proposal-preview-workspace proposal-writing-columns" :class="{ 'proposal-writing-preview-hidden': !previewPaneOpen }" @resize.window.debounce.150ms="resizeProposalPaperPreview()">
                                <div class="proposal-edit-pane" :inert="previewFullscreen">
                                    <form x-ref="form" method="POST" action="/save" data-work-plan-autosave-form>
                                        <input type="hidden" name="_method" value="PUT"><input type="hidden" name="document_version" value="0"><input type="hidden" name="save_as_draft" value="0" data-paper-save-mode>
                                        <section data-revision-section="section-schedule">${entryView}</section>
                                    </form>
                                </div>${previewView}
                            </div>
                            <button type="button" x-show="!previewPaneOpen" @click="showProposalPreview()" class="proposal-writing-preview-launcher">Preview paper</button>
                        </main>
                    </div></body></html>`);
                await page.addScriptTag({ content: `${preview}\n${workspace}
                    window.savedBodies=[]; window.previewBodies=[]; window.previewFailure=false;
                    window.fetch=async (url, options)=>{
                        const body=[...options.body.entries()];
                        if(url==='/save') {
                            window.savedBodies.push(body);
                            return {ok:true,status:200,json:async()=>({document_version:window.savedBodies.length,saved_as_draft:false})};
                        }
                        window.previewBodies.push(body);
                        await new Promise(resolve=>setTimeout(resolve,30));
                        if(window.previewFailure) return {ok:false,status:422,json:async()=>({errors:{entries:['Choose the required months.']}})};
                        const activity=options.body.get('entries[1][activity]');
                        return {ok:true,status:200,text:async()=>'<html><body style="width:1122px"><h1>MAJOR ACTIVITIES/WORK PLAN</h1><p>'+activity+'</p><div style="height:2000px"></div></body></html>'};
                    };
                    document.addEventListener('alpine:init',()=>{
                        const register=Alpine.data.bind(Alpine);
                        Alpine.data=(name,factory)=>register(name,config=>{
                            const state=factory(config); const initialize=state.init;
                            state.init=function(){window.workPlanState=this;initialize.call(this)};
                            return state;
                        });
                        ${controller}
                    });` });
                await page.addScriptTag({ content: readFileSync(new URL('../../node_modules/alpinejs/dist/cdn.min.js', import.meta.url), 'utf8') });
                await page.locator('#activity-2').waitFor();
                assert.equal(await page.locator('[data-proposal-workspace-toolbar]').count(), 1);
                assert.equal(await page.locator('#objective-2').getAttribute('readonly'), '');
                assert.equal(await page.locator('.proposal-preview-dock').isVisible(), false);
                await page.locator('#output-2').fill('Reviewed evaluation dataset');
                const secondEntry = page.locator('[data-work-plan-entry-id="2"]');
                assert.equal(await secondEntry.locator('input[type="checkbox"][value="1"]').isDisabled(), true);
                await secondEntry.locator('label:has(input[value="15"])').click();
                assert.equal(await secondEntry.locator('input[type="checkbox"][value="15"]').isChecked(), true);
                await page.waitForFunction(() => window.savedBodies.length > 0 && !window.workPlanState.autoSaveInFlight);
                assert.equal(await page.evaluate(() => window.previewBodies.length), 0);
                const saved = await page.evaluate(() => window.savedBodies.at(-1));
                assert.ok(saved.some(([name, value]) => name === 'entries[1][expected_output]' && value === 'Reviewed evaluation dataset'));
                assert.ok(saved.some(([name, value]) => name === 'entries[1][months][]' && value === '15'));

                await page.getByRole('button', { name: 'Collapse objective', exact: true }).click();
                await page.locator('#activity-2').waitFor({ state: 'hidden' });
                assert.equal(await page.locator('#activity-2').isVisible(), false);
                await page.getByRole('button', { name: 'Expand objective', exact: true }).click();
                await page.locator('#activity-2').waitFor({ state: 'visible' });
                assert.equal(await page.locator('#activity-2').isVisible(), true);
                await page.locator('.proposal-writing-preview-launcher').click();
                await page.waitForFunction(() => window.workPlanState.previewReady && !window.workPlanState.previewLoading);
                const request = await page.evaluate(() => window.previewBodies.at(-1));
                assert.ok(request.some(([name, value]) => name === 'entries[1][months][]' && value === '15'));
                assert.equal(request.some(([name]) => name === '_method'), false);
                if (width >= 1024) {
                    assert.equal(await page.locator('.proposal-preview-dock-expanded').count(), 0);
                    await page.getByRole('button', { name: 'Enlarge the paper preview' }).click();
                }
                await page.locator('.proposal-preview-dock-expanded').waitFor({ state: 'visible' });
                assert.equal(await page.locator('.proposal-preview-dock-expanded').isVisible(), true);
                assert.equal(await page.locator('.proposal-edit-pane').evaluate((element) => element.inert), true);
                await page.getByRole('button', { name: 'Zoom in', exact: true }).click();
                assert.equal(await page.locator('.proposal-preview-zoom output').textContent(), '110%');
                await page.locator('[x-ref="previewFrame"]').focus();
                await page.keyboard.press('Tab');
                assert.equal(await page.getByRole('button', { name: 'Zoom out', exact: true }).evaluate((element) => element === document.activeElement), true);
                await page.keyboard.press('Escape');
                await page.waitForFunction(() => !window.workPlanState.previewFullscreen);
                const restoredFocus = width >= 1024 ? page.getByRole('button', { name: 'Enlarge the paper preview' }) : page.locator('.proposal-writing-preview-launcher');
                await page.waitForFunction((element) => element === document.activeElement, await restoredFocus.elementHandle());
                await page.locator('#activity-2').fill('Validate results with community monitors');
                if (width < 1024) await page.locator('.proposal-writing-preview-launcher').click();
                await page.waitForFunction(() => !window.workPlanState.previewStale && window.workPlanState.previewHtml.includes('Validate results'));
                const lastPreview = await page.evaluate(() => window.workPlanState.previewHtml);
                await page.evaluate(() => { window.previewFailure = true; });
                await page.getByRole('button', { name: 'Refresh preview', exact: true }).click();
                await page.waitForFunction(() => !window.workPlanState.previewLoading && window.workPlanState.validationMessage);
                assert.equal(await page.evaluate(() => window.workPlanState.previewHtml), lastPreview);
                assert.equal(await page.getByRole('alert').textContent(), 'Choose the required months.');
                await page.evaluate(() => { window.previewFailure = false; });
                await page.getByRole('button', { name: 'Refresh preview', exact: true }).click();
                await page.waitForFunction(() => !window.workPlanState.previewLoading && !window.workPlanState.previewStale);
                await page.evaluate(() => window.workPlanState.closeProposalPreview());
                assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
                const monthColumns = await secondEntry.locator('.work-plan-writing-months').first().evaluate((element) => getComputedStyle(element).gridTemplateColumns.split(' ').length);
                assert.ok(monthColumns >= 3 && monthColumns <= 12);
                if (process.env.ATHENA_EDITOR_VISUAL_DIR) {
                    await page.screenshot({ path: resolve(process.env.ATHENA_EDITOR_VISUAL_DIR, `work-plan-workspace-${width}-${dark ? 'dark' : 'light'}.png`), fullPage: true });
                }
                await page.evaluate(() => {
                    document.body.classList.add('revision-embedded');
                    document.querySelector('main').parentElement.setAttribute('data-revision-embedded', '');
                    window.workPlanState.showProposalPreview();
                });
                assert.equal(await page.evaluate(() => window.workPlanState.previewPaneOpen || window.workPlanState.previewFullscreen), false);
                assert.equal(await page.locator('[data-proposal-preview-toggle]').isVisible(), false);
                assert.deepEqual(errors, []);
                await page.close();
            }
        }
    } finally {
        await browser.close();
    }
});
