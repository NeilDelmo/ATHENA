<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProposalVersion extends Model
{
    protected $fillable = [
        'submitted_by',
        'version_number',
        'submission_type',
        'file_path',
        'original_filename',
        'mime_type',
        'file_size',
        'checksum',
        'title',
        'description',
        'estimated_budget',
        'estimated_duration_months',
    ];

    protected function casts(): array
    {
        return [
            'estimated_budget' => 'decimal:2',
            'file_size' => 'integer',
            'version_number' => 'integer',
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
}
