import assert from 'node:assert/strict';
import test from 'node:test';
import { projectWorkspace } from '../../resources/js/project-workspace.js';

test('opening a folder search result clears the previous location query and reveals its contents', t => {
    const { state } = fixture(t);
    state.query = 'Archive';
    assert.equal(state.childFolders.length, 1);
    state.openLocation(archiveId);
    assert.equal(state.query, '');
    assert.deepEqual(state.filteredProjects.map(project => project.id), [1]);
    assert.equal(state.childFolders[0].name, 'Fieldwork');
});

test('copying selected filename text leaves the native clipboard available', t => {
    const { state } = fixture(t);
    state.selectProject(1);
    const originalWindow = globalThis.window;
    const anchor = {};
    globalThis.window = { getSelection: () => ({ toString: () => 'Coastal research.pdf', anchorNode: anchor }) };
    t.after(() => { globalThis.window = originalWindow; });
    for (const key of ['c', 'x']) state.handleWorkspaceKeydown({ key, ctrlKey: true, target: { closest: () => null }, currentTarget: { contains: node => node === anchor }, preventDefault() { assert.fail('native text clipboard was intercepted'); }, stopPropagation() {} });
    assert.equal(state.clipboard, null);
});

test('document shortcuts still resolve when their source project is outside the current portfolio filter', async t => {
    const { requests } = fixture(t);
    const state = projectWorkspace({ projects: [], documentsUrlTemplate: '/projects/__PROJECT__/documents', workspace: { ...workspace(), shortcuts: [{ id: 'shortcut-source', folder_id: archiveId, project_id: 17, document_key: 'report:1' }] } });
    await state.openLocation(archiveId);
    assert.equal(requests[0].url, '/projects/17/documents');
    assert.equal(state.folderShortcuts[0].filename, 'progress.pdf');
    assert.equal(state.documentsError, '');
});

test('Escape in a context menu is handled by the menu and preserves the file selection', t => {
    const { state } = fixture(t);
    state.selectProject(1);
    state.copySelection();
    state.handleWorkspaceKeydown({ key: 'Escape', target: { closest: selector => selector.includes('[role="menu"]') ? {} : null }, preventDefault() { assert.fail('workspace intercepted a menu key'); }, stopPropagation() {} });
    assert.deepEqual(state.selectedIds, [1]);
    assert.equal(state.clipboard.mode, 'copy');
});

test('Escape closes an opening context menu before its focus frame without clearing selected files', t => {
    const { state } = fixture(t);
    state.selectProject(1);
    state.copySelection();
    state.viewMenuOpen = true;
    let prevented = false;
    state.handleWorkspaceKeydown({ key: 'Escape', target: { closest: () => null }, preventDefault() { prevented = true; }, stopPropagation() {} });
    assert.equal(prevented, true);
    assert.equal(state.viewMenuOpen, false);
    assert.deepEqual(state.selectedIds, [1]);
    assert.equal(state.clipboard.mode, 'copy');
});

const archiveId = '0a908c94-a007-4f2a-bdea-9240b5275087';
const childId = '87d99ebf-7f9e-425c-9c21-3ecf74f0d366';
const otherId = 'bf182126-e29c-4610-984b-e3c9cb36b3cd';

function projects(count = 3) {
    return Array.from({ length: count }, (_, index) => ({
        id: index + 1,
        title: index === 0 ? 'Zulu coastal study' : index === 1 ? 'Alpha forest study' : `Project ${index + 1}`,
        researcher: index === 1 ? 'Dr. Rivera' : 'Dr. Santos',
        call: 'Research 2026',
        status: index === 1 ? 'delayed' : 'ongoing',
        status_label: index === 1 ? 'Delayed' : 'Ongoing',
        year: index === 0 ? 2025 : 2026,
        date: index === 0 ? '2025-10-10' : `2026-01-${String(index + 1).padStart(2, '0')}`,
        progress: 50,
        latest_label: 'Monitoring is open',
        secretary: 'Alex Secretary',
        secretary_initials: 'AS',
        reports: 2,
        pending: index === 1 ? 1 : 0,
        url: `/projects/${index + 1}`,
        documents_url: `/projects/${index + 1}/documents`,
        action_label: 'View project',
    }));
}

function workspace() {
    return {
        revision: 3,
        folders: [
            { id: archiveId, name: 'Archive', parent_id: null },
            { id: childId, name: 'Fieldwork', parent_id: archiveId },
            { id: otherId, name: 'Active', parent_id: null },
        ],
        assignments: [
            { project_id: 1, folder_id: archiveId },
            { project_id: 2, folder_id: childId },
        ],
        shortcuts: [],
        view: 'grid',
        grouping: 'year',
        sort: 'attention',
    };
}

function fixture(t, options = {}) {
    const originalFetch = globalThis.fetch;
    const originalDocument = globalThis.document;
    const requests = [];
    globalThis.document = { querySelector: () => ({ content: 'csrf-token' }) };
    globalThis.fetch = async (url, request) => {
        const payload = request.body ? JSON.parse(request.body) : null;
        requests.push({ url, request, payload });
        if (request.method === 'GET') {
            if (options.documentFetch) return options.documentFetch(url);
            return { ok: true, status: 200, json: async () => ({ categories: [{ key: 'reports', label: 'Reports', count: 1 }], documents: [{ key: 'report:1', category: 'reports', title: 'Progress report', filename: 'progress.pdf', note: 'First quarter', source: 'Monitoring', uploaded_by: 'Dr. Santos', uploaded_at: '2026-01-02', file_size: 200, view_url: '/reports/1/view', download_url: '/reports/1/download', official: true }], total: 1 }) };
        }
        if (options.fetch) return options.fetch(payload);
        return { ok: true, status: 200, json: async () => ({ workspace: { ...payload, revision: payload.revision + 1 } }) };
    };
    t.after(() => {
        globalThis.fetch = originalFetch;
        globalThis.document = originalDocument;
    });
    return {
        state: projectWorkspace({ projects: projects(options.count), workspace: workspace(), saveUrl: '/projects/workspace', displayStorageKey: options.displayStorageKey }),
        requests,
    };
}

test('item sizing persists without changing the project view selections or workspace records', (t) => {
    const originalStorage = globalThis.localStorage;
    const values = new Map([['browser-account-1', JSON.stringify({ view: 'list', size: 'small' })]]);
    globalThis.localStorage = {
        getItem: (key) => values.get(key) ?? null,
        setItem: (key, value) => values.set(key, value),
    };
    t.after(() => { globalThis.localStorage = originalStorage; });
    const { state, requests } = fixture(t, { displayStorageKey: 'browser-account-1' });
    state.init();
    assert.equal(state.size, 'small');
    assert.equal(state.view, 'grid');
    state.selectProject(2);
    const previous = structuredClone(state.workspace);
    state.setItemSize('large');
    state.setItemSize('tiny');
    assert.equal(state.size, 'large');
    assert.deepEqual(state.selectedIds, [2]);
    assert.deepEqual(state.workspace, previous);
    assert.equal(requests.length, 0);
    assert.deepEqual(JSON.parse(values.get('browser-account-1')), { view: 'list', size: 'large' });
    const restored = projectWorkspace({ displayStorageKey: 'browser-account-1' });
    restored.init();
    assert.equal(restored.size, 'large');
    const separate = projectWorkspace({ displayStorageKey: 'browser-account-2' });
    separate.init();
    assert.equal(separate.size, 'medium');
});

