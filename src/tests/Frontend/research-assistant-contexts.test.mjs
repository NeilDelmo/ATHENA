import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { chromium, expect } from '@playwright/test';
import { researchAssistantContexts } from '../../resources/js/research-assistant-contexts.js';

function fixture(t, fetcher) {
    const oldDocument = globalThis.document;
    const oldWindow = globalThis.window;
    globalThis.document = { body: { dataset: { authUserId: '7', activeWorkspace: 'faculty', researchAssistantContextsUrl: '/contexts' } } };
    globalThis.window = { location: { href: 'https://athena.test/projects', origin: 'https://athena.test' }, athenaResearchAssistantActiveContextId: 12 };
    t.after(() => { globalThis.document = oldDocument; globalThis.window = oldWindow; });
    let requests = 0;
    const state = {
        ...researchAssistantContexts((...args) => { requests++; return fetcher(...args); }),
        contextOptions: [{ id: 12, label: 'Current project' }], selectedContextId: 12, contextEnabled: true,
    };
    return { state, get requests() { return requests; } };
}

const response = (contexts = [], overrides = {}) => ({ ok: true, json: async () => ({ contexts, account_id: 7, workspace: 'faculty', ...overrides }) });

test('project options load only on demand, share requests, and retain the current selection', async t => {
    let release;
    const f = fixture(t, (url, options) => {
        assert.equal(new URL(url).searchParams.get('active_topic_id'), '12');
        assert.equal(options.credentials, 'same-origin');
        return new Promise(resolve => { release = resolve; });
    });
    assert.equal(f.requests, 0);
    const first = f.state.loadContextOptions();
    const second = f.state.loadContextOptions();
    await Promise.resolve();
    assert.equal(f.requests, 1);
    assert.equal(f.state.contextOptionsLoading, true);
    release(response([{ id: 12, label: 'Updated current project' }, { id: 20, label: 'Older project' }]));
    assert.deepEqual(await Promise.all([first, second]), [true, true]);
    await f.state.loadContextOptions();
    assert.equal(f.requests, 1);
    assert.equal(f.state.selectedContextId, 12);
    assert.equal(f.state.contextEnabled, true);
    assert.equal(f.state.contextOptions[0].label, 'Updated current project');
});

test('failed option requests retain the seed and retry, while successful empty lists are cached', async t => {
    let succeed = false;
    const f = fixture(t, async () => succeed ? response([]) : { ok: false });
    assert.equal(await f.state.loadContextOptions(), false);
    assert.equal(f.state.contextOptions[0].id, 12);
    assert.ok(f.state.contextOptionsError);
    succeed = true;
    assert.equal(await f.state.loadContextOptions(), true);
    assert.deepEqual(f.state.contextOptions, []);
    assert.equal(f.state.contextEnabled, false);
    assert.equal(f.state.selectedContextId, 0);
    await f.state.loadContextOptions();
    assert.equal(f.requests, 2);
});

test('page resets discard stale loads without clearing the new request state', async t => {
    const replies = [];
    const f = fixture(t, () => new Promise(resolve => replies.push(resolve)));
    const old = f.state.loadContextOptions();
    await Promise.resolve();
    f.state.resetContextOptions();
    f.state.contextOptions = [];
    document.body.dataset.activeWorkspace = 'faculty_researcher';
    const current = f.state.loadContextOptions();
    await Promise.resolve();
    replies[0](response([{ id: 90, label: 'Previous workspace' }]));
    assert.equal(await old, false);
    assert.equal(f.state.contextOptionsLoading, true);
    assert.deepEqual(f.state.contextOptions, []);
    replies[1](response([{ id: 30, label: 'Current workspace' }], { workspace: 'faculty_researcher' }));
    assert.equal(await current, true);
    assert.equal(f.state.contextOptions[0].id, 30);
    assert.equal(f.state.contextOptionsLoading, false);
});

test('account or workspace mismatches never replace current project options', async t => {
    let account = 8;
    const f = fixture(t, async () => response([{ id: 99 }], { account_id: account, workspace: 'faculty_researcher' }));
    assert.equal(await f.state.loadContextOptions(), false);
    assert.equal(f.state.contextOptions[0].id, 12);
    account = 7;
    assert.equal(await f.state.loadContextOptions(), false);
    assert.equal(f.state.contextOptions[0].id, 12);
});

test('loading options preserves a review selection made while the request is pending', async t => {
    let release;
    const f = fixture(t, () => new Promise(resolve => { release = resolve; }));
    const pending = f.state.loadContextOptions();
    await Promise.resolve();
    f.state.selectedContextId = 42;
    f.state.reviewContext = { topic_id: 42 };
    release(response([{ id: 12 }]));
    await pending;
    assert.equal(f.state.selectedContextId, 42);
    assert.equal(f.state.contextEnabled, true);
});

test('pages without a context endpoint keep existing seeded options without a request', async t => {
    const f = fixture(t, async () => { throw new Error('Unexpected request'); });
    delete document.body.dataset.researchAssistantContextsUrl;
    assert.equal(await f.state.loadContextOptions(), true);
    assert.equal(f.requests, 0);
    assert.equal(f.state.contextOptions[0].id, 12);
});

