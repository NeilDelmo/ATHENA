import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { chromium, expect } from '@playwright/test';

test('editors autosave without offering or retaining old browser recovery copies', async () => {
    const manifest = JSON.parse(readFileSync('public/build/manifest.json', 'utf8'));
    const app = manifest['resources/js/app.js'].file;
    const assets = new Set(Object.values(manifest).map(entry => entry.file));
    const origin = 'https://athena-autosave.test';
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        const page = await browser.newPage();
        const errors = [];
        const posts = [];
        let version = 1;
        let title = 'Saved project';
        let failSave = false;
        page.on('pageerror', error => errors.push(error.message));
        await page.addInitScript(() => {
            const key = 'athena-proposal-recovery:1:/state:normal:old-tab';
            const copy = JSON.stringify({ savedAt: Date.now(), loadedVersion: 1, fields: [{ name: 'project_title', label: 'Project title', type: 'text', value: 'Earlier browser copy' }] });
            for (const storage of [localStorage, sessionStorage]) storage.setItem(key, copy);
            localStorage.setItem('athena-proposal-recovery-account', '1');
            localStorage.setItem('unrelated-preference', 'keep');
        });
        await page.route(`${origin}/**`, route => {
            const path = new URL(route.request().url()).pathname.slice(1);
            if (assets.has(path)) return route.fulfill({ body: readFileSync(resolve('public/build', path)), contentType: path.endsWith('.css') ? 'text/css' : 'text/javascript' });
            if (path === 'state') return route.fulfill({ json: { version } });
            if (path === 'save') {
                const body = route.request().postData();
                posts.push(body);
                if (failSave) return route.abort('failed');
                if (!body.includes(`name="draft_version"\r\n\r\n${version}\r\n`)) return route.fulfill({ status: 422, json: { errors: { draft_version: ['A newer version is available.'] } } });
                title = body.match(/name="project_title"\r\n\r\n([^]*?)\r\n--/)[1];
                return route.fulfill({ json: { draft_version: ++version, project_details_complete: true } });
            }
            return route.fulfill({ contentType: 'text/html', body: `<html><head><meta name="csrf-token" content="test"><meta name="app-url" content="${origin}">
                <script>window.livewireScriptConfig={csrf:'test',uri:'/livewire/update',progressBar:''};</script><script type="module" src="/${app}"></script></head>
                <body data-auth-user-id="1"><main data-paper-editor data-project-details-autosave="true" data-paper-dirty="false" x-data="proposalDraftProjectDetails({autoSave:true})">
                    <p data-proposal-autosave-status><span data-proposal-autosave-indicator></span><span data-proposal-autosave-message></span></p>
                    <form data-paper-form data-project-details-autosave-form action="/save" method="post"><label>Project title<input name="project_title" value="${title}"></label><input type="hidden" name="draft_version" value="${version}"></form>
                    <section data-proposal-version-monitor data-loaded-version="${version}" data-state-url="/state"><div data-proposal-stale-warning hidden>A newer saved version is available.</div><div data-proposal-conflict-recovery hidden></div></section>
                </main></body></html>` });
        });
        await page.goto(`${origin}/editor`);
        await page.waitForFunction(() => document.querySelector('[x-data]')._x_dataStack);
        await expect(page.locator('[data-proposal-conflict-recovery]')).toBeHidden();
        assert.deepEqual(await page.evaluate(() => [localStorage, sessionStorage].map(storage => Object.keys(storage).filter(key => key.startsWith('athena-proposal-recovery')))), [[], []]);
        assert.equal(await page.evaluate(() => localStorage.getItem('unrelated-preference')), 'keep');
        await page.getByLabel('Project title').fill('Autosaved project');
        await expect(page.locator('[data-proposal-autosave-message]')).toHaveText('Saved just now.');
        assert.equal(title, 'Autosaved project');
        assert.equal(posts.length, 1);
        failSave = true;
        await page.getByLabel('Project title').fill('Work still on this page');
        await expect(page.locator('[data-proposal-autosave-indicator]')).toHaveClass(/bg-red-600/);
        assert.equal(await page.evaluate(() => document.querySelector('[data-paper-editor]').dataset.paperDirty), 'true');
        assert.equal(title, 'Autosaved project');
        failSave = false;
        await page.evaluate(() => window.dispatchEvent(new Event('online')));
        await expect(page.locator('[data-proposal-autosave-message]')).toHaveText('Saved just now.');
        assert.equal(title, 'Work still on this page');
        version++;
        await page.getByLabel('Project title').fill('Stale work');
        await expect(page.locator('[data-proposal-stale-warning]')).toBeVisible();
        assert.equal(title, 'Work still on this page');
        await expect(page.locator('[data-proposal-conflict-recovery]')).toBeHidden();
        assert.deepEqual(await page.evaluate(() => Object.keys(localStorage).filter(key => key.startsWith('athena-proposal-recovery'))), []);
        assert.deepEqual(errors, []);
    } finally { await browser.close(); }
});

