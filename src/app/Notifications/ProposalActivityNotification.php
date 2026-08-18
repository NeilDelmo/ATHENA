<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class ProposalActivityNotification extends Notification
{
    use Queueable;

    public const SIDEBAR_AREA_PROPOSAL_SUBMISSIONS = 'proposal_submissions';

    public const SIDEBAR_AREA_PROJECT_MONITORING = 'project_monitoring';

    public const SIDEBAR_AREA_PROPOSAL_WORKSPACE = 'proposal_workspace';

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
