@props(['researchCall' => null])

@if ($researchCall)
    @php
        $deadline = $researchCall->closes_at->copy()->timezone(config('app.timezone'));
        $submissionPeriodEnded = $researchCall->closes_at->isPast();
    @endphp

    <aside
        @class([
            'sticky top-[120px] z-20 border-b bg-white/95 shadow-sm backdrop-blur dark:bg-slate-950/95',
            'border-amber-200 dark:border-amber-950' => $submissionPeriodEnded,
            'border-red-200 dark:border-red-950' => ! $submissionPeriodEnded,
        ])
        role="status"
        aria-live="polite"
        data-research-call-deadline-banner
        data-research-call-notice-state="{{ $submissionPeriodEnded ? 'ended' : 'approaching' }}"
        x-show="!dismissed"
        x-data="{
            dismissed: false,
            dismissing: false,
            async dismiss() {
                if (this.dismissing) return;

                this.dismissing = true;

                try {
                    const response = await fetch(@js(route('research-calls.deadline-dismissal.store', $researchCall)), {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=\'csrf-token\']')?.content,
                        },
                    });

                    if (response.ok) {
                        this.dismissed = true;

                        return;
                    }
                } catch {}

                this.dismissing = false;
            },
        }"
    >
        <div class="mx-auto flex max-w-7xl flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
            <div class="flex min-w-0 items-start gap-3 sm:items-center">
                <span @class([
                    'inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl text-white shadow-sm',
                    'bg-amber-600 shadow-amber-900/20' => $submissionPeriodEnded,
                    'bg-red-700 shadow-red-900/20' => ! $submissionPeriodEnded,
                ]) aria-hidden="true">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        @if ($submissionPeriodEnded)
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3.75v2.25m10.5-2.25v2.25M3.75 9h16.5m-13.5 4.5h4.5m-4.5 3h2.25M5.25 5.25h13.5a1.5 1.5 0 0 1 1.5 1.5v11.25a1.5 1.5 0 0 1-1.5 1.5H5.25a1.5 1.5 0 0 1-1.5-1.5V6.75a1.5 1.5 0 0 1 1.5-1.5Z" />
                        @else
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m5-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        @endif
                    </svg>
                </span>
                <div class="min-w-0">
                    <p @class([
                        'text-xs font-black uppercase tracking-[0.16em]',
                        'text-amber-700 dark:text-amber-300' => $submissionPeriodEnded,
                        'text-red-700 dark:text-red-300' => ! $submissionPeriodEnded,
                    ])>{{ $submissionPeriodEnded ? 'Submission period ended' : 'Submission deadline approaching' }}</p>
                    <p class="mt-0.5 text-sm leading-5 text-gray-900 dark:text-white">
                        <span class="font-black">{{ $researchCall->title }}</span>
                        {{ $submissionPeriodEnded ? 'closed on' : 'closes on' }} <time datetime="{{ $deadline->toIso8601String() }}" class="font-bold">{{ $deadline->format('M j, Y \a\t g:i A') }} PHT</time>.
                        @if ($submissionPeriodEnded)
                            New proposals cannot be started or submitted until the Research Office opens another call.
                        @endif
                    </p>
                </div>
            </div>
            <div class="flex shrink-0 items-center gap-2">
                <a wire:navigate href="{{ route('research-calls.index') }}" @class([
                    'inline-flex min-h-10 items-center justify-center rounded-xl px-4 py-2 text-xs font-black text-white shadow-sm transition focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-slate-950',
                    'bg-amber-600 hover:bg-amber-700 focus:ring-amber-500 dark:bg-amber-600 dark:hover:bg-amber-500' => $submissionPeriodEnded,
                    'bg-red-700 hover:bg-red-800 focus:ring-red-600 dark:bg-red-700 dark:hover:bg-red-600' => ! $submissionPeriodEnded,
                ])>
                    {{ $submissionPeriodEnded ? 'View research calls' : 'View research call' }}
                </a>
                <button type="button" @click="dismiss" :disabled="dismissing" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-black bg-white text-black shadow-sm transition hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 disabled:cursor-wait disabled:opacity-60 dark:border-white dark:bg-slate-950 dark:text-white dark:hover:bg-slate-900 dark:focus:ring-offset-slate-950" aria-label="{{ $submissionPeriodEnded ? 'Dismiss ended research call notice until tomorrow' : 'Dismiss deadline reminder until tomorrow' }}" title="Hide until tomorrow">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.3" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m6 6 12 12M18 6 6 18" /></svg>
                </button>
            </div>
        </div>
    </aside>
@endif
