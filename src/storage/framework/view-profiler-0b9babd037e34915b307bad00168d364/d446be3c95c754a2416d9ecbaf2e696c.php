<?php
    $unindexedPages = $index['unindexed_pages'] ?? $manual->unindexed_pages ?? [];
    $effectiveDateIsFuture = $manual->effective_date?->isAfter(today()) ?? false;
?>

<?php if (isset($component)) { $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54 = $attributes; } ?>
<?php $component = App\View\Components\AppLayout::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('app-layout'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\AppLayout::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

     <?php $__env->slot('header', null, []); ?> 
        <?php if (isset($component)) { $__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.page-header','data' => ['title' => 'Review manual edition','subtitle' => $manual->title]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('page-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Review manual edition','subtitle' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($manual->title)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

             <?php $__env->slot('actions', null, []); ?> 
                <?php if (isset($component)) { $__componentOriginal5426bd0bea02df2e6dd2a60e50fa4c01 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal5426bd0bea02df2e6dd2a60e50fa4c01 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.back-link','data' => ['href' => ''.e(route('research-manuals.index')).'']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('back-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => ''.e(route('research-manuals.index')).'']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>
Back to manuals <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal5426bd0bea02df2e6dd2a60e50fa4c01)): ?>
<?php $attributes = $__attributesOriginal5426bd0bea02df2e6dd2a60e50fa4c01; ?>
<?php unset($__attributesOriginal5426bd0bea02df2e6dd2a60e50fa4c01); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal5426bd0bea02df2e6dd2a60e50fa4c01)): ?>
<?php $component = $__componentOriginal5426bd0bea02df2e6dd2a60e50fa4c01; ?>
<?php unset($__componentOriginal5426bd0bea02df2e6dd2a60e50fa4c01); ?>
<?php endif; ?>
                <a href="<?php echo e(route('research-manuals.download', $manual)); ?>" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-red-600 px-5 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 dark:focus:ring-offset-slate-950">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3.75V16.5m0 0 4.5-4.5M12 16.5 7.5 12M3.75 15v3.75a1.5 1.5 0 0 0 1.5 1.5h13.5a1.5 1.5 0 0 0 1.5-1.5V15" /></svg>
                    Download PDF
                </a>
             <?php $__env->endSlot(); ?>
         <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e)): ?>
