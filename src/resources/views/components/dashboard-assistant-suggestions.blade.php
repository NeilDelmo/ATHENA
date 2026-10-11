@props(['items'])

<section {{ $attributes->class(['space-y-3']) }} aria-labelledby="athena-review-heading" data-dashboard-assistant-suggestions>
    <div>
        <h2 id="athena-review-heading" class="text-base font-semibold text-slate-950 dark:text-white">Review with Athena</h2>
        <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Choose a proposal for a focused check. Findings appear after you start a review.</p>
    </div>
    <div class="divide-y divide-slate-200 overflow-hidden rounded-xl border border-slate-200 bg-white dark:divide-slate-800 dark:border-slate-800 dark:bg-slate-900">
        @forelse ($items as $item)
            <article class="flex min-w-0 flex-col items-start gap-4 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5">
                <div class="min-w-0">
                    <h3 class="break-words text-sm font-semibold text-slate-950 dark:text-white">{{ $item['target_label'] }}</h3>
                    <p class="mt-1 text-xs leading-5 text-slate-600 dark:text-slate-400">{{ $item['evidence'] }}</p>
                </div>
                <button
                    type="button"
                    data-review-suggestion="{{ Illuminate\Support\Js::encode($item) }}"
                    @click="$store.researchAssistant.startSuggestedReview(JSON.parse($el.dataset.reviewSuggestion), $el)"
                    :disabled="$store.researchAssistant.reviewLaunchBusy()"
                    aria-controls="athena-assistant-workspace"
                    aria-label="{{ $item['label'] }} for {{ $item['target_label'] }} with Athena"
                    class="inline-flex min-h-11 shrink-0 items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-brand hover:border-brand hover:bg-brand-wash focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand disabled:cursor-wait disabled:opacity-50 dark:border-slate-700 dark:text-rose-300 dark:hover:border-rose-400 dark:hover:bg-rose-950/30 dark:focus-visible:outline-rose-400"
                >
                    {{ $item['label'] }}
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="m9 5 7 7-7 7" stroke-linecap="round" stroke-linejoin="round" /></svg>
                </button>
            </article>
        @empty
            <p class="p-5 text-sm text-slate-600 dark:text-slate-400">No proposal reviews to suggest in this workspace yet.</p>
        @endforelse
    </div>
</section>
