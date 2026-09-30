<x-app-layout>
    <x-slot name="header">
        <x-workspace-header-banner class="!pt-0 dark:border-rose-800 dark:[&_h2]:text-slate-100 dark:[&_p]:text-slate-400" eyebrow="Research Head" title="Analytics" description="Research performance, reported budgets, and annual targets.">
            <x-slot:actions><x-research-head-page-navigation /></x-slot:actions>
        </x-workspace-header-banner>
    </x-slot>
    <div class="-mx-4 -my-6 min-h-screen bg-slate-50 px-4 py-6 text-slate-900 dark:bg-slate-950 dark:text-slate-100 sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8" data-research-head-analytics>
        <livewire:research-head-dashboard />
    </div>
</x-app-layout>
