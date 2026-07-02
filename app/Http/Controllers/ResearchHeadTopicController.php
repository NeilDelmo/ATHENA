<?php

namespace App\Http\Controllers;

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
            'user', 'researchCall', 'category', 'expertAssignments.expert', 'versions.submitter', 'versions.files',
            'reviews' => fn ($query) => $query->with('reviewer')->oldest(),
        ])
            ->latest()
            ->get();

        $experts = User::role('expert')->orderBy('name')->get();

        return view('research_head.dashboard', compact('topics', 'experts'));
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

        $approvalPath = null;
        if ($request->hasFile('signed_approval')) {
            $approvalPath = $request->file('signed_approval')->store('approvals', 'local');
        }

        try {
            DB::transaction(function () use ($request, $topic, $validated, $approvalPath) {
                $reviewedTopic = TopicProposal::query()
                    ->whereKey($topic->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! in_array($reviewedTopic->status, ['pending', 'resubmitted', 'for_final_decision'], true)) {
                    throw ValidationException::withMessages([
                        'status' => 'Only pending or resubmitted proposals can be reviewed.',
                    ]);
                }

                if ($validated['status'] === 'expert_review') {
                    if ($reviewedTopic->status === 'for_final_decision') {
                        throw ValidationException::withMessages(['status' => 'Expert review has already been completed.']);
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

                $reviewedTopic->reviews()->create([
                    'reviewer_id' => $request->user()->id,
                    'decision' => $validated['status'],
                    'comment' => $validated['comment'] ?? null,
                ]);

                if ($validated['status'] === 'approved') {
                    $facultyRole = Role::firstOrCreate(['name' => 'faculty']);
                    $facultyResearcherRole = Role::firstOrCreate(['name' => 'faculty_researcher']);

                    $reviewedTopic->user()->firstOrFail()->assignRole([$facultyRole, $facultyResearcherRole]);
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
                    'Expert review assigned',
                    'You were assigned to review “'.$topic->title.'”.',
                    route('topics.show', $topic),
                    'info',
                    $topic->id,
                ),
            );
        } else {
            $notificationDetails = match ($validated['status']) {
                'approved' => ['Proposal approved', 'Your proposal “'.$topic->title.'” was approved.', 'success'],
                'revision_requested' => ['Revision requested', 'Changes were requested for “'.$topic->title.'”. Review the comments and submit a new version.', 'warning'],
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
            'expert_review' => 'Proposal sent to the selected subject experts.',
            'revision_requested' => 'Revision requested and your comments were sent to the faculty member.',
            'rejected' => 'Proposal rejected and your comments were recorded.',
        };

        $redirectRoute = ($validated['redirect_to'] ?? null) === 'topic' ? 'topics.show' : 'research_head.dashboard';

        return redirect()->route($redirectRoute, $redirectRoute === 'topics.show' ? $topic : [])->with('success', $message);
    }
}
