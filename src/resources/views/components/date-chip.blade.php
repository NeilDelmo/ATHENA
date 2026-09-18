@props([
    'date',
    'weekday' => false,
    'time' => false,
    'relative' => false,
    'year' => false,
    'compact' => false,
    'tone' => 'rose',
])

@php
    $carbon = $date instanceof \Illuminate\Support\Carbon ? $date : \Illuminate\Support\Carbon::parse($date);
    $meta = collect([
        $weekday ? $carbon->format('D') : null,
        $time && ! $carbon->isMidnight() ? $carbon->format('g:i A') : null,
        $relative ? $carbon->diffForHumans(short: true) : null,
    ])->filter()->implode(' · ');
    $tones = [
        'rose' => 'border-rose-100 bg-rose-50/90 text-[#7A0019] dark:border-red-900/50 dark:bg-red-950/50 dark:text-red-300',
        'stone' => 'border-stone-200 bg-stone-50 text-stone-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-400',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2 align-middle']) }}>
    <span class="{{ $compact ? 'px-1.5 py-1' : 'px-2.5 py-1.5' }} inline-flex flex-col items-center justify-center rounded-2xl border leading-none shadow-bubble-sm {{ $tones[$tone] ?? $tones['rose'] }}">
        <span class="text-[8px] font-black uppercase tracking-[0.18em]">{{ $carbon->format('M') }}</span>
        <span class="{{ $compact ? 'text-xs' : 'text-sm' }} mt-0.5 font-black tabular-nums text-gray-950 dark:text-white">{{ $carbon->format('j') }}</span>
    </span>
    @if (filled($meta) || $year)
        <span class="min-w-0 text-[11px] font-semibold leading-tight text-gray-500 dark:text-gray-400">
            {{ $meta }}
            @if ($year && $carbon->year !== now()->year)
                <span class="block text-[10px] font-bold text-[#7A0019] dark:text-red-300">{{ $carbon->format('Y') }}</span>
            @endif
        </span>
    @endif
</span>
