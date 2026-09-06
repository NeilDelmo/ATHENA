<?php

namespace App\Support;

use App\Models\ProposalVersionFile;

class ProposalRevisionSectionCatalog
{
    /** @return list<array{value: string, label: string, pattern: string}> */
    public function forType(string $type, array $source = []): array
    {
        $sections = match ($type) {
            ProposalVersionFile::TYPE_DETAILED_PROPOSAL => [
                ['project-information', 'I. Research Project Title', 'I\.\s*Research Project Title'],
                ['research-agenda', 'II. Research Agenda', 'II\.\s*BatStateU\s*Research Agenda'],
                ['sdgs', 'III. Sustainable Development Goal', 'III\.\s*Sustainable Development Goal'],
                ['project-team', 'IV. Project Leader and Staff', 'IV\.\s*Project Leader'],
                ['proponent', 'V. Proponent Agency', 'V\.\s*Proponent Agency'],
                ['cooperating-agency', 'VI. Cooperating Agency', 'VI\.\s*Cooperating Agency'],
                ['executive-brief', 'VII. Executive Brief', 'VII\.\s*Executive Brief'],
                ['rationale', 'VIII. Rationale', 'VIII\.\s*Rationale'],
                ['objectives', 'IX. Objectives of the Project', 'IX\.\s*Objectives of the Project'],
                ['expected-outputs', 'X. Expected Output of the Project', 'X\.\s*Expected Output'],
                ['literature', 'XI. Introduction and Related Literature', 'XI\.\s*(?:Review of Related|Introduction)'],
                ['methodology', 'XII. Methodology', 'XII\.\s*Methodology'],
                ['responsibilities', 'XIII. Duties and Responsibilities', 'XIII\.\s*Duties and Responsibilities'],
                ['work-plan', 'XIV. Major Activities / Work Plan', 'XIV\.\s*Major Activities'],
                ['budget', 'XV. Line-Item Budget', 'XV\.\s*Line\s*[-–]?\s*Item Budget'],
                ['references', 'XVI. References', 'XVI\.\s*References'],
                ['curriculum-vitae', 'XVII. Curriculum Vitae', 'XVII\.\s*Curriculum Vitae'],
                ['signatories', 'Approval Signatories', '(?:Checked and\s*Verified\s*by|Approved by the Research Council)'],
            ],
            ProposalVersionFile::TYPE_WORK_PLAN => [
                ['project-information', 'Project Information', '(?:Program|Project) Title\s*:'],
                ['schedule', 'Objectives and Gantt Schedule', 'Objectives(?:\s|$)'],
                ['signatories', 'Signatories', 'Prepared\s+by\s*:'],
            ],
            ProposalVersionFile::TYPE_LINE_ITEM_BUDGET => [
                ['project-information', 'Project Information', '(?:Program|Project) Title\s*:'],
                ['project-team', 'Project Leader and Staff', 'Project Leader\s*:'],
                ['mooe', 'I. Maintenance and Other Operating Expenses', 'I\.\s*Maintenance and Other Operating Expenses'],
                ['co', 'II. Capital Outlays', 'II\.\s*Capital Outlay'],
                ['totals', 'Total Project Cost', 'TOTAL PROJECT COST'],
                ['research-office', 'Research Office', 'To be accomplished by the Research Office'],
            ],
            ProposalVersionFile::TYPE_EXPENSE_BREAKDOWN => [
                ['project-information', 'Project Information', '(?:Program|Project) Title\s*:'],
                ['expense-items', 'Expense Items', '(?:Particulars|I\.\s*Maintenance|II\.\s*Capital|MAINTENANCE AND OTHER OPERATING|CAPITAL OUTLAY)'],
                ['totals', 'Total Estimated Expenses', '(?:TOTAL ESTIMATED|GRAND TOTAL|TOTAL PROJECT COST|TOTAL MOOE and CAPITAL OUTLAY)'],
            ],
            default => [],
        };

        if ($type === ProposalVersionFile::TYPE_CURRICULUM_VITAE) {
            foreach (array_values($source['people'] ?? [[]]) as $index => $person) {
                $number = $index + 1;
                $sections[] = ["cv-{$number}-personal", "CV {$number} · Personal Information", 'PERSONAL INFORMATION'];
                foreach (config('curriculum_vitae.sections', []) as $key => $section) {
                    $sections[] = ["cv-{$number}-{$key}", "CV {$number} · {$section['label']}", preg_quote(trim(explode('(', $section['label'])[0]), '~')];
                }
            }
        }

        return array_map(fn (array $section): array => [
            'value' => 'section-'.$section[0],
            'label' => $section[1],
            'pattern' => '~^\s*'.$section[2].'~iu',
        ], $sections);
    }
}
