<x-app-layout>
    @php
        $isFacultyResearcher = Auth::user()->isUsingWorkspace('faculty_researcher');
        $revisionRequestedTopics = $topics->where('status', 'revision_requested')
            ->sortBy(fn ($topic) => $topic->review_stage !== 'lrec'
                ? ($topic->researchCall?->paper_revisions_end_date?->toDateString() ?? '9999-12-31')
                : '9999-12-31');
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
        $revisionReviewChecks = $assistantReviewSuggestions->whereNotNull('context.topic_id')->keyBy('context.topic_id');
        $draftReviewChecks = $assistantReviewSuggestions->whereNotNull('context.proposal_draft_id')->keyBy('context.proposal_draft_id');
    @endphp

    <x-slot name="header">
        <x-page-header data-dashboard-header variant="hero" eyebrow="Faculty" title="Dashboard" subtitle="Prepare proposals, track submissions, and respond to requested revisions.">
            <x-slot:actions>
                <div class="flex w-full sm:w-auto">
                    <a href="{{ route('faculty.proposal-drafts.create') }}" class="dashboard-action w-full sm:w-auto">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                        New proposal
                    </a>
                </div>
            </x-slot:actions>
        </x-page-header>
    </x-slot>

    <div class="role-dashboard" data-dashboard-palette="maroon-slate-white" data-dashboard-layout="faculty-overview">
        @include('faculty.partials.research-call-carousel', ['researchCallCarouselItems' => $researchCallCarouselItems])

        <section aria-labelledby="proposal-overview-heading">
            <h2 id="proposal-overview-heading" class="sr-only">Proposal overview</h2>
            <dl class="dashboard-metrics" data-dashboard-metrics>
                @foreach ([
                    ['Needs revision', $revisionRequestedTopics->count(), 'Changes requested', route('faculty.submissions', ['category' => 'active', 'status' => 'revision_requested']), 'rename', 'View revisions'],
                    ['Drafts', $proposalDraftCount, 'Proposal preparation', route('faculty.proposal-drafts.index'), 'folder-open', 'View drafts'],
                    ['Submitted', $topics->count(), 'Across all statuses', route('faculty.submissions', ['category' => 'all']), 'submit', 'View submissions'],
                    ['Under review', $underReviewTopics->count(), 'Review and final approval', route('faculty.submissions', ['category' => 'active', 'status' => 'review']), 'history', 'View queue'],
                ] as [$label, $count, $description, $href, $icon, $action])
                    <x-dashboard-stat :label="$label" :value="$count" :description="$description" :icon="$icon"
                        :href="$href" :action="$action" :priority="$loop->first" wire:navigate />
                @endforeach
            </dl>
        </section>
        @if (session('success'))
            <div class="flex items-start gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700  dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300">
                <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-[#7A0019] dark:bg-red-400" aria-hidden="true"></span>
                <p class="font-semibold">{{ session('success') }}</p>
            </div>
        @endif

        @if ($errors->resubmission->any())
            <div class="rounded-xl border border-slate-200 border-l-4 border-l-[#7A0019] bg-white px-4 py-3 text-sm text-[#7A0019] dark:border-slate-700 dark:border-l-red-400 dark:bg-slate-900 dark:text-red-200">
                <p class="font-semibold">Please review your submission.</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach ($errors->resubmission->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div data-dashboard-columns class="min-w-0 space-y-6">
        @if ($revisionRequestedTopics->isNotEmpty())
            <section data-revision-worklist aria-labelledby="revision-worklist-heading" class="dashboard-panel">
                <header class="dashboard-panel-heading">
                    <div>
                        <h2 id="revision-worklist-heading" class="dashboard-section-title">Revisions to address</h2>
                        <p class="mt-1 text-sm rh-muted">Respond to reviewer feedback before resubmitting.</p>
                    </div>
                    <span class="rh-badge">{{ $revisionRequestedTopics->count() }}</span>
                </header>
                    <div class="divide-y divide-slate-200 dark:divide-slate-700">
                        @foreach ($revisionRequestedTopics as $topic)
                            @php
                                $latestRevisionReview = $topic->reviews->where('decision', 'revision_requested')->last();
                                $revisionDeadline = $topic->review_stage !== 'lrec' ? $topic->researchCall?->paper_revisions_end_date : null;
                            @endphp
                            <article data-revision-task data-topic-id="{{ $topic->id }}" class="min-w-0 p-5 transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                <div class="flex min-w-0 flex-col items-start gap-2">
                                    <div class="flex min-w-0 items-start gap-3">
                                        <span class="inline-flex h-6 w-6 shrink-0 items-center justify-center text-brand dark:text-red-300" aria-hidden="true">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 3h10l6 6v12H4V3Zm10 0v6h6M8 13h8M8 17h5" /></svg>
                                        </span>
                                        <h3 class="max-w-prose break-words text-base font-semibold leading-6 text-slate-950 dark:text-white">{{ $topic->title }}</h3>
                                    </div>
                                    <p class="text-sm leading-5 text-slate-600 dark:text-slate-400">{{ $topic->researchCall?->title ?? 'Independent submission' }}</p>
                                    @if ($revisionDeadline)
                                        <x-dashboard-deadline :date="$revisionDeadline->copy()->endOfDay()" />
                                    @endif
                                    <div data-reviewer-feedback class="mt-1 min-w-0">
                                        <p class="flex items-center gap-1.5 text-sm font-semibold text-brand dark:text-red-300"><svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8 9h8M8 13h5M5 3h14a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2h-8l-6 4v-4H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z" /></svg>Reviewer feedback</p>
                                        <p class="mt-1 max-w-prose whitespace-pre-line break-words text-sm leading-6 text-slate-700 dark:text-slate-300">{{ $latestRevisionReview?->comment ?: 'Open the revision page to see the requested changes.' }}</p>
                                    </div>
                                    <div class="mt-2 flex flex-wrap items-center gap-3">
                                        <a href="{{ route('faculty.topics.revision', $topic) }}" class="dashboard-action"><svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m16 4 4 4-12 12H4v-4L16 4ZM13 7l4 4" /></svg>Revise and resubmit proposal</a>
                                        @if ($revisionReviewChecks->has($topic->id))
                                            <x-dashboard-assistant-action :item="$revisionReviewChecks->get($topic->id)" label="Get revision help" variant="outline" :help="true" />
                                        @endif
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>
            </section>
        @endif

        @if (! $isFacultyResearcher)
            <section id="recent-drafts" class="dashboard-panel scroll-mt-40" aria-labelledby="recent-drafts-heading">
            <div class="dashboard-panel-heading">
                <div class="flex items-center gap-3">
                    <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center text-brand dark:text-red-300" aria-hidden="true"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7a2 2 0 0 1 2-2h5l2 2h7a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7Z" /></svg></span>
                    <div>
                        <h2 id="recent-drafts-heading" class="dashboard-section-title">Drafts</h2>
                        <p class="mt-1 text-xs text-slate-600 dark:text-slate-400">Resume one of your recent drafts.</p>
                    </div>
                </div>
                <a href="{{ route('faculty.proposal-drafts.index') }}" class="secondary-action">View all drafts<svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6" /></svg></a>
            </div>

            <div data-dashboard-draft-list class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($recentProposalDrafts->take(2) as $proposalDraft)
                    @php
                        $progress = $proposalDraftProgress->get($proposalDraft->getKey());
                    @endphp
                    <article data-dashboard-draft data-draft-id="{{ $proposalDraft->id }}" class="grid min-w-0 gap-4 p-5 transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/50 lg:grid-cols-[minmax(0,1fr)_8rem_auto] lg:items-center">
                        <div class="flex min-w-0 items-start gap-3">
                            <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center text-brand dark:text-red-300" aria-hidden="true"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 3h10l6 6v12H4V3Zm10 0v6h6M8 13h8M8 17h5" /></svg></span>
                            <div class="min-w-0 space-y-2">
                                <h3 class="break-words text-base font-semibold leading-6 text-slate-950 dark:text-white">{{ $proposalDraft->project_title ?: 'Untitled proposal' }}</h3>
                                <p class="flex flex-wrap gap-x-3 gap-y-1 text-sm leading-5 text-slate-600 dark:text-slate-400">
                                    <span>{{ $proposalDraft->isOwnedBy(Auth::user()) ? 'Project leader' : 'Project staff' }}</span>
                                    @unless ($proposalDraft->isOwnedBy(Auth::user()))
                                        <span>Shared by {{ $proposalDraft->owner->name }}</span>
                                    @endunless
                                    <span>Edited {{ $proposalDraft->updated_at->diffForHumans() }}</span>
                                </p>
                                @if ($proposalDraft->researchCall)
                                    <p class="text-sm leading-5 text-slate-600 dark:text-slate-400">{{ $proposalDraft->researchCall->title }}</p>
                                @endif
                            </div>
                        </div>
                        <div>
                            <p class="flex items-center gap-1.5 text-sm font-medium text-brand dark:text-red-300"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>Papers ready</p>
                            <p class="mt-1 text-sm font-semibold text-brand dark:text-red-300">{{ $progress['completed'] }} of {{ $progress['total'] }} <span class="sr-only">papers ready</span></p>
                        </div>
                        <div class="flex flex-wrap items-center gap-3">
                            <a href="{{ route('faculty.proposal-drafts.show', $proposalDraft) }}" class="dashboard-action">
                                Resume draft
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6" /></svg>
                            </a>
                            @if ($draftReviewChecks->has($proposalDraft->id))
                                <x-dashboard-assistant-action :item="$draftReviewChecks->get($proposalDraft->id)" label="Improve this draft" variant="outline" :help="true" />
                            @endif
                        </div>
                    </article>
                @empty
                    <div class="px-6 py-8 text-center">
                        <div class="mx-auto flex h-11 w-11 items-center justify-center rounded-full bg-brand text-white dark:bg-red-800">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                        </div>
                        <h4 class="mt-4 text-sm font-semibold text-slate-950 dark:text-white">No proposal drafts yet</h4>
                        <p class="mx-auto mt-1 max-w-md text-sm leading-5 text-slate-500 dark:text-slate-400">Start a proposal and prepare the required papers.</p>
                        <a href="{{ route('faculty.proposal-drafts.create') }}" class="mt-4 dashboard-action">Create first proposal</a>
                    </div>
                @endforelse
            </div>
        </section>
        @endif

        </div>
    </div>
</x-app-layout>
