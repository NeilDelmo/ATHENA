<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Calendar" subtitle="Research schedules, official deadlines, and your reminders." />
    </x-slot>
    <section id="research-calendar" aria-label="Research calendar" data-research-head-calendar>
        <div class="mb-3 flex justify-end">
            <a wire:navigate href="{{ route('research-calls.index') }}" class="rh-button-secondary">Manage calls</a>
        </div>
        <livewire:dashboard-calendar />
    </section>
</x-app-layout>
