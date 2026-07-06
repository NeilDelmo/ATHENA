<?php

namespace App\Http\Controllers;

use App\Models\ProposalTemplate;
use App\Models\TopicExpertAssignment;
use App\Notifications\ProposalActivityNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ExpertReviewController extends Controller
{
    public function index(Request $request)
    {
        $assignments = $request->user()->expertAssignments()
            ->with(['topic.user', 'topic.category', 'topic.researchCall', 'topic.versions.submitter', 'topic.versions.files'])
            ->latest()
            ->get();
        $screeningTemplates = ProposalTemplate::active()
            ->where('workflow_stage', ProposalTemplate::STAGE_INITIAL_SCREENING)
            ->orderBy('name')
            ->get()
            ->filter(fn (ProposalTemplate $template) => Storage::disk('local')->exists($template->file_path));

        return view('expert.dashboard', compact('assignments', 'screeningTemplates'));
    }

    public function submit(Request $request, TopicExpertAssignment $assignment)
    {
        abort_unless($assignment->expert_id === $request->user()->id, 403);

        $validated = $request->validate([
            'recommendation' => ['required', Rule::in(['recommend_approval', 'recommend_revision', 'recommend_rejection'])],
            'comment' => ['required', 'string', 'max:5000'],
            'redirect_to' => ['nullable', Rule::in(['topic'])],
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
                'Co-evaluation completed',
                $request->user()->name.' submitted an Initial Screening recommendation for “'.$assignment->topic()->firstOrFail()->title.'”.',
                route('topics.show', $assignment->topic_id),
                'info',
                $assignment->topic_id,
            ));
        }

        if (($validated['redirect_to'] ?? null) === 'topic') {
            return redirect()->route('topics.show', $assignment->topic_id)
                ->with('success', 'Your Initial Screening recommendation was submitted to the Research Head.');
        }

        return back()->with('success', 'Your Initial Screening recommendation was submitted to the Research Head.');
    }
}
