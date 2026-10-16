<?php

namespace App\Models;

use Database\Factories\CollegeQuarterlyReportSubmissionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Arr;

class CollegeQuarterlyReportSubmission extends Model
{
    /** @use HasFactory<CollegeQuarterlyReportSubmissionFactory> */
    use HasFactory;

    protected $fillable = ['report_id', 'version_number', 'submitted_by', 'snapshot', 'submitted_at', 'returned_by', 'return_reason', 'returned_at'];

    protected function casts(): array
    {
        return ['version_number' => 'integer', 'snapshot' => 'array', 'submitted_at' => 'datetime', 'returned_at' => 'datetime'];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(CollegeQuarterlyReport::class, 'report_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function returnedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_by');
    }

    public function asReport(): CollegeQuarterlyReport
    {
        $report = clone $this->report;
        $report->fill([...Arr::only($this->snapshot, ['college', 'seminars', 'other_accomplishments', 'reviewed_by', 'reviewed_by_title', 'evidence']),
            'presentations' => $this->snapshot['manual_presentations'] ?? [], 'publications' => $this->snapshot['manual_publications'] ?? [],
            'manual_research' => $this->snapshot['manual_research'] ?? [],
            'ip_assets' => $this->snapshot['ip_assets'] ?? [],
            'snapshot' => $this->snapshot, 'submitted_at' => $this->submitted_at, 'prepared_by' => $this->submitted_by,
        ]);
        $report->setRelation('preparedBy', $this->submittedBy);

        return $report;
    }
}
