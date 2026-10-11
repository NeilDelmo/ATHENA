export function acceptedProjectPdfFiles(files, limit = 10) {
    const uniqueFiles = [];
    const seen = new Set();

    for (const file of Array.from(files || [])) {
        const filename = String(file?.name || '');
        const isPdf = file?.type === 'application/pdf' || filename.toLowerCase().endsWith('.pdf');
        const fingerprint = `${filename}:${file?.size || 0}:${file?.lastModified || 0}`;

        if (!isPdf || seen.has(fingerprint)) continue;

        seen.add(fingerprint);
        uniqueFiles.push(file);

        if (uniqueFiles.length === limit) break;
    }

    return uniqueFiles;
}

export function projectDocumentDrawer(config = {}) {
    return {
        inline: Boolean(config.inline),
        open: Boolean(config.inline || config.initialOpen),
        uploadOpen: Boolean(config.uploadOpen),
        activeCategory: 'all',
        selectedItem: null,
        isDragging: false,
        selectedFiles: [],
        fileError: '',
        init() {
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
        chooseFiles(event) {
            this.setFiles(event.target.files);
        },
        dropFiles(event) {
            this.isDragging = false;
            this.setFiles(event.dataTransfer?.files || []);
        },
        setFiles(files) {
            const incomingFiles = Array.from(files || []);
            const combinedFiles = [...this.selectedFiles, ...incomingFiles];
            const validFiles = acceptedProjectPdfFiles(combinedFiles, Number.MAX_SAFE_INTEGER);
            const acceptedFiles = validFiles.slice(0, 10);
            const rejectedCount = incomingFiles.filter((file) => (
                file?.type !== 'application/pdf'
                && !String(file?.name || '').toLowerCase().endsWith('.pdf')
            )).length;

            this.selectedFiles = acceptedFiles;
            this.fileError = rejectedCount > 0
                ? `${rejectedCount} non-PDF ${rejectedCount === 1 ? 'file was' : 'files were'} not added.`
                : (validFiles.length > 10
                    ? 'Upload no more than 10 PDFs at a time.'
                    : '');
            this.syncFileInput();
        },
        removeFile(index) {
            this.selectedFiles.splice(index, 1);
            this.fileError = '';
            this.syncFileInput();
        },
        syncFileInput() {
            if (!(this.$refs.fileInput instanceof HTMLInputElement) || typeof DataTransfer === 'undefined') return;

            const transfer = new DataTransfer();
            this.selectedFiles.forEach((file) => transfer.items.add(file));
            this.$refs.fileInput.files = transfer.files;
        },
        formatSize(bytes) {
            const value = Number(bytes || 0);

            if (value < 1024) return `${value} B`;
            if (value < 1024 * 1024) return `${(value / 1024).toFixed(1)} KB`;

            return `${(value / (1024 * 1024)).toFixed(1)} MB`;
        },
        uploadButtonLabel() {
            if (this.selectedFiles.length === 0) return 'Choose PDFs to upload';

            return `Upload ${this.selectedFiles.length} ${this.selectedFiles.length === 1 ? 'PDF' : 'PDFs'}`;
        },
    };
}
