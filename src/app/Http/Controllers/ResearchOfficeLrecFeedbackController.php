<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreResearchOfficeLrecFeedbackRequest;
use App\Models\TopicProposal;
use App\Models\User;
use App\Notifications\ProposalActivityNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ResearchOfficeLrecFeedbackController extends Controller
{
    public function start(Request $request, TopicProposal $topic): RedirectResponse
    {
        DB::transaction(function () use ($request, $topic): void {
            $reviewedTopic = TopicProposal::query()
                ->whereKey($topic->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            Gate::forUser($request->user())->authorize('startLrecReview', $reviewedTopic);

            if (! $reviewedTopic->latestVersion()->exists()) {
                throw ValidationException::withMessages([
                    'status' => 'A submitted proposal version is required before LREC review can start.',
                ]);
            }

            $reviewedTopic->update(['status' => TopicProposal::STATUS_LREC_REVIEW]);
            $reviewedTopic->reviews()->create([
                'reviewer_id' => $request->user()->id,
                'decision' => TopicProposal::STATUS_LREC_REVIEW,
                'review_stage' => 'lrec',
            ]);
        });

        $topic->user()->firstOrFail()->notify(new ProposalActivityNotification(
            'LREC review started',
            'The research office is recording the LREC outcome for “'.$topic->title.'”. Any revisions will be shared in a Comment Response paper.',
            route('topics.show', $topic),
            'info',
            $topic->id,
            workspace: User::WORKSPACE_FACULTY,
            sidebarArea: ProposalActivityNotification::SIDEBAR_AREA_SUBMITTED_PROPOSALS,
        ));

        return redirect()->to(route('topics.show', $topic).'#proposal-review')
            ->with('success', 'LREC review is open. Record the committee comments below.')
            ->with('topic_tab', 'review');
    }

    public function store(StoreResearchOfficeLrecFeedbackRequest $request, TopicProposal $topic): RedirectResponse
    {
        $comments = array_values($request->validated('committee_comments'));

        DB::transaction(function () use ($request, $topic, $comments): void {
            $reviewedTopic = TopicProposal::query()
                ->whereKey($topic->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            Gate::forUser($request->user())->authorize('recordLrecFeedback', $reviewedTopic);

            if (! $reviewedTopic->latestVersion()->exists()) {
                throw ValidationException::withMessages([
                    'committee_comments' => 'A submitted proposal version is required before LREC feedback can be recorded.',
                ]);
            }

            $reviewedTopic->update([
                'status' => 'revision_requested',
                'lrec_cleared_at' => null,
            ]);

            $reviewedTopic->reviews()->create([
                'reviewer_id' => $request->user()->id,
                'decision' => 'revision_requested',
                'review_stage' => 'lrec',
                'committee_comments' => $comments,
            ]);
        });

        $topic->user()->firstOrFail()->notify(new ProposalActivityNotification(
            'LREC revision requested',
            'The LREC committee requested changes to “'.$topic->title.'”. Open the Comment Response paper, respond to each comment, and submit a revised version.',
            route('faculty.topics.revision', $topic),
            'warning',
            $topic->id,
            workspace: User::WORKSPACE_FACULTY,
            sidebarArea: ProposalActivityNotification::SIDEBAR_AREA_SUBMITTED_PROPOSALS,
        ));

        return redirect()->to(route('topics.show', $topic).'#proposal-review')
            ->with('success', 'LREC comments were sent to the faculty member and added to the Comment Response paper.')
            ->with('topic_tab', 'review');
    }
}
