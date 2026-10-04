<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['user']));

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

foreach (array_filter((['user']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    $initial = Str::of($user->name)->trim()->substr(0, 1)->upper()->value() ?: '?';
?>

<div
    x-data="{ avatarFailed: false }"
    <?php if(filled($user->avatar)): ?>
        x-init="$nextTick(() => { if ($refs.image.complete && $refs.image.naturalWidth === 0) avatarFailed = true })"
    <?php endif; ?>
    role="img"
    aria-label="Profile photo for <?php echo e($user->name); ?>"
    data-user-avatar
    <?php echo e($attributes->class(['relative inline-flex shrink-0 items-center justify-center overflow-hidden font-black uppercase text-white'])); ?>

>
    <span aria-hidden="true" class="flex h-full w-full items-center justify-center"><?php echo e($initial); ?></span>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(filled($user->avatar)): ?>
        <img
            x-ref="image"
            x-show="! avatarFailed"
            x-on:error="avatarFailed = true"
            src="<?php echo e($user->avatar); ?>"
            alt=""
            class="absolute inset-0 h-full w-full object-cover"
            data-user-avatar-image
        >
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div><?php /**PATH C:\laragon\www\athena-app\src\resources\views/components/user-avatar.blade.php ENDPATH**/ ?>