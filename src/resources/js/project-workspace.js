export function projectWorkspace(config = {}) {
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
        projects: config.projects || [],
        workspace: initialWorkspace,
        location: 'all',
        locationHistory: ['all'],
        historyIndex: 0,
        query: '',
        statusFilter: 'all',
        page: 1,
        pageSize: 24,
        selectedIds: [],
        selectedFolderId: null,
        selectedYear: null,
        selectedDocumentKey: null,
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
            this.$watch?.('query', () => { this.page = 1; this.clearSelection(); });
            this.$watch?.('statusFilter', () => { this.page = 1; this.clearSelection(); });
        },

        get folders() { return this.workspace.folders; },
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
                .some((value) => String(value || '').toLocaleLowerCase().includes(query)));
            const categories = [...this.documentCategories];
            documents.forEach((document) => {
                if (!categories.some((category) => category.key === document.category)) categories.push({ key: document.category, label: 'Files', description: '' });
            });
            return categories.map((category) => ({ ...category, documents: documents.filter((document) => document.category === category.key) }))
                .filter((category) => category.documents.length);
        },
        get selectedCount() { return this.selectedIds.length + Number(Boolean(this.selectedFolderId)) + Number(Boolean(this.selectedYear)) + Number(Boolean(this.selectedDocumentKey)); },
        get selectedProject() { return this.selectedIds.length === 1 ? this.projects.find((project) => project.id === this.selectedIds[0]) || null : null; },
        get selectedFolder() { return this.folders.find((folder) => folder.id === this.selectedFolderId) || null; },
        get selectedDocument() { return this.projectDocuments.find((document) => document.key === this.selectedDocumentKey) || null; },
        get canOpenSelection() { return this.selectedCount === 1 && Boolean(this.selectedFolder || this.selectedYear || this.selectedDocument || this.selectedProject); },
        get canMoveSelection() { return Boolean(this.selectedFolder || this.selectedIds.length); },
        get canRenameSelection() { return Boolean(this.selectedFolder); },
        get selectionLabel() { return this.selectedFolder?.name || this.selectedProject?.title || this.selectedDocument?.filename || this.selectedYear || (this.selectedCount ? `${this.selectedCount} projects selected` : ''); },
        get locationLabel() { return this.activeProject?.title || this.currentFolder?.name || (this.location.startsWith('year:') ? this.location.slice(5) : this.location === 'unfiled' ? 'Unfiled' : 'Projects'); },
        get canGoBack() { return this.locationHistory.slice(0, this.historyIndex).some((id) => this.isLocation(id)); },
        get canGoUp() { return this.location !== 'all'; },
        get canUndo() { return Boolean(this.undoWorkspace) && !this.saving && !this.stale; },
        get unfiledCount() {
            const filed = new Set(this.workspace.assignments.map((assignment) => assignment.project_id));
            return this.projects.filter((project) => !filed.has(project.id)).length;
        },

        get folderTree() {
            const tree = [];
            const visited = new Set();
            const appendChildren = (parentId, depth) => {
                this.folders.filter((folder) => folder.parent_id === parentId).sort(byName).forEach((folder) => {
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
            return this.folders.filter((folder) => folder.parent_id === (this.currentFolder?.id || null)).sort(byName);
        },

        get years() {
            const counts = new Map();
            this.projects.forEach((project) => {
                const year = projectYear(project);
                counts.set(year, (counts.get(year) || 0) + 1);
            });
            return [...counts].map(([year, count]) => ({ year, count }))
                .sort((first, second) => second.year.localeCompare(first.year, undefined, { numeric: true }));
        },

        get filteredProjects() {
            const query = this.query.trim().toLocaleLowerCase();
            const assignments = new Map(this.workspace.assignments.map((item) => [item.project_id, item.folder_id]));
            const result = this.projects.filter((project) => {
                if (this.location === 'unfiled' && assignments.has(project.id)) return false;
                if (this.location.startsWith('year:') && projectYear(project) !== this.location.slice(5)) return false;
                if (this.currentFolder && assignments.get(project.id) !== this.currentFolder.id) return false;
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

        folderDepth(id) { return Math.max(0, this.folderPath(id).length - 1); },
        folderCount(id) {
            const descendants = this.descendantIds(id);
            const projectIds = new Set(this.projects.map((project) => project.id));
            return this.workspace.assignments.filter((item) => descendants.has(item.folder_id) && projectIds.has(item.project_id)).length;
        },
        projectFolderLabel(id) {
            const assignment = this.workspace.assignments.find((item) => item.project_id === id);
            return assignment ? this.folderPath(assignment.folder_id).map((folder) => folder.name).join(' / ') : 'Unfiled';
        },

        isLocation(id) { return ['all', 'unfiled'].includes(id) || this.years.some((item) => `year:${item.year}` === id) || this.folders.some((folder) => folder.id === id) || this.projects.some((project) => `project:${project.id}` === id); },
        openLocation(id, remember = true) {
            if (!this.isLocation(id)) return;
            if (id !== this.location && (id.startsWith('project:') || this.activeProject)) this.query = '';
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
        clearSelection() { this.selectedIds = []; this.selectedFolderId = null; this.selectedYear = null; this.selectedDocumentKey = null; },
        selectProject(id, event = false) {
            if (!this.projects.some((project) => project.id === id)) return;
            this.selectedFolderId = null;
            this.selectedYear = null;
            this.selectedDocumentKey = null;
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
        openSelection() {
            if (!this.canOpenSelection) return;
            if (this.selectedFolder) return this.openLocation(this.selectedFolder.id);
            if (this.selectedYear) return this.openLocation(`year:${this.selectedYear}`);
            if (this.selectedDocument) return this.activateDocument(this.selectedDocument.key);
            return this.activateProject(this.selectedProject.id);
        },
        activateDocument(key) { this.previewDocument = this.projectDocuments.find((document) => document.key === key) || null; },
        openDocument(key) { this.activateDocument(typeof key === 'object' ? key?.key : key); },
        closePreview() { this.previewDocument = null; },
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
            if (!this.activeProject) return Promise.resolve(false);
            delete this.documentsByProject[this.activeProject.id];
            return this.loadDocuments();
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
                else this.selectProject(Number(itemId));
                return;
            }
            if (event.key === 'Escape') { this.clearSelection(); this.endDrag(); }
            else if (event.key.toLowerCase() === 'a' && additiveSelection(event)) this.selectAll();
            else if (event.key === 'Enter') {
                if (type === 'folder') this.openLocation(id);
                else if (type === 'year') this.openLocation(`year:${id}`);
                else if (type === 'document') this.activateDocument(id);
                else this.activateProject(id, event);
            } else if (type === 'folder') this.selectFolder(id, event);
            else if (type === 'year') this.selectYear(id, event);
            else if (type === 'document') this.selectDocument(id, event);
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
            if (this.saving || this.stale || (!this.selectedIds.length && !this.selectedFolderId)) return;
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
                saved = this.selectedFolderId
                    ? await this.moveFolder(this.selectedFolderId, this.destinationId || null)
                    : await this.moveSelected(this.destinationId || null);
            } else if (this.dialog === 'delete') {
                if (!this.folders.some((folder) => folder.id === this.selectedFolderId)) return false;
                const descendants = this.descendantIds(this.selectedFolderId);
                saved = await this.saveChange((next) => {
                    next.folders = next.folders.filter((folder) => !descendants.has(folder.id));
                    next.assignments = next.assignments.filter((item) => !descendants.has(item.folder_id));
                }, 'Folder removed. Its projects are now unfiled.');
            }
            if (saved) this.dialog = null;
            return saved;
        },
        moveSelected(destinationId = null) { return this.moveProjects([...this.selectedIds], destinationId); },
        async moveProjects(ids, destinationId = null) {
            if (!ids.length || ids.some((id) => !this.projects.some((project) => project.id === id)) || !this.validDestination(destinationId)) return false;
            const saved = await this.saveChange((next) => {
                next.assignments = next.assignments.filter((item) => !ids.includes(item.project_id));
                if (destinationId) ids.forEach((id) => next.assignments.push({ project_id: id, folder_id: destinationId }));
            }, `${ids.length} ${ids.length === 1 ? 'project moved' : 'projects moved'}.`);
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
