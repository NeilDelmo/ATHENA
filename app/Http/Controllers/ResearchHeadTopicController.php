<?php

namespace App\Http\Controllers;

use App\Models\TopicProposal;
use Illuminate\Http\Request;

class ResearchHeadTopicController extends Controller
{
    public function index()
    {
        $topics = TopicProposal::with('user')
            ->latest()
            ->get();

        return view('research_head.dashboard', compact('topics')); 
    }

    public function updateStatus(Request $request, TopicProposal $topic)
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,approved,rejected',
        ]);

        $topic->update($validated);

        return redirect()->route('research_head.dashboard')->with('success', 'Proposal status updated.');
    }
}
