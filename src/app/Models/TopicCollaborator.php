<?php

namespace App\Models;

use Database\Factories\TopicCollaboratorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TopicCollaborator extends Model
{
    /** @use HasFactory<TopicCollaboratorFactory> */
    use HasFactory;

    protected $fillable = [
        'topic_id',
        'user_id',
        'name',
        'email',
        'accepted_at',
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
}
