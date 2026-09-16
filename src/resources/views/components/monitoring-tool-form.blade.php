@props([
    'topic',
    'preparedReport' => null,
    'revisionReport' => null,
    'monitoringDraft' => null,
    'standalone' => false,
    'quarterOptions' => [],
    'selectedReportingDate' => null,
    'approvedWorkPlanByPeriod' => [],
    'approvedWorkPlanAvailable' => false,
    'selectedPeriodKey' => null,
    'selectedReportNumber' => null,
    'monitoringReportCount' => null,
    'initialWorkPlanRows' => [],
])

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
            <x-monitoring-action-dock :fixed="$standalone">
                @if ($standalone)
                    <x-back-link data-paper-cancel-exit href="{{ route('research.show', $topic) }}#project-monitoring">Exit monitoring</x-back-link>
                @endif
                <a href="{{ route('project-progress.monitoring-tool', $preparedReport) }}" class="inline-flex min-h-12 items-center justify-center rounded-xl border border-gray-300 bg-white px-5 py-3 text-sm font-bold text-gray-900 shadow-sm transition hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-700 focus-visible:ring-offset-2 dark:border-slate-600 dark:bg-slate-900 dark:text-white dark:hover:bg-slate-800">Download prepared PDF</a>
                <form method="POST" action="{{ route('project-progress.submit-prepared', [$topic, $preparedReport]) }}">
                    @csrf
                    <button class="inline-flex min-h-12 items-center justify-center rounded-xl bg-red-700 px-5 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-red-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-700 focus-visible:ring-offset-2">Submit to Research Head</button>
                </form>
                <form method="POST" action="{{ route('project-progress.discard-prepared', [$topic, $preparedReport]) }}" onsubmit="return confirm('Discard this prepared PDF? You will need to prepare it again.')">
                    @csrf
                    @method('DELETE')
                    <button class="inline-flex min-h-12 items-center justify-center rounded-xl border border-red-200 bg-white px-5 py-3 text-sm font-bold text-red-700 shadow-sm transition hover:bg-red-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-700 focus-visible:ring-offset-2 dark:border-red-900 dark:bg-slate-900 dark:text-red-300 dark:hover:bg-red-950">Discard</button>
                </form>
            </x-monitoring-action-dock>
        </div>
    </section>
@else

@php
    $draftData = is_array($monitoringDraft?->source_data) ? $monitoringDraft->source_data : [];
    $defaultWorkPlan = $initialWorkPlanRows !== [] ? $initialWorkPlanRows : [[
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
        periodEntries: @js($approvedWorkPlanByPeriod),
        approvedWorkPlanAvailable: @js($approvedWorkPlanAvailable),
        initialPeriodKey: @js($selectedPeriodKey),
        reportCount: @js($monitoringReportCount),
    })"
