<x-app-layout>
    <x-slot name="header"><x-page-header variant="hero" eyebrow="Research office / Planning" title="Research targets" subtitle="Your college’s annual targets, progress, and recorded accomplishments."><x-slot name="actions"><x-back-link href="{{ route('research_coordinator.dashboard') }}">Back to dashboard</x-back-link></x-slot></x-page-header></x-slot>
    <div class="secretary-workspace" data-coordinator-workspace="targets"><livewire:research-target-board /></div>
</x-app-layout>
