import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { chromium } from '@playwright/test';
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
        page.on('pageerror', (error) => errors.push(error.message));
        await page.route('http://athena-loading.test/**', async (route) => {
            const path = new URL(route.request().url()).pathname.slice(1);
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
                <a href="/second" wire:navigate>Next page</a>
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
        await page.getByRole('link', { name: 'Next page' }).click();
        await page.getByRole('heading', { name: 'Second page' }).waitFor();
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
