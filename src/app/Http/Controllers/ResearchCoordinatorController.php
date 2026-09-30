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
        $lrecProposals = TopicProposal::query()
            ->with(['user:id,name,college', 'latestVersion'])
            ->where('review_stage', 'lrec')
            ->whereIn('status', [
                TopicProposal::STATUS_LREC_QUEUED,
                TopicProposal::STATUS_LREC_REVIEW,
                'revision_requested',
                'resubmitted',
                'pending',
                'expert_review',
                'for_final_decision',
            ])
            ->whereHas('user', fn (Builder $query) => $query->where('college', $coordinator->college))
            ->when(blank($coordinator->college), fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->latest()
            ->paginate(12);

        return view('research_coordinator.dashboard', [
            'coordinator' => $coordinator,
            'memberCount' => $memberCount,
            'lrecProposals' => $lrecProposals,
        ]);
    }

    public function members(Request $request): View
    {
        $coordinator = $request->user();

        $members = $this->membersQuery($coordinator)->paginate(20);

        return view('research_coordinator.faculty-members', [
            'coordinator' => $coordinator,
            'members' => $members,
            'memberCount' => $members->total(),
        ]);
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
