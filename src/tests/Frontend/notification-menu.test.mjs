import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { runInNewContext } from 'node:vm';
import { fileURLToPath } from 'node:url';
import { chromium, expect } from '@playwright/test';

const app = readFileSync(new URL('../../resources/js/app.js', import.meta.url), 'utf8');
const source = app.slice(
    app.indexOf("Alpine.data('notificationMenu',"),
    app.indexOf("Alpine.data('fileDropzone',"),
);

function createMenu({ area = 'proposal_submissions', actionUrl = null, ok = true, fetcher = null } = {}) {
    const item = {
        id: 'notification-1',
        read_at: null,
        data: { url: '/review', sidebar_area: area, action_url: actionUrl },
    };
    const calls = [];
    let factory;
    runInNewContext(source, {
        Alpine: { data: (name, callback) => { factory = callback; } },
        document: { querySelector: () => ({ content: 'csrf-token' }) },
        window: { location: { assign: (url) => calls.push(['navigate', url]) } },
        fetch: fetcher || (async (url, options) => {
            calls.push(['request', url, options.method]);
            return { ok, json: async () => ({ read: ok, unread_count: 0, preserved_ids: [] }) };
        }),
        showProposalConfirmation: async () => {
            calls.push(['invitation', item.read_at]);
            return false;
        },
    });
    const menu = factory({
        notifications: [item],
        unreadCount: 1,
        workspace: 'research_head',
        readUrl: '/notifications/__ID__/read',
        readAllUrl: '/notifications/read-all',
        openUrl: '/notifications/__ID__/open',
    });

    const event = { preventDefault: () => calls.push(['prevent-default']) };
    return { menu, item, calls, event };
}

for (const area of ['proposal_submissions', 'project_monitoring', null]) {
    test(`opening ${area ?? 'general'} notifications submits the server read and redirect form`, async () => {
        const { menu, item, calls, event } = createMenu({ area });

        await menu.openNotification(event, item);

        assert.equal(menu.notificationOpenUrl(item), '/notifications/notification-1/open');
        assert.deepEqual(calls, [], 'The native form must submit without interception');
    });
}

test('viewing an invitation marks it read even when acceptance is declined', async () => {
    const { menu, item, calls, event } = createMenu({ actionUrl: '/invitation/accept' });

    await menu.openNotification(event, item);

    assert.ok(item.read_at);
    assert.equal(menu.unreadCount, 0);
    assert.equal(item.data.action_completed, undefined);
    assert.equal(item.data.action_url, '/invitation/accept');
    assert.deepEqual(calls, [
        ['prevent-default'],
        ['request', '/notifications/notification-1/read', 'PATCH'],
        ['invitation', item.read_at],
    ]);
});

test('an unsuccessful read request does not clear the unread indicator', async () => {
    const { menu, item } = createMenu({ ok: false });

    assert.equal(await menu.markNotificationRead(item), false);

    assert.equal(item.read_at, null);
    assert.equal(menu.unreadCount, 1);
});

test('mark all read clears review notifications and the unread count', async () => {
    const { menu, calls } = createMenu();

    await menu.markAllRead();

    assert.ok(menu.notifications[0].read_at);
    assert.equal(menu.unreadCount, 0);
    assert.deepEqual(calls, [['request', '/notifications/read-all', 'PATCH']]);
});

test('reading a group sends only displayed ids and uses the server unread notification total', async () => {
    const { menu, item } = createMenu();
    item.notification_ids = ['notification-1', 'notification-2'];
    item.unread_count = 2;
    item.needs_action = true;
    menu.unreadCount = 3;
    const requests = [];
    menu.request = async (...args) => {
        requests.push(args);
        return { ok: true, json: async () => ({ read: true, unread_count: 1 }) };
    };

    assert.equal(await menu.markNotificationRead(item), true);
    assert.deepEqual(JSON.parse(JSON.stringify(requests)), [[
        '/notifications/notification-1/read', 'PATCH', { notification_ids: ['notification-1', 'notification-2'] },
    ]]);
    assert.equal(menu.unreadCount, 1);
    assert.equal(item.unread_count, 0);
    assert.equal(item.needs_action, true, 'Reading does not finish the underlying task');
});

test('broadcast updates refresh server grouping instead of appending duplicate project rows', () => {
    const { menu } = createMenu();
    let refreshes = 0;
    menu.refresh = () => { refreshes += 1; };

    menu.receive({ id: 'new-project-update', workspace: 'research_head', topic_id: 42 });
    menu.receive({ id: 'other-workspace', workspace: 'faculty', topic_id: 42 });

    assert.equal(refreshes, 1);
    assert.equal(menu.notifications.length, 1);
    assert.equal(menu.unreadCount, 1);
});

