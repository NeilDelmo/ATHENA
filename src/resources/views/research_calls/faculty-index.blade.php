<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Research calls">
            <x-slot:actions>
                @can('create', \App\Models\ProposalDraft::class)
                    <a href="{{ route('faculty.proposal-drafts.create') }}" class="dashboard-action">New proposal</a>
                @endcan
            </x-slot:actions>
        </x-page-header>
    </x-slot>

    <div x-data="{ poster: null }" x-on:keydown.escape.window="poster = null" class="mx-auto max-w-7xl space-y-7" data-faculty-research-calls>
        <p class="text-sm text-slate-600 dark:text-slate-300">Drafts can be prepared at any time. Select an open call when submitting a proposal.</p>
        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900" aria-label="Research-call counts">
            <dl class="grid sm:grid-cols-3 sm:divide-x sm:divide-slate-200 dark:sm:divide-slate-800">
                @foreach (collect([
                    ['Open for submission', $openCalls->count(), 'Accepting new proposals'],
                    $upcomingCalls->isNotEmpty() ? ['Coming soon', $upcomingCalls->count(), 'Published future schedules'] : null,
                    ['Your archive', $archivedCalls->count(), 'Calls connected to your work'],
                ])->filter() as [$label, $count, $description])
                    <div class="border-b border-slate-200 px-5 py-4 last:border-b-0 dark:border-slate-800 sm:border-b-0">
                        <dt class="text-[10px] font-black uppercase tracking-[0.15em] text-slate-400">{{ $label }}</dt>
                        <dd class="mt-1 text-2xl font-black text-slate-950 dark:text-white">{{ $count }}</dd>
                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ $description }}</p>
                    </div>
                @endforeach
            </dl>
        </section>

        <section aria-labelledby="open-research-calls-heading">
            <div class="flex items-end justify-between gap-4">
                <div>
                    <h3 id="open-research-calls-heading" class="text-xl font-black text-slate-950 dark:text-white">Open for submission</h3>
                </div>
                <span class="text-xs font-bold text-slate-400">{{ $openCalls->count() }} {{ Str::plural('call', $openCalls->count()) }}</span>
            </div>

            <div class="mt-4 grid gap-5 xl:grid-cols-2">
                @forelse ($openCalls as $call)
                    <article class="group overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:border-red-200 hover:shadow-lg hover:shadow-slate-950/5 dark:border-slate-800 dark:bg-slate-900 dark:hover:border-red-900">
                        <div class="grid min-h-full sm:grid-cols-[10rem_minmax(0,1fr)]">
                            <div class="border-b border-slate-200 bg-slate-100 p-3 dark:border-slate-800 dark:bg-slate-950/60 sm:border-b-0 sm:border-r">
                                @if ($call->reference_image_path)
                                    <button
                                        type="button"
                                        x-on:click="poster = { src: @js(route('research-calls.reference-image', $call)), alt: @js('Poster for '.$call->title) }"
                                        class="flex h-48 w-full items-center justify-center overflow-hidden rounded-xl bg-white focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 dark:bg-slate-900 dark:focus:ring-offset-slate-950 sm:h-full"
                                        aria-label="Preview poster for {{ $call->title }}"
                                        data-research-call-poster-preview
                                    >
                                        <img src="{{ route('research-calls.reference-image', $call) }}" alt="Poster for {{ $call->title }}" class="h-full max-h-72 w-full object-contain transition duration-300 group-hover:scale-[1.02]">
                                    </button>
                                @else
                                    <div class="flex h-48 w-full flex-col items-center justify-center rounded-xl border border-dashed border-slate-300 bg-white px-4 text-center text-slate-400 dark:border-slate-700 dark:bg-slate-900 sm:h-full">
                                        <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3.75h7.5L18 7.5v12.75H6.75V3.75Z" /><path stroke-linecap="round" d="M9.5 12h5M9.5 15h5" /></svg>
                                        <span class="mt-2 text-[10px] font-black uppercase tracking-wider">Official call</span>
                                    </div>
                                @endif
                            </div>

                            <div class="flex min-w-0 flex-col p-5 sm:p-6">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-[10px] font-black uppercase tracking-wide text-emerald-700 ring-1 ring-inset ring-emerald-200 dark:bg-emerald-950/35 dark:text-emerald-300 dark:ring-emerald-900"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>Open</span>
                                    <span class="text-[11px] font-bold text-slate-400">{{ $call->academic_year }}{{ $call->term ? ' · '.$call->term : '' }}</span>
                                </div>
                                <h4 class="mt-3 text-lg font-black leading-6 text-slate-950 dark:text-white">{{ $call->title }}</h4>
                                <p class="mt-2 line-clamp-3 text-sm leading-6 text-slate-600 dark:text-slate-300">{{ $call->description ?: 'Review the official schedule and prepare your proposal for this research call.' }}</p>

                                <dl class="mt-5 grid grid-cols-2 gap-3 rounded-2xl bg-slate-50 p-4 text-xs dark:bg-slate-950/55">
                                    <div><dt class="font-bold text-slate-400">Deadline</dt><dd class="mt-1 font-black text-slate-800 dark:text-slate-100">{{ $call->closes_at->format('M d, Y') }}</dd><dd class="text-slate-500 dark:text-slate-400">{{ $call->closes_at->format('h:i A') }}</dd></div>
                                    <div><dt class="font-bold text-slate-400">Maximum budget</dt><dd class="mt-1 font-black text-slate-800 dark:text-slate-100">PHP {{ number_format($call->budgetCeiling(), 2) }}</dd><dd class="text-slate-500 dark:text-slate-400">Per proposal</dd></div>
                                </dl>

                                <div class="mt-auto flex flex-col gap-2 pt-5 sm:flex-row">
                                    @can('create', \App\Models\ProposalDraft::class)
                                        <a href="{{ route('faculty.proposal-drafts.create', ['research_call_id' => $call->id]) }}" class="inline-flex flex-1 items-center justify-center rounded-xl bg-red-600 px-4 py-2.5 text-xs font-black text-white transition hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 dark:focus:ring-offset-slate-900">Create proposal</a>
                                    @endcan
                                    <a href="{{ route('research-calls.index', ['call' => $call->id]) }}" class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-xs font-black text-slate-700 transition hover:border-red-200 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-red-900 dark:hover:text-red-300">View schedule <span aria-hidden="true">→</span></a>
                                </div>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="rounded-3xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center dark:border-slate-700 dark:bg-slate-900 xl:col-span-2">
                        <p class="text-sm font-black text-slate-700 dark:text-slate-200">No calls are accepting proposals today.</p>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">You can prepare a draft before the next call opens.</p>
                    </div>
                @endforelse
            </div>
        </section>

        @if ($upcomingCalls->isNotEmpty())
            <section aria-labelledby="upcoming-research-calls-heading">
                <div>
                    <h3 id="upcoming-research-calls-heading" class="text-xl font-black text-slate-950 dark:text-white">Upcoming calls</h3>
                </div>
                <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($upcomingCalls as $call)
                        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                            <div class="flex items-center justify-between gap-3"><span class="rounded-full bg-blue-50 px-2.5 py-1 text-[10px] font-black uppercase tracking-wide text-blue-700 ring-1 ring-inset ring-blue-200 dark:bg-blue-950/35 dark:text-blue-300 dark:ring-blue-900">Scheduled</span><span class="text-[11px] font-bold text-slate-400">{{ $call->academic_year }}</span></div>
                            <h4 class="mt-4 text-base font-black leading-6 text-slate-950 dark:text-white">{{ $call->title }}</h4>
                            <p class="mt-2 line-clamp-2 text-sm leading-6 text-slate-500 dark:text-slate-400">{{ $call->description ?: 'The Research Office has published this upcoming submission schedule.' }}</p>
                            <div class="mt-5 flex items-center justify-between gap-3 border-t border-slate-100 pt-4 dark:border-slate-800"><div><p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Opens</p><p class="mt-1 text-xs font-black text-slate-700 dark:text-slate-200">{{ $call->opens_at->format('M d, Y · h:i A') }}</p></div><a href="{{ route('research-calls.index', ['call' => $call->id]) }}" class="text-xs font-black text-red-700 hover:text-red-800 dark:text-red-300">Schedule →</a></div>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($archivedCalls->isNotEmpty())
            <section aria-labelledby="archived-research-calls-heading">
                <div>
                    <h3 id="archived-research-calls-heading" class="text-xl font-black text-slate-950 dark:text-white">Previous research calls</h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Only previous calls connected to a proposal you lead or participate in as project staff appear here.</p>
                </div>
                <div class="mt-4 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($archivedCalls as $call)
                            <article class="grid gap-4 px-5 py-5 sm:px-6 md:grid-cols-[minmax(0,1fr)_minmax(16rem,.7fr)_auto] md:items-center">
                                <div class="min-w-0"><div class="flex flex-wrap items-center gap-2"><h4 class="truncate text-sm font-black text-slate-950 dark:text-white">{{ $call->title }}</h4><span class="rounded-full bg-slate-100 px-2 py-0.5 text-[9px] font-black uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-300">{{ ucfirst($call->lifecycleStatus()) }}</span></div><p class="mt-1 text-xs font-semibold text-slate-400">{{ $call->academic_year }}{{ $call->term ? ' · '.$call->term : '' }}</p></div>
                                <div class="min-w-0">
                                    <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Connected work</p>
                                    <div class="mt-1 space-y-1">
                                        @foreach ($call->proposalDrafts as $draft)<p class="truncate text-xs font-semibold text-slate-700 dark:text-slate-200">{{ $draft->project_title }}</p>@endforeach
                                        @foreach ($call->topics as $topic)<p class="truncate text-xs font-semibold text-slate-700 dark:text-slate-200">{{ $topic->title }}</p>@endforeach
                                    </div>
                                </div>
                                <a href="{{ route('research-calls.index', ['call' => $call->id]) }}" class="inline-flex items-center justify-center rounded-lg border border-slate-300 px-3 py-2 text-xs font-black text-slate-700 transition hover:border-red-200 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 dark:border-slate-700 dark:text-slate-200 dark:hover:border-red-900 dark:hover:text-red-300">View record</a>
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        <div x-show="poster !== null" x-cloak class="fixed inset-0 z-[90] flex items-center justify-center p-4 sm:p-8" data-research-call-poster-modal>
            <button type="button" x-on:click="poster = null" class="absolute inset-0 bg-slate-950/75 backdrop-blur-sm" aria-label="Close poster preview"></button>
            <figure class="relative z-10 max-h-full max-w-4xl overflow-hidden rounded-2xl bg-white p-3 shadow-2xl dark:bg-slate-900">
                <button type="button" x-on:click="poster = null" class="absolute right-5 top-5 inline-flex h-10 w-10 items-center justify-center rounded-full bg-slate-950/80 text-white shadow-lg transition hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-white" aria-label="Close poster preview"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18" /></svg></button>
                <img x-bind:src="poster?.src" x-bind:alt="poster?.alt" class="max-h-[85vh] w-auto max-w-full rounded-xl object-contain">
            </figure>
        </div>
    </div>
</x-app-layout>
