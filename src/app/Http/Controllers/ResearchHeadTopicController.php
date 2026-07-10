<?php

namespace App\Http\Controllers;

use App\Models\ProposalTemplate;
use App\Models\TopicProposal;
use App\Models\User;
use App\Notifications\ProposalActivityNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class ResearchHeadTopicController extends Controller
{
    public function index()
    {
        $topics = TopicProposal::with([
            'user', 'researchCall', 'category', 'expertAssignments.expert', 'versions.submitter', 'versions.files', 'progressReports',
            'reviews' => fn ($query) => $query->with(['reviewer', 'fileRevisions.file'])->oldest(),
        ])
            ->latest()
            ->get();

        $experts = User::role('expert')->orderBy('name')->get();
        $screeningTemplates = ProposalTemplate::active()
            ->where('workflow_stage', ProposalTemplate::STAGE_INITIAL_SCREENING)
            ->orderBy('name')
            ->get()
            ->filter(fn (ProposalTemplate $template) => Storage::disk('local')->exists($template->file_path));

        return view('research_head.dashboard', compact('topics', 'experts', 'screeningTemplates'));
    }

    public function updateStatus(Request $request, TopicProposal $topic)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['expert_review', 'approved', 'revision_requested', 'rejected'])],
            'redirect_to' => ['nullable', Rule::in(['topic'])],
            'comment' => ['nullable', 'required_if:status,revision_requested,rejected', 'string', 'max:5000'],
            'expert_ids' => ['nullable', 'required_if:status,expert_review', 'array', 'min:1'],
            'expert_ids.*' => ['integer', 'distinct', 'exists:users,id'],
            'signed_approval' => ['nullable', 'required_if:status,approved', 'file', 'mimes:pdf', 'max:25600'],
            'revision_file_ids' => ['nullable', 'array'],
            'revision_file_ids.*' => ['integer', 'distinct', 'exists:proposal_version_files,id'],
            'revision_file_notes' => ['nullable', 'array'],
            'revision_file_notes.*' => ['nullable', 'string', 'max:2000'],
        ], [
            'comment.required_if' => 'Review comments are required when requesting a revision or rejecting a proposal.',
        ]);

        if ($validated['status'] === 'expert_review') {
            $expertCount = User::role('expert')
                ->whereKey($validated['expert_ids'])
                ->count();

            if ($expertCount !== count($validated['expert_ids'])) {
                throw ValidationException::withMessages([
                    'expert_ids' => 'Every selected reviewer must have the expert role.',
                ]);
            }
        }

        $selectedRevisionFiles = collect();

        if ($validated['status'] === 'revision_requested') {
            $latestVersion = $topic->latestVersion()->with('files')->first();
            $latestFiles = $latestVersion?->files ?? collect();
            $selectedIds = collect($validated['revision_file_ids'] ?? [])->map(fn ($id) => (int) $id);
            $selectedRevisionFiles = $latestFiles->whereIn('id', $selectedIds)->values();

            if ($latestFiles->isNotEmpty() && $selectedIds->isEmpty()) {
                throw ValidationException::withMessages([
                    'revision_file_ids' => 'Select at least one proposal file that requires revision.',
                ]);
            }

            if ($selectedRevisionFiles->count() !== $selectedIds->count()) {
                throw ValidationException::withMessages([
                    'revision_file_ids' => 'Every selected file must belong to the latest proposal version.',
                ]);
            }
        }

        $approvalPath = null;
        if ($request->hasFile('signed_approval')) {
            $approvalPath = $request->file('signed_approval')->store('approvals', 'local');
        }

        try {
            DB::transaction(function () use ($request, $topic, $validated, $approvalPath, $selectedRevisionFiles) {
                $reviewedTopic = TopicProposal::query()
                    ->whereKey($topic->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! in_array($reviewedTopic->status, ['pending', 'resubmitted', 'for_final_decision'], true)) {
                    throw ValidationException::withMessages([
                        'status' => 'Only pending or resubmitted proposals can be reviewed.',
                    ]);
                }

                if ($validated['status'] === 'approved') {
                    $this->ensureInitialScreeningCompleted($reviewedTopic);
                    $this->ensureResearchWorkloadAvailable($reviewedTopic);
                }

                if ($validated['status'] === 'expert_review') {
                    if ($reviewedTopic->status === 'for_final_decision') {
                        throw ValidationException::withMessages(['status' => 'Initial screening has already been completed.']);
                    }

                    if ($reviewedTopic->status === 'resubmitted') {
                        $previousEvaluatorIds = $reviewedTopic->expertAssignments()
                            ->pluck('expert_id')
                            ->unique()
                            ->sort()
                            ->values();
                        $selectedEvaluatorIds = collect($validated['expert_ids'])->map(fn ($id) => (int) $id)->sort()->values();

                        if ($previousEvaluatorIds->isNotEmpty() && $previousEvaluatorIds->all() !== $selectedEvaluatorIds->all()) {
                            throw ValidationException::withMessages([
                                'expert_ids' => 'A revised proposal must repeat Initial Screening with the same assigned co-evaluator(s).',
                            ]);
                        }
                    }

                    $reviewedTopic->expertAssignments()->createMany(
                        collect($validated['expert_ids'])->map(fn ($expertId) => [
                            'expert_id' => $expertId,
                            'assigned_by' => $request->user()->id,
                            'status' => 'pending',
                        ])->all()
                    );

                    $reviewedTopic->update(['status' => 'expert_review']);
                    $reviewedTopic->reviews()->create([
                        'reviewer_id' => $request->user()->id,
                        'decision' => 'expert_review_assigned',
                        'comment' => $validated['comment'] ?? null,
                    ]);

                    return;
                }

                $reviewedTopic->update([
                    'status' => $validated['status'],
                    'signed_approval_path' => $validated['status'] === 'approved' ? $approvalPath : null,
                ]);

                if ($validated['status'] === 'revision_requested') {
                    $reviewedTopic->expertAssignments()
                        ->whereIn('status', ['pending', 'completed'])
                        ->update(['status' => 'superseded']);
                }

                if ($validated['status'] === 'rejected') {
                    $reviewedTopic->expertAssignments()
                        ->where('status', 'pending')
                        ->update(['status' => 'cancelled']);
                }

                $review = $reviewedTopic->reviews()->create([
                    'reviewer_id' => $request->user()->id,
                    'decision' => $validated['status'],
                    'comment' => $validated['comment'] ?? null,
                ]);

                if ($validated['status'] === 'revision_requested') {
                    $review->fileRevisions()->createMany($selectedRevisionFiles->map(fn ($file) => [
                        'proposal_version_file_id' => $file->id,
                        'document_type' => $file->document_type,
                        'original_filename' => $file->original_filename,
                        'revision_note' => $validated['revision_file_notes'][$file->id] ?? null,
                    ])->all());
                }

                if ($validated['status'] === 'approved') {
                    $facultyRole = Role::firstOrCreate(['name' => 'faculty']);
                    $facultyResearcherRole = Role::firstOrCreate(['name' => 'faculty_researcher']);

                    $reviewedTopic->user()->firstOrFail()->assignRole([$facultyRole, $facultyResearcherRole]);
                    $reviewedTopic->update(['project_status' => 'ongoing']);
                }
            });
        } catch (\Throwable $exception) {
            if ($approvalPath) {
                Storage::disk('local')->delete($approvalPath);
            }

            throw $exception;
        }

        if ($validated['status'] === 'expert_review') {
            User::query()->whereKey($validated['expert_ids'])->get()->each->notify(
                new ProposalActivityNotification(
                    'Co-evaluation assigned',
                    'You were assigned as a co-evaluator for “'.$topic->title.'”.',
                    route('topics.show', $topic),
                    'info',
                    $topic->id,
                ),
            );
        } else {
            $notificationDetails = match ($validated['status']) {
                'approved' => ['Proposal approved', 'Your proposal “'.$topic->title.'” was approved.', 'success'],
                'revision_requested' => [
                    'Revision requested',
                    ($selectedRevisionFiles->isNotEmpty() ? $selectedRevisionFiles->count().' proposal file(s) require changes in ' : 'Changes were requested for ').'“'.$topic->title.'”. Review the comments and submit a new version.',
                    'warning',
                ],
                'rejected' => ['Proposal rejected', 'Your proposal “'.$topic->title.'” was not approved. Review the decision comments.', 'danger'],
            };

            $topic->user()->firstOrFail()->notify(new ProposalActivityNotification(
                $notificationDetails[0],
                $notificationDetails[1],
                route('topics.show', $topic),
                $notificationDetails[2],
                $topic->id,
            ));
        }

        $message = match ($validated['status']) {
            'approved' => 'Proposal approved successfully.',
            'expert_review' => 'Proposal sent to the selected co-evaluator(s) for Initial Screening.',
            'revision_requested' => 'Revision requested and your comments were sent to the faculty member.',
            'rejected' => 'Proposal rejected and your comments were recorded.',
        };

        $redirectRoute = ($validated['redirect_to'] ?? null) === 'topic' ? 'topics.show' : 'research_head.dashboard';

        return redirect()->route($redirectRoute, $redirectRoute === 'topics.show' ? $topic : [])->with('success', $message);
    }

    private function ensureInitialScreeningCompleted(TopicProposal $topic): void
    {
        $assignments = $topic->expertAssignments()->get();
        $completedAssignments = $assignments->where('status', 'completed');

        if ($topic->status !== 'for_final_decision' || $completedAssignments->isEmpty() || $assignments->contains('status', 'pending')) {
            throw ValidationException::withMessages([
                'status' => 'Initial Screening must be completed by the Research/RDES Head and every assigned co-evaluator before final approval.',
            ]);
        }

        if ($completedAssignments->contains(fn ($assignment) => $assignment->recommendation !== 'recommend_approval')) {
            throw ValidationException::withMessages([
                'status' => 'Outstanding co-evaluator comments must be resolved through revision and Initial Screening before final approval.',
            ]);
        }
    }

    private function ensureResearchWorkloadAvailable(TopicProposal $topic): void
    {
        $researchCall = $topic->researchCall()->firstOrFail();
        $approvedProjectIds = TopicProposal::query()
            ->where('user_id', $topic->user_id)
            ->whereKeyNot($topic->getKey())
            ->where('status', 'approved')
            ->whereHas('researchCall', fn ($query) => $query->where('academic_year', $researchCall->academic_year))
            ->lockForUpdate()
            ->pluck('id');

        if ($approvedProjectIds->count() >= $researchCall->max_active_research_per_faculty) {
            throw ValidationException::withMessages([
                'status' => "This faculty researcher already has the maximum of {$researchCall->max_active_research_per_faculty} approved research projects for academic year {$researchCall->academic_year}. Applications remain unlimited, but another project cannot be approved for that year.",
            ]);
        }
    }
}
