<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Research targets" subtitle="Set annual research goals and allocate targets to each college.">
            <x-slot name="actions">
                <a wire:navigate href="{{ route('research_head.dashboard') }}" class="rh-button-secondary gap-2"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="m14 7-5 5 5 5M9 12h11" stroke-linecap="round" stroke-linejoin="round" /></svg>Back to dashboard</a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <livewire:research-target-board />
</x-app-layout>
