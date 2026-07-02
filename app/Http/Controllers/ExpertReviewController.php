<?php

namespace App\Http\Controllers;

use App\Models\TopicExpertAssignment;
use App\Notifications\ProposalActivityNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ExpertReviewController extends Controller
{
    public function index(Request $request)
    {
        $assignments = $request->user()->expertAssignments()
            ->with(['topic.user', 'topic.category', 'topic.researchCall', 'topic.versions.submitter'])
            ->latest()
            ->get();

        return view('expert.dashboard', compact('assignments'));
    }

    public function submit(Request $request, TopicExpertAssignment $assignment)
    {
        abort_unless($assignment->expert_id === $request->user()->id, 403);

        $validated = $request->validate([
            'recommendation' => ['required', Rule::in(['recommend_approval', 'recommend_revision', 'recommend_rejection'])],
            'comment' => ['required', 'string', 'max:5000'],
        ]);

        $submitted = DB::transaction(function () use ($assignment, $validated) {
            $review = TopicExpertAssignment::query()->whereKey($assignment->id)->lockForUpdate()->firstOrFail();

            if ($review->status !== 'pending') {
                return false;
            }

            $review->update([
                ...$validated,
                'status' => 'completed',
                'reviewed_at' => now(),
            ]);

            $topic = $review->topic()->lockForUpdate()->firstOrFail();
            if ($topic->expertAssignments()->where('status', 'pending')->doesntExist()) {
                $topic->update(['status' => 'for_final_decision']);
            }

            return true;
        });

        if ($submitted) {
            $assignment->assigner()->first()?->notify(new ProposalActivityNotification(
                'Expert review completed',
                $request->user()->name.' submitted a recommendation for “'.$assignment->topic()->firstOrFail()->title.'”.',
                route('research_head.dashboard'),
                'info',
                $assignment->topic_id,
            ));
        }

        return back()->with('success', 'Your expert recommendation was submitted to the Research Head.');
    }
}
