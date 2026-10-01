<section class="min-w-0 overflow-hidden rounded-2xl border border-red-100 bg-white text-slate-900 shadow-sm dark:border-red-950/70 dark:bg-slate-950 dark:text-white" aria-label="Research calendar" data-calendar-palette="maroon-slate-white">
    @if ($compact)
        <div data-calendar-compact class="dashboard-panel-heading">
            <h2 class="text-lg font-semibold">Research calendar</h2>
            <button type="button" x-data x-on:click="$dispatch('open-modal', 'calendar-expanded')" class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-50 hover:text-[#7A0019] focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#7A0019] dark:hover:bg-slate-900" aria-label="Expand research calendar" title="Expand research calendar">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8 3H3v5m0-5 6 6m7-6h5v5m0-5-6 6M8 21H3v-5m0 5 6-6m7 6h5v-5m0 5-6-6" /></svg>
            </button>
        </div>
        <div class="p-4">
            <x-dashboard-calendar-grid :days="$days" :month-label="$monthLabel" :selected-date="$selectedDate" monochrome />
            <div class="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-4 dark:border-slate-800">
                <h3 class="text-sm font-semibold text-slate-600 dark:text-slate-300">{{ $selectedLabel }}</h3>
                <button type="button" wire:click="addReminder" class="rounded-lg px-2 py-1.5 text-sm font-semibold text-[#7A0019] hover:bg-red-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#7A0019] dark:text-red-300 dark:hover:bg-red-950/30">+ Add reminder</button>
            </div>
            <x-dashboard-calendar-events :events="$selectedEvents" monochrome />
            <div class="mt-5 border-t border-slate-100 pt-4 dark:border-slate-800">
                <h3 class="mb-3 text-xl font-semibold text-slate-800 dark:text-slate-200">Upcoming dates</h3>
                <x-dashboard-calendar-events :events="$upcoming" monochrome empty="No upcoming deadlines or reminders." />
            </div>
            <p class="mt-4 text-sm text-slate-400">Times in {{ config('app.timezone') }}</p>
        </div>
    @else
    <header class="flex flex-wrap items-center justify-between gap-4 bg-brand px-5 py-5 text-white sm:px-6">
        <div>
            <h3 class="text-2xl font-semibold tracking-tight">Research calendar</h3>
            <p class="mt-1 text-sm text-red-100">Official dates and personal reminders · {{ config('app.timezone') }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" wire:click="addReminder" class="inline-flex min-h-11 items-center gap-2 rounded-lg bg-white px-4 py-2 text-base font-semibold text-brand hover:bg-red-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="M12 5v14M5 12h14" stroke-linecap="round" /></svg>Add reminder</button>
            <button type="button" x-data x-on:click="$dispatch('open-modal', 'calendar-expanded')" class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-white/40 px-4 py-2 text-base font-semibold hover:bg-white/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">Expand calendar</button>
        </div>
    </header>
    <div data-calendar-legend class="flex flex-wrap items-center gap-x-5 gap-y-2 border-b border-slate-200 px-5 py-3 text-sm text-slate-600 dark:border-slate-800 dark:text-slate-300 sm:px-6">
        <span class="inline-flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full bg-brand dark:bg-red-400" aria-hidden="true"></span>Official schedule</span>
        <span class="inline-flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-sm border-2 border-brand dark:border-red-400" aria-hidden="true"></span>Deadline</span>
        <span class="inline-flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full bg-slate-400" aria-hidden="true"></span>Personal reminder</span>
    </div>
    <div class="grid min-w-0 lg:grid-cols-[20rem_minmax(0,1fr)]">
        <div class="order-1 min-w-0 p-4 sm:p-6 lg:order-2">
            <x-dashboard-calendar-grid :days="$days" :month-label="$monthLabel" :selected-date="$selectedDate" large />
            <section class="mt-6 border-t border-slate-200 pt-5 dark:border-slate-800" aria-label="Events on selected date">
                <h4 class="mb-3 text-lg font-semibold text-slate-900 dark:text-white">{{ $selectedLabel }}</h4>
                <x-dashboard-calendar-events :events="$selectedEvents" />
            </section>
        </div>
        <aside data-calendar-upcoming class="order-2 min-w-0 border-t border-slate-200 p-4 dark:border-slate-800 sm:p-6 lg:order-1 lg:border-r lg:border-t-0" aria-labelledby="upcoming-dates-heading">
            <h4 id="upcoming-dates-heading" class="text-xl font-semibold text-slate-900 dark:text-white">Upcoming dates</h4>
            <p class="mb-5 mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">Deadlines, review schedules, and your personal reminders.</p>
            <x-dashboard-calendar-events :events="$upcoming" empty="No upcoming deadlines or reminders in the next year." />
        </aside>
    </div>
    @endif

    <x-modal name="calendar-expanded" maxWidth="6xl" focusable>
        <div role="dialog" aria-modal="true" aria-label="Expanded research calendar" class="bg-white p-5 text-slate-900 dark:bg-slate-950 dark:text-white">
            <div class="mb-4 flex items-center justify-between border-b border-slate-200 pb-3">
                <div><p class="text-sm font-bold uppercase tracking-[0.18em] text-[#800000] dark:text-red-300">Research operations</p><h3 class="mt-0.5 text-xl font-semibold">Research calendar</h3></div>
                <button type="button" x-on:click="$dispatch('close-modal', 'calendar-expanded')" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-bold text-slate-600 transition hover:border-[#800000] hover:text-[#800000] dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300">Close</button>
            </div>
            <x-dashboard-calendar-grid :days="$days" :month-label="$monthLabel" :selected-date="$selectedDate" :monochrome="$compact" expanded />
            <h4 class="mt-5 border-t border-slate-200 pt-4 text-sm font-bold uppercase tracking-wider text-slate-600">{{ $selectedLabel }}</h4>
            <x-dashboard-calendar-events :events="$selectedEvents" :monochrome="$compact" />
        </div>
    </x-modal>

    <x-modal name="calendar-event" maxWidth="md" focusable>
        <div role="dialog" aria-modal="true" aria-label="Calendar event details" class="bg-white p-5 text-slate-900 dark:bg-slate-950 dark:text-white">
            <div class="flex items-center justify-between border-b border-slate-200 pb-3 dark:border-slate-800"><p class="text-sm font-bold uppercase tracking-wider text-[#800000] dark:text-red-300">Event details</p><button type="button" x-on:click="$dispatch('close-modal', 'calendar-event')" class="rounded-xl border border-slate-200 px-3 py-1.5 text-sm font-bold text-slate-600 transition hover:border-[#800000] hover:text-[#800000] dark:border-slate-700 dark:text-slate-300">Close</button></div>
            @if ($event)

                <div class="mt-4 flex flex-wrap items-center gap-2"><span class="text-base font-medium text-slate-700 dark:text-slate-200">{{ $event['display_at'] }}</span><span class="rounded-full px-2 py-1 text-sm font-bold bg-red-50 text-brand dark:bg-red-950/40 dark:text-red-200">{{ $event['kind'] === 'personal' ? 'Personal reminder' : ($event['deadline'] ? 'Deadline' : 'Official schedule') }}</span></div>
                <h3 class="mt-4 text-xl font-semibold tracking-tight">{{ $event['title'] }}</h3>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ $event['context'] }}</p>
                <p class="mt-1.5 text-sm text-slate-500 dark:text-slate-400">{{ config('app.timezone') }}{{ $event['draft'] ? ' · Draft schedule' : '' }}</p>
                <p class="mt-4 whitespace-pre-line break-words text-base leading-7 text-slate-600 dark:text-slate-300">{{ $event['notes'] }}</p>
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
        <form wire:submit="saveReminder" role="dialog" aria-modal="true" aria-label="Personal reminder" class="space-y-4 bg-white p-5 text-slate-900 dark:bg-slate-950 dark:text-white">
            <div class="flex items-center justify-between border-b border-slate-200 pb-3 dark:border-slate-800"><div><p class="text-sm font-bold uppercase tracking-wider text-brand dark:text-red-300">Personal schedule</p><h3 class="mt-0.5 text-xl font-semibold">{{ $editingId ? 'Edit reminder' : 'Add reminder' }}</h3></div><button type="button" x-on:click="$dispatch('close-modal', 'calendar-reminder')" class="rounded-xl border border-slate-200 px-3 py-1.5 text-sm font-bold text-slate-600 dark:border-slate-700 dark:text-slate-300">Cancel</button></div>
            <p class="text-sm text-slate-500">Only you can see this reminder. Times use {{ config('app.timezone') }}.</p>
            <label class="block text-base font-semibold text-slate-700 dark:text-slate-200">Title<input wire:model="title" maxlength="160" required class="mt-1 block w-full rounded-md border-slate-200 text-base dark:border-slate-700 dark:bg-slate-900 dark:text-white focus:border-[#800000] focus:ring-[#800000]">@error('title')<span class="text-sm text-red-600">{{ $message }}</span>@enderror</label>
            <label class="block text-base font-semibold text-slate-700 dark:text-slate-200">Date and time<input type="datetime-local" wire:model="startsAt" required class="mt-1 block w-full rounded-md border-slate-200 text-base dark:border-slate-700 dark:bg-slate-900 dark:text-white focus:border-[#800000] focus:ring-[#800000]">@error('startsAt')<span class="text-sm text-red-600">{{ $message }}</span>@enderror</label>
            <label class="block text-base font-semibold text-slate-700 dark:text-slate-200">Notes <span class="font-normal text-slate-400">(optional)</span><textarea wire:model="notes" maxlength="2000" rows="3" class="mt-1 block w-full rounded-md border-slate-200 text-base dark:border-slate-700 dark:bg-slate-900 dark:text-white focus:border-[#800000] focus:ring-[#800000]"></textarea>@error('notes')<span class="text-sm text-red-600">{{ $message }}</span>@enderror</label>
            <button type="submit" wire:loading.attr="disabled" class="rounded-md bg-[#800000] px-4 py-2.5 text-sm font-semibold text-white hover:bg-rose-900 disabled:opacity-50">Save reminder</button>
        </form>
    </x-modal>
</section>
