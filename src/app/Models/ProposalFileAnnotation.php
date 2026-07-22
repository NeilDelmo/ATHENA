<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProposalFileAnnotation extends Model
{
    public const TYPE_TEXT = 'text';

    public const TYPE_AREA = 'area';

    protected $fillable = [
        'reviewer_id',
        'topic_review_file_revision_id',
        'annotation_type',
        'page_number',
        'selected_text',
        'rectangles',
        'comment',
    ];

    protected function casts(): array
    {
        return [
            'page_number' => 'integer',
            'rectangles' => 'array',
        ];
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(ProposalVersionFile::class, 'proposal_version_file_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function fileRevision(): BelongsTo
    {
        return $this->belongsTo(TopicReviewFileRevision::class, 'topic_review_file_revision_id');
    }
}
