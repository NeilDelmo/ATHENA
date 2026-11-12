<?php

namespace App\Notifications;

use App\Models\ProposalDraftMember;
use App\Models\ProposalInvitationEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\SentMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Throwable;

class ProposalWorkspaceInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [15, 60];

    public function __construct(
        public string $recipientName,
        public string $inviterName,
        public string $projectTitle,
        public string $invitedEmail,
        public string $workspaceUrl,
        public bool $accountLinked,
        public bool $requiresAcceptance = false,
        public ?int $invitationId = null,
        public ?string $attempt = null,
    ) {
        $this->afterCommit();
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        preg_match('~/notifications/proposal-invitations/(\d+)$~', $this->workspaceUrl, $matches);
        $invitationId = $this->invitationId ?? (isset($matches[1]) ? (int) $matches[1] : null);
        if ($invitationId === null) {
            return true;
        }

        $delivery = ProposalInvitationEmail::query()->find($invitationId);
        if (($this->attempt ?? null) !== null && ! $delivery?->isSendable($this->attempt)) {
            return false;
        }
        if (($this->attempt ?? null) === null && $delivery?->attempt !== null) {
            return false;
        }

        $active = ProposalDraftMember::query()->whereKey($invitationId)->where('email', $this->invitedEmail)
            ->when($this->requiresAcceptance, fn ($query) => $query->whereNull('accepted_at'))
            ->whereHas('draft')->exists()
            || ProposalInvitationEmail::query()->whereKey($invitationId)->whereHas('collaborator', function ($query): void {
                $query->where('email', $this->invitedEmail)
                    ->when($this->requiresAcceptance, fn ($query) => $query->whereNull('accepted_at'));
            })->exists();

        if (! $active) {
            $this->recordResult(ProposalInvitationEmail::SKIPPED);
        }

        return $active;
    }

    public function afterSending(object $notifiable, string $channel, mixed $response): void
    {
        $mailer = config('mail.default');
        $transport = config("mail.mailers.{$mailer}.transport");
        $status = match (true) {
            ! $response instanceof SentMessage => ProposalInvitationEmail::FAILED,
            in_array($transport, ['log', 'array'], true) => ProposalInvitationEmail::CAPTURED,
            default => ProposalInvitationEmail::PROCESSED,
        };

        $this->recordResult($status);
    }

    public function failed(?Throwable $exception): void
    {
        $this->recordResult(ProposalInvitationEmail::FAILED);
    }

    private function recordResult(string $status): void
    {
        if (isset($this->invitationId, $this->attempt)) {
            ProposalInvitationEmail::recordResult($this->invitationId, $this->attempt, $status);
        }
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('ATHENA project staff invitation: '.$this->projectTitle)
            ->greeting('Hello '.$this->recipientName.',')
            ->line($this->inviterName.' invited you to serve as project staff for “'.$this->projectTitle.'” in ATHENA.')
            ->line($this->accountLinked
                ? ($this->requiresAcceptance
                    ? 'Review the invitation in ATHENA and choose Accept or Decline.'
                    : 'Your verified ATHENA account is already linked to this proposal workspace.')
                : 'Sign in with the invited BatStateU Google account, then choose Accept or Decline. Access starts only after you accept.')
            ->action('Open ATHENA', $this->workspaceUrl)
            ->line('Sign in using '.$this->invitedEmail.'. Open the invitation to find the shared proposal or its submitted project record.')
            ->line('Project staff may edit draft papers. Only the project leader can manage invitations, assign project roles, submit, or delete the proposal.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'project_title' => $this->projectTitle,
            'inviter_name' => $this->inviterName,
            'invited_email' => $this->invitedEmail,
            'account_linked' => $this->accountLinked,
            'requires_acceptance' => $this->requiresAcceptance,
        ];
    }
}
