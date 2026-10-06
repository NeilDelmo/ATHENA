import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { runInNewContext } from 'node:vm';
import { chromium } from '@playwright/test';
import postcss from 'postcss';
import tailwindcss from 'tailwindcss';
import loadConfig from 'tailwindcss/loadConfig.js';

const read = path => readFileSync(resolve(path), 'utf8').replaceAll('\r\n', '\n');
const app = read('resources/js/app.js');
const start = app.indexOf("Alpine.data('monitoringToolForm',");
const factorySource = app.slice(start, app.indexOf('Alpine.data(', start + 1));
const previewSource = app.slice(app.indexOf('const documentPreviewForm ='), start);
const datePicker = app.slice(app.indexOf("Alpine.data('datePicker',"), app.indexOf("Alpine.data('dateTimePicker',"));
const dateHelpers = app.slice(app.indexOf('function normalizeIsoDate('), app.indexOf("Alpine.data('notificationMenu',"));
const previewHelpers = read('resources/js/proposal-preview-workspace.js').replaceAll('export ', '')
    + read('resources/js/proposal-paper-workspace.js').replace(/^import .+;\n/gm, '').replaceAll('export ', '');
const image = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jz1kAAAAASUVORK5CYII=', 'base64');

function state(config = {}) {
    let factory;
    runInNewContext(factorySource, { Alpine: { data: (_, value) => { factory = value; } }, documentPreviewForm: () => ({}), window: {} });
    const result = factory(config);
    result.$nextTick = callback => callback?.();
    result.triggerMonitoringDraftAutoSave = () => {};
    return result;
}

test('completion uses evidenced quantities, stays within the approved weight and counts spanning activities once', () => {
    const form = state({ initialPeriodKey: '2026-2', previousProgressByPeriod: { '2026-2': {
        'plan-0': { accomplished_percentage: 25 }, 'plan-1': { accomplished_percentage: 15 },
    } } });
    const entry = { source_work_plan_index: 1, percent_weight: 50, target_units: 20, completed_units: 12, evidence: [{ id: 'proof' }] };
    form.entries = [entry];
    form.updateEntryProgress(entry);
    assert.equal(entry.completion, 60);
    assert.equal(entry.accomplished_percentage, 30);
    assert.equal(form.totalProjectProgress(), 55);
    entry.evidence.push({ id: 'extra-proof' });
    form.updateEntryProgress(entry);
    assert.equal(entry.accomplished_percentage, 30);
    entry.completed_units = 100;
    form.updateEntryProgress(entry);
    assert.equal(entry.accomplished_percentage, 50);
    form.removeEvidence(entry, 'proof');
    form.removeEvidence(entry, 'extra-proof');
    assert.equal(entry.accomplished_percentage, 0);
    assert.equal(form.totalProjectProgress(), 25);
    entry.percent_weight = 33.33;
    entry.target_units = 2;
    entry.completed_units = 1;
    entry.evidence = [{ id: 'rounding' }];
    form.updateEntryProgress(entry);
    assert.equal(entry.accomplished_percentage, 16.67);
});

test('a completed output is counted once and removing its last proof resets completion', () => {
    const form = state();
    const entry = { percent_weight: 40, target_units: 1, evidence: [] };
    form.setEvidenceFiles(entry, { target: { files: [{ name: 'report.pdf', size: 100 }, { name: 'photo.png', size: 100 }] } });
    assert.equal(entry.completion, 100);
    assert.equal(entry.accomplished_percentage, 40);
    entry.pendingEvidenceCount = 0;
    form.updateEntryProgress(entry);
    assert.equal(entry.accomplished_percentage, 0);
});

test('upload responses preserve evidence removed and files selected while saving', () => {
    const form = state();
    const entry = { source_work_plan_index: 0, percent_weight: 50, target_units: 1, evidence: [], pendingEvidenceCount: 1 };
    form.entries = [entry];
    const field = { name: 'activity_evidence[plan-0][]', files: [{ name: 'new.png', size: 1, lastModified: 2 }], value: 'new.png' };
    form.acceptSavedEvidence([{ ...entry, evidence: [{ id: 'removed' }, { id: 'uploaded' }] }], {
        'plan-0': { ids: ['removed'], files: form.evidenceFileFingerprint([{ name: 'old.png', size: 1, lastModified: 1 }]) },
    }, { querySelectorAll: () => [field] });
    assert.deepEqual([...entry.evidence].map(file => file.id), ['uploaded']);
    assert.equal(field.value, 'new.png');
    assert.equal(entry.pendingEvidenceCount, 1);
});

