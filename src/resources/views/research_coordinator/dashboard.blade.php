<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-2xl font-black tracking-tight text-gray-900 dark:text-white">Research Coordinator Dashboard</h2>
            <p class="mt-1 text-xs text-gray-500 dark:text-slate-400"> {{ $coordinator->college ?: 'your college' }}.</p>
        </div>
    </x-slot>

    <section class="grid grid-cols-2 gap-3 lg:grid-cols-4" aria-label="College summary">
        <div class="flex items-center justify-between rounded-2xl border border-gray-200/70 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-5">
            <div>
                <span class="block text-[10px] font-black uppercase tracking-wider text-gray-400">College members</span>
                <span class="mt-1 block text-2xl font-black text-gray-900 dark:text-white sm:text-3xl">{{ $memberCount }}</span>
            </div>
            <div class="rounded-xl bg-red-50 p-2.5 text-red-600 dark:bg-red-950/40 dark:text-red-300">
                <svg class="h-5 w-5 sm:h-6 sm:w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6.75a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" /></svg>
            </div>
        </div>
    </section>
</x-app-layout>
