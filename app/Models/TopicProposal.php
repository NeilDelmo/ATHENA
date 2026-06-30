<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TopicProposal extends Model
{
    protected $table = 'topics';

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'estimated_budget',
        'initial_file_path',
        'final_file_path',
        'status', // 'pending', 'revision_requested', 'resubmitted', 'approved', 'rejected'
    ];

    protected function casts(): array
    {
        return [
            'estimated_budget' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(TopicReview::class, 'topic_id');
    }
}
