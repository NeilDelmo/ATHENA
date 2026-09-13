<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\ProposalActivityNotification;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SidebarAttentionService
{
    /**
     * @return array<string, int>
     */
    public function countsFor(User $user): array
    {
        $notifications = $this->unreadVisibleNotifications($user);

        return collect($this->availableAreasFor($user))
            ->mapWithKeys(fn (string $area): array => [$area => $this->notificationsFor($user, $area, $notifications)->count()])
            ->all();
    }

    public function canOpen(User $user, string $area): bool
    {
        return in_array($area, $this->availableAreasFor($user), true);
    }

    public function markAsRead(User $user, string $area): void
    {
        $this->notificationsFor($user, $area)
            ->each(fn (DatabaseNotification $notification) => $notification->markAsRead());
    }

    public function requiresCompletedReview(string $area): bool
    {
        return in_array($area, [
            ProposalActivityNotification::SIDEBAR_AREA_PROPOSAL_SUBMISSIONS,
            ProposalActivityNotification::SIDEBAR_AREA_PROJECT_MONITORING,
        ], true);
    }

    public function notificationRequiresCompletedReview(User $user, DatabaseNotification $notification): bool
    {
        $area = $this->areaFor($notification);

        return $user->activeWorkspace() === User::WORKSPACE_RESEARCH_HEAD
            && $area !== null
            && $this->requiresCompletedReview($area);
    }

    public function markTopicAsRead(User $user, string $area, int $topicId): void
    {
        $this->notificationsFor($user, $area)
            ->filter(fn (DatabaseNotification $notification): bool => (int) data_get($notification->data, 'topic_id') === $topicId)
            ->each(fn (DatabaseNotification $notification) => $notification->markAsRead());
    }

    /**
     * @return list<int>
     */
    public function unreadTopicIdsFor(User $user, string $area): array
    {
        return $this->notificationsFor($user, $area)
            ->map(fn (DatabaseNotification $notification): int => (int) data_get($notification->data, 'topic_id'))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function routeNameFor(string $area): string
    {
        return match ($area) {
            ProposalActivityNotification::SIDEBAR_AREA_PROPOSAL_SUBMISSIONS => 'research_head.proposal-submissions.index',
            ProposalActivityNotification::SIDEBAR_AREA_PROJECT_MONITORING => 'research_head.projects.index',
            ProposalActivityNotification::SIDEBAR_AREA_PROPOSAL_WORKSPACE => 'faculty.proposal-drafts.index',
            ProposalActivityNotification::SIDEBAR_AREA_MY_PROJECTS => 'research.index',
        };
    }

    public function switchesToResearcherWorkspace(string $area): bool
    {
        return $area === ProposalActivityNotification::SIDEBAR_AREA_MY_PROJECTS;
    }

    /**
     * @return list<string>
     */
    private function availableAreasFor(User $user): array
    {
        return match ($user->activeWorkspace()) {
            User::WORKSPACE_RESEARCH_HEAD => [
                ProposalActivityNotification::SIDEBAR_AREA_PROPOSAL_SUBMISSIONS,
                ProposalActivityNotification::SIDEBAR_AREA_PROJECT_MONITORING,
            ],
            User::WORKSPACE_FACULTY => array_values(array_filter([
                ProposalActivityNotification::SIDEBAR_AREA_PROPOSAL_WORKSPACE,
                $user->canUseWorkspace(User::WORKSPACE_FACULTY_RESEARCHER)
                    ? ProposalActivityNotification::SIDEBAR_AREA_MY_PROJECTS
                    : null,
            ])),
            User::WORKSPACE_FACULTY_RESEARCHER => [
                ProposalActivityNotification::SIDEBAR_AREA_MY_PROJECTS,
            ],
            default => [],
        };
    }

    /**
     * @return Collection<int, DatabaseNotification>
     */
    private function notificationsFor(User $user, string $area, ?Collection $notifications = null): Collection
    {
        return ($notifications ?? $this->unreadVisibleNotifications($user))
            ->filter(fn (DatabaseNotification $notification): bool => $this->areaFor($notification) === $area)
            ->values();
    }

    /**
     * @return Collection<int, DatabaseNotification>
     */
    private function unreadVisibleNotifications(User $user): Collection
    {
        return $user->visibleNotifications()
            ->whereNull('read_at')
            ->values();
    }

    private function areaFor(DatabaseNotification $notification): ?string
    {
        $area = data_get($notification->data, 'sidebar_area');

        if (in_array($area, $this->knownAreas(), true)) {
            return $area;
        }

        $title = (string) data_get($notification->data, 'title');
        $url = (string) data_get($notification->data, 'url');

        if (in_array($title, ['New proposal submitted', 'Proposal revision submitted'], true)) {
            return ProposalActivityNotification::SIDEBAR_AREA_PROPOSAL_SUBMISSIONS;
        }

        if ($title === 'Monitoring tool submitted'
            || $title === 'Progress report submitted'
            || Str::endsWith($title, ['Monitoring Tool Submitted', 'Monitoring Tool Resubmitted'])) {
            return ProposalActivityNotification::SIDEBAR_AREA_PROJECT_MONITORING;
        }

        if (in_array($title, [
            'Proposal workspace invitation',
            'Collaborator accepted invitation',
            'Proposal submitted for review',
            'Proposal ready for signature',
            'Revision requested',
            'Proposal rejected',
            'Signed documents ready',
        ], true) || Str::contains($url, '/faculty/proposal-drafts')) {
            return ProposalActivityNotification::SIDEBAR_AREA_PROPOSAL_WORKSPACE;
        }

        if (in_array($title, [
            'Signed Notice to Proceed issued',
            'Project status updated',
            'Progress report reviewed',
            'Progress report needs revision',
            'Progress report corrections requested',
        ], true) || Str::endsWith($title, ['Monitoring Tool Reviewed', 'Monitoring Tool Needs Revision', 'Monitoring Tool Corrections Requested'])
            || Str::contains($url, '/research/')) {
            return ProposalActivityNotification::SIDEBAR_AREA_MY_PROJECTS;
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function knownAreas(): array
    {
        return [
            ProposalActivityNotification::SIDEBAR_AREA_PROPOSAL_SUBMISSIONS,
            ProposalActivityNotification::SIDEBAR_AREA_PROJECT_MONITORING,
            ProposalActivityNotification::SIDEBAR_AREA_PROPOSAL_WORKSPACE,
            ProposalActivityNotification::SIDEBAR_AREA_MY_PROJECTS,
        ];
    }
}
