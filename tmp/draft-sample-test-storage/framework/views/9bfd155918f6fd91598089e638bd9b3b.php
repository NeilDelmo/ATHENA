<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['comparison', 'proposalDraft']));

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

foreach (array_filter((['comparison', 'proposalDraft']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    $hasMismatches = ($comparison['mismatches'] ?? []) !== [];
    $overBudget = (bool) ($comparison['over_budget'] ?? false);
?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($hasMismatches || $overBudget): ?>
    <section role="alert" <?php echo e($attributes->merge(['class' => 'border-l-4 border-red-600 bg-red-50 p-5 text-red-950 dark:bg-red-950/40 dark:text-red-100'])); ?>>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h3 class="text-sm font-black"><?php echo e($overBudget ? 'Budget limit exceeded' : 'Budget totals do not match'); ?></h3>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($overBudget): ?>
                    <p class="mt-1 max-w-3xl text-xs leading-5">The project total is over the project budget limit of Php <?php echo e(number_format($comparison['budget_ceiling'], 2)); ?> by Php <?php echo e(number_format($comparison['overage'], 2)); ?>. The Line-Item Budget and Estimated Expense Breakdown are retained as drafts, but both must be reduced before the papers can be completed or the proposal turned in.</p>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($hasMismatches): ?>
                    <p class="<?php echo e($overBudget ? 'mt-2' : 'mt-1'); ?> max-w-3xl text-xs leading-5">Attachment B and the Estimated Expense Breakdown contain different totals. Standard Attachment B amounts already follow the latest Expense Breakdown; review any custom budget rows or manual total overrides that make the totals differ before turning in the proposal.</p>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
            <span class="inline-flex w-fit shrink-0 rounded-full bg-red-600 px-3 py-1 text-[10px] font-black uppercase tracking-wider text-white">Submission blocked</span>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($hasMismatches): ?>
            <div class="mt-4 overflow-x-auto rounded-lg border border-red-200 bg-white dark:border-red-900 dark:bg-slate-900">
                <table class="min-w-full divide-y divide-gray-200 text-left text-xs dark:divide-slate-800">
                    <thead class="bg-gray-100 text-[10px] font-black uppercase tracking-wider text-gray-700 dark:bg-slate-800 dark:text-slate-200">
                        <tr>
                            <th scope="col" class="px-4 py-3">Inconsistent value</th>
                            <th scope="col" class="px-4 py-3 text-right">Attachment B</th>
                            <th scope="col" class="px-4 py-3 text-right">Expense Breakdown</th>
                            <th scope="col" class="px-4 py-3 text-right">Difference</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-gray-800 dark:divide-slate-800 dark:text-slate-200">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $comparison['mismatches']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $mismatch): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <tr>
                                <th scope="row" class="whitespace-nowrap px-4 py-3 font-black"><?php echo e($mismatch['label']); ?></th>
                                <td class="whitespace-nowrap px-4 py-3 text-right font-semibold">Php <?php echo e(number_format($mismatch['line_item_budget'], 2)); ?></td>
                                <td class="whitespace-nowrap px-4 py-3 text-right font-semibold">Php <?php echo e(number_format($mismatch['expense_breakdown'], 2)); ?></td>
                                <td class="whitespace-nowrap px-4 py-3 text-right font-black text-red-700 dark:text-red-300">Php <?php echo e(number_format(abs($mismatch['difference']), 2)); ?></td>
                            </tr>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <div class="mt-4 flex flex-col gap-2 sm:flex-row">
            <a href="<?php echo e(route('faculty.proposal-drafts.line-item-budget.edit', $proposalDraft)); ?>" class="inline-flex w-full items-center justify-center rounded-lg bg-gray-950 px-4 py-2.5 text-xs font-black text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 dark:bg-white dark:text-gray-950 sm:w-auto">Review Attachment B</a>
            <a href="<?php echo e(route('faculty.proposal-drafts.expense-breakdown.edit', $proposalDraft)); ?>" class="inline-flex w-full items-center justify-center rounded-lg border border-red-300 bg-white px-4 py-2.5 text-xs font-black text-red-950 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 dark:bg-transparent dark:text-red-100 sm:w-auto">Review Expense Breakdown</a>
        </div>
    </section>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views/components/budget-consistency-warning.blade.php ENDPATH**/ ?>