test('initial page requests run once and restart once after Livewire navigation', async context => {
    const manifest = JSON.parse(readFileSync('public/build/manifest.json', 'utf8'));
    const app = manifest['resources/js/app.js'].file;
    const assets = new Set(Object.values(manifest).map(entry => entry.file));
    const origin = 'https://athena-startup.test';
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    const page = await browser.newPage();
    const errors = [];
    const requests = { status: 0, prepare: 0, bundle: 0 };
    page.on('pageerror', error => errors.push(error.message));
    page.on('request', request => {
        const path = new URL(request.url()).pathname.slice(1);
        if (path === 'status') requests.status++;
        if (path === 'prepare') requests.prepare++;
        if (path === app) requests.bundle++;
    });
    try {
        await page.route(`${origin}/**`, route => {
            const path = new URL(route.request().url()).pathname.slice(1);
            if (assets.has(path)) return route.fulfill({
                body: readFileSync(resolve('public/build', path)),
                contentType: path.endsWith('.css') ? 'text/css' : 'text/javascript',
            });
            if (path === 'status') return route.fulfill({ json: {
                account_id: 1, workspace: 'faculty_researcher',
                proposal: { scope: 'approved', label: 'Approved', actions: [] }, reports: {},
            } });
            if (path === 'prepare') return route.fulfill({ json: {
                preparation: { status: 'ready', message: 'PDFs are ready', prepared: 7, total: 7 },
            } });
            return route.fulfill({ contentType: 'text/html', body: `<!doctype html><html><head>
                <meta name="csrf-token" content="test"><meta name="app-url" content="${origin}">
                <script data-navigate-once>
                    window.livewireScriptConfig = { csrf: 'test', uri: '/livewire/update', progressBar: '' };
                    window.startupNavigations = 0;
                    document.addEventListener('livewire:navigated', () => window.startupNavigations++);
                </script>
                <script type="module" src="/${app}"></script>
            </head><body x-data data-app-shell data-auth-user-id="1" data-active-workspace="faculty_researcher">
                <h1>${path}</h1><a wire:navigate href="/second">Next page</a>
                <main data-topic-live-status data-status-url="/status" data-account-id="1" data-workspace="faculty_researcher"
                    data-live-snapshot='{"proposal":"approved","reports":{}}'>
                    <section data-proposal-pdf-preparation data-state-url="/preparation-state" data-prepare-url="/prepare"
                        data-pdf-state='{"status":"needed","message":"Preparing PDFs","prepared":0,"total":7}'>
                        <p data-pdf-preparation-message></p><p data-pdf-preparation-count></p>
                        <button type="button" data-pdf-preparation-retry hidden>Retry</button>
                    </section>
                </main>
            </body></html>` });
        });
        await page.goto(`${origin}/first`);
        await page.waitForFunction(() => window.startupNavigations === 1);
        await expect(page.locator('[data-pdf-preparation-message]')).toHaveText('PDFs are ready');
        context.diagnostic(`Initial requests: ${JSON.stringify(requests)}`);
        assert.deepEqual(requests, { status: 1, prepare: 1, bundle: 1 });

        await page.getByRole('link', { name: 'Next page' }).click();
        await expect(page.getByRole('heading')).toHaveText('second');
        await page.waitForFunction(() => window.startupNavigations === 2);
        await expect(page.locator('[data-pdf-preparation-message]')).toHaveText('PDFs are ready');
        context.diagnostic(`After navigation: ${JSON.stringify(requests)}`);
        assert.deepEqual(requests, { status: 2, prepare: 2, bundle: 1 });
        assert.deepEqual(errors, []);
    } finally {
        await browser.close();
    }
});
