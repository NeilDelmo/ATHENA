<?php

namespace App\Services;

use App\Models\CollegeQuarterlyReport;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Support\Arr;

class RmsQuarterlyReportData
{
    public const FIELDS = [
        'presentations' => [
            'presentation_title' => ['label' => 'Presentation Title', 'type' => 'text', 'required' => true],
            'presenter' => ['label' => 'Presenter Name', 'type' => 'text', 'required' => true],
            'college' => ['label' => 'College/Campus', 'type' => 'text', 'required' => true],
            'project_title' => ['label' => 'Project Title', 'type' => 'text'],
            'authors' => ['label' => 'Authors', 'type' => 'text', 'required' => true],
            'source_of_fund' => ['label' => 'Source of Fund', 'type' => 'select', 'required' => true, 'options' => ['Externally-funded', 'Institutionally-funded', 'Non-funded']],
            'project_status' => ['label' => 'Funded Project Status', 'type' => 'select', 'options' => ['Completed', 'On-going']],
            'sdgs' => ['label' => 'SDGs', 'type' => 'text', 'required' => true],
            'support_requested' => ['label' => 'Presentation Support Requested', 'type' => 'select', 'required' => true, 'options' => ['Yes', 'No', 'N/A']],
            'event_title' => ['label' => 'Event Title', 'type' => 'text', 'required' => true],
            'theme' => ['label' => 'Theme', 'type' => 'text'],
            'date' => ['label' => 'Date or Period', 'type' => 'text', 'required' => true],
            'venue' => ['label' => 'Venue', 'type' => 'text', 'required' => true],
            'classification' => ['label' => 'Classification', 'type' => 'select', 'required' => true, 'options' => ['Institutional', 'Local', 'Regional', 'National', 'International']],
            'organizer' => ['label' => 'Organizer Name/Email', 'type' => 'text'],
            'website' => ['label' => 'Website', 'type' => 'url'],
            'evidence_url' => ['label' => 'Supporting Documents Link', 'type' => 'url', 'required' => true],
        ],
        'publications' => [
            'article_title' => ['label' => 'Article Title', 'type' => 'text', 'required' => true],
            'project_title' => ['label' => 'Project Title', 'type' => 'text'],
            'authors' => ['label' => 'Authors', 'type' => 'text', 'required' => true],
            'college' => ['label' => 'College/Campus', 'type' => 'text', 'required' => true],
            'source_of_fund' => ['label' => 'Source of Fund', 'type' => 'select', 'required' => true, 'options' => ['Externally-funded', 'Institutionally-funded', 'Non-funded']],
            'project_status' => ['label' => 'Funded Project Status', 'type' => 'select', 'options' => ['Completed', 'On-going']],
            'sdgs' => ['label' => 'SDGs', 'type' => 'text', 'required' => true],
            'support_requested' => ['label' => 'Publication Support/Incentive Requested', 'type' => 'select', 'required' => true, 'options' => ['Yes', 'No', 'Incentive only']],
            'journal_title' => ['label' => 'Journal or Conference Proceeding Title', 'type' => 'text', 'required' => true],
            'publication_type' => ['label' => 'Publication Type', 'type' => 'select', 'required' => true, 'options' => ['Journal', 'Conference Proceeding']],
            'issn_isbn' => ['label' => 'ISSN/ISBN', 'type' => 'text'],
            'indexing' => ['label' => 'Indexing', 'type' => 'select', 'options' => ['Scopus', 'Web of Science', 'Asean Citation Index', 'Others']],
            'date' => ['label' => 'Publication Date', 'type' => 'date', 'required' => true],
            'volume_issue' => ['label' => 'Volume No./Issue No.', 'type' => 'text'],
            'pages' => ['label' => 'Pages', 'type' => 'text'],
            'publisher' => ['label' => 'Publisher', 'type' => 'text'],
            'evidence_url' => ['label' => 'Supporting Documents Link', 'type' => 'url', 'required' => true],
        ],
        'ip_assets' => [
            'title' => ['label' => 'IP Asset Title', 'type' => 'text', 'required' => true],
            'type' => ['label' => 'Asset Type', 'type' => 'select', 'required' => true, 'options' => ['Invention/Patent', 'Utility Model', 'Industrial Design', 'Copyright']],
            'inventors' => ['label' => 'Inventor/Author Names', 'type' => 'text', 'required' => true],
            'college' => ['label' => 'College/Campus', 'type' => 'text', 'required' => true],
            'date' => ['label' => 'Date Submitted', 'type' => 'date', 'required' => true],
            'remarks' => ['label' => 'Remarks', 'type' => 'text'],
            'evidence_url' => ['label' => 'Supporting Documents Link', 'type' => 'url', 'required' => true],
        ],
    ];

