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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.page-header','data' => ['title' => 'Proposal signatory directory','subtitle' => 'Set the default name for each signature role. Editable proposals use these names automatically until you change them.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('page-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Proposal signatory directory','subtitle' => 'Set the default name for each signature role. Editable proposals use these names automatically until you change them.']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

             <?php $__env->slot('actions', null, []); ?> 
            <button type="button" x-data x-on:click="$dispatch('open-add-signatory-form')" class="inline-flex min-h-11 w-full shrink-0 items-center justify-center gap-2 rounded-xl bg-red-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-red-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-600 focus-visible:ring-offset-2 sm:w-auto dark:focus-visible:ring-offset-slate-900">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                Add a signatory
            </button>
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

    <div x-data="{ addSignatoryOpen: <?php echo \Illuminate\Support\Js::from($errors->any())->toHtml() ?> }" x-on:open-add-signatory-form.window="addSignatoryOpen = true" x-on:keydown.escape.window="addSignatoryOpen = false" class="mx-auto max-w-7xl space-y-4" data-signatory-directory>
        <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900" aria-label="Default paper signatories">
            <h3 class="text-sm font-bold text-gray-900 dark:text-white">Default paper signatories</h3>
            <dl class="mt-3 grid gap-4 sm:grid-cols-2">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $defaultSignatories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $roleKey => $signatory): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <div><dt class="text-xs text-gray-500 dark:text-slate-400"><?php echo e($roles[$roleKey]); ?></dt><dd class="mt-1 text-sm font-semibold text-gray-900 dark:text-white"><?php echo e($signatory['name']); ?></dd></div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </dl>
            <p class="mt-3 text-xs text-gray-500 dark:text-slate-400">Mark an active directory entry as the default for its role. Template names apply until a default is set. Submitted documents retain their original names.</p>
        </section>
        <div x-show="addSignatoryOpen" x-cloak class="pointer-events-none fixed inset-0 z-[80]" role="presentation">
            <button type="button" x-on:click="addSignatoryOpen = false" data-add-signatory-backdrop class="pointer-events-auto absolute inset-0 bg-slate-950/60 backdrop-blur-sm xl:hidden" aria-label="Close add signatory editor"></button>
            <section
                data-add-signatory-panel
                x-show="addSignatoryOpen"
                x-transition:enter="transition ease-out duration-200 motion-reduce:transition-none"
                x-transition:enter-start="translate-y-4 scale-95 opacity-0"
                x-transition:enter-end="translate-y-0 scale-100 opacity-100"
                x-transition:leave="transition ease-in duration-150 motion-reduce:transition-none"
                x-transition:leave-start="translate-y-0 scale-100 opacity-100"
                x-transition:leave-end="translate-y-4 scale-95 opacity-0"
                class="pointer-events-auto absolute inset-x-2 bottom-2 flex h-auto max-h-[calc(100dvh-1rem)] origin-bottom-right flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl dark:border-slate-700 dark:bg-slate-900 sm:inset-x-auto sm:bottom-4 sm:right-4 sm:max-h-[calc(100dvh-2rem)] sm:w-[min(40rem,calc(100vw-2rem))]"
                role="dialog"
                x-bind:aria-modal="window.matchMedia('(max-width: 1279px)').matches ? 'true' : null"
                aria-labelledby="add-signatory-heading"
            >
                <header class="flex shrink-0 items-center justify-between gap-4 border-b border-slate-200 bg-white px-5 py-4 dark:border-slate-800 dark:bg-slate-900 sm:px-6">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-red-700 dark:text-red-300">Signatory editor</p>
                        <h3 id="add-signatory-heading" class="mt-1 text-lg font-black text-slate-950 dark:text-white">Add a signatory</h3>
                    </div>
                    <button type="button" x-on:click="addSignatoryOpen = false" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-slate-200 text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-600 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white" aria-label="Close add signatory form">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18" /></svg>
                    </button>
                </header>

                <div class="min-h-0 flex-auto overflow-y-auto overscroll-contain bg-slate-50/70 dark:bg-slate-950/55">
                    <form action="<?php echo e(route('signatories.store')); ?>" method="POST" class="space-y-5 px-4 py-5 sm:px-6">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="active" value="1">

                        <section class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-5" aria-labelledby="new-signatory-details-heading">
                            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-red-700 dark:text-red-300">Signatory details</p>
                            <h4 id="new-signatory-details-heading" class="mt-1 text-lg font-black tracking-tight text-gray-950 dark:text-white">Name and designation</h4>
                            <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-slate-400">Add an approved name for faculty to use across proposal signature blocks.</p>

                            <div class="mt-5 grid gap-5 md:grid-cols-2">
                                <label class="block md:col-span-2" for="new-signatory-role">
                                    <span class="text-sm font-black text-gray-800 dark:text-slate-100">Signature role</span>
                                    <select id="new-signatory-role" name="role_key" required class="mt-2 block w-full rounded-xl border-gray-300 bg-white px-3.5 py-3 text-sm font-semibold text-gray-900 shadow-sm transition hover:border-gray-400 focus:border-red-600 focus:ring-red-600 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:hover:border-slate-500">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $roles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                            <option value="<?php echo e($key); ?>" <?php if(old('role_key') === $key): echo 'selected'; endif; ?>><?php echo e($label); ?></option>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                    </select>
                                    <?php if (isset($component)) { $__componentOriginalf94ed9c5393ef72725d159fe01139746 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf94ed9c5393ef72725d159fe01139746 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.input-error','data' => ['messages' => $errors->get('role_key'),'class' => 'mt-2']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('input-error'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['messages' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($errors->get('role_key')),'class' => 'mt-2']); ?>
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
                                </label>

                                <label class="block" for="new-signatory-name">
                                    <span class="text-sm font-black text-gray-800 dark:text-slate-100">Full name</span>
                                    <input id="new-signatory-name" name="name" value="<?php echo e(old('name')); ?>" required maxlength="120" autocomplete="name" placeholder="e.g. Dr. Ana M. Reyes" class="mt-2 block w-full rounded-xl border-gray-300 bg-white px-3.5 py-3 text-sm font-semibold text-gray-900 shadow-sm transition placeholder:text-gray-400 hover:border-gray-400 focus:border-red-600 focus:ring-red-600 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:hover:border-slate-500">
                                    <?php if (isset($component)) { $__componentOriginalf94ed9c5393ef72725d159fe01139746 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf94ed9c5393ef72725d159fe01139746 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.input-error','data' => ['messages' => $errors->get('name'),'class' => 'mt-2']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('input-error'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['messages' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($errors->get('name')),'class' => 'mt-2']); ?>
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
                                </label>

                                <label class="block" for="new-signatory-position">
                                    <span class="text-sm font-black text-gray-800 dark:text-slate-100">Position / designation</span>
                                    <input id="new-signatory-position" name="position" value="<?php echo e(old('position')); ?>" required maxlength="120" placeholder="e.g. Dean, College of Engineering" class="mt-2 block w-full rounded-xl border-gray-300 bg-white px-3.5 py-3 text-sm font-semibold text-gray-900 shadow-sm transition placeholder:text-gray-400 hover:border-gray-400 focus:border-red-600 focus:ring-red-600 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:hover:border-slate-500">
                                    <?php if (isset($component)) { $__componentOriginalf94ed9c5393ef72725d159fe01139746 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf94ed9c5393ef72725d159fe01139746 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.input-error','data' => ['messages' => $errors->get('position'),'class' => 'mt-2']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('input-error'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['messages' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($errors->get('position')),'class' => 'mt-2']); ?>
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
                                </label>
                            </div>

                            <div class="mt-5 flex justify-end border-t border-gray-100 pt-5 dark:border-slate-800">
                                <input type="hidden" name="is_default" value="0">
                                <label class="mr-auto flex items-center gap-2 text-sm text-gray-700 dark:text-slate-200"><input type="checkbox" name="is_default" value="1" <?php if(old('is_default')): echo 'checked'; endif; ?> class="rounded border-gray-300 text-red-700 focus:ring-red-600">Use as default for this role</label>
                                <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-red-600 px-5 py-3 text-sm font-black text-white shadow-sm shadow-red-600/20 transition hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 sm:w-auto dark:focus:ring-offset-slate-900">Add name</button>
                            </div>
                        </section>
                    </form>
                </div>
            </section>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('success')): ?>
            <div role="status" class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-800 dark:border-green-900 dark:bg-green-950/40 dark:text-green-300"><?php echo e(session('success')); ?></div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?>
            <div role="alert" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <p><?php echo e($error); ?></p>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <div class="grid items-start gap-6 min-[860px]:grid-cols-[15rem_minmax(0,1fr)]">
            <aside class="space-y-2.5 min-[860px]:sticky min-[860px]:top-5" aria-label="Directory summary">
                <span class="block rounded-xl border border-[#E7E2D8] bg-white px-4 py-3 text-[13px] text-[#6B6258] shadow-sm dark:border-slate-700 dark:bg-slate-900 dark:text-slate-400"><strong class="block text-xl font-black text-[#201A15] dark:text-white"><?php echo e(\Illuminate\Support\Number::format($summary['total'])); ?></strong>total signatories</span>
                <span class="block rounded-xl border border-[#E7E2D8] bg-white px-4 py-3 text-[13px] text-[#6B6258] shadow-sm dark:border-slate-700 dark:bg-slate-900 dark:text-slate-400"><strong class="block text-xl font-black text-[#201A15] dark:text-white"><?php echo e(\Illuminate\Support\Number::format($summary['active'])); ?></strong>active</span>
                <span class="block rounded-xl border border-[#E7E2D8] bg-white px-4 py-3 text-[13px] text-[#6B6258] shadow-sm dark:border-slate-700 dark:bg-slate-900 dark:text-slate-400"><strong class="block text-xl font-black text-[#201A15] dark:text-white"><?php echo e(\Illuminate\Support\Number::format($summary['roles'])); ?></strong>signature roles</span>

                <nav aria-label="Filter signatories by signature role" class="flex flex-col items-start gap-2 pt-1">
                    <a href="<?php echo e(route('signatories.index', array_filter(['search' => $search]))); ?>" class="<?php echo \Illuminate\Support\Arr::toCssClasses([
                        'inline-flex w-full items-center rounded-xl border px-3 py-2 text-xs font-bold transition',
                        'border-[#201A15] bg-[#201A15] text-white dark:border-white dark:bg-white dark:text-[#201A15]' => $selectedRole === '',
                        'border-[#E7E2D8] bg-white text-[#6B6258] hover:bg-[#F4F1EB] dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800' => $selectedRole !== '',
                    ]); ?>">All roles</a>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $roles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $roleKey => $roleLabel): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <a href="<?php echo e(route('signatories.index', array_filter(['search' => $search, 'role' => $roleKey]))); ?>" class="<?php echo \Illuminate\Support\Arr::toCssClasses([
                            'inline-flex w-full items-center rounded-xl border px-3 py-2 text-left text-xs font-bold transition',
                            'border-[#201A15] bg-[#201A15] text-white dark:border-white dark:bg-white dark:text-[#201A15]' => $selectedRole === $roleKey,
                            'border-[#E7E2D8] bg-white text-[#6B6258] hover:bg-[#F4F1EB] dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800' => $selectedRole !== $roleKey,
                        ]); ?>"><?php echo e($roleLabel); ?></a>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </nav>
            </aside>

            <div class="min-w-0">
                <form
                    method="GET"
                    action="<?php echo e(route('signatories.index')); ?>"
                    x-data
                    x-on:input.debounce.350ms="$el.requestSubmit()"
                    class="mb-4 flex flex-col gap-2.5 sm:flex-row"
                >
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($selectedRole !== ''): ?>
                        <input type="hidden" name="role" value="<?php echo e($selectedRole); ?>">
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <label class="relative min-w-0 flex-1" for="signatory-search">
                        <span class="sr-only">Search by name or position</span>
                        <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#8A8178]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35m2.1-5.4a7.5 7.5 0 1 1-15 0 7.5 7.5 0 0 1 15 0Z" /></svg>
                        <input id="signatory-search" name="search" type="search" value="<?php echo e($search); ?>" placeholder="Search by name or position" class="block w-full rounded-lg border-[#E7E2D8] bg-white py-2.5 pl-9 pr-3 text-sm text-[#201A15] shadow-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-700 dark:bg-slate-900 dark:text-white dark:placeholder:text-slate-500">
                    </label>

                    <button type="submit" class="sr-only">Search directory</button>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($search !== ''): ?>
                        <a href="<?php echo e(route('signatories.index')); ?>" class="inline-flex items-center justify-center rounded-lg border border-[#E7E2D8] bg-white px-3 py-2.5 text-sm font-medium text-[#6B6258] transition hover:bg-[#F4F1EB] dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800">Clear</a>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </form>

                <section class="overflow-hidden rounded-xl border border-[#E7E2D8] bg-white shadow-[0_1px_2px_rgba(32,26,21,0.04),0_8px_20px_-12px_rgba(32,26,21,0.10)] dark:border-slate-700 dark:bg-slate-900" aria-label="Signatory directory">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-[#E7E2D8] text-left dark:divide-slate-700">
                            <thead class="bg-[#F9F7F3] text-[11px] font-bold uppercase tracking-wide text-[#6B6258] dark:bg-slate-800/70 dark:text-slate-400">
                                <tr>
                                    <th scope="col" class="px-[18px] py-3">Name</th>
                                    <th scope="col" class="px-[18px] py-3">Position / designation</th>
                                    <th scope="col" class="px-[18px] py-3">Signature role</th>
                                    <th scope="col" class="px-[18px] py-3">Availability</th>
                                    <th scope="col" class="px-[18px] py-3"><span class="sr-only">Actions</span></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#E7E2D8] dark:divide-slate-700">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $signatories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $signatory): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($editingSignatoryId === $signatory->id): ?>
                                        <tr id="signatory-<?php echo e($signatory->id); ?>" class="scroll-mt-40 bg-[#FBEAEA]/50 dark:bg-red-950/20">
                                            <td colspan="5" class="px-[18px] py-4">
                                                <form action="<?php echo e(route('signatories.update', $signatory)); ?>" method="POST">
                                                <?php echo csrf_field(); ?>
                                                <?php echo method_field('PATCH'); ?>

                                                <div class="grid gap-2.5 sm:grid-cols-2 lg:grid-cols-4">
                                                    <label class="text-[11.5px] font-medium text-[#6B6258] dark:text-slate-300" for="name-<?php echo e($signatory->id); ?>">
                                                        Full name
                                                        <input id="name-<?php echo e($signatory->id); ?>" name="name" value="<?php echo e(old('name', $signatory->name)); ?>" required maxlength="120" class="mt-1 block w-full rounded-md border-[#C1272D] bg-white px-2.5 py-2 text-[13.5px] text-[#201A15] focus:border-red-700 focus:ring-red-700 dark:bg-slate-950 dark:text-white">
                                                    </label>
                                                    <label class="text-[11.5px] font-medium text-[#6B6258] dark:text-slate-300" for="position-<?php echo e($signatory->id); ?>">
                                                        Position / designation
                                                        <input id="position-<?php echo e($signatory->id); ?>" name="position" value="<?php echo e(old('position', $signatory->position)); ?>" required maxlength="120" class="mt-1 block w-full rounded-md border-[#C1272D] bg-white px-2.5 py-2 text-[13.5px] text-[#201A15] focus:border-red-700 focus:ring-red-700 dark:bg-slate-950 dark:text-white">
                                                    </label>
                                                    <label class="text-[11.5px] font-medium text-[#6B6258] dark:text-slate-300" for="role-<?php echo e($signatory->id); ?>">
                                                        Signature role
                                                        <select id="role-<?php echo e($signatory->id); ?>" name="role_key" required class="mt-1 block w-full rounded-md border-[#E7E2D8] bg-white px-2.5 py-2 text-[13px] text-[#201A15] focus:border-red-600 focus:ring-red-600 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $roles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                                                <option value="<?php echo e($key); ?>" <?php if(old('role_key', $signatory->role_key) === $key): echo 'selected'; endif; ?>><?php echo e($label); ?></option>
                                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                                        </select>
                                                    </label>
                                                    <input type="hidden" name="is_default" value="0">
                                                    <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300"><input type="checkbox" name="is_default" value="1" <?php if(old('is_default', $signatory->is_default)): echo 'checked'; endif; ?> class="rounded border-gray-300 text-red-700 focus:ring-red-600">Use as default for this role</label>
                                                    <label class="text-[11.5px] font-medium text-[#6B6258] dark:text-slate-300" for="availability-<?php echo e($signatory->id); ?>">
                                                        Availability
                                                        <select id="availability-<?php echo e($signatory->id); ?>" name="active" required class="mt-1 block w-full rounded-md border-[#E7E2D8] bg-white px-2.5 py-2 text-[13px] text-[#201A15] focus:border-red-600 focus:ring-red-600 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                                                            <option value="1" <?php if((bool) old('active', $signatory->active)): echo 'selected'; endif; ?>>Active</option>
                                                            <option value="0" <?php if(! (bool) old('active', $signatory->active)): echo 'selected'; endif; ?>>Inactive</option>
                                                        </select>
                                                    </label>
                                                </div>

                                                <div class="mt-3 flex justify-end gap-2">
                                                    <a href="<?php echo e(route('signatories.index', array_filter(['search' => $search, 'role' => $selectedRole]))); ?>#signatory-<?php echo e($signatory->id); ?>" class="rounded-md border border-[#E7E2D8] bg-white px-3 py-2 text-xs font-medium text-[#6B6258] transition hover:bg-[#F4F1EB] dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800">Cancel</a>
                                                    <button type="submit" class="rounded-md bg-[#C1272D] px-3.5 py-2 text-xs font-semibold text-white transition hover:bg-[#9C1E23] focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 dark:focus:ring-offset-slate-900">Save changes</button>
                                                </div>
                                            </form>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <tr id="signatory-<?php echo e($signatory->id); ?>" class="scroll-mt-40">
                                            <td class="whitespace-nowrap px-[18px] py-[13px] text-[14.5px] font-semibold text-[#201A15] dark:text-white"><?php echo e($signatory->name); ?> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($signatory->is_default): ?><span class="ml-2 rounded-full bg-red-50 px-2 py-1 text-xs text-red-800 dark:bg-red-950 dark:text-red-200">Default</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></td>
                                            <td class="px-[18px] py-[13px] text-[13px] text-[#6B6258] dark:text-slate-400"><?php echo e($signatory->position); ?></td>
                                            <td class="px-[18px] py-[13px] text-[13px] text-[#6B6258] dark:text-slate-400"><?php echo e($roles[$signatory->role_key]); ?></td>
                                            <td class="whitespace-nowrap px-[18px] py-[13px]"><span class="rounded-full px-3 py-1 text-[12px] font-semibold <?php echo e($signatory->active ? 'bg-[#E8F3EC] text-[#3F7D5C] dark:bg-green-950/40 dark:text-green-300' : 'bg-[#F4F1EB] text-[#6B6258] dark:bg-slate-800 dark:text-slate-400'); ?>"><?php echo e($signatory->active ? 'Active' : 'Inactive'); ?></span></td>
                                            <td class="whitespace-nowrap px-[18px] py-[13px]">
                                                <div class="flex items-center justify-end gap-1.5">
                                                    <a href="<?php echo e(route('signatories.index', array_filter(['search' => $search, 'role' => $selectedRole, 'edit' => $signatory->id]))); ?>#signatory-<?php echo e($signatory->id); ?>" title="Edit <?php echo e($signatory->name); ?>" class="inline-flex items-center gap-1.5 rounded-md px-2 py-1.5 text-xs font-medium text-[#6B6258] transition hover:bg-[#F4F1EB] hover:text-[#201A15] focus:outline-none focus:ring-2 focus:ring-red-600 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white">
                                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931ZM19.5 7.125 16.875 4.5" /></svg>
                                                        Edit
                                                    </a>
                                                    <form
                                                        method="POST"
                                                        action="<?php echo e(route('signatories.destroy', $signatory)); ?>"
                                                        data-proposal-confirm
                                                        data-confirm-title="Remove <?php echo e($signatory->name); ?>?"
                                                        data-confirm-text="This removes the name from future selections. Existing proposal signature blocks keep their saved copy."
                                                        data-confirm-button="Remove signatory"
                                                        data-confirm-icon="warning"
                                                    >
                                                        <?php echo csrf_field(); ?>
                                                        <?php echo method_field('DELETE'); ?>
                                                        <button type="submit" title="Delete <?php echo e($signatory->name); ?>" class="inline-flex items-center gap-1.5 rounded-md px-2 py-1.5 text-xs font-medium text-[#8A8178] transition hover:bg-red-50 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 dark:text-slate-500 dark:hover:bg-red-950/40 dark:hover:text-red-300">
                                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.166L18.16 19.673A2.25 2.25 0 0 1 15.916 21H8.084a2.25 2.25 0 0 1-2.245-2.327L4.772 5.79m14.456 0A48.667 48.667 0 0 0 4.772 5.79" /></svg>
                                                            Delete
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                    <tr>
                                        <td colspan="5" class="px-6 py-12 text-center">
                                            <h3 class="text-sm font-semibold text-[#201A15] dark:text-white"><?php echo e($search !== '' || $selectedRole !== '' ? 'No matching signatories' : 'No signatories yet'); ?></h3>
                                            <p class="mt-1 text-[13px] text-[#6B6258] dark:text-slate-400"><?php echo e($search !== '' || $selectedRole !== '' ? 'Try another search or clear the filters.' : 'Add the first approved name using the form.'); ?></p>
                                        </td>
                                    </tr>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
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
<?php /**PATH C:\laragon\www\athena-app\src\resources\views/research_head/signatories.blade.php ENDPATH**/ ?>