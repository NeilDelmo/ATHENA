@props([
    'id' => null,
    'idExpression' => null,
    'name' => null,
    'nameExpression' => null,
    'value' => null,
    'model' => null,
    'min' => null,
    'max' => null,
    'required' => false,
    'placeholder' => 'Select date',
])

@php
    $initialValue = $model ? null : ($name ? old($name, $value) : $value);
@endphp

<div
    x-data="datePicker({
        initialValue: @js($initialValue),
        min: @js($min),
        max: @js($max),
        required: @js((bool) $required),
    })"
    @if ($model) x-modelable="value" x-model="{{ $model }}" @endif
    x-on:click.outside="close"
    {{ $attributes->class(['relative']) }}
>
    <input
        type="hidden"
        @if ($name) name="{{ $name }}" @endif
        @if ($nameExpression) x-bind:name="{{ $nameExpression }}" @endif
        x-model="value"
    >

    <div class="relative">
        <input
            x-ref="display"
            type="text"
            @if ($id) id="{{ $id }}" @endif
            @if ($idExpression) x-bind:id="{{ $idExpression }}" @endif
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
            class="block w-full cursor-pointer rounded-xl border-gray-300 bg-white py-2.5 pl-3 pr-11 text-sm text-gray-900 shadow-sm transition hover:border-gray-400 focus:border-red-600 focus:ring-red-600"
        >
        <button
            type="button"
            x-on:click="toggle"
            class="absolute inset-y-0 right-0 inline-flex w-11 items-center justify-center rounded-r-xl text-gray-400 transition hover:text-red-600 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-red-600"
            aria-label="Open calendar"
            tabindex="-1"
        >
            <svg aria-hidden="true" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3.75 9h16.5M5.25 5.25h13.5a1.5 1.5 0 0 1 1.5 1.5v12a1.5 1.5 0 0 1-1.5 1.5H5.25a1.5 1.5 0 0 1-1.5-1.5v-12a1.5 1.5 0 0 1 1.5-1.5Z" />
            </svg>
        </button>
    </div>

    <div
        x-cloak
        x-show="isOpen"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="translate-y-1 opacity-0"
        x-transition:enter-end="translate-y-0 opacity-100"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="translate-y-0 opacity-100"
        x-transition:leave-end="translate-y-1 opacity-0"
        x-bind:id="panelId"
        x-on:keydown.escape.prevent.stop="closeAndFocus"
        role="dialog"
        aria-label="Choose a date"
        class="absolute left-0 z-50 mt-2 w-[calc(100vw-2rem)] max-w-sm origin-top-left rounded-2xl border border-gray-200 bg-white p-4 shadow-2xl sm:w-80"
    >
        <div class="flex items-center gap-2">
            <button
                type="button"
                x-on:click="changeMonth(-1)"
                x-bind:disabled="!canChangeMonth(-1)"
                class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-gray-200 text-gray-600 transition hover:border-red-200 hover:bg-red-50 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 disabled:cursor-not-allowed disabled:opacity-30"
                aria-label="Previous month"
            >
                <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m15 18-6-6 6-6" /></svg>
            </button>

            <div class="grid min-w-0 flex-1 grid-cols-[minmax(0,1fr)_5.5rem] gap-2">
                <label class="sr-only" x-bind:for="monthSelectId">Calendar month</label>
                <select
                    x-bind:id="monthSelectId"
                    x-model.number="viewMonth"
                    class="min-w-0 rounded-xl border-gray-200 py-2 pl-3 pr-8 text-sm font-bold text-gray-900 focus:border-red-600 focus:ring-red-600"
                    aria-label="Calendar month"
                >
                    <template x-for="(month, monthIndex) in months" x-bind:key="month">
                        <option x-bind:value="monthIndex" x-text="month"></option>
                    </template>
                </select>

                <label class="sr-only" x-bind:for="yearInputId">Calendar year</label>
                <input
                    x-bind:id="yearInputId"
                    x-model.number="viewYear"
                    x-on:change="clampViewYear"
                    type="number"
                    inputmode="numeric"
                    x-bind:min="minYear"
                    x-bind:max="maxYear"
                    class="min-w-0 rounded-xl border-gray-200 px-2 py-2 text-center text-sm font-bold text-gray-900 focus:border-red-600 focus:ring-red-600"
                    aria-label="Calendar year"
                >
            </div>

            <button
                type="button"
                x-on:click="changeMonth(1)"
                x-bind:disabled="!canChangeMonth(1)"
                class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-gray-200 text-gray-600 transition hover:border-red-200 hover:bg-red-50 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 disabled:cursor-not-allowed disabled:opacity-30"
                aria-label="Next month"
            >
                <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6" /></svg>
            </button>
        </div>

        <p class="mt-2 text-center text-[11px] font-medium text-gray-500">Choose a month or type a year to jump directly.</p>

        <div class="mt-3 grid grid-cols-7 gap-1 text-center" aria-hidden="true">
            <template x-for="weekday in weekdays" x-bind:key="weekday">
                <span class="py-1 text-[10px] font-black uppercase tracking-wide text-gray-400" x-text="weekday"></span>
            </template>
        </div>

        <div class="mt-1 grid grid-cols-7 gap-1" role="grid" aria-label="Calendar days">
            <template x-for="day in calendarDays" x-bind:key="day.iso">
                <button
                    type="button"
                    x-on:click="selectDate(day.iso)"
                    x-bind:disabled="!day.selectable"
                    x-bind:aria-label="day.label"
                    x-bind:aria-current="day.isToday ? 'date' : null"
                    x-bind:aria-pressed="day.isSelected"
                    x-bind:class="{
                        'bg-red-600 text-white shadow-sm hover:bg-red-700': day.isSelected,
                        'text-gray-900 hover:bg-red-50 hover:text-red-700': day.isCurrentMonth && !day.isSelected && day.selectable,
                        'text-gray-300 hover:bg-gray-50': !day.isCurrentMonth && !day.isSelected && day.selectable,
                        'ring-1 ring-inset ring-red-300': day.isToday && !day.isSelected,
                        'cursor-not-allowed text-gray-300 opacity-40': !day.selectable,
                    }"
                    class="inline-flex h-9 w-full items-center justify-center rounded-lg text-sm font-bold transition focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-1"
                    x-text="day.dayNumber"
                ></button>
            </template>
        </div>

        <div class="mt-4 flex items-center justify-between gap-3 border-t border-gray-100 pt-3">
            <button
                x-show="!required && value"
                type="button"
                x-on:click="clear"
                class="rounded-lg px-2 py-1.5 text-xs font-bold text-gray-500 hover:bg-gray-100 hover:text-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-500"
            >Clear</button>
            <span x-show="required || !value" aria-hidden="true"></span>
            <button
                type="button"
                x-on:click="selectToday"
                x-bind:disabled="!todaySelectable"
                class="rounded-lg px-2 py-1.5 text-xs font-bold text-red-700 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-600 disabled:cursor-not-allowed disabled:opacity-40"
            >Today</button>
        </div>
    </div>
</div>
