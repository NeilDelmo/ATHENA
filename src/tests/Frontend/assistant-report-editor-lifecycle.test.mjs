import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { chromium } from '@playwright/test';
import { assistantReportEditor } from '../../resources/js/assistant-report-editor.js';

const app = readFileSync(resolve('resources/js/app.js'), 'utf8');
const controllers = app.slice(app.indexOf("Alpine.data('monitoringToolForm',"), app.indexOf("Alpine.data('noticeToProceedForm',"));
const semanticEditor = app.slice(app.indexOf('function sanitizedSemanticHtml('), app.indexOf('window.insertProposalCitationMarker ='));
const semanticHelpers = readFileSync(resolve('resources/js/proposal-semantic-editor.js'), 'utf8').replace(/^export /gm, '');
const reportEditor = ['research-assistant-markdown', 'proposal-paper-autosave', 'research-assistant-report-changes', 'assistant-report-editor']
    .map(name => readFileSync(resolve(`resources/js/${name}.js`), 'utf8').replace(/^import .+;\r?\n/gm, '').replace(/^export /gm, '')).join('\n');

async function fixture(browser, type) {
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    const monitoring = type === 'monitoring-tool';
    const manual = type === 'comment-response';
    const rich = type === 'terminal-report';
    const context = { report_type: type, topic_id: 42, ...(monitoring ? { source_key: 'new' } : {}) };
    const key = `${type}:topic:42${monitoring ? ':new' : ''}`;
    const field = monitoring ? 'work_plan.0.findings' : manual ? 'feedback_responses.committee_0.response'
        : rich ? 'terminal_data.literature_review' : 'results_discussion';
    const citation = '<span data-proposal-citation="17" data-proposal-locator="p. 4" contenteditable="false"> [1, p. 4]</span>';
    const reference = '<p data-proposal-managed-reference="17">[1] Saved study reference.</p>';
    const before = rich ? `<p>Recorded findings agree with the cited study${citation}.</p>${reference}` : manual ? '' : 'Saved report text.';
    const name = field.split('.').map((part, index) => index ? `[${part}]` : part).join('');
    const value = rich ? `<p>The cited study${citation} supports the recorded findings.</p>${reference}` : manual
        ? 'The requested clarification is included in the revised methodology.' : 'Clearer saved report text.';
    const root = manual ? '' : `data-${monitoring ? 'monitoring-tool' : 'narrative-progress'}-autosave="true" x-data="${monitoring ? 'monitoringToolForm' : 'narrativeProgressReportForm'}({draftSaveUrl:'/save',csrfToken:'test',${monitoring ? `entries:[{source_work_plan_index:0,percent_weight:50,findings:'Saved report text.',actual_accomplishment:'Keep evidence statement.',evidence:[]}]` : ''}})"`;
    await page.setContent(`<main ${root}><form x-ref="form" ${manual ? '' : `data-${monitoring ? 'monitoring-tool' : 'narrative-progress'}-autosave-form`} data-assistant-report-type="${type}" data-assistant-report-key="${key}">
        ${manual ? '<input type="hidden" name="feedback_review_id" value="7">' : '<input type="hidden" name="draft_version" value="3">'}
        <input name="tracking_number" value="Keep project details">
        <label for="report-text">Report text</label><textarea id="report-text" name="${name}" ${rich ? 'data-semantic-editor' : ''} ${monitoring ? 'x-model="entries[0].findings"' : ''}>${before}</textarea>
        ${monitoring ? '<input type="hidden" name="work_plan[0][completed_units]" value="0">' : ''}
        <div data-assistant-report-editor x-data='assistantReportEditor(${JSON.stringify({ context })})'
            @athena-prepare-report.window="onPrepare($event)" @athena-apply-report-change.window="onApply($event)"></div>
    </form></main>`);
    await page.addScriptTag({ content: `${reportEditor}\n${semanticHelpers}\n${semanticEditor}
        function escapeHtml(value) { return String(value).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c])); }
        const documentPreviewForm = () => ({submitting:false,initializeProposalPaperPreview(){},markProposalPreviewStale(){},clearPreviewObjectUrls(){},destroyProposalPaperPreview(){}});
        const uploadRequest = (url, options) => window.fetch(url, options);
        window.savedForms=[];window.failSave=false;window.submissions=0;
        document.querySelector('form').addEventListener('submit', event=>{event.preventDefault();window.submissions++;});
        window.fetch=async(url,options)=>{window.savedForms.push(Object.fromEntries(options.body.entries()));
            return window.failSave ? {ok:false,status:500,json:async()=>({message:'Save unavailable.'})}
                : {ok:true,status:200,json:async()=>({draft_version:Number(options.body.get('draft_version'))+1,work_plan:[],accomplishments:[]})};};
        document.addEventListener('alpine:init',()=>{
            ${controllers}
            Alpine.data('assistantReportEditor', config=>assistantReportEditor(config,{sanitizeHtml:sanitizedSemanticHtml}));
        });` });
    await page.addScriptTag({ path: resolve('node_modules/alpinejs/dist/cdn.js') });
    await page.evaluate(async () => { await Alpine.nextTick(); initializeSemanticEditors(); await Alpine.nextTick(); });
    return { page, errors, field, before, value, key, type,
        change: { report_key: key, report_type: type, field, before, value, format: rich ? 'html' : 'plain', draft_version: 3 } };
}

