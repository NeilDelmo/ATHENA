@props(['events', 'empty' => 'No events on this date.', 'monochrome' => false])
<div class="space-y-0.5">
    @forelse ($events as $item)
        @php
            $when = \Illuminate\Support\Carbon::parse($item['at']);
            $allDay = str_contains($item['display_at'], 'All day');
            $tone = $item['kind'] === 'personal' ? 'blue' : ($item['deadline'] ? 'amber' : 'rose');
            $accent = $item['kind'] === 'personal'
                ? 'border-sky-100 bg-sky-50/55 hover:border-sky-200 hover:bg-sky-50 dark:border-sky-950/70 dark:bg-sky-950/20 dark:hover:bg-sky-950/30'
                : ($item['deadline']
                    ? 'border-amber-100 bg-amber-50/55 hover:border-amber-200 hover:bg-amber-50 dark:border-amber-950/70 dark:bg-amber-950/20 dark:hover:bg-amber-950/30'
                    : 'border-red-100 bg-red-50/45 hover:border-red-200 hover:bg-red-50 dark:border-red-950/70 dark:bg-red-950/20 dark:hover:bg-red-950/30');
            $label = $item['kind'] === 'personal' ? 'Personal reminder' : ($item['deadline'] ? 'Deadline' : 'Official date');
            if ($monochrome) {
                $tone = 'rose';
                $accent = 'border-slate-100 bg-white hover:border-red-100 hover:bg-red-50/40 dark:border-slate-800 dark:bg-slate-950 dark:hover:border-red-950';
            }
        @endphp
        <button type="button" wire:click="openEvent('{{ $item['id'] }}')" class="group flex w-full items-start gap-3 rounded-xl border px-3 py-2.5 text-left shadow-sm transition {{ $accent }}">
            <x-date-chip :date="$when" compact :weekday="! $monochrome && ! $allDay" :time="! $monochrome && ! $allDay" :relative="! $monochrome" :tone="$tone" />
            <span class="min-w-0 flex-1 pt-0.5">
                <span class="flex items-center gap-2">
                    <span class="block truncate text-xs font-bold text-slate-900 dark:text-white">{{ $item['title'] }}</span>
                    @if ($item['draft'])<span class="shrink-0 text-[10px] font-semibold text-slate-500">Draft</span>@endif
                </span>
                <span class="mt-0.5 block truncate text-[11px] text-slate-500 dark:text-slate-400">{{ $item['context'] }}@if ($allDay) · All day @endif</span>
                @if ($monochrome)<span class="mt-1 block text-[10px] font-medium text-[#7A0019] dark:text-red-300">{{ $label }}@unless ($allDay) · {{ $when->format('g:i A') }}@endunless</span>@endif
            </span>
            <span class="mt-1 h-2 w-2 shrink-0 rounded-full {{ $tone === 'blue' ? 'bg-sky-500' : ($tone === 'amber' ? 'bg-amber-500' : 'bg-[#7A0019]') }}" aria-label="{{ $label }}"></span>
        </button>
    @empty
        <p class="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-3 py-4 text-center text-xs text-slate-500 dark:border-slate-700 dark:bg-slate-900/60 dark:text-slate-400">{{ $empty }}</p>
    @endforelse
</div>
