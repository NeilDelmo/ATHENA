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
use App\Services\MonitoringQuarterService;
use App\Services\MonitoringToolDocumentService;
use App\Services\ProjectMonitoringFormDataService;
use App\Services\SidebarAttentionService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectMonitoringController extends Controller
{
    public function create(Request $request, TopicProposal $topic, ProjectMonitoringFormDataService $formData): View
    {
        $this->ensureResearcherCanPrepareReport($request, $topic);

        return view('faculty.monitoring-tools.create', [
            'topic' => $topic,
            ...$formData->monitoringTool($request->user(), $topic, $request->integer('revise_monitoring_report')),
        ]);
    }

    public function index(Request $request)
    {
        $status = $request->string('status')->toString();
        $attention = $request->string('attention')->toString();
        $search = trim($request->string('search')->toString());
        $allowedStatuses = ['ongoing', 'delayed', 'completed'];
        $allowedAttention = ['needs_attention', 'pending_reports'];

        $summary = [
            'ongoing' => TopicProposal::withIssuedNotice()->where('project_status', TopicProposal::PROJECT_STATUS_ONGOING)->count(),
            'delayed' => TopicProposal::withIssuedNotice()->where('project_status', TopicProposal::PROJECT_STATUS_DELAYED)->count(),
            'completed' => TopicProposal::completedProject()->count(),
            'pending_reports' => ProjectProgressReport::submitted()->where('review_status', 'pending')
                ->whereHas('topic', fn ($query) => $query->withIssuedNotice())
                ->count()
                + ProjectNarrativeReport::submitted()->where('review_status', ProjectNarrativeReport::STATUS_PENDING)
                    ->whereHas('topic', fn ($query) => $query->withIssuedNotice())
                    ->count(),
        ];

        $projects = TopicProposal::withIssuedNotice()
            ->with(['user', 'researchCall', 'category', 'latestProgressReport', 'latestNarrativeReport'])
            ->withCount([
                'progressReports',
                'progressReports as pending_reports_count' => fn ($query) => $query->where('review_status', 'pending'),
                'narrativeReports',
                'narrativeReports as pending_narrative_reports_count' => fn ($query) => $query->where('review_status', ProjectNarrativeReport::STATUS_PENDING),
            ])
            ->when(in_array($status, $allowedStatuses, true), function ($query) use ($status) {
                $status === 'ongoing'
                    ? $query->where('project_status', 'ongoing')
                    : $query->where('project_status', $status);
            })
            ->when(in_array($attention, $allowedAttention, true), function ($query) use ($attention) {
                if ($attention === 'pending_reports') {
                    $query->where(function ($query) {
                        $query->whereHas('progressReports', fn ($query) => $query->where('review_status', 'pending'))
                            ->orWhereHas('narrativeReports', fn ($query) => $query->where('review_status', ProjectNarrativeReport::STATUS_PENDING));
                    });
                } else {
                    $query->where(function ($query) {
                        $query->where('project_status', 'delayed')
                            ->orWhereHas('progressReports', fn ($query) => $query->where('review_status', 'pending'))
                            ->orWhereHas('narrativeReports', fn ($query) => $query->where('review_status', ProjectNarrativeReport::STATUS_PENDING));
                    });
                }
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($query) => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderByRaw(
                "CASE WHEN project_status = 'delayed' OR EXISTS (SELECT 1 FROM project_progress_reports WHERE project_progress_reports.topic_id = topics.id AND review_status = 'pending' AND submission_status = ?) THEN 0 ELSE 1 END",
                [ProjectProgressReport::SUBMISSION_STATUS_SUBMITTED],
            )
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString();

        return view('research_head.projects.index', compact('projects', 'summary', 'status', 'attention', 'search'));
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
        $period = $monitoringQuarterService->forDate($validated['reporting_date']);

        if ($sourceReport !== null) {
            $sourcePeriod = $monitoringQuarterService->forReport($sourceReport);

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
            $report = $prepareProjectProgressReport->handle(
                $topic,
                $request->user(),
                $validated,
                $request->file('attachment'),
                $sourceReport,
            );
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

        return back()->with(
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
        $draft = $saveProjectMonitoringDraft->handle(
            $topic,
            $request->user(),
            $sourceReport,
            $request->integer('draft_version'),
            collect($validated)->except(['draft_version', 'source_report_id'])->all(),
        );

        return response()->json([
            'message' => 'Monitoring Tool draft saved.',
            'draft_version' => $draft->lock_version,
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

        $report->update([
            'submission_status' => ProjectProgressReport::SUBMISSION_STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);

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

        return back()->with('success', 'Official monitoring tool submitted for Research Head review.');
    }

    public function discardPrepared(
        SubmitPreparedProjectProgressReportRequest $request,
        TopicProposal $topic,
        ProjectProgressReport $report,
    ): RedirectResponse {
        $this->deletePreparedReportFiles($report);
        $report->delete();

        return back()->with('success', 'Prepared Monitoring Tool discarded. You can now prepare a new PDF.');
    }

    public function store(
        StoreProjectProgressReportRequest $request,
        TopicProposal $topic,
        MonitoringQuarterService $monitoringQuarterService,
    ): RedirectResponse {
        $validated = $request->validated();
        $workPlan = collect($validated['work_plan']);
        $period = $monitoringQuarterService->forDate($validated['reporting_date']);

        $validated['topic_id'] = $topic->id;
        $validated['submitted_by'] = $request->user()->id;
        $validated['reporting_year'] = $period['year'];
        $validated['reporting_quarter'] = $period['quarter'];
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
            'progress_percentage' => (int) round($workPlan->sum(
                fn (array $entry): float => (float) $entry['accomplished_percentage'],
            )),
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
                : $report->quarter_label.' Monitoring Tool Needs Revision',
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
        ]);

        $topic->update($validated);

        $topic->user()->firstOrFail()->notify(new ProposalActivityNotification(
            'Project status updated',
            'Your project “'.$topic->title.'” is now marked '.str_replace('_', ' ', $validated['project_status']).'.',
            route('topics.show', $topic).'#project-monitoring',
            $validated['project_status'] === 'completed' ? 'success' : 'info',
            $topic->id,
            workspace: User::WORKSPACE_FACULTY_RESEARCHER,
            sidebarArea: ProposalActivityNotification::SIDEBAR_AREA_MY_PROJECTS,
        ));

        return back()->with('success', 'Project monitoring status updated.');
    }

    public function download(Request $request, ProjectProgressReport $report)
    {
        $this->authorizeViewer($request, $report);
        abort_unless($report->attachment_path && Storage::disk('local')->exists($report->attachment_path), 404);

        return Storage::disk('local')->download($report->attachment_path);
    }

    public function downloadMonitoringTool(
        Request $request,
        ProjectProgressReport $report,
        MonitoringToolDocumentService $documentService,
        DocumentPdfConverter $pdfConverter,
    ): StreamedResponse {
        $this->authorizeViewer($request, $report);

        if (filled($report->official_pdf_path)
            && filled($report->official_pdf_filename)
            && Storage::disk('local')->exists($report->official_pdf_path)) {
            return Storage::disk('local')->download(
                $report->official_pdf_path,
                $report->official_pdf_filename,
                ['Content-Type' => 'application/pdf', 'X-Content-Type-Options' => 'nosniff'],
            );
        }

        abort_unless($report->isSubmitted() && is_array($report->work_plan) && is_array($report->budget_utilization), 404);
        $report->loadMissing(['topic.user', 'submitter', 'reviewer']);
        $pdf = $pdfConverter->convertDocx($documentService->generate($report));

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
                $request->user()->id === $report->submitted_by
                    && $report->topic->isAccessibleTo($request->user()),
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
                    $query->where('reporting_year', $period['year'])
                        ->where('reporting_quarter', $period['quarter']);
                })->orWhere(function ($query) use ($period): void {
                    $query->whereNull('reporting_year')
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
