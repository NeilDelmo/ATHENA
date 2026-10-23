import assert from 'node:assert/strict';
import test from 'node:test';
import { collegeReportLibrary } from '../../resources/js/college-report-library.js';

const sources = [
    { id: 'a', source_id: 'a', name: 'CICS', kind: 'folder', parent_id: null },
    { id: 'b', source_id: 'b', name: 'CTE', kind: 'folder', parent_id: null },
    { id: 'q', source_id: 'q', name: 'Q3', kind: 'folder', parent_id: 'a' },
    { id: 'f', source_id: 'f', name: 'Report', kind: 'file', parent_id: 'q' },
];
function library() {
    const values = new Map();
    globalThis.localStorage = { getItem: key => values.get(key) ?? null, setItem: (key, value) => values.set(key, value), removeItem: key => values.delete(key) };
    globalThis.window = { location: { reload() {} } };
    const state = collegeReportLibrary({ sources, workspace: { revision: 0, items: [] }, location: null, storageKey: 'test', updateUrl: '/save' });
    state.init();
    state.save = async items => { state.workspace = { revision: state.workspace.revision + 1, items }; return true; };
    return state;
}

test('cut and paste move a folder with its children only after paste succeeds', async () => {
    const state = library();
    state.selectedIds = ['a'];
    state.copySelection('cut');
    assert.equal(state.nodes.find(node => node.id === 'a').parent_id, null);
    await state.pasteSelection('b');
    assert.equal(state.nodes.find(node => node.id === 'a').parent_id, 'b');
    assert.equal(state.nodes.find(node => node.id === 'q').parent_id, 'a');
    assert.equal(state.nodes.find(node => node.id === 'f').parent_id, 'q');
    assert.equal(state.clipboard, null);
});

test('copy and paste duplicate the entire folder tree with independent instance ids', async () => {
    const state = library();
    state.selectedIds = ['a'];
    state.copySelection();
    await state.pasteSelection('b');
    const copied = state.nodes.find(node => node.source_id === 'a' && node.id !== 'a');
    const quarter = state.nodes.find(node => node.parent_id === copied.id);
    const file = state.nodes.find(node => node.parent_id === quarter.id);
    assert.equal(copied.parent_id, 'b');
    assert.equal(quarter.source_id, 'q');
    assert.equal(file.source_id, 'f');
    assert.notEqual(file.id, 'f');
    assert.equal(state.nodes.find(node => node.id === 'a').parent_id, null);
    assert.equal(state.clipboard.mode, 'copy');
});

test('clipboard survives navigation and refuses descendants files and same-location moves', async () => {
    const state = library();
    state.selectedIds = ['a'];
    state.copySelection('cut');
    const reopened = collegeReportLibrary({ sources, workspace: state.workspace, storageKey: 'test' });
    reopened.init();
    assert.equal(reopened.clipboard.mode, 'cut');
    for (const destination of ['q', 'f', null]) {
        assert.equal(await state.pasteSelection(destination), false);
        assert.equal(state.workspace.revision, 0);
    }
    assert.equal(state.clipboard.mode, 'cut');
});

test('failed saves retain cut items and a repeated copy gets a distinct name', async () => {
    const state = library();
    state.selectedIds = ['a'];
    state.copySelection('cut');
    const save = state.save;
    state.save = async () => false;
    assert.equal(await state.pasteSelection('b'), false);
    assert.equal(state.clipboard.mode, 'cut');
    assert.equal(state.nodes.find(node => node.id === 'a').parent_id, null);
    state.save = save;
    state.copySelection();
    await state.pasteSelection('b');
    await state.pasteSelection('b');
    assert.deepEqual(state.nodes.filter(node => node.parent_id === 'b').map(node => node.name), ['CICS', 'CICS (copy)']);
});

test('right-click selects its item and always exposes clipboard commands', () => {
    const state = library();
    state.prepareContextMenu({ target: { closest: () => ({ dataset: { libraryItem: 'a' } }) } });
    assert.deepEqual(state.selectedIds, ['a']);
    assert.deepEqual(state.menuActions.filter(action => ['cut', 'copy', 'paste'].includes(action.id)).map(action => [action.label, Boolean(action.disabled)]), [
        ['Cut', false], ['Copy', false], ['Paste', true],
    ]);
    state.prepareContextMenu({ target: { closest: () => null }, currentTarget: { hasAttribute: () => true } });
    assert.deepEqual(state.selectedIds, []);
});

test('the Actions button retains the selected item for keyboard and touch access', () => {
    const state = library();
    state.selectedIds = ['a'];
    state.prepareContextMenu({ target: { closest: () => null }, currentTarget: { hasAttribute: () => false } });
    assert.deepEqual(state.selectedIds, ['a']);
    assert.equal(state.menuActions.find(action => action.id === 'cut').disabled, false);
});

test('Enter opens a focused folder and editing fields keep native shortcuts', () => {
    const state = library();
    let opened = false;
    let prevented = false;
    state.openSelection = () => { opened = true; };
    state.handleFileKey({ key: 'Enter', target: { dataset: { libraryItem: 'a' }, matches: () => true }, preventDefault() { prevented = true; } });
    assert.equal(opened, true);
    assert.equal(prevented, true);
    assert.deepEqual(state.selectedIds, ['a']);
    state.handleFileKey({ key: 'x', ctrlKey: true, target: { closest: () => ({}) }, preventDefault() { assert.fail('Editing shortcut was captured'); } });
    assert.equal(state.clipboard, null);
});

test('sorting toggles direction, keeps the folder, and starts from the first page', () => {
    const state = library();
    let navigated;
    window.location.href = 'http://localhost/reports?year=2026&college=CICS&page=2';
    window.location.assign = url => { navigated = new URL(url); };
    state.setSort('name');
    assert.equal(navigated.searchParams.get('sort'), 'name');
    assert.equal(navigated.searchParams.get('direction'), 'desc');
    assert.equal(navigated.searchParams.get('college'), 'CICS');
    assert.equal(navigated.searchParams.has('page'), false);
    state.setSort('submitted_at');
    assert.equal(navigated.searchParams.get('direction'), 'desc');
    const previous = navigated.href;
    state.setSort('unknown');
    assert.equal(navigated.href, previous);
});
