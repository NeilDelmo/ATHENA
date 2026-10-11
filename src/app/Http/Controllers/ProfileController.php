<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCollegeRequest;
use App\Http\Requests\UpdateContactNumberRequest;
use App\Http\Requests\UpdateProfileDetailsRequest;
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

        $user->load('researcherProfile');

        return view('profile.edit', [
            'user' => $user,
        ]);
    }

    public function updateCollege(UpdateCollegeRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        return back()->with('status', 'college-updated');
    }

    public function updateDetails(UpdateProfileDetailsRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        return back()->with('status', 'profile-details-updated');
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
