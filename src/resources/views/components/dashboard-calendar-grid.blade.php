@props(['days', 'monthLabel', 'selectedDate', 'expanded' => false, 'large' => false, 'monochrome' => false])
<div>
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h4 class="text-xl font-semibold tracking-tight text-slate-900 dark:text-white">{{ $monthLabel }}</h4>
        <div class="flex items-center gap-1 rounded-lg border border-slate-200 bg-white p-1 dark:border-slate-700 dark:bg-slate-900">
            <button type="button" wire:click="moveMonth(-1)" aria-label="Previous month" class="inline-flex h-11 w-11 items-center justify-center rounded-md text-2xl text-slate-600 hover:bg-red-50 hover:text-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand dark:text-slate-300 dark:hover:bg-red-950/30">&lsaquo;</button>
            <button type="button" wire:click="today" class="min-h-11 rounded-md px-3 text-sm font-semibold text-slate-700 hover:bg-red-50 hover:text-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand dark:text-slate-300 dark:hover:bg-red-950/30">Today</button>
            <button type="button" wire:click="moveMonth(1)" aria-label="Next month" class="inline-flex h-11 w-11 items-center justify-center rounded-md text-2xl text-slate-600 hover:bg-red-50 hover:text-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand dark:text-slate-300 dark:hover:bg-red-950/30">&rsaquo;</button>
        </div>
    </div>
    <div class="overflow-hidden rounded-lg border border-slate-200 dark:border-slate-700">
        <div class="grid grid-cols-7 bg-brand text-center text-sm font-medium text-white">
            @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $weekday)
                <span class="py-3">{{ $weekday }}</span>
            @endforeach
        </div>
        <div class="grid grid-cols-7">
            @foreach ($days as $day)
                <button type="button" wire:click="selectDate('{{ $day['date'] }}')" aria-label="{{ $day['date'] }}, {{ $day['events']->count() }} events" aria-pressed="{{ $selectedDate === $day['date'] ? 'true' : 'false' }}" aria-current="{{ $day['today'] ? 'date' : 'false' }}" class="flex min-w-0 flex-col items-center border-b border-r border-slate-200 bg-white p-1.5 text-base hover:bg-slate-50 focus-visible:z-10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand dark:border-slate-700 dark:bg-slate-950 dark:hover:bg-slate-900 {{ $large || $expanded ? 'min-h-20 sm:min-h-28 sm:items-start sm:p-2' : 'min-h-12 justify-center' }} {{ $selectedDate === $day['date'] ? 'ring-2 ring-inset ring-brand dark:ring-red-400' : '' }} {{ $day['current'] ? 'text-slate-900 dark:text-slate-100' : 'text-slate-400 dark:text-slate-500' }}">
                    <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full tabular-nums {{ $day['today'] ? 'bg-brand font-semibold text-white' : '' }}">{{ $day['number'] }}</span>
                    @if (($large || $expanded) && $day['events']->isNotEmpty())
                        @foreach ($day['events']->take(2) as $dayEvent)
                            <span title="{{ $dayEvent['title'] }}" class="mt-1 hidden w-full truncate rounded-sm border-l-2 border-brand bg-red-50 px-1.5 py-1 text-left text-sm font-medium text-brand dark:border-red-400 dark:bg-red-950/30 dark:text-red-200 sm:block">{{ $dayEvent['title'] }}</span>
                        @endforeach
                        <span class="mt-1 flex items-center gap-1 text-sm text-brand dark:text-red-300 sm:hidden"><span class="h-1.5 w-1.5 rounded-full bg-brand dark:bg-red-400" aria-hidden="true"></span>{{ $day['events']->count() }}</span>
                        @if ($day['events']->count() > 2)
                            <span class="mt-1 hidden text-sm text-slate-500 dark:text-slate-400 sm:block">+{{ $day['events']->count() - 2 }} more</span>
                        @endif
                    @elseif ($day['events']->isNotEmpty())
                        <span aria-hidden="true" class="mt-1 h-1.5 w-1.5 rounded-full bg-brand dark:bg-red-400"></span>
                    @endif
                </button>
            @endforeach
        </div>
    </div>
</div>
