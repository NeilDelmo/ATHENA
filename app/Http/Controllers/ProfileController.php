<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        $user = $request->user();

        $user->loadCount([
            'proposals',
            'proposals as approved_proposals_count' => fn ($query) => $query->where('status', 'approved'),
            'proposals as active_proposals_count' => fn ($query) => $query->whereNotIn('status', ['approved', 'rejected']),
            'topicReviews',
            'expertAssignments',
        ]);

        $recentProposals = $user->proposals()
            ->with(['researchCall', 'category'])
            ->latest()
            ->limit(3)
            ->get();

        return view('profile.edit', [
            'user' => $user,
            'recentProposals' => $recentProposals,
        ]);
    }
}
