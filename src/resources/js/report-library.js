export function reportLibrary(storageKey) {
    const choices = { view: ['grid', 'list'], size: ['small', 'medium', 'large'] };
    const editableTarget = target => Boolean(target?.closest?.('input, textarea, select, [contenteditable]:not([contenteditable="false"]), [data-pdf-annotation-config]'));
    let wheelDelta = 0;
    let menuTarget = null;

    return {
        view: 'grid',
        size: 'medium',
        viewMenuOpen: false,
        viewMenuReady: false,
        viewMenuX: 0,
        viewMenuY: 0,
        menuLabel: 'View',
        menuActions: [],
        saving: false,
        stale: false,
        init() {
            try {
                const saved = JSON.parse(localStorage.getItem(storageKey));
                for (const key of Object.keys(choices)) {
                    if (choices[key].includes(saved?.[key])) this[key] = saved[key];
                }
            } catch {
                // Layout controls remain usable when browser storage is unavailable.
            }
        },
        setPreference(key, value) {
            if (!Object.hasOwn(choices, key) || !choices[key].includes(value)) return;
            this[key] = value;
            try {
                localStorage.setItem(storageKey, JSON.stringify({ view: this.view, size: this.size }));
            } catch {
                // Keep the chosen layout for this page when storage cannot save it.
            }
        },
        setItemSize(size) {
            this.setPreference('size', size);
        },
        chooseViewOption(option) {
            if (!['list', ...choices.size].includes(option) || this.saving || this.stale) return;
            if (option !== 'list') this.setItemSize(option);
            const result = this.setPreference('view', option === 'list' ? 'list' : 'grid');
            this.closeViewMenu();
            return result;
        },
        resizeWithWheel(event) {
            if (!event.ctrlKey || event.shiftKey || !event.deltaY || Math.abs(event.deltaX) > Math.abs(event.deltaY) || editableTarget(event.target)) {
                wheelDelta = 0;
                return;
            }
            event.preventDefault();
            if (Math.sign(wheelDelta) !== Math.sign(event.deltaY)) wheelDelta = 0;
            wheelDelta += event.deltaY * (event.deltaMode === 1 ? 16 : event.deltaMode === 2 ? (event.currentTarget?.clientHeight || 800) : 1);
            if (Math.abs(wheelDelta) < 40) return;
            const options = ['list', ...choices.size];
            const current = options.indexOf(this.view === 'list' ? 'list' : this.size);
            const next = Math.max(0, Math.min(options.length - 1, current + (wheelDelta < 0 ? 1 : -1)));
            wheelDelta = 0;
            if (next !== current) this.chooseViewOption(options[next]);
        },
        openViewMenu(event) {
            if (this.saving || this.stale || editableTarget(event.target)) return;
            const selection = window.getSelection?.();
            if (selection?.toString() && event.currentTarget?.contains(selection.anchorNode)) return;
            event.preventDefault();
            this.viewMenuReady = false;
            this.prepareContextMenu?.(event);
            menuTarget = event.target?.closest?.('button, a, [tabindex], summary') || event.currentTarget;
            const anchor = menuTarget.getBoundingClientRect();
            this.viewMenuX = event.clientX || anchor.left;
            this.viewMenuY = event.clientY || anchor.bottom;
            this.viewMenuOpen = true;
            this.$nextTick(() => window.requestAnimationFrame(() => {
                const menu = this.$refs.libraryViewMenu;
                if (!menu || !this.viewMenuOpen) return;
                const bounds = menu.getBoundingClientRect();
                this.viewMenuX = Math.max(8, Math.min(this.viewMenuX, window.innerWidth - bounds.width - 8));
                this.viewMenuY = Math.max(8, Math.min(this.viewMenuY, window.innerHeight - bounds.height - 8));
                menu.querySelector(this.menuActions.length ? '[role="menuitem"]:not(:disabled)' : '[aria-checked="true"]')?.focus({ preventScroll: true });
                window.requestAnimationFrame(() => { this.viewMenuReady = this.viewMenuOpen; });
            }));
        },
        activateMenuAction(action) {
            if (action.disabled || this.saving || this.stale) return;
            this.closeViewMenu();
            return action.run?.();
        },
        documentDetails(item) {
            const date = Date.parse(item.uploaded_at);
            const bytes = Number(item.file_size);
            const size = !Number.isFinite(bytes) || item.file_size == null ? '' : bytes < 1024 ? `${bytes} B`
                : bytes < 1024 * 1024 ? `${(bytes / 1024).toFixed(1)} KB` : `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
            return [item.source, item.uploaded_by, Number.isNaN(date) ? '' : new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(date), size].filter(Boolean).join(' · ');
        },
        closeViewMenu(restoreFocus = true) {
            if (!this.viewMenuOpen) return;
            this.viewMenuOpen = false;
            this.viewMenuReady = false;
            if (restoreFocus) menuTarget?.focus({ preventScroll: true });
        },
        handleViewMenuKey(event) {
            if (event.key === 'Escape') {
                event.preventDefault();
                this.closeViewMenu();
            } else if (event.key === 'Tab') {
                this.closeViewMenu();
            } else if (['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) {
                event.preventDefault();
                const items = [...event.currentTarget.querySelectorAll('[role="menuitem"]:not(:disabled), [role="menuitemradio"]:not(:disabled)')];
                const current = items.indexOf(event.target);
                const next = event.key === 'Home' ? 0 : event.key === 'End' ? items.length - 1
                    : (current + (event.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length;
                items[next]?.focus({ preventScroll: true });
            }
        },
    };
}
