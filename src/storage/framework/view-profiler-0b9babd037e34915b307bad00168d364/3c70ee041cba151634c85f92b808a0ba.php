<?php
    $reviewedCount = $reviewedFileIds->count();
    $totalFiles = $files->count();
    $reviewComplete = $totalFiles > 0 && $reviewedCount === $totalFiles;
?>

<div data-research-head-file-checklist>
    <div class="border-b border-gray-100 bg-gray-50/80 px-5 py-4 dark:border-gray-800 dark:bg-gray-900/60 sm:px-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h4 class="text-sm font-black text-gray-950 dark:text-white">Your paper review checklist</h4>
                    <span class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-wider <?php echo e($reviewComplete ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-200' : 'bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-200'); ?>">
                        <?php echo e($reviewComplete ? 'Review complete' : 'In progress'); ?>

                    </span>
                </div>
                <p class="mt-1 text-xs leading-5 text-gray-600 dark:text-gray-400">Mark a paper only after checking it. This is a private progress guide and does not block your final decision.</p>
            </div>
            <p class="shrink-0 text-sm font-black <?php echo e($reviewComplete ? 'text-emerald-700 dark:text-emerald-300' : 'text-gray-700 dark:text-gray-300'); ?>" data-review-progress>
                <?php echo e($reviewedCount); ?> of <?php echo e($totalFiles); ?> reviewed
            </p>
        </div>
        <progress
            value="<?php echo e($reviewedCount); ?>"
            max="<?php echo e(max($totalFiles, 1)); ?>"
            aria-label="<?php echo e($reviewedCount); ?> of <?php echo e($totalFiles); ?> submitted papers reviewed"
            class="mt-3 h-2 w-full overflow-hidden rounded-full accent-emerald-600"
        ></progress>
    </div>

    <div class="divide-y divide-gray-100 dark:divide-gray-800">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $files; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $file): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <?php
                $fileAvailable = $availableFileIds->contains($file->id);
                $fileViewable = $viewableFileIds->contains($file->id);
                $fileReviewed = $reviewedFileIds->contains($file->id);
                $reviewedAt = $file->reviewChecks->first()?->reviewed_at;
            ?>
            <article <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'proposal-file-review-'.e($file->id).''; ?>wire:key="proposal-file-review-<?php echo e($file->id); ?>" class="flex flex-col gap-4 px-5 py-4 transition-colors sm:flex-row sm:items-center sm:justify-between sm:px-6 <?php echo e($fileReviewed ? 'bg-emerald-50/60 dark:bg-emerald-950/15' : 'bg-white dark:bg-gray-950'); ?>">
                <div class="flex min-w-0 items-start gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl <?php echo e($fileReviewed ? 'bg-emerald-600 text-white' : ($fileAvailable ? 'bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-200' : 'bg-gray-100 text-gray-400 dark:bg-gray-900 dark:text-gray-500')); ?>">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($fileReviewed): ?>
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12.75 4 4L19 6.75" /></svg>
                        <?php else: ?>
                            <span class="text-[10px] font-black">PDF</span>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </span>
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h4 class="text-sm font-black text-gray-900 dark:text-white"><?php echo e($file->label()); ?></h4>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($fileReviewed): ?>
                                <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-black uppercase tracking-wider text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-200">Reviewed</span>
                            <?php elseif(! $fileAvailable): ?>
                                <span class="rounded-full bg-red-50 px-2 py-0.5 text-[10px] font-black uppercase tracking-wider text-red-700 dark:bg-red-950/40 dark:text-red-200">Unavailable</span>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                        <p class="mt-1 break-all text-sm font-semibold text-gray-600 dark:text-gray-300"><?php echo e($file->original_filename); ?></p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            <?php echo e($file->file_size ? \Illuminate\Support\Number::fileSize($file->file_size) : 'Size unavailable'); ?>

                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($file->is_carried_forward): ?> &middot; Carried forward from an earlier version <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($reviewedAt): ?> &middot; Marked <?php echo e($reviewedAt->diffForHumans()); ?> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </p>
                    </div>
                </div>

                <div class="grid w-full shrink-0 grid-cols-2 gap-2 sm:flex sm:w-auto">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($fileViewable): ?>
                        <a href="<?php echo e(route('topics.versions.files.view', [$topic, $version, $file])); ?>" target="_blank" rel="noopener" class="inline-flex items-center justify-center rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-xs font-bold text-gray-700 transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-700 focus:ring-offset-2 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800">View</a>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($fileAvailable): ?>
                        <a href="<?php echo e(route('topics.versions.files.download', [$topic, $version, $file])); ?>" class="inline-flex items-center justify-center rounded-xl border border-gray-900 bg-gray-900 px-4 py-2.5 text-xs font-bold text-white transition hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-900 focus:ring-offset-2 dark:border-white dark:bg-white dark:text-gray-950">Download</a>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <button
                        type="button"
                        wire:click="toggle(<?php echo e($file->id); ?>)"
                        wire:loading.attr="disabled"
                        wire:target="toggle(<?php echo e($file->id); ?>)"
                        aria-pressed="<?php echo e($fileReviewed ? 'true' : 'false'); ?>"
                        class="col-span-2 inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-xs font-black transition focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:cursor-wait disabled:opacity-60 sm:col-span-1 <?php echo e($fileReviewed ? 'border border-emerald-300 bg-emerald-50 text-emerald-800 hover:bg-emerald-100 focus:ring-emerald-600 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200' : 'border border-red-200 bg-red-50 text-red-700 hover:bg-red-100 focus:ring-red-600 dark:border-red-900 dark:bg-red-950/30 dark:text-red-200'); ?>"
                    >
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($fileReviewed): ?>
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12.75 4 4L19 6.75" /></svg>
                            Reviewed
                        <?php else: ?>
                            Mark reviewed
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </button>
                </div>
            </article>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            <div class="p-8 text-center">
                <p class="text-sm font-black text-gray-800 dark:text-gray-200">No individual submitted files are available</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Legacy proposals may only provide a combined proposal download.</p>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
</div>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\livewire\research-head-proposal-file-checklist.blade.php ENDPATH**/ ?>