<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-black uppercase tracking-[0.22em] text-red-700 dark:text-red-400">Research opportunities</p>
            <h1 class="mt-1 text-2xl font-black tracking-tight text-gray-950 dark:text-white">Research Calls</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-gray-600 dark:text-slate-300">Find a call that is open for submission, begin your proposal, or return to a call connected to your previous work.</p>
        </div>
    </x-slot>

    <div class="py-8" data-faculty-research-calls data-research-call-poster-gallery>
        <div class="mx-auto max-w-7xl space-y-10 px-4 sm:px-6 lg:px-8">
            @if (session('success'))
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-bold text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200">{{ session('success') }}</div>
            @endif

            <section aria-labelledby="open-calls-heading">
                <div class="mb-5 flex items-end justify-between gap-4">
                    <div>
                        <p class="text-xs font-black uppercase tracking-[0.2em] text-red-700 dark:text-red-400">Accepting proposals</p>
                        <h2 id="open-calls-heading" class="mt-1 text-xl font-black text-gray-950 dark:text-white">Open for submission</h2>
                    </div>
                    @if ($activeCalls->isNotEmpty())
                        <span class="rounded-full bg-red-50 px-3 py-1 text-xs font-black text-red-700 ring-1 ring-inset ring-red-200 dark:bg-red-950/40 dark:text-red-300 dark:ring-red-900">{{ $activeCalls->count() }} {{ Str::plural('call', $activeCalls->count()) }}</span>
                    @endif
                </div>

                <div class="space-y-5">
                    @forelse ($activeCalls as $call)
                        @php
                            $draft = $proposalDraftsByResearchCall->get($call->id)?->first();
                            $topic = $topicProposalsByResearchCall->get($call->id)?->first();
                        @endphp
                        <article class="overflow-hidden rounded-3xl border border-gray-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900 lg:grid lg:grid-cols-[18rem_minmax(0,1fr)]">
                            <div class="relative min-h-72 overflow-hidden bg-gradient-to-br from-gray-950 via-slate-900 to-red-950 lg:min-h-full">
                                @if ($call->reference_image_path)
                                    <button
                                        type="button"
                                        data-research-call-poster-preview
                                        data-poster-url="{{ route('research-calls.reference-image', $call) }}"
                                        data-poster-alt="Reference poster for {{ $call->title }}"
                                        class="group absolute inset-0 flex h-full w-full cursor-zoom-in items-center justify-center p-3 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-white"
                                        aria-label="Enlarge the poster for {{ $call->title }}"
                                    >
                                        <img src="{{ route('research-calls.reference-image', $call) }}" alt="Reference poster for {{ $call->title }}" class="h-full w-full object-contain transition duration-300 group-hover:scale-[1.02]" loading="eager" decoding="async">
                                    </button>
                                @else
                                    <div class="relative flex h-full min-h-56 flex-col justify-between p-6 text-white">
                                        <span class="w-fit rounded-full border border-white/30 bg-white/10 px-3 py-1 text-[10px] font-black uppercase tracking-[0.18em]">ATHENA Research</span>
                                        <div>
                                            <p class="text-xs font-bold uppercase tracking-[0.18em] text-red-100">Call for proposals</p>
                                            <p class="mt-2 text-xl font-black leading-tight">{{ $call->academic_year }}</p>
                                        </div>
                                    </div>
                                @endif
                            </div>

                            <div class="flex flex-col p-5 sm:p-7">
                                <div class="flex flex-wrap items-center gap-2 text-xs font-bold text-gray-500 dark:text-slate-400">
                                    <span>{{ $call->academic_year }}</span>
                                    @if ($call->term)
                                        <span aria-hidden="true">•</span>
                                        <span>{{ $call->term }}</span>
                                    @endif
                                </div>
                                <h3 class="mt-2 text-xl font-black leading-tight text-gray-950 dark:text-white sm:text-2xl">{{ $call->title }}</h3>
                                <p class="mt-3 max-w-3xl whitespace-pre-line text-sm leading-6 text-gray-600 dark:text-slate-300">{{ Str::limit($call->description ?: 'Open the call poster for the complete research priorities and submission guidance.', 320) }}</p>

                                <dl class="mt-6 grid gap-3 sm:grid-cols-3">
                                    <div class="rounded-2xl bg-red-50 p-4 dark:bg-red-950/30">
                                        <dt class="text-[10px] font-black uppercase tracking-wider text-red-700 dark:text-red-300">Submit by</dt>
                                        <dd class="mt-1 text-sm font-black text-gray-950 dark:text-white">{{ $call->closes_at->timezone('Asia/Manila')->format('M j, Y') }}</dd>
                                        <dd class="text-xs font-semibold text-gray-600 dark:text-slate-300">{{ $call->closes_at->timezone('Asia/Manila')->format('g:i A') }} PHT</dd>
                                    </div>
                                    <div class="rounded-2xl bg-gray-50 p-4 dark:bg-slate-800/80">
                                        <dt class="text-[10px] font-black uppercase tracking-wider text-gray-500 dark:text-slate-400">Budget ceiling</dt>
                                        <dd class="mt-1 text-sm font-black text-gray-950 dark:text-white">PHP {{ number_format($call->budgetCeiling(), 2) }}</dd>
                                    </div>
                                    <div class="rounded-2xl bg-gray-50 p-4 dark:bg-slate-800/80">
                                        <dt class="text-[10px] font-black uppercase tracking-wider text-gray-500 dark:text-slate-400">Workload limit</dt>
                                        <dd class="mt-1 text-sm font-black text-gray-950 dark:text-white">{{ $call->max_active_research_per_faculty }} concurrent {{ Str::plural('project', $call->max_active_research_per_faculty) }}</dd>
                                    </div>
                                </dl>

                                <div class="mt-6 flex flex-col gap-3 border-t border-gray-100 pt-5 sm:flex-row sm:flex-wrap dark:border-slate-800">
                                    @if ($draft)
                                        <a href="{{ route('faculty.proposal-drafts.show', $draft) }}" class="inline-flex items-center justify-center rounded-xl bg-[#7A0019] px-5 py-3 text-sm font-black text-white transition hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-red-700 focus:ring-offset-2 dark:bg-red-700 dark:hover:bg-red-600 dark:focus:ring-offset-slate-900">Continue your draft</a>
                                    @elseif ($topic)
                                        <a href="{{ route('topics.show', $topic) }}" class="inline-flex items-center justify-center rounded-xl bg-[#7A0019] px-5 py-3 text-sm font-black text-white transition hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-red-700 focus:ring-offset-2 dark:bg-red-700 dark:hover:bg-red-600 dark:focus:ring-offset-slate-900">Open your proposal</a>
                                    @else
                                        <a href="{{ route('faculty.proposal-drafts.create', ['research_call_id' => $call->id]) }}" class="inline-flex items-center justify-center rounded-xl bg-[#7A0019] px-5 py-3 text-sm font-black text-white transition hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-red-700 focus:ring-offset-2 dark:bg-red-700 dark:hover:bg-red-600 dark:focus:ring-offset-slate-900">Start a proposal</a>
                                    @endif
                                    @if ($draft || $topic)
                                        <a href="{{ route('faculty.proposal-drafts.create', ['research_call_id' => $call->id]) }}" class="inline-flex items-center justify-center rounded-xl border border-gray-300 px-5 py-3 text-sm font-black text-gray-700 transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-400 focus:ring-offset-2 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800 dark:focus:ring-offset-slate-900">Start another proposal</a>
                                    @endif
                                    @if ($call->reference_image_path)
                                        <a href="{{ route('research-calls.reference-image', $call) }}" target="_blank" rel="noopener" class="inline-flex items-center justify-center rounded-xl px-4 py-3 text-sm font-black text-gray-600 transition hover:bg-gray-50 hover:text-gray-950 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white">Open poster in new tab</a>
                                    @endif
                                </div>
                            </div>
                        </article>
                    @empty
                        <div class="rounded-3xl border border-dashed border-gray-300 bg-white px-6 py-12 text-center dark:border-slate-700 dark:bg-slate-900">
                            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-gray-100 text-xl dark:bg-slate-800">◷</div>
                            <h3 class="mt-4 text-base font-black text-gray-950 dark:text-white">No call is accepting proposals right now</h3>
                            <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-gray-600 dark:text-slate-300">When the Research Office opens a submission window, it will appear here with a direct way to start your proposal.</p>
                        </div>
                    @endforelse
                </div>
            </section>

            @if ($upcomingCalls->isNotEmpty())
                <section aria-labelledby="coming-soon-heading">
                    <div class="mb-4">
                        <p class="text-xs font-black uppercase tracking-[0.2em] text-blue-700 dark:text-blue-400">Plan ahead</p>
                        <h2 id="coming-soon-heading" class="mt-1 text-xl font-black text-gray-950 dark:text-white">Coming soon</h2>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        @foreach ($upcomingCalls as $call)
                            <article class="rounded-2xl border border-blue-100 bg-blue-50/60 p-5 dark:border-blue-950 dark:bg-blue-950/20">
                                <span class="text-[10px] font-black uppercase tracking-wider text-blue-700 dark:text-blue-300">Opens {{ $call->opens_at->timezone('Asia/Manila')->format('M j, Y · g:i A') }} PHT</span>
                                <h3 class="mt-2 text-base font-black leading-snug text-gray-950 dark:text-white">{{ $call->title }}</h3>
                                <p class="mt-2 text-xs font-semibold text-gray-500 dark:text-slate-400">{{ $call->academic_year }}{{ $call->term ? ' · '.$call->term : '' }}</p>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endif

            <section aria-labelledby="archive-heading">
                <div class="mb-5">
                    <p class="text-xs font-black uppercase tracking-[0.2em] text-gray-500 dark:text-slate-400">Your history</p>
                    <h2 id="archive-heading" class="mt-1 text-xl font-black text-gray-950 dark:text-white">Your research-call archive</h2>
                    <p class="mt-2 text-sm leading-6 text-gray-600 dark:text-slate-300">Only past calls connected to a draft or submitted proposal that you owned or joined appear here.</p>
                </div>

                <div class="overflow-hidden rounded-3xl border border-gray-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    @forelse ($archivedCalls as $call)
                        @php
                            $draft = $proposalDraftsByResearchCall->get($call->id)?->first();
                            $topic = $topicProposalsByResearchCall->get($call->id)?->first();
                        @endphp
                        <article class="flex flex-col gap-4 border-b border-gray-100 p-5 last:border-b-0 sm:flex-row sm:items-center sm:justify-between sm:p-6 dark:border-slate-800">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-full bg-gray-100 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-gray-600 dark:bg-slate-800 dark:text-slate-300">{{ $topic ? 'Proposal submitted' : 'Draft started' }}</span>
                                    <span class="text-xs font-bold text-gray-500 dark:text-slate-400">Closed {{ $call->closes_at->timezone('Asia/Manila')->format('M j, Y') }}</span>
                                </div>
                                <h3 class="mt-2 truncate text-base font-black text-gray-950 dark:text-white">{{ $call->title }}</h3>
                                <p class="mt-1 truncate text-sm text-gray-600 dark:text-slate-300">{{ $topic?->title ?? $draft?->project_title ?? 'Untitled proposal' }}</p>
                            </div>
                            @if ($topic)
                                <a href="{{ route('topics.show', $topic) }}" class="inline-flex shrink-0 items-center justify-center rounded-xl bg-gray-950 px-4 py-2.5 text-xs font-black text-white transition hover:bg-[#7A0019] dark:bg-white dark:text-gray-950 dark:hover:bg-red-200">View proposal</a>
                            @elseif ($draft)
                                <a href="{{ route('faculty.proposal-drafts.show', $draft) }}" class="inline-flex shrink-0 items-center justify-center rounded-xl bg-gray-950 px-4 py-2.5 text-xs font-black text-white transition hover:bg-[#7A0019] dark:bg-white dark:text-gray-950 dark:hover:bg-red-200">View draft</a>
                            @endif
                        </article>
                    @empty
                        <div class="px-6 py-10 text-center">
                            <h3 class="text-sm font-black text-gray-950 dark:text-white">No archived calls connected to your work yet</h3>
                            <p class="mt-2 text-sm text-gray-600 dark:text-slate-300">Calls will move here after their submission window ends if you created, joined, or submitted a proposal for them.</p>
                        </div>
                    @endforelse
                </div>

                @if ($archivedCalls->hasPages())
                    <div class="mt-5">{{ $archivedCalls->links() }}</div>
                @endif
            </section>
        </div>
        <div data-research-call-poster-modal class="fixed inset-0 z-[100] hidden items-center justify-center bg-gray-950/80 p-4 backdrop-blur-sm sm:p-8" role="dialog" aria-modal="true" aria-labelledby="research-call-poster-preview-title" aria-hidden="true" tabindex="-1">
            <div class="relative flex max-h-[92vh] max-w-5xl items-center justify-center overflow-hidden rounded-2xl border border-white/10 bg-gray-950 p-2 shadow-2xl">
                <h2 id="research-call-poster-preview-title" class="sr-only">Research call poster preview</h2>
                <img data-research-call-poster-modal-image src="" alt="" class="max-h-[88vh] max-w-full transform-gpu object-contain">
                <button type="button" data-research-call-poster-modal-close class="absolute right-3 top-3 inline-flex h-10 w-10 items-center justify-center rounded-full border border-white/20 bg-gray-950/80 text-xl font-bold text-white shadow-lg backdrop-blur transition hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-white" aria-label="Close poster preview">&times;</button>
            </div>
        </div>
    </div>
</x-app-layout>
