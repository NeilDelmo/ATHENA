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
const source = app.slice(app.indexOf("Alpine.data('narrativeProgressReportForm',"), app.indexOf("Alpine.data('noticeToProceedForm',"));
const previewSource = app.slice(app.indexOf('const documentPreviewForm ='), app.indexOf("Alpine.data('monitoringToolForm',"));
const datePicker = app.slice(app.indexOf("Alpine.data('datePicker',"), app.indexOf("Alpine.data('dateTimePicker',"));
const dateHelpers = app.slice(app.indexOf('function normalizeIsoDate('), app.indexOf("Alpine.data('notificationMenu',"));
const previewHelpers = read('resources/js/proposal-preview-workspace.js').replaceAll('export ', '')
    + read('resources/js/proposal-paper-workspace.js').replace(/^import .+;\n/gm, '').replaceAll('export ', '');
const rowKey = 'row-00000000-0000-4000-8000-000000000001';
const image = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jz1kAAAAASUVORK5CYII=', 'base64');

function state(config = {}) {
    let factory;
    runInNewContext(previewHelpers + previewSource + source, {
        Alpine: { data: (_, callback) => { factory = callback; } },
        crypto,
        File,
        window: { crypto, clearTimeout() {} },
        FormData: class extends FormData {
            constructor(entries) {
                super();
                for (const [name, value] of entries) this.append(name, value);
            }
        },
    });
    const result = factory(config);
    result.$nextTick = callback => callback?.();
    result.triggerNarrativeDraftAutoSave = () => {};
    return result;
}

test('optional accomplishment evidence keeps stable row keys and leaves reported accomplishments unchanged', () => {
    const form = state({ initialAccomplishments: [
        { evidence_key: rowKey, objective: 'Interview users', target: '20 interviews', actual: '12 interviews', evidence: [] },
        { evidence_key: 'row-00000000-0000-4000-8000-000000000002', objective: 'Summarize results', target: '1 report', actual: 'Not yet started', evidence: [] },
    ] });
    const remaining = form.accomplishmentRows[1];
    form.removeAccomplishment(0);
    assert.equal(form.accomplishmentRows[0], remaining);
    assert.equal(remaining.evidence_key, 'row-00000000-0000-4000-8000-000000000002');
    form.addAccomplishment();
    assert.match(form.accomplishmentRows[1].evidence_key, /^row-[a-f0-9-]{36}$/);
    form.selectAccomplishmentEvidence(remaining, { target: { files: [new File(['proof'], 'proof.pdf', { type: 'application/pdf', lastModified: 1 })] } });
    assert.equal(remaining.actual, 'Not yet started');
    assert.equal(remaining.target, '1 report');
    assert.equal(remaining.pendingEvidenceFiles.length, 1);
});

test('draft fingerprints identify binary selections while preview requests retain only report content and figures', () => {
    const form = state();
    const file = new File(['proof'], 'proof.pdf', { type: 'application/pdf', lastModified: 1 });
    const entries = [
        ['_token', 'token'], ['draft_version', '2'],
        ['accomplishments[0][actual]', '12 interviews'],
        ['accomplishments[0][evidence_key]', rowKey],
        ['accomplishments[0][evidence_ids][]', 'saved-proof'],
        [`accomplishment_evidence[${rowKey}][]`, file],
        ['figures[0][image]', new File(['figure'], 'figure.png', { type: 'image/png' })],
        ['figures[0][caption]', 'Results diagram'],
    ];
    const draft = form.narrativeDraftFormData(entries);
    assert.equal(draft.get(`accomplishment_evidence[${rowKey}][]`).name, 'proof.pdf');
    assert.equal(draft.has('figures[0][image]'), false);
    const fingerprint = form.narrativeDraftFingerprint(entries);
    const changed = entries.map(([name, value]) => [name, name.startsWith('accomplishment_evidence[')
        ? new File(['proof'], 'proof.pdf', { type: 'application/pdf', lastModified: 2 }) : value]);
    assert.notEqual(form.narrativeDraftFingerprint(changed), fingerprint, 'Replacing a same-sized, same-named file must trigger autosave');
    assert.equal(form.narrativeDraftFingerprint(entries.map(([name, value]) => [name, name === 'draft_version' ? '3' : value])), fingerprint);
    const preview = form.previewFormData(entries);
    assert.equal(preview.has(`accomplishment_evidence[${rowKey}][]`), false);
    assert.equal(preview.has('accomplishments[0][evidence_key]'), false);
    assert.equal(preview.has('accomplishments[0][evidence_ids][]'), false);
    assert.equal(preview.get('accomplishments[0][actual]'), '12 interviews');
    assert.equal(preview.get('figures[0][image]').name, 'figure.png');
});

