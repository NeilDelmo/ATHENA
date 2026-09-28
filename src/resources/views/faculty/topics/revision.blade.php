<x-app-layout>
    <div class="-mx-4 -my-6 min-h-full bg-slate-50/80 px-4 py-6 dark:bg-slate-950 sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8">
        <div class="mx-auto max-w-6xl space-y-5">
            <x-back-link fixed href="{{ route('topics.show', $topic) }}#proposal-review">Back to proposal</x-back-link>

            <section data-revision-summary class="flex flex-col gap-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900 sm:flex-row sm:items-start sm:justify-between sm:p-6" aria-labelledby="revision-workspace-title">
                <div class="flex min-w-0 gap-4">
                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-red-50 text-[#7A0019] dark:bg-red-950/40 dark:text-red-300" aria-hidden="true">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3.75h7.5l3 3v13.5H6.75V3.75Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M14.25 3.75v3h3M9.5 11h5M9.5 14.5h5" /></svg>
                    </span>
                    <div class="min-w-0">
                        <p class="text-xs font-black uppercase tracking-[0.12em] text-slate-400 dark:text-slate-500">Research proposal</p>
                        <h1 id="revision-workspace-title" class="mt-1 max-w-4xl text-xl font-black leading-tight tracking-tight text-slate-950 dark:text-white sm:text-2xl">{{ $topic->title }}</h1>
                        <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-xs font-medium text-slate-500 dark:text-slate-400">
                            <span class="inline-flex items-center gap-1.5"><svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3.75h7.5l3 3v13.5H6.75V3.75Z" /></svg>Proposal #{{ $topic->id }}</span>
                            <span class="inline-flex items-center gap-1.5"><svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h5M20 20v-5h-5" /><path stroke-linecap="round" stroke-linejoin="round" d="M5.5 15a7 7 0 0 0 12.9 2.3M18.5 9A7 7 0 0 0 5.6 6.7" /></svg>Version {{ $latestVersion?->version_number ?? 1 }}</span>
                            <span class="inline-flex items-center gap-1.5"><svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="8" r="3.2" /><path stroke-linecap="round" d="M5 20c1-3.5 4-5.5 7-5.5s6 2 7 5.5" /></svg>Requested by {{ $latestRevisionReview?->reviewer?->name ?? 'Research Head' }}</span>
                            @if ($latestVersion?->created_at)
                                <span class="inline-flex items-center gap-1.5"><svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3.5" y="5" width="17" height="15.5" rx="2" /><path stroke-linecap="round" d="M3.5 9.5h17M8 3v3.5M16 3v3.5" /></svg>Submitted {{ $latestVersion->created_at->format('M d, Y · h:i A') }}</span>
                            @endif
                        </div>
                    </div>
                </div>
                <span class="inline-flex w-fit shrink-0 items-center gap-1.5 rounded-full border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-black text-[#7A0019] dark:border-red-900 dark:bg-red-950/40 dark:text-red-300">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path stroke-linecap="round" d="M12 8v5M12 16h.01" /></svg>
                    Revision required
                </span>
            </section>

            <main class="min-w-0">
                <x-proposal-revision-form
                    :comment-response-rows="$commentResponseRows"
                    :topic="$topic"
                    :pending-file-revisions="$pendingFileRevisions"
                    :staged-revision-files="$stagedRevisionFiles"
                    :display-project-cost="$displayProjectCost"
                />
            </main>
        </div>
    </div>
</x-app-layout>
