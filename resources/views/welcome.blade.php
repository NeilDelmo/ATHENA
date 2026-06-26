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
    <body class="antialiased bg-gray-50 text-gray-900 dark:bg-gray-950 dark:text-gray-100 min-h-screen flex flex-col justify-between selection:bg-red-600 selection:text-white">

        <div class="absolute top-0 left-1/2 -translate-x-1/2 w-full max-w-7xl h-[500px] pointer-events-none overflow-hidden opacity-30 dark:opacity-20 z-0">
            <div class="absolute top-[-20%] left-[-10%] w-[600px] h-[600px] bg-red-600 rounded-full blur-[120px]"></div>
            <div class="absolute top-[-10%] right-[-10%] w-[500px] h-[500px] bg-amber-500 rounded-full blur-[100px]"></div>
        </div>

        <header class="relative z-10 w-full max-w-7xl mx-auto px-6 py-6 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <span class="text-2xl font-black tracking-widest text-red-600 dark:text-red-500">ATHENA</span>
                <span class="text-xs px-2 py-0.5 bg-red-500/10 dark:bg-red-500/20 text-red-700 dark:text-red-300 rounded-full border border-red-500/20 font-semibold uppercase tracking-wider">Portal</span>
            </div>

            <div>
                @if (Route::has('login'))
                    <div class="flex items-center gap-4">
                        @auth
                            <a href="{{ url('/dashboard') }}" class="inline-flex items-center justify-center px-5 py-2.5 text-sm font-semibold text-white bg-red-600 hover:bg-red-700 rounded-xl shadow-sm transition-all duration-150 cursor-pointer">
                                Go to Dashboard
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="inline-flex items-center justify-center px-5 py-2.5 text-sm font-semibold text-white bg-red-600 hover:bg-red-700 rounded-xl shadow-sm transition-all duration-150 cursor-pointer">
                                Access Portal
                            </a>
                        @endauth
                    </div>
                @endif
            </div>
        </header>

        <main class="relative z-10 my-auto w-full max-w-5xl mx-auto px-6 py-12 text-center flex flex-col items-center">
            
            <div class="inline-flex items-center gap-2 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 px-3 py-1.5 rounded-full shadow-xs mb-8">
                <span class="flex h-2 w-2 relative">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2 w-2 bg-red-500"></span>
                </span>
                <span class="text-xs font-medium text-gray-600 dark:text-gray-400">Batangas State University Lifecycle Engine</span>
            </div>

            <h1 class="text-4xl sm:text-6xl font-extrabold tracking-tight max-w-3xl leading-none mb-6">
                Academic Tracking & Higher Education <span class="bg-gradient-to-r from-red-600 to-amber-500 bg-clip-text text-transparent dark:from-red-500 dark:to-orange-400">Network Asset</span>
            </h1>

            <p class="text-base sm:text-xl text-gray-600 dark:text-gray-400 max-w-2xl leading-relaxed mb-10">
                Streamlining the university research management pipeline. Submit proposals, track documentation status, and accelerate collaboration across departments.
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

        </main>

        <footer class="relative z-10 w-full max-w-7xl mx-auto px-6 py-8 border-t border-gray-200/60 dark:border-gray-900 text-center sm:text-left flex flex-col sm:flex-row justify-between items-center gap-4 text-xs text-gray-500">
            <div>
                &copy; {{ date('Y') }} Project ATHENA. Developed for Batangas State University.
            </div>
            <div class="flex items-center gap-6 font-medium text-gray-600 dark:text-gray-400">
                <span>Research Office Portal</span>
                <span>v1.0.0</span>
            </div>
        </footer>

    </body>
</html>