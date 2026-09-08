@props(['events', 'empty' => 'No events on this date.'])
<div class="divide-y divide-gray-100 dark:divide-gray-800">
    @forelse ($events as $item)
        <button type="button" wire:click="openEvent('{{ $item['id'] }}')" class="flex w-full gap-3 rounded-lg py-2.5 text-left hover:bg-gray-50 dark:hover:bg-gray-900">
            <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full {{ $item['kind'] === 'personal' ? 'bg-gray-400' : 'bg-[#7A0019] dark:bg-red-400' }}" aria-hidden="true"></span>
            <span class="min-w-0">
                <span class="block text-xs font-semibold">{{ $item['title'] }} @if($item['draft'])<span class="font-normal text-gray-500">(Draft)</span>@endif</span>
                <span class="mt-0.5 block truncate text-xs text-gray-500 dark:text-gray-400">{{ $item['context'] }}</span>
                <span class="mt-1 block text-[11px] text-gray-500 dark:text-gray-400">{{ $item['display_at'] }}</span>
            </span>
        </button>
    @empty
        <p class="py-3 text-xs text-gray-500 dark:text-gray-400">{{ $empty }}</p>
    @endforelse
</div>