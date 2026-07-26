<?php

namespace App\Http\Controllers;

use App\Actions\AcceptProposalWorkspaceInvitation;
use App\Models\ProposalDraftMember;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'notifications' => $request->user()->notifications()
                ->latest()
                ->limit(15)
                ->get()
                ->map(fn ($notification) => [
                    'id' => $notification->id,
                    'data' => $notification->data,
                    'read_at' => $notification->read_at?->toIso8601String(),
                    'created_at' => $notification->created_at->diffForHumans(),
                ]),
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function markRead(Request $request, string $notification): JsonResponse
    {
        $storedNotification = $request->user()->notifications()->findOrFail($notification);
        $storedNotification->markAsRead();

        return response()->json(['read' => true]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json(['read' => true]);
    }

    public function acceptProposalInvitation(
        Request $request,
        ProposalDraftMember $proposalDraftMember,
        AcceptProposalWorkspaceInvitation $acceptInvitation,
    ): JsonResponse {
        Gate::authorize('accept', $proposalDraftMember);

        $proposalDraft = $acceptInvitation->handle($request->user(), $proposalDraftMember);

        if (! $request->user()->isUsingWorkspace([
            User::WORKSPACE_FACULTY,
            User::WORKSPACE_FACULTY_RESEARCHER,
        ])) {
            $request->session()->put(User::ACTIVE_WORKSPACE_SESSION_KEY, User::WORKSPACE_FACULTY);
        }

        return response()->json([
            'accepted' => true,
            'url' => route('faculty.proposal-drafts.show', $proposalDraft),
        ]);
    }
}
