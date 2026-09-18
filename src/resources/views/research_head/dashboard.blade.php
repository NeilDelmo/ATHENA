<x-app-layout>
    <x-slot name="header">
        <div class="relative overflow-hidden rounded-3xl border border-rose-100 bg-gradient-to-br from-white via-rose-50/80 to-white px-5 py-6 shadow-bubble sm:px-7 dark:border-red-950/70 dark:from-slate-900 dark:via-red-950/25 dark:to-slate-900">
            <div class="pointer-events-none absolute -right-10 -top-16 h-44 w-44 rounded-full bg-gradient-to-br from-rose-200/70 to-red-300/40 blur-2xl dark:from-red-900/50 dark:to-red-950/40" aria-hidden="true"></div>
            <div class="pointer-events-none absolute -bottom-20 right-40 h-40 w-40 rounded-full border-[14px] border-rose-100/80 dark:border-red-950/60" aria-hidden="true"></div>
            <div class="pointer-events-none absolute -left-8 top-8 h-16 w-16 rounded-full border-[6px] border-rose-100 dark:border-red-950/60" aria-hidden="true"></div>
            <div class="relative">
                <p class="text-[11px] font-black uppercase tracking-[0.22em] text-[#7A0019] dark:text-red-300">Research administration</p>
                <h2 class="mt-1.5 text-2xl font-black tracking-tight text-gray-950 dark:text-white sm:text-3xl">Research Head Dashboard</h2>
                <p class="mt-1.5 max-w-3xl text-sm leading-6 text-gray-600 dark:text-gray-300">Review submitted proposals, manage decisions, and move approved research into project monitoring.</p>
            </div>
        </div>
    </x-slot>

    <div class="space-y-5" data-dashboard-palette="red-black-white">
        @if (session('success'))
            <div class="flex items-start gap-3 rounded-3xl border border-rose-100 bg-white px-4 py-3 text-sm text-gray-700 shadow-bubble-sm dark:border-red-950/70 dark:bg-slate-950 dark:text-gray-300">
                <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-[#7A0019] dark:bg-red-400" aria-hidden="true"></span>
                <p class="font-semibold">{{ session('success') }}</p>
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-3xl border border-rose-200 border-l-4 border-l-[#7A0019] bg-rose-50 px-4 py-3 text-sm text-[#7A0019] dark:border-red-950 dark:border-l-red-500 dark:bg-red-950/30 dark:text-red-200">
                <p class="font-black">The request could not be completed.</p>
                <p class="mt-1">{{ $errors->first() }}</p>
            </div>
        @endif

        <livewire:research-head-dashboard />
    </div>
</x-app-layout>
