<section class="min-w-0 rounded-3xl border border-rose-100 bg-white p-5 text-gray-950 shadow-bubble dark:border-red-950/70 dark:bg-slate-950 dark:text-white" aria-label="Research calendar">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <div class="flex items-center gap-2">
            <span class="inline-flex h-8 w-8 items-center justify-center rounded-2xl bg-rose-50 text-[#7A0019] dark:bg-red-950/60 dark:text-red-300"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" /></svg></span>
            <div>
                <h3 class="text-sm font-black tracking-tight">Research calendar</h3>
                <p class="mt-0.5 text-[11px] font-medium text-gray-500 dark:text-gray-400">{{ config('app.timezone') }} · Official dates and your reminders</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" wire:click="addReminder" class="rounded-full border border-rose-200 bg-white px-3.5 py-1.5 text-xs font-black text-gray-700 transition hover:border-[#7A0019] hover:text-[#7A0019] dark:border-red-950 dark:bg-slate-950 dark:text-gray-200 dark:hover:border-red-500 dark:hover:text-red-300">Add reminder</button>
            <button type="button" x-data x-on:click="$dispatch('open-modal', 'calendar-expanded')" class="rounded-full bg-rose-50 px-3.5 py-1.5 text-xs font-black text-[#7A0019] transition hover:bg-rose-100 dark:bg-red-950/60 dark:text-red-300 dark:hover:bg-red-950">Expand</button>
        </div>
    </div>
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
        <div class="min-w-0">
            <x-dashboard-calendar-grid :days="$days" :month-label="$monthLabel" :selected-date="$selectedDate" />
            <div class="mt-3 border-t border-rose-100 pt-3 dark:border-red-950/70">
                <h4 class="text-xs font-black uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ $selectedLabel }}</h4>
                <div class="max-h-36 overflow-y-auto"><x-dashboard-calendar-events :events="$selectedEvents" /></div>
            </div>
        </div>
        <div class="min-w-0 sm:border-l sm:border-rose-100 sm:pl-5 dark:sm:border-red-950/70">
            <h4 class="mb-1 text-xs font-black uppercase tracking-wider text-gray-500 dark:text-gray-400">Upcoming deadlines &amp; reminders</h4>
            <x-dashboard-calendar-events :events="$upcoming" empty="No upcoming deadlines or reminders in the next year." />
        </div>
    </div>
    <x-modal name="calendar-expanded" maxWidth="4xl" focusable>
        <div role="dialog" aria-modal="true" aria-label="Expanded research calendar" class="p-5 text-gray-950 dark:text-white">
            <div class="mb-4 flex items-center justify-between"><h3 class="font-black tracking-tight">Research calendar</h3><button type="button" x-on:click="$dispatch('close-modal', 'calendar-expanded')" class="rounded-full bg-rose-50 px-4 py-2 text-xs font-black text-[#7A0019] transition hover:bg-rose-100 dark:bg-red-950/60 dark:text-red-300">Close</button></div>
            <x-dashboard-calendar-grid :days="$days" :month-label="$monthLabel" :selected-date="$selectedDate" expanded />
            <h4 class="mt-5 text-sm font-black">{{ $selectedLabel }}</h4>
            <x-dashboard-calendar-events :events="$selectedEvents" />
        </div>
    </x-modal>
    <x-modal name="calendar-event" maxWidth="md" focusable>
        <div role="dialog" aria-modal="true" aria-label="Calendar event details" class="p-5 text-gray-950 dark:text-white">
            <div class="flex items-center justify-between"><p class="text-xs font-black uppercase tracking-wider text-gray-500">Event details</p><button type="button" x-on:click="$dispatch('close-modal', 'calendar-event')" class="rounded-full bg-rose-50 px-3.5 py-1.5 text-xs font-black text-[#7A0019] dark:bg-red-950/60 dark:text-red-300">Close</button></div>
            @if ($event)
                <h3 class="mt-2 text-lg font-black tracking-tight">{{ $event['title'] }}</h3>
                <p class="mt-1 text-sm">{{ $event['context'] }}</p>
                <p class="mt-3"><x-date-chip :date="\Illuminate\Support\Carbon::parse($event['at'])" weekday time relative /></p>
                <p class="mt-1.5 text-xs font-medium text-gray-500">{{ config('app.timezone') }}{{ $event['draft'] ? ' · Draft schedule' : '' }}</p>
                <p class="mt-4 whitespace-pre-line break-words text-sm text-gray-600 dark:text-gray-300">{{ $event['notes'] }}</p>
                @if ($event['url'])
                    <a href="{{ $event['url'] }}" class="mt-5 inline-flex rounded-full bg-[#7A0019] px-5 py-2.5 text-sm font-black text-white shadow-bubble-sm transition hover:bg-red-800">Open research call</a>
                @else
                    <div class="mt-5 flex gap-3"><button wire:click="editReminder({{ $event['reminder_id'] }})" type="button" class="rounded-full bg-[#7A0019] px-5 py-2.5 text-sm font-black text-white shadow-bubble-sm transition hover:bg-red-800">Edit reminder</button><button type="button" wire:click="deleteReminder({{ $event['reminder_id'] }})" wire:confirm="Delete this personal reminder?" class="rounded-full px-4 py-2.5 text-sm font-bold text-red-700 transition hover:bg-rose-50 dark:text-red-300 dark:hover:bg-red-950/40">Delete</button></div>
                @endif
            @else
                <p class="py-4 text-sm">This event is no longer available. Select it again from the calendar.</p>
            @endif
        </div>
    </x-modal>
    <x-modal name="calendar-reminder" maxWidth="md" focusable>
        <form wire:submit="saveReminder" role="dialog" aria-modal="true" aria-label="Personal reminder" class="space-y-4 p-5 text-gray-950 dark:text-white">
            <div class="flex items-center justify-between"><h3 class="font-black tracking-tight">{{ $editingId ? 'Edit reminder' : 'Add reminder' }}</h3><button type="button" x-on:click="$dispatch('close-modal', 'calendar-reminder')" class="rounded-full px-3 py-1.5 text-xs font-black text-gray-500 hover:bg-rose-50 dark:hover:bg-red-950/40">Cancel</button></div>
            <p class="text-xs font-medium text-gray-500">Only you can see this reminder. Times use {{ config('app.timezone') }}.</p>
            <label class="block text-sm">Title<input wire:model="title" maxlength="160" required class="mt-1 block w-full rounded-2xl border-rose-100 text-sm focus:border-[#7A0019] focus:ring-[#7A0019] dark:border-red-950 dark:bg-slate-900">@error('title')<span class="text-xs text-red-600">{{ $message }}</span>@enderror</label>
            <label class="block text-sm">Date and time<input type="datetime-local" wire:model="startsAt" required class="mt-1 block w-full rounded-2xl border-rose-100 text-sm focus:border-[#7A0019] focus:ring-[#7A0019] dark:border-red-950 dark:bg-slate-900">@error('startsAt')<span class="text-xs text-red-600">{{ $message }}</span>@enderror</label>
            <label class="block text-sm">Notes <span class="text-gray-500">(optional)</span><textarea wire:model="notes" maxlength="2000" rows="3" class="mt-1 block w-full rounded-2xl border-rose-100 text-sm focus:border-[#7A0019] focus:ring-[#7A0019] dark:border-red-950 dark:bg-slate-900"></textarea>@error('notes')<span class="text-xs text-red-600">{{ $message }}</span>@enderror</label>
            <button type="submit" wire:loading.attr="disabled" class="rounded-full bg-[#7A0019] px-5 py-2.5 text-sm font-black text-white shadow-bubble-sm transition hover:bg-red-800 disabled:opacity-50">Save reminder</button>
        </form>
    </x-modal>
</section>
