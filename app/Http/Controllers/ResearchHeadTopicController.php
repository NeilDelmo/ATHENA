<?php

namespace App\Http\Controllers;

use App\Models\TopicProposal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class ResearchHeadTopicController extends Controller
{
    public function index()
    {
        $topics = TopicProposal::with([
            'user',
            'reviews' => fn ($query) => $query->with('reviewer')->oldest(),
        ])
            ->latest()
            ->get();

        return view('research_head.dashboard', compact('topics'));
    }

    public function updateStatus(Request $request, TopicProposal $topic)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['approved', 'revision_requested', 'rejected'])],
            'comment' => ['nullable', 'required_if:status,revision_requested,rejected', 'string', 'max:5000'],
        ], [
            'comment.required_if' => 'Review comments are required when requesting a revision or rejecting a proposal.',
        ]);

        DB::transaction(function () use ($request, $topic, $validated) {
            $reviewedTopic = TopicProposal::query()
                ->whereKey($topic->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($reviewedTopic->status, ['pending', 'resubmitted'], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Only pending or resubmitted proposals can be reviewed.',
                ]);
            }

            $reviewedTopic->update(['status' => $validated['status']]);

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

        $message = match ($validated['status']) {
            'approved' => 'Proposal approved successfully.',
            'revision_requested' => 'Revision requested and your comments were sent to the faculty member.',
            'rejected' => 'Proposal rejected and your comments were recorded.',
        };

        return redirect()->route('research_head.dashboard')->with('success', $message);
    }
}
