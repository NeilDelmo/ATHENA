<x-guest-layout>
    <main class="relative flex min-h-screen items-center justify-center overflow-hidden bg-slate-50 px-4 py-10 dark:bg-slate-950 sm:px-6">
        <div class="absolute inset-0 opacity-70 [background-image:linear-gradient(to_right,#dc2626_1px,transparent_1px),linear-gradient(to_bottom,#dc2626_1px,transparent_1px)] [background-size:24px_24px] [mask-image:linear-gradient(to_bottom,black,transparent)] dark:opacity-[0.06] dark:[background-image:linear-gradient(to_right,#ffffff_1px,transparent_1px),linear-gradient(to_bottom,#ffffff_1px,transparent_1px)]" aria-hidden="true"></div>

        <section class="relative z-10 w-full max-w-5xl overflow-hidden rounded-[1.75rem] border border-white/60 bg-white shadow-2xl shadow-red-950/20 dark:border-slate-800 dark:bg-slate-900 dark:shadow-black/30" aria-labelledby="role-selection-title">
            <header class="border-b border-slate-200 bg-gradient-to-r from-red-700 to-red-950 px-6 py-7 text-white sm:px-9">
                <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div class="flex items-center gap-3">
                            <img src="{{ asset('images/logo_athena_gray-transparent.png') }}" alt="ATHENA" class="h-12 w-12 rounded-xl bg-white/90 object-contain p-1">
                            <div>
                                <p class="text-[11px] font-black uppercase tracking-[0.2em] text-red-100">ATHENA access</p>
                                <h1 id="role-selection-title" class="mt-1 text-2xl font-black tracking-tight sm:text-3xl">Choose your workspace</h1>
                            </div>
                        </div>
                        <p class="mt-4 max-w-2xl text-sm leading-6 text-red-100">Your account has more than one workspace available. Choose how you want to use ATHENA for this session.</p>
                    </div>

                    <div class="rounded-2xl border border-white/20 bg-red-950/30 px-4 py-3 backdrop-blur-sm">
                        <p class="text-xs font-black">{{ $user->name }}</p>
                        <p class="mt-1 max-w-56 truncate text-[11px] text-red-100">{{ $user->email }}</p>
                    </div>
                </div>
            </header>

            <div class="p-6 sm:p-9">
                @error('role')
                    <div class="mb-5 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700 dark:border-red-900/70 dark:bg-red-950/40 dark:text-red-300">{{ $message }}</div>
                @enderror

                <div class="grid gap-4 md:grid-cols-2">
                    <form method="POST" action="{{ route('role-selection.store') }}" class="flex">
                        @csrf
                        <input type="hidden" name="role" value="faculty">
                        <button type="submit" class="group flex w-full flex-col rounded-2xl border border-slate-200 bg-white p-5 text-left shadow-sm transition hover:-translate-y-0.5 hover:border-red-300 hover:shadow-lg hover:shadow-red-950/10 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 dark:border-slate-700 dark:bg-slate-800 dark:hover:border-red-700 dark:focus:ring-offset-slate-900">
                            <span class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-red-50 text-red-700 transition group-hover:bg-red-600 group-hover:text-white dark:bg-red-950/50 dark:text-red-300">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 14.25 3.75 9 12 3.75 20.25 9 12 14.25Zm0 0v6m-5.25-8.25v4.5c2.9 2.3 7.6 2.3 10.5 0V12" /></svg>
                            </span>
                            <span class="mt-5 text-lg font-black text-slate-900 dark:text-white">Continue as Faculty</span>
                            <span class="mt-2 grow text-sm leading-6 text-slate-500 dark:text-slate-400">Create proposal packages and track your research submissions.</span>
                            <span class="mt-5 inline-flex items-center gap-2 text-xs font-black uppercase tracking-wider text-red-700 dark:text-red-300">Open workspace <span aria-hidden="true" class="transition group-hover:translate-x-1">&rarr;</span></span>
                        </button>
                    </form>

                    <form method="POST" action="{{ route('role-selection.store') }}" class="flex">
                        @csrf
                        <input type="hidden" name="role" value="research_coordinator">
                        <button type="submit" class="group flex w-full flex-col rounded-2xl border border-slate-200 bg-white p-5 text-left shadow-sm transition hover:-translate-y-0.5 hover:border-red-300 hover:shadow-lg hover:shadow-red-950/10 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 dark:border-slate-700 dark:bg-slate-800 dark:hover:border-red-700 dark:focus:ring-offset-slate-900">
                            <span class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-red-50 text-red-700 transition group-hover:bg-red-600 group-hover:text-white dark:bg-red-950/50 dark:text-red-300">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.1 9.1 0 0 0 3.74-.48 3 3 0 0 0-4.68-2.72m.94 3.2v-.01c0-1.2-.34-2.32-.94-3.19m.94 3.2v.13A11.9 11.9 0 0 1 12 20.4c-2.17 0-4.2-.58-5.94-1.6v-.12a6 6 0 0 1 11-3.17M15 7.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                            </span>
                            <span class="mt-5 text-lg font-black text-slate-900 dark:text-white">Continue as Research Coordinator</span>
                            <span class="mt-2 grow text-sm leading-6 text-slate-500 dark:text-slate-400">View and coordinate faculty members from your college.</span>
                            <span class="mt-5 inline-flex items-center gap-2 text-xs font-black uppercase tracking-wider text-red-700 dark:text-red-300">Open workspace <span aria-hidden="true" class="transition group-hover:translate-x-1">&rarr;</span></span>
                        </button>
                    </form>
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
