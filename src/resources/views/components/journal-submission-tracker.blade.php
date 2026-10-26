@props(['topic', 'journalSubmissions', 'canTrackJournals' => false, 'returnToFinder' => false])
@php
    $validationContext = session('dissemination_validation_context', []);
    $hasJournalErrors = data_get($validationContext, 'topic_id') === $topic->id && data_get($validationContext, 'kind') === 'journal' && $errors->any();
@endphp

<section id="journal-submissions" class="space-y-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-950 sm:p-6"
    x-data="{ adding: @js($hasJournalErrors && data_get($validationContext, 'record_id') === null) }"
    @journal-selected.window="adding = true; $nextTick(() => $el.scrollIntoView({ behavior: 'smooth', block: 'start' }))">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h3 class="text-lg font-bold text-gray-950 dark:text-white">Journal submission tracker</h3>
            <p class="mt-1 text-sm text-gray-600 dark:text-slate-300">Follow your manuscript from shortlist to publication.</p>
        </div>
        @if ($canTrackJournals)
            <button type="button" @click="adding = !adding" :aria-expanded="adding" class="rounded-lg border border-red-200 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50 dark:border-red-900 dark:text-red-300" x-text="adding ? 'Close form' : 'Add a journal'"></button>
        @endif
    </div>
    @if (session('status'))
        <p role="status" class="rounded-lg bg-gray-50 p-3 text-sm text-gray-700 dark:bg-slate-900 dark:text-slate-200">{{ session('status') }}</p>
    @endif
    @if ($hasJournalErrors)
        <div role="alert" class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">
            <p class="font-semibold">Please correct these details:</p>
            <ul class="mt-1 list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
    @if ($canTrackJournals)
        <div x-show="adding" x-cloak class="border-t border-gray-200 pt-4 dark:border-slate-700">
            <x-journal-submission-form :topic="$topic" :return-to-finder="$returnToFinder" />
        </div>
    @endif
    <div class="divide-y divide-gray-200 dark:divide-slate-700">
        @forelse ($journalSubmissions as $submission)
            <article class="py-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h4 class="break-words text-sm font-bold text-gray-950 dark:text-white">{{ $submission->journal_name }}</h4>
                        <p class="mt-1 break-words text-sm text-gray-600 dark:text-slate-300">{{ $submission->manuscript_title }}</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">{{ $submission->issn ? 'ISSN '.$submission->issn.' · ' : '' }}Tracked by {{ $submission->addedBy?->name }}</p>
                    </div>
                    <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-700 dark:bg-slate-800 dark:text-slate-200">{{ \App\Models\ProjectJournalSubmission::STATUSES[$submission->status] }}</span>
                </div>
                <dl class="mt-3 flex flex-wrap gap-x-5 gap-y-2 text-xs text-gray-600 dark:text-slate-300">
                    @foreach (['submitted_on' => 'Submitted', 'accepted_on' => 'Accepted', 'published_on' => 'Published'] as $field => $label)
                        @if ($submission->{$field})
                            <div class="flex gap-1"><dt class="font-semibold">{{ $label }}:</dt><dd>{{ $submission->{$field}->format('M j, Y') }}</dd></div>
                        @endif
                    @endforeach
                    @if ($submission->submission_reference)<div class="flex gap-1"><dt class="font-semibold">Journal ID:</dt><dd>{{ $submission->submission_reference }}</dd></div>@endif
                </dl>
                <div class="mt-3 flex flex-wrap gap-4 text-sm font-semibold text-red-700 dark:text-red-300">
                    @if ($submission->journal_url)<a href="{{ $submission->journal_url }}" target="_blank" rel="noopener noreferrer" class="hover:underline">Journal website ↗</a>@endif
                    @if ($submission->status === 'published' && $submission->publication_url)
                        <a href="{{ $submission->publication_url }}" target="_blank" rel="noopener noreferrer" class="hover:underline">Read published article ↗</a>
                        <a href="https://scholar.google.com/scholar?{{ http_build_query(['q' => '"'.$submission->manuscript_title.'"']) }}" target="_blank" rel="noopener noreferrer" class="hover:underline">Check Google Scholar ↗</a>
                    @endif
                </div>
                @if ($submission->notes)<p class="mt-2 whitespace-pre-line text-sm text-gray-600 dark:text-slate-300">{{ $submission->notes }}</p>@endif
                @if ($canTrackJournals)
                    <details class="mt-3" @if ($hasJournalErrors && data_get($validationContext, 'record_id') === $submission->id) open @endif>
                        <summary class="disclosure-summary cursor-pointer text-sm font-semibold text-gray-700 dark:text-slate-200">Update stage or details</summary>
                        <div class="mt-4"><x-journal-submission-form :topic="$topic" :submission="$submission" :return-to-finder="$returnToFinder" /></div>
                    </details>
                @endif
            </article>
        @empty
            <p class="py-5 text-center text-sm text-gray-500 dark:text-slate-400">No journal submissions tracked yet. Choose “Track this journal” from a recommendation or add a journal here.</p>
        @endforelse
    </div>
</section>