    /** @param array<string, array<string, string|null>> $details
     * @return array<string, mixed>
     */
    public function build(int $year, int $quarter, array $details = []): array
    {
        $data = ['presentations' => [], 'publications' => [], 'ip_assets' => [], 'sources' => []];
        $sources = [];
        $reports = CollegeQuarterlyReport::query()->where('reporting_year', $year)->where('reporting_quarter', $quarter)
            ->whereNotNull('submitted_at')->with('latestSubmission')->orderBy('college')->orderBy('id')->get();

        foreach ($reports as $report) {
            $submission = $report->latestSubmission;
            if ($submission?->returned_at !== null) {
                continue;
            }
            $snapshot = $submission?->snapshot ?? $report->snapshot ?? [];
            $college = (string) ($snapshot['college'] ?? $report->college);
            $sourceId = $submission !== null ? 's'.$submission->id : 'r'.$report->id;
            $sources[] = [$report->id, $submission?->id, $report->submitted_at->toISOString(), $snapshot];
            $data['sources'][] = [
                'college' => $college, 'report_id' => $report->id, 'submission_id' => $submission?->id,
                'version' => $submission?->version_number ?? 1, 'submitted_at' => $submission?->submitted_at ?? $report->submitted_at,
            ];

            foreach (self::FIELDS as $section => $fields) {
                $rows = $section === 'ip_assets' ? ($snapshot['ip_assets'] ?? [])
                    : ($snapshot['manual_'.$section] ?? $this->legacyRows($section, $snapshot[$section] ?? []));
                foreach ($rows as $index => $row) {
                    $prefix = match ($section) {
                        'presentations' => 'presentation', 'publications' => 'publication', default => 'ip',
                    };
                    $key = $prefix.'-'.$sourceId.'-'.($row['id'] ?? $index);
                    $values = array_fill_keys(array_keys($fields), '');
                    $values = [...$values, ...$this->sourceValues($section, $row),
                        ...Arr::except(Arr::only($details[$key] ?? [], array_keys($fields)), ['college']), 'college' => $college];
                    $values = array_map(fn (mixed $value): string => (string) ($value ?? ''), $values);
                    if (blank($values['evidence_url']) && $submission !== null && filled($row['id'] ?? null)) {
                        $proofIndex = collect($snapshot['evidence'] ?? [])->search(fn (array $proof): bool => ($proof['section'] ?? null) === $section
                            && ($proof['row_id'] ?? null) === $row['id']);
                        if ($proofIndex !== false) {
                            $values['evidence_url'] = route('college-quarterly-submissions.evidence', [$submission, $proofIndex]);
                        }
                    }
                    if (($values['source_of_fund'] ?? '') === 'Non-funded') {
                        $values['project_status'] = '';
                        if ($section === 'presentations') {
                            $values['support_requested'] = 'N/A';
                        }
                    }
                    $missing = [];
                    foreach ($fields as $field => $definition) {
                        $required = ($definition['required'] ?? false)
                            || ($field === 'project_status' && in_array($values['source_of_fund'] ?? '', ['Externally-funded', 'Institutionally-funded'], true));
                        if ($required && blank($values[$field])) {
                            $missing[] = $definition['label'];
                        }
                    }
                    $data[$section][] = [
                        'key' => $key, 'report_id' => $report->id, 'submission_id' => $submission?->id,
                        'college' => $college, 'values' => $values, 'missing' => $missing,
                    ];
                }
            }
        }

        $data['missing_colleges'] = array_values(array_diff(array_values(User::COLLEGES), array_column($data['sources'], 'college')));
        $data['source_token'] = hash('sha256', json_encode($sources, JSON_THROW_ON_ERROR));

        return $data;
    }

    /** @param array<string, mixed> $row
     * @return array<string, string>
     */
    private function sourceValues(string $section, array $row): array
    {
        return match ($section) {
            'presentations' => [
                'presentation_title' => $row['research_title'] ?? '', 'presenter' => $row['personnel'] ?? '',
                'project_title' => $row['research_title'] ?? '', 'event_title' => $row['event'] ?? '',
                'date' => $this->date($row['date'] ?? ''), 'venue' => $row['place'] ?? '',
            ],
            'publications' => [
                'article_title' => $row['research_title'] ?? '', 'project_title' => $row['research_title'] ?? '',
                'authors' => $row['personnel'] ?? '', 'journal_title' => $row['event'] ?? '', 'date' => $this->date($row['date'] ?? ''),
            ],
            default => Arr::only($row, ['title', 'type', 'inventors', 'date', 'remarks', 'evidence_url']),
        };
    }

    /** @param list<list<string>> $rows
     * @return list<array<string, string>>
     */
    private function legacyRows(string $section, array $rows): array
    {
        return array_map(fn (array $row): array => [
            'event' => $row[0] ?? '', 'research_title' => $row[1] ?? '', 'date' => $row[2] ?? '',
            'place' => $section === 'presentations' ? ($row[3] ?? '') : '',
            'personnel' => $row[$section === 'presentations' ? 4 : 3] ?? '',
        ], $rows);
    }

    private function date(string $value): string
    {
        if ($value === '') {
            return '';
        }
        try {
            return CarbonImmutable::parse($value)->toDateString();
        } catch (InvalidFormatException) {
            return $value;
        }
    }
}
