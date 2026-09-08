<section class="min-w-0 rounded-2xl border border-gray-200 bg-white p-5 text-gray-950 shadow-sm dark:border-gray-800 dark:bg-gray-950 dark:text-white" aria-label="Research calendar">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <div><h3 class="text-sm font-bold">Research calendar</h3><p class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400">{{ config('app.timezone') }} · Official dates and your reminders</p></div>
        <div class="flex items-center gap-2">
            <button type="button" wire:click="addReminder" class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800">Add reminder</button>
            <button type="button" x-data x-on:click="$dispatch('open-modal', 'calendar-expanded')" class="rounded-lg px-2 py-1.5 text-xs font-semibold text-[#7A0019] hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-950">Expand</button>
        </div>
    </div>
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
        <div class="min-w-0">
            <x-dashboard-calendar-grid :days="$days" :month-label="$monthLabel" :selected-date="$selectedDate" />
            <div class="mt-3 border-t border-gray-100 pt-3 dark:border-gray-800">
                <h4 class="text-xs font-semibold">{{ $selectedLabel }}</h4>
                <div class="max-h-36 overflow-y-auto"><x-dashboard-calendar-events :events="$selectedEvents" /></div>
            </div>
        </div>
        <div class="min-w-0 sm:border-l sm:border-gray-100 sm:pl-5 dark:sm:border-gray-800">
            <h4 class="mb-1 text-xs font-semibold">Upcoming deadlines &amp; reminders</h4>
            <x-dashboard-calendar-events :events="$upcoming" empty="No upcoming deadlines or reminders in the next year." />
        </div>
    </div>
    <x-modal name="calendar-expanded" maxWidth="4xl" focusable>
        <div role="dialog" aria-modal="true" aria-label="Expanded research calendar" class="p-5 text-gray-950 dark:text-white">
            <div class="mb-4 flex items-center justify-between"><h3 class="font-bold">Research calendar</h3><button type="button" x-on:click="$dispatch('close-modal', 'calendar-expanded')" class="rounded-lg px-3 py-2 text-sm">Close</button></div>
            <x-dashboard-calendar-grid :days="$days" :month-label="$monthLabel" :selected-date="$selectedDate" expanded />
            <h4 class="mt-5 text-sm font-semibold">{{ $selectedLabel }}</h4>
            <x-dashboard-calendar-events :events="$selectedEvents" />
        </div>
    </x-modal>
    <x-modal name="calendar-event" maxWidth="md" focusable>
        <div role="dialog" aria-modal="true" aria-label="Calendar event details" class="p-5 text-gray-950 dark:text-white">
            <div class="flex items-center justify-between"><p class="text-xs font-semibold text-gray-500">Event details</p><button type="button" x-on:click="$dispatch('close-modal', 'calendar-event')" class="rounded-lg px-3 py-2 text-xs">Close</button></div>
            @if ($event)
                <h3 class="mt-2 text-lg font-bold">{{ $event['title'] }}</h3>
                <p class="mt-1 text-sm">{{ $event['context'] }}</p>
                <p class="mt-3 text-sm font-semibold">{{ $event['display_at'] }}</p>
                <p class="mt-1 text-xs text-gray-500">{{ config('app.timezone') }}{{ $event['draft'] ? ' · Draft schedule' : '' }}</p>
                <p class="mt-4 whitespace-pre-line break-words text-sm text-gray-600 dark:text-gray-300">{{ $event['notes'] }}</p>
                @if ($event['url'])
                    <a href="{{ $event['url'] }}" class="mt-5 inline-flex rounded-lg bg-[#7A0019] px-4 py-2 text-sm font-semibold text-white">Open research call</a>
                @else
                    <div class="mt-5 flex gap-3"><button wire:click="editReminder({{ $event['reminder_id'] }})" type="button" class="rounded-lg bg-[#7A0019] px-4 py-2 text-sm font-semibold text-white">Edit reminder</button><button type="button" wire:click="deleteReminder({{ $event['reminder_id'] }})" wire:confirm="Delete this personal reminder?" class="px-3 py-2 text-sm text-red-700 dark:text-red-300">Delete</button></div>
                @endif
            @else
                <p class="py-4 text-sm">This event is no longer available. Select it again from the calendar.</p>
            @endif
        </div>
    </x-modal>
    <x-modal name="calendar-reminder" maxWidth="md" focusable>
        <form wire:submit="saveReminder" role="dialog" aria-modal="true" aria-label="Personal reminder" class="space-y-4 p-5 text-gray-950 dark:text-white">
            <div class="flex items-center justify-between"><h3 class="font-bold">{{ $editingId ? 'Edit reminder' : 'Add reminder' }}</h3><button type="button" x-on:click="$dispatch('close-modal', 'calendar-reminder')" class="px-2 py-1 text-xs">Cancel</button></div>
            <p class="text-xs text-gray-500">Only you can see this reminder. Times use {{ config('app.timezone') }}.</p>
            <label class="block text-sm">Title<input wire:model="title" maxlength="160" required class="mt-1 block w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900">@error('title')<span class="text-xs text-red-600">{{ $message }}</span>@enderror</label>
            <label class="block text-sm">Date and time<input type="datetime-local" wire:model="startsAt" required class="mt-1 block w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900">@error('startsAt')<span class="text-xs text-red-600">{{ $message }}</span>@enderror</label>
            <label class="block text-sm">Notes <span class="text-gray-500">(optional)</span><textarea wire:model="notes" maxlength="2000" rows="3" class="mt-1 block w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900"></textarea>@error('notes')<span class="text-xs text-red-600">{{ $message }}</span>@enderror</label>
            <button type="submit" wire:loading.attr="disabled" class="rounded-lg bg-[#7A0019] px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">Save reminder</button>
        </form>
    </x-modal>
</section>