test('Explorer view gestures preserve selection and save only layout preferences', async (t) => {
    const { state, requests } = fixture(t);
    state.init();
    state.selectProject(2);
    const officialProjects = structuredClone(state.projects);
    const assignments = structuredClone(state.workspace.assignments);
    let prevented = false;
    state.resizeWithWheel({ ctrlKey: true, deltaY: -120, deltaX: 0, deltaMode: 0, preventDefault() { prevented = true; } });
    assert.equal(prevented, true);
    assert.equal(state.size, 'large');
    assert.equal(state.view, 'grid');
    assert.equal(requests.length, 0);
    await state.chooseViewOption('list');
    assert.equal(state.view, 'list');
    assert.equal(requests.length, 1);
    assert.deepEqual(state.selectedIds, [2]);
    assert.deepEqual(state.projects, officialProjects);
    assert.deepEqual(state.workspace.assignments, assignments);
});

test('automatic year and personal folder locations include every project beyond a server page', (t) => {
    const { state } = fixture(t, { count: 31 });
    assert.deepEqual(state.years, [{ year: '2026', count: 30 }, { year: '2025', count: 1 }]);
    assert.equal(state.filteredProjects.length, 31);
    assert.equal(state.pageCount, 2);
    assert.equal(state.projectGroups.flatMap((group) => group.projects).length, 24);
    state.setPage(2);
    assert.equal(state.pagedProjects.length, 7);
    state.openLocation('year:2025');
    assert.deepEqual(state.filteredProjects.map((project) => project.id), [1]);
    state.openLocation(archiveId);
    assert.deepEqual(state.filteredProjects.map((project) => project.id), [1]);
    assert.deepEqual(state.childFolders.map((folder) => folder.id), [childId]);
    assert.equal(state.folderCount(archiveId), 2);
    assert.deepEqual(state.breadcrumbs.map((folder) => folder.name), ['Archive']);
    state.openLocation(childId);
    assert.deepEqual(state.breadcrumbs.map((folder) => folder.name), ['Archive', 'Fieldwork']);
    assert.equal(state.folderDepth(childId), 1);
    assert.equal(state.projectFolderLabel(2), 'Archive / Fieldwork');
    state.openLocation('unfiled');
    assert.equal(state.filteredProjects.length, 29);
});

test('large project folders resolve their current folder once while searches and moves stay current', async (t) => {
    const { state } = fixture(t, { count: 2000 });
    state.workspace.folders.unshift(...Array.from({ length: 197 }, (_, index) => ({
        id: `00000000-0000-4000-8000-${String(index + 1).padStart(12, '0')}`,
        name: `Other folder ${index + 1}`, parent_id: null,
    })));
    const currentFolder = Object.getOwnPropertyDescriptor(state, 'currentFolder').get;
    let folderResolutions = 0;
    Object.defineProperty(state, 'currentFolder', { get() { folderResolutions++; return currentFolder.call(this); } });
    const visibleIds = () => {
        folderResolutions = 0;
        const ids = state.filteredProjects.map(project => project.id);
        assert.equal(folderResolutions, 1, 'Folder resolution must not repeat for every project');
        return ids;
    };

    state.openLocation(archiveId);
    assert.deepEqual(visibleIds(), [1]);
    state.query = 'rivera';
    assert.deepEqual(visibleIds(), []);
    state.openLocation(childId);
    assert.deepEqual(visibleIds(), [2]);
    state.selectProject(2);
    assert.equal(await state.moveSelected(archiveId), true);
    assert.deepEqual(visibleIds(), []);
    state.query = '';
    state.openLocation(archiveId);
    assert.deepEqual(visibleIds(), [2, 1]);
});

test('large folder trees read parent links a linear number of times', (t) => {
    const { state } = fixture(t);
    state.workspace.folders.push(...Array.from({ length: 197 }, (_, index) => ({
        id: `folder-${index}`, name: `Other folder ${index + 1}`, parent_id: null,
    })));
    let parentReads = 0;
    state.folders.forEach(folder => {
        const parentId = folder.parent_id;
        Object.defineProperty(folder, 'parent_id', { enumerable: true, get() { parentReads++; return parentId; } });
    });

    const tree = state.folderTree;
    assert.equal(tree.length, 200);
    assert.deepEqual(tree.slice(0, 4).map(folder => [folder.name, folder.depth]), [
        ['Active', 0], ['Archive', 0], ['Fieldwork', 1], ['Other folder 1', 0],
    ]);
    assert.equal(tree.at(-1).name, 'Other folder 197');
    assert.ok(parentReads <= 400, `Building 200 folders must avoid repeated full scans (${parentReads} parent reads)`);
});

test('folder trees follow renames, moves and workspace replacement without exposing orphans or cycles', async (t) => {
    const { state } = fixture(t);
    const tree = () => state.folderTree.map(folder => [folder.id, folder.depth]);
    assert.deepEqual(tree(), [[otherId, 0], [archiveId, 0], [childId, 1]]);
    state.openRename(state.folders.find(folder => folder.id === otherId));
    state.folderName = 'Zzz';
    assert.equal(await state.submitDialog(), true);
    assert.deepEqual(tree(), [[archiveId, 0], [childId, 1], [otherId, 0]]);
    assert.equal(await state.moveFolder(childId, otherId), true);
    assert.deepEqual(tree(), [[archiveId, 0], [otherId, 0], [childId, 1]]);
    assert.equal(await state.moveFolder(otherId, childId), false);
    assert.deepEqual(tree(), [[archiveId, 0], [otherId, 0], [childId, 1]]);

    state.workspace = { ...state.workspace, folders: [
        { id: archiveId, name: 'Root', parent_id: null },
        { id: childId, name: 'Cycle A', parent_id: otherId },
        { id: otherId, name: 'Cycle B', parent_id: childId },
        { id: 'orphan', name: 'Orphan', parent_id: 'missing' },
        { id: 'self', name: 'Self', parent_id: 'self' },
    ] };
    assert.deepEqual(tree(), [[archiveId, 0]]);
    state.workspace.folders[1].parent_id = archiveId;
    assert.deepEqual(tree(), [[archiveId, 0], [childId, 1], [otherId, 2]]);
});

