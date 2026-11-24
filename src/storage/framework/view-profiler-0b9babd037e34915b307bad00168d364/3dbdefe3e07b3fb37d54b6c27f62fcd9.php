<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['compact' => false]));

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

foreach (array_filter((['compact' => false]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<div x-show="!$store.researchAssistant.historyRestoring && !$store.researchAssistant.hasConversation()" class="<?php echo \Illuminate\Support\Arr::toCssClasses(['mx-auto flex min-h-full w-full max-w-3xl flex-col items-center justify-center text-center', 'px-5 py-8' => $compact, 'px-6 py-12 sm:px-10' => ! $compact]); ?>">
    <div class="mb-6 flex h-12 w-12 items-center justify-center rounded-2xl bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-300">
        <?php if (isset($component)) { $__componentOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.assistant-icon','data' => ['class' => 'h-7 w-7']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('assistant-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'h-7 w-7']); ?>
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
    </div>
    <h4 class="<?php echo \Illuminate\Support\Arr::toCssClasses(['font-semibold tracking-tight text-gray-900 dark:text-white', 'text-[1.75rem] leading-tight' => $compact, 'text-3xl sm:text-4xl' => ! $compact]); ?>" x-text="$store.researchAssistant.starterPromptHeading()"></h4>
    <p class="mt-3 max-w-md text-sm leading-6 text-gray-500 dark:text-slate-400" x-text="$store.researchAssistant.starterPromptDescription()"></p>
    <div x-show="$store.researchAssistant.hasStarterPrompts()" x-cloak class="<?php echo \Illuminate\Support\Arr::toCssClasses(['mt-8 grid w-full gap-2 text-left', 'sm:grid-cols-2' => ! $compact]); ?>">
        <template x-for="item in $store.researchAssistant.starterPrompts()" :key="item.prompt">
            <button type="button" @click="item.action?.type === 'review_proposal' ? $store.researchAssistant.startSuggestedReview(item, $event.currentTarget) : $store.researchAssistant.sendPrompt(item.prompt, item.action || null)" :disabled="$store.researchAssistant.reviewLaunchBusy()" :title="item.evidence" class="group flex items-start gap-3 rounded-2xl bg-gray-50 px-4 py-3.5 text-left transition hover:bg-gray-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-slate-800/50 dark:hover:bg-slate-800">
                <?php if (isset($component)) { $__componentOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b = $component; } ?>
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
<?php endif; ?>
                <span class="min-w-0 flex-1">
                    <span class="block text-sm font-medium text-gray-800 dark:text-slate-100" x-text="item.label"></span>
                    <span class="mt-1 block text-xs leading-5 text-gray-500 dark:text-slate-400" x-text="item.description"></span>
                    <span class="sr-only" x-text="item.evidence"></span>
                </span>
            </button>
        </template>
    </div>
</div>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\components\research-assistant-welcome.blade.php ENDPATH**/ ?>