<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
        <meta name="app-url" content="<?php echo e(url('/')); ?>">

        <title><?php echo e(config('app.name', 'Laravel')); ?></title>

        <?php echo $__env->make('partials.theme-script', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

        <script>
            try {
                document.documentElement.dataset.sidebarCollapsed = String(
                    window.innerWidth >= 640 && sessionStorage.getItem('athena-sidebar-open') === 'false'
                );
            } catch {
                document.documentElement.dataset.sidebarCollapsed = 'false';
            }
        </script>

        <?php if (isset($component)) { $__componentOriginal38a24e6aeb8692b58428ce9665902ac0 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal38a24e6aeb8692b58428ce9665902ac0 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.app-fonts','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('app-fonts'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal38a24e6aeb8692b58428ce9665902ac0)): ?>
<?php $attributes = $__attributesOriginal38a24e6aeb8692b58428ce9665902ac0; ?>
<?php unset($__attributesOriginal38a24e6aeb8692b58428ce9665902ac0); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal38a24e6aeb8692b58428ce9665902ac0)): ?>
<?php $component = $__componentOriginal38a24e6aeb8692b58428ce9665902ac0; ?>
<?php unset($__componentOriginal38a24e6aeb8692b58428ce9665902ac0); ?>
<?php endif; ?>

        <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>
        <?php echo \Livewire\Mechanisms\FrontendAssets\FrontendAssets::styles(); ?>

    </head>
    <?php ($isFacultyWorkspace = Auth::user()?->isUsingWorkspace(['faculty', 'faculty_researcher'])); ?>
    <body
        x-data
        @keydown.escape.window="$store.sidebar.setOpen(false)"
        @resize.window="$store.researchAssistant.syncPageScroll()"
        data-app-shell
        <?php if($isFacultyWorkspace): ?> data-faculty-shell <?php endif; ?>
        data-auth-user-id="<?php echo e(Auth::id()); ?>"
        <?php if(auth()->guard()->check()): ?>
            data-research-assistant-url="<?php echo e(route('research-support.chat')); ?>"
            data-research-assistant-history-url="<?php echo e(route('research-support.history')); ?>"
            data-research-assistant-documents-url="<?php echo e(route('research-support.documents')); ?>"
            <?php if($researchAssistantPaperContext ?? null): ?>
                data-research-assistant-paper-slug="<?php echo e($researchAssistantPaperContext['paper_slug']); ?>"
                data-research-assistant-paper-label="<?php echo e($researchAssistantPaperContext['paper_label']); ?>"
            <?php endif; ?>
            <?php if($researchAssistantProposalDraftId ?? null): ?>
                data-research-assistant-proposal-draft-id="<?php echo e($researchAssistantProposalDraftId); ?>"
            <?php endif; ?>
        <?php endif; ?>
        <?php if(Auth::user()?->isUsingWorkspace(['faculty', 'faculty_researcher'])): ?> data-literature-search-url="<?php echo e(route('research-support.literature-search')); ?>" <?php endif; ?>
        <?php if(Auth::user()?->isUsingWorkspace('faculty_researcher')): ?> data-conference-search-url="<?php echo e(route('research-support.conference-search')); ?>" <?php endif; ?>
        class="bg-[#F5F7FA] font-sans text-gray-900 antialiased transition-colors duration-300 dark:bg-slate-950 dark:text-slate-100"
    >
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->check()): ?>
            <script>
                window.athenaResearchAssistantContexts = <?php echo e(Illuminate\Support\Js::from($researchAssistantContexts ?? collect())); ?>;
                window.athenaResearchAssistantActiveContextId = <?php echo e(Illuminate\Support\Js::from($activeResearchAssistantContextId ?? null)); ?>;
                window.athenaResearchAssistantHistory = <?php echo e(Illuminate\Support\Js::from($researchAssistantHistory ?? collect())); ?>;
                window.athenaResearchAssistantPageActions = <?php echo e(Illuminate\Support\Js::from($researchAssistantPageActions ?? collect())); ?>;
            </script>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <?php ($sidebarPersistenceKey = 'app-sidebar-'.(Auth::user()?->activeWorkspace() ?? 'guest')); ?>
        <?php app("livewire")->forceAssetInjection(); ?><div x-persist="<?php echo e($sidebarPersistenceKey); ?>">
            <?php echo $__env->make('layouts.navigation', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        </div>

        <div
            x-cloak
            x-show="$store.sidebar.open"
            x-transition.opacity
            @click="$store.sidebar.setOpen(false)"
            class="fixed inset-0 z-30 bg-slate-950/45 backdrop-blur-[1px] sm:hidden"
            aria-hidden="true"
        ></div>

        <div
            :class="$store.sidebar.open ? 'sm:!pl-[280px]' : 'sm:!pl-[76px]'"
            class="flex min-h-screen flex-col bg-[#F5F7FA] pl-[76px] sm:pl-[280px] transition-colors duration-300 dark:bg-slate-950"
            data-app-content-shell
        >
            
            <nav data-app-topbar class="sticky top-0 z-30 flex h-[120px] items-end justify-between border-b border-red-200/60 bg-white px-4 pb-3 shadow-sm transition-colors duration-300 dark:border-red-950 dark:bg-slate-900 sm:px-8 relative">
                <div class="pointer-events-none absolute inset-0 bg-gradient-to-r from-red-700 via-red-700 to-red-950 md:from-transparent md:via-red-700/70 md:to-red-950" aria-hidden="true"></div>

                <div
                    class="pointer-events-none absolute inset-y-0 left-0 hidden w-[30rem] overflow-hidden md:block"
                    style="-webkit-mask-image: linear-gradient(to right, #000 0%, #000 72%, transparent 100%); mask-image: linear-gradient(to right, #000 0%, #000 72%, transparent 100%);"
                    aria-hidden="true"
                >
                    <img src="<?php echo e(asset('images/front.jpg')); ?>" alt="" class="absolute inset-0 h-full w-full object-cover object-center" />
                    <div class="absolute inset-0 bg-gradient-to-r from-red-950/65 via-red-700/25 to-red-600/60"></div>
                    <div class="absolute -bottom-14 right-20 h-32 w-32 rotate-45 rounded-3xl border border-white/20 bg-white/10"></div>
                    <div class="absolute -top-16 left-24 h-36 w-36 rounded-full border-[18px] border-white/10"></div>
                </div>

                <svg class="pointer-events-none absolute inset-y-0 right-0 h-full w-[55%] text-white/[0.11]" aria-hidden="true">
                    <defs>
                        <pattern id="athena-header-hexagons" width="52" height="45" patternUnits="userSpaceOnUse">
                            <path d="M13 1h26l12 21.5L39 44H13L1 22.5 13 1Z" fill="none" stroke="currentColor" stroke-width="1" />
                        </pattern>
                        <linearGradient id="athena-header-pattern-fade" x1="0" x2="1">
                            <stop offset="0" stop-color="white" stop-opacity="0" />
                            <stop offset="0.38" stop-color="white" stop-opacity="0.55" />
                            <stop offset="1" stop-color="white" stop-opacity="1" />
                        </linearGradient>
                        <mask id="athena-header-pattern-mask">
                            <rect width="100%" height="100%" fill="url(#athena-header-pattern-fade)" />
                        </mask>
                    </defs>
                    <rect width="100%" height="100%" fill="url(#athena-header-hexagons)" mask="url(#athena-header-pattern-mask)" />
                </svg>

                <div class="pointer-events-none absolute inset-x-0 bottom-0 z-30 h-[5px] bg-gradient-to-r from-red-700 via-red-400 to-yellow-400" aria-hidden="true"></div>

                <div class="absolute right-8 top-3 z-10 hidden min-w-0 items-center gap-3 md:flex">
                    <div class="rounded-xl border border-white/15 bg-red-950/25 px-4 py-2 text-xs font-medium text-red-100 shadow-sm backdrop-blur-sm">
                        Philippine Time:
                        <time
                            id="manila-system-time"
                            class="font-bold tabular-nums text-white"
                            data-timezone="Asia/Manila"
                            aria-live="off"
                        ><?php echo e(now()->format('M d, Y | h:i:s A')); ?></time>
                        <span class="ml-1 text-[10px] font-black uppercase tracking-wider text-red-200">PHT</span>
                    </div>
                </div>

                <div class="athena-header-actions relative z-20 ml-auto flex items-center gap-1.5 sm:gap-3 lg:gap-4">

                    <button id="app-theme-toggle" data-theme-toggle type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-xl text-gray-500 transition hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-red-500 dark:text-slate-300 dark:hover:bg-slate-800" aria-label="Toggle light and dark theme" title="Toggle theme">
                        <svg class="h-5 w-5 dark:hidden" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 15.75A9 9 0 118.25 2.25a7.5 7.5 0 0013.5 13.5z" />
                        </svg>
                        <svg class="hidden h-5 w-5 dark:block" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1.5m0 15V21m9-9h-1.5M4.5 12H3m15.364 6.364l-1.061-1.061M6.697 6.697L5.636 5.636m12.728 0l-1.061 1.061M6.697 17.303l-1.061 1.061M16.5 12a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0z" />
                        </svg>
                    </button>

                    <?php if (isset($component)) { $__componentOriginaleb679bf477ebe75e7696184b8df1a98c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginaleb679bf477ebe75e7696184b8df1a98c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.notification-menu','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('notification-menu'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginaleb679bf477ebe75e7696184b8df1a98c)): ?>
<?php $attributes = $__attributesOriginaleb679bf477ebe75e7696184b8df1a98c; ?>
<?php unset($__attributesOriginaleb679bf477ebe75e7696184b8df1a98c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginaleb679bf477ebe75e7696184b8df1a98c)): ?>
<?php $component = $__componentOriginaleb679bf477ebe75e7696184b8df1a98c; ?>
<?php unset($__componentOriginaleb679bf477ebe75e7696184b8df1a98c); ?>
<?php endif; ?>

                    <div class="hidden h-6 w-px bg-white/25 sm:block"></div>

                    <div x-data="{ open: false }" class="relative shrink-0" data-header-account-menu>
                        <button type="button" @click="open = !open" :aria-expanded="open" aria-haspopup="menu" aria-label="Open account menu" class="flex min-w-0 cursor-pointer items-center gap-2 rounded-xl p-1.5 transition duration-150 hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-white/70">
                            <?php if (isset($component)) { $__componentOriginalaa6ddd3b8ee0acee5a2d1d7ac5c7e40e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalaa6ddd3b8ee0acee5a2d1d7ac5c7e40e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.user-avatar','data' => ['user' => Auth::user(),'class' => 'h-8 w-8 rounded-full border border-gray-200 bg-red-600 text-xs shadow-sm']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('user-avatar'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['user' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(Auth::user()),'class' => 'h-8 w-8 rounded-full border border-gray-200 bg-red-600 text-xs shadow-sm']); ?>
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
                            <span class="hidden min-w-0 max-w-20 truncate text-sm font-bold text-white sm:block lg:max-w-[120px]" title="<?php echo e(Auth::user()->name); ?>"><?php echo e(Auth::user()->name); ?></span>
                            <svg class="hidden h-4 w-4 text-red-100 sm:block" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                            </svg>
                        </button>

                        <div x-cloak x-show="open" @click.away="open = false" x-transition role="menu" class="absolute right-0 z-50 mt-2 w-56 max-w-[calc(100vw-6rem)] overflow-hidden rounded-2xl border border-gray-200 bg-white py-1 shadow-xl dark:border-slate-700 dark:bg-slate-900">
                            <div class="border-b border-gray-100 px-4 py-2 dark:border-slate-800">
                                <p class="text-xs text-gray-400 font-semibold uppercase">Account Profile</p>
                                <p class="text-xs font-bold text-gray-800 truncate mt-0.5"><?php echo e(Auth::user()->email); ?></p>
                                <p class="mt-1 text-[11px] font-bold text-red-600 dark:text-red-300">Using <?php echo e(Auth::user()->activeWorkspaceLabel()); ?></p>
                            </div>
                            
                            <a wire:navigate href="<?php echo e(route('profile.edit')); ?>" class="block px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:text-slate-200 dark:hover:bg-slate-800">
                                Account Profile
                            </a>

                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(Auth::user()->hasMultipleWorkspaces() || (Auth::user()->hasRole('research_coordinator') && Auth::user()->hasAnyRole(['faculty', 'faculty_researcher']))): ?>
                                <a wire:navigate href="<?php echo e(route(Auth::user()->hasRole('research_coordinator') && Auth::user()->hasAnyRole(['faculty', 'faculty_researcher']) ? 'role-selection.show' : 'workspace.select')); ?>" class="block px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:text-slate-200 dark:hover:bg-slate-800">
                                    Switch Workspace
                                </a>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            
                            <form method="POST" action="<?php echo e(route('logout')); ?>" data-proposal-confirm data-confirm-title="Log out of ATHENA?" data-confirm-text="You will need to sign in again to continue working in ATHENA." data-confirm-button="Log out" data-cancel-button="Stay signed in" data-confirm-icon="warning">
                                <?php echo csrf_field(); ?>
                                <button type="submit" class="block w-full cursor-pointer px-4 py-2.5 text-left text-sm font-bold text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/40">
                                    Log Out
                                </button>
                            </form>
                        </div>
                    </div>

                </div>
            </nav>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($header)): ?>
                <?php if (isset($component)) { $__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.page-header','data' => ['container' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('page-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['container' => true]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(request()->routeIs('faculty.proposal-drafts.*') && ! request()->routeIs('faculty.proposal-drafts.index')): ?>
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start">
                            <div class="min-w-0 flex-1"><?php echo e($header); ?></div>
                        </div>
                    <?php else: ?>
                        <?php echo e($header); ?>

                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
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
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->check()): ?>
                <?php if (isset($component)) { $__componentOriginal1365eb9831e71146585e2bedacf1d363 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1365eb9831e71146585e2bedacf1d363 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.research-call-deadline-banner','data' => ['researchCall' => $researchCallDeadlineNotice ?? null]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('research-call-deadline-banner'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['research-call' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($researchCallDeadlineNotice ?? null)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal1365eb9831e71146585e2bedacf1d363)): ?>
<?php $attributes = $__attributesOriginal1365eb9831e71146585e2bedacf1d363; ?>
<?php unset($__attributesOriginal1365eb9831e71146585e2bedacf1d363); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal1365eb9831e71146585e2bedacf1d363)): ?>
<?php $component = $__componentOriginal1365eb9831e71146585e2bedacf1d363; ?>
<?php unset($__componentOriginal1365eb9831e71146585e2bedacf1d363); ?>
<?php endif; ?>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <main class="flex-1 py-6 px-4 sm:px-6 lg:px-8">
                <div class="athena-page-content w-full">
                    <?php echo e($slot); ?>

                </div>
            </main>

        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->check()): ?>
            <?php if (isset($component)) { $__componentOriginalefd7e0c501623c5ce638e92ba052a146 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalefd7e0c501623c5ce638e92ba052a146 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.research-assistant-drawer','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('research-assistant-drawer'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalefd7e0c501623c5ce638e92ba052a146)): ?>
