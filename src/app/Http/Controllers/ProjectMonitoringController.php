<?php

namespace App\Http\Controllers;

use App\Actions\PrepareProjectProgressReport;
use App\Actions\SaveProjectMonitoringDraft;
use App\Contracts\DocumentPdfConverter;
use App\Http\Requests\SaveProjectProgressReportDraftRequest;
use App\Http\Requests\StoreProjectProgressReportRequest;
use App\Http\Requests\SubmitPreparedProjectProgressReportRequest;
use App\Models\ProjectMonitoringDraft;
use App\Models\ProjectNarrativeReport;
use App\Models\ProjectProgressReport;
use App\Models\TopicProposal;
use App\Models\User;
use App\Notifications\ProposalActivityNotification;
use App\Services\ApprovedWorkPlanMonitoringService;
use App\Services\MonitoringEvidenceService;
use App\Services\MonitoringQuarterService;
use App\Services\MonitoringToolDocumentService;
use App\Services\ProjectDocumentLibrary;
use App\Services\ProjectMonitoringFormDataService;
use App\Services\SidebarAttentionService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectMonitoringController extends Controller
{
    public function create(
        Request $request,
        TopicProposal $topic,
        ProjectMonitoringFormDataService $formData,
        ApprovedWorkPlanMonitoringService $approvedWorkPlan,
    ): View|RedirectResponse {
        $this->ensureResearcherCanPrepareReport($request, $topic);

        $request->validate(['reporting_date' => ['nullable', 'date_format:Y-m-d']]);
        $quarterService = app(MonitoringQuarterService::class);
        $quarters = $quarterService->summaryRows($topic->progressReports()->get([
            'id', 'topic_id', 'reporting_date', 'version_number', 'submission_status', 'review_status',
        ]), $topic);
        $requestedDate = $request->input('reporting_date');
        $data = $formData->monitoringTool($request->user(), $topic, $request->integer('revise_monitoring_report'), $requestedDate);
        $preparedDate = $data['preparedReport']?->reporting_date?->toDateString();
        if ($preparedDate && $requestedDate && $preparedDate !== $requestedDate && $data['revisionReport'] === null) {
            return redirect()->route('project-progress.create', ['topic' => $topic, 'reporting_date' => $preparedDate])
                ->with('success', 'Your prepared Monitoring Tool was reopened. Submit or discard it before starting another quarter.');
        }
        $draftDate = data_get($data['monitoringDraft']?->source_data, 'reporting_date');
        if ($draftDate && $requestedDate && $data['revisionReport'] === null && $quarterService->forDate($draftDate, $topic)['start']->ne($quarterService->forDate($requestedDate, $topic)['start'])) {
            return redirect()->route('project-progress.create', ['topic' => $topic, 'reporting_date' => $draftDate])
                ->with('success', 'Your unfinished quarterly draft was reopened. Submit this report before starting another quarter.');
        }
        $quarterOptions = $quarters->filter(fn (array $row): bool => $row['drafting_date'] !== null && ($row['report'] === null || $row['report']->isPrepared()))
            ->map(fn (array $row): array => [...$row, 'reporting_date' => $row['drafting_date']])->values();
        if ($draftDate && $data['revisionReport'] === null) {
            $draftPeriod = $quarterService->forDate($draftDate, $topic);
            $quarterOptions = $quarterOptions->filter(fn (array $row): bool => $row['start']->eq($draftPeriod['start']))->values();
        }
        if ($quarterOptions->isEmpty() && $data['revisionReport'] === null) {
            return redirect()->to(route('topics.show', $topic).'#project-monitoring')
                ->withErrors(['monitoring' => 'No reporting period is open for a new report. Check the schedule below.']);
        }
        $selectedReportingDate = $data['revisionReport']?->reporting_date?->toDateString()
            ?? $draftDate
            ?? $preparedDate
            ?? $request->old('reporting_date')
            ?? $requestedDate
            ?? $quarterOptions->first()['reporting_date']
            ?? null;
        if ($requestedDate && $data['revisionReport'] === null) {
            $requestedQuarter = $quarterService->forDate($requestedDate, $topic);
            $row = $quarters->first(fn (array $row): bool => $row['start']->eq($requestedQuarter['start']));
            if (! $row || $row['drafting_date'] === null || ($row['report'] && ! $row['report']->isPrepared())) {
                return redirect()->to(route('topics.show', $topic).'#project-monitoring')
                    ->withErrors(['monitoring' => 'This quarter is not open for a new report. Open its existing report to view or revise it.']);
            }
        }

        $approvedWorkPlanByPeriod = $quarters
            ->mapWithKeys(fn (array $row): array => [
                $row['year'].'-'.$row['quarter'] => $approvedWorkPlan->defaultsForDate($topic, $row['end']),
            ])
            ->all();
        $evidence = app(MonitoringEvidenceService::class);
        foreach ($quarters as $row) {
            $key = $row['year'].'-'.$row['quarter'];
            $approvedWorkPlanByPeriod[$key] = $evidence->initialRows($topic, $row['end']->toDateString(), $approvedWorkPlanByPeriod[$key]);
        }
        $selectedPeriod = $selectedReportingDate
            ? $quarterService->forDate($selectedReportingDate, $topic)
            : null;
        $savedWorkPlanRows = data_get($data['monitoringDraft']?->source_data, 'work_plan')
            ?? $data['revisionReport']?->work_plan
            ?? [];
        $initialWorkPlanRows = $selectedReportingDate
            ? $approvedWorkPlan->synchronizeForDate(
                $topic,
                $selectedReportingDate,
                is_array($savedWorkPlanRows) ? $savedWorkPlanRows : [],
            )
            : [];

        $initialWorkPlanRows = $selectedReportingDate ? $evidence->initialRows($topic, $selectedReportingDate, $initialWorkPlanRows) : [];

        return view('faculty.monitoring-tools.create', [
            'topic' => $topic, ...$data, 'quarterOptions' => $quarterOptions,
            'selectedReportingDate' => $selectedReportingDate,
            'approvedWorkPlanByPeriod' => $approvedWorkPlanByPeriod,
            'approvedWorkPlanAvailable' => $approvedWorkPlan->hasApprovedWorkPlan($topic),
            'selectedPeriodKey' => $selectedPeriod ? $selectedPeriod['year'].'-'.$selectedPeriod['quarter'] : null,
            'selectedReportNumber' => $selectedPeriod['quarter'] ?? null,
            'monitoringReportCount' => $quarters->count(),
            'initialWorkPlanRows' => $initialWorkPlanRows,
            'previousProgressByPeriod' => $quarters->mapWithKeys(fn (array $row): array => [$row['year'].'-'.$row['quarter'] => array_map(fn (array $activity): array => ['accomplished_percentage' => $activity['accomplished_percentage'] ?? 0], $evidence->previousRows($topic, $row['end']->toDateString()))])->all(),
            'overdueWorkPlanActivities' => $approvedWorkPlan->overdueActivities($topic),
        ]);
    }

    public function index(Request $request, ProjectDocumentLibrary $documentLibrary): View
    {
        $completedOnly = $request->routeIs('research_head.completed-projects.index');
        $status = $completedOnly ? 'completed' : $request->string('status')->toString();
        $attention = $request->string('attention')->toString();
        $search = trim($request->string('search')->toString());
        $allowedStatuses = ['ongoing', 'delayed', 'completion_pending', 'completed'];
        $allowedAttention = ['needs_attention', 'pending_reports'];
        $hasMonitoringFilters = $search !== '' || in_array($attention, $allowedAttention, true)
            || (! $completedOnly && in_array($status, $allowedStatuses, true));
        $initialRecordsView = $request->query('view') === 'monitoring' || $hasMonitoringFilters || ! $completedOnly
            ? 'monitoring' : 'files';

        $summary = [
            'ongoing' => TopicProposal::withIssuedNotice()->where('project_status', TopicProposal::PROJECT_STATUS_ONGOING)->withoutCompletionPending()->count(),
            'delayed' => TopicProposal::withIssuedNotice()->where('project_status', TopicProposal::PROJECT_STATUS_DELAYED)->withoutCompletionPending()->count(),
            'completion_pending' => TopicProposal::withIssuedNotice()->completionPending()->count(),
            'completed' => TopicProposal::completedProject()->count(),
            'pending_reports' => ProjectProgressReport::submitted()->where('review_status', 'pending')
                ->whereHas('topic', fn (Builder $query): Builder => $query->withIssuedNotice())
                ->count()
                + ProjectNarrativeReport::submitted()->where('review_status', ProjectNarrativeReport::STATUS_PENDING)
                    ->whereHas('topic', fn (Builder $query): Builder => $query->withIssuedNotice())
                    ->count(),
        ];

        // shortcut: folder navigation loads all route-scoped summaries; move folder filtering to the server if portfolio payloads become slow.
        $projectQuery = TopicProposal::withIssuedNotice()
            ->select(['id', 'user_id', 'research_secretary_id', 'research_call_id', 'title', 'status', 'project_status', 'notice_to_proceed_issued_at', 'created_at', 'updated_at'])
            ->with([
                'user:id,name',
                'researchSecretary:id,name,avatar',
                'researchCall:id,title',
                'latestProgressReport:project_progress_reports.id,project_progress_reports.topic_id,project_progress_reports.progress_percentage,project_progress_reports.reporting_date',
                'latestNarrativeReport:project_narrative_reports.id,project_narrative_reports.topic_id,project_narrative_reports.submission_date',
            ])
            ->withCount([
                'progressReports',
                'progressReports as pending_reports_count' => fn (Builder $query): Builder => $query->where('review_status', 'pending'),
                'narrativeReports',
                'narrativeReports as pending_narrative_reports_count' => fn (Builder $query): Builder => $query->where('review_status', ProjectNarrativeReport::STATUS_PENDING),
            ])
            ->when($completedOnly, fn (Builder $query): Builder => $query->where('project_status', TopicProposal::PROJECT_STATUS_COMPLETED));

        $allProjects = (clone $projectQuery)
            ->when(in_array($status, $allowedStatuses, true), function (Builder $query) use ($status): void {
                if ($status === TopicProposal::PROJECT_STATUS_COMPLETION_PENDING) {
                    $query->completionPending();

                    return;
                }

                $query->where('project_status', $status);

                if (in_array($status, [TopicProposal::PROJECT_STATUS_ONGOING, TopicProposal::PROJECT_STATUS_DELAYED], true)) {
                    $query->withoutCompletionPending();
                }
            })
            ->when(in_array($attention, $allowedAttention, true), function (Builder $query) use ($attention): void {
                if ($attention === 'pending_reports') {
                    $query->where(function (Builder $query): void {
                        $query->whereHas('progressReports', fn (Builder $query): Builder => $query->where('review_status', 'pending'))
                            ->orWhereHas('narrativeReports', fn (Builder $query): Builder => $query->where('review_status', ProjectNarrativeReport::STATUS_PENDING));
                    });
                } else {
                    $query->where(function (Builder $query): void {
                        $query->where('project_status', 'delayed')
                            ->orWhere(fn (Builder $query): Builder => $query->completionPending())
                            ->orWhereHas('progressReports', fn (Builder $query): Builder => $query->where('review_status', 'pending'))
                            ->orWhereHas('narrativeReports', fn (Builder $query): Builder => $query->where('review_status', ProjectNarrativeReport::STATUS_PENDING));
                    });
                }
            })
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhereHas('user', fn (Builder $query): Builder => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderByRaw(
                "CASE WHEN project_status = 'delayed' OR EXISTS (SELECT 1 FROM project_progress_reports WHERE project_progress_reports.topic_id = topics.id AND review_status = 'pending' AND submission_status = ?) THEN 0 ELSE 1 END",
                [ProjectProgressReport::SUBMISSION_STATUS_SUBMITTED],
            )
            ->latest('updated_at')
            ->get();

        $fileProjects = $hasMonitoringFilters ? (clone $projectQuery)->latest('updated_at')->get() : $allProjects;
        $fileProjects = $fileProjects->filter(fn (TopicProposal $project): bool => Gate::forUser($request->user())->allows('view', $project))->values();

        $posterUrls = ProjectNarrativeReport::submitted()
            ->where('report_type', 'terminal')
            ->whereIn('topic_id', $fileProjects->modelKeys())
            ->latest('submission_date')->latest('id')
            ->get(['id', 'topic_id', 'report_type', 'submission_status', 'photos'])
            ->groupBy('topic_id')
            ->map(function (Collection $reports) use ($documentLibrary): ?string {
                foreach ($reports as $report) {
                    foreach (array_keys($report->photos ?? []) as $index) {
                        $url = $documentLibrary->terminalPosterUrl($report, $index);
                        if ($url !== null) {
                            return $url;
                        }
                    }
                }

                return null;
            });

        $page = LengthAwarePaginator::resolveCurrentPage();
        $projects = (new LengthAwarePaginator(
            $allProjects->forPage($page, 15)->values(), $allProjects->count(), 15, $page,
            ['path' => $request->url()],
        ))->withQueryString()->appends(['view' => 'monitoring']);
        $workspacePreferences = $request->user()->projectWorkspacePreferences();
        if ($workspacePreferences['assignments'] !== []) {
            $assignedProjects = TopicProposal::withIssuedNotice()
                ->whereKey(array_column($workspacePreferences['assignments'], 'project_id'))
                ->get(['id', 'user_id', 'status', 'project_status', 'notice_to_proceed_issued_at'])
                ->filter(fn (TopicProposal $project): bool => Gate::forUser($request->user())->allows('view', $project))
                ->keyBy('id');
            $workspacePreferences['assignments'] = array_values(array_filter(
                $workspacePreferences['assignments'],
                fn (array $assignment): bool => $assignedProjects->has($assignment['project_id']),
            ));
        }
        $workspaceProjects = $fileProjects->map(function (TopicProposal $project) use ($posterUrls): array {
            $progress = $project->latestProgressReport?->progress_percentage;
            $date = $project->notice_to_proceed_issued_at ?? $project->created_at;
            $secretary = $project->researchSecretary?->name;
            $completed = $project->isCompletedProject();

            return [
                'id' => $project->id,
                'title' => $project->title,
                'researcher' => $project->user->name,
                'call' => $project->researchCall?->title ?? 'Independent submission',
                'status' => $project->monitoringStatusForProgress($progress),
                'status_label' => $project->monitoringStatusLabelForProgress($progress),
                'year' => (int) $date->year,
                'date' => $date->toDateString(),
                'progress' => $progress,
                'latest_label' => $project->latestProgressReport
                    ? 'Tool · '.$project->latestProgressReport->reporting_date->format('M d, Y')
                    : ($project->latestNarrativeReport ? 'Progress report · '.$project->latestNarrativeReport->submission_date->format('M d, Y') : 'No report yet'),
                'secretary' => $secretary,
                'secretary_initials' => collect(explode(' ', $secretary ?? ''))->filter()
                    ->map(fn (string $part): string => mb_substr($part, 0, 1))->take(2)->implode(''),
                'reports' => $project->progress_reports_count + $project->narrative_reports_count,
                'pending' => $project->pending_reports_count + $project->pending_narrative_reports_count,
                'url' => route('topics.show', $project).($completed ? '' : '#project-monitoring'),
                'documents_url' => route('research_head.projects.workspace.documents', $project),
                'poster_url' => $posterUrls->get($project->id),
                'action_label' => $completed ? 'View record' : 'Monitor project',
            ];
        })->all();

        return view('research_head.projects.index', compact('projects', 'summary', 'status', 'attention', 'search', 'completedOnly', 'initialRecordsView', 'workspacePreferences', 'workspaceProjects'));
    }

    private function ensureResearcherCanPrepareReport(Request $request, TopicProposal $topic): void
    {
        Gate::forUser($request->user())->authorize('view', $topic);

        abort_unless($topic->isMonitoringAvailable() && $topic->isAccessibleTo($request->user()), 404);
    }

    public function prepare(
        StoreProjectProgressReportRequest $request,
        TopicProposal $topic,
        PrepareProjectProgressReport $prepareProjectProgressReport,
        MonitoringQuarterService $monitoringQuarterService,
    ): RedirectResponse {
        $validated = $request->validated();
        $sourceReport = $this->revisionSourceReport($request, $topic);
        $period = $monitoringQuarterService->forDate($validated['reporting_date'], $topic);
        $savedDraft = ProjectMonitoringDraft::query()->whereBelongsTo($topic, 'topic')->whereBelongsTo($request->user(), 'user')->forSource($sourceReport)->first();
        $savedDate = data_get($savedDraft?->source_data, 'reporting_date');
        if ($savedDate && $monitoringQuarterService->forDate($savedDate, $topic)['start']->ne($period['start'])) {
            return back()->withInput()->withErrors(['reporting_date' => 'Finish your saved Monitoring Tool draft before preparing another quarter.']);
        }

        if ($sourceReport !== null) {
            $sourcePeriod = $monitoringQuarterService->forDate($sourceReport->reporting_date, $topic);

            if ($sourcePeriod['year'] !== $period['year'] || $sourcePeriod['quarter'] !== $period['quarter']) {
                return back()->withInput()->withErrors([
                    'reporting_date' => 'Use a reporting date within '.$sourceReport->quarter_label.' when revising this Monitoring Tool.',
                ]);
            }
        }

        $existingPreparedReport = $this->reportsForPeriod($topic, $period)
            ->prepared()
            ->when(
                $sourceReport !== null,
                fn ($query) => $query->where('supersedes_report_id', $sourceReport->id),
                fn ($query) => $query->whereNull('supersedes_report_id'),
            )
            ->exists();

        if ($existingPreparedReport) {
            return back()->withErrors([
                'preparation' => 'Discard the prepared '.$period['label'].' Monitoring Tool before preparing another one.',
            ]);
        }

        if ($sourceReport === null && $this->reportsForPeriod($topic, $period)->submitted()->exists()) {
            return back()->withInput()->withErrors([
                'reporting_date' => $period['label'].' already has a submitted Monitoring Tool. Open that quarter if a revision is required.',
            ]);
        }

        if ($sourceReport !== null && $sourceReport->nextVersion()->exists()) {
            return back()->withErrors([
                'preparation' => 'A newer version of this '.$sourceReport->quarter_label.' Monitoring Tool is already in progress.',
            ]);
        }

        try {
            $report = app(MonitoringEvidenceService::class)->store(
                $topic, $validated, $request->file('activity_evidence', []),
                fn (array $data): ProjectProgressReport => $prepareProjectProgressReport->handle(
                    $topic, $request->user(), $data, $request->file('attachment'), $sourceReport,
                ),
            );
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->withErrors([
                    'preparation' => 'The Monitoring Tool PDF could not be prepared. Your form data was kept, so you can try again.',
                ]);
        }

        ProjectMonitoringDraft::query()
            ->whereBelongsTo($topic, 'topic')
            ->whereBelongsTo($request->user(), 'user')
            ->forSource($sourceReport)
            ->delete();

        app(MonitoringEvidenceService::class)->deleteUnreferenced($topic, collect($request->attributes->get('monitoring_evidence_rows', []))->flatMap(fn (array $row): array => array_column($row['evidence'] ?? [], 'path'))->all());

        return redirect()->route('project-progress.create', [
            'topic' => $topic,
            'reporting_date' => $report->reporting_date->toDateString(),
            'revise_monitoring_report' => $sourceReport?->id,
        ])->with(
            'success',
            $report->quarter_label.' '.$report->version_label.' PDF prepared. Review it, then submit the exact file to the Research Head.',
        );
    }

    public function saveDraft(
        SaveProjectProgressReportDraftRequest $request,
        TopicProposal $topic,
        SaveProjectMonitoringDraft $saveProjectMonitoringDraft,
    ): JsonResponse {
        $sourceReport = $this->revisionSourceReport($request, $topic);
        $validated = $request->validated();
        $draft = app(MonitoringEvidenceService::class)->store(
            $topic, $validated, $request->file('activity_evidence', []),
            fn (array $data): ProjectMonitoringDraft => $saveProjectMonitoringDraft->handle(
                $topic, $request->user(), $sourceReport, $request->integer('draft_version'),
                collect($data)->except(['draft_version', 'source_report_id'])->all(),
            ),
        );
        app(MonitoringEvidenceService::class)->deleteUnreferenced($topic, collect($request->attributes->get('monitoring_evidence_rows', []))->flatMap(fn (array $row): array => array_column($row['evidence'] ?? [], 'path'))->all());

        return response()->json([
            'message' => 'Monitoring Tool draft saved.',
            'draft_version' => $draft->lock_version,
            'work_plan' => $draft->source_data['work_plan'] ?? [],
        ]);
    }

    public function submitPrepared(
        SubmitPreparedProjectProgressReportRequest $request,
        TopicProposal $topic,
        ProjectProgressReport $report,
    ): RedirectResponse {
        if (! filled($report->official_pdf_path) || ! Storage::disk('local')->exists($report->official_pdf_path)) {
            return back()->withErrors([
                'preparation' => 'The prepared Monitoring Tool PDF is unavailable. Discard it and prepare the form again.',
            ]);
        }

        DB::transaction(function () use ($topic, $report, $request): void {
            $lockedTopic = TopicProposal::query()->whereKey($topic->id)->lockForUpdate()->firstOrFail();
            $lockedReport = ProjectProgressReport::query()->whereKey($report->id)->lockForUpdate()->firstOrFail();
            if (! $lockedTopic->isMonitoringAvailable() || ! $lockedReport->isPrepared() || $lockedTopic->user_id !== $request->user()->id) {
                throw ValidationException::withMessages(['preparation' => 'This report is no longer available for submission. Reload the quarter.']);
            }
            $lockedReport->update([
                'submission_status' => ProjectProgressReport::SUBMISSION_STATUS_SUBMITTED,
                'submitted_by' => $request->user()->id,
                'submitted_at' => now(),
            ]);
        });

        User::role('research_head')->get()->each->notify(new ProposalActivityNotification(
            title: $report->version_number > 1
                ? $report->quarter_label.' Monitoring Tool Resubmitted'
                : 'New '.$report->quarter_label.' Monitoring Tool Submitted',
            message: 'Project: '.$topic->title."\nResearcher: ".$request->user()->name."\nReporting Period: ".$report->reporting_period_label,
            url: route('topics.show', $topic).'#monitoring-tool-'.$report->id,
            level: 'info',
            topicId: $topic->id,
            workspace: 'research_head',
            sidebarArea: ProposalActivityNotification::SIDEBAR_AREA_PROJECT_MONITORING,
        ));

        return redirect()->to(route('topics.show', $topic).'#monitoring-tool-'.$report->id)->with('success', 'Monitoring Tool submitted for Research Head review.');
    }

    public function discardPrepared(
        SubmitPreparedProjectProgressReportRequest $request,
        TopicProposal $topic,
        ProjectProgressReport $report,
    ): RedirectResponse {
        DB::transaction(function () use ($topic, $report): void {
            TopicProposal::query()->whereKey($topic->id)->lockForUpdate()->firstOrFail();
            $lockedReport = ProjectProgressReport::query()->whereKey($report->id)->lockForUpdate()->firstOrFail();
            if (! $lockedReport->isPrepared()) {
                throw ValidationException::withMessages(['preparation' => 'A submitted report cannot be discarded.']);
            }
            $lockedReport->delete();
        });
        $this->deletePreparedReportFiles($report);

        return back()->with('success', 'Prepared Monitoring Tool discarded. You can now prepare a new PDF.');
    }

    public function store(
        StoreProjectProgressReportRequest $request,
        TopicProposal $topic,
        MonitoringQuarterService $monitoringQuarterService,
    ): RedirectResponse {
        $validated = $request->validated();
        $workPlan = collect($validated['work_plan']);
        $period = $monitoringQuarterService->forDate($validated['reporting_date'], $topic);

        $validated['topic_id'] = $topic->id;
        $validated['submitted_by'] = $request->user()->id;
        $validated['reporting_year'] = $period['year'];
        $validated['reporting_quarter'] = $period['quarter'];
        $validated['period_start'] = $period['start']->toDateString();
        $validated['period_end'] = $period['end']->toDateString();
        $validated['version_number'] = 1;
        $validated['progress_percentage'] = (int) round($workPlan->sum(
            fn (array $entry): float => (float) $entry['accomplished_percentage'],
        ));
        $validated['accomplishments'] = $workPlan
            ->pluck('actual_accomplishment')
            ->filter()
            ->implode("\n");
        $validated['issues'] = $workPlan
            ->pluck('findings')
            ->filter()
            ->implode("\n") ?: null;
        $validated['attachment_path'] = $request->file('attachment')?->store('progress-reports/'.$topic->id, 'local');
        unset($validated['attachment'], $validated['source_report_id']);

        $report = ProjectProgressReport::create($validated);

        User::role('research_head')->get()->each->notify(new ProposalActivityNotification(
            'Monitoring tool submitted',
            $request->user()->name.' submitted a monitoring tool for “'.$topic->title.'”.',
            route('topics.show', $topic).'#project-monitoring',
            'info',
            $topic->id,
            workspace: User::WORKSPACE_RESEARCH_HEAD,
            sidebarArea: ProposalActivityNotification::SIDEBAR_AREA_PROJECT_MONITORING,
        ));

        return back()->with('success', 'Official monitoring tool submitted for Research Head review.');
    }

    public function preview(StoreProjectProgressReportRequest $request, TopicProposal $topic): View
    {
        $validated = $request->validated();
        $workPlan = collect($validated['work_plan']);

        $report = new ProjectProgressReport([
            'topic_id' => $topic->id,
            'submitted_by' => $request->user()->id,
            'reporting_date' => $validated['reporting_date'],
            'tracking_number' => $validated['tracking_number'] ?? null,
            'progress_percentage' => app(MonitoringEvidenceService::class)->overallProgress($topic, $validated['reporting_date'], $validated['work_plan']),
            'work_plan' => $validated['work_plan'],
            'budget_utilization' => $validated['budget_utilization'],
            'prepared_by_date_signed' => $validated['prepared_by_date_signed'] ?? null,
        ]);
        $report->setRelation('topic', $topic->loadMissing('user'));
        $report->setRelation('submitter', $request->user());

        return view('faculty.monitoring-tools.preview', compact('report'));
    }

    public function review(
        Request $request,
        ProjectProgressReport $report,
        SidebarAttentionService $sidebarAttention,
    ): RedirectResponse {
        abort_unless($report->topic->isMonitoringAvailable(), 404);
        abort_unless($report->topic()->withIssuedNotice()->exists(), 404);
        abort_unless($report->isSubmitted(), 404);
        abort_if($report->nextVersion()->exists(), 404);

        $validated = $request->validate([
            'review_status' => ['required', Rule::in(['reviewed', 'revision_requested'])],
            'research_head_remarks' => ['nullable', 'required_if:review_status,revision_requested', 'string', 'max:5000'],
        ]);

        $report->update([
            ...$validated,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        $sidebarAttention->markTopicAsRead(
            $request->user(),
            ProposalActivityNotification::SIDEBAR_AREA_PROJECT_MONITORING,
            $report->topic_id,
        );

        $report->load(['topic.user', 'submitter']);
        $report->submitter->notify(new ProposalActivityNotification(
            title: $validated['review_status'] === 'reviewed'
                ? $report->quarter_label.' Monitoring Tool Reviewed'
                : $report->quarter_label.' Monitoring Tool Corrections Requested',
            message: 'Project: '.$report->topic->title."\nReporting Period: ".$report->reporting_period_label,
            url: route('topics.show', $report->topic).'#monitoring-tool-'.$report->id,
            level: $validated['review_status'] === 'reviewed' ? 'success' : 'warning',
            topicId: $report->topic_id,
            workspace: 'faculty_researcher',
            sidebarArea: ProposalActivityNotification::SIDEBAR_AREA_MY_PROJECTS,
        ));

        return back()->with('success', 'Monitoring tool review saved.');
    }

    public function updateProjectStatus(Request $request, TopicProposal $topic): RedirectResponse
    {
        abort_unless($topic->isMonitoringAvailable(), 404);

        $validated = $request->validate([
            'project_status' => ['required', Rule::in([
                TopicProposal::PROJECT_STATUS_ONGOING,
                TopicProposal::PROJECT_STATUS_DELAYED,
                TopicProposal::PROJECT_STATUS_COMPLETED,
            ])],
            'completion_confirmed' => ['accepted_if:project_status,completed'],
        ], [
            'completion_confirmed.accepted_if' => 'Confirm that completion is final before marking this project Completed.',
        ]);

        $topic = DB::transaction(function () use ($topic, $validated): TopicProposal {
            $topic = TopicProposal::query()->whereKey($topic->id)->lockForUpdate()->firstOrFail();
            abort_unless($topic->isMonitoringAvailable(), 404);

            if ($validated['project_status'] === TopicProposal::PROJECT_STATUS_COMPLETED
                && ($topic->latestProgressReport()->value('progress_percentage') ?? 0) < 100) {
                throw ValidationException::withMessages(['project_status' => 'A project can only be completed when its latest monitoring tool shows 100% progress.']);
            }

            $terminalReport = $topic->narrativeReports()
                ->where('report_type', 'terminal')
                ->reorder()
                ->latest('id')
                ->first();

            if ($validated['project_status'] === TopicProposal::PROJECT_STATUS_COMPLETED
                && (! app(MonitoringQuarterService::class)->canSubmitTerminal($topic)
                    || ! $terminalReport instanceof ProjectNarrativeReport
                    || $terminalReport->review_status !== ProjectNarrativeReport::STATUS_REVIEWED)) {
                throw ValidationException::withMessages(['project_status' => 'Complete the project after its end date and after the terminal report has been submitted and reviewed.']);
            }

            if ($validated['project_status'] === TopicProposal::PROJECT_STATUS_COMPLETED
                && (! $terminalReport->hasSignedCopy()
                    || ! Storage::disk('local')->exists($terminalReport->signedCopy()['path']))) {
                throw ValidationException::withMessages(['project_status' => 'Upload the fully signed Terminal Report PDF before marking the project completed.']);
            }

            if ($validated['project_status'] === TopicProposal::PROJECT_STATUS_COMPLETED) {
                $missingReports = app(MonitoringQuarterService::class)->missingTerminalReportPeriods($topic);
                if ($missingReports !== []) {
                    throw ValidationException::withMessages(['project_status' => 'Complete all quarterly Monitoring Tools and Progress Reports before completing the project. Missing or requiring corrections: '.implode(', ', $missingReports).'.']);
                }
            }

            $topic->update(['project_status' => $validated['project_status']]);

            return $topic;
        });

        $topic->user()->firstOrFail()->notify(new ProposalActivityNotification(
            'Project status updated',
            'Your project “'.$topic->title.'” is now marked '.str_replace('_', ' ', $validated['project_status']).'.',
            route('topics.show', $topic).'#project-monitoring',
            $validated['project_status'] === 'completed' ? 'success' : 'info',
            $topic->id,
            workspace: User::WORKSPACE_FACULTY_RESEARCHER,
            sidebarArea: ProposalActivityNotification::SIDEBAR_AREA_MY_PROJECTS,
        ));

        return back()->with('success', $topic->isCompletedProject()
            ? 'Project marked Completed. This status is final. Monitoring reports are now read-only.'
            : 'Project monitoring status updated.');
    }

    public function download(Request $request, ProjectProgressReport $report)
    {
        $this->authorizeViewer($request, $report);
        abort_unless($report->attachment_path && Storage::disk('local')->exists($report->attachment_path), 404);

        return Storage::disk('local')->download($report->attachment_path);
    }

    public function downloadEvidence(Request $request, TopicProposal $topic, string $evidence): StreamedResponse
    {
        $file = null;
        if ($request->user()->isUsingWorkspace('faculty_researcher') && $topic->isAccessibleTo($request->user())) {
            foreach (ProjectMonitoringDraft::query()->whereBelongsTo($topic, 'topic')->whereBelongsTo($request->user(), 'user')->get(['source_data']) as $draft) {
                $file = collect($draft->source_data['work_plan'] ?? [])->flatMap(fn (array $row): array => $row['evidence'] ?? [])->firstWhere('id', $evidence);
                if ($file) {
                    break;
                }
            }
        }
        if (! $file) {
            foreach (ProjectProgressReport::query()->whereBelongsTo($topic, 'topic')->orderByDesc('submission_status')->orderByDesc('id')->get(['id', 'topic_id', 'submission_status', 'work_plan']) as $report) {
                $candidate = collect($report->work_plan ?? [])->flatMap(fn (array $row): array => $row['evidence'] ?? [])->firstWhere('id', $evidence);
                if ($candidate) {
                    if ($report->isSubmitted()) {
                        Gate::forUser($request->user())->authorize('view', $topic);
                    } else {
                        abort_unless($request->user()->isUsingWorkspace(User::WORKSPACE_FACULTY_RESEARCHER)
                            && $topic->isAccessibleTo($request->user()), 403);
                    }
                    $file = $candidate;
                    break;
                }
            }
        }
        abort_unless($file && str_starts_with($file['path'], 'progress-reports/'.$topic->id.'/evidence/') && ! str_contains($file['path'], '..') && Storage::disk('local')->exists($file['path']), 404);

        $mimeType = Storage::disk('local')->mimeType($file['path']);
        if ($request->boolean('preview')) {
            abort_unless(in_array($mimeType, ['image/jpeg', 'image/png'], true), 404);

            return Storage::disk('local')->response($file['path'], $file['name'], ['Content-Type' => $mimeType, 'X-Content-Type-Options' => 'nosniff']);
        }

        return Storage::disk('local')->download($file['path'], $file['name'], ['X-Content-Type-Options' => 'nosniff']);
    }

    public function downloadMonitoringTool(
        Request $request,
        ProjectProgressReport $report,
        MonitoringToolDocumentService $documentService,
        DocumentPdfConverter $pdfConverter,
    ): StreamedResponse {
        $this->authorizeViewer($request, $report);
        $inline = $request->routeIs('project-progress.monitoring-tool.view');

        if (filled($report->official_pdf_path)
            && filled($report->official_pdf_filename)
            && Storage::disk('local')->exists($report->official_pdf_path)) {
            return Storage::disk('local')->response(
                $report->official_pdf_path,
                $report->official_pdf_filename,
                ['Content-Type' => 'application/pdf', 'X-Content-Type-Options' => 'nosniff'],
                $inline ? 'inline' : 'attachment',
            );
        }

        abort_unless($report->isSubmitted() && is_array($report->work_plan) && is_array($report->budget_utilization), 404);
        $report->loadMissing(['topic.user', 'submitter', 'reviewer']);
        $pdf = $pdfConverter->convertDocx($documentService->generate($report));

        if ($inline) {
            return response()->stream(
                static fn () => print $pdf,
                200,
                ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline', 'X-Content-Type-Options' => 'nosniff'],
            );
        }

        return response()->streamDownload(
            static fn () => print $pdf,
            Str::slug($report->topic->title).'-monitoring-tool.pdf',
            ['Content-Type' => 'application/pdf', 'X-Content-Type-Options' => 'nosniff'],
        );
    }

    private function authorizeViewer(Request $request, ProjectProgressReport $report): void
    {
        if ($report->isPrepared()) {
            abort_unless(
                ($request->user()->isUsingWorkspace('faculty_researcher')
                    && $report->topic->isAccessibleTo($request->user()))
                    || ($request->user()->isUsingWorkspace(User::WORKSPACE_RESEARCH_SECRETARY)
                        && $report->topic->research_secretary_id === $request->user()->id),
                403,
            );

            return;
        }

        abort_unless(
            $request->user()->isUsingWorkspace('research_head')
                || $report->topic->isAccessibleTo($request->user()),
            403,
        );
    }

    private function deletePreparedReportFiles(ProjectProgressReport $report): void
    {
        app(MonitoringEvidenceService::class)->deleteUnreferenced($report->topic, collect($report->work_plan ?? [])->flatMap(fn (array $row): array => array_column($row['evidence'] ?? [], 'path'))->all());
        collect([$report->attachment_path, $report->official_pdf_path])
            ->filter()
            ->each(fn (string $path) => Storage::disk('local')->delete($path));
    }

    /**
     * @param  array{year: int, quarter: int, start: CarbonImmutable, end: CarbonImmutable}  $period
     */
    private function reportsForPeriod(TopicProposal $topic, array $period): Builder
    {
        return ProjectProgressReport::query()
            ->whereBelongsTo($topic, 'topic')
            ->where(function ($query) use ($period): void {
                $query->where(function ($query) use ($period): void {
                    $query->whereNotNull('period_start')->where('reporting_year', $period['year'])
                        ->where('reporting_quarter', $period['quarter']);
                })->orWhere(function ($query) use ($period): void {
                    $query->whereNull('period_start')
                        ->whereBetween('reporting_date', [
                            $period['start']->toDateString(),
                            $period['end']->toDateString(),
                        ]);
                });
            });
    }

    private function revisionSourceReport(Request $request, TopicProposal $topic): ?ProjectProgressReport
    {
        $sourceReportId = $request->integer('source_report_id');

        if ($sourceReportId === 0) {
            return null;
        }

        $sourceReport = ProjectProgressReport::query()
            ->submitted()
            ->whereBelongsTo($topic, 'topic')
            ->findOrFail($sourceReportId);

        abort_unless($sourceReport->review_status === 'revision_requested', 404);

        return $sourceReport;
    }
}
