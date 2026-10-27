import { reportLibrary } from './report-library.js';
import { workspacePageState } from './workspace-switching.js';

export function projectRecordsPage({ initialView = 'monitoring' } = {}) {
    const continuity = workspacePageState('records');
    return {
        recordsView: initialView,
        init() {
            const saved = continuity.read();
            if (!new URLSearchParams(window.location.search).has('view') && ['monitoring', 'files'].includes(saved?.view)) this.recordsView = saved.view;
            this.$watch('recordsView', view => { if (['monitoring', 'files'].includes(view)) continuity.write({ view }); });
        },
    };
}

export function projectWorkspace(config = {}) {
    const display = reportLibrary(config.displayStorageKey);
    const continuity = workspacePageState('folders');
    const copy = (value) => JSON.parse(JSON.stringify(value));
    const preferences = {
        view: ['grid', 'list'],
        grouping: ['year', 'none'],
        sort: ['attention', 'title', 'newest', 'oldest'],
    };
    const initialWorkspace = {
        revision: 0,
        folders: [],
        assignments: [],
        shortcuts: [],
        view: 'grid',
        grouping: 'year',
        sort: 'attention',
        ...copy(config.workspace || {}),
    };
    const dragType = 'application/x-athena-projects';
    const byName = (first, second) => first.name.localeCompare(second.name, undefined, { sensitivity: 'base', numeric: true });
    const projectYear = (project) => String(project.year || 'Undated');
    const projectDate = (project) => Date.parse(project.date) || 0;
    const additiveSelection = (event) => event === true || Boolean(event?.ctrlKey || event?.metaKey);
    const documentRequests = new Map();
    const newFolderId = () => {
        if (crypto.randomUUID) return crypto.randomUUID();
        // Local HTTP hostnames expose getRandomValues but may not expose randomUUID.
        const bytes = crypto.getRandomValues(new Uint8Array(16));
        bytes[6] = (bytes[6] & 0x0f) | 0x40;
        bytes[8] = (bytes[8] & 0x3f) | 0x80;
        const hex = Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('');
        return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
    };

    return {
        ...display,
        menuLabel: 'File actions',
        projects: config.projects || [],
        workspace: initialWorkspace,
        location: 'all',
        locationHistory: ['all'],
        historyIndex: 0,
        query: '',
        statusFilter: 'all',
        documentSort: 'name',
        page: 1,
        pageSize: 24,
        selectedIds: [],
        selectedFolderId: null,
        selectedYear: null,
        selectedDocumentKey: null,
        selectedShortcutId: null,
        clipboard: null,
        previewDocument: null,
        documentsByProject: {},
        projectParentLocations: {},
        documentsLoading: false,
        documentsError: '',
        dialog: null,
        folderName: '',
        folderParentId: null,
        destinationId: '',
        saving: false,
        stale: false,
        error: '',
        notice: '',
        dropTarget: null,
        dragging: null,
        undoWorkspace: null,

        init() {
            display.init();
            this.size = display.size;
            const saved = continuity.read();
            if (saved) {
                if (typeof saved.location === 'string' && this.isLocation(saved.location)) this.openLocation(saved.location, false);
                this.query = typeof saved.query === 'string' ? saved.query.slice(0, 200) : '';
                if (saved.statusFilter === 'all' || this.projects.some(project => project.status === saved.statusFilter)) this.statusFilter = saved.statusFilter;
            }
            this.$watch?.('query', () => { this.page = 1; this.clearSelection(); });
            this.$watch?.('statusFilter', () => { this.page = 1; this.clearSelection(); });
            for (const field of ['query', 'statusFilter', 'location']) this.$watch?.(field, () => continuity.write({ query: this.query.slice(0, 200), statusFilter: this.statusFilter, location: this.location }));
            if (this.currentFolder) this.loadShortcuts();
        },

        setItemSize(size) {
            display.setPreference('size', size);
            this.size = display.size;
        },

        get folders() { return this.workspace.folders; },
        get shortcuts() { return this.workspace.shortcuts || []; },
        get view() { return this.workspace.view; },
        get grouping() { return this.workspace.grouping; },
        get sort() { return this.workspace.sort; },
        get currentFolder() { return this.folders.find((folder) => folder.id === this.location) || null; },
        get activeProject() { return this.projects.find((project) => `project:${project.id}` === this.location) || null; },
        get projectDocuments() { return this.documentsByProject[this.activeProject?.id]?.documents || []; },
        get documentCategories() { return this.documentsByProject[this.activeProject?.id]?.categories || []; },
        get documentGroups() {
            const query = this.query.trim().toLocaleLowerCase();
            const documents = this.projectDocuments.filter((document) => !query || [document.title, document.filename, document.note, document.source, document.uploaded_by]
                .some((value) => String(value || '').toLocaleLowerCase().includes(query))).sort((first, second) => this.compareDocuments(first, second));
            const categories = [...this.documentCategories];
            documents.forEach((document) => {
                if (!categories.some((category) => category.key === document.category)) categories.push({ key: document.category, label: 'Files', description: '' });
            });
            return categories.map((category) => ({ ...category, documents: documents.filter((document) => document.category === category.key) }))
                .filter((category) => category.documents.length);
        },
        get folderShortcuts() {
            const query = this.query.trim().toLocaleLowerCase();
            return this.shortcuts.filter(shortcut => shortcut.folder_id === this.currentFolder?.id).flatMap(shortcut => {
                const document = this.documentsByProject[shortcut.project_id]?.documents.find(item => item.key === shortcut.document_key);
                if (!document || (query && ![document.filename, document.title, document.note, document.source, document.uploaded_by].some(value => String(value || '').toLocaleLowerCase().includes(query)))) return [];
                return [{ ...document, ...shortcut, key: shortcut.id }];
            }).sort((first, second) => this.compareDocuments(first, second));
        },
        get selectedCount() { return this.selectedIds.length + Number(Boolean(this.selectedFolderId)) + Number(Boolean(this.selectedYear)) + Number(Boolean(this.selectedDocumentKey)) + Number(Boolean(this.selectedShortcutId)); },
        get selectedProject() { return this.selectedIds.length === 1 ? this.projects.find((project) => project.id === this.selectedIds[0]) || null : null; },
        get selectedFolder() { return this.folders.find((folder) => folder.id === this.selectedFolderId) || null; },
        get selectedDocument() { return this.projectDocuments.find((document) => document.key === this.selectedDocumentKey) || null; },
        get selectedShortcut() { return this.folderShortcuts.find(shortcut => shortcut.id === this.selectedShortcutId) || null; },
        get canOpenSelection() { return this.selectedCount === 1 && Boolean(this.selectedFolder || this.selectedYear || this.selectedDocument || this.selectedProject || this.selectedShortcut); },
        get canMoveSelection() { return Boolean(this.selectedFolder || this.selectedIds.length || this.selectedShortcut); },
        get canRenameSelection() { return Boolean(this.selectedFolder); },
        get canCopySelection() { return !this.saving && !this.stale && Boolean(this.selectedFolder || this.selectedIds.length || this.selectedDocument || this.selectedShortcut); },
        get canCutSelection() { return this.canCopySelection && !this.selectedDocument; },
        get canPaste() { return !this.saving && !this.stale && Boolean(this.clipboard) && !this.activeProject && (Boolean(this.currentFolder) || (this.location === 'all' && (this.clipboard.type === 'folder' || (this.clipboard.type === 'projects' && this.clipboard.mode === 'cut')))); },
        get selectionLabel() { return this.selectedFolder?.name || this.selectedProject?.title || this.selectedDocument?.filename || this.selectedShortcut?.filename || this.selectedYear || (this.selectedCount ? `${this.selectedCount} projects selected` : ''); },
        get locationLabel() { return this.activeProject?.title || this.currentFolder?.name || (this.location.startsWith('year:') ? this.location.slice(5) : this.location === 'unfiled' ? 'Unfiled' : 'Projects'); },
        get canGoBack() { return this.locationHistory.slice(0, this.historyIndex).some((id) => this.isLocation(id)); },
        get canGoUp() { return this.location !== 'all'; },
        get canUndo() { return Boolean(this.undoWorkspace) && !this.saving && !this.stale; },
        get unfiledCount() {
            const filed = new Set(this.workspace.assignments.map((assignment) => assignment.project_id));
            return this.projects.filter((project) => !filed.has(project.id)).length;
        },

        get folderTree() {
            const children = new Map();
            this.folders.forEach((folder) => {
                const parentId = folder.parent_id;
                if (!children.has(parentId)) children.set(parentId, []);
                children.get(parentId).push(folder);
            });
            const tree = [];
            const visited = new Set();
            const appendChildren = (parentId, depth) => {
                (children.get(parentId) || []).sort(byName).forEach((folder) => {
                    if (visited.has(folder.id)) return;
                    visited.add(folder.id);
                    tree.push({ ...folder, depth });
                    appendChildren(folder.id, depth + 1);
                });
            };
            appendChildren(null, 0);
            return tree;
        },

        get breadcrumbs() {
            if (this.currentFolder) return this.folderPath(this.currentFolder.id);
            if (this.activeProject) {
                const parent = this.projectParentLocations[this.location];
                const path = this.folders.some((folder) => folder.id === parent) ? this.folderPath(parent)
                    : parent === 'unfiled' ? [{ id: 'unfiled', name: 'Unfiled' }]
                        : [{ id: `year:${projectYear(this.activeProject)}`, name: projectYear(this.activeProject) }];
                return [...path, { id: this.location, name: this.activeProject.title }];
            }
            return this.location === 'all' ? [] : [{ id: this.location, name: this.locationLabel }];
        },
        get childFolders() {
            if (this.location !== 'all' && !this.currentFolder) return [];
            const query = this.query.trim().toLocaleLowerCase();
            return this.folders.filter((folder) => folder.parent_id === (this.currentFolder?.id || null) && (!query || folder.name.toLocaleLowerCase().includes(query))).sort(byName);
        },

        get years() {
            const counts = new Map();
            this.projects.forEach((project) => {
                const year = projectYear(project);
                counts.set(year, (counts.get(year) || 0) + 1);
            });
            return [...counts].map(([year, count]) => ({ year, count,
                needs_attention: this.projects.some(project => projectYear(project) === year && this.hasAttention(project)),
                has_new_activity: this.projects.some(project => projectYear(project) === year && project.has_new_activity),
            }))
                .sort((first, second) => second.year.localeCompare(first.year, undefined, { numeric: true }));
        },

        get filteredProjects() {
            const query = this.query.trim().toLocaleLowerCase();
            const filed = new Set(this.workspace.assignments.map(item => item.project_id));
            const currentFolder = this.currentFolder;
            const folderProjects = currentFolder ? new Set(this.workspace.assignments.filter(item => item.folder_id === currentFolder.id).map(item => item.project_id)) : null;
            const result = this.projects.filter((project) => {
                if (this.location === 'unfiled' && filed.has(project.id)) return false;
                if (this.location.startsWith('year:') && projectYear(project) !== this.location.slice(5)) return false;
                if (folderProjects && !folderProjects.has(project.id)) return false;
                if (this.statusFilter !== 'all' && project.status !== this.statusFilter) return false;
                return !query || [project.title, project.researcher, project.call, project.secretary]
                    .some((value) => String(value || '').toLocaleLowerCase().includes(query));
            });
            return result.sort((first, second) => {
                const titleOrder = () => first.title.localeCompare(second.title, undefined, { sensitivity: 'base', numeric: true });
                if (this.sort === 'title') return titleOrder();
                if (this.sort === 'oldest') return projectDate(first) - projectDate(second) || titleOrder();
                if (this.sort === 'newest') return projectDate(second) - projectDate(first) || titleOrder();
                return (Number(second.pending) || 0) - (Number(first.pending) || 0)
                    || Number(second.status === 'delayed') - Number(first.status === 'delayed')
                    || projectDate(second) - projectDate(first) || titleOrder();
            });
        },

        get pageCount() { return Math.max(1, Math.ceil(this.filteredProjects.length / this.pageSize)); },
        get pagedProjects() {
            const page = Math.min(this.page, this.pageCount);
            return this.filteredProjects.slice((page - 1) * this.pageSize, page * this.pageSize);
        },
        get projectGroups() {
            if (this.grouping === 'none') return [{ label: 'Projects', projects: this.pagedProjects }];
            const groups = new Map();
            this.pagedProjects.forEach((project) => {
                const year = projectYear(project);
                if (!groups.has(year)) groups.set(year, []);
                groups.get(year).push(project);
            });
            return [...groups].map(([label, projects]) => ({ label, projects }))
                .sort((first, second) => second.label.localeCompare(first.label, undefined, { numeric: true }));
        },

        get destinationFolders() {
            const excluded = this.selectedFolderId ? this.descendantIds(this.selectedFolderId) : new Set();
            return this.folderTree.filter((folder) => !excluded.has(folder.id));
        },

        folderPath(id) {
            const path = [];
            const visited = new Set();
            let folder = this.folders.find((item) => item.id === id);
            while (folder && !visited.has(folder.id)) {
                visited.add(folder.id);
                path.unshift(folder);
                folder = this.folders.find((item) => item.id === folder.parent_id);
            }
            return path;
        },

        descendantIds(id) {
            const descendants = new Set([id]);
            const visit = (parentId) => this.folders.filter((folder) => folder.parent_id === parentId).forEach((folder) => {
                if (descendants.has(folder.id)) return;
                descendants.add(folder.id);
                visit(folder.id);
            });
            visit(id);
            return descendants;
        },

        hasAttention(item) { return Boolean(item?.has_new_activity || item?.needs_attention || Number(item?.pending) > 0); },
        folderHasNewActivity(id) {
            const descendants = this.descendantIds(id);
            const unreadProjectIds = new Set(this.projects.filter(project => project.has_new_activity).map(project => project.id));
            return this.workspace.assignments.some(item => descendants.has(item.folder_id) && unreadProjectIds.has(item.project_id));
        },
        folderNeedsAttention(id) {
            const descendants = this.descendantIds(id);
            const projectIds = new Set(this.workspace.assignments.filter(item => descendants.has(item.folder_id)).map(item => item.project_id));
            const shortcuts = this.shortcuts.filter(item => descendants.has(item.folder_id));
            return this.projects.some(project => projectIds.has(project.id) && this.hasAttention(project))
                || shortcuts.some(shortcut => this.documentsByProject[shortcut.project_id]?.documents.some(document => document.key === shortcut.document_key && this.hasAttention(document)));
        },
        folderDepth(id) { return Math.max(0, this.folderPath(id).length - 1); },
        folderCount(id) {
            const descendants = this.descendantIds(id);
            const projectIds = new Set(this.projects.map((project) => project.id));
            return new Set(this.workspace.assignments.filter((item) => descendants.has(item.folder_id) && projectIds.has(item.project_id)).map(item => item.project_id)).size;
        },
        projectFolderLabel(id) {
            const assignments = this.workspace.assignments.filter((item) => item.project_id === id);
            return assignments.length ? assignments.map(assignment => this.folderPath(assignment.folder_id).map(folder => folder.name).join(' / ')).join(' · ') : 'Unfiled';
        },

        isLocation(id) { return ['all', 'unfiled'].includes(id) || this.years.some((item) => `year:${item.year}` === id) || this.folders.some((folder) => folder.id === id) || this.projects.some((project) => `project:${project.id}` === id); },
        openLocation(id, remember = true) {
            if (!this.isLocation(id)) return;
            if (id !== this.location) this.query = '';
            if (remember && id !== this.location && id.startsWith('project:') && !this.activeProject) this.projectParentLocations[id] = this.location;
            if (remember && id !== this.location) {
                this.locationHistory = [...this.locationHistory.slice(0, this.historyIndex + 1), id];
                this.historyIndex = this.locationHistory.length - 1;
            }
            this.location = id;
            this.page = 1;
            this.clearSelection();
            this.dropTarget = null;
            this.previewDocument = null;
            this.documentsLoading = false;
            this.documentsError = '';
            if (this.activeProject) return this.loadDocuments();
            if (this.currentFolder) return this.loadShortcuts();
        },
        back() {
            while (this.historyIndex > 0) {
                this.historyIndex--;
                if (this.isLocation(this.locationHistory[this.historyIndex])) {
                    return this.openLocation(this.locationHistory[this.historyIndex], false);
                }
            }
        },
        up() {
            if (!this.canGoUp) return;
            const projectParent = this.projectParentLocations[this.location];
            this.openLocation(this.activeProject
                ? this.isLocation(projectParent) ? projectParent : `year:${projectYear(this.activeProject)}`
                : this.currentFolder?.parent_id || 'all');
        },
        setPage(page) { this.page = Math.max(1, Math.min(Number(page) || 1, this.pageCount)); this.clearSelection(); },
        resetFilters() { this.query = ''; this.statusFilter = 'all'; this.page = 1; this.clearSelection(); },
        clearSelection() { this.selectedIds = []; this.selectedFolderId = null; this.selectedYear = null; this.selectedDocumentKey = null; this.selectedShortcutId = null; },
        selectProject(id, event = false) {
            if (!this.projects.some((project) => project.id === id)) return;
            this.selectedFolderId = null;
            this.selectedYear = null;
            this.selectedDocumentKey = null;
            this.selectedShortcutId = null;
            this.selectedIds = additiveSelection(event)
                ? this.selectedIds.includes(id) ? this.selectedIds.filter((selectedId) => selectedId !== id) : [...this.selectedIds, id]
                : [id];
        },
        activateProject(id, event = null) {
            const project = this.projects.find((item) => item.id === id);
            if (!project) return;
            if (additiveSelection(event)) { this.selectProject(id, event); return; }
            return this.openLocation(`project:${project.id}`);
        },
        selectAll() {
            this.clearSelection();
            if (this.activeProject || (this.location === 'all' && this.grouping === 'year' && !this.query && this.statusFilter === 'all')) return;
            this.selectedIds = this.filteredProjects.map((project) => project.id);
        },
        selectFolder(id, event = false) {
            this.selectedIds = [];
            this.selectedYear = null;
            this.selectedDocumentKey = null;
            this.selectedShortcutId = null;
            this.selectedFolderId = this.folders.some((folder) => folder.id === id)
                && !(additiveSelection(event) && this.selectedFolderId === id) ? id : null;
        },
        selectYear(year, event = false) {
            const selected = this.selectedYear;
            this.clearSelection();
            if (this.years.some((item) => item.year === year) && !(additiveSelection(event) && selected === year)) this.selectedYear = year;
        },
        selectDocument(key, event = false) {
            const selected = this.selectedDocumentKey;
            this.clearSelection();
            if (this.projectDocuments.some((document) => document.key === key) && !(additiveSelection(event) && selected === key)) this.selectedDocumentKey = key;
        },
        selectShortcut(id) {
            this.clearSelection();
            if (this.folderShortcuts.some(shortcut => shortcut.id === id)) this.selectedShortcutId = id;
        },
        selectContextItem(type, id) {
            if (type === 'project') { if (!this.selectedIds.includes(Number(id))) this.selectProject(Number(id)); }
            else if (type === 'folder') this.selectFolder(id);
            else if (type === 'year') this.selectYear(id);
            else if (type === 'shortcut') this.selectShortcut(id);
            else if (type === 'document') this.selectDocument(id);
        },
        prepareContextMenu(event) {
            const item = event.target?.closest?.('[data-workspace-item]');
            if (item) this.selectContextItem(item.dataset.itemType, item.dataset.itemId);
            else if (event.target?.closest?.('[data-workspace-items]')) this.clearSelection();
        },
        openSelection() {
            if (!this.canOpenSelection) return;
            if (this.selectedFolder) return this.openLocation(this.selectedFolder.id);
            if (this.selectedYear) return this.openLocation(`year:${this.selectedYear}`);
            if (this.selectedDocument) return this.activateDocument(this.selectedDocument.key);
            if (this.selectedShortcut) return this.openShortcut(this.selectedShortcut.id);
            return this.activateProject(this.selectedProject.id);
        },
        activateDocument(key) { this.previewDocument = this.projectDocuments.find((document) => document.key === key) || null; },
        openDocument(key) { this.activateDocument(typeof key === 'object' ? key?.key : key); },
        closePreview() { this.previewDocument = null; },
        openShortcut(id) {
            const shortcut = this.folderShortcuts.find(item => item.id === id);
            this.previewDocument = shortcut ? this.documentsByProject[shortcut.project_id]?.documents.find(item => item.key === shortcut.document_key) || null : null;
        },
        compareDocuments(first, second) {
            const byFilename = () => String(first.filename || '').localeCompare(String(second.filename || ''), undefined, { sensitivity: 'base', numeric: true });
            if (this.documentSort === 'size') return (Number(second.file_size) || 0) - (Number(first.file_size) || 0) || byFilename();
            if (this.documentSort === 'newest') return (Date.parse(second.uploaded_at) || 0) - (Date.parse(first.uploaded_at) || 0) || byFilename();
            if (this.documentSort === 'oldest') return (Date.parse(first.uploaded_at) || 0) - (Date.parse(second.uploaded_at) || 0) || byFilename();
            return byFilename();
        },
        async loadShortcuts() {
            const folderId = this.currentFolder?.id;
            if (!folderId) return false;
            const projectIds = new Set(this.shortcuts.filter(shortcut => shortcut.folder_id === folderId).map(shortcut => shortcut.project_id));
            if (!projectIds.size) return true;
            this.documentsLoading = true;
            this.documentsError = '';
            const loaded = await Promise.all([...projectIds].map(id => this.loadDocuments(this.projects.find(project => project.id === id)
                || { id, documents_url: config.documentsUrlTemplate?.replace('__PROJECT__', id) })));
            const missing = this.shortcuts.some(shortcut => shortcut.folder_id === folderId && !this.documentsByProject[shortcut.project_id]?.documents.some(document => document.key === shortcut.document_key));
            if (this.location === folderId) {
                this.documentsLoading = false;
                if (missing || loaded.some(result => !result)) this.documentsError = 'Some document shortcuts could not be loaded. Try again to check source access.';
            }
            return !missing && loaded.every(Boolean);
        },
        async loadDocuments(project = this.activeProject) {
            if (!project) return false;
            const active = () => this.activeProject?.id === project.id;
            if (this.documentsByProject[project.id]) return true;
            if (active()) { this.documentsLoading = true; this.documentsError = ''; }
            if (documentRequests.has(project.id)) return documentRequests.get(project.id);
            const request = (async () => {
                try {
                    const url = project.documents_url || project.documentsUrl;
                    if (!url) throw new Error('Documents are unavailable for this project.');
                    const response = await fetch(url, { method: 'GET', credentials: 'same-origin', headers: { Accept: 'application/json' } });
                    const payload = await response.json();
                    if (!response.ok) throw new Error(payload.message || 'Documents could not be loaded. Please try again.');
                    if (!Array.isArray(payload.documents) || !Array.isArray(payload.categories)) throw new Error('The document response was incomplete. Please try again.');
                    this.documentsByProject = { ...this.documentsByProject, [project.id]: payload };
                    return true;
                } catch (error) {
                    if (active()) this.documentsError = error instanceof Error ? error.message : 'Documents could not be loaded. Please try again.';
                    return false;
                } finally {
                    if (active()) this.documentsLoading = false;
                }
            })().finally(() => { documentRequests.delete(project.id); });
            documentRequests.set(project.id, request);
            return request;
        },
        retryDocuments() {
            if (!this.activeProject) {
                this.shortcuts.filter(shortcut => shortcut.folder_id === this.currentFolder?.id).forEach(shortcut => { delete this.documentsByProject[shortcut.project_id]; });
                return this.loadShortcuts();
            }
            delete this.documentsByProject[this.activeProject.id];
            return this.loadDocuments();
        },
        copySelection(mode = 'copy') {
            if (!['copy', 'cut'].includes(mode) || !(mode === 'cut' ? this.canCutSelection : this.canCopySelection)) return false;
            const sourceFolderId = this.currentFolder?.id || null;
            if (this.selectedFolder) this.clipboard = { mode, type: 'folder', id: this.selectedFolder.id };
            else if (this.selectedShortcut) this.clipboard = { mode, type: 'shortcut', id: this.selectedShortcut.id };
            else if (this.selectedDocument) this.clipboard = { mode, type: 'document', project_id: this.activeProject.id, document_key: this.selectedDocument.key };
            else this.clipboard = { mode, type: 'projects', ids: [...this.selectedIds], sourceFolderId };
            this.notice = mode === 'cut' ? 'Ready to move. Choose a destination and paste.' : 'Copied. Choose a personal folder and paste.';
            return true;
        },
        cancelClipboard() { this.clipboard = null; },
        isCutItem(type, id) {
            const pending = this.clipboard;
            if (pending?.mode !== 'cut') return false;
            return type === 'project' ? pending.type === 'projects' && pending.ids.includes(Number(id)) && pending.sourceFolderId === (this.currentFolder?.id || null)
                : pending.type === type && pending.id === id;
        },
        async pasteSelection(destinationId = this.currentFolder?.id || null) {
            const pending = this.clipboard;
            if (!pending || this.saving || this.stale || !this.validDestination(destinationId)) return false;
            if (!destinationId && (['document', 'shortcut'].includes(pending.type) || (pending.type === 'projects' && pending.mode === 'copy'))) {
                this.error = 'Choose a personal folder before pasting these items.';
                return false;
            }
            let saved = false;
            if (pending.type === 'projects') {
                if (!pending.ids.length || pending.ids.some(id => !this.projects.some(project => project.id === id))) return false;
                if (pending.mode === 'cut' && pending.sourceFolderId && pending.ids.some(id => !this.workspace.assignments.some(item => item.project_id === id && item.folder_id === pending.sourceFolderId))) {
                    this.error = 'The cut items are no longer in their original folder. Select them again.';
                    return false;
                }
                if (pending.mode === 'cut') saved = await this.moveProjects(pending.ids, destinationId, pending.sourceFolderId);
                else saved = await this.saveChange(next => {
                    pending.ids.forEach(id => {
                        if (!next.assignments.some(item => item.project_id === id && item.folder_id === destinationId)) next.assignments.push({ project_id: id, folder_id: destinationId });
                    });
                }, 'Projects copied to this folder.');
            } else if (pending.type === 'folder') {
                const folder = this.folders.find(item => item.id === pending.id);
                if (!folder) { this.error = 'The copied folder is no longer available.'; return false; }
                if (pending.mode === 'cut') saved = await this.moveFolder(folder.id, destinationId);
                else {
                    const descendants = this.descendantIds(folder.id);
                    if (this.folders.length + descendants.size > 200) { this.error = 'Your workspace can contain no more than 200 folders.'; return false; }
                    const folders = this.folders.filter(item => descendants.has(item.id));
                    const assignments = this.workspace.assignments.filter(item => descendants.has(item.folder_id));
                    const shortcuts = this.shortcuts.filter(item => descendants.has(item.folder_id));
                    const ids = new Map(folders.map(item => [item.id, newFolderId()]));
                    let index = 1;
                    let name;
                    do {
                        const suffix = index === 1 ? ' (copy)' : ` (copy ${index})`;
                        name = Array.from(folder.name).slice(0, 80 - suffix.length).join('').trimEnd() + suffix;
                        index++;
                    } while (this.folders.some(item => item.parent_id === destinationId && item.name.toLocaleLowerCase() === name.toLocaleLowerCase()));
                    saved = await this.saveChange(next => {
                        folders.forEach(item => next.folders.push({ ...item, id: ids.get(item.id), parent_id: item.id === folder.id ? destinationId : ids.get(item.parent_id), name: item.id === folder.id ? name : item.name }));
                        assignments.forEach(item => next.assignments.push({ ...item, folder_id: ids.get(item.folder_id) }));
                        next.shortcuts ||= [];
                        shortcuts.forEach(item => next.shortcuts.push({ ...item, id: newFolderId(), folder_id: ids.get(item.folder_id) }));
                    }, 'Folder copied.');
                }
            } else if (['document', 'shortcut'].includes(pending.type)) {
                const shortcut = pending.type === 'shortcut' ? this.shortcuts.find(item => item.id === pending.id) : pending;
                const document = shortcut && this.documentsByProject[shortcut.project_id]?.documents.find(item => item.key === shortcut.document_key);
                if (!document) { this.error = 'The copied document is no longer available. Open its project and try again.'; return false; }
                saved = await this.saveChange(next => {
                    next.shortcuts ||= [];
                    const duplicate = next.shortcuts.find(item => item.folder_id === destinationId && item.project_id === shortcut.project_id && item.document_key === shortcut.document_key);
                    if (pending.mode === 'cut') {
                        if (duplicate && duplicate.id !== shortcut.id) next.shortcuts = next.shortcuts.filter(item => item.id !== shortcut.id);
                        else next.shortcuts.find(item => item.id === shortcut.id).folder_id = destinationId;
                    } else if (!duplicate) next.shortcuts.push({ id: newFolderId(), folder_id: destinationId, project_id: shortcut.project_id, document_key: shortcut.document_key });
                }, pending.mode === 'cut' ? 'Document shortcut moved.' : 'Document shortcut copied.');
            }
            if (saved) {
                if (pending.mode === 'cut') this.cancelClipboard();
                this.clearSelection();
                if (this.currentFolder) await this.loadShortcuts();
            }
            return saved;
        },
        handleWorkspaceKeydown(event) {
            if (this.viewMenuOpen && event.key === 'Escape') {
                event.preventDefault();
                event.stopPropagation();
                this.closeViewMenu();
                return;
            }
            if (event.altKey || this.dialog || this.previewDocument || event.target?.closest?.('input, textarea, select, [contenteditable]:not([contenteditable="false"]), [role="dialog"], [role="menu"], [data-pdf-annotation-config]')) return;
            const key = event.key.toLowerCase();
            const modifier = event.ctrlKey || event.metaKey;
            const selection = typeof window === 'undefined' ? null : window.getSelection?.();
            if (modifier && ['c', 'x'].includes(key) && selection?.toString() && event.currentTarget?.contains?.(selection.anchorNode)) return;
            const context = event.key === 'ContextMenu' || (event.shiftKey && event.key === 'F10');
            if (!context && !['Escape', 'F2', 'Delete'].includes(event.key) && !(modifier && ['c', 'x', 'v', 'z', 'a'].includes(key))) return;
            event.preventDefault();
            event.stopPropagation();
            if (context) this.openViewMenu(event);
            else if (event.key === 'Escape') { this.cancelClipboard(); this.clearSelection(); this.endDrag(); }
            else if (event.key === 'F2' && this.selectedFolder) { this.openRename(this.selectedFolder); if (this.dialog) this.$dispatch?.('open-modal', 'project-workspace-dialog'); }
            else if (event.key === 'Delete' && this.selectedFolder) { this.openDelete(this.selectedFolder); if (this.dialog) this.$dispatch?.('open-modal', 'project-workspace-dialog'); }
            else if (event.key === 'Delete' && this.selectedShortcut) return this.removeSelectedShortcut();
            else if (modifier && key === 'c') this.copySelection();
            else if (modifier && key === 'x') this.copySelection('cut');
            else if (modifier && key === 'v' && this.canPaste) return this.pasteSelection();
            else if (modifier && key === 'z') return this.undo();
            else if (modifier && key === 'a') this.selectAll();
        },
        get menuActions() {
            const dialog = (open) => () => { open(); if (this.dialog) this.$dispatch?.('open-modal', 'project-workspace-dialog'); };
            const actions = [];
            if (this.canOpenSelection) actions.push({ id: 'open', label: this.selectedDocument || this.selectedShortcut ? 'Preview' : 'Open', run: () => { this.openSelection(); if (this.previewDocument) this.$dispatch?.('open-modal', 'project-workspace-preview'); } });
            const document = this.selectedDocument || this.selectedShortcut;
            if (document) actions.push({ id: 'download', label: 'Download', href: document.download_url, download: document.generated_comment_response ? document.filename : null });
            if (this.canCutSelection) actions.push({ id: 'cut', label: 'Cut', shortcut: 'Ctrl+X', run: () => this.copySelection('cut') });
            if (this.canCopySelection) actions.push({ id: 'copy', label: this.selectedDocument ? 'Copy shortcut' : 'Copy', shortcut: 'Ctrl+C', run: () => this.copySelection() });
            if (this.clipboard) actions.push({ id: 'paste', label: 'Paste', shortcut: 'Ctrl+V', disabled: !this.canPaste, run: () => this.pasteSelection() });
            if (this.canMoveSelection) actions.push({ id: 'move', label: 'Move to…', disabled: this.saving || this.stale, run: dialog(() => this.openMove()) });
            if (this.selectedFolder) {
                actions.push({ id: 'rename', label: 'Rename', shortcut: 'F2', disabled: this.saving || this.stale, run: dialog(() => this.openRename(this.selectedFolder)) });
                actions.push({ id: 'remove', label: 'Remove folder', shortcut: 'Delete', disabled: this.saving || this.stale, run: dialog(() => this.openDelete(this.selectedFolder)) });
            }
            if (this.selectedShortcut) actions.push({ id: 'remove-shortcut', label: 'Remove shortcut', shortcut: 'Delete', disabled: this.saving || this.stale, run: () => this.removeSelectedShortcut() });
            if (!this.activeProject) actions.push({ id: 'new', label: 'New folder', disabled: this.saving || this.stale, run: dialog(() => this.openCreate()) });
            if (this.activeProject || this.currentFolder) actions.push({ id: 'refresh', label: 'Refresh documents', run: () => this.retryDocuments() });
            if (this.undoWorkspace) actions.push({ id: 'undo', label: 'Undo', shortcut: 'Ctrl+Z', disabled: !this.canUndo, run: () => this.undo() });
            return actions;
        },
        handleItemKeydown(event, type, id) {
            if (!['Enter', ' ', 'Escape', 'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(event.key) && !(event.key.toLowerCase() === 'a' && additiveSelection(event))) return;
            event.preventDefault();
            event.stopPropagation();
            if (event.key.startsWith('Arrow')) {
                const items = Array.from(event.currentTarget?.closest('[data-workspace-items]')?.querySelectorAll('[data-workspace-item]') || [])
                    .filter((item) => item.getClientRects().length);
                const index = items.indexOf(event.currentTarget);
                const direction = ['ArrowLeft', 'ArrowUp'].includes(event.key) ? -1 : 1;
                const item = items[Math.max(0, Math.min(items.length - 1, index + direction))];
                if (index < 0 || !item) return;
                item.focus();
                const itemId = item.dataset.itemId;
                if (item.dataset.itemType === 'folder') this.selectFolder(itemId);
                else if (item.dataset.itemType === 'year') this.selectYear(itemId);
                else if (item.dataset.itemType === 'document') this.selectDocument(itemId);
                else if (item.dataset.itemType === 'shortcut') this.selectShortcut(itemId);
                else this.selectProject(Number(itemId));
                return;
            }
            if (event.key === 'Escape') { this.cancelClipboard(); this.clearSelection(); this.endDrag(); }
            else if (event.key.toLowerCase() === 'a' && additiveSelection(event)) this.selectAll();
            else if (event.key === 'Enter') {
                if (type === 'folder') this.openLocation(id);
                else if (type === 'year') this.openLocation(`year:${id}`);
                else if (type === 'document') this.activateDocument(id);
                else if (type === 'shortcut') this.openShortcut(id);
                else this.activateProject(id, event);
            } else if (type === 'folder') this.selectFolder(id, event);
            else if (type === 'year') this.selectYear(id, event);
            else if (type === 'document') this.selectDocument(id, event);
            else if (type === 'shortcut') this.selectShortcut(id);
            else this.selectProject(id, event);
        },

        startProjectDrag(event, id) {
            this.endDrag();
            if (this.saving || this.stale || !this.projects.some((project) => project.id === id)) { event.preventDefault(); return; }
            if (!this.selectedIds.includes(id)) this.selectProject(id);
            this.dragging = { type: 'projects', ids: [...this.selectedIds] };
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData(dragType, JSON.stringify(this.dragging));
        },
        startFolderDrag(event, id) {
            this.endDrag();
            if (this.saving || this.stale || !this.folders.some((folder) => folder.id === id)) { event.preventDefault(); return; }
            this.selectFolder(id);
            this.dragging = { type: 'folder', id };
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData(dragType, JSON.stringify(this.dragging));
        },
        endDrag() { this.dragging = null; this.dropTarget = null; },
        canDrop(event, folderId = null) {
            if (this.saving || this.stale || !this.dragging || (folderId !== null && !this.folders.some((folder) => folder.id === folderId))) return false;
            const types = Array.from(event.dataTransfer?.types || []);
            if (!types.includes(dragType) || types.includes('Files')) return false;
            if (this.dragging.type !== 'folder') return true;
            const folder = this.folders.find((item) => item.id === this.dragging.id);
            return Boolean(folder) && !this.descendantIds(folder.id).has(folderId)
                && !this.folders.some((item) => item.id !== folder.id && item.parent_id === folderId && item.name.toLocaleLowerCase() === folder.name.toLocaleLowerCase());
        },
        dragOver(event, folderId = null) {
            event.stopPropagation?.();
            const allowed = this.canDrop(event, folderId);
            if (allowed) event.preventDefault();
            if (event.dataTransfer) event.dataTransfer.dropEffect = allowed ? 'move' : 'none';
            this.dropTarget = allowed ? folderId || 'root' : null;
        },
        dragLeave(event, folderId = null) {
            if (event.currentTarget?.contains(event.relatedTarget)) return;
            if (this.dropTarget === (folderId || 'root')) this.dropTarget = null;
        },
        async drop(event, folderId = null) {
            event.preventDefault();
            event.stopPropagation?.();
            const allowed = this.canDrop(event, folderId);
            let data;
            try { data = JSON.parse(event.dataTransfer.getData(dragType)); } catch { this.endDrag(); return false; }
            const matchesDrag = JSON.stringify(data) === JSON.stringify(this.dragging);
            this.endDrag();
            if (!allowed || !matchesDrag) return false;
            if (data?.type === 'folder' && typeof data.id === 'string') return this.moveFolder(data.id, folderId);
            if (data?.type !== 'projects' || !Array.isArray(data.ids) || !data.ids.length
                || data.ids.some((id) => !this.projects.some((project) => project.id === id))) return false;
            return this.moveProjects([...new Set(data.ids)], folderId);
        },

        validFolderName(name, parentId, excludeId = null) {
            if (!name || name.length > 80) { this.error = 'Use a folder name between 1 and 80 characters.'; return false; }
            if (this.folders.some((folder) => folder.id !== excludeId && folder.parent_id === parentId && folder.name.toLocaleLowerCase() === name.toLocaleLowerCase())) {
                this.error = 'A folder with the same name already exists here.';
                return false;
            }
            return true;
        },
        validDestination(id) {
            if (id !== null && !this.folders.some((folder) => folder.id === id)) { this.error = 'Choose an existing folder.'; return false; }
            return true;
        },
        openCreate(parentId = this.currentFolder?.id || null) {
            if (this.saving || this.stale) return;
            this.dialog = 'create'; this.folderName = ''; this.folderParentId = parentId; this.error = '';
        },
        openRename(folder) {
            if (this.saving || this.stale || !this.folders.some((item) => item.id === folder?.id)) return;
            this.selectFolder(folder.id);
            this.dialog = 'rename'; this.folderName = folder.name; this.error = '';
        },
        openMove() {
            if (this.saving || this.stale || !this.canMoveSelection) return;
            this.dialog = 'move'; this.destinationId = ''; this.error = '';
        },
        openDelete(folder) {
            if (this.saving || this.stale || !this.folders.some((item) => item.id === folder?.id)) return;
            this.selectFolder(folder.id);
            this.dialog = 'delete'; this.error = '';
        },
        closeDialog() { if (!this.saving) this.dialog = null; },
        async submitDialog() {
            if (this.saving || this.stale) return false;
            let saved = false;
            if (this.dialog === 'create' || this.dialog === 'rename') {
                const folder = this.folders.find((item) => item.id === this.selectedFolderId);
                const parentId = this.dialog === 'create' ? this.folderParentId : folder?.parent_id;
                const name = this.folderName.trim();
                if (parentId === undefined || !this.validDestination(parentId) || !this.validFolderName(name, parentId, this.dialog === 'rename' ? folder.id : null)) return false;
                const creating = this.dialog === 'create';
                const createdId = creating ? newFolderId() : null;
                saved = await this.saveChange((next) => {
                    if (creating) next.folders.push({ id: createdId, name, parent_id: parentId });
                    else next.folders.find((item) => item.id === folder.id).name = name;
                }, creating ? 'Folder created.' : 'Folder renamed.');
                if (saved && creating) {
                    if (!this.currentFolder && this.location !== 'all') this.openLocation('all');
                    this.selectFolder(createdId);
                }
            } else if (this.dialog === 'move') {
                saved = this.selectedShortcutId ? await this.moveShortcut(this.selectedShortcutId, this.destinationId || null) : this.selectedFolderId
                    ? await this.moveFolder(this.selectedFolderId, this.destinationId || null)
                    : await this.moveSelected(this.destinationId || null);
            } else if (this.dialog === 'delete') {
                if (!this.folders.some((folder) => folder.id === this.selectedFolderId)) return false;
                const descendants = this.descendantIds(this.selectedFolderId);
                saved = await this.saveChange((next) => {
                    next.folders = next.folders.filter((folder) => !descendants.has(folder.id));
                    next.assignments = next.assignments.filter((item) => !descendants.has(item.folder_id));
                    next.shortcuts = (next.shortcuts || []).filter(item => !descendants.has(item.folder_id));
                }, 'Folder removed. Its projects are now unfiled.');
            }
            if (saved) this.dialog = null;
            return saved;
        },
        moveSelected(destinationId = null) { return this.moveProjects([...this.selectedIds], destinationId); },
        async moveProjects(ids, destinationId = null, sourceFolderId = this.currentFolder?.id || null) {
            if (!ids.length || ids.some((id) => !this.projects.some((project) => project.id === id)) || !this.validDestination(destinationId)) return false;
            if (sourceFolderId && sourceFolderId === destinationId) { this.clearSelection(); return true; }
            const saved = await this.saveChange((next) => {
                next.assignments = next.assignments.filter((item) => !ids.includes(item.project_id) || (sourceFolderId && item.folder_id !== sourceFolderId));
                if (destinationId) ids.forEach((id) => { if (!next.assignments.some(item => item.project_id === id && item.folder_id === destinationId)) next.assignments.push({ project_id: id, folder_id: destinationId }); });
            }, `${ids.length} ${ids.length === 1 ? 'project moved' : 'projects moved'}.`);
            if (saved) this.clearSelection();
            return saved;
        },
        async moveShortcut(id, destinationId) {
            if (!destinationId || !this.validDestination(destinationId) || !this.shortcuts.some(item => item.id === id)) { this.error = 'Choose a personal folder for this shortcut.'; return false; }
            const saved = await this.saveChange(next => {
                const shortcut = next.shortcuts.find(item => item.id === id);
                const duplicate = next.shortcuts.some(item => item.id !== id && item.folder_id === destinationId && item.project_id === shortcut.project_id && item.document_key === shortcut.document_key);
                if (duplicate) next.shortcuts = next.shortcuts.filter(item => item.id !== id);
                else shortcut.folder_id = destinationId;
            }, 'Document shortcut moved.');
            if (saved) this.clearSelection();
            return saved;
        },
        async removeSelectedShortcut() {
            const id = this.selectedShortcut?.id;
            if (!id) return false;
            const saved = await this.saveChange(next => { next.shortcuts = next.shortcuts.filter(shortcut => shortcut.id !== id); }, 'Document shortcut removed. The original document is kept.');
            if (saved) this.clearSelection();
            return saved;
        },
        async moveFolder(folderId, destinationId = null) {
            const folder = this.folders.find((item) => item.id === folderId);
            if (!folder || !this.validDestination(destinationId)) return false;
            if (this.descendantIds(folderId).has(destinationId)) { this.error = 'A folder cannot be moved inside itself or one of its subfolders.'; return false; }
            if (!this.validFolderName(folder.name, destinationId, folderId)) return false;
            const saved = await this.saveChange((next) => { next.folders.find((item) => item.id === folderId).parent_id = destinationId; }, 'Folder moved.');
            if (saved) this.clearSelection();
            return saved;
        },
        async setPreference(key, value) {
            if (!preferences[key]?.includes(value)) return false;
            const saved = await this.saveChange((next) => { next[key] = value; }, 'Workspace preference saved.');
            if (saved && key === 'grouping') this.clearSelection();
            return saved;
        },

        async saveChange(change, notice, rememberUndo = true) {
            if (this.saving || this.stale) return false;
            const previous = copy(this.workspace);
            const next = copy(previous);
            change(next);
            if (JSON.stringify(previous) === JSON.stringify(next)) return true;
            this.workspace = next;
            this.saving = true;
            this.error = '';
            this.notice = '';
            try {
                const response = await fetch(config.saveUrl, {
                    method: 'PUT',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                    body: JSON.stringify(next),
                });
                if (response.status === 409) {
                    this.stale = true;
                    throw new Error('Your workspace changed in another tab. Reload this page before organizing again.');
                }
                const payload = await response.json();
                if (!response.ok) throw new Error(Object.values(payload.errors || {}).flat().join(' ') || payload.message || 'Your workspace could not be saved. Please try again.');
                if (!payload.workspace || !Array.isArray(payload.workspace.folders) || !Array.isArray(payload.workspace.assignments)) throw new Error('The workspace response was incomplete. Reload the page to check your saved changes.');
                this.workspace = payload.workspace;
                this.undoWorkspace = rememberUndo ? previous : null;
                this.notice = notice;
                this.page = Math.min(this.page, this.pageCount);
                if (!this.isLocation(this.location)) this.openLocation('all');
                if (this.selectedFolderId && !this.folders.some((folder) => folder.id === this.selectedFolderId)) this.selectedFolderId = null;
                return true;
            } catch (error) {
                this.workspace = previous;
                this.error = error instanceof Error ? error.message : 'Your workspace could not be saved. Please try again.';
                return false;
            } finally {
                this.saving = false;
            }
        },
        async undo() {
            if (!this.canUndo) return false;
            const previous = copy(this.undoWorkspace);
            return this.saveChange((next) => { Object.assign(next, previous, { revision: next.revision }); }, 'Last change undone.', false);
        },
    };
}
