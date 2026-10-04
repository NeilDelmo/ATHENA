<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['livewireTarget' => null]));

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

foreach (array_filter((['livewireTarget' => null]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<div
    data-proposal-pdf-preparation-loading
    <?php if($livewireTarget): ?>
        wire:cloak
        wire:loading.flex
        wire:target="<?php echo e($livewireTarget); ?>"
    <?php else: ?>
        hidden
    <?php endif; ?>
    role="status"
    aria-live="assertive"
    aria-atomic="true"
    aria-hidden="<?php echo e($livewireTarget ? 'false' : 'true'); ?>"
    class="fixed inset-0 z-[110] items-center justify-center overflow-y-auto bg-gray-950/70 px-4 py-8 backdrop-blur-sm"
>
    <div class="flex min-h-full w-full items-center justify-center">
        <div class="w-full max-w-md overflow-hidden rounded-3xl border border-white/20 bg-white shadow-2xl">
            <div class="flex flex-col items-center px-6 py-8 text-center sm:px-8 sm:py-10">
                <span class="flex h-16 w-16 items-center justify-center rounded-2xl bg-red-50 ring-1 ring-red-100">
                    <svg aria-hidden="true" class="h-9 w-9 animate-spin text-red-600" viewBox="0 0 24 24" fill="none">
                        <circle class="opacity-25" cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3"></circle>
                        <path class="opacity-90" fill="currentColor" d="M21 12a9 9 0 0 0-9-9v3a6 6 0 0 1 6 6h3Z"></path>
                    </svg>
                </span>

                <h2 class="mt-5 text-xl font-black tracking-tight text-gray-900">Generating submission PDFs</h2>
                <p class="mt-2 text-sm font-semibold leading-6 text-gray-600">ATHENA is preparing the seven final PDFs for your review.</p>
                <p class="mt-3 text-xs font-bold text-red-700">Please keep this page open. This can take a moment.</p>

                <div class="mt-6 w-full overflow-hidden rounded-full bg-red-100" aria-hidden="true">
                    <div class="h-2 w-2/3 animate-pulse rounded-full bg-red-600"></div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views/components/proposal-pdf-preparation-loading-screen.blade.php ENDPATH**/ ?>