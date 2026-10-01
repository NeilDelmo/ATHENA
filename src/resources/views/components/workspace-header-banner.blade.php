@props([
    'eyebrow',
    'title',
    'description',
])

<x-page-header variant="banner" :title="$title" :subtitle="$description" :eyebrow="$eyebrow" {{ $attributes }}>
    @isset($actions)
        <x-slot name="actions">{{ $actions }}</x-slot>
    @endisset
</x-page-header>
