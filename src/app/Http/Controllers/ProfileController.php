<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCollegeRequest;
use App\Http\Requests\UpdateContactNumberRequest;
use Illuminate\Http\RedirectResponse;
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

    public function updateCollege(UpdateCollegeRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        return back()->with('status', 'college-updated');
    }

    public function updateContactNumber(UpdateContactNumberRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $request->user()->update([
            'contact_number' => $validated['contact_number'] ?? null,
        ]);

        return back()->with('status', 'contact-number-updated');
    }
}
