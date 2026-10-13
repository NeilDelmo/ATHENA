@props(['items', 'tone' => null])

<section {{ $attributes->class(['space-y-3']) }} aria-labelledby="athena-review-heading" data-dashboard-assistant-suggestions>
    <div>
        <h2 id="athena-review-heading" @class(['text-base font-semibold', 'text-brand dark:text-rose-200' => $tone === 'brand', 'text-slate-950 dark:text-white' => $tone !== 'brand'])>Review with Athena</h2>
        <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Choose a proposal for a focused check. Findings appear after you start a review.</p>
    </div>
    <div @class(['divide-y overflow-hidden rounded-xl border', 'divide-stone-200 border-stone-200 bg-white dark:divide-stone-700 dark:border-stone-700 dark:bg-stone-900' => $tone === 'brand', 'divide-slate-200 border-slate-200 bg-white dark:divide-slate-800 dark:border-slate-800 dark:bg-slate-900' => $tone !== 'brand'])>
        @forelse ($items as $item)
            <article class="flex min-w-0 flex-col items-start gap-4 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5">
                <div class="min-w-0">
                    <h3 class="break-words text-sm font-semibold text-slate-950 dark:text-white">{{ $item['target_label'] }}</h3>
                    <p class="mt-1 text-xs leading-5 text-slate-600 dark:text-slate-400">{{ $item['evidence'] }}</p>
                </div>
                <x-dashboard-assistant-action :item="$item" :tone="$tone" />
            </article>
        @empty
            <p class="p-5 text-sm text-slate-600 dark:text-slate-400">No proposal reviews to suggest in this workspace yet.</p>
        @endforelse
    </div>
</section>
