<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectProgressReport extends Model
{
    public const SUBMISSION_STATUS_PREPARED = 'prepared';

    public const SUBMISSION_STATUS_SUBMITTED = 'submitted';

    protected $fillable = [
        'topic_id', 'submitted_by', 'reporting_date', 'tracking_number',
        'progress_percentage', 'accomplishments', 'issues', 'work_plan',
        'budget_utilization', 'prepared_by_date_signed', 'attachment_path', 'submission_status',
        'official_pdf_path', 'official_pdf_filename', 'official_pdf_checksum', 'official_pdf_size',
        'prepared_at', 'submitted_at', 'review_status',
        'research_head_remarks', 'reviewed_by', 'reviewed_at',
    ];

    protected $attributes = [
        'submission_status' => self::SUBMISSION_STATUS_SUBMITTED,
        'review_status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'reporting_date' => 'date',
            'prepared_by_date_signed' => 'date',
            'prepared_at' => 'datetime',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'progress_percentage' => 'integer',
            'official_pdf_size' => 'integer',
            'work_plan' => 'array',
            'budget_utilization' => 'array',
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

    public function isSubmitted(): bool
    {
        return $this->submission_status === self::SUBMISSION_STATUS_SUBMITTED;
    }
}
