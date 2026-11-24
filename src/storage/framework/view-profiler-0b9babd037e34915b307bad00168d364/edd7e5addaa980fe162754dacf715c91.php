<?php
    $callTabs = [
        'active' => ['label' => 'Active', 'heading' => 'Active calls', 'calls' => $activeCalls],
        'upcoming' => ['label' => 'Upcoming', 'heading' => 'Upcoming calls', 'calls' => $upcomingCalls],
        'previous' => ['label' => 'Previous', 'heading' => 'Previous calls', 'calls' => $previousCalls],
    ];
    $allCalls = $activeCalls->concat($upcomingCalls)->concat($previousCalls)->values();
    $submittedForm = old('research_call_form');
    $initialPanel = $errors->any()
        ? ($submittedForm && $submittedForm !== 'create' ? 'edit-'.$submittedForm : 'create')
        : null;
    $initialTab = 'active';

    if ($submittedForm && $submittedForm !== 'create') {
        $submittedCall = $allCalls->firstWhere('id', (int) $submittedForm);
        $initialTab = match ($submittedCall?->lifecycleStatus()) {
            'open' => 'active',
            'draft', 'scheduled' => 'upcoming',
            default => 'previous',
        };
    }
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.page-header','data' => ['title' => 'Research Calls','subtitle' => 'Manage calls, submission windows, funding limits, and published schedules.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('page-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Research Calls','subtitle' => 'Manage calls, submission windows, funding limits, and published schedules.']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

             <?php $__env->slot('actions', null, []); ?> 
                <button type="button" x-on:click="window.dispatchEvent(new CustomEvent('research-call-create'))" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-red-600 px-5 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 focus:ring-offset-white dark:focus:ring-offset-slate-950">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M12 5v14M5 12h14" /></svg>
                    Create new call
                </button>
                <a href="<?php echo e(route('announcement-images.index')); ?>" class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-black text-slate-700 shadow-sm transition hover:border-red-200 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-red-900 dark:hover:text-red-300 dark:focus:ring-offset-slate-950">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10.34 15.84 9.1 20.2a1.5 1.5 0 0 1-2.86-.9l1.13-3.75M5.5 14.5h-1A2.5 2.5 0 0 1 2 12v-1a2.5 2.5 0 0 1 2.5-2.5h1l9-4v14l-9-4Zm9-6a3 3 0 0 1 0 6" /></svg>
                    Announcement studio
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

    <div
        x-data="{ activeTab: '<?php echo e($initialTab); ?>', panel: <?php echo \Illuminate\Support\Js::from($initialPanel)->toHtml() ?>, search: '' }"
        x-on:keydown.escape.window="panel = null"
        x-on:research-call-create.window="panel = 'create'"
        class="mx-auto max-w-[1600px] space-y-5"
        data-research-call-palette="red-black-white"
    >
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('success')): ?>
            <div class="flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800 dark:border-emerald-900/70 dark:bg-emerald-950/30 dark:text-emerald-200" role="status">
                <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 4.5 4.5 10.5-10.5" /></svg>
                <span><?php echo e(session('success')); ?></span>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <?php if (isset($component)) { $__componentOriginal629ab6ff68dfa7c57a7ddb83c9ae9302 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal629ab6ff68dfa7c57a7ddb83c9ae9302 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.kpi-strip','data' => ['items' => [
            ['label' => 'Open now', 'value' => $activeCalls->count(), 'icon' => 'megaphone', 'hint' => 'Currently accepting proposals'],
            ['label' => 'Upcoming', 'value' => $upcomingCalls->count(), 'icon' => 'calendar', 'hint' => 'Draft or scheduled calls'],
            ['label' => 'Previous', 'value' => $previousCalls->count(), 'icon' => 'archive', 'hint' => 'Closed and ended calls'],
            ['label' => 'Budget ceiling', 'value' => 'PHP '.number_format($institutionalBudgetCeiling, 2), 'icon' => 'wallet', 'hint' => 'Fixed per proposal'],
        ]]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('kpi-strip'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['items' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute([
            ['label' => 'Open now', 'value' => $activeCalls->count(), 'icon' => 'megaphone', 'hint' => 'Currently accepting proposals'],
            ['label' => 'Upcoming', 'value' => $upcomingCalls->count(), 'icon' => 'calendar', 'hint' => 'Draft or scheduled calls'],
            ['label' => 'Previous', 'value' => $previousCalls->count(), 'icon' => 'archive', 'hint' => 'Closed and ended calls'],
            ['label' => 'Budget ceiling', 'value' => 'PHP '.number_format($institutionalBudgetCeiling, 2), 'icon' => 'wallet', 'hint' => 'Fixed per proposal'],
        ])]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal629ab6ff68dfa7c57a7ddb83c9ae9302)): ?>
