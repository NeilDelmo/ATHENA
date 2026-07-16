<x-guest-layout>
    <div class="relative flex min-h-screen items-center justify-center overflow-hidden bg-slate-950 px-4 py-10">
        <div class="absolute inset-0 bg-gradient-to-br from-red-950 via-slate-950 to-slate-900" aria-hidden="true"></div>
        <div class="absolute -left-20 top-10 h-72 w-72 rounded-full bg-red-600/20 blur-3xl" aria-hidden="true"></div>
        <div class="absolute -right-20 bottom-10 h-72 w-72 rounded-full bg-purple-600/20 blur-3xl" aria-hidden="true"></div>

        <section class="relative w-full max-w-md rounded-3xl border border-white/10 bg-white p-6 shadow-2xl dark:bg-slate-900 sm:p-8" role="dialog" aria-labelledby="role-selection-title" aria-modal="true">
            <div class="flex justify-center">
                <img src="{{ asset('images/athenalogo-transparent.png') }}" alt="ATHENA" class="h-20 w-20 object-contain">
            </div>
            <div class="mt-3 text-center">
                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-red-600 dark:text-red-400">Choose your workspace</p>
                <h1 id="role-selection-title" class="mt-2 text-2xl font-black tracking-tight text-gray-900 dark:text-white">How would you like to continue?</h1>
                <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-slate-400">Welcome, {{ $user->name }}. Select the role you want to use for this session.</p>
            </div>

            <div class="mt-6 grid gap-3">
                <form method="POST" action="{{ route('role-selection.store') }}">
                    @csrf
                    <input type="hidden" name="role" value="faculty">
                    <button type="submit" class="group flex w-full items-center gap-4 rounded-2xl border border-gray-200 p-4 text-left transition hover:border-red-200 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-500 dark:border-slate-700 dark:hover:border-red-900 dark:hover:bg-red-950/30">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-red-50 text-red-600 group-hover:bg-red-600 group-hover:text-white dark:bg-red-950/50 dark:text-red-300">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 14.25c3.73 0 6.75-2.01 6.75-4.5S15.73 5.25 12 5.25s-6.75 2.01-6.75 4.5 3.02 4.5 6.75 4.5Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M5.25 9.75v4.5c0 2.49 3.02 4.5 6.75 4.5s6.75-2.01 6.75-4.5v-4.5M3 8.25l9-4.5 9 4.5-9 4.5-9-4.5Z" /></svg>
                        </span>
                        <span class="min-w-0 flex-1"><span class="block text-sm font-black text-gray-900 dark:text-white">Continue as Faculty</span><span class="mt-1 block text-xs text-gray-500 dark:text-slate-400">Open your faculty research workspace.</span></span>
                        <span class="text-gray-300 group-hover:text-red-600" aria-hidden="true">&rarr;</span>
                    </button>
                </form>

                <form method="POST" action="{{ route('role-selection.store') }}">
                    @csrf
                    <input type="hidden" name="role" value="research_coordinator">
                    <button type="submit" class="group flex w-full items-center gap-4 rounded-2xl border border-gray-200 p-4 text-left transition hover:border-purple-200 hover:bg-purple-50 focus:outline-none focus:ring-2 focus:ring-purple-500 dark:border-slate-700 dark:hover:border-purple-900 dark:hover:bg-purple-950/30">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-purple-50 text-purple-700 group-hover:bg-purple-700 group-hover:text-white dark:bg-purple-950/50 dark:text-purple-300">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.1 9.1 0 0 0 3.74-.48 3 3 0 0 0-4.68-2.72m.94 3.2v-.01c0-1.2-.34-2.32-.94-3.19m.94 3.2v.13A11.9 11.9 0 0 1 12 20.4c-2.17 0-4.2-.58-5.94-1.6v-.12a6 6 0 0 1 11-3.17M15 7.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                        </span>
                        <span class="min-w-0 flex-1"><span class="block text-sm font-black text-gray-900 dark:text-white">Continue as Research Coordinator</span><span class="mt-1 block text-xs text-gray-500 dark:text-slate-400">View faculty members from your college.</span></span>
                        <span class="text-gray-300 group-hover:text-purple-700" aria-hidden="true">&rarr;</span>
                    </button>
                </form>
            </div>
        </section>
    </div>
</x-guest-layout>
