@props(['topic', 'preparedReport' => null, 'revisionReport' => null, 'monitoringDraft' => null, 'standalone' => false, 'quarterOptions' => [], 'selectedReportingDate' => null])

@if ($preparedReport)
    <section class="rounded-2xl border border-red-200 bg-red-50 p-5 dark:border-red-950 dark:bg-slate-950">
        @if ($errors->has('preparation'))
            <p class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-xs font-semibold text-red-700">{{ $errors->first('preparation') }}</p>
        @endif
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-sm font-black text-gray-950 dark:text-white">{{ $preparedReport->quarter_label }} {{ $preparedReport->version_label }} Monitoring Tool PDF prepared</p>
                <p class="mt-1 max-w-2xl text-xs leading-5 text-gray-700 dark:text-slate-300">Review this exact stored PDF before sending it to the Research Head. To change its contents, discard it and prepare a new file.</p>
                <p class="mt-2 text-[11px] font-semibold text-red-700 dark:text-red-300">Prepared {{ $preparedReport->prepared_at?->format('M d, Y g:i A') }}</p>
            </div>
            <div class="flex shrink-0 flex-wrap gap-2">
                <a href="{{ route('project-progress.monitoring-tool', $preparedReport) }}" class="inline-flex items-center justify-center rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-xs font-bold text-gray-900 shadow-sm hover:bg-gray-50 dark:border-slate-600 dark:bg-slate-900 dark:text-white dark:hover:bg-slate-800">Download prepared PDF</a>
                <form method="POST" action="{{ route('project-progress.submit-prepared', [$topic, $preparedReport]) }}">
                    @csrf
                    <button class="inline-flex items-center justify-center rounded-xl bg-gray-950 px-4 py-2.5 text-xs font-bold text-white shadow-sm hover:bg-black dark:bg-white dark:text-gray-950 dark:hover:bg-gray-200">Submit to Research Head</button>
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
    $draftData = is_array($monitoringDraft?->source_data) ? $monitoringDraft->source_data : [];
    $defaultWorkPlan = $draftData['work_plan'] ?? $revisionReport?->work_plan ?? [[
        'activity' => '',
        'percent_weight' => '',
        'physical_target' => '',
        'target_completion_date' => '',
        'actual_accomplishment' => '',
        'accomplished_percentage' => '',
        'findings' => '',
    ]];
    $defaultBudget = $draftData['budget_utilization'] ?? $revisionReport?->budget_utilization ?? collect(['Purchase Request', 'Cash Advance', 'Request of Payment'])
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
    $defaultReportingDate = array_key_exists('reporting_date', $draftData)
        ? $draftData['reporting_date']
        : $revisionReport?->reporting_date?->toDateString() ?? $selectedReportingDate ?? now()->toDateString();
    $defaultTrackingNumber = array_key_exists('tracking_number', $draftData)
        ? $draftData['tracking_number']
        : $revisionReport?->tracking_number;
    $defaultPreparedByDate = array_key_exists('prepared_by_date_signed', $draftData)
        ? $draftData['prepared_by_date_signed']
        : $revisionReport?->prepared_by_date_signed?->toDateString();
@endphp

<section
    class="overflow-hidden rounded-xl bg-white dark:bg-slate-900"
    data-monitoring-tool-autosave="true"
    x-data="monitoringToolForm({
        entries: @js($workPlanRows),
        previewUrl: @js(route('project-progress.preview', $topic)),
        draftSaveUrl: @js(route('project-progress.draft', $topic)),
        initialDraftVersion: @js((int) ($monitoringDraft?->lock_version ?? 0)),
        csrfToken: @js(csrf_token()),
    })"
