<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <a href="{{ route('faculty.proposal-drafts.show', $proposalDraft) }}" class="text-xs font-bold text-red-600 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2">&larr; Proposal package</a>
                <h2 class="mt-2 text-2xl font-black tracking-tight text-gray-900">Version history</h2>
                <p class="mt-1 break-words text-xs text-gray-500">{{ $proposalDraft->project_title }}</p>
            </div>
            <span class="inline-flex w-fit rounded-full bg-gray-100 px-3 py-1.5 text-xs font-black text-gray-700">{{ $versions->total() }} {{ Str::plural('version', $versions->total()) }}</span>
        </div>
    </x-slot>

    <div class="mx-auto max-w-5xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
        <section aria-labelledby="history-explanation-heading" class="rounded-2xl border border-blue-200 bg-blue-50 p-5 sm:p-6">
            <h3 id="history-explanation-heading" class="text-base font-black text-blue-950">Every save is preserved</h3>
            <p class="mt-2 text-sm leading-6 text-blue-900">Like a commit history, this timeline records which teammate saved each paper and when. Replaced and removed uploads remain downloadable until the draft is turned in or deleted.</p>
        </section>

        <nav aria-label="Filter version history by paper" class="flex flex-wrap gap-2">
            <a href="{{ route('faculty.proposal-drafts.history.index', $proposalDraft) }}" class="rounded-full border px-3 py-2 text-xs font-bold {{ $selectedPaper === null ? 'border-gray-900 bg-gray-900 text-white' : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50' }}">All papers</a>
            @foreach ($papers as $paper)
                <a href="{{ route('faculty.proposal-drafts.history.index', [$proposalDraft, 'paper' => $paper['slug']]) }}" class="rounded-full border px-3 py-2 text-xs font-bold {{ ($selectedPaper['slug'] ?? null) === $paper['slug'] ? 'border-gray-900 bg-gray-900 text-white' : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50' }}">{{ $paper['label'] }}</a>
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
                        <article class="p-5 sm:p-6">
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                <div class="flex min-w-0 gap-4">
                                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-gray-900 text-xs font-black text-white">v{{ $version->version_number }}</span>
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h3 class="text-sm font-black text-gray-900">{{ $version->label() }}</h3>
                                            <span class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-wider {{ $version->isCurrent() ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">{{ $version->isCurrent() ? 'Current' : 'Previous' }}</span>
                                        </div>

                                        @if ($version->hasStoredFile())
                                            <p class="mt-2 break-all text-sm font-bold text-gray-800">{{ $version->original_filename }}</p>
                                            <p class="mt-1 text-xs text-gray-500">{{ $version->file_size ? \Illuminate\Support\Number::fileSize($version->file_size) : 'Size unavailable' }} &middot; PDF attachment</p>
                                        @else
                                            <p class="mt-2 text-sm font-semibold text-gray-700">Structured form data saved</p>
                                            <p class="mt-1 text-xs text-gray-500">The latest form data will be rendered into the official PDF during Turn in.</p>
                                        @endif

                                        <p class="mt-3 text-xs text-gray-500">
                                            Saved by <span class="font-bold text-gray-700">{{ $version->creator?->name ?? 'ATHENA' }}</span>
                                            <span aria-hidden="true">&middot;</span>
                                            <time datetime="{{ $version->created_at->toIso8601String() }}" title="{{ $version->created_at->format('M j, Y g:i A') }}">{{ $version->created_at->diffForHumans() }}</time>
                                        </p>
                                    </div>
                                </div>

                                @if ($version->hasStoredFile())
                                    <a href="{{ route('faculty.proposal-drafts.history.download', [$proposalDraft, $version]) }}" class="inline-flex w-full shrink-0 items-center justify-center rounded-xl border border-gray-300 px-4 py-2.5 text-xs font-bold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-700 focus:ring-offset-2 sm:w-auto">Download this version</a>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>

            {{ $versions->links() }}
        @endif
    </div>
</x-app-layout>
