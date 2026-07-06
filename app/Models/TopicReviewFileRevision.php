<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TopicReviewFileRevision extends Model
{
    protected $fillable = [
        'proposal_version_file_id',
        'resolved_by_version_file_id',
        'document_type',
        'original_filename',
        'revision_note',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(TopicReview::class, 'topic_review_id');
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(ProposalVersionFile::class, 'proposal_version_file_id');
    }

    public function resolutionFile(): BelongsTo
    {
        return $this->belongsTo(ProposalVersionFile::class, 'resolved_by_version_file_id');
    }
}