>
    @if (! $standalone)
    <header class="flex items-center justify-between gap-3 px-5 py-4 text-sm font-black text-gray-950 dark:text-white">
        <span>
            {{ $revisionReport ? 'Revise '.$revisionReport->quarter_label.' Monitoring Tool' : 'Submit monitoring tool' }}
            <span class="mt-1 block text-xs font-normal text-red-700 dark:text-red-300">BatStateU-REC-RES-03 · Revision 03{{ $revisionReport ? ' · '.$revisionReport->version_label.' is retained as the original record' : '' }}</span>
        </span>
        <span class="rounded-full bg-gray-950 px-3 py-1 text-[10px] font-black uppercase text-white shadow-sm dark:bg-white dark:text-gray-950">Open form</span>
    </header>
    @endif

    <form
        x-ref="form"
        data-monitoring-tool-autosave-form
        method="POST"
        action="{{ route('project-progress.prepare', $topic) }}"
        enctype="multipart/form-data"
        class="space-y-6 border-t border-red-200 bg-white p-5 dark:border-red-950 dark:bg-slate-900"
        @submit="submitting = true"
    >
        @csrf
        @if ($revisionReport)
            <input type="hidden" name="source_report_id" value="{{ $revisionReport->id }}">
        @endif
        <input type="hidden" name="draft_version" value="{{ $monitoringDraft?->lock_version ?? 0 }}">

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

        <x-proposal-autosave-status />

        <div class="grid gap-3 rounded-xl bg-gray-50 p-4 dark:bg-slate-950 sm:grid-cols-2 lg:grid-cols-4">
            <div class="sm:col-span-2">
                <p class="text-[10px] font-black uppercase tracking-wide text-gray-400">Research project title</p>
                <p class="mt-1 text-sm font-medium text-gray-800 dark:text-slate-200">{{ $topic->title }}</p>
            </div>
            <div>
                <p class="text-[10px] font-black uppercase tracking-wide text-gray-400">Project leader</p>
                <p class="mt-1 text-sm font-medium text-gray-800 dark:text-slate-200">{{ $topic->user->name }}</p>
            </div>
            <div>
                <p class="text-[10px] font-black uppercase tracking-wide text-gray-400">Project cost / duration</p>
                <p class="mt-1 text-sm font-medium text-gray-800 dark:text-slate-200">₱{{ number_format((float) $topic->estimated_budget, 2) }} · {{ $topic->estimated_duration_months }} months</p>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <label class="text-sm font-medium text-gray-700 dark:text-slate-200">Reporting quarter
                @if ($revisionReport)
                    <p class="mt-2 font-semibold">{{ $revisionReport->quarter_label }} · {{ $revisionReport->reporting_period_label }}</p>
                    <input type="hidden" name="reporting_date" value="{{ $defaultReportingDate }}">
                @else
                    <select name="reporting_date" required class="mt-2 block w-full rounded-lg border-gray-300 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                        @foreach ($quarterOptions as $quarter)
                            @php
                                $selected = str_starts_with((string) old('reporting_date', $defaultReportingDate), $quarter['year'].'-') && (int) ceil((int) substr((string) old('reporting_date', $defaultReportingDate), 5, 2) / 3) === $quarter['quarter'];
                            @endphp
                            <option value="{{ $selected ? old('reporting_date', $defaultReportingDate) : $quarter['reporting_date'] }}" @selected($selected)>{{ $quarter['label'] }} {{ $quarter['year'] }} · {{ $quarter['period'] }}</option>
                        @endforeach
                    </select>
                @endif
            </label>
            <p class="self-center text-xs leading-5 text-gray-500 dark:text-slate-400">Each calendar quarter has its own report. Earlier open quarters can still be submitted; a requested revision stays in its original quarter.</p>
        </div>

        <section class="space-y-3">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <p class="text-base font-semibold text-gray-900 dark:text-white">Activities and progress</p>
                    <p class="mt-1 text-xs text-gray-500">List this quarter’s activities. Set each activity’s share of the project and how much of it is complete. You can add up to 11 activities.</p>
                </div>
                <button type="button" @click="addEntry" :disabled="entries.length >= 11" class="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-xs font-bold text-red-700 disabled:cursor-not-allowed disabled:opacity-40">Add activity</button>
            </div>

            <template x-for="(entry, index) in entries" :key="index">
                <article class="rounded-xl border border-gray-200 p-4 dark:border-slate-700">
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <p class="text-xs font-black text-gray-700">Activity <span x-text="index + 1"></span></p>
                        <button type="button" @click="removeEntry(index)" x-show="entries.length > 1" class="text-[11px] font-bold text-red-600">Remove</button>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="text-sm font-medium text-gray-700 dark:text-slate-200 sm:col-span-2">Activity
                            <textarea :name="`work_plan[${index}][activity]`" x-model="entry.activity" rows="2" maxlength="300" required placeholder="Describe a measurable activity or output" class="mt-1 block w-full rounded-lg border-gray-300 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white"></textarea>
                        </label>
                        <label class="text-sm font-medium text-gray-700 dark:text-slate-200">Share of project (%)
                            <input type="number" :name="`work_plan[${index}][percent_weight]`" x-model="entry.percent_weight" @input="updateEntryProgress(entry)" min="0" max="100" step="0.01" required class="mt-1 block w-full rounded-lg border-gray-300 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                        </label>
                        <label class="text-sm font-medium text-gray-700 dark:text-slate-200">Target completion date
                            <input type="date" :name="`work_plan[${index}][target_completion_date]`" x-model="entry.target_completion_date" required class="mt-1 block w-full rounded-lg border-gray-300 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                        </label>
                        <label class="text-sm font-medium text-gray-700 dark:text-slate-200 sm:col-span-2">Planned output
                            <textarea :name="`work_plan[${index}][physical_target]`" x-model="entry.physical_target" rows="2" maxlength="300" required placeholder="Describe a measurable activity or output" class="mt-1 block w-full rounded-lg border-gray-300 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white"></textarea>
                        </label>
                        <label class="text-sm font-medium text-gray-700 dark:text-slate-200 sm:col-span-2">What was completed
                            <textarea :name="`work_plan[${index}][actual_accomplishment]`" x-model="entry.actual_accomplishment" rows="2" maxlength="500" required placeholder="State what was achieved, or explain why no work was completed" class="mt-1 block w-full rounded-lg border-gray-300 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white"></textarea>
                        </label>
                        <label class="text-sm font-medium text-gray-700 dark:text-slate-200">Activity completed (%)
                            <input type="number" x-model="entry.completion" @input="updateEntryProgress(entry)" min="0" max="100" step="0.01" required class="mt-1 block w-full rounded-lg border-gray-300 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                            <input type="hidden" :name="`work_plan[${index}][accomplished_percentage]`" :value="entry.accomplished_percentage">
                            <span class="mt-1 block text-xs font-normal text-gray-500">A 20% activity that is halfway done contributes 10% to project progress.</span>
                        </label>
                        <label class="text-sm font-medium text-gray-700 dark:text-slate-200 sm:col-span-2">Notable findings or challenges <span class="font-normal text-gray-400">(optional)</span>
                            <textarea :name="`work_plan[${index}][findings]`" x-model="entry.findings" rows="2" maxlength="500" class="mt-1 block w-full rounded-lg border-gray-300 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white"></textarea>
                        </label>
                    </div>
                </article>
            </template>
        </section>

        <section class="space-y-3">
            <div>
                <p class="text-base font-semibold text-gray-900 dark:text-white">Spending this quarter</p>
                <p class="mt-1 text-xs text-gray-500">Open only the request types you used. Leave amounts at zero when there was no spending.</p>
            </div>

            <div class="space-y-3">
                @foreach ($budgetRows as $index => $budget)
                    @php
                        $budget = is_array($budget) ? $budget : [];
                        $budgetType = $budget['type'] ?? ($defaultBudget[$index]['type'] ?? 'Request');
                    @endphp
                    <details class="rounded-xl border border-gray-200 p-4 dark:border-slate-700" @if ((float) ($budget['amount_requested'] ?? 0) > 0 || filled($budget['details'] ?? null)) open @endif>
                        <input type="hidden" name="budget_utilization[{{ $index }}][type]" value="{{ $budgetType }}">
                        <summary class="cursor-pointer text-sm font-semibold text-gray-800 dark:text-slate-200">{{ $budgetType }}</summary>
                        <div class="mt-3 grid gap-4 sm:grid-cols-2">
                            <label class="text-sm font-medium text-gray-700 dark:text-slate-200 sm:col-span-2">Details of request
                                <textarea name="budget_utilization[{{ $index }}][details]" rows="2" maxlength="300" class="mt-1 block w-full rounded-lg border-gray-300 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">{{ $budget['details'] ?? '' }}</textarea>
                            </label>
                            <label class="text-sm font-medium text-gray-700 dark:text-slate-200">Amount requested (PHP)
                                <input type="number" name="budget_utilization[{{ $index }}][amount_requested]" value="{{ $budget['amount_requested'] ?? 0 }}" min="0" step="0.01" required class="mt-1 block w-full rounded-lg border-gray-300 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                            </label>
                            <label class="text-sm font-medium text-gray-700 dark:text-slate-200">Amount spent (PHP)
                                <input type="number" name="budget_utilization[{{ $index }}][actual_amount]" value="{{ $budget['actual_amount'] ?? 0 }}" min="0" step="0.01" required class="mt-1 block w-full rounded-lg border-gray-300 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                            </label>
                            <label class="text-sm font-medium text-gray-700 dark:text-slate-200 sm:col-span-2">Remarks or challenges <span class="font-normal text-gray-400">(optional)</span>
                                <textarea name="budget_utilization[{{ $index }}][remarks]" rows="2" maxlength="300" class="mt-1 block w-full rounded-lg border-gray-300 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">{{ $budget['remarks'] ?? '' }}</textarea>
                            </label>
                        </div>
                    </details>
                @endforeach
            </div>
        </section>

        <details class="rounded-xl border border-gray-200 p-4 dark:border-slate-700 dark:border-slate-700">
            <summary class="cursor-pointer text-sm font-semibold text-gray-800 dark:text-slate-200 dark:text-slate-100">Supporting details <span class="font-normal text-gray-500">(optional)</span></summary>
        <div class="grid gap-4 rounded-xl bg-gray-50 p-4 dark:bg-slate-950 sm:grid-cols-2">
            <div>
                <label for="prepared_by_date_signed" class="text-sm font-medium text-gray-700 dark:text-slate-200">Date signed by project leader <span class="font-normal text-gray-400">(optional)</span></label>
                <x-date-picker id="prepared_by_date_signed" name="prepared_by_date_signed" :value="old('prepared_by_date_signed', $defaultPreparedByDate)" :max="now()->toDateString()" class="mt-1" />
            </div>
            <label class="text-sm font-medium text-gray-700 dark:text-slate-200">Supporting attachment <span class="font-normal text-gray-400">(optional)</span>
                <input type="file" name="attachment" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png" class="mt-1 block w-full rounded-xl border border-gray-200 bg-white p-2 text-xs">
            </label>
        </div>

            <label class="block text-sm font-medium text-gray-700 dark:text-slate-200">Tracking number<input name="tracking_number" maxlength="100" value="{{ old('tracking_number', $defaultTrackingNumber) }}" class="mt-2 block w-full rounded-lg border-gray-300 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white" placeholder="If assigned by the research office"></label>
        </details>

        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 pt-5">
            <p class="text-xs text-gray-500">Your draft stays private until you prepare the PDF and submit it.</p>
            <div class="flex flex-wrap gap-2">
                <button type="button" @click="generatePreview" :disabled="previewLoading || submitting" class="rounded-xl border border-gray-300 bg-white px-5 py-3 text-xs font-bold text-gray-900 shadow-sm hover:bg-gray-50 disabled:cursor-wait disabled:opacity-60 dark:border-slate-600 dark:bg-slate-900 dark:text-white dark:hover:bg-slate-800">
                    <span x-show="!previewLoading">Preview monitoring tool</span>
                    <span x-show="previewLoading" x-cloak>Generating preview...</span>
                </button>
                <button type="submit" :disabled="submitting || previewLoading" class="rounded-xl bg-red-700 px-5 py-3 text-xs font-bold text-white shadow-sm hover:bg-red-800 disabled:cursor-wait disabled:opacity-60">
                    <span x-show="!submitting">Prepare official PDF</span>
                    <span x-show="submitting" x-cloak>Preparing PDF…</span>
                </button>
            </div>
        </div>

        <p x-show="previewError" x-cloak x-text="previewError" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-xs font-semibold text-red-700"></p>

        <section x-show="previewHtml" x-cloak x-ref="previewSection" class="space-y-3 rounded-2xl border border-gray-200 bg-gray-100 p-3 sm:p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-base font-semibold text-gray-900 dark:text-white">Monitoring form preview</p>
                    <p class="text-xs text-gray-500">This preview is generated from the current form values and has not been submitted.</p>
                </div>
                <button type="button" @click="printPreview" :disabled="!previewReady" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-bold text-gray-700 shadow-sm disabled:opacity-50">Print preview</button>
            </div>
            <iframe x-ref="previewFrame" :srcdoc="previewHtml" @load="hydratePreview" title="Monitoring tool document preview" class="h-[75vh] w-full rounded-xl border border-gray-300 bg-white shadow-inner"></iframe>
        </section>
    </form>
</section>
@endif