test('overlapping refreshes share one request and keep the current grouped feed', async () => {
    const replies = [];
    const { menu } = createMenu({ fetcher: () => new Promise((resolve) => replies.push(resolve)) });
    const oldPoll = menu.refresh();
    const newPoll = menu.refresh();
    assert.equal(replies.length, 1, 'Only one grouped feed request may be pending');
    replies[0]({ ok: true, json: async () => ({ notifications: [{ id: 'latest-group' }], unread_count: 2 }) });
    await Promise.all([oldPoll, newPoll]);
    assert.equal(menu.notifications[0].id, 'latest-group');
    assert.equal(menu.unreadCount, 2);
});

test('a refresh begun before marking notifications read cannot restore old unread state', async () => {
    let reply;
    const { menu } = createMenu({ fetcher: () => new Promise((resolve) => { reply = resolve; }) });
    const poll = menu.refresh();
    menu.request = async () => ({ ok: true, json: async () => ({ read: true, unread_count: 0 }) });
    await menu.markAllRead();
    reply({ ok: true, json: async () => ({ notifications: [{ id: 'old', read_at: null }], unread_count: 1 }) });
    await poll;
    assert.ok(menu.notifications[0].read_at);
    assert.equal(menu.unreadCount, 0);
});

test('opening the menu after a read queues a fresh feed behind the invalidated request', async () => {
    const replies = [];
    const { menu } = createMenu({ fetcher: () => new Promise(resolve => replies.push(resolve)) });
    const previous = menu.refresh();
    menu.request = async () => ({ ok: true, json: async () => ({ read: true, unread_count: 0 }) });
    await menu.markAllRead();
    const opening = menu.refresh();
    assert.equal(replies.length, 1, 'An invalidated request must finish before the next feed request starts');
    replies[0]({ ok: true, json: async () => ({ notifications: [{ id: 'stale', read_at: null }], unread_count: 1 }) });
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(replies.length, 2, 'Opening the menu must obtain a fresh feed after its read invalidated the pending response');
    replies[1]({ ok: true, json: async () => ({ notifications: [{ id: 'fresh', read_at: '2026-10-22' }], unread_count: 0 }) });
    await Promise.all([previous, opening]);
    assert.equal(menu.notifications[0].id, 'fresh');
    assert.equal(menu.unreadCount, 0);
});

test('notification dismissal restores focus without collapsing navigation, while mobile sidebar Escape still works', async () => {
    const template = readFileSync(new URL('../../resources/views/components/notification-menu.blade.php', import.meta.url), 'utf8')
        .replace(/\{\{ Js::from\(\$notificationItems\) \}\}/, '[]')
        .replace(/\{\{ \$unreadNotificationCount \}\}/, '1')
        .replace(/\{\{[\s\S]*?\}\}/g, "'/notifications'")
        .replaceAll('@csrf', '');
    const layout = readFileSync(new URL('../../resources/views/layouts/app.blade.php', import.meta.url), 'utf8');
    const escapeHandler = layout.match(/@keydown.escape.window="([^"]+)"/)[1];
    const sidebar = app.slice(app.indexOf("const sidebarStorageKey ="), app.indexOf("Alpine.data('journalFinder'"));
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    try {
        for (const width of [1280, 390]) {
            const page = await browser.newPage({ viewport: { width, height: 800 } });
            await page.setContent(`<style>[x-cloak]{display:none!important}</style><body x-data @keydown.escape.window="${escapeHandler}">
                <button @click="$store.sidebar.setOpen(true)" :aria-expanded="$store.sidebar.open" aria-label="Navigation">Menu</button>
                ${template}<button>Outside</button></body>`);
            await page.addScriptTag({ content: `document.addEventListener('alpine:init', () => { ${sidebar}\n${source} });` });
            await page.addScriptTag({ path: fileURLToPath(new URL('../../node_modules/alpinejs/dist/cdn.min.js', import.meta.url)) });
            const navigation = page.getByRole('button', { name: 'Navigation', exact: true });
            const bell = page.getByRole('button', { name: 'Notifications', exact: true });
            const dialog = page.getByRole('dialog', { name: 'Notifications', exact: true });
            await navigation.click();
            for (const dismissal of ['Escape', 'close button']) {
                await bell.click();
                await expect(dialog).toBeVisible();
                await bell.press('Tab');
                if (dismissal === 'Escape') await page.keyboard.press('Escape');
                else await page.getByRole('button', { name: 'Close notifications', exact: true }).click();
                await expect(dialog).toBeHidden();
                await expect(bell).toBeFocused();
                await expect(navigation).toHaveAttribute('aria-expanded', 'true');
            }
            await page.getByRole('button', { name: 'Outside', exact: true }).press('Escape');
            await expect(navigation).toHaveAttribute('aria-expanded', width < 640 ? 'false' : 'true');
            await page.close();
        }
    } finally {
        await browser.close();
    }
});
