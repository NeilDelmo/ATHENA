@props(['topic', 'submission' => null])
@php
    $restore = (string) old('journal_submission_id', '') === (string) ($submission?->id ?? '');
    $value = fn ($field, $default = '') => $restore ? old($field, $default) : $default;
    $prefix = 'journal-entry-'.($submission?->id ?? 'new');
    $stage = $value('status', $submission?->status ?? 'shortlisted');
@endphp
<form
    method="POST"
    action="{{ $submission ? route('research.dissemination.journal-submissions.update', [$topic, $submission]) : route('research.dissemination.journal-submissions.store', $topic) }}"
    class="space-y-4"
    x-data="{
        stage: @js($stage),
        journalName: @js($value('journal_name', $submission?->journal_name ?? '')),
        issn: @js($value('issn', $submission?->issn ?? '')),
        journalUrl: @js($value('journal_url', $submission?->journal_url ?? '')),
        get needsSubmission() { return ['submitted', 'under_review', 'revision_requested', 'accepted', 'published', 'rejected'].includes(this.stage) },
        get needsAcceptance() { return ['accepted', 'published'].includes(this.stage) }
    }"
    @if (!$submission)
        @journal-selected.window="journalName = $event.detail.name; issn = $event.detail.issn || ''; journalUrl = $event.detail.homepage_url || ''; $nextTick(() => $refs.journalName.focus())"
    @endif
>
    @csrf
    @if ($submission) @method('PATCH') @endif
    <input type="hidden" name="journal_submission_id" value="{{ $submission?->id }}">
    <div class="grid gap-4 sm:grid-cols-2">
        <label for="{{ $prefix }}-name" class="text-sm font-semibold text-gray-800 dark:text-slate-100">Journal name
            <input id="{{ $prefix }}-name" x-ref="journalName" name="journal_name" x-model="journalName" required maxlength="255" class="mt-1 block w-full rounded-lg border-gray-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
        </label>
        <label for="{{ $prefix }}-title" class="text-sm font-semibold text-gray-800 dark:text-slate-100">Manuscript title
            <input id="{{ $prefix }}-title" name="manuscript_title" value="{{ $value('manuscript_title', $submission?->manuscript_title ?? $topic->title) }}" required maxlength="500" class="mt-1 block w-full rounded-lg border-gray-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
        </label>
        <label for="{{ $prefix }}-url" class="text-sm font-semibold text-gray-800 dark:text-slate-100">Official journal website <span class="font-normal text-gray-500">(optional)</span>
            <input id="{{ $prefix }}-url" type="url" name="journal_url" x-model="journalUrl" maxlength="2000" placeholder="https://" class="mt-1 block w-full rounded-lg border-gray-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
        </label>
        <label for="{{ $prefix }}-issn" class="text-sm font-semibold text-gray-800 dark:text-slate-100">ISSN <span class="font-normal text-gray-500">(optional)</span>
            <input id="{{ $prefix }}-issn" name="issn" x-model="issn" maxlength="9" placeholder="1234-5678" pattern="[0-9]{4}-[0-9]{3}[0-9Xx]" class="mt-1 block w-full rounded-lg border-gray-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
        </label>
        <label for="{{ $prefix }}-status" class="text-sm font-semibold text-gray-800 dark:text-slate-100">Current stage
            <select id="{{ $prefix }}-status" name="status" x-model="stage" class="mt-1 block w-full rounded-lg border-gray-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                @foreach (\App\Models\ProjectJournalSubmission::STATUSES as $key => $label)
                    <option value="{{ $key }}" @selected($stage === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label for="{{ $prefix }}-reference" class="text-sm font-semibold text-gray-800 dark:text-slate-100">Journal submission ID <span class="font-normal text-gray-500">(optional)</span>
            <input id="{{ $prefix }}-reference" name="submission_reference" value="{{ $value('submission_reference', $submission?->submission_reference) }}" maxlength="255" class="mt-1 block w-full rounded-lg border-gray-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
        </label>
    </div>
    <div class="grid gap-4 sm:grid-cols-3">
        @foreach (['submitted_on' => ['Submitted on', 'needsSubmission'], 'accepted_on' => ['Accepted on', 'needsAcceptance'], 'published_on' => ['Published on', "stage === 'published'"]] as $field => [$label, $required])
            <label for="{{ $prefix }}-{{ $field }}" class="text-sm font-semibold text-gray-800 dark:text-slate-100">{{ $label }}
                <span x-show="{{ $required }}" class="font-normal text-red-700 dark:text-red-300">(required)</span>
                <input id="{{ $prefix }}-{{ $field }}" type="date" name="{{ $field }}" value="{{ $value($field, $submission?->{$field}?->toDateString()) }}" max="{{ now()->toDateString() }}" :required="{{ $required }}" class="mt-1 block w-full rounded-lg border-gray-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
            </label>
        @endforeach
    </div>
    <label for="{{ $prefix }}-publication" class="block text-sm font-semibold text-gray-800 dark:text-slate-100">Published article or DOI link
        <span x-show="stage === 'published'" class="font-normal text-red-700 dark:text-red-300">(required)</span>
        <input id="{{ $prefix }}-publication" type="url" name="publication_url" value="{{ $value('publication_url', $submission?->publication_url) }}" :required="stage === 'published'" maxlength="2000" placeholder="https://doi.org/… or the published article URL" class="mt-1 block w-full rounded-lg border-gray-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
    </label>
    <label for="{{ $prefix }}-notes" class="block text-sm font-semibold text-gray-800 dark:text-slate-100">Notes <span class="font-normal text-gray-500">(optional)</span>
        <textarea id="{{ $prefix }}-notes" name="notes" rows="2" maxlength="5000" class="mt-1 block w-full rounded-lg border-gray-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">{{ $value('notes', $submission?->notes) }}</textarea>
    </label>
    <p class="text-xs leading-5 text-gray-500 dark:text-slate-400">Record updates from the journal here. Submit the actual manuscript through the journal’s official website.</p>
    <button type="submit" class="rounded-lg bg-red-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-red-800 focus:ring-2 focus:ring-red-700 focus:ring-offset-2">{{ $submission ? 'Save changes' : 'Add journal to tracker' }}</button>
</form>
