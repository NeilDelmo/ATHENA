<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$manualReader['title']" :subtitle="$manualReader['edition'] ?? 'Research manual'" />
    </x-slot>

    <div class="mx-auto max-w-7xl">
        <x-research-manual-reader :reader="$manualReader" open-on-load />
    </div>
</x-app-layout>