test('search, status, sorting, and grouping never alter official project fields', async (t) => {
    const { state } = fixture(t);
    const originals = JSON.stringify(state.projects);
    assert.deepEqual(state.filteredProjects.map((project) => project.id), [2, 3, 1]);
    await state.setPreference('sort', 'title');
    assert.deepEqual(state.filteredProjects.map((project) => project.id), [2, 3, 1]);
    state.query = 'rivera';
    assert.deepEqual(state.filteredProjects.map((project) => project.id), [2]);
    state.query = '';
    state.statusFilter = 'ongoing';
    assert.deepEqual(state.filteredProjects.map((project) => project.id), [3, 1]);
    state.resetFilters();
    await state.setPreference('sort', 'oldest');
    assert.deepEqual(state.filteredProjects.map((project) => project.id), [1, 2, 3]);
    await state.setPreference('grouping', 'none');
    assert.equal(state.projectGroups.length, 1);
    assert.equal(JSON.stringify(state.projects), originals);
});

test('multi-project drag moves selection and sends revision and CSRF headers', async (t) => {
    const { state, requests } = fixture(t);
    const data = new Map();
    const event = { preventDefault() {}, dataTransfer: { setData: (key, value) => data.set(key, value), getData: (key) => data.get(key), types: ['application/x-athena-projects'] } };
    state.selectProject(1);
    state.selectProject(2, true);
    state.startProjectDrag(event, 1);
    assert.deepEqual(JSON.parse(data.get('application/x-athena-projects')), { type: 'projects', ids: [1, 2] });
    assert.equal(state.canDrop(event, otherId), true);
    await state.drop(event, otherId);
    assert.deepEqual(state.workspace.assignments, [{ project_id: 1, folder_id: otherId }, { project_id: 2, folder_id: otherId }]);
    assert.equal(state.workspace.revision, 4);
    assert.equal(requests[0].request.method, 'PUT');
    assert.equal(requests[0].request.headers['X-CSRF-TOKEN'], 'csrf-token');
    assert.equal(requests[0].payload.revision, 3);
    assert.equal(requests[0].url, '/projects/workspace');
    assert.equal(state.canUndo, true);
    await state.undo();
    assert.deepEqual(state.workspace.assignments, workspace().assignments);
    assert.equal(state.workspace.revision, 5);
    assert.equal(state.canUndo, false);
});

test('folder moves reject descendants, self, duplicate siblings, and unknown destinations', async (t) => {
    const { state, requests } = fixture(t);
    assert.equal(await state.moveFolder(archiveId, childId), false);
    assert.match(state.error, /inside itself/);
    assert.equal(await state.moveFolder(archiveId, archiveId), false);
    assert.equal(await state.moveFolder(childId, 'missing-folder'), false);
    state.openCreate(otherId);
    state.folderName = 'Fieldwork';
    assert.equal(await state.submitDialog(), true);
    assert.equal(await state.moveFolder(childId, otherId), false);
    assert.match(state.error, /same name/);
    assert.equal(requests.length, 1);
    assert.equal(await state.moveFolder(childId, null), true);
    assert.equal(state.folders.find((folder) => folder.id === childId).parent_id, null);
});

test('folder dialog validates names and creates or renames only the selected folder', async (t) => {
    const { state, requests } = fixture(t);
    state.openCreate();
    state.folderName = '   ';
    assert.equal(await state.submitDialog(), false);
    state.folderName = ' archive ';
    assert.equal(await state.submitDialog(), false);
    assert.match(state.error, /same name/);
    state.folderName = 'x'.repeat(81);
    assert.equal(await state.submitDialog(), false);
    state.folderName = '  Publications  ';
    assert.equal(await state.submitDialog(), true);
    const folder = state.folders.find((item) => item.name === 'Publications');
    assert.match(folder.id, /^[0-9a-f-]{36}$/i);
    assert.equal(folder.parent_id, null);
    state.openRename(folder);
    state.folderName = 'Papers';
    assert.equal(await state.submitDialog(), true);
    assert.equal(state.folders.find((item) => item.id === folder.id).name, 'Papers');
    assert.equal(requests.length, 2);
    assert.equal(state.dialog, null);
});

test('folder creation still generates valid unique UUIDs on a local HTTP hostname', async (t) => {
    const { state } = fixture(t);
    const cryptoDescriptor = Object.getOwnPropertyDescriptor(globalThis, 'crypto');
    const realCrypto = globalThis.crypto;
    Object.defineProperty(globalThis, 'crypto', { configurable: true, value: { getRandomValues: realCrypto.getRandomValues.bind(realCrypto) } });
    t.after(() => Object.defineProperty(globalThis, 'crypto', cryptoDescriptor));
    state.openCreate();
    state.folderName = 'Local folder';
    assert.equal(await state.submitDialog(), true);
    state.openCreate();
    state.folderName = 'Another local folder';
    assert.equal(await state.submitDialog(), true);
    const local = state.folders.slice(-2);
    local.forEach((folder) => assert.match(folder.id, /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/));
    assert.notEqual(local[0].id, local[1].id);
});

test('deleting a nested folder returns its whole subtree projects to unfiled and undo restores it', async (t) => {
    const { state } = fixture(t);
    state.openLocation(childId);
    state.openDelete(state.folders.find((folder) => folder.id === archiveId));
    assert.equal(await state.submitDialog(), true);
    assert.deepEqual(state.folders.map((folder) => folder.id), [otherId]);
    assert.deepEqual(state.workspace.assignments, []);
    assert.equal(state.location, 'all');
    assert.equal(state.projects.length, 3);
    await state.undo();
    assert.deepEqual(state.folders, workspace().folders);
    assert.deepEqual(state.workspace.assignments, workspace().assignments);
});

test('a delete dialog without an existing folder cannot remove the workspace root', async (t) => {
    const { state, requests } = fixture(t);
    state.openDelete({ id: 'missing-folder', name: 'Missing' });
    assert.equal(state.dialog, null);
    state.dialog = 'delete';
    assert.equal(await state.submitDialog(), false);
    assert.deepEqual(state.workspace, workspace());
    assert.equal(requests.length, 0);
});

test('move dialog handles selected projects or a folder without mixing both selections', async (t) => {
    const { state } = fixture(t);
    state.openLocation('unfiled');
    state.selectAll();
    assert.deepEqual(state.selectedIds, [3]);
    state.openLocation('year:2026');
    state.selectAll();
    assert.deepEqual(state.selectedIds, [2, 3]);
    state.openMove();
    state.destinationId = otherId;
    assert.equal(await state.submitDialog(), true);
    assert.equal(state.workspace.assignments.length, 3);
    state.selectFolder(childId);
    assert.deepEqual(state.selectedIds, []);
    state.openMove();
    assert.equal(state.destinationFolders.some((folder) => folder.id === childId), false);
    state.destinationId = otherId;
    assert.equal(await state.submitDialog(), true);
    assert.equal(state.folders.find((folder) => folder.id === childId).parent_id, otherId);
    state.openLocation('all');
    assert.equal(state.selectedFolderId, null);
});

