@php
    $restore = $errors->any() && session('research_outcome_validation_context') === $context;
    $selected = $restore ? old('labels', []) : ($outcome?->labels ?? []);
    $selected = is_array($selected) ? $selected : [];
@endphp
@if ($restore)
    <div role="alert" class="rounded-lg bg-red-50 p-4 text-sm text-red-800 dark:bg-red-950/40 dark:text-red-200">
        <ul class="list-inside list-disc space-y-1">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        @if ($errors->has('lock_version'))<a href="{{ $outcome->detailUrl() }}" class="mt-2 inline-block font-semibold underline">Reload current labels</a>@endif
    </div>
@endif
@if (! $outcome?->topic_id)
    @php
        $title = $restore ? old('title') : $outcome?->title;
        $members = $restore ? old('team_members') : $outcome?->team_members;
        $funding = $restore ? old('funding_source') : $outcome?->funding_source;
    @endphp
    <div>
        <x-input-label for="outcome-title" value="Project title" />
        <x-text-input id="outcome-title" name="title" class="mt-2 block w-full" :value="is_string($title) ? $title : ''" required maxlength="255" />
    </div>
    <div>
        <x-input-label for="outcome-members" value="Other team members" />
        <textarea id="outcome-members" name="team_members" rows="3" maxlength="2000" class="mt-2 block w-full rounded-lg border-slate-300 text-sm focus:border-brand focus:ring-brand dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">{{ is_string($members) ? $members : '' }}</textarea>
    </div>
    <div>
        <x-input-label for="outcome-funding" value="Funding source" />
        <select id="outcome-funding" name="funding_source" required class="mt-2 block w-full rounded-lg border-slate-300 text-sm focus:border-brand focus:ring-brand dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
            <option value="">Select funding source</option>
            @foreach (['external', 'unfunded'] as $source)<option value="{{ $source }}" @selected($funding === $source)>{{ App\Models\ResearchOutcome::FUNDING_SOURCES[$source] }}</option>@endforeach
        </select>
    </div>
@endif
<fieldset aria-describedby="labels-help" class="space-y-3">
    <legend class="text-base font-semibold text-slate-900 dark:text-white">Project labels</legend>
    <p id="labels-help" class="text-sm text-slate-600 dark:text-slate-300">Select all that apply. Clear a label if it no longer applies.</p>
    <div class="grid grid-cols-2 gap-3">
        @foreach (App\Models\ResearchOutcome::LABELS as $key => $label)
            <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-slate-200 px-4 py-3 text-sm text-slate-800 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-100 dark:hover:bg-slate-900">
                <input type="checkbox" name="labels[]" value="{{ $key }}" @checked(in_array($key, $selected, true)) class="rounded border-slate-300 text-brand focus:ring-brand dark:border-slate-600 dark:bg-slate-900" />
                {{ $label }}
            </label>
        @endforeach
    </div>
</fieldset>
@if ($outcome)
    <input type="hidden" name="lock_version" value="{{ $restore && is_scalar(old('lock_version')) ? old('lock_version') : $outcome->lock_version }}" />
@endif
