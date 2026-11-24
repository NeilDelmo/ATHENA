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
            <div>
                <h3 id="<?php echo e($panelId); ?>-heading" x-text="previewTitle">Paper preview</h3>
                <p x-show="previewLoading || previewReadOnly || (previewStale && !previewError && !validationMessage)" x-cloak role="status" x-text="!previewLoading && previewReadOnly ? 'Read-only form' : 'Updating preview…'"></p>
            </div>
            <button type="button" class="proposal-preview-action proposal-preview-dismiss" x-ref="previewClose" @click="previewFullscreen ? returnToProposalPaperEditor() : closeProposalPreview()" :aria-label="previewReadOnly ? 'Close preview' : previewFullscreen ? 'Minimize preview' : 'Collapse preview'">
                <svg x-show="!previewReadOnly" class="proposal-preview-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" aria-hidden="true">
                    <path d="M5 12h14" />
                </svg>
                <svg x-show="previewReadOnly" x-cloak class="proposal-preview-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" aria-hidden="true">
                    <path d="m6 6 12 12M18 6 6 18" />
                </svg>
                <span x-text="previewReadOnly ? 'Close preview' : previewFullscreen ? 'Minimize' : 'Hide'"></span>
            </button>
        </header>
        <div class="proposal-preview-dock-actions">
            <button type="button" x-show="previewError || validationMessage" x-cloak @click="$refs.previewClose.focus({ preventScroll: true }); generatePreview()" :disabled="previewLoading">Retry preview</button>
            <button type="button" class="proposal-preview-action proposal-preview-print" @click="printPreview()" :disabled="!previewReady || previewStale || previewLoading">
                <svg class="proposal-preview-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M6 9V3h12v6M6 17H4a2 2 0 0 1-2-2v-4a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2h-2M6 14h12v7H6zM6 12h1" />
                </svg>
                <span>Print</span>
            </button>
            <?php if (isset($component)) { $__componentOriginal9b424d6d62ddd0483a58583769fc2f5d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9b424d6d62ddd0483a58583769fc2f5d = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.paper-preview-pagination','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('paper-preview-pagination'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9b424d6d62ddd0483a58583769fc2f5d)): ?>
<?php $attributes = $__attributesOriginal9b424d6d62ddd0483a58583769fc2f5d; ?>
<?php unset($__attributesOriginal9b424d6d62ddd0483a58583769fc2f5d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9b424d6d62ddd0483a58583769fc2f5d)): ?>
<?php $component = $__componentOriginal9b424d6d62ddd0483a58583769fc2f5d; ?>
<?php unset($__componentOriginal9b424d6d62ddd0483a58583769fc2f5d); ?>
<?php endif; ?>
            <a x-show="previewReadOnly && previewDownloadUrl" x-cloak :href="previewDownloadUrl" class="proposal-preview-download">Download Word</a>
        </div>
        <p x-show="previewError || validationMessage" x-cloak role="alert" class="proposal-preview-error" x-text="previewError || validationMessage"></p>
        <div class="proposal-preview-paper" :aria-busy="previewLoading">
            <p x-show="!previewHtml" x-text="previewLoading ? 'Preparing your document…' : 'Your paper will appear here. You can preview an unfinished draft.'"></p>
            <iframe x-ref="previewFrame" x-show="previewHtml" :srcdoc="previewHtml" @load="proposalPreviewLoaded()" title="<?php echo e($frameTitle); ?>" :title="previewReadOnly ? previewTitle : '<?php echo e($frameTitle); ?>'" :tabindex="previewFullscreen ? 0 : -1"></iframe>
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
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\components\proposal-paper-preview.blade.php ENDPATH**/ ?>