<?php

namespace App\Http\Controllers;

use App\Http\Requests\SelectWorkspaceRequest;
use App\Support\WorkspaceLocation;
use App\Support\WorkspacePreference;
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
                WorkspacePreference::select($request, $workspace, persist: true);
            }

            return redirect()->route('dashboard');
        }

        return view('auth.select-workspace', compact('workspaces'));
    }

    public function store(SelectWorkspaceRequest $request): JsonResponse|RedirectResponse
    {
        $workspace = $request->validated('workspace');
        WorkspacePreference::select($request, $workspace, persist: true);
        $request->session()->forget('url.intended');
        $request->session()->flash('status', 'You are now using the '.$request->user()->activeWorkspaceLabel().' workspace.');

        $dashboardUrl = WorkspaceLocation::destination($request, $workspace, $request->validated('resume_url'));

        if ($request->expectsJson()) {
            return response()->json(['redirect' => $dashboardUrl]);
        }

        return redirect()->to($dashboardUrl);
    }
}
