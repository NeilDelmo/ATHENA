<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>ATHENA | BatStateU Research Portal</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        
        <style>
            body { font-family: 'Plus Jakarta Sans', sans-serif; }
        </style>
    </head>
    <body class="bg-white antialiased">
        <section
            id="home"
            class="relative h-[90vh] bg-cover bg-center bg-no-repeat text-white"
            style="background-image:url('{{ asset('images/nasugbu.jpg') }}');">
            <div class="absolute inset-0 bg-black/65"></div>
            <div class="absolute inset-0 bg-gradient-to-b from-black/30 via-black/50 to-black/80"></div>
                

            <header class="fixed top-0 left-0 w-full z-50 backdrop-blur-lg bg-black/30 border-b border-white/10">
                <div class="max-w-7xl mx-auto px-8 py-3 flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 rounded-full bg-white p-1 shadow-lg border border-white">
                            <img
                                src="{{ asset('images/athenalogo.png') }}"
                                alt="ATHENA Logo"
                                class="w-full h-full rounded-full object-cover">
                        </div>
                        <div>
                            <h1 class="text-2xl font-black tracking-wide">
                                ATHENA
                            </h1>
                        </div>
                    </div>
                    <nav class="hidden lg:flex gap-8 text-sm font-medium">
                        <a href="#home" class="hover:text-red-400 transition">
                            Home
                        </a>
                        <a href="#about" class="hover:text-red-400 transition">
                            About
                        </a>
                        <a href="#features" class="hover:text-red-400 transition">
                            Features
                        </a>
                    </nav>
                    @if (Route::has('login'))
                        @auth
                            <a href="{{ url('/dashboard') }}"
                            class="px-6 py-3 rounded-xl bg-red-600 hover:bg-red-700 font-semibold transition">
                                Dashboard
                            </a>
                        @else
                            <a href="{{ route('login') }}"
                            class="px-5 py-2.5 rounded-xl bg-red-600 hover:bg-red-700 font-semibold transition">
                                Access Portal
                            </a>
                        @endauth
                    @endif
                </div>
            </header>

            <main
            class="relative z-20 flex flex-col justify-center items-center text-center h-[90vh] pt-24 px-6">           
                <h1 class="text-3xl sm:text-6xl font-extrabold tracking-tight max-w-3xl leading-none mb-6">
                    Automated Research Management 
                    <span class="bg-gradient-to-r from-red-600 to-amber-500 bg-clip-text text-transparent dark:from-red-500 dark:to-orange-400"> and Monitoring System</span>
                </h1>

                <p class="text-lg sm:text-xl text-gray-200 max-w-3xl leading-relaxed mb-10">
                    The official research management platform of
                    <strong>Batangas State University ARASOF–Nasugbu.</strong>
                </p>

                <div class="flex flex-col sm:flex-row items-center gap-4 w-full justify-center">
                    @auth
                        <a href="{{ url('/dashboard') }}" class="w-full sm:w-auto inline-flex items-center justify-center px-8 py-4 text-base font-bold text-white bg-red-600 hover:bg-red-700 rounded-xl shadow-md transition-all duration-150 cursor-pointer">
                            Enter Workspace
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="w-full sm:w-auto inline-flex items-center justify-center px-8 py-4 text-base font-bold text-white bg-red-600 hover:bg-red-700 rounded-xl shadow-md transition-all duration-150 cursor-pointer">
                            Sign In with Spartan Email
                        </a>
                    @endauth
                    <a href="#features" class="w-full sm:w-auto inline-flex items-center justify-center px-8 py-4 text-base font-semibold bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-800 hover:border-gray-300 dark:hover:border-gray-700 rounded-xl shadow-xs transition-all duration-150">
                        Learn More
                    </a>
                </div>
            
                </div>
            </main>
        </section>

            <section id="about" class="bg-white py-24">
                <div class="max-w-7xl mx-auto px-6">
                    <div class="grid lg:grid-cols-2 gap-16 items-center">
                        <div>
                            <p class="text-red-600 font-semibold uppercase tracking-[0.2em]">
                                ABOUT ATHENA
                            </p>

                            <h2 class="text-4xl font-bold text-gray-900 mt-4 leading-tight">
                                Empowering Research Through Innovation
                            </h2>

                            <p class="text-gray-600 mt-6 leading-8">
                                ATHENA (Automated Research Management and Monitoring System
                                with Analytics and Research Support Tools) is an intelligent
                                platform developed for Batangas State University
                                ARASOF–Nasugbu.

                                It streamlines research proposal submission,
                                project monitoring, document management,
                                analytics, and AI-assisted research support
                                through one centralized digital workspace.
                            </p>

                            <div class="mt-10 space-y-4">

                                <div class="flex items-center gap-3">
                                    <span class="text-red-600 text-xl">✔</span>
                                    <span class="text-gray-700">
                                        Centralized Research Management
                                    </span>
                                </div>

                                <div class="flex items-center gap-3">
                                    <span class="text-red-600 text-xl">✔</span>
                                    <span class="text-gray-700">
                                        AI-Powered Research Support
                                    </span>
                                </div>

                                <div class="flex items-center gap-3">
                                    <span class="text-red-600 text-xl">✔</span>
                                    <span class="text-gray-700">
                                        Analytics & Progress Monitoring
                                    </span>
                                </div>

                                <div class="flex items-center gap-3">
                                    <span class="text-red-600 text-xl">✔</span>
                                    <span class="text-gray-700">
                                        Secure Role-Based Access
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-center items-center">
                            <img
                                src="{{ asset('images/athenalogo.png') }}"
                                alt="ATHENA Logo"
                                class="w-72 sm:w-80 md:w-96 lg:w-[340px] xl:w-[400px] h-auto object-contain drop-shadow-2xl">
                        </div>
                    </div>
            </section>
           
            <section id="features" class="bg-gray-50 py-24">

                <div class="max-w-7xl mx-auto px-6">
                    <div class="text-center">
                        <p class="text-red-600 font-semibold uppercase tracking-widest">
                            SYSTEM FEATURES
                        </p>

                        <h2 class="text-4xl font-bold text-gray-900 mt-4">
                            Everything You Need to Manage Research
                        </h2>

                        <p class="text-gray-600 mt-6 max-w-3xl mx-auto">
                            ATHENA provides a complete set of tools to simplify research
                            management, improve collaboration, and support data-driven
                            decision making across the university.
                        </p>
                    </div>

                    <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8 mt-20">
                        <div class="bg-white rounded-2xl p-8 shadow-lg hover:shadow-xl transition duration-300">
                            <div class="text-5xl mb-6">📄</div>

                            <h3 class="text-2xl font-bold text-gray-900">
                                Research Proposal Management
                            </h3>

                            <p class="text-gray-600 mt-4 leading-7">
                                Submit research proposals online, track approval status,
                                manage revisions, and monitor progress from submission to completion.
                            </p>
                        </div>

                        <div class="bg-white rounded-2xl p-8 shadow-lg hover:shadow-xl transition duration-300">
                            <div class="text-5xl mb-6">📊</div>

                            <h3 class="text-2xl font-bold text-gray-900">
                                Analytics Dashboard
                            </h3>

                            <p class="text-gray-600 mt-4 leading-7">
                                View real-time statistics, research productivity,
                                completion rates, faculty performance, and institutional reports.
                            </p>
                        </div>

                        <div class="bg-white rounded-2xl p-8 shadow-lg hover:shadow-xl transition duration-300">
                            <div class="text-5xl mb-6">🤖</div>

                            <h3 class="text-2xl font-bold text-gray-900">
                                AI Research Support
                            </h3>

                            <p class="text-gray-600 mt-4 leading-7">
                                Utilize AI-powered tools for document classification,
                                writing assistance, and intelligent research recommendations.
                            </p>
                        </div>

                        <div class="bg-white rounded-2xl p-8 shadow-lg hover:shadow-xl transition duration-300">
                            <div class="text-5xl mb-6">📁</div>

                            <h3 class="text-2xl font-bold text-gray-900">
                                Document Repository
                            </h3>

                            <p class="text-gray-600 mt-4 leading-7">
                                Securely upload, organize, and retrieve research
                                documents with centralized cloud storage.
                            </p>
                        </div>

                        <div class="bg-white rounded-2xl p-8 shadow-lg hover:shadow-xl transition duration-300">
                            <div class="text-5xl mb-6">👥</div>

                            <h3 class="text-2xl font-bold text-gray-900">
                                Collaboration Tools
                            </h3>

                            <p class="text-gray-600 mt-4 leading-7">
                                Enable seamless communication between researchers,
                                coordinators, reviewers, and administrators.
                            </p>
                        </div>

                        <div class="bg-white rounded-2xl p-8 shadow-lg hover:shadow-xl transition duration-300">
                            <div class="text-5xl mb-6">🔒</div>

                            <h3 class="text-2xl font-bold text-gray-900">
                                Secure Role-Based Access
                            </h3>

                            <p class="text-gray-600 mt-4 leading-7">
                                Protect institutional research through secure authentication
                                and role-specific permissions for every user.
                            </p>
                        </div>
                    </div>
                </div>
            </section>

             <footer class="relative z-10 w-full max-w-7xl mx-auto px-6 py-8 border-t border-gray-200/60 dark:border-gray-900 text-center sm:text-left flex flex-col sm:flex-row justify-between items-center gap-4 text-xs text-gray-500"> 
                <div>
                    &copy; {{ date('Y') }} Project ATHENA. Developed for Batangas State University.
                </div>
                <div class="flex items-center gap-6 font-medium text-gray-600 dark:text-gray-400">
                    <span>Research Office Portal</span>
                    <span>v1.0.0</span>
                </div>
            </footer>
        </section>

    </body>
</html>