<?php

namespace App\Notifications;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class ResearchCallDeadlineReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $researchCallId,
        public string $researchCallTitle,
        public CarbonInterface $closesAt,
        public string $stage,
        public string $url,
    ) {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $isFinalReminder = $this->stage === 'final';

        return [
            'title' => $isFinalReminder ? 'Research call closes in about 24 hours' : 'Research call deadline approaching',
            'message' => 'The research call “'.$this->researchCallTitle.'” closes on '.$this->closesAt->timezone(config('app.timezone'))->format('M j, Y \a\t g:i A').' PHT. Proposal submissions remain available anytime in your Proposal Workspace.',
            'url' => $this->url,
            'level' => $isFinalReminder ? 'warning' : 'info',
            'research_call_id' => $this->researchCallId,
            'deadline_notification_stage' => $this->stage,
            'deadline_at' => $this->closesAt->toIso8601String(),
            'workspace' => User::WORKSPACE_FACULTY,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    public function broadcastType(): string
    {
        return 'research-call.deadline-reminder';
    }
}
