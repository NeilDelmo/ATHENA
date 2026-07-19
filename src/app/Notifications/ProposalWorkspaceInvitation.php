<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProposalWorkspaceInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $recipientName,
        public string $inviterName,
        public string $projectTitle,
        public string $invitedEmail,
        public string $workspaceUrl,
        public bool $accountLinked,
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

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('ATHENA proposal invitation: '.$this->projectTitle)
            ->greeting('Hello '.$this->recipientName.',')
            ->line($this->inviterName.' invited you to collaborate on “'.$this->projectTitle.'” in ATHENA.')
            ->line($this->accountLinked
                ? 'Your verified ATHENA account is already linked to this proposal workspace.'
                : 'Your workspace access will activate automatically after you sign in with the invited BatStateU Google account.')
            ->action('Open ATHENA', $this->workspaceUrl)
            ->line('Sign in using '.$this->invitedEmail.'. The shared proposal will appear in your Proposal Workspace.')
            ->line('Collaborators may edit draft papers. Only the proposal owner can manage invitations, submit, or delete the proposal.');
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
        ];
    }
}