<?php $attributes = $__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e; ?>
<?php unset($__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e)): ?>
<?php $component = $__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e; ?>
<?php unset($__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e); ?>
<?php endif; ?>
     <?php $__env->endSlot(); ?>

    <div class="mx-auto max-w-7xl space-y-5" data-research-manual-review>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('success')): ?>
            <div class="flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800 dark:border-emerald-900/70 dark:bg-emerald-950/30 dark:text-emerald-200" role="status">
                <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 4.5 4.5 10.5-10.5" /></svg>
                <span><?php echo e(session('success')); ?></span>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-6" aria-labelledby="edition-details-heading">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 id="edition-details-heading" class="text-lg font-black tracking-tight text-slate-950 dark:text-white">Edition details</h2>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isActive): ?>
                    <span class="inline-flex rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-800 ring-1 ring-inset ring-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-200 dark:ring-emerald-900">Current manual</span>
                <?php elseif($manual->activated_at): ?>
                    <span class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300">Previously activated</span>
                <?php else: ?>
                    <span class="inline-flex rounded-full bg-amber-50 px-3 py-1 text-xs font-bold text-amber-800 ring-1 ring-inset ring-amber-200 dark:bg-amber-950/40 dark:text-amber-200 dark:ring-amber-900">Awaiting review</span>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
            <dl class="mt-5 grid gap-x-6 gap-y-5 text-sm sm:grid-cols-2 lg:grid-cols-4">
                <div><dt class="text-slate-500 dark:text-slate-400">Edition</dt><dd class="mt-1 break-words font-bold text-slate-900 dark:text-white"><?php echo e($manual->edition); ?></dd></div>
                <div><dt class="text-slate-500 dark:text-slate-400">Effective date</dt><dd class="mt-1 font-bold text-slate-900 dark:text-white"><?php echo e($manual->effective_date?->format('M j, Y') ?? 'Not recorded'); ?></dd></div>
                <div><dt class="text-slate-500 dark:text-slate-400">Pages</dt><dd class="mt-1 font-bold text-slate-900 dark:text-white"><?php echo e(number_format($manual->page_count)); ?></dd></div>
                <div><dt class="text-slate-500 dark:text-slate-400">Uploaded by</dt><dd class="mt-1 font-bold text-slate-900 dark:text-white"><?php echo e($manual->uploadedBy?->name ?? 'Existing manual'); ?><span class="mt-1 block text-xs font-normal text-slate-500 dark:text-slate-400"><?php echo e($manual->created_at->format('M j, Y')); ?></span></dd></div>
                <div class="sm:col-span-2 lg:col-span-4"><dt class="text-slate-500 dark:text-slate-400">Version reference</dt><dd class="mt-1 break-all text-xs font-semibold text-slate-600 dark:text-slate-300"><?php echo e($manual->version); ?></dd></div>
            </dl>
        </section>

        <?php if (isset($component)) { $__componentOriginal08dbe30f92c018abcc7ecd939aab2500 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal08dbe30f92c018abcc7ecd939aab2500 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.research-manual-checks','data' => ['manual' => $manual]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('research-manual-checks'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['manual' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($manual)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal08dbe30f92c018abcc7ecd939aab2500)): ?>
<?php $attributes = $__attributesOriginal08dbe30f92c018abcc7ecd939aab2500; ?>
<?php unset($__attributesOriginal08dbe30f92c018abcc7ecd939aab2500); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal08dbe30f92c018abcc7ecd939aab2500)): ?>
<?php $component = $__componentOriginal08dbe30f92c018abcc7ecd939aab2500; ?>
<?php unset($__componentOriginal08dbe30f92c018abcc7ecd939aab2500); ?>
<?php endif; ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($unindexedPages) > 0): ?>
            <aside class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-100" aria-labelledby="missing-text-heading" role="alert">
                <div class="flex items-start gap-3">
                    <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.3 3.9L10.4 3.7a1.875 1.875 0 0 1 3.2 0l7.7 12.95a1.875 1.875 0 0 1-1.6 2.85H4.3a1.875 1.875 0 0 1-1.6-2.85ZM12 15.75h.008v.008H12v-.008Z" /></svg>
                    <div class="min-w-0">
                        <h2 id="missing-text-heading" class="text-sm font-bold">Some pages need a PDF review</h2>
                        <p class="mt-1 max-w-3xl text-sm leading-6 text-amber-800 dark:text-amber-200">Text could not be read on the pages below. Review them in the PDF before activating this edition. Their content may be missing from research guidance.</p>
                        <ul class="mt-3 flex flex-wrap gap-2 text-xs font-bold" aria-label="Pages with unreadable text">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $unindexedPages; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $page): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <li><a href="#page=<?php echo e($page); ?>" x-data x-on:click.prevent="$dispatch('research-manual:page', { version: <?php echo \Illuminate\Support\Js::from($manual->version)->toHtml() ?>, page: <?php echo e($page); ?> })" class="secondary-action">Page <?php echo e($page); ?></a></li>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </ul>
                    </div>
                </div>
            </aside>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <section id="manual-comparison" class="space-y-5 rounded-3xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900 sm:p-6" aria-labelledby="manual-comparison-heading" data-manual-comparison>
            <div>
                <h2 id="manual-comparison-heading" class="text-lg font-black tracking-tight text-slate-950 dark:text-white">Compare with the current manual</h2>
                <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">Review differences in extracted text before activation. Formatting is ignored. Renamed or moved sections and PDF extraction changes may appear as added or removed text. Highlights include words between separate edits in a passage.</p>
            </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($comparisonError): ?>
                <p role="alert" class="rounded-xl bg-amber-50 p-4 text-sm text-amber-900 dark:bg-amber-950/40 dark:text-amber-100">Comparison is unavailable: <?php echo e($comparisonError); ?> Review both PDFs before activation.</p>
            <?php elseif($isActive): ?>
                <p class="text-sm text-slate-600 dark:text-slate-300">This is the current edition. Open an uploaded edition to compare it before activation.</p>
            <?php elseif($comparison === null): ?>
                <p class="text-sm text-slate-600 dark:text-slate-300">There is no current manual to compare. Review this edition and its guidance before first activation.</p>
            <?php else: ?>
                <?php if (isset($component)) { $__componentOriginalc1211bd904cb5c58d2da523c68c925ec = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc1211bd904cb5c58d2da523c68c925ec = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.research-manual-reader','data' => ['reader' => $currentReader]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('research-manual-reader'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['reader' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($currentReader)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

                     <?php $__env->slot('summary', null, []); ?> 
                        <h3 class="font-bold text-slate-900 dark:text-white">Current: <?php echo e($currentReader['title']); ?></h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400"><?php echo e($currentReader['edition'] ?? 'Legacy edition'); ?></p>
                     <?php $__env->endSlot(); ?>
                 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalc1211bd904cb5c58d2da523c68c925ec)): ?>
