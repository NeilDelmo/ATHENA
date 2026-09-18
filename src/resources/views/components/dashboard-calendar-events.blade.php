@props(['events', 'empty' => 'No events on this date.'])
<div class="space-y-1">
    @forelse ($events as $item)
        @php
            $when = \Illuminate\Support\Carbon::parse($item['at']);
            $allDay = str_contains($item['display_at'], 'All day');
        @endphp
        <button type="button" wire:click="openEvent('{{ $item['id'] }}')" class="flex w-full items-start gap-3 rounded-2xl px-2 py-2 text-left transition hover:bg-rose-50/70 dark:hover:bg-red-950/20">
            <x-date-chip :date="$when" compact :weekday="! $allDay" :time="! $allDay" relative :tone="$item['kind'] === 'personal' ? 'stone' : 'rose'" />
            <span class="min-w-0 pt-0.5">
                <span class="block truncate text-xs font-bold">{{ $item['title'] }} @if($item['draft'])<span class="font-medium text-gray-500">(Draft)</span>@endif</span>
                <span class="mt-0.5 block truncate text-[11px] font-medium text-gray-500 dark:text-gray-400">{{ $item['context'] }}@if($allDay) · All day @endif</span>
            </span>
        </button>
    @empty
        <p class="rounded-2xl bg-rose-50/60 py-3 text-center text-xs font-semibold text-gray-500 dark:bg-red-950/20">{{ $empty }}</p>
    @endforelse
</div>
