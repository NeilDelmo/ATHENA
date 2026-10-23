import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { runInNewContext } from 'node:vm';
import { fileURLToPath } from 'node:url';
import { chromium, expect } from '@playwright/test';

const app = readFileSync(new URL('../../resources/js/app.js', import.meta.url), 'utf8');
const source = app.slice(app.indexOf("Alpine.data('notificationMenu',"), app.indexOf("Alpine.data('fileDropzone',"));
const settle = () => new Promise((resolve) => setImmediate(resolve));

function createMenu({ fetcher, workspace = 'faculty' } = {}) {
    const document = new EventTarget();
    document.hidden = false;
    const window = new EventTarget();
    const timers = new Set();
    window.setInterval = (callback) => { timers.add(callback); return callback; };
    window.clearInterval = (callback) => timers.delete(callback);
    let factory;
    let requests = 0;

    runInNewContext(source, {
        Alpine: { data: (_name, callback) => { factory = callback; } },
        document,
        window,
        CustomEvent,
        fetch: (...args) => {
            requests += 1;
            return fetcher ? fetcher(...args) : Promise.resolve({ ok: true, json: async () => ({ notifications: [{ id: 'latest' }], unread_count: 2, workspace, pending_actions: [] }) });
        },
    });
    const menu = factory({ notifications: [], unreadCount: 0, workspace, indexUrl: '/notifications' });
    menu.init();

    return {
        menu, document, window,
        get requests() { return requests; },
        poll: async () => { timers.forEach((callback) => callback()); await settle(); },
        setHidden: async (hidden) => { document.hidden = hidden; document.dispatchEvent(new Event('visibilitychange')); await settle(); },
    };
}

test('notification intervals skip hidden tabs and continue polling visible tabs', async () => {
    const fixture = createMenu();
    fixture.document.hidden = true;
    await fixture.poll();
    await fixture.poll();
    assert.equal(fixture.requests, 0);

    fixture.document.hidden = false;
    await fixture.poll();
    assert.equal(fixture.requests, 1);
    assert.equal(fixture.menu.unreadCount, 2);
});

test('returning to a visible tab refreshes notifications immediately', async () => {
    const fixture = createMenu();
    await fixture.setHidden(true);
    assert.equal(fixture.requests, 0);

    await fixture.setHidden(false);
    assert.equal(fixture.requests, 1);
    assert.equal(fixture.menu.notifications[0].id, 'latest');
});

test('destroyed menus stop interval, visibility, and broadcast refreshes', async () => {
    const fixture = createMenu();
    fixture.menu.destroy();
    await fixture.poll();
    await fixture.setHidden(true);
    await fixture.setHidden(false);
    fixture.window.dispatchEvent(new CustomEvent('athena-notification', { detail: { workspace: 'faculty' } }));
    await settle();
    assert.equal(fixture.requests, 0);
});

test('visibility, menu opening, and interval polling share one pending refresh and retry a failed request', async () => {
    const replies = [];
    const fixture = createMenu({ fetcher: () => new Promise(resolve => replies.push(resolve)) });
    await fixture.setHidden(false);
    const opening = fixture.menu.refresh();
    await fixture.poll();
    assert.equal(fixture.requests, 1, 'Three refresh triggers must share one request');
    replies[0]({ ok: false });
    await opening;
    await fixture.poll();
    assert.equal(fixture.requests, 2, 'A failed request must release the next refresh');
    replies[1]({ ok: true, json: async () => ({ notifications: [{ id: 'retried' }], unread_count: 1 }) });
    await settle();
    assert.equal(fixture.menu.notifications[0].id, 'retried');
    fixture.menu.destroy();
});

test('notification broadcasts queue one fresh follow-up while other refresh triggers reuse the pending request', async () => {
    const replies = [];
    const fixture = createMenu({ fetcher: () => new Promise(resolve => replies.push(resolve)) });
    const pending = fixture.menu.refresh();
    for (let event = 0; event < 3; event++) {
        fixture.window.dispatchEvent(new CustomEvent('athena-notification', { detail: { workspace: 'faculty' } }));
    }
    await fixture.poll();
    assert.equal(fixture.requests, 1, 'Broadcasts must not start concurrent feed requests');
    replies[0]({ ok: true, json: async () => ({ notifications: [{ id: 'earlier' }], unread_count: 1 }) });
    await settle();
    assert.equal(fixture.requests, 2, 'Several broadcasts share one follow-up request');
    replies[1]({ ok: true, json: async () => ({ notifications: [{ id: 'new-event' }], unread_count: 2 }) });
    await pending;
    assert.equal(fixture.menu.notifications[0].id, 'new-event');
    fixture.menu.destroy();
});

