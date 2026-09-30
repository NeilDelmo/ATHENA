<x-app-layout>
    <x-slot name="header">
        <x-workspace-header-banner :eyebrow="Auth::user()->activeWorkspaceLabel()" title="Calendar" description="Research deadlines, scheduled activities, and your personal reminders." />
    </x-slot>
    <section aria-label="Research calendar" data-faculty-calendar>
        <livewire:dashboard-calendar />
    </section>
</x-app-layout>
