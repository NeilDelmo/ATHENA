<?php

namespace App\Http\Controllers;

use App\Actions\AcceptProposalWorkspaceInvitation;
use App\Models\ProposalDraftMember;
use App\Models\User;
use App\Services\FacultyProjectCapacityService;
use App\Services\NotificationInbox;
use App\Services\WorkspaceActionItems;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class NotificationController extends Controller
{
    public function index(Request $request, WorkspaceActionItems $actionItems, NotificationInbox $inbox): JsonResponse|View
    {
        $pendingActions = $actionItems->for($request->user());
        $request->attributes->set('athena.workspace_actions', $pendingActions);
        $notificationItems = $inbox->for($request->user(), $pendingActions, recentOnly: $request->expectsJson());
        $unreadCount = $request->user()->visibleNotificationsQuery()->whereNull('read_at')->count();
        $request->attributes->set('athena.unread_notification_count', $unreadCount);
        if ($request->expectsJson()) {
            return response()->json([
                'notifications' => $notificationItems->take(15)->map(fn (array $item): array => Arr::except($item, 'history')),
                'unread_count' => $unreadCount,
                'pending_actions' => $pendingActions,
                'workspace' => $request->user()->activeWorkspace(),
            ])->header('Cache-Control', 'private, no-store');
        }

        $categoryCounts = $notificationItems->countBy('category');
        $categories = collect(NotificationInbox::CATEGORIES)
            ->map(fn (array $definition, string $key): array => [
                'key' => $key,
                ...$definition,
                'count' => $categoryCounts->get($key, 0),
            ])
            ->values();

        return view('notifications.index', [
            'notificationItems' => $notificationItems,
            'unreadCount' => $unreadCount,
            'categories' => $categories,
            'pendingActions' => $pendingActions,
        ]);
    }

    public function markRead(Request $request, string $notification): JsonResponse
    {
        $storedNotification = $request->user()->visibleNotificationsQuery()->find($notification);
        abort_unless($storedNotification, 404);

        $this->markVisibleGroupRead($request, $storedNotification);

        return response()->json([
            'read' => true,
            'unread_count' => $request->user()->visibleNotificationsQuery()->whereNull('read_at')->count(),
        ]);
    }

    public function markAllRead(Request $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $user->visibleNotificationsQuery()->whereNull('read_at')->update(['read_at' => now()]);

        if (! $request->expectsJson()) {
            return redirect()->route('notifications.index');
        }

        return response()->json([
            'read' => true,
            'unread_count' => 0,
            'preserved_ids' => [],
        ]);
    }

    public function open(Request $request, string $notification): RedirectResponse
    {
        $storedNotification = $request->user()->visibleNotificationsQuery()->find($notification);
        abort_unless($storedNotification, 404);
        $this->markVisibleGroupRead($request, $storedNotification);

        return redirect()->to($this->safeNotificationUrl($storedNotification->data['url'] ?? null));
    }

    public function showProposalInvitation(
        Request $request,
        ProposalDraftMember $proposalDraftMember,
        FacultyProjectCapacityService $capacityService,
    ): View|RedirectResponse {
        Gate::authorize('review', $proposalDraftMember);

        $this->activateFacultyWorkspace($request);

        if ($proposalDraftMember->isAccepted()) {
            return redirect()->route('faculty.proposal-drafts.show', $proposalDraftMember->draft);
        }

        $proposalDraftMember->loadMissing('draft.owner:id,name', 'draft.researchCall');

        return view('notifications.proposal-invitation', [
            'proposalDraftMember' => $proposalDraftMember,
            'workloadWarning' => $capacityService->warningForAdditionalParticipation(
                $request->user(),
                $proposalDraftMember->draft->researchCall,
            ),
        ]);
    }

    public function acceptProposalInvitation(
        Request $request,
        ProposalDraftMember $proposalDraftMember,
        AcceptProposalWorkspaceInvitation $acceptInvitation,
        FacultyProjectCapacityService $capacityService,
    ): JsonResponse|RedirectResponse {
        $request->validate([
            'notification_id' => ['nullable', 'uuid'],
        ]);

        Gate::authorize('accept', $proposalDraftMember);

        $proposalDraft = $acceptInvitation->handle($request->user(), $proposalDraftMember);
        $workloadWarning = $capacityService->warningForAdditionalParticipation(
            $request->user(),
            $proposalDraft->researchCall,
        );
        $this->completeInvitationNotification($request, $proposalDraftMember);

        $this->activateFacultyWorkspace($request);

        $url = route('faculty.proposal-drafts.show', $proposalDraft);

        if (! $request->expectsJson()) {
            return redirect()->to($url)->with('workload_warning', $workloadWarning);
        }

        return response()->json([
            'accepted' => true,
            'url' => $url,
            'workload_warning' => $workloadWarning,
        ]);
    }

    private function activateFacultyWorkspace(Request $request): void
    {
        if (! $request->user()->isUsingWorkspace([
            User::WORKSPACE_FACULTY,
            User::WORKSPACE_FACULTY_RESEARCHER,
        ])) {
            $request->session()->put(User::ACTIVE_WORKSPACE_SESSION_KEY, User::WORKSPACE_FACULTY);
        }
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

    private function safeNotificationUrl(mixed $url): string
    {
        if (! is_string($url) || $url === '' || preg_match('/[\x00-\x20\x7f\\\\]/', $url)) {
            return route('notifications.index');
        }

        $applicationUrl = rtrim(url('/'), '/');

        if ((Str::startsWith($url, '/') && ! Str::startsWith($url, '//')) || $url === $applicationUrl || Str::startsWith($url, $applicationUrl.'/')) {
            return $url;
        }

        return route('notifications.index');
    }

    private function markVisibleGroupRead(Request $request, DatabaseNotification $notification): void
    {
        $validated = $request->validate([
            'notification_ids' => ['sometimes', 'array', 'min:1', 'max:50'],
            'notification_ids.*' => ['required', 'uuid', 'distinct'],
        ]);
        $inbox = app(NotificationInbox::class);
        $groupKey = $inbox->groupKey($notification);
        $ids = $validated['notification_ids'] ?? [$notification->id];

        $request->user()->visibleNotificationsQuery()->whereIn('id', $ids)->get()
            ->filter(fn (DatabaseNotification $candidate): bool => $inbox->groupKey($candidate) === $groupKey)
            ->each(fn (DatabaseNotification $candidate) => $candidate->markAsRead());
    }
}
