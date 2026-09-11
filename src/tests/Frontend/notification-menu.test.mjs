import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { runInNewContext } from 'node:vm';

const app = readFileSync(new URL('../../resources/js/app.js', import.meta.url), 'utf8');
const source = app.slice(
    app.indexOf("Alpine.data('notificationMenu',"),
    app.indexOf("Alpine.data('fileDropzone',"),
);

function createMenu({ area = 'proposal_submissions', actionUrl = null, ok = true } = {}) {
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
        fetch: async (url, options) => {
            calls.push(['request', url, options.method]);
            return { ok, json: async () => ({ read: ok, unread_count: 0, preserved_ids: [] }) };
        },
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
    });

    return { menu, item, calls };
}

for (const area of ['proposal_submissions', 'project_monitoring', null]) {
    test(`opening ${area ?? 'general'} notifications persists read status before navigation`, async () => {
        const { menu, item, calls } = createMenu({ area });

        await menu.openNotification(item);

        assert.ok(item.read_at);
        assert.equal(menu.unreadCount, 0);
        assert.deepEqual(calls, [
            ['request', '/notifications/notification-1/read', 'PATCH'],
            ['navigate', '/review'],
        ]);

        await menu.openNotification(item);
        assert.equal(calls.filter(([kind]) => kind === 'request').length, 1);
        assert.equal(menu.unreadCount, 0);
    });
}

test('viewing an invitation marks it read even when acceptance is declined', async () => {
    const { menu, item, calls } = createMenu({ actionUrl: '/invitation/accept' });

    await menu.openNotification(item);

    assert.ok(item.read_at);
    assert.equal(menu.unreadCount, 0);
    assert.equal(item.data.action_completed, undefined);
    assert.equal(item.data.action_url, '/invitation/accept');
    assert.deepEqual(calls, [
        ['request', '/notifications/notification-1/read', 'PATCH'],
        ['invitation', item.read_at],
    ]);
});

test('an unsuccessful read request does not clear the unread indicator', async () => {
    const { menu, item } = createMenu({ ok: false });

    await menu.openNotification(item);

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
