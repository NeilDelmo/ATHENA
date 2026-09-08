<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectNarrativeReportDraft extends Model
{
    protected $fillable = [
        'topic_id',
        'user_id',
        'source_data',
        'report_type',
        'lock_version',
    ];

    protected $attributes = [
        'lock_version' => 0,
        'report_type' => 'progress',
    ];

    protected function casts(): array
    {
        return [
            'source_data' => 'array',
            'lock_version' => 'integer',
        ];
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(TopicProposal::class, 'topic_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
