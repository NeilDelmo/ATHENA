<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Calendar" subtitle="Research deadlines, scheduled activities, and your personal reminders." />
    </x-slot>
    <section aria-label="Research calendar" data-faculty-calendar>
        <livewire:dashboard-calendar />
    </section>
</x-app-layout>
