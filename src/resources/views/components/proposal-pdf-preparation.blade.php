@props(['proposalDraft'])
@php($preparation = app(\App\Services\ProposalPdfPreparation::class)->status($proposalDraft))
<section data-proposal-pdf-preparation
    @if ($preparation['status'] !== 'failed') hidden @endif
    data-state-url="{{ route('faculty.proposal-drafts.review', $proposalDraft) }}"
    data-prepare-url="{{ auth()->user()->can('prepare', $proposalDraft) ? route('faculty.proposal-drafts.submission-files.prepare', $proposalDraft) : '' }}"
    data-pdf-state="{{ json_encode($preparation, JSON_THROW_ON_ERROR) }}"
    {{ $attributes->merge(['class' => 'rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm dark:border-amber-900 dark:bg-amber-950/20']) }}>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p data-pdf-preparation-message role="status" aria-live="polite" class="min-w-0 text-amber-950 dark:text-amber-100">We couldn't prepare your submission documents. Your work is saved.</p>
        @can('prepare', $proposalDraft)
            <button type="button" data-pdf-preparation-retry @if ($preparation['status'] !== 'failed') hidden @endif
                class="min-h-11 rounded-lg border border-amber-300 bg-white px-3 py-2 font-semibold text-amber-950 hover:bg-amber-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-amber-700 dark:border-amber-800 dark:bg-slate-900 dark:text-amber-100">Try again</button>
        @endcan
    </div>
</section>
