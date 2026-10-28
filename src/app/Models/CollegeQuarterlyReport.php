<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\CollegeQuarterlyReportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CollegeQuarterlyReport extends Model
{
    /** @use HasFactory<CollegeQuarterlyReportFactory> */
    use HasFactory;

    public const IP_ASSET_TYPES = ['Invention/Patent', 'Utility Model', 'Industrial Design', 'Copyright'];

    protected $fillable = [
        'college', 'reporting_year', 'reporting_quarter', 'prepared_by', 'presentations', 'publications', 'seminars', 'manual_research', 'ip_assets',
        'other_accomplishments', 'reviewed_by', 'reviewed_by_title', 'evidence', 'snapshot', 'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'reporting_year' => 'integer', 'reporting_quarter' => 'integer',
            'presentations' => 'array', 'publications' => 'array', 'seminars' => 'array', 'manual_research' => 'array', 'ip_assets' => 'array', 'evidence' => 'array', 'snapshot' => 'array', 'submitted_at' => 'datetime',
        ];
    }

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function draftToken(): string
    {
        return hash('sha256', json_encode([
            $this->presentations ?? [], $this->publications ?? [], $this->seminars ?? [],
            $this->manual_research ?? [], $this->ip_assets ?? [], $this->evidence ?? [],
            $this->other_accomplishments ?? '', $this->reviewed_by ?? '', $this->reviewed_by_title ?? '',
        ], JSON_THROW_ON_ERROR));
    }

    /** @return list<list<string>> */
    public function activityRows(string $section): array
    {
        $rows = $section === 'ip_assets' && $this->submitted_at !== null
            ? ($this->snapshot['ip_assets'] ?? []) : ($this->getAttribute($section) ?? []);

        return collect($rows)->map(function (array $row) use ($section): array {
            if ($section === 'manual_research') {
                return [$row['title'] ?? '', $row['duration'] ?? '', $row['personnel'] ?? '', $row['status'] ?? ''];
            }
            $date = filled($row['date'] ?? null) ? CarbonImmutable::parse($row['date'])->format('F j, Y') : '';

            return match ($section) {
                'ip_assets' => [$row['title'] ?? '', $row['type'] ?? '', $row['inventors'] ?? '', $date, implode("\n", array_filter([$row['remarks'] ?? '', $row['evidence_url'] ?? ''], fn (?string $value): bool => filled($value)))],
                'presentations' => [$row['event'] ?? '', $row['research_title'] ?? '', $date, $row['place'] ?? '', $row['personnel'] ?? ''],
                'publications' => [$row['event'] ?? '', $row['research_title'] ?? '', $date, $row['personnel'] ?? ''],
                default => [$row['event'] ?? '', $date, $row['place'] ?? '', $row['personnel'] ?? ''],
            };
        })->values()->all();
    }

    /** @param array<string, mixed> $evidence */
    public function evidenceLabel(array $evidence): string
    {
        $section = $evidence['section'] ?? null;
        $label = match ($section) {
            'presentations' => 'a. Presentation',
            'publications' => 'b. Publication',
            'seminars' => 'c. Seminar/Training',
            default => null,
        };
        if ($label === null) {
            return $evidence['caption'] ?? '';
        }

        $rows = collect($this->getAttribute($section) ?? []);
        $index = $rows->search(fn (array $row): bool => ($row['id'] ?? null) === ($evidence['row_id'] ?? null));
        $row = $index !== false ? $rows[$index] : [];
        $title = $row['research_title'] ?? $row['event'] ?? $evidence['folder'] ?? '';
        if (blank($title)) {
            $title = $row['event'] ?? $evidence['folder'] ?? '';
        }

        return implode(' · ', array_filter([$label, $index !== false ? 'Row '.($index + 1) : null, $title, $evidence['caption'] ?? null], fn ($value): bool => filled($value)));
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(CollegeQuarterlyReportSubmission::class, 'report_id');
    }

    public function latestSubmission(): HasOne
    {
        return $this->submissions()->one()->latestOfMany();
    }
}
