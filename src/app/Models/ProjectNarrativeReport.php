<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectNarrativeReport extends Model
{
    public const SUBMISSION_STATUS_PREPARED = 'prepared';

    public const SUBMISSION_STATUS_SUBMITTED = 'submitted';

    public const STATUS_PENDING = 'pending';

    public const STATUS_REVIEWED = 'reviewed';

    public const STATUS_REVISION_REQUESTED = 'revision_requested';

    protected $fillable = [
        'report_type',
        'terminal_data',
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
        'submission_status',
        'official_pdf_path',
        'official_pdf_filename',
        'official_pdf_checksum',
        'official_pdf_size',
        'prepared_at',
        'submitted_at',
        'review_status',
        'research_head_remarks',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $attributes = [
        'submission_status' => self::SUBMISSION_STATUS_SUBMITTED,
        'review_status' => self::STATUS_PENDING,
    ];

    protected function casts(): array
    {
        return [
            'terminal_data' => 'array',
            'submission_date' => 'date',
            'implementation_start' => 'date',
            'implementation_end' => 'date',
            'budget' => 'decimal:2',
            'accomplishments' => 'array',
            'photos' => 'array',
            'prepared_by_date_signed' => 'date',
            'prepared_at' => 'datetime',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'official_pdf_size' => 'integer',
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

    public function scopePrepared(Builder $query): Builder
    {
        return $query->where('submission_status', self::SUBMISSION_STATUS_PREPARED);
    }

    public function scopeSubmitted(Builder $query): Builder
    {
        return $query->where('submission_status', self::SUBMISSION_STATUS_SUBMITTED);
    }

    public function isPrepared(): bool
    {
        return $this->submission_status === self::SUBMISSION_STATUS_PREPARED;
    }

    public function getReportLabelAttribute(): string
    {
        return $this->report_type === 'terminal' ? 'Terminal report' : 'Progress report';
    }

    public function isSubmitted(): bool
    {
        return $this->submission_status === self::SUBMISSION_STATUS_SUBMITTED;
    }
}
