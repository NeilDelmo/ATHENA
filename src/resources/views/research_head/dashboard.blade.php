<x-app-layout>
    <x-slot name="header">
        <x-page-header variant="hero"
            class="!border-l-0 !bg-transparent !px-0 !pt-0 [&_p:first-child]:!text-sm [&_p:first-child]:!normal-case [&_p:first-child]:!tracking-normal [&_h2]:!text-3xl dark:[&_h2]:text-slate-100 dark:[&_p]:text-slate-400"
            eyebrow="Research Head"
            title="Dashboard"
            subtitle="Your review queue, project priorities, and upcoming deadlines."
        >
        </x-page-header>
    </x-slot>

    <div class="-mx-4 -my-6 min-h-screen bg-slate-50 px-4 py-6 text-slate-900 dark:bg-slate-950 dark:text-slate-100 sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8" data-dashboard-palette="maroon-slate-white" data-research-head-dashboard>
        @if (session('success'))
            <div class="mb-4 flex items-start gap-3 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 shadow-sm">
                <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-emerald-500" aria-hidden="true"></span>
                <p class="font-semibold">{{ session('success') }}</p>
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-rose-200 border-l-4 border-l-[#800000] bg-rose-50 px-4 py-3 text-sm text-[#800000]">
                <p class="font-black">The request could not be completed.</p>
                <p class="mt-1">{{ $errors->first() }}</p>
            </div>
        @endif

        <livewire:research-head-dashboard :overview="true" />
    </div>
</x-app-layout>
