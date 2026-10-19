import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { chromium, expect } from '@playwright/test';
import { freshProposalRecoveryVersion } from '../../resources/js/proposal-conflict-recovery.js';

const recoverySource = readFileSync(resolve('resources/js/proposal-conflict-recovery.js'), 'utf8');
const uploadSource = readFileSync(resolve('resources/js/file-upload.js'), 'utf8');
const appSource = readFileSync(resolve('resources/js/app.js'), 'utf8');
const editors = appSource.slice(appSource.indexOf("Alpine.data('monitoringToolForm'"), appSource.indexOf("Alpine.data('noticeToProceedForm'"));
const keys = () => Object.keys(localStorage).filter(key => key.startsWith('athena-proposal-recovery:'));
const launch = () => chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });

test('report version checks reject locks and scope changes even when the number stays the same', async () => {
    const request = state => async () => ({ ok: true, json: async () => state });
    const state = { version: 0, scope: 'original-report', is_editable: true };
    assert.equal((await freshProposalRecoveryVersion('/state', 0, request(state), 'original-report')).current, true);
    for (const changed of [{ ...state, is_editable: false }, { ...state, scope: 'new-correction' }, { version: 0 }]) {
        assert.equal((await freshProposalRecoveryVersion('/state', 0, request(changed), 'original-report')).current, false);
    }
});

async function fixture(browser, kind, state = {}, storageState) {
    state = Object.assign({ version: 0, scope: `${kind}-cycle`, editable: true, authorized: true, user: '1', text: 'Saved server text', requests: [], posts: [] }, state);
    const context = await browser.newContext(storageState ? { storageState } : {});
    const isMonitoring = kind === 'monitoring';
    const formAttribute = isMonitoring ? 'data-monitoring-tool-autosave-form' : 'data-narrative-progress-autosave-form';
    const rootAttribute = isMonitoring ? 'data-monitoring-tool-autosave' : 'data-narrative-progress-autosave';
    const factory = isMonitoring ? 'monitoringToolForm' : 'narrativeProgressReportForm';
    const saveMethod = isMonitoring ? 'saveMonitoringDraft' : 'saveNarrativeDraft';
    const fieldValue = (body, name) => body.match(new RegExp(`name="${name}"\\r\\n\\r\\n([^]*?)\\r\\n--`))?.[1];
    await context.route('https://athena.test/**', route => {
        const request = route.request();
        const path = new URL(request.url()).pathname;
        if (path === '/recovery.js') return route.fulfill({ contentType: 'text/javascript', body: recoverySource });
        if (path === '/file-upload.js') return route.fulfill({ contentType: 'text/javascript', body: uploadSource });
        if (path.endsWith('/edit-state')) {
            state.requests.push(path);
            return route.fulfill({ status: state.authorized ? 200 : 403, json: { version: state.version, scope: state.scope, is_editable: state.editable } });
        }
        if (path.endsWith('/draft') && request.method() === 'POST') {
            const body = request.postData() || '';
            state.posts.push(body);
            if (!state.authorized) return route.fulfill({ status: 403, json: { message: 'Unauthorized' } });
            if (!state.editable || Number(fieldValue(body, 'draft_version')) !== state.version) {
                return route.fulfill({ status: 422, json: { errors: { draft_version: ['The saved report changed or is no longer editable.'] } } });
            }
            state.version++;
            state.text = fieldValue(body, 'introduction') || state.text;
            return route.fulfill({ json: { draft_version: state.version, work_plan: [], accomplishments: [], images: {} } });
        }
        const date = new URL(request.url()).searchParams.get('date') || '2026-03-31';
        return route.fulfill({ contentType: 'text/html', body: `<body data-auth-user-id="${state.user}">
            <section data-monitoring-paper-workspace ${rootAttribute}="true" data-paper-dirty="false" x-data="${factory}({draftSaveUrl:'/research/4/${kind}/draft',csrfToken:'CSRF_SECRET'})">
                <p data-proposal-autosave-status><span data-proposal-autosave-indicator></span><span data-proposal-autosave-message>Saved to ATHENA</span></p>
                <section data-report-version-monitor data-loaded-version="${state.version}" data-state-scope="${state.scope}" data-state-url="/research/4/report-drafts/${kind}/${kind === 'terminal' ? 'terminal' : date}/0/edit-state">
                    <div data-proposal-stale-warning hidden><p data-proposal-stale-message></p><a data-proposal-load-latest href="/editor">Load latest saved work</a></div>
                    <div data-proposal-conflict-recovery hidden></div>
                </section>
                <form x-ref="form" ${formAttribute} method="POST" action="/prepare">
                    <input type="hidden" name="draft_version" value="${state.version}"><input type="hidden" name="recovery_scope" value="${state.scope}">
                    <input type="hidden" name="_token" value="CSRF_SECRET"><input type="hidden" name="report_type" value="${kind === 'monitoring' ? 'progress' : kind}">
                    ${kind === 'terminal' ? '' : `<label>Quarter<select name="reporting_date"><option value="2026-03-31" ${date === '2026-03-31' ? 'selected' : ''}>Q1</option><option value="2026-06-30" ${date === '2026-06-30' ? 'selected' : ''}>Q2</option></select></label>`}
                    <label for="report-introduction">Introduction</label><textarea id="report-introduction" name="introduction">${state.text}</textarea>
                    <label for="report-findings">Findings</label><input id="report-findings" name="findings" value="Server finding">
                    <label><input type="checkbox" name="choice[]" value="A">Choice A</label><label><input type="checkbox" name="choice[]" value="B">Choice B</label>
                    <label>Approved objective<input name="objective" readonly value="APPROVED_SECRET"></label>
                    <label>Evidence<input type="file" name="attachment"></label><button type="submit">Prepare official PDF</button>
                </form><button type="button" @click="${saveMethod}()">Save draft</button>
            </section><form action="/logout" method="post"><button>Log out</button></form></body>` });
    });
    async function open(date) {
        const page = await context.newPage();
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.goto(`https://athena.test/editor${date ? `?date=${date}` : ''}`);
        await page.addScriptTag({ content: `window.documentPreviewForm=()=>({initializeProposalPaperPreview(){},markProposalPreviewStale(){},clearPreviewObjectUrls(){},destroyProposalPaperPreview(){}});
            window.autoSaveHasStaleVersionError=payload=>Boolean(payload?.errors?.draft_version);
            document.addEventListener('alpine:init',()=>{${editors}});
            window.submissions=0;document.addEventListener('submit',event=>{if(!event.target.action.endsWith('/logout')){event.preventDefault();window.submissions++;}});` });
        await page.addScriptTag({ path: resolve('node_modules/alpinejs/dist/cdn.min.js') });
        await page.waitForFunction(() => Boolean(document.querySelector('[x-data]')._x_dataStack));
        await page.addScriptTag({ type: 'module', content: `import init from '/recovery.js';
            import { uploadRequest } from '/file-upload.js'; window.uploadRequest=uploadRequest;
            const root=document.querySelector('[data-monitoring-paper-workspace]');window.editorState=Alpine.$data(root);
            window.guard=()=>root._proposalRecovery.beforeSave();window.failedSave=()=>root._proposalRecovery.connectionFailed();
            window.dispose=init({resumeSave:root=>Alpine.$data(root).${saveMethod}()});window.recoveryLoaded=true;` });
        await page.waitForFunction(() => window.recoveryLoaded);
        return { page, errors };
    }
    return { context, state, open };
}

