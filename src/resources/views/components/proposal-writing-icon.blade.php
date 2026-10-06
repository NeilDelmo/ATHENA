@props(['name'])

@php
    $paths = [
        'bold' => 'M6 4h7a4 4 0 0 1 0 8H6V4Zm0 8h8a4 4 0 0 1 0 8H6v-8Z',
        'italic' => 'M10 4h10M4 20h10M15 4 9 20',
        'underline' => 'M6 3v7a6 6 0 0 0 12 0V3M4 21h16',
        'bullets' => 'M9 6h12M9 12h12M9 18h12M3 6h.01M3 12h.01M3 18h.01',
        'numbered-list' => 'M10 6h11M10 12h11M10 18h11M3 4h1v4M3 8h2M3 12a1 1 0 0 1 2 0c0 1-2 1-2 3h2M3 18h1a1 1 0 0 1 0 2H3m1-2a1 1 0 0 0 0-2H3',
        'undo' => 'M3 10h11a6 6 0 0 1 0 12M3 10l5-5M3 10l5 5',
        'redo' => 'M21 10H10a6 6 0 0 0 0 12M21 10l-5-5M21 10l-5 5',
        'image' => 'M3 3h18v18H3V3Zm0 14 6-6 4 4 3-3 5 5M8 7h.01',
        'table' => 'M3 3h18v18H3V3Zm0 6h18M3 15h18M9 3v18M15 3v18',
        'source' => 'M4 3h12l4 4v14H4V3Zm12 0v5h4M8 12h8M8 16h5',
        'expand' => 'M8 3H3v5M3 3l6 6M16 3h5v5M21 3l-6 6M3 16v5h5M3 21l6-6M21 16v5h-5M21 21l-6-6',
        'shrink' => 'M3 3l6 6M4 9h5V4M21 3l-6 6M20 9h-5V4M3 21l6-6M4 15h5v5M21 21l-6-6M20 15h-5v5',
        'add-row' => 'M3 3h18v10H3V3Zm0 5h18M9 3v10M12 16v6M9 19h6',
        'add-column' => 'M3 3h10v18H3V3Zm5 0v18M3 9h10M16 12h6M19 9v6',
        'remove-row' => 'M3 3h18v10H3V3Zm0 5h18M9 3v10M9 19h6',
        'remove-column' => 'M3 3h10v18H3V3Zm5 0v18M3 9h10M16 12h6',
        'remove-table' => 'M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7M14 10v7',
    ];
@endphp

<svg {{ $attributes->merge(['class' => 'proposal-writing-icon']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
    <path d="{{ $paths[$name] }}" />
</svg>
