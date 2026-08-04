@props(['comparison', 'proposalDraft'])

@if (($comparison['mismatches'] ?? []) !== [])
    <section role="alert" {{ $attributes->merge(['class' => 'border-l-4 border-red-600 bg-red-50 p-5 text-red-950 dark:bg-red-950/40 dark:text-red-100']) }}>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h3 class="text-sm font-black">Budget totals do not match</h3>
                <p class="mt-1 max-w-3xl text-xs leading-5">Attachment B and the Estimated Expense Breakdown contain different totals. No values were changed automatically. Review both forms and make the listed values agree before turning in the proposal.</p>
            </div>
            <span class="inline-flex w-fit shrink-0 rounded-full bg-red-600 px-3 py-1 text-[10px] font-black uppercase tracking-wider text-white">Submission blocked</span>
        </div>

        <div class="mt-4 overflow-x-auto rounded-lg border border-red-200 bg-white dark:border-red-900 dark:bg-slate-900">
            <table class="min-w-full divide-y divide-gray-200 text-left text-xs dark:divide-slate-800">
                <thead class="bg-gray-100 text-[10px] font-black uppercase tracking-wider text-gray-700 dark:bg-slate-800 dark:text-slate-200">
                    <tr>
                        <th scope="col" class="px-4 py-3">Inconsistent value</th>
                        <th scope="col" class="px-4 py-3 text-right">Attachment B</th>
                        <th scope="col" class="px-4 py-3 text-right">Expense Breakdown</th>
                        <th scope="col" class="px-4 py-3 text-right">Difference</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-gray-800 dark:divide-slate-800 dark:text-slate-200">
                    @foreach ($comparison['mismatches'] as $mismatch)
                        <tr>
                            <th scope="row" class="whitespace-nowrap px-4 py-3 font-black">{{ $mismatch['label'] }}</th>
                            <td class="whitespace-nowrap px-4 py-3 text-right font-semibold">Php {{ number_format($mismatch['line_item_budget'], 2) }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right font-semibold">Php {{ number_format($mismatch['expense_breakdown'], 2) }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right font-black text-red-700 dark:text-red-300">Php {{ number_format(abs($mismatch['difference']), 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4 flex flex-col gap-2 sm:flex-row">
            <a href="{{ route('faculty.proposal-drafts.line-item-budget.edit', $proposalDraft) }}" class="inline-flex w-full items-center justify-center rounded-lg bg-gray-950 px-4 py-2.5 text-xs font-black text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 dark:bg-white dark:text-gray-950 sm:w-auto">Review Attachment B</a>
            <a href="{{ route('faculty.proposal-drafts.expense-breakdown.edit', $proposalDraft) }}" class="inline-flex w-full items-center justify-center rounded-lg border border-red-300 bg-white px-4 py-2.5 text-xs font-black text-red-950 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 dark:bg-transparent dark:text-red-100 sm:w-auto">Review Expense Breakdown</a>
        </div>
    </section>
@endif
