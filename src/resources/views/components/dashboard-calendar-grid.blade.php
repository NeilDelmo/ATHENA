@props(['days', 'monthLabel', 'selectedDate', 'expanded' => false])
<div>
    <div class="mb-3 flex items-center justify-between gap-2">
        <h4 class="text-sm font-black tracking-tight">{{ $monthLabel }}</h4>
        <div class="flex items-center gap-1">
            <button type="button" wire:click="moveMonth(-1)" aria-label="Previous month" class="rounded-full px-2.5 py-1.5 font-black text-gray-500 transition hover:bg-rose-50 hover:text-[#7A0019] dark:text-gray-400 dark:hover:bg-red-950/40 dark:hover:text-red-300">&lsaquo;</button>
            <button type="button" wire:click="today" class="rounded-full px-3 py-1.5 text-xs font-black text-gray-500 transition hover:bg-rose-50 hover:text-[#7A0019] dark:text-gray-400 dark:hover:bg-red-950/40 dark:hover:text-red-300">Today</button>
            <button type="button" wire:click="moveMonth(1)" aria-label="Next month" class="rounded-full px-2.5 py-1.5 font-black text-gray-500 transition hover:bg-rose-50 hover:text-[#7A0019] dark:text-gray-400 dark:hover:bg-red-950/40 dark:hover:text-red-300">&rsaquo;</button>
        </div>
    </div>
    <div class="grid grid-cols-7 gap-1 text-center">
        @foreach (['M', 'T', 'W', 'T', 'F', 'S', 'S'] as $weekday)
            <span class="pb-1 text-[10px] font-black uppercase tracking-wider text-gray-400 dark:text-gray-500">{{ $weekday }}</span>
        @endforeach
        @foreach ($days as $day)
            <button type="button" wire:click="selectDate('{{ $day['date'] }}')" aria-label="{{ $day['date'] }}, {{ $day['events']->count() }} events" aria-pressed="{{ $selectedDate === $day['date'] ? 'true' : 'false' }}" class="{{ $expanded ? 'min-h-24 rounded-2xl p-1.5' : 'mx-auto flex min-h-9 w-9 flex-col items-center justify-center rounded-full p-1.5' }} text-xs transition focus:outline-none focus:ring-2 focus:ring-red-500 {{ $selectedDate === $day['date'] ? 'bg-gradient-to-br from-[#7A0019] to-rose-600 text-white shadow-bubble-sm' : ($day['today'] ? 'bg-rose-50 font-black text-[#7A0019] ring-2 ring-rose-200 dark:bg-red-950/60 dark:text-red-200 dark:ring-red-900' : ($day['current'] ? 'hover:bg-rose-50 hover:text-[#7A0019] dark:hover:bg-red-950/40 dark:hover:text-red-300' : 'text-gray-400 dark:text-gray-600')) }}">
                <span class="{{ $selectedDate === $day['date'] ? 'font-black' : '' }}">{{ $day['number'] }}</span>
                @if ($expanded)
                    @foreach ($day['events']->take(2) as $dayEvent)
                        <span class="mt-1 w-full truncate rounded-full bg-white/60 px-1 text-[9px] font-semibold dark:bg-white/10">{{ $dayEvent['title'] }}</span>
                    @endforeach
                    @if ($day['events']->count() > 2)<span class="mt-0.5 text-[9px] font-bold">+{{ $day['events']->count() - 2 }} more</span>@endif
                @elseif ($day['events']->isNotEmpty())
                    <span aria-hidden="true" class="mt-0.5 h-1.5 w-1.5 rounded-full {{ $selectedDate === $day['date'] ? 'bg-white' : 'bg-[#7A0019] dark:bg-red-400' }}"></span>
                @endif
            </button>
        @endforeach
    </div>
</div>
