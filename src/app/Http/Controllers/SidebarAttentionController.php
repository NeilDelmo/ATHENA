<?php

namespace App\Http\Controllers;

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
        if (! $this->sidebarAttention->requiresCompletedReview($area)) {
            $this->sidebarAttention->markAsRead($user, $area);
        }

        return to_route($this->sidebarAttention->routeNameFor($area));
    }
}