>
    @if (! $standalone)
    <header class="flex items-center justify-between gap-3 px-5 py-4 text-sm font-black text-gray-950 dark:text-white">
        <span>
            {{ $revisionReport ? 'Correct '.$revisionReport->quarter_label.' Monitoring Tool' : 'Submit monitoring tool' }}
            <span class="mt-1 block text-xs font-normal text-red-700 dark:text-red-300">BatStateU-REC-RES-03 · Revision 03{{ $revisionReport ? ' · '.$revisionReport->version_label.' is retained as the original submitted report' : '' }}</span>
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
        class="space-y-6 border-t border-red-200 bg-white p-5 dark:border-red-950 dark:bg-slate-900 {{ $standalone ? 'pb-44 sm:pb-32' : '' }}"
        @submit="submitting = true"
    >
        @csrf
        @if ($revisionReport)
            <input type="hidden" name="source_report_id" value="{{ $revisionReport->id }}">
        @endif
        <input type="hidden" name="draft_version" value="{{ $monitoringDraft?->lock_version ?? 0 }}">

        @if ($errors->any())
            <div role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-xs text-red-700">
                <p class="font-black">Please correct the highlighted monitoring fields.</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <x-proposal-autosave-status />

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-950">
            <div class="grid gap-px bg-slate-200 dark:bg-slate-700 sm:grid-cols-3">
                <div class="bg-white p-4 dark:bg-slate-900 sm:col-span-2">
                    <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Research Project</p>
                    <p class="mt-1 break-words text-sm font-semibold text-slate-900 dark:text-white">{{ $topic->title }}</p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $topic->user->name }} · ₱{{ number_format((float) $topic->estimated_budget, 2) }} · {{ $topic->estimated_duration_months }} months</p>
                </div>
                <div class="bg-white p-4 dark:bg-slate-900">
                    <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Reporting Sequence</p>
                    <p class="mt-1 text-lg font-black tabular-nums text-slate-950 dark:text-white">
                        Report <span x-text="currentReportNumber()">{{ $selectedReportNumber }}</span>
                        <span class="text-sm font-semibold text-slate-400">of {{ $monitoringReportCount }}</span>
                    </p>
                </div>
            </div>
            <div class="grid gap-4 p-4 sm:grid-cols-2 sm:items-end">
                <div>
                    <label for="monitoring-reporting-date" class="text-sm font-semibold text-slate-800 dark:text-slate-100">Reporting Period</label>
                    @if ($revisionReport)
                        <p id="monitoring-reporting-date" class="mt-2 text-sm font-semibold text-slate-950 dark:text-white">{{ $revisionReport->quarter_label }} · {{ $revisionReport->reporting_period_label }}</p>
                        <input type="hidden" name="reporting_date" value="{{ $defaultReportingDate }}">
                    @else
                        <select
                            id="monitoring-reporting-date"
                            name="reporting_date"
                            required
                            autocomplete="off"
                            @change="selectReportingPeriod($event.target.selectedOptions[0]?.dataset.planKey)"
                            class="mt-2 block w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-900 dark:text-white"
                        >
                            @foreach ($quarterOptions as $quarter)
                                @php
                                    $periodKey = $quarter['year'].'-'.$quarter['quarter'];
                                    $selected = $periodKey === $selectedPeriodKey;
                                @endphp
                                <option data-plan-key="{{ $periodKey }}" value="{{ $selected ? old('reporting_date', $defaultReportingDate) : $quarter['reporting_date'] }}" @selected($selected)>{{ $quarter['label'] }} · {{ $quarter['period'] }}</option>
                            @endforeach
                        </select>
                    @endif
                </div>
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs leading-5 text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-100" x-show="hasApprovedEntries()">
                    <p class="font-black">Synced From Approved Work Plan</p>
                    <p>Objectives, activities, targets, weights, and dates are locked to the approved plan. Record progress in the fields below.</p>
                </div>
            </div>
        </section>

        <section class="space-y-4">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="text-base font-bold text-slate-950 dark:text-white">Approved Activities & Progress</h2>
                    <p class="mt-1 max-w-2xl text-xs leading-5 text-slate-500 dark:text-slate-400" x-text="hasApprovedEntries() ? 'Only progress details are editable. Planned information comes directly from the approved Work Plan.' : 'No structured Work Plan activity is available for this period. Add the activities that need to be reported.'"></p>
                </div>
                <button
                    type="button"
                    @click="addEntry"
                    x-show="!hasApprovedEntries()"
                    :disabled="entries.length >= 11"
                    class="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-xs font-bold text-red-700 hover:bg-red-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-700 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-40 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200"
                >Add Activity</button>
            </div>

            <div class="space-y-4">
                <template x-for="(entry, index) in entries" :key="`${currentPeriodKey}-${entry.source_work_plan_index ?? index}`">
                    <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
                        <div class="border-b border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-950/70">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-full bg-slate-900 px-2.5 py-1 text-[10px] font-black uppercase tracking-wide text-white dark:bg-white dark:text-slate-950">Activity <span x-text="index + 1"></span></span>
                                        <span x-show="isApprovedEntry(entry)" class="rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[10px] font-black uppercase tracking-wide text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/50 dark:text-emerald-200">Approved Plan</span>
                                        <span x-show="isApprovedEntry(entry)" class="text-xs font-semibold text-slate-500" x-text="monthLabel(entry.work_plan_months)"></span>
                                    </div>
                                    <template x-if="isApprovedEntry(entry)">
                                        <div class="mt-3 space-y-3">
                                            <div>
                                                <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Objective</p>
                                                <p class="mt-1 break-words text-sm font-bold leading-6 text-slate-950 dark:text-white" x-text="entry.objective"></p>
                                            </div>
                                            <div class="grid gap-3 sm:grid-cols-2">
                                                <div>
                                                    <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Activity</p>
                                                    <p class="mt-1 break-words text-sm leading-6 text-slate-700 dark:text-slate-200" x-text="entry.activity"></p>
                                                </div>
                                                <div>
                                                    <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Expected Output</p>
                                                    <p class="mt-1 break-words text-sm leading-6 text-slate-700 dark:text-slate-200" x-text="entry.physical_target"></p>
                                                </div>
                                            </div>
                                            <div class="flex flex-wrap gap-x-5 gap-y-1 text-xs font-semibold text-slate-500 dark:text-slate-400">
                                                <span><span class="text-slate-400">Project Weight:</span> <span class="tabular-nums" x-text="`${entry.percent_weight}%`"></span></span>
                                                <span><span class="text-slate-400">Target Date:</span> <span class="tabular-nums" x-text="formatPlanDate(entry.target_completion_date)"></span></span>
                                            </div>

                                            <input type="hidden" :name="`work_plan[${index}][source_work_plan_index]`" :value="entry.source_work_plan_index">
                                            <input type="hidden" :name="`work_plan[${index}][objective]`" :value="entry.objective">
                                            <input type="hidden" :name="`work_plan[${index}][activity]`" :value="entry.activity">
                                            <input type="hidden" :name="`work_plan[${index}][percent_weight]`" :value="entry.percent_weight">
                                            <input type="hidden" :name="`work_plan[${index}][physical_target]`" :value="entry.physical_target">
                                            <input type="hidden" :name="`work_plan[${index}][target_completion_date]`" :value="entry.target_completion_date">
                                            <template x-for="month in entry.work_plan_months" :key="month">
                                                <input type="hidden" :name="`work_plan[${index}][work_plan_months][]`" :value="month">
                                            </template>
                                        </div>
                                    </template>
                                    <template x-if="!isApprovedEntry(entry)">
                                        <div class="mt-3 grid gap-4 sm:grid-cols-2">
                                            <label class="text-sm font-medium text-slate-700 dark:text-slate-200 sm:col-span-2">Activity
                                                <textarea :name="`work_plan[${index}][activity]`" x-model="entry.activity" rows="2" maxlength="1500" required autocomplete="off" placeholder="Example: Conduct field interviews with 20 participants…" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-950 dark:text-white"></textarea>
                                            </label>
                                            <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Project Weight (%)
                                                <input type="number" inputmode="decimal" :name="`work_plan[${index}][percent_weight]`" x-model="entry.percent_weight" @input="updateEntryProgress(entry)" min="0" max="100" step="0.01" required autocomplete="off" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-950 dark:text-white">
                                            </label>
                                            <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Target Completion Date
                                                <input type="date" :name="`work_plan[${index}][target_completion_date]`" x-model="entry.target_completion_date" required autocomplete="off" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-950 dark:text-white">
                                            </label>
                                            <label class="text-sm font-medium text-slate-700 dark:text-slate-200 sm:col-span-2">Expected Output
                                                <textarea :name="`work_plan[${index}][physical_target]`" x-model="entry.physical_target" rows="2" maxlength="500" required autocomplete="off" placeholder="Example: Interview dataset with 20 complete responses…" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-950 dark:text-white"></textarea>
                                            </label>
                                        </div>
                                    </template>
                                </div>
                                <button type="button" @click="removeEntry(index)" x-show="!isApprovedEntry(entry) && entries.length > 1" class="rounded-lg px-2 py-1 text-xs font-bold text-red-600 hover:bg-red-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-700 dark:text-red-300 dark:hover:bg-red-950/40">Remove</button>
                            </div>
                        </div>

                        <div class="grid gap-4 p-4 sm:grid-cols-2">
                            <label class="text-sm font-semibold text-slate-800 dark:text-slate-100 sm:col-span-2">Actual Accomplishment
                                <textarea :name="`work_plan[${index}][actual_accomplishment]`" x-model="entry.actual_accomplishment" rows="3" maxlength="500" required autocomplete="off" placeholder="State the measurable result completed during this reporting period…" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-950 dark:text-white"></textarea>
                            </label>
                            <label class="text-sm font-semibold text-slate-800 dark:text-slate-100">Activity Completion (%)
                                <input type="number" inputmode="decimal" x-model="entry.completion" @input="updateEntryProgress(entry)" min="0" max="100" step="0.01" required autocomplete="off" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-950 dark:text-white">
                                <input type="hidden" :name="`work_plan[${index}][accomplished_percentage]`" :value="entry.accomplished_percentage">
                                <span class="mt-1 block text-xs font-normal leading-5 text-slate-500">Completion is converted to its weighted contribution to overall project progress.</span>
                            </label>
                            <label class="text-sm font-semibold text-slate-800 dark:text-slate-100 sm:col-span-2">Findings or Challenges <span class="font-normal text-slate-400">(optional)</span>
                                <textarea :name="`work_plan[${index}][findings]`" x-model="entry.findings" rows="2" maxlength="500" autocomplete="off" placeholder="Describe a blocker, variance, or finding that needs attention…" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-950 dark:text-white"></textarea>
                            </label>
                        </div>
                    </article>
                </template>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-700 dark:bg-slate-950">
                <p class="text-xs font-semibold text-slate-500 dark:text-slate-400">Weighted overall project progress in this report</p>
                <p class="text-lg font-black tabular-nums text-slate-950 dark:text-white"><span x-text="totalProjectProgress().toFixed(2)"></span>%</p>
            </div>
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
                        <summary class="cursor-pointer rounded-lg text-sm font-semibold text-gray-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-700 focus-visible:ring-offset-2 dark:text-slate-200">{{ $budgetType }}</summary>
                        <div class="mt-3 grid gap-4 sm:grid-cols-2">
                            <label class="text-sm font-medium text-gray-700 dark:text-slate-200 sm:col-span-2">Details of request
                                <textarea name="budget_utilization[{{ $index }}][details]" rows="2" maxlength="300" autocomplete="off" class="mt-1 block w-full rounded-lg border-gray-300 text-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-700 dark:bg-slate-950 dark:text-white">{{ $budget['details'] ?? '' }}</textarea>
                            </label>
                            <label class="text-sm font-medium text-gray-700 dark:text-slate-200">Amount requested (PHP)
                                <input type="number" inputmode="decimal" name="budget_utilization[{{ $index }}][amount_requested]" value="{{ $budget['amount_requested'] ?? 0 }}" min="0" step="0.01" required autocomplete="off" class="mt-1 block w-full rounded-lg border-gray-300 text-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                            </label>
                            <label class="text-sm font-medium text-gray-700 dark:text-slate-200">Amount spent (PHP)
                                <input type="number" inputmode="decimal" name="budget_utilization[{{ $index }}][actual_amount]" value="{{ $budget['actual_amount'] ?? 0 }}" min="0" step="0.01" required autocomplete="off" class="mt-1 block w-full rounded-lg border-gray-300 text-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                            </label>
                            <label class="text-sm font-medium text-gray-700 dark:text-slate-200 sm:col-span-2">Remarks or challenges <span class="font-normal text-gray-400">(optional)</span>
                                <textarea name="budget_utilization[{{ $index }}][remarks]" rows="2" maxlength="300" autocomplete="off" class="mt-1 block w-full rounded-lg border-gray-300 text-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-700 dark:bg-slate-950 dark:text-white">{{ $budget['remarks'] ?? '' }}</textarea>
                            </label>
                        </div>
                    </details>
                @endforeach
            </div>
        </section>

        <details class="rounded-xl border border-gray-200 p-4 dark:border-slate-700">
            <summary class="cursor-pointer rounded-lg text-sm font-semibold text-gray-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-700 focus-visible:ring-offset-2 dark:text-slate-100">Supporting Details <span class="font-normal text-gray-500">(optional)</span></summary>
        <div class="grid gap-4 rounded-xl bg-gray-50 p-4 dark:bg-slate-950 sm:grid-cols-2">
            <div>
                <label for="prepared_by_date_signed" class="text-sm font-medium text-gray-700 dark:text-slate-200">Date signed by project leader <span class="font-normal text-gray-400">(optional)</span></label>
                <x-date-picker id="prepared_by_date_signed" name="prepared_by_date_signed" :value="old('prepared_by_date_signed', $defaultPreparedByDate)" :max="now()->toDateString()" class="mt-1" />
            </div>
            <label class="text-sm font-medium text-gray-700 dark:text-slate-200">Supporting attachment <span class="font-normal text-gray-400">(optional)</span>
                <input type="file" name="attachment" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png" class="mt-1 block w-full rounded-xl border border-gray-200 bg-white p-2 text-xs">
            </label>
        </div>

            <label class="block text-sm font-medium text-gray-700 dark:text-slate-200">Tracking Number<input name="tracking_number" maxlength="100" autocomplete="off" value="{{ old('tracking_number', $defaultTrackingNumber) }}" class="mt-2 block w-full rounded-lg border-gray-300 text-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-700 dark:bg-slate-950 dark:text-white" placeholder="Example: REC-2026-001…"></label>
        </details>

        <p class="border-t border-gray-100 pt-5 text-sm text-gray-500">Your draft stays private until you prepare the PDF and submit it.</p>

        <x-monitoring-action-dock :fixed="$standalone">
            @if ($standalone)
                <x-back-link data-paper-cancel-exit href="{{ route('research.show', $topic) }}#project-monitoring">Exit monitoring</x-back-link>
            @endif
            <button type="button" @click="generatePreview" :disabled="previewLoading || submitting" class="min-h-12 rounded-xl border border-gray-300 bg-white px-5 py-3 text-sm font-bold text-gray-900 shadow-sm transition hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-700 focus-visible:ring-offset-2 disabled:cursor-wait disabled:opacity-60 dark:border-slate-600 dark:bg-slate-900 dark:text-white dark:hover:bg-slate-800">
                <span x-show="!previewLoading">Preview monitoring tool</span>
                <span x-show="previewLoading" x-cloak>Generating preview…</span>
            </button>
            <button type="submit" :disabled="submitting || previewLoading" class="min-h-12 rounded-xl bg-red-700 px-5 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-red-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-700 focus-visible:ring-offset-2 disabled:cursor-wait disabled:opacity-60">
                <span x-show="!submitting">Prepare official PDF</span>
                <span x-show="submitting" x-cloak>Preparing PDF…</span>
            </button>
        </x-monitoring-action-dock>

        <p x-show="previewError" x-cloak x-text="previewError" role="alert" aria-live="polite" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-xs font-semibold text-red-700"></p>

        <section x-show="previewHtml" x-cloak x-ref="previewSection" class="space-y-3 rounded-2xl border border-gray-200 bg-gray-100 p-3 sm:p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-base font-semibold text-gray-900 dark:text-white">Monitoring form preview</p>
                    <p class="text-xs text-gray-500">This preview is generated from the current form values and has not been submitted.</p>
                </div>
                <button type="button" @click="printPreview" :disabled="!previewReady" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-bold text-gray-700 shadow-sm hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-700 focus-visible:ring-offset-2 disabled:opacity-50">Print Preview</button>
            </div>
            <iframe x-ref="previewFrame" :srcdoc="previewHtml" @load="hydratePreview" title="Monitoring tool document preview" class="h-[75vh] w-full rounded-xl border border-gray-300 bg-white shadow-inner"></iframe>
        </section>
    </form>
</section>
@endif
