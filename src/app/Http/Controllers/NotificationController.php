<?php

namespace App\Http\Controllers;

use App\Actions\AcceptProposalWorkspaceInvitation;
use App\Models\ProposalDraft;
use App\Models\ProposalDraftMember;
use App\Models\ProposalInvitationEmail;
use App\Models\TopicCollaborator;
use App\Models\TopicProposal;
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
use Spatie\Permission\Models\Role;
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
        int $proposalDraftMember,
        FacultyProjectCapacityService $capacityService,
    ): View|RedirectResponse {
        $member = ProposalDraftMember::query()->with('draft.owner:id,name', 'draft.researchCall')->find($proposalDraftMember);
        if ($member === null) {
            $collaborator = $this->submittedInvite($request->user(), $proposalDraftMember);
            if ($collaborator->accepted_at !== null) {
                return $this->submittedInviteDestination($request->user(), $collaborator->topic);
            }

            return view('notifications.proposal-invitation', [
                'invitationId' => $proposalDraftMember,
                'inviterName' => $collaborator->topic->user->name,
                'projectTitle' => $collaborator->topic->title,
                'submitted' => true,
                'workloadWarning' => $capacityService->warningForAdditionalParticipation(
                    $request->user(),
                    $collaborator->topic->researchCall,
                ),
            ]);
        }

        Gate::authorize('review', $member);

        if ($member->isAccepted()) {
            if ($request->user()->isUsingWorkspace(User::WORKSPACE_FACULTY)) {
                return redirect()->route('faculty.proposal-drafts.show', $member->draft);
            }

            return redirect()->route($request->user()->dashboardRouteName() ?? 'workspace.select')
                ->with('status', 'This invitation is already accepted. Switch to the Faculty workspace to edit the shared proposal.');
        }

        return view('notifications.proposal-invitation', [
            'invitationId' => $proposalDraftMember,
            'inviterName' => $member->draft->owner->name,
            'projectTitle' => $member->draft->project_title,
            'submitted' => false,
            'workloadWarning' => $capacityService->warningForAdditionalParticipation(
                $request->user(),
                $member->draft->researchCall,
            ),
        ]);
    }

    public function acceptProposalInvitation(
        Request $request,
        int $proposalDraftMember,
        AcceptProposalWorkspaceInvitation $acceptInvitation,
        FacultyProjectCapacityService $capacityService,
    ): JsonResponse|RedirectResponse {
        $request->validate([
            'notification_id' => ['nullable', 'uuid'],
        ]);

        $member = ProposalDraftMember::query()->find($proposalDraftMember);
        if ($member === null) {
            return $this->respondToSubmittedInvite($request, $proposalDraftMember, accept: true);
        }
        Gate::authorize('accept', $member);

        $proposalDraft = $acceptInvitation->handle($request->user(), $member);
        $workloadWarning = $capacityService->warningForAdditionalParticipation(
            $request->user(),
            $proposalDraft->researchCall,
        );
        $this->completeInvitationNotifications($request->user(), $proposalDraftMember);

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

    public function declineProposalInvitation(Request $request, int $proposalDraftMember): JsonResponse|RedirectResponse
    {
        $request->validate(['notification_id' => ['nullable', 'uuid']]);
        $member = ProposalDraftMember::query()->find($proposalDraftMember);
        if ($member === null) {
            return $this->respondToSubmittedInvite($request, $proposalDraftMember, accept: false);
        }
        Gate::authorize('decline', $member);

        $draft = DB::transaction(function () use ($member): ProposalDraft {
            $draft = $member->draft()->lockForUpdate()->firstOrFail();
            $membership = $draft->members()->lockForUpdate()->findOrFail($member->getKey());
            Gate::authorize('decline', $membership);
            $membership->delete();

            return $draft;
        }, 3);
        $this->completeInvitationNotifications($request->user(), $proposalDraftMember, declined: true);

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

    private function submittedInvite(User $user, int $invitationId, bool $lock = false): TopicCollaborator
    {
        $link = ProposalInvitationEmail::query()->whereNotNull('topic_collaborator_id')->findOrFail($invitationId);
        $collaborator = $link->collaborator()->with('topic.user:id,name', 'topic.researchCall')
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->firstOrFail();

        abort_unless($user->canUseWorkspace(User::WORKSPACE_FACULTY)
            && ($collaborator->user_id === $user->getKey()
                || ($collaborator->user_id === null && $user->email_verified_at !== null
                    && $collaborator->email === mb_strtolower(trim($user->email)))), 403);

        return $collaborator;
    }

    private function respondToSubmittedInvite(Request $request, int $invitationId, bool $accept): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        [$topic, $changed] = DB::transaction(function () use ($user, $invitationId, $accept): array {
            $collaborator = $this->submittedInvite($user, $invitationId, lock: true);
            $topic = $collaborator->topic;
            if ($collaborator->accepted_at !== null) {
                abort_unless($accept, 403);

                return [$topic, false];
            }

            $pendingRevisionMemberships = ProposalDraftMember::query()
                ->whereHas('draft', fn ($query) => $query->withTrashed()->where('topic_id', $topic->id))
                ->where('email', $collaborator->email)->whereNull('accepted_at');

            if ($accept) {
                $acceptance = [
                    'user_id' => $user->getKey(),
                    'name' => $user->name,
                    'accepted_at' => now(),
                ];
                $collaborator->update($acceptance);
                $pendingRevisionMemberships->update($acceptance);
                if ($topic->hasIssuedNoticeToProceed()) {
                    $user->assignRole(Role::findOrCreate(User::WORKSPACE_FACULTY_RESEARCHER, 'web'));
                }
            } else {
                $pendingRevisionMemberships->delete();
                $collaborator->delete();
            }

            return [$topic, true];
        }, 3);

        $this->completeInvitationNotifications($user, $invitationId, declined: ! $accept);
        $message = $accept ? 'Invitation accepted.' : 'Invitation declined.';
        $request->session()->flash('status', $message);
        $response = $accept
            ? $this->submittedInviteDestination($user, $topic)
            : redirect()->route($user->dashboardRouteName() ?? 'workspace.select');

        if ($changed) {
            rescue(fn () => $topic->user->notify(new ProposalActivityNotification(
                title: 'Project staff invitation '.($accept ? 'accepted' : 'declined'),
                message: $user->name.($accept ? ' accepted' : ' declined').' the project staff invitation for "'.$topic->title.'".',
                url: route('topics.show', $topic),
                topicId: $topic->id,
            )));
        }

        return $request->expectsJson()
            ? response()->json([($accept ? 'accepted' : 'declined') => true, 'url' => $response->getTargetUrl(), 'message' => $message])
            : $response;
    }

    private function submittedInviteDestination(User $user, TopicProposal $topic): RedirectResponse
    {
        if (Gate::forUser($user)->allows('view', $topic)) {
            return redirect()->route('topics.show', $topic);
        }

        return redirect()->route($user->dashboardRouteName() ?? 'workspace.select')
            ->with('status', 'Switch to the Faculty workspace to view the submitted proposal.');
    }

    private function completeInvitationNotifications(User $user, int $invitationId, bool $declined = false): void
    {
        $expectedActionUrl = route('notifications.proposal-invitations.accept', $invitationId);
        foreach ($user->notifications()->where('data->action_url', $expectedActionUrl)->get() as $notification) {
            $data = $notification->data;
            unset($data['action_url'], $data['action_data']);
            $data['action_completed'] = true;
            if ($declined) {
                $data['url'] = route('notifications.index');
            }
            $notification->forceFill(['data' => $data, 'read_at' => $notification->read_at ?? now()])->save();
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
