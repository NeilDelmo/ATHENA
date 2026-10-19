<?php

namespace App\Notifications;

use App\Models\TopicProposal;
use App\Models\User;
use App\Services\ApprovedWorkPlanMonitoringService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class ProposalActivityNotification extends Notification
{
    use Queueable;

    public const SIDEBAR_AREA_PROPOSAL_SUBMISSIONS = 'proposal_submissions';

    public const SIDEBAR_AREA_PROJECT_MONITORING = 'project_monitoring';

    public const SIDEBAR_AREA_PROPOSAL_WORKSPACE = 'proposal_workspace';

    public const SIDEBAR_AREA_SUBMITTED_PROPOSALS = 'submitted_proposals';

    public const SIDEBAR_AREA_MY_PROJECTS = 'my_projects';

    /**
     * @param  list<string>|string|null  $workspace
     */
    public function __construct(
        public string $title,
        public string $message,
        public string $url,
        public string $level = 'info',
        public ?int $topicId = null,
        public ?string $actionUrl = null,
        public array $actionData = [],
        public string|array|null $workspace = null,
        public ?string $sidebarArea = null,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        $key = $this->actionData['work_plan_overdue_key'] ?? null;
        if ($key === null) {
            return true;
        }
        $topic = TopicProposal::query()->find($this->topicId);
        if (! $notifiable instanceof User || $topic === null
            || ($topic->user_id !== $notifiable->id && ! $notifiable->hasRole(User::WORKSPACE_RESEARCH_HEAD))) {
            return false;
        }

        return collect(app(ApprovedWorkPlanMonitoringService::class)->overdueActivities($topic))
            ->contains(fn (array $activity): bool => $topic->id.':'.$activity['source_work_plan_index'].':'.$activity['target_completion_date'] === $key);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'url' => $this->url,
            'level' => $this->level,
            'topic_id' => $this->topicId,
            'action_url' => $this->actionUrl,
            'action_data' => $this->actionData,
            'workspace' => $this->workspace,
            'sidebar_area' => $this->sidebarArea,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    public function broadcastType(): string
    {
        return 'proposal.activity';
    }
}
