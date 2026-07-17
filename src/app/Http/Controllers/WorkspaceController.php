<?php

namespace App\Http\Controllers;

use App\Http\Requests\SelectWorkspaceRequest;
use App\Models\User;
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

    public function store(SelectWorkspaceRequest $request): RedirectResponse
    {
        $workspace = $request->validated('workspace');
        $request->session()->put(User::ACTIVE_WORKSPACE_SESSION_KEY, $workspace);
        $request->session()->forget('url.intended');

        return redirect()
            ->route($request->user()->dashboardRouteName($workspace))
            ->with('status', 'You are now using the '.$request->user()->activeWorkspaceLabel().' workspace.');
    }
}
