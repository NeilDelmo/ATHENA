<?php

namespace App\Http\Controllers;

use App\Models\TopicProposal;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ResearchCoordinatorController extends Controller
{
    public function index(Request $request): View
    {
        $coordinator = $request->user();
        $memberCount = $this->membersQuery($coordinator)->count();
        $lrecQuery = $this->lrecProposalsQuery($coordinator);
        $lrecReviewCount = (clone $lrecQuery)->where('status', TopicProposal::STATUS_LREC_REVIEW)->count();
        $lrecProposals = $lrecQuery->paginate(12);

        return view('research_coordinator.dashboard', [
            'coordinator' => $coordinator,
            'memberCount' => $memberCount,
            'lrecProposals' => $lrecProposals,
            'lrecReviewCount' => $lrecReviewCount,
        ]);
    }

    public function proposals(Request $request): View
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:200'], 'status' => ['nullable', 'in:review,queued,revision']]);
        $coordinator = $request->user();
        $query = $this->lrecProposalsQuery($coordinator);
        if (filled($filters['status'] ?? null)) {
            $query->where('status', match ($filters['status']) {
                'review' => TopicProposal::STATUS_LREC_REVIEW,
                'queued' => TopicProposal::STATUS_LREC_QUEUED,
                'revision' => 'revision_requested',
            });
        }
        if (filled($filters['q'] ?? null)) {
            $search = '%'.trim($filters['q']).'%';
            $query->where(fn (Builder $query): Builder => $query->where('title', 'like', $search)
                ->orWhereHas('user', fn (Builder $user): Builder => $user->where('name', 'like', $search)));
        }

        return view('research_coordinator.proposals', ['coordinator' => $coordinator, 'lrecProposals' => $query->paginate(12)->withQueryString()]);
    }

    public function members(Request $request): View
    {
        $coordinator = $request->user();

        $filters = $request->validate(['q' => ['nullable', 'string', 'max:200']]);
        $query = $this->membersQuery($coordinator);
        $memberCount = (clone $query)->count();
        if (filled($filters['q'] ?? null)) {
            $search = '%'.trim($filters['q']).'%';
            $query->where(fn (Builder $query): Builder => $query->where('name', 'like', $search)->orWhere('email', 'like', $search));
        }
        $members = $query->paginate(20)->withQueryString();

        return view('research_coordinator.faculty-members', [
            'coordinator' => $coordinator,
            'members' => $members,
            'memberCount' => $memberCount,
        ]);
    }

    private function lrecProposalsQuery(User $coordinator): Builder
    {
        return TopicProposal::query()->with(['user:id,name,college', 'latestVersion'])
            ->where('review_stage', 'lrec')
            ->whereIn('status', [TopicProposal::STATUS_LREC_QUEUED, TopicProposal::STATUS_LREC_REVIEW, 'revision_requested', 'resubmitted', 'pending', 'expert_review', 'for_final_decision'])
            ->forCollege($coordinator->college)->orderByRaw('COALESCE(status_started_at, created_at)')->orderBy('id');
    }

    private function membersQuery(User $coordinator): Builder
    {
        return User::query()
            ->select(['id', 'name', 'email', 'avatar'])
            ->when(
                $coordinator->college,
                fn ($query) => $query->where('college', $coordinator->college)->whereKeyNot($coordinator->getKey()),
                fn ($query) => $query->whereKey(-1),
            )
            ->orderBy('name');
    }
}
