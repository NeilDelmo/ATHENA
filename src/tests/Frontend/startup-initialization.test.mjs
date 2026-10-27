import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';
import { chromium, expect } from '@playwright/test';

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
