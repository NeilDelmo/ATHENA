<?php

namespace App\Http\Controllers;

use App\Actions\AcceptProposalWorkspaceInvitation;
use App\Models\ProposalDraftMember;
use App\Models\User;
use App\Services\FacultyProjectCapacityService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class NotificationController extends Controller
{
    private const INBOX_CATEGORIES = [
        'invitations' => [
            'label' => 'Invitations',
            'description' => 'Requests to join a proposal workspace.',
        ],
        'collaboration' => [
            'label' => 'Project team',
            'description' => 'Accepted invitations and project team activity.',
        ],
        'reviews' => [
            'label' => 'Reviews',
            'description' => 'Proposal submissions, revisions, and review decisions.',
        ],
        'research_calls' => [
            'label' => 'Research calls',
            'description' => 'Openings, updates, and deadline reminders.',
        ],
        'projects' => [
            'label' => 'Projects',
            'description' => 'Monitoring, reports, and implementation activity.',
        ],
        'general' => [
            'label' => 'General',
            'description' => 'Other ATHENA updates.',
        ],
    ];

    public function index(Request $request): JsonResponse|View
    {
        $notifications = $request->user()->visibleNotifications();
        $notificationItems = $notifications->map(fn (DatabaseNotification $notification): array => $this->presentNotification($notification));
        $unreadCount = $notifications->whereNull('read_at')->count();

        if ($request->expectsJson()) {
            return response()->json([
                'notifications' => $notificationItems->take(15),
                'unread_count' => $unreadCount,
            ]);
        }

        $categoryCounts = $notificationItems->countBy('category');
        $categories = collect(self::INBOX_CATEGORIES)
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
        ]);
    }

    public function markRead(Request $request, string $notification): JsonResponse
    {
        $storedNotification = $request->user()->visibleNotifications()->firstWhere('id', $notification);
        abort_unless($storedNotification, 404);

        $storedNotification->markAsRead();

        return response()->json(['read' => true]);
    }

    public function markAllRead(Request $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $unreadNotifications = $user->visibleNotifications()->whereNull('read_at');
        $unreadNotifications
            ->each(fn (DatabaseNotification $notification) => $notification->markAsRead());

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
        $storedNotification = $request->user()->visibleNotifications()->firstWhere('id', $notification);
        abort_unless($storedNotification, 404);
        $storedNotification->markAsRead();

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

    /**
     * @return array<string, mixed>
     */
    private function presentNotification(DatabaseNotification $notification): array
    {
        $category = $this->notificationCategory($notification);
        $data = $notification->data;

        return [
            'id' => $notification->id,
            'data' => $data,
            'read_at' => $notification->read_at?->toIso8601String(),
            'created_at' => $notification->created_at->diffForHumans(),
            'created_at_iso' => $notification->created_at->toIso8601String(),
            'category' => $category,
            'category_label' => self::INBOX_CATEGORIES[$category]['label'],
            'search_text' => Str::lower(implode(' ', [
                (string) ($data['title'] ?? ''),
                (string) ($data['message'] ?? ''),
                self::INBOX_CATEGORIES[$category]['label'],
            ])),
        ];
    }

    private function notificationCategory(DatabaseNotification $notification): string
    {
        $data = $notification->data;
        $title = Str::lower((string) ($data['title'] ?? ''));
        $sidebarArea = $data['sidebar_area'] ?? null;

        if (isset($data['research_call_id']) || Str::contains($title, 'research call')) {
            return 'research_calls';
        }

        $isInvitation = Str::contains($title, ['invitation', 'invited']);
        $isCompletedInvitation = (bool) ($data['action_completed'] ?? false)
            || Str::contains($title, ['accepted invitation', 'invitation accepted']);

        if ($isInvitation && ! $isCompletedInvitation) {
            return 'invitations';
        }

        if ($isCompletedInvitation) {
            return 'collaboration';
        }

        if ($sidebarArea === 'proposal_submissions' || Str::contains($title, [
            'review',
            'revision',
            'proposal submitted',
            'proposal approved',
            'proposal rejected',
            'screening',
        ])) {
            return 'reviews';
        }

        if ($sidebarArea === 'proposal_workspace') {
            return 'collaboration';
        }

        if (in_array($sidebarArea, ['project_monitoring', 'my_projects'], true) || Str::contains($title, [
            'project',
            'monitoring',
            'progress report',
            'narrative report',
            'notice to proceed',
        ])) {
            return 'projects';
        }

        return 'general';
    }

    private function safeNotificationUrl(mixed $url): string
    {
        if (! is_string($url) || $url === '') {
            return route('notifications.index');
        }

        $applicationUrl = rtrim(url('/'), '/');

        if (Str::startsWith($url, '/') || $url === $applicationUrl || Str::startsWith($url, $applicationUrl.'/')) {
            return $url;
        }

        return route('notifications.index');
    }
}
