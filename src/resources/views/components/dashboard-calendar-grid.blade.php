@props(['days', 'monthLabel', 'selectedDate', 'expanded' => false, 'large' => false])
<div>
    <div class="mb-3 flex items-center justify-between gap-2">
        <h4 class="text-sm font-bold tracking-tight text-slate-900">{{ $monthLabel }}</h4>
        <div class="flex items-center gap-1">
            <button type="button" wire:click="moveMonth(-1)" aria-label="Previous month" class="rounded border border-slate-200 bg-white px-2 py-1 text-sm font-bold text-slate-500 hover:border-[#800000] hover:text-[#800000]">&lsaquo;</button>
            <button type="button" wire:click="today" class="rounded border border-slate-200 bg-white px-2.5 py-1 text-[10px] font-semibold text-slate-600 hover:border-[#800000] hover:text-[#800000]">Today</button>
            <button type="button" wire:click="moveMonth(1)" aria-label="Next month" class="rounded border border-slate-200 bg-white px-2 py-1 text-sm font-bold text-slate-500 hover:border-[#800000] hover:text-[#800000]">&rsaquo;</button>
        </div>
    </div>
    <div class="grid grid-cols-7 {{ $large ? 'gap-1.5' : 'gap-1' }} text-center">
        @foreach (['M', 'T', 'W', 'T', 'F', 'S', 'S'] as $weekday)
            <span class="pb-1 text-[9px] font-bold uppercase tracking-wider text-slate-400 {{ $large ? 'sm:text-[10px]' : '' }}">{{ $weekday }}</span>
        @endforeach
        @foreach ($days as $day)
            <button type="button" wire:click="selectDate('{{ $day['date'] }}')" aria-label="{{ $day['date'] }}, {{ $day['events']->count() }} events" aria-pressed="{{ $selectedDate === $day['date'] ? 'true' : 'false' }}" class="{{ $expanded ? 'min-h-24 rounded-md border border-slate-100 p-1.5' : ($large ? 'flex min-h-14 w-full flex-col items-center justify-center rounded-md border border-slate-100 p-1.5 sm:min-h-16' : 'mx-auto flex min-h-8 w-8 flex-col items-center justify-center rounded p-1') }} text-[11px] transition focus:outline-none focus:ring-2 focus:ring-rose-400 {{ $selectedDate === $day['date'] ? 'bg-[#800000] font-bold text-white shadow-sm' : ($day['today'] ? 'border border-rose-200 bg-rose-50 font-bold text-[#800000]' : ($day['current'] ? 'text-slate-700 hover:bg-slate-100' : 'text-slate-300')) }}">
                <span>{{ $day['number'] }}</span>
                @if ($expanded)
                    @foreach ($day['events']->take(2) as $dayEvent)
                        <span class="mt-1 w-full truncate rounded bg-white/70 px-1 text-[9px] font-semibold">{{ $dayEvent['title'] }}</span>
                    @endforeach
                    @if ($day['events']->count() > 2)<span class="mt-0.5 text-[9px] font-bold">+{{ $day['events']->count() - 2 }} more</span>@endif
                @elseif ($large && $day['events']->isNotEmpty())
                    <span class="mt-1 max-w-full truncate rounded px-1 text-[8px] font-semibold {{ $selectedDate === $day['date'] ? 'bg-white/20 text-white' : 'bg-rose-50 text-[#800000]' }}">{{ $day['events']->first()['title'] }}</span>
                    @if ($day['events']->count() > 1)<span class="mt-0.5 text-[8px] font-bold">+{{ $day['events']->count() - 1 }}</span>@endif
                @elseif ($day['events']->isNotEmpty())
                    <span aria-hidden="true" class="mt-0.5 h-1 w-1 rounded-full {{ $selectedDate === $day['date'] ? 'bg-white' : 'bg-[#800000]' }}"></span>
                @endif
            </button>
        @endforeach
    </div>
</div>
