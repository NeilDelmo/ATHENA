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

    <div class="-mx-4 -my-6 min-h-full bg-slate-50/80 px-4 py-6 dark:bg-slate-950 sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8">
        <div class="mx-auto max-w-6xl space-y-5">
            <?php if (isset($component)) { $__componentOriginal5426bd0bea02df2e6dd2a60e50fa4c01 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal5426bd0bea02df2e6dd2a60e50fa4c01 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.back-link','data' => ['fixed' => true,'href' => ''.e(route('topics.show', $topic)).'#proposal-review']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('back-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['fixed' => true,'href' => ''.e(route('topics.show', $topic)).'#proposal-review']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>
Back to proposal <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal5426bd0bea02df2e6dd2a60e50fa4c01)): ?>
<?php $attributes = $__attributesOriginal5426bd0bea02df2e6dd2a60e50fa4c01; ?>
<?php unset($__attributesOriginal5426bd0bea02df2e6dd2a60e50fa4c01); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal5426bd0bea02df2e6dd2a60e50fa4c01)): ?>
<?php $component = $__componentOriginal5426bd0bea02df2e6dd2a60e50fa4c01; ?>
<?php unset($__componentOriginal5426bd0bea02df2e6dd2a60e50fa4c01); ?>
<?php endif; ?>

            <section data-revision-summary class="flex flex-col gap-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900 sm:flex-row sm:items-start sm:justify-between sm:p-6" aria-labelledby="revision-workspace-title">
                <div class="flex min-w-0 gap-4">
                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-red-50 text-[#7A0019] dark:bg-red-950/40 dark:text-red-300" aria-hidden="true">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3.75h7.5l3 3v13.5H6.75V3.75Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M14.25 3.75v3h3M9.5 11h5M9.5 14.5h5" /></svg>
                    </span>
                    <div class="min-w-0">
                        <p class="text-xs font-black uppercase tracking-[0.12em] text-slate-400 dark:text-slate-500">Research proposal</p>
                        <h1 id="revision-workspace-title" class="mt-1 max-w-4xl text-xl font-black leading-tight tracking-tight text-slate-950 dark:text-white sm:text-2xl"><?php echo e($topic->title); ?></h1>
                        <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-xs font-medium text-slate-500 dark:text-slate-400">
                            <span class="inline-flex items-center gap-1.5"><svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3.75h7.5l3 3v13.5H6.75V3.75Z" /></svg>Proposal #<?php echo e($topic->id); ?></span>
                            <span class="inline-flex items-center gap-1.5"><svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h5M20 20v-5h-5" /><path stroke-linecap="round" stroke-linejoin="round" d="M5.5 15a7 7 0 0 0 12.9 2.3M18.5 9A7 7 0 0 0 5.6 6.7" /></svg>Version <?php echo e($latestVersion?->version_number ?? 1); ?></span>
                            <span class="inline-flex items-center gap-1.5"><svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="8" r="3.2" /><path stroke-linecap="round" d="M5 20c1-3.5 4-5.5 7-5.5s6 2 7 5.5" /></svg>Requested by <?php echo e($latestRevisionReview?->reviewer?->name ?? 'Research Head'); ?></span>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($latestVersion?->created_at): ?>
                                <span class="inline-flex items-center gap-1.5"><svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3.5" y="5" width="17" height="15.5" rx="2" /><path stroke-linecap="round" d="M3.5 9.5h17M8 3v3.5M16 3v3.5" /></svg>Submitted <?php echo e($latestVersion->created_at->format('M d, Y · h:i A')); ?></span>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    </div>
                </div>
                <span class="inline-flex w-fit shrink-0 items-center gap-1.5 rounded-full border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-black text-[#7A0019] dark:border-red-900 dark:bg-red-950/40 dark:text-red-300">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path stroke-linecap="round" d="M12 8v5M12 16h.01" /></svg>
                    Revision required
                </span>
            </section>

            <main class="min-w-0">
                <?php if (isset($component)) { $__componentOriginala3b9cea561492d335854a713e6390818 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginala3b9cea561492d335854a713e6390818 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-revision-form','data' => ['commentResponseRows' => $commentResponseRows,'topic' => $topic,'pendingFileRevisions' => $pendingFileRevisions,'stagedRevisionFiles' => $stagedRevisionFiles,'displayProjectCost' => $displayProjectCost]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-revision-form'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['comment-response-rows' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($commentResponseRows),'topic' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($topic),'pending-file-revisions' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($pendingFileRevisions),'staged-revision-files' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($stagedRevisionFiles),'display-project-cost' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($displayProjectCost)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginala3b9cea561492d335854a713e6390818)): ?>
<?php $attributes = $__attributesOriginala3b9cea561492d335854a713e6390818; ?>
<?php unset($__attributesOriginala3b9cea561492d335854a713e6390818); ?>
<?php endif; ?>
<?php if (isset($__componentOriginala3b9cea561492d335854a713e6390818)): ?>
<?php $component = $__componentOriginala3b9cea561492d335854a713e6390818; ?>
<?php unset($__componentOriginala3b9cea561492d335854a713e6390818); ?>
<?php endif; ?>
            </main>
        </div>
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
<?php /**PATH C:\laragon\www\athena-app\src\resources\views/faculty/topics/revision.blade.php ENDPATH**/ ?>