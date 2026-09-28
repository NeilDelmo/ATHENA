@props(['days', 'monthLabel', 'selectedDate', 'expanded' => false, 'large' => false])
<div>
    <div class="mb-4 flex items-center justify-between gap-3">
        <div><p class="text-[10px] font-black uppercase tracking-[0.16em] text-[#7A0019] dark:text-red-300">Month view</p><h4 class="mt-0.5 text-lg font-black tracking-tight text-slate-950 dark:text-white">{{ $monthLabel }}</h4></div>
        <div class="flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white p-1 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <button type="button" wire:click="moveMonth(-1)" aria-label="Previous month" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-base font-black text-slate-500 transition hover:bg-red-50 hover:text-[#7A0019] dark:text-slate-300 dark:hover:bg-red-950/30 dark:hover:text-red-200">&lsaquo;</button>
            <button type="button" wire:click="today" class="rounded-lg px-2.5 py-1.5 text-[10px] font-black uppercase tracking-wide text-slate-600 transition hover:bg-slate-100 hover:text-[#7A0019] dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-red-200">Today</button>
            <button type="button" wire:click="moveMonth(1)" aria-label="Next month" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-base font-black text-slate-500 transition hover:bg-red-50 hover:text-[#7A0019] dark:text-slate-300 dark:hover:bg-red-950/30 dark:hover:text-red-200">&rsaquo;</button>
        </div>
    </div>
    <div class="grid grid-cols-7 {{ $large ? 'gap-1.5' : 'gap-1' }} text-center">
        @foreach (['M', 'T', 'W', 'T', 'F', 'S', 'S'] as $weekday)
            <span class="pb-1 text-[9px] font-bold uppercase tracking-wider text-slate-400 {{ $large ? 'sm:text-[10px]' : '' }}">{{ $weekday }}</span>
        @endforeach
        @foreach ($days as $day)
            @php
                $hasDeadline = $day['events']->contains(fn (array $event) => $event['deadline']);
                $hasPersonal = $day['events']->contains(fn (array $event) => $event['kind'] === 'personal');
                $eventTone = $hasDeadline ? 'amber' : ($hasPersonal ? 'sky' : 'red');
                $eventClasses = match ($eventTone) {
                    'amber' => 'border-amber-200 bg-amber-50 text-amber-900 hover:bg-amber-100 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-100',
                    'sky' => 'border-sky-200 bg-sky-50 text-sky-900 hover:bg-sky-100 dark:border-sky-900 dark:bg-sky-950/30 dark:text-sky-100',
                    default => 'border-red-200 bg-red-50 text-[#7A0019] hover:bg-red-100 dark:border-red-900 dark:bg-red-950/30 dark:text-red-200',
                };
                $dotClasses = match ($eventTone) {
                    'amber' => 'bg-amber-500',
                    'sky' => 'bg-sky-500',
                    default => 'bg-[#7A0019]',
                };
            @endphp
            <button type="button" wire:click="selectDate('{{ $day['date'] }}')" aria-label="{{ $day['date'] }}, {{ $day['events']->count() }} events" aria-pressed="{{ $selectedDate === $day['date'] ? 'true' : 'false' }}" class="{{ $expanded ? 'min-h-24 rounded-xl border p-1.5' : ($large ? 'flex min-h-14 w-full flex-col items-center justify-center rounded-xl border p-1.5 sm:min-h-16' : 'mx-auto flex min-h-8 w-8 flex-col items-center justify-center rounded-lg border p-1') }} text-[11px] transition focus:outline-none focus:ring-2 focus:ring-red-400 focus:ring-offset-2 dark:focus:ring-offset-slate-950 {{ $selectedDate === $day['date'] ? 'border-[#7A0019] bg-[#7A0019] font-black text-white shadow-md shadow-red-950/20' : ($day['today'] ? 'border-red-300 bg-red-50 font-black text-[#7A0019] dark:border-red-800 dark:bg-red-950/40 dark:text-red-200' : ($day['events']->isNotEmpty() ? $eventClasses : ($day['current'] ? 'border-slate-100 bg-white text-slate-700 hover:border-slate-200 hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800' : 'border-transparent text-slate-300 dark:text-slate-600'))) }}">
                <span>{{ $day['number'] }}</span>
                @if ($expanded)
                    @foreach ($day['events']->take(2) as $dayEvent)
                        <span class="mt-1 w-full truncate rounded-lg bg-white/70 px-1 text-[9px] font-semibold text-slate-700">{{ $dayEvent['title'] }}</span>
                    @endforeach
                    @if ($day['events']->count() > 2)<span class="mt-0.5 text-[9px] font-bold">+{{ $day['events']->count() - 2 }} more</span>@endif
                @elseif ($large && $day['events']->isNotEmpty())
                    <span class="mt-1 max-w-full truncate rounded-md px-1 text-[8px] font-semibold {{ $selectedDate === $day['date'] ? 'bg-white/20 text-white' : ($eventTone === 'amber' ? 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-200' : ($eventTone === 'sky' ? 'bg-sky-100 text-sky-800 dark:bg-sky-950/60 dark:text-sky-200' : 'bg-red-100 text-[#7A0019] dark:bg-red-950/60 dark:text-red-200')) }}">{{ $day['events']->first()['title'] }}</span>
                    @if ($day['events']->count() > 1)<span class="mt-0.5 text-[8px] font-bold">+{{ $day['events']->count() - 1 }}</span>@endif
                @elseif ($day['events']->isNotEmpty())
                    <span aria-hidden="true" class="mt-0.5 h-1.5 w-1.5 rounded-full {{ $selectedDate === $day['date'] ? 'bg-white' : $dotClasses }}"></span>
                @endif
            </button>
        @endforeach
    </div>
</div>
