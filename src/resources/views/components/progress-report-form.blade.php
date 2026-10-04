@props(['topic', 'preparedReport' => null, 'narrativeReportDraft' => null, 'standalone' => false, 'progressDefaults' => [], 'terminalDefaults' => [], 'terminalEvidence' => [], 'quarterOptions' => [], 'selectedReportingDate' => null])
@php
    $reportType = $preparedReport?->report_type ?? old('report_type', request('report_type', data_get($narrativeReportDraft?->source_data, 'report_type', 'progress')));
    $reportLabel = $reportType === 'terminal' ? 'Terminal report' : 'Progress report';
    $schedule = app(\App\Services\MonitoringQuarterService::class);
    $submissionOpen = $reportType === 'terminal' ? $schedule->canSubmitTerminal($topic) : ($selectedReportingDate && $schedule->canSubmitForDate($topic, $selectedReportingDate));
    $submissionOpensAt = $reportType === 'terminal' ? $schedule->terminalOpensAt($topic)->format('M j, Y') : ($selectedReportingDate ? $schedule->forDate($selectedReportingDate, $topic)['opens_at']->format('M j, Y') : '');
@endphp

@if ($preparedReport)
    <section class="rounded-2xl border border-red-200 bg-red-50 p-5 dark:border-red-950 dark:bg-slate-950">
        @if ($errors->narrativeProgress->has('preparation'))
            <p class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-xs font-semibold text-red-700">{{ $errors->narrativeProgress->first('preparation') }}</p>
        @endif
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-sm font-black text-gray-950 dark:text-white">{{ $reportLabel }} PDF prepared</p>
                @if ($preparedReport->reporting_period_label)<p class="mt-2 text-base font-semibold text-red-700 dark:text-red-300">{{ $preparedReport->reporting_period_label }} · Version {{ $preparedReport->version_number }}</p>@endif
                <p class="mt-1 max-w-2xl text-sm leading-6 text-gray-700 dark:text-slate-300">Review this exact stored PDF before sending it to the Research Head. To change its contents or figures, discard it and prepare a new file.</p>
                <p class="mt-2 text-[11px] font-semibold text-red-700 dark:text-red-300">Prepared {{ $preparedReport->prepared_at?->format('M d, Y g:i A') }}</p>
            </div>
            <x-monitoring-action-dock :fixed="$standalone">
                @if ($standalone)
                    <x-back-link data-paper-cancel-exit href="{{ route('research.show', $topic) }}#project-monitoring">Exit monitoring</x-back-link>
                @endif
                <a href="{{ route('project-narrative-reports.download', $preparedReport) }}" class="inline-flex min-h-12 items-center justify-center rounded-xl border border-gray-300 bg-white px-5 py-3 text-sm font-bold text-gray-900 shadow-sm transition hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-600 focus-visible:ring-offset-2 dark:border-slate-600 dark:bg-slate-900 dark:text-white dark:hover:bg-slate-800">Download prepared PDF</a>
                @if (Auth::id() === $topic->user_id)
                <form method="POST" action="{{ route('project-narrative-reports.submit-prepared', [$topic, $preparedReport]) }}">
                    @csrf
                    <button class="inline-flex min-h-12 items-center justify-center rounded-xl bg-red-700 px-5 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-red-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-600 focus-visible:ring-offset-2">Submit to Research Head</button>
                </form>
                @else
                    <p class="text-sm font-semibold text-red-700 dark:text-red-300">Only the project leader can submit this report.</p>
                @endif
                @if (in_array(Auth::id(), [$topic->user_id, $preparedReport->submitted_by], true))
                <form method="POST" action="{{ route('project-narrative-reports.discard-prepared', [$topic, $preparedReport]) }}">
                    @csrf
                    @method('DELETE')
                    <button class="inline-flex min-h-12 items-center justify-center rounded-xl border border-red-200 bg-white px-5 py-3 text-sm font-bold text-red-700 shadow-sm transition hover:bg-red-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-600 focus-visible:ring-offset-2 dark:border-red-900 dark:bg-slate-900 dark:text-red-300 dark:hover:bg-red-950">Discard</button>
                </form>
                @endif
            </x-monitoring-action-dock>
        </div>
    </section>
@else
@if ($reportType === 'terminal')
    <x-terminal-report-form :topic="$topic" :draft="$narrativeReportDraft" :defaults="$terminalDefaults" :evidence="$terminalEvidence" :standalone="$standalone" />