<?php $attributes = $__attributesOriginalc1211bd904cb5c58d2da523c68c925ec; ?>
<?php unset($__attributesOriginalc1211bd904cb5c58d2da523c68c925ec); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc1211bd904cb5c58d2da523c68c925ec)): ?>
<?php $component = $__componentOriginalc1211bd904cb5c58d2da523c68c925ec; ?>
<?php unset($__componentOriginalc1211bd904cb5c58d2da523c68c925ec); ?>
<?php endif; ?>
                <div class="flex flex-wrap gap-2 text-xs font-semibold text-slate-600 dark:text-slate-300" aria-label="Extracted text comparison summary">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = ['changed' => 'changed passages', 'added' => 'added passages', 'removed' => 'removed passages', 'unreadable' => 'unmatched passages needing PDF review']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $type => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <span class="rounded-lg bg-slate-100 px-3 py-2 dark:bg-slate-800"><?php echo e($comparisonCounts[$type] ?? 0); ?> <?php echo e($label); ?></span>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    <span class="rounded-lg bg-slate-100 px-3 py-2 dark:bg-slate-800"><?php echo e($comparison['unchanged_sections']); ?> unchanged sections</span>
                </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($currentReader['unindexed_pages'] !== [] || $unindexedPages !== []): ?>
                    <aside class="rounded-xl bg-amber-50 p-4 text-sm leading-6 text-amber-900 dark:bg-amber-950/40 dark:text-amber-100">
                        <p class="font-bold">Unreadable pages are excluded from the comparison.</p>
                        <p>Missing extracted text does not establish that a policy was added or removed. Review these PDF pages and any unmatched passages.</p>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = [['reader' => $currentReader, 'label' => 'Current edition'], ['reader' => $manualReader, 'label' => 'Uploaded edition']]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $edition): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($edition['reader']['unindexed_pages'] !== []): ?>
                                <p class="mt-2 font-semibold"><?php echo e($edition['label']); ?>:</p>
                                <ul class="flex flex-wrap gap-x-4 gap-y-1" aria-label="<?php echo e($edition['label']); ?> unreadable pages">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $edition['reader']['unindexed_pages']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $page): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                        <li><a href="<?php echo e($edition['reader']['read_url']); ?>#page=<?php echo e($page); ?>" x-data x-on:click.prevent="$dispatch('research-manual:page', { version: <?php echo \Illuminate\Support\Js::from($edition['reader']['version'])->toHtml() ?>, page: <?php echo e($page); ?> })" class="secondary-action">Page <?php echo e($page); ?></a></li>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                </ul>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </aside>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $comparisonChanges; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $change): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <article class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-700">
                        <div class="flex flex-wrap items-center justify-between gap-2 bg-slate-50 px-4 py-3 dark:bg-slate-950/50">
                            <h3 class="break-words text-sm font-bold text-slate-900 dark:text-white"><?php echo e($change['section']); ?></h3>
                            <span class="text-xs font-bold text-slate-600 dark:text-slate-300"><?php echo e($change['source_kind'] === 'appendix' ? 'Manual appendix · ' : ''); ?><?php echo e($change['type'] === 'unreadable' ? 'Unmatched text — PDF review needed' : ucfirst($change['type']).' extracted text'); ?></span>
                        </div>
                        <div class="grid divide-y divide-slate-200 dark:divide-slate-700 md:grid-cols-2 md:divide-x md:divide-y-0">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = [['passage' => $change['before'], 'reader' => $currentReader, 'label' => 'Current edition', 'before' => true], ['passage' => $change['after'], 'reader' => $manualReader, 'label' => 'Uploaded edition', 'before' => false]]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $side): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <div class="min-w-0 p-4">
                                    <p class="text-xs font-bold text-slate-500 dark:text-slate-400"><?php echo e($side['label']); ?> · <?php echo e($side['reader']['edition'] ?? 'Legacy edition'); ?></p>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($side['passage']): ?>
                                        <a href="<?php echo e($side['reader']['read_url']); ?>#page=<?php echo e($side['passage']['page_start']); ?>" x-data x-on:click.prevent="$dispatch('research-manual:page', { version: <?php echo \Illuminate\Support\Js::from($side['reader']['version'])->toHtml() ?>, page: <?php echo e($side['passage']['page_start']); ?> })" class="secondary-action"><?php echo e($side['passage']['page_start'] === $side['passage']['page_end'] ? 'Page '.$side['passage']['page_start'] : 'Pages '.$side['passage']['page_start'].'–'.$side['passage']['page_end']); ?></a>
                                        <p class="break-words text-sm leading-7 text-slate-700 dark:text-slate-200"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $side['passage']['segments']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $segment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($segment['changed']): ?><mark class="<?php echo \Illuminate\Support\Arr::toCssClasses(['bg-red-100 text-red-900 dark:bg-red-950 dark:text-red-200 line-through' => $side['before'], 'bg-emerald-100 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-200' => ! $side['before']]); ?>"><?php echo e($segment['text']); ?></mark><?php else: ?><?php echo e($segment['text']); ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></p>
                                    <?php else: ?>
                                        <p class="mt-3 text-sm leading-6 text-slate-500 dark:text-slate-400">No matching extracted passage in this section.<?php echo e($change['type'] === 'unreadable' ? ' Check the unreadable PDF pages before interpreting this difference.' : ''); ?></p>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </div>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </div>
                    </article>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    <p class="text-sm text-slate-600 dark:text-slate-300">No differences were found in the indexed section text. Review PDF pages with unreadable text separately.</p>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php echo e($comparisonChanges->links()); ?>

                <p class="text-sm leading-6 text-slate-500 dark:text-slate-400">Changes to manual appendices, forms or approval steps may require separate application updates. This review does not change forms or workflows.</p>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </section>

        <?php if (isset($component)) { $__componentOriginalc1211bd904cb5c58d2da523c68c925ec = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc1211bd904cb5c58d2da523c68c925ec = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.research-manual-reader','data' => ['reader' => $manualReader,'class' => 'rounded-3xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900 sm:p-6']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('research-manual-reader'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['reader' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($manualReader),'class' => 'rounded-3xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900 sm:p-6']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

             <?php $__env->slot('summary', null, []); ?> 
                <h2 class="font-bold text-slate-900 dark:text-white"><?php echo e($isActive ? 'Current edition' : 'Uploaded edition'); ?>: <?php echo e($manual->title); ?></h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400"><?php echo e($manual->edition); ?> · <?php echo e(number_format($manual->page_count)); ?> pages</p>
             <?php $__env->endSlot(); ?>
         <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalc1211bd904cb5c58d2da523c68c925ec)): ?>
