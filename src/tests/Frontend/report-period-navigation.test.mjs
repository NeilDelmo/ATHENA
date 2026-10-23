import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { chromium, expect } from '@playwright/test';

const read = path => readFileSync(resolve(path), 'utf8');
const app = read('resources/js/app.js');
const controllers = app.slice(app.indexOf('const documentPreviewForm ='), app.indexOf("Alpine.data('noticeToProceedForm',"));
const dates = ['2026-03-31', '2026-06-30'];

async function editor(browser, kind, { fail = false, attachment = false } = {}) {
    const state = { posts: [], saved: { [dates[0]]: 'First quarter saved text', [dates[1]]: 'Second quarter saved text' } };
    const context = await browser.newContext();
    const page = await context.newPage();
    page.setDefaultTimeout(4000);
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    const view = read(`resources/views/components/${kind === 'monitoring' ? 'monitoring-tool' : 'progress-report'}-form.blade.php`);
    const select = view.match(new RegExp(`<select[^>]*id="${kind === 'monitoring' ? 'monitoring' : 'progress'}-reporting-date"[^]*?</select>`))[0];
    const change = select.match(/@change="([^"]+)"/)[1];
    const input = select.includes('@input.stop') ? '@input.stop' : '';
    const factory = kind === 'monitoring' ? 'monitoringToolForm' : 'narrativeProgressReportForm';
    await context.route('https://athena.test/**', async route => {
        const request = route.request();
        if (request.method() === 'POST') {
            const body = request.postData() || '';
            const value = name => body.match(new RegExp(`name="${name}"\\r\\n\\r\\n([^]*?)\\r\\n--`))?.[1];
            const post = { date: value('reporting_date'), text: value('introduction') };
            state.posts.push(post);
            if (fail) return route.fulfill({ status: 422, json: { errors: { introduction: ['Please review this report.'] } } });
            state.saved[post.date] = post.text;
            return route.fulfill({ json: { draft_version: 5, work_plan: [], accomplishments: [], images: {} } });
        }
        const date = new URL(request.url()).searchParams.get('reporting_date') || dates[0];
        return route.fulfill({ contentType: 'text/html', body: `<section data-monitoring-paper-workspace x-data="${factory}({draftSaveUrl:'/draft',initialReportingDate:'${date}',initialPeriodKey:'2026-1'})">
            <p data-proposal-autosave-status><span data-proposal-autosave-indicator></span><span data-proposal-autosave-message>Draft saved</span></p>
            <form x-ref="form"><input type="hidden" name="draft_version" value="4">
                <label for="quarter">Reporting quarter</label><select id="quarter" name="reporting_date" ${input} @change="${change}">
                    ${dates.map((value, index) => `<option value="${value}" data-plan-key="2026-${index + 1}" data-submission-open="true" data-editor-url="/editor?reporting_date=${value}" ${value === date ? 'selected' : ''}>Q${index + 1}</option>`).join('')}
                </select>
                <label for="introduction">Introduction</label><textarea id="introduction" name="introduction">${state.saved[date]}</textarea>
                ${attachment ? '<label>Supporting attachment<input type="file" name="attachment"></label>' : ''}
            </form>
        </section>` });
    });
    const initialize = async () => {
        await page.addScriptTag({ content: `window.proposalPaperPreviewWorkspace=()=>({initializeProposalPaperPreview(){},markProposalPreviewStale(){},clearPreviewObjectUrls(){},destroyProposalPaperPreview(){}});
            window.uploadRequest=(url,options)=>fetch(url,options);window.autoSaveHasStaleVersionError=payload=>Boolean(payload?.errors?.draft_version);
            window.showProposalConfirmation=async()=>false;
            document.addEventListener('alpine:init',()=>{${controllers}});` });
        await page.addScriptTag({ path: resolve('node_modules/alpinejs/dist/cdn.min.js') });
        await page.waitForFunction(() => Boolean(document.querySelector('[x-data]')._x_dataStack));
    };
    await page.goto(`https://athena.test/editor?reporting_date=${dates[0]}`);
    await initialize();
    return { context, page, state, errors, initialize };
}

for (const kind of ['monitoring', 'progress']) {
    test(`${kind} saves the original quarter before loading the selected quarter's saved report`, { timeout: 15000 }, async () => {
        const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
        try {
            const { context, page, state, errors, initialize } = await editor(browser, kind);
            await page.getByLabel('Introduction', { exact: true }).fill('Unfinished first quarter work');
            await page.getByLabel('Reporting quarter', { exact: true }).selectOption(dates[1]);
            await expect(page).toHaveURL(new RegExp(`reporting_date=${dates[1]}`), { timeout: 3000 });
            assert.deepEqual(state.posts, [{ date: dates[0], text: 'Unfinished first quarter work' }]);
            await initialize();
            await expect(page.getByLabel('Introduction', { exact: true })).toHaveValue('Second quarter saved text');
            assert.equal(state.saved[dates[0]], 'Unfinished first quarter work');
            assert.deepEqual(errors, []);
            await context.close();
        } finally { await browser.close(); }
    });

    test(`${kind} keeps the original quarter and edits when draft validation fails`, { timeout: 10000 }, async () => {
        const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
        try {
            const { context, page, state, errors } = await editor(browser, kind, { fail: true });
            await page.getByLabel('Introduction', { exact: true }).fill('Keep unfinished work');
            await page.getByLabel('Reporting quarter', { exact: true }).selectOption(dates[1]);
            await expect.poll(() => state.posts.length, { timeout: 2500 }).toBe(1);
            await expect(page.getByLabel('Reporting quarter', { exact: true })).toHaveValue(dates[0]);
            await expect(page.getByLabel('Introduction', { exact: true })).toHaveValue('Keep unfinished work');
            await expect(page.locator('[data-proposal-autosave-message]')).toContainText('Please review this report.');
            await expect.poll(() => page.evaluate(() => Alpine.$data(document.querySelector('[x-data]')).switchingPeriod), { timeout: 1500 }).toBe(false);
            assert.equal(new URL(page.url()).searchParams.get('reporting_date'), dates[0]);
            assert.equal(state.saved[dates[0]], 'First quarter saved text');
            assert.deepEqual(errors, []);
            await context.close();
        } finally { await browser.close(); }
    });
}

test('changing a monitoring quarter keeps an unsaved supporting attachment unless leaving is confirmed', { timeout: 10000 }, async () => {
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        const { context, page, state } = await editor(browser, 'monitoring', { attachment: true });
        await page.getByLabel('Supporting attachment', { exact: true }).setInputFiles({ name: 'private-proof.pdf', mimeType: 'application/pdf', buffer: Buffer.from('proof') });
        await page.getByLabel('Reporting quarter', { exact: true }).selectOption(dates[1]);
        await expect(page.getByLabel('Reporting quarter', { exact: true })).toHaveValue(dates[0]);
        assert.equal(await page.getByLabel('Supporting attachment', { exact: true }).evaluate(field => field.files[0]?.name), 'private-proof.pdf');
        assert.equal(new URL(page.url()).searchParams.get('reporting_date'), dates[0]);
        assert.equal(state.posts.length, 0);
        await page.evaluate(() => { window.showProposalConfirmation = async () => true; });
        await page.getByLabel('Reporting quarter', { exact: true }).selectOption(dates[1]);
        await expect(page).toHaveURL(new RegExp(`reporting_date=${dates[1]}`));
        await context.close();
    } finally { await browser.close(); }
});
