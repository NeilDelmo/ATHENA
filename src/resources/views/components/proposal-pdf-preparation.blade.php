@props(['proposalDraft'])
@php($preparation = app(\App\Services\ProposalPdfPreparation::class)->status($proposalDraft))
<section data-proposal-pdf-preparation
    data-state-url="{{ route('faculty.proposal-drafts.review', $proposalDraft) }}"
    data-prepare-url="{{ auth()->user()->can('prepare', $proposalDraft) ? route('faculty.proposal-drafts.submission-files.prepare', $proposalDraft) : '' }}"
    data-pdf-state="{{ json_encode($preparation, JSON_THROW_ON_ERROR) }}"
    class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-red-100 bg-red-50/60 px-4 py-3 text-sm dark:border-red-950 dark:bg-red-950/20">
    <div role="status" aria-live="polite" aria-atomic="true" class="min-w-0">
        <p data-pdf-preparation-message class="font-semibold text-red-950 dark:text-red-100">{{ $preparation['message'] }}</p>
        <p data-pdf-preparation-count class="mt-1 text-xs text-slate-600 dark:text-slate-300">{{ $preparation['prepared'] }} / {{ $preparation['total'] }} PDFs</p>
    </div>
    @can('prepare', $proposalDraft)
        <button type="button" data-pdf-preparation-retry @if ($preparation['status'] !== 'failed') hidden @endif
            class="min-h-11 rounded-lg border border-red-200 bg-white px-3 py-2 font-semibold text-red-800 hover:bg-red-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-red-700 dark:border-red-800 dark:bg-slate-900 dark:text-red-200">Retry PDF preparation</button>
    @endcan
</section>
