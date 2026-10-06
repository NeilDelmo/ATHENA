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
const app = read('resources/js/app.js');
const previewSource = app.slice(app.indexOf('const documentPreviewForm ='), app.indexOf("Alpine.data('monitoringToolForm',"));
const controllers = app.slice(app.indexOf("Alpine.data('monitoringToolForm',"), app.indexOf("Alpine.data('noticeToProceedForm',"));
const datePicker = app.slice(app.indexOf("Alpine.data('datePicker',"), app.indexOf("Alpine.data('dateTimePicker',"));
const dateHelpers = app.slice(app.indexOf('function normalizeIsoDate('), app.indexOf("Alpine.data('notificationMenu',"));
const previewHelpers = read('resources/js/proposal-preview-workspace.js').replaceAll('export ', '')
    + read('resources/js/proposal-paper-workspace.js').replace(/^import .+;\n/gm, '').replaceAll('export ', '');
const image = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jz1kAAAAASUVORK5CYII=', 'base64');

test('monitoring previews use the proposal paper panel with live edits, images and accessible full view', async () => {
    const fixtureDirectory = resolve('../tmp');
    if (!process.env.ATHENA_MONITORING_USE_EXISTING_FIXTURES) {
        execFileSync('php', ['artisan', 'test', '--compact', 'tests/Feature/MonitoringPageLoadingTest.php', '--filter=monitoring report forms share'], {
            cwd: resolve('.'), encoding: 'utf8', maxBuffer: 3 * 1024 * 1024,
            env: { ...process.env, ATHENA_MONITORING_BROWSER_FIXTURE_DIRECTORY: fixtureDirectory },
        });
    }
    const styles = await postcss([tailwindcss(loadConfig(resolve('tailwind.config.js')))])
        .process(read('resources/css/app.css'), { from: undefined });
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        for (const type of ['monitoring', 'progress', 'terminal']) {
            for (const width of [1440, 390]) {
                for (const dark of [false, true]) {
                    const page = await browser.newPage({ viewport: { width, height: 1000 }, reducedMotion: 'reduce' });
                    const errors = [];
                    page.on('pageerror', (error) => errors.push(error.message));
                    await page.setContent(`<html class="${dark ? 'dark' : ''}"><style>${styles.css}\n${read('resources/css/proposal-paper-workspace.css')}</style>
                        <body data-app-shell><div style="height:120px">Application header</div>
                        <main class="mx-auto max-w-[90rem] p-4">${readFileSync(resolve(fixtureDirectory, `monitoring-preview-${type}.html`), 'utf8')}</main></body></html>`);
                    await page.addScriptTag({ content: `${previewHelpers}\n${previewSource}\n${dateHelpers}
                        window.previewRequests=[];window.savedDrafts=[];window.previewFailure=false;window.previewDelay=0;
                        window.fetch=async(url,options)=>{
                            const fields=[...options.body.entries()];
                            if(url.includes('/draft')) {
                                window.savedDrafts.push(fields);
                                return {ok:true,status:200,json:async()=>({draft_version:window.savedDrafts.length})};
                            }
                            window.previewRequests.push(fields);
                            await new Promise(resolve=>setTimeout(resolve,window.previewDelay));
                            if(window.previewFailure) return {ok:false,status:422,json:async()=>({errors:{report:['Please review the report details.']}})};
                            const photo=fields.find(([name,value])=>value instanceof File && value.size && name!=='attachment');
                            const text=fields.filter(([,value])=>typeof value==='string').map(([,value])=>value).join(' ');
                            return {ok:true,status:200,text:async()=>'<html><style>body{margin:0;padding:20px;background:#e2e8f0}.sheet{box-sizing:border-box;width:${type === 'monitoring' ? '297mm' : '210mm'};min-height:297mm;padding:24mm;background:white;font:16px Arial}img{width:80px;height:80px}</style><body><article class="sheet"><h1>${type} report</h1><p>'+text+'</p>'+(photo?'<figure><img data-preview-file-input="'+photo[0]+'"><figcaption>Field evidence</figcaption></figure>':'')+'</article></body></html>'};
                        };
                        document.addEventListener('alpine:init',()=>{
                            ${datePicker}
                            const register=Alpine.data.bind(Alpine);
                            Alpine.data=(name,factory)=>register(name,(config)=>{
                                const state=factory(config);const initialize=state.init;
                                state.init=function(){window.monitorState=this;initialize.call(this)};
                                return state;
                            });
                            ${controllers}
                        });
                    ` });
                    await page.addScriptTag({ path: resolve('node_modules/alpinejs/dist/cdn.min.js') });
                    await page.waitForFunction(() => Boolean(window.monitorState));
                    const workspace = page.locator('[data-monitoring-paper-workspace]');
                    const form = workspace.locator('form[x-ref="form"]');
                    assert.equal(await workspace.locator('.proposal-preview-dock').isVisible(), false);
                    assert.equal(await page.evaluate(() => window.previewRequests.length), 0);
                    if (type === 'progress') await page.getByRole('button', { name: 'Add figure', exact: true }).click();
                    const fileName = type === 'progress' ? 'figures[0][image]' : 'cover_image';
                    if (type !== 'monitoring') {
                        await form.locator(`input[name="${fileName}"]`).setInputFiles({ name: 'field-evidence.png', mimeType: 'image/png', buffer: image });
                    }
                    await form.evaluate((element) => {
                        for (const picker of element.querySelectorAll('[x-data^="datePicker("]')) {
                            const state = window.Alpine.$data(picker);
                            if (state.required && !state.value) state.value = state.min > '2026-03-31' ? state.min : state.max < '2026-03-31' ? state.max : '2026-03-31';
                        }
                        for (const field of element.querySelectorAll('[required]')) {
                            if (field.value || field.type === 'file' || field.type === 'hidden') continue;
                            if (field.tagName === 'SELECT') {
                                field.value = [...field.options].find((option) => option.value)?.value || '';
                            } else if (field.type === 'date') {
                                field.value = '2026-03-31';
                                if (field.min && field.value < field.min) field.value = field.min;
                                if (field.max && field.value > field.max) field.value = field.max;
                            } else if (field.type === 'number') {
                                const minimum = Number(field.min || 0);
                                const maximum = field.max === '' ? Infinity : Number(field.max);
                                field.value = String(Math.min(maximum, Math.max(minimum, 1)));
                            } else if (field.type === 'checkbox') field.checked = true;
                            else field.value = 'Recorded results';
                            field.dispatchEvent(new Event('input', { bubbles: true }));
                            field.dispatchEvent(new Event('change', { bubbles: true }));
                        }
                    });
                    const field = form.locator('textarea:not([readonly])').first();
                    await field.fill('First field notes.');
                    const invalid = await form.evaluate((element) => [...element.elements].filter((control) => control.willValidate && !control.validity.valid).map((control) => [control.name, control.validationMessage]));
                    assert.deepEqual(invalid, [], `${type} fixture must be valid before previewing`);
                    const toolbar = workspace.locator('[data-proposal-workspace-toolbar]');
                    assert.equal(await toolbar.isVisible(), true);
                    assert.equal(await form.locator('[data-monitoring-action-dock-fixed]').count(), 0);
                    await toolbar.locator('[data-writing-toolbar-close]').click();
                    await toolbar.locator('[data-writing-toolbar-controls]').waitFor({ state: 'hidden' });
                    await page.waitForFunction(() => document.activeElement?.hasAttribute('data-writing-toolbar-open'));
                    assert.equal(await toolbar.locator('[data-writing-toolbar-controls]').isVisible(), false);
                    assert.equal(await toolbar.locator('[data-writing-toolbar-open]').evaluate((element) => element === document.activeElement), true);
                    assert.equal(await field.inputValue(), 'First field notes.');
                    await toolbar.locator('[data-writing-toolbar-open]').click();
                    await toolbar.locator('[data-writing-toolbar-controls]').waitFor({ state: 'visible' });
                    await page.waitForFunction(() => document.activeElement?.hasAttribute('data-writing-toolbar-close'));
                    assert.equal(await toolbar.locator('[data-writing-toolbar-controls]').isVisible(), true);
                    assert.equal(await toolbar.locator('[data-writing-toolbar-close]').evaluate((element) => element === document.activeElement), true);
                    const previewButton = toolbar.locator('[data-proposal-preview-toggle]');
                    await previewButton.click();
                    await page.waitForFunction(() => window.monitorState.previewReady && !window.monitorState.previewLoading, null, { timeout: 10000 }).catch(async (error) => {
                        assert.fail(`${type}/${width}/${dark ? 'dark' : 'light'}: ${error.message}; ${JSON.stringify(await page.evaluate(() => ({ ready: window.monitorState.previewReady, loading: window.monitorState.previewLoading, error: window.monitorState.previewError, html: window.monitorState.previewHtml, requests: window.previewRequests.length })))}; browser errors: ${errors.join('; ')}`);
                    });
                    const dock = workspace.locator('.proposal-preview-dock');
                    const frame = dock.frameLocator('iframe');
                    await frame.getByText(/First field notes/).waitFor();
                    if (type !== 'monitoring') {
                        assert.equal(await frame.locator('img').evaluate((element) => element.naturalWidth), 1, 'Uploaded images must be hydrated inside the paper iframe');
                    }
                    if (width >= 1024) {
                        assert.equal(await page.evaluate(() => window.monitorState.previewFullscreen), false);
                        await field.scrollIntoViewIfNeeded();
                        const pane = await workspace.locator('.proposal-edit-pane').boundingBox();
                        const bounds = await dock.boundingBox();
                        assert.ok(bounds.x >= pane.x + pane.width, 'Preview must be beside the editing form');
                        await dock.getByRole('button', { name: 'Enlarge the paper preview', exact: true }).click();
                    }
                    assert.equal(await dock.locator('[role="dialog"][aria-modal="true"]').count(), 1);
                    assert.equal(await workspace.locator('.proposal-edit-pane').evaluate((element) => element.inert), true);
                    assert.equal(await toolbar.evaluate((element) => element.inert), true);
                    const zoom = dock.locator('output');
                    await dock.getByRole('button', { name: 'Zoom in', exact: true }).click();
                    assert.equal(await zoom.innerText(), '110%');
                    await dock.getByRole('button', { name: 'Zoom out', exact: true }).click();
                    assert.equal(await zoom.innerText(), '100%');
                    await dock.getByRole('button', { name: 'Print', exact: true }).focus();
                    await page.keyboard.press('Shift+Tab');
                    assert.equal(await dock.getByRole('button', { name: 'Refresh preview', exact: true }).evaluate((element) => element === document.activeElement), true);
                    await frame.locator('body').press('Escape');
                    assert.equal(await page.evaluate(() => window.monitorState.previewFullscreen), false);
                    assert.equal(await field.inputValue(), 'First field notes.');
                    assert.equal(await workspace.locator('.proposal-edit-pane').getAttribute('inert'), null);
                    assert.equal(await toolbar.evaluate((element) => element.inert), false);
                    await page.evaluate(() => { window.previewDelay = 400; });
                    await field.fill('Updated field notes.');
                    assert.equal(await page.evaluate(() => window.monitorState.previewStale), true);
                    if (width < 1024) await previewButton.click();
                    assert.equal(await dock.getByRole('button', { name: 'Print', exact: true }).isDisabled(), true);
                    await page.waitForFunction(() => window.monitorState.previewHtml.includes('Updated field notes.') && window.monitorState.previewReady && !window.monitorState.previewLoading);
                    await frame.getByText(/Updated field notes/).waitFor();
                    await page.waitForFunction(() => window.savedDrafts.some((fields) => fields.some(([,value]) => value === 'Updated field notes.')));
                    await page.evaluate(() => { window.previewFailure = true; });
                    await dock.getByRole('button', { name: 'Refresh preview', exact: true }).click();
                    await dock.getByRole('alert').getByText('Please review the report details.').waitFor();
                    assert.equal(await field.inputValue(), 'Updated field notes.');
                    await page.evaluate(() => { window.previewFailure = false; });
                    await dock.getByRole('button', { name: 'Refresh preview', exact: true }).click();
                    await page.waitForFunction(() => !window.monitorState.previewError && window.monitorState.previewReady && !window.monitorState.previewLoading);
                    assert.equal(await page.getByRole('button', { name: 'View full paper', exact: true }).count(), 0);
                    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
                    assert.deepEqual(errors, []);
                    if (process.env.MONITORING_PREVIEW_SCREENSHOT_DIRECTORY) {
                        if (width >= 1024) await field.scrollIntoViewIfNeeded();
                        await page.screenshot({ path: resolve(process.env.MONITORING_PREVIEW_SCREENSHOT_DIRECTORY, `monitoring-paper-${type}-${width}-${dark ? 'dark' : 'light'}.png`) });
                    }
                    await page.close();
                }
            }
        }
    } finally {
        await browser.close();
    }
});
