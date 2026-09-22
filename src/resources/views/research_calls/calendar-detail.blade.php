@php
    $isResearchHead = Auth::user()->isUsingWorkspace('research_head');
    $lifecycleStatus = $call->lifecycleStatus();
    [$statusLabel, $statusClass] = match ($lifecycleStatus) {
        'open' => ['Open for submission', 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-950/35 dark:text-emerald-300 dark:ring-emerald-900'],
        'scheduled' => ['Coming soon', 'bg-blue-50 text-blue-700 ring-blue-200 dark:bg-blue-950/35 dark:text-blue-300 dark:ring-blue-900'],
        'draft' => ['Draft', 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-950/35 dark:text-amber-300 dark:ring-amber-900'],
        'closed' => ['Closed', 'bg-slate-100 text-slate-600 ring-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700'],
        default => ['Ended', 'bg-slate-100 text-slate-600 ring-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700'],
    };
@endphp

<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-[10px] font-black uppercase tracking-[0.22em] text-red-700 dark:text-red-300">Research Office</p>
            <h2 class="mt-1 text-2xl font-black tracking-tight text-slate-950 dark:text-white">Research-call schedule</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Official submission and review milestones for this announcement.</p>
        </div>
    </x-slot>

    <div class="mx-auto max-w-6xl space-y-5">
        <a href="{{ route('research-calls.index') }}" class="inline-flex items-center gap-2 text-xs font-black text-slate-500 transition hover:text-red-700 dark:text-slate-400 dark:hover:text-red-300"><span aria-hidden="true">←</span>{{ $isResearchHead ? 'All research calls' : 'All announcements' }}</a>

        <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="grid lg:grid-cols-[minmax(0,1fr)_20rem]">
                <div class="p-5 sm:p-8">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-wide ring-1 ring-inset {{ $statusClass }}">{{ $statusLabel }}</span>
                        <span class="text-[11px] font-bold text-slate-400">{{ $call->academic_year }}{{ $call->term ? ' · '.$call->term : '' }}</span>
                    </div>
                    <h3 class="mt-4 max-w-3xl text-2xl font-black leading-tight tracking-tight text-slate-950 dark:text-white sm:text-3xl">{{ $call->title }}</h3>
                    <p class="mt-4 max-w-3xl whitespace-pre-line text-sm leading-7 text-slate-600 dark:text-slate-300">{{ $call->description ?: 'No additional guidelines were provided for this research call.' }}</p>

                    <dl class="mt-7 grid gap-3 sm:grid-cols-2">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-950/50"><dt class="text-[10px] font-black uppercase tracking-wider text-slate-400">Submission window</dt><dd class="mt-2 text-sm font-black text-slate-800 dark:text-slate-100">{{ $call->opens_at->format('M j, Y · g:i A') }}</dd><dd class="mt-1 text-xs text-slate-500 dark:text-slate-400">through {{ $call->closes_at->format('M j, Y · g:i A') }}</dd></div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-950/50"><dt class="text-[10px] font-black uppercase tracking-wider text-slate-400">Maximum budget</dt><dd class="mt-2 text-sm font-black text-slate-800 dark:text-slate-100">PHP {{ number_format($call->budgetCeiling(), 2) }}</dd><dd class="mt-1 text-xs text-slate-500 dark:text-slate-400">Per submitted proposal</dd></div>
                    </dl>

                    @if (! $isResearchHead)
                        <div class="mt-7 flex flex-col gap-3 border-t border-slate-100 pt-6 dark:border-slate-800 sm:flex-row sm:items-center sm:justify-between">
                            <p class="max-w-xl text-xs leading-5 text-slate-500 dark:text-slate-400">These dates describe the announcement. You may prepare a draft anytime; submission is available while the call is open.</p>
                            @if ($call->isAcceptingSubmissions())
                                <a href="{{ route('faculty.proposal-drafts.create', ['research_call_id' => $call->id]) }}" class="inline-flex shrink-0 items-center justify-center rounded-xl bg-red-600 px-5 py-3 text-xs font-black text-white transition hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 dark:focus:ring-offset-slate-900">Create proposal</a>
                            @endif
                        </div>
                    @endif
                </div>

                <aside class="border-t border-slate-200 bg-slate-50 p-5 dark:border-slate-800 dark:bg-slate-950/45 lg:border-l lg:border-t-0">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Official poster</p>
                    @if ($call->reference_image_path)
                        <div class="mt-3 flex min-h-72 items-center justify-center overflow-hidden rounded-2xl border border-slate-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-900"><img src="{{ route('research-calls.reference-image', $call) }}" alt="Poster for {{ $call->title }}" class="max-h-[32rem] w-full object-contain"></div>
                    @else
                        <div class="mt-3 flex min-h-72 flex-col items-center justify-center rounded-2xl border border-dashed border-slate-300 bg-white px-6 text-center text-slate-400 dark:border-slate-700 dark:bg-slate-900"><svg class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3.75h7.5L18 7.5v12.75H6.75V3.75Z" /><path stroke-linecap="round" d="M9.5 12h5M9.5 15h5" /></svg><p class="mt-3 text-xs font-bold">No poster was attached to this call.</p></div>
                    @endif
                </aside>
            </div>
        </section>

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
