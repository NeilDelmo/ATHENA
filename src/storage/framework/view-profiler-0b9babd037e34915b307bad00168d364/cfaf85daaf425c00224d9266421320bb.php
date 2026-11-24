<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['report', 'photo', 'photoIndex', 'figureNumber']));

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

foreach (array_filter((['report', 'photo', 'photoIndex', 'figureNumber']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php ($previewId = 'narrative-figure-'.$report->id.'-'.$photoIndex); ?>

<figure class="space-y-3 py-2" data-report-figure="<?php echo e($figureNumber); ?>">
    <button type="button" @click="$dispatch('open-modal', '<?php echo e($previewId); ?>')" aria-haspopup="dialog" aria-label="Preview figure <?php echo e($figureNumber); ?>" class="group relative block w-full overflow-hidden rounded-lg border border-slate-200 bg-slate-50 p-3 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700 dark:border-slate-700 dark:bg-slate-950">
        <span class="sr-only">Preview figure</span>
        <picture><source media="print" srcset="<?php echo e(route('project-narrative-reports.photos.view', [$report, $photoIndex])); ?>"><img src="<?php echo e(route('project-narrative-reports.photos.view', [$report, $photoIndex, 'preview' => 1])); ?>" alt="<?php echo e($photo['caption'] ?? 'Report figure'); ?>" loading="lazy" decoding="async" class="mx-auto aspect-[4/3] max-h-[26rem] w-full object-contain print:aspect-auto"></picture>
        <span aria-hidden="true" class="absolute bottom-3 right-3 rounded-md border border-slate-200 bg-white p-2 text-slate-500 group-hover:text-slate-900 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8 3H3v5m13-5h5v5M3 16v5h5m13-5v5h-5" /></svg></span>
    </button>
    <figcaption class="break-words text-sm leading-6 text-slate-500 dark:text-slate-400"><span class="font-medium text-slate-700 dark:text-slate-200">Figure <?php echo e($figureNumber); ?>.</span> <?php echo e($photo['caption'] ?? ''); ?></figcaption>
</figure>

<?php if (isset($component)) { $__componentOriginal9f64f32e90b9102968f2bc548315018c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9f64f32e90b9102968f2bc548315018c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.modal','data' => ['name' => $previewId,'maxWidth' => '6xl','focusable' => true,'class' => '!z-[140]','dataNarrativeFigurePreviewModal' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('modal'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($previewId),'maxWidth' => '6xl','focusable' => true,'class' => '!z-[140]','data-narrative-figure-preview-modal' => true]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

    <template x-if="show">
        <section role="dialog" aria-modal="true" aria-labelledby="<?php echo e($previewId); ?>-heading">
            <header class="flex items-center justify-between gap-4 border-b border-slate-200 px-5 py-3 dark:border-slate-700">
                <h3 id="<?php echo e($previewId); ?>-heading" class="text-base font-semibold text-slate-900 dark:text-white">Figure <?php echo e($figureNumber); ?></h3>
                <button type="button" @click="$dispatch('close')" aria-label="Close figure preview" class="monitoring-report-control">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18"/></svg>
                </button>
            </header>
            <div class="max-h-[75dvh] space-y-4 overflow-auto p-5">
                <img src="<?php echo e(route('project-narrative-reports.photos.view', [$report, $photoIndex])); ?>" alt="<?php echo e($photo['caption'] ?? 'Report figure'); ?>" class="mx-auto h-auto max-w-full">
                <p class="break-words text-base leading-7 text-gray-700 dark:text-slate-200"><span class="font-bold">Figure <?php echo e($figureNumber); ?>.</span> <?php echo e($photo['caption'] ?? ''); ?></p>
            </div>
        </section>
    </template>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9f64f32e90b9102968f2bc548315018c)): ?>
<?php $attributes = $__attributesOriginal9f64f32e90b9102968f2bc548315018c; ?>
<?php unset($__attributesOriginal9f64f32e90b9102968f2bc548315018c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9f64f32e90b9102968f2bc548315018c)): ?>
<?php $component = $__componentOriginal9f64f32e90b9102968f2bc548315018c; ?>
<?php unset($__componentOriginal9f64f32e90b9102968f2bc548315018c); ?>
<?php endif; ?>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\components\narrative-report-figure.blade.php ENDPATH**/ ?>