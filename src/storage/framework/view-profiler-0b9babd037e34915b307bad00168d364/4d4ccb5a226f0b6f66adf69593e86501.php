<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['id']));

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

foreach (array_filter((['id']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<div x-show="$store.researchAssistant.canSelectDocuments()" x-cloak @keydown.escape.stop.prevent="$store.researchAssistant.closeDocumentPicker(); $refs.documentTrigger.focus()" x-data="{}" class="shrink-0">
    <button x-ref="documentTrigger" type="button" @click="$store.researchAssistant.toggleDocumentPicker()" :aria-expanded="$store.researchAssistant.documentPickerOpen" aria-controls="assistant-document-picker-<?php echo e($id); ?>" aria-label="Analyze document" title="Analyze document" class="inline-flex h-11 w-11 items-center justify-center rounded-full text-gray-500 transition hover:bg-gray-100 hover:text-gray-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white">
        <?php if (isset($component)) { $__componentOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.assistant-icon','data' => ['class' => 'h-5 w-5']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('assistant-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'h-5 w-5']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b)): ?>
<?php $attributes = $__attributesOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b; ?>
<?php unset($__attributesOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b)): ?>
<?php $component = $__componentOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b; ?>
<?php unset($__componentOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b); ?>
<?php endif; ?>
    </button>
    <div id="assistant-document-picker-<?php echo e($id); ?>" x-show="$store.researchAssistant.documentPickerOpen" x-cloak @click.outside="$store.researchAssistant.closeDocumentPicker()" x-transition.opacity.duration.150ms class="absolute bottom-full left-0 right-0 z-20 mb-2 rounded-2xl border border-gray-200 bg-white p-3 shadow-xl dark:border-slate-700 dark:bg-slate-900">
        <div class="mb-2 flex items-center justify-between gap-2">
            <p class="pl-2 text-sm font-medium text-gray-900 dark:text-white">Analyze a document</p>
            <button type="button" @click="$store.researchAssistant.closeDocumentPicker(); $refs.documentTrigger.focus()" class="inline-flex h-11 w-11 items-center justify-center rounded-xl text-gray-500 hover:bg-gray-100 dark:text-slate-400 dark:hover:bg-slate-800" aria-label="Close document picker"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18" /></svg></button>
        </div>
        <p x-show="$store.researchAssistant.documentLoading" role="status" class="px-2 py-4 text-sm text-gray-500 dark:text-slate-400">Loading documents…</p>
        <div class="max-h-[min(12rem,25dvh)] overflow-y-auto">
            <template x-for="documentOption in $store.researchAssistant.documentOptions" :key="documentOption.token">
                <button type="button" @click="$store.researchAssistant.selectedDocumentToken = documentOption.token" :aria-pressed="$store.researchAssistant.selectedDocumentToken === documentOption.token" class="flex min-h-11 w-full items-start gap-3 rounded-xl px-3 py-3 text-left text-sm text-gray-700 hover:bg-gray-100 aria-pressed:bg-gray-100 dark:text-slate-200 dark:hover:bg-slate-800 dark:aria-pressed:bg-slate-800">
                    <span class="min-w-0 flex-1 break-words" x-text="documentOption.label"></span>
                    <span class="shrink-0 text-xs font-medium text-gray-500 dark:text-slate-400" x-text="documentOption.format"></span>
                    <svg x-show="$store.researchAssistant.selectedDocumentToken === documentOption.token" class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6" /></svg>
                </button>
            </template>
        </div>
        <p x-show="$store.researchAssistant.documentError" role="alert" class="px-2 py-3 text-sm text-red-600 dark:text-red-300" x-text="$store.researchAssistant.documentError"></p>
        <p class="px-2 py-3 text-xs leading-5 text-gray-500 dark:text-slate-400">Choose a saved paper before PDF generation, or a PDF or DOCX. Type a question below or request a summary. Only relevant readable passages from this document are sent; it stays selected for follow-up questions. Current edits must finish saving first.</p>
        <button type="button" @click="$store.researchAssistant.analyzeSelectedDocument()" :disabled="$store.researchAssistant.documentLoading || $store.researchAssistant.documentAnalysisPending || !$store.researchAssistant.selectedDocumentToken || $store.researchAssistant.isLoading" class="inline-flex items-center justify-center gap-2 min-h-11 w-full rounded-xl bg-gray-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-gray-700 disabled:cursor-not-allowed disabled:opacity-40 dark:bg-white dark:text-slate-900"><?php if (isset($component)) { $__componentOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.assistant-icon','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('assistant-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b)): ?>
<?php $attributes = $__attributesOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b; ?>
<?php unset($__attributesOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b)): ?>
<?php $component = $__componentOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b; ?>
<?php unset($__componentOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b); ?>
<?php endif; ?><span x-text="$store.researchAssistant.documentLoading ? 'Loading…' : $store.researchAssistant.documentAnalysisPending ? 'Saving paper…' : 'Analyze document'"></span></button>
    </div>
</div>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\components\research-assistant-document-action.blade.php ENDPATH**/ ?>