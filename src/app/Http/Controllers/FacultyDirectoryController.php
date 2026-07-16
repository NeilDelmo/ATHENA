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
            ->when($selectedCollege !== 'all', fn ($query) => $query->where('college', User::COLLEGES[$selectedCollege]))
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            }))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('research_head.faculty-directory', [
            'colleges' => User::COLLEGES,
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
