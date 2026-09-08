<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TopicProposal extends Model
{
    public const MAX_CONCURRENT_APPROVED_PROJECTS = 2;

    public const STATUS_LREC_QUEUED = 'lrec_queued';

    public const STATUS_LREC_REVIEW = 'lrec_review';

    public const STATUS_READY_FOR_SIGNATURE = 'ready_for_signature';

    public const AWAITING_APPROVAL_STATUSES = [
        'pending',
        'expert_review',
        'for_final_decision',
        'revision_requested',
        'resubmitted',
        self::STATUS_READY_FOR_SIGNATURE,
        self::STATUS_LREC_QUEUED,
        self::STATUS_LREC_REVIEW,
    ];

    public const PROJECT_STATUS_ONGOING = 'ongoing';

    public const PROJECT_STATUS_DELAYED = 'delayed';

    public const PROJECT_STATUS_COMPLETED = 'completed';

    protected $table = 'topics';

    protected $attributes = ['status' => 'pending', 'review_stage' => 'initial'];

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
        'notice_to_proceed_data',
        'status',
        'review_stage',
        'lrec_cleared_at',
        'project_status',
    ];

    protected function casts(): array
    {
        return [
            'estimated_budget' => 'decimal:2',
            'status_started_at' => 'datetime',
            'lrec_cleared_at' => 'datetime',
            'notice_to_proceed_issued_at' => 'datetime',
            'notice_to_proceed_data' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (TopicProposal $topic): void {
            $topic->status_started_at = $topic->created_at ?? now();
        });
        static::updating(function (TopicProposal $topic): void {
            if ($topic->isDirty('status')) {
                $topic->status_started_at = now();
            }
        });
        static::created(function (TopicProposal $topic): void {
            $topic->stageTransitions()->create([
                'from_status' => null,
                'to_status' => $topic->status,
                'changed_at' => $topic->status_started_at,
            ]);
        });
        static::updated(function (TopicProposal $topic): void {
            if ($topic->wasChanged('status')) {
                $topic->stageTransitions()->create([
                    'from_status' => $topic->getOriginal('status'),
                    'to_status' => $topic->status,
                    'previous_started_at' => $topic->getOriginal('status_started_at'),
                    'changed_at' => $topic->status_started_at,
                ]);
            }
        });
    }

    public function canRecordDecision(string $decision): bool
    {
        $allowed = match ($this->status) {
            self::STATUS_LREC_QUEUED => [self::STATUS_LREC_REVIEW],
            self::STATUS_LREC_REVIEW => ['revision_requested', 'rejected', self::STATUS_READY_FOR_SIGNATURE],
            self::STATUS_READY_FOR_SIGNATURE => ['revision_requested'],
            'pending', 'resubmitted', 'expert_review', 'for_final_decision' => $this->review_stage === 'lrec'
                ? ['revision_requested', 'rejected', self::STATUS_READY_FOR_SIGNATURE]
                : ['revision_requested', 'rejected', self::STATUS_LREC_QUEUED],
            default => [],
        };

        if (! in_array($decision, $allowed, true)) {
            return false;
        }

        return $decision !== self::STATUS_LREC_QUEUED
            || $this->versions()
                ->where('submission_type', 'revision')
                ->where('version_number', '>', 1)
                ->exists();
    }

    public function workflowStatusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_LREC_QUEUED => 'Awaiting LREC presentation',
            self::STATUS_LREC_REVIEW => 'LREC review',
            self::STATUS_READY_FOR_SIGNATURE => 'Signing and Notice to Proceed',
            'revision_requested' => $this->review_stage === 'lrec' ? 'LREC revisions requested' : 'Initial revisions requested',
            'resubmitted' => $this->review_stage === 'lrec' ? 'LREC revision awaiting review' : 'Revision awaiting review',
            'approved' => $this->hasIssuedNoticeToProceed() ? 'Approved and released' : 'Preparing final release',
            'rejected' => 'Rejected',
            default => 'Initial review',
        };
    }

    public function stageTransitions(): HasMany
    {
        return $this->hasMany(ProposalStageTransition::class, 'topic_id');
    }

    public function scopeMonitoringAvailable(Builder $query): Builder
    {
        return $query->activeProject();
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    public function scopeOccupiesCapacity(Builder $query): Builder
    {
        return $query
            ->approved()
            ->where(function (Builder $query): void {
                $query->whereNull('project_status')
                    ->orWhere('project_status', '!=', self::PROJECT_STATUS_COMPLETED);
            });
    }

    public function scopeAwaitingApproval(Builder $query): Builder
    {
        return $query->whereIn('status', self::AWAITING_APPROVAL_STATUSES);
    }

    public function scopeAwaitingNoticeToProceed(Builder $query): Builder
    {
        return $query
            ->occupiesCapacity()
            ->whereNull('notice_to_proceed_issued_at');
    }

    public function scopeWithIssuedNotice(Builder $query): Builder
    {
        return $query
            ->approved()
            ->whereNotNull('notice_to_proceed_issued_at');
    }

    public function scopeActiveProject(Builder $query): Builder
    {
        return $query
            ->withIssuedNotice()
            ->whereIn('project_status', [
                self::PROJECT_STATUS_ONGOING,
                self::PROJECT_STATUS_DELAYED,
            ]);
    }

    public function scopeCompletedProject(Builder $query): Builder
    {
        return $query
            ->approved()
            ->where('project_status', self::PROJECT_STATUS_COMPLETED);
    }

    public function scopeVisibleInResearcherWorkspace(Builder $query): Builder
    {
        return $query
            ->approved()
            ->where(function (Builder $query): void {
                $query->where(function (Builder $query): void {
                    $query->whereNull('notice_to_proceed_issued_at')
                        ->where(function (Builder $query): void {
                            $query->whereNull('project_status')
                                ->orWhere('project_status', '!=', self::PROJECT_STATUS_COMPLETED);
                        });
                })->orWhere(function (Builder $query): void {
                    $query->whereNotNull('notice_to_proceed_issued_at')
                        ->whereIn('project_status', [
                            self::PROJECT_STATUS_ONGOING,
                            self::PROJECT_STATUS_DELAYED,
                        ]);
                })->orWhere('project_status', self::PROJECT_STATUS_COMPLETED);
            });
    }

    /** @param Builder<TopicProposal> $query */
    public function scopeAccessibleTo(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $accessible) use ($user): void {
            $accessible
                ->where('user_id', $user->getKey())
                ->orWhereHas('collaborators', fn (Builder $collaborators): Builder => $collaborators->forUser($user));
        });
    }

    public function isMonitoringAvailable(): bool
    {
        return $this->status === 'approved'
            && $this->notice_to_proceed_issued_at !== null
            && in_array($this->project_status, [
                self::PROJECT_STATUS_ONGOING,
                self::PROJECT_STATUS_DELAYED,
            ], true);
    }

    public function isAccessibleTo(User $user): bool
    {
        return $this->user_id === $user->getKey()
            || $this->collaborators()->forUser($user)->exists();
    }

    public function hasIssuedNoticeToProceed(): bool
    {
        return $this->status === 'approved'
            && $this->notice_to_proceed_issued_at !== null;
    }

    public function hasPreparedNoticeToProceed(): bool
    {
        return in_array($this->status, ['approved', self::STATUS_READY_FOR_SIGNATURE], true)
            && $this->notice_to_proceed_issued_at === null
            && filled($this->notice_to_proceed_data);
    }

    public function isAwaitingNoticeToProceed(): bool
    {
        return $this->status === 'approved'
            && $this->project_status !== self::PROJECT_STATUS_COMPLETED
            && $this->notice_to_proceed_issued_at === null;
    }

    public function isCompletedProject(): bool
    {
        return $this->status === 'approved'
            && $this->project_status === self::PROJECT_STATUS_COMPLETED;
    }

    public function isVisibleInResearcherWorkspace(): bool
    {
        return $this->isAwaitingNoticeToProceed()
            || $this->isMonitoringAvailable()
            || $this->isCompletedProject();
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

    public function collaborators(): HasMany
    {
        return $this->hasMany(TopicCollaborator::class, 'topic_id')
            ->orderBy('name');
    }

    public function progressReports(): HasMany
    {
        return $this->hasMany(ProjectProgressReport::class, 'topic_id')
            ->submitted()
            ->latest('reporting_date');
    }

    public function narrativeReports(): HasMany
    {
        return $this->hasMany(ProjectNarrativeReport::class, 'topic_id')
            ->submitted()
            ->latest('submission_date');
    }

    public function latestProgressReport(): HasOne
    {
        return $this->hasOne(ProjectProgressReport::class, 'topic_id')->ofMany(
            [
                'reporting_date' => 'max',
                'id' => 'max',
            ],
            fn (Builder $query): Builder => $query->submitted(),
        );
    }

    public function latestNarrativeReport(): HasOne
    {
        return $this->hasOne(ProjectNarrativeReport::class, 'topic_id')->ofMany(
            [
                'submission_date' => 'max',
                'id' => 'max',
            ],
            fn (Builder $query): Builder => $query->submitted(),
        );
    }
}
