<?php

namespace App\Http\Controllers;

use App\Actions\AcceptProposalWorkspaceInvitation;
use App\Models\ProposalDraft;
use App\Models\ProposalDraftMember;
use App\Models\User;
use App\Notifications\ProposalActivityNotification;
use App\Services\FacultyProjectCapacityService;
use App\Services\NotificationInbox;
use App\Services\WorkspaceActionItems;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Throwable;

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

        if ($proposalDraftMember->isAccepted()) {
            if ($request->user()->isUsingWorkspace(User::WORKSPACE_FACULTY)) {
                return redirect()->route('faculty.proposal-drafts.show', $proposalDraftMember->draft);
            }

            return redirect()->route($request->user()->dashboardRouteName() ?? 'workspace.select')
                ->with('status', 'This invitation is already accepted. Switch to the Faculty workspace to edit the shared proposal.');
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

        $usingFaculty = $request->user()->isUsingWorkspace(User::WORKSPACE_FACULTY);
        $url = $usingFaculty
            ? route('faculty.proposal-drafts.show', $proposalDraft)
            : route($request->user()->dashboardRouteName() ?? 'workspace.select');
        $message = $usingFaculty
            ? 'Invitation accepted. You can now edit the shared proposal.'
            : 'Invitation accepted. Switch to the Faculty workspace to edit the shared proposal.';
        $request->session()->flash('status', $message);

        if (! $request->expectsJson()) {
            return redirect()->to($url)->with('workload_warning', $workloadWarning);
        }

        return response()->json([
            'accepted' => true,
            'url' => $url,
            'message' => $message,
            'workload_warning' => $workloadWarning,
        ]);
    }

    public function declineProposalInvitation(Request $request, ProposalDraftMember $proposalDraftMember): JsonResponse|RedirectResponse
    {
        $request->validate(['notification_id' => ['nullable', 'uuid']]);
        Gate::authorize('decline', $proposalDraftMember);

        $draft = DB::transaction(function () use ($proposalDraftMember): ProposalDraft {
            $draft = $proposalDraftMember->draft()->lockForUpdate()->firstOrFail();
            $membership = $draft->members()->lockForUpdate()->findOrFail($proposalDraftMember->getKey());
            Gate::authorize('decline', $membership);
            $membership->delete();

            return $draft;
        }, 3);
        $this->completeInvitationNotification($request, $proposalDraftMember, declined: true);

        try {
            $draft->owner->notify(new ProposalActivityNotification(
                title: 'Project staff invitation declined',
                message: $request->user()->name.' declined the project staff invitation for “'.$draft->project_title.'”.',
                url: route('faculty.proposal-drafts.show', $draft),
                sidebarArea: ProposalActivityNotification::SIDEBAR_AREA_PROPOSAL_WORKSPACE,
            ));
        } catch (Throwable $exception) {
            report($exception);
        }

        $url = route($request->user()->dashboardRouteName() ?? 'workspace.select');
        $request->session()->flash('status', 'Invitation declined.');

        return $request->expectsJson()
            ? response()->json(['declined' => true, 'url' => $url, 'message' => 'Invitation declined.'])
            : redirect()->to($url);
    }

    private function completeInvitationNotification(Request $request, ProposalDraftMember $proposalDraftMember, bool $declined = false): void
    {
        $notificationId = $request->string('notification_id')->toString();
        $expectedActionUrl = route('notifications.proposal-invitations.accept', $proposalDraftMember);
        foreach ($request->user()->notifications()->where('data->action_url', $expectedActionUrl)
            ->when($notificationId !== '' && ! $declined, fn ($query) => $query->whereKey($notificationId))->get() as $notification) {
            $data = $notification->data;
            unset($data['action_url'], $data['action_data']);
            $data['action_completed'] = true;
            if ($declined) {
                $data['url'] = route('notifications.index');
            }
            $notification->forceFill(['data' => $data, 'read_at' => now()])->save();
        }
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
