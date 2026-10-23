import { reportLibrary } from './report-library.js';

export function collegeReportLibrary(config = {}) {
    const display = reportLibrary(config.storageKey);
    const clone = value => JSON.parse(JSON.stringify(value));
    const clipboardKey = `${config.storageKey}:clipboard`;
    const sourceMap = new Map((config.sources || []).map(source => [source.id, source]));
    const editable = target => target?.closest?.('input, textarea, select, [contenteditable]:not([contenteditable="false"])');
    const newId = () => {
        if (crypto.randomUUID) return crypto.randomUUID();
        return '10000000-1000-4000-8000-100000000000'.replace(/[018]/g, digit => (digit ^ crypto.getRandomValues(new Uint8Array(1))[0] & 15 >> digit / 4).toString(16));
    };
    return {
        ...display,
        menuLabel: 'File actions',
        sources: config.sources || [],
        workspace: clone(config.workspace || { revision: 0, items: [] }),
        location: config.location ?? null,
        selectedIds: [],
        clipboard: null,
        menuDestination: config.location ?? null,
        sortKey: config.sortKey || 'name',
        sortDirection: config.sortDirection || 'asc',
        error: '',
        notice: '',
        init() {
            display.init();
            this.view = display.view;
            this.size = display.size;
            try {
                const saved = JSON.parse(localStorage.getItem(clipboardKey));
                if (['copy', 'cut'].includes(saved?.mode) && Array.isArray(saved.items) && saved.items.every(item => typeof item.id === 'string')) this.clipboard = saved;
            } catch {
                // Keep file actions usable when saved clipboard data is unavailable.
            }
        },
        get nodes() {
            const nodes = new Map(sourceMap);
            for (const item of this.workspace.items) {
                const source = sourceMap.get(item.source_id);
                if (source) nodes.set(item.id, { ...source, ...item });
            }
            return [...nodes.values()];
        },
        setSort(field) {
            if (!['name', 'status', 'report_count', 'submitted_at', 'submitted_by'].includes(field) || this.saving || this.stale) return;
            const url = new URL(window.location.href);
            const direction = field === this.sortKey ? (this.sortDirection === 'asc' ? 'desc' : 'asc') : field === 'submitted_at' ? 'desc' : 'asc';
            url.searchParams.set('sort', field);
            url.searchParams.set('direction', direction);
            url.searchParams.delete('page');
            window.location.assign(url.href);
        },
        get selection() { return this.nodes.filter(node => this.selectedIds.includes(node.id)); },
        get canPaste() { return Boolean(this.clipboard?.items.length) && !this.saving && !this.stale; },
        isCut(id) { return this.clipboard?.mode === 'cut' && this.clipboard.items.some(item => item.id === id); },
        selectItem(id, event = {}) {
            if (this.saving || this.stale || editable(event.target)) return;
            if (event.currentTarget?.tagName !== 'A' && event.target?.closest?.('a, button, summary')) return;
            this.selectedIds = event.ctrlKey || event.metaKey
                ? (this.selectedIds.includes(id) ? this.selectedIds.filter(selected => selected !== id) : [...this.selectedIds, id]) : [id];
        },
        prepareContextMenu(event) {
            const id = event.target?.closest?.('[data-library-item]')?.dataset.libraryItem;
            if (!id && event.currentTarget?.hasAttribute?.('data-library-view-surface')) this.selectedIds = [];
            else if (id && !this.selectedIds.includes(id)) this.selectedIds = [id];
            const node = this.nodes.find(node => node.id === id);
            this.menuDestination = node?.kind === 'folder' ? node.id : this.location;
        },
        itemUrl(node) {
            return node.kind === 'folder' && node.id !== node.source_id ? `${config.rootUrl}?folder=${encodeURIComponent(node.id)}` : node.url;
        },
        openSelection() {
            if (this.selection.length === 1) window.location.assign(this.itemUrl(this.selection[0]));
        },
        copySelection(mode = 'copy') {
            if (!['copy', 'cut'].includes(mode) || !this.selection.length || this.saving || this.stale) return false;
            const clipboard = { mode, items: this.selection.map(({ id, parent_id }) => ({ id, parent_id })) };
            try {
                localStorage.setItem(clipboardKey, JSON.stringify(clipboard));
            } catch {
                this.error = 'Browser storage is unavailable. Allow site storage to copy or cut between folders.';
                return false;
            }
            this.clipboard = clipboard;
            this.error = '';
            this.notice = mode === 'cut' ? 'Cut. Open the destination folder and paste to move.' : 'Copied. Open the destination folder and paste.';
            return true;
        },
        cancelClipboard() {
            try { localStorage.removeItem(clipboardKey); } catch { /* The current clipboard can still be cleared. */ }
            this.clipboard = null;
            this.notice = '';
        },
        descendants(id) {
            const ids = new Set([id]);
            const nodes = this.nodes;
            let previous = 0;
            while (previous !== ids.size) {
                previous = ids.size;
                nodes.forEach(node => { if (ids.has(node.parent_id)) ids.add(node.id); });
            }
            return ids;
        },
        async pasteSelection(destinationId = this.location) {
            if (!this.canPaste) return false;
            const destination = this.nodes.find(node => node.id === destinationId);
            const pending = clone(this.clipboard);
            const selected = pending.items.map(item => this.nodes.find(node => node.id === item.id));
            if ((destinationId !== null && destination?.kind !== 'folder') || selected.some(node => !node)) {
                this.error = 'Choose an existing destination folder and available items.';
                return false;
            }
            const roots = selected.filter(node => !selected.some(parent => parent.id !== node.id && this.descendants(parent.id).has(node.id)));
            if (roots.some(node => this.descendants(node.id).has(destinationId))) {
                this.error = 'A folder cannot be pasted inside itself.';
                return false;
            }
            if (pending.mode === 'cut' && roots.some(node => node.parent_id === destinationId || pending.items.find(item => item.id === node.id).parent_id !== node.parent_id)) {
                this.error = 'These items are already here or have moved since they were cut. Select them again.';
                return false;
            }
            const next = new Map(this.workspace.items.map(item => [item.id, clone(item)]));
            const names = new Set(this.nodes.filter(node => node.parent_id === destinationId).map(node => node.name.toLocaleLowerCase()));
            for (const root of roots) {
                let name = root.name;
                if (pending.mode === 'cut' && names.has(name.toLocaleLowerCase())) {
                    this.error = `The destination already contains “${name}”.`;
                    return false;
                }
                let index = 1;
                while (names.has(name.toLocaleLowerCase())) {
                    const suffix = index === 1 ? ' (copy)' : ` (copy ${index})`;
                    name = Array.from(root.name).slice(0, 80 - suffix.length).join('') + suffix;
                    index++;
                }
                names.add(name.toLocaleLowerCase());
                const branchIds = this.descendants(root.id);
                const branch = pending.mode === 'cut' ? [root] : this.nodes.filter(node => branchIds.has(node.id));
                const ids = new Map(branch.map(node => [node.id, pending.mode === 'cut' ? node.id : newId()]));
                for (const node of branch) {
                    const id = ids.get(node.id);
                    next.set(id, { id, source_id: node.source_id, parent_id: node.id === root.id ? destinationId : ids.get(node.parent_id), name: node.id === root.id ? name : node.name });
                }
            }
            if (next.size > 2000) {
                this.error = 'The report library can contain up to 2,000 organization changes.';
                return false;
            }
            if (!await this.save([...next.values()])) return false;
            if (pending.mode === 'cut') this.cancelClipboard();
            window.location.reload();
            return true;
        },
        async save(items) {
            this.saving = true;
            this.error = '';
            try {
                const response = await fetch(config.updateUrl, {
                    method: 'PUT', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
                    body: JSON.stringify({ revision: this.workspace.revision, items }),
                });
                const payload = await response.json();
                if (!response.ok) {
                    this.stale = response.status === 409;
                    throw new Error(Object.values(payload.errors || {}).flat()[0] || payload.message || 'The report library could not be saved.');
                }
                this.workspace = payload.workspace;
                return true;
            } catch (error) {
                this.error = error.message || 'The report library could not be saved. Try again.';
                return false;
            } finally {
                this.saving = false;
            }
        },
        handleFileKey(event) {
            if (editable(event.target) || event.target?.closest?.('button, summary') || this.saving || this.stale) return;
            const modifier = event.ctrlKey || event.metaKey;
            const key = event.key.toLowerCase();
            if (modifier && ['c', 'x', 'v', 'a'].includes(key)) {
                if (window.getSelection?.()?.toString()) return;
                event.preventDefault();
                if (key === 'v') this.pasteSelection();
                else if (key === 'a') this.selectedIds = config.visibleIds || this.nodes.filter(node => node.parent_id === this.location).map(node => node.id);
                else this.copySelection(key === 'x' ? 'cut' : 'copy');
            } else if (event.key === 'Escape') {
                this.cancelClipboard();
                this.selectedIds = [];
            } else if (event.key === 'Enter' && event.target?.matches?.('[data-library-item]')) {
                event.preventDefault();
                this.selectedIds = [event.target.dataset.libraryItem];
                this.openSelection();
            }
        },
        get menuActions() {
            const node = this.selection.length === 1 ? this.selection[0] : null;
            return [
                ...(node ? [{ id: 'open', label: node.kind === 'folder' ? 'Open' : 'Preview', icon: node.kind === 'folder' ? 'open' : 'preview', run: () => this.openSelection() }] : []),
                ...(node?.download_url ? [{ id: 'download', label: 'Download', href: node.download_url }] : []),
                { id: 'cut', label: 'Cut', shortcut: 'Ctrl+X', disabled: !this.selection.length, run: () => this.copySelection('cut') },
                { id: 'copy', label: 'Copy', shortcut: 'Ctrl+C', disabled: !this.selection.length, run: () => this.copySelection() },
                { id: 'paste', label: 'Paste', shortcut: 'Ctrl+V', disabled: !this.canPaste, run: () => this.pasteSelection(this.menuDestination) },
            ];
        },
    };
}
