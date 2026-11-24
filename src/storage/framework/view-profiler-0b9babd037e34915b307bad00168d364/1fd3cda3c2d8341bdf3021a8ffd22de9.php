<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['name', 'title', 'description' => null, 'previewUrl', 'downloadUrl', 'viewerLabel' => 'Submitted report PDF', 'expanded' => false, 'modalName' => null, 'searchable' => false, 'initialPageExpression' => null, 'loadingLabel' => 'Loading submitted report…', 'previewUrlExpression' => null, 'downloadUrlExpression' => null, 'titleExpression' => null, 'descriptionExpression' => null, 'searchLabel' => 'Search manual']));

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

foreach (array_filter((['name', 'title', 'description' => null, 'previewUrl', 'downloadUrl', 'viewerLabel' => 'Submitted report PDF', 'expanded' => false, 'modalName' => null, 'searchable' => false, 'initialPageExpression' => null, 'loadingLabel' => 'Loading submitted report…', 'previewUrlExpression' => null, 'downloadUrlExpression' => null, 'titleExpression' => null, 'descriptionExpression' => null, 'searchLabel' => 'Search manual']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    $configuration = ['pdfUrl' => $previewUrl, 'annotations' => [], 'canAnnotate' => false, 'fitWidth' => true, 'loadWhenVisible' => ! $expanded, 'firstPageOnly' => ! $expanded, 'wheelZoom' => $expanded, 'maxZoom' => $expanded ? 400 : 150, 'searchable' => $searchable && $expanded];
?>

<div <?php if($previewUrlExpression || $initialPageExpression): ?> x-data="pdfAnnotationWorkspace({ ...<?php echo \Illuminate\Support\Js::from($configuration)->toHtml() ?>, <?php if($previewUrlExpression): ?> pdfUrl: <?php echo e($previewUrlExpression); ?>, <?php endif; ?> initialPage: <?php echo e($initialPageExpression ?? 'null'); ?> })" <?php else: ?> x-data="pdfAnnotationWorkspace" <?php endif; ?> x-init="previewFit = '<?php echo e($expanded ? 'width' : 'page'); ?>'" data-pdf-annotation-config='<?php echo json_encode($configuration, 15, 512) ?>' data-submitted-report-document <?php if($expanded): ?> data-submitted-report-preview <?php else: ?> data-submitted-report-paper :style="{ '--submitted-paper-ratio': previewPageRatio }" <?php endif; ?> <?php if($searchable && $expanded): ?> @pdf-preview-page.window="if ($event.detail.url === config.pdfUrl) goToPage($event.detail.page)" @keydown.ctrl.f.prevent="$refs.find.focus()" @keydown.meta.f.prevent="$refs.find.focus()" <?php endif; ?> class="proposal-preview-dock submitted-report-preview <?php echo e($expanded ? 'proposal-preview-dock-expanded' : 'submitted-report-preview-thumbnail'); ?>">
    <section <?php if($expanded): ?> role="dialog" aria-modal="true" <?php else: ?> role="region" <?php endif; ?> aria-labelledby="<?php echo e($name); ?>-heading">
        <header class="<?php echo e($expanded ? 'proposal-preview-dock-heading' : 'sr-only'); ?>">
            <div class="min-w-0">
                <h3 id="<?php echo e($name); ?>-heading" <?php if($titleExpression): ?> x-text="<?php echo e($titleExpression); ?>" <?php endif; ?>><?php echo e($title); ?></h3>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($descriptionExpression): ?><p x-show="<?php echo e($descriptionExpression); ?>" x-text="<?php echo e($descriptionExpression); ?>"></p><?php elseif($description): ?><p><?php echo e($description); ?></p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($expanded): ?>
                <button type="button" @click="$dispatch('close')" aria-label="Close PDF preview" class="shrink-0">
                    <svg class="proposal-preview-icon" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18" /></svg>
                    <span>Close preview</span>
                </button>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </header>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($expanded): ?>
            <div class="proposal-preview-dock-actions" <?php if($searchable): ?> role="search" aria-label="<?php echo e($searchLabel); ?>" x-id="['pdf-find', 'pdf-page']" <?php endif; ?>>
                <a <?php if($downloadUrlExpression): ?> :href="<?php echo e($downloadUrlExpression); ?>" <?php else: ?> href="<?php echo e($downloadUrl); ?>" <?php endif; ?> class="proposal-preview-download">
                    <svg class="proposal-preview-icon" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m-4-4 4 4 4-4M5 16v4h14v-4" /></svg>
                    <span>Download PDF</span>
                </a>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($searchable): ?>
                    <div class="proposal-preview-search">
                        <label :for="$id('pdf-find')" class="sr-only"><?php echo e($searchLabel); ?></label>
                        <svg class="proposal-preview-icon" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="10.5" cy="10.5" r="6.5" /><path stroke-linecap="round" d="m16 16 4 4" /></svg>
                        <input :id="$id('pdf-find')" x-ref="find" data-manual-find type="search" x-model="searchQuery" @input.debounce.200ms="findText()" @keydown.enter.prevent="findText($event.shiftKey, true)" @keydown.escape.stop.prevent="searchQuery = ''; findText()" :disabled="loading || !!loadError" placeholder="<?php echo e($searchLabel); ?>">
                    </div>
                    <button type="button" @click="findText(true, true)" :disabled="loading || !searchQuery.trim()" aria-label="Previous match" class="proposal-preview-icon-button">
                        <svg class="proposal-preview-icon" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m6 15 6-6 6 6" /></svg>
                    </button>
                    <button type="button" @click="findText(false, true)" :disabled="loading || !searchQuery.trim()" aria-label="Next match" class="proposal-preview-icon-button">
                        <svg class="proposal-preview-icon" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" /></svg>
                    </button>
                    <div class="flex items-center gap-2">
                        <label :for="$id('pdf-page')" class="text-sm text-slate-600 dark:text-slate-300">Page</label>
                        <input :id="$id('pdf-page')" data-manual-page type="number" min="1" :max="pageCount" :value="currentPage" @change="goToPage($event.target.value); $event.target.value = currentPage" @keydown.enter.prevent="goToPage($event.target.value); $event.target.value = currentPage" :disabled="loading || !!loadError" class="proposal-preview-page-input">
                        <span class="text-sm text-slate-600 dark:text-slate-300">of <span x-text="pageCount"></span></span>
                    </div>
                    <span x-show="searchQuery.trim()" x-text="searchStatus" data-manual-find-status role="status" class="basis-full text-sm text-slate-600 dark:text-slate-300"></span>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <div class="proposal-preview-paper" :aria-busy="loading">
            <p x-show="loading" role="status" class="absolute inset-x-0 top-0 z-10 text-center"><?php echo e($loadingLabel); ?></p>
            <div x-show="loadError" x-cloak role="alert" class="proposal-preview-error">
                <p x-text="loadError"></p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <button x-show="viewerRefreshRequired" type="button" @click="window.location.reload()">Reload page</button>
                    <button x-show="!viewerRefreshRequired" type="button" @click="loadPdf()">Try again</button>
                    <a <?php if($downloadUrlExpression): ?> :href="<?php echo e($downloadUrlExpression); ?>" <?php else: ?> href="<?php echo e($downloadUrl); ?>" <?php endif; ?> class="proposal-preview-download">Download PDF</a>
                </div>
            </div>
            <div x-ref="viewer" tabindex="<?php echo e($expanded ? '0' : '-1'); ?>" <?php if(! $expanded): ?> aria-hidden="true" <?php endif; ?> aria-label="<?php echo e($viewerLabel); ?>" class="pdf-annotation-viewer revision-pdf-pages"></div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $expanded): ?>
                <button type="button" data-report-paper-maximize x-show="!loadError" @click="$dispatch('open-modal', <?php echo \Illuminate\Support\Js::from($modalName)->toHtml() ?>)" class="proposal-preview-paper-open" aria-label="Enlarge the paper preview" aria-haspopup="dialog"></button>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($expanded): ?>
            <footer class="proposal-preview-dock-footer">
                <div class="proposal-preview-zoom" role="group" aria-label="Document zoom controls">
                    <button type="button" @click="setPaperZoom(previewZoom - 10)" :disabled="loading || !!loadError || previewZoom <= 50" aria-label="Zoom out">−</button>
                    <output x-text="`${previewZoom}%`" aria-live="polite">100%</output>
                    <button type="button" @click="setPaperZoom(previewZoom + 10)" :disabled="loading || !!loadError || previewZoom >= maxPaperZoom" aria-label="Zoom in">+</button>
                    <button type="button" @click="fitPaper('page')" :disabled="loading || !!loadError" :aria-pressed="previewFit === 'page'">Fit page</button>
                    <button type="button" @click="fitPaper('width')" :disabled="loading || !!loadError" :aria-pressed="previewFit === 'width'">Fit width</button>
                </div>
                <p class="submitted-report-wheel-hint">Scroll to read · Pinch or Ctrl + wheel to zoom</p>
            </footer>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </section>
</div>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\components\submitted-report-document.blade.php ENDPATH**/ ?>