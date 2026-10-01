@props(['items'])

<dl
    {{ $attributes->class(['grid w-full grid-cols-2 gap-y-3 rounded-[10px] bg-[linear-gradient(90deg,#650015_0%,#7A0019_50%,#90142C_100%)] px-2 py-[11px] sm:grid-cols-[repeat(var(--cols),minmax(0,1fr))] sm:gap-y-0']) }}
    style="--cols: {{ count($items) }}"
    data-kpi-strip
>
    @foreach ($items as $item)
        <div @if (isset($item['hint'])) title="{{ $item['hint'] }}" @endif class="min-w-0 px-5 [container-type:inline-size] sm:border-l sm:border-white/[0.18] sm:first:border-l-0">
            <dt class="flex items-center gap-1.5 text-xs font-normal leading-[14px] tracking-normal text-white/80">
                <svg class="h-3.5 w-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                    @switch($item['icon'])
                        @case('folder')
                            <path d="M3 7a2 2 0 0 1 2-2h5l2 2h7a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Z" />
                            @break
                        @case('clock')
                            <circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" />
                            @break
                        @case('layers')
                            <path d="m12 3 9 5-9 5-9-5Zm-9 9 9 5 9-5M3 16l9 5 9-5" />
                            @break
                        @case('file-plus')
                            <path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9Zm0 0v6h6M12 12v6m-3-3h6" />
                            @break
                        @case('refresh')
                            <path d="M20 7v5h-5M4 17v-5h5M6 7a7 7 0 0 1 11.5-1L20 9M4 15l2.5 3A7 7 0 0 0 18 17" />
                            @break
                        @case('play')
                            <path d="m8 4 12 8-12 8Z" />
                            @break
                        @case('alert-triangle')
                            <path d="m12 3 10 18H2ZM12 9v5m0 3h.01" />
                            @break
                        @case('hourglass')
                            <path d="M6 3h12M6 21h12M7 3v4l5 5-5 5v4M17 3v4l-5 5 5 5v4" />
                            @break
                        @case('circle-check')
                            <circle cx="12" cy="12" r="9" /><path d="m8 12 3 3 5-6" />
                            @break
                        @case('file-text')
                            <path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9Zm0 0v6h6M8 13h8m-8 4h8" />
                            @break
                        @case('megaphone')
                            <path d="m10 15-1 5H6l1-6M4 9h3l13-5v16L7 15H4a2 2 0 0 1-2-2v-2a2 2 0 0 1 2-2Zm3 0v6" />
                            @break
                        @case('calendar')
                            <rect x="3" y="5" width="18" height="16" rx="2" /><path d="M16 3v4M8 3v4M3 11h18m-13 5h2" />
                            @break
                        @case('archive')
                            <rect x="3" y="3" width="18" height="4" rx="1" /><path d="M5 7v14h14V7m-9 5h4" />
                            @break
                        @case('wallet')
                            <path d="M20 8V5a2 2 0 0 0-2-2H5a3 3 0 0 0 0 6h15v12H5a3 3 0 0 1-3-3V6m18 7h-5v4h5m-3-2h.01" />
                            @break
                    @endswitch
                </svg>
                <span class="min-w-0 break-words">{{ $item['label'] }}</span>
            </dt>
            <dd class="mt-[5px] whitespace-nowrap text-[22px] font-medium leading-[1.1] tracking-normal text-white tabular-nums" style="font-size: min(22px, calc(100cqi / {{ max(mb_strlen((string) $item['value']), 1) }} * 1.6))">{{ $item['value'] }}</dd>
        </div>
    @endforeach
</dl>
