<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['count' => 0, 'label' => null]));

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

foreach (array_filter((['count' => 0, 'label' => null]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($count > 0): ?>
    <span
        x-show="$store.sidebar.open"
        data-sidebar-attention-badge
        class="ml-auto inline-flex min-w-5 items-center justify-center rounded-full bg-red-600 px-1.5 py-0.5 text-[10px] font-bold leading-none text-white dark:bg-red-500"
        aria-label="<?php echo e($label ?? ($count.' unread '.\Illuminate\Support\Str::plural('update', $count))); ?>"
    >
        <?php echo e($count > 99 ? '99+' : $count); ?>

    </span>
    <span
        x-show="!$store.sidebar.open"
        data-sidebar-attention-badge
        class="absolute right-0.5 top-1/2 h-2.5 w-2.5 -translate-y-1/2 rounded-full bg-red-600 ring-2 ring-white dark:bg-red-500 dark:ring-slate-950"
        aria-label="<?php echo e($label ?? ($count.' unread '.\Illuminate\Support\Str::plural('update', $count))); ?>"
    ></span>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views/components/sidebar-attention-badge.blade.php ENDPATH**/ ?>