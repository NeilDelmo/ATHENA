<?php

namespace App\Services;

use App\Models\CollegeQuarterlyReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

class CollegeReportLibrary
{
    public const SORT_OPTIONS = ['name' => 'Name', 'status' => 'Status', 'report_count' => 'Reports', 'submitted_at' => 'Last submitted', 'submitted_by' => 'Submitted by'];

    public function catalog(int $year, array $workspace = []): Collection
    {
        $reports = CollegeQuarterlyReport::whereHas('submissions')->with([
            'latestSubmission' => fn (HasOne $relation): HasOne => $relation->select($relation->getRelated()->qualifyColumns(['id', 'report_id', 'returned_at', 'submitted_at', 'submitted_by'])),
            'latestSubmission.submittedBy:id,name',
        ])->get(['id', 'college', 'reporting_year', 'reporting_quarter']);
        $years = $reports->pluck('reporting_year')->push(now()->year)->push($year);
        foreach ($workspace['items'] ?? [] as $item) {
            foreach (['id', 'source_id', 'parent_id'] as $key) {
                if (preg_match('/^(?:year|college|quarter):(20\d{2}|2100)(?::|$)/', $item[$key] ?? '', $match)) {
                    $years->push((int) $match[1]);
                }
            }
        }
        $nodes = collect();
        foreach ($years->unique()->sortDesc() as $folderYear) {
            $yearId = 'year:'.$folderYear;
            $nodes->put($yearId, $this->folder($yearId, (string) $folderYear, null, ['year' => $folderYear], 'College submissions'));
            $yearReports = $reports->where('reporting_year', $folderYear);
            $colleges = collect(User::COLLEGES)->merge($yearReports->pluck('college', 'college')->reject(fn (string $name): bool => in_array($name, User::COLLEGES, true)));
            foreach ($colleges as $code => $college) {
                $collegeId = 'college:'.$folderYear.':'.sha1($college);
                $collegeReports = $yearReports->where('college', $college);
                $nodes->put($collegeId, $this->folder($collegeId, $code, $yearId, ['year' => $folderYear, 'college' => $college], $college.' · '.$collegeReports->count().' '.str('report')->plural($collegeReports->count())));
                foreach ([1 => 'January–March', 2 => 'April–June', 3 => 'July–September', 4 => 'October–December'] as $quarter => $months) {
                    $quarterId = 'quarter:'.$folderYear.':'.sha1($college).':'.$quarter;
                    $report = $collegeReports->firstWhere('reporting_quarter', $quarter);
                    $state = $report ? ($report->latestSubmission->returned_at ? 'Returned for correction' : 'Submitted') : 'Not submitted';
                    $nodes->put($quarterId, $this->folder($quarterId, 'Q'.$quarter, $collegeId, ['year' => $folderYear, 'college' => $college, 'quarter' => $quarter], $months.' · '.$state));
                    if ($report) {
                        $id = 'report:'.$report->id;
                        $nodes->put($id, [
                            'id' => $id, 'source_id' => $id, 'parent_id' => $quarterId, 'kind' => 'file', 'report_id' => $report->id,
                            'name' => 'Q'.$quarter.' '.$folderYear.' accomplishment report', 'description' => $college,
                            'url' => route('college-quarterly-submissions.print', $report->latestSubmission),
                            'download_url' => route('college-quarterly-submissions.download', $report->latestSubmission),
                            'report_count' => 1, 'status' => $state,
                            'submitted_at' => $report->latestSubmission->submitted_at?->toISOString(),
                            'submitted_label' => $report->latestSubmission->submitted_at?->format('M j, Y · g:i A'),
                            'submitted_by' => $report->latestSubmission->submittedBy?->name,
                        ]);
                    }
                }
            }
        }

        return $nodes;
    }

    public function nodes(Collection $catalog, array $workspace): Collection
    {
        $nodes = clone $catalog;
        foreach ($workspace['items'] ?? [] as $item) {
            if ($catalog->has($item['source_id'])) {
                $nodes->put($item['id'], [...$catalog->get($item['source_id']), ...$item]);
            }
        }
        foreach ($nodes as $id => $node) {
            if ($node['kind'] === 'folder') {
                $nodes->put($id, [...$node, 'report_count' => 0, 'status' => 'No reports', 'submitted_at' => null, 'submitted_label' => null, 'submitted_by' => null]);
            }
        }
        foreach ($nodes->where('kind', 'file') as $file) {
            $parentId = $file['parent_id'];
            $visited = [];
            while ($parentId !== null && $nodes->has($parentId) && ! isset($visited[$parentId])) {
                $visited[$parentId] = true;
                $parent = $nodes[$parentId];
                $parent['report_count']++;
                $parent['status'] = $file['status'] === 'Returned for correction' || $parent['status'] === 'Correction needed' ? 'Correction needed' : 'Submitted';
                if ($file['submitted_at'] !== null && ($parent['submitted_at'] === null || $file['submitted_at'] > $parent['submitted_at'])) {
                    foreach (['submitted_at', 'submitted_label', 'submitted_by'] as $key) {
                        $parent[$key] = $file[$key];
                    }
                }
                $nodes->put($parentId, $parent);
                $parentId = $parent['parent_id'];
            }
        }
        foreach ($nodes->where('kind', 'folder') as $id => $folder) {
            $description = str($folder['description'])->beforeLast(' · ');
            $suffix = str_starts_with($folder['source_id'], 'quarter:')
                ? ($folder['status'] === 'Correction needed' ? 'Returned for correction' : ($folder['report_count'] ? 'Submitted' : 'No reports'))
                : $folder['report_count'].' '.str('report')->plural($folder['report_count']);
            $nodes->put($id, [...$folder, 'description' => $description.' · '.$suffix]);
        }

        return $nodes;
    }

    public function url(array $node): string
    {
        return $node['id'] === $node['source_id'] ? $node['url']
            : route('research_head.quarterly-reports.index', ['folder' => $node['id']]);
    }

    public function sort(Collection $items, string $field, string $direction): Collection
    {
        return $items->sort(function (array $first, array $second) use ($field, $direction): int {
            if ($first['kind'] !== $second['kind']) {
                return $first['kind'] === 'folder' ? -1 : 1;
            }
            $firstValue = $first[$field] ?? null;
            $secondValue = $second[$field] ?? null;
            if (($firstValue === null) !== ($secondValue === null)) {
                return $firstValue === null ? 1 : -1;
            }
            $comparison = $field === 'report_count' ? $firstValue <=> $secondValue : strnatcasecmp((string) $firstValue, (string) $secondValue);

            return ($direction === 'desc' ? -$comparison : $comparison) ?: strnatcasecmp($first['name'], $second['name']) ?: strcmp($first['id'], $second['id']);
        })->values();
    }

    private function folder(string $id, string $name, ?string $parentId, array $parameters, string $description): array
    {
        return [
            'id' => $id, 'source_id' => $id, 'parent_id' => $parentId, 'name' => $name, 'kind' => 'folder',
            'description' => $description, 'url' => route('research_head.quarterly-reports.index', $parameters),
        ];
    }
}
