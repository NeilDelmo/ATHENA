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
    'previousProgressByPeriod' => [],
])
@php
    $schedule = app(\App\Services\MonitoringQuarterService::class);
    $submissionOpen = $selectedReportingDate && $schedule->canSubmitForDate($topic, $selectedReportingDate);
    $submissionOpensAt = $selectedReportingDate ? $schedule->forDate($selectedReportingDate, $topic)['opens_at']->format('M j, Y') : '';
@endphp

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
                @if ($topic->research_secretary_id)
                    @if ($preparedReport->hasPreparedBudget())
                        <p class="mt-3 inline-flex items-center rounded-full bg-emerald-100 px-3 py-1 text-[11px] font-black text-emerald-800">Budget confirmed by {{ $preparedReport->budgetPreparer?->name }}</p>
                    @else
                        <p class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-800">{{ $topic->researchSecretary?->name ?? 'The assigned project secretary' }} has priority for budget utilization, but any authorized project member may complete it.</p>
                    @endif
                @endif
            </div>
            <x-monitoring-action-dock :fixed="$standalone">
                @if ($standalone)
                    <x-back-link data-paper-cancel-exit href="{{ route('research.show', $topic) }}#project-monitoring">Exit monitoring</x-back-link>
                @endif
                <a href="{{ route('project-progress.monitoring-tool', $preparedReport) }}" class="inline-flex min-h-12 items-center justify-center rounded-xl border border-gray-300 bg-white px-5 py-3 text-sm font-bold text-gray-900 shadow-sm transition hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-700 focus-visible:ring-offset-2 dark:border-slate-600 dark:bg-slate-900 dark:text-white dark:hover:bg-slate-800">Download prepared PDF</a>
                @if ($topic->research_secretary_id && ! $preparedReport->hasPreparedBudget())
                    <a href="{{ route('project-budget.edit', [$topic, $preparedReport]) }}" class="inline-flex min-h-12 items-center justify-center rounded-xl border border-amber-300 bg-amber-50 px-5 py-3 text-sm font-bold text-amber-900 shadow-sm transition hover:bg-amber-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-700 focus-visible:ring-offset-2">Complete budget utilization</a>
                @endif
                @if (Auth::id() === $topic->user_id)
                <form method="POST" action="{{ route('project-progress.submit-prepared', [$topic, $preparedReport]) }}">
                    @csrf
                    <button @disabled($topic->research_secretary_id && ! $preparedReport->hasPreparedBudget()) class="inline-flex min-h-12 items-center justify-center rounded-xl bg-red-700 px-5 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-red-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-700 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-45">Submit to Research Head</button>
                </form>
                @else
                    <p class="text-sm font-semibold text-red-700 dark:text-red-300">Only the project leader can submit this report.</p>
                @endif
                @if (in_array(Auth::id(), [$topic->user_id, $preparedReport->submitted_by], true))
                <form method="POST" action="{{ route('project-progress.discard-prepared', [$topic, $preparedReport]) }}" onsubmit="return confirm('Discard this prepared PDF? You will need to prepare it again.')">
                    @csrf
                    @method('DELETE')
                    <button class="inline-flex min-h-12 items-center justify-center rounded-xl border border-red-200 bg-white px-5 py-3 text-sm font-bold text-red-700 shadow-sm transition hover:bg-red-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-700 focus-visible:ring-offset-2 dark:border-red-900 dark:bg-slate-900 dark:text-red-300 dark:hover:bg-red-950">Discard</button>
                </form>
                @endif
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
    class="rounded-xl bg-white dark:bg-slate-900"
    data-proposal-paper-workspace
    data-monitoring-paper-workspace
    data-monitoring-tool-autosave="true"
    x-data="monitoringToolForm({
        entries: @js($workPlanRows),
        previousProgressByPeriod: @js($previousProgressByPeriod),
        evidenceUrlTemplate: @js(route('project-progress.evidence', ['topic' => $topic, 'evidence' => '__EVIDENCE__'])),
        previewUrl: @js(route('project-progress.preview', $topic)),
        previewTitle: 'Monitoring Tool preview',
        draftSaveUrl: @js(route('project-progress.draft', $topic)),
        initialDraftVersion: @js((int) ($monitoringDraft?->lock_version ?? 0)),
        csrfToken: @js(csrf_token()),
        periodEntries: @js($approvedWorkPlanByPeriod),
        approvedWorkPlanAvailable: @js($approvedWorkPlanAvailable),
        initialPeriodKey: @js($selectedPeriodKey),
        reportCount: @js($monitoringReportCount),
        submissionOpen: @js((bool) $submissionOpen),
        submissionOpensAt: @js($submissionOpensAt),
        scheduleCheckedOn: @js(now()->toDateString()),
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

    <x-monitoring-writing-toolbar
        :topic="$topic"
        report-label="Monitoring Tool"
        panel-id="monitoring-preview-{{ $topic->id }}"
        form-id="monitoring-form-{{ $topic->id }}"
        save-method="saveMonitoringDraft"
        :standalone="$standalone"
        class="mb-4"
    />

    <div class="proposal-preview-workspace proposal-writing-columns" :class="{ 'proposal-writing-preview-hidden': !previewPaneOpen }" @resize.window.debounce.150ms="resizeProposalPaperPreview()">
    <div class="proposal-edit-pane min-w-0" :inert="previewFullscreen">
    <form
        id="monitoring-form-{{ $topic->id }}"
        x-ref="form"
        data-monitoring-tool-autosave-form
        method="POST"
        action="{{ route('project-progress.prepare', $topic) }}"
        enctype="multipart/form-data"
        class="space-y-8 bg-white p-4 sm:p-6 dark:bg-slate-900 {{ $standalone ? '' : 'border-t border-red-200 dark:border-red-950' }}"
        @submit="prepareMonitoringPdf($event)"
    >
        @csrf
        @if ($revisionReport)
            <input type="hidden" name="source_report_id" value="{{ $revisionReport->id }}">
        @endif
        <input type="hidden" name="draft_version" value="{{ $monitoringDraft?->lock_version ?? 0 }}">
        <input type="hidden" name="progress_mode" value="evidence">

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

        <p data-report-submission-lock x-show="!submissionOpen" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200">You can fill, save, and preview this draft now. Official PDF preparation and submission open <span class="font-semibold" x-text="submissionOpensAt">{{ $submissionOpensAt }}</span>, after the reporting period ends.</p>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-950">
            <div class="grid gap-px bg-slate-200 dark:bg-slate-700 sm:grid-cols-3">
                <div class="bg-white p-4 dark:bg-slate-900 sm:col-span-2">
                    <p class="text-xs font-semibold text-slate-500 dark:text-slate-400">Research project</p>
                    <p class="mt-1 break-words text-base font-semibold text-slate-900 dark:text-white">{{ $topic->title }}</p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $topic->user->name }} · ₱{{ number_format((float) $topic->estimated_budget, 2) }} · {{ $topic->estimated_duration_months }} months</p>
                </div>
                <div class="bg-white p-4 dark:bg-slate-900">
                    <p class="text-xs font-semibold text-slate-500 dark:text-slate-400">Reporting sequence</p>
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
                            @change="selectReportingPeriod($event.target.selectedOptions[0]?.dataset.planKey); submissionOpen = $event.target.selectedOptions[0]?.dataset.submissionOpen === 'true'; submissionOpensAt = $event.target.selectedOptions[0]?.dataset.opensAt || ''"
                            class="mt-2 block w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-900 dark:text-white"
                        >
                            @foreach ($quarterOptions as $quarter)
                                @php
                                    $periodKey = $quarter['year'].'-'.$quarter['quarter'];
                                    $selected = $periodKey === $selectedPeriodKey;
                                @endphp
                                <option data-plan-key="{{ $periodKey }}" data-submission-open="{{ $schedule->canSubmitForDate($topic, $quarter['reporting_date']) ? 'true' : 'false' }}" data-opens-at="{{ $quarter['opens_at']->format('M j, Y') }}" value="{{ $selected ? old('reporting_date', $defaultReportingDate) : $quarter['reporting_date'] }}" @selected($selected)>{{ $quarter['label'] }} · {{ $quarter['period'] }}</option>
                            @endforeach
                        </select>
                    @endif
                </div>
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs leading-5 text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-100" x-show="hasApprovedEntries()">
                    <p class="font-bold">From your approved work plan</p>
                    <p>Add evidence for completed work. Progress is calculated against each approved target and its activity weight.</p>
                </div>
            </div>
        </section>

        <section class="space-y-4">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold text-slate-950 dark:text-white">Approved Activities & Progress</h2>
                    <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-500 dark:text-slate-400" x-text="hasApprovedEntries() ? 'Review each planned activity and record what you accomplished this quarter.' : 'Describe each activity, its expected output, and what you accomplished this quarter.'"></p>
                </div>
                <button
                    type="button"
                    @click="addEntry"
                    x-show="!hasApprovedEntries()"
                    :disabled="entries.length >= 11"
                    class="min-h-11 rounded-xl bg-red-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-red-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-700 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-40"
                >Add Activity</button>
            </div>

            <div x-ref="activityList" class="space-y-5">
                <template x-for="(entry, index) in entries" :key="`${currentPeriodKey}-${entry.source_work_plan_index ?? index}`">
                    <article data-monitoring-activity class="grid scroll-mt-40 overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900 xl:grid-cols-2">
                        <div class="border-b border-slate-200 bg-slate-50 p-4 sm:p-5 dark:border-slate-700 dark:bg-slate-950/70 xl:border-b-0 xl:border-r">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-lg bg-slate-900 px-3 py-1.5 text-sm font-bold text-white dark:bg-white dark:text-slate-950">Activity <span x-text="index + 1"></span></span>
                                        <span x-show="isApprovedEntry(entry)" class="rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/50 dark:text-emerald-200">Approved plan</span>
                                        <span x-show="isApprovedEntry(entry)" class="text-xs font-semibold text-slate-500" x-text="monthLabel(entry.work_plan_months)"></span>
                                        <span data-monitoring-activity-overdue x-show="isEntryOverdue(entry)" x-cloak role="status" class="rounded-full border border-amber-300 bg-amber-50 px-2.5 py-1 text-xs font-bold text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200">Past target date</span>
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
                                                <textarea :name="`work_plan[${index}][physical_target]`" x-model="entry.physical_target" @input="updateActivityTarget(entry)" rows="2" maxlength="500" required autocomplete="off" placeholder="Example: Interview dataset with 20 responses…" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-950 dark:text-white"></textarea>
                                            </label>
                                        </div>
                                    </template>
                                </div>
                                <button type="button" @click="removeEntry(index)" x-show="!isApprovedEntry(entry) && entries.length > 1" class="rounded-lg px-2 py-1 text-xs font-bold text-red-600 hover:bg-red-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-700 dark:text-red-300 dark:hover:bg-red-950/40">Remove</button>
                            </div>
                        </div>

                        <div class="grid content-start gap-4 p-4 sm:p-5 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <h3 class="text-sm font-bold text-slate-950 dark:text-white">Progress this quarter</h3>
                                <p class="mt-1 text-sm leading-6 text-slate-500 dark:text-slate-400">Describe the completed work and attach its evidence.</p>
                            </div>
                            <label class="text-sm font-semibold text-slate-800 dark:text-slate-100 sm:col-span-2">Actual Accomplishment
                                <textarea :name="`work_plan[${index}][actual_accomplishment]`" x-model="entry.actual_accomplishment" rows="3" maxlength="500" required autocomplete="off" placeholder="State the measurable result completed during this reporting period…" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-950 dark:text-white"></textarea>
                            </label>
                            <div class="space-y-3 sm:col-span-2" data-monitoring-evidence>
                                <input type="hidden" :name="`work_plan[${index}][activity_id]`" :value="entry.activity_id || ''">
                                <label class="block text-sm font-semibold text-slate-800 dark:text-slate-100" :for="`activity-evidence-${index}`">Evidence of completed work</label>
                                <input :id="`activity-evidence-${index}`" :name="`activity_evidence[${evidenceKey(entry, index)}][]`" type="file" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png" @change="setEvidenceFiles(entry, $event)" class="block w-full rounded-lg border border-slate-300 text-sm text-slate-600 file:mr-3 file:cursor-pointer file:border-0 file:bg-slate-100 file:px-4 file:py-3 file:font-semibold file:text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-600 dark:border-slate-600 dark:text-slate-300 dark:file:bg-slate-800 dark:file:text-white">
                                <p class="text-sm leading-6 text-slate-500 dark:text-slate-400" x-text="Number(entry.target_units || 1) > 1 ? 'Enter the total completed quantity supported by these files. Extra files do not increase progress.' : 'Attach proof that this activity’s approved output is complete. The milestone is counted once, regardless of file count.'"></p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">Up to 5 files per activity · PDF, Word, Excel, JPG or PNG · 10 MB each</p>
                                <p x-show="entry.evidenceError" x-text="entry.evidenceError" role="alert" class="text-sm font-semibold text-red-700 dark:text-red-300"></p>
                                <template x-for="file in (entry.evidence || [])" :key="file.id">
                                    <div class="flex items-center gap-3 rounded-lg bg-slate-50 px-3 py-2 dark:bg-slate-950">
                                        <input type="hidden" :name="`work_plan[${index}][evidence_ids][]`" :value="file.id">
                                        <a :href="evidenceUrl(file.id)" x-text="file.name" class="min-w-0 flex-1 break-words text-sm font-medium text-slate-700 underline decoration-slate-300 underline-offset-4 hover:text-red-700 dark:text-slate-200"></a>
                                        <button type="button" @click="removeEvidence(entry, file.id)" :aria-label="`Remove ${file.name}`" title="Remove evidence" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-slate-300 bg-white text-slate-600 hover:border-red-300 hover:text-red-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="m6 6 12 12M6 18 18 6" /></svg></button>
                                    </div>
                                </template>
                                <p x-show="entry.pendingEvidenceCount" class="text-sm text-slate-500 dark:text-slate-400">New evidence saves with your draft.</p>
                                <label x-show="Number(entry.target_units || 1) > 1" class="block text-sm font-semibold text-slate-800 dark:text-slate-100">Total completed <span class="font-normal text-slate-500" x-text="`(of ${entry.target_units} ${entry.progress_unit})`"></span>
                                    <input :name="`work_plan[${index}][completed_units]`" type="number" inputmode="numeric" x-model="entry.completed_units" @input="updateEntryProgress(entry)" min="0" :max="entry.target_units || 1" step="1" required autocomplete="off" class="mt-1 block w-full rounded-xl border-slate-300 text-base shadow-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-950 dark:text-white">
                                </label>
                                <input type="hidden" :name="`work_plan[${index}][accomplished_percentage]`" :value="entry.accomplished_percentage">
                                <div class="flex items-baseline justify-between gap-3 border-t border-slate-200 pt-3 dark:border-slate-700" role="status" aria-live="polite">
                                    <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">Calculated completion</span>
                                    <span class="text-xl font-bold tabular-nums text-slate-950 dark:text-white"><span x-text="Number(entry.completion || 0).toFixed(2)"></span>%</span>
                                </div>
                                <p class="text-sm text-slate-500 dark:text-slate-400"><span x-text="Number(entry.accomplished_percentage || 0).toFixed(2)"></span>% toward overall project progress</p>
                            </div>
                            <label class="text-sm font-semibold text-slate-800 dark:text-slate-100 sm:col-span-2">Findings or Challenges <span class="font-normal text-slate-400">(optional)</span>
                                <textarea :name="`work_plan[${index}][findings]`" x-model="entry.findings" rows="2" maxlength="500" autocomplete="off" placeholder="Describe a blocker, variance, or finding that needs attention…" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-950 dark:text-white"></textarea>
                            </label>
                        </div>
                    </article>
                </template>
            </div>

            <div x-show="!hasApprovedEntries()" class="flex flex-wrap items-center justify-between gap-3">
                <p role="status" aria-live="polite" class="text-sm text-slate-500 dark:text-slate-400"><span x-text="entries.length"></span> of 11 activities <span x-show="entries.length >= 11">· Activity limit reached</span></p>
                <button type="button" @click="addEntry" :disabled="entries.length >= 11" class="min-h-11 rounded-xl border border-red-200 bg-red-50 px-4 py-2.5 text-sm font-bold text-red-700 hover:bg-red-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-700 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-40 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">Add Activity</button>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-4 dark:border-slate-700 dark:bg-slate-950">
                <p class="text-sm font-semibold text-slate-500 dark:text-slate-400">Overall project progress, including earlier quarters</p>
                <p class="text-lg font-black tabular-nums text-slate-950 dark:text-white"><span x-text="totalProjectProgress().toFixed(2)"></span>%</p>
            </div>
        </section>

        <section class="space-y-3">
            <div>
                <h2 class="text-lg font-bold text-gray-900 dark:text-white">Spending this quarter</h2>
                @if ($topic->research_secretary_id)
                    <p class="mt-1 text-xs text-gray-500">The selected project secretary gets priority for this financial section. After preparing the Monitoring Tool, any authorized project member can complete it if needed.</p>
                @else
                    <p class="mt-1 text-xs text-gray-500">Open only the request types you used. Leave amounts at zero when there was no spending.</p>
                @endif
            </div>

            <div class="space-y-3">
                @if ($topic->research_secretary_id)
                    <div class="flex items-center gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-900 dark:bg-amber-950/30">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-full bg-white text-sm font-black text-amber-800 ring-1 ring-amber-200">
                            @if ($topic->researchSecretary?->avatar)
                                <img src="{{ $topic->researchSecretary->avatar }}" alt="" class="h-full w-full object-cover">
                            @else
                                {{ collect(explode(' ', $topic->researchSecretary?->name ?? 'RS'))->filter()->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->implode('') }}
                            @endif
                        </span>
                        <div class="min-w-0">
                            <p class="text-xs font-black uppercase tracking-wider text-amber-800">Priority project secretary</p>
                            <p class="truncate text-sm font-bold text-gray-950 dark:text-white">{{ $topic->researchSecretary?->name }}</p>
                            <p class="truncate text-xs text-gray-500">{{ $topic->researchSecretary?->email }}</p>
                        </div>
                    </div>
                    @foreach ($budgetRows as $index => $budget)
                        @php
                            $budget = is_array($budget) ? $budget : [];
                            $budgetType = $budget['type'] ?? ($defaultBudget[$index]['type'] ?? 'Request');
                        @endphp
                        <input type="hidden" name="budget_utilization[{{ $index }}][type]" value="{{ $budgetType }}">
                        <input type="hidden" name="budget_utilization[{{ $index }}][details]" value="{{ $budget['details'] ?? '' }}">
                        <input type="hidden" name="budget_utilization[{{ $index }}][amount_requested]" value="{{ $budget['amount_requested'] ?? 0 }}">
                        <input type="hidden" name="budget_utilization[{{ $index }}][actual_amount]" value="{{ $budget['actual_amount'] ?? 0 }}">
                        <input type="hidden" name="budget_utilization[{{ $index }}][remarks]" value="{{ $budget['remarks'] ?? '' }}">
                    @endforeach
                @else
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
                @endif
            </div>
        </section>

        <details class="rounded-xl border border-gray-200 p-4 dark:border-slate-700">
            <summary class="cursor-pointer rounded-lg text-sm font-semibold text-gray-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-700 focus-visible:ring-offset-2 dark:text-slate-100">Supporting Details <span class="font-normal text-gray-500">(optional)</span></summary>
        <div class="mt-4 grid gap-4 rounded-xl bg-gray-50 p-4 dark:bg-slate-950 sm:grid-cols-2">
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
        <p x-show="!submissionOpen" class="text-sm font-semibold text-amber-900 dark:text-amber-200">Official PDF preparation opens <span x-text="submissionOpensAt">{{ $submissionOpensAt }}</span>. Save or preview your draft now.</p>

    </form>
    </div>
    <x-proposal-paper-preview panel-id="monitoring-preview-{{ $topic->id }}" preview-label="Monitoring Tool preview" frame-title="Monitoring tool document preview" />
    </div>
</section>
@endif
