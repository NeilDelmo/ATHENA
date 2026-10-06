<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['topic', 'version' => null, 'workspace' => null]));

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

foreach (array_filter((['topic', 'version' => null, 'workspace' => null]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    $reviews = $topic->reviews->where('decision', '!=', 'head_upload')->sortBy([['created_at', 'desc'], ['id', 'desc']])->values();
    $requiredFiles = $workspace['requiredSignatureFiles'] ?? collect();
    $signedIds = $workspace['signedSourceFileIds'] ?? collect();
    $signedCount = $requiredFiles->filter(fn ($file) => $signedIds->contains($file->id))->count();
    $released = $topic->hasIssuedNoticeToProceed();
?>

<div data-proposal-review-summary class="space-y-5">
    <header class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-950">
        <div class="flex items-start gap-4 px-5 py-6 sm:px-6">
            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-400" aria-hidden="true">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m9 12 2 2 4-4" /><circle cx="12" cy="12" r="9" /></svg>
            </span>
            <div class="min-w-0">
                <h3 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white"><?php echo e($released ? 'Proposal released' : 'Review complete'); ?></h3>
                <p class="mt-2 max-w-prose text-base leading-7 text-slate-600 dark:text-slate-400"><?php echo e($released ? 'The signed project and Notice to Proceed have been released. The review record remains available below.' : 'LREC review is complete. The proposal is cleared for signing; collect the signed papers before releasing the proposal.'); ?></p>
            </div>
        </div>
        <dl class="grid grid-cols-1 gap-4 border-t border-slate-100 bg-slate-50/70 px-5 py-4 text-base dark:border-slate-800 dark:bg-slate-900/50 sm:grid-cols-3 sm:px-6">
            <div><dt class="text-sm text-slate-500 dark:text-slate-400">Latest submission</dt><dd class="mt-1 font-semibold text-slate-800 dark:text-slate-200"><?php echo e($version ? 'Version '.$version->version_number : 'No submitted version'); ?></dd></div>
            <div><dt class="text-sm text-slate-500 dark:text-slate-400">Review record</dt><dd class="mt-1 font-semibold text-slate-800 dark:text-slate-200"><?php echo e($reviews->count()); ?> <?php echo e(str('decision')->plural($reviews->count())); ?></dd></div>
            <div><dt class="text-sm text-slate-500 dark:text-slate-400">Revision rounds</dt><dd class="mt-1 font-semibold text-slate-800 dark:text-slate-200"><?php echo e($reviews->where('decision', 'revision_requested')->count()); ?> requested</dd></div>
        </dl>
    </header>

    <div class="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_24rem]">
        <section aria-labelledby="review-timeline-heading" class="min-w-0 overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-950">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-5 py-4 dark:border-slate-800 sm:px-6">
                <h3 id="review-timeline-heading" class="text-lg font-bold text-slate-900 dark:text-white">Review timeline</h3>
                <span class="text-sm text-slate-500 dark:text-slate-400">Most recent first</span>
            </div>
            <ol data-review-timeline class="px-5 py-5 sm:px-6">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $reviews; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $review): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <?php
                        $decisionLabel = match ($review->decision) {
                            'ready_for_signature' => 'Cleared for signing',
                            'gad_review' => 'Cleared for GAD assessment',
                            'lrec_queued' => 'Sent to LREC',
                            'lrec_review' => 'LREC review opened',
                            'revision_requested' => 'Revision requested',
                            'approved' => 'Proposal approved',
                            'rejected' => 'Proposal rejected',
                            default => str($review->decision)->replace('_', ' ')->ucfirst(),
                        };
                    ?>
                    <li class="relative flex gap-4 <?php echo e($loop->last ? '' : 'pb-6'); ?>">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($loop->last)): ?><span class="absolute bottom-0 left-[15px] top-8 w-px bg-slate-200 dark:bg-slate-800" aria-hidden="true"></span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <span class="relative flex h-8 w-8 shrink-0 items-center justify-center rounded-full <?php echo e($loop->first ? 'bg-brand text-white' : 'border border-slate-200 bg-white text-slate-400 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-500'); ?>" aria-hidden="true">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="<?php echo e(match ($review->decision) { 'revision_requested' => 'M9 10 5 6l4-4M5 6h8a6 6 0 0 1 0 12h-2', 'rejected' => 'm6 6 12 12M6 18 18 6', default => 'm5 12 4 4L19 6' }); ?>" /></svg>
                        </span>
                        <div class="min-w-0 flex-1 pt-1">
                            <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                                <h4 class="text-base font-semibold text-slate-900 dark:text-slate-100"><?php echo e($decisionLabel); ?></h4>
                                <time datetime="<?php echo e($review->created_at->toIso8601String()); ?>" class="text-sm text-slate-500 dark:text-slate-400"><?php echo e($review->created_at->format('M j, Y')); ?></time>
                            </div>
                            <p class="mt-1 text-sm leading-6 text-slate-500 dark:text-slate-400"><?php echo e($review->reviewer?->name ?? 'Former reviewer'); ?> <span aria-hidden="true">&middot;</span> <?php echo e(match ($review->review_stage) { 'lrec' => 'LREC review', 'gad' => 'GAD / Co-evaluator review', default => 'Research Head review' }); ?></p>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($review->comment): ?><p class="mt-2 whitespace-pre-line break-words text-base leading-7 text-slate-600 dark:text-slate-300"><?php echo e($review->comment); ?></p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($review->decision === 'revision_requested'): ?>
                                <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300"><?php echo e($review->fileRevisions->count()); ?> <?php echo e(str('paper')->plural($review->fileRevisions->count())); ?> marked for revision. Feedback and faculty responses are in the full record.</p>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    </li>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    <li class="text-base leading-7 text-slate-500 dark:text-slate-400">No review decisions have been recorded. The proposal’s current status is shown above.</li>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </ol>
            <div class="border-t border-slate-100 px-5 py-3 dark:border-slate-800 sm:px-6">
                <button type="button" @click="setTopicTab('history', 'version-history')" class="inline-flex min-h-11 items-center gap-2 rounded-lg text-sm font-semibold text-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand dark:text-red-300">View full decisions and version history <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m9 5 7 7-7 7" /></svg></button>
            </div>
        </section>

        <aside class="min-w-0 space-y-5" aria-label="Proposal next steps">
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-950">
                <div class="border-t-4 border-brand px-5 pb-5 pt-4">
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white"><?php echo e($released ? 'Released documents' : 'Signing & release'); ?></h3>
                    <p class="mt-2 text-base leading-7 text-slate-600 dark:text-slate-400"><?php echo e($released ? 'Open the signed papers and the issued Notice to Proceed.' : 'Upload the signed proposal papers, then prepare the Notice to Proceed.'); ?></p>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($requiredFiles->isNotEmpty()): ?>
                        <div class="mt-5 flex items-center justify-between gap-3 text-sm"><span class="font-semibold text-slate-700 dark:text-slate-200">Signed papers</span><span data-review-signature-count class="font-semibold tabular-nums text-slate-500 dark:text-slate-400"><?php echo e($signedCount); ?> of <?php echo e($requiredFiles->count()); ?></span></div>
                        <ul class="mt-3 space-y-3" data-review-signature-checklist>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $requiredFiles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $file): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <li class="flex items-start gap-2.5 text-sm leading-6 text-slate-600 dark:text-slate-300">
                                    <span class="mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full <?php echo e($signedIds->contains($file->id) ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-400' : 'border border-slate-300 dark:border-slate-600'); ?>" aria-hidden="true"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($signedIds->contains($file->id)): ?><svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6" /></svg><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></span>
                                    <span><?php echo e($file->label()); ?><span class="sr-only">: <?php echo e($signedIds->contains($file->id) ? 'Signed copy uploaded' : 'Awaiting signed copy'); ?></span></span>
                                </li>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </ul>
                    <?php else: ?>
                        <p class="mt-4 text-sm leading-6 text-slate-500 dark:text-slate-400">No required proposal papers are available in the latest submission.</p>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <button type="button" @click="setTopicTab('notice', 'notice-to-proceed')" class="mt-5 inline-flex min-h-11 w-full items-center justify-center rounded-lg bg-brand px-4 py-2.5 text-base font-semibold text-white hover:bg-brand-soft focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"><?php echo e($released ? 'View released documents' : 'Continue to signing'); ?></button>
                </div>
            </section>
            <section class="px-1">
                <h3 class="text-base font-semibold text-slate-800 dark:text-slate-200">Latest project</h3>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($version): ?>
                    <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">Version <?php echo e($version->version_number); ?> submitted <?php echo e($version->created_at->format('M j, Y')); ?> by <?php echo e($version->submitter?->name ?? $topic->user->name); ?>.</p>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <button type="button" @click="$dispatch('open-project-documents')" class="mt-2 inline-flex min-h-11 items-center gap-2 rounded-lg text-sm font-semibold text-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand dark:text-red-300"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7a2 2 0 0 1 2-2h5l2 2h7a2 2 0 0 1 2 2v10H3V7Z" /></svg>Open proposal files</button>
            </section>
        </aside>
    </div>
</div>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views/components/proposal-review-summary.blade.php ENDPATH**/ ?>