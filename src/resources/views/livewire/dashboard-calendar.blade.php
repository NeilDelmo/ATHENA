<section class="min-w-0 overflow-hidden rounded-2xl border border-red-100 bg-white text-slate-900 shadow-sm dark:border-red-950/70 dark:bg-slate-950 dark:text-white" aria-label="Research calendar" data-calendar-palette="maroon-slate-white">
    <div class="relative overflow-hidden border-b border-red-950/15 bg-gradient-to-r from-[#780019] via-[#991b35] to-[#b4233f] px-5 py-4 text-white">
        <div class="pointer-events-none absolute -right-10 -top-16 size-44 rounded-full border border-white/15"></div>
        <div class="pointer-events-none absolute right-20 -bottom-20 size-36 rounded-full bg-white/5"></div>
        <div class="relative flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-start gap-3">
                <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-white/15 ring-1 ring-white/20"><svg aria-hidden="true" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3.75 9h16.5M5.25 5.25h13.5a1.5 1.5 0 0 1 1.5 1.5v12a1.5 1.5 0 0 1-1.5 1.5H5.25a1.5 1.5 0 0 1-1.5-1.5v-12a1.5 1.5 0 0 1 1.5-1.5Z" /></svg></span>
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-red-100">Research planning</p>
                    <h3 class="mt-0.5 text-lg font-extrabold tracking-tight">Research calendar</h3>
                    <p class="mt-0.5 text-xs text-red-100">Official dates and personal reminders · {{ config('app.timezone') }}</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" wire:click="addReminder" class="inline-flex items-center gap-1.5 rounded-xl border border-white/30 bg-white/10 px-3 py-2 text-xs font-bold text-white transition hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white/70"><svg aria-hidden="true" class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M12 5.25v13.5M5.25 12h13.5" /></svg>Add reminder</button>
                <button type="button" x-data x-on:click="$dispatch('open-modal', 'calendar-expanded')" class="inline-flex items-center gap-1.5 rounded-xl bg-white px-3 py-2 text-xs font-bold text-red-900 shadow-sm transition hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-white/70"><svg aria-hidden="true" class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 3.75H3.75v4.5m0-4.5 5.5 5.5M15.75 3.75h4.5v4.5m0-4.5-5.5 5.5M8.25 20.25H3.75v-4.5m0 4.5 5.5-5.5m6.5 5.5h4.5v-4.5m0 4.5-5.5-5.5" /></svg>Expand</button>
            </div>
        </div>
    </div>

    <div data-calendar-legend class="flex flex-wrap items-center gap-x-4 gap-y-2 border-b border-slate-100 bg-slate-50/75 px-5 py-2.5 text-[11px] font-semibold text-slate-600 dark:border-slate-800 dark:bg-slate-900/60 dark:text-slate-300">
        <span class="mr-1 text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400 dark:text-slate-500">Legend</span>
        <span class="inline-flex items-center gap-1.5"><span class="size-2 rounded-full bg-red-700"></span>Official schedule</span>
        <span class="inline-flex items-center gap-1.5"><span class="size-2 rounded-full bg-amber-500"></span>Deadline</span>
        <span class="inline-flex items-center gap-1.5"><span class="size-2 rounded-full bg-sky-500"></span>Personal reminder</span>
    </div>

    <div class="grid gap-5 p-4 lg:grid-cols-[minmax(0,1.65fr)_minmax(18rem,0.75fr)] lg:p-5">
        <div class="min-w-0">
            <x-dashboard-calendar-grid :days="$days" :month-label="$monthLabel" :selected-date="$selectedDate" large />

            <div class="mt-5 border-t border-slate-100 pt-4 dark:border-slate-800">
                <h4 class="text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ $selectedLabel }}</h4>
                <div class="mt-1"><x-dashboard-calendar-events :events="$selectedEvents" /></div>
            </div>
        </div>

        <div class="min-w-0 rounded-xl border border-sky-100 bg-gradient-to-br from-sky-50 to-white p-4 dark:border-sky-950/70 dark:from-sky-950/25 dark:to-slate-950 lg:border-y-0 lg:border-r-0 lg:border-l lg:bg-none lg:p-0 lg:pl-5">
            <div class="mb-3">
                <p class="text-[10px] font-bold uppercase tracking-wider text-sky-700 dark:text-sky-300">Schedule overview</p>
                <h4 class="mt-0.5 text-sm font-extrabold text-slate-900 dark:text-white">Upcoming dates</h4>
                <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">Deadlines, review schedules, and your personal reminders.</p>
            </div>
            <div class="max-h-[31rem] overflow-y-auto pr-1"><x-dashboard-calendar-events :events="$upcoming" empty="No upcoming deadlines or reminders in the next year." /></div>
        </div>
    </div>

    <x-modal name="calendar-expanded" maxWidth="4xl" focusable>
        <div role="dialog" aria-modal="true" aria-label="Expanded research calendar" class="bg-white p-5 text-slate-900 dark:bg-slate-950 dark:text-white">
            <div class="mb-4 flex items-center justify-between border-b border-slate-200 pb-3">
                <div><p class="text-[10px] font-bold uppercase tracking-[0.18em] text-[#800000] dark:text-red-300">Research operations</p><h3 class="mt-0.5 text-lg font-extrabold">Research calendar</h3></div>
                <button type="button" x-on:click="$dispatch('close-modal', 'calendar-expanded')" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-600 transition hover:border-[#800000] hover:text-[#800000] dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300">Close</button>
            </div>
            <x-dashboard-calendar-grid :days="$days" :month-label="$monthLabel" :selected-date="$selectedDate" expanded />
            <h4 class="mt-5 border-t border-slate-200 pt-4 text-xs font-bold uppercase tracking-wider text-slate-600">{{ $selectedLabel }}</h4>
            <x-dashboard-calendar-events :events="$selectedEvents" />
        </div>
    </x-modal>

    <x-modal name="calendar-event" maxWidth="md" focusable>
        <div role="dialog" aria-modal="true" aria-label="Calendar event details" class="bg-white p-5 text-slate-900 dark:bg-slate-950 dark:text-white">
            <div class="flex items-center justify-between border-b border-slate-200 pb-3 dark:border-slate-800"><p class="text-[10px] font-bold uppercase tracking-wider text-[#800000] dark:text-red-300">Event details</p><button type="button" x-on:click="$dispatch('close-modal', 'calendar-event')" class="rounded-xl border border-slate-200 px-3 py-1.5 text-xs font-bold text-slate-600 transition hover:border-[#800000] hover:text-[#800000] dark:border-slate-700 dark:text-slate-300">Close</button></div>
            @if ($event)
                @php($eventTone = $event['kind'] === 'personal' ? 'blue' : ($event['deadline'] ? 'amber' : 'rose'))
                <div class="mt-4 flex flex-wrap items-center gap-2"><x-date-chip :date="\Illuminate\Support\Carbon::parse($event['at'])" :tone="$eventTone" weekday time relative /><span class="rounded-full px-2 py-1 text-[10px] font-bold {{ $event['kind'] === 'personal' ? 'bg-sky-100 text-sky-800' : ($event['deadline'] ? 'bg-amber-100 text-amber-800' : 'bg-red-100 text-red-800') }}">{{ $event['kind'] === 'personal' ? 'Personal reminder' : ($event['deadline'] ? 'Deadline' : 'Official schedule') }}</span></div>
                <h3 class="mt-4 text-lg font-extrabold tracking-tight">{{ $event['title'] }}</h3>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ $event['context'] }}</p>
                <p class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">{{ config('app.timezone') }}{{ $event['draft'] ? ' · Draft schedule' : '' }}</p>
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
        <form wire:submit="saveReminder" role="dialog" aria-modal="true" aria-label="Personal reminder" class="space-y-4 bg-white p-5 text-slate-900 dark:bg-slate-950 dark:text-white">
            <div class="flex items-center justify-between border-b border-slate-200 pb-3 dark:border-slate-800"><div><p class="text-[10px] font-bold uppercase tracking-wider text-sky-700 dark:text-sky-300">Personal schedule</p><h3 class="mt-0.5 text-lg font-extrabold">{{ $editingId ? 'Edit reminder' : 'Add reminder' }}</h3></div><button type="button" x-on:click="$dispatch('close-modal', 'calendar-reminder')" class="rounded-xl border border-slate-200 px-3 py-1.5 text-xs font-bold text-slate-600 dark:border-slate-700 dark:text-slate-300">Cancel</button></div>
            <p class="text-xs text-slate-500">Only you can see this reminder. Times use {{ config('app.timezone') }}.</p>
            <label class="block text-sm font-semibold text-slate-700">Title<input wire:model="title" maxlength="160" required class="mt-1 block w-full rounded-md border-slate-200 text-sm focus:border-[#800000] focus:ring-[#800000]">@error('title')<span class="text-xs text-red-600">{{ $message }}</span>@enderror</label>
            <label class="block text-sm font-semibold text-slate-700">Date and time<input type="datetime-local" wire:model="startsAt" required class="mt-1 block w-full rounded-md border-slate-200 text-sm focus:border-[#800000] focus:ring-[#800000]">@error('startsAt')<span class="text-xs text-red-600">{{ $message }}</span>@enderror</label>
            <label class="block text-sm font-semibold text-slate-700">Notes <span class="font-normal text-slate-400">(optional)</span><textarea wire:model="notes" maxlength="2000" rows="3" class="mt-1 block w-full rounded-md border-slate-200 text-sm focus:border-[#800000] focus:ring-[#800000]"></textarea>@error('notes')<span class="text-xs text-red-600">{{ $message }}</span>@enderror</label>
            <button type="submit" wire:loading.attr="disabled" class="rounded-md bg-[#800000] px-4 py-2.5 text-sm font-semibold text-white hover:bg-rose-900 disabled:opacity-50">Save reminder</button>
        </form>
    </x-modal>
</section>
