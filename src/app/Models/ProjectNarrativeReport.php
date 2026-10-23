<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder as QueryBuilder;

class ProjectNarrativeReport extends Model
{
    public const SUBMISSION_STATUS_PREPARED = 'prepared';

    public const SUBMISSION_STATUS_SUBMITTED = 'submitted';

    public const STATUS_PENDING = 'pending';

    public const STATUS_REVIEWED = 'reviewed';

    public const STATUS_REVISION_REQUESTED = 'revision_requested';

    protected $fillable = [
        'report_type',
        'reporting_date',
        'reporting_quarter',
        'period_start',
        'period_end',
        'version_number',
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
        'review_stage',
        'secretary_remarks',
        'secretary_reviewed_by',
        'secretary_reviewed_at',
        'forwarded_at',
    ];

    protected $attributes = [
        'submission_status' => self::SUBMISSION_STATUS_SUBMITTED,
        'review_status' => self::STATUS_PENDING,
    ];

    protected function casts(): array
    {
        return [
            'terminal_data' => 'array',
            'reporting_date' => 'date',
            'reporting_quarter' => 'integer',
            'period_start' => 'date',
            'period_end' => 'date',
            'version_number' => 'integer',
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
            'secretary_reviewed_at' => 'datetime',
            'forwarded_at' => 'datetime',
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

    public function secretaryReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'secretary_reviewed_by');
    }

    public function scopePrepared(Builder $query): Builder
    {
        return $query->where('submission_status', self::SUBMISSION_STATUS_PREPARED);
    }

    public function scopeSubmitted(Builder $query): Builder
    {
        return $query->where('submission_status', self::SUBMISSION_STATUS_SUBMITTED);
    }

    public function scopeVisibleToResearchHead(Builder $query): Builder
    {
        $stage = $query->qualifyColumn('review_stage');
        $type = $query->qualifyColumn('report_type');

        return $query->where(fn (Builder $query): Builder => $query->where($type, 'terminal')->orWhereNull($stage)->orWhere($stage, 'head'));
    }

    /** @param array{quarter: int, start: \Carbon\CarbonImmutable, end: \Carbon\CarbonImmutable} $period */
    public function scopeForReportingPeriod(Builder $query, array $period): Builder
    {
        return $query->where(function (Builder $query) use ($period): void {
            $query->where('reporting_quarter', $period['quarter'])
                ->orWhere(function (Builder $legacy) use ($period): void {
                    $dates = [$period['start']->toDateString(), $period['end']->toDateString()];
                    $legacy->whereNull('reporting_quarter')->where(function (Builder $query) use ($dates): void {
                        $query->whereBetween('reporting_date', $dates)
                            ->orWhere(fn (Builder $query): Builder => $query->whereNull('reporting_date')->whereBetween('period_end', $dates));
                    });
                });
        });
    }

    /** @param array{quarter: int, start: \Carbon\CarbonImmutable, end: \Carbon\CarbonImmutable} $period */
    public function isForReportingPeriod(array $period): bool
    {
        return $this->reporting_quarter !== null
            ? $this->reporting_quarter === $period['quarter']
            : (($this->reporting_date ?? $this->period_end)?->betweenIncluded($period['start'], $period['end']) ?? false);
    }

    public function scopeLatestSubmittedVersion(Builder $query): Builder
    {
        return $query->whereNotExists(function (QueryBuilder $newer): void {
            $newer->selectRaw('1')->from('project_narrative_reports as newer_report')
                ->whereColumn('newer_report.topic_id', 'project_narrative_reports.topic_id')
                ->whereColumn('newer_report.report_type', 'project_narrative_reports.report_type')
                ->whereColumn('newer_report.id', '>', 'project_narrative_reports.id')
                ->where(function (QueryBuilder $period): void {
                    $period->whereColumn('newer_report.reporting_quarter', 'project_narrative_reports.reporting_quarter')
                        ->orWhere(function (QueryBuilder $legacy): void {
                            $legacy->whereNull('project_narrative_reports.reporting_quarter')
                                ->where(function (QueryBuilder $dates): void {
                                    $dates->whereColumn('newer_report.period_start', 'project_narrative_reports.period_start')
                                        ->orWhere(function (QueryBuilder $date): void {
                                            $date->whereNull('project_narrative_reports.period_start')
                                                ->whereColumn('newer_report.reporting_date', 'project_narrative_reports.reporting_date');
                                        })->orWhere(function (QueryBuilder $date): void {
                                            $date->whereColumn('newer_report.period_start', '<=', 'project_narrative_reports.reporting_date')
                                                ->whereColumn('newer_report.period_end', '>=', 'project_narrative_reports.reporting_date');
                                        })->orWhere(function (QueryBuilder $date): void {
                                            $date->whereNull('project_narrative_reports.reporting_date')
                                                ->whereColumn('newer_report.period_start', '<=', 'project_narrative_reports.period_end')
                                                ->whereColumn('newer_report.period_end', '>=', 'project_narrative_reports.period_end');
                                        });
                                });
                        });
                });
        });
    }

    public function isLatestSubmittedVersion(): bool
    {
        return self::query()->whereKey($this->id)->latestSubmittedVersion()->exists();
    }

    public function isAvailableToResearchHead(): bool
    {
        return $this->report_type === 'terminal' || $this->review_stage === null || $this->review_stage === 'head';
    }

    public function isAwaitingSecretaryReview(): bool
    {
        return $this->report_type === 'progress' && $this->isSubmitted() && $this->review_stage === 'secretary' && $this->review_status === self::STATUS_PENDING;
    }

    public function isPrepared(): bool
    {
        return $this->submission_status === self::SUBMISSION_STATUS_PREPARED;
    }

    public function getReportLabelAttribute(): string
    {
        return $this->report_type === 'terminal' ? 'Terminal report' : 'Progress report';
    }

    public function getReportingPeriodLabelAttribute(): ?string
    {
        return $this->period_start && $this->period_end
            ? 'Q'.$this->reporting_quarter.' · '.$this->period_start->format('M j, Y').' – '.$this->period_end->format('M j, Y')
            : null;
    }

    public function isSubmitted(): bool
    {
        return $this->submission_status === self::SUBMISSION_STATUS_SUBMITTED;
    }

    /** @return array{path: string, original_filename: string, mime_type: string, size: int, checksum: string, uploaded_by: int, uploaded_at: string}|null */
    public function signedCopy(): ?array
    {
        $signedCopy = $this->terminal_data['signed_copy'] ?? null;

        return is_array($signedCopy) && filled($signedCopy['path'] ?? null)
            ? $signedCopy
            : null;
    }

    public function hasSignedCopy(): bool
    {
        return $this->report_type === 'terminal' && $this->signedCopy() !== null;
    }

    public function getReviewStatusLabelAttribute(): string
    {
        if ($this->report_type === 'progress' && $this->review_stage === 'secretary') {
            return $this->review_status === self::STATUS_REVISION_REQUESTED
                ? 'Corrections requested by Research Office Secretary'
                : 'Awaiting Research Office Secretary review';
        }

        if ($this->report_type === 'progress' && $this->review_stage === 'head') {
            return $this->review_status === self::STATUS_REVIEWED ? 'Accepted by Research Head' : 'Awaiting Research Head review';
        }

        return match ($this->review_status) {
            self::STATUS_REVISION_REQUESTED => 'Corrections requested by Research Head',
            self::STATUS_REVIEWED => 'Reviewed by Research Head',
            default => 'Pending Research Head review',
        };
    }
}
