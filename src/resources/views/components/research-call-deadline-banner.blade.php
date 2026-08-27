@props(['researchCall' => null])

@if ($researchCall)
    @php
        $deadline = $researchCall->closes_at->copy()->timezone(config('app.timezone'));
    @endphp

    <aside
        class="sticky top-[120px] z-20 border-b border-red-200 bg-white/95 shadow-sm backdrop-blur dark:border-red-950 dark:bg-slate-950/95"
        role="status"
        aria-live="polite"
        data-research-call-deadline-banner
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
                <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-red-700 text-white shadow-sm shadow-red-900/20" aria-hidden="true">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m5-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </span>
                <div class="min-w-0">
                    <p class="text-xs font-black uppercase tracking-[0.16em] text-red-700 dark:text-red-300">Submission deadline approaching</p>
                    <p class="mt-0.5 text-sm leading-5 text-gray-900 dark:text-white">
                        <span class="font-black">{{ $researchCall->title }}</span>
                        closes on <time datetime="{{ $deadline->toIso8601String() }}" class="font-bold">{{ $deadline->format('M j, Y \a\t g:i A') }} PHT</time>.
                    </p>
                </div>
            </div>
            <div class="flex shrink-0 items-center gap-2">
                <a wire:navigate href="{{ route('research-calls.index') }}" class="inline-flex min-h-10 items-center justify-center rounded-xl bg-red-700 px-4 py-2 text-xs font-black text-white shadow-sm transition hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 dark:bg-red-700 dark:hover:bg-red-600 dark:focus:ring-offset-slate-950">
                    View research call
                </a>
                <button type="button" @click="dismiss" :disabled="dismissing" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-black bg-white text-black shadow-sm transition hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 disabled:cursor-wait disabled:opacity-60 dark:border-white dark:bg-slate-950 dark:text-white dark:hover:bg-slate-900 dark:focus:ring-offset-slate-950" aria-label="Dismiss deadline reminder until tomorrow" title="Hide until tomorrow">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.3" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m6 6 12 12M18 6 6 18" /></svg>
                </button>
            </div>
        </div>
    </aside>
@endif
