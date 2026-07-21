<?php

namespace App\Http\Controllers;

use App\Actions\UpdateResearchCoordinatorAction;
use App\Http\Requests\UpdateResearchCoordinatorRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class FacultyDirectoryController extends Controller
{
    public function index(Request $request): View
    {
        $selectedCollege = $request->string('college')->upper()->toString();

        if (! array_key_exists($selectedCollege, User::COLLEGES)) {
            $selectedCollege = 'all';
        }

        $search = Str::of($request->string('search')->toString())
            ->squish()
            ->limit(100)
            ->toString();

        $members = User::query()
            ->select(['id', 'name', 'email', 'avatar', 'college'])
            ->with('roles:id,name')
            ->orderBy('name')
            ->get();

        $memberFilters = $members->map(fn (User $member): array => [
            'id' => $member->getKey(),
            'college' => array_search($member->college, User::COLLEGES, true) ?: '',
            'search' => Str::lower($member->name.' '.$member->email),
        ])->values();

        $coordinatorsByCollege = $members
            ->filter(fn (User $member): bool => filled($member->college) && $member->hasRole('research_coordinator'))
            ->keyBy('college')
            ->map(fn (User $member): array => [
                'id' => $member->getKey(),
                'name' => $member->name,
            ]);

        return view('research_head.faculty-directory', [
            'colleges' => User::COLLEGES,
            'coordinatorsByCollege' => $coordinatorsByCollege,
            'memberFilters' => $memberFilters,
            'members' => $members,
            'search' => $search,
            'selectedCollege' => $selectedCollege,
        ]);
    }

    public function updateCoordinator(UpdateResearchCoordinatorRequest $request, User $member, UpdateResearchCoordinatorAction $updateCoordinator): RedirectResponse
    {
        $action = $request->validated('action');

        $updateCoordinator->handle($member, $action);

        if ($action === 'assign') {
            $message = "{$member->name} is now a Research Coordinator.";
        } else {
            $message = "{$member->name} is no longer a Research Coordinator.";
        }

        return back()->with('status', $message);
    }
}
