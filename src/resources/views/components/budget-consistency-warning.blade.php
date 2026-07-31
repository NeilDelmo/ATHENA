@props(['comparison', 'proposalDraft'])

@if (($comparison['mismatches'] ?? []) !== [])
    <section role="alert" {{ $attributes->merge(['class' => 'rounded-2xl border border-amber-300 bg-amber-50 p-5 text-amber-950']) }}>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h3 class="text-sm font-black">Budget totals do not match</h3>
                <p class="mt-1 max-w-3xl text-xs leading-5">Attachment B and the Estimated Expense Breakdown contain different totals. No values were changed automatically. Review both forms and make the listed values agree before turning in the proposal.</p>
            </div>
            <span class="inline-flex w-fit shrink-0 rounded-full bg-amber-200 px-3 py-1 text-[10px] font-black uppercase tracking-wider text-amber-900">Submission blocked</span>
        </div>

        <div class="mt-4 overflow-x-auto rounded-xl border border-amber-200 bg-white">
            <table class="min-w-full divide-y divide-amber-200 text-left text-xs">
                <thead class="bg-amber-100 text-[10px] font-black uppercase tracking-wider text-amber-900">
                    <tr>
                        <th scope="col" class="px-4 py-3">Inconsistent value</th>
                        <th scope="col" class="px-4 py-3 text-right">Attachment B</th>
                        <th scope="col" class="px-4 py-3 text-right">Expense Breakdown</th>
                        <th scope="col" class="px-4 py-3 text-right">Difference</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-amber-100 text-gray-800">
                    @foreach ($comparison['mismatches'] as $mismatch)
                        <tr>
                            <th scope="row" class="whitespace-nowrap px-4 py-3 font-black">{{ $mismatch['label'] }}</th>
                            <td class="whitespace-nowrap px-4 py-3 text-right font-semibold">Php {{ number_format($mismatch['line_item_budget'], 2) }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right font-semibold">Php {{ number_format($mismatch['expense_breakdown'], 2) }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right font-black text-amber-900">Php {{ number_format(abs($mismatch['difference']), 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4 flex flex-col gap-2 sm:flex-row">
            <a href="{{ route('faculty.proposal-drafts.line-item-budget.edit', $proposalDraft) }}" class="inline-flex w-full items-center justify-center rounded-xl bg-amber-900 px-4 py-2.5 text-xs font-black text-white hover:bg-amber-950 focus:outline-none focus:ring-2 focus:ring-amber-900 focus:ring-offset-2 sm:w-auto">Review Attachment B</a>
            <a href="{{ route('faculty.proposal-drafts.expense-breakdown.edit', $proposalDraft) }}" class="inline-flex w-full items-center justify-center rounded-xl border border-amber-400 bg-white px-4 py-2.5 text-xs font-black text-amber-950 hover:bg-amber-100 focus:outline-none focus:ring-2 focus:ring-amber-900 focus:ring-offset-2 sm:w-auto">Review Expense Breakdown</a>
        </div>
    </section>
@endif
