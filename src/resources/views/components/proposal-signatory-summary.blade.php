@props(['proposalDraft', 'paper'])
<section data-revision-section="section-signatories" class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
    <div class="flex flex-wrap items-center justify-between gap-3"><h3 class="text-sm font-bold dark:text-white">Signature names</h3><a href="{{ route('signatories.edit', $proposalDraft) }}" class="text-sm font-semibold text-red-700 dark:text-red-300">Choose signatories</a></div>
    <dl class="mt-3 grid gap-3 sm:grid-cols-2">
        @foreach(\App\Models\ProposalSignatory::FIELDS[$paper] ?? [] as $key => $label)
            <div><dt class="text-xs text-gray-500">{{ $label }}</dt><dd class="mt-1 text-sm font-semibold dark:text-white">{{ $proposalDraft->signatory_selections[$key]['name'] ?? 'Not selected' }}</dd></div>
        @endforeach
    </dl>
    <p class="mt-3 text-xs text-gray-500">Select names from the Research Head’s directory. Signature and date-signed lines stay blank.</p>
</section>
