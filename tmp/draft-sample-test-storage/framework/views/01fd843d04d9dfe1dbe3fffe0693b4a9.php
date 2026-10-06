<?php
    $isFacultyNavigation = Auth::user()->isUsingWorkspace(['faculty', 'faculty_researcher']);
    $isResearchOfficeNavigation = Auth::user()->isUsingWorkspace(\App\Models\User::WORKSPACE_RESEARCH_OFFICE);
    $usesSimpleSidebar = $isFacultyNavigation || $isResearchOfficeNavigation;
    $sidebarLinkClasses = 'relative flex w-full items-center gap-3 rounded-2xl px-4 py-3 text-[13px] font-semibold text-slate-600 transition-all duration-200 ease-out hover:translate-x-0.5 hover:bg-slate-100/90 hover:text-[#7A0019] dark:text-slate-400 dark:hover:bg-slate-900 dark:hover:text-white';
    $sidebarCurrentClasses = '!bg-white !text-[#7A0019] shadow-[0_10px_30px_rgba(15,23,42,0.08)] ring-1 ring-slate-200 before:absolute before:left-0 before:top-1/2 before:h-7 before:w-1 before:-translate-y-1/2 before:rounded-r-full before:bg-[#7A0019] dark:!bg-slate-900 dark:!text-white dark:ring-slate-800 dark:shadow-[0_12px_30px_rgba(0,0,0,0.28)]';
    if ($usesSimpleSidebar) {
        $sidebarLinkClasses = 'relative flex w-full items-center gap-3 rounded-lg px-4 py-2.5 text-[13px] font-medium text-slate-600 transition-colors hover:bg-slate-50 hover:text-[#7A0019] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#7A0019] dark:text-slate-400 dark:hover:bg-slate-900 dark:hover:text-white';
        $sidebarCurrentClasses = '!bg-red-50 !text-[#7A0019] before:absolute before:left-0 before:top-1/2 before:h-5 before:w-0.5 before:-translate-y-1/2 before:rounded-full before:bg-[#7A0019] dark:!bg-red-950/30 dark:!text-red-200';
    }
    if ($isResearchOfficeNavigation) {
        $sidebarLinkClasses = 'relative flex min-h-[44px] w-full items-center gap-3 rounded-xl px-4 py-2.5 text-[13px] font-medium text-slate-600 transition-colors hover:bg-slate-50 hover:text-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white';
        $sidebarCurrentClasses = '!bg-brand-wash !text-brand !font-semibold before:absolute before:left-0 before:top-1/2 before:h-5 before:w-0.5 before:-translate-y-1/2 before:rounded-full before:bg-brand dark:!bg-red-950/30 dark:!text-red-200';
    }
?>

<aside
    x-data
    id="app-sidebar"
    :class="$store.sidebar.open ? '!w-[280px] <?php echo e($usesSimpleSidebar ? 'shadow-xl sm:shadow-none' : 'shadow-2xl sm:shadow-xl'); ?>' : '!w-[76px] <?php echo e($usesSimpleSidebar ? '' : 'shadow-lg'); ?>'"
    class="fixed inset-y-0 left-0 z-40 flex w-[76px] flex-col overflow-hidden <?php echo e($usesSimpleSidebar ? 'bg-white dark:bg-slate-900' : 'rounded-r-[28px] bg-white/95 shadow-slate-900/10 backdrop-blur-xl dark:bg-slate-950/95'); ?> border-r border-slate-200/80 transition-colors duration-300 dark:border-slate-800/80 sm:w-[280px]"
