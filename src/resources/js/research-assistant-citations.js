import { safeAssistantSourceUrl } from './research-assistant-markdown';

export function assistantCitationLocation(source) {
    if (!source) return '';
    const start = Number(source.page_start);
    const end = Number(source.page_end);
    return [source.edition ? `Edition ${source.edition}` : '', source.section || '',
        Number.isSafeInteger(start) && start > 0 ? (Number.isSafeInteger(end) && end > start ? `Pages ${start}–${end}` : `Page ${start}`) : '',
    ].filter(Boolean).join(' · ');
}

export function assistantSourcePreview(source, origin = window.location.origin) {
    const sourceUrl = safeAssistantSourceUrl(source?.url);
    if (!sourceUrl) return null;
    const url = new URL(sourceUrl);
    if (url.origin !== origin) return null;
    const manual = url.pathname.match(/^(.*\/research-manuals\/[a-f0-9]{64})(?:\/file)?$/);
    const paper = /\/topics\/\d+\/versions\/\d+\/files\/\d+\/view$/.test(url.pathname);
    if (!manual && !paper) return null;
    const hashPage = new URLSearchParams(url.hash.slice(1)).get('page');
    const page = Number(source.page_start || hashPage || 1);
    url.hash = '';
    if (manual) url.pathname = manual[1] + '/file';
    const download = new URL(url);
    if (manual) download.searchParams.set('download', '1');
    else download.pathname = download.pathname.replace(/\/view$/, '/download');
    return { title: source.title || 'Source document', description: assistantCitationLocation(source),
        pdfUrl: url.href, downloadUrl: download.href, page: Number.isSafeInteger(page) && page > 0 ? page : 1 };
}

export function researchAssistantSourcePreview() {
    return {
        sourcePreview: null,
        sourcePreviewOpen: false,
        openSourcePreview(source, event = null) {
            const preview = assistantSourcePreview(source);
            if (!preview) return false;
            event?.preventDefault();
            this.sourcePreview = preview;
            this.sourcePreviewOpen = true;
            window.dispatchEvent(new CustomEvent('open-modal', { detail: 'assistant-source-preview' }));
            return true;
        },
    };
}

export function researchAssistantCitations() {
    return {
        citationSource: null,
        citationTrigger: null,
        citationLocation: assistantCitationLocation,
        canPreviewCitation: source => Boolean(assistantSourcePreview(source)),
        openCitation(event, message) {
            const button = event.target.closest('[data-assistant-citation]');
            if (!button || !this.$el.contains(button)) return;
            const index = Number(button.dataset.assistantCitation);
            const source = Number.isSafeInteger(index) ? message.sources?.[index] : null;
            if (!source) return;
            if (this.citationTrigger === button && this.citationSource) return this.closeCitation();
            this.closeCitation(false);
            this.citationTrigger = button;
            this.citationSource = source;
            button.setAttribute('aria-expanded', 'true');
            button.setAttribute('aria-controls', this.$refs.citationPanel.id);
            this.$nextTick(() => window.requestAnimationFrame(() => this.$refs.citationPanel.focus({ preventScroll: true })));
        },
        closeCitation(returnFocus = true) {
            if (!this.citationSource) return;
            this.citationSource = null;
            this.citationTrigger?.setAttribute('aria-expanded', 'false');
            if (returnFocus) this.citationTrigger?.focus({ preventScroll: true });
            this.citationTrigger = null;
        },
    };
}
