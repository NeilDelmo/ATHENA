@props(['item', 'label' => null, 'tone' => null, 'variant' => 'filled', 'help' => false])

@php
    $actionLabel = $label ?? $item['label'];
@endphp

<button
    type="button"
    data-review-suggestion="{{ Illuminate\Support\Js::encode($item) }}"
    @if ($help) data-proposal-help @endif
    @click="$store.researchAssistant.{{ $help ? 'openProposalHelp' : 'startSuggestedReview' }}(JSON.parse($el.dataset.reviewSuggestion), $el)"
    :disabled="$store.researchAssistant.reviewLaunchBusy()"
    aria-controls="athena-assistant-workspace"
    aria-label="{{ $actionLabel }} for {{ $item['target_label'] }} with Athena"
    {{ $attributes->class(['inline-flex min-h-11 shrink-0 items-center gap-2 rounded-lg border px-3 py-2 text-xs font-semibold focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand disabled:cursor-wait disabled:opacity-50 dark:focus-visible:outline-rose-400', 'border-brand bg-white text-brand hover:bg-stone-50 dark:border-red-400 dark:bg-stone-900 dark:text-red-300 dark:hover:border-red-300 dark:hover:bg-stone-800' => $variant === 'outline', 'border-brand bg-brand text-white hover:border-brand-soft hover:bg-brand-soft dark:border-brand-soft dark:bg-brand dark:text-white dark:hover:border-brand-soft dark:hover:bg-brand-soft' => $variant !== 'outline' && $tone === 'brand', 'border-slate-200 text-brand hover:border-brand hover:bg-brand-wash dark:border-slate-700 dark:text-rose-300 dark:hover:border-rose-400 dark:hover:bg-rose-950/30' => $variant !== 'outline' && $tone !== 'brand']) }}
>
    {{ $actionLabel }}
    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="m9 5 7 7-7 7" stroke-linecap="round" stroke-linejoin="round" /></svg>
</button>
