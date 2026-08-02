@props(['topic'])

@php
    $defaultWorkPlan = [[
        'activity' => '',
        'percent_weight' => '',
        'physical_target' => '',
        'target_completion_date' => '',
        'actual_accomplishment' => '',
        'accomplished_percentage' => '',
        'findings' => '',
    ]];
    $defaultBudget = collect(['Purchase Request', 'Cash Advance', 'Request of Payment'])
        ->map(fn ($type) => [
            'type' => $type,
            'details' => '',
            'amount_requested' => '0',
            'actual_amount' => '0',
            'remarks' => '',
        ])->all();
    $workPlanRows = old('work_plan', $defaultWorkPlan);
    $workPlanRows = is_array($workPlanRows) && $workPlanRows !== [] ? array_values(array_filter($workPlanRows, 'is_array')) : $defaultWorkPlan;
    $workPlanRows = $workPlanRows !== [] ? $workPlanRows : $defaultWorkPlan;
    $budgetRows = old('budget_utilization', $defaultBudget);
    $budgetRows = is_array($budgetRows) ? $budgetRows : $defaultBudget;
@endphp

<details
    class="overflow-hidden rounded-2xl border border-blue-100 bg-blue-50/50"
    @if ($errors->any()) open @endif
    x-data="{
        entries: @js($workPlanRows),
        submitting: false,
        addEntry() {
            if (this.entries.length >= 11) return;
            this.entries.push({ activity: '', percent_weight: '', physical_target: '', target_completion_date: '', actual_accomplishment: '', accomplished_percentage: '', findings: '' });
        },
        removeEntry(index) {
            if (this.entries.length > 1) this.entries.splice(index, 1);
        }
    }"
