<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-[10px] font-black uppercase tracking-[0.22em] text-red-600">{{ $report->quarter_label }} financial entry</p>
                <h2 class="mt-1 text-xl font-black text-gray-950 dark:text-white">Budget utilization</h2>
                <p class="mt-1 max-w-2xl text-sm text-gray-500 dark:text-slate-400">{{ $topic->title }}</p>
            </div>
            <x-back-link href="{{ request()->routeIs('project-budget.edit') ? route('research.show', $topic).'#project-monitoring' : route('research_secretary.dashboard') }}">Back to project monitoring</x-back-link>
        </div>
    </x-slot>

    @php
        $defaultRows = collect(['Purchase Request', 'Cash Advance', 'Request of Payment'])->map(fn ($type) => [
            'type' => $type,
            'details' => '',
            'amount_requested' => 0,
            'actual_amount' => 0,
            'remarks' => '',
        ])->all();
        $budgetRows = old('budget_utilization', $report->budget_utilization ?: $defaultRows);
    @endphp

    <div class="mx-auto max-w-5xl space-y-5 py-6 sm:px-6 lg:px-8">
        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="grid gap-px bg-gray-200 dark:bg-slate-800 md:grid-cols-3">
                <div class="bg-slate-950 p-5 text-white md:col-span-2">
                    <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Prepared monitoring report</p>
                    <p class="mt-2 text-base font-black">{{ $report->quarter_label }} · {{ $report->reporting_period_label }}</p>
                    <p class="mt-1 text-xs text-slate-400">Researcher · {{ $report->submitter->name }}</p>
                </div>
                <div class="bg-red-700 p-5 text-white">
                    <p class="text-[10px] font-black uppercase tracking-[0.18em] text-red-100">Approved project budget</p>
                    <p class="mt-2 text-2xl font-black tabular-nums">₱{{ number_format((float) $topic->estimated_budget, 2) }}</p>
                </div>
            </div>

            <div class="border-b border-gray-200 bg-amber-50 px-5 py-3 text-xs leading-5 text-amber-900 dark:border-slate-800 dark:bg-amber-950/30 dark:text-amber-100 sm:px-6">
                @if (Auth::id() === $topic->research_secretary_id)
                    You are the priority project secretary for this section. Other authorized project members may also complete it when needed.
                @else
                    {{ $topic->researchSecretary?->name ?? 'The assigned project secretary' }} has priority for this section, but you may complete it as an authorized project member.
                @endif
            </div>

            <form method="POST" action="{{ request()->routeIs('project-budget.edit') ? route('project-budget.update', [$topic, $report]) : route('research_secretary.projects.budget.update', [$topic, $report]) }}" x-data="budgetUtilizationForm({ rows: @js($budgetRows), approvedBudget: @js((float) $topic->estimated_budget) })" class="space-y-6 p-5 sm:p-6">
                @csrf
                @method('PUT')

                @if ($errors->any())
                    <div role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-xs text-red-700">
                        <p class="font-black">Please correct the budget entries.</p>
                        <ul class="mt-2 list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </div>
                @endif

                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-950"><p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Requested</p><p class="mt-2 text-xl font-black tabular-nums text-slate-950 dark:text-white" x-text="currency(requestedTotal())"></p></div>
                    <div class="rounded-xl border border-red-200 bg-red-50 p-4 dark:border-red-900 dark:bg-red-950/30"><p class="text-[10px] font-black uppercase tracking-wider text-red-500">Utilized</p><p class="mt-2 text-xl font-black tabular-nums text-red-900 dark:text-red-100" x-text="currency(spentTotal())"></p><p class="mt-1 text-[11px] font-bold text-red-600" x-text="`${utilizationPercentage().toFixed(1)}% utilized`"></p></div>
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-900 dark:bg-emerald-950/30"><p class="text-[10px] font-black uppercase tracking-wider text-emerald-600">Remaining</p><p class="mt-2 text-xl font-black tabular-nums text-emerald-900 dark:text-emerald-100" x-text="currency(remainingBudget())"></p></div>
                </div>

                <div class="space-y-4">
                    <template x-for="(row, index) in rows" :key="row.type">
                        <article class="overflow-hidden rounded-2xl border border-gray-200 dark:border-slate-700">
                            <div class="flex items-center justify-between border-b border-gray-100 bg-gray-50 px-4 py-3 dark:border-slate-700 dark:bg-slate-950">
                                <p class="text-sm font-black text-gray-950 dark:text-white" x-text="row.type"></p>
                                <span class="rounded-full bg-white px-2.5 py-1 text-[10px] font-black text-gray-500 ring-1 ring-gray-200 dark:bg-slate-900 dark:ring-slate-700" x-text="currency(row.actual_amount)"></span>
                            </div>
                            <input type="hidden" :name="`budget_utilization[${index}][type]`" :value="row.type">
                            <div class="grid gap-4 p-4 sm:grid-cols-2">
                                <label class="text-xs font-bold text-gray-700 dark:text-slate-200 sm:col-span-2">Details of request<textarea :name="`budget_utilization[${index}][details]`" x-model="row.details" rows="2" maxlength="300" autocomplete="off" class="mt-1.5 block w-full rounded-xl border-gray-300 text-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-950 dark:text-white"></textarea></label>
                                <label class="text-xs font-bold text-gray-700 dark:text-slate-200">Amount requested (PHP)<input :name="`budget_utilization[${index}][amount_requested]`" x-model.number="row.amount_requested" type="number" inputmode="decimal" min="0" step="0.01" required class="mt-1.5 block w-full rounded-xl border-gray-300 text-sm tabular-nums focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-950 dark:text-white"></label>
                                <label class="text-xs font-bold text-gray-700 dark:text-slate-200">Amount spent (PHP)<input :name="`budget_utilization[${index}][actual_amount]`" x-model.number="row.actual_amount" type="number" inputmode="decimal" min="0" step="0.01" required class="mt-1.5 block w-full rounded-xl border-gray-300 text-sm tabular-nums focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-950 dark:text-white"></label>
                                <label class="text-xs font-bold text-gray-700 dark:text-slate-200 sm:col-span-2">Remarks or challenges <span class="font-normal text-gray-400">(optional)</span><textarea :name="`budget_utilization[${index}][remarks]`" x-model="row.remarks" rows="2" maxlength="300" autocomplete="off" class="mt-1.5 block w-full rounded-xl border-gray-300 text-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-950 dark:text-white"></textarea></label>
                            </div>
                        </article>
                    </template>
                </div>

                <div class="flex flex-col gap-3 border-t border-gray-100 pt-5 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800">
                    <p class="max-w-xl text-xs leading-5 text-gray-500 dark:text-slate-400">Saving refreshes the official Monitoring Tool PDF and records your account and completion time. The researcher can then submit it to the Research Head.</p>
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('project-progress.monitoring-tool', $report) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-gray-300 bg-white px-4 py-2 text-xs font-bold text-gray-700 shadow-sm hover:bg-gray-50 dark:border-slate-600 dark:bg-slate-900 dark:text-white">Review PDF</a>
                        <button class="inline-flex min-h-11 items-center justify-center rounded-xl bg-red-700 px-5 py-2 text-xs font-black text-white shadow-sm hover:bg-red-800">Confirm budget utilization</button>
                    </div>
                </div>
            </form>
        </section>
    </div>
</x-app-layout>
