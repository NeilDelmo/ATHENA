<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProposalFileAnnotation extends Model
{
    public const TYPE_TEXT = 'text';

    public const TYPE_AREA = 'area';

    public const TYPE_PIN = 'pin';

    public const SOURCE_HEAD = 'research_head';

    public const SOURCE_CO_EVALUATOR = 'co_evaluator';

    protected $attributes = ['feedback_source' => self::SOURCE_HEAD];

    protected $fillable = [
        'feedback_source',
        'co_evaluator_name',
        'reviewer_id',
        'topic_review_file_revision_id',
        'annotation_type',
        'page_number',
        'selected_text',
        'rectangles',
        'comment',
        'editor_target',
    ];

    protected function casts(): array
    {
        return [
            'page_number' => 'integer',
            'rectangles' => 'array',
        ];
    }

    public function feedbackLabel(): string
    {
        return $this->feedback_source === self::SOURCE_CO_EVALUATOR
            ? 'Co-evaluator · '.$this->co_evaluator_name
            : 'Research Head';
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
