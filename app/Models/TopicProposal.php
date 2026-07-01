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
        'research_call_id',
        'research_category_id',
        'title',
        'description',
        'estimated_budget',
        'estimated_duration_months',
        'initial_file_path',
        'final_file_path',
        'signed_approval_path',
        'status',
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

    public function researchCall(): BelongsTo
    {
        return $this->belongsTo(ResearchCall::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ResearchCategory::class, 'research_category_id');
    }

    public function expertAssignments(): HasMany
    {
        return $this->hasMany(TopicExpertAssignment::class, 'topic_id');
    }
}
