<?php

namespace App\Notifications;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class ResearchCallOpeningReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $researchCallId,
        public string $researchCallTitle,
        public CarbonInterface $opensAt,
        public string $url,
    ) {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Research call opens tomorrow',
            'message' => 'The research call “'.$this->researchCallTitle.'” will open for faculty on '.$this->opensAt->format('M j, Y \a\t g:i A').'.',
            'url' => $this->url,
            'level' => 'info',
            'research_call_id' => $this->researchCallId,
            'workspace' => User::WORKSPACE_RESEARCH_HEAD,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    public function broadcastType(): string
    {
        return 'research-call.opening-reminder';
    }
}