test('the built app loads projects on opening, retries failures, and drops stale options across navigation', { timeout: 60000 }, async () => {
    const manifest = JSON.parse(readFileSync(resolve('public/build/manifest.json'), 'utf8'));
    const assets = new Set(Object.values(manifest).map(entry => entry.file));
    const app = manifest['resources/js/app.js'].file;
    const picker = readFileSync(resolve('resources/views/components/research-assistant-project-picker.blade.php'), 'utf8')
        .replace(/^@props\([^\n]+\)\s*/m, '')
        .replaceAll("{{ $id === 'drawer' ? 'drawerOpen' : 'workspaceOpen' }}", 'workspaceOpen')
        .replaceAll('{{ $id }}', 'workspace');
    const origin = 'https://athena-contexts.test';
    const pending = [];
    const errors = [];
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        const page = await browser.newPage();
        page.on('pageerror', error => errors.push(error.message));
        await page.route(`${origin}/**`, async route => {
            const path = new URL(route.request().url()).pathname.slice(1);
            if (assets.has(path)) return route.fulfill({ body: readFileSync(resolve('public/build', path)), contentType: path.endsWith('.css') ? 'text/css' : 'text/javascript' });
            if (path === 'contexts') {
                const reply = await new Promise(resolve => pending.push(resolve));
                return route.fulfill(reply);
            }
            if (path === 'history') return route.fulfill({ json: { conversations: [] } });
            if (path === 'history/9') return route.fulfill({ json: { conversation: { id: 9, context: { topic_id: 101 }, messages: [{ role: 'user', content: 'Saved project question' }] } } });
            const other = path === 'other';
            const seed = path === 'dashboard' ? [] : [{ id: other ? 100 : 12, label: other ? 'New account project' : 'Current project', meta: 'Research', status: 'approved' }];
            return route.fulfill({ contentType: 'text/html', body: `<!doctype html><html><head>
                <meta charset="utf-8"><meta name="csrf-token" content="test"><meta name="app-url" content="${origin}">
                <style>[x-cloak]{display:none!important}</style>
                <script data-navigate-once>window.livewireScriptConfig={csrf:'test',uri:'/livewire/update',progressBar:''};</script>
                <script type="module" src="/${app}"></script>
                </head><body x-data data-app-shell data-auth-user-id="${other ? 8 : 7}" data-active-workspace="${other ? 'research_head' : 'faculty'}" data-research-assistant-contexts-url="/contexts" data-research-assistant-history-url="/history">
                <script>window.athenaResearchAssistantContexts=${JSON.stringify(seed)};window.athenaResearchAssistantActiveContextId=${seed[0]?.id || 'null'};window.athenaResearchAssistantPageActions=[];</script>
                <h1>${path}</h1><button @click="$store.researchAssistant.openWorkspace()">Open assistant</button>
                <button @click="$store.researchAssistant.closeWorkspace()">Close assistant</button>
                <output x-text="$store.researchAssistant.selectedContextId"></output>
                <a wire:navigate href="/project">Current project page</a><a wire:navigate href="/other">Other account page</a>
                <section x-show="$store.researchAssistant.workspaceOpen">${picker}</section></body></html>` });
        });
        await page.goto(`${origin}/dashboard`);
        await page.waitForFunction(() => window.Alpine?.store('researchAssistant'));
        assert.equal(pending.length, 0, 'Closed assistants must not request the project list');
        await page.getByRole('button', { name: 'Open assistant', exact: true }).click();
        await expect.poll(() => pending.length).toBe(1);
        await page.getByRole('button', { name: 'Add project context', exact: true }).click();
        await expect(page.getByText('Loading projects…', { exact: true })).toBeVisible();
        assert.equal(pending.length, 1, 'Opening the picker must reuse the pending request');
        pending[0]({ json: { account_id: 7, workspace: 'faculty', contexts: Array.from({ length: 12 }, (_, index) => ({ id: index + 1, label: `Project ${index + 1}`, meta: 'Research', status: 'pending' })) } });
        await page.locator('[data-assistant-project-option]').filter({ has: page.getByText('Project 11', { exact: true }) }).click();
        await expect(page.locator('output')).toHaveText('11');
        await page.getByRole('button', { name: 'Close assistant', exact: true }).click();
        await page.getByRole('link', { name: 'Current project page', exact: true }).click();
        await expect(page.getByRole('heading', { name: 'project', exact: true })).toBeVisible();
        await expect(page.locator('output')).toHaveText('12');
        assert.equal(pending.length, 1, 'Navigation with a closed assistant must not load the full list');
        await page.getByRole('button', { name: 'Open assistant', exact: true }).click();
        await expect.poll(() => pending.length).toBe(2);
        pending[1]({ status: 500, json: { message: 'Temporary failure' } });
        await page.getByRole('button', { name: 'Change project: Current project', exact: true }).click();
        await expect.poll(() => pending.length).toBe(3);
        pending[2]({ status: 500, json: {} });
        await expect(page.getByRole('button', { name: 'Retry loading projects', exact: true })).toBeVisible();
        await page.getByRole('button', { name: 'Retry loading projects', exact: true }).click();
        await expect.poll(() => pending.length).toBe(4);
        await page.getByRole('link', { name: 'Other account page', exact: true }).click();
        await expect(page.getByRole('heading', { name: 'other', exact: true })).toBeVisible();
        await expect.poll(() => pending.length).toBe(5);
        pending[3]({ json: { account_id: 7, workspace: 'faculty', contexts: [{ id: 999, label: 'Stale private project' }] } });
        pending[4]({ json: { account_id: 8, workspace: 'research_head', contexts: [{ id: 100, label: 'New account project' }, { id: 101, label: 'Older saved project' }] } });
        await expect.poll(() => page.evaluate(() => window.Alpine.store('researchAssistant').contextOptionsLoaded)).toBe(true);
        assert.deepEqual(await page.evaluate(() => window.Alpine.store('researchAssistant').contextOptions.map(project => project.id)), [100, 101]);
        await page.evaluate(() => window.Alpine.store('researchAssistant').openConversation(9));
        await expect(page.locator('output')).toHaveText('101');
        assert.deepEqual(errors, []);
    } finally {
        pending.forEach(reply => reply({ status: 503, json: {} }));
        await browser.close();
    }
});