async function offline(page) {
    await page.evaluate(() => { Object.defineProperty(navigator, 'onLine', { configurable: true, get: () => false }); window.dispatchEvent(new Event('offline')); });
}

async function reconnect(page) {
    await page.evaluate(() => { Object.defineProperty(navigator, 'onLine', { configurable: true, get: () => true }); window.dispatchEvent(new Event('online')); });
}

for (const kind of ['monitoring', 'progress', 'terminal']) {
    test(`${kind} recovery survives browser restart and applies only selected text and choices`, { timeout: 60000 }, async () => {
        let browser = await launch();
        try {
            let { context, state, open } = await fixture(browser, kind);
            let { page } = await open();
            await offline(page);
            await page.getByLabel('Introduction', { exact: true }).fill('Unfinished report narrative');
            await page.getByLabel('Findings', { exact: true }).fill('Keep this recovery finding');
            await page.getByLabel('Choice B', { exact: true }).check();
            await page.getByLabel('Evidence', { exact: true }).setInputFiles({ name: 'private-proof.pdf', mimeType: 'application/pdf', buffer: Buffer.from('FILE_SECRET') });
            await expect(page.locator('[data-proposal-conflict-recovery]')).toContainText('saved locally in this browser');
            const saved = await context.storageState();
            const serialized = JSON.stringify(saved);
            for (const secret of ['CSRF_SECRET', 'APPROVED_SECRET', 'FILE_SECRET', 'private-proof.pdf']) assert.ok(!serialized.includes(secret), secret);
            await browser.close();
            browser = await launch();
            ({ context, open } = await fixture(browser, kind, state, saved));
            ({ page } = await open());
            const panel = page.locator('[data-proposal-conflict-recovery]');
            await expect(panel.getByRole('button', { name: 'Apply selected changes' })).toBeVisible();
            await expect(page.getByRole('textbox', { name: 'Introduction', exact: true })).toHaveValue('Saved server text');
            await expect(panel).toContainText('Select image and attachment files again');
            await panel.getByRole('checkbox', { name: 'Introduction', exact: true }).check();
            await panel.getByRole('checkbox', { name: 'Choice A', exact: true }).check();
            const before = state.requests.length;
            await panel.getByRole('button', { name: 'Apply selected changes' }).click();
            await expect(page.getByRole('textbox', { name: 'Introduction', exact: true })).toHaveValue('Unfinished report narrative');
            await expect(page.getByRole('textbox', { name: 'Findings', exact: true })).toHaveValue('Server finding');
            await expect(page.locator('form [value="B"]')).toBeChecked();
            assert.ok(state.requests.length > before, 'Applying a selection checks the authorized server state again');
            await page.getByRole('button', { name: 'Save draft', exact: true }).click();
            await expect.poll(() => state.posts.length).toBe(1);
            await expect.poll(() => page.locator('[name="draft_version"]').inputValue()).toBe('1');
            assert.equal(await page.evaluate(() => window.submissions), 0);
            const remaining = await page.evaluate(() => JSON.stringify(Object.entries(localStorage)));
            assert.ok(!remaining.includes('Unfinished report narrative'), 'Acknowledged restored text is cleared from recovery');
            assert.ok(remaining.includes('Keep this recovery finding'), 'Unselected recovery fields remain available');
            await context.close();
        } finally { await browser.close(); }
    });

    test(`${kind} reconnect checks authorization and scope before retrying drafts and blocks locked reports`, { timeout: 60000 }, async () => {
        const browser = await launch();
        try {
            const { context, state, open } = await fixture(browser, kind);
            const { page, errors } = await open();
            await offline(page);
            await page.getByLabel('Introduction', { exact: true }).fill('Offline edits');
            await page.getByRole('button', { name: 'Save draft', exact: true }).click();
            assert.equal(state.posts.length, 0);
            state.authorized = false;
            await reconnect(page);
            await expect(page.locator('[data-proposal-autosave-message]')).toContainText('Saved locally only');
            assert.equal(state.posts.length, 0);
            state.authorized = true;
            await page.getByRole('button', { name: 'Retry draft saving' }).click();
            await expect.poll(() => state.posts.length).toBe(1);
            await expect.poll(() => page.locator('[name="draft_version"]').inputValue()).toBe('1');
            await offline(page);
            await page.getByRole('textbox', { name: 'Introduction', exact: true }).fill('More offline work');
            state.editable = false;
            await reconnect(page);
            await expect(page.locator('[data-proposal-stale-warning]')).toBeVisible();
            assert.equal(state.posts.length, 1);
            assert.equal(await page.evaluate(() => window.editorState.autoSaveBlocked), true);
            assert.equal(await page.evaluate(() => window.guard()), false);
            assert.match(await page.evaluate(() => JSON.stringify(Object.entries(localStorage))), /More offline work/);
            assert.equal(await page.evaluate(() => window.submissions), 0);
            assert.deepEqual(errors, []);
            await context.close();
        } finally { await browser.close(); }
    });
}

