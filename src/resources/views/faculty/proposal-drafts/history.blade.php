<x-app-layout>
    @php
        $historySubject = $archived ? $topic : $proposalDraft;
        $indexRoute = $archived ? 'topics.draft-history.index' : 'faculty.proposal-drafts.history.index';
        $backRoute = $archived ? route('topics.show', $topic) : route('faculty.proposal-drafts.show', $proposalDraft);
        $subjectTitle = $archived ? $topic->title : $proposalDraft->project_title;
    @endphp

    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <a href="{{ $backRoute }}" class="text-xs font-bold text-red-600 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2">&larr; {{ $archived ? 'Submitted proposal' : 'Proposal package' }}</a>
                <h2 class="mt-2 text-2xl font-black tracking-tight text-gray-900">{{ $archived ? 'Archived draft history' : 'Version history' }}</h2>
                <p class="mt-1 break-words text-xs text-gray-500">{{ $subjectTitle }}</p>
            </div>
            <span class="inline-flex w-fit rounded-full bg-gray-100 px-3 py-1.5 text-xs font-black text-gray-700">{{ $versions->total() }} {{ Str::plural('version', $versions->total()) }}</span>
        </div>
    </x-slot>

    <div class="mx-auto max-w-5xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
        @if (session('success'))
            <x-proposal-alert>{{ session('success') }}</x-proposal-alert>
        @endif

        @if (session('warning'))
            <x-proposal-alert type="warning">{{ session('warning') }}</x-proposal-alert>
        @endif

        @if ($errors->any())
            <x-proposal-alert type="error">
                <p class="font-black">The version could not be restored.</p>
                <p class="mt-1">{{ $errors->first() }}</p>
            </x-proposal-alert>
        @endif

        <section aria-labelledby="history-explanation-heading" class="rounded-2xl border border-blue-200 bg-blue-50 p-5 sm:p-6">
            <h3 id="history-explanation-heading" class="text-base font-black text-blue-950">{{ $archived ? 'The submitted draft timeline is preserved' : 'Meaningful saves are preserved' }}</h3>
            <p class="mt-2 text-sm leading-6 text-blue-900">
                @if ($archived)
                    This read-only timeline was archived with the submitted proposal. It records who saved each paper, what changed, and the historical PDFs that were available before Turn in.
                @else
                    Like a commit history, this timeline records who saved each paper, what changed, and when. Identical saves are skipped, and any earlier version can be restored as a new version.
                @endif
            </p>
        </section>

        <nav aria-label="Filter version history by paper" class="flex flex-wrap gap-2">
            <a href="{{ route($indexRoute, $historySubject) }}" class="rounded-full border px-3 py-2 text-xs font-bold {{ $selectedPaper === null ? 'border-gray-900 bg-gray-900 text-white' : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50' }}">All papers</a>
            @foreach ($papers as $paper)
                <a href="{{ route($indexRoute, [$historySubject, 'paper' => $paper['slug']]) }}" class="rounded-full border px-3 py-2 text-xs font-bold {{ ($selectedPaper['slug'] ?? null) === $paper['slug'] ? 'border-gray-900 bg-gray-900 text-white' : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50' }}">{{ $paper['label'] }}</a>
            @endforeach
        </nav>

        @if ($versions->isEmpty())
            <section class="rounded-2xl border border-dashed border-gray-300 bg-white p-8 text-center shadow-sm">
                <h3 class="text-base font-black text-gray-900">No saved versions yet</h3>
                <p class="mt-2 text-sm text-gray-500">The first version will appear here after a paper is saved or a PDF is uploaded.</p>
            </section>
        @else
            <section aria-label="Proposal paper timeline" class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                <div class="divide-y divide-gray-100">
                    @foreach ($versions as $version)
                        @php
                            $currentDocument = $currentDocuments->get($version->document_type.':'.$version->position);
                            $currentVersion = $currentVersions->get($version->document_type.':'.$version->position, 0);
                            $isCurrent = ! $archived && $version->isCurrent();
                            $changes = collect($version->changes ?? []);
                        @endphp
                        <article class="p-5 sm:p-6">
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                <div class="flex min-w-0 gap-4">
                                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl {{ $version->action === 'restored' ? 'bg-blue-700' : ($version->action === 'removed' ? 'bg-red-700' : 'bg-gray-900') }} text-xs font-black text-white">v{{ $version->version_number }}</span>
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h3 class="text-sm font-black text-gray-900">{{ $version->label() }}</h3>
                                            <span class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-wider {{ $isCurrent ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">{{ $isCurrent ? 'Current' : ($archived ? 'Archived' : 'Previous') }}</span>
                                            @if ($version->action === 'restored')
                                                <span class="rounded-full bg-blue-100 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-blue-800">Restored</span>
                                            @elseif ($version->action === 'removed')
                                                <span class="rounded-full bg-red-100 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-red-800">Removed</span>
                                            @endif
                                        </div>

                                        <p class="mt-2 text-sm font-bold text-gray-800">{{ $version->change_summary ?: 'Saved paper version' }}</p>

                                        @if ($version->hasStoredFile())
                                            <p class="mt-1 break-all text-xs font-semibold text-gray-700">{{ $version->original_filename }}</p>
                                            <p class="mt-1 text-xs text-gray-500">{{ $version->file_size ? \Illuminate\Support\Number::fileSize($version->file_size) : 'Size unavailable' }} &middot; PDF attachment</p>
                                        @else
                                            <p class="mt-1 text-xs text-gray-500">Structured form data saved for PDF generation during Turn in.</p>
                                        @endif

                                        @if (filled($version->change_note))
                                            <blockquote class="mt-3 rounded-xl border-l-4 border-blue-300 bg-blue-50 px-4 py-3 text-sm leading-6 text-blue-950">
                                                <span class="font-black">Save note:</span> {{ $version->change_note }}
                                            </blockquote>
                                        @endif

                                        @if ($version->restoredFrom)
                                            <p class="mt-3 text-xs font-semibold text-blue-700">Restored from version {{ $version->restoredFrom->version_number }}.</p>
                                        @endif

                                        <p class="mt-3 text-xs text-gray-500">
                                            Saved by <span class="font-bold text-gray-700">{{ $version->creator?->name ?? 'ATHENA' }}</span>
                                            <span aria-hidden="true">&middot;</span>
                                            <time datetime="{{ $version->created_at->toIso8601String() }}" title="{{ $version->created_at->format('M j, Y g:i A') }}">{{ $version->created_at->diffForHumans() }}</time>
                                        </p>
                                    </div>
                                </div>

                                @if ($version->hasStoredFile())
                                    <a href="{{ $archived ? route('topics.draft-history.download', [$topic, $version]) : route('faculty.proposal-drafts.history.download', [$proposalDraft, $version]) }}" class="inline-flex w-full shrink-0 items-center justify-center rounded-xl border border-gray-300 px-4 py-2.5 text-xs font-bold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-700 focus:ring-offset-2 sm:w-auto">Download this version</a>
                                @endif
                            </div>

                            @if ($changes->isNotEmpty())
                                <details class="mt-4 rounded-xl border border-gray-200 bg-gray-50">
                                    <summary class="cursor-pointer px-4 py-3 text-xs font-black text-gray-700 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-gray-700">See {{ $changes->count() }} {{ Str::plural('change', $changes->count()) }}</summary>
                                    <div class="overflow-x-auto border-t border-gray-200">
                                        <table class="min-w-full divide-y divide-gray-200 text-left text-xs">
                                            <thead class="bg-white text-[10px] font-black uppercase tracking-wider text-gray-500"><tr><th class="px-4 py-3">Field</th><th class="px-4 py-3">Before</th><th class="px-4 py-3">After</th></tr></thead>
                                            <tbody class="divide-y divide-gray-200">
                                                @foreach ($changes as $change)
                                                    <tr><th class="px-4 py-3 font-bold text-gray-800">{{ $change['label'] }}</th><td class="max-w-xs whitespace-pre-wrap px-4 py-3 text-gray-500">{{ $change['before'] }}</td><td class="max-w-xs whitespace-pre-wrap px-4 py-3 font-semibold text-gray-800">{{ $change['after'] }}</td></tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </details>
                            @endif

                            @if (! $archived && ! $isCurrent)
                                @can('update', $proposalDraft)
                                    <details class="mt-4 rounded-xl border border-blue-200 bg-blue-50">
                                        <summary class="cursor-pointer px-4 py-3 text-xs font-black text-blue-800 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-700">Restore this version</summary>
                                        <form
                                            method="POST"
                                            action="{{ route('faculty.proposal-drafts.history.restore', [$proposalDraft, $version]) }}"
                                            class="space-y-3 border-t border-blue-200 p-4"
                                            data-proposal-confirm
                                            data-confirm-title="Restore version {{ $version->version_number }}?"
                                            data-confirm-text="A new current version will be created from this snapshot. Existing history will stay unchanged."
                                            data-confirm-button="Restore version"
                                            data-confirm-icon="question"
                                        >
                                            @csrf
                                            <input type="hidden" name="document_version" value="{{ old('document_version', $currentVersion) }}">
                                            <div>
                                                <label for="restore-note-{{ $version->id }}" class="block text-xs font-black text-blue-950">Restore note <span class="font-semibold text-blue-700">(optional)</span></label>
                                                <textarea id="restore-note-{{ $version->id }}" name="change_note" rows="2" maxlength="500" placeholder="Why is this version being restored?" class="mt-2 block w-full rounded-xl border-blue-200 bg-white text-sm shadow-sm focus:border-blue-700 focus:ring-blue-700"></textarea>
                                            </div>
                                            <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl bg-blue-700 px-4 py-2.5 text-xs font-bold text-white hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-700 focus:ring-offset-2 sm:w-auto">Restore as new version</button>
                                        </form>
                                    </details>
                                @endcan
                            @endif
                        </article>
                    @endforeach
                </div>
            </section>

            {{ $versions->links() }}
        @endif
    </div>
</x-app-layout>
