<x-app-layout>
    <x-slot name="header">
        <x-workspace-header-banner class="!pt-0 dark:border-rose-800 dark:[&_h2]:text-slate-100 dark:[&_p]:text-slate-400" eyebrow="Research Head" title="Calendar" description="Research schedules, official deadlines, and your reminders.">
            <x-slot:actions><x-research-head-page-navigation /></x-slot:actions>
        </x-workspace-header-banner>
    </x-slot>
    <section id="research-calendar" aria-label="Research calendar" data-research-head-calendar>
        <div class="mb-3 flex justify-end">
            <a wire:navigate href="{{ route('research-calls.index') }}" class="rh-button-secondary">Manage calls</a>
        </div>
        <livewire:dashboard-calendar />
    </section>
</x-app-layout>