test('successful moves clear selection so later actions cannot move invisible records', async (t) => {
    const { state, requests } = fixture(t);
    state.openLocation(archiveId);
    state.selectProject(1);
    assert.equal(await state.moveSelected(otherId), true);
    assert.deepEqual(state.filteredProjects, []);
    assert.equal(state.selectedCount, 0);
    state.openMove();
    assert.equal(state.dialog, null);
    assert.equal(await state.moveSelected(null), false);
    state.selectFolder(childId);
    assert.equal(await state.moveFolder(childId, otherId), true);
    assert.equal(state.selectedFolderId, null);
    assert.equal(requests.length, 2);
});

test('save failures roll back mutation, preserve last undo, and allow a retry', async (t) => {
    let fail = false;
    const { state } = fixture(t, { fetch: async (payload) => {
        if (fail) throw new Error('Offline');
        return { ok: true, status: 200, json: async () => ({ workspace: { ...payload, revision: payload.revision + 1 } }) };
    } });
    state.selectProject(3);
    assert.equal(await state.moveSelected(otherId), true);
    fail = true;
    assert.equal(await state.setPreference('view', 'list'), false);
    assert.equal(state.view, 'grid');
    assert.equal(state.saving, false);
    assert.equal(state.canUndo, true);
    assert.match(state.error, /Offline/);
    fail = false;
    assert.equal(await state.setPreference('view', 'list'), true);
    assert.equal(state.view, 'list');
});

test('stale revisions roll back and stop later writes until reload', async (t) => {
    const { state, requests } = fixture(t, { fetch: async () => ({ ok: false, status: 409, json: async () => ({ message: 'Changed elsewhere' }) }) });
    state.selectProject(1);
    assert.equal(await state.moveSelected(otherId), false);
    assert.deepEqual(state.workspace, workspace());
    assert.equal(state.stale, true);
    assert.match(state.error, /reload/i);
    assert.equal(await state.setPreference('view', 'list'), false);
    assert.equal(requests.length, 1);
});

test('overlapping writes cannot overwrite a mutation or produce a second request', async (t) => {
    let finish;
    const { state, requests } = fixture(t, { fetch: (payload) => new Promise((resolve) => {
        finish = () => resolve({ ok: true, status: 200, json: async () => ({ workspace: { ...payload, revision: payload.revision + 1 } }) });
    }) });
    state.selectProject(3);
    const first = state.moveSelected(otherId);
    assert.equal(state.saving, true);
    assert.equal(await state.setPreference('view', 'list'), false);
    assert.equal(requests.length, 1);
    finish();
    assert.equal(await first, true);
    assert.equal(state.view, 'grid');
    assert.equal(state.workspace.assignments.find((item) => item.project_id === 3).folder_id, otherId);
});

test('untrusted drag data and files never create assignments', async (t) => {
    const { state, requests } = fixture(t);
    const event = (data, types = ['application/x-athena-projects']) => ({ preventDefault() {}, dataTransfer: { types, getData: () => data } });
    assert.equal(state.canDrop(event('', ['Files']), otherId), false);
    assert.equal(await state.drop(event('not-json'), otherId), false);
    assert.equal(await state.drop(event(JSON.stringify({ type: 'projects', ids: [999] })), otherId), false);
    assert.equal(await state.drop(event(JSON.stringify({ type: 'folder', id: archiveId })), childId), false);
    assert.equal(requests.length, 0);
    assert.deepEqual(state.workspace, workspace());
});

test('item clicks select once and modifier clicks toggle without opening a folder', (t) => {
    const { state } = fixture(t);
    state.selectProject(1);
    state.selectProject(2, { ctrlKey: false, metaKey: false });
    assert.deepEqual(state.selectedIds, [2]);
    assert.equal(state.selectedProject.id, 2);
    state.selectProject(1, { ctrlKey: true });
    assert.deepEqual(state.selectedIds, [2, 1]);
    assert.equal(state.selectedProject, null);
    state.selectProject(2, { metaKey: true });
    assert.deepEqual(state.selectedIds, [1]);
    state.selectFolder(archiveId);
    assert.equal(state.location, 'all');
    assert.equal(state.selectedFolder.id, archiveId);
    assert.equal(state.selectedCount, 1);
    state.selectFolder(archiveId, { ctrlKey: true });
    assert.equal(state.selectedFolderId, null);
});

test('project activation opens its documents while modifier activation only selects', async (t) => {
    const { state, requests } = fixture(t);
    state.activateProject(1, { ctrlKey: true });
    assert.deepEqual(state.selectedIds, [1]);
    assert.equal(state.location, 'all');
    assert.equal(requests.length, 0);
    await state.activateProject(1);
    assert.equal(state.location, 'project:1');
    assert.equal(state.activeProject.id, 1);
    assert.equal(state.projectDocuments[0].filename, 'progress.pdf');
    state.activateProject(999);
    assert.equal(state.location, 'project:1');
});

test('back and up navigate actual folder ancestry and clear old selection', (t) => {
    const { state } = fixture(t);
    assert.equal(state.canGoBack, false);
    assert.equal(state.canGoUp, false);
    state.openLocation('year:2025');
    state.openLocation(archiveId);
    state.openLocation(childId);
    state.selectProject(2);
    state.up();
    assert.equal(state.location, archiveId);
    assert.deepEqual(state.selectedIds, []);
    state.back();
    assert.equal(state.location, childId);
    state.back();
    assert.equal(state.location, archiveId);
    state.back();
    assert.equal(state.location, 'year:2025');
    state.up();
    assert.equal(state.location, 'all');
    state.openLocation('missing');
    assert.equal(state.location, 'all');
});

test('keyboard activation, select all, and escape use the current location', (t) => {
    const { state } = fixture(t);
    const event = (key, extra = {}) => ({ key, preventDefault() {}, stopPropagation() {}, ...extra });
    state.handleItemKeydown(event(' '), 'folder', archiveId);
    assert.equal(state.selectedFolderId, archiveId);
    assert.equal(state.location, 'all');
    state.handleItemKeydown(event('Enter'), 'folder', archiveId);
    assert.equal(state.location, archiveId);
    state.handleItemKeydown(event('a', { ctrlKey: true }), 'project', 1);
    assert.deepEqual(state.selectedIds, [1]);
    state.dragging = { type: 'projects', ids: [1] };
    state.dropTarget = otherId;
    state.handleItemKeydown(event('Escape'), 'project', 1);
    assert.deepEqual(state.selectedIds, []);
    assert.equal(state.dragging, null);
    assert.equal(state.dropTarget, null);
});