test('a destroyed workspace cannot publish its pending response or queued broadcast after a switch', async () => {
    let reply;
    const previous = createMenu({ fetcher: () => new Promise(resolve => { reply = resolve; }) });
    let oldEvents = 0;
    previous.window.addEventListener('athena-workspace-actions', () => { oldEvents++; });
    const pending = previous.menu.refresh();
    previous.menu.receive({ workspace: 'faculty' });
    previous.menu.destroy();

    const current = createMenu({ workspace: 'research_head' });
    await current.menu.refresh();
    reply({ ok: true, json: async () => ({ notifications: [{ id: 'old-workspace' }], unread_count: 8, workspace: 'faculty', pending_actions: [] }) });
    await pending;
    assert.equal(previous.requests, 1, 'Destroyed menus must discard queued refreshes');
    assert.equal(previous.menu.unreadCount, 0);
    assert.equal(oldEvents, 0, 'A completed old request must not publish to the new page');
    assert.equal(current.menu.workspace, 'research_head');
    assert.equal(current.menu.notifications[0].id, 'latest');
    current.menu.destroy();
});

test('the browser shares pending notification requests and drops responses from a removed workspace', { timeout: 30000 }, async () => {
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    const releases = [];
    try {
        const page = await browser.newPage();
        await page.clock.install();
        const requests = [];
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        const menu = workspace => `<div data-menu x-data="notificationMenu({ notifications: [], unreadCount: 0, workspace: '${workspace}', indexUrl: '/notifications?workspace=${workspace}' })"><button @click="open = !open; if (open) refresh()">Notifications</button><output x-text="unreadCount"></output></div>`;
        await page.route('https://athena-notification-polling.test/**', async route => {
            const url = new URL(route.request().url());
            if (url.pathname === '/notifications') {
                const workspace = url.searchParams.get('workspace');
                requests.push(workspace);
                await new Promise(resolve => releases.push(resolve));
                return route.fulfill({ json: { notifications: [{ id: workspace }], unread_count: 2, workspace, pending_actions: [] } });
            }
            return route.fulfill({ contentType: 'text/html', body: `<body>${menu('faculty')}</body>` });
        });
        await page.goto('https://athena-notification-polling.test/');
        await page.addScriptTag({ content: `window.workspaceEvents = []; window.addEventListener('athena-workspace-actions', event => window.workspaceEvents.push(event.detail.workspace)); document.addEventListener('alpine:init', () => { ${source} });` });
        await page.addScriptTag({ path: fileURLToPath(new URL('../../node_modules/alpinejs/dist/cdn.min.js', import.meta.url)) });
        await page.waitForFunction(() => window.Alpine?.$data(document.querySelector('[data-menu]')).workspace === 'faculty');
        await page.evaluate(() => document.dispatchEvent(new Event('visibilitychange')));
        await expect.poll(() => requests.length).toBe(1);
        await page.getByRole('button', { name: 'Notifications', exact: true }).click();
        await page.clock.fastForward(30000);
        assert.deepEqual(requests, ['faculty'], 'Visibility, opening, and polling must share one browser request');
        await page.evaluate(() => {
            for (let event = 0; event < 3; event++) window.dispatchEvent(new CustomEvent('athena-notification', { detail: { workspace: 'faculty' } }));
        });
        await page.evaluate(html => { document.querySelector('[data-menu]').outerHTML = html; }, menu('research_head'));
        await page.waitForFunction(() => window.Alpine.$data(document.querySelector('[data-menu]')).workspace === 'research_head');
        await page.getByRole('button', { name: 'Notifications', exact: true }).click();
        await expect.poll(() => requests.length).toBe(2);
        releases[0]();
        await page.waitForTimeout(50);
        assert.deepEqual(await page.evaluate(() => window.workspaceEvents), [], 'Removed workspaces must discard pending results and broadcasts');
        releases[1]();
        await expect(page.locator('output')).toHaveText('2');
        assert.deepEqual(requests, ['faculty', 'research_head']);
        assert.deepEqual(await page.evaluate(() => window.workspaceEvents), ['research_head']);
        assert.deepEqual(errors, []);
    } finally {
        releases.forEach(release => release());
        await browser.close();
    }
});
