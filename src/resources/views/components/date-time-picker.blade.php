@props([
    'id' => null,
    'name' => null,
    'value' => null,
    'required' => false,
    'placeholder' => 'Select date and time',
])

<div
    x-data="dateTimePicker({ initialValue: @js(old($name, $value)), required: @js((bool) $required) })"
    x-on:click.outside="close"
    {{ $attributes->class(['relative']) }}
>
    <input type="hidden" name="{{ $name }}" x-model="value">

    <div class="relative">
        <input
            x-ref="display"
            type="text"
            @if ($id) id="{{ $id }}" @endif
            x-bind:value="formattedValue"
            x-on:focus="handleFocus"
            x-on:click="open"
            x-on:beforeinput.prevent
            x-on:paste.prevent
            x-on:keydown.enter.prevent="open"
            x-on:keydown.space.prevent="open"
            x-on:keydown.arrow-down.prevent="open"
            x-on:keydown.escape.prevent.stop="close"
            x-on:keydown.tab="close"
            x-bind:aria-controls="panelId"
            x-bind:aria-expanded="isOpen"
            aria-haspopup="dialog"
            aria-autocomplete="none"
            aria-required="{{ $required ? 'true' : 'false' }}"
            role="combobox"
            inputmode="none"
            autocomplete="off"
            placeholder="{{ $placeholder }}"
            @required($required)
            class="block w-full cursor-pointer rounded-xl border-gray-300 bg-white py-3 pl-3.5 pr-11 text-sm font-semibold text-gray-900 shadow-sm transition hover:border-gray-400 focus:border-red-600 focus:ring-red-600 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:hover:border-slate-500"
        >
        <button type="button" x-on:click="toggle" class="absolute inset-y-0 right-0 inline-flex w-11 items-center justify-center rounded-r-xl text-gray-400 transition hover:text-red-600 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-red-600" aria-label="Open date and time picker" tabindex="-1">
            <svg aria-hidden="true" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3.75 9h16.5M5.25 5.25h13.5a1.5 1.5 0 0 1 1.5 1.5v12a1.5 1.5 0 0 1-1.5 1.5H5.25a1.5 1.5 0 0 1-1.5-1.5v-12a1.5 1.5 0 0 1 1.5-1.5Z" /></svg>
        </button>
    </div>

    <div x-cloak x-show="isOpen" x-transition x-bind:id="panelId" x-on:keydown.escape.prevent.stop="closeAndFocus" role="dialog" aria-label="Choose a date and time" class="absolute left-0 z-50 mt-2 w-[calc(100vw-2rem)] max-w-sm origin-top-left rounded-2xl border border-gray-200 bg-white p-4 shadow-2xl dark:border-slate-700 dark:bg-slate-900">
        <div class="flex items-center gap-2">
            <button type="button" x-on:click="changeMonth(-1)" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-gray-200 text-gray-600 transition hover:border-red-200 hover:bg-red-50 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 dark:border-slate-700 dark:text-slate-300 dark:hover:border-red-900 dark:hover:bg-red-950/40 dark:hover:text-red-300" aria-label="Previous month"><svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m15 18-6-6 6-6" /></svg></button>
            <div class="grid min-w-0 flex-1 grid-cols-[minmax(0,1fr)_5.5rem] gap-2">
                <select x-model.number="viewMonth" class="min-w-0 rounded-xl border-gray-200 bg-white py-2 pl-3 pr-8 text-sm font-bold text-gray-900 focus:border-red-600 focus:ring-red-600 dark:border-slate-700 dark:bg-slate-950 dark:text-white" aria-label="Calendar month">
                    @foreach (['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'] as $monthIndex => $month)
                        <option value="{{ $monthIndex }}">{{ $month }}</option>
                    @endforeach
                </select>
                <input x-model.number="viewYear" x-on:change="clampViewYear" type="number" inputmode="numeric" min="1900" max="2100" class="min-w-0 rounded-xl border-gray-200 bg-white px-2 py-2 text-center text-sm font-bold text-gray-900 focus:border-red-600 focus:ring-red-600 dark:border-slate-700 dark:bg-slate-950 dark:text-white" aria-label="Calendar year">
            </div>
            <button type="button" x-on:click="changeMonth(1)" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-gray-200 text-gray-600 transition hover:border-red-200 hover:bg-red-50 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 dark:border-slate-700 dark:text-slate-300 dark:hover:border-red-900 dark:hover:bg-red-950/40 dark:hover:text-red-300" aria-label="Next month"><svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6" /></svg></button>
        </div>

        <div class="mt-3 grid grid-cols-7 gap-1 text-center" aria-hidden="true"><template x-for="weekday in weekdays" x-bind:key="weekday"><span class="py-1 text-[10px] font-black uppercase tracking-wide text-gray-400" x-text="weekday"></span></template></div>
        <div class="mt-1 grid grid-cols-7 gap-1" role="grid" aria-label="Calendar days"><template x-for="day in calendarDays" x-bind:key="day.iso"><button type="button" x-on:click="selectDate(day.iso)" x-bind:aria-label="day.label" x-bind:class="{ 'bg-red-600 text-white shadow-sm hover:bg-red-700': day.isSelected, 'text-gray-900 hover:bg-red-50 hover:text-red-700 dark:text-slate-100 dark:hover:bg-red-950/40 dark:hover:text-red-300': day.isCurrentMonth && !day.isSelected, 'text-gray-300 dark:text-slate-600': !day.isCurrentMonth && !day.isSelected, 'ring-1 ring-inset ring-red-300': day.isToday && !day.isSelected }" class="inline-flex h-9 w-full items-center justify-center rounded-lg text-sm font-bold transition focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-1 dark:focus:ring-offset-slate-900" x-text="day.dayNumber"></button></template></div>

        <div class="mt-4 border-t border-gray-100 pt-4 dark:border-slate-800">
            <p class="text-[11px] font-black uppercase tracking-wider text-gray-500 dark:text-slate-400">Submission time</p>
            <div class="mt-2 grid grid-cols-2 gap-2">
                <select x-model="hour" x-on:change="setTime" class="rounded-xl border-gray-200 bg-white px-3 py-2.5 text-sm font-bold text-gray-900 focus:border-red-600 focus:ring-red-600 dark:border-slate-700 dark:bg-slate-950 dark:text-white" aria-label="Hour"><template x-for="option in hourOptions" x-bind:key="option.value"><option x-bind:value="option.value" x-text="option.label"></option></template></select>
                <select x-model="minute" x-on:change="setTime" class="rounded-xl border-gray-200 bg-white px-3 py-2.5 text-sm font-bold text-gray-900 focus:border-red-600 focus:ring-red-600 dark:border-slate-700 dark:bg-slate-950 dark:text-white" aria-label="Minute"><option value="00">:00</option><option value="15">:15</option><option value="30">:30</option><option value="45">:45</option></select>
            </div>
        </div>

        <div class="mt-4 flex items-center justify-between gap-3 border-t border-gray-100 pt-3 dark:border-slate-800">
            <button x-show="!required && value" type="button" x-on:click="clear" class="rounded-lg px-2 py-1.5 text-xs font-bold text-gray-500 hover:bg-gray-100 hover:text-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-500 dark:hover:bg-slate-800 dark:hover:text-white">Clear</button>
            <span x-show="required || !value" aria-hidden="true"></span>
            <button type="button" x-on:click="selectNow" class="rounded-lg px-2 py-1.5 text-xs font-bold text-red-700 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-600 dark:text-red-300 dark:hover:bg-red-950/40">Now</button>
        </div>
    </div>
</div>
