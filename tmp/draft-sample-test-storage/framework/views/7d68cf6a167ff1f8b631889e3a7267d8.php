<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['item', 'documentType' => '', 'documentTypes' => collect()]));

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

foreach (array_filter((['item', 'documentType' => '', 'documentTypes' => collect()]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<div data-revision-response-key="<?php echo e($item['key']); ?>" data-revision-response-source="<?php echo e($item['form_source'] ?? ''); ?>" class="space-y-3">
    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-200">
        Your response <span class="font-normal text-slate-500 dark:text-slate-400">(required)</span>
        <textarea data-revision-response-document="<?php echo e($documentType); ?>" name="feedback_responses[<?php echo e($item['key']); ?>][response]" rows="2" maxlength="5000" required placeholder="Explain the change you made, or why no change is needed." class="mt-2 block w-full rounded-lg border-slate-300 text-sm leading-6 focus:border-[#7A0019] focus:ring-[#7A0019] dark:border-slate-600 dark:bg-slate-950 dark:text-white"><?php echo e(old('feedback_responses.'.$item['key'].'.response', $item['response'] ?? '')); ?></textarea>
    </label>
    <fieldset data-comment-response-location data-response-key="<?php echo e($item['key']); ?>" data-response-document="<?php echo e($documentType); ?>" x-data="{ noChange: <?php echo \Illuminate\Support\Js::from((bool) old('feedback_responses.'.$item['key'].'.no_change', $item['no_change'] ?? false))->toHtml() ?> }" class="space-y-3">
        <legend class="sr-only">Response details</legend>
        <div class="flex flex-wrap gap-3 text-xs text-slate-600 dark:text-slate-300" aria-label="Response action">
            <label class="flex items-center gap-2">
                <input type="radio" data-comment-response-action name="feedback_responses[<?php echo e($item['key']); ?>][no_change]" value="0" @change="noChange = false" :checked="!noChange" <?php if(!old('feedback_responses.'.$item['key'].'.no_change', $item['no_change'] ?? false)): echo 'checked'; endif; ?> class="border-slate-300 text-brand focus:ring-brand dark:border-slate-600 dark:bg-slate-950">
                Changed this passage
            </label>
            <label class="flex items-center gap-2">
                <input type="radio" data-comment-response-action data-comment-response-no-change name="feedback_responses[<?php echo e($item['key']); ?>][no_change]" value="1" @change="noChange = true" :checked="noChange" <?php if(old('feedback_responses.'.$item['key'].'.no_change', $item['no_change'] ?? false)): echo 'checked'; endif; ?> class="border-slate-300 text-brand focus:ring-brand dark:border-slate-600 dark:bg-slate-950">
                Explanation only
            </label>
        </div>
        <details data-response-location-details x-show="!noChange" class="rounded-lg border border-slate-200 p-3 dark:border-slate-700">
            <summary class="cursor-pointer text-xs font-semibold text-slate-700 dark:text-slate-200">Location of your change</summary>
            <div class="mt-3 space-y-3">
                <div data-location-automation x-show="!noChange" class="space-y-3 rounded-lg bg-slate-50 p-3 dark:bg-slate-800/60">
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-200">Revised paper
                        <select data-location-document class="mt-2 block w-full rounded-lg border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-950 dark:text-white">
                            <option value="">Choose a revised paper</option>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $documentTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $locationType): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <option value="<?php echo e($locationType); ?>" <?php if($documentType === $locationType): echo 'selected'; endif; ?>><?php echo e(app(\App\Support\ProposalPaperCatalog::class)->label($locationType)); ?></option>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </select>
                    </label>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-200">Changed passage
                        <select data-location-passage class="mt-2 block w-full rounded-lg border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-950 dark:text-white"><option value="">Read the revised paper to choose a passage</option></select>
                    </label>
                    <button type="button" data-location-refresh class="min-h-9 rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 disabled:opacity-50 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200">Find page and paragraph</button>
                    <p data-location-status role="status" aria-live="polite" class="text-xs leading-5 text-slate-600 dark:text-slate-300">Numbers will fill automatically for a single changed passage linked to this comment.</p>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-200">Page
                        <input type="number" data-comment-response-page name="feedback_responses[<?php echo e($item['key']); ?>][page]" value="<?php echo e(old('feedback_responses.'.$item['key'].'.page', $item['page'] ?? '')); ?>" min="1" max="100000" step="1" required :required="!noChange" :disabled="noChange" <?php if(old('feedback_responses.'.$item['key'].'.no_change', $item['no_change'] ?? false)): echo 'disabled'; endif; ?> placeholder="e.g. 4" class="mt-2 block w-full rounded-lg border-slate-300 text-sm focus:border-[#7A0019] focus:ring-[#7A0019] disabled:opacity-50 dark:border-slate-600 dark:bg-slate-950 dark:text-white">
                    </label>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-200">Paragraph
                        <input type="number" data-comment-response-paragraph name="feedback_responses[<?php echo e($item['key']); ?>][paragraph]" value="<?php echo e(old('feedback_responses.'.$item['key'].'.paragraph', $item['paragraph'] ?? '')); ?>" min="1" max="100000" step="1" required :required="!noChange" :disabled="noChange" <?php if(old('feedback_responses.'.$item['key'].'.no_change', $item['no_change'] ?? false)): echo 'disabled'; endif; ?> placeholder="e.g. 2" class="mt-2 block w-full rounded-lg border-slate-300 text-sm focus:border-[#7A0019] focus:ring-[#7A0019] disabled:opacity-50 dark:border-slate-600 dark:bg-slate-950 dark:text-white">
                    </label>
                </div>
                <p class="text-xs leading-5 text-slate-500 dark:text-slate-400">Check the detected location against the final revised PDF.</p>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(filled($item['remarks'] ?? '') && empty($item['page']) && empty($item['paragraph'])): ?>
                    <p class="text-xs leading-5 text-slate-500 dark:text-slate-400">Previous remarks: <?php echo e($item['remarks']); ?>. Enter the page and paragraph above, or choose Explanation only.</p>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </details>
    </fieldset>
</div>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views/components/proposal-revision-response.blade.php ENDPATH**/ ?>