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
        <x-page-header variant="hero"
            eyebrow="Faculty workspace"
            title="Dashboard"
            subtitle="Your proposals and next actions."
        >
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

    <div class="space-y-5" data-dashboard-palette="red-black-white" data-dashboard-layout="faculty-overview">
        @include('faculty.partials.research-call-carousel', ['researchCallCarouselItems' => $researchCallCarouselItems])

        <section aria-labelledby="proposal-overview-heading">
            <h2 id="proposal-overview-heading" class="sr-only">Proposal overview</h2>
            <p class="sr-only">A quick view of your research pipeline.</p>
            <dl class="grid grid-cols-2 gap-4 xl:grid-cols-4">
                @foreach ([
                    ['Needs revision', $revisionRequestedTopics->count(), 'Changes requested', route('faculty.submissions', ['category' => 'active', 'status' => 'revision_requested']), 'M12 9v4m0 3h.01M12 3 2 20h20L12 3Z'],
                    ['Drafts', $proposalDraftCount, 'Proposal preparation', route('faculty.proposal-drafts.index'), 'M4 4h10l6 6v10H4V4Zm10 0v6h6M8 14h8M8 17h5'],
                    ['Submitted', $topics->count(), 'Across all statuses', route('faculty.submissions', ['category' => 'all']), 'M4 4h16v16H4V4Zm4 5h8M8 12h8M8 15h5'],
                    ['Under review', $underReviewTopics->count(), 'Review and final approval', route('faculty.submissions', ['category' => 'active', 'status' => 'review']), 'M12 6v6l4 2m5-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'],
                ] as [$label, $count, $description, $href, $icon])
                    <x-dashboard-stat :label="$label" :value="$count" :description="$description" :icon="$icon" :href="$href" tone="brand" :priority="$label === 'Needs revision' && $count > 0" />
                @endforeach
            </dl>
        </section>
        @if (session('success'))
            <div class="flex items-start gap-3 rounded-3xl border border-stone-200 bg-white px-4 py-3 text-sm text-gray-700 shadow-bubble-sm dark:border-stone-700 dark:bg-stone-900 dark:text-gray-300">
                <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-[#7A0019] dark:bg-red-400" aria-hidden="true"></span>
                <p class="font-semibold">{{ session('success') }}</p>
            </div>
        @endif

        @if ($errors->resubmission->any())
            <div class="rounded-3xl border border-stone-200 border-l-4 border-l-[#7A0019] bg-white px-4 py-3 text-sm text-[#7A0019] dark:border-stone-700 dark:border-l-red-400 dark:bg-stone-900 dark:text-red-200">
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
            <section data-revision-worklist aria-labelledby="revision-worklist-heading" class="space-y-3">
                <div class="flex items-center gap-3">
                    <h2 id="revision-worklist-heading" class="text-base font-semibold text-brand dark:text-red-300">Revisions to address</h2>
                    <span class="flex h-6 min-w-6 items-center justify-center rounded-full bg-brand px-2 text-xs font-semibold tabular-nums text-white dark:bg-red-800">{{ $revisionRequestedTopics->count() }}</span>
                </div>
                <p class="text-xs leading-5 text-stone-600 dark:text-stone-400">Address the requested changes, or ask Athena to help you plan your revisions.</p>
                <div class="overflow-hidden rounded-xl border border-stone-200 bg-white dark:border-stone-700 dark:bg-stone-900">
                    <div class="divide-y divide-stone-200 dark:divide-stone-700">
                        @foreach ($revisionRequestedTopics as $topic)
                            @php
                                $latestRevisionReview = $topic->reviews->where('decision', 'revision_requested')->last();
                                $revisionDeadline = $topic->review_stage !== 'lrec' ? $topic->researchCall?->paper_revisions_end_date : null;
                            @endphp
                            <article data-revision-task data-topic-id="{{ $topic->id }}" class="min-w-0 p-5">
                                <div class="flex min-w-0 flex-col items-start gap-2">
                                    <h3 class="max-w-prose break-words text-base font-semibold leading-6 text-gray-950 dark:text-white">{{ $topic->title }}</h3>
                                    <p class="text-xs leading-5 text-gray-600 dark:text-gray-400">{{ $topic->researchCall?->title ?? 'Independent submission' }}</p>
                                    @if ($revisionDeadline)
                                        <x-dashboard-deadline :date="$revisionDeadline->copy()->endOfDay()" />
                                    @endif
                                    <div data-reviewer-feedback class="mt-1 min-w-0">
                                        <p class="text-xs font-semibold text-stone-700 dark:text-stone-300">Requested change</p>
                                        <p class="mt-1 max-w-prose whitespace-pre-line break-words text-sm leading-6 text-stone-700 dark:text-stone-300">{{ $latestRevisionReview?->comment ?: 'Open the revision page to see the requested changes.' }}</p>
                                    </div>
                                    <div class="mt-2 flex flex-wrap items-center gap-3">
                                        <a href="{{ route('faculty.topics.revision', $topic) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-brand px-4 py-2 text-xs font-semibold text-white hover:bg-brand-soft focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand dark:bg-red-700 dark:hover:bg-red-600 dark:focus-visible:outline-red-400">Revise and resubmit proposal</a>
                                        @if ($revisionReviewChecks->has($topic->id))
                                            <x-dashboard-assistant-action :item="$revisionReviewChecks->get($topic->id)" label="Get revision help" variant="outline" :help="true" />
                                        @endif
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        @if (! $isFacultyResearcher)
            <section id="recent-drafts" aria-labelledby="recent-drafts-heading">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 id="recent-drafts-heading" class="text-base font-semibold text-brand dark:text-red-300">Drafts</h2>
                    <p class="mt-1 text-xs text-stone-600 dark:text-stone-400">Resume one of your recent drafts.</p>
                </div>
                <a href="{{ route('faculty.proposal-drafts.index') }}" class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-lg border border-stone-300 bg-white px-4 py-2 text-xs font-semibold text-brand transition-colors hover:border-brand hover:bg-stone-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand dark:border-stone-600 dark:bg-stone-900 dark:text-red-300 dark:hover:border-red-400 dark:hover:bg-stone-800 dark:focus-visible:outline-red-400">View all drafts</a>
            </div>

            <div data-dashboard-draft-list class="mt-3 divide-y divide-stone-200 overflow-hidden rounded-xl border border-stone-200 bg-white dark:divide-stone-700 dark:border-stone-700 dark:bg-stone-900">
                @forelse ($recentProposalDrafts->take(2) as $proposalDraft)
                    @php
                        $progress = $proposalDraftProgress->get($proposalDraft->getKey());
                    @endphp
                    <article data-dashboard-draft data-draft-id="{{ $proposalDraft->id }}" class="grid min-w-0 gap-4 p-5 lg:grid-cols-[minmax(0,1fr)_8rem_auto] lg:items-center">
                        <div class="min-w-0 space-y-2">
                            <h3 class="break-words text-sm font-semibold leading-6 text-stone-950 dark:text-white">{{ $proposalDraft->project_title ?: 'Untitled proposal' }}</h3>
                            <p class="flex flex-wrap gap-x-3 gap-y-1 text-xs leading-5 text-stone-600 dark:text-stone-400">
                                <span>{{ $proposalDraft->isOwnedBy(Auth::user()) ? 'Project leader' : 'Project staff' }}</span>
                                @unless ($proposalDraft->isOwnedBy(Auth::user()))
                                    <span>Shared by {{ $proposalDraft->owner->name }}</span>
                                @endunless
                                <span>Edited {{ $proposalDraft->updated_at->diffForHumans() }}</span>
                            </p>
                            @if ($proposalDraft->researchCall)
                                <p class="text-xs leading-5 text-stone-600 dark:text-stone-400">{{ $proposalDraft->researchCall->title }}</p>
                            @endif
                        </div>
                        <div>
                            <p class="text-xs font-medium text-stone-600 dark:text-stone-400">Papers ready</p>
                            <p class="mt-1 text-sm font-semibold text-brand dark:text-red-300">{{ $progress['completed'] }} of {{ $progress['total'] }} <span class="sr-only">papers ready</span></p>
                        </div>
                        <div class="flex flex-wrap items-center gap-3">
                            <a href="{{ route('faculty.proposal-drafts.show', $proposalDraft) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-brand px-4 py-2 text-xs font-semibold text-white transition-colors hover:bg-brand-soft focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand dark:bg-red-700 dark:hover:bg-red-600 dark:focus-visible:outline-red-400">
                                Resume draft
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
                        <h4 class="mt-4 text-sm font-semibold text-gray-950 dark:text-white">No proposal drafts yet</h4>
                        <p class="mx-auto mt-1 max-w-md text-xs leading-5 text-gray-500 dark:text-gray-400">Start a proposal and prepare the required papers.</p>
                                                    <a href="{{ route('faculty.proposal-drafts.create') }}" class="mt-4 inline-flex items-center justify-center rounded-xl bg-[#7A0019] px-4 py-2.5 text-xs font-semibold text-white transition hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-[#7A0019] focus:ring-offset-2 dark:bg-red-700 dark:hover:bg-red-600 dark:focus:ring-red-400 dark:focus:ring-offset-gray-950">Create first proposal</a>
                    </div>
                @endforelse
            </div>
        </section>
        @endif

        </div>
    </div>
</x-app-layout>