test('drop highlighting survives moving within the target and clears on leaving', (t) => {
    const { state } = fixture(t);
    const data = new Map();
    const event = { preventDefault() {}, stopPropagation() {}, dataTransfer: { setData: (key, value) => data.set(key, value), getData: (key) => data.get(key), types: ['application/x-athena-projects'] } };
    state.startProjectDrag(event, 1);
    state.dragOver(event, otherId);
    assert.equal(state.dropTarget, otherId);
    assert.equal(event.dataTransfer.dropEffect, 'move');
    const child = {};
    state.dragLeave({ currentTarget: { contains: (target) => target === child }, relatedTarget: child }, otherId);
    assert.equal(state.dropTarget, otherId);
    state.dragLeave({ currentTarget: { contains: () => false }, relatedTarget: null }, otherId);
    assert.equal(state.dropTarget, null);
    state.endDrag();
    assert.equal(state.dragging, null);
});

test('internal drag payloads cannot be forged, changed, or combined with external files', async (t) => {
    const { state, requests } = fixture(t);
    let data = JSON.stringify({ type: 'projects', ids: [1] });
    let stopped = 0;
    const event = { preventDefault() {}, stopPropagation() { stopped++; }, dataTransfer: { setData: (key, value) => { data = value; }, getData: () => data, types: ['application/x-athena-projects'] } };
    assert.equal(await state.drop(event, otherId), false);
    state.startProjectDrag(event, 1);
    data = JSON.stringify({ type: 'projects', ids: [2] });
    assert.equal(await state.drop(event, otherId), false);
    assert.equal(state.dragging, null);
    state.startProjectDrag(event, 1);
    event.dataTransfer.types.push('Files');
    assert.equal(await state.drop(event, otherId), false);
    assert.equal(state.dragging, null);
    assert.equal(stopped, 3);
    assert.equal(requests.length, 0);
    assert.deepEqual(state.workspace, workspace());
});

test('a rejected descendant drop stops the parent pane and clears the pending drag', async (t) => {
    const { state, requests } = fixture(t);
    let data;
    let stopped = false;
    const event = { preventDefault() {}, stopPropagation() { stopped = true; }, dataTransfer: { setData: (key, value) => { data = value; }, getData: () => data, types: ['application/x-athena-projects'] } };
    state.startFolderDrag(event, archiveId);
    assert.equal(await state.drop(event, childId), false);
    assert.equal(stopped, true);
    assert.equal(state.dragging, null);
    assert.equal(state.dropTarget, null);
    assert.equal(requests.length, 0);
});

test('document requests are cached and file selection opens a preview without changing assignments', async (t) => {
    const { state, requests } = fixture(t);
    state.selectYear('2025');
    assert.equal(state.selectedYear, '2025');
    assert.equal(state.location, 'all');
    state.openLocation('year:2025');
    await state.activateProject(1);
    state.selectDocument('report:1');
    assert.equal(state.selectedDocument.filename, 'progress.pdf');
    assert.equal(state.previewDocument, null);
    state.activateDocument('report:1');
    assert.equal(state.previewDocument.view_url, '/reports/1/view');
    state.closePreview();
    assert.equal(state.previewDocument, null);
    state.up();
    assert.equal(state.location, 'year:2025');
    await state.activateProject(1);
    assert.equal(requests.length, 1);
    assert.equal(requests[0].url, '/projects/1/documents');
    assert.equal(requests[0].request.credentials, 'same-origin');
    assert.deepEqual(state.workspace, workspace());
    state.query = 'quarter';
    assert.equal(state.documentGroups[0].documents.length, 1);
    state.query = 'missing file';
    assert.deepEqual(state.documentGroups, []);
});

test('failed or incomplete document responses remain retryable', async (t) => {
    let attempt = 0;
    const { state, requests } = fixture(t, { documentFetch: async () => {
        attempt++;
        if (attempt === 1) return { ok: false, status: 403, json: async () => ({ message: 'Access denied' }) };
        if (attempt === 2) return { ok: true, status: 200, json: async () => ({ documents: [] }) };
        return { ok: true, status: 200, json: async () => ({ documents: [], categories: [], total: 0 }) };
    } });
    await state.activateProject(1);
    assert.equal(state.documentsLoading, false);
    assert.match(state.documentsError, /Access denied/);
    assert.equal(await state.retryDocuments(), false);
    assert.match(state.documentsError, /incomplete/i);
    assert.equal(await state.retryDocuments(), true);
    assert.equal(state.documentsError, '');
    assert.deepEqual(state.projectDocuments, []);
    assert.equal(requests.length, 3);
});

test('an older project document request cannot replace the current project files', async (t) => {
    const finishes = new Map();
    const { state } = fixture(t, { documentFetch: (url) => new Promise((resolve) => {
        finishes.set(url, () => resolve({ ok: true, status: 200, json: async () => ({ categories: [], documents: [{ key: url, category: 'files', title: url, filename: url }], total: 1 }) }));
    }) });
    const first = state.activateProject(1);
    assert.equal(state.documentsLoading, true);
    const second = state.activateProject(2);
    finishes.get('/projects/1/documents')();
    await first;
    assert.equal(state.activeProject.id, 2);
    assert.deepEqual(state.projectDocuments, []);
    assert.equal(state.documentsLoading, true);
    finishes.get('/projects/2/documents')();
    await second;
    assert.equal(state.projectDocuments[0].key, '/projects/2/documents');
    assert.equal(state.documentsLoading, false);
});

test('arrow keys focus and select neighboring icon items without opening them', (t) => {
    const { state } = fixture(t);
    let focused = null;
    const first = { dataset: { itemType: 'year', itemId: '2025' }, getClientRects: () => [1], focus() { focused = this; } };
    const hidden = { dataset: { itemType: 'project', itemId: '2' }, getClientRects: () => [], focus() { focused = this; } };
    const second = { dataset: { itemType: 'folder', itemId: archiveId }, getClientRects: () => [1], focus() { focused = this; } };
    const third = { dataset: { itemType: 'project', itemId: '1' }, getClientRects: () => [1], focus() { focused = this; } };
    first.closest = () => ({ querySelectorAll: () => [first, hidden, second, third] });
    second.closest = first.closest;
    third.closest = first.closest;
    const event = (currentTarget, key) => ({ currentTarget, key, preventDefault() {}, stopPropagation() {} });
    state.handleItemKeydown(event(first, 'ArrowRight'), 'year', '2025');
    assert.equal(focused, second);
    assert.equal(state.selectedFolderId, archiveId);
    state.handleItemKeydown(event(second, 'ArrowDown'), 'folder', archiveId);
    assert.equal(focused, third);
    assert.deepEqual(state.selectedIds, [1]);
    assert.equal(state.location, 'all');
});

