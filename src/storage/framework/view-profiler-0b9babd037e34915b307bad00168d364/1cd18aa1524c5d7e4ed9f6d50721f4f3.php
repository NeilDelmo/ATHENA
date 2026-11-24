<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['id', 'inputId']));

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

foreach (array_filter((['id', 'inputId']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<p x-show="$store.researchAssistant.historyRestoring" x-cloak role="status" class="mb-2 px-1 text-base leading-6 text-stone-600 dark:text-stone-300">Opening saved chat…</p>
<p x-show="$store.researchAssistant.conversationNotice" x-cloak role="status" class="mb-2 px-1 text-base leading-6 text-stone-600 dark:text-stone-300" x-text="$store.researchAssistant.conversationNotice"></p>
<button x-show="$store.researchAssistant.pendingLiteratureRequest" x-cloak type="button" @click="$store.researchAssistant.resumeLiteratureRequest()" :disabled="$store.researchAssistant.literatureBusy() || !!$store.researchAssistant.draft.trim()" class="mb-2 inline-flex min-h-11 items-center rounded-lg border border-stone-300 px-3 py-2 text-base font-medium text-stone-800 focus-visible:ring-2 focus-visible:ring-red-600 disabled:opacity-50 dark:border-stone-600 dark:text-stone-100">Continue RRL request</button>
<div class="relative rounded-xl border border-stone-400 bg-white p-2 transition focus-within:border-red-700 dark:border-stone-600 dark:bg-stone-950 dark:focus-within:border-red-400" data-assistant-composer-shell>
    <div data-assistant-active-document x-show="$store.researchAssistant.activeDocument" x-cloak class="mx-2 mb-1 flex min-w-0 items-center gap-2 border-b border-stone-200 pb-1 text-xs text-stone-700 dark:border-stone-700 dark:text-stone-200">
        <svg class="h-4 w-4 shrink-0 text-red-900 dark:text-red-200" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-6-6Z"/><path d="M14 3v6h6M8 13h8M8 17h5"/></svg>
        <span class="min-w-0 flex-1"><span class="block text-[11px] text-stone-500 dark:text-stone-400">Asking about this document</span><span class="block truncate font-medium" :title="$store.researchAssistant.activeDocument?.label" x-text="$store.researchAssistant.activeDocument?.label"></span></span>
        <span class="shrink-0 text-[11px] text-stone-500 dark:text-stone-400" x-text="$store.researchAssistant.activeDocument?.format"></span>
        <button type="button" @click="$store.researchAssistant.clearActiveDocument()" class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg hover:bg-stone-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 dark:hover:bg-stone-800" aria-label="Stop asking about this document" title="Stop asking about this document"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18"/></svg></button>
    </div>
    <div data-assistant-review-target x-show="$store.researchAssistant.reviewContext" x-cloak class="mx-2 mb-1 border-b border-stone-200 pb-1 text-xs leading-5 text-red-900 dark:border-stone-700 dark:text-red-200">
        <p x-show="$store.researchAssistant.reviewContext?.proposal_draft_id || !$store.researchAssistant.hasContextOptions()" :title="$store.researchAssistant.reviewTargetLabel()" class="truncate font-semibold" x-text="$store.researchAssistant.reviewTargetLabel()"></p>
        <p class="font-medium" x-text="$store.researchAssistant.reviewFocusLabel()"></p>
    </div>
    <form id="assistant-send-<?php echo e($id); ?>" @submit.prevent="$store.researchAssistant.send()">
        <label for="<?php echo e($inputId); ?>" class="sr-only">Message Athena Research Assistant</label>
        <textarea id="<?php echo e($inputId); ?>" data-assistant-composer aria-describedby="<?php echo e($inputId); ?>-limit" :disabled="$store.researchAssistant.historyRestoring || !$store.researchAssistant.conversationReady" x-model="$store.researchAssistant.draft" @input="$store.researchAssistant.resizeComposer($event)" @keydown="$store.researchAssistant.handleComposerKeydown($event)" rows="1" maxlength="8000" placeholder="Ask Athena about your research…" class="block max-h-44 min-h-12 w-full resize-none border-0 bg-white px-3 py-3 text-base leading-6 text-stone-950 shadow-none placeholder:text-stone-500 focus:border-0 focus:ring-0 dark:bg-stone-950 dark:text-stone-100 dark:placeholder:text-stone-400"></textarea>
        <p id="<?php echo e($inputId); ?>-limit" data-assistant-message-limit x-show="$store.researchAssistant.messageLengthNotice()" x-text="$store.researchAssistant.messageLengthNotice()" x-cloak class="mx-3 mb-2 text-sm leading-5 text-amber-900 dark:text-amber-200"></p>
    </form>
    <div class="flex items-end justify-between gap-2">
        <div class="flex min-w-0 flex-1 items-center gap-1">
            <div class="min-w-0" x-show="!$store.researchAssistant.reviewContext?.proposal_draft_id"><?php if (isset($component)) { $__componentOriginal39a84ad26958c84cf71f957ae7ae980e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal39a84ad26958c84cf71f957ae7ae980e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.research-assistant-project-picker','data' => ['id' => $id]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('research-assistant-project-picker'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['id' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($id)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal39a84ad26958c84cf71f957ae7ae980e)): ?>
<?php $attributes = $__attributesOriginal39a84ad26958c84cf71f957ae7ae980e; ?>
<?php unset($__attributesOriginal39a84ad26958c84cf71f957ae7ae980e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal39a84ad26958c84cf71f957ae7ae980e)): ?>
<?php $component = $__componentOriginal39a84ad26958c84cf71f957ae7ae980e; ?>
<?php unset($__componentOriginal39a84ad26958c84cf71f957ae7ae980e); ?>
<?php endif; ?></div>
            <?php if (isset($component)) { $__componentOriginale6b838ae4341d613647a5aee06953b80 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginale6b838ae4341d613647a5aee06953b80 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.research-assistant-document-action','data' => ['id' => $id]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('research-assistant-document-action'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['id' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($id)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginale6b838ae4341d613647a5aee06953b80)): ?>
<?php $attributes = $__attributesOriginale6b838ae4341d613647a5aee06953b80; ?>
<?php unset($__attributesOriginale6b838ae4341d613647a5aee06953b80); ?>
<?php endif; ?>
<?php if (isset($__componentOriginale6b838ae4341d613647a5aee06953b80)): ?>
<?php $component = $__componentOriginale6b838ae4341d613647a5aee06953b80; ?>
<?php unset($__componentOriginale6b838ae4341d613647a5aee06953b80); ?>
<?php endif; ?>
            <button x-show="$store.researchAssistant.proposalHelpOptions().length" type="button" @click="$store.researchAssistant.proposalHelpOpen = !$store.researchAssistant.proposalHelpOpen; $nextTick(() => $el.closest('[data-assistant-workspace], .athena-chat-panel')?.querySelector('[data-assistant-messages]')?.scrollTo({ top: 0, behavior: 'auto' }))" :aria-expanded="$store.researchAssistant.proposalHelpOpen" aria-label="Help options" title="Help options" class="inline-flex h-11 shrink-0 items-center gap-2 rounded-lg border border-stone-300 bg-white px-3 text-xs text-red-900 hover:bg-stone-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 dark:border-stone-600 dark:bg-stone-900 dark:text-red-200 dark:hover:bg-stone-800">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M9.5 9a2.5 2.5 0 0 1 5 0c0 2-2.5 2-2.5 4m0 3h.01" /></svg>
                <span class="hidden sm:inline">Help options</span>
            </button>
        </div>
        <button x-show="!$store.researchAssistant.isLoading" type="submit" form="assistant-send-<?php echo e($id); ?>" :disabled="$store.researchAssistant.historyRestoring || !$store.researchAssistant.conversationReady || !$store.researchAssistant.draft.trim() || $store.researchAssistant.retryAfter > 0" class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-red-800 text-white transition hover:bg-red-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:bg-stone-100 disabled:text-stone-400 dark:disabled:bg-stone-800 dark:disabled:text-stone-500" aria-label="Send message">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 7-7 7 7M12 19V5" /></svg>
        </button>
        <button x-show="$store.researchAssistant.isLoading" x-cloak type="button" @click="$store.researchAssistant.stop()" class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-red-800 text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2" aria-label="Stop response"><span class="h-3.5 w-3.5 rounded-sm bg-current"></span></button>
    </div>
</div>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\components\research-assistant-composer.blade.php ENDPATH**/ ?>