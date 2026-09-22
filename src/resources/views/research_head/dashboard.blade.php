<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 border-l-4 border-[#800000] bg-white px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-[#800000]">Research Office Control Center</p>
                <h2 class="mt-1 text-xl font-bold tracking-tight text-slate-950 sm:text-2xl">Research Operations Dashboard</h2>
                <p class="mt-1 max-w-3xl text-sm text-slate-500">Review proposals, track active projects, and manage official research deadlines.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 text-xs">
                <span class="inline-flex items-center gap-2 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 font-semibold text-emerald-800">
                    <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                    Research operations active
                </span>
                <a href="{{ route('research_head.projects.index') }}" class="inline-flex items-center rounded-md border border-slate-200 bg-white px-3 py-2 font-semibold text-slate-700 hover:border-[#800000] hover:text-[#800000]">Project monitoring</a>
            </div>
        </div>
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
