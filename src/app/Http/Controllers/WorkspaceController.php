<?php

namespace App\Http\Controllers;

use App\Http\Requests\SelectWorkspaceRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkspaceController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $workspaces = $user->availableWorkspaces();

        if (count($workspaces) <= 1) {
            if ($workspace = array_key_first($workspaces)) {
                $request->session()->put(User::ACTIVE_WORKSPACE_SESSION_KEY, $workspace);
            }

            return redirect()->route('dashboard');
        }

        return view('auth.select-workspace', compact('workspaces'));
    }

    public function store(SelectWorkspaceRequest $request): JsonResponse|RedirectResponse
    {
        $workspace = $request->validated('workspace');
        $request->session()->put(User::ACTIVE_WORKSPACE_SESSION_KEY, $workspace);
        if ($request->user()->hasRole('research_coordinator')
            && $request->user()->hasAnyRole(['faculty', 'faculty_researcher'])
            && in_array($workspace, [User::WORKSPACE_RESEARCH_OFFICE, User::WORKSPACE_FACULTY, User::WORKSPACE_FACULTY_RESEARCHER], true)) {
            $request->session()->put('active_role', $workspace === User::WORKSPACE_RESEARCH_OFFICE ? 'research_coordinator' : 'faculty');
        }
        $request->session()->forget('url.intended');
        $request->session()->flash('status', 'You are now using the '.$request->user()->activeWorkspaceLabel().' workspace.');

        $dashboardUrl = route($request->user()->dashboardRouteName($workspace));

        if ($request->expectsJson()) {
            return response()->json(['redirect' => $dashboardUrl]);
        }

        return redirect()->to($dashboardUrl);
    }
}
