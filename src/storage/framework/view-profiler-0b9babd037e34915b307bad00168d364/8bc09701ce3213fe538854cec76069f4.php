<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['proposalDraft']));

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

foreach (array_filter((['proposalDraft']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<?php ($preparation = app(\App\Services\ProposalPdfPreparation::class)->status($proposalDraft)); ?>
<section data-proposal-pdf-preparation
    <?php if($preparation['status'] !== 'failed'): ?> hidden <?php endif; ?>
    data-state-url="<?php echo e(route('faculty.proposal-drafts.review', $proposalDraft)); ?>"
    data-prepare-url="<?php echo e(auth()->user()->can('prepare', $proposalDraft) ? route('faculty.proposal-drafts.submission-files.prepare', $proposalDraft) : ''); ?>"
    data-pdf-state="<?php echo e(json_encode($preparation, JSON_THROW_ON_ERROR)); ?>"
    <?php echo e($attributes->merge(['class' => 'rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm dark:border-amber-900 dark:bg-amber-950/20'])); ?>>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p data-pdf-preparation-message role="status" aria-live="polite" class="min-w-0 text-amber-950 dark:text-amber-100">We couldn't prepare your submission documents. Your work is saved.</p>
        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('prepare', $proposalDraft)): ?>
            <button type="button" data-pdf-preparation-retry <?php if($preparation['status'] !== 'failed'): ?> hidden <?php endif; ?>
                class="min-h-11 rounded-lg border border-amber-300 bg-white px-3 py-2 font-semibold text-amber-950 hover:bg-amber-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-amber-700 dark:border-amber-800 dark:bg-slate-900 dark:text-amber-100">Try again</button>
        <?php endif; ?>
    </div>
</section>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\components\proposal-pdf-preparation.blade.php ENDPATH**/ ?>