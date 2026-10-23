import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { chromium, expect } from '@playwright/test';
import { researchAssistantHistory } from '../../resources/js/research-assistant-history.js';
import { createProposalDialog } from '../../resources/js/proposal-dialog.js';
import { createManilaClock } from '../../resources/js/manila-clock.js';

function historyStore(fetchHistory) {
    return {
        ...researchAssistantHistory([], fetchHistory),
        historyUrl: () => '/research-support/history',
        historySearchQuery: '',
        historySearchResults: [],
    };
}

test('history loads only on demand and shares a single request across openings', async () => {
    let calls = 0;
    let resolve;
    const store = historyStore(() => {
        calls += 1;
        return new Promise((callback) => { resolve = callback; });
    });
    assert.equal(calls, 0);
    const first = store.loadHistory();
    const second = store.loadHistory();
    assert.equal(calls, 1);
    assert.equal(store.historyLoading, true);
    resolve({ ok: true, json: async () => ({ conversations: [{ id: 1, title: 'Saved chat' }] }) });
    await Promise.all([first, second]);
    await store.loadHistory();
    assert.equal(calls, 1);
    assert.equal(store.historyLoading, false);
    assert.equal(store.historyLoaded, true);
    assert.deepEqual(store.historySearchResults, store.history);
});

test('history retries after failure and caches an empty successful history', async () => {
    let calls = 0;
    const store = historyStore(async () => {
        calls += 1;
        return { ok: calls > 1, json: async () => ({ conversations: [] }) };
    });
    await store.loadHistory();
    assert.equal(store.historyLoaded, false);
    assert.ok(store.historyLoadError);
    await store.loadHistory();
    await store.loadHistory();
    assert.equal(calls, 2);
    assert.equal(store.historyLoaded, true);
    assert.equal(store.historyLoadError, '');
});

test('a pending history request preserves new chats and current search results', async () => {
    let resolve;
    const store = historyStore(() => new Promise((callback) => { resolve = callback; }));
    const pending = store.loadHistory();
    store.history = [{ id: 2, title: 'New title', updated_at: '2026-10-04' }];
    store.historySearchQuery = 'matching';
    store.historySearchResults = [{ id: 7, title: 'Search match' }];
    resolve({ ok: true, json: async () => ({ conversations: [
        { id: 2, title: 'Old title', updated_at: '2026-10-03' },
        { id: 1, title: 'Older chat', updated_at: '2026-10-02' },
    ] }) });
    await pending;
    assert.deepEqual(store.history.map(({ title }) => title), ['New title', 'Older chat']);
    assert.deepEqual(store.historySearchResults, [{ id: 7, title: 'Search match' }]);
});

test('confirmation dialogs load only when used and preserve returned decisions', async () => {
    let imports = 0;
    const options = { title: 'Submit proposal?' };
    const dialog = createProposalDialog(async () => {
        imports += 1;
        return { default: { fire: async (received) => ({ isConfirmed: received === options }) } };
    });
    assert.equal(imports, 0);
    const results = await Promise.all([dialog.fire(options), dialog.fire(options)]);
    assert.equal(imports, 1);
    assert.deepEqual(results, [{ isConfirmed: true }, { isConfirmed: true }]);
});

test('a failed dialog chunk can be retried', async () => {
    let imports = 0;
    const dialog = createProposalDialog(async () => {
        imports += 1;
        if (imports === 1) throw new Error('Offline');
        return { default: { fire: async () => ({ isConfirmed: false }) } };
    });
    await assert.rejects(dialog.fire({}), /Offline/);
    assert.deepEqual(await dialog.fire({}), { isConfirmed: false });
    assert.equal(imports, 2);
});

test('page navigation replaces the clock timer and releases detached pages', () => {
    let clock = {};
    const activeTimers = new Map();
    let nextId = 0;
    const initialize = createManilaClock({ getElementById: () => clock }, {
        setInterval(callback) { activeTimers.set(++nextId, callback); return nextId; },
        clearInterval(id) { activeTimers.delete(id); },
    });
    initialize();
    const previousClock = clock;
    assert.ok(previousClock.dateTime);
    clock = {};
    initialize();
    assert.equal(activeTimers.size, 1);
    assert.ok(clock.dateTime);
    clock = null;
    initialize();
    assert.equal(activeTimers.size, 0);
});

