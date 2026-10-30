@php
    $isResearchHead = Auth::user()->isUsingWorkspace('research_head');
    $lifecycleStatus = $call->lifecycleStatus();
    [$statusLabel, $statusClass] = match ($lifecycleStatus) {
        'open' => ['Open for submission', 'bg-emerald-500'],
        'scheduled' => ['Coming soon', 'bg-blue-500'],
        'draft' => ['Draft', 'bg-amber-500'],
        'closed' => ['Closed', 'bg-slate-500'],
        default => ['Ended', 'bg-slate-500'],
    };
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Research-call schedule" subtitle="Official submission and review milestones for this announcement." />
    </x-slot>

    <div class="mx-auto max-w-6xl space-y-5">
        <a href="{{ route('research-calls.index') }}" class="inline-flex items-center gap-2 text-xs font-black text-slate-500 transition hover:text-red-700 dark:text-slate-400 dark:hover:text-red-300"><span aria-hidden="true">←</span>{{ $isResearchHead ? 'All research calls' : 'All announcements' }}</a>

        <article data-research-call-announcement class="overflow-hidden rounded-2xl border border-slate-300 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900" aria-labelledby="call-title">
            <header class="flex items-start gap-4 bg-[#800020] p-5 text-white sm:p-7">
                <span class="hidden h-12 w-12 shrink-0 items-center justify-center rounded-xl border border-white/25 bg-white/10 sm:inline-flex" aria-hidden="true">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="m10 15-1 5H6l1-6M4 9h3l13-5v16L7 15H4a2 2 0 0 1-2-2v-2a2 2 0 0 1 2-2Zm3 0v6" /></svg>
                </span>
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                        <span class="inline-flex items-center gap-1.5 rounded-md bg-white px-2.5 py-1 text-xs font-bold text-slate-900" data-call-status>
                            <span class="h-2 w-2 rounded-full ring-1 ring-inset {{ $statusClass }}" aria-hidden="true"></span>{{ $statusLabel }}
                        </span>
                        <span class="text-sm font-medium text-white/80">{{ $call->academic_year }}{{ $call->term ? ' · '.$call->term : '' }}</span>
                    </div>
                    <h2 id="call-title" class="mt-3 max-w-4xl break-words text-2xl font-bold leading-snug tracking-tight sm:text-3xl">{{ $call->title }}</h2>
                </div>
            </header>

            <div class="grid lg:grid-cols-[minmax(0,1fr)_21rem]">
                <div class="flex min-w-0 flex-col gap-6 p-5 sm:p-7">
                    <section aria-labelledby="call-guidelines-heading">
                        <h3 id="call-guidelines-heading" class="flex items-center gap-2 text-base font-bold text-slate-950 dark:text-white">
                            <svg class="h-5 w-5 shrink-0 text-[#800020] dark:text-red-300" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M14 3H6a2 2 0 0 0-2 2v16h16V9l-6-6Zm0 0v6h6M8 13h8m-8 4h5" /></svg>
                            Proposal guidelines
                        </h3>
                        <div data-call-guidelines class="mt-3 max-w-[65ch] space-y-3 text-sm leading-7 text-slate-600 dark:text-slate-300">
                            @foreach (preg_split('/\R\s*\R/u', trim($call->description ?: 'No additional guidelines were provided for this research call.')) as $paragraph)
                                <p>{{ $paragraph }}</p>
                            @endforeach
                        </div>
                    </section>

                    <dl class="grid gap-3 sm:grid-cols-2">
                        <div data-call-submission-window class="rounded-xl border border-slate-300 p-4 dark:border-slate-700">
                            <dt class="flex items-center gap-2 text-sm font-bold text-slate-950 dark:text-white">
                                <svg class="h-5 w-5 shrink-0 text-[#800020] dark:text-red-300" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2" /><path d="M16 3v4M8 3v4M3 11h18m-13 5h2" /></svg>
                                Submission window
                            </dt>
                            <dd class="mt-4 grid grid-cols-[4rem_minmax(0,1fr)] gap-x-2 gap-y-3 text-sm">
                                <span class="text-slate-500 dark:text-slate-400">Opens</span>
                                <time datetime="{{ $call->opens_at->toIso8601String() }}" class="font-semibold tabular-nums text-slate-900 dark:text-slate-100">{{ $call->opens_at->format('M j, Y') }}<span class="mt-0.5 block text-xs font-normal text-slate-500 dark:text-slate-400">{{ $call->opens_at->format('g:i A') }}</span></time>
                                <span class="text-slate-500 dark:text-slate-400">Deadline</span>
                                <time datetime="{{ $call->closes_at->toIso8601String() }}" class="font-semibold tabular-nums text-[#800020] dark:text-red-300">{{ $call->closes_at->format('M j, Y') }}<span class="mt-0.5 block text-xs font-normal text-slate-500 dark:text-slate-400">{{ $call->closes_at->format('g:i A') }}</span></time>
                            </dd>
                        </div>
                        <div data-call-budget class="flex flex-col rounded-xl border border-slate-300 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-950/50">
                            <dt class="flex items-center gap-2 text-sm font-bold text-slate-950 dark:text-white">
                                <svg class="h-5 w-5 shrink-0 text-[#800020] dark:text-red-300" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 8V5a2 2 0 0 0-2-2H5a3 3 0 0 0 0 6h15v12H5a3 3 0 0 1-3-3V6m18 7h-5v4h5m-3-2h.01" /></svg>
                                Maximum budget
                            </dt>
                            <dd class="mt-5 break-words text-2xl font-bold tracking-tight tabular-nums text-[#800020] dark:text-red-300"><span class="mr-1 text-sm font-semibold">PHP</span>{{ number_format($call->budgetCeiling(), 2) }}</dd>
                            <dd class="mt-2 text-xs text-slate-500 dark:text-slate-400">Per submitted proposal</dd>
                        </div>
                    </dl>

                    @can('create', \App\Models\ProposalDraft::class)
                        <div class="mt-auto flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <p class="max-w-sm text-xs leading-5 text-slate-500 dark:text-slate-400">You can prepare a draft anytime. Submit while the call is open.</p>
                            @if ($call->isAcceptingSubmissions())
                                <a href="{{ route('faculty.proposal-drafts.create', ['research_call_id' => $call->id]) }}" class="inline-flex min-h-11 shrink-0 items-center justify-center gap-2 rounded-lg bg-[#800020] px-5 py-3 text-sm font-bold text-white transition hover:bg-[#650019] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#800020] dark:focus-visible:outline-red-300">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg>
                                    Create proposal
                                </a>
                            @endif
                        </div>
                    @endcan
                </div>

                <aside class="border-t border-slate-300 bg-slate-50 p-5 dark:border-slate-700 dark:bg-slate-950/45 sm:p-6 lg:border-l lg:border-t-0" aria-labelledby="call-poster-heading">
                    <h3 id="call-poster-heading" class="flex items-center gap-2 text-sm font-bold text-slate-900 dark:text-slate-100">
                        <svg class="h-5 w-5 text-[#800020] dark:text-red-300" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3h18v18H3V3Zm0 14 6-6 4 4 3-3 5 5M8 7h.01" /></svg>
                        Official poster
                    </h3>
                    @if ($call->reference_image_path)
                        <div class="mt-4 overflow-hidden rounded-lg border border-slate-300 bg-white p-2 dark:border-slate-700 dark:bg-slate-900"><img src="{{ route('research-calls.reference-image', $call) }}" alt="Poster for {{ $call->title }}" class="mx-auto max-h-[32rem] w-full object-contain"></div>
                    @else
                        <div class="mt-4 flex min-h-64 flex-col items-center justify-center rounded-lg border border-dashed border-slate-300 bg-white px-6 text-center text-slate-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-400"><svg class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3.75h7.5L18 7.5v12.75H6.75V3.75Z" /><path stroke-linecap="round" d="M9.5 12h5M9.5 15h5" /></svg><p class="mt-3 text-sm">No poster attached.</p></div>
                    @endif
                </aside>
            </div>
        </article>

        <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-8" aria-labelledby="milestone-heading">
            <div><p class="text-[10px] font-black uppercase tracking-[0.18em] text-red-700 dark:text-red-300">Official timeline</p><h3 id="milestone-heading" class="mt-1 text-xl font-black text-slate-950 dark:text-white">Submission and review milestones</h3></div>
            <ol class="relative mt-7 space-y-0 before:absolute before:bottom-5 before:left-[0.72rem] before:top-5 before:w-px before:bg-slate-200 dark:before:bg-slate-700">
                @foreach ($milestones as $field => $label)
                    @if ($call->{$field})
                        <li class="relative grid grid-cols-[1.5rem_minmax(0,1fr)] gap-4 py-3 first:pt-0 last:pb-0">
                            <span class="relative z-10 mt-1 h-6 w-6 rounded-full border-4 border-white bg-red-600 shadow-sm ring-1 ring-red-200 dark:border-slate-900 dark:ring-red-900" aria-hidden="true"></span>
                            <div class="flex flex-col gap-1 rounded-xl border border-slate-100 bg-slate-50/75 px-4 py-3 dark:border-slate-800 dark:bg-slate-950/40 sm:flex-row sm:items-center sm:justify-between sm:gap-4"><span class="text-sm font-bold text-slate-700 dark:text-slate-200">{{ $label }}</span><time class="shrink-0 text-xs font-black text-slate-950 dark:text-white" datetime="{{ $call->{$field}->toDateTimeString() }}">{{ $call->{$field}->format(in_array($field, ['opens_at', 'closes_at']) ? 'M j, Y · g:i A' : 'M j, Y') }}</time></div>
                        </li>
                    @endif
                @endforeach
            </ol>
        </section>
    </div>
</x-app-layout>