>
    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-5 py-4 text-sm font-black text-blue-900">
        <span>
            Submit monitoring tool
            <span class="mt-1 block text-xs font-normal text-blue-700">BatStateU-REC-RES-03 · Revision 03</span>
        </span>
        <span class="rounded-full bg-white px-3 py-1 text-[10px] font-black uppercase text-blue-700 shadow-sm">Open form</span>
    </summary>

    <form
        method="POST"
        action="{{ route('project-progress.store', $topic) }}"
        enctype="multipart/form-data"
        class="space-y-6 border-t border-blue-100 bg-white p-5"
        @submit="submitting = true"
    >
        @csrf

        @if ($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-xs text-red-700">
                <p class="font-black">Please correct the highlighted monitoring fields.</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid gap-3 rounded-xl bg-gray-50 p-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="sm:col-span-2">
                <p class="text-[10px] font-black uppercase tracking-wide text-gray-400">Research project title</p>
                <p class="mt-1 text-xs font-bold text-gray-800">{{ $topic->title }}</p>
            </div>
            <div>
                <p class="text-[10px] font-black uppercase tracking-wide text-gray-400">Project leader</p>
                <p class="mt-1 text-xs font-bold text-gray-800">{{ $topic->user->name }}</p>
            </div>
            <div>
                <p class="text-[10px] font-black uppercase tracking-wide text-gray-400">Project cost / duration</p>
                <p class="mt-1 text-xs font-bold text-gray-800">₱{{ number_format((float) $topic->estimated_budget, 2) }} · {{ $topic->estimated_duration_months }} months</p>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="reporting_date" class="text-[11px] font-bold text-gray-600">Reporting date</label>
                <x-date-picker id="reporting_date" name="reporting_date" :value="old('reporting_date', now()->toDateString())" :max="now()->toDateString()" required class="mt-1" />
            </div>
            <label class="text-[11px] font-bold text-gray-600">
                Tracking number <span class="font-normal text-gray-400">(optional)</span>
                <input type="text" name="tracking_number" value="{{ old('tracking_number') }}" maxlength="100" class="mt-1 block w-full rounded-xl border-gray-200 text-xs" placeholder="Enter the official tracking number">
            </label>
        </div>

        <section class="space-y-3">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <p class="text-sm font-black text-gray-900">A. Work Plan</p>
                    <p class="mt-1 text-xs text-gray-500">Add up to eleven activities. Accomplished percentages are added for the report total.</p>
                </div>
                <button type="button" @click="addEntry" :disabled="entries.length >= 11" class="rounded-xl border border-blue-200 bg-blue-50 px-3 py-2 text-xs font-bold text-blue-700 disabled:cursor-not-allowed disabled:opacity-40">Add activity</button>
            </div>

            <template x-for="(entry, index) in entries" :key="index">
                <article class="rounded-xl border border-gray-200 p-4">
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <p class="text-xs font-black text-gray-700">Activity <span x-text="index + 1"></span></p>
                        <button type="button" @click="removeEntry(index)" x-show="entries.length > 1" class="text-[11px] font-bold text-red-600">Remove</button>
                    </div>
                    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                        <label class="text-[11px] font-bold text-gray-600 md:col-span-2">Activity
                            <textarea :name="`work_plan[${index}][activity]`" x-model="entry.activity" rows="2" maxlength="300" required class="mt-1 block w-full rounded-xl border-gray-200 text-xs"></textarea>
                        </label>
                        <label class="text-[11px] font-bold text-gray-600">Percent weight
                            <input type="number" :name="`work_plan[${index}][percent_weight]`" x-model="entry.percent_weight" min="0" max="100" step="0.01" required class="mt-1 block w-full rounded-xl border-gray-200 text-xs">
                        </label>
                        <label class="text-[11px] font-bold text-gray-600">Target completion date
                            <input type="date" :name="`work_plan[${index}][target_completion_date]`" x-model="entry.target_completion_date" required class="mt-1 block w-full rounded-xl border-gray-200 text-xs">
                        </label>
                        <label class="text-[11px] font-bold text-gray-600 md:col-span-2">Physical target (quantifiable)
                            <textarea :name="`work_plan[${index}][physical_target]`" x-model="entry.physical_target" rows="2" maxlength="300" required class="mt-1 block w-full rounded-xl border-gray-200 text-xs"></textarea>
                        </label>
                        <label class="text-[11px] font-bold text-gray-600 md:col-span-2">Actual accomplishment
                            <textarea :name="`work_plan[${index}][actual_accomplishment]`" x-model="entry.actual_accomplishment" rows="2" maxlength="500" required class="mt-1 block w-full rounded-xl border-gray-200 text-xs"></textarea>
                        </label>
                        <label class="text-[11px] font-bold text-gray-600">Percentage of accomplished tasks
                            <input type="number" :name="`work_plan[${index}][accomplished_percentage]`" x-model="entry.accomplished_percentage" min="0" max="100" step="0.01" required class="mt-1 block w-full rounded-xl border-gray-200 text-xs">
                        </label>
                        <label class="text-[11px] font-bold text-gray-600 md:col-span-2 xl:col-span-3">Notable findings or challenges <span class="font-normal text-gray-400">(optional)</span>
                            <textarea :name="`work_plan[${index}][findings]`" x-model="entry.findings" rows="2" maxlength="500" class="mt-1 block w-full rounded-xl border-gray-200 text-xs"></textarea>
                        </label>
                    </div>
                </article>
            </template>
        </section>

        <section class="space-y-3">
            <div>
                <p class="text-sm font-black text-gray-900">B. Budget Utilization</p>
                <p class="mt-1 text-xs text-gray-500">The three request types follow the client template. Enter zero when a type was not used.</p>
            </div>

            <div class="space-y-3">
                @foreach ($budgetRows as $index => $budget)
                    @php
                        $budget = is_array($budget) ? $budget : [];
                        $budgetType = $budget['type'] ?? ($defaultBudget[$index]['type'] ?? 'Request');
                    @endphp
                    <article class="rounded-xl border border-gray-200 p-4">
                        <input type="hidden" name="budget_utilization[{{ $index }}][type]" value="{{ $budgetType }}">
                        <p class="text-xs font-black text-gray-800">{{ $budgetType }}</p>
                        <div class="mt-3 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                            <label class="text-[11px] font-bold text-gray-600 md:col-span-2">Details of request
                                <textarea name="budget_utilization[{{ $index }}][details]" rows="2" maxlength="300" class="mt-1 block w-full rounded-xl border-gray-200 text-xs">{{ $budget['details'] ?? '' }}</textarea>
                            </label>
                            <label class="text-[11px] font-bold text-gray-600">Amount requested
                                <input type="number" name="budget_utilization[{{ $index }}][amount_requested]" value="{{ $budget['amount_requested'] ?? 0 }}" min="0" step="0.01" required class="mt-1 block w-full rounded-xl border-gray-200 text-xs">
                            </label>
                            <label class="text-[11px] font-bold text-gray-600">Actual amount disbursed
                                <input type="number" name="budget_utilization[{{ $index }}][actual_amount]" value="{{ $budget['actual_amount'] ?? 0 }}" min="0" step="0.01" required class="mt-1 block w-full rounded-xl border-gray-200 text-xs">
                            </label>
                            <label class="text-[11px] font-bold text-gray-600 md:col-span-2 xl:col-span-4">Remarks or challenges <span class="font-normal text-gray-400">(optional)</span>
                                <textarea name="budget_utilization[{{ $index }}][remarks]" rows="2" maxlength="300" class="mt-1 block w-full rounded-xl border-gray-200 text-xs">{{ $budget['remarks'] ?? '' }}</textarea>
                            </label>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        <div class="grid gap-4 rounded-xl bg-gray-50 p-4 sm:grid-cols-2">
            <div>
                <label for="prepared_by_date_signed" class="text-[11px] font-bold text-gray-600">Prepared-by date signed <span class="font-normal text-gray-400">(optional)</span></label>
                <x-date-picker id="prepared_by_date_signed" name="prepared_by_date_signed" :value="old('prepared_by_date_signed')" :max="now()->toDateString()" class="mt-1" />
            </div>
            <label class="text-[11px] font-bold text-gray-600">Supporting attachment <span class="font-normal text-gray-400">(optional)</span>
                <input type="file" name="attachment" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png" class="mt-1 block w-full rounded-xl border border-gray-200 bg-white p-2 text-xs">
            </label>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 pt-5">
            <p class="text-xs text-gray-500">The system will generate the official Revision 03 Word form after submission.</p>
            <button type="submit" :disabled="submitting" class="rounded-xl bg-blue-700 px-5 py-3 text-xs font-bold text-white shadow-sm disabled:cursor-wait disabled:opacity-60">
                <span x-show="!submitting">Submit monitoring tool</span>
                <span x-show="submitting" x-cloak>Submitting…</span>
            </button>
        </div>
    </form>
</details>
