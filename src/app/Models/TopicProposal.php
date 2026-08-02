<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TopicProposal extends Model
{
    public const STATUS_READY_FOR_SIGNATURE = 'ready_for_signature';

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
        'notice_to_proceed_path',
        'notice_to_proceed_original_filename',
        'notice_to_proceed_issued_by',
        'notice_to_proceed_issued_at',
        'status',
        'project_status',
    ];

    protected function casts(): array
    {
        return [
            'estimated_budget' => 'decimal:2',
            'notice_to_proceed_issued_at' => 'datetime',
        ];
    }

    public function scopeMonitoringAvailable(Builder $query): Builder
    {
        return $query
            ->where('status', 'approved')
            ->whereNotNull('notice_to_proceed_issued_at');
    }

    public function isMonitoringAvailable(): bool
    {
        return $this->status === 'approved'
            && $this->notice_to_proceed_issued_at !== null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function noticeIssuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'notice_to_proceed_issued_by');
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

    public function versions(): HasMany
    {
        return $this->hasMany(ProposalVersion::class, 'topic_id')->orderBy('version_number');
    }

    public function documentHistory(): HasMany
    {
        return $this->hasMany(ProposalDraftDocumentVersion::class, 'topic_id')
            ->latest();
    }

    public function latestVersion(): HasOne
    {
        return $this->hasOne(ProposalVersion::class, 'topic_id')->ofMany('version_number', 'max');
    }

    public function revisionDraft(): HasOne
    {
        return $this->hasOne(ProposalDraft::class, 'topic_id');
    }

    public function progressReports(): HasMany
    {
        return $this->hasMany(ProjectProgressReport::class, 'topic_id')->latest('reporting_date');
    }

    public function narrativeReports(): HasMany
    {
        return $this->hasMany(ProjectNarrativeReport::class, 'topic_id')->latest('submission_date');
    }

    public function latestProgressReport(): HasOne
    {
        return $this->hasOne(ProjectProgressReport::class, 'topic_id')->ofMany([
            'reporting_date' => 'max',
            'id' => 'max',
        ]);
    }

    public function latestNarrativeReport(): HasOne
    {
        return $this->hasOne(ProjectNarrativeReport::class, 'topic_id')->ofMany([
            'submission_date' => 'max',
            'id' => 'max',
        ]);
    }
}
