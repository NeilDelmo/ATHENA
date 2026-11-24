<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">
<head>
    <?php if (isset($component)) { $__componentOriginal82e3f864bb766fbb95cb0a10b750823c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal82e3f864bb766fbb95cb0a10b750823c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.favicon','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('favicon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal82e3f864bb766fbb95cb0a10b750823c)): ?>
<?php $attributes = $__attributesOriginal82e3f864bb766fbb95cb0a10b750823c; ?>
<?php unset($__attributesOriginal82e3f864bb766fbb95cb0a10b750823c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal82e3f864bb766fbb95cb0a10b750823c)): ?>
<?php $component = $__componentOriginal82e3f864bb766fbb95cb0a10b750823c; ?>
<?php unset($__componentOriginal82e3f864bb766fbb95cb0a10b750823c); ?>
<?php endif; ?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="ATHENA is the research project management system for authorized BatStateU ARASOF–Nasugbu users.">
    <title>ATHENA | BatStateU ARASOF–Nasugbu</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/css/welcome.css', 'resources/js/welcome.js']); ?>
</head>

<body class="landing-page bg-white text-slate-900 antialiased">
    <header class="fixed inset-x-0 top-0 z-50 px-4 pt-4 sm:px-6 lg:px-8">
        <div class="mx-auto flex max-w-7xl items-center justify-between rounded-2xl border border-white/70 bg-white/80 px-4 py-3 shadow-[0_12px_35px_-24px_rgba(15,23,42,.5)] backdrop-blur-md sm:px-5">
            <a href="#home" class="group flex min-w-0 items-center gap-3" aria-label="ATHENA home">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-white p-1 shadow-sm ring-1 ring-slate-200/80">
                    <img src="<?php echo e(asset('images/athenalogo.png')); ?>" alt="" class="h-full w-full rounded-lg object-cover">
                </span>
                <span class="min-w-0">
                    <span class="block text-base font-extrabold tracking-[0.14em] text-[#7A0019]">ATHENA</span>
                    <span class="hidden truncate text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-500 sm:block">BatStateU ARASOF–Nasugbu</span>
                </span>
            </a>

            <nav class="hidden items-center gap-1 text-sm font-semibold text-slate-600 lg:flex" aria-label="Primary navigation">
                <a href="#home" class="rounded-lg px-4 py-2 transition hover:bg-[#7A0019]/5 hover:text-[#7A0019]">Home</a>
                <a href="#about" class="rounded-lg px-4 py-2 transition hover:bg-[#7A0019]/5 hover:text-[#7A0019]">Overview</a>
                <a href="#people" class="rounded-lg px-4 py-2 transition hover:bg-[#7A0019]/5 hover:text-[#7A0019]">Roles</a>
                <a href="#features" class="rounded-lg px-4 py-2 transition hover:bg-[#7A0019]/5 hover:text-[#7A0019]">Functions</a>
            </nav>

            <div class="flex items-center gap-2">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(Route::has('login')): ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->check()): ?>
                        <a href="<?php echo e(url('/dashboard')); ?>" class="hidden items-center justify-center rounded-xl bg-[#7A0019] px-5 py-2.5 text-sm font-bold text-white shadow-[0_10px_24px_-14px_rgba(122,0,25,.9)] transition hover:-translate-y-0.5 hover:bg-[#620014] sm:inline-flex">Dashboard</a>
                    <?php else: ?>
                        <a href="<?php echo e(route('login')); ?>" class="hidden items-center justify-center rounded-xl bg-[#7A0019] px-5 py-2.5 text-sm font-bold text-white shadow-[0_10px_24px_-14px_rgba(122,0,25,.9)] transition hover:-translate-y-0.5 hover:bg-[#620014] sm:inline-flex">Sign in</a>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <details class="relative lg:hidden">
                    <summary class="flex h-10 w-10 cursor-pointer list-none items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-700 transition hover:border-[#7A0019]/30 hover:text-[#7A0019]" aria-label="Open navigation">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16" />
                        </svg>
                    </summary>
                    <nav class="absolute right-0 top-12 w-52 rounded-2xl border border-slate-200 bg-white p-2 text-sm font-semibold text-slate-700 shadow-xl" aria-label="Mobile navigation">
                        <a href="#home" class="block rounded-xl px-4 py-3 hover:bg-slate-50 hover:text-[#7A0019]">Home</a>
                        <a href="#about" class="block rounded-xl px-4 py-3 hover:bg-slate-50 hover:text-[#7A0019]">Overview</a>
                        <a href="#people" class="block rounded-xl px-4 py-3 hover:bg-slate-50 hover:text-[#7A0019]">Roles</a>
                        <a href="#features" class="block rounded-xl px-4 py-3 hover:bg-slate-50 hover:text-[#7A0019]">Functions</a>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(Route::has('login')): ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->check()): ?>
                                <a href="<?php echo e(url('/dashboard')); ?>" class="mt-1 block rounded-xl bg-[#7A0019] px-4 py-3 text-center text-white">Dashboard</a>
                            <?php else: ?>
                                <a href="<?php echo e(route('login')); ?>" class="mt-1 block rounded-xl bg-[#7A0019] px-4 py-3 text-center text-white">Sign in</a>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </nav>
                </details>
            </div>
        </div>
    </header>

    <main>
        <section id="home" class="hero-campus relative isolate flex min-h-[760px] items-end overflow-hidden sm:min-h-screen">
            <img src="<?php echo e(asset('images/bsu_front.png')); ?>" alt="Batangas State University - TNEU ARASOF–Nasugbu campus" class="absolute inset-0 -z-30 h-full w-full object-cover object-center" fetchpriority="high">
            <div class="absolute inset-0 -z-20 bg-gradient-to-r from-slate-950/70 via-slate-950/25 to-transparent"></div>
            <div class="hero-maroon-accent absolute bottom-0 right-0 -z-10 h-[76%] w-[72%] sm:w-[62%]"></div>
            <div class="absolute inset-x-0 bottom-0 -z-10 h-32 bg-gradient-to-t from-white via-white/30 to-transparent"></div>

            <div class="mx-auto w-full max-w-7xl px-6 pb-24 pt-36 sm:pb-28 lg:px-8 lg:pb-32">
                <div class="max-w-3xl text-white">
                    <p class="flex items-center gap-3 text-xs font-bold uppercase tracking-[0.22em] text-white/80 sm:text-sm">
                        <span class="h-px w-10 bg-white/70"></span>
                        Batangas State University - TNEU
                    </p>

                    <h1 class="mt-6 max-w-2xl text-5xl font-extrabold leading-[1.02] tracking-[-0.04em] drop-shadow-sm sm:text-6xl lg:text-7xl">
                        Research Project Management
                    </h1>

                    <p class="mt-6 max-w-2xl text-base font-medium leading-8 text-white/85 sm:text-lg">
                        ATHENA manages research proposals, project documents, progress reports, and institutional review at BatStateU ARASOF–Nasugbu.
                    </p>

                    <div class="mt-9 flex flex-col gap-3 sm:flex-row">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->check()): ?>
                            <a href="<?php echo e(url('/dashboard')); ?>" class="inline-flex items-center justify-center gap-2 rounded-xl bg-white px-6 py-3.5 text-sm font-extrabold text-[#7A0019] shadow-xl transition hover:-translate-y-0.5 hover:bg-slate-50">
                                Open your workspace
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
                            </a>
                        <?php else: ?>
                            <a href="<?php echo e(route('login')); ?>" class="inline-flex items-center justify-center gap-2 rounded-xl bg-white px-6 py-3.5 text-sm font-extrabold text-[#7A0019] shadow-xl transition hover:-translate-y-0.5 hover:bg-slate-50">
                                Sign in with Spartan email
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
                            </a>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>

                </div>
            </div>
        </section>

        <section id="about" class="landing-section landing-section--about scroll-mt-24 py-24 sm:py-28">
            <div class="relative mx-auto max-w-7xl px-6 lg:px-8">
                <div>
                    <div>
                        <p class="text-xs font-extrabold uppercase tracking-[0.22em] text-[#7A0019]">System overview</p>
                        <h2 class="mt-4 max-w-2xl text-4xl font-extrabold tracking-[-0.035em] text-slate-950 sm:text-5xl">Proposal review and project monitoring.</h2>
                        <p class="mt-7 max-w-2xl text-lg leading-8 text-slate-600">
                            Authorized researchers and research offices use ATHENA to submit proposals, record reviews, maintain project documents, and prepare required reports.
                        </p>

                        <div class="mt-10 grid gap-5 border-t border-[#7A0019]/10 pt-8 sm:grid-cols-3">
                            <div class="landing-stat-card">
                                <p class="text-2xl font-extrabold text-[#7A0019]">Project records</p>
                                <p class="mt-2 text-sm leading-6 text-slate-500">Proposal versions, documents, and review decisions.</p>
                            </div>
                            <div class="landing-stat-card">
                                <p class="text-2xl font-extrabold text-[#7A0019]">Review status</p>
                                <p class="mt-2 text-sm leading-6 text-slate-500">Submission stages, required corrections, and pending actions.</p>
                            </div>
                            <div class="landing-stat-card">
                                <p class="text-2xl font-extrabold text-[#7A0019]">Research support</p>
                                <p class="mt-2 text-sm leading-6 text-slate-500">Research manual guidance and document assistance.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="people" class="landing-section landing-section--people scroll-mt-24 py-24 sm:py-28">
            <div class="relative mx-auto max-w-7xl px-6 lg:px-8">
                <div>
                    <div>
                        <p class="text-xs font-extrabold uppercase tracking-[0.22em] text-[#7A0019]">Institutional research management</p>
                        <h2 class="mt-4 max-w-2xl text-4xl font-extrabold tracking-[-0.035em] text-slate-950 sm:text-5xl">Research roles and responsibilities.</h2>
                        <p class="mt-6 max-w-xl text-base leading-8 text-slate-600">Access proposal reviews, project records, monitoring tools, and reports according to your assigned institutional role.</p>
                    </div>

                </div>

                <?php
                    $people = [
                        ['VCRDES', 'See campus research performance and guide institutional research priorities.'],
                        ['Research Head', 'Review proposals, guide research teams, and monitor project progress.'],
                        ['Research Office', 'Coordinate submissions, maintain research records, and support reporting.'],
                        ['Faculty Researcher', 'Prepare proposals, manage research documents, and report project milestones.'],
                        ['Faculty', 'View research calls, project invitations, and institutional research guidance.'],
                    ];
                ?>

                <div class="mt-14 grid gap-px overflow-hidden rounded-3xl border border-[#7A0019]/10 bg-[#7A0019]/10 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $people; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $person): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <article class="group bg-white p-7 transition duration-300 hover:bg-[#7A0019]/[0.025] sm:p-8">
                            <span class="block h-2 w-2 rounded-full bg-[#7A0019]/25 transition group-hover:bg-[#7A0019]" aria-hidden="true"></span>
                            <h3 class="mt-6 text-xl font-extrabold text-slate-900"><?php echo e($person[0]); ?></h3>
                            <p class="mt-3 text-sm leading-7 text-slate-500"><?php echo e($person[1]); ?></p>
                        </article>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </div>
            </div>
        </section>

        <section id="features" class="landing-section landing-section--features scroll-mt-24 py-24 sm:py-28">
            <div class="relative mx-auto max-w-7xl px-6 lg:px-8">
                <div>
                    <div class="max-w-2xl">
                        <p class="text-xs font-extrabold uppercase tracking-[0.22em] text-[#7A0019]">System functions</p>
                        <h2 class="mt-4 text-4xl font-extrabold tracking-[-0.035em] text-slate-950 sm:text-5xl">Research management functions.</h2>
                        <p class="mt-6 text-base leading-8 text-slate-600">Available functions depend on the institutional role assigned to your account.</p>
                    </div>
                </div>

                <?php
                    $features = [
                        ['Proposal workflows', 'Prepare, submit, review, and revise institutional research proposals.'],
                        ['Progress monitoring', 'Record project milestones, accomplishments, and required progress reports.'],
                        ['Project documents', 'Maintain proposal versions, supporting files, and submitted reports.'],
                        ['Research analytics', 'Review campus research activity, annual targets, and project status.'],
                        ['Research assistance', 'Consult the research manual and use document and literature assistance.'],
                        ['Role-based access', 'Access records and actions according to assigned institutional responsibilities.'],
                    ];
                ?>

                <div class="mt-14 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $features; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $feature): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <article class="rounded-2xl border border-slate-200 bg-white p-7 sm:p-8">
                            <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-[#7A0019]/5 text-sm font-extrabold text-[#7A0019]">
                                <?php echo e(str_pad($index + 1, 2, '0', STR_PAD_LEFT)); ?>

                            </div>
                            <h3 class="mt-7 text-xl font-extrabold text-slate-900"><?php echo e($feature[0]); ?></h3>
                            <p class="mt-3 text-sm leading-7 text-slate-500"><?php echo e($feature[1]); ?></p>
                        </article>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </div>
            </div>
        </section>

        <section class="bg-white pb-24 pt-4 sm:pb-28">
            <div class="mx-auto max-w-7xl px-6 lg:px-8">
                <div class="rounded-2xl border border-[#7A0019]/10 px-6 py-14 text-center sm:px-12 sm:py-16">
                    <div class="relative mx-auto max-w-3xl">
                        <p class="text-xs font-extrabold uppercase tracking-[0.22em] text-[#7A0019]">Authorized access</p>
                        <h2 class="mt-4 text-3xl font-extrabold tracking-[-0.03em] text-slate-950 sm:text-4xl">Sign in with your institutional account.</h2>
                        <p class="mx-auto mt-5 max-w-2xl text-base leading-8 text-slate-600">Access is limited to authorized BatStateU ARASOF–Nasugbu users. Your assigned role determines the records and actions available.</p>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->check()): ?>
                            <a href="<?php echo e(url('/dashboard')); ?>" class="mt-8 inline-flex items-center justify-center rounded-xl bg-[#7A0019] px-6 py-3.5 text-sm font-extrabold text-white transition hover:-translate-y-0.5 hover:bg-[#620014]">Open dashboard</a>
                        <?php else: ?>
                            <a href="<?php echo e(route('login')); ?>" class="mt-8 inline-flex items-center justify-center rounded-xl bg-[#7A0019] px-6 py-3.5 text-sm font-extrabold text-white transition hover:-translate-y-0.5 hover:bg-[#620014]">Sign in to ATHENA</a>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="border-t border-slate-200 bg-white">
        <div class="mx-auto flex max-w-7xl flex-col gap-8 px-6 py-10 sm:flex-row sm:items-center sm:justify-between lg:px-8">
            <div class="flex items-center gap-3">
                <span class="flex h-11 w-11 items-center justify-center overflow-hidden rounded-xl border border-slate-200 bg-white p-1.5">
                    <img src="<?php echo e(asset('images/athenalogo.png')); ?>" alt="" class="h-full w-full rounded-lg object-cover">
                </span>
                <div>
                    <p class="font-extrabold tracking-[0.12em] text-[#7A0019]">ATHENA</p>
                    <p class="mt-0.5 text-xs text-slate-500">BatStateU ARASOF–Nasugbu</p>
                </div>
            </div>

            <nav class="flex flex-wrap gap-x-6 gap-y-2 text-sm font-semibold text-slate-500" aria-label="Footer navigation">
                <a href="#about" class="transition hover:text-[#7A0019]">Overview</a>
                <a href="#people" class="transition hover:text-[#7A0019]">Roles</a>
                <a href="#features" class="transition hover:text-[#7A0019]">Functions</a>
            </nav>

            <p class="text-xs leading-6 text-slate-400">&copy; <?php echo e(date('Y')); ?> Project ATHENA</p>
        </div>
    </footer>

</body>
</html>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\welcome.blade.php ENDPATH**/ ?>