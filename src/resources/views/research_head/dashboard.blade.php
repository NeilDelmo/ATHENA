<x-app-layout>
    <x-slot name="header">
        <x-workspace-header-banner
            class="!pt-0"
            eyebrow="Research Office Control Center"
            title="Research Operations Dashboard"
            description="Review proposals, track active projects, and manage official research deadlines."
        >
            <x-slot:actions>
                <span data-research-operations-status class="inline-flex self-end items-center gap-2 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-800 sm:self-auto">
                <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                Research operations active
                </span>
            </x-slot:actions>
        </x-workspace-header-banner>
    </x-slot>

    <div class="-mx-4 -my-6 min-h-screen bg-slate-50 px-4 py-6 text-slate-900 sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8" data-dashboard-palette="maroon-slate-white" data-research-head-dashboard>
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

        <livewire:research-head-dashboard />
    </div>
</x-app-layout>
