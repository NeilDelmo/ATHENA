<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectNarrativeReport extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_REVIEWED = 'reviewed';

    public const STATUS_REVISION_REQUESTED = 'revision_requested';

    protected $fillable = [
        'topic_id',
        'submitted_by',
        'submission_date',
        'tracking_number',
        'researchers',
        'implementation_start',
        'implementation_end',
        'budget',
        'funding_agency',
        'accomplishment_summary',
        'accomplishments',
        'introduction',
        'rationale',
        'objectives',
        'methodology',
        'results_discussion',
        'photos',
        'prepared_by_date_signed',
        'review_status',
        'research_head_remarks',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $attributes = [
        'review_status' => self::STATUS_PENDING,
    ];

    protected function casts(): array
    {
        return [
            'submission_date' => 'date',
            'implementation_start' => 'date',
            'implementation_end' => 'date',
            'budget' => 'decimal:2',
            'accomplishments' => 'array',
            'photos' => 'array',
            'prepared_by_date_signed' => 'date',
            'reviewed_at' => 'datetime',
        ];
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(TopicProposal::class, 'topic_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
