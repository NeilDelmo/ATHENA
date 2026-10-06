@props(['panelId' => 'paper-preview-panel', 'previewLabel' => 'Paper preview', 'frameTitle' => 'Document content preview'])
<aside x-show="previewPaneOpen" x-cloak class="proposal-preview-dock" :class="{ 'proposal-preview-dock-expanded': previewFullscreen }" aria-label="{{ $previewLabel }}" @keydown.escape.stop="returnToProposalPaperEditor()" @keydown.stop="trapProposalPaperPreviewFocus($event)">
    <section id="{{ $panelId }}" x-ref="previewPanel" :role="previewFullscreen ? 'dialog' : 'region'" :aria-modal="previewFullscreen ? 'true' : null" aria-labelledby="{{ $panelId }}-heading">
        <header class="proposal-preview-dock-heading">
            <div><h3 id="{{ $panelId }}-heading" x-text="previewTitle">Paper preview</h3><p x-text="previewLoading ? 'Updating…' : previewReadOnly ? 'Read-only form' : previewStale ? 'Edits awaiting preview' : previewReady ? 'Up to date' : 'Your official form'"></p></div>
            <button type="button" x-ref="previewClose" @click="previewFullscreen ? returnToProposalPaperEditor() : closeProposalPreview()" :aria-label="previewReadOnly ? 'Close preview' : previewFullscreen ? 'Minimize preview' : 'Collapse preview'"><span x-text="previewReadOnly ? 'Close preview' : previewFullscreen ? 'Minimize' : 'Hide'"></span></button>
        </header>
        <div class="proposal-preview-dock-actions">
            <button type="button" @click="generatePreview()" :disabled="previewLoading">Refresh preview</button>
            <button type="button" @click="printPreview()" :disabled="!previewReady || previewStale || previewLoading">Print</button>
            <a x-show="previewReadOnly && previewDownloadUrl" x-cloak :href="previewDownloadUrl" class="proposal-preview-download">Download Word</a>
        </div>
        <p x-show="previewError || validationMessage" x-cloak role="alert" class="proposal-preview-error" x-text="previewError || validationMessage"></p>
        <div class="proposal-preview-paper" :aria-busy="previewLoading">
            <p x-show="!previewHtml" x-text="previewLoading ? 'Preparing your document…' : 'Your paper will appear here. You can preview an unfinished draft.'"></p>
            <iframe x-ref="previewFrame" x-show="previewHtml" :srcdoc="previewHtml" @load="proposalPreviewLoaded()" title="{{ $frameTitle }}" :title="previewReadOnly ? previewTitle : '{{ $frameTitle }}'" :tabindex="previewFullscreen ? 0 : -1"></iframe>
            <button type="button" x-show="previewHtml && !previewFullscreen" @click="expandProposalPaperPreview()" class="proposal-preview-paper-open" aria-label="Enlarge the paper preview"></button>
        </div>
        <footer x-show="previewFullscreen" class="proposal-preview-dock-footer">
            <div x-show="previewFullscreen" class="proposal-preview-zoom" aria-label="Document zoom controls">
                <button type="button" @click="decreaseProposalPreviewZoom()" :disabled="previewZoom <= 50" aria-label="Zoom out">−</button>
                <output x-text="`${previewZoom}%`" aria-live="polite"></output>
                <button type="button" @click="increaseProposalPreviewZoom()" :disabled="previewZoom >= 150" aria-label="Zoom in">+</button>
                <button type="button" @click="fitProposalPreview('page')" :aria-pressed="previewFit === 'page'">Fit page</button>
                <button type="button" @click="fitProposalPreview('width')" :aria-pressed="previewFit === 'width'">Fit width</button>
            </div>
        </footer>
    </section>
</aside>