test('toolbar actions follow the selected icon type', async (t) => {
    const { state } = fixture(t);
    assert.equal(state.canOpenSelection, false);
    state.selectYear('2025');
    assert.equal(state.canOpenSelection, true);
    assert.equal(state.canMoveSelection, false);
    state.openSelection();
    assert.equal(state.location, 'year:2025');
    state.selectProject(1);
    assert.equal(state.canMoveSelection, true);
    assert.equal(state.canRenameSelection, false);
    await state.openSelection();
    assert.equal(state.activeProject.id, 1);
    state.selectDocument('report:1');
    state.openSelection();
    assert.equal(state.previewDocument.filename, 'progress.pdf');
    assert.equal(state.canMoveSelection, false);
    state.closePreview();
    state.openLocation('all');
    state.selectFolder(archiveId);
    assert.equal(state.canRenameSelection, true);
    assert.equal(state.canMoveSelection, true);
    state.selectProject(1);
    state.selectProject(2, true);
    assert.equal(state.canOpenSelection, false);
});

test('project navigation breadcrumbs and view saves preserve its original parent', async (t) => {
    const { state } = fixture(t);
    state.openLocation('year:2025');
    await state.activateProject(1);
    assert.deepEqual(state.breadcrumbs.map((crumb) => [crumb.id, crumb.name]), [['year:2025', '2025'], ['project:1', 'Zulu coastal study']]);
    await state.setPreference('view', 'list');
    assert.equal(state.location, 'project:1');
    state.openLocation(otherId);
    await state.back();
    assert.deepEqual(state.breadcrumbs.map((crumb) => crumb.id), ['year:2025', 'project:1']);
    state.up();
    assert.equal(state.location, 'year:2025');
    state.openLocation(childId);
    await state.activateProject(2);
    assert.deepEqual(state.breadcrumbs.map((crumb) => crumb.id), [archiveId, childId, 'project:2']);
    state.up();
    assert.equal(state.location, childId);
});

test('opening a project search result clears the project query before showing its files', async (t) => {
    const { state } = fixture(t);
    state.query = 'coastal';
    assert.deepEqual(state.filteredProjects.map((project) => project.id), [1]);
    await state.activateProject(1);
    assert.equal(state.query, '');
    assert.equal(state.documentGroups.length, 1);
    state.query = 'progress';
    state.up();
    assert.equal(state.query, '');
});

test('creating a personal folder from a virtual year reveals and selects it at the root', async (t) => {
    const { state } = fixture(t);
    state.openLocation('year:2025');
    state.openCreate();
    state.folderName = 'Year notes';
    assert.equal(await state.submitDialog(), true);
    assert.equal(state.location, 'all');
    assert.equal(state.selectedFolder.name, 'Year notes');
    assert.equal(state.selectedFolder.parent_id, null);
    assert.equal(state.childFolders.some((folder) => folder.id === state.selectedFolderId), true);
    state.openLocation(archiveId);
    state.openCreate();
    state.folderName = 'Local notes';
    assert.equal(await state.submitDialog(), true);
    assert.equal(state.location, archiveId);
    assert.equal(state.selectedFolder.name, 'Local notes');
    assert.equal(state.selectedFolder.parent_id, archiveId);
});

test('failed folder creation keeps the virtual location and does not select a nonexistent folder', async (t) => {
    const { state } = fixture(t, { fetch: async () => { throw new Error('Offline'); } });
    state.openLocation('year:2025');
    state.openCreate();
    state.folderName = 'Year notes';
    assert.equal(await state.submitDialog(), false);
    assert.equal(state.location, 'year:2025');
    assert.equal(state.selectedFolder, null);
    assert.equal(state.dialog, 'create');
});

test('select all cannot move hidden projects behind root year folders', async (t) => {
    const { state, requests } = fixture(t);
    state.handleItemKeydown({ key: 'a', ctrlKey: true, preventDefault() {}, stopPropagation() {} }, 'year', '2025');
    assert.deepEqual(state.selectedIds, []);
    assert.equal(state.canMoveSelection, false);
    state.openMove();
    assert.equal(state.dialog, null);
    assert.equal(requests.length, 0);
    state.query = 'coastal';
    state.selectAll();
    assert.deepEqual(state.selectedIds, [1]);
    state.query = '';
    state.statusFilter = 'delayed';
    state.selectAll();
    assert.deepEqual(state.selectedIds, [2]);
    state.statusFilter = 'all';
    await state.setPreference('grouping', 'none');
    state.selectAll();
    assert.deepEqual(state.selectedIds, [2, 3, 1]);
});

test('switching to year folders clears project selection hidden by the new view', async (t) => {
    const { state } = fixture(t);
    await state.setPreference('grouping', 'none');
    state.selectProject(1);
    assert.equal(state.canMoveSelection, true);
    await state.setPreference('grouping', 'year');
    assert.deepEqual(state.selectedIds, []);
    assert.equal(state.canMoveSelection, false);
});

test('copying projects adds memberships and cutting from one folder preserves other copies', async (t) => {
    const { state } = fixture(t);
    state.openLocation(archiveId);
    state.selectProject(1);
    assert.equal(state.copySelection(), true);
    assert.equal(await state.pasteSelection(otherId), true);
    assert.deepEqual(state.workspace.assignments.filter(item => item.project_id === 1).map(item => item.folder_id), [archiveId, otherId]);
    assert.equal(state.clipboard.mode, 'copy');
    state.selectProject(1);
    assert.equal(state.copySelection('cut'), true);
    assert.equal(state.isCutItem('project', 1), true);
    assert.equal(await state.pasteSelection(childId), true);
    assert.deepEqual(state.workspace.assignments.filter(item => item.project_id === 1).map(item => item.folder_id), [otherId, childId]);
    assert.equal(state.clipboard, null);
    state.openLocation(otherId);
    assert.deepEqual(state.filteredProjects.map(item => item.id), [1]);
    assert.match(state.projectFolderLabel(1), /Active/);
    assert.match(state.projectFolderLabel(1), /Fieldwork/);
    assert.equal(state.folderCount(archiveId), 2);
    state.openLocation(childId);
    state.selectProject(1);
    assert.equal(await state.moveSelected(archiveId), true);
    assert.deepEqual(state.workspace.assignments.filter(item => item.project_id === 1).map(item => item.folder_id), [otherId, archiveId]);
});

test('clipboard rejects virtual paste destinations and failed cut remains retryable', async (t) => {
    let fail = true;
    const { state, requests } = fixture(t, { fetch: async payload => {
        if (fail) throw new Error('Offline');
        return { ok: true, status: 200, json: async () => ({ workspace: { ...payload, revision: payload.revision + 1 } }) };
    } });
    state.openLocation(archiveId);
    state.selectProject(1);
    state.copySelection('cut');
    const pending = structuredClone(state.clipboard);
    assert.equal(await state.pasteSelection('year:2026'), false);
    assert.equal(requests.length, 0);
    assert.equal(await state.pasteSelection(otherId), false);
    assert.deepEqual(state.clipboard, pending);
    assert.equal(state.workspace.assignments[0].folder_id, archiveId);
    fail = false;
    assert.equal(await state.pasteSelection(otherId), true);
    assert.equal(state.clipboard, null);
    assert.equal(await state.undo(), true);
    assert.equal(state.workspace.assignments[0].folder_id, archiveId);
});

