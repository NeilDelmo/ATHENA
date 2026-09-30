<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Analytics" subtitle="Research performance, reported budgets, and annual targets." />
    </x-slot>
    <div class="-mx-4 -my-6 min-h-screen bg-slate-50 px-4 py-6 text-slate-900 dark:bg-slate-950 dark:text-slate-100 sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8" data-research-head-analytics>
        <livewire:research-head-dashboard />
    </div>
</x-app-layout>
