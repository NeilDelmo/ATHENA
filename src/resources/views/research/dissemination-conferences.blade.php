<div class="space-y-6">
    @if ($canEdit)
        <section class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-950">
            <h3 class="text-lg font-bold">Find a conference for this project</h3>
            <p class="mt-1 text-sm text-gray-600 dark:text-slate-300">Start with the suggested topic words. Compare the scope and dates on the official call before making a submission.</p>
            <details class="mt-3 text-sm">
                <summary class="cursor-pointer font-semibold">Project context</summary>
                <p class="mt-2 whitespace-pre-line text-gray-600 dark:text-slate-300">{{ $projectAbstract ?: 'Use the project title and your manuscript keywords to narrow the search.' }}</p>
            </details>
            <form @submit.prevent="searchConferences" class="mt-5 grid gap-3 sm:grid-cols-2">
                <label class="text-sm font-medium sm:col-span-2">Topic or keywords
                    <input type="search" x-model="query" required minlength="3" maxlength="140" class="mt-1 w-full rounded-lg border-gray-300 text-sm dark:border-slate-600 dark:bg-slate-900">
                </label>
                <label class="text-sm font-medium">Location preference
                    <select x-model="scope" class="mt-1 w-full rounded-lg border-gray-300 text-sm dark:border-slate-600 dark:bg-slate-900">
                        <option value="">All locations</option><option value="local">Philippines</option><option value="international">Outside the Philippines</option><option value="online">Online / hybrid</option>
                    </select>
                </label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" x-model="openOnly" class="rounded border-gray-300 text-red-700">Hide passed submission deadlines</label>
                <button :disabled="!!busy" class="rounded-lg bg-red-700 px-4 py-2.5 text-sm font-bold text-white disabled:opacity-50 sm:col-span-2" x-text="busy === 'conferences' ? 'Finding conferences…' : 'Find conferences'"></button>
            </form>
            <p class="mt-3 text-xs leading-5 text-gray-500 dark:text-slate-400">Listings come from WikiCFP and are cached for 30 minutes. Topic-word matches help narrow the list; they are not a quality rating. Unknown deadlines remain visible. Fees and publication arrangements require confirmation.</p>
            <p x-show="conferenceSearched && !conferenceResults.length" x-cloak class="mt-4 text-sm">No listings matched these filters. Try broader keywords or add a conference from its official website below.</p>
            <div class="mt-4 grid gap-4 lg:grid-cols-2">
                <template x-for="item in conferenceResults" :key="item.url">
                    <article class="flex flex-col gap-3 rounded-xl border border-gray-200 p-4 dark:border-slate-700">
                        <h4 class="font-bold" x-text="item.title"></h4>
                        <p class="text-xs text-gray-600 dark:text-slate-300" x-text="'Topic words matched: ' + (item.matched_keywords.join(', ') || 'No specific matches')"></p>
                        <dl class="grid grid-cols-2 gap-3 text-sm">
                            <div><dt class="text-xs text-gray-500">Submission deadline</dt><dd x-text="item.deadline || 'Not confirmed'"></dd><dd class="text-xs font-bold" x-text="item.deadline_status === 'closed' ? 'Deadline passed' : item.deadline_status === 'unknown' ? 'Date not confirmed' : 'Check official call'"></dd></div>
                            <div><dt class="text-xs text-gray-500">Event date</dt><dd x-text="item.event_date || 'Not confirmed'"></dd></div>
                            <div><dt class="text-xs text-gray-500">Location</dt><dd x-text="item.location || 'Not confirmed'"></dd></div>
                            <div><dt class="text-xs text-gray-500">Fees / publication</dt><dd>Not confirmed</dd></div>
                        </dl>
                        <p class="text-xs text-gray-500" x-text="'Listing checked: ' + new Date(item.source_checked_at).toLocaleString()"></p>
                        <div class="mt-auto flex flex-wrap items-center gap-4">
                            <a :href="item.url" target="_blank" rel="noopener noreferrer" class="text-sm font-bold text-red-700 dark:text-red-300">Read source listing ↗</a>
                            <button type="button" @click="chooseConference(item)" class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold dark:border-slate-600">Add to shortlist</button>
                        </div>
                    </article>
                </template>
            </div>
        </section>

        <section x-ref="conferenceForm" class="scroll-mt-36 rounded-2xl border border-gray-200 p-5 dark:border-slate-700">
            <h3 class="text-lg font-bold">Add to project shortlist</h3>
            <p class="mb-4 mt-1 text-sm text-gray-600 dark:text-slate-300">Choose a result above or enter a conference you found elsewhere. Leave unconfirmed details blank.</p>
            <form method="POST" action="{{ route('research.dissemination.conferences.store', $topic) }}" class="space-y-4">
                @csrf
                <x-project-conference-fields :reactive="true" />
                <button class="rounded-lg bg-red-700 px-4 py-2.5 text-sm font-bold text-white">Save conference</button>
            </form>
        </section>
    @endif

    <section class="space-y-4">
        <div><h3 class="text-lg font-bold">Project shortlist &amp; submissions</h3><p class="text-sm text-gray-500 dark:text-slate-400">Conference participation is tracked separately from published papers.</p></div>
        @forelse ($conferences as $conference)
            <article class="rounded-2xl border border-gray-200 p-5 dark:border-slate-700">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <h4 class="min-w-0 break-words font-bold">{{ $conference->title }}</h4>
                    <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-bold dark:bg-slate-800">{{ ucfirst($conference->status) }}</span>
                </div>
                <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-3">
                    <div><dt class="text-xs text-gray-500">Submission deadline</dt><dd>{{ $conference->submission_deadline?->format('M j, Y') ?? 'Not confirmed' }}</dd></div>
                    <div><dt class="text-xs text-gray-500">Event starts</dt><dd>{{ $conference->event_date?->format('M j, Y') ?? 'Not confirmed' }}</dd></div>
                    <div><dt class="text-xs text-gray-500">Location / mode</dt><dd>{{ $conference->location ?: 'Not confirmed' }} · {{ str_replace('_', ' ', $conference->attendance_mode ?: 'Not confirmed') }}</dd></div>
                    <div><dt class="text-xs text-gray-500">Fees</dt><dd>{{ $conference->fees ?: 'Not confirmed' }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-xs text-gray-500">Publication arrangements</dt><dd class="whitespace-pre-line">{{ $conference->publication_details ?: 'Not confirmed' }}</dd></div>
                </dl>
                <p class="mt-3 text-xs text-gray-500">{{ $conference->source }} @if ($conference->source_checked_at) · Listing retrieved {{ $conference->source_checked_at->format('M j, Y H:i') }} @endif · Tracking details supplied by the research team</p>
                <div class="mt-4 flex flex-wrap gap-4 text-sm font-semibold text-red-700 dark:text-red-300">
                    <a href="{{ $conference->url }}" target="_blank" rel="noopener noreferrer">Source listing ↗</a>
                    @if ($conference->official_url)<a href="{{ $conference->official_url }}" target="_blank" rel="noopener noreferrer">Official website ↗</a>@endif
                    @if ($conference->evidence_url)<a href="{{ $conference->evidence_url }}" target="_blank" rel="noopener noreferrer">Supporting evidence ↗</a>@endif
                </div>
                @if ($conference->notes)<p class="mt-3 whitespace-pre-line text-sm text-gray-600 dark:text-slate-300">{{ $conference->notes }}</p>@endif
                @if ($canEdit)
                    <details class="mt-4 border-t border-gray-200 pt-4 dark:border-slate-700" @if ((string) old('conference_id') === (string) $conference->id) open @endif>
                        <summary class="cursor-pointer text-sm font-bold">Update details or submission progress</summary>
                        <form method="POST" action="{{ route('research.dissemination.conferences.update', [$topic, $conference]) }}" class="mt-4 space-y-4">
                            @csrf @method('PATCH')
                            <x-project-conference-fields :conference="$conference" />
                            <button class="rounded-lg bg-red-700 px-4 py-2.5 text-sm font-bold text-white">Save progress</button>
                        </form>
                    </details>
                @else
                    <p class="mt-3 text-xs text-gray-500">Submitted: {{ $conference->submitted_on?->format('M j, Y') ?? '—' }} · Accepted: {{ $conference->accepted_on?->format('M j, Y') ?? '—' }} · Presented: {{ $conference->presented_on?->format('M j, Y') ?? '—' }}</p>
                @endif
            </article>
        @empty
            <p class="rounded-xl border border-dashed border-gray-300 p-6 text-sm text-gray-600 dark:border-slate-700 dark:text-slate-300">No conferences have been saved for this project yet.</p>
        @endforelse
        {{ $conferences->links() }}
    </section>
</div>
