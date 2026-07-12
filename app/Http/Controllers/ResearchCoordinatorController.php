<?php

namespace App\Http\Controllers;

use App\Models\ProposalTemplate;
use App\Models\ResearchCall;
use App\Models\TopicProposal;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ResearchCoordinatorController extends Controller
{
    public function index(Request $request)
    {
        $college = $request->user()->college;
        $topics = TopicProposal::with(['user', 'researchCall', 'category', 'latestVersion'])
            ->whereHas('user', fn ($query) => $query->where('college', $college))
            ->latest()
            ->get();

        return view('research_coordinator.dashboard', [
            'college' => $college,
            'topics' => $topics,
            'activeResearchers' => $topics->whereNotIn('status', ['rejected'])->pluck('user_id')->unique()->count(),
            'templates' => ProposalTemplate::active()->orderBy('name')->get(),
            'calls' => ResearchCall::where('status', 'open')->latest('opens_at')->take(3)->get(),
        ]);
    }

    public function review(Request $request, TopicProposal $topic)
    {
        abort_unless($topic->user()->where('college', $request->user()->college)->exists(), 403);
        $data = $request->validate([
            'action' => ['required', Rule::in(['comment', 'flag', 'forward'])],
            'comment' => ['required', 'string', 'max:5000'],
        ]);

        $topic->reviews()->create([
            'reviewer_id' => $request->user()->id,
            'decision' => 'coordinator_'.$data['action'],
            'comment' => $data['comment'],
        ]);

        return back()->with('success', $data['action'] === 'forward'
            ? 'Submission forwarded to the Research Head for approval.'
            : 'Coordinator review saved.');
    }
}
