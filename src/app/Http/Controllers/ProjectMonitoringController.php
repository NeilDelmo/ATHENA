<?php

namespace App\Http\Controllers;

use App\Actions\PrepareProjectProgressReport;
use App\Contracts\DocumentPdfConverter;
use App\Http\Requests\StoreProjectProgressReportRequest;
use App\Http\Requests\SubmitPreparedProjectProgressReportRequest;
use App\Models\ProjectNarrativeReport;
use App\Models\ProjectProgressReport;
use App\Models\TopicProposal;
use App\Models\User;
use App\Notifications\ProposalActivityNotification;
use App\Services\MonitoringToolDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectMonitoringController extends Controller
{
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

    public function prepare(
        StoreProjectProgressReportRequest $request,
        TopicProposal $topic,
        PrepareProjectProgressReport $prepareProjectProgressReport,
    ): RedirectResponse {
        $existingPreparedReport = ProjectProgressReport::query()
            ->prepared()
            ->whereBelongsTo($topic, 'topic')
            ->where('submitted_by', $request->user()->id)
            ->exists();

        if ($existingPreparedReport) {
            return back()->withErrors([
                'preparation' => 'Discard the prepared Monitoring Tool before preparing another one.',
            ]);
        }

        try {
            $prepareProjectProgressReport->handle(
                $topic,
                $request->user(),
                $request->validated(),
                $request->file('attachment'),
            );
        } catch (\Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->withErrors([
                    'preparation' => 'The Monitoring Tool PDF could not be prepared. Your form data was kept, so you can try again.',
                ]);
        }

        return back()->with('success', 'Monitoring Tool PDF prepared. Review it, then submit the exact file to the Research Head.');
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
            'Monitoring tool submitted',
            $request->user()->name.' submitted a monitoring tool for '.$topic->title.'.',
            route('topics.show', $topic).'#project-monitoring',
            'info',
            $topic->id,
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

    public function store(StoreProjectProgressReportRequest $request, TopicProposal $topic): RedirectResponse
    {
        $validated = $request->validated();
        $workPlan = collect($validated['work_plan']);

        $validated['topic_id'] = $topic->id;
        $validated['submitted_by'] = $request->user()->id;
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
        unset($validated['attachment']);

        $report = ProjectProgressReport::create($validated);

        User::role('research_head')->get()->each->notify(new ProposalActivityNotification(
            'Monitoring tool submitted',
            $request->user()->name.' submitted a monitoring tool for “'.$topic->title.'”.',
            route('topics.show', $topic).'#project-monitoring',
            'info',
            $topic->id,
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

    public function review(Request $request, ProjectProgressReport $report): RedirectResponse
    {
        abort_unless($report->topic()->withIssuedNotice()->exists(), 404);
        abort_unless($report->isSubmitted(), 404);

        $validated = $request->validate([
            'review_status' => ['required', Rule::in(['reviewed', 'revision_requested'])],
            'research_head_remarks' => ['nullable', 'required_if:review_status,revision_requested', 'string', 'max:5000'],
        ]);

        $report->update([
            ...$validated,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        $report->load('topic.user');
        $report->topic->user->notify(new ProposalActivityNotification(
            $validated['review_status'] === 'reviewed' ? 'Monitoring tool reviewed' : 'Monitoring tool needs revision',
            'The Research Head reviewed your monitoring tool for “'.$report->topic->title.'”.',
            route('topics.show', $report->topic).'#project-monitoring',
            $validated['review_status'] === 'reviewed' ? 'success' : 'warning',
            $report->topic_id,
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
}
