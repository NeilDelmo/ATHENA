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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.page-header','data' => ['title' => 'Faculty Announcements','subtitle' => 'Publish visual notices and control exactly when research-call posters appear.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('page-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Faculty Announcements','subtitle' => 'Publish visual notices and control exactly when research-call posters appear.']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

             <?php $__env->slot('actions', null, []); ?> 
                <?php if (isset($component)) { $__componentOriginal5426bd0bea02df2e6dd2a60e50fa4c01 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal5426bd0bea02df2e6dd2a60e50fa4c01 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.back-link','data' => ['href' => ''.e(route('research-calls.index')).'']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('back-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => ''.e(route('research-calls.index')).'']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>
Back to research calls <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal5426bd0bea02df2e6dd2a60e50fa4c01)): ?>
<?php $attributes = $__attributesOriginal5426bd0bea02df2e6dd2a60e50fa4c01; ?>
<?php unset($__attributesOriginal5426bd0bea02df2e6dd2a60e50fa4c01); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal5426bd0bea02df2e6dd2a60e50fa4c01)): ?>
<?php $component = $__componentOriginal5426bd0bea02df2e6dd2a60e50fa4c01; ?>
<?php unset($__componentOriginal5426bd0bea02df2e6dd2a60e50fa4c01); ?>
<?php endif; ?>
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

    <div class="space-y-6" data-announcement-palette="red-black-white">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('success')): ?>
            <div class="flex items-start gap-3 rounded-2xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-700 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200">
                <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-red-700" aria-hidden="true"></span>
                <p class="font-bold"><?php echo e(session('success')); ?></p>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <section class="relative isolate overflow-hidden rounded-3xl border border-gray-200 bg-white px-6 py-7 text-gray-950 shadow-xl shadow-gray-950/10 dark:border-slate-800 dark:bg-gray-950 dark:text-white sm:px-8 sm:py-9" aria-labelledby="announcement-overview-heading">
            <div class="pointer-events-none absolute -right-16 -top-24 h-64 w-64 rounded-full border-[44px] border-red-900/[0.04] dark:border-white/[0.04]" aria-hidden="true"></div>
            <div class="pointer-events-none absolute bottom-0 right-1/4 h-28 w-72 bg-red-700/25 blur-3xl" aria-hidden="true"></div>
            <div class="relative grid gap-6 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-end">
                <div class="max-w-2xl">
                    <span class="inline-flex rounded-full border border-red-200 bg-red-50 px-3 py-1 text-[10px] font-black uppercase tracking-[0.18em] text-red-700 dark:border-red-400/20 dark:bg-red-500/10 dark:text-red-200">Announcement visibility</span>
                    <h3 id="announcement-overview-heading" class="mt-4 text-2xl font-black tracking-tight sm:text-3xl">Publish with the right audience.</h3>
                    <p class="mt-3 text-sm leading-6 text-gray-600 dark:text-gray-300">Linked posters show only while submissions are open. General notices stay visible until archived.</p>
                </div>
                <dl class="grid grid-cols-2 gap-px overflow-hidden rounded-2xl bg-gray-200 text-center dark:bg-white/10">
                    <div class="min-w-28 bg-gray-50 px-5 py-4 dark:bg-white/[0.04]">
                        <dt class="text-[10px] font-black uppercase tracking-wider text-gray-400">Announcements</dt>
                        <dd class="mt-1 text-2xl font-black"><?php echo e($announcementImages->count()); ?></dd>
                    </div>
                    <div class="min-w-28 bg-gray-50 px-5 py-4 dark:bg-white/[0.04]">
                        <dt class="text-[10px] font-black uppercase tracking-wider text-gray-400">Open calls</dt>
                        <dd class="mt-1 text-2xl font-black text-red-700 dark:text-red-300"><?php echo e($researchCalls->filter->isAcceptingSubmissions()->count()); ?></dd>
                    </div>
                </dl>
            </div>
        </section>

        <section class="overflow-hidden rounded-3xl border border-gray-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900" aria-labelledby="announcement-upload-heading">
            <div class="border-b border-gray-100 px-5 py-5 dark:border-slate-800 sm:px-6">
                <div>
                    <h3 id="announcement-upload-heading" class="text-base font-black text-gray-950 dark:text-white">Publish an announcement</h3>
                    <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">JPG, PNG, or WebP up to 10 MB.</p>
                </div>
            </div>

            <form method="POST" action="<?php echo e(route('announcement-images.store')); ?>" enctype="multipart/form-data" class="grid gap-5 p-5 lg:grid-cols-[minmax(0,1fr)_20rem] lg:items-start sm:p-6" data-announcement-image-form>
                <?php echo csrf_field(); ?>
                <label for="announcement-image" data-announcement-image-dropzone tabindex="0" class="group flex min-h-72 cursor-pointer flex-col items-center justify-center overflow-hidden rounded-2xl border-2 border-dashed border-red-200 bg-red-50/40 p-4 text-center transition hover:border-red-500 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-700 dark:border-red-900/70 dark:bg-red-950/20 dark:hover:border-red-700">
                    <input id="announcement-image" name="image" type="file" accept="image/jpeg,image/png,image/webp" data-announcement-image class="sr-only">
                    <span data-announcement-image-empty class="flex flex-col items-center gap-3 px-5 py-8">
                        <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white text-red-700 shadow-sm dark:bg-slate-900 dark:text-red-300">
                            <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5V6.75A2.25 2.25 0 015.25 4.5h13.5A2.25 2.25 0 0121 6.75v10.5a2.25 2.25 0 01-2.25 2.25H8.25M3 16.5l3.75-3.75a2.25 2.25 0 013.182 0L12 13.818m-9 2.682 2.25 2.25m12-7.5 1.5-1.5M15 8.25h.008v.008H15V8.25z" /><path stroke-linecap="round" stroke-linejoin="round" d="M3 19.5h6" /></svg>
                        </span>
                        <span>
                            <span class="block text-sm font-black text-gray-800 dark:text-slate-100">Drop or paste your announcement</span>
                            <span class="mt-1 block text-xs text-gray-400">Click to browse &middot; Ctrl+V also works</span>
                        </span>
                    </span>
                    <img data-announcement-image-preview src="" alt="Selected announcement preview" class="hidden max-h-96 w-full rounded-xl object-contain">
                </label>

                <div class="flex flex-col gap-4 rounded-2xl border border-gray-200 bg-white p-5 text-gray-950 shadow-sm dark:border-slate-800 dark:bg-gray-950 dark:text-white">
                    <div>
                        <label for="announcement-research-call" class="text-xs font-black uppercase tracking-wider text-gray-600 dark:text-gray-300">Visibility rule</label>
                        <select id="announcement-research-call" name="research_call_id" class="mt-2 block w-full rounded-xl border-gray-300 bg-white text-sm font-semibold text-gray-900 focus:border-red-500 focus:ring-red-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                            <option value="">General announcement &mdash; show until archived</option>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $researchCalls; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $researchCall): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <option value="<?php echo e($researchCall->id); ?>" <?php if((string) old('research_call_id') === (string) $researchCall->id): echo 'selected'; endif; ?>>
                                    <?php echo e($researchCall->title); ?> (<?php echo e(ucfirst($researchCall->lifecycleStatus())); ?>)
                                </option>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </select>
                        <p class="mt-2 text-[11px] leading-5 text-gray-500 dark:text-gray-400">Choose a research call for call-for-proposals artwork. It will only appear while that call accepts submissions.</p>
                    </div>

                    <div class="mt-auto border-t border-gray-200 pt-4 dark:border-white/10">
                        <p data-announcement-image-name class="min-h-5 truncate text-xs font-bold text-gray-600 dark:text-gray-300"></p>
                        <button type="submit" class="mt-3 inline-flex w-full items-center justify-center rounded-xl bg-red-700 px-5 py-3 text-xs font-black text-white shadow-sm transition hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 focus:ring-offset-white dark:focus:ring-red-400 dark:focus:ring-offset-gray-950 disabled:cursor-not-allowed disabled:opacity-60">Publish announcement</button>
                        <p data-announcement-image-status role="status" class="mt-3 hidden text-xs font-semibold text-green-700 dark:text-green-300"></p>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?>
                            <p class="mt-3 rounded-xl bg-red-50 p-3 text-xs font-semibold text-red-800 dark:bg-red-950 dark:text-red-200"><?php echo e($errors->first()); ?></p>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </div>
            </form>
        </section>

        <section aria-labelledby="uploaded-announcements-heading">
            <div class="mb-4 flex items-end justify-between gap-4">
                <div>
                    <h3 id="uploaded-announcements-heading" class="text-lg font-black text-gray-950 dark:text-white">Active announcements</h3>
                    <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">Update an existing poster’s visibility rule at any time.</p>
                </div>
                <span class="rounded-full bg-gray-950 px-3 py-1 text-xs font-black text-white dark:bg-white dark:text-gray-950"><?php echo e($announcementImages->count()); ?></span>
            </div>

            <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $announcementImages; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $announcementImage): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <?php
                        $isVisible = $announcementImage->researchCall === null || $announcementImage->researchCall->isAcceptingSubmissions();
                    ?>
                    <article class="group overflow-hidden rounded-3xl border border-gray-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg dark:border-slate-800 dark:bg-slate-900">
                        <div class="relative flex h-72 items-center justify-center overflow-hidden bg-gray-100 p-3 dark:bg-slate-950">
                            <img src="<?php echo e(route('announcement-images.show', $announcementImage)); ?>" alt="Uploaded announcement image" class="h-full w-full object-contain transition duration-500 group-hover:scale-[1.02]" loading="lazy" decoding="async">
                            <span class="absolute left-3 top-3 rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-wider shadow-sm <?php echo e($isVisible ? 'bg-gray-950 text-white' : 'bg-red-700 text-white'); ?>">
                                <?php echo e($isVisible ? 'Visible' : 'Hidden with call'); ?>

                            </span>
                        </div>

                        <div class="space-y-4 border-t border-gray-200 p-4 dark:border-slate-800">
                            <div>
                                <p class="truncate text-xs font-black text-gray-900 dark:text-white"><?php echo e(basename($announcementImage->image_path)); ?></p>
                                <p class="mt-1 text-[11px] text-gray-500 dark:text-slate-400"><?php echo e($announcementImage->researchCall?->title ?? 'General announcement'); ?></p>
                            </div>

                            <form method="POST" action="<?php echo e(route('announcement-images.update', $announcementImage)); ?>" class="space-y-2">
                                <?php echo csrf_field(); ?>
                                <?php echo method_field('PATCH'); ?>
                                <label for="announcement-call-<?php echo e($announcementImage->id); ?>" class="text-[10px] font-black uppercase tracking-wider text-gray-500 dark:text-slate-400">Linked research call</label>
                                <div class="flex gap-2">
                                    <select id="announcement-call-<?php echo e($announcementImage->id); ?>" name="research_call_id" class="min-w-0 flex-1 rounded-xl border-gray-200 text-xs focus:border-red-600 focus:ring-red-600 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                                        <option value="">General announcement</option>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $researchCalls; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $researchCall): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                            <option value="<?php echo e($researchCall->id); ?>" <?php if($announcementImage->research_call_id === $researchCall->id): echo 'selected'; endif; ?>><?php echo e($researchCall->title); ?></option>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                    </select>
                                    <button type="submit" class="shrink-0 rounded-xl bg-gray-950 px-3 py-2 text-[11px] font-black text-white transition hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 dark:bg-white dark:text-gray-950 dark:hover:bg-red-200">Save</button>
                                </div>
                            </form>

                            <form method="POST" action="<?php echo e(route('announcement-images.archive', $announcementImage)); ?>" class="border-t border-gray-100 pt-3 dark:border-slate-800">
                                <?php echo csrf_field(); ?>
                                <?php echo method_field('PATCH'); ?>
                                <button type="submit" class="secondary-action">Archive announcement</button>
                            </form>
                        </div>
                    </article>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    <div class="rounded-3xl border border-dashed border-gray-300 bg-white px-6 py-14 text-center dark:border-slate-700 dark:bg-slate-900 sm:col-span-2 xl:col-span-3">
                        <div class="mx-auto h-1 w-12 rounded-full bg-red-700" aria-hidden="true"></div>
                        <p class="mt-5 text-sm font-black text-gray-800 dark:text-slate-100">No announcements published yet.</p>
                        <p class="mt-1 text-xs text-gray-400">Upload the first poster above.</p>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </section>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($archivedAnnouncementImages->isNotEmpty()): ?>
            <section aria-labelledby="archived-announcements-heading" class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-6">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-[11px] font-black uppercase tracking-[0.18em] text-gray-500 dark:text-slate-400">History</p>
                        <h3 id="archived-announcements-heading" class="mt-1 text-lg font-black text-gray-950 dark:text-white">Archived announcements</h3>
                        <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">Hidden from faculty. Restore one whenever it needs to be published again.</p>
                    </div>
                    <span class="inline-flex w-fit rounded-full bg-gray-100 px-3 py-1 text-xs font-black text-gray-700 dark:bg-slate-800 dark:text-slate-200"><?php echo e($archivedAnnouncementImages->count()); ?></span>
                </div>
                <div class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $archivedAnnouncementImages; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $announcementImage): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <article class="overflow-hidden rounded-2xl border border-gray-200 bg-gray-50/70 dark:border-slate-800 dark:bg-slate-950/45">
                            <div class="flex h-44 items-center justify-center bg-gray-100 p-3 dark:bg-slate-950">
                                <img src="<?php echo e(route('announcement-images.show', $announcementImage)); ?>" alt="Archived announcement image" class="h-full w-full object-contain opacity-70" loading="lazy" decoding="async">
                            </div>
                            <div class="space-y-3 p-4">
                                <div>
                                    <p class="truncate text-xs font-black text-gray-900 dark:text-white"><?php echo e(basename($announcementImage->image_path)); ?></p>
                                    <p class="mt-1 text-[11px] text-gray-500 dark:text-slate-400">Archived <?php echo e($announcementImage->archived_at?->format('M d, Y')); ?></p>
                                </div>
                                <form method="POST" action="<?php echo e(route('announcement-images.restore', $announcementImage)); ?>">
                                    <?php echo csrf_field(); ?>
                                    <?php echo method_field('PATCH'); ?>
                                    <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl border border-gray-300 bg-white px-3 py-2.5 text-xs font-black text-gray-800 transition hover:border-red-200 hover:bg-red-50 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:hover:border-red-900 dark:hover:bg-red-950/40 dark:hover:text-red-300">Restore announcement</button>
                                </form>
                            </div>
                        </article>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </div>
            </section>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
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
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\announcement_images\index.blade.php ENDPATH**/ ?>