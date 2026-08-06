<x-app-layout>
    @php
        $isFacultyResearcher = Auth::user()->isUsingWorkspace('faculty_researcher');
    @endphp

    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-black uppercase tracking-[0.2em] text-[#7A0019] dark:text-red-300">Faculty research</p>
                <h2 class="mt-1 text-2xl font-black tracking-tight text-gray-950 dark:text-white">
                    {{ $isFacultyResearcher ? 'Faculty Researcher Workspace' : 'Research Proposal Workspace' }}
                </h2>
                <p class="mt-1 text-sm leading-6 text-gray-600 dark:text-gray-400">
                    Welcome back, <span class="font-bold text-gray-950 dark:text-white">{{ Auth::user()->name }}</span>.
                    {{ $isFacultyResearcher ? 'Manage and track your institutional research submissions.' : 'Submit and track your research proposals.' }}
                </p>
            </div>

            <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
                @if ($isFacultyResearcher)
                    <a href="{{ route('research.index') }}" class="inline-flex items-center justify-center rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-xs font-black text-gray-700 shadow-sm transition hover:border-gray-950 hover:text-gray-950 focus:outline-none focus:ring-2 focus:ring-[#7A0019] focus:ring-offset-2 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-200 dark:hover:border-white dark:hover:text-white dark:focus:ring-red-400 dark:focus:ring-offset-gray-950">
                        View projects
                    </a>
                @else
                    <a href="{{ route('faculty.proposal-drafts.index') }}" class="inline-flex items-center justify-center rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-xs font-black text-gray-700 shadow-sm transition hover:border-gray-950 hover:text-gray-950 focus:outline-none focus:ring-2 focus:ring-[#7A0019] focus:ring-offset-2 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-200 dark:hover:border-white dark:hover:text-white dark:focus:ring-red-400 dark:focus:ring-offset-gray-950">
                        View all drafts
                    </a>
                    <a href="{{ route('faculty.proposal-drafts.create') }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#7A0019] px-4 py-2.5 text-xs font-black text-white shadow-sm transition hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-[#7A0019] focus:ring-offset-2 dark:bg-red-700 dark:hover:bg-red-600 dark:focus:ring-red-400 dark:focus:ring-offset-gray-950">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                        New proposal
                    </a>
                @endif
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
        
        @include('faculty.partials.research-call-carousel', ['researchCallCarouselItems' => $researchCallCarouselItems])

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
                    <p class="mt-3 max-w-xl text-sm leading-6 text-gray-300">You can still start a proposal or continue one of your saved drafts while the next call is being prepared.</p>
                    <div class="mt-6 flex flex-wrap gap-3">
                        <a href="{{ route('faculty.proposal-drafts.create') }}" class="inline-flex items-center justify-center rounded-xl bg-white px-4 py-2.5 text-xs font-black text-gray-950 transition hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-gray-950">Start a proposal</a>
                        <a href="#recent-drafts" class="inline-flex items-center justify-center rounded-xl border border-white/20 bg-white/5 px-4 py-2.5 text-xs font-bold text-white transition hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-gray-950">Continue a draft</a>
                    </div>
                </div>
            </section>
        @endif

        <section aria-labelledby="proposal-overview-heading" class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-950">
            <div class="flex flex-col gap-2 border-b border-gray-200 px-5 py-4 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h3 id="proposal-overview-heading" class="text-sm font-black text-gray-950 dark:text-white">{{ $isFacultyResearcher ? 'Project overview' : 'Proposal overview' }}</h3>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $isFacultyResearcher ? 'Your approved institutional research projects' : 'Your current work across drafting and review' }}</p>
                </div>
                <span class="inline-flex w-fit items-center gap-2 text-[11px] font-black uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">
                    <span class="h-2 w-2 rounded-full bg-[#7A0019] dark:bg-red-400" aria-hidden="true"></span>
                    Personal workspace
                </span>
            </div>

            <div class="grid grid-cols-2 lg:grid-cols-4">
                @foreach ($isFacultyResearcher
                    ? [
                        ['Approved projects', $topics->count(), 'Approved'],
                        ['Ongoing', $topics->where('project_status', 'ongoing')->count(), 'In execution'],
                        ['Delayed', $topics->where('project_status', 'delayed')->count(), 'Needs attention'],
                        ['Completed', $topics->where('project_status', 'completed')->count(), 'Finished'],
                    ]
                    : [
                        ['Draft packages', $proposalDraftCount, 'In progress'],
                        ['Submitted', $topics->count(), 'All records'],
                        ['Under review', $topics->whereIn('status', ['pending', 'expert_review', 'for_final_decision', 'resubmitted', 'ready_for_signature'])->count(), 'With research office'],
                        ['Action required', $topics->where('status', 'revision_requested')->count(), 'Needs revision'],
                    ] as [$label, $count, $context])
                    <div class="flex min-h-32 flex-col border-b border-r border-gray-200 px-5 py-4 last:border-r-0 dark:border-gray-800 lg:border-b-0">
                        <p class="text-xs font-bold text-gray-600 dark:text-gray-400">{{ $label }}</p>
                        <p class="mt-2 text-3xl font-black tracking-tight text-gray-950 dark:text-white">{{ $count }}</p>
                        <p class="mt-auto pt-3 text-[10px] font-black uppercase tracking-wider {{ $label === 'Action required' ? 'text-[#7A0019] dark:text-red-300' : 'text-gray-400 dark:text-gray-600' }}">
                            @if ($label === 'Action required')
                                <span class="mr-1 inline-block h-1.5 w-1.5 rounded-full bg-current" aria-hidden="true"></span>
                            @endif
                            {{ $context }}
                        </p>
                    </div>
                @endforeach
            </div>
        </section>

        @if (! $isFacultyResearcher)
            <section id="recent-drafts" aria-labelledby="recent-drafts-heading" class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-950">
            <div class="flex items-end justify-between gap-4 border-b border-gray-200 px-5 py-5 dark:border-gray-800">
                <div>
                    <div class="border-l-4 border-[#7A0019] pl-3 dark:border-red-500">
                        <h3 id="recent-drafts-heading" class="text-base font-black text-gray-950 dark:text-white">Recent proposal drafts</h3>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Continue the proposal packages you edited most recently.</p>
                    </div>
                </div>
                <a href="{{ route('faculty.proposal-drafts.index') }}" class="shrink-0 text-xs font-black text-[#7A0019] transition hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-[#7A0019] focus:ring-offset-2 dark:text-red-300 dark:hover:text-red-200 dark:focus:ring-red-400 dark:focus:ring-offset-gray-950">View all drafts</a>
            </div>

            <div class="grid md:grid-cols-2">
                @forelse ($recentProposalDrafts as $proposalDraft)
                    @php
                        $progress = $proposalDraftProgress->get($proposalDraft->getKey());
                    @endphp
                    <article class="group flex flex-col border-t border-gray-200 p-5 transition-colors hover:bg-gray-50/80 dark:border-gray-800 dark:hover:bg-gray-900/50 md:odd:border-r">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex flex-wrap gap-2">
                                <span class="rounded-full bg-gray-950 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-white dark:bg-white dark:text-gray-950">Draft</span>
                                <span class="rounded-full bg-gray-100 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-gray-600 ring-1 ring-inset ring-gray-200 dark:bg-gray-900 dark:text-gray-300 dark:ring-gray-800">{{ $proposalDraft->isOwnedBy(Auth::user()) ? 'Owner' : 'Collaborator' }}</span>
                            </div>
                            <span class="shrink-0 text-[11px] font-medium text-gray-400">Edited {{ $proposalDraft->updated_at->diffForHumans() }}</span>
                        </div>

                        <h4 class="mt-4 line-clamp-2 text-base font-black leading-6 text-gray-950 dark:text-white">{{ $proposalDraft->project_title ?: 'Untitled proposal' }}</h4>
                        <p class="mt-1 line-clamp-1 text-xs text-gray-500 dark:text-gray-400">{{ $proposalDraft->researchCall->title }}</p>
                        @unless ($proposalDraft->isOwnedBy(Auth::user()))
                            <p class="mt-1 text-[11px] font-semibold text-gray-400">Shared by {{ $proposalDraft->owner->name }}</p>
                        @endunless

                        <div class="mt-5">
                            <div class="flex items-center justify-between text-[11px] font-bold text-gray-600 dark:text-gray-300">
                                <span>Package progress</span>
                                <span>{{ $progress['completed'] }}/{{ $progress['total'] }} papers</span>
                            </div>
                            <div class="mt-2 grid grid-cols-10 gap-1" role="img" aria-label="{{ $progress['percentage'] }} percent complete">
                                @for ($step = 1; $step <= 10; $step++)
                                    <span class="h-1.5 rounded-full {{ $step <= (int) ceil($progress['percentage'] / 10) ? 'bg-[#7A0019] dark:bg-red-500' : 'bg-gray-100 dark:bg-gray-800' }}"></span>
                                @endfor
                            </div>
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
                    <div class="px-6 py-12 text-center md:col-span-2">
                        <div class="mx-auto flex h-11 w-11 items-center justify-center rounded-full border border-gray-200 text-[#7A0019] dark:border-gray-800 dark:text-red-300">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                        </div>
                        <h4 class="mt-4 text-sm font-black text-gray-950 dark:text-white">No proposal drafts yet</h4>
                        <p class="mx-auto mt-1 max-w-md text-xs leading-5 text-gray-500 dark:text-gray-400">Start a proposal and ATHENA will keep it here until the package is ready to submit.</p>
                        <a href="{{ route('faculty.proposal-drafts.create') }}" class="mt-4 inline-flex items-center justify-center rounded-xl bg-[#7A0019] px-4 py-2.5 text-xs font-black text-white transition hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-[#7A0019] focus:ring-offset-2 dark:bg-red-700 dark:hover:bg-red-600 dark:focus:ring-red-400 dark:focus:ring-offset-gray-950">Create first proposal</a>
                    </div>
                @endforelse
            </div>
        </section>
        @endif

        <section aria-labelledby="submitted-proposals-heading" class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-950">
            <div class="flex items-center justify-between gap-4 border-b border-gray-200 px-5 py-5 dark:border-gray-800">
                <div class="border-l-4 border-[#7A0019] pl-3 dark:border-red-500">
                    <h3 id="submitted-proposals-heading" class="text-base font-black text-gray-950 dark:text-white">{{ $isFacultyResearcher ? 'Approved projects' : 'Submitted proposals' }}</h3>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $isFacultyResearcher ? 'Monitor your approved research projects and report progress.' : 'Track decisions, review feedback, and requested revisions.' }}</p>
                </div>
                <p class="shrink-0 text-xs font-bold text-gray-500 dark:text-gray-400">{{ $topics->count() }} {{ str('proposal')->plural($topics->count()) }}</p>
            </div>
            @forelse ($topics as $topic)
                @php
                    $latestReview = $topic->reviews->last();
                    $isCurrentResubmission = (string) old('resubmitting_topic_id') === (string) $topic->id;
                    $pendingFileRevisions = $topic->reviews->flatMap->fileRevisions->whereNull('resolved_at')->values();
                    $requiredRevisionTypes = $pendingFileRevisions->pluck('document_type')->unique();
                    $statusStyle = match (true) {
                        $topic->status === 'approved' && $topic->isMonitoringAvailable() => 'bg-gray-950 text-white ring-gray-950 dark:bg-white dark:text-gray-950 dark:ring-white',
                        $topic->status === 'approved' && ! $topic->isMonitoringAvailable() => 'bg-red-50 text-[#7A0019] ring-red-200 dark:bg-red-950/40 dark:text-red-200 dark:ring-red-900',
                        $topic->status === 'rejected' => 'bg-gray-100 text-gray-600 ring-gray-200 dark:bg-gray-900 dark:text-gray-400 dark:ring-gray-800',
                        in_array($topic->status, ['revision_requested', 'ready_for_signature'], true) => 'bg-red-50 text-[#7A0019] ring-red-200 dark:bg-red-950/40 dark:text-red-200 dark:ring-red-900',
                        default => 'bg-gray-100 text-gray-700 ring-gray-200 dark:bg-gray-900 dark:text-gray-300 dark:ring-gray-800',
                    };
                    $statusLabel = $topic->status === 'approved' && ! $topic->isMonitoringAvailable()
                        ? 'approved - awaiting notice'
                        : str_replace('_', ' ', $topic->status);
                @endphp
                <article class="border-b border-gray-200 px-5 py-6 transition-colors last:border-b-0 hover:bg-gray-50/80 dark:border-gray-800 dark:hover:bg-gray-900/40">
                    <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_180px] lg:items-start lg:gap-6">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="h-2 w-2 shrink-0 rounded-full bg-[#7A0019] dark:bg-red-400" aria-hidden="true"></span>
                                <h4 class="text-sm font-black text-gray-950 dark:text-white">{{ $topic->title }}</h4>
                                <span class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-wider ring-1 ring-inset {{ $statusStyle }}">{{ $statusLabel }}</span>
                            </div>
                            <p class="mt-2 line-clamp-2 text-xs leading-5 text-gray-600 dark:text-gray-400">{{ $topic->description ?: 'No description provided.' }}</p>
                            <dl class="mt-3 flex flex-wrap gap-x-5 gap-y-2 text-[11px] text-gray-500 dark:text-gray-400">
                                <div><dt class="inline font-black uppercase tracking-wider text-gray-400 dark:text-gray-600">Cost</dt> <dd class="ml-1 inline font-bold text-gray-700 dark:text-gray-300">{{ $topic->estimated_budget !== null ? 'PHP '.number_format((float) $topic->estimated_budget, 2) : 'Not provided' }}</dd></div>
                                <div><dt class="inline font-black uppercase tracking-wider text-gray-400 dark:text-gray-600">Duration</dt> <dd class="ml-1 inline font-bold text-gray-700 dark:text-gray-300">{{ $topic->estimated_duration_months }} months</dd></div>
                                <div><dt class="inline font-black uppercase tracking-wider text-gray-400 dark:text-gray-600">Submitted</dt> <dd class="ml-1 inline font-bold text-gray-700 dark:text-gray-300">{{ $topic->created_at->diffForHumans() }}</dd></div>
                            </dl>
                            <p class="mt-2 text-[11px] font-semibold text-gray-400 dark:text-gray-500">{{ $topic->researchCall->title }}@if ($topic->category) <span class="text-gray-300 dark:text-gray-700">/</span> {{ $topic->category->name }}@endif</p>

                            @if ($latestReview)
                                <div class="mt-4 border-l-2 border-[#7A0019] bg-gray-50 px-4 py-3 dark:border-red-500 dark:bg-gray-900/70">
                                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-[#7A0019] dark:text-red-300">Latest review: {{ str_replace('_', ' ', $latestReview->decision) }}</p>
                                @if ($latestReview->comment)
                                        <p class="mt-1 whitespace-pre-line text-xs leading-5 text-gray-700 dark:text-gray-300">{{ $latestReview->comment }}</p>
                                @endif
                                    <p class="mt-1 text-[10px] text-gray-400 dark:text-gray-500">{{ $latestReview->reviewer?->name ?? 'Research Head' }} / {{ $latestReview->created_at->format('M d, Y h:i A') }}</p>
                                </div>
                            @endif

                            @if ($topic->reviews->count() > 1)
                                <details class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                                    <summary class="cursor-pointer font-black text-[#7A0019] dark:text-red-300">View all review history</summary>
                                    <div class="mt-2 space-y-2 border-l border-gray-200 pl-3 dark:border-gray-800">
                                    @foreach ($topic->reviews as $review)
                                        <div>
                                            <p class="text-[11px] font-bold uppercase tracking-wider">{{ str_replace('_', ' ', $review->decision) }}</p>
                                            @if ($review->comment)
                                                <p class="mt-0.5 whitespace-pre-line leading-relaxed">{{ $review->comment }}</p>
                                            @endif
                                            <p class="mt-0.5 text-[10px] text-gray-400">{{ $review->created_at->format('M d, Y h:i A') }}</p>
                                        </div>
                                    @endforeach
                                </div>
                                </details>
                            @endif

                            @include('topics.partials.version-history', ['topic' => $topic])
                        </div>

                        <div class="flex flex-col gap-2 border-t border-gray-200 pt-4 dark:border-gray-800 lg:border-l lg:border-t-0 lg:pl-6 lg:pt-0">
                            <a href="{{ route('topics.show', $topic) }}" class="inline-flex items-center justify-center rounded-xl bg-gray-950 px-3 py-2.5 text-xs font-black text-white transition hover:bg-[#7A0019] focus:outline-none focus:ring-2 focus:ring-[#7A0019] focus:ring-offset-2 dark:bg-white dark:text-gray-950 dark:hover:bg-red-200 dark:focus:ring-red-400 dark:focus:ring-offset-gray-950">Open workspace</a>
                            <a href="{{ route('topics.download', $topic) }}" class="inline-flex items-center justify-center rounded-xl border border-gray-300 px-3 py-2.5 text-xs font-black text-gray-700 transition hover:border-gray-950 hover:text-gray-950 focus:outline-none focus:ring-2 focus:ring-[#7A0019] focus:ring-offset-2 dark:border-gray-700 dark:text-gray-300 dark:hover:border-white dark:hover:text-white dark:focus:ring-red-400 dark:focus:ring-offset-gray-950">
                                Download latest
                            </a>
                        </div>
                    </div>

                    @if ($topic->status === 'revision_requested')
                        <details class="mt-5 border-l-2 border-[#7A0019] bg-gray-50 p-4 dark:border-red-500 dark:bg-gray-900/70" @if ($isCurrentResubmission && $errors->resubmission->any()) open @endif>
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
                                    <input id="revision_budget_{{ $topic->id }}" name="estimated_budget" type="number" value="{{ $isCurrentResubmission ? old('estimated_budget') : $topic->estimated_budget }}" min="0" max="{{ $topic->researchCall->budgetCeiling() }}" step="0.01" required class="block w-full rounded-xl border-gray-300 bg-white text-sm text-gray-950 shadow-sm focus:border-[#7A0019] focus:ring-[#7A0019] dark:border-gray-700 dark:bg-gray-950 dark:text-white dark:focus:border-red-400 dark:focus:ring-red-400">
                                    <p class="text-[10px] text-gray-400">Maximum: PHP {{ number_format($topic->researchCall->budgetCeiling(), 2) }}</p>
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
                                    <input id="revision_expenses_{{ $topic->id }}" name="expense_breakdown" type="file" accept=".xls,.xlsx" @required($requiredRevisionTypes->contains('expense_breakdown')) class="block w-full rounded-xl border border-gray-300 bg-white p-2 text-xs text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-gray-950 file:px-3 file:py-1.5 file:font-bold file:text-white dark:border-gray-700 dark:bg-gray-950 dark:text-gray-300 dark:file:bg-white dark:file:text-gray-950">
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

    </div>
</x-app-layout>
