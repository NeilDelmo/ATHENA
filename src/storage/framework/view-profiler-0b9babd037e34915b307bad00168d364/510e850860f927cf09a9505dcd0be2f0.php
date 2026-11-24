<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['manual']));

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

foreach (array_filter((['manual']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<?php
    $state = $manual->guidance_check_status;
    $results = $manual->guidance_check_results ?? [];
    $checks = $results['checks'] ?? [];
    $finished = count(array_filter($checks, fn (array $check): bool => $check['status'] !== 'pending'));
    $labels = ['pending' => 'Waiting', 'checked' => 'Edition, pages and citation format checked', 'invalid_references' => 'Reference check needs attention', 'no_evidence' => 'No matching evidence found', 'unavailable' => 'Answer service unavailable'];
?>
<section data-manual-checks data-check-status="<?php echo e($state); ?>" data-status-url="<?php echo e(route('research-manuals.show', $manual)); ?>" class="space-y-4 rounded-3xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900 sm:p-6" aria-labelledby="manual-checks-heading">
    <div>
        <h2 id="manual-checks-heading" class="text-lg font-black tracking-tight text-slate-950 dark:text-white">Automated guidance checks</h2>
        <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">The system chooses sample questions from known guidance topics and this edition's sections. No questions or expected answers are needed from you.</p>
        <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">These checks verify retrieved edition, indexed page ranges and citation format in sample answers. They do not certify that every answer is correct or that every claim is supported. Activation remains your decision.</p>
    </div>
    <p role="status" aria-live="polite" class="text-sm font-bold text-slate-800 dark:text-slate-200">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($state === 'pending'): ?>
            Checks queued automatically. Results will appear here as the system works.
        <?php elseif($state === 'running'): ?>
            Checks running — <?php echo e($finished); ?> of <?php echo e(count($checks)); ?> sample questions processed.
        <?php elseif($state === 'completed'): ?>
            Checks completed · <?php echo e($manual->guidance_check_completed_at?->format('M j, Y g:i A')); ?>. Review any missing evidence or unavailable answers below.
        <?php elseif($state === 'failed'): ?>
            <?php echo e($results['message'] ?? 'Automated checking stopped. The system could not complete this edition’s checks.'); ?>

        <?php else: ?>
            No automated run is recorded for this previously activated edition.
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </p>
    <p data-manual-checks-poll-error hidden role="status" class="text-sm text-amber-800 dark:text-amber-200">Results are temporarily unavailable. Reconnecting automatically…</p>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($results['unindexed_pages'] ?? []) !== []): ?>
        <p class="rounded-xl bg-amber-50 p-4 text-sm leading-6 text-amber-900 dark:bg-amber-950/40 dark:text-amber-100">Unreadable pages excluded from guidance: <?php echo e(implode(', ', $results['unindexed_pages'])); ?>. Sample checks cannot cover their missing text.</p>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($checks !== []): ?>
        <ol class="divide-y divide-slate-200 dark:divide-slate-700">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $checks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $check): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <li class="py-4 first:pt-0 last:pb-0">
                    <details data-check-number="<?php echo e($loop->index); ?>">
                        <summary class="disclosure-summary text-sm font-bold leading-6 text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-600 dark:text-white">
                            <span class="min-w-0">
                                <?php echo e($check['question']); ?>

                                <span class="block pl-4 text-xs font-semibold text-slate-500 dark:text-slate-400"><?php echo e($labels[$check['status']] ?? 'Check unavailable'); ?></span>
                            </span>
                        </summary>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! empty($check['reply'])): ?>
                            <p class="mt-3 whitespace-pre-line break-words text-sm leading-7 text-slate-700 dark:text-slate-200"><?php echo e($check['reply']); ?></p>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! empty($check['message'])): ?>
                            <p class="mt-3 text-sm leading-6 text-amber-900 dark:text-amber-200"><?php echo e($check['message']); ?></p>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! empty($check['sources'])): ?>
                            <ul class="mt-3 space-y-2 text-sm" aria-label="Candidate edition evidence">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $check['sources']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $source): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                    <li><a href="<?php echo e($source['url']); ?>" class="secondary-action">[<?php echo e($source['reference']); ?>] <?php echo e($source['title']); ?></a></li>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            </ul>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </details>
                </li>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </ol>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</section>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\components\research-manual-checks.blade.php ENDPATH**/ ?>