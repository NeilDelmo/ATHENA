import { reportLibrary } from './report-library.js';

export function projectDocumentDrawer(config = {}) {
    const display = reportLibrary(config.displayStorageKey);

    return {
        ...display,
        inline: Boolean(config.inline),
        open: Boolean(config.inline),
        activeCategory: 'all',
        selectedItem: null,
        menuLabel: 'File actions',
        get menuActions() {
            const item = (config.documents || []).find(document => document.key === this.selectedItem);
            if (item) return [
                ...(item.view_url ? [{ id: 'preview', label: 'Preview', run: () => this.$dispatch('open-modal', `project-${config.topicId}-${item.key}`) }] : []),
                { id: 'download', label: 'Download', href: item.download_url, download: item.filename },
            ];
            return this.selectedItem?.startsWith('category:')
                ? [{ id: 'open', label: 'Open folder', run: () => this.openCategory(this.selectedItem.slice(9)) }]
                : [];
        },
        prepareContextMenu(event) {
            const item = event.target?.closest?.('[data-project-document-key]');
            const category = event.target?.closest?.('[data-project-file-category]');
            if (item) this.selectItem(item.dataset.projectDocumentKey);
            else if (category) this.selectItem(`category:${category.dataset.projectFileCategory}`);
            else if (event.target?.closest?.('[data-library-view-surface]')) this.selectedItem = null;
        },
        init() {
            display.init.call(this);
            if (this.inline) return;
            this.$watch('open', (isOpen) => {
                document.body.classList.toggle('overflow-hidden', isOpen);

                this.$nextTick(() => {
                    if (isOpen) {
                        this.$refs.closeButton?.focus();
                    }
                });
            });

            if (this.open) {
                document.body.classList.add('overflow-hidden');
            }
        },
        destroy() {
            if (this.inline) return;
            document.body.classList.remove('overflow-hidden');
        },
        openDrawer(category = 'all') {
            this.openCategory(category);
            this.open = true;
        },
        closeDrawer() {
            if (this.inline) return;
            this.open = false;
            this.$nextTick(() => this.$refs.trigger?.focus());
        },
        selectItem(key) {
            this.selectedItem = key;
        },
        openCategory(category = 'all') {
            this.activeCategory = category;
            this.selectedItem = null;
            this.$nextTick?.(() => this.$refs?.browserHeading?.focus());
        },
    };
}
