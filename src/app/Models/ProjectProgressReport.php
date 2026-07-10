<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectProgressReport extends Model
{
    protected $fillable = [
        'topic_id', 'submitted_by', 'reporting_date', 'progress_percentage',
        'accomplishments', 'issues', 'attachment_path', 'review_status',
        'research_head_remarks', 'reviewed_by', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'reporting_date' => 'date',
            'reviewed_at' => 'datetime',
            'progress_percentage' => 'integer',
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