>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $usesSimpleSidebar): ?>
    <svg
        class="pointer-events-none absolute inset-0 z-0 h-full w-full text-[#7A0019]/[0.035] dark:text-white/[0.025]"
        aria-hidden="true"
    >
        <defs>
            <pattern
                id="athena-sidebar-hexagons"
                width="52"
                height="45"
                patternUnits="userSpaceOnUse"
            >
                <path
                    d="M13 1h26l12 21.5L39 44H13L1 22.5 13 1Z"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.15"
                />
            </pattern>

            <linearGradient
                id="athena-sidebar-pattern-fade"
                x1="0"
                y1="0"
                x2="1"
                y2="0"
            >
                <stop offset="0" stop-color="white" stop-opacity="0.18" />
                <stop offset="0.38" stop-color="white" stop-opacity="0.72" />
                <stop offset="1" stop-color="white" stop-opacity="1" />
            </linearGradient>

            <mask id="athena-sidebar-pattern-mask">
                <rect
                    width="100%"
                    height="100%"
                    fill="url(#athena-sidebar-pattern-fade)"
                />
            </mask>
        </defs>

        <rect
            width="100%"
            height="100%"
            fill="url(#athena-sidebar-hexagons)"
            mask="url(#athena-sidebar-pattern-mask)"
        />
    </svg>

    <div
        class="pointer-events-none absolute inset-y-0 right-0 z-[1] w-px bg-gradient-to-b
               from-transparent via-[#7A0019]/15 to-transparent dark:via-white/10"
    ></div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <div
        :class="$store.sidebar.open ? 'px-5 pb-5 pt-5' : 'px-3 pb-4 pt-4'"
        class="relative z-10 flex shrink-0 flex-col border-b border-slate-200/70 dark:border-slate-800/80"
    >
        <a
            wire:navigate
            x-show="$store.sidebar.open"
            @click="if (window.innerWidth < 640) $store.sidebar.setOpen(false)"
            href="<?php echo e(route('dashboard')); ?>"
            class="flex min-w-0 items-center gap-3 rounded-2xl px-1"
            title="ATHENA dashboard"
            aria-label="ATHENA dashboard"
        >
            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-[#7A0019]/8 ring-1 ring-[#7A0019]/10 dark:bg-white/5 dark:ring-white/10">
                <img
                    src="<?php echo e(asset('images/athenalogo-transparent.png')); ?>"
                    alt="ATHENA logo"
                    class="h-10 w-10 object-contain"
                />
            </span>

            <span class="min-w-0">
                <span class="block truncate text-lg font-extrabold tracking-[0.12em] text-[#7A0019] dark:text-white">
                    ATHENA
                </span>
                <span class="block truncate text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-400">
                    Research Management
                </span>
            </span>
        </a>

        <button
            x-show="$store.sidebar.open"
            type="button"
            @click="$store.sidebar.setOpen(false)"
            :aria-expanded="$store.sidebar.open"
            aria-controls="app-sidebar-navigation"
            class="absolute right-3 top-6 inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl
                   text-slate-400 transition hover:bg-slate-100 hover:text-[#7A0019]
                   focus:outline-none focus:ring-2 focus:ring-[#7A0019]/20
                   dark:text-slate-500 dark:hover:bg-slate-900 dark:hover:text-white"
            aria-label="Collapse navigation menu"
            title="Collapse menu"
        >
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <rect width="18" height="18" x="3" y="3" rx="2" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 3v18m7-6-3-3 3-3" />
            </svg>
        </button>

        <button
            x-show="!$store.sidebar.open"
            type="button"
            @click="$store.sidebar.setOpen(true)"
            :aria-expanded="$store.sidebar.open"
            aria-controls="app-sidebar-navigation"
            class="group inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl
                   bg-[#7A0019]/8 ring-1 ring-[#7A0019]/10 transition
                   hover:bg-[#7A0019]/12 focus:outline-none focus:ring-2 focus:ring-[#7A0019]/20
                   dark:bg-white/5 dark:ring-white/10 dark:hover:bg-white/10"
            aria-label="Expand navigation menu"
            title="Expand menu"
        >
            <img
                src="<?php echo e(asset('images/athenalogo-transparent.png')); ?>"
                alt=""
                class="h-11 w-11 object-contain group-hover:hidden"
            />

            <svg
                class="hidden h-5 w-5 text-[#7A0019] group-hover:block dark:text-[#E7A5B2]"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                viewBox="0 0 24 24"
                aria-hidden="true"
            >
                <rect width="18" height="18" x="3" y="3" rx="2" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 3v18m5-6 3-3-3-3" />
            </svg>
        </button>
    </div>

    <div
        id="app-sidebar-navigation"
        :class="$store.sidebar.open
            ? 'px-4'
            : 'px-3 [&>a]:justify-center [&>a]:gap-0 [&>a]:px-0 [&>div>button]:justify-center [&>div>button]:gap-0 [&>div>button]:px-0'"
        wire:navigate:scroll
        class="relative z-10 grow space-y-1 overflow-x-hidden overflow-y-auto py-4
               scrollbar-thin scrollbar-track-transparent scrollbar-thumb-slate-300
               dark:scrollbar-thumb-slate-700"
    >
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(Auth::user()->isUsingWorkspace('research_head')): ?>
            <?php if (isset($component)) { $__componentOriginalc6def3d3e83b9c8f1477036b61732aa7 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc6def3d3e83b9c8f1477036b61732aa7 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.research-head-navigation','data' => ['attentionCounts' => $sidebarAttentionCounts ?? [],'reportReviewCount' => $reportReviewCount ?? 0]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('research-head-navigation'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['attention-counts' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($sidebarAttentionCounts ?? []),'report-review-count' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($reportReviewCount ?? 0)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalc6def3d3e83b9c8f1477036b61732aa7)): ?>
<?php $attributes = $__attributesOriginalc6def3d3e83b9c8f1477036b61732aa7; ?>
<?php unset($__attributesOriginalc6def3d3e83b9c8f1477036b61732aa7); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc6def3d3e83b9c8f1477036b61732aa7)): ?>
<?php $component = $__componentOriginalc6def3d3e83b9c8f1477036b61732aa7; ?>
<?php unset($__componentOriginalc6def3d3e83b9c8f1477036b61732aa7); ?>
<?php endif; ?>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>


        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(Auth::user()->isUsingWorkspace(\App\Models\User::WORKSPACE_RESEARCH_SECRETARY)): ?>
            <a
                wire:navigate
                wire:current="<?php echo e($sidebarCurrentClasses); ?>"
                @click="if (window.innerWidth < 640) $store.sidebar.setOpen(false)"
                href="<?php echo e(route('research_secretary.dashboard')); ?>"
                aria-label="Research Secretary Dashboard"
                title="Research Secretary Dashboard"
                class="<?php echo e($sidebarLinkClasses); ?>"
            >
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5h4.5v6.75h-4.5V13.5Zm6-4.5h4.5v11.25h-4.5V9Zm6-5.25h4.5v16.5h-4.5V3.75Z" /></svg>
                <span x-show="$store.sidebar.open" class="whitespace-nowrap">Signing &amp; Budgets</span>
            </a>
            <a wire:navigate wire:current="<?php echo e($sidebarCurrentClasses); ?>" href="<?php echo e(route('signatories.index')); ?>" aria-label="Signatory defaults" title="Signatory defaults" class="<?php echo e($sidebarLinkClasses); ?>">
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h8M8 11h5M5 3h14v18H5V3m5 14h5" /></svg>
                <span x-show="$store.sidebar.open" class="whitespace-nowrap">Signatories</span>
            </a>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (\Illuminate\Support\Facades\Blade::check('role', 'research_coordinator')): ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(Auth::user()->isUsingWorkspace(\App\Models\User::WORKSPACE_RESEARCH_OFFICE)): ?>
                <nav aria-label="Research Office navigation" class="space-y-4" data-research-office-navigation>
                    <section aria-label="Overview">
                        <h2 x-show="$store.sidebar.open" class="mb-2 flex items-center gap-3 px-4 text-xs font-semibold text-slate-500 dark:text-slate-400">
                            Overview<span class="h-px flex-1 bg-slate-200 dark:bg-slate-800" aria-hidden="true"></span>
                        </h2>
                        <a
                            wire:navigate
                            wire:current.exact="<?php echo e($sidebarCurrentClasses); ?>"
                            @click="if (window.innerWidth < 640) $store.sidebar.setOpen(false)"
                            href="<?php echo e(route('research_coordinator.dashboard')); ?>"
                            aria-label="Dashboard"
                            title="Dashboard"
                            class="<?php echo e($sidebarLinkClasses); ?>"
                            :class="$store.sidebar.open ? '' : '!justify-center !gap-0 !px-0'"
                        >
                            <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2v-4z" />
                            </svg>
                            <span x-show="$store.sidebar.open" class="whitespace-nowrap">Dashboard</span>
                        </a>
                    </section>

                    <section aria-label="Research">
                        <h2 x-show="$store.sidebar.open" class="mb-2 flex items-center gap-3 px-4 text-xs font-semibold text-slate-500 dark:text-slate-400">
                            Research<span class="h-px flex-1 bg-slate-200 dark:bg-slate-800" aria-hidden="true"></span>
                        </h2>
                        <a
                            wire:navigate
                            wire:current="<?php echo e($sidebarCurrentClasses); ?>"
                            @click="if (window.innerWidth < 640) $store.sidebar.setOpen(false)"
                            href="<?php echo e(route('research_coordinator.members.index')); ?>"
                            aria-label="Faculty Members"
                            title="Faculty Members"
                            class="<?php echo e($sidebarLinkClasses); ?>"
                            :class="$store.sidebar.open ? '' : '!justify-center !gap-0 !px-0'"
                        >
                            <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.1 9.1 0 0 0 3.74-.48 3 3 0 0 0-4.68-2.72m.94 3.2v-.01c0-1.2-.34-2.32-.94-3.19m.94 3.2v.13A11.9 11.9 0 0 1 12 20.4c-2.17 0-4.2-.58-5.94-1.6v-.12a6 6 0 0 1 11-3.17M15 7.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                            </svg>
                            <span x-show="$store.sidebar.open" class="whitespace-nowrap">Faculty Members</span>
                        </a>
                    </section>
                </nav>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isFacultyNavigation): ?>
            <?php if (isset($component)) { $__componentOriginalf23352e33eed9f4aded66d89e971cb00 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf23352e33eed9f4aded66d89e971cb00 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.faculty-navigation','data' => ['attentionCounts' => $sidebarAttentionCounts ?? []]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('faculty-navigation'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['attention-counts' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($sidebarAttentionCounts ?? [])]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalf23352e33eed9f4aded66d89e971cb00)): ?>
<?php $attributes = $__attributesOriginalf23352e33eed9f4aded66d89e971cb00; ?>
<?php unset($__attributesOriginalf23352e33eed9f4aded66d89e971cb00); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalf23352e33eed9f4aded66d89e971cb00)): ?>
<?php $component = $__componentOriginalf23352e33eed9f4aded66d89e971cb00; ?>
<?php unset($__componentOriginalf23352e33eed9f4aded66d89e971cb00); ?>
<?php endif; ?>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    </div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($usesSimpleSidebar || Auth::user()->isUsingWorkspace('research_head')): ?>
        <div data-sidebar-account class="relative z-10 shrink-0 border-t border-slate-100 p-3 dark:border-slate-800">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isFacultyNavigation || Auth::user()->isUsingWorkspace('research_head')): ?>
                <h2 x-show="$store.sidebar.open" class="mb-2 px-4 text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Account</h2>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(Auth::user()->hasMultipleWorkspaces()): ?>
                <a wire:navigate href="<?php echo e(route(Auth::user()->hasRole('research_coordinator') ? 'role-selection.show' : 'workspace.select')); ?>" aria-label="Switch Workspace" title="Switch Workspace" class="<?php echo e($sidebarLinkClasses); ?>" :class="!$store.sidebar.open && 'justify-center !px-0'">
                    <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7h14m0 0-4-4m4 4-4 4M17 17H3m0 0 4 4m-4-4 4-4" /></svg>
                    <span x-show="$store.sidebar.open">Switch Workspace</span>
                </a>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <a wire:navigate href="<?php echo e(route('profile.edit')); ?>" aria-label="Account Profile" title="Account Profile" class="flex items-center gap-3 rounded-lg p-2 transition hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#7A0019] dark:hover:bg-slate-800" :class="!$store.sidebar.open && 'justify-center'">
                <?php if (isset($component)) { $__componentOriginalaa6ddd3b8ee0acee5a2d1d7ac5c7e40e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalaa6ddd3b8ee0acee5a2d1d7ac5c7e40e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.user-avatar','data' => ['user' => Auth::user(),'class' => 'h-8 w-8 shrink-0 rounded-full bg-[#7A0019] text-xs text-white']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('user-avatar'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['user' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(Auth::user()),'class' => 'h-8 w-8 shrink-0 rounded-full bg-[#7A0019] text-xs text-white']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalaa6ddd3b8ee0acee5a2d1d7ac5c7e40e)): ?>
<?php $attributes = $__attributesOriginalaa6ddd3b8ee0acee5a2d1d7ac5c7e40e; ?>
<?php unset($__attributesOriginalaa6ddd3b8ee0acee5a2d1d7ac5c7e40e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalaa6ddd3b8ee0acee5a2d1d7ac5c7e40e)): ?>
<?php $component = $__componentOriginalaa6ddd3b8ee0acee5a2d1d7ac5c7e40e; ?>
<?php unset($__componentOriginalaa6ddd3b8ee0acee5a2d1d7ac5c7e40e); ?>
<?php endif; ?>
                <span x-show="$store.sidebar.open" class="min-w-0">
                    <span class="block truncate text-xs font-semibold text-slate-800 dark:text-slate-100"><?php echo e(Auth::user()->name); ?></span>
                    <span class="block truncate text-[11px] text-slate-500">Account Profile</span>
                </span>
            </a>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</aside>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views/layouts/navigation.blade.php ENDPATH**/ ?>