<?php $attributes = $__attributesOriginal629ab6ff68dfa7c57a7ddb83c9ae9302; ?>
<?php unset($__attributesOriginal629ab6ff68dfa7c57a7ddb83c9ae9302); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal629ab6ff68dfa7c57a7ddb83c9ae9302)): ?>
<?php $component = $__componentOriginal629ab6ff68dfa7c57a7ddb83c9ae9302; ?>
<?php unset($__componentOriginal629ab6ff68dfa7c57a7ddb83c9ae9302); ?>
<?php endif; ?>

        <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900" aria-label="Research call records">
            <div class="flex flex-col gap-4 border-b border-slate-200 px-4 py-4 dark:border-slate-800 sm:px-6 lg:flex-row lg:items-center lg:justify-between">
                <div class="inline-flex min-w-0 flex-1 gap-1 overflow-x-auto rounded-xl border border-slate-200 bg-slate-100 p-1 shadow-sm dark:border-slate-700 dark:bg-slate-950" role="tablist" aria-label="Research call status">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $callTabs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tabKey => $tab): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <button
                            type="button"
                            role="tab"
                            x-on:click="activeTab = '<?php echo e($tabKey); ?>'"
                            x-bind:aria-selected="activeTab === '<?php echo e($tabKey); ?>'"
                            x-bind:class="activeTab === '<?php echo e($tabKey); ?>' ? 'bg-gray-900 text-white shadow-sm dark:bg-white dark:text-gray-950' : 'text-slate-500 hover:bg-white/60 hover:text-slate-800 dark:text-slate-400 dark:hover:bg-slate-900 dark:hover:text-slate-200'"
                            class="border border-slate-400 dark:border-slate-500 inline-flex shrink-0 items-center gap-2 rounded-lg px-4 py-2.5 text-xs font-black transition focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-900 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-100 dark:focus-visible:ring-white dark:focus-visible:ring-offset-slate-950"
                        >
                            <?php echo e($tab['label']); ?>

                            <span x-bind:class="activeTab === '<?php echo e($tabKey); ?>' ? 'bg-white/15 text-white dark:bg-gray-900/10 dark:text-gray-950' : 'bg-slate-200/80 text-slate-600 dark:bg-slate-800 dark:text-slate-400'" class="rounded-full px-2 py-0.5 text-[10px]"><?php echo e($tab['calls']->count()); ?></span>
                        </button>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </div>

                <label class="relative block w-full lg:max-w-xs">
                    <span class="sr-only">Search research calls</span>
                    <svg class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7" /><path stroke-linecap="round" d="m20 20-3.5-3.5" /></svg>
                    <input x-model.debounce.200ms="search" type="search" placeholder="Search calls or academic year" class="block w-full rounded-xl border-slate-300 bg-slate-50 py-2.5 pl-10 pr-3 text-sm font-semibold text-slate-900 placeholder:text-slate-400 focus:border-red-600 focus:ring-red-600 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                </label>
            </div>

            <div class="hidden grid-cols-[minmax(0,2.1fr)_minmax(15rem,1.1fr)_minmax(11rem,.8fr)_8rem_minmax(13rem,.9fr)] gap-5 border-b border-slate-200 bg-slate-50/80 px-6 py-3 text-[10px] font-black uppercase tracking-[0.14em] text-slate-400 dark:border-slate-800 dark:bg-slate-950/45 lg:grid">
                <span>Research call</span><span>Submission schedule</span><span>Allocation</span><span>Status</span><span class="text-right">Actions</span>
            </div>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $callTabs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tabKey => $tab): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <section x-show="activeTab === '<?php echo e($tabKey); ?>'" x-cloak role="tabpanel" aria-label="<?php echo e($tab['heading']); ?>">
                    <div class="divide-y divide-slate-100 dark:divide-slate-800">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $tab['calls']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $call): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <?php
                                $lifecycleStatus = $call->lifecycleStatus();
                                [$statusLabel, $statusClass, $statusDot] = match ($lifecycleStatus) {
                                    'open' => ['Open', 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-950/35 dark:text-emerald-300 dark:ring-emerald-900', 'bg-emerald-500'],
                                    'scheduled' => ['Scheduled', 'bg-blue-50 text-blue-700 ring-blue-200 dark:bg-blue-950/35 dark:text-blue-300 dark:ring-blue-900', 'bg-blue-500'],
                                    'draft' => ['Draft', 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-950/35 dark:text-amber-300 dark:ring-amber-900', 'bg-amber-500'],
                                    'closed' => ['Closed', 'bg-slate-100 text-slate-600 ring-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700', 'bg-slate-400'],
                                    default => ['Ended', 'bg-slate-100 text-slate-600 ring-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700', 'bg-slate-400'],
                                };
                                $canReopen = $call->status === 'closed' && $call->closes_at->isFuture();
                                $nextStatus = $call->status === 'open' ? 'closed' : 'open';
                                $actionLabel = match (true) {
                                    $call->status === 'draft' => 'Publish schedule',
                                    $call->status === 'open' && $lifecycleStatus !== 'ended' => 'Close early',
                                    $canReopen => 'Reopen call',
                                    default => null,
                                };
                            ?>
                            <article
                                x-show="!search || <?php echo \Illuminate\Support\Js::from(Str::lower($call->title.' '.$call->academic_year.' '.($call->term ?? '')))->toHtml() ?>.includes(search.toLowerCase())"
                                class="grid gap-5 px-4 py-5 transition hover:bg-slate-50/70 dark:hover:bg-slate-950/25 sm:px-6 lg:grid-cols-[minmax(0,2.1fr)_minmax(15rem,1.1fr)_minmax(11rem,.8fr)_8rem_minmax(13rem,.9fr)] lg:items-center"
                            >
                                <div class="flex min-w-0 items-start gap-4">
                                    <div class="flex h-16 w-12 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-slate-200 bg-slate-100 text-slate-400 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($call->reference_image_path): ?>
                                            <img src="<?php echo e(route('research-calls.reference-image', $call)); ?>" alt="Poster for <?php echo e($call->title); ?>" class="h-full w-full object-contain">
                                        <?php else: ?>
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3.75h7.5L18 7.5v12.75H6.75V3.75Z" /><path stroke-linecap="round" d="M9.5 12h5M9.5 15h5" /></svg>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </div>
                                    <div class="min-w-0">
                                        <h4 class="truncate text-sm font-black text-slate-950 dark:text-white"><?php echo e($call->title); ?></h4>
                                        <p class="mt-1 text-xs font-bold text-slate-500 dark:text-slate-400"><?php echo e($call->academic_year); ?><?php echo e($call->term ? ' · '.$call->term : ''); ?></p>
                                        <p class="mt-2 line-clamp-2 text-xs leading-5 text-slate-500 dark:text-slate-400"><?php echo e($call->description ?: 'No additional guidelines.'); ?></p>
                                    </div>
                                </div>

                                <dl class="grid grid-cols-2 gap-3 text-xs lg:grid-cols-1 lg:gap-2">
                                    <div><dt class="font-bold text-slate-400">Opens</dt><dd class="mt-0.5 font-semibold text-slate-700 dark:text-slate-200"><?php echo e($call->opens_at->format('M d, Y · h:i A')); ?></dd></div>
                                    <div><dt class="font-bold text-slate-400">Closes</dt><dd class="mt-0.5 font-semibold text-slate-700 dark:text-slate-200"><?php echo e($call->closes_at->format('M d, Y · h:i A')); ?></dd></div>
                                </dl>

                                <dl class="grid grid-cols-2 gap-3 text-xs lg:grid-cols-1 lg:gap-2">
                                    <div><dt class="font-bold text-slate-400">Maximum budget</dt><dd class="mt-0.5 font-black text-slate-800 dark:text-slate-100">PHP <?php echo e(number_format($call->budgetCeiling(), 2)); ?></dd></div>
                                    <div><dt class="font-bold text-slate-400">Submissions</dt><dd class="mt-0.5 font-semibold text-slate-700 dark:text-slate-200"><?php echo e($call->topics_count); ?> <?php echo e(Str::plural('proposal', $call->topics_count)); ?></dd></div>
                                </dl>

                                <div>
                                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-wide ring-1 ring-inset <?php echo e($statusClass); ?>"><span class="h-1.5 w-1.5 rounded-full <?php echo e($statusDot); ?>"></span><?php echo e($statusLabel); ?></span>
                                </div>

                                <div class="flex flex-wrap items-center gap-2 lg:justify-end">
                                    <button type="button" x-on:click="panel = 'edit-<?php echo e($call->id); ?>'" class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-black text-slate-700 transition hover:border-red-200 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-red-900 dark:hover:text-red-300">Edit research call</button>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($actionLabel): ?>
                                        <form method="POST" action="<?php echo e(route('research-calls.update-status', $call)); ?>" <?php if($call->status === 'open'): ?> onsubmit="return confirm('Close this research call before its scheduled end date? It will no longer appear as an open research call, but faculty can still submit independent proposals.')" <?php endif; ?>>
                                            <?php echo csrf_field(); ?>
                                            <?php echo method_field('PATCH'); ?>
                                            <input type="hidden" name="status" value="<?php echo e($nextStatus); ?>">
                                            <button class="inline-flex items-center justify-center rounded-lg px-3 py-2 text-xs font-black transition <?php echo e($call->status === 'open' ? 'bg-red-600 text-white hover:bg-red-700' : 'bg-[#7A0019] text-white hover:bg-red-800 dark:bg-red-600 dark:hover:bg-red-700'); ?>"><?php echo e($actionLabel); ?></button>
                                        </form>
                                    <?php elseif($lifecycleStatus === 'ended'): ?>
                                        <span class="text-right text-[10px] font-semibold leading-4 text-slate-400">The submission period ended automatically.</span>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </div>
                            </article>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            <div class="px-6 py-14 text-center">
                                <span class="mx-auto flex h-11 w-11 items-center justify-center rounded-2xl bg-slate-100 text-slate-400 dark:bg-slate-800"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3.75h7.5L18 7.5v12.75H6.75V3.75Z" /><path stroke-linecap="round" d="M9.5 12h5M9.5 15h5" /></svg></span>
                                <p class="mt-3 text-sm font-black text-slate-700 dark:text-slate-200">No <?php echo e(strtolower($tab['heading'])); ?> yet</p>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Calls in this lifecycle stage will appear here.</p>
                            </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </section>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </section>

        <div x-show="panel !== null" x-cloak class="pointer-events-none fixed inset-0 z-[80]" role="presentation">
            <button type="button" x-on:click="panel = null" data-research-call-editor-backdrop class="pointer-events-auto absolute inset-0 bg-slate-950/60 backdrop-blur-sm xl:hidden" aria-label="Close research call editor"></button>
            <section
                x-show="panel !== null"
                x-transition:enter="transition ease-out duration-200 motion-reduce:transition-none"
                x-transition:enter-start="translate-y-4 scale-95 opacity-0"
                x-transition:enter-end="translate-y-0 scale-100 opacity-100"
                x-transition:leave="transition ease-in duration-150 motion-reduce:transition-none"
                x-transition:leave-start="translate-y-0 scale-100 opacity-100"
                x-transition:leave-end="translate-y-4 scale-95 opacity-0"
                data-research-call-editor-panel
                class="pointer-events-auto absolute inset-x-2 bottom-2 top-2 flex origin-bottom-right flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl dark:border-slate-700 dark:bg-slate-900 sm:inset-x-auto sm:bottom-4 sm:right-4 sm:top-4 sm:w-[min(64rem,calc(100vw-2rem))]"
                role="dialog"
                x-bind:aria-modal="window.matchMedia('(max-width: 1279px)').matches ? 'true' : null"
                aria-labelledby="research-call-editor-title"
            >
                <header class="flex shrink-0 items-center justify-between gap-4 border-b border-slate-200 bg-white px-5 py-4 dark:border-slate-800 dark:bg-slate-900 sm:px-6">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-red-700 dark:text-red-300">Research call editor</p>
                        <h3 id="research-call-editor-title" class="mt-1 text-lg font-black text-slate-950 dark:text-white" x-text="panel === 'create' ? 'Create a new research call' : 'Edit research call'"></h3>
                    </div>
                    <button type="button" x-on:click="panel = null" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-600 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white" aria-label="Close editor">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18" /></svg>
                    </button>
                </header>
                <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain">
                    <div x-show="panel === 'create'">
                        <?php echo $__env->make('research_calls.partials.form', ['researchCall' => null], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                    </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $allCalls; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $call): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <div x-show="panel === 'edit-<?php echo e($call->id); ?>'">
                            <?php echo $__env->make('research_calls.partials.form', ['researchCall' => $call], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                        </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </div>
            </section>
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
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\research_calls\index.blade.php ENDPATH**/ ?>