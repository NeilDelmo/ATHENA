<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\SidebarAttentionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SidebarAttentionController extends Controller
{
    public function __construct(private readonly SidebarAttentionService $sidebarAttention) {}

    public function open(Request $request, string $area): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->sidebarAttention->canOpen($user, $area), 404);
        $this->sidebarAttention->markAsRead($user, $area);
        if ($this->sidebarAttention->switchesToResearcherWorkspace($area)) {
            $request->session()->put(User::ACTIVE_WORKSPACE_SESSION_KEY, User::WORKSPACE_FACULTY_RESEARCHER);
        }

        return to_route($this->sidebarAttention->routeNameFor($area));
    }
}
