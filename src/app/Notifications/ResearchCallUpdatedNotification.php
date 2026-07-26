<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class ResearchCallUpdatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $researchCallId,
        public string $researchCallTitle,
        public string $url,
    ) {
        $this->afterCommit();
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
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
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    public function broadcastType(): string
    {
        return 'research-call.updated';
    }
}
