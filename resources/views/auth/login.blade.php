<x-guest-layout>
    <main class="relative flex min-h-screen bg-slate-50 text-slate-900 transition-colors duration-300 dark:bg-slate-950 dark:text-white">
        <button
            id="auth-theme-toggle"
            type="button"
            class="absolute right-5 top-5 z-30 inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white/90 text-slate-600 shadow-sm backdrop-blur transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-red-500 dark:border-slate-700 dark:bg-slate-900/90 dark:text-slate-300 dark:hover:bg-slate-800"
            aria-label="Toggle light and dark theme"
            title="Toggle theme"
        >
            <svg class="h-5 w-5 dark:hidden" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 15.75A9 9 0 118.25 2.25a7.5 7.5 0 0013.5 13.5z" />
            </svg>
            <svg class="hidden h-5 w-5 dark:block" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1.5m0 15V21m9-9h-1.5M4.5 12H3m15.364 6.364l-1.061-1.061M6.697 6.697L5.636 5.636m12.728 0l-1.061 1.061M6.697 17.303l-1.061 1.061M16.5 12a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0z" />
            </svg>
        </button>

        <section class="relative hidden w-1/2 flex-col justify-between overflow-hidden bg-gradient-to-br from-red-950 via-slate-950 to-red-900 p-12 text-white lg:flex">
            <div class="absolute inset-0 opacity-10 [background-image:linear-gradient(to_right,#94a3b8_1px,transparent_1px),linear-gradient(to_bottom,#94a3b8_1px,transparent_1px)] [background-size:24px_24px]"></div>
            <div class="absolute -bottom-40 -right-40 h-96 w-96 rounded-full bg-red-600/20 blur-3xl"></div>

            <div class="relative z-10 flex items-center gap-3">
                <span class="text-2xl font-black tracking-[0.2em] text-red-500">ATHENA</span>
                <span class="rounded-full border border-red-500/30 bg-red-500/15 px-2 py-0.5 text-xs font-bold text-red-200">Research Portal</span>
            </div>

            <div class="relative z-10 my-auto max-w-lg">
                <p class="mb-4 text-xs font-black uppercase tracking-[0.25em] text-red-400">Batangas State University</p>
                <h1 class="text-4xl font-black leading-tight tracking-tight xl:text-5xl">Research management, review, and support in one secure workspace.</h1>
                <p class="mt-5 max-w-md text-base leading-7 text-slate-300">Access is tied to your university-managed Google Workspace identity.</p>
            </div>

            <div class="relative z-10 flex items-center justify-between border-t border-slate-700/60 pt-4 text-xs text-slate-400">
                <span>&copy; {{ date('Y') }} Project ATHENA</span>
                <span>Institutional access only</span>
            </div>
        </section>

        <section class="flex w-full items-center justify-center px-6 py-20 sm:px-12 lg:w-1/2">
            <div class="w-full max-w-md">
                <div class="mb-10 lg:hidden">
                    <p class="text-2xl font-black tracking-[0.2em] text-red-600 dark:text-red-500">ATHENA</p>
                    <p class="mt-1 text-xs font-bold uppercase tracking-wider text-slate-400">BatStateU Research Portal</p>
                </div>

                <div class="rounded-3xl border border-slate-200 bg-white p-7 shadow-xl shadow-slate-200/40 transition-colors dark:border-slate-800 dark:bg-slate-900 dark:shadow-black/20 sm:p-9">
                    <div>
                        <span class="inline-flex rounded-full bg-red-50 px-3 py-1 text-[11px] font-black uppercase tracking-wider text-red-700 dark:bg-red-950/50 dark:text-red-300">Secure institutional sign-in</span>
                        <h2 class="mt-5 text-3xl font-black tracking-tight text-slate-900 dark:text-white">Welcome to ATHENA</h2>
                        <p class="mt-3 text-sm leading-6 text-slate-500 dark:text-slate-400">Continue using the Google account issued by Batangas State University.</p>
                    </div>

                    <x-auth-session-status class="mt-6" :status="session('status')" />

                    @error('google')
                        <div class="mt-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700 dark:border-red-900/70 dark:bg-red-950/40 dark:text-red-300">{{ $message }}</div>
                    @enderror

                    <a href="{{ route('auth.google.redirect') }}" class="mt-7 flex w-full items-center justify-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3.5 text-sm font-bold text-slate-700 shadow-sm transition hover:border-slate-300 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 dark:hover:border-slate-600 dark:hover:bg-slate-700 dark:focus:ring-offset-slate-900">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 01-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                            <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                            <path d="M5.84 14.09A6.3 6.3 0 015.49 12c0-.73.13-1.43.35-2.09V7.06H2.18A11 11 0 001 12c0 1.78.43 3.45 1.18 4.94l2.85-2.22.81-.63z" fill="#FBBC05"/>
                            <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06L5.84 9.9C6.71 7.3 9.14 5.38 12 5.38z" fill="#EA4335"/>
                        </svg>
                        Continue with BatStateU Google
                    </a>

                    <div class="mt-6 rounded-2xl bg-slate-50 p-4 dark:bg-slate-950/60">
                        <p class="text-xs font-bold text-slate-700 dark:text-slate-300">Required account</p>
                        <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">Only accounts ending in <span class="font-bold text-slate-700 dark:text-slate-200">@g.batstate-u.edu.ph</span> are accepted. Account creation and password recovery are managed by the university.</p>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <script>
        document.getElementById('auth-theme-toggle')?.addEventListener('click', () => {
            const isDark = document.documentElement.classList.toggle('dark');
            localStorage.setItem('athena-theme', isDark ? 'dark' : 'light');
            localStorage.removeItem('athena-auth-theme');
        });
    </script>
</x-guest-layout>