test('folder copy clones a subtree with fresh ids, memberships and shortcuts', async (t) => {
    const { state } = fixture(t);
    state.workspace.shortcuts.push({ id: 'e8ddcdbc-f04a-41aa-a610-6e71adc2220e', folder_id: childId, project_id: 1, document_key: 'report:1' });
    state.selectFolder(archiveId);
    assert.equal(state.copySelection(), true);
    assert.equal(await state.pasteSelection(null), true);
    const clone = state.folders.find(folder => folder.name === 'Archive (copy)');
    const child = state.folders.find(folder => folder.parent_id === clone.id);
    assert.notEqual(clone.id, archiveId);
    assert.equal(child.name, 'Fieldwork');
    assert.notEqual(child.id, childId);
    assert.deepEqual(state.workspace.assignments.filter(item => [clone.id, child.id].includes(item.folder_id)), [{ project_id: 1, folder_id: clone.id }, { project_id: 2, folder_id: child.id }]);
    const shortcut = state.shortcuts.find(item => item.folder_id === child.id);
    assert.notEqual(shortcut.id, state.shortcuts[0].id);
    assert.deepEqual({ ...shortcut, id: '', folder_id: '' }, { id: '', folder_id: '', project_id: 1, document_key: 'report:1' });
    assert.equal(await state.pasteSelection(null), true);
    assert.ok(state.folders.some(folder => folder.name === 'Archive (copy 2)'));
    assert.equal(state.folderCount(archiveId), 2);
});

test('folder clipboard rejects cycles and oversized copies without writing', async (t) => {
    const { state, requests } = fixture(t);
    state.selectFolder(archiveId);
    state.copySelection('cut');
    assert.equal(await state.pasteSelection(childId), false);
    assert.equal(await state.pasteSelection(archiveId), false);
    assert.equal(state.clipboard.mode, 'cut');
    state.copySelection();
    for (let index = 0; index < 197; index++) state.folders.push({ id: `extra-${index}`, name: `Extra ${index}`, parent_id: null });
    assert.equal(await state.pasteSelection(null), false);
    assert.match(state.error, /200/);
    assert.equal(requests.length, 0);
});

test('document copies create URL-free shortcuts which resolve their original preview and move independently', async (t) => {
    const { state, requests } = fixture(t);
    await state.activateProject(1);
    state.selectDocument('report:1');
    assert.equal(state.canCutSelection, false);
    assert.equal(state.copySelection('cut'), false);
    assert.equal(state.copySelection(), true);
    assert.equal(await state.pasteSelection(null), false);
    assert.equal(await state.pasteSelection(archiveId), true);
    assert.equal(state.shortcuts.length, 1);
    assert.deepEqual(Object.keys(state.shortcuts[0]).sort(), ['document_key', 'folder_id', 'id', 'project_id']);
    const shortcutId = state.shortcuts[0].id;
    await state.openLocation(archiveId);
    state.selectShortcut(shortcutId);
    assert.equal(state.selectedShortcut.filename, 'progress.pdf');
    assert.equal(state.canOpenSelection, true);
    state.openShortcut(shortcutId);
    assert.equal(state.previewDocument.view_url, '/reports/1/view');
    state.closePreview();
    assert.equal(state.copySelection('cut'), true);
    assert.equal(await state.pasteSelection(otherId), true);
    assert.equal(state.shortcuts[0].folder_id, otherId);
    assert.equal(state.projectDocuments.length, 0);
    assert.deepEqual(state.workspace.assignments, workspace().assignments);
    assert.equal(requests.filter(item => item.request.method === 'GET').length, 1);
});

test('opening stored shortcuts fetches source permissions and inaccessible files have no preview', async (t) => {
    const { state } = fixture(t, { documentFetch: async () => ({ ok: false, status: 403, json: async () => ({ message: 'Access denied' }) }) });
    state.workspace.shortcuts.push({ id: 'e8ddcdbc-f04a-41aa-a610-6e71adc2220e', folder_id: archiveId, project_id: 1, document_key: 'report:1' });
    await state.openLocation(archiveId);
    assert.deepEqual(state.folderShortcuts, []);
    state.openShortcut(state.shortcuts[0].id);
    assert.equal(state.previewDocument, null);
    assert.match(state.documentsError, /shortcut/i);
});

test('folder removal deletes contained shortcuts and undo restores them', async (t) => {
    const { state } = fixture(t);
    state.workspace.shortcuts.push({ id: 'e8ddcdbc-f04a-41aa-a610-6e71adc2220e', folder_id: childId, project_id: 1, document_key: 'report:1' });
    state.openDelete(state.folders[0]);
    assert.equal(await state.submitDialog(), true);
    assert.deepEqual(state.shortcuts, []);
    assert.equal(await state.undo(), true);
    assert.equal(state.shortcuts[0].folder_id, childId);
});

test('folder search and document ordering use names, dates and size without mutating cached data', async (t) => {
    const { state } = fixture(t);
    state.query = ' arCH ';
    assert.deepEqual(state.childFolders.map(folder => folder.name), ['Archive']);
    state.query = '';
    await state.activateProject(1);
    state.projectDocuments.push({ ...state.projectDocuments[0], key: 'report:2', filename: 'Alpha.pdf', uploaded_at: '2026-02-01', file_size: 100 });
    const names = () => state.documentGroups.flatMap(group => group.documents.map(document => document.filename));
    assert.deepEqual(names(), ['Alpha.pdf', 'progress.pdf']);
    state.documentSort = 'oldest';
    assert.deepEqual(names(), ['progress.pdf', 'Alpha.pdf']);
    state.documentSort = 'newest';
    assert.deepEqual(names(), ['Alpha.pdf', 'progress.pdf']);
    state.documentSort = 'size';
    assert.deepEqual(names(), ['progress.pdf', 'Alpha.pdf']);
    assert.equal(state.projectDocuments[0].filename, 'progress.pdf');
});

test('context selection preserves selected projects and keyboard actions respect editable fields', async (t) => {
    const { state } = fixture(t);
    state.selectProject(1);
    state.selectProject(2, true);
    state.selectContextItem('project', 1);
    assert.deepEqual(state.selectedIds, [1, 2]);
    state.selectContextItem('folder', archiveId);
    assert.equal(state.selectedFolderId, archiveId);
    const dispatched = [];
    state.$dispatch = (...args) => dispatched.push(args);
    const event = (key, extra = {}) => ({ key, target: { closest: () => null }, preventDefault() {}, stopPropagation() {}, ...extra });
    state.handleWorkspaceKeydown(event('F2'));
    assert.equal(state.dialog, 'rename');
    assert.deepEqual(dispatched[0], ['open-modal', 'project-workspace-dialog']);
    state.closeDialog();
    state.handleWorkspaceKeydown(event('c', { ctrlKey: true, target: { closest: () => ({}) } }));
    assert.equal(state.clipboard, null);
    state.handleWorkspaceKeydown(event('x', { ctrlKey: true }));
    assert.equal(state.clipboard.mode, 'cut');
    state.handleWorkspaceKeydown(event('Escape'));
    assert.equal(state.clipboard, null);
    assert.equal(state.selectedCount, 0);
});

