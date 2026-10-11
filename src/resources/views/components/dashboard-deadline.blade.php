@props(['date'])

@php
    $deadline = \Carbon\CarbonImmutable::parse($date)->timezone(config('app.timezone'));
    $today = \Carbon\CarbonImmutable::now(config('app.timezone'));
    $days = (int) $today->startOfDay()->diffInDays($deadline->startOfDay(), false);
    $label = match (true) {
        $days < 0 => abs($days).' '.str('day')->plural(abs($days)).' overdue',
        $days === 0 => $deadline->isPast() ? 'Overdue today' : 'Due today',
        $days === 1 => 'Due tomorrow',
        default => 'Due in '.$days.' days',
    };
@endphp

<span {{ $attributes->class(['inline-flex flex-wrap items-baseline gap-x-2 gap-y-1 text-sm']) }}>
    <span @class(['font-semibold tabular-nums', 'text-brand dark:text-rose-300' => $days <= 3, 'text-slate-700 dark:text-slate-200' => $days > 3])>{{ $label }}</span>
    <time datetime="{{ $deadline->toIso8601String() }}" class="text-slate-600 dark:text-slate-400">{{ $deadline->format('M j, Y') }}</time>
</span>
