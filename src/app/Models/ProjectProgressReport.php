<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Query\Builder as QueryBuilder;

class ProjectProgressReport extends Model
{
    public const SUBMISSION_STATUS_PREPARED = 'prepared';

    public const SUBMISSION_STATUS_SUBMITTED = 'submitted';

    protected $fillable = [
        'topic_id', 'submitted_by', 'reporting_date', 'reporting_year', 'reporting_quarter',
        'period_start', 'period_end',
        'version_number', 'supersedes_report_id', 'tracking_number',
        'progress_percentage', 'accomplishments', 'issues', 'work_plan',
        'budget_utilization', 'budget_prepared_by', 'budget_prepared_at',
        'prepared_by_date_signed', 'attachment_path', 'submission_status',
        'official_pdf_path', 'official_pdf_filename', 'official_pdf_checksum', 'official_pdf_size',
        'prepared_at', 'submitted_at', 'review_status',
        'research_head_remarks', 'reviewed_by', 'reviewed_at',
        'review_stage', 'secretary_remarks', 'secretary_reviewed_by', 'secretary_reviewed_at', 'forwarded_at',
    ];

    protected $attributes = [
        'submission_status' => self::SUBMISSION_STATUS_SUBMITTED,
        'review_status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'reporting_date' => 'date',
            'period_start' => 'date',
            'period_end' => 'date',
            'prepared_by_date_signed' => 'date',
            'budget_prepared_at' => 'datetime',
            'prepared_at' => 'datetime',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'secretary_reviewed_at' => 'datetime',
            'forwarded_at' => 'datetime',
            'progress_percentage' => 'integer',
            'reporting_year' => 'integer',
            'reporting_quarter' => 'integer',
            'version_number' => 'integer',
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

    public function budgetPreparer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'budget_prepared_by');
    }

    public function secretaryReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'secretary_reviewed_by');
    }

    public function hasPreparedBudget(): bool
    {
        return $this->budget_prepared_by !== null
            && $this->budget_prepared_at !== null;
    }

    public function budgetToken(): string
    {
        return hash('sha256', json_encode([
            $this->id, $this->official_pdf_path, $this->official_pdf_checksum, $this->budget_utilization,
        ], JSON_THROW_ON_ERROR));
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_report_id');
    }

    public function nextVersion(): HasOne
    {
        return $this->hasOne(self::class, 'supersedes_report_id');
    }

    public function scopePrepared(Builder $query): Builder
    {
        return $query->where('submission_status', self::SUBMISSION_STATUS_PREPARED);
    }

    public function scopeSubmitted(Builder $query): Builder
    {
        return $query->where('submission_status', self::SUBMISSION_STATUS_SUBMITTED);
    }

    public function scopeCurrentSubmittedSnapshot(Builder $query, bool $forResearchHead = false): Builder
    {
        $table = $this->getTable();

        return $query->submitted()
            ->when($forResearchHead, fn (Builder $visible): Builder => $visible->visibleToResearchHead())
            ->whereNotExists(function (QueryBuilder $newer) use ($table, $forResearchHead): void {
                $newer->selectRaw('1')->from($table.' as newer_snapshot')
                    ->whereColumn('newer_snapshot.topic_id', $table.'.topic_id')
                    ->where('newer_snapshot.submission_status', self::SUBMISSION_STATUS_SUBMITTED)
                    ->when($forResearchHead, fn (QueryBuilder $visible) => $visible->where(fn (QueryBuilder $stage) => $stage
                        ->whereNull('newer_snapshot.review_stage')->orWhere('newer_snapshot.review_stage', 'head')))
                    ->where(fn (QueryBuilder $version) => $version
                        ->whereColumn('newer_snapshot.version_number', '>', $table.'.version_number')
                        ->orWhere(fn (QueryBuilder $same) => $same->whereColumn('newer_snapshot.version_number', $table.'.version_number')
                            ->whereColumn('newer_snapshot.id', '>', $table.'.id')))
                    ->where(function (QueryBuilder $period) use ($table): void {
                        $period->whereColumn('newer_snapshot.supersedes_report_id', $table.'.id')
                            ->orWhereColumn('newer_snapshot.period_start', $table.'.period_start')
                            ->orWhere(fn (QueryBuilder $dates) => $dates
                                ->whereColumn($table.'.reporting_date', '>=', 'newer_snapshot.period_start')
                                ->whereColumn($table.'.reporting_date', '<=', 'newer_snapshot.period_end'))
                            ->orWhere(fn (QueryBuilder $quarter) => $quarter
                                ->whereColumn('newer_snapshot.reporting_quarter', $table.'.reporting_quarter')
                                ->where(fn (QueryBuilder $year) => $year
                                    ->whereColumn('newer_snapshot.reporting_year', $table.'.reporting_year')
                                    ->orWhere(fn (QueryBuilder $legacy) => $legacy
                                        ->whereNull('newer_snapshot.reporting_year')->whereNull($table.'.reporting_year')
                                        ->whereRaw('YEAR(newer_snapshot.reporting_date) = YEAR('.$table.'.reporting_date)'))));
                    });
            });
    }

    public function scopeVisibleToResearchHead(Builder $query): Builder
    {
        $stage = $query->qualifyColumn('review_stage');

        return $query->where(fn (Builder $query): Builder => $query->whereNull($stage)->orWhere($stage, 'head'));
    }

    public function scopeLatestSubmittedVersion(Builder $query): Builder
    {
        return $query->whereDoesntHave('nextVersion');
    }

    public function isLatestSubmittedVersion(): bool
    {
        return $this->relationLoaded('nextVersion') ? $this->nextVersion === null : ! $this->nextVersion()->exists();
    }

    public function isAvailableToResearchHead(): bool
    {
        return $this->review_stage === null || $this->review_stage === 'head';
    }

    public function isAwaitingSecretaryReview(): bool
    {
        return $this->isSubmitted() && $this->review_stage === 'secretary' && $this->review_status === 'pending';
    }

    public function scopeForQuarter(Builder $query, int $year, int $quarter): Builder
    {
        return $query->where('reporting_year', $year)
            ->where('reporting_quarter', $quarter);
    }

    public function isPrepared(): bool
    {
        return $this->submission_status === self::SUBMISSION_STATUS_PREPARED;
    }

    public function isSubmitted(): bool
    {
        return $this->submission_status === self::SUBMISSION_STATUS_SUBMITTED;
    }

    public function getQuarterLabelAttribute(): string
    {
        if ($this->period_start) {
            return 'Q'.$this->reporting_quarter;
        }
        $quarter = $this->reporting_quarter ?? (int) ceil($this->reporting_date->month / 3);

        return 'Q'.min(max($quarter, 1), 4);
    }

    public function getReportingPeriodLabelAttribute(): string
    {
        if ($this->period_start && $this->period_end) {
            return Carbon::parse($this->period_start)->format('M j, Y').' – '.Carbon::parse($this->period_end)->format('M j, Y');
        }
        $year = $this->reporting_year ?? $this->reporting_date->year;
        $quarter = $this->reporting_quarter ?? (int) ceil($this->reporting_date->month / 3);
        $start = $this->reporting_date->copy()->setDate($year, (($quarter - 1) * 3) + 1, 1)->startOfMonth();
        $end = $start->copy()->addMonths(2)->endOfMonth();

        return $start->format('M j').'–'.$end->format('M j, Y');
    }

    public function getVersionLabelAttribute(): string
    {
        return 'Version '.$this->version_number;
    }

    public function getReviewStatusLabelAttribute(): string
    {
        if ($this->review_stage === 'secretary') {
            return $this->review_status === 'revision_requested'
                ? 'Corrections requested by Research Office Secretary'
                : 'Awaiting Research Office Secretary review';
        }

        if ($this->review_stage === 'head') {
            return $this->review_status === 'reviewed' ? 'Accepted by Research Head' : 'Awaiting Research Head review';
        }

        return match ($this->review_status) {
            'revision_requested' => 'Report corrections requested',
            'reviewed' => 'Reviewed',
            default => 'Awaiting review',
        };
    }
}
