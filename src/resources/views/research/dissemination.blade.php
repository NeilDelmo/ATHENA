<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Journal Finder &amp; Conferences" :subtitle="$topic->title">
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
            :finder-url="route('research-support.index', ['topic' => $topic->id]).'#journal-finder'"
            heading="Find a journal for this manuscript"
            description="The project title and report abstract are prefilled. Refine them to match the manuscript you intend to submit."
        />

        <x-journal-submission-tracker :topic="$topic" :journal-submissions="$journalSubmissions" :can-track-journals="$canTrackJournals" />

        @php
            $conferenceDraft = collect(['candidate_key', 'title', 'url', 'official_url', 'location', 'submission_deadline', 'event_date', 'attendance_mode', 'fees', 'publication_details'])
                ->mapWithKeys(fn ($field) => [$field => old('conference_id') === 'new' ? old($field, '') : ''])->all();
        @endphp
        <section id="conferences" class="scroll-mt-28" x-data="projectDissemination({{ Illuminate\Support\Js::from(['endpoints' => ['conferenceSearch' => route('research.dissemination.conferences.search', $topic)], 'query' => $conferenceQuery, 'conferenceDraft' => $conferenceDraft]) }})">
            <h2 class="mb-4 text-xl font-semibold text-gray-950 dark:text-white">Conference shortlist</h2>
            @if (session('success'))
                <p role="status" class="mb-4 rounded-lg bg-gray-50 p-3 text-sm text-gray-700 dark:bg-slate-900 dark:text-slate-200">{{ session('success') }}</p>
            @endif
            @if ($errors->any() && old('conference_id'))
                <ul role="alert" class="mb-4 list-inside list-disc rounded-lg bg-red-50 p-3 text-sm text-red-800 dark:bg-red-950/40 dark:text-red-200">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            @endif
            <p x-show="error" x-cloak role="alert" class="mb-4 text-sm text-red-700 dark:text-red-300" x-text="error"></p>
            @include('research.dissemination-conferences')
        </section>
    </div>
</x-app-layout>
