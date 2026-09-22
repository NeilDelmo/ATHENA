@props(['events', 'empty' => 'No events on this date.'])
<div class="space-y-0.5">
    @forelse ($events as $item)
        @php
            $when = \Illuminate\Support\Carbon::parse($item['at']);
            $allDay = str_contains($item['display_at'], 'All day');
        @endphp
        <button type="button" wire:click="openEvent('{{ $item['id'] }}')" class="flex w-full items-start gap-2.5 rounded-md px-2 py-2 text-left transition hover:bg-slate-50">
            <x-date-chip :date="$when" compact :weekday="! $allDay" :time="! $allDay" relative :tone="$item['kind'] === 'personal' ? 'stone' : 'rose'" />
            <span class="min-w-0 pt-0.5">
                <span class="block truncate text-xs font-semibold text-slate-900">{{ $item['title'] }} @if ($item['draft'])<span class="font-normal text-slate-500">(Draft)</span>@endif</span>
                <span class="mt-0.5 block truncate text-[10px] text-slate-500">{{ $item['context'] }}@if ($allDay) · All day @endif</span>
            </span>
        </button>
    @empty
        <p class="rounded-md border border-dashed border-slate-200 bg-slate-50 py-3 text-center text-xs text-slate-500">{{ $empty }}</p>
    @endforelse
</div>
