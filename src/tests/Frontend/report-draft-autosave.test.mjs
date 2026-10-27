import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { chromium, expect } from '@playwright/test';

const uploadSource = readFileSync(resolve('resources/js/file-upload.js'), 'utf8');
const appSource = readFileSync(resolve('resources/js/app.js'), 'utf8');
const editors = appSource.slice(appSource.indexOf("Alpine.data('monitoringToolForm'"), appSource.indexOf("Alpine.data('noticeToProceedForm'"));
const launch = () => chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });

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
        if (path === '/file-upload.js') return route.fulfill({ contentType: 'text/javascript', body: uploadSource });
        if (path.endsWith('/edit-state')) {
            state.requests.push(path);
            return route.fulfill({ status: state.authorized ? 200 : 403, json: { version: state.version, scope: state.scope, is_editable: state.editable } });
        }
        if (path.endsWith('/draft') && request.method() === 'POST') {
            const body = request.postData() || '';
            state.posts.push(body);
            if (state.offline) return route.abort('failed');
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
        await page.addScriptTag({ type: 'module', content: `import { uploadRequest } from '/file-upload.js'; window.uploadRequest=uploadRequest;
            window.editorState=Alpine.$data(document.querySelector('[data-monitoring-paper-workspace]')); window.editorReady=true;` });
        await page.waitForFunction(() => window.editorReady);
        return { page, errors };
    }
    return { context, state, open };
}

for (const kind of ['monitoring', 'progress', 'terminal']) {
    test(`${kind} autosaves directly, shows failed saves, and rejects newer or locked drafts`, { timeout: 60000 }, async () => {
        const browser = await launch();
        try {
            const { context, state, open } = await fixture(browser, kind);
            const { page, errors } = await open();
            const introduction = page.getByLabel('Introduction', { exact: true });
            const status = page.locator('[data-proposal-autosave-message]');
            await introduction.fill('Autosaved report narrative');
            await expect(status).toHaveText('Draft saved just now.');
            assert.equal(state.text, 'Autosaved report narrative');
            assert.equal(state.posts.length, 1);
            state.offline = true;
            await introduction.fill('Unsaved report narrative');
            await expect(status).toContainText('Could not save');
            assert.equal(state.text, 'Autosaved report narrative');
            assert.equal(await page.evaluate(() => document.querySelector('[data-monitoring-paper-workspace]').dataset.paperDirty), 'true');
            state.offline = false;
            await introduction.fill('Retried report narrative');
            await expect(status).toHaveText('Draft saved just now.');
            assert.equal(state.text, 'Retried report narrative');
            state.version++;
            await introduction.fill('Stale report narrative');
            await expect(page.locator('[data-proposal-stale-warning]')).toBeVisible();
            assert.equal(await page.evaluate(() => window.editorState.autoSaveBlocked), true);
            assert.equal(state.text, 'Retried report narrative');
            const posts = state.posts.length;
            await introduction.fill('Blocked report narrative');
            await page.getByRole('button', { name: 'Save draft', exact: true }).click();
            assert.equal(state.posts.length, posts);
            const locked = await open();
            state.editable = false;
            await locked.page.getByLabel('Introduction', { exact: true }).fill('Locked report narrative');
            await expect(locked.page.locator('[data-proposal-stale-warning]')).toBeVisible();
            assert.equal(state.text, 'Retried report narrative');
            assert.equal(await page.evaluate(() => window.submissions), 0);
            assert.deepEqual(await page.evaluate(() => Object.keys(localStorage).filter(key => key.startsWith('athena-proposal-recovery'))), []);
            assert.deepEqual(errors, []);
            assert.deepEqual(locked.errors, []);
            await context.close();
        } finally { await browser.close(); }
    });
}
