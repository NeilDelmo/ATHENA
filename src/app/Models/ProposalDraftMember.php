<?php

namespace App\Models;

use Database\Factories\ProposalDraftMemberFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProposalDraftMember extends Model
{
    /** @use HasFactory<ProposalDraftMemberFactory> */
    use HasFactory;

    protected $fillable = [
        'proposal_draft_id',
        'user_id',
        'name',
        'email',
    ];

    public function draft(): BelongsTo
    {
        return $this->belongsTo(ProposalDraft::class, 'proposal_draft_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @param Builder<ProposalDraftMember> $query */
    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $membership) use ($user): void {
            $membership->where('user_id', $user->getKey());

            if ($user->email_verified_at !== null) {
                $membership->orWhere(function (Builder $externalMembership) use ($user): void {
                    $externalMembership
                        ->whereNull('user_id')
                        ->where('email', mb_strtolower(trim($user->email)));
                });
            }
        });
    }

    public function isLinked(): bool
    {
        return $this->user_id !== null;
    }
}