for (const type of ['monitoring-tool', 'progress-report', 'terminal-report']) {
    test(`${type} accepted text synchronizes the real editor and existing draft save`, async () => {
        const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
        try {
            const item = await fixture(browser, type);
            const result = await item.page.evaluate(async change => {
                const detail = { ...change, handled: false, promise: null };
                window.dispatchEvent(new CustomEvent('athena-apply-report-change', { detail }));
                if (!detail.handled) throw new Error('The real report did not receive its accepted change.');
                const accepted = await detail.promise;
                await Alpine.nextTick();
                const field = document.getElementById('report-text');
                return { accepted, value: field.value, rendered: field._semanticEditor?.innerHTML,
                    model: Alpine.$data(document.querySelector('main')).entries?.[0]?.findings,
                    saved: window.savedForms.at(-1), submissions: window.submissions,
                    version: document.querySelector('[name="draft_version"]').value };
            }, item.change);
            assert.equal(result.accepted.applied, true);
            assert.equal(result.accepted.saved, true);
            assert.equal(result.value, item.value);
            if (type === 'monitoring-tool') assert.equal(result.model, item.value);
            if (type === 'terminal-report') assert.equal(result.rendered, item.value);
            const name = item.field.split('.').map((part, index) => index ? `[${part}]` : part).join('');
            assert.equal(result.saved[name], item.value);
            assert.equal(result.saved.tracking_number, 'Keep project details');
            assert.equal(result.version, '4');
            assert.equal(result.submissions, 0);
            assert.deepEqual(item.errors, []);
        } finally { await browser.close(); }
    });
}

test('comment responses stay staged in their form and never submit the revision automatically', async () => {
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        const item = await fixture(browser, 'comment-response');
        const result = await item.page.evaluate(async change => {
            const state = Alpine.$data(document.querySelector('[data-assistant-report-editor]'));
            const context = await state.prepare();
            const accepted = await state.applyChange(change);
            const navigation = { detail: { allowed: true } };
            state.onNavigate(navigation);
            return { context, accepted, value: document.getElementById('report-text').value,
                saves: window.savedForms.length, submissions: window.submissions, canNavigate: navigation.detail.allowed };
        }, item.change);
        assert.deepEqual(result.context.response_values, { committee_0: '' });
        assert.equal(result.accepted.applied, true);
        assert.equal(result.accepted.saved, false);
        assert.match(result.accepted.notice, /Review and submit/);
        assert.equal(result.value, item.value);
        assert.equal(result.saves, 0);
        assert.equal(result.submissions, 0);
        assert.equal(result.canNavigate, false);
        assert.deepEqual(item.errors, []);
    } finally { await browser.close(); }
});