<?php $attributes = $__attributesOriginalefd7e0c501623c5ce638e92ba052a146; ?>
<?php unset($__attributesOriginalefd7e0c501623c5ce638e92ba052a146); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalefd7e0c501623c5ce638e92ba052a146)): ?>
<?php $component = $__componentOriginalefd7e0c501623c5ce638e92ba052a146; ?>
<?php unset($__componentOriginalefd7e0c501623c5ce638e92ba052a146); ?>
<?php endif; ?>
            <div
                x-cloak
                x-show="$store.researchAssistant.workspaceOpen"
                x-transition.opacity
                class="fixed inset-0 z-[80] bg-white dark:bg-slate-900"
                data-assistant-full-workspace
            >
                <?php if (isset($component)) { $__componentOriginal8c6c20ad270f289b8bec96a7503e8760 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal8c6c20ad270f289b8bec96a7503e8760 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.research-assistant-workspace','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('research-assistant-workspace'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal8c6c20ad270f289b8bec96a7503e8760)): ?>
<?php $attributes = $__attributesOriginal8c6c20ad270f289b8bec96a7503e8760; ?>
<?php unset($__attributesOriginal8c6c20ad270f289b8bec96a7503e8760); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal8c6c20ad270f289b8bec96a7503e8760)): ?>
<?php $component = $__componentOriginal8c6c20ad270f289b8bec96a7503e8760; ?>
<?php unset($__componentOriginal8c6c20ad270f289b8bec96a7503e8760); ?>
<?php endif; ?>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <?php echo \Livewire\Mechanisms\FrontendAssets\FrontendAssets::scriptConfig(); ?>

    </body>
</html>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views/layouts/app.blade.php ENDPATH**/ ?>