@props(['conference' => null, 'reactive' => false])
@php
    $recordId = $conference?->id ?? 'new';
    $value = fn ($field) => (string) old('conference_id') === (string) $recordId
        ? old($field) : ($conference?->$field instanceof \DateTimeInterface ? $conference->$field->format('Y-m-d') : ($conference?->$field ?? ''));
    $inputClass = 'mt-1 block w-full rounded-lg border-gray-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white';
@endphp
<input type="hidden" name="conference_id" value="{{ $recordId }}">
@if ($reactive) <input type="hidden" name="candidate_key" x-model="conferenceDraft.candidate_key"> @endif
<div class="grid gap-4 sm:grid-cols-2">
    @foreach (['title' => ['Conference name', 'text', true], 'url' => ['Listing or official website URL', 'url', true], 'official_url' => ['Official conference website (if different)', 'url', false], 'location' => ['Location', 'text', false], 'submission_deadline' => ['Submission deadline', 'date', false], 'event_date' => ['Event start date', 'date', false], 'fees' => ['Fees and currency, if confirmed', 'text', false]] as $field => [$label, $type, $required])
        <label for="conference-{{ $recordId }}-{{ $field }}" class="text-sm font-medium">
            {{ $label }} @if ($required)<span aria-hidden="true">*</span>@endif
            <input id="conference-{{ $recordId }}-{{ $field }}" name="{{ $field }}" type="{{ $type }}" value="{{ $value($field) }}"
                @if ($reactive) x-model="conferenceDraft.{{ $field }}" @endif
                @required($required) @readonly($conference && $field === 'url')
                @if ($type !== 'date') maxlength="{{ $type === 'url' ? 2000 : 500 }}" @endif class="{{ $inputClass }}">
        </label>
    @endforeach
    <label class="text-sm font-medium">Attendance
        <select name="attendance_mode" @if ($reactive) x-model="conferenceDraft.attendance_mode" @endif class="{{ $inputClass }}">
            <option value="">Not confirmed</option>
            @foreach (['in_person' => 'In person', 'online' => 'Online', 'hybrid' => 'Hybrid'] as $key => $label)<option value="{{ $key }}" @selected($value('attendance_mode') === $key)>{{ $label }}</option>@endforeach
        </select>
    </label>
    <label class="text-sm font-medium sm:col-span-2">Publication arrangements
        <textarea name="publication_details" rows="2" maxlength="5000" @if ($reactive) x-model="conferenceDraft.publication_details" @endif placeholder="Proceedings publisher, journal opportunity, or other confirmed details. Leave blank when unknown." class="{{ $inputClass }}">{{ $value('publication_details') }}</textarea>
    </label>
    <label class="text-sm font-medium">Submission progress
        <select name="status" required class="{{ $inputClass }}">
            @foreach (\App\Models\ProjectConference::STATUSES as $status)<option value="{{ $status }}" @selected(($value('status') ?: 'shortlisted') === $status)>{{ ucfirst($status) }}</option>@endforeach
        </select>
    </label>
    @foreach (['submitted_on' => 'Submitted on', 'accepted_on' => 'Accepted on', 'presented_on' => 'Presented on'] as $field => $label)
        <label class="text-sm font-medium">{{ $label }}<input type="date" name="{{ $field }}" value="{{ $value($field) }}" max="{{ now()->toDateString() }}" class="{{ $inputClass }}"></label>
    @endforeach
    <p class="text-xs text-gray-500 dark:text-slate-400 sm:col-span-2">Record the corresponding dates when marking a submission submitted, accepted, or presented. Acceptance and presentation do not automatically establish publication.</p>
    <label class="text-sm font-medium sm:col-span-2">Supporting evidence URL
        <input type="url" name="evidence_url" value="{{ $value('evidence_url') }}" maxlength="2000" placeholder="Official acceptance, presentation, or proceedings page" class="{{ $inputClass }}">
    </label>
    <label class="text-sm font-medium sm:col-span-2">Notes
        <textarea name="notes" rows="2" maxlength="5000" class="{{ $inputClass }}">{{ $value('notes') }}</textarea>
    </label>
</div>
