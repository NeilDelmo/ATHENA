<?php

namespace App\Http\Controllers;

use App\Http\Requests\SelectActiveRoleRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RoleSelectionController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if (! $user->hasRole('research_coordinator') || ! $user->hasAnyRole(['faculty', 'faculty_researcher'])) {
            return redirect()->route('dashboard');
        }

        return view('auth.select-role', ['user' => $user]);
    }

    public function store(SelectActiveRoleRequest $request): RedirectResponse
    {
        $activeRole = $request->validated('role');

        $request->session()->put('active_role', $activeRole);

        return redirect()->route($activeRole === 'faculty' ? 'faculty.dashboard' : 'research_coordinator.dashboard');
    }
}
