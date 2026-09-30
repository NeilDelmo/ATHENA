<x-app-layout>
    @php
        $isFacultyResearcher = Auth::user()->isUsingWorkspace('faculty_researcher');
        $revisionRequestedTopics = $topics->where('status', 'revision_requested');
        $underReviewTopics = $topics->whereIn('status', [
            'pending',
            'expert_review',
            'for_final_decision',
            'lrec_queued',
            'lrec_review',
            'gad_review',
            'resubmitted',
            'ready_for_signature',
        ]);
    @endphp

    <x-slot name="header">
        <x-workspace-header-banner
            eyebrow="Faculty workspace"
            title="Faculty research"
            :description="'Welcome back, '.Auth::user()->name.'. Manage your drafts, review feedback, and submitted proposals.'"
        >
            <x-slot:actions>
                <div class="flex w-full sm:w-auto">
                    <a href="{{ route('faculty.proposal-drafts.create') }}" class="dashboard-action w-full sm:w-auto">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                        New proposal
                    </a>
                </div>
            </x-slot:actions>
        </x-workspace-header-banner>
    </x-slot>

    <div class="space-y-5" data-dashboard-palette="red-black-white" data-dashboard-layout="faculty-overview">

        <section aria-labelledby="proposal-overview-heading">
            <h2 id="proposal-overview-heading" class="sr-only">Proposal overview</h2>
            <p class="sr-only">A quick view of your research pipeline.</p>
            <dl class="grid grid-cols-2 gap-4 xl:grid-cols-4">
                @foreach ([
                    ['Drafts', $proposalDraftCount, 'Proposal packages in progress', 'M4 4h10l6 6v10H4V4Zm10 0v6h6M8 14h8M8 17h5'],
                    ['Submitted', $topics->count(), 'Your submitted proposals', 'M4 4h16v16H4V4Zm4 5h8M8 12h8M8 15h5'],
                    ['Under review', $underReviewTopics->count(), 'Moving through the review process', 'M12 6v6l4 2m5-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'],
                    ['Needs revision', $revisionRequestedTopics->count(), 'Feedback ready for your response', 'M12 9v4m0 3h.01M12 3 2 20h20L12 3Z'],
                ] as [$label, $count, $description, $icon])
                    <x-dashboard-stat :label="$label" :value="$count" :description="$description" :icon="$icon" />
                @endforeach
            </dl>
        </section>
        @if (session('success'))
            <div class="flex items-start gap-3 rounded-3xl border border-rose-100 bg-white px-4 py-3 text-sm text-gray-700 shadow-bubble-sm dark:border-red-950/70 dark:bg-slate-950 dark:text-gray-300">
                <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-[#7A0019] dark:bg-red-400" aria-hidden="true"></span>
                <p class="font-semibold">{{ session('success') }}</p>
            </div>
        @endif

        @if ($errors->resubmission->any())
            <div class="rounded-3xl border border-rose-200 border-l-4 border-l-[#7A0019] bg-rose-50 px-4 py-3 text-sm text-[#7A0019] dark:border-red-950 dark:border-l-red-500 dark:bg-red-950/30 dark:text-red-200">
                <p class="font-semibold">Please review your submission.</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach ($errors->resubmission->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($revisionRequestedTopics->isNotEmpty())
            <section aria-labelledby="action-required-heading" class="overflow-hidden rounded-2xl border border-red-200 bg-red-50/70 shadow-sm dark:border-red-950 dark:bg-red-950/20">
                <div class="flex flex-col gap-2 border-b border-red-200 px-5 py-4 dark:border-red-950 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-3">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#7A0019] text-white dark:bg-red-700" aria-hidden="true">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.25" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9.303 3.376c.866 1.5-.217 3.374-1.948 3.374H4.645c-1.73 0-2.813-1.874-1.948-3.374L10.052 3.38c.865-1.5 3.03-1.5 3.896 0l7.355 12.746ZM12 16.5h.008v.008H12V16.5Z" /></svg>
                        </span>
                        <div>
                            <h3 id="action-required-heading" class="text-sm font-semibold text-gray-950 dark:text-white">Action required</h3>
                            <p class="mt-0.5 text-xs text-gray-600 dark:text-gray-300">{{ $revisionRequestedTopics->count() }} {{ str('proposal')->plural($revisionRequestedTopics->count()) }} {{ $revisionRequestedTopics->count() === 1 ? 'needs' : 'need' }} your attention.</p>
                        </div>
                    </div>
                    <span class="w-fit rounded-full bg-white px-3 py-1 text-[10px] font-semibold uppercase tracking-wider text-[#7A0019] ring-1 ring-inset ring-red-200 dark:bg-red-950/50 dark:text-red-200 dark:ring-red-900">Needs revision</span>
                </div>

                <div class="divide-y divide-red-200 dark:divide-red-950">
                    @foreach ($revisionRequestedTopics as $topic)
                        @php
                            $latestRevisionReview = $topic->reviews->where('decision', 'revision_requested')->last() ?? $topic->reviews->last();
                        @endphp
                        <article class="grid gap-4 px-5 py-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center">
                            <div class="min-w-0">
                                <h4 class="truncate text-sm font-semibold text-gray-950 dark:text-white">{{ $topic->title }}</h4>
                                <p class="mt-1 truncate text-xs text-gray-500 dark:text-gray-400">{{ $topic->researchCall?->title ?? 'Independent submission' }}</p>
                                <p class="mt-2 line-clamp-2 text-xs leading-5 text-gray-700 dark:text-gray-300">{{ $latestRevisionReview?->comment ?: 'The Research Office requested changes to this proposal.' }}</p>
                            </div>
                            <a href="{{ route('faculty.topics.revision', $topic) }}" class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-[#7A0019] px-4 py-2.5 text-xs font-semibold text-white transition hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-[#7A0019] focus:ring-offset-2 dark:bg-red-700 dark:hover:bg-red-600 dark:focus:ring-red-400 dark:focus:ring-offset-red-950">
                                Revise and resubmit proposal
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6" /></svg>
                            </a>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif


        <div data-dashboard-columns class="min-w-0 space-y-6">
                @if ($researchCallCarouselItems->isNotEmpty())
                    <div class="dashboard-panel">
                        <div class="dashboard-panel-heading">
                            <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Research Office announcements</h2>
                            <a href="{{ route('research-calls.index') }}" class="text-xs font-semibold text-[#7A0019] hover:underline dark:text-red-300">View research calls</a>
                        </div>
                        @include('faculty.partials.research-call-carousel', ['researchCallCarouselItems' => $researchCallCarouselItems])
                    </div>
                @endif
        @if (! $isFacultyResearcher)
            <section id="recent-drafts" aria-labelledby="recent-drafts-heading">
            <div class="flex items-end justify-between gap-4">
                <div>
                    <h3 id="recent-drafts-heading" class="text-base font-semibold text-gray-950 dark:text-white">Continue working</h3>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Your two most recently edited proposal packages.</p>
                </div>
                <a href="{{ route('faculty.proposal-drafts.index') }}" class="shrink-0 text-xs font-semibold text-[#7A0019] transition hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-[#7A0019] focus:ring-offset-2 dark:text-red-300 dark:hover:text-red-200 dark:focus:ring-red-400 dark:focus:ring-offset-gray-950">View all drafts</a>
            </div>

            <div class="mt-3 grid gap-3 md:grid-cols-2">
                @forelse ($recentProposalDrafts->take(2) as $proposalDraft)
                    @php
                        $progress = $proposalDraftProgress->get($proposalDraft->getKey());
                    @endphp
                    <article class="group flex flex-col rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition-colors hover:bg-gray-50/80 dark:border-gray-800 dark:bg-gray-950 dark:hover:bg-gray-900/50">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex flex-wrap gap-2">
                                <span class="rounded-full bg-gray-950 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wider text-white dark:bg-white dark:text-gray-950">Draft</span>
                                <span class="rounded-full bg-gray-100 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wider text-gray-600 ring-1 ring-inset ring-gray-200 dark:bg-gray-900 dark:text-gray-300 dark:ring-gray-800">{{ $proposalDraft->isOwnedBy(Auth::user()) ? 'Owner' : 'Team member' }}</span>
                            </div>
                            <span class="shrink-0 text-[11px] font-medium text-gray-400">Edited {{ $proposalDraft->updated_at->diffForHumans() }}</span>
                        </div>

                        <h4 class="mt-4 line-clamp-2 text-base font-semibold leading-6 text-gray-950 dark:text-white">{{ $proposalDraft->project_title ?: 'Untitled proposal' }}</h4>
                        <p class="mt-1 line-clamp-1 text-xs text-gray-500 dark:text-gray-400">{{ $proposalDraft->researchCall?->title ?? 'Draft in progress' }}</p>
                        @unless ($proposalDraft->isOwnedBy(Auth::user()))
                            <p class="mt-1 text-[11px] font-semibold text-gray-400">Shared by {{ $proposalDraft->owner->name }}</p>
                        @endunless

                        <div class="mt-5">
                            <div class="flex items-center justify-between text-[11px] font-bold text-gray-600 dark:text-gray-300">
                                <span>Package progress</span>
                                <span>{{ $progress['completed'] }}/{{ $progress['total'] }} papers</span>
                            </div>
                            <div role="progressbar" aria-label="Proposal package completion" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $progress['percentage'] }}" class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                <div class="h-full rounded-full bg-[#7A0019] dark:bg-red-400" style="width: {{ $progress['percentage'] }}%"></div>
                            </div>
                        </div>

                        <div class="mt-auto flex items-center justify-between gap-4 pt-5">
                            <span class="text-[11px] font-bold text-gray-400">{{ $progress['percentage'] }}% complete</span>
                            <a href="{{ route('faculty.proposal-drafts.show', $proposalDraft) }}" class="inline-flex items-center gap-1.5 rounded-xl bg-gray-950 px-4 py-2.5 text-xs font-semibold text-white transition hover:bg-[#7A0019] focus:outline-none focus:ring-2 focus:ring-[#7A0019] focus:ring-offset-2 dark:bg-white dark:text-gray-950 dark:hover:bg-red-200 dark:focus:ring-red-400 dark:focus:ring-offset-gray-950">
                                Resume draft
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6" /></svg>
                            </a>
                        </div>
                    </article>
                @empty
                    <div class="rounded-2xl border border-gray-200 bg-white px-6 py-12 text-center shadow-sm dark:border-gray-800 dark:bg-gray-950 md:col-span-2">
                        <div class="mx-auto flex h-11 w-11 items-center justify-center rounded-full border border-gray-200 text-[#7A0019] dark:border-gray-800 dark:text-red-300">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                        </div>
                        <h4 class="mt-4 text-sm font-semibold text-gray-950 dark:text-white">No proposal drafts yet</h4>
                        <p class="mx-auto mt-1 max-w-md text-xs leading-5 text-gray-500 dark:text-gray-400">Start a proposal and complete each required paper. You can submit anytime.</p>
                                                    <a href="{{ route('faculty.proposal-drafts.create') }}" class="mt-4 inline-flex items-center justify-center rounded-xl bg-[#7A0019] px-4 py-2.5 text-xs font-semibold text-white transition hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-[#7A0019] focus:ring-offset-2 dark:bg-red-700 dark:hover:bg-red-600 dark:focus:ring-red-400 dark:focus:ring-offset-gray-950">Create first proposal</a>
                    </div>
                @endforelse
            </div>
        </section>
        @endif

        </div>
    </div>
</x-app-layout>
