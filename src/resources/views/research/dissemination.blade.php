<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Journal Finder &amp; Publication" :subtitle="$topic->title">
            <x-slot name="actions">
                <span class="rounded-full bg-slate-100 px-3 py-1.5 text-[11px] font-black uppercase tracking-wider text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ $topic->project_status }} project</span>
                <x-back-link href="{{ route('topics.show', $topic) }}">Back to project</x-back-link>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="mx-auto max-w-6xl space-y-6">
        <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-950 sm:p-6">
            <div class="flex items-start gap-4">
                <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-red-50 text-red-700 dark:bg-red-950/50 dark:text-red-300">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4.75 5.75A1.75 1.75 0 0 1 6.5 4h11A1.75 1.75 0 0 1 19.25 5.75v12.5A1.75 1.75 0 0 1 17.5 20h-11a1.75 1.75 0 0 1-1.75-1.75V5.75Z"></path><path stroke-linecap="round" d="M8 8h8M8 12h8M8 16h5"></path></svg>
                </span>
                <div>
                    <h3 class="text-lg font-bold text-gray-950 dark:text-white">Choose a suitable publishing venue</h3>
                    <p class="mt-1 text-base leading-7 text-gray-600 dark:text-slate-300">
                        Find journals that publish work related to your manuscript, check their indexing, and track the journal’s decision through to publication.
                    </p>
                    @if ($topic->isCompletedProject())
                        <p class="mt-2 text-sm font-semibold text-gray-700 dark:text-slate-200">Research reporting is complete and remains archived. Journal searches do not alter those reports.</p>
                    @endif
                    @if (Auth::user()->isUsingWorkspace('research_head'))
                        <p class="mt-2 text-sm font-semibold text-red-700 dark:text-red-300">Research Head view: you can review and run recommendations without changing the project.</p>
                    @endif
                </div>
            </div>
            <ol class="mt-5 grid gap-2 text-sm sm:grid-cols-5">
                @foreach (['Choose a journal', 'Submit manuscript', 'Journal review', 'Acceptance', 'Publication'] as $step)
                    <li class="flex items-center gap-2 rounded-lg bg-gray-50 px-3 py-2 text-gray-700 dark:bg-slate-900 dark:text-slate-200"><span class="font-bold text-red-700 dark:text-red-300">{{ $loop->iteration }}.</span>{{ $step }}</li>
                @endforeach
            </ol>
            <p class="mt-3 text-sm leading-6 text-gray-600 dark:text-slate-300">Submitting or receiving acceptance does not mean the paper is published. After publication, check Google Scholar separately; inclusion depends on Google’s discovery and indexing of the article.</p>
        </section>

        <x-journal-finder
            :endpoint="route('research.dissemination.journals.search', $topic)"
            :initial-query="$topic->title"
            :initial-context="$projectAbstract"
            :can-track="$canTrackJournals"
            heading="Find a journal for this manuscript"
            description="The project title and report abstract are prefilled. Refine them to match the manuscript you intend to submit."
        />

        <section id="journal-submissions" class="space-y-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-950 sm:p-6"
            x-data="{ adding: @js($errors->any() && !old('journal_submission_id')) }"
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
            @if ($errors->any())
                <div role="alert" class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">
                    <p class="font-semibold">Please correct these details:</p>
                    <ul class="mt-1 list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif
            @if ($canTrackJournals)
                <div x-show="adding" x-cloak class="border-t border-gray-200 pt-4 dark:border-slate-700">
                    <x-journal-submission-form :topic="$topic" />
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
                            <details class="mt-3" @if ((string) old('journal_submission_id') === (string) $submission->id) open @endif>
                                <summary class="cursor-pointer text-sm font-semibold text-gray-700 dark:text-slate-200">Update stage or details</summary>
                                <div class="mt-4"><x-journal-submission-form :topic="$topic" :submission="$submission" /></div>
                            </details>
                        @endif
                    </article>
                @empty
                    <p class="py-5 text-center text-sm text-gray-500 dark:text-slate-400">No journal submissions tracked yet. Choose “Track this journal” from a recommendation or add a journal here.</p>
                @endforelse
            </div>
        </section>
    </div>
</x-app-layout>
