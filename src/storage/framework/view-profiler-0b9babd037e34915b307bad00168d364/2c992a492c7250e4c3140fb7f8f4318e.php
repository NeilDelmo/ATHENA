<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['url']));

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

foreach (array_filter((['url']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<div x-data="commentResponsePaperPreview(<?php echo \Illuminate\Support\Js::from($url)->toHtml() ?>)" @comment-response-paper-view-change="setPaperExpanded($event.detail.expanded)" data-comment-response-preview data-comment-response-preview-url="<?php echo e($url); ?>" <?php echo e($attributes->class(['revision-pdf-viewer'])); ?>>
    <p x-show="!previewReady && !previewError" role="status" class="absolute inset-x-0 top-5 z-10 text-center text-sm text-gray-700 dark:text-slate-300">Loading Comment Response paper…</p>
    <div x-show="previewError" x-cloak role="alert" class="m-4 rounded-lg bg-red-50 p-4 text-base text-red-800">
        <p x-text="previewError"></p>
        <button type="button" @click="loadPaperPreview()" class="mt-3 rounded-lg bg-red-700 px-4 py-2 font-semibold text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700">Try again</button>
    </div>
    <iframe x-ref="previewFrame" x-show="previewHtml" :srcdoc="previewHtml" @load="paperPreviewLoaded()" tabindex="-1" title="Comment Response paper" class="min-h-0 w-full flex-1 border-0 bg-white"></iframe>
    <div x-show="previewReady" class="border-t border-gray-200 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-900"><?php if (isset($component)) { $__componentOriginal9b424d6d62ddd0483a58583769fc2f5d = $component; } ?>
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
<?php endif; ?></div>
    <footer x-show="previewExpanded && previewReady" x-cloak class="revision-reference-footer" role="group" aria-label="Comment Response preview zoom">
        <div data-revision-preview-zoom class="flex flex-wrap items-center gap-2">
            <button type="button" @click="decreaseProposalPreviewZoom()" :disabled="previewZoom <= 50" aria-label="Zoom out">−</button>
            <output x-text="`${previewZoom}%`" aria-live="polite"></output>
            <button type="button" @click="increaseProposalPreviewZoom()" :disabled="previewZoom >= 150" aria-label="Zoom in">+</button>
            <button type="button" @click="fitProposalPreview('page')" :aria-pressed="previewFit === 'page'">Fit page</button>
            <button type="button" @click="fitProposalPreview('width')" :aria-pressed="previewFit === 'width'">Fit width</button>
        </div>
    </footer>
</div>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\components\comment-response-paper-preview.blade.php ENDPATH**/ ?>