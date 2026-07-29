<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ATHENA | BatStateU Research Portal</title>

    @include('partials.theme-script')

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        html { scroll-behavior: smooth; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; }

        .fade-up {
            opacity: 0;
            transform: translateY(32px);
            transition: opacity .7s ease, transform .7s ease;
        }

        .fade-up.show {
            opacity: 1;
            transform: translateY(0);
        }

        .athena-grid {
            background-image:
                linear-gradient(to right, rgba(122, 0, 25, .03) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(122, 0, 25, .03) 1px, transparent 1px);
            background-size: 42px 42px;
        }

        .dark .athena-grid {
            background-image:
                linear-gradient(to right, rgba(255, 255, 255, .025) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(255, 255, 255, .025) 1px, transparent 1px);
        }

        .hero-noise {
            background-image: radial-gradient(rgba(255, 255, 255, .12) .7px, transparent .7px);
            background-size: 18px 18px;
        }
    </style>
</head>

<body class="bg-white text-slate-900 antialiased transition-colors duration-300 dark:bg-slate-950 dark:text-white">
    <header class="fixed inset-x-0 top-0 z-50 px-4 pt-4 sm:px-6 lg:px-8">
        <div class="mx-auto flex max-w-7xl items-center justify-between rounded-2xl border border-white/60 bg-white/90 px-4 py-3 shadow-[0_18px_50px_-24px_rgba(15,23,42,.45)] backdrop-blur-xl transition-colors dark:border-white/10 dark:bg-slate-950/85 sm:px-6">
            <a href="#home" class="group flex min-w-0 items-center gap-3">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-white p-1.5 shadow-md ring-1 ring-[#7A0019]/10 transition group-hover:scale-105 dark:bg-slate-900 dark:ring-white/10">
                    <img src="{{ asset('images/athenalogo.png') }}" alt="ATHENA Logo" class="h-full w-full rounded-xl object-cover">
                </span>
                <span class="min-w-0">
                    <span class="block text-lg font-extrabold tracking-[0.12em] text-[#7A0019] dark:text-white">ATHENA</span>
                    <span class="hidden truncate text-[11px] font-medium text-slate-500 dark:text-slate-400 sm:block">BatStateU ARASOF–Nasugbu Research Portal</span>
                </span>
            </a>

            <nav class="hidden items-center gap-1 rounded-xl bg-slate-100/80 p-1 text-sm font-semibold text-slate-600 dark:bg-white/5 dark:text-slate-300 lg:flex">
                <a href="#home" class="rounded-lg px-4 py-2 hover:bg-white hover:text-[#7A0019] hover:shadow-sm dark:hover:bg-white/10 dark:hover:text-white">Home</a>
                <a href="#about" class="rounded-lg px-4 py-2 hover:bg-white hover:text-[#7A0019] hover:shadow-sm dark:hover:bg-white/10 dark:hover:text-white">About</a>
                <a href="#users" class="rounded-lg px-4 py-2 hover:bg-white hover:text-[#7A0019] hover:shadow-sm dark:hover:bg-white/10 dark:hover:text-white">Users</a>
                <a href="#features" class="rounded-lg px-4 py-2 hover:bg-white hover:text-[#7A0019] hover:shadow-sm dark:hover:bg-white/10 dark:hover:text-white">Features</a>
            </nav>

            <div class="flex items-center gap-2">
                @if (Route::has('login'))
                    @auth
                        <a href="{{ url('/dashboard') }}" class="hidden rounded-xl bg-[#7A0019] px-5 py-2.5 text-sm font-bold text-white shadow-lg shadow-[#7A0019]/20 transition hover:-translate-y-0.5 hover:bg-[#5E0013] sm:inline-flex">Dashboard</a>
                    @else
                        <a href="{{ route('login') }}" class="hidden rounded-xl bg-[#7A0019] px-5 py-2.5 text-sm font-bold text-white shadow-lg shadow-[#7A0019]/20 transition hover:-translate-y-0.5 hover:bg-[#5E0013] sm:inline-flex">Access Portal</a>
                    @endauth
                @endif

                <button id="theme-toggle" data-theme-toggle class="flex h-11 w-11 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-700 transition hover:border-[#7A0019]/30 hover:text-[#7A0019] dark:border-white/10 dark:bg-white/5 dark:text-slate-200 dark:hover:bg-white/10" aria-label="Toggle theme">
                    <svg class="h-5 w-5 dark:hidden" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 15.75A9 9 0 118.25 2.25a7.5 7.5 0 0013.5 13.5z" />
                    </svg>
                    <svg class="hidden h-5 w-5 dark:block" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1.5m0 15V21m9-9h-1.5M4.5 12H3m15.364 6.364l-1.061-1.061M6.697 6.697L5.636 5.636m12.728 0l-1.061 1.061M6.697 17.303l-1.061 1.061M16.5 12a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0z" />
                    </svg>
                </button>
            </div>
        </div>
    </header>

    <main>
        <section id="home" class="relative isolate flex min-h-screen items-center overflow-hidden bg-cover bg-center bg-no-repeat pt-28 text-white" style="background-image:url('{{ asset('images/nasugbu.jpg') }}');">
            <div class="absolute inset-0 -z-20 bg-slate-950/55"></div>
            <div class="absolute inset-0 -z-10 bg-gradient-to-br from-[#31000A]/95 via-[#7A0019]/70 to-slate-950/80"></div>
            <div class="hero-noise absolute inset-0 -z-10 opacity-25"></div>
            <div class="absolute -left-32 top-1/3 -z-10 h-96 w-96 rounded-full bg-[#D4AF37]/15 blur-3xl"></div>
            <div class="absolute -right-28 bottom-0 -z-10 h-[30rem] w-[30rem] rounded-full bg-[#B20D30]/25 blur-3xl"></div>

            <div class="mx-auto grid w-full max-w-7xl items-center gap-14 px-6 py-20 lg:grid-cols-[1.1fr_.9fr] lg:px-8">
                <div class="text-center lg:text-left">
                    <div class="mb-6 inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-white/90 backdrop-blur-md">
                        <span class="h-2 w-2 rounded-full bg-[#D4AF37]"></span>
                        Research Management Portal
                    </div>

                    <h1 class="text-5xl font-black leading-[.95] tracking-tight sm:text-6xl lg:text-7xl">
                        ATHENA
                    </h1>

                    <p class="mt-5 max-w-3xl text-2xl font-bold leading-tight text-white sm:text-3xl">
                        Automated Research Management and Monitoring System
                    </p>

                    <p class="mx-auto mt-6 max-w-2xl text-base leading-8 text-white/75 sm:text-lg lg:mx-0">
                        A centralized digital workspace for proposal submission, project monitoring, document management, analytics, and AI-assisted research support for Batangas State University ARASOF–Nasugbu.
                    </p>

                    <div class="mt-9 flex flex-col justify-center gap-3 sm:flex-row lg:justify-start">
                        @auth
                            <a href="{{ url('/dashboard') }}" class="inline-flex items-center justify-center gap-2 rounded-2xl bg-white px-7 py-4 text-sm font-extrabold text-[#7A0019] shadow-2xl shadow-black/20 transition hover:-translate-y-1 hover:bg-[#FFF8F0]">
                                Enter Workspace
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="inline-flex items-center justify-center gap-2 rounded-2xl bg-white px-7 py-4 text-sm font-extrabold text-[#7A0019] shadow-2xl shadow-black/20 transition hover:-translate-y-1 hover:bg-[#FFF8F0]">
                                Sign In with Spartan Email
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                            </a>
                        @endauth

                        <a href="#about" class="inline-flex items-center justify-center rounded-2xl border border-white/25 bg-white/10 px-7 py-4 text-sm font-bold text-white backdrop-blur-md transition hover:-translate-y-1 hover:bg-white/20">Explore ATHENA</a>
                    </div>

                    <div class="mt-8 flex items-center justify-center gap-3 text-sm text-white/65 lg:justify-start">
                        <span class="h-px w-10 bg-[#D4AF37]"></span>
                        <span>Batangas State University ARASOF–Nasugbu</span>
                    </div>
                </div>

                <div class="relative mx-auto w-full max-w-lg">
                    <div class="absolute inset-0 rounded-[2.5rem] bg-[#D4AF37]/20 blur-3xl"></div>
                    <div class="relative overflow-hidden rounded-[2.5rem] border border-white/20 bg-white/10 p-6 shadow-2xl backdrop-blur-xl sm:p-8">
                        <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-transparent via-[#D4AF37] to-transparent"></div>
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-[0.18em] text-white/55">Welcome to</p>
                                <h2 class="mt-1 text-3xl font-black">ATHENA</h2>
                            </div>
                            <span class="flex h-16 w-16 items-center justify-center rounded-2xl bg-white p-2 shadow-xl">
                                <img src="{{ asset('images/athenalogo.png') }}" alt="ATHENA Logo" class="h-full w-full rounded-xl object-cover">
                            </span>
                        </div>

                        <div class="mt-8 space-y-4">
                            <div class="rounded-2xl border border-white/10 bg-black/10 p-4">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-white/10">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5V5.625a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3h4.5M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                                    </span>
                                    <div>
                                        <p class="font-bold">Research Management</p>
                                        <p class="text-sm text-white/60">Organized and centralized workflows</p>
                                    </div>
                                </div>
                            </div>

                            <div class="rounded-2xl border border-white/10 bg-black/10 p-4">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-white/10">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/></svg>
                                    </span>
                                    <div>
                                        <p class="font-bold">Monitoring & Analytics</p>
                                        <p class="text-sm text-white/60">Clear progress and decision support</p>
                                    </div>
                                </div>
                            </div>

                            <div class="rounded-2xl border border-white/10 bg-black/10 p-4">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-white/10">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.847-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.847a4.5 4.5 0 003.09 3.09L15.75 12l-2.847.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.456-2.456L14.25 6l1.035-.259a3.375 3.375 0 002.456-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z"/></svg>
                                    </span>
                                    <div>
                                        <p class="font-bold">AI Research Support</p>
                                        <p class="text-sm text-white/60">Smarter assistance for researchers</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="about" class="athena-grid relative overflow-hidden border-b border-slate-200 bg-white py-24 transition-colors dark:border-white/10 dark:bg-slate-950">
            <div class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
                <div class="absolute inset-0 athena-grid opacity-70"></div>
                <div class="absolute -left-32 top-0 h-[420px] w-[420px] rounded-full bg-[#7A0019]/8 blur-3xl dark:bg-[#7A0019]/15"></div>
                <div class="absolute -right-24 bottom-0 h-[380px] w-[380px] rounded-full bg-[#D4AF37]/6 blur-3xl"></div>
                <img
                    src="{{ asset('images/athenalogo-transparent.png') }}"
                    alt=""
                    class="pointer-events-none absolute left-8 top-8 w-40 opacity-[0.08] dark:opacity-[0.10] grayscale"
                >
            </div>

            <div class="relative z-10 mx-auto grid max-w-7xl items-center gap-14 px-6 lg:grid-cols-2 lg:px-8">
                <div class="fade-up">
                    <span class="inline-flex items-center gap-2 rounded-full bg-[#7A0019]/8 px-4 py-2 text-xs font-extrabold uppercase tracking-[0.18em] text-[#7A0019] dark:bg-white/5 dark:text-[#F6D8DE]">
                        <span class="h-2 w-2 rounded-full bg-[#D4AF37]"></span>
                        About ATHENA
                    </span>

                    <h2 class="mt-6 text-4xl font-extrabold leading-tight text-slate-950 dark:text-white sm:text-5xl">
                        Empowering Research Through Innovation
                    </h2>

                    <p class="mt-6 text-base leading-8 text-slate-600 dark:text-slate-300">
                        ATHENA (Automated Research Management and Monitoring System with Analytics and Research Support Tools)
                        is an intelligent platform developed for Batangas State University ARASOF–Nasugbu.
                    </p>

                    <p class="mt-4 text-base leading-8 text-slate-600 dark:text-slate-300">
                        It streamlines research proposal submission, project monitoring,
                        document management, analytics, and AI-assisted research support
                        through one centralized digital workspace.
                    </p>

                    <div class="mt-8 grid gap-3 sm:grid-cols-2">
                        @foreach ([
                            'Centralized Research Management',
                            'AI-Powered Research Support',
                            'Analytics & Progress Monitoring',
                            'Secure Role-Based Access'
                        ] as $item)

                            <div class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:shadow-lg dark:border-white/10 dark:bg-white/5">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-[#7A0019]/10 text-[#7A0019] dark:bg-white/10 dark:text-white">
                                    <svg
                                        class="h-4 w-4"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2.5"
                                        viewBox="0 0 24 24">

                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M4.5 12.75l6 6 9-13.5"/>

                                    </svg>
                                </span>
                                <span class="text-sm font-bold text-slate-700 dark:text-slate-200">
                                    {{ $item }}
                                </span>
                            </div>
                        @endforeach
                    </div>

                </div>

                <div class="fade-up relative flex items-center justify-center">
                    <div class="absolute h-80 w-80 rounded-full bg-[#7A0019]/10 blur-3xl dark:bg-[#7A0019]/20"></div>
                    <div class="absolute left-8 top-8 h-20 w-20 rounded-3xl border border-[#7A0019]/10 bg-white/60 shadow-xl backdrop-blur dark:border-white/10 dark:bg-white/5"></div>
                    <div class="absolute bottom-10 right-5 h-28 w-28 rounded-full border border-[#D4AF37]/30 bg-[#D4AF37]/10"></div>
                    <div class="relative overflow-hidden rounded-[2.5rem] border border-[#7A0019]/10 bg-white/75 p-10 shadow-[0_30px_80px_-35px_rgba(122,0,25,.55)] backdrop-blur-xl dark:border-white/10 dark:bg-[#171422]/90">
                        <div class="absolute inset-0 bg-gradient-to-br from-white/40 via-transparent to-[#7A0019]/5 dark:from-white/5 dark:to-[#7A0019]/10"></div>
                        <img
                            src="{{ asset('images/athenalogo-transparent.png') }}"
                            alt="ATHENA Logo"
                            class="relative z-10 w-72 object-contain sm:w-80"
                        >
                    </div>
                </div>
            </div>
            <img
                src="{{ asset('images/maingatebg-transparent.png') }}"
                alt=""
                class="absolute bottom-0 right-0 w-[38rem]opacity-[0.07] dark:opacity-[0.05] mix-blend-multiply dark:opacity-[0.08] dark:mix-blend-screen"
            >
        </section>
        <section id="users" class="border-b border-slate-200 bg-[#FFF9FA] py-24 transition-colors dark:border-white/10 dark:bg-slate-900">
            <div class="relative mx-auto max-w-7xl px-6 lg:px-8">
                <div
                    class="pointer-events-none absolute left-1/2 top-6 h-44 w-44 -translate-x-1/2 rounded-full bg-[#7A0019]/6 blur-[90px]">
                </div>
                 <div class="fade-up mx-auto max-w-3xl text-center">
                    <p class="text-xs font-extrabold uppercase tracking-[0.2em] text-[#7A0019] dark:text-[#F6D8DE]">System Users</p>
                    <h2 class="mt-4 text-4xl font-extrabold text-slate-950 dark:text-white sm:text-5xl">Who Uses ATHENA?</h2>
                    <p class="mt-6 leading-8 text-slate-600 dark:text-slate-300">ATHENA supports different research stakeholders by providing centralized access to research management, monitoring, analytics, and decision-support tools.</p>
                </div>

                @php
                    $users = [
                        ['Faculty', 'Faculty members who are not directly engaged in formal research activities can access research announcements and calls posted by the research office. ATHENA provides transparency into ongoing institutional research efforts, keeping faculty informed about research developments within the campus.'],
                        ['Faculty Researchers', 'Faculty Researchers can submit proposals, quarterly progress reports, and terminal reports directly through ATHENA. The system provides access to research forms, Turnitin resources, and AI-powered research assistance for recommended journals and conferences.'],
                        ['Research Coordinators', 'Research Coordinators can efficiently prepare research accomplishment reports using centralized research data and project progress information, reducing manual data gathering and documentation efforts.'],
                        ['Research Head', 'Research Heads gain real-time insights into institutional research performance through dashboards and target tracking. They can monitor faculty engagement, publication compliance, and budget utilization for informed decision-making.'],
                        ['VCRDES', 'The Office for the Vice Chancellor for Research, Development, and Extension Services gains comprehensive visibility over institutional research activities, including project status, budget allocation, publication outputs, and overall research performance.'],
                    ];
                @endphp

                <div class="mt-14 grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                    @foreach (array_slice($users, 0, 3) as $user)
                        <article class="fade-up group relative overflow-hidden rounded-3xl border border-[#7A0019]/10 bg-white p-7 shadow-md transition-all duration-300 hover:-translate-y-3 hover:scale-[1.02] hover:border-[#7A0019]/30 hover:shadow-[0_28px_70px_-30px_rgba(122,0,25,.35)] dark:border-white/10 dark:bg-white/5 dark:hover:border-[#B20D30]/40 dark:hover:shadow-[0_28px_70px_-30px_rgba(122,0,25,.45)]">
                                <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-[#7A0019] via-[#B20D30] to-[#D4AF37]"></div>

                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#7A0019]/10 text-[#7A0019] transition group-hover:bg-[#7A0019] group-hover:text-white dark:bg-white/10 dark:text-white">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.203-.574-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.941 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"/>
                                </svg>
                            </div>

                            <h3 class="mt-6 text-xl font-extrabold text-slate-900 dark:text-white">
                                {{ $user[0] }}
                            </h3>

                            <p class="mt-4 text-sm leading-7 text-slate-600 dark:text-slate-300">
                                {{ $user[1] }}
                            </p>
                        </article>
                    @endforeach
                </div>

                <div class="mx-auto mt-6 grid max-w-4xl gap-6 md:grid-cols-2">
                    @foreach (array_slice($users, 3, 2) as $user)
                        <article class="fade-up group relative overflow-hidden rounded-3xl border border-[#7A0019]/10 bg-white p-7 shadow-md transition-all duration-300 hover:-translate-y-4 hover:scale-[1.02] hover:border-[#7A0019]/30 hover:shadow-[0_28px_70px_-30px_rgba(122,0,25,.35)] dark:border-white/10 dark:bg-white/5 dark:hover:border-[#B20D30]/40 dark:hover:shadow-[0_28px_70px_-30px_rgba(122,0,25,.45)]">
                            <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-[#7A0019] via-[#B20D30] to-[#D4AF37]"></div>

                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#7A0019]/10 text-[#7A0019] transition group-hover:bg-[#7A0019] group-hover:text-white dark:bg-white/10 dark:text-white">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.203-.574-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.941 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"/>
                                </svg>
                            </div>

                            <h3 class="mt-6 text-xl font-extrabold text-slate-900 dark:text-white">
                                {{ $user[0] }}
                            </h3>

                            <p class="mt-4 text-sm leading-7 text-slate-600 dark:text-slate-300">
                                {{ $user[1] }}
                            </p>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

    <section
        id="features"
        class="athena-grid relative overflow-hidden border-b border-slate-200 bg-white py-24 transition-colors dark:border-white/10 dark:bg-slate-950">
        <div class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
            <div class="absolute inset-0 athena-grid opacity-70"></div>
            <div class="absolute -left-40 top-10 h-[480px] w-[480px] rounded-full bg-[#7A0019]/10 blur-[140px] dark:bg-[#7A0019]/18"></div>
            <div class="absolute -right-32 bottom-0 h-[420px] w-[420px] rounded-full bg-[#D4AF37]/10 blur-[120px]"></div>
            <img
                src="{{ asset('images/maingatebg-transparent.png') }}"
                alt=""
                class="absolute bottom-0 right-0 w-[35rem]opacity-[0.08] dark:opacity-[0.08] mix-blend-multiply dark:opacity-[0.08] dark:mix-blend-screen"
            >
            <div class="absolute right-0 top-0 h-60 w-96 bg-gradient-to-bl from-[#7A0019]/6 via-transparent to-transparent dark:from-[#7A0019]/12"></div>
                </div>
                <div class="relative z-10 mx-auto max-w-7xl px-6 lg:px-8">
                <div
                    class="pointer-events-none absolute left-1/2 top-6 h-44 w-44 -translate-x-1/2 rounded-full bg-[#7A0019]/6 blur-[90px]">
                </div>
        <div class="fade-up mx-auto max-w-3xl text-center">
                    <p class="text-xs font-extrabold uppercase tracking-[0.2em] text-[#7A0019] dark:text-[#F6D8DE]">System Features</p>
                    <h2 class="mt-4 text-4xl font-extrabold text-slate-950 dark:text-white sm:text-5xl">Everything You Need to Manage Research</h2>
                    <p class="mt-6 leading-8 text-slate-600 dark:text-slate-300">ATHENA provides a complete set of tools to simplify research management, improve collaboration, and support data-driven decision making across the university.</p>
                </div>

                @php
                    $features = [
                        ['Research Proposal Management', 'Submit research proposals online, track approval status, manage revisions, and monitor progress from submission to completion.', 'document'],
                        ['Analytics Dashboard', 'View real-time statistics, research productivity, completion rates, faculty performance, and institutional reports.', 'chart'],
                        ['AI Research Support', 'Utilize AI-powered tools for document classification, writing assistance, and intelligent research recommendations.', 'spark'],
                        ['Document Repository', 'Securely upload, organize, and retrieve research documents with centralized cloud storage.', 'folder'],
                        ['Collaboration Tools', 'Enable seamless communication between researchers, coordinators, reviewers, and administrators.', 'users'],
                        ['Secure Role-Based Access', 'Protect institutional research through secure authentication and role-specific permissions for every user.', 'shield'],
                    ];
                @endphp

                <div class="mt-14 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                    @foreach ($features as $feature)
                        <article class="fade-up group relative overflow-hidden rounded-3xl border border-[#7A0019]/10 bg-white p-7 shadow-md transition-all duration-300 hover:-translate-y-3 hover:scale-[1.02] hover:border-[#7A0019]/30 hover:shadow-[0_28px_70px_-30px_rgba(122,0,25,.35)] dark:border-white/10 dark:bg-white/5 dark:hover:border-[#B20D30]/40 dark:hover:shadow-[0_28px_70px_-30px_rgba(122,0,25,.45)]">                            <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-[#7A0019]/10 text-[#7A0019] transition group-hover:scale-110 group-hover:bg-[#7A0019] group-hover:text-white dark:bg-white/10 dark:text-white">
                                @if ($feature[2] === 'document')
                                    <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5V5.625a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3h4.5M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.12 five5V11.25a9 9 0 00-9-9z"/></svg>
                                @elseif ($feature[2] === 'spark')
                                    <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.847-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.847a4.5 4.5 0 003.09 3.09L15.75 12l-2.847.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.456-2.456L14.25 6l1.035-.259a3.375 3.375 0 002.456-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z"/></svg>
                                @elseif ($feature[2] === 'folder')
                                    <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V6.75A2.25 2.25 0 014.5 4.5h4.379a2.25 2.25 0 011.591.659l1.372 1.372a2.25 2.25 0 001.591.659H19.5a2.25 2.25 0 012.25 2.25v8.25a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25v-4.94z"/></svg>
                                @elseif ($feature[2] === 'users')
                                    <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.203-.574-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.941 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                @else
                                    <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75l2.25 2.25L15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.75c0 5.592 3.824 10.29 9 11.623 5.176-1.333 9-6.03 9-11.623 0-1.31-.21-2.57-.598-3.75h-.152c-3.196 0-6.1-1.25-8.25-3.286z"/></svg>
                                @endif
                            </div>
                            <h3 class="mt-6 text-xl font-extrabold text-slate-900 dark:text-white">{{ $feature[0] }}</h3>
                            <p class="mt-4 text-sm leading-7 text-slate-600 dark:text-slate-300">{{ $feature[1] }}</p>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="bg-[#7A0019] py-16 text-white">
            <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-8 px-6 text-center lg:flex-row lg:px-8 lg:text-left">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.2em] text-[#F2D98A]">Access the system</p>
                    <h2 class="mt-3 text-3xl font-extrabold sm:text-4xl">Continue your research journey with ATHENA.</h2>
                    <p class="mt-3 max-w-2xl text-white/70">Use your authorized Spartan account to access the research management workspace.</p>
                </div>
                @auth
                    <a href="{{ url('/dashboard') }}" class="inline-flex shrink-0 items-center justify-center rounded-2xl bg-white px-7 py-4 text-sm font-extrabold text-[#7A0019] shadow-xl transition hover:-translate-y-1 hover:bg-[#FFF8F0]">Open Dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="inline-flex shrink-0 items-center justify-center rounded-2xl bg-white px-7 py-4 text-sm font-extrabold text-[#7A0019] shadow-xl transition hover:-translate-y-1 hover:bg-[#FFF8F0]">Access ATHENA</a>
                @endauth
            </div>
        </section>
    </main>

    <footer class="bg-slate-950 text-slate-300">
        <div class="mx-auto grid max-w-7xl gap-10 px-6 py-12 md:grid-cols-[1.4fr_.6fr_.8fr] lg:px-8">
            <div>
                <div class="flex items-center gap-3">
                    <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white p-1.5">
                        <img src="{{ asset('images/athenalogo.png') }}" alt="ATHENA Logo" class="h-full w-full rounded-xl object-cover">
                    </span>
                    <div>
                        <p class="text-xl font-extrabold tracking-[0.12em] text-white">ATHENA</p>
                        <p class="text-xs text-slate-400">Research Management Portal</p>
                    </div>
                </div>
                <p class="mt-5 max-w-xl text-sm leading-7 text-slate-400">Automated Research Management and Monitoring System with Analytics and Research Support Tools for Batangas State University ARASOF–Nasugbu.</p>
            </div>

            <div>
                <p class="font-bold text-white">Quick Links</p>
                <div class="mt-4 space-y-3 text-sm text-slate-400">
                    <a href="#about" class="block hover:text-white">About</a>
                    <a href="#users" class="block hover:text-white">Users</a>
                    <a href="#features" class="block hover:text-white">Features</a>
                </div>
            </div>

            <div>
                <p class="font-bold text-white">Institution</p>
                <p class="mt-4 text-sm leading-7 text-slate-400">Batangas State University<br>ARASOF–Nasugbu<br>Research Office Portal</p>
            </div>
        </div>

        <div class="border-t border-white/10">
            <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-3 px-6 py-5 text-center text-xs text-slate-500 sm:flex-row sm:text-left lg:px-8">
                <span>&copy; {{ date('Y') }} Project ATHENA. Developed for Batangas State University.</span>
                <span>Version 1.0.0</span>
            </div>
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('show');
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.12 });

            document.querySelectorAll('.fade-up').forEach((element) => observer.observe(element));
        });
    </script>
</body>
</html>