<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectProgressReportRequest;
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
            'ongoing' => TopicProposal::monitoringAvailable()->where('project_status', 'ongoing')->count(),
            'delayed' => TopicProposal::monitoringAvailable()->where('project_status', 'delayed')->count(),
            'completed' => TopicProposal::monitoringAvailable()->where('project_status', 'completed')->count(),
            'pending_reports' => ProjectProgressReport::where('review_status', 'pending')
                ->whereHas('topic', fn ($query) => $query->monitoringAvailable())
                ->count()
                + ProjectNarrativeReport::where('review_status', ProjectNarrativeReport::STATUS_PENDING)
                    ->whereHas('topic', fn ($query) => $query->monitoringAvailable())
                    ->count(),
        ];

        $projects = TopicProposal::monitoringAvailable()
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
            ->orderByRaw("CASE WHEN project_status = 'delayed' OR EXISTS (SELECT 1 FROM project_progress_reports WHERE project_progress_reports.topic_id = topics.id AND review_status = 'pending') THEN 0 ELSE 1 END")
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString();

        return view('research_head.projects.index', compact('projects', 'summary', 'status', 'attention', 'search'));
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

    public function review(Request $request, ProjectProgressReport $report): RedirectResponse
    {
        abort_unless($report->topic()->monitoringAvailable()->exists(), 404);

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
            'project_status' => ['required', Rule::in(['ongoing', 'delayed', 'completed'])],
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
        $topic = $report->topic;
        abort_unless(
            $request->user()->isUsingWorkspace('research_head') || $topic->user_id === $request->user()->id,
            403,
        );
        abort_unless($report->attachment_path && Storage::disk('local')->exists($report->attachment_path), 404);

        return Storage::disk('local')->download($report->attachment_path);
    }

    public function downloadMonitoringTool(
        Request $request,
        ProjectProgressReport $report,
        MonitoringToolDocumentService $documentService,
    ): StreamedResponse {
        $report->loadMissing(['topic.user', 'submitter', 'reviewer']);
        abort_unless(
            $request->user()->isUsingWorkspace('research_head') || $report->topic->user_id === $request->user()->id,
            403,
        );
        abort_unless(is_array($report->work_plan) && is_array($report->budget_utilization), 404);

        $filename = Str::slug($report->topic->title).'-monitoring-tool.docx';

        return response()->streamDownload(
            static fn () => print $documentService->generate($report),
            $filename,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        );
    }
}