test('the built app loads history and dialogs on demand across page navigation', async () => {
    const draftList = readFileSync(resolve('resources/views/faculty/proposal-drafts/index.blade.php'), 'utf8');
    const resumeNavigation = draftList.match(/<a\s+(wire:navigate(?:\.hover)?)\s+href="\{\{ route\('faculty\.proposal-drafts\.show'/)?.[1];
    assert.ok(resumeNavigation, 'Resume must keep Livewire navigation');
    const manifest = JSON.parse(readFileSync(resolve('public/build/manifest.json'), 'utf8'));
    const appEntry = manifest['resources/js/app.js'];
    const allowedAssets = new Set(Object.values(manifest).map((entry) => entry.file));
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        const page = await browser.newPage();
        const errors = [];
        let historyRequests = 0;
        let dialogRequests = 0;
        let actionRequests = 0;
        let draftPageRequests = 0;
        page.on('pageerror', (error) => errors.push(error.message));
        await page.route('http://athena-loading.test/**', async (route) => {
            const path = new URL(route.request().url()).pathname.slice(1);
            if (path === 'second') draftPageRequests++;
            if (allowedAssets.has(path)) {
                if (path.includes('sweetalert2')) dialogRequests++;
                await route.fulfill({ body: readFileSync(resolve('public/build', path)), contentType: path.endsWith('.css') ? 'text/css' : 'text/javascript' });
                return;
            }
            if (path === 'history') {
                historyRequests++;
                await route.fulfill({ json: { conversations: [{ id: 1, title: 'Saved chat is ready' }] } });
                return;
            }
            if (path === 'action') {
                actionRequests++;
                await route.fulfill({ body: '<p>Action completed</p>', contentType: 'text/html' });
                return;
            }
            await route.fulfill({ contentType: 'text/html', body: `<!DOCTYPE html><html><head>
                <meta name="csrf-token" content="test"><meta name="app-url" content="http://athena-loading.test">
                <script data-navigate-once>window.livewireScriptConfig = { csrf: 'test', uri: '/livewire/update', progressBar: '' };</script>
                <script type="module" src="/${appEntry.file}"></script>
            </head><body x-data data-app-shell data-auth-user-id="1" data-research-assistant-history-url="/history">
                <h1>${path === 'second' ? 'Second page' : 'First page'}</h1>
                <button @click="$store.researchAssistant.openWorkspace()">Open history</button>
                <template x-for="item in $store.researchAssistant.history"><p x-text="item.title"></p></template>
                <a href="/second" ${resumeNavigation}>Resume</a>
                <form action="/action" method="post" data-proposal-confirm><button type="submit">Check confirmation</button></form>
                <time id="manila-system-time"></time>
            </body></html>` });
        });
        await page.goto('http://athena-loading.test/first');
        await page.waitForFunction(() => window.Alpine?.store('researchAssistant'));
        assert.equal(historyRequests, 0);
        assert.equal(dialogRequests, 0);
        await page.getByRole('button', { name: 'Open history' }).click();
        await page.getByText('Saved chat is ready', { exact: true }).waitFor();
        assert.equal(historyRequests, 1);
        const prefetchedDraft = page.waitForResponse('http://athena-loading.test/second', { timeout: 2000 });
        await page.getByRole('link', { name: 'Resume', exact: true }).hover();
        await prefetchedDraft;
        assert.equal(draftPageRequests, 1);
        assert.equal(page.url(), 'http://athena-loading.test/first');
        await page.getByRole('link', { name: 'Resume', exact: true }).click();
        await page.getByRole('heading', { name: 'Second page' }).waitFor();
        assert.equal(draftPageRequests, 1, 'Resume reuses its prefetched page');
        await page.getByRole('button', { name: 'Open history' }).click();
        assert.equal(historyRequests, 1);

        await page.getByRole('button', { name: 'Check confirmation' }).click();
        await page.locator('.swal2-cancel').waitFor({ timeout: 5000 });
        assert.deepEqual(errors, []);
        await page.locator('.swal2-cancel').click();
        await page.locator('.swal2-container').waitFor({ state: 'detached' });
        assert.equal(actionRequests, 0);
        await page.getByRole('button', { name: 'Check confirmation' }).click();
        await page.locator('.swal2-confirm').click();
        await page.getByText('Action completed', { exact: true }).waitFor();
        assert.equal(actionRequests, 1);
        assert.equal(dialogRequests, 1);
        assert.deepEqual(errors, []);
    } finally {
        await browser.close();
    }
});

