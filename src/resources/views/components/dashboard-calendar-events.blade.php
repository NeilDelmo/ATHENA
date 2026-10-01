@props(['events', 'empty' => 'No events on this date.', 'monochrome' => false])
<div class="space-y-3" data-calendar-events>
    @forelse ($events as $item)
        @php
            $when = \Illuminate\Support\Carbon::parse($item['at']);
            $allDay = str_contains($item['display_at'], 'All day');
            $label = $item['kind'] === 'personal' ? 'Personal reminder' : ($item['deadline'] ? 'Deadline' : 'Official date');
        @endphp
        <button type="button" wire:click="openEvent('{{ $item['id'] }}')" class="group flex w-full min-w-0 items-start gap-3 rounded-lg border border-slate-200 bg-white p-3 text-left hover:border-red-300 hover:bg-red-50/30 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand dark:border-slate-700 dark:bg-slate-950 dark:hover:border-red-800 dark:hover:bg-red-950/20">
            <span class="flex w-12 shrink-0 flex-col overflow-hidden rounded-md border border-slate-200 text-center dark:border-slate-700" aria-label="{{ $when->format('M j, Y') }}">
                <span class="bg-brand py-1 text-sm font-medium text-white">{{ $when->format('M') }}</span>
                <span class="bg-white py-1.5 text-xl font-semibold tabular-nums text-slate-900 dark:bg-slate-900 dark:text-white">{{ $when->format('j') }}</span>
            </span>
            <span class="min-w-0 flex-1">
                <span class="block break-words text-base font-semibold leading-6 text-slate-900 group-hover:text-brand dark:text-white dark:group-hover:text-red-300">{{ $item['title'] }}</span>
                <span class="mt-1 block break-words text-sm leading-5 text-slate-500 dark:text-slate-400">{{ $item['context'] }}</span>
                <span class="mt-2 block text-sm leading-5 text-slate-600 dark:text-slate-300">{{ $allDay ? 'All day' : $when->format('D · g:i A') }} · {{ $when->diffForHumans(short: true) }}</span>
                <span class="mt-2 inline-flex rounded border border-slate-200 px-2 py-0.5 text-sm font-medium text-brand dark:border-slate-700 dark:text-red-300">{{ $label }}@if ($item['draft']) · Draft @endif</span>
            </span>
        </button>
    @empty
        <p class="rounded-lg border border-slate-200 bg-white px-4 py-5 text-sm leading-6 text-slate-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-400">{{ $empty }}</p>
    @endforelse
</div>