test('quarterly reports with restored validation input must be saved before checking or revising', async () => {
    for (const type of ['college-quarterly', 'rms-quarterly']) {
        const state = assistantReportEditor({ context: { report_type: type, report_id: 42 }, unsaved: true });
        state.form = () => ({ isConnected: true, closest: () => null });
        state.autoSave = () => null;
        await assert.rejects(state.prepare(), /validation errors and save your report/);
        await assert.rejects(state.prepare(true), /validation errors and save your report/);
    }
});

for (const type of ['college-quarterly', 'rms-quarterly']) {
    test(`${type} stages accepted text in the real manual form without submitting it`, async () => {
        const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
        try {
            const page = await browser.newPage();
            const errors = [];
            page.on('pageerror', error => errors.push(error.message));
            const college = type === 'college-quarterly';
            const context = { report_type: type, report_id: 42 };
            const key = `${type}:42`;
            const field = college ? `saved_evidence_captions.${'a'.repeat(64)}` : 'row_details.ip-s7-asset1.remarks';
            const name = field.split('.').map((part, index) => index ? `[${part}]` : part).join('');
            const before = 'Application documents await review.';
            const value = 'Application documents are awaiting review.';
            const rmsModule = readFileSync(resolve('resources/js/rms-quarterly-report-form.js'), 'utf8').replace(/^export /gm, '');
            await page.setContent(`<form x-data="${college ? '{caption: \'' + before + '\'}' : 'rmsQuarterlyReportForm()'}" ${college ? '' : '@formdata="serialize($event)"'}>
                <input name="reporting_year" value="2026"><textarea id="manual-text" name="${name}" ${college ? 'x-model="caption"' : ''}>${before}</textarea>
                <div data-assistant-report-editor x-data='assistantReportEditor(${JSON.stringify({ context })})'
                    @athena-report-navigation.window="onNavigate($event)"></div></form>`);
            await page.addScriptTag({ content: `${reportEditor}\n${rmsModule}
                window.submissions=0; document.querySelector('form').addEventListener('submit',event=>{event.preventDefault();window.submissions++;});
                document.addEventListener('alpine:init',()=>{Alpine.data('assistantReportEditor',assistantReportEditor);Alpine.data('rmsQuarterlyReportForm',rmsQuarterlyReportForm);});` });
            await page.addScriptTag({ path: resolve('node_modules/alpinejs/dist/cdn.js') });
            await page.evaluate(() => Alpine.nextTick());
            const result = await page.evaluate(async change => {
                const editor = Alpine.$data(document.querySelector('[data-assistant-report-editor]'));
                const accepted = await editor.applyChange(change);
                const navigation = { allowed: true };
                window.dispatchEvent(new CustomEvent('athena-report-navigation', { detail: navigation }));
                let nextError = '';
                try { await editor.prepare(); } catch (error) { nextError = error.message; }
                return { accepted, value: document.getElementById('manual-text').value,
                    caption: Alpine.$data(document.querySelector('form')).caption,
                    entries: Object.fromEntries(new FormData(document.querySelector('form'))),
                    submissions: window.submissions, allowed: navigation.allowed, nextError };
            }, { report_key: key, report_type: type, field, before, value, format: 'plain', draft_version: null });
            assert.equal(result.accepted.applied, true);
            assert.equal(result.accepted.saved, false);
            assert.match(result.accepted.notice, /Save draft/);
            assert.equal(result.value, value);
            if (college) { assert.equal(result.caption, value); assert.equal(result.entries[name], value); }
            else assert.equal(JSON.parse(result.entries.row_details_json)['ip-s7-asset1'].remarks, value);
            assert.equal(result.submissions, 0);
            assert.equal(result.allowed, false);
            assert.match(result.nextError, /Save your current report edits/);
            assert.deepEqual(errors, []);
        } finally { await browser.close(); }
    });
}

