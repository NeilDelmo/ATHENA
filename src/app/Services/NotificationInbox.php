<?php

namespace App\Services;

use App\Models\ProjectNarrativeReport;
use App\Models\ProjectProgressReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class NotificationInbox
{
    public const CATEGORIES = [
        'invitations' => ['label' => 'Invitations', 'description' => 'Invitations to serve as project staff.'],
        'collaboration' => ['label' => 'Project team', 'description' => 'Accepted invitations and project team activity.'],
        'reviews' => ['label' => 'Reviews', 'description' => 'Proposal submissions, revisions, and review decisions.'],
        'research_calls' => ['label' => 'Research calls', 'description' => 'Openings, updates, and deadline reminders.'],
        'projects' => ['label' => 'Projects', 'description' => 'Monitoring, reports, and implementation activity.'],
        'general' => ['label' => 'General', 'description' => 'Other ATHENA updates.'],
    ];

    /** @param list<array{key: string, label: string, title: string, url: string}> $actions */
    public function for(User $user, array $actions, bool $recentOnly = false): Collection
    {
        $actionsByEntity = $this->actionsByEntity($actions);

        // Keep header loading bounded; the inbox retains older history.
        return $user->visibleNotificationsQuery()->latest()->orderByDesc('id')
            ->when($recentOnly, fn (Builder $query): Builder => $query->limit(750))->get()
            ->groupBy(fn (DatabaseNotification $notification): string => $this->groupKey($notification))
            ->flatMap(fn (Collection $notifications): Collection => $notifications->chunk(50))
            ->when($recentOnly, fn (Collection $groups): Collection => $groups
                ->sortByDesc(fn (Collection $group): array => [$group->first()->created_at->toIso8601String(), $group->first()->id])
                ->take(15))
            ->map(function (Collection $notifications) use ($actionsByEntity): array {
                $history = $notifications->map(fn (DatabaseNotification $notification): array => $this->present($notification))->values();
                $latest = $history->first();
                $actions = $actionsByEntity[$this->actionEntity($notifications->first())] ?? [];

                if ($actions === []) {
                    unset($latest['data']['action_url'], $latest['data']['action_data']);
                }

                return [
                    ...$latest,
                    'group_key' => $this->groupKey($notifications->first()),
                    'notification_ids' => $notifications->pluck('id')->values()->all(),
                    'update_count' => $notifications->count(),
                    'unread_count' => $notifications->whereNull('read_at')->count(),
                    'read_at' => $notifications->contains(fn (DatabaseNotification $notification): bool => $notification->read_at === null) ? null : $latest['read_at'],
                    'needs_action' => $actions !== [],
                    'actions' => $actions,
                    'history' => $history->all(),
                    'search_text' => $history->pluck('search_text')->join(' '),
                ];
            })->sortBy([['created_at_iso', 'desc'], ['id', 'desc']])->values();
    }

    public function groupKey(DatabaseNotification $notification): string
    {
        $data = $notification->data;
        if (isset($data['action_data']['work_plan_overdue_key'])) {
            return 'overdue:'.$data['action_data']['work_plan_overdue_key'];
        }
        if ($this->isInvitation($notification)) {
            $entity = $this->actionEntity($notification);

            return $entity !== '' ? $entity : 'notification:'.$notification->id;
        }
        if (isset($data['research_call_id'])) {
            if (isset($data['deadline_at'])) {
                return 'deadline:'.$data['research_call_id'].':'.$data['deadline_at'].':'.($data['deadline_notification_stage'] ?? '');
            }

            return 'call:'.$data['research_call_id'].':'.$notification->type;
        }
        if (isset($data['action_data']['rms_period_key'])) {
            return 'rms:'.$data['action_data']['rms_period_key'];
        }
        if (isset($data['topic_id'])) {
            return 'topic:'.$data['topic_id'].':'.$this->category($notification);
        }

        return 'notification:'.$notification->id;
    }

    /** @return array<string, mixed> */
    private function present(DatabaseNotification $notification): array
    {
        $category = $this->category($notification);
        $data = $notification->data;

        return [
            'id' => $notification->id,
            'data' => $data,
            'read_at' => $notification->read_at?->toIso8601String(),
            'created_at' => $notification->created_at->diffForHumans(),
            'created_at_iso' => $notification->created_at->toIso8601String(),
            'category' => $category,
            'category_label' => self::CATEGORIES[$category]['label'],
            'search_text' => Str::lower(implode(' ', [(string) ($data['title'] ?? ''), (string) ($data['message'] ?? ''), self::CATEGORIES[$category]['label']])),
        ];
    }

    private function category(DatabaseNotification $notification): string
    {
        $data = $notification->data;
        $title = Str::lower((string) ($data['title'] ?? ''));
        $area = $data['sidebar_area'] ?? null;
        if (isset($data['research_call_id']) || Str::contains($title, 'research call')) {
            return 'research_calls';
        }
        if ($this->isInvitation($notification)) {
            return ($data['action_completed'] ?? false) || Str::contains($title, ['accepted invitation', 'invitation accepted']) ? 'collaboration' : 'invitations';
        }
        if (in_array($area, ['proposal_submissions', 'submitted_proposals'], true) || Str::contains($title, ['review', 'revision', 'proposal submitted', 'proposal approved', 'proposal rejected', 'screening'])) {
            return in_array($area, ['project_monitoring', 'my_projects'], true) ? 'projects' : 'reviews';
        }
        if ($area === 'proposal_workspace') {
            return 'collaboration';
        }
        if (in_array($area, ['project_monitoring', 'my_projects'], true) || Str::contains($title, ['project', 'monitoring', 'progress report', 'narrative report', 'notice to proceed'])) {
            return 'projects';
        }

        return 'general';
    }

    private function isInvitation(DatabaseNotification $notification): bool
    {
        return Str::contains(Str::lower((string) ($notification->data['title'] ?? '')), ['invitation', 'invited'])
            || filled($notification->data['action_url'] ?? null);
    }

    private function actionEntity(DatabaseNotification $notification): string
    {
        $data = $notification->data;
        if (isset($data['action_data']['work_plan_overdue_key'])) {
            return 'overdue:'.$data['action_data']['work_plan_overdue_key'];
        }
        if ($this->isInvitation($notification)) {
            preg_match('~/notifications/proposal-invitations/(\\d+)(?:/accept)?$~', (string) ($data['action_url'] ?? $data['url'] ?? ''), $matches);

            return isset($matches[1]) ? 'invitation:'.$matches[1] : '';
        }

        return isset($data['topic_id']) ? ($this->category($notification) === 'projects' ? 'project:' : 'proposal:').$data['topic_id'] : '';
    }

    /**
     * @param  list<array{key: string, label: string, title: string, url: string}>  $actions
     * @return array<string, list<array<string, string>>>
     */
    private function actionsByEntity(array $actions): array
    {
        $entities = [];
        $reports = [];
        foreach (['monitoring' => ProjectProgressReport::class, 'narrative' => ProjectNarrativeReport::class] as $prefix => $model) {
            $ids = collect($actions)->pluck('key')->filter(fn (string $key): bool => Str::startsWith($key, $prefix.':'))->map(fn (string $key): string => Str::after($key, ':'));
            $reports[$prefix] = $ids->isEmpty() ? collect() : $model::query()->whereKey($ids)->pluck('topic_id', 'id');
        }
        foreach ($actions as $action) {
            [$prefix, $id] = explode(':', $action['key'], 2);
            $entity = isset($reports[$prefix]) ? 'project:'.$reports[$prefix]->get($id) : $action['key'];
            $entities[$entity][] = $action;
        }

        return $entities;
    }
}