test('research call image tools download only on their page and survive delayed chunks and navigation', { timeout: 60000 }, async () => {
    const manifest = JSON.parse(readFileSync(resolve('public/build/manifest.json'), 'utf8'));
    const appEntry = manifest['resources/js/app.js'];
    const extractorFile = manifest['resources/js/research-call-image-extractor.js']?.file;
    const allowedAssets = new Set(Object.values(manifest).map(entry => entry.file));
    const origin = 'https://athena-loading.test';
    let extractorRequests = 0;
    let extractionRequests = 0;
    let holdExtractor = true;
    let releaseExtractor;
    const poster = {
        name: 'poster.png', mimeType: 'image/png',
        buffer: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jE9sAAAAASUVORK5CYII=', 'base64'),
    };
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        const page = await browser.newPage();
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.route(`${origin}/**`, async route => {
            const path = new URL(route.request().url()).pathname.slice(1);
            if (allowedAssets.has(path)) {
                if (path === extractorFile) {
                    extractorRequests++;
                    if (holdExtractor) await new Promise(resolve => { releaseExtractor = resolve; });
                }
                return route.fulfill({ body: readFileSync(resolve('public/build', path)), contentType: path.endsWith('.css') ? 'text/css' : 'text/javascript' });
            }
            if (path === 'extract') {
                extractionRequests++;
                assert.equal(route.request().method(), 'POST');
                assert.equal(route.request().headers()['x-csrf-token'], 'test');
                assert.ok(route.request().postDataBuffer().includes(Buffer.from('poster.png')));
                return route.fulfill({ json: { fields: { title: `Detected call ${extractionRequests}` }, detected_fields: ['Call title'], missing_fields: [], warnings: [] } });
            }
            const callsPage = path === 'calls';
            return route.fulfill({ contentType: 'text/html', body: `<!DOCTYPE html><html><head>
                <meta name="csrf-token" content="test"><meta name="app-url" content="${origin}">
                <script data-navigate-once>window.livewireScriptConfig = { csrf: 'test', uri: '/livewire/update', progressBar: '' };</script>
                <script type="module" src="/${appEntry.file}"></script>
            </head><body x-data data-app-shell data-auth-user-id="1">
                <h1>${callsPage ? 'Research calls' : 'Dashboard'}</h1>
                <a wire:navigate href="/calls">Open research calls</a>
                <a wire:navigate href="/dashboard">Open dashboard</a>
                ${callsPage ? `<form data-research-call-form data-extract-url="/extract" method="post" action="/save">
                    <label>Call title<input name="title"></label>
                    <label>Poster image<input type="file" name="reference_image" accept="image/png" data-research-call-image></label>
                    <img data-research-call-image-preview alt="Selected poster">
                    <p data-research-call-image-name></p><p data-research-call-image-status></p>
                    <button type="button" data-research-call-extract disabled>Read image</button>
                </form>` : ''}
            </body></html>` });
        });
        const extractPoster = async (expected, selectPoster = true) => {
            await expect(page.locator('[data-research-call-form]')).toHaveAttribute('data-research-call-image-ready', 'true');
            if (selectPoster) await page.getByLabel('Poster image').setInputFiles(poster);
            await expect(page.locator('[data-research-call-image-name]')).toHaveText('poster.png');
            await expect(page.locator('[data-research-call-image-preview]')).toHaveAttribute('src', /^blob:/);
            await expect(page.getByRole('button', { name: 'Read image', exact: true })).toBeEnabled();
            await page.getByRole('button', { name: 'Read image', exact: true }).click();
            await expect(page.getByLabel('Call title')).toHaveValue(`Detected call ${expected}`);
            await page.waitForTimeout(50);
            assert.equal(extractionRequests, expected, 'each click must send one extraction request');
        };

        await page.goto(`${origin}/dashboard`);
        await page.waitForFunction(() => window.Alpine?.store('researchAssistant'));
        assert.equal(extractorRequests, 0, 'ordinary pages must not download image extraction tools');
        await page.getByRole('link', { name: 'Open research calls', exact: true }).click();
        await page.getByRole('heading', { name: 'Research calls', exact: true }).waitFor();
        await expect.poll(() => extractorRequests).toBe(1);
        await expect(page.locator('[data-research-call-form]')).not.toHaveAttribute('data-research-call-image-ready');
        await page.getByRole('link', { name: 'Open dashboard', exact: true }).click();
        await page.getByRole('heading', { name: 'Dashboard', exact: true }).waitFor();
        holdExtractor = false;
        const chunkLoaded = page.waitForResponse(`${origin}/${extractorFile}`);
        releaseExtractor();
        await chunkLoaded;
        assert.deepEqual(errors, []);

        await page.getByRole('link', { name: 'Open research calls', exact: true }).click();
        await extractPoster(1);
        await page.getByRole('link', { name: 'Open dashboard', exact: true }).click();
        await page.getByRole('heading', { name: 'Dashboard', exact: true }).waitFor();
        await page.getByRole('link', { name: 'Open research calls', exact: true }).click();
        await page.evaluate(() => document.dispatchEvent(new Event('livewire:navigated')));
        await extractPoster(2);
        assert.equal(extractorRequests, 1, 'page navigation must reuse the loaded extractor chunk');
        assert.deepEqual(errors, []);

        holdExtractor = true;
        await page.goto(`${origin}/calls`);
        await expect.poll(() => extractorRequests).toBe(2);
        await page.getByLabel('Poster image').setInputFiles(poster);
        await expect(page.getByRole('button', { name: 'Read image', exact: true })).toBeDisabled();
        holdExtractor = false;
        releaseExtractor();
        await extractPoster(3, false);
        assert.equal(extractorRequests, 2, 'a direct visit must initialize image tools from its lazy chunk');
        assert.deepEqual(errors, []);
    } finally {
        releaseExtractor?.();
        await browser.close();
    }
});