test('terminal report revisions preserve the source identity and locator of existing citations', async () => {
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        const item = await fixture(browser, 'terminal-report');
        for (const value of [
            '<p>The citation was removed from this wording.</p>',
            item.value.replace('data-proposal-citation="17"', 'data-proposal-citation="99"'),
            item.value.replace('data-proposal-locator="p. 4"', 'data-proposal-locator="p. 5"'),
            item.value.replace('[1, p. 4]', '[1, p. 9]'),
            item.value.replace('[1] Saved study reference.', '[1] An altered study reference.'),
            item.value.replace('data-proposal-managed-reference="17"', 'data-proposal-managed-reference="99"'),
            item.value + '<p data-proposal-managed-reference="17">[1] Saved study reference.</p>',
        ]) {
            const result = await item.page.evaluate(async change => {
                try { await Alpine.$data(document.querySelector('[data-assistant-report-editor]')).applyChange(change); }
                catch (error) { return { message: error.message, value: document.getElementById('report-text').value, saves: window.savedForms.length }; }
            }, { ...item.change, value });
            assert.match(result.message, /citation/i);
            assert.equal(result.value, item.before);
            assert.equal(result.saves, 0);
        }
        assert.deepEqual(item.errors, []);
    } finally { await browser.close(); }
});

test('equivalent sanitized marker markup remains applicable without weakening source protection', async () => {
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        const item = await fixture(browser, 'terminal-report');
        const result = await item.page.evaluate(async change => {
            const control = document.getElementById('report-text');
            control.value = control.value.replace(' contenteditable="false"', '');
            const report = Alpine.$data(document.querySelector('main'));
            report.lastSavedNarrativeDraft = report.narrativeDraftFingerprint(control.form);
            const accepted = await Alpine.$data(document.querySelector('[data-assistant-report-editor]')).applyChange(change);
            return { accepted, marker: control._semanticEditor.querySelector('[data-proposal-citation]').outerHTML };
        }, item.change);
        assert.equal(result.accepted.saved, true);
        assert.match(result.marker, /data-proposal-citation="17"/);
        assert.match(result.marker, /data-proposal-locator="p\. 4"/);
        assert.match(result.marker, /\[1, p\. 4\]/);
        assert.deepEqual(item.errors, []);
    } finally { await browser.close(); }
});

test('stale versions and changed baselines preserve the real report text', async () => {
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        const item = await fixture(browser, 'progress-report');
        for (const changes of [{ draft_version: 2 }, { before: 'An older saved value.' }]) {
            const result = await item.page.evaluate(async change => {
                try { await Alpine.$data(document.querySelector('[data-assistant-report-editor]')).applyChange(change); }
                catch (error) { return { message: error.message, value: document.getElementById('report-text').value, saves: window.savedForms.length }; }
            }, { ...item.change, ...changes });
            assert.match(result.message, /changed/);
            assert.equal(result.value, item.before);
            assert.equal(result.saves, 0);
        }
        assert.deepEqual(item.errors, []);
    } finally { await browser.close(); }
});

test('a failed draft save keeps accepted text visible and reports that it is unsaved', async () => {
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        const item = await fixture(browser, 'progress-report');
        const result = await item.page.evaluate(async change => {
            window.failSave = true;
            const accepted = await Alpine.$data(document.querySelector('[data-assistant-report-editor]')).applyChange(change);
            return { accepted, value: document.getElementById('report-text').value, submissions: window.submissions };
        }, item.change);
        assert.equal(result.accepted.applied, true);
        assert.equal(result.accepted.saved, false);
        assert.match(result.accepted.notice, /Saving failed/);
        assert.equal(result.value, item.value);
        assert.equal(result.submissions, 0);
        assert.deepEqual(item.errors, []);
    } finally { await browser.close(); }
});
