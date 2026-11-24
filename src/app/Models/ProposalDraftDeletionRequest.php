<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProposalDraftDeletionRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_DECLINED = 'declined';

    public const STATUS_CANCELLED = 'cancelled';

    protected $attributes = ['status' => self::STATUS_PENDING];

    protected $fillable = ['reason', 'status', 'member_ids', 'approved_member_ids'];

    protected function casts(): array
    {
        return ['member_ids' => 'array', 'approved_member_ids' => 'array'];
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
