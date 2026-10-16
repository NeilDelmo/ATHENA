<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Research targets" subtitle="Set annual research goals and allocate targets to each college.">
            <x-slot name="actions">
                <a wire:navigate href="{{ route('research_head.dashboard') }}" class="dashboard-action">Back to dashboard</a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <livewire:research-target-board />
</x-app-layout>
