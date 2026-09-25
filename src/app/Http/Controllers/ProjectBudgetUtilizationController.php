<?php

namespace App\Http\Controllers;

use App\Contracts\DocumentPdfConverter;
use App\Http\Requests\AssignProjectResearchSecretaryRequest;
use App\Http\Requests\UpdateProjectBudgetUtilizationRequest;
use App\Models\ProjectProgressReport;
use App\Models\TopicCollaborator;
use App\Models\TopicProposal;
use App\Notifications\ProposalActivityNotification;
use App\Services\MonitoringToolDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProjectBudgetUtilizationController extends Controller
{
    public function index(Request $request): View
    {
        $projects = TopicProposal::query()
            ->whereBelongsTo($request->user(), 'researchSecretary')
            ->with(['user', 'preparedProgressReports.submitter'])
            ->withIssuedNotice()
            ->latest('updated_at')
            ->get();

        return view('research_secretary.projects.index', compact('projects'));
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
        $report->loadMissing(['topic.user', 'submitter', 'reviewer']);
        $report->fill([
            'budget_utilization' => $request->validated('budget_utilization'),
            'budget_prepared_by' => $request->user()->id,
            'budget_prepared_at' => now(),
        ]);

        try {
            $pdf = $pdfConverter->convertDocx($documentService->generate($report));

            if (! filled($report->official_pdf_path) || ! Storage::disk('local')->put($report->official_pdf_path, $pdf)) {
                throw new \RuntimeException('The updated Monitoring Tool PDF could not be stored.');
            }

            $report->fill([
                'official_pdf_checksum' => hash('sha256', $pdf),
                'official_pdf_size' => strlen($pdf),
            ])->save();
        } catch (\Throwable $exception) {
            report($exception);

            return back()->withInput()->withErrors([
                'budget_utilization' => 'The budget could not be applied to the prepared Monitoring Tool. Please try again.',
            ]);
        }

        $budgetPreparer = $request->user();
        $preparerLabel = $topic->research_secretary_id === $budgetPreparer->id
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