@else
@php
    $proposalDraft = $topic->revisionDraft;
    $draftData = is_array($narrativeReportDraft?->source_data) ? $narrativeReportDraft->source_data : [];
    $researcherNames = collect([$proposalDraft?->project_leader ?: $topic->user->name])
        ->merge($proposalDraft?->members?->pluck('name') ?? collect())
        ->filter()
        ->unique()
        ->implode("\n");
    $approvedStart = $proposalDraft?->planned_start ?? $topic->notice_to_proceed_issued_at?->copy()->startOfDay();
    $approvedEnd = $proposalDraft?->planned_end ?? $approvedStart?->copy()->addMonths((int) $topic->estimated_duration_months);
    $draftData = array_replace($progressDefaults, $draftData);
    $approvedObjectives = (bool) ($progressDefaults['objectives_from_work_plan'] ?? false);
    $draftData = app(\App\Support\ProgressReportData::class)->normalize($topic, [...$draftData, 'accomplishments' => old('accomplishments', $draftData['accomplishments'] ?? [])]);
    $blankAccomplishment = ['objective' => '', 'target' => '', 'actual' => '', 'activities' => ''];
    $approvedActivities = collect($progressDefaults['accomplishments'] ?? [])->pluck('activities', 'objective');
    $accomplishmentRows = collect($draftData['accomplishments'] ?? [])
        ->map(function ($row) use ($blankAccomplishment, $approvedActivities) {
            $row = array_merge($blankAccomplishment, is_array($row) ? $row : []);
            $row['activities'] = $row['activities'] ?: $approvedActivities->get($row['objective'], '');

            return $row;
        })
        ->values()->all();
    if ($accomplishmentRows === []) {
        $accomplishmentRows = [$blankAccomplishment];
    }
    $figureRows = old('figures', $draftData['figures'] ?? []);
    if ($figureRows === []) {
        foreach (array_keys($draftData) as $key) {
            if (preg_match('/^photo_caption_(\d+)$/', $key, $matches) === 1) {
                $index = (int) $matches[1];
                $figureRows[] = ['caption' => $draftData[$key] ?? '', 'section' => $draftData['photo_section_'.$index] ?? 'results_discussion', 'after_paragraph' => $draftData['photo_after_paragraph_'.$index] ?? 0];
            }
        }
    }
    $figureRows = collect($figureRows)->map(fn ($row) => is_array($row) ? \Illuminate\Support\Arr::only($row, ['caption', 'section', 'after_paragraph']) : [])->values()->all();
    $defaultSubmissionDate = array_key_exists('submission_date', $draftData) ? $draftData['submission_date'] : now()->toDateString();
    $defaultTrackingNumber = array_key_exists('tracking_number', $draftData) ? $draftData['tracking_number'] : '';
    $defaultResearchers = array_key_exists('researchers', $draftData) ? $draftData['researchers'] : $researcherNames;
    $defaultImplementationStart = array_key_exists('implementation_start', $draftData) ? $draftData['implementation_start'] : $approvedStart?->toDateString();
    $defaultImplementationEnd = array_key_exists('implementation_end', $draftData) ? $draftData['implementation_end'] : $approvedEnd?->toDateString();
    $defaultFundingAgency = array_key_exists('funding_agency', $draftData) ? $draftData['funding_agency'] : 'Batangas State University';
    $defaultPreparedByDate = array_key_exists('prepared_by_date_signed', $draftData) ? $draftData['prepared_by_date_signed'] : '';
@endphp

<section
    class="overflow-hidden rounded-2xl border border-red-200 bg-red-50/50 dark:border-red-950 dark:bg-slate-950"
    data-narrative-progress-autosave="true"
    x-data="narrativeProgressReportForm({
        initialAccomplishments: @js($accomplishmentRows),
        initialFigures: @js($figureRows),
        previewUrl: @js(route('project-narrative-reports.preview', $topic)),
        draftSaveUrl: @js(route('project-narrative-reports.draft', $topic)),
        initialDraftVersion: @js((int) ($narrativeReportDraft?->lock_version ?? 0)),
        csrfToken: @js(csrf_token()),
        submissionOpen: @js((bool) $submissionOpen),
        submissionOpensAt: @js($submissionOpensAt),
    })"
