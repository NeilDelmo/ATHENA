<section class="min-w-0 overflow-hidden rounded-lg border border-slate-200 bg-white text-slate-900 shadow-sm" aria-label="Research calendar" data-calendar-palette="maroon-slate-white">
    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 bg-slate-50/80 px-4 py-3">
        <div>
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-900">Research calendar</h3>
            <p class="mt-0.5 text-[10px] text-slate-500">Official dates and personal reminders · {{ config('app.timezone') }}</p>
        </div>
        <div class="flex items-center gap-1.5">
            <button type="button" wire:click="addReminder" class="rounded-md border border-slate-200 bg-white px-2.5 py-1.5 text-[10px] font-semibold text-[#800000] hover:border-[#800000]">Add reminder</button>
            <button type="button" x-data x-on:click="$dispatch('open-modal', 'calendar-expanded')" class="rounded-md bg-[#800000] px-2.5 py-1.5 text-[10px] font-semibold text-white hover:bg-rose-900">Expand</button>
        </div>
    </div>

    <div class="grid gap-5 p-4 lg:grid-cols-[minmax(0,1.65fr)_minmax(18rem,0.75fr)] lg:p-5">
        <div class="min-w-0">
            <x-dashboard-calendar-grid :days="$days" :month-label="$monthLabel" :selected-date="$selectedDate" large />

            <div class="mt-5 border-t border-slate-100 pt-4">
                <h4 class="text-[10px] font-bold uppercase tracking-wider text-slate-500">{{ $selectedLabel }}</h4>
                <div class="mt-1"><x-dashboard-calendar-events :events="$selectedEvents" /></div>
            </div>
        </div>

        <div class="min-w-0 rounded-lg border border-slate-200 bg-slate-50/70 p-4 lg:border-y-0 lg:border-r-0 lg:border-l lg:bg-transparent lg:p-0 lg:pl-5">
            <div class="mb-3">
                <p class="text-[10px] font-bold uppercase tracking-wider text-[#800000]">Schedule overview</p>
                <h4 class="mt-0.5 text-sm font-bold text-slate-900">Upcoming dates</h4>
                <p class="mt-1 text-xs leading-5 text-slate-500">Deadlines, review schedules, and your personal reminders.</p>
            </div>
            <div class="max-h-[31rem] overflow-y-auto pr-1"><x-dashboard-calendar-events :events="$upcoming" empty="No upcoming deadlines or reminders in the next year." /></div>
        </div>
    </div>

    <x-modal name="calendar-expanded" maxWidth="4xl" focusable>
        <div role="dialog" aria-modal="true" aria-label="Expanded research calendar" class="bg-white p-5 text-slate-900">
            <div class="mb-4 flex items-center justify-between border-b border-slate-200 pb-3">
                <div><p class="text-[10px] font-bold uppercase tracking-[0.18em] text-[#800000]">Research operations</p><h3 class="mt-0.5 font-bold">Research calendar</h3></div>
                <button type="button" x-on:click="$dispatch('close-modal', 'calendar-expanded')" class="rounded-md border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 hover:border-[#800000] hover:text-[#800000]">Close</button>
            </div>
            <x-dashboard-calendar-grid :days="$days" :month-label="$monthLabel" :selected-date="$selectedDate" expanded />
            <h4 class="mt-5 border-t border-slate-200 pt-4 text-xs font-bold uppercase tracking-wider text-slate-600">{{ $selectedLabel }}</h4>
            <x-dashboard-calendar-events :events="$selectedEvents" />
        </div>
    </x-modal>

    <x-modal name="calendar-event" maxWidth="md" focusable>
        <div role="dialog" aria-modal="true" aria-label="Calendar event details" class="bg-white p-5 text-slate-900">
            <div class="flex items-center justify-between border-b border-slate-200 pb-3"><p class="text-[10px] font-bold uppercase tracking-wider text-[#800000]">Event details</p><button type="button" x-on:click="$dispatch('close-modal', 'calendar-event')" class="rounded-md border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600">Close</button></div>
            @if ($event)
                <h3 class="mt-4 text-lg font-bold tracking-tight">{{ $event['title'] }}</h3>
                <p class="mt-1 text-sm text-slate-600">{{ $event['context'] }}</p>
                <p class="mt-3"><x-date-chip :date="\Illuminate\Support\Carbon::parse($event['at'])" weekday time relative /></p>
                <p class="mt-1.5 text-xs text-slate-500">{{ config('app.timezone') }}{{ $event['draft'] ? ' · Draft schedule' : '' }}</p>
                <p class="mt-4 whitespace-pre-line break-words text-sm text-slate-600">{{ $event['notes'] }}</p>
                @if ($event['url'])
                    <a href="{{ $event['url'] }}" class="mt-5 inline-flex rounded-md bg-[#800000] px-4 py-2.5 text-sm font-semibold text-white hover:bg-rose-900">Open research call</a>
                @else
                    <div class="mt-5 flex gap-2"><button wire:click="editReminder({{ $event['reminder_id'] }})" type="button" class="rounded-md bg-[#800000] px-4 py-2.5 text-sm font-semibold text-white hover:bg-rose-900">Edit reminder</button><button type="button" wire:click="deleteReminder({{ $event['reminder_id'] }})" wire:confirm="Delete this personal reminder?" class="rounded-md border border-rose-200 px-4 py-2.5 text-sm font-semibold text-rose-700 hover:bg-rose-50">Delete</button></div>
                @endif
            @else
                <p class="py-4 text-sm text-slate-600">This event is no longer available. Select it again from the calendar.</p>
            @endif
        </div>
    </x-modal>

    <x-modal name="calendar-reminder" maxWidth="md" focusable>
        <form wire:submit="saveReminder" role="dialog" aria-modal="true" aria-label="Personal reminder" class="space-y-4 bg-white p-5 text-slate-900">
            <div class="flex items-center justify-between border-b border-slate-200 pb-3"><div><p class="text-[10px] font-bold uppercase tracking-wider text-[#800000]">Personal schedule</p><h3 class="mt-0.5 font-bold">{{ $editingId ? 'Edit reminder' : 'Add reminder' }}</h3></div><button type="button" x-on:click="$dispatch('close-modal', 'calendar-reminder')" class="rounded-md border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600">Cancel</button></div>
            <p class="text-xs text-slate-500">Only you can see this reminder. Times use {{ config('app.timezone') }}.</p>
            <label class="block text-sm font-semibold text-slate-700">Title<input wire:model="title" maxlength="160" required class="mt-1 block w-full rounded-md border-slate-200 text-sm focus:border-[#800000] focus:ring-[#800000]">@error('title')<span class="text-xs text-red-600">{{ $message }}</span>@enderror</label>
            <label class="block text-sm font-semibold text-slate-700">Date and time<input type="datetime-local" wire:model="startsAt" required class="mt-1 block w-full rounded-md border-slate-200 text-sm focus:border-[#800000] focus:ring-[#800000]">@error('startsAt')<span class="text-xs text-red-600">{{ $message }}</span>@enderror</label>
            <label class="block text-sm font-semibold text-slate-700">Notes <span class="font-normal text-slate-400">(optional)</span><textarea wire:model="notes" maxlength="2000" rows="3" class="mt-1 block w-full rounded-md border-slate-200 text-sm focus:border-[#800000] focus:ring-[#800000]"></textarea>@error('notes')<span class="text-xs text-red-600">{{ $message }}</span>@enderror</label>
            <button type="submit" wire:loading.attr="disabled" class="rounded-md bg-[#800000] px-4 py-2.5 text-sm font-semibold text-white hover:bg-rose-900 disabled:opacity-50">Save reminder</button>
        </form>
    </x-modal>
</section>
