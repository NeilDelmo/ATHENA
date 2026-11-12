<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Preserves the original draft member ID for invitation links after submission. */
class ProposalInvitationEmail extends Model
{
    public const QUEUED = 'queued';

    public const PROCESSED = 'processed';

    public const CAPTURED = 'captured';

    public const FAILED = 'failed';

    public const SKIPPED = 'skipped';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['id', 'draft_member_id', 'topic_collaborator_id', 'attempt', 'status'];

    public function collaborator(): BelongsTo
    {
        return $this->belongsTo(TopicCollaborator::class, 'topic_collaborator_id');
    }

    public function isSendable(string $attempt): bool
    {
        return $this->attempt === $attempt && in_array($this->status, [self::QUEUED, self::FAILED], true);
    }

    public static function recordResult(int $invitationId, string $attempt, string $status): void
    {
        self::query()->whereKey($invitationId)->where('attempt', $attempt)
            ->whereIn('status', [self::QUEUED, self::FAILED])
            ->update(['status' => $status]);
    }

    public function statusMessage(): ?string
    {
        return match ($this->status) {
            self::QUEUED => 'Invitation email queued.',
            self::PROCESSED => 'Invitation email handed to the mailer. Inbox delivery is unconfirmed.',
            self::CAPTURED => 'Invitation email captured by the test mailer.',
            self::FAILED => 'Invitation email failed. You can resend it.',
            self::SKIPPED => 'Invitation email skipped because it was no longer needed.',
            default => null,
        };
    }
}
