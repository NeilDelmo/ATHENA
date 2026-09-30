export function proposalSignedUploads(config = {}) {
    return {
        documents: (config.documents ?? []).map(document => ({ ...document, busy: false, error: '', file: null })),
        pending: [],
        nextId: 0,
        complete: Boolean(config.complete),
        get busy() { return this.documents.some(document => document.busy); },
        get count() { return this.documents.filter(document => document.saved).length; },
        selectBatch(files) {
            // Require an explicit destination: a filename cannot prove which paper a scan contains.
            this.pending.push(...Array.from(files).map(file => ({ id: ++this.nextId, file })));
        },
        async assign(item, sourceId) {
            const document = this.documents.find(document => String(document.id) === String(sourceId));
            if (!document || document.busy) return;
            this.pending = this.pending.filter(pending => pending !== item);
            await this.upload(document, item.file);
        },
        async upload(document, file) {
            if (!file || document.busy) return;
            document.file = file;
            document.error = '';
            if (!/\.pdf$/i.test(file.name) || (file.type && file.type !== 'application/pdf')) {
                document.error = 'Choose a PDF file.';
                return;
            }
            if (file.size > 25 * 1024 * 1024) {
                document.error = 'Choose a PDF smaller than 25 MB.';
                return;
            }
            document.busy = true;
            try {
                const body = new FormData();
                body.append('_token', config.csrf);
                body.append('purpose', 'signed');
                body.append('source_file_id', document.id);
                body.append('review_file', file);
                const response = await fetch(config.url, {
                    method: 'POST', body, headers: { Accept: 'application/json' },
                });
                const result = await response.json();
                if (!response.ok) {
                    throw new Error(Object.values(result.errors ?? {}).flat()[0] ?? result.message ?? 'Upload failed. Please retry.');
                }
                document.saved = true;
                document.filename = result.filename;
                document.viewUrl = result.view_url;
                document.downloadUrl = result.download_url;
                document.file = null;
                this.complete = this.complete || result.complete;
            } catch (error) {
                document.error = error.message || 'Upload failed. Please retry.';
            } finally {
                document.busy = false;
            }
        },
    };
}
