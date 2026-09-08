@props(['days', 'monthLabel', 'selectedDate', 'expanded' => false])
<div>
    <div class="mb-3 flex items-center justify-between gap-2">
        <h4 class="text-sm font-semibold">{{ $monthLabel }}</h4>
        <div class="flex items-center gap-1">
            <button type="button" wire:click="moveMonth(-1)" aria-label="Previous month" class="rounded-lg px-2.5 py-1.5 hover:bg-gray-100 dark:hover:bg-gray-800">&lsaquo;</button>
            <button type="button" wire:click="today" class="rounded-lg px-2 py-1.5 text-xs hover:bg-gray-100 dark:hover:bg-gray-800">Today</button>
            <button type="button" wire:click="moveMonth(1)" aria-label="Next month" class="rounded-lg px-2.5 py-1.5 hover:bg-gray-100 dark:hover:bg-gray-800">&rsaquo;</button>
        </div>
    </div>
    <div class="grid grid-cols-7 gap-1 text-center">
        @foreach (['M', 'T', 'W', 'T', 'F', 'S', 'S'] as $weekday)
            <span class="pb-1 text-[10px] font-semibold text-gray-500 dark:text-gray-400">{{ $weekday }}</span>
        @endforeach
        @foreach ($days as $day)
            <button type="button" wire:click="selectDate('{{ $day['date'] }}')" aria-label="{{ $day['date'] }}, {{ $day['events']->count() }} events" aria-pressed="{{ $selectedDate === $day['date'] ? 'true' : 'false' }}" class="flex min-w-0 flex-col items-center rounded-lg p-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-red-700 {{ $expanded ? 'min-h-24' : 'min-h-9' }} {{ $selectedDate === $day['date'] ? 'bg-[#7A0019] text-white' : ($day['today'] ? 'bg-red-50 font-bold text-[#7A0019] dark:bg-red-950 dark:text-red-200' : ($day['current'] ? 'hover:bg-gray-100 dark:hover:bg-gray-800' : 'text-gray-400 dark:text-gray-600')) }}">
                <span>{{ $day['number'] }}</span>
                @if ($expanded)
                    @foreach ($day['events']->take(2) as $dayEvent)
                        <span class="mt-1 w-full truncate text-[10px]">{{ $dayEvent['title'] }}</span>
                    @endforeach
                    @if ($day['events']->count() > 2)<span class="text-[10px]">+{{ $day['events']->count() - 2 }} more</span>@endif
                @elseif ($day['events']->isNotEmpty())
                    <span aria-hidden="true" class="mt-1 h-1 w-1 rounded-full bg-current"></span>
                @endif
            </button>
        @endforeach
    </div>
</div>