<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="ATHENA brings BatStateU ARASOF–Nasugbu research work into one focused, collaborative digital workspace.">
    <title>ATHENA | BatStateU ARASOF–Nasugbu</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/css/welcome.css', 'resources/js/app.js', 'resources/js/welcome.js'])
</head>

<body class="landing-page bg-white text-slate-900 antialiased">
    <header class="fixed inset-x-0 top-0 z-50 px-4 pt-4 sm:px-6 lg:px-8">
        <div class="mx-auto flex max-w-7xl items-center justify-between rounded-2xl border border-white/70 bg-white/80 px-4 py-3 shadow-[0_12px_35px_-24px_rgba(15,23,42,.5)] backdrop-blur-md sm:px-5">
            <a href="#home" class="group flex min-w-0 items-center gap-3" aria-label="ATHENA home">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-white p-1 shadow-sm ring-1 ring-slate-200/80">
                    <img src="{{ asset('images/athenalogo.png') }}" alt="" class="h-full w-full rounded-lg object-cover">
                </span>
                <span class="min-w-0">
                    <span class="block text-base font-extrabold tracking-[0.14em] text-[#7A0019]">ATHENA</span>
                    <span class="hidden truncate text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-500 sm:block">BatStateU ARASOF–Nasugbu</span>
                </span>
            </a>

            <nav class="hidden items-center gap-1 text-sm font-semibold text-slate-600 lg:flex" aria-label="Primary navigation">
                <a href="#home" class="rounded-lg px-4 py-2 transition hover:bg-[#7A0019]/5 hover:text-[#7A0019]">Home</a>
                <a href="#about" class="rounded-lg px-4 py-2 transition hover:bg-[#7A0019]/5 hover:text-[#7A0019]">About</a>
                <a href="#people" class="rounded-lg px-4 py-2 transition hover:bg-[#7A0019]/5 hover:text-[#7A0019]">People</a>
                <a href="#features" class="rounded-lg px-4 py-2 transition hover:bg-[#7A0019]/5 hover:text-[#7A0019]">Features</a>
            </nav>

            <div class="flex items-center gap-2">
                @if (Route::has('login'))
                    @auth
                        <a href="{{ url('/dashboard') }}" class="hidden items-center justify-center rounded-xl bg-[#7A0019] px-5 py-2.5 text-sm font-bold text-white shadow-[0_10px_24px_-14px_rgba(122,0,25,.9)] transition hover:-translate-y-0.5 hover:bg-[#620014] sm:inline-flex">Dashboard</a>
                    @else
                        <a href="{{ route('login') }}" class="hidden items-center justify-center rounded-xl bg-[#7A0019] px-5 py-2.5 text-sm font-bold text-white shadow-[0_10px_24px_-14px_rgba(122,0,25,.9)] transition hover:-translate-y-0.5 hover:bg-[#620014] sm:inline-flex">Sign in</a>
                    @endauth
                @endif

                <details class="relative lg:hidden">
                    <summary class="flex h-10 w-10 cursor-pointer list-none items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-700 transition hover:border-[#7A0019]/30 hover:text-[#7A0019]" aria-label="Open navigation">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16" />
                        </svg>
                    </summary>
                    <nav class="absolute right-0 top-12 w-52 rounded-2xl border border-slate-200 bg-white p-2 text-sm font-semibold text-slate-700 shadow-xl" aria-label="Mobile navigation">
                        <a href="#home" class="block rounded-xl px-4 py-3 hover:bg-slate-50 hover:text-[#7A0019]">Home</a>
                        <a href="#about" class="block rounded-xl px-4 py-3 hover:bg-slate-50 hover:text-[#7A0019]">About</a>
                        <a href="#people" class="block rounded-xl px-4 py-3 hover:bg-slate-50 hover:text-[#7A0019]">People</a>
                        <a href="#features" class="block rounded-xl px-4 py-3 hover:bg-slate-50 hover:text-[#7A0019]">Features</a>
                        @if (Route::has('login'))
                            @auth
                                <a href="{{ url('/dashboard') }}" class="mt-1 block rounded-xl bg-[#7A0019] px-4 py-3 text-center text-white">Dashboard</a>
                            @else
                                <a href="{{ route('login') }}" class="mt-1 block rounded-xl bg-[#7A0019] px-4 py-3 text-center text-white">Sign in</a>
                            @endauth
                        @endif
                    </nav>
                </details>
            </div>
        </div>
    </header>

    <main>
        <section id="home" class="hero-campus relative isolate flex min-h-[760px] items-end overflow-hidden sm:min-h-screen">
            <div class="absolute inset-0 -z-20 bg-gradient-to-r from-slate-950/70 via-slate-950/25 to-transparent"></div>
            <div class="hero-maroon-accent absolute bottom-0 right-0 -z-10 h-[76%] w-[72%] sm:w-[62%]"></div>
            <div class="absolute inset-x-0 bottom-0 -z-10 h-32 bg-gradient-to-t from-white via-white/30 to-transparent"></div>

            <div class="mx-auto w-full max-w-7xl px-6 pb-24 pt-36 sm:pb-28 lg:px-8 lg:pb-32">
                <div class="max-w-3xl text-white">
                    <p class="flex items-center gap-3 text-xs font-bold uppercase tracking-[0.22em] text-white/80 sm:text-sm">
                        <span class="h-px w-10 bg-white/70"></span>
                        Batangas State University
                    </p>

                    <h1 class="mt-6 max-w-2xl text-5xl font-extrabold leading-[1.02] tracking-[-0.04em] drop-shadow-sm sm:text-6xl lg:text-7xl">
                        Research moves forward here.
                    </h1>

                    <p class="mt-6 max-w-2xl text-base font-medium leading-8 text-white/85 sm:text-lg">
                        ATHENA brings proposals, collaboration, progress, and research support together for the ARASOF–Nasugbu community.
                    </p>

                    <div class="mt-9 flex flex-col gap-3 sm:flex-row">
                        @auth
                            <a href="{{ url('/dashboard') }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-white px-6 py-3.5 text-sm font-extrabold text-[#7A0019] shadow-xl transition hover:-translate-y-0.5 hover:bg-slate-50">
                                Open your workspace
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-white px-6 py-3.5 text-sm font-extrabold text-[#7A0019] shadow-xl transition hover:-translate-y-0.5 hover:bg-slate-50">
                                Continue with Spartan email
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
                            </a>
                        @endauth
                    </div>

                    <div class="mt-10 flex flex-wrap gap-x-7 gap-y-3 text-sm font-semibold text-white/75" aria-label="ATHENA capabilities">
                        <span class="flex items-center gap-2"><span class="h-1.5 w-1.5 rounded-full bg-white"></span>Submit</span>
                        <span class="flex items-center gap-2"><span class="h-1.5 w-1.5 rounded-full bg-white"></span>Collaborate</span>
                        <span class="flex items-center gap-2"><span class="h-1.5 w-1.5 rounded-full bg-white"></span>Track</span>
                        <span class="flex items-center gap-2"><span class="h-1.5 w-1.5 rounded-full bg-white"></span>Discover</span>
                    </div>
                </div>
            </div>
        </section>

        <section id="about" class="landing-section landing-section--about scroll-mt-24 py-24 sm:py-28">
            <div class="landing-grid" aria-hidden="true"></div>
            <div class="relative mx-auto max-w-7xl px-6 lg:px-8">
                <div class="grid items-center gap-12 lg:grid-cols-[minmax(0,.8fr)_minmax(0,1.2fr)] lg:gap-20">
                    <div class="reveal landing-visual landing-visual--workflow" data-landing-tilt>
                        <div class="landing-orbit landing-orbit--one"></div>
                        <div class="landing-orbit landing-orbit--two"></div>
                        <div class="relative z-10 mx-auto flex max-w-sm flex-col gap-4 p-7 sm:p-9">
                            <div class="landing-mini-card landing-mini-card--top self-start">
                                <span class="landing-mini-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14.25 3.75H6.75A2.25 2.25 0 0 0 4.5 6v12a2.25 2.25 0 0 0 2.25 2.25h10.5A2.25 2.25 0 0 0 19.5 18V9l-5.25-5.25Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M14.25 3.75V9h5.25M8.25 13.5h7.5m-7.5 3h5.25"/></svg></span>
                                <span><strong>Proposal</strong><small>Prepared</small></span>
                            </div>
                            <div class="landing-connector self-center"><span></span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6"/></svg></div>
                            <div class="landing-mini-card landing-mini-card--center self-end">
                                <span class="landing-mini-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg></span>
                                <span><strong>Review</strong><small>Moving forward</small></span>
                            </div>
                            <div class="landing-status-pill self-start"><span></span>All steps connected</div>
                        </div>
                    </div>

                    <div class="reveal">
                        <p class="text-xs font-extrabold uppercase tracking-[0.22em] text-[#7A0019]">Built for better research</p>
                        <h2 class="mt-4 max-w-2xl text-4xl font-extrabold tracking-[-0.035em] text-slate-950 sm:text-5xl">Less friction. More progress.</h2>
                        <p class="mt-7 max-w-2xl text-lg leading-8 text-slate-600">
                            ATHENA gives researchers and campus leaders one clear place to move ideas from proposal to completion. Every step stays organized, visible, and easier to act on.
                        </p>

                        <div class="mt-10 grid gap-5 border-t border-[#7A0019]/10 pt-8 sm:grid-cols-3">
                            <div class="landing-stat-card">
                                <p class="text-2xl font-extrabold text-[#7A0019]">One place</p>
                                <p class="mt-2 text-sm leading-6 text-slate-500">Files, updates, and decisions stay connected.</p>
                            </div>
                            <div class="landing-stat-card">
                                <p class="text-2xl font-extrabold text-[#7A0019]">Clear status</p>
                                <p class="mt-2 text-sm leading-6 text-slate-500">Know what is moving and what needs attention.</p>
                            </div>
                            <div class="landing-stat-card">
                                <p class="text-2xl font-extrabold text-[#7A0019]">Smart support</p>
                                <p class="mt-2 text-sm leading-6 text-slate-500">Useful tools help researchers work with confidence.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="people" class="landing-section landing-section--people scroll-mt-24 py-24 sm:py-28">
            <div class="relative mx-auto max-w-7xl px-6 lg:px-8">
                <div class="grid items-center gap-12 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,.8fr)] lg:gap-20">
                    <div class="reveal">
                        <p class="text-xs font-extrabold uppercase tracking-[0.22em] text-[#7A0019]">Made for the community</p>
                        <h2 class="mt-4 max-w-2xl text-4xl font-extrabold tracking-[-0.035em] text-slate-950 sm:text-5xl">One connected research community.</h2>
                        <p class="mt-6 max-w-xl text-base leading-8 text-slate-600">From a first proposal to institution-wide insight, ATHENA gives each role a focused view of the work that matters.</p>
                    </div>

                    <div class="reveal landing-visual landing-visual--people" data-landing-tilt>
                        <div class="landing-orbit landing-orbit--one"></div>
                        <div class="relative z-10 flex h-full items-center justify-center p-7 sm:p-9">
                            <svg class="h-auto w-full max-w-[19rem] text-[#7A0019]" viewBox="0 0 320 250" fill="none" aria-hidden="true">
                                <circle cx="160" cy="125" r="104" fill="currentColor" opacity=".06"/>
                                <path d="M102 183c0-27 20-49 45-49h26c25 0 45 22 45 49v16H102v-16Z" fill="currentColor" opacity=".16"/>
                                <circle cx="160" cy="103" r="31" fill="currentColor" opacity=".22"/>
                                <path d="M54 193c0-20 15-36 34-36h15c19 0 34 16 34 36v10H54v-10Z" fill="currentColor" opacity=".1"/>
                                <circle cx="95" cy="133" r="23" fill="currentColor" opacity=".15"/>
                                <path d="M183 193c0-20 15-36 34-36h15c19 0 34 16 34 36v10h-83v-10Z" fill="currentColor" opacity=".1"/>
                                <circle cx="225" cy="133" r="23" fill="currentColor" opacity=".15"/>
                                <path d="M149 103c6 5 16 5 22 0" stroke="currentColor" stroke-width="4" stroke-linecap="round"/>
                                <path d="M84 133c4 4 11 4 15 0m118 0c4 4 11 4 15 0" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                                <rect x="195" y="36" width="66" height="31" rx="15.5" fill="white" stroke="currentColor" stroke-opacity=".18"/>
                                <circle cx="214" cy="51.5" r="4" fill="currentColor"/><circle cx="228" cy="51.5" r="4" fill="currentColor" opacity=".55"/><circle cx="242" cy="51.5" r="4" fill="currentColor" opacity=".25"/>
                            </svg>
                            <span class="landing-status-pill landing-status-pill--people"><span></span>Working together</span>
                        </div>
                    </div>
                </div>

                @php
                    $people = [
                        ['Researchers', 'Create proposals, manage requirements, and keep every milestone moving.', '01'],
                        ['Coordinators', 'Guide submissions, follow progress, and keep campus research organized.', '02'],
                        ['Research leaders', 'See performance clearly and turn timely information into action.', '03'],
                        ['University offices', 'Stay aligned through consistent records, reports, and shared visibility.', '04'],
                    ];
                @endphp

                <div class="mt-14 grid gap-px overflow-hidden rounded-3xl border border-[#7A0019]/10 bg-[#7A0019]/10 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($people as $person)
                        <article class="reveal group bg-white p-7 transition duration-300 hover:bg-[#7A0019]/[0.025] sm:p-8">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-extrabold tracking-[0.2em] text-slate-300">{{ $person[2] }}</span>
                                <span class="h-2 w-2 rounded-full bg-[#7A0019]/25 transition group-hover:bg-[#7A0019]"></span>
                            </div>
                            <h3 class="mt-10 text-xl font-extrabold text-slate-900">{{ $person[0] }}</h3>
                            <p class="mt-3 text-sm leading-7 text-slate-500">{{ $person[1] }}</p>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section id="features" class="landing-section landing-section--features scroll-mt-24 py-24 sm:py-28">
            <div class="landing-grid" aria-hidden="true"></div>
            <div class="relative mx-auto max-w-7xl px-6 lg:px-8">
                <div class="grid items-center gap-12 lg:grid-cols-[minmax(0,.8fr)_minmax(0,1.2fr)] lg:gap-20">
                    <div class="reveal landing-visual landing-visual--features" data-landing-tilt>
                        <div class="landing-orbit landing-orbit--two"></div>
                        <div class="relative z-10 grid h-full grid-cols-2 gap-3 p-7 sm:p-9">
                            <div class="landing-feature-tile landing-feature-tile--tall"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a5.25 5.25 0 1 0 0 10.5 5.25 5.25 0 0 0 0-10.5Zm0 0V3m0 18v-3.75m5.25-5.25H21M3 12h3.75m8.962-3.712 2.652-2.652M5.636 18.364l2.652-2.652m7.424 0 2.652 2.652M5.636 5.636l2.652 2.652"/></svg><span>Guided</span></div>
                            <div class="landing-feature-tile"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 18.75V9.375M9.375 18.75V5.625M15 18.75v-7.5m5.25 7.5V3.375"/></svg><span>Clear</span></div>
                            <div class="landing-feature-tile"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Zm-3-9 2 2 4-4"/></svg><span>Ready</span></div>
                            <div class="landing-feature-tile landing-feature-tile--accent"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v18m9-9H3"/></svg><span>More</span></div>
                        </div>
                    </div>

                    <div class="reveal max-w-2xl lg:justify-self-end">
                        <p class="text-xs font-extrabold uppercase tracking-[0.22em] text-[#7A0019]">Focused by design</p>
                        <h2 class="mt-4 text-4xl font-extrabold tracking-[-0.035em] text-slate-950 sm:text-5xl">Everything important, within reach.</h2>
                        <p class="mt-6 text-base leading-8 text-slate-600">Practical tools that simplify the work without getting in the way of the research.</p>
                    </div>
                </div>

                @php
                    $features = [
                        ['Proposal workflows', 'Prepare, submit, review, and revise proposals through one guided process.'],
                        ['Progress monitoring', 'Follow milestones and surface the next action before work stalls.'],
                        ['Connected documents', 'Keep research files organized, current, and easy to retrieve.'],
                        ['Useful analytics', 'Turn campus research activity into a clear view of performance.'],
                        ['Research assistance', 'Find helpful guidance and AI-supported tools when you need them.'],
                        ['Role-based access', 'Give each person the right tools and information for their work.'],
                    ];
                @endphp

                <div class="mt-14 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                    @foreach ($features as $index => $feature)
                        <article class="reveal group rounded-3xl border border-slate-200 bg-white p-7 shadow-[0_16px_45px_-36px_rgba(15,23,42,.45)] transition duration-300 hover:-translate-y-1 hover:border-[#7A0019]/25 hover:shadow-[0_22px_50px_-34px_rgba(122,0,25,.3)] sm:p-8">
                            <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-[#7A0019]/5 text-sm font-extrabold text-[#7A0019] transition group-hover:bg-[#7A0019] group-hover:text-white">
                                {{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}
                            </div>
                            <h3 class="mt-7 text-xl font-extrabold text-slate-900">{{ $feature[0] }}</h3>
                            <p class="mt-3 text-sm leading-7 text-slate-500">{{ $feature[1] }}</p>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="bg-white pb-24 pt-4 sm:pb-28">
            <div class="mx-auto max-w-7xl px-6 lg:px-8">
                <div class="reveal relative overflow-hidden rounded-[2rem] border border-[#7A0019]/10 bg-gradient-to-br from-white via-white to-[#7A0019]/5 px-6 py-14 text-center shadow-[0_28px_70px_-48px_rgba(122,0,25,.45)] sm:px-12 sm:py-16">
                    <div class="absolute -bottom-28 -right-24 h-64 w-64 rounded-full bg-[#7A0019]/10 blur-3xl" aria-hidden="true"></div>
                    <div class="relative mx-auto max-w-3xl">
                        <p class="text-xs font-extrabold uppercase tracking-[0.22em] text-[#7A0019]">Ready when you are</p>
                        <h2 class="mt-4 text-3xl font-extrabold tracking-[-0.03em] text-slate-950 sm:text-4xl">Your research workspace is one sign-in away.</h2>
                        <p class="mx-auto mt-5 max-w-2xl text-base leading-8 text-slate-600">Use your authorized Spartan account to continue your work in ATHENA.</p>
                        @auth
                            <a href="{{ url('/dashboard') }}" class="mt-8 inline-flex items-center justify-center rounded-xl bg-[#7A0019] px-6 py-3.5 text-sm font-extrabold text-white transition hover:-translate-y-0.5 hover:bg-[#620014]">Open dashboard</a>
                        @else
                            <a href="{{ route('login') }}" class="mt-8 inline-flex items-center justify-center rounded-xl bg-[#7A0019] px-6 py-3.5 text-sm font-extrabold text-white transition hover:-translate-y-0.5 hover:bg-[#620014]">Sign in to ATHENA</a>
                        @endauth
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="border-t border-slate-200 bg-white">
        <div class="mx-auto flex max-w-7xl flex-col gap-8 px-6 py-10 sm:flex-row sm:items-center sm:justify-between lg:px-8">
            <div class="flex items-center gap-3">
                <span class="flex h-11 w-11 items-center justify-center overflow-hidden rounded-xl border border-slate-200 bg-white p-1.5">
                    <img src="{{ asset('images/athenalogo.png') }}" alt="" class="h-full w-full rounded-lg object-cover">
                </span>
                <div>
                    <p class="font-extrabold tracking-[0.12em] text-[#7A0019]">ATHENA</p>
                    <p class="mt-0.5 text-xs text-slate-500">BatStateU ARASOF–Nasugbu</p>
                </div>
            </div>

            <nav class="flex flex-wrap gap-x-6 gap-y-2 text-sm font-semibold text-slate-500" aria-label="Footer navigation">
                <a href="#about" class="transition hover:text-[#7A0019]">About</a>
                <a href="#people" class="transition hover:text-[#7A0019]">People</a>
                <a href="#features" class="transition hover:text-[#7A0019]">Features</a>
            </nav>

            <p class="text-xs leading-6 text-slate-400">&copy; {{ date('Y') }} Project ATHENA</p>
        </div>
    </footer>

</body>
</html>
