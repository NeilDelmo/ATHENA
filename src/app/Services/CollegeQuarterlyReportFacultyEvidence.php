<?php

namespace App\Services;

use App\Models\CollegeQuarterlyReport;
use App\Models\TopicProposal;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class CollegeQuarterlyReportFacultyEvidence
{
    public function __construct(private readonly ProjectReportEvidence $evidence, private readonly MonitoringQuarterService $quarters) {}

    /** @return list<array<string, mixed>> */
    public function forQuarter(User $coordinator, int $year, int $quarter): array
    {
        if (blank($coordinator->college)) {
            return [];
        }
        $period = $this->quarters->forYearAndQuarter($year, $quarter);

        return TopicProposal::query()->withIssuedNotice()
            ->where('notice_to_proceed_issued_at', '<=', $period['end'])
            ->whereHas('user', fn (Builder $owner): Builder => $owner->where('college', $coordinator->college))
            ->with(['progressReports.submitter', 'narrativeReports.submitter'])
            ->orderBy('title')->orderBy('id')->get()
            ->flatMap(fn (TopicProposal $topic): array => $this->evidence->forProject($topic, $period['start'], $period['end']))
            ->unique('path')->values()->all();
    }

    /**
     * @param  list<array<string, mixed>>  $evidence
     * @param  list<string>  $storedPaths
     * @return list<array<string, mixed>>
     */
    public function freeze(CollegeQuarterlyReport $report, array $evidence, array &$storedPaths): array
    {
        $disk = Storage::disk('local');

        return array_map(function (array $file) use ($report, $disk, &$storedPaths): array {
            $extension = pathinfo($file['path'], PATHINFO_EXTENSION);
            $path = 'college-quarterly-reports/'.$report->id.'/faculty-evidence/'.Str::uuid().($extension !== '' ? '.'.$extension : '');
            if (! $disk->copy($file['path'], $path)) {
                throw new RuntimeException('The faculty evidence could not be preserved with the accomplishment report.');
            }
            $storedPaths[] = $path;
            unset($file['url'], $file['download_url'], $file['uploaded_at'], $file['uploaded_by']);

            return [...$file, 'path' => $path];
        }, $evidence);
    }
}
