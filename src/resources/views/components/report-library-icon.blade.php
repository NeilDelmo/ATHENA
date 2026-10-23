@props(['name'])

@if ($name === 'folder')
    <svg {{ $attributes->merge(['class' => 'h-16 w-20 shrink-0']) }} viewBox="0 0 80 64" aria-hidden="true" focusable="false">
        <path d="M5 13a5 5 0 0 1 5-5h20l7 7h33a5 5 0 0 1 5 5v32a5 5 0 0 1-5 5H10a5 5 0 0 1-5-5Z" fill="#D99417" />
        <path d="M9 23h62v28H9Z" fill="#FFF4C7" />
        <path d="M4 25a4 4 0 0 1 4-4h64a4 4 0 0 1 4 4l-4 29a4 4 0 0 1-4 4H12a4 4 0 0 1-4-4Z" fill="#F5C451" stroke="#D99417" stroke-width="1.5" />
        <path d="M9 27h61" stroke="#FFE8A0" stroke-width="2" />
    </svg>
@else
    @php
        $paths = [
            'back' => 'm12 5-7 7 7 7M5 12h15',
            'home' => 'm3 10 9-7 9 7v11h-6v-7H9v7H3V10Z',
            'open' => 'm9 5 7 7-7 7',
            'folder-open' => 'M3 7V5a2 2 0 0 1 2-2h5l2 3h7a2 2 0 0 1 2 2v2M3 7v12a2 2 0 0 0 2 2h14l3-11H6L3 21',
            'cut' => 'M6 9a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm0 12a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM8.2 8.2 21 21M8.2 15.8 21 3',
            'copy' => 'M9 9h12v12H9V9ZM5 15H3V3h12v2',
            'paste' => 'M9 4H5v17h14V4h-4M9 3h6v4H9V3Zm0 9h6m-6 4h6',
            'actions' => 'M6 12a1 1 0 1 1-2 0 1 1 0 0 1 2 0Zm7 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0Zm7 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0Z',
            'rename' => 'm15 4 5 5M4 20l4-1L21 6l-5-5L3 14l1 6Z',
            'remove' => 'M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7m4-7v7',
            'new' => 'M3 7V5h7l2 3h9v13H3V7Zm9 5v6m-3-3h6',
            'move' => 'M3 7V5h7l2 3h9v13H3V7Zm4 7h10m-3-3 3 3-3 3',
            'refresh' => 'M21 3v6h-6M3 21v-6h6M5 9a8 8 0 0 1 13-5l3 5M3 15l3 5a8 8 0 0 0 13-5',
            'undo' => 'm8 5-5 5 5 5M3 10h10a7 7 0 0 1 7 7',
            'download' => 'M12 3v12m-5-5 5 5 5-5M4 16v5h16v-5',
            'preview' => 'M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12Zm13 0a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z',
            'save' => 'M4 3h13l4 4v14H3V3h1Zm3 0v6h10V3M7 21v-8h10v8',
            'export' => 'M14 3H4v18h16V9M14 3v6h6M12 12v6m-3-3 3 3 3-3',
            'return' => 'M9 5 3 11l6 6M3 11h11a6 6 0 0 1 6 6v3',
            'history' => 'M3 11a9 9 0 1 1 2 7M3 4v7h7m2-4v6l4 2',
            'presentation' => 'M3 4h18v12H3V4Zm9 12v5m-4 0 4-5 4 5M7 8h10M7 12h6',
            'publication' => 'M3 4h7l2 2 2-2h7v16h-7l-2 2-2-2H3V4Zm9 2v16M6 8h3M6 12h3m6-4h3m-3 4h3',
            'ip' => 'M9 18h6m-5 3h4M8 14a6 6 0 1 1 8 0c-1 1-1 2-1 4H9c0-2 0-3-1-4Z',
            'calendar' => 'M4 5h16v16H4V5Zm0 5h16M8 3v4m8-4v4',
            'grid' => 'M3 3h7v7H3V3Zm11 0h7v7h-7V3ZM3 14h7v7H3v-7Zm11 0h7v7h-7v-7Z',
            'list' => 'M8 5h13M8 12h13M8 19h13M3 5h1M3 12h1M3 19h1',
            'sort-asc' => 'M7 20V4m-4 4 4-4 4 4m3-2h7m-7 6h5m-5 6h3',
            'sort-desc' => 'M7 4v16m-4-4 4 4 4-4m3-10h7m-7 6h5m-5 6h3',
            'college' => 'm3 8 9-5 9 5H3Zm2 3v7m5-7v7m4-7v7m5-7v7M3 21h18',
            'submit' => 'm3 3 18 9-18 9 4-9-4-9Zm4 9h14',
        ];
    @endphp
    <svg {{ $attributes->merge(['class' => 'h-6 w-6 shrink-0']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="{{ $paths[$name] }}" /></svg>
@endif
