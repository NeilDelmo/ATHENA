<?php

namespace App\Http\Controllers;

use App\Http\Requests\SelectActiveRoleRequest;
use Illuminate\Http\JsonResponse;
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

    public function store(SelectActiveRoleRequest $request): JsonResponse|RedirectResponse
    {
        $activeRole = $request->validated('role');

        $request->session()->put('active_role', $activeRole);
        $request->session()->put('active_workspace', $activeRole === 'research_coordinator' ? 'research_office' : 'faculty');

        $dashboardUrl = route($activeRole === 'faculty' ? 'faculty.dashboard' : 'research_coordinator.dashboard');

        if ($request->expectsJson()) {
            return response()->json(['redirect' => $dashboardUrl]);
        }

        return redirect()->to($dashboardUrl);
    }
}
