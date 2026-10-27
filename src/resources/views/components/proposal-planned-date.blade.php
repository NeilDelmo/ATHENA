@props(['researchCall' => null, 'field', 'minimumDate'])

@php
    $isStart = $field === 'start';
    $name = $isStart ? 'planned_start' : 'planned_end';
    $model = $isStart ? 'plannedStart' : 'plannedEnd';
    $call = $researchCall?->status !== 'draft' ? $researchCall : null;
    $start = $call?->implementation_start_date?->toDateString();
    $end = $call?->implementation_end_date?->toDateString();
    $referenceDates = [];
    if ($start) {
        $referenceDates[] = ['date' => $start, 'label' => 'Implementation begins'];
    }
    if ($end) {
        $referenceDates[] = ['date' => $end, 'label' => 'Implementation ends'];
    }
@endphp

<div x-data="proposalDateGuidance({ start: @js($start), end: @js($end), label: @js($isStart ? 'planned start' : 'planned end') })">
    <label for="{{ $name }}" class="block text-xs font-black uppercase tracking-wider text-gray-600 dark:text-slate-300">{{ $isStart ? 'Planned Start' : 'Planned End' }} <span class="text-red-600">Required</span></label>
    <x-date-picker :id="$name" :name="$name" :model="$model" :min="$minimumDate" :reference-dates="$referenceDates" aria-describedby="{{ $name }}-hint {{ $name }}-date-warning" required class="mt-2" />
    <p id="{{ $name }}-hint" class="mt-2 text-[11px] text-gray-500 dark:text-slate-400">{{ $isStart ? 'Only today and future dates can be selected.' : 'Automatically calculated from the total duration and planned start.' }}</p>
    <p id="{{ $name }}-date-warning" x-cloak x-show="dateWarning({{ $model }})" x-text="dateWarning({{ $model }})" role="status" aria-live="polite" class="mt-2 text-xs leading-5 text-amber-800 dark:text-amber-300"></p>
    @error($name)<p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p>@enderror
</div>
