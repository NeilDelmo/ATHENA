<?php

namespace App\Http\Controllers;

use App\Contracts\DocumentPdfConverter;
use App\Http\Requests\AssignProjectResearchSecretaryRequest;
use App\Http\Requests\UpdateProjectBudgetUtilizationRequest;
use App\Models\ProjectNarrativeReport;
use App\Models\ProjectProgressReport;
use App\Models\TopicCollaborator;
use App\Models\TopicProposal;
use App\Notifications\ProposalActivityNotification;
use App\Services\MonitoringToolDocumentService;
use App\Services\ProjectMonitoringHealth;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProjectBudgetUtilizationController extends Controller
{
    public function index(Request $request, ProjectMonitoringHealth $monitoringHealth): View
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:200'], 'status' => ['nullable', 'string', 'max:40'], 'type' => ['nullable', 'in:monitoring,progress,terminal,returned']]);
        $signingProjects = TopicProposal::query()
            ->accessibleForDocumentRelease($request->user())
            ->awaitingDocumentRelease()
            ->with('user:id,name,college')
            ->orderByRaw('COALESCE(status_started_at, created_at)')
            ->orderBy('id')
            ->paginate(10, ['*'], 'signing_page');
        $projectQuery = TopicProposal::query()->withIssuedNotice();
        $projectCount = (clone $projectQuery)->count();
        $projects = $request->routeIs('research_secretary.projects.index')
            ? $projectQuery->with(['user', 'progressReports', 'narrativeReports', 'latestProgressReport', 'researchCall'])->latest('updated_at')->get()
            : collect();
        $reviewableProject = fn (Builder $query): Builder => $query
            ->forImplementationReportReview($request->user())
            ->withIssuedNotice()
            ->whereIn('project_status', [TopicProposal::PROJECT_STATUS_ONGOING, TopicProposal::PROJECT_STATUS_DELAYED]);
        $monitoringReports = ProjectProgressReport::query()
            ->submitted()
            ->latestSubmittedVersion()
            ->where('review_stage', 'secretary')
            ->where('review_status', 'pending')
            ->whereHas('topic', $reviewableProject)
            ->with(['topic.user:id,name,college', 'submitter:id,name'])
            ->get([
                'id', 'topic_id', 'submitted_by', 'reporting_date', 'reporting_year', 'reporting_quarter',
                'period_start', 'period_end', 'version_number', 'research_head_remarks', 'submitted_at', 'created_at',
            ]);
        $narrativeReports = ProjectNarrativeReport::query()
            ->awaitingSecretaryReview()
            ->latestSubmittedVersion()
            ->whereHas('topic', $reviewableProject)
            ->with(['topic.user:id,name,college', 'submitter:id,name'])
            ->get([
                'id', 'topic_id', 'submitted_by', 'report_type', 'reporting_date', 'reporting_quarter',
                'period_start', 'period_end', 'version_number', 'research_head_remarks', 'submitted_at', 'created_at',
            ]);
        $reportReviewTasks = $monitoringReports->toBase()->concat($narrativeReports)
            ->sortBy(fn (ProjectProgressReport|ProjectNarrativeReport $report) => $report->submitted_at ?? $report->created_at)
            ->values();

        $budgetMonitoring = $projects->mapWithKeys(fn (TopicProposal $project): array => [$project->id => $monitoringHealth->assess($project)]);
        $view = match ($request->route()->getName()) {
            'research_secretary.projects.index' => 'research_secretary.projects.index',
            'research_secretary.reports.index' => 'research_secretary.reports',
            'research_secretary.notices.index' => 'research_secretary.notices',
            default => 'research_secretary.dashboard',
        };
        if ($request->routeIs('research_secretary.projects.index')) {
            $projects = $projects->filter(fn (TopicProposal $project): bool => (! filled($filters['q'] ?? null) || Str::contains(Str::lower($project->title.' '.$project->user->name), Str::lower(trim($filters['q']))))
                && (! filled($filters['status'] ?? null) || ($project->project_status ?? 'ongoing') === $filters['status']));
        }
        if ($request->routeIs('research_secretary.reports.index') && filled($filters['type'] ?? null)) {
            $reportReviewTasks = $reportReviewTasks->filter(fn (ProjectProgressReport|ProjectNarrativeReport $report): bool => match ($filters['type']) {
                'monitoring' => $report instanceof ProjectProgressReport,
                'progress', 'terminal' => $report instanceof ProjectNarrativeReport && $report->report_type === $filters['type'],
                'returned' => filled($report->research_head_remarks),
            })->values();
        }

        return view($view, compact('projects', 'signingProjects', 'reportReviewTasks', 'budgetMonitoring', 'projectCount'));
    }

    public function budgets(Request $request, ProjectMonitoringHealth $monitoringHealth): View
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:200']]);
        $forResearchHead = $request->routeIs('research_head.budgets.index');
        $workspace = $forResearchHead ? 'research_head' : 'research_secretary';
        $latestReport = $forResearchHead ? 'latestProgressReportForResearchHead' : 'latestProgressReport';
        $projects = TopicProposal::query()->withIssuedNotice()
            ->with(['user', 'progressReports', 'narrativeReports', 'researchCall', $latestReport])
            ->latest('updated_at')->get()
            ->filter(fn (TopicProposal $project): bool => ! filled($filters['q'] ?? null)
                || Str::contains(Str::lower($project->title.' '.$project->user->name), Str::lower(trim($filters['q']))));
        $budgetMonitoring = $projects->mapWithKeys(fn (TopicProposal $project): array => [
            $project->id => $monitoringHealth->assess($project, forResearchHead: $forResearchHead),
        ]);

        return view($workspace.'.budgets', compact('projects', 'budgetMonitoring'));
    }

    public function edit(Request $request, TopicProposal $topic, ProjectProgressReport $report): View
    {
        abort_unless(
            $topic->canPrepareMonitoringBudget($request->user())
                && $report->topic_id === $topic->id
                && $report->isPrepared(),
            404,
        );

        $report->loadMissing(['topic.user', 'submitter', 'budgetPreparer']);

        return view('research_secretary.projects.budget', compact('topic', 'report'));
    }

    public function update(
        UpdateProjectBudgetUtilizationRequest $request,
        TopicProposal $topic,
        ProjectProgressReport $report,
        MonitoringToolDocumentService $documentService,
        DocumentPdfConverter $pdfConverter,
    ): RedirectResponse {
        if (! hash_equals($report->budgetToken(), $request->validated('budget_token'))) {
            throw ValidationException::withMessages([
                'budget_token' => 'This budget changed after you opened the form. Reload the current budget before saving again.',
            ]);
        }
        $report->loadMissing(['topic.user', 'submitter', 'reviewer']);
        $report->fill([
            'budget_utilization' => $request->validated('budget_utilization'),
            'budget_prepared_by' => $request->user()->id,
            'budget_prepared_at' => now(),
        ]);
        $previousPdfPath = $report->official_pdf_path;
        $updatedPdfPath = null;

        try {
            $pdf = $pdfConverter->convertDocx($documentService->generate($report));
            $report = DB::transaction(function () use ($request, $topic, $report, $pdf, &$updatedPdfPath): ProjectProgressReport {
                $lockedTopic = TopicProposal::query()->whereKey($topic->id)->lockForUpdate()->firstOrFail();
                $lockedReport = ProjectProgressReport::query()->whereKey($report->id)->lockForUpdate()->firstOrFail();
                if (! $lockedReport->isPrepared() || $lockedReport->topic_id !== $lockedTopic->id
                    || ! $lockedTopic->canPrepareMonitoringBudget($request->user())
                    || ! hash_equals($lockedReport->budgetToken(), $request->validated('budget_token'))) {
                    throw ValidationException::withMessages([
                        'budget_utilization' => 'This Monitoring Tool changed or is no longer editable. Reload the report before saving its budget.',
                    ]);
                }
                if (! filled($lockedReport->official_pdf_path)) {
                    throw new \RuntimeException('The prepared Monitoring Tool PDF is unavailable.');
                }
                $updatedPdfPath = 'progress-reports/'.$topic->id.'/prepared/'.Str::uuid().'.pdf';
                if (! Storage::disk('local')->put($updatedPdfPath, $pdf)) {
                    throw new \RuntimeException('The updated Monitoring Tool PDF could not be stored.');
                }
                $lockedReport->fill([
                    ...$report->only(['budget_utilization', 'budget_prepared_by', 'budget_prepared_at']),
                    'official_pdf_path' => $updatedPdfPath,
                    'official_pdf_checksum' => hash('sha256', $pdf),
                    'official_pdf_size' => strlen($pdf),
                ])->save();

                return $lockedReport->setRelation('topic', $lockedTopic);
            });
        } catch (\Throwable $exception) {
            if ($updatedPdfPath !== null) {
                Storage::disk('local')->delete($updatedPdfPath);
            }
            if ($exception instanceof ValidationException) {
                throw $exception;
            }
            report($exception);

            return back()->withInput()->withErrors([
                'budget_utilization' => 'The budget could not be applied to the prepared Monitoring Tool. Please try again.',
            ]);
        }
        Storage::disk('local')->delete($previousPdfPath);

        $budgetPreparer = $request->user();
        $preparerLabel = $report->topic->research_secretary_id === $budgetPreparer->id
            ? 'The assigned project secretary'
            : $budgetPreparer->name;

        $report->submitter->notify(new ProposalActivityNotification(
            title: $report->quarter_label.' Budget Utilization Completed',
            message: $preparerLabel.' completed the budget utilization for '.$topic->title.'. The prepared Monitoring Tool is ready for submission.',
            url: route('project-progress.create', ['topic' => $topic, 'reporting_date' => $report->reporting_date->toDateString()]),
            level: 'success',
            topicId: $topic->id,
            workspace: 'faculty_researcher',
            sidebarArea: ProposalActivityNotification::SIDEBAR_AREA_MY_PROJECTS,
        ));

        $destination = $request->routeIs('project-budget.update')
            ? route('research.show', $topic).'#project-monitoring'
            : route('research_secretary.dashboard');

        return redirect()->to($destination)
            ->with('success', $report->quarter_label.' budget utilization saved and the official PDF refreshed.');
    }

    public function assign(
        AssignProjectResearchSecretaryRequest $request,
        TopicProposal $topic,
    ): RedirectResponse {
        $secretaryId = $request->filled('research_secretary_id')
            ? $request->integer('research_secretary_id')
            : null;

        DB::transaction(function () use ($topic, $secretaryId): void {
            $lockedTopic = TopicProposal::query()->whereKey($topic->id)->lockForUpdate()->firstOrFail();

            if ($lockedTopic->research_secretary_id === $secretaryId) {
                return;
            }

            $lockedTopic->update(['research_secretary_id' => $secretaryId]);
            $lockedTopic->collaborators()
                ->where('project_role', TopicCollaborator::ROLE_SECRETARY)
                ->update(['project_role' => null]);

            if ($secretaryId !== null) {
                $lockedTopic->collaborators()
                    ->where('user_id', $secretaryId)
                    ->whereNotNull('accepted_at')
                    ->update(['project_role' => TopicCollaborator::ROLE_SECRETARY]);
            }
        });

        return back()->with(
            'success',
            $secretaryId ? 'Project secretary assigned by the project group.' : 'Project secretary assignment removed.',
        );
    }
}
