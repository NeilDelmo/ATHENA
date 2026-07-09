<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>@yield('page_title') | {{ config('app.name', 'ATHENA') }}</title>

        <script>
            (() => {
                const savedTheme = localStorage.getItem('athena-theme') || localStorage.getItem('athena-auth-theme');
                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                document.documentElement.classList.toggle('dark', savedTheme ? savedTheme === 'dark' : prefersDark);
            })();
        </script>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800,900&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <main class="relative flex min-h-screen items-center justify-center overflow-hidden bg-slate-50 px-6 py-12 text-slate-900 dark:bg-slate-950 dark:text-white">
            <div class="absolute inset-x-0 top-0 h-1 bg-red-600"></div>
            <div class="absolute -left-24 -top-24 h-72 w-72 rounded-full bg-red-100/70 blur-3xl dark:bg-red-950/30"></div>
            <div class="absolute -bottom-24 -right-24 h-72 w-72 rounded-full bg-slate-200/80 blur-3xl dark:bg-slate-800/50"></div>

            <section class="relative w-full max-w-lg rounded-3xl border border-slate-200 bg-white p-8 text-center shadow-xl shadow-slate-200/60 dark:border-slate-800 dark:bg-slate-900 dark:shadow-black/20 sm:p-10">
                <a href="{{ url('/') }}" class="inline-flex items-center gap-2 text-sm font-black tracking-[0.2em] text-red-600 dark:text-red-400">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-red-600 text-base tracking-normal text-white shadow-lg shadow-red-600/20">A</span>
                    ATHENA
                </a>

                <div class="mx-auto mt-8 flex h-16 w-16 items-center justify-center rounded-2xl bg-red-50 text-red-600 dark:bg-red-950/50 dark:text-red-300">
                    <svg class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                    </svg>
                </div>

                <p class="mt-6 text-xs font-black uppercase tracking-[0.24em] text-red-600 dark:text-red-400">Error @yield('code')</p>
                <h1 class="mt-3 text-3xl font-black tracking-tight text-slate-900 dark:text-white">@yield('heading')</h1>
                <p class="mx-auto mt-3 max-w-sm text-sm leading-6 text-slate-500 dark:text-slate-400">
                    @yield('message')
                </p>

                <div class="mt-8 flex flex-col-reverse justify-center gap-3 sm:flex-row">
                    <button type="button" onclick="history.back()" class="inline-flex items-center justify-center rounded-xl border border-slate-200 px-5 py-3 text-sm font-bold text-slate-700 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-400 focus:ring-offset-2 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800 dark:focus:ring-offset-slate-900">
                        Go back
                    </button>

                    @auth
                        <a href="{{ route('dashboard') }}" class="inline-flex items-center justify-center rounded-xl bg-red-600 px-5 py-3 text-sm font-bold text-white shadow-lg shadow-red-600/20 transition hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900">
                            Go to my dashboard
                        </a>
                    @else
                        <a href="{{ url('/') }}" class="inline-flex items-center justify-center rounded-xl bg-red-600 px-5 py-3 text-sm font-bold text-white shadow-lg shadow-red-600/20 transition hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900">
                            Return home
                        </a>
                    @endauth
                </div>

                <p class="mt-8 text-xs text-slate-400 dark:text-slate-500">If this keeps happening, contact the ATHENA system administrator.</p>
            </section>
        </main>
    </body>
</html>
