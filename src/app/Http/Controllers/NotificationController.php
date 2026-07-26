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
        $notifications = $request->user()->visibleNotifications();

        return response()->json([
            'notifications' => $notifications
                ->take(15)
                ->map(fn ($notification) => [
                    'id' => $notification->id,
                    'data' => $notification->data,
                    'read_at' => $notification->read_at?->toIso8601String(),
                    'created_at' => $notification->created_at->diffForHumans(),
                ]),
            'unread_count' => $notifications->whereNull('read_at')->count(),
        ]);
    }

    public function markRead(Request $request, string $notification): JsonResponse
    {
        $storedNotification = $request->user()->visibleNotifications()->firstWhere('id', $notification);
        abort_unless($storedNotification, 404);
        $storedNotification->markAsRead();

        return response()->json(['read' => true]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()
            ->visibleNotifications()
            ->whereNull('read_at')
            ->each(fn ($notification) => $notification->markAsRead());

        return response()->json(['read' => true]);
    }

    public function acceptProposalInvitation(
        Request $request,
        ProposalDraftMember $proposalDraftMember,
        AcceptProposalWorkspaceInvitation $acceptInvitation,
    ): JsonResponse {
        $request->validate([
            'notification_id' => ['nullable', 'uuid'],
        ]);

        Gate::authorize('accept', $proposalDraftMember);

        $proposalDraft = $acceptInvitation->handle($request->user(), $proposalDraftMember);
        $this->completeInvitationNotification($request, $proposalDraftMember);

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

    private function completeInvitationNotification(Request $request, ProposalDraftMember $proposalDraftMember): void
    {
        $notificationId = $request->string('notification_id')->toString();

        if ($notificationId === '') {
            return;
        }

        $notification = $request->user()->notifications()->find($notificationId);

        if (! $notification) {
            return;
        }

        $expectedActionUrl = route('notifications.proposal-invitations.accept', $proposalDraftMember);
        $data = $notification->data ?? [];

        if (($data['action_url'] ?? null) !== $expectedActionUrl) {
            return;
        }

        unset($data['action_url'], $data['action_data']);
        $data['action_completed'] = true;

        $notification->forceFill([
            'data' => $data,
            'read_at' => now(),
        ])->save();
    }
}
