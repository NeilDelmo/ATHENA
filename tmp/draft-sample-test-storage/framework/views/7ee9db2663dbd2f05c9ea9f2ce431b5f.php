<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['panelId' => 'paper-preview-panel', 'previewLabel' => 'Paper preview', 'frameTitle' => 'Document content preview']));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Illuminate\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter((['panelId' => 'paper-preview-panel', 'previewLabel' => 'Paper preview', 'frameTitle' => 'Document content preview']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<aside x-show="previewPaneOpen" x-cloak class="proposal-preview-dock" :class="{ 'proposal-preview-dock-expanded': previewFullscreen }" aria-label="<?php echo e($previewLabel); ?>" @keydown.escape.stop="returnToProposalPaperEditor()" @keydown.stop="trapProposalPaperPreviewFocus($event)">
    <section id="<?php echo e($panelId); ?>" x-ref="previewPanel" :role="previewFullscreen ? 'dialog' : 'region'" :aria-modal="previewFullscreen ? 'true' : null" aria-labelledby="<?php echo e($panelId); ?>-heading">
        <header class="proposal-preview-dock-heading">
            <div><h3 id="<?php echo e($panelId); ?>-heading" x-text="previewTitle">Paper preview</h3><p x-text="previewLoading ? 'Updating…' : previewReadOnly ? 'Read-only form' : previewStale ? 'Edits awaiting preview' : previewReady ? 'Up to date' : 'Your official form'"></p></div>
            <button type="button" x-ref="previewClose" @click="previewFullscreen ? returnToProposalPaperEditor() : closeProposalPreview()" :aria-label="previewReadOnly ? 'Close preview' : previewFullscreen ? 'Return to editing' : 'Collapse preview'"><span x-text="previewReadOnly ? 'Close preview' : previewFullscreen ? 'Return to editing' : 'Hide'"></span></button>
        </header>
        <div class="proposal-preview-dock-actions">
            <button type="button" @click="generatePreview()" :disabled="previewLoading">Refresh preview</button>
            <button type="button" @click="printPreview()" :disabled="!previewReady">Print</button>
            <a x-show="previewReadOnly && previewDownloadUrl" x-cloak :href="previewDownloadUrl" class="proposal-preview-download">Download Word</a>
        </div>
        <p x-show="previewError || validationMessage" x-cloak role="alert" class="proposal-preview-error" x-text="previewError || validationMessage"></p>
        <div class="proposal-preview-paper" :aria-busy="previewLoading">
            <p x-show="!previewHtml" x-text="previewLoading ? 'Preparing your document…' : 'Your paper will appear here. You can preview an unfinished draft.'"></p>
            <iframe x-ref="previewFrame" x-show="previewHtml" :srcdoc="previewHtml" @load="proposalPreviewLoaded()" title="<?php echo e($frameTitle); ?>" :title="previewReadOnly ? previewTitle : '<?php echo e($frameTitle); ?>'" :tabindex="previewFullscreen ? 0 : -1"></iframe>
            <button type="button" x-show="previewHtml && !previewFullscreen" @click="expandProposalPaperPreview()" class="proposal-preview-paper-open" aria-label="Enlarge the paper preview"></button>
        </div>
        <footer class="proposal-preview-dock-footer">
            <button type="button" x-show="!previewFullscreen" @click="expandProposalPaperPreview()">View full paper</button>
            <div x-show="previewFullscreen" class="proposal-preview-zoom" aria-label="Document zoom controls">
                <button type="button" @click="decreaseProposalPreviewZoom()" :disabled="previewZoom <= 50" aria-label="Zoom out">−</button>
                <output x-text="`${previewZoom}%`" aria-live="polite"></output>
                <button type="button" @click="increaseProposalPreviewZoom()" :disabled="previewZoom >= 150" aria-label="Zoom in">+</button>
            </div>
        </footer>
    </section>
</aside>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views/components/proposal-paper-preview.blade.php ENDPATH**/ ?>