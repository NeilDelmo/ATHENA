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
    public function index(Request $request): View
    {
        $signingProjects = TopicProposal::query()
            ->accessibleForDocumentRelease($request->user())
            ->awaitingDocumentRelease()
            ->with('user:id,name,college')
            ->orderByRaw('COALESCE(status_started_at, created_at)')
            ->orderBy('id')
            ->paginate(10, ['*'], 'signing_page');
        $projects = TopicProposal::query()
            ->whereBelongsTo($request->user(), 'researchSecretary')
            ->with(['user', 'preparedProgressReports.submitter'])
            ->withIssuedNotice()
            ->latest('updated_at')
            ->get();
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
        $progressReports = ProjectNarrativeReport::query()
            ->submitted()
            ->latestSubmittedVersion()
            ->where('report_type', 'progress')
            ->where('review_stage', 'secretary')
            ->where('review_status', 'pending')
            ->whereHas('topic', $reviewableProject)
            ->with(['topic.user:id,name,college', 'submitter:id,name'])
            ->get([
                'id', 'topic_id', 'submitted_by', 'reporting_date', 'reporting_quarter',
                'period_start', 'period_end', 'version_number', 'research_head_remarks', 'submitted_at', 'created_at',
            ]);
        $reportReviewTasks = $monitoringReports->toBase()->concat($progressReports)
            ->sortBy(fn (ProjectProgressReport|ProjectNarrativeReport $report) => $report->submitted_at ?? $report->created_at)
            ->values();

        return view('research_secretary.projects.index', compact('projects', 'signingProjects', 'reportReviewTasks'));
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
