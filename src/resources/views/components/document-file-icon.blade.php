@props(['label' => null])

<span {{ $attributes->merge(['class' => 'relative inline-flex h-14 w-12 shrink-0 items-center justify-center']) }} aria-hidden="true" data-document-file-icon>
    <svg class="h-full w-full" viewBox="0 0 56 64" fill="none" focusable="false">
        <path d="M8 2h27l13 13v45a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2Z" fill="#FFF" stroke="#94A3B8" stroke-width="1.5" />
        <path d="M35 2v13h13M15 24h25M15 30h25M15 36h18" stroke="#CBD5E1" stroke-width="2" />
    </svg>
    @if ($label)
        <span class="absolute bottom-1 rounded bg-red-700 px-1 text-sm font-bold leading-5 text-white">{{ $label }}</span>
    @endif
</span>