test('shortcut moves use the existing dialog and pasted copies never duplicate a destination membership', async (t) => {
    const { state, requests } = fixture(t);
    await state.activateProject(1);
    state.selectDocument('report:1');
    state.copySelection();
    await state.pasteSelection(archiveId);
    await state.pasteSelection(archiveId);
    assert.equal(state.shortcuts.length, 1);
    assert.equal(requests.filter(request => request.request.method === 'PUT').length, 1);
    await state.openLocation(archiveId);
    state.selectShortcut(state.shortcuts[0].id);
    state.openMove();
    assert.equal(state.dialog, 'move');
    state.destinationId = otherId;
    assert.equal(await state.submitDialog(), true);
    assert.equal(state.shortcuts[0].folder_id, otherId);
    assert.equal(state.selectedShortcutId, null);
    assert.equal(state.dialog, null);
});

test('missing shortcut sources remain unavailable and folder copy names stay valid unicode within the name limit', async (t) => {
    const { state } = fixture(t);
    state.workspace.shortcuts.push({ id: 'e8ddcdbc-f04a-41aa-a610-6e71adc2220e', folder_id: archiveId, project_id: 1, document_key: 'report:missing' });
    await state.openLocation(archiveId);
    assert.deepEqual(state.folderShortcuts, []);
    assert.match(state.documentsError, /shortcut/i);
    state.openLocation('all');
    state.folders[0].name = '😀'.repeat(40);
    state.selectFolder(archiveId);
    state.copySelection();
    await state.pasteSelection(null);
    const name = state.folders.at(-2).name;
    assert.ok(Array.from(name).length <= 80);
    assert.equal(name.isWellFormed(), true);
});

test('context menu actions and keyboard paste reuse safe workspace operations', async (t) => {
    const { state } = fixture(t);
    const dispatched = [];
    state.$dispatch = (...args) => dispatched.push(args);
    state.openLocation(archiveId);
    const project = { dataset: { itemType: 'project', itemId: '1' } };
    state.prepareContextMenu({ target: { closest: selector => selector === '[data-workspace-item]' ? project : null } });
    assert.deepEqual(state.selectedIds, [1]);
    assert.deepEqual(state.menuActions.filter(action => ['open', 'cut', 'copy', 'move'].includes(action.id)).map(action => action.id), ['open', 'cut', 'copy', 'move']);
    state.menuActions.find(action => action.id === 'copy').run();
    await state.openLocation(otherId);
    assert.equal(state.canPaste, true);
    const event = (key, extra = {}) => ({ key, target: { closest: () => null }, preventDefault() {}, stopPropagation() {}, ...extra });
    await state.handleWorkspaceKeydown(event('v', { metaKey: true }));
    assert.deepEqual(state.filteredProjects.map(project => project.id), [1]);
    state.selectFolder(childId);
    state.handleWorkspaceKeydown(event('Delete'));
    assert.equal(state.dialog, 'delete');
    assert.deepEqual(dispatched.at(-1), ['open-modal', 'project-workspace-dialog']);
    state.closeDialog();
    state.prepareContextMenu({ target: { closest: selector => selector === '[data-workspace-items]' ? {} : null } });
    assert.equal(state.selectedCount, 0);
});

test('explicit shortcut refresh rechecks source access instead of using a cached document', async (t) => {
    let allowed = true;
    const { state, requests } = fixture(t, { documentFetch: async () => ({ ok: allowed, status: allowed ? 200 : 403, json: async () => allowed ? { categories: [], documents: [{ key: 'report:1', filename: 'Progress.pdf' }], total: 1 } : { message: 'Access denied' } }) });
    state.workspace.shortcuts.push({ id: 'e8ddcdbc-f04a-41aa-a610-6e71adc2220e', folder_id: archiveId, project_id: 1, document_key: 'report:1' });
    await state.openLocation(archiveId);
    assert.equal(state.folderShortcuts.length, 1);
    allowed = false;
    assert.equal(await state.retryDocuments(), false);
    assert.deepEqual(state.folderShortcuts, []);
    assert.equal(requests.length, 2);
});

test('cut clipboard cannot recreate a project membership after its source folder was removed', async (t) => {
    const { state, requests } = fixture(t);
    await state.openLocation(archiveId);
    state.selectProject(1);
    state.copySelection('cut');
    state.openDelete(state.currentFolder);
    await state.submitDialog();
    const saves = requests.length;
    assert.equal(await state.pasteSelection(otherId), false);
    assert.equal(requests.length, saves);
    assert.equal(state.clipboard.mode, 'cut');
    assert.equal(state.workspace.assignments.some(item => item.project_id === 1), false);
});

test('removing a selected shortcut keeps its original document and other references and supports undo', async (t) => {
    const { state } = fixture(t);
    await state.activateProject(1);
    state.selectDocument('report:1');
    state.copySelection();
    await state.pasteSelection(archiveId);
    await state.pasteSelection(otherId);
    const original = structuredClone(state.documentsByProject[1].documents);
    await state.openLocation(archiveId);
    state.selectShortcut(state.shortcuts[0].id);
    assert.equal(state.menuActions.find(action => action.id === 'remove-shortcut').label, 'Remove shortcut');
    assert.equal(await state.removeSelectedShortcut(), true);
    assert.equal(state.shortcuts.length, 1);
    assert.equal(state.shortcuts[0].folder_id, otherId);
    assert.deepEqual(state.documentsByProject[1].documents, original);
    assert.equal(state.selectedShortcutId, null);
    await state.undo();
    assert.equal(state.shortcuts.length, 2);
    state.selectShortcut(state.shortcuts[0].id);
    await state.handleWorkspaceKeydown({ key: 'Delete', target: { closest: () => null }, preventDefault() {}, stopPropagation() {} });
    assert.equal(state.shortcuts.length, 1);
    assert.deepEqual(state.documentsByProject[1].documents, original);
});

test('pasting a cut project into its source folder clears cut state without saving or changing membership order', async (t) => {
    const { state, requests } = fixture(t);
    state.workspace.assignments.push({ project_id: 1, folder_id: otherId });
    await state.openLocation(archiveId);
    state.selectProject(1);
    state.copySelection('cut');
    const before = structuredClone(state.workspace);
    assert.equal(await state.pasteSelection(archiveId), true);
    assert.deepEqual(state.workspace, before);
    assert.equal(state.clipboard, null);
    assert.equal(requests.length, 0);
});
