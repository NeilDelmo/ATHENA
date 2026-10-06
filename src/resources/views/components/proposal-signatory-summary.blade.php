@props(['proposalDraft', 'paper'])
<section data-revision-section="section-signatories" class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
    <h3 class="text-sm font-bold dark:text-white">Signature names</h3>
    @php($signatorySelections = $proposalDraft->resolvedSignatorySelections())
    <dl class="mt-3 grid gap-3 sm:grid-cols-2">
        @foreach(\App\Models\ProposalSignatory::FIELDS[$paper] ?? [] as $key => $label)
            <div><dt class="text-xs text-gray-500">{{ $label }}</dt><dd class="mt-1 text-sm font-semibold dark:text-white">{{ $signatorySelections[$key]['name'] ?? 'Not configured' }}</dd></div>
        @endforeach
    </dl>
    <p class="mt-3 text-xs text-gray-500">Names are filled automatically from defaults managed by the Research Head or Research Office Secretary. Signature and date lines remain blank for signing.</p>
</section>
