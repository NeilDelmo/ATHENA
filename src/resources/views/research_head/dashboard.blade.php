<x-app-layout>
    <x-slot name="header">
        <x-page-header variant="hero"
            eyebrow="Research Head"
            title="Dashboard"
            subtitle="Review submissions, follow up on project issues, and track upcoming deadlines."
        >
            <x-slot name="actions">
                <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                    <a wire:navigate href="{{ route('research_head.targets.index') }}" class="dashboard-action justify-center">
                        <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><circle cx="12" cy="12" r="9" /><circle cx="12" cy="12" r="5" /><circle cx="12" cy="12" r="1" /></svg>
                        Research targets
                    </a>
                    <a wire:navigate href="{{ route('research_head.rms-quarterly-reports.index') }}" class="dashboard-action justify-center">
                        <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="M6 3v3m12-3v3M3 10h18M5 5h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Zm2 9h3m4 0h3m-10 3h3" stroke-linecap="round" stroke-linejoin="round" /></svg>
                        Quarterly monitoring
                    </a>
                </div>
            </x-slot>
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