>
    @if (! $standalone)
    <header class="flex items-center justify-between gap-3 px-5 py-4 text-lg font-bold text-gray-950 dark:text-white">
        <span>
            Submit {{ strtolower($reportLabel) }}
            <span class="mt-1 block text-xs font-normal text-red-700 dark:text-red-300">BatStateU-REC-RES-02 · Revision 02</span>
        </span>
    </header>
    @endif

    <form x-ref="form" data-narrative-progress-autosave-form method="POST" action="{{ route('project-narrative-reports.prepare', $topic) }}" enctype="multipart/form-data" class="space-y-6 border-t border-red-200 bg-white p-5 dark:border-red-950 dark:bg-slate-900 {{ $standalone ? 'pb-44 sm:pb-32' : '' }}" @submit="if (!submissionOpen) { $event.preventDefault() } else { submitting = true }">
        @csrf
        <input type="hidden" name="draft_version" value="{{ $narrativeReportDraft?->lock_version ?? 0 }}">
        <input type="hidden" name="report_type" value="{{ $reportType }}">
        <div class="rounded-xl border border-red-200 bg-white p-4 dark:border-red-900 dark:bg-slate-900">
            <label for="progress-reporting-date" class="block text-base font-semibold text-gray-950 dark:text-white">Reporting quarter</label>
            <p class="mt-1 text-sm leading-6 text-gray-600 dark:text-slate-300">Use the same three-month period as the Monitoring Tool. Submission opens after the period ends; the final period may be shorter.</p>
            <select id="progress-reporting-date" @change="submissionOpen = $event.target.selectedOptions[0]?.dataset.submissionOpen === 'true'; submissionOpensAt = $event.target.selectedOptions[0]?.dataset.opensAt || ''" name="reporting_date" required class="mt-3 block min-h-11 w-full rounded-lg border-gray-300 text-base focus:border-red-700 focus:ring-red-700 dark:border-slate-600 dark:bg-slate-950 dark:text-white">
                <option value="">Choose a quarter</option>
                @foreach ($quarterOptions as $period)
                    <option data-submission-open="{{ $schedule->canSubmitForDate($topic, $period['reporting_date']) ? 'true' : 'false' }}" data-opens-at="{{ $period['opens_at']->format('M j, Y') }}" value="{{ $period['reporting_date'] }}" @selected(old('reporting_date', $selectedReportingDate) === $period['reporting_date'])>{{ $period['label'] }} · {{ $period['period'] }}{{ $period['report']?->review_status === 'revision_requested' ? ' · Corrections requested' : '' }}</option>
                @endforeach
            </select>
            @error('reporting_date', 'narrativeProgress')<p class="mt-2 text-sm text-red-700 dark:text-red-300">{{ $message }}</p>@enderror
        </div>

        @if ($errors->narrativeProgress->any())
            <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-xs text-red-700">
                <p class="font-black">Please correct the highlighted progress-report fields.</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach ($errors->narrativeProgress->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <x-proposal-autosave-status />

        <p class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm leading-6 text-gray-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">Changes save privately as a draft. Review the reused proposal content and write this period’s accomplishments and results. Select figure files before preparing the PDF; uploads are not kept in text drafts.</p>
        <p data-report-submission-lock x-show="!submissionOpen" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200">Fill and save this draft now. PDF preparation and submission open <span x-text="submissionOpensAt">{{ $submissionOpensAt }}</span>.</p>

        <div class="grid gap-3 rounded-xl bg-gray-50 p-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="sm:col-span-2">
                <p class="text-sm font-medium text-gray-400">Research project title</p>
                <p class="mt-1 text-base font-bold text-gray-800">{{ $topic->title }}</p>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-400">Project leader</p>
                <p class="mt-1 text-base font-bold text-gray-800">{{ $topic->user->name }}</p>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-400">Approved budget</p>
                <p class="mt-1 text-base font-bold text-gray-800">₱{{ number_format((float) $topic->estimated_budget, 2) }}</p>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="progress_submission_date" class="text-sm font-semibold text-gray-600">Submission date</label>
                <x-date-picker id="progress_submission_date" name="submission_date" :value="old('submission_date', $defaultSubmissionDate)" :max="now()->toDateString()" required class="mt-1" />
            </div>
            <label class="text-sm font-semibold text-gray-600">Tracking number <span class="font-normal text-gray-400">(optional)</span>
                <input type="text" name="tracking_number" value="{{ old('tracking_number', $defaultTrackingNumber) }}" maxlength="100" class="mt-1 block w-full rounded-xl border-gray-200 text-base" placeholder="Enter the official tracking number">
            </label>
        </div>

        <section class="grid gap-4 md:grid-cols-2">
            <label class="text-sm font-semibold text-gray-600 md:col-span-2">II. Researchers
                <textarea name="researchers" rows="3" maxlength="1000" required class="mt-1 block w-full rounded-xl border-gray-200 text-base" placeholder="Enter one researcher per line">{{ old('researchers', $defaultResearchers) }}</textarea>
            </label>
            <div>
                <label for="implementation_start" class="text-sm font-semibold text-gray-600">III. Approved implementation start</label>
                <x-date-picker id="implementation_start" name="implementation_start" :value="old('implementation_start', $defaultImplementationStart)" required class="mt-1" />
            </div>
            <div>
                <label for="implementation_end" class="text-sm font-semibold text-gray-600">III. Approved implementation end</label>
                <x-date-picker id="implementation_end" name="implementation_end" :value="old('implementation_end', $defaultImplementationEnd)" required class="mt-1" />
            </div>
            <label class="text-sm font-semibold text-gray-600 md:col-span-2">V. Funding agency
                <input type="text" name="funding_agency" value="{{ old('funding_agency', $defaultFundingAgency) }}" maxlength="255" required class="mt-1 block w-full rounded-xl border-gray-200 text-base">
            </label>
        </section>

        <section class="space-y-4">
            <div>
                <p class="text-lg font-bold text-gray-900">VI. Summary of Accomplishment for the Monitoring Period</p>
                <p class="mt-1 text-sm text-gray-600">Compare the planned outputs with what was completed during this monitoring period.</p>
            </div>

            @if ($progressDefaults['objectives_from_work_plan'] ?? false)
                <p class="rounded-lg bg-red-50 px-4 py-3 text-sm leading-6 text-red-900 dark:bg-red-950/40 dark:text-red-100">Objectives and target outputs come from the approved work plan. Update the actual accomplishments for this period, including any work not yet started.</p>
            @endif
            <div class="space-y-4">
                <template x-for="(row, index) in accomplishmentRows" :key="row.id">
                    <div class="space-y-3 rounded-xl border border-red-200 p-4 dark:border-red-900">
                        <div class="flex items-center justify-between gap-3">
                            <h4 class="text-base font-semibold text-brand dark:text-red-200" x-text="'Objective ' + (index + 1)"></h4>
                            @unless ($approvedObjectives)
                                <button type="button" @click="removeAccomplishment(index)" :disabled="accomplishmentRows.length === 1" class="rounded-lg px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-50 disabled:opacity-40 dark:text-red-300">Remove row</button>
                            @endunless
                        </div>
                        <p x-show="row.activities" x-text="'Planned activities: ' + row.activities" class="whitespace-pre-line text-sm leading-6 text-gray-600 dark:text-slate-300"></p>
                        <div class="grid gap-4 lg:grid-cols-3">
                            <label class="text-sm font-semibold text-gray-700 dark:text-slate-200">Approved objective
                                <textarea :name="'accomplishments[' + index + '][objective]'" x-model="row.objective" @readonly($approvedObjectives) rows="4" maxlength="1000" required class="mt-2 block w-full rounded-xl border-gray-200 text-base leading-7 dark:border-slate-700 dark:bg-slate-950 dark:text-white"></textarea>
                            </label>
                            <label class="text-sm font-semibold text-gray-700 dark:text-slate-200">Target accomplishment
                                <textarea :name="'accomplishments[' + index + '][target]'" x-model="row.target" @readonly($approvedObjectives) rows="4" maxlength="2000" required class="mt-2 block w-full rounded-xl border-gray-200 text-base leading-7 dark:border-slate-700 dark:bg-slate-950 dark:text-white" placeholder="Expected outputs from the approved work plan"></textarea>
                            </label>
                            <label class="text-sm font-semibold text-gray-700 dark:text-slate-200">Actual accomplishment
                                <textarea :name="'accomplishments[' + index + '][actual]'" x-model="row.actual" rows="4" maxlength="2000" required class="mt-2 block w-full rounded-xl border-gray-200 text-base leading-7 dark:border-slate-700 dark:bg-slate-950 dark:text-white" placeholder="What was completed during this period? Include partial progress or work not yet started."></textarea>
                            </label>
                        </div>
                    </div>
                </template>
            </div>
            @unless ($approvedObjectives)
                <button type="button" @click="addAccomplishment" class="inline-flex min-h-11 items-center rounded-lg border border-red-200 px-4 py-2 text-sm font-semibold text-brand hover:bg-red-50 dark:border-red-900 dark:text-red-200">Add accomplishment row</button>
            @endunless
        </section>

        <section class="space-y-4">
            <div>
                <p class="text-lg font-bold text-gray-900">Narrative sections</p>
                <p class="mt-1 text-sm text-gray-600">Introduction, rationale, objectives, and methodology start from the approved detailed proposal. Review the methods used this period and write new results. This progress-report format has no separate RRL section.</p>
            </div>
            @foreach ([
                'introduction' => 'VII. Introduction',
                'rationale' => 'VIII. Rationale',
                'objectives' => 'VIII. Objectives',
                'methodology' => 'IX. Methodology',
                'results_discussion' => 'X. Results and Discussion',
            ] as $field => $label)
                <label class="block text-sm font-semibold text-gray-600">{{ $label }}
                    <textarea name="{{ $field }}" @readonly($field === 'objectives' && filled($progressDefaults['objectives'])) rows="4" maxlength="{{ config('detailed_proposal.maximum_narrative_length') }}" required class="mt-2 block w-full rounded-xl border-gray-200 text-base leading-7 dark:border-slate-700 dark:bg-slate-950 dark:text-white">{{ $field === 'objectives' && filled($progressDefaults['objectives']) ? $progressDefaults['objectives'] : old($field, $draftData[$field] ?? '') }}</textarea>
                </label>
            @endforeach
        </section>

        <section class="space-y-4 rounded-xl border border-red-200 bg-red-50/40 p-4 dark:border-red-900 dark:bg-red-950/20" data-progress-report-figures>
            <div class="space-y-2">
                <h3 class="text-xl font-bold text-brand dark:text-red-200">Figures</h3>
                <p class="text-base leading-7 text-gray-600 dark:text-slate-300">Add diagrams, charts, screenshots, or research photos that support your methods and results. Use a caption for each figure. JPG or PNG, up to 10 MB per file.</p>
                <p class="text-sm text-gray-600 dark:text-slate-300">Add as many figures as the report needs. Figures are optional; files must fit the server’s upload limits.</p>
            </div>
            <p x-show="figureRows.length === 0" class="text-base text-gray-600 dark:text-slate-300">No figures added.</p>
            <template x-for="(figure, index) in figureRows" :key="figure.id">
                <div class="space-y-4 rounded-xl border border-red-200 bg-white p-4 dark:border-red-900 dark:bg-slate-900">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h4 class="text-base font-bold text-brand dark:text-red-200" x-text="'Figure entry ' + (index + 1)"></h4>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" @click="moveFigure(index, -1)" :disabled="index === 0" aria-label="Move figure earlier" class="rounded-lg border border-gray-200 px-3 py-2 text-sm font-semibold disabled:opacity-40 dark:border-slate-700 dark:text-white">Move up</button>
                            <button type="button" @click="moveFigure(index, 1)" :disabled="index === figureRows.length - 1" aria-label="Move figure later" class="rounded-lg border border-gray-200 px-3 py-2 text-sm font-semibold disabled:opacity-40 dark:border-slate-700 dark:text-white">Move down</button>
                            <button type="button" @click="removeFigure(index)" class="rounded-lg px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-50 dark:text-red-300">Remove</button>
                        </div>
                    </div>
                    <img x-show="figure.previewUrl" :src="figure.previewUrl" :alt="figure.caption || 'Selected figure preview'" class="max-h-64 max-w-full rounded-lg object-contain">
                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="text-sm font-semibold text-gray-700 dark:text-slate-200">Figure image
                            <input type="file" :name="'figures[' + index + '][image]'" accept=".jpg,.jpeg,.png" @change="selectFigureFile(figure, $event)" class="mt-2 block w-full rounded-xl border border-gray-200 p-3 text-sm dark:border-slate-700">
                        </label>
                        <label class="text-sm font-semibold text-gray-700 dark:text-slate-200">Caption
                            <input type="text" :name="'figures[' + index + '][caption]'" x-model="figure.caption" :required="!!figure.previewUrl" maxlength="1000" class="mt-2 block w-full rounded-xl border-gray-200 text-base dark:border-slate-700 dark:bg-slate-950 dark:text-white" placeholder="Describe what this figure demonstrates">
                        </label>
                        <label class="text-sm font-semibold text-gray-700 dark:text-slate-200">Place in section
                            <select :name="'figures[' + index + '][section]'" x-model="figure.section" class="mt-2 block w-full rounded-xl border-gray-200 text-base dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                                <option value="methodology">Methodology</option>
                                <option value="results_discussion">Results and Discussion</option>
                            </select>
                        </label>
                        <label class="text-sm font-semibold text-gray-700 dark:text-slate-200">Insert after paragraph
                            <input type="number" :name="'figures[' + index + '][after_paragraph]'" x-model="figure.after_paragraph" min="0" max="100000" class="mt-2 block w-full rounded-xl border-gray-200 text-base dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                            <span class="mt-2 block text-sm font-normal text-gray-600 dark:text-slate-300">Use 0 for the end of the section. Separate paragraphs with a blank line. Figures are numbered in document order.</span>
                        </label>
                    </div>
                </div>
            </template>
            <button type="button" @click="addFigure" class="inline-flex min-h-11 items-center rounded-lg bg-brand px-5 py-2.5 text-base font-semibold text-white hover:bg-brand-soft focus:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2">Add figure</button>
        </section>

        <div class="rounded-xl bg-gray-50 p-4">
            <div>
                <label for="progress_prepared_by_date_signed" class="text-sm font-semibold text-gray-600">Prepared-by date signed <span class="font-normal text-gray-400">(optional)</span></label>
                <x-date-picker id="progress_prepared_by_date_signed" name="prepared_by_date_signed" :value="old('prepared_by_date_signed', $defaultPreparedByDate)" :max="now()->toDateString()" class="mt-1" />
            </div>
        </div>

        <x-monitoring-action-dock :fixed="$standalone">
            @if ($standalone)
                <x-back-link data-paper-cancel-exit href="{{ route('research.show', $topic) }}#project-monitoring">Exit monitoring</x-back-link>
            @endif
            <button type="button" @click="saveNarrativeDraft" :disabled="autoSaveInFlight || autoSaveBlocked" class="min-h-12 rounded-xl border border-gray-300 bg-white px-5 py-3 text-sm font-bold text-gray-900 disabled:opacity-50 dark:border-slate-600 dark:bg-slate-900 dark:text-white">Save draft</button>
            <button type="button" @click="generatePreview" :disabled="!submissionOpen || previewLoading || submitting" class="min-h-12 rounded-xl border border-gray-300 bg-white px-5 py-3 text-sm font-bold text-gray-900 shadow-sm transition hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-600 focus-visible:ring-offset-2 disabled:cursor-wait disabled:opacity-60 dark:border-slate-600 dark:bg-slate-900 dark:text-white dark:hover:bg-slate-800">
                <span x-show="!previewLoading">Preview {{ strtolower($reportLabel) }}</span>
                <span x-show="previewLoading" x-cloak>Generating preview...</span>
            </button>
            <button type="submit" :disabled="!submissionOpen || submitting || previewLoading" class="min-h-12 rounded-xl bg-red-700 px-5 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-red-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-600 focus-visible:ring-offset-2 disabled:cursor-wait disabled:opacity-60">
                <span x-show="!submitting">Prepare official PDF</span>
                <span x-show="submitting" x-cloak>Preparing PDF…</span>
            </button>
        </x-monitoring-action-dock>

        <p x-show="previewError" x-cloak x-text="previewError" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-xs font-semibold text-red-700"></p>

        <section x-show="previewHtml" x-cloak x-ref="previewSection" class="space-y-3 rounded-2xl border border-gray-200 bg-gray-100 p-3 sm:p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-lg font-bold text-gray-900">{{ $reportLabel }} preview</p>
                    <p class="text-sm text-gray-600">This preview is generated from the current form values and has not been submitted.</p>
                </div>
                <button type="button" @click="printPreview" :disabled="!previewReady" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-bold text-gray-700 shadow-sm disabled:opacity-50">Print preview</button>
            </div>
            <iframe x-ref="previewFrame" :srcdoc="previewHtml" @load="hydratePreview" title="Progress report document preview" class="h-[75vh] w-full rounded-xl border border-gray-300 bg-white shadow-inner"></iframe>
        </section>
    </form>
</section>
@endif
@endif
