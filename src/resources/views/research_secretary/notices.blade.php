<x-app-layout>
    <x-slot name="header"><x-page-header variant="hero" title="Notices to Proceed" subtitle="Prepare Notices to Proceed and release signed documents for cleared projects."><x-slot name="actions"><x-back-link href="{{ route('research_secretary.dashboard') }}">Back to dashboard</x-back-link></x-slot></x-page-header></x-slot>
    <div class="secretary-workspace" data-secretary-workspace="notices"><x-notice-to-proceed-queue :projects="$signingProjects" /></div>
</x-app-layout>
