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

<?php
    $noChange = (bool) old('feedback_responses.'.$item['key'].'.no_change', $item['no_change'] ?? false);
?>

<div data-revision-response-key="<?php echo e($item['key']); ?>" data-revision-response-source="<?php echo e($item['form_source'] ?? ''); ?>" class="space-y-3">
    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-200">
        Your response <span aria-hidden="true" class="text-brand dark:text-rose-300">*</span><span class="sr-only"> (required)</span>
        <textarea data-revision-response-document="<?php echo e($documentType); ?>" name="feedback_responses[<?php echo e($item['key']); ?>][response]" rows="4" maxlength="5000" required placeholder="Explain your change or why the submitted paper already addresses this comment." class="mt-2 block w-full rounded-lg border-slate-300 text-sm leading-6 focus:border-[#7A0019] focus:ring-[#7A0019] dark:border-slate-600 dark:bg-slate-950 dark:text-white"><?php echo e(old('feedback_responses.'.$item['key'].'.response', $item['response'] ?? '')); ?></textarea>
    </label>
    <fieldset data-comment-response-location data-response-key="<?php echo e($item['key']); ?>" data-response-document="<?php echo e($documentType); ?>" aria-label="Automatic change location" class="space-y-2">
        <input type="hidden" name="feedback_responses[<?php echo e($item['key']); ?>][no_change]" value="0">
        <input type="checkbox" data-comment-response-action data-comment-response-no-change name="feedback_responses[<?php echo e($item['key']); ?>][no_change]" value="1" hidden tabindex="-1" <?php if($noChange): echo 'checked'; endif; ?>>
        <input type="hidden" data-location-document value="<?php echo e($documentType); ?>">
        <input type="hidden" data-comment-response-page name="feedback_responses[<?php echo e($item['key']); ?>][page]" value="<?php echo e(old('feedback_responses.'.$item['key'].'.page', $item['page'] ?? '')); ?>" <?php if($noChange): echo 'disabled'; endif; ?>>
        <input type="hidden" data-comment-response-paragraph name="feedback_responses[<?php echo e($item['key']); ?>][paragraph]" value="<?php echo e(old('feedback_responses.'.$item['key'].'.paragraph', $item['paragraph'] ?? '')); ?>" <?php if($noChange): echo 'disabled'; endif; ?>>
        <p data-location-status role="status" aria-live="polite" class="text-xs leading-5 text-slate-500 dark:text-slate-400">The page and paragraph will be added automatically when you finish this paper.</p>
        <button type="button" data-location-retry hidden class="revision-location-button">Try again</button>
        <div data-location-confirm hidden aria-label="Choose the updated passage" class="revision-location-confirm"></div>
    </fieldset>
    <button type="button" data-comment-response-preview-open="<?php echo e($item['form_source'] ?? 'research_head'); ?>" aria-haspopup="dialog" class="revision-location-button">Preview Comment Response</button>
</div>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\components\proposal-revision-response.blade.php ENDPATH**/ ?>