import assert from 'node:assert/strict';
import test from 'node:test';
import { projectDocumentDrawer } from '../../resources/js/project-document-drawer.js';

test('right-click targets project documents and exposes preview and download actions', () => {
    const files = projectDocumentDrawer({ topicId: 17, documents: [{ key: 'paper-1', filename: 'report.pdf', view_url: '/view', download_url: '/download' }] });
    const dispatched = [];
    files.$dispatch = (...args) => dispatched.push(args);
    files.prepareContextMenu({ target: { closest(selector) { return selector === '[data-project-document-key]' ? { dataset: { projectDocumentKey: 'paper-1' } } : null; } } });
    assert.equal(files.selectedItem, 'paper-1');
    files.menuActions.find(action => action.id === 'preview').run();
    assert.deepEqual(dispatched, [['open-modal', 'project-17-paper-1']]);
    assert.equal(files.menuActions.find(action => action.id === 'download').href, '/download');
});

test('project file layout restores per-account preferences without losing category selection', (t) => {
    const originalStorage = globalThis.localStorage;
    const values = new Map([['athena-report-library:42', '{"view":"list","size":"small"}']]);
    globalThis.localStorage = {
        getItem: (key) => values.get(key) ?? null,
        setItem: (key, value) => values.set(key, value),
    };
    t.after(() => { globalThis.localStorage = originalStorage; });

    const files = projectDocumentDrawer({ inline: true, displayStorageKey: 'athena-report-library:42' });
    files.init();
    assert.equal(files.view, 'list');
    assert.equal(files.size, 'small');
    files.openCategory('signed_papers');
    files.selectItem('signed-copy');

    files.setPreference('size', 'large');
    files.setPreference('view', 'grid');
    assert.equal(files.activeCategory, 'signed_papers');
    assert.equal(files.selectedItem, 'signed-copy');
    assert.equal(files.open, true);
    assert.deepEqual(JSON.parse(values.get('athena-report-library:42')), { view: 'grid', size: 'large' });

    const restored = projectDocumentDrawer({ inline: true, displayStorageKey: 'athena-report-library:42' });
    restored.init();
    assert.equal(restored.view, 'grid');
    assert.equal(restored.size, 'large');
    const separateAccount = projectDocumentDrawer({ inline: true, displayStorageKey: 'athena-report-library:43' });
    separateAccount.init();
    assert.equal(separateAccount.view, 'grid');
    assert.equal(separateAccount.size, 'medium');
});

test('signing shortcut opens signed papers and the Files button returns to all documents', () => {
    const drawer = projectDocumentDrawer();
    drawer.openDrawer('signed_papers');
    assert.equal(drawer.open, true);
    assert.equal(drawer.activeCategory, 'signed_papers');
    drawer.openDrawer();
    assert.equal(drawer.activeCategory, 'all');
});

test('inline project files navigate categories without locking the page', () => {
    const files = projectDocumentDrawer({ inline: true });
    let watches = 0;
    files.$watch = () => { watches++; };
    files.init();
    assert.equal(watches, 0);
    assert.equal(files.open, true);
    files.selectItem('category:signed_papers');
    assert.equal(files.selectedItem, 'category:signed_papers');
    files.openCategory('signed_papers');
    assert.equal(files.activeCategory, 'signed_papers');
    assert.equal(files.selectedItem, null);
    files.selectItem('signed-copy');
    files.openCategory('all');
    assert.equal(files.selectedItem, null);
});
