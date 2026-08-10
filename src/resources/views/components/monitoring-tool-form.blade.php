@props(['topic', 'preparedReport' => null, 'revisionReport' => null])

@if ($preparedReport)
    <section class="rounded-2xl border border-blue-200 bg-blue-50 p-5">
        @if ($errors->has('preparation'))
            <p class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-xs font-semibold text-red-700">{{ $errors->first('preparation') }}</p>
        @endif
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-sm font-black text-blue-950">{{ $preparedReport->quarter_label }} {{ $preparedReport->version_label }} Monitoring Tool PDF prepared</p>
                <p class="mt-1 max-w-2xl text-xs leading-5 text-blue-800">Review this exact stored PDF before sending it to the Research Head. To change its contents, discard it and prepare a new file.</p>
                <p class="mt-2 text-[11px] font-semibold text-blue-700">Prepared {{ $preparedReport->prepared_at?->format('M d, Y g:i A') }}</p>
            </div>
            <div class="flex shrink-0 flex-wrap gap-2">
                <a href="{{ route('project-progress.monitoring-tool', $preparedReport) }}" class="inline-flex items-center justify-center rounded-xl border border-blue-300 bg-white px-4 py-2.5 text-xs font-bold text-blue-800 shadow-sm hover:bg-blue-100">Download prepared PDF</a>
                <form method="POST" action="{{ route('project-progress.submit-prepared', [$topic, $preparedReport]) }}">
                    @csrf
                    <button class="inline-flex items-center justify-center rounded-xl bg-blue-700 px-4 py-2.5 text-xs font-bold text-white shadow-sm hover:bg-blue-800">Submit to Research Head</button>
                </form>
                <form method="POST" action="{{ route('project-progress.discard-prepared', [$topic, $preparedReport]) }}">
                    @csrf
                    @method('DELETE')
                    <button class="inline-flex items-center justify-center rounded-xl border border-red-200 bg-white px-4 py-2.5 text-xs font-bold text-red-700 shadow-sm hover:bg-red-50">Discard</button>
                </form>
            </div>
        </div>
    </section>
@else

@php
    $defaultWorkPlan = $revisionReport?->work_plan ?: [[
        'activity' => '',
        'percent_weight' => '',
        'physical_target' => '',
        'target_completion_date' => '',
        'actual_accomplishment' => '',
        'accomplished_percentage' => '',
        'findings' => '',
    ]];
    $defaultBudget = $revisionReport?->budget_utilization ?: collect(['Purchase Request', 'Cash Advance', 'Request of Payment'])
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
    @if ($errors->any() || $revisionReport) open @endif
    x-data="monitoringToolForm({
        entries: @js($workPlanRows),
        previewUrl: @js(route('project-progress.preview', $topic)),
        csrfToken: @js(csrf_token()),
    })"
>
    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-5 py-4 text-sm font-black text-blue-900">
        <span>
            {{ $revisionReport ? 'Revise '.$revisionReport->quarter_label.' Monitoring Tool' : 'Submit monitoring tool' }}
            <span class="mt-1 block text-xs font-normal text-blue-700">BatStateU-REC-RES-03 · Revision 03{{ $revisionReport ? ' · '.$revisionReport->version_label.' is retained as the original record' : '' }}</span>
        </span>
        <span class="rounded-full bg-white px-3 py-1 text-[10px] font-black uppercase text-blue-700 shadow-sm">Open form</span>
    </summary>

    <form
        x-ref="form"
        method="POST"
        action="{{ route('project-progress.prepare', $topic) }}"
        enctype="multipart/form-data"
        class="space-y-6 border-t border-blue-100 bg-white p-5"
        @submit="submitting = true"
    >
        @csrf
        @if ($revisionReport)
            <input type="hidden" name="source_report_id" value="{{ $revisionReport->id }}">
        @endif

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
                <x-date-picker id="reporting_date" name="reporting_date" :value="old('reporting_date', $revisionReport?->reporting_date?->toDateString() ?? now()->toDateString())" :max="now()->toDateString()" required class="mt-1" />
            </div>
            <label class="text-[11px] font-bold text-gray-600">
                Tracking number <span class="font-normal text-gray-400">(optional)</span>
                <input type="text" name="tracking_number" value="{{ old('tracking_number', $revisionReport?->tracking_number) }}" maxlength="100" class="mt-1 block w-full rounded-xl border-gray-200 text-xs" placeholder="Enter the official tracking number">
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
                <x-date-picker id="prepared_by_date_signed" name="prepared_by_date_signed" :value="old('prepared_by_date_signed', $revisionReport?->prepared_by_date_signed?->toDateString())" :max="now()->toDateString()" class="mt-1" />
            </div>
            <label class="text-[11px] font-bold text-gray-600">Supporting attachment <span class="font-normal text-gray-400">(optional)</span>
                <input type="file" name="attachment" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png" class="mt-1 block w-full rounded-xl border border-gray-200 bg-white p-2 text-xs">
            </label>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 pt-5">
            <p class="text-xs text-gray-500">Prepare and review the official Revision 03 PDF before submitting it to the Research Head.</p>
            <div class="flex flex-wrap gap-2">
                <button type="button" @click="generatePreview" :disabled="previewLoading || submitting" class="rounded-xl border border-blue-200 bg-white px-5 py-3 text-xs font-bold text-blue-700 shadow-sm hover:bg-blue-50 disabled:cursor-wait disabled:opacity-60">
                    <span x-show="!previewLoading">Preview monitoring tool</span>
                    <span x-show="previewLoading" x-cloak>Generating preview...</span>
                </button>
                <button type="submit" :disabled="submitting || previewLoading" class="rounded-xl bg-blue-700 px-5 py-3 text-xs font-bold text-white shadow-sm disabled:cursor-wait disabled:opacity-60">
                    <span x-show="!submitting">Prepare official PDF</span>
                    <span x-show="submitting" x-cloak>Preparing PDF…</span>
                </button>
            </div>
        </div>

        <p x-show="previewError" x-cloak x-text="previewError" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-xs font-semibold text-red-700"></p>

        <section x-show="previewHtml" x-cloak x-ref="previewSection" class="space-y-3 rounded-2xl border border-gray-200 bg-gray-100 p-3 sm:p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-sm font-black text-gray-900">Monitoring form preview</p>
                    <p class="text-xs text-gray-500">This preview is generated from the current form values and has not been submitted.</p>
                </div>
                <button type="button" @click="printPreview" :disabled="!previewReady" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-bold text-gray-700 shadow-sm disabled:opacity-50">Print preview</button>
            </div>
            <iframe x-ref="previewFrame" :srcdoc="previewHtml" @load="hydratePreview" title="Monitoring tool document preview" class="h-[75vh] w-full rounded-xl border border-gray-300 bg-white shadow-inner"></iframe>
        </section>
    </form>
</details>
@endif
