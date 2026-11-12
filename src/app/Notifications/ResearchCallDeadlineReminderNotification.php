<?php

namespace App\Notifications;

use App\Models\ResearchCall;
use App\Models\User;
use Carbon\CarbonInterface;

class ResearchCallDeadlineReminderNotification extends ResearchCallNotification
{
    public function __construct(
        public int $researchCallId,
        public string $researchCallTitle,
        public CarbonInterface $closesAt,
        public string $stage,
        public string $url,
    ) {
        $this->afterCommit();
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        $call = ResearchCall::query()->find($this->researchCallId);

        return $notifiable instanceof User && $notifiable->hasRole(User::WORKSPACE_FACULTY)
            && $call !== null && $call->isAcceptingSubmissions()
            && $call->closes_at->equalTo($this->closesAt)
            && $call->closes_at->lte(now()->addDays(7))
            && $this->stage === (now()->gte($call->closes_at->copy()->subDay()) ? 'final' : 'approaching');
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

    public function broadcastType(): string
    {
        return 'research-call.deadline-reminder';
    }
}
