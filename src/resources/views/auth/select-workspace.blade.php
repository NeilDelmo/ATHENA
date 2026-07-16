<x-guest-layout>
    <main class="relative flex min-h-screen items-center justify-center overflow-hidden bg-slate-100 px-5 py-12 text-slate-900 transition-colors dark:bg-slate-950 dark:text-white sm:px-8">
        <button
            id="workspace-theme-toggle"
            data-theme-toggle
            type="button"
            class="absolute right-5 top-5 z-30 inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white/90 text-slate-600 shadow-sm backdrop-blur transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-red-500 dark:border-slate-700 dark:bg-slate-900/90 dark:text-slate-300 dark:hover:bg-slate-800"
            aria-label="Toggle light and dark theme"
            title="Toggle theme"
        >
            <svg class="h-5 w-5 dark:hidden" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 15.75A9 9 0 118.25 2.25a7.5 7.5 0 0013.5 13.5z" /></svg>
            <svg class="hidden h-5 w-5 dark:block" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1.5m0 15V21m9-9h-1.5M4.5 12H3m15.364 6.364l-1.061-1.061M6.697 6.697 5.636 5.636m12.728 0-1.061 1.061M6.697 17.303l-1.061 1.061M16.5 12a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0Z" /></svg>
        </button>

        <div class="absolute inset-0 bg-gradient-to-br from-red-100/90 via-white/75 to-slate-200/80 dark:from-red-950/35 dark:via-slate-950/65 dark:to-red-950/20"></div>
        <div class="absolute inset-0 opacity-[0.07] [background-image:linear-gradient(to_right,#7f1d1d_1px,transparent_1px),linear-gradient(to_bottom,#7f1d1d_1px,transparent_1px)] [background-size:24px_24px] dark:opacity-[0.06] dark:[background-image:linear-gradient(to_right,#ffffff_1px,transparent_1px),linear-gradient(to_bottom,#ffffff_1px,transparent_1px)]"></div>

        <section class="relative z-10 w-full max-w-5xl overflow-hidden rounded-[1.75rem] border border-white/60 bg-white shadow-2xl shadow-red-950/20 dark:border-slate-800 dark:bg-slate-900 dark:shadow-black/30">
            <header class="border-b border-slate-200 bg-gradient-to-r from-red-700 to-red-950 px-6 py-7 text-white sm:px-9">
                <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div class="flex items-center gap-3">
                            <img src="{{ asset('images/logo_athena_gray-transparent.png') }}" alt="ATHENA" class="h-12 w-12 rounded-xl bg-white/90 object-contain p-1">
                            <div>
                                <p class="text-[11px] font-black uppercase tracking-[0.2em] text-red-100">ATHENA access</p>
                                <h1 class="mt-1 text-2xl font-black tracking-tight sm:text-3xl">Choose your workspace</h1>
                            </div>
                        </div>
                        <p class="mt-4 max-w-2xl text-sm leading-6 text-red-100">Your account has more than one workspace available. Choose how you want to use ATHENA for this session.</p>
                    </div>

                    <div class="rounded-2xl border border-white/20 bg-red-950/30 px-4 py-3 backdrop-blur-sm">
                        <p class="text-xs font-black">{{ Auth::user()->name }}</p>
                        <p class="mt-1 max-w-56 truncate text-[11px] text-red-100">{{ Auth::user()->email }}</p>
                    </div>
                </div>
            </header>

            <div class="p-6 sm:p-9">
                @error('workspace')
                    <div class="mb-5 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700 dark:border-red-900/70 dark:bg-red-950/40 dark:text-red-300">{{ $message }}</div>
                @enderror

                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    @foreach ($workspaces as $key => $workspace)
                        <form method="POST" action="{{ route('workspace.store') }}" class="flex">
                            @csrf
                            <input type="hidden" name="workspace" value="{{ $key }}">
                            <button type="submit" class="group flex w-full flex-col rounded-2xl border border-slate-200 bg-white p-5 text-left shadow-sm transition hover:-translate-y-0.5 hover:border-red-300 hover:shadow-lg hover:shadow-red-950/10 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 dark:border-slate-700 dark:bg-slate-800 dark:hover:border-red-700 dark:focus:ring-offset-slate-900">
                                <span class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-red-50 text-red-700 transition group-hover:bg-red-600 group-hover:text-white dark:bg-red-950/50 dark:text-red-300">
                                    @if ($key === 'research_head')
                                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v6m-9.75 3.75h13.5M4.5 5.25h15a.75.75 0 0 1 .75.75v12.75H3.75V6a.75.75 0 0 1 .75-.75Z" /></svg>
                                    @elseif ($key === 'expert')
                                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m9 12.75 2.25 2.25L15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                                    @elseif ($key === 'faculty_researcher')
                                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18.75h12M7.5 3.75h9v15h-9v-15Zm2.25 3h4.5m-4.5 3h4.5" /></svg>
                                    @else
                                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 14.25 3.75 9 12 3.75 20.25 9 12 14.25Zm0 0v6m-5.25-8.25v4.5c2.9 2.3 7.6 2.3 10.5 0V12" /></svg>
                                    @endif
                                </span>
                                <span class="mt-5 text-lg font-black text-slate-900 dark:text-white">Continue as {{ $workspace['label'] }}</span>
                                <span class="mt-2 grow text-sm leading-6 text-slate-500 dark:text-slate-400">{{ $workspace['description'] }}</span>
                                <span class="mt-5 inline-flex items-center gap-2 text-xs font-black uppercase tracking-wider text-red-700 dark:text-red-300">
                                    Open workspace
                                    <span aria-hidden="true" class="transition group-hover:translate-x-1">&rarr;</span>
                                </span>
                            </button>
                        </form>
                    @endforeach
                </div>

                <footer class="mt-7 flex flex-col gap-3 border-t border-slate-200 pt-5 text-xs text-slate-500 dark:border-slate-700 dark:text-slate-400 sm:flex-row sm:items-center sm:justify-between">
                    <p>Your assigned system roles are unchanged. You can switch workspaces again from your account menu.</p>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="font-bold text-red-700 hover:text-red-800 focus:outline-none focus:underline dark:text-red-300 dark:hover:text-red-200">Sign out</button>
                    </form>
                </footer>
            </div>
        </section>
    </main>
</x-guest-layout>
