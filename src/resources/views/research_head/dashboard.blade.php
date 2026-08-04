<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-black uppercase tracking-[0.2em] text-[#7A0019] dark:text-red-300">Research administration</p>
            <h2 class="mt-1 text-2xl font-black tracking-tight text-gray-950 dark:text-white">Research Head Dashboard</h2>
            <p class="mt-1 max-w-3xl text-sm leading-6 text-gray-600 dark:text-gray-400">Review submitted proposals, manage decisions, and move approved research into project monitoring.</p>
        </div>
    </x-slot>

    <div class="space-y-5" data-dashboard-palette="red-black-white">
        @if (session('success'))
            <div class="flex items-start gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-700 shadow-sm dark:border-gray-800 dark:bg-gray-950 dark:text-gray-300">
                <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-[#7A0019] dark:bg-red-400" aria-hidden="true"></span>
                <p class="font-semibold">{{ session('success') }}</p>
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-xl border border-red-200 border-l-4 border-l-[#7A0019] bg-red-50 px-4 py-3 text-sm text-[#7A0019] dark:border-red-950 dark:border-l-red-500 dark:bg-red-950/30 dark:text-red-200">
                <p class="font-black">The request could not be completed.</p>
                <p class="mt-1">{{ $errors->first() }}</p>
            </div>
        @endif

        <livewire:research-head-dashboard />
    </div>
</x-app-layout>
