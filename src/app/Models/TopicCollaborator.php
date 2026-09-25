<?php

namespace App\Models;

use Database\Factories\TopicCollaboratorFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TopicCollaborator extends Model
{
    public const ROLE_SECRETARY = 'secretary';

    /** @use HasFactory<TopicCollaboratorFactory> */
    use HasFactory;

    protected $fillable = [
        'topic_id',
        'user_id',
        'name',
        'email',
        'accepted_at',
        'project_role',
    ];

    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
        ];
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(TopicProposal::class, 'topic_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @param Builder<TopicCollaborator> $query */
    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query
            ->whereNotNull('accepted_at')
            ->where(function (Builder $collaborator) use ($user): void {
                $collaborator->where('user_id', $user->getKey());

                if ($user->email_verified_at !== null) {
                    $collaborator->orWhere(function (Builder $externalCollaborator) use ($user): void {
                        $externalCollaborator
                            ->whereNull('user_id')
                            ->where('email', mb_strtolower(trim($user->email)));
                    });
                }
            });
    }

    public function isProjectSecretary(): bool
    {
        return $this->project_role === self::ROLE_SECRETARY;
    }
}
