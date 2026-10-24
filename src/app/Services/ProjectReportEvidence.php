<?php

namespace App\Services;

use App\Models\ProjectNarrativeReport;
use App\Models\ProjectProgressReport;
use App\Models\TopicProposal;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProjectReportEvidence
{
    private const MIME_TYPES = [
        'application/pdf', 'application/msword', 'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/x-ole-storage', 'application/vnd.ms-office', 'image/jpeg', 'image/png',
    ];

    /** @return list<array<string, mixed>> */
    public function forProject(TopicProposal $topic, ?CarbonInterface $start = null, ?CarbonInterface $end = null, ?User $viewer = null): array
    {
        if ($viewer?->isUsingWorkspace(User::WORKSPACE_RESEARCH_SECRETARY) && ! $topic->canReviewImplementationReports($viewer)) {
            return [];
        }

        $topic->loadMissing(['progressReports.submitter', 'narrativeReports.submitter']);
        $quarters = app(MonitoringQuarterService::class);
        $sources = [
            'monitoring' => $topic->progressReports->sortByDesc(fn (ProjectProgressReport $report): string => sprintf('%010d-%020d', $report->version_number, $report->id)),
            'narrative' => $topic->narrativeReports->where('report_type', 'progress')->sortByDesc('id'),
        ];
        $entries = [];
        foreach ($sources as $kind => $reports) {
            $reports = $reports->filter(fn (ProjectProgressReport|ProjectNarrativeReport $report): bool => $report->isSubmitted()
                && (! $viewer?->isUsingWorkspace(User::WORKSPACE_RESEARCH_HEAD) || $report->isAvailableToResearchHead()))
                ->unique(function (ProjectProgressReport|ProjectNarrativeReport $report) use ($quarters, $topic): ?string {
                    $date = $report->reporting_date ?? $report->period_end;

                    return $date ? $quarters->forDate($date, $topic)['start']->toDateString() : null;
                })->filter(function (ProjectProgressReport|ProjectNarrativeReport $report) use ($start, $end): bool {
                    $date = $report->reporting_date ?? $report->period_end;

                    return ($start === null || $date?->toDateString() >= $start->toDateString())
                        && ($end === null || $date?->toDateString() <= $end->toDateString());
                });
            foreach ($reports as $report) {
                $reportingDate = $report->reporting_date ?? $report->period_end;
                $label = $kind === 'monitoring' ? 'Monitoring Tool' : 'Progress Report';
                $rows = $kind === 'monitoring' ? $report->work_plan : $report->accomplishments;
                foreach ($rows ?? [] as $index => $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    foreach ($row['evidence'] ?? [] as $file) {
                        if (! is_array($file) || ! $this->isStoredEvidence($topic, $kind, $file) || isset($entries[$file['path']])) {
                            continue;
                        }
                        $path = $file['path'];
                        $mimeType = Storage::disk('local')->mimeType($path);
                        $downloadUrl = route($kind === 'monitoring' ? 'project-progress.evidence' : 'project-narrative-reports.evidence', [$topic, $file['id']]);
                        $entries[$path] = [
                            'path' => $path,
                            'caption' => implode(' · ', array_filter([$topic->title, $label, $report->reporting_period_label ?: $reportingDate?->format('M j, Y'),
                                $row[$kind === 'monitoring' ? 'activity' : 'objective'] ?? '', $file['name']], fn (mixed $value): bool => filled($value))),
                            'mime_type' => $mimeType,
                            'original_filename' => $file['name'],
                            'project_id' => $topic->id,
                            'source' => 'faculty_report',
                            'source_key' => hash('sha256', $kind.':'.$report->id.':'.$index.':'.$file['id']),
                            'url' => $downloadUrl.(in_array($mimeType, ['image/jpeg', 'image/png'], true) ? '?preview=1' : ''),
                            'download_url' => $downloadUrl,
                            'reporting_date' => $reportingDate?->toDateString(),
                            'uploaded_by' => $report->submitter?->name,
                            'uploaded_at' => $report->submitted_at ?? $report->created_at,
                            'file_size' => Storage::disk('local')->size($path),
                            'checksum' => $file['checksum'],
                        ];
                    }
                }
            }
        }

        return array_values($entries);
    }

    /** @param array<string, mixed> $file */
    public function isStoredEvidence(TopicProposal $topic, string $kind, array $file): bool
    {
        $prefix = match ($kind) {
            'monitoring' => 'progress-reports',
            'narrative' => 'narrative-progress-reports',
            default => null,
        };
        $path = $file['path'] ?? null;

        return $prefix !== null && is_string($path)
            && str_starts_with($path, $prefix.'/'.$topic->id.'/evidence/')
            && ! str_contains($path, '..') && ! str_contains($path, '\\')
            && is_string($file['id'] ?? null) && Str::isUuid($file['id'])
            && is_string($file['name'] ?? null) && $file['name'] !== ''
            && is_string($file['checksum'] ?? null) && preg_match('/\A[a-f0-9]{64}\z/', $file['checksum']) === 1
            && Storage::disk('local')->exists($path)
            && in_array(Storage::disk('local')->mimeType($path), self::MIME_TYPES, true)
            && hash_equals($file['checksum'], hash_file('sha256', Storage::disk('local')->path($path)));
    }
}
