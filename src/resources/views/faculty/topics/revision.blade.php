<x-app-layout>
    <div class="-mx-4 -my-6 min-h-full bg-slate-50/80 px-4 py-6 dark:bg-slate-950 sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8">
        <div class="mx-auto max-w-6xl space-y-5">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <a href="{{ route('topics.show', $topic) }}#proposal-review" class="inline-flex min-h-10 items-center gap-2 rounded-full border border-slate-200 bg-white px-4 text-sm font-bold text-slate-700 shadow-sm transition hover:border-slate-300 hover:text-[#7A0019] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#7A0019] focus-visible:ring-offset-2 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-slate-600 dark:focus-visible:ring-offset-slate-950">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m15 19-7-7 7-7" /></svg>
                    Back to proposal
                </a>
                <div class="flex items-center gap-3" aria-label="Revision progress: step 1 of 4">
                    <span class="text-xs font-bold text-slate-500 dark:text-slate-400">1 of 4 steps</span>
                    <span class="flex gap-1" aria-hidden="true">
                        <span class="h-1.5 w-8 rounded-full bg-[#7A0019]"></span>
                        <span class="h-1.5 w-8 rounded-full bg-slate-200 dark:bg-slate-700"></span>
                        <span class="h-1.5 w-8 rounded-full bg-slate-200 dark:bg-slate-700"></span>
                        <span class="h-1.5 w-8 rounded-full bg-slate-200 dark:bg-slate-700"></span>
                    </span>
                </div>
            </div>

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

            <div class="grid items-start gap-5 lg:grid-cols-[17rem_minmax(0,1fr)]">
                <aside class="lg:sticky lg:top-32" aria-label="Revision steps">
                    <ol data-revision-step-panel class="grid gap-2.5 sm:grid-cols-2 lg:grid-cols-1">
                        <li>
                            <a href="#revision-feedback" class="flex h-full gap-3 rounded-xl border border-red-200 border-l-[3px] border-l-[#7A0019] bg-red-50 p-4 text-slate-800 transition hover:border-red-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#7A0019] focus-visible:ring-offset-2 dark:border-red-900 dark:border-l-red-500 dark:bg-red-950/30 dark:text-slate-100 dark:focus-visible:ring-offset-slate-950">
                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-[#7A0019] text-xs font-black text-white">1</span>
                                <span><strong class="block text-sm text-[#7A0019] dark:text-red-300">Respond to feedback</strong><span class="mt-1 block text-xs leading-5 text-slate-600 dark:text-slate-400">Address comments from the Research Head and co-evaluator.</span><span class="mt-2 inline-flex rounded-full bg-red-100 px-2 py-0.5 text-[11px] font-bold text-[#7A0019] dark:bg-red-900/50 dark:text-red-200">In progress</span></span>
                            </a>
                        </li>
                        <li>
                            <a href="#revision-papers" class="flex h-full gap-3 rounded-xl border border-slate-200 bg-white p-4 text-slate-800 transition hover:border-slate-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#7A0019] focus-visible:ring-offset-2 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:focus-visible:ring-offset-slate-950">
                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full border border-slate-200 bg-white text-xs font-black text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">2</span>
                                <span><strong class="block text-sm">Revise requested papers</strong><span class="mt-1 block text-xs leading-5 text-slate-500 dark:text-slate-400">Update {{ $pendingFileRevisions->groupBy('document_type')->count() }} requested {{ Str::plural('paper', $pendingFileRevisions->groupBy('document_type')->count()) }}.</span><span class="mt-2 inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-bold text-slate-500 dark:bg-slate-800 dark:text-slate-400">Pending</span></span>
                            </a>
                        </li>
                        <li>
                            <a href="#revision-details" class="flex h-full gap-3 rounded-xl border border-slate-200 bg-white p-4 text-slate-800 transition hover:border-slate-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#7A0019] focus-visible:ring-offset-2 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:focus-visible:ring-offset-slate-950">
                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full border border-slate-200 bg-white text-xs font-black text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">3</span>
                                <span><strong class="block text-sm">Confirm proposal details</strong><span class="mt-1 block text-xs leading-5 text-slate-500 dark:text-slate-400">Verify the title, cost, description, and duration.</span><span class="mt-2 inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-bold text-slate-500 dark:bg-slate-800 dark:text-slate-400">Pending</span></span>
                            </a>
                        </li>
                        <li>
                            <a href="#review-and-submit" class="flex h-full gap-3 rounded-xl border border-slate-200 bg-white p-4 text-slate-800 transition hover:border-slate-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#7A0019] focus-visible:ring-offset-2 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:focus-visible:ring-offset-slate-950">
                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full border border-slate-200 bg-white text-xs font-black text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">4</span>
                                <span><strong class="block text-sm">Review and send</strong><span class="mt-1 block text-xs leading-5 text-slate-500 dark:text-slate-400">Create the next version and return it for review.</span><span class="mt-2 inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-bold text-slate-500 dark:bg-slate-800 dark:text-slate-400">Pending</span></span>
                            </a>
                        </li>
                    </ol>
                </aside>

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
    </div>
</x-app-layout>
