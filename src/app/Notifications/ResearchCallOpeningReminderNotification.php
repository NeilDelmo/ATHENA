<?php

namespace App\Notifications;

use App\Models\ResearchCall;
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

    public function shouldSend(object $notifiable, string $channel): bool
    {
        $call = ResearchCall::query()->find($this->researchCallId);

        return $notifiable instanceof User && $call !== null
            && ($notifiable->hasRole(User::WORKSPACE_RESEARCH_HEAD) || $call->created_by === $notifiable->id)
            && $call->status === 'open' && ! $call->closes_at->isPast()
            && $call->opens_at->equalTo($this->opensAt)
            && $call->opens_at->isFuture() && $call->opens_at->lte(now()->addDay());
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
