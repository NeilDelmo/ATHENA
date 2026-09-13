<x-app-layout>
    <x-slot name="header">
        <div class="space-y-3">
            <x-back-link href="{{ route('topics.show', $topic) }}">Back to project</x-back-link>
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-red-700 dark:text-red-300">{{ $topic->project_status }} project</p>
                <h2 class="mt-1 text-2xl font-black text-gray-950 dark:text-white">Conferences &amp; Publications</h2>
                <p class="mt-2 text-sm text-gray-600 dark:text-slate-300">{{ $topic->title }}</p>
            </div>
        </div>
    </x-slot>

    @php
        $endpoints = collect(['authors', 'profile', 'papers', 'doi', 'import'])->mapWithKeys(fn ($action) => [$action => route('research.dissemination.'.$action, $topic)])->all();
        $endpoints['conferenceSearch'] = route('research.dissemination.conferences.search', $topic);
        $draft = array_fill_keys(['candidate_key', 'title', 'url', 'official_url', 'location', 'submission_deadline', 'event_date', 'attendance_mode', 'fees', 'publication_details'], '');
        if (old('conference_id') === 'new') {
            foreach ($draft as $field => $value) {
                $draft[$field] = old($field, $value);
            }
        }
    @endphp
    <div class="mx-auto max-w-6xl space-y-6"
        x-data="projectDissemination(@js(['endpoints' => $endpoints, 'profile' => $profile, 'query' => $suggestedQuery, 'authorName' => Auth::user()->name, 'conferenceDraft' => $draft, 'initialTab' => old('type') || old('publication_id') ? 'publications' : null]))">
        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 text-sm leading-6 text-gray-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300">
            @if ($topic->isCompletedProject())
                Research reporting is complete. Continue finding venues and recording research outputs here; the approved reports remain archived.
            @else
                Plan where to present your findings and record publications as they become available.
            @endif
            @unless ($canEdit) <p class="mt-1 font-semibold">Research Head view: project records are shown for reference.</p> @endunless
        </div>

        @if (session('success'))
            <p role="status" class="rounded-xl bg-green-50 p-4 text-sm text-green-800 dark:bg-green-950 dark:text-green-200">{{ session('success') }}</p>
        @endif
        @if ($errors->any())
            <div role="alert" class="rounded-xl bg-red-50 p-4 text-sm text-red-800 dark:bg-red-950 dark:text-red-200">
                @foreach ($errors->all() as $error) <p>{{ $error }}</p> @endforeach
            </div>
        @endif
        <p x-show="error" x-cloak x-text="error" role="alert" class="rounded-xl bg-red-50 p-4 text-sm text-red-800 dark:bg-red-950 dark:text-red-200"></p>
        <p x-show="message" x-cloak x-text="message" role="status" class="rounded-xl bg-green-50 p-4 text-sm text-green-800 dark:bg-green-950 dark:text-green-200"></p>
        <p x-show="busy" x-cloak role="status" class="text-sm text-gray-600 dark:text-slate-300">Loading results…</p>

        <nav class="flex gap-3 border-b border-gray-200 dark:border-slate-700" aria-label="Research outputs">
            <button type="button" @click="tab = 'conferences'; window.location.hash = 'conferences'" :aria-current="tab === 'conferences' ? 'page' : null" :class="tab === 'conferences' ? 'border-red-700 text-red-700 dark:text-red-300' : 'border-transparent'" class="border-b-2 px-2 py-3 text-sm font-bold">Conferences</button>
            <button type="button" @click="tab = 'publications'; window.location.hash = 'publications'" :aria-current="tab === 'publications' ? 'page' : null" :class="tab === 'publications' ? 'border-red-700 text-red-700 dark:text-red-300' : 'border-transparent'" class="border-b-2 px-2 py-3 text-sm font-bold">Publications</button>
        </nav>
        <div x-show="tab === 'conferences'">
            @include('research.dissemination-conferences')
        </div>
        <div x-show="tab === 'publications'" x-cloak>
            @include('research.dissemination-publications')
        </div>
    </div>
</x-app-layout>
