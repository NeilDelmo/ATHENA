<?php

namespace App\Notifications;

use App\Models\ResearchCall;
use App\Models\User;

class ResearchCallUpdatedNotification extends ResearchCallNotification
{
    public function __construct(
        public int $researchCallId,
        public string $researchCallTitle,
        public string $url,
    ) {
        $this->afterCommit();
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        return $notifiable instanceof User && $notifiable->hasRole(User::WORKSPACE_FACULTY)
            && ResearchCall::query()->whereKey($this->researchCallId)->visibleToFaculty()->exists();
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Research call updated',
            'message' => 'The research call “'.$this->researchCallTitle.'” has been updated. Review the latest details.',
            'url' => $this->url,
            'level' => 'info',
            'research_call_id' => $this->researchCallId,
            'workspace' => User::WORKSPACE_FACULTY,
        ];
    }

    public function broadcastType(): string
    {
        return 'research-call.updated';
    }
}