test('a successful evidence response adopts saved files and clears the matching upload once', () => {
    const form = state({ initialAccomplishments: [{ evidence_key: rowKey, actual: '12 interviews', evidence: [{ id: 'retained', name: 'existing.pdf' }] }] });
    const row = form.accomplishmentRows[0];
    const selected = new File(['proof'], 'proof.pdf', { type: 'application/pdf', lastModified: 1 });
    row.pendingEvidenceFiles = [selected];
    const field = { name: `accomplishment_evidence[${rowKey}][]`, files: [selected], value: 'proof.pdf' };
    form.acceptSavedAccomplishmentEvidence([{ evidence_key: rowKey, evidence: [{ id: 'retained', name: 'existing.pdf' }, { id: 'uploaded', name: 'proof.pdf' }] }], {
        [rowKey]: { ids: ['retained'], files: form.accomplishmentEvidenceFileFingerprint([selected]) },
    }, { querySelectorAll: () => [field] });
    assert.deepEqual([...row.evidence].map(file => file.id), ['retained', 'uploaded']);
    assert.equal(field.value, '');
    assert.equal(row.pendingEvidenceFiles.length, 0);
    assert.equal(row.actual, '12 interviews');
});

test('evidence saved during an edit preserves removals and a newly selected replacement file', () => {
    const form = state({ initialAccomplishments: [{ evidence_key: rowKey, evidence: [] }] });
    const row = form.accomplishmentRows[0];
    const earlier = new File(['old'], 'proof.pdf', { type: 'application/pdf', lastModified: 1 });
    const replacement = new File(['new'], 'proof.pdf', { type: 'application/pdf', lastModified: 2 });
    row.pendingEvidenceFiles = [replacement];
    const field = { name: `accomplishment_evidence[${rowKey}][]`, files: [replacement], value: 'proof.pdf' };
    form.acceptSavedAccomplishmentEvidence([{ evidence_key: rowKey, evidence: [{ id: 'removed', name: 'old.pdf' }, { id: 'uploaded', name: 'proof.pdf' }] }], {
        [rowKey]: { ids: ['removed'], files: form.accomplishmentEvidenceFileFingerprint([earlier]) },
    }, { querySelectorAll: () => [field] });
    assert.deepEqual([...row.evidence].map(file => file.id), ['uploaded']);
    assert.equal(field.value, 'proof.pdf');
    assert.equal(row.pendingEvidenceFiles[0], replacement);
});

test('invalid evidence selections and upload rejections leave accomplishments intact and preserve later selections', () => {
    const form = state({ initialAccomplishments: [{ evidence_key: rowKey, actual: '12 interviews', evidence: [] }] });
    const row = form.accomplishmentRows[0];
    const invalid = { files: [new File(['script'], 'proof.exe')], value: 'proof.exe' };
    form.selectAccomplishmentEvidence(row, { target: invalid });
    assert.equal(invalid.value, '');
    assert.equal(row.pendingEvidenceFiles.length, 0);
    assert.match(row.evidenceError, /five.*10 MB/);
    assert.equal(row.actual, '12 interviews');
    const earlier = new File(['old'], 'proof.pdf', { type: 'application/pdf', lastModified: 1 });
    const replacement = new File(['new'], 'proof.pdf', { type: 'application/pdf', lastModified: 2 });
    row.pendingEvidenceFiles = [replacement];
    row.evidenceError = '';
    const field = { name: `accomplishment_evidence[${rowKey}][]`, files: [replacement], value: 'proof.pdf' };
    const errors = { [`accomplishment_evidence.${rowKey}.0`]: ['The selected evidence is invalid.'] };
    const snapshot = { [rowKey]: { ids: [], files: form.accomplishmentEvidenceFileFingerprint([earlier]) } };
    form.rejectAccomplishmentEvidence(errors, snapshot, { querySelectorAll: () => [field] });
    assert.equal(field.value, 'proof.pdf');
    assert.equal(row.pendingEvidenceFiles[0], replacement);
    assert.equal(row.evidenceError, '');
    snapshot[rowKey].files = form.accomplishmentEvidenceFileFingerprint([replacement]);
    form.rejectAccomplishmentEvidence(errors, snapshot, { querySelectorAll: () => [field] });
    assert.equal(field.value, '');
    assert.equal(row.pendingEvidenceFiles.length, 0);
    assert.equal(row.evidenceError, 'The selected evidence is invalid.');
});

