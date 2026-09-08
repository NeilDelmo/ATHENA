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
            'resubmitted',
            'ready_for_signature',
        ]);
    @endphp

    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="max-w-2xl">
                <h2 class="text-2xl font-black tracking-tight text-gray-950 dark:text-white">Faculty research</h2>
                <p class="mt-1 text-sm leading-6 text-gray-600 dark:text-gray-400">
                    Welcome back, <span class="font-bold text-gray-950 dark:text-white">{{ Auth::user()->name }}</span>.
                    Manage your drafts, review feedback, and submitted proposals.
                </p>
            </div>

            <div class="flex w-full sm:w-auto">
                                    <a href="{{ route('faculty.proposal-drafts.create') }}" class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-[#7A0019] px-4 py-2.5 text-xs font-black text-white shadow-sm transition hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-[#7A0019] focus:ring-offset-2 dark:bg-red-700 dark:hover:bg-red-600 dark:focus:ring-red-400 dark:focus:ring-offset-gray-950 sm:w-auto">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                        New proposal
                    </a>
            </div>
        </div>
    </x-slot>

    <div class="space-y-5" data-dashboard-palette="red-black-white">
        @if (session('success'))
            <div class="flex items-start gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-700 shadow-sm dark:border-gray-800 dark:bg-gray-950 dark:text-gray-300">
                <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-[#7A0019] dark:bg-red-400" aria-hidden="true"></span>
                <p class="font-semibold">{{ session('success') }}</p>
            </div>
        @endif

        @if ($errors->resubmission->any())
            <div class="rounded-xl border border-red-200 border-l-4 border-l-[#7A0019] bg-red-50 px-4 py-3 text-sm text-[#7A0019] dark:border-red-950 dark:border-l-red-500 dark:bg-red-950/30 dark:text-red-200">
                <p class="font-black">Please review your submission.</p>
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
                            <h3 id="action-required-heading" class="text-sm font-black text-gray-950 dark:text-white">Action required</h3>
                            <p class="mt-0.5 text-xs text-gray-600 dark:text-gray-300">{{ $revisionRequestedTopics->count() }} {{ str('proposal')->plural($revisionRequestedTopics->count()) }} {{ $revisionRequestedTopics->count() === 1 ? 'needs' : 'need' }} your attention.</p>
                        </div>
                    </div>
                    <span class="w-fit rounded-full bg-white px-3 py-1 text-[10px] font-black uppercase tracking-wider text-[#7A0019] ring-1 ring-inset ring-red-200 dark:bg-red-950/50 dark:text-red-200 dark:ring-red-900">Needs revision</span>
                </div>

                <div class="divide-y divide-red-200 dark:divide-red-950">
                    @foreach ($revisionRequestedTopics as $topic)
                        @php
                            $latestRevisionReview = $topic->reviews->where('decision', 'revision_requested')->last() ?? $topic->reviews->last();
                        @endphp
                        <article class="grid gap-4 px-5 py-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center">
                            <div class="min-w-0">
                                <h4 class="truncate text-sm font-black text-gray-950 dark:text-white">{{ $topic->title }}</h4>
                                <p class="mt-1 truncate text-xs text-gray-500 dark:text-gray-400">{{ $topic->researchCall?->title ?? 'Independent submission' }}</p>
                                <p class="mt-2 line-clamp-2 text-xs leading-5 text-gray-700 dark:text-gray-300">{{ $latestRevisionReview?->comment ?: 'The Research Office requested changes to this proposal.' }}</p>
                            </div>
                            <a href="{{ route('topics.show', $topic) }}#proposal-review" class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-[#7A0019] px-4 py-2.5 text-xs font-black text-white transition hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-[#7A0019] focus:ring-offset-2 dark:bg-red-700 dark:hover:bg-red-600 dark:focus:ring-red-400 dark:focus:ring-offset-red-950">
                                Revise and resubmit proposal
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6" /></svg>
                            </a>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        <livewire:dashboard-calendar />

        @if (false)
            <section aria-labelledby="research-call-posters-heading">
                <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-red-600 dark:text-red-400">Stay informed</p>
                        <h3 id="research-call-posters-heading" class="mt-1 text-lg font-black text-gray-900 dark:text-white">Research calls</h3>
                        <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">Browse the latest calls for proposals from the Research Office.</p>
                    </div>
                    <span class="self-start rounded-full bg-gray-100 px-3 py-1 text-[11px] font-black text-gray-600 dark:bg-slate-800 dark:text-slate-300 sm:self-auto">
                        {{ $researchCallPosters->count() }} {{ Str::plural('poster', $researchCallPosters->count()) }}
                    </span>
                </div>

                <div data-research-call-carousel class="relative isolate overflow-hidden rounded-3xl border border-gray-200/70 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900" aria-roledescription="carousel" aria-label="Research call posters">
                    <img src="{{ asset('images/maingate.jpg') }}" alt="" class="pointer-events-none absolute inset-0 h-full w-full object-cover opacity-[0.13] grayscale dark:opacity-[0.08]" aria-hidden="true">
                    <div class="pointer-events-none absolute inset-0 bg-white/90 dark:bg-slate-950/90" aria-hidden="true"></div>

                    <div class="relative p-4 sm:p-6">
                        <div class="relative min-h-[17rem] sm:min-h-[20rem]">
                            @foreach ($researchCallPosters as $researchCallPoster)
                                @php
                                    $posterImageUrl = route('research-calls.reference-image', $researchCallPoster);
                                    $submissionWindow = collect([
                                        $researchCallPoster->opens_at?->format('M d, Y'),
                                        $researchCallPoster->closes_at?->format('M d, Y'),
                                    ])->filter()->implode(' – ');
                                @endphp
                                <article
                                    data-research-call-slide
                                    class="{{ $loop->first ? '' : 'hidden' }} h-full"
                                    aria-hidden="{{ $loop->first ? 'false' : 'true' }}"
                                    aria-roledescription="slide"
                                    aria-label="{{ $loop->iteration }} of {{ $researchCallPosters->count() }}"
                                >
                                    <div class="grid h-full items-center gap-6 md:grid-cols-[minmax(12rem,19rem)_minmax(0,1fr)] lg:grid-cols-[minmax(15rem,23rem)_minmax(0,1fr)]">
                                        <div class="relative mx-auto block w-full max-w-sm overflow-hidden rounded-2xl border border-gray-200 bg-gray-100 p-1.5 shadow-lg shadow-gray-900/10 dark:border-slate-700 dark:bg-slate-800">
                                            <span class="relative block aspect-[4/3] overflow-hidden rounded-xl bg-white dark:bg-slate-950">
                                                <img src="{{ $posterImageUrl }}" alt="{{ $researchCallPoster->title }}" class="h-full w-full object-contain" loading="{{ $loop->first ? 'eager' : 'lazy' }}" decoding="async">
                                            </span>
                                        </div>

                                        <div class="max-w-2xl px-2 md:px-0">
                                            <span class="inline-flex rounded-full bg-red-50 px-3 py-1 text-[10px] font-black uppercase tracking-[0.16em] text-red-700 dark:bg-red-950/50 dark:text-red-200">Research call</span>
                                            <h4 class="mt-4 text-xl font-black leading-tight text-gray-900 dark:text-white sm:text-2xl">{{ $researchCallPoster->title }}</h4>
                                            @if ($researchCallPoster->term || $researchCallPoster->academic_year)
                                                <p class="mt-2 text-xs font-bold text-gray-500 dark:text-slate-400">
                                                    {{ collect([$researchCallPoster->term, $researchCallPoster->academic_year])->filter()->implode(' · ') }}
                                                </p>
                                            @endif
                                            <p class="mt-4 line-clamp-4 whitespace-pre-line text-sm leading-6 text-gray-600 dark:text-slate-300">{{ $researchCallPoster->description ?: 'Review the poster for the eligibility requirements, submission details, and important dates.' }}</p>
                                            @if ($submissionWindow !== '')
                                                <p class="mt-5 text-xs font-black text-gray-700 dark:text-slate-200">Submission window: <span class="font-semibold text-gray-500 dark:text-slate-400">{{ $submissionWindow }}</span></p>
                                            @endif
                                            <div class="mt-6 flex flex-wrap gap-3">
                                                <a href="{{ route('faculty.proposal-drafts.create') }}" class="inline-flex items-center justify-center rounded-xl bg-red-600 px-4 py-2.5 text-xs font-black text-white shadow-sm shadow-red-600/20 transition hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 dark:focus:ring-offset-slate-900">Start a proposal</a>
                                                <a href="#recent-drafts" class="inline-flex items-center justify-center rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-xs font-bold text-gray-700 transition hover:border-red-200 hover:bg-red-50 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-red-800 dark:hover:bg-red-950/40 dark:hover:text-red-300 dark:focus:ring-offset-slate-900">Continue a draft</a>
                                            </div>
                                        </div>
                                    </div>
                                </article>
                            @endforeach

                            @if ($researchCallPosters->count() > 1)
                                <button type="button" data-research-call-previous class="group absolute left-0 top-1/2 inline-flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full border border-gray-200 bg-white/90 text-gray-500 shadow-md backdrop-blur-sm transition duration-200 hover:scale-125 hover:border-red-200 hover:bg-red-50 hover:text-red-600 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 dark:border-slate-700 dark:bg-slate-900/90 dark:text-slate-300 dark:hover:border-red-800 dark:hover:bg-red-950/60 dark:hover:text-red-300 dark:focus:ring-offset-slate-900" aria-label="Show previous research call">
                                    <svg class="h-5 w-5 transition-transform group-hover:-translate-x-0.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m15 18-6-6 6-6" /></svg>
                                </button>
                                <button type="button" data-research-call-next class="group absolute right-0 top-1/2 inline-flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full border border-gray-200 bg-white/90 text-gray-500 shadow-md backdrop-blur-sm transition duration-200 hover:scale-125 hover:border-red-200 hover:bg-red-50 hover:text-red-600 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 dark:border-slate-700 dark:bg-slate-900/90 dark:text-slate-300 dark:hover:border-red-800 dark:hover:bg-red-950/60 dark:hover:text-red-300 dark:focus:ring-offset-slate-900" aria-label="Show next research call">
                                    <svg class="h-5 w-5 transition-transform group-hover:translate-x-0.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6" /></svg>
                                </button>
                            @endif
                        </div>

                        @if ($researchCallPosters->count() > 1)
                            <div class="mt-5 flex items-center justify-center gap-1.5" aria-label="Choose a research call poster">
                                @foreach ($researchCallPosters as $researchCallPoster)
                                    <button type="button" data-research-call-indicator class="h-1.5 w-7 rounded-full transition-colors {{ $loop->first ? 'bg-red-600 dark:bg-red-500' : 'bg-gray-300 dark:bg-slate-700' }}" aria-label="Show {{ $researchCallPoster->title }}" aria-current="{{ $loop->first ? 'true' : 'false' }}"></button>
                                @endforeach
                            </div>
                            <p class="mt-3 text-center text-[10px] font-semibold text-gray-400 dark:text-slate-500">Posters rotate automatically. Use the arrows to browse.</p>
                        @endif
                    </div>

                </div>
            </section>
        @elseif (false)
            <section class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-gray-950 via-gray-900 to-red-950 px-6 py-8 text-white shadow-xl shadow-gray-950/10 sm:px-8 sm:py-10" aria-labelledby="research-call-posters-heading">
                <div class="pointer-events-none absolute -right-12 -top-20 h-56 w-56 rounded-full border-[36px] border-white/[0.04]" aria-hidden="true"></div>
                <div class="pointer-events-none absolute -bottom-16 right-36 h-40 w-40 rounded-full bg-red-500/10 blur-2xl" aria-hidden="true"></div>
                <div class="relative max-w-2xl">
                    <span class="inline-flex rounded-full border border-red-400/25 bg-red-500/10 px-3 py-1 text-[10px] font-black uppercase tracking-[0.18em] text-red-200">Research workspace</span>
                    <h3 id="research-call-posters-heading" class="mt-4 text-2xl font-black tracking-tight sm:text-3xl">No research call poster has been uploaded yet.</h3>
                    <p class="mt-3 max-w-xl text-sm leading-6 text-gray-300">Continue a saved draft while the Research Office prepares the next call. New proposals remain unavailable until its submission window opens.</p>
                    <div class="mt-6 flex flex-wrap gap-3">
                        <a href="{{ route('research-calls.index') }}" class="inline-flex items-center justify-center rounded-xl bg-white px-4 py-2.5 text-xs font-black text-gray-950 transition hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-gray-950">View announcements</a>
                        <a href="#recent-drafts" class="inline-flex items-center justify-center rounded-xl border border-white/20 bg-white/5 px-4 py-2.5 text-xs font-bold text-white transition hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-gray-950">Continue a draft</a>
                    </div>
                </div>
            </section>
        @endif

        <section aria-labelledby="proposal-overview-heading">
            <div>
                <h3 id="proposal-overview-heading" class="text-sm font-black text-gray-950 dark:text-white">Proposal overview</h3>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">A quick view of your research pipeline.</p>
            </div>

            <dl class="mt-3 grid grid-cols-2 gap-3 lg:grid-cols-4">
                @foreach ([
                    ['Drafts', $proposalDraftCount],
                    ['Submitted', $topics->count()],
                    ['Under review', $underReviewTopics->count()],
                    ['Needs revision', $revisionRequestedTopics->count()],
                ] as [$label, $count])
                    <div class="rounded-xl border bg-white px-4 py-3 shadow-sm dark:bg-gray-950 {{ $label === 'Needs revision' && $count > 0 ? 'border-red-200 dark:border-red-950' : 'border-gray-200 dark:border-gray-800' }}">
                        <dt class="text-xs font-bold {{ $label === 'Needs revision' && $count > 0 ? 'text-[#7A0019] dark:text-red-300' : 'text-gray-500 dark:text-gray-400' }}">{{ $label }}</dt>
                        <dd class="mt-1 text-2xl font-black tracking-tight text-gray-950 dark:text-white">{{ $count }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>

        @if (! $isFacultyResearcher)
            <section id="recent-drafts" aria-labelledby="recent-drafts-heading">
            <div class="flex items-end justify-between gap-4">
                <div>
                    <h3 id="recent-drafts-heading" class="text-base font-black text-gray-950 dark:text-white">Continue working</h3>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Your two most recently edited proposal packages.</p>
                </div>
                <a href="{{ route('faculty.proposal-drafts.index') }}" class="shrink-0 text-xs font-black text-[#7A0019] transition hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-[#7A0019] focus:ring-offset-2 dark:text-red-300 dark:hover:text-red-200 dark:focus:ring-red-400 dark:focus:ring-offset-gray-950">View all drafts</a>
            </div>

            <div class="mt-3 grid gap-3 md:grid-cols-2">
                @forelse ($recentProposalDrafts->take(2) as $proposalDraft)
                    @php
                        $progress = $proposalDraftProgress->get($proposalDraft->getKey());
                    @endphp
                    <article class="group flex flex-col rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition-colors hover:bg-gray-50/80 dark:border-gray-800 dark:bg-gray-950 dark:hover:bg-gray-900/50">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex flex-wrap gap-2">
                                <span class="rounded-full bg-gray-950 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-white dark:bg-white dark:text-gray-950">Draft</span>
                                <span class="rounded-full bg-gray-100 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-gray-600 ring-1 ring-inset ring-gray-200 dark:bg-gray-900 dark:text-gray-300 dark:ring-gray-800">{{ $proposalDraft->isOwnedBy(Auth::user()) ? 'Owner' : 'Collaborator' }}</span>
                            </div>
                            <span class="shrink-0 text-[11px] font-medium text-gray-400">Edited {{ $proposalDraft->updated_at->diffForHumans() }}</span>
                        </div>

                        <h4 class="mt-4 line-clamp-2 text-base font-black leading-6 text-gray-950 dark:text-white">{{ $proposalDraft->project_title ?: 'Untitled proposal' }}</h4>
                        <p class="mt-1 line-clamp-1 text-xs text-gray-500 dark:text-gray-400">{{ $proposalDraft->researchCall?->title ?? 'Draft in progress' }}</p>
                        @unless ($proposalDraft->isOwnedBy(Auth::user()))
                            <p class="mt-1 text-[11px] font-semibold text-gray-400">Shared by {{ $proposalDraft->owner->name }}</p>
                        @endunless

                        <div class="mt-5">
                            <div class="flex items-center justify-between text-[11px] font-bold text-gray-600 dark:text-gray-300">
                                <span>Package progress</span>
                                <span>{{ $progress['completed'] }}/{{ $progress['total'] }} papers</span>
                            </div>
                            <progress value="{{ $progress['percentage'] }}" max="100" class="mt-2 h-2 w-full overflow-hidden rounded-full accent-[#7A0019]" aria-label="{{ $progress['percentage'] }} percent complete">{{ $progress['percentage'] }}%</progress>
                        </div>

                        <div class="mt-auto flex items-center justify-between gap-4 pt-5">
                            <span class="text-[11px] font-bold text-gray-400">{{ $progress['percentage'] }}% complete</span>
                            <a href="{{ route('faculty.proposal-drafts.show', $proposalDraft) }}" class="inline-flex items-center gap-1.5 rounded-xl bg-gray-950 px-4 py-2.5 text-xs font-black text-white transition hover:bg-[#7A0019] focus:outline-none focus:ring-2 focus:ring-[#7A0019] focus:ring-offset-2 dark:bg-white dark:text-gray-950 dark:hover:bg-red-200 dark:focus:ring-red-400 dark:focus:ring-offset-gray-950">
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
                        <h4 class="mt-4 text-sm font-black text-gray-950 dark:text-white">No proposal drafts yet</h4>
                        <p class="mx-auto mt-1 max-w-md text-xs leading-5 text-gray-500 dark:text-gray-400">Start a proposal and complete each required paper. You can submit anytime.</p>
                                                    <a href="{{ route('faculty.proposal-drafts.create') }}" class="mt-4 inline-flex items-center justify-center rounded-xl bg-[#7A0019] px-4 py-2.5 text-xs font-black text-white transition hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-[#7A0019] focus:ring-offset-2 dark:bg-red-700 dark:hover:bg-red-600 dark:focus:ring-red-400 dark:focus:ring-offset-gray-950">Create first proposal</a>
                    </div>
                @endforelse
            </div>
        </section>
        @endif

        <section aria-labelledby="submitted-proposals-heading" class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-950">
            <div class="flex items-center justify-between gap-4 border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                <div>
                    <h3 id="submitted-proposals-heading" class="text-base font-black text-gray-950 dark:text-white">Submitted proposals</h3>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Status and submission details at a glance.</p>
                </div>
                <p class="shrink-0 text-xs font-bold text-gray-500 dark:text-gray-400">{{ $topics->count() }} {{ str('proposal')->plural($topics->count()) }}</p>
            </div>
            @forelse ($topics as $topic)
                @php
                    $statusStyle = match (true) {
                        $topic->status === 'approved' && $topic->isMonitoringAvailable() => 'bg-gray-950 text-white ring-gray-950 dark:bg-white dark:text-gray-950 dark:ring-white',
                        $topic->status === 'approved' && ! $topic->isMonitoringAvailable() => 'bg-red-50 text-[#7A0019] ring-red-200 dark:bg-red-950/40 dark:text-red-200 dark:ring-red-900',
                        $topic->status === 'rejected' => 'bg-gray-100 text-gray-600 ring-gray-200 dark:bg-gray-900 dark:text-gray-400 dark:ring-gray-800',
                        in_array($topic->status, ['revision_requested', 'ready_for_signature'], true) => 'bg-red-50 text-[#7A0019] ring-red-200 dark:bg-red-950/40 dark:text-red-200 dark:ring-red-900',
                        default => 'bg-gray-100 text-gray-700 ring-gray-200 dark:bg-gray-900 dark:text-gray-300 dark:ring-gray-800',
                    };
                    $statusLabel = $topic->workflowStatusLabel();
                @endphp
                <article class="border-b border-gray-200 px-5 py-4 transition-colors last:border-b-0 hover:bg-gray-50/80 dark:border-gray-800 dark:hover:bg-gray-900/40">
                    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_140px] lg:items-center">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h4 class="text-sm font-black text-gray-950 dark:text-white">{{ $topic->title }}</h4>
                                <span class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-wider ring-1 ring-inset {{ $statusStyle }}">{{ $statusLabel }}</span>
                            </div>
                            <p class="mt-1 truncate text-xs text-gray-500 dark:text-gray-400">{{ $topic->researchCall?->title ?? 'Independent submission' }} <span class="text-gray-300 dark:text-gray-700">·</span> Submitted {{ $topic->created_at->diffForHumans() }}</p>
                        </div>

                        <div class="flex">
                            <a href="{{ route('topics.show', $topic) }}" class="inline-flex items-center justify-center rounded-xl bg-gray-950 px-3 py-2.5 text-xs font-black text-white transition hover:bg-[#7A0019] focus:outline-none focus:ring-2 focus:ring-[#7A0019] focus:ring-offset-2 dark:bg-white dark:text-gray-950 dark:hover:bg-red-200 dark:focus:ring-red-400 dark:focus:ring-offset-gray-950">Open workspace</a>
                        </div>
                    </div>

                    @if (false)
                        <details class="hidden" @if ($isCurrentResubmission && $errors->resubmission->any()) open @endif>
                            <summary class="cursor-pointer text-sm font-black text-[#7A0019] dark:text-red-300">Revise and resubmit proposal</summary>
                            <form action="{{ route('faculty.topics.resubmit', $topic) }}" method="POST" enctype="multipart/form-data" class="mt-4 grid gap-4 sm:grid-cols-2">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="resubmitting_topic_id" value="{{ $topic->id }}">

                                @if ($isCurrentResubmission && $errors->resubmission->any())
                                    <div class="rounded-xl border border-red-200 bg-red-50 p-3 text-xs text-red-700 sm:col-span-2">
                                        <ul class="list-disc space-y-1 pl-4">
                                            @foreach ($errors->resubmission->all() as $error)
                                                <li>{{ $error }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif

                                <div class="space-y-1 sm:col-span-2">
                                    <label for="revision_title_{{ $topic->id }}" class="text-xs font-bold text-gray-600 dark:text-gray-300">Proposal title</label>
                                    <input id="revision_title_{{ $topic->id }}" name="title" type="text" value="{{ $isCurrentResubmission ? old('title') : $topic->title }}" required class="block w-full rounded-xl border-gray-300 bg-white text-sm text-gray-950 shadow-sm focus:border-[#7A0019] focus:ring-[#7A0019] dark:border-gray-700 dark:bg-gray-950 dark:text-white dark:focus:border-red-400 dark:focus:ring-red-400">
                                </div>
                                <div class="space-y-1 sm:col-span-2">
                                    <label for="revision_description_{{ $topic->id }}" class="text-xs font-bold text-gray-600 dark:text-gray-300">Description</label>
                                    <textarea id="revision_description_{{ $topic->id }}" name="description" rows="3" class="block w-full rounded-xl border-gray-300 bg-white text-sm text-gray-950 shadow-sm focus:border-[#7A0019] focus:ring-[#7A0019] dark:border-gray-700 dark:bg-gray-950 dark:text-white dark:focus:border-red-400 dark:focus:ring-red-400">{{ $isCurrentResubmission ? old('description') : $topic->description }}</textarea>
                                </div>
                                <div class="space-y-1">
                                    <label for="revision_budget_{{ $topic->id }}" class="text-xs font-bold text-gray-600 dark:text-gray-300">Total project cost (PHP)</label>
                                    <input id="revision_budget_{{ $topic->id }}" name="estimated_budget" type="number" value="{{ $isCurrentResubmission ? old('estimated_budget') : $topic->estimated_budget }}" min="0" max="{{ ($topic->researchCall?->budgetCeiling() ?? \App\Models\ResearchCall::MAXIMUM_BUDGET) }}" step="0.01" required class="block w-full rounded-xl border-gray-300 bg-white text-sm text-gray-950 shadow-sm focus:border-[#7A0019] focus:ring-[#7A0019] dark:border-gray-700 dark:bg-gray-950 dark:text-white dark:focus:border-red-400 dark:focus:ring-red-400">
                                    <p class="text-[10px] text-gray-400">Maximum: PHP {{ number_format(($topic->researchCall?->budgetCeiling() ?? \App\Models\ResearchCall::MAXIMUM_BUDGET), 2) }}</p>
                                </div>
                                <div class="space-y-1">
                                    <label for="revision_duration_{{ $topic->id }}" class="text-xs font-bold text-gray-600 dark:text-gray-300">Total project duration (months)</label>
                                    <input id="revision_duration_{{ $topic->id }}" name="estimated_duration_months" type="number" value="{{ $isCurrentResubmission ? old('estimated_duration_months') : $topic->estimated_duration_months }}" min="1" max="120" required class="block w-full rounded-xl border-gray-300 bg-white text-sm text-gray-950 shadow-sm focus:border-[#7A0019] focus:ring-[#7A0019] dark:border-gray-700 dark:bg-gray-950 dark:text-white dark:focus:border-red-400 dark:focus:ring-red-400">
                                </div>
                                <div class="space-y-1 sm:col-span-2">
                                    <label for="change_summary_{{ $topic->id }}" class="text-xs font-bold text-gray-600 dark:text-gray-300">Revision summary</label>
                                    <textarea id="change_summary_{{ $topic->id }}" name="change_summary" rows="2" maxlength="2000" class="block w-full rounded-xl border-gray-300 bg-white text-sm text-gray-950 shadow-sm focus:border-[#7A0019] focus:ring-[#7A0019] dark:border-gray-700 dark:bg-gray-950 dark:text-white dark:focus:border-red-400 dark:focus:ring-red-400" placeholder="Briefly explain what changed in this version.">{{ $isCurrentResubmission ? old('change_summary') : '' }}</textarea>
                                </div>
                                <div class="border-l-2 border-[#7A0019] bg-white p-3 text-xs leading-5 text-gray-700 dark:border-red-500 dark:bg-gray-950 dark:text-gray-300 sm:col-span-2">
                                    Upload only the files you changed. Files left empty will be carried forward from the previous version; uploading CVs replaces the previous CV set.
                                </div>
                                @if ($pendingFileRevisions->isNotEmpty())
                                    <div class="rounded-xl border border-red-200 bg-red-50 p-3 text-xs text-[#7A0019] dark:border-red-950 dark:bg-red-950/30 dark:text-red-200 sm:col-span-2">
                                        <p class="font-black uppercase tracking-wider">Files specifically marked for revision</p>
                                        <div class="mt-2 space-y-2">@foreach ($pendingFileRevisions as $fileRevision)<div><span class="font-bold">{{ $fileRevision->file?->label() ?? str($fileRevision->document_type)->replace('_', ' ')->title() }}:</span> {{ $fileRevision->original_filename }}@if ($fileRevision->revision_note)<p class="pl-2 text-[11px] text-red-700 dark:text-red-300">{{ $fileRevision->revision_note }}</p>@endif</div>@endforeach</div>
                                    </div>
                                @endif
                                <div class="space-y-1">
                                    <label for="revision_detailed_{{ $topic->id }}" class="text-xs font-bold text-gray-600 dark:text-gray-300">Detailed proposal</label>
                                    <input id="revision_detailed_{{ $topic->id }}" name="detailed_proposal" type="file" accept=".doc,.docx,.pdf" @required($requiredRevisionTypes->contains('detailed_proposal')) class="block w-full rounded-xl border border-gray-300 bg-white p-2 text-xs text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-gray-950 file:px-3 file:py-1.5 file:font-bold file:text-white dark:border-gray-700 dark:bg-gray-950 dark:text-gray-300 dark:file:bg-white dark:file:text-gray-950">
                                </div>
                                <div class="space-y-1">
                                    <label for="revision_work_plan_{{ $topic->id }}" class="text-xs font-bold text-gray-600 dark:text-gray-300">Work plan</label>
                                    <input id="revision_work_plan_{{ $topic->id }}" name="work_plan" type="file" accept=".doc,.docx,.pdf" @required($requiredRevisionTypes->contains('work_plan')) class="block w-full rounded-xl border border-gray-300 bg-white p-2 text-xs text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-gray-950 file:px-3 file:py-1.5 file:font-bold file:text-white dark:border-gray-700 dark:bg-gray-950 dark:text-gray-300 dark:file:bg-white dark:file:text-gray-950">
                                </div>
                                <div class="space-y-1">
                                    <label for="revision_budget_file_{{ $topic->id }}" class="text-xs font-bold text-gray-600 dark:text-gray-300">Line-item budget</label>
                                    <input id="revision_budget_file_{{ $topic->id }}" name="line_item_budget" type="file" accept=".doc,.docx,.pdf" @required($requiredRevisionTypes->contains('line_item_budget')) class="block w-full rounded-xl border border-gray-300 bg-white p-2 text-xs text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-gray-950 file:px-3 file:py-1.5 file:font-bold file:text-white dark:border-gray-700 dark:bg-gray-950 dark:text-gray-300 dark:file:bg-white dark:file:text-gray-950">
                                </div>
                                <div class="space-y-1">
                                    <label for="revision_expenses_{{ $topic->id }}" class="text-xs font-bold text-gray-600 dark:text-gray-300">Expense breakdown</label>
                                    <input id="revision_expenses_{{ $topic->id }}" name="expense_breakdown" type="file" accept=".pdf" @required($requiredRevisionTypes->contains('expense_breakdown')) class="block w-full rounded-xl border border-gray-300 bg-white p-2 text-xs text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-gray-950 file:px-3 file:py-1.5 file:font-bold file:text-white dark:border-gray-700 dark:bg-gray-950 dark:text-gray-300 dark:file:bg-white dark:file:text-gray-950">
                                </div>
                                <div class="space-y-1 sm:col-span-2">
                                    <label for="revision_cv_{{ $topic->id }}" class="text-xs font-bold text-gray-600 dark:text-gray-300">Curriculum vitae files</label>
                                    <input id="revision_cv_{{ $topic->id }}" name="curricula_vitae[]" type="file" accept=".doc,.docx,.pdf" multiple @required($requiredRevisionTypes->contains('curriculum_vitae')) class="block w-full rounded-xl border border-gray-300 bg-white p-2 text-xs text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-gray-950 file:px-3 file:py-1.5 file:font-bold file:text-white dark:border-gray-700 dark:bg-gray-950 dark:text-gray-300 dark:file:bg-white dark:file:text-gray-950">
                                </div>
                                <div class="space-y-1 sm:col-span-2">
                                    <label for="revision_gad_{{ $topic->id }}" class="text-xs font-bold text-gray-600 dark:text-gray-300">GAD checklist</label>
                                    <input id="revision_gad_{{ $topic->id }}" name="gad_checklist" type="file" accept=".doc,.docx,.pdf" @required($requiredRevisionTypes->contains('gad_checklist')) class="block w-full rounded-xl border border-gray-300 bg-white p-2 text-xs text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-gray-950 file:px-3 file:py-1.5 file:font-bold file:text-white dark:border-gray-700 dark:bg-gray-950 dark:text-gray-300 dark:file:bg-white dark:file:text-gray-950">
                                </div>
                                <div class="sm:col-span-2 sm:text-right">
                                    <button type="submit" class="rounded-xl bg-[#7A0019] px-4 py-2.5 text-xs font-black text-white transition hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-[#7A0019] focus:ring-offset-2 dark:bg-red-700 dark:hover:bg-red-600 dark:focus:ring-red-400 dark:focus:ring-offset-gray-950">Submit revision</button>
                                </div>
                            </form>
                        </details>
                    @endif
                </article>
            @empty
                <div class="mx-auto flex max-w-sm flex-col items-center px-6 py-14 text-center">
                    <div class="flex h-11 w-11 items-center justify-center rounded-full border border-gray-200 text-[#7A0019] dark:border-gray-800 dark:text-red-300">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5m16.5 0a6 6 0 00-12 0m12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17.25" /></svg>
                    </div>
                    <h4 class="mt-4 text-sm font-black text-gray-800 dark:text-gray-200">No projects recorded</h4>
                    <p class="mt-1 text-xs leading-relaxed text-gray-500 dark:text-gray-400">You haven't uploaded any research proposals to the portal yet.</p>
                </div>
            @endforelse
        </section>

        @include('faculty.partials.research-call-carousel', ['researchCallCarouselItems' => $researchCallCarouselItems])

    </div>
</x-app-layout>
