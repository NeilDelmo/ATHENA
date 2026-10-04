<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['configuration', 'loadingLabel' => 'Loading submitted PDF…', 'viewerLabel' => 'Submitted document']));

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

foreach (array_filter((['configuration', 'loadingLabel' => 'Loading submitted PDF…', 'viewerLabel' => 'Submitted document']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<div x-data="pdfAnnotationWorkspace" data-pdf-annotation-config='<?php echo json_encode(array_merge($configuration, ["fitWidth" => true]), 512) ?>' <?php echo e($attributes->merge(['class' => 'revision-pdf-viewer'])); ?>>
    <p x-show="loading" role="status" class="absolute inset-x-0 top-5 z-10 text-center text-sm text-gray-700"><?php echo e($loadingLabel); ?></p>
    <div x-show="loadError" x-cloak role="alert" class="m-4 rounded-lg bg-red-50 p-4 text-base text-red-800">
        <p x-text="loadError"></p>
        <button x-show="viewerRefreshRequired" type="button" @click="window.location.reload()" class="mt-3 rounded-lg bg-red-700 px-4 py-2 font-semibold text-white hover:bg-red-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700">Reload page</button>
        <button x-show="!viewerRefreshRequired" type="button" @click="loadPdf()" class="mt-3 rounded-lg bg-red-700 px-4 py-2 font-semibold text-white hover:bg-red-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700">Try again</button>
    </div>
    <div x-ref="viewer" tabindex="0" aria-label="<?php echo e($viewerLabel); ?>" class="pdf-annotation-viewer revision-pdf-pages"></div>
</div>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views/components/proposal-revision-pdf.blade.php ENDPATH**/ ?>