test('rendered progress reports save private evidence and keep it out of preview on desktop and mobile in both themes', async () => {
    const fixture = resolve('../tmp/narrative-progress-evidence.html');
    if (!process.env.ATHENA_NARRATIVE_EVIDENCE_USE_EXISTING_FIXTURE) {
        execFileSync('php', ['artisan', 'test', '--compact', 'tests/Feature/NarrativeProgressEvidenceTest.php', '--filter=narrative accomplishment evidence uploads'], {
            cwd: resolve('.'), encoding: 'utf8', maxBuffer: 3 * 1024 * 1024,
            env: { ...process.env, ATHENA_NARRATIVE_EVIDENCE_FIXTURE: fixture },
        });
    }
    const styles = await postcss([tailwindcss(loadConfig(resolve('tailwind.config.js')))]).process(read('resources/css/app.css'), { from: undefined });
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        for (const width of [1440, 390]) {
            for (const dark of [false, true]) {
                const page = await browser.newPage({ viewport: { width, height: 1000 }, reducedMotion: 'reduce' });
                const errors = [];
                page.on('pageerror', error => errors.push(error.message));
                await page.setContent(`<html class="${dark ? 'dark' : ''}"><style>${styles.css}\n${read('resources/css/proposal-paper-workspace.css')}</style><body data-app-shell><main class="mx-auto max-w-[90rem] p-4">${readFileSync(fixture, 'utf8')}</main></body></html>`);
                await page.addScriptTag({ content: `${previewHelpers}\n${previewSource}\n${dateHelpers}
                    window.saveRequests=[];window.previewRequests=[];window.savedRows=[];window.gateNextSave=false;
                    window.fetch=async(url,options)=>{
                        const fields=[...options.body.entries()];
                        if(!url.includes('/draft')) {
                            window.previewRequests.push(fields);
                            const actual=fields.find(([name])=>name==='accomplishments[0][actual]')?.[1]||'';
                            return {ok:true,status:200,text:async()=>'<html><body><article class="sheet"><h1>Progress report</h1><p>'+actual+'</p></article></body></html>'};
                        }
                        window.saveRequests.push(fields);
                        const rows=window.narrativeState.accomplishmentRows.map((row,index)=>{
                            const key=fields.find(([name])=>name==='accomplishments['+index+'][evidence_key]')?.[1]||row.evidence_key;
                            const ids=fields.filter(([name])=>name==='accomplishments['+index+'][evidence_ids][]').map(([,value])=>value);
                            const evidence=(window.savedRows.find(saved=>saved.evidence_key===key)?.evidence||[]).filter(file=>ids.includes(file.id));
                            const uploads=fields.filter(([name,file])=>name==='accomplishment_evidence['+key+'][]'&&file instanceof File&&file.size);
                            for(const [,file] of uploads) evidence.push({id:'00000000-0000-4000-8000-'+String(window.saveRequests.length*10+evidence.length).padStart(12,'0'),name:file.name,path:'private/'+file.name,mime_type:file.type,size:file.size,checksum:'hash'});
                            return {evidence_key:key,evidence,objective:fields.find(([name])=>name==='accomplishments['+index+'][objective]')?.[1]||'',target:fields.find(([name])=>name==='accomplishments['+index+'][target]')?.[1]||'',actual:fields.find(([name])=>name==='accomplishments['+index+'][actual]')?.[1]||'',activities:row.activities||''};
                        });
                        if(window.gateNextSave) {window.gateNextSave=false;await new Promise(resolve=>window.releaseSave=resolve);}
                        window.savedRows=rows;
                        return {ok:true,status:200,json:async()=>({draft_version:window.saveRequests.length+1,accomplishments:rows})};
                    };
                    document.addEventListener('alpine:init',()=>{
                        ${datePicker}
                        const register=Alpine.data.bind(Alpine);
                        Alpine.data=(name,factory)=>register(name,(config)=>{
                            const value=factory(config);const initialize=value.init;
                            value.init=function(){window.narrativeState=this;window.savedRows=JSON.parse(JSON.stringify(this.accomplishmentRows));initialize.call(this)};
                            return value;
                        });
                        ${source}
                    });` });
                await page.addScriptTag({ path: resolve('node_modules/alpinejs/dist/cdn.min.js') });
                await page.waitForFunction(() => Boolean(window.narrativeState?.accomplishmentRows.length));
                const accomplishment = page.locator('[data-progress-accomplishment]').first();
                const panel = accomplishment.locator('[data-progress-evidence]');
                const upload = panel.locator('input[type="file"]');
                const actual = accomplishment.locator('textarea').nth(2);
                const originalActual = await actual.inputValue();
                assert.equal(await accomplishment.locator('.grid').evaluate(element => getComputedStyle(element).gridTemplateColumns.split(' ').length), width>=1280?4:1);
                assert.equal(await upload.getAttribute('required'), null, 'Evidence must stay optional');
                await panel.getByRole('button', { name: 'Remove interviews.png', exact: true }).click();
                assert.equal(await page.evaluate(() => window.narrativeState.finishNarrativeDraftAutoSave()), true);
                assert.equal(await actual.inputValue(), originalActual);
                await upload.setInputFiles({ name: 'proof.png', mimeType: 'image/png', buffer: image });
                assert.equal(await page.evaluate(() => window.narrativeState.finishNarrativeDraftAutoSave()), true);
                await panel.getByRole('link', { name: 'proof.png', exact: true }).waitFor();
                assert.equal(await upload.evaluate(field => field.files.length), 0);
                assert.equal(await page.evaluate(() => window.saveRequests.some(fields => fields.some(([name,file]) => name.startsWith('accomplishment_evidence[') && file instanceof File && file.size > 0))), true);
                const saves = await page.evaluate(() => window.saveRequests.length);
                await page.waitForTimeout(1400);
                assert.equal(await page.evaluate(() => window.saveRequests.length), saves, 'Acknowledged evidence must not upload repeatedly');
                await page.evaluate(() => {
                    window.narrativeState.accomplishmentRows = window.savedRows.map((row,index) => ({ ...row,id:index,pendingEvidenceFiles:[],evidenceError:'' }));
                });
                await panel.getByRole('link', { name: 'proof.png', exact: true }).waitFor();
                await page.evaluate(() => { window.gateNextSave=true; });
                await upload.setInputFiles({ name: 'first-save.png', mimeType: 'image/png', buffer: image });
                await page.evaluate(() => { void window.narrativeState.saveNarrativeDraft(); });
                await page.waitForFunction(() => typeof window.releaseSave === 'function');
                await panel.getByRole('button', { name: 'Remove proof.png', exact: true }).click();
                await upload.setInputFiles({ name: 'later-selection.png', mimeType: 'image/png', buffer: image });
                await page.evaluate(() => window.releaseSave());
                assert.equal(await page.evaluate(() => window.narrativeState.finishNarrativeDraftAutoSave()), true);
                assert.equal(await panel.getByRole('link', { name: 'proof.png', exact: true }).count(), 0);
                await panel.getByRole('link', { name: 'first-save.png', exact: true }).waitFor();
                await panel.getByRole('link', { name: 'later-selection.png', exact: true }).waitFor();
                assert.equal(await upload.evaluate(field => field.files.length), 0);
                await actual.fill('Updated accomplishments for this period.');
                assert.equal(await page.evaluate(() => window.narrativeState.finishNarrativeDraftAutoSave()), true);
                await page.evaluate(() => {
                    const form=window.narrativeState.$refs.form;
                    for(const picker of form.querySelectorAll('[x-data^="datePicker("]')) {
                        const value=Alpine.$data(picker);
                        if(value.required&&!value.value) value.value=value.min>'2026-03-31'?value.min:value.max<'2026-03-31'?value.max:'2026-03-31';
                    }
                    for(const field of form.querySelectorAll('[required]')) {
                        if(field.value||field.type==='file'||field.type==='hidden') continue;
                        field.value=field.tagName==='SELECT'?[...field.options].find(option=>option.value)?.value||'':field.type==='number'?String(Math.max(Number(field.min||0),1)):'Recorded results';
                        field.dispatchEvent(new Event('input',{bubbles:true}));
                        field.dispatchEvent(new Event('change',{bubbles:true}));
                    }
                });
                const invalid = await page.evaluate(() => [...window.narrativeState.$refs.form.elements].filter(field => field.willValidate && !field.validity.valid).map(field => [field.name,field.validationMessage]));
                assert.deepEqual(invalid, []);
                await page.getByRole('button', { name: 'Add figure', exact: true }).click();
                const figure = page.locator('[data-progress-report-figures]');
                await figure.locator('input[type="file"]').setInputFiles({ name: 'report-figure.png', mimeType: 'image/png', buffer: image });
                await figure.locator('input[type="text"]').fill('Results diagram');
                await page.locator('[data-proposal-workspace-toolbar] [data-proposal-preview-toggle]').click();
                await page.waitForFunction(() => window.narrativeState.previewReady && !window.narrativeState.previewLoading);
                assert.equal(await page.evaluate(() => window.previewRequests.every(fields => fields.every(([name]) => !name.startsWith('accomplishment_evidence[') && !/\[evidence(?:_key|_ids)?\]/.test(name)))), true);
                assert.equal(await page.evaluate(() => window.previewRequests.at(-1).some(([name,file]) => name==='figures[0][image]' && file instanceof File && file.size>0)), true, 'Report figures must remain available to the paper preview');
                const frame = page.locator('.proposal-preview-dock').frameLocator('iframe');
                await frame.getByText('Updated accomplishments for this period.', { exact: true }).waitFor();
                assert.equal(await frame.getByText(/proof\.png|first-save\.png|later-selection\.png/).count(), 0);
                if(width<1024) await frame.locator('body').press('Escape');
                await accomplishment.scrollIntoViewIfNeeded();
                assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
                const columns = await accomplishment.locator('.grid').evaluate(element => getComputedStyle(element).gridTemplateColumns.split(' ').length);
                assert.equal(columns, width>=1024?2:1, 'Accomplishment fields must fit beside the desktop paper preview');
                await page.evaluate(() => window.narrativeState.closeProposalPreview());
                await page.waitForFunction(() => !window.narrativeState.previewPaneOpen);
                await accomplishment.screenshot({
                    path: resolve(`../tmp/progress-evidence-${width}-${dark?'dark':'light'}.png`),
                    style: '[data-proposal-workspace-toolbar] { visibility: hidden !important; }',
                });
                await upload.setInputFiles({ name: 'final-proof.png', mimeType: 'image/png', buffer: image });
                await page.evaluate(() => {
                    window.narrativeState.submissionOpen=true;
                    HTMLFormElement.prototype.submit=function(){window.preparedFields=[...new FormData(this).entries()].map(([name,value])=>[name,value instanceof File?value.name:value]);};
                });
                await page.locator('[data-proposal-workspace-toolbar] button[type="submit"]').click();
                await page.waitForFunction(() => Boolean(window.preparedFields));
                assert.equal(await page.evaluate(() => window.preparedFields.filter(([name]) => name==='accomplishments[0][evidence_ids][]').length), 3, 'Preparing the PDF must wait for evidence to finish saving');
                assert.equal(await page.evaluate(() => window.preparedFields.find(([name]) => name.startsWith('accomplishment_evidence['))[1]), '', 'Preparing the PDF must not upload evidence a second time');
                assert.equal(await page.evaluate(() => window.preparedFields.find(([name]) => name==='figures[0][image]')[1]), 'report-figure.png');
                assert.deepEqual(errors, []);
                await page.close();
            }
        }
    } finally {
        await browser.close();
    }
});
