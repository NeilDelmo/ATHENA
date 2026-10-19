<?php

namespace App\Notifications;

use App\Models\ResearchCall;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class ResearchCallPublishedNotification extends Notification implements ShouldQueue
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

    public function shouldSend(object $notifiable, string $channel): bool
    {
        return $notifiable instanceof User && $notifiable->hasRole(User::WORKSPACE_FACULTY)
            && ResearchCall::query()->whereKey($this->researchCallId)->acceptingSubmissions()->exists();
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'New research call available',
            'message' => 'The research call “'.$this->researchCallTitle.'” has been published. You can submit proposals anytime from your Proposal Workspace.',
            'url' => $this->url,
            'level' => 'info',
            'research_call_id' => $this->researchCallId,
            'workspace' => User::WORKSPACE_FACULTY,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    public function broadcastType(): string
    {
        return 'research-call.published';
    }
}
