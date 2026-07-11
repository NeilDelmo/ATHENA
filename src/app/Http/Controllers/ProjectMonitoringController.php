<?php

namespace App\Http\Controllers;

use App\Models\ProjectProgressReport;
use App\Models\TopicProposal;
use App\Models\User;
use App\Notifications\ProposalActivityNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

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
            'ongoing' => TopicProposal::where('status', 'approved')->where(fn ($query) => $query->where('project_status', 'ongoing')->orWhereNull('project_status'))->count(),
            'delayed' => TopicProposal::where('status', 'approved')->where('project_status', 'delayed')->count(),
            'completed' => TopicProposal::where('status', 'approved')->where('project_status', 'completed')->count(),
            'pending_reports' => ProjectProgressReport::where('review_status', 'pending')
                ->whereHas('topic', fn ($query) => $query->where('status', 'approved'))
                ->count(),
        ];

        $projects = TopicProposal::query()
            ->where('status', 'approved')
            ->with(['user', 'researchCall', 'category', 'latestProgressReport'])
            ->withCount([
                'progressReports',
                'progressReports as pending_reports_count' => fn ($query) => $query->where('review_status', 'pending'),
            ])
            ->when(in_array($status, $allowedStatuses, true), function ($query) use ($status) {
                $status === 'ongoing'
                    ? $query->where(fn ($query) => $query->where('project_status', 'ongoing')->orWhereNull('project_status'))
                    : $query->where('project_status', $status);
            })
            ->when(in_array($attention, $allowedAttention, true), function ($query) use ($attention) {
                if ($attention === 'pending_reports') {
                    $query->whereHas('progressReports', fn ($query) => $query->where('review_status', 'pending'));
                } else {
                    $query->where(function ($query) {
                        $query->where('project_status', 'delayed')
                            ->orWhereHas('progressReports', fn ($query) => $query->where('review_status', 'pending'));
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

    public function store(Request $request, TopicProposal $topic): RedirectResponse
    {
        abort_unless($topic->status === 'approved' && $topic->user_id === $request->user()->id, 403);

        $validated = $request->validate([
            'reporting_date' => ['required', 'date', 'before_or_equal:today'],
            'progress_percentage' => ['required', 'integer', 'between:0,100'],
            'accomplishments' => ['required', 'string', 'max:5000'],
            'issues' => ['nullable', 'string', 'max:5000'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png', 'max:25600'],
        ]);

        $validated['topic_id'] = $topic->id;
        $validated['submitted_by'] = $request->user()->id;
        $validated['attachment_path'] = $request->file('attachment')?->store('progress-reports/'.$topic->id, 'local');
        unset($validated['attachment']);

        $report = ProjectProgressReport::create($validated);

        User::role('research_head')->get()->each->notify(new ProposalActivityNotification(
            'Project progress report submitted',
            $request->user()->name.' submitted a progress report for “'.$topic->title.'”.',
            route('topics.show', $topic).'#project-monitoring',
            'info',
            $topic->id,
        ));

        return back()->with('success', 'Progress report submitted for Research Head review.');
    }

    public function review(Request $request, ProjectProgressReport $report): RedirectResponse
    {
        abort_unless($report->topic()->where('status', 'approved')->exists(), 404);

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
            $validated['review_status'] === 'reviewed' ? 'Progress report reviewed' : 'Progress report needs revision',
            'The Research Head reviewed your progress report for “'.$report->topic->title.'”.',
            route('topics.show', $report->topic).'#project-monitoring',
            $validated['review_status'] === 'reviewed' ? 'success' : 'warning',
            $report->topic_id,
        ));

        return back()->with('success', 'Progress report review saved.');
    }

    public function updateProjectStatus(Request $request, TopicProposal $topic): RedirectResponse
    {
        abort_unless($topic->status === 'approved', 404);

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
            $request->user()->hasRole('research_head') || $topic->user_id === $request->user()->id,
            403,
        );
        abort_unless($report->attachment_path && Storage::disk('local')->exists($report->attachment_path), 404);

        return Storage::disk('local')->download($report->attachment_path);
    }
}
