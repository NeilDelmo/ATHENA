<x-app-layout>
    @php
        $historySubject = $archived ? $topic : $proposalDraft;
        $indexRoute = $archived ? 'topics.draft-history.index' : 'faculty.proposal-drafts.history.index';
        $backRoute = $archived
            ? route('topics.show', $topic)
            : route('faculty.proposal-drafts.show', $proposalDraft).'#required-pdf-attachments';
        $subjectTitle = $archived ? $topic->title : $proposalDraft->project_title;
    @endphp

    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <x-back-link fixed href="{{ $backRoute }}">Back to {{ $archived ? 'submitted proposal' : 'proposal package' }}</x-back-link>
                <h2 class="mt-2 text-2xl font-black tracking-tight text-gray-900 dark:text-white">{{ $archived ? 'Submitted draft record' : 'Recovery history' }}</h2>
                <p class="mt-1 break-words text-xs text-gray-500 dark:text-slate-400">{{ $subjectTitle }}</p>
            </div>
            <span class="inline-flex w-fit rounded-full bg-gray-100 px-3 py-1.5 text-xs font-black text-gray-700 dark:bg-slate-800 dark:text-slate-200">{{ $versions->total() }} {{ Str::plural('recovery point', $versions->total()) }}</span>
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
                <p class="font-black">The recovery point could not be restored.</p>
                <p class="mt-1">{{ $errors->first() }}</p>
            </x-proposal-alert>
        @endif

        <section aria-labelledby="history-explanation-heading" class="rounded-2xl border-l-4 border-red-600 bg-gray-50 p-5 dark:bg-slate-800/70 sm:p-6">
            <h3 id="history-explanation-heading" class="text-base font-black text-gray-950 dark:text-white">{{ $archived ? 'This submitted record is preserved' : 'Recovery is automatic' }}</h3>
            <p class="mt-2 text-sm leading-6 text-gray-700 dark:text-slate-300">
                @if ($archived)
                    This read-only record was kept with the submitted proposal. It includes the saved papers and PDFs that were available when the proposal was turned in.
                @else
                    Your working draft saves continuously. ATHENA keeps recovery points about every 30 minutes of active editing, before a restore, and when you turn in the proposal. Redundant automatic points are kept to a small useful set.
                @endif
            </p>
        </section>

        <nav aria-label="Filter recovery history by paper" class="flex flex-wrap gap-2">
            <a href="{{ route($indexRoute, $historySubject) }}" class="rounded-full border px-3 py-2 text-xs font-bold {{ $selectedPaper === null ? 'border-gray-950 bg-gray-950 text-white dark:border-white dark:bg-white dark:text-gray-950' : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800' }}">All papers</a>
            @foreach ($papers as $paper)
                <a href="{{ route($indexRoute, [$historySubject, 'paper' => $paper['slug']]) }}" class="rounded-full border px-3 py-2 text-xs font-bold {{ ($selectedPaper['slug'] ?? null) === $paper['slug'] ? 'border-gray-950 bg-gray-950 text-white dark:border-white dark:bg-white dark:text-gray-950' : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800' }}">{{ $paper['label'] }}</a>
            @endforeach
        </nav>

        @if ($versions->isEmpty())
            <section class="rounded-2xl border border-dashed border-gray-300 bg-white p-8 text-center shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <h3 class="text-base font-black text-gray-900 dark:text-white">No recovery points yet</h3>
                <p class="mt-2 text-sm text-gray-500 dark:text-slate-400">Your working draft still saves automatically. A recovery point appears after meaningful active editing or when a paper is uploaded.</p>
            </section>
        @else
            <section aria-label="Proposal paper recovery points" class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="divide-y divide-gray-100 dark:divide-slate-800">
                    @foreach ($versions as $version)
                        @php
                            $currentDocument = $currentDocuments->get($version->document_type.':'.$version->position);
                            $currentVersion = $currentVersions->get($version->document_type.':'.$version->position, 0);
                            $matchesWorkingDraft = ! $archived && $currentDocument !== null && $version->version_number === $currentVersion;
                            $changes = collect($version->changes ?? []);
                            $pointLabel = match ($version->action) {
                                \App\Models\ProposalDraftDocumentVersion::ACTION_CHECKPOINT => 'Automatic',
                                \App\Models\ProposalDraftDocumentVersion::ACTION_PRE_RESTORE => 'Before restore',
                                \App\Models\ProposalDraftDocumentVersion::ACTION_RESTORED => 'Restored',
                                \App\Models\ProposalDraftDocumentVersion::ACTION_SUBMITTED => 'Submitted',
                                \App\Models\ProposalDraftDocumentVersion::ACTION_REMOVED => 'Removed',
                                default => $archived ? 'Archived' : 'Saved',
                            };
                        @endphp
                        <article class="p-5 sm:p-6">
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                <div class="flex min-w-0 gap-4">
                                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl {{ $version->action === \App\Models\ProposalDraftDocumentVersion::ACTION_REMOVED ? 'bg-red-700' : 'bg-gray-950 dark:bg-white' }} text-white dark:text-gray-950" aria-hidden="true">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m5-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                                    </span>
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h3 class="text-sm font-black text-gray-900 dark:text-white">{{ $version->label() }}</h3>
                                            <span class="rounded-full bg-gray-100 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-gray-700 dark:bg-slate-800 dark:text-slate-200">{{ $pointLabel }}</span>
                                            @if ($matchesWorkingDraft)
                                                <span class="rounded-full bg-red-100 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-red-800 dark:bg-red-950/60 dark:text-red-200">Matches working draft</span>
                                            @endif
                                        </div>

                                        <p class="mt-2 text-sm font-bold text-gray-800 dark:text-slate-100">{{ $version->displaySummary() }}</p>

                                        @if ($version->hasStoredFile())
                                            <p class="mt-1 break-all text-xs font-semibold text-gray-700 dark:text-slate-300">{{ $version->original_filename }}</p>
                                            <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">{{ $version->file_size ? \Illuminate\Support\Number::fileSize($version->file_size) : 'Size unavailable' }} &middot; PDF attachment</p>
                                        @else
                                            <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">Structured form data saved for PDF generation during Turn in.</p>
                                        @endif

                                        @if (filled($version->change_note))
                                            <blockquote class="mt-3 rounded-xl border-l-4 border-red-300 bg-red-50 px-4 py-3 text-sm leading-6 text-red-950 dark:border-red-700 dark:bg-red-950/40 dark:text-red-100">
                                                <span class="font-black">Details:</span> {{ $version->change_note }}
                                            </blockquote>
                                        @endif

                                        @if ($version->restoredFrom)
                                            <p class="mt-3 text-xs font-semibold text-red-700 dark:text-red-300">Restored from an earlier recovery point.</p>
                                        @endif

                                        <p class="mt-3 text-xs text-gray-500 dark:text-slate-400">
                                            Saved by <span class="font-bold text-gray-700 dark:text-slate-200">{{ $version->creator?->name ?? 'ATHENA' }}</span>
                                            <span aria-hidden="true">&middot;</span>
                                            <time datetime="{{ $version->created_at->toIso8601String() }}" title="{{ $version->created_at->format('M j, Y g:i A') }}">{{ $version->created_at->format('M j, Y g:i A') }}</time>
                                        </p>
                                    </div>
                                </div>

                                @if ($version->hasStoredFile())
                                    <a href="{{ $archived ? route('topics.draft-history.download', [$topic, $version]) : route('faculty.proposal-drafts.history.download', [$proposalDraft, $version]) }}" class="inline-flex w-full shrink-0 items-center justify-center rounded-xl border border-gray-300 px-4 py-2.5 text-xs font-bold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800 sm:w-auto">Download PDF</a>
                                @endif
                            </div>

                            @if ($changes->isNotEmpty())
                                <details class="mt-4 rounded-xl border border-gray-200 bg-gray-50 dark:border-slate-700 dark:bg-slate-800/70">
                                    <summary class="cursor-pointer px-4 py-3 text-xs font-black text-gray-700 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-red-600 dark:text-slate-200">See {{ $changes->count() }} {{ Str::plural('change', $changes->count()) }}</summary>
                                    <div class="overflow-x-auto border-t border-gray-200 dark:border-slate-700">
                                        <table class="min-w-full divide-y divide-gray-200 text-left text-xs dark:divide-slate-700">
                                            <thead class="bg-white text-[10px] font-black uppercase tracking-wider text-gray-500 dark:bg-slate-900 dark:text-slate-400"><tr><th class="px-4 py-3">Field</th><th class="px-4 py-3">Before</th><th class="px-4 py-3">After</th></tr></thead>
                                            <tbody class="divide-y divide-gray-200 dark:divide-slate-700">
                                                @foreach ($changes as $change)
                                                    <tr><th class="px-4 py-3 font-bold text-gray-800 dark:text-slate-100">{{ $change['label'] }}</th><td class="max-w-xs whitespace-pre-wrap px-4 py-3 text-gray-500 dark:text-slate-400">{{ $change['before'] }}</td><td class="max-w-xs whitespace-pre-wrap px-4 py-3 font-semibold text-gray-800 dark:text-slate-200">{{ $change['after'] }}</td></tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </details>
                            @endif

                            @if (! $archived && ! $matchesWorkingDraft)
                                @can('update', $proposalDraft)
                                    <details class="mt-4 rounded-xl border border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-950/30">
                                        <summary class="cursor-pointer px-4 py-3 text-xs font-black text-red-800 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-red-600 dark:text-red-200">Restore this recovery point</summary>
                                        <form
                                            method="POST"
                                            action="{{ route('faculty.proposal-drafts.history.restore', [$proposalDraft, $version]) }}"
                                            class="space-y-3 border-t border-red-200 p-4 dark:border-red-900"
                                            data-proposal-confirm
                                            data-confirm-title="Restore this recovery point?"
                                            data-confirm-text="ATHENA will first preserve your current working draft as another recovery point. You can restore it again later."
                                            data-confirm-button="Restore recovery point"
                                            data-confirm-icon="question"
                                        >
                                            @csrf
                                            <input type="hidden" name="document_version" value="{{ old('document_version', $currentVersion) }}">
                                            <p class="text-xs leading-5 text-red-950 dark:text-red-100">This replaces the current working draft for this paper. Your current state is saved first, so it remains recoverable.</p>
                                            <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl bg-red-600 px-4 py-2.5 text-xs font-bold text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 sm:w-auto">Restore this point</button>
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
