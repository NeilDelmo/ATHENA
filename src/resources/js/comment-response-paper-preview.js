import { proposalPreviewWorkspace } from './proposal-preview-workspace.js';

export function commentResponsePaperPreview(url) {
    const previewWorkspace = proposalPreviewWorkspace();
    let visibilityObserver;
    let resizeObserver;
    let revisionForm;
    let replyListener;
    let disposed = false;

    return {
        ...previewWorkspace,
        previewFit: 'page',
        previewExpanded: false,
        previewHtml: '',
        previewReady: false,
        previewLoading: false,
        previewError: '',

        init() {
            this.$nextTick(() => {
                revisionForm = this.$el.closest('[data-revision-workspace]');
                replyListener = () => this.syncPaperReplies();
                revisionForm?.addEventListener('input', replyListener);
                revisionForm?.addEventListener('change', replyListener);
                resizeObserver = new ResizeObserver(() => this.fitPaperPreview());
                resizeObserver.observe(this.$refs.previewFrame);
                if (typeof IntersectionObserver === 'undefined') {
                    this.loadPaperPreview();
                    return;
                }
                visibilityObserver = new IntersectionObserver((entries) => {
                    if (!entries.some((entry) => entry.isIntersecting)) return;
                    visibilityObserver.disconnect();
                    this.loadPaperPreview();
                });
                visibilityObserver.observe(this.$el);
            });
        },

        async loadPaperPreview() {
            if (this.previewLoading || this.previewHtml || disposed) return;
            this.previewLoading = true;
            this.previewError = '';
            try {
                const response = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'text/html' } });
                if (!response.ok) throw new Error('The Comment Response paper could not load. Please try again.');
                const html = await response.text();
                if (!disposed) this.previewHtml = html;
            } catch (error) {
                if (!disposed) this.previewError = error instanceof Error ? error.message : 'The Comment Response paper could not load.';
            } finally {
                this.previewLoading = false;
            }
        },

        paperPreviewLoaded() {
            if (!this.previewHtml) return;
            this.previewReady = true;
            this.syncPaperReplies();
            this.bindProposalPreviewWheel(this.$refs.previewFrame, () => this.previewExpanded);
            this.$nextTick(() => this.fitPaperPreview());
            this.$refs.previewFrame.contentDocument?.addEventListener('keydown', (event) => {
                const paper = this.$el.closest('[data-comment-response-paper]');
                if (event.key === 'Escape' && paper?.matches(':modal')) {
                    event.preventDefault();
                    paper.dispatchEvent(new Event('cancel', { cancelable: true }));
                }
            });
        },

        fitPaperPreview() {
            if (!this.previewReady) return;
            previewWorkspace.applyProposalPreviewZoom.call(this);
        },

        applyProposalPreviewZoom() {
            this.fitPaperPreview();
        },

        syncPaperReplies() {
            const paper = this.$refs.previewFrame.contentDocument;
            if (!revisionForm || !paper) return;
            const replies = new Map([...revisionForm.querySelectorAll('[data-revision-response-key]')]
                .map((reply) => [reply.dataset.revisionResponseKey, reply]));
            paper.querySelectorAll('[data-comment-response-row]').forEach((row) => {
                const reply = replies.get(row.dataset.commentResponseRow);
                if (!reply) return;
                const response = reply.querySelector('textarea');
                const page = reply.querySelector('[data-comment-response-page]')?.value;
                const paragraph = reply.querySelector('[data-comment-response-paragraph]')?.value;
                const noChange = reply.querySelector('[data-comment-response-no-change]')?.checked;
                row.querySelector('[data-comment-response-answer]').textContent = response?.value || '';
                row.querySelector('[data-comment-response-remarks]').textContent = noChange
                    ? 'No change made' : (page && paragraph ? `Page ${page}, paragraph ${paragraph}` : '');
            });
            this.$nextTick(() => this.fitPaperPreview());
        },

        setPaperExpanded(expanded) {
            this.previewExpanded = expanded;
            this.previewFit = 'page';
            this.previewZoom = 100;
            if (expanded) this.loadPaperPreview();
            this.syncPaperReplies();
            this.$nextTick(() => this.fitPaperPreview());
        },

        destroy() {
            disposed = true;
            visibilityObserver?.disconnect();
            resizeObserver?.disconnect();
            revisionForm?.removeEventListener('input', replyListener);
            revisionForm?.removeEventListener('change', replyListener);
        },
    };
}