<?php $attributes = $__attributesOriginalc1211bd904cb5c58d2da523c68c925ec; ?>
<?php unset($__attributesOriginalc1211bd904cb5c58d2da523c68c925ec); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc1211bd904cb5c58d2da523c68c925ec)): ?>
<?php $component = $__componentOriginalc1211bd904cb5c58d2da523c68c925ec; ?>
<?php unset($__componentOriginalc1211bd904cb5c58d2da523c68c925ec); ?>
<?php endif; ?>

        <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-6" aria-labelledby="activate-manual-heading">
            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,2fr)] lg:gap-10">
                <div>
                    <h2 id="activate-manual-heading" class="text-lg font-black tracking-tight text-slate-950 dark:text-white">Activate this edition</h2>
                    <p class="mt-2 max-w-sm text-sm leading-6 text-slate-500 dark:text-slate-400">Activation updates the manual used for general guidance. Existing proposals keep their assigned edition.</p>
                    <p class="mt-4 max-w-sm text-sm leading-6 text-slate-500 dark:text-slate-400">Review forms and approval steps separately if the new policy changes them.</p>
                </div>
                <div class="min-w-0">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isActive): ?>
                        <p class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold leading-6 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-200">This edition is already the current manual for general research guidance.</p>
                        <button type="button" disabled class="mt-4 inline-flex min-h-11 cursor-default items-center justify-center rounded-xl bg-slate-100 px-5 py-3 text-sm font-bold text-slate-500 dark:bg-slate-800 dark:text-slate-400">Current edition</button>
                    <?php else: ?>
                        <form method="POST" action="<?php echo e(route('research-manuals.activate', $manual)); ?>" x-data="{ submitting: false }" x-on:submit="if (submitting) { $event.preventDefault(); } else { submitting = true; }" x-bind:aria-busy="submitting" class="space-y-4">
                            <?php echo csrf_field(); ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?>
                                <div role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">
                                    <p class="font-bold">This edition could not be activated.</p>
                                    <ul class="mt-2 list-disc space-y-1 pl-5">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                            <li><?php echo e($error); ?></li>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                    </ul>
                                </div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($effectiveDateIsFuture): ?>
                                <p id="activation-date-note" class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200">This edition can be activated on or after its effective date, <?php echo e($manual->effective_date->format('M j, Y')); ?>.</p>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <fieldset <?php if($effectiveDateIsFuture): echo 'disabled'; endif; ?> class="space-y-4 disabled:opacity-60">
                                <legend class="sr-only">Confirm the manual review</legend>
                                <div>
                                    <label for="manual-review-confirmation" class="flex cursor-pointer items-start gap-3 text-sm leading-6 text-slate-700 dark:text-slate-200">
                                        <input id="manual-review-confirmation" name="review_confirmation" type="checkbox" value="1" required <?php if(old('review_confirmation')): echo 'checked'; endif; ?> <?php $__errorArgs = ['review_confirmation'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> aria-invalid="true" aria-describedby="manual-review-confirmation-error" <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> class="mt-1 h-4 w-4 shrink-0 rounded border-slate-300 text-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-950">
                                        <span>I have reviewed this edition and confirm it is the approved manual for general research guidance.</span>
                                    </label>
                                    <?php if (isset($component)) { $__componentOriginalf94ed9c5393ef72725d159fe01139746 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf94ed9c5393ef72725d159fe01139746 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.input-error','data' => ['id' => 'manual-review-confirmation-error','messages' => $errors->get('review_confirmation'),'class' => 'mt-2']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('input-error'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['id' => 'manual-review-confirmation-error','messages' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($errors->get('review_confirmation')),'class' => 'mt-2']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalf94ed9c5393ef72725d159fe01139746)): ?>
<?php $attributes = $__attributesOriginalf94ed9c5393ef72725d159fe01139746; ?>
<?php unset($__attributesOriginalf94ed9c5393ef72725d159fe01139746); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalf94ed9c5393ef72725d159fe01139746)): ?>
<?php $component = $__componentOriginalf94ed9c5393ef72725d159fe01139746; ?>
<?php unset($__componentOriginalf94ed9c5393ef72725d159fe01139746); ?>
<?php endif; ?>
                                </div>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($unindexedPages) > 0): ?>
                                    <div>
                                        <label for="manual-unindexed-confirmation" class="flex cursor-pointer items-start gap-3 text-sm leading-6 text-slate-700 dark:text-slate-200">
                                            <input id="manual-unindexed-confirmation" name="acknowledge_unindexed_pages" type="checkbox" value="1" required <?php if(old('acknowledge_unindexed_pages')): echo 'checked'; endif; ?> <?php $__errorArgs = ['acknowledge_unindexed_pages'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> aria-invalid="true" aria-describedby="manual-unindexed-confirmation-error" <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> class="mt-1 h-4 w-4 shrink-0 rounded border-slate-300 text-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-950">
                                            <span>I have reviewed the pages with unreadable text in the PDF and understand their content may be missing from guidance.</span>
                                        </label>
                                        <?php if (isset($component)) { $__componentOriginalf94ed9c5393ef72725d159fe01139746 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf94ed9c5393ef72725d159fe01139746 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.input-error','data' => ['id' => 'manual-unindexed-confirmation-error','messages' => $errors->get('acknowledge_unindexed_pages'),'class' => 'mt-2']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('input-error'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['id' => 'manual-unindexed-confirmation-error','messages' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($errors->get('acknowledge_unindexed_pages')),'class' => 'mt-2']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalf94ed9c5393ef72725d159fe01139746)): ?>
<?php $attributes = $__attributesOriginalf94ed9c5393ef72725d159fe01139746; ?>
<?php unset($__attributesOriginalf94ed9c5393ef72725d159fe01139746); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalf94ed9c5393ef72725d159fe01139746)): ?>
<?php $component = $__componentOriginalf94ed9c5393ef72725d159fe01139746; ?>
<?php unset($__componentOriginalf94ed9c5393ef72725d159fe01139746); ?>
<?php endif; ?>
                                    </div>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <div class="border-t border-slate-100 pt-5 dark:border-slate-800">
                                    <button type="submit" <?php if($effectiveDateIsFuture): echo 'disabled'; endif; ?> x-bind:disabled="submitting || <?php echo \Illuminate\Support\Js::from($effectiveDateIsFuture)->toHtml() ?>" <?php if($effectiveDateIsFuture): ?> aria-describedby="activation-date-note" <?php endif; ?> class="inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-red-600 px-5 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto dark:focus:ring-offset-slate-900">
                                        <span x-show="!submitting">Activate edition</span>
                                        <span x-show="submitting" x-cloak>Activating…</span>
                                    </button>
                                </div>
                            </fieldset>
                        </form>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>
        </section>
    </div>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $attributes = $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $component = $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\research-manuals\show.blade.php ENDPATH**/ ?>