test('report restore rechecks a version or scope change made after the comparison opened', { timeout: 60000 }, async () => {
    const browser = await launch();
    try {
        const { context, state, open } = await fixture(browser, 'progress');
        let { page } = await open();
        await offline(page);
        await page.getByLabel('Introduction', { exact: true }).fill('Old local report text');
        await page.close();
        ({ page } = await open());
        const panel = page.locator('[data-proposal-conflict-recovery]');
        await panel.getByRole('checkbox', { name: 'Introduction', exact: true }).check();
        state.scope = 'different-correction-cycle';
        await panel.getByRole('button', { name: 'Apply selected changes' }).click();
        await expect(page.locator('[data-proposal-stale-warning]')).toBeVisible();
        await expect(page.getByRole('textbox', { name: 'Introduction', exact: true })).toHaveValue('Saved server text');
        assert.equal(state.posts.length, 0);
        await context.close();
    } finally { await browser.close(); }
});

test('discarded file recovery stays discarded and report recovery is isolated by account and quarter', { timeout: 60000 }, async () => {
    const browser = await launch();
    try {
        const { context, state, open } = await fixture(browser, 'monitoring');
        const { page } = await open();
        await offline(page);
        await page.getByLabel('Introduction', { exact: true }).fill('Discarded local copy');
        await page.getByLabel('Evidence', { exact: true }).setInputFiles({ name: 'proof.pdf', mimeType: 'application/pdf', buffer: Buffer.from('file') });
        await page.getByRole('button', { name: 'Discard recovery copy' }).click();
        await page.evaluate(() => window.dispatchEvent(new Event('pagehide')));
        assert.equal((await page.evaluate(keys)).length, 0);
        await page.getByLabel('Introduction', { exact: true }).fill('Q1 private work');
        await expect.poll(() => page.evaluate(keys)).toHaveLength(1);
        const second = await open('2026-06-30');
        await expect(second.page.locator('[data-proposal-conflict-recovery]')).toBeHidden();
        await offline(second.page);
        await second.page.getByLabel('Introduction', { exact: true }).fill('Q2 private work');
        await expect.poll(() => second.page.evaluate(keys)).toHaveLength(2);
        state.user = '2';
        const other = await open();
        await expect.poll(() => other.page.evaluate(keys)).toHaveLength(0);
        await page.getByLabel('Introduction', { exact: true }).fill('Old account must stay private');
        await page.evaluate(() => window.dispatchEvent(new Event('pagehide')));
        assert.equal((await page.evaluate(keys)).length, 0);
        await other.page.getByLabel('Introduction', { exact: true }).fill('Current account work');
        await expect.poll(() => other.page.evaluate(keys)).toHaveLength(1);
        await other.page.evaluate(() => document.querySelector('[action="/logout"]').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true })));
        assert.equal((await other.page.evaluate(keys)).length, 0);
        await context.close();
    } finally { await browser.close(); }
});