test('the rendered monitoring form uploads, computes, autosaves and restores evidence on desktop and mobile', async () => {
    const fixture = resolve('../tmp/monitoring-evidence.html');
    execFileSync('php', ['artisan', 'test', '--compact', 'tests/Feature/MonitoringEvidenceTest.php', '--filter=evidence computes partial weighted'], {
        cwd: resolve('.'), encoding: 'utf8', maxBuffer: 3 * 1024 * 1024,
        env: { ...process.env, ATHENA_MONITORING_EVIDENCE_FIXTURE: fixture },
    });
    const styles = await postcss([tailwindcss(loadConfig(resolve('tailwind.config.js')))]).process(read('resources/css/app.css'), { from: undefined });
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        for (const width of [1440, 390]) {
            const page = await browser.newPage({ viewport: { width, height: 1000 }, reducedMotion: 'reduce' });
            const errors = [];
            page.on('pageerror', error => errors.push(error.message));
            await page.setContent(`<html><style>${styles.css}\n${read('resources/css/proposal-paper-workspace.css')}</style><body><main>${readFileSync(fixture, 'utf8')}</main></body></html>`);
            await page.addScriptTag({ content: `${previewHelpers}\n${previewSource}\n${dateHelpers}
                window.savedRows=[];window.saveRequests=[];
                window.fetch=async(url,options)=>{
                    const fields=[...options.body.entries()];window.saveRequests.push(fields);
                    const current=window.monitorState.entries[0];
                    const ids=fields.filter(([name])=>name==='work_plan[0][evidence_ids][]').map(([,id])=>id);
                    const uploads=fields.filter(([name,file])=>name==='activity_evidence[plan-0][]' && file instanceof File && file.size);
                    const evidence=(current.evidence||[]).filter(file=>ids.includes(file.id));
                    for(const [,file] of uploads) evidence.push({id:'00000000-0000-4000-8000-'+String(window.saveRequests.length).padStart(12,'0'),name:file.name});
                    const units=Number(fields.find(([name])=>name==='work_plan[0][completed_units]')?.[1]||0);
                    window.savedRows=JSON.parse(JSON.stringify([{...current,evidence,pendingEvidenceCount:0,completed_units:units,accomplished_percentage:evidence.length?Number((50*units/20).toFixed(2)):0}]));
                    return {ok:true,status:200,json:async()=>({draft_version:window.saveRequests.length+1,work_plan:window.savedRows})};
                };
                document.addEventListener('alpine:init',()=>{
                    ${datePicker}
                    const register=Alpine.data.bind(Alpine);
                    Alpine.data=(name,factory)=>register(name,(config)=>{
                        const value=factory(config);const initialize=value.init;
                        value.init=function(){window.monitorState=this;initialize.call(this)};return value;
                    });
                    ${factorySource}
                });` });
            await page.addScriptTag({ path: resolve('node_modules/alpinejs/dist/cdn.min.js') });
            await page.waitForFunction(() => window.monitorState?.entries[0]?.completion === 60);
            assert.equal(await page.locator('input[x-model="entry.completion"]').count(), 0);
            const panel = page.locator('[data-monitoring-evidence]');
            await panel.getByRole('button', { name: 'Remove interviews.png' }).click();
            await page.waitForFunction(() => window.monitorState.entries[0].completion === 0);
            assert.equal(await page.evaluate(() => window.monitorState.finishMonitoringDraftAutoSave()), true);
            await panel.locator('input[type="file"]').setInputFiles({ name: 'proof.png', mimeType: 'image/png', buffer: image });
            await page.waitForFunction(() => window.monitorState.entries[0].completion === 60);
            assert.equal(await page.evaluate(() => window.monitorState.finishMonitoringDraftAutoSave()), true);
            assert.equal(await panel.getByRole('link', { name: 'proof.png' }).count(), 1);
            assert.equal(await panel.locator('input[type="file"]').evaluate(field => field.files.length), 0);
            await panel.locator('input[type="number"]').fill('6');
            await page.waitForFunction(() => window.monitorState.entries[0].completion === 30);
            assert.equal(await page.evaluate(() => window.monitorState.finishMonitoringDraftAutoSave()), true);
            assert.equal(await page.evaluate(() => window.savedRows[0].accomplished_percentage), 15);
            const saves = await page.evaluate(() => window.saveRequests.length);
            await page.waitForTimeout(1400);
            assert.equal(await page.evaluate(() => window.saveRequests.length), saves, 'saved evidence does not cause repeated uploads');
            await page.evaluate(() => {
                window.monitorState.entries = structuredClone(window.savedRows);
                window.monitorState.updateEntryProgress(window.monitorState.entries[0], false);
            });
            assert.equal(await page.evaluate(() => window.monitorState.totalProjectProgress()), 15);
            assert.equal(await panel.getByRole('link', { name: 'proof.png' }).count(), 1);
            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), true);
            await panel.screenshot({ path: resolve(`../tmp/monitoring-evidence-${width}.png`) });
            await panel.locator('input[type="file"]').setInputFiles({ name: 'final-proof.png', mimeType: 'image/png', buffer: image });
            await page.evaluate(() => {
                HTMLFormElement.prototype.submit = function () { window.preparedFields = [...new FormData(this).entries()].map(([name,value]) => [name,value instanceof File ? value.name : value]); };
            });
            await page.locator('[data-proposal-workspace-toolbar] button[type="submit"]').click();
            await page.waitForFunction(() => Boolean(window.preparedFields));
            assert.equal(await page.evaluate(() => window.preparedFields.find(([name]) => name === 'work_plan[0][accomplished_percentage]')[1]), '15');
            assert.equal(await page.evaluate(() => window.preparedFields.filter(([name]) => name === 'work_plan[0][evidence_ids][]').length), 2);
            assert.equal(await page.evaluate(() => window.preparedFields.find(([name]) => name === 'activity_evidence[plan-0][]')[1]), '');
            assert.deepEqual(errors, []);
            await page.close();
        }
    } finally {
        await browser.close();
    }
});
