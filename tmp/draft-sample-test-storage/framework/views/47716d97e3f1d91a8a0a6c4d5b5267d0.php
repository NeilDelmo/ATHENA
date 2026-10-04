<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'description',
    'frameTitle',
    'panelId',
    'src' => null,
    'title',
]));

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

foreach (array_filter(([
    'description',
    'frameTitle',
    'panelId',
    'src' => null,
    'title',
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<div
    x-show="previewPaneOpen"
    x-cloak
    data-proposal-preview-floating
    class="pointer-events-none fixed inset-0 z-[80]"
    role="presentation"
    @keydown.escape.window="closeProposalPreview()"
    @resize.window.debounce.150ms="constrainProposalPreviewToViewport(); applyProposalPreviewZoom()"
>
    <button type="button" @click="closeProposalPreview()" class="pointer-events-auto absolute inset-0 bg-slate-950/45 backdrop-blur-sm xl:hidden" aria-label="Close <?php echo e(Str::lower($title)); ?>"></button>
    <section
        id="<?php echo e($panelId); ?>"
        x-ref="previewPanel"
        x-show="previewPaneOpen"
        x-transition:enter="transition ease-out duration-200 motion-reduce:transition-none"
        x-transition:enter-start="translate-y-4 scale-95 opacity-0"
        x-transition:enter-end="translate-y-0 scale-100 opacity-100"
        x-transition:leave="transition ease-in duration-150 motion-reduce:transition-none"
        x-transition:leave-start="translate-y-0 scale-100 opacity-100"
        x-transition:leave-end="translate-y-4 scale-95 opacity-0"
        class="proposal-preview-pane pointer-events-auto absolute inset-x-2 bottom-2 flex h-[48rem] max-h-[calc(100dvh-1rem)] origin-bottom-right flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl dark:border-slate-700 dark:bg-slate-900 sm:inset-x-auto sm:bottom-4 sm:right-4 sm:max-h-[calc(100dvh-2rem)] sm:w-[min(44rem,calc(100vw-2rem))]"
        :class="{ 'proposal-preview-fullscreen': previewFullscreen, 'proposal-preview-dragging': previewDragging }"
        role="dialog"
        :aria-modal="previewFullscreen || window.matchMedia('(max-width: 1279px)').matches ? 'true' : null"
        aria-labelledby="<?php echo e($panelId); ?>-title"
    >
        <div class="shrink-0 space-y-3 border-b border-slate-200 p-4 dark:border-slate-700">
            <div
                data-proposal-preview-drag-handle
                class="proposal-preview-drag-handle flex items-start justify-between gap-4"
                @pointerdown="startProposalPreviewDrag($event)"
            >
                <div>
                    <h3 id="<?php echo e($panelId); ?>-title" class="text-base font-black text-slate-900 dark:text-white"><?php echo e($title); ?></h3>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400"><?php echo e($description); ?></p>
                    <p class="mt-1 hidden text-[10px] font-semibold uppercase tracking-wider text-slate-400 sm:block">Drag this title bar to move. Resize from the bottom-right corner.</p>
                </div>
                <button type="button" @click="closeProposalPreview()" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-600 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white" aria-label="Close <?php echo e(Str::lower($title)); ?>">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18" /></svg>
                </button>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($src === null): ?>
                    <button type="button" @click="generatePreview()" :disabled="previewLoading" class="rounded-lg bg-red-700 px-3 py-2 text-xs font-bold text-white disabled:opacity-50" x-text="previewLoading ? 'Generating…' : 'Refresh preview'"></button>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <button type="button" @click="toggleProposalPreviewFullscreen()" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-bold dark:text-white" x-text="previewFullscreen ? 'Exit full screen' : 'Full screen'"></button>
                <button type="button" @click="printPreview()" :disabled="!previewReady" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-bold disabled:opacity-50 dark:text-white">Print preview</button>
            </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($src === null): ?>
                <p x-show="previewStale" x-cloak role="status" class="text-xs font-semibold text-amber-700 dark:text-amber-300">Your edits are newer than this preview. Refresh to update it.</p>
                <p x-show="previewError || validationMessage" x-cloak role="alert" class="text-sm text-red-700 dark:text-red-300" x-text="previewError || validationMessage"></p>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($src === null): ?>
            <p x-show="!previewHtml" class="p-6 text-sm text-slate-500 dark:text-slate-400" x-text="previewLoading ? 'Preparing your document preview…' : 'Select Refresh preview to see your current document here.'"></p>
            <iframe x-ref="previewFrame" x-show="previewHtml" x-bind:srcdoc="previewHtml" x-on:load="proposalPreviewLoaded()" title="<?php echo e($frameTitle); ?>" class="min-h-0 w-full flex-1 rounded-b-2xl bg-white"></iframe>
        <?php else: ?>
            <iframe x-ref="previewFrame" src="<?php echo e($src); ?>" x-on:load="proposalPreviewLoaded()" title="<?php echo e($frameTitle); ?>" class="min-h-0 w-full flex-1 rounded-b-2xl bg-white"></iframe>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <div class="pointer-events-auto absolute bottom-4 left-1/2 z-20 flex -translate-x-1/2 items-center gap-1 rounded-full border border-slate-200 bg-white/95 p-1 shadow-xl shadow-slate-950/20 backdrop-blur dark:border-slate-600 dark:bg-slate-900/95" aria-label="Document zoom controls">
            <button type="button" @click="decreaseProposalPreviewZoom()" :disabled="previewZoom <= 50" class="inline-flex h-9 w-9 items-center justify-center rounded-full text-xl font-bold leading-none text-slate-700 transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-red-600 disabled:cursor-not-allowed disabled:opacity-40 dark:text-slate-100 dark:hover:bg-slate-800" aria-label="Zoom out">&minus;</button>
            <output class="min-w-12 text-center text-xs font-black tabular-nums text-slate-700 dark:text-slate-100" x-text="`${previewZoom}%`" aria-live="polite"></output>
            <button type="button" @click="increaseProposalPreviewZoom()" :disabled="previewZoom >= 150" class="inline-flex h-9 w-9 items-center justify-center rounded-full text-xl font-bold leading-none text-slate-700 transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-red-600 disabled:cursor-not-allowed disabled:opacity-40 dark:text-slate-100 dark:hover:bg-slate-800" aria-label="Zoom in">+</button>
        </div>
    </section>
</div>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views/components/proposal-document-preview.blade.php ENDPATH**/ ?>