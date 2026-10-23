<?php

namespace App\Services;

use App\Models\CollegeQuarterlyReport;
use App\Models\RmsQuarterlyReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

class RmsQuarterlyReportStatus
{
    /** @return array<string, mixed> */
    public function forPeriod(int $year, int $quarter, ?RmsQuarterlyReport $report = null): array
    {
        $sources = $this->sources()->where('reporting_year', $year)->where('reporting_quarter', $quarter)->get();

        return $this->summarize($sources, $report);
    }

    /**
     * @param  list<int>  $years
     * @param  Collection<array-key, RmsQuarterlyReport>  $reports
     * @return Collection<string, array<string, mixed>>
     */
    public function forYears(array $years, Collection $reports): Collection
    {
        $sources = $this->sources()->whereIn('reporting_year', $years)->get()
            ->groupBy(fn (CollegeQuarterlyReport $report): string => $report->reporting_year.'-'.$report->reporting_quarter);
        $reports = $reports->keyBy(fn (RmsQuarterlyReport $report): string => $report->reporting_year.'-'.$report->reporting_quarter);
        $statuses = collect();
        foreach ($years as $year) {
            foreach (range(1, 4) as $quarter) {
                $key = $year.'-'.$quarter;
                $statuses->put($key, $this->summarize($sources->get($key, collect()), $reports->get($key)));
            }
        }

        return $statuses;
    }

    /** @param array<string, mixed> $status */
    public function fingerprint(array $status, ?RmsQuarterlyReport $report): string
    {
        return hash('sha256', json_encode([
            $status['source_token'], $report?->campus ?? 'ARASOF-Nasugbu',
            $report?->signatories ?? [], $report?->row_details ?? [],
        ], JSON_THROW_ON_ERROR));
    }

    /** @return Builder<CollegeQuarterlyReport> */
    private function sources(): Builder
    {
        return CollegeQuarterlyReport::query()
            ->select(['id', 'college', 'reporting_year', 'reporting_quarter', 'submitted_at', 'updated_at'])
            ->with(['latestSubmission' => fn (HasOne $relation): HasOne => $relation->select(
                $relation->getRelated()->qualifyColumns(['id', 'report_id', 'submitted_at', 'returned_at']),
            )])->orderBy('college')->orderBy('id');
    }

    /**
     * @param  Collection<array-key, CollegeQuarterlyReport>  $sources
     * @return array<string, mixed>
     */
    private function summarize(Collection $sources, ?RmsQuarterlyReport $report): array
    {
        $colleges = array_fill_keys(array_values(User::COLLEGES), 'pending');
        $versions = [];
        foreach ($sources as $source) {
            $submission = $source->latestSubmission;
            $state = $submission?->returned_at !== null ? 'returned' : ($source->submitted_at !== null ? 'submitted' : 'pending');
            if (array_key_exists($source->college, $colleges)) {
                $colleges[$source->college] = $state;
            }
            if ($state === 'submitted') {
                $versions[] = [$source->id, $source->college, $submission?->id, $source->submitted_at->toISOString(),
                    $submission?->submitted_at?->toISOString() ?? $source->updated_at?->toISOString()];
            }
        }
        $missing = array_keys(array_filter($colleges, fn (string $state): bool => $state !== 'submitted'));
        $status = [
            'submitted_count' => count($colleges) - count($missing), 'total_count' => count($colleges),
            'missing_colleges' => $missing, 'ready' => $missing === [], 'colleges' => $colleges,
            'source_token' => hash('sha256', json_encode($versions, JSON_THROW_ON_ERROR)),
            'downloaded_at' => $report?->downloaded_at,
        ];
        $status['outdated'] = $report?->downloaded_at !== null
            && ! hash_equals($report->downloaded_fingerprint ?? '', $this->fingerprint($status, $report));

        return $status;
    }
}
