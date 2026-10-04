<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['proposalDraft', 'paper']));

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

foreach (array_filter((['proposalDraft', 'paper']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<section data-revision-section="section-signatories" class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
    <div class="flex flex-wrap items-center justify-between gap-3"><h3 class="text-sm font-bold dark:text-white">Signature names</h3><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(array_diff_key(\App\Models\ProposalSignatory::FIELDS[$paper] ?? [], \App\Models\ProposalSignatory::defaultSelections()) !== []): ?><a href="<?php echo e(route('signatories.edit', ['proposalDraft' => $proposalDraft, 'paper' => $paper])); ?>" class="text-sm font-semibold text-red-700 dark:text-red-300">Other signatories</a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
    <dl class="mt-3 grid gap-3 sm:grid-cols-2">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = \App\Models\ProposalSignatory::FIELDS[$paper] ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <div><dt class="text-xs text-gray-500"><?php echo e($label); ?></dt><dd class="mt-1 text-sm font-semibold dark:text-white"><?php echo e($proposalDraft->resolvedSignatorySelections()[$key]['name'] ?? 'Not selected'); ?></dd></div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
    </dl>
    <p class="mt-3 text-xs text-gray-500">Research Head and VCRDES names are filled automatically. Signature and date-signed lines stay blank for wet signatures.</p>
</section>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views/components/proposal-signatory-summary.blade.php ENDPATH**/ ?>