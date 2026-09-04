<?php

namespace App\Support;

use App\Models\ProposalVersionFile;
use Illuminate\Support\Str;

class ProposalRevisionTargetCatalog
{
    /**
     * @return list<array{value: string, label: string}>
     */
    public function forFile(ProposalVersionFile $file): array
    {
        $sourceData = is_array($file->source_data) ? $file->source_data : [];

        return match ($file->document_type) {
            ProposalVersionFile::TYPE_DETAILED_PROPOSAL => $this->detailedProposalTargets(),
            ProposalVersionFile::TYPE_WORK_PLAN => $this->workPlanTargets($sourceData),
            ProposalVersionFile::TYPE_LINE_ITEM_BUDGET => $this->lineItemBudgetTargets($sourceData),
            ProposalVersionFile::TYPE_EXPENSE_BREAKDOWN => $this->expenseBreakdownTargets($sourceData),
            ProposalVersionFile::TYPE_CURRICULUM_VITAE => $this->curriculumVitaeTargets($sourceData),
            default => [],
        };
    }

    public function labelFor(ProposalVersionFile $file, ?string $target): ?string
    {
        if (blank($target)) {
            return null;
        }

        $match = collect($this->forFile($file))->firstWhere('value', $target);

        return is_array($match) ? $match['label'] : null;
    }

    public function contains(ProposalVersionFile $file, ?string $target): bool
    {
        return $this->labelFor($file, $target) !== null;
    }

    /** @param array<string, mixed> $sourceData */
    public function targetForDraft(ProposalVersionFile $file, ?string $target, array $sourceData): ?string
    {
        if (! $this->contains($file, $target)) {
            return null;
        }

        $isRepeatingTarget = preg_match('/^(?:objective-\d|output-\d|activity-\d|work-plan-editor-\d|expense-.+-\d|staff-.+-\d|custom-.+-\d|cv-\d)/', $target) === 1;
        if ($isRepeatingTarget && $this->rowStructure($file->document_type, $file->source_data ?? []) !== $this->rowStructure($file->document_type, $sourceData)) {
            return null;
        }

        return $target;
    }

    /** @param array<string, mixed> $data @return array<mixed> */
    private function rowStructure(string $documentType, array $data): array
    {
        return match ($documentType) {
            ProposalVersionFile::TYPE_WORK_PLAN => [count($this->rows($data['entries'] ?? null))],
            ProposalVersionFile::TYPE_EXPENSE_BREAKDOWN => [count($this->rows($data['items'] ?? null))],
            ProposalVersionFile::TYPE_LINE_ITEM_BUDGET => array_map(
                fn (string $key): int => count($this->rows($data[$key] ?? null)),
                ['staff', 'custom_mooe_items', 'custom_co_items'],
            ),
            ProposalVersionFile::TYPE_CURRICULUM_VITAE => array_map(
                fn (array $person): array => array_map(
                    fn (string $key): int => max(count($this->rows($person[$key] ?? null)), (int) config("curriculum_vitae.sections.{$key}.default_rows", 0)),
                    array_keys(config('curriculum_vitae.sections', [])),
                ),
                $this->rows($data['people'] ?? null),
            ),
            default => [],
        };
    }

    /** @return list<array{value: string, label: string}> */
    private function detailedProposalTargets(): array
    {
        return [
            $this->target('research-agenda', 'Research agenda'),
            $this->target('leader-title', 'Project leader — professional title'),
            $this->target('leader-name', 'Project leader — name'),
            $this->target('leader-email', 'Project leader — email'),
            $this->target('leader-contact', 'Project leader — contact number'),
            $this->target('proponent-department', 'Proponent — department'),
            $this->target('proponent-college', 'Proponent — college'),
            $this->target('proponent-campus', 'Proponent — campus'),
            $this->target('cooperating-agency', 'Cooperating agency'),
            $this->target('executive-brief', 'Executive brief'),
            $this->target('rationale', 'Rationale'),
            $this->target('general-objective', 'General objective'),
            $this->target('specific-objectives', 'Specific objectives'),
            $this->target('expected-outputs', 'Expected outputs'),
            $this->target('introduction', 'Introduction'),
            $this->target('related-literature', 'Related studies and literature'),
            $this->target('methodology-research_design', 'Methodology — research design'),
            $this->target('methodology-specific-methods', 'Methodology — specific methods'),
            $this->target('methodology-data_analysis', 'Methodology — data analysis'),
            $this->target('responsibilities', 'Duties and responsibilities'),
            $this->target('checked-verified-by-name', 'Checked and verified by'),
            $this->target('recommending-approval-name', 'Recommending approval'),
            $this->target('approved-by-name', 'Approved by'),
            $this->target('references', 'References'),
        ];
    }

    /** @param array<string, mixed> $sourceData @return list<array{value: string, label: string}> */
    private function workPlanTargets(array $sourceData): array
    {
        $targets = [$this->target('work-plan-objectives-heading', 'Objectives and Gantt schedule')];

        foreach ($this->rows($sourceData['entries'] ?? null) as $index => $entry) {
            $number = $index + 1;
            $name = $this->rowName($entry['objective'] ?? null, "Entry {$number}");
            $targets[] = $this->target("objective-{$number}", "{$name} — objective");
            $targets[] = $this->target("output-{$number}", "{$name} — expected output");
            $targets[] = $this->target("activity-{$number}", "{$name} — activity");
            $targets[] = $this->target("work-plan-editor-{$number}", "{$name} — schedule months");
        }

        return $targets;
    }

    /** @param array<string, mixed> $sourceData @return list<array{value: string, label: string}> */
    private function lineItemBudgetTargets(array $sourceData): array
    {
        $targets = [
            $this->target('leader-campus', 'Project leader — campus'),
            $this->target('leader-college', 'Project leader — college'),
        ];
        $nextId = 0;

        foreach ($this->rows($sourceData['staff'] ?? null) as $index => $member) {
            $id = ++$nextId;
            $name = $this->rowName($member['name'] ?? null, 'Project staff '.($index + 1));
            $targets[] = $this->target("staff-name-{$id}", "{$name} — name");
            $targets[] = $this->target("staff-campus-{$id}", "{$name} — campus");
            $targets[] = $this->target("staff-college-{$id}", "{$name} — college");
        }

        foreach (config('line_item_budget.sections', []) as $section) {
            foreach ($section['items'] ?? [] as $item) {
                $targets[] = $this->target('amount-'.$item['key'], 'Budget amount — '.$item['label']);
            }
        }

        foreach (['mooe' => 'MOOE', 'co' => 'Capital outlay'] as $key => $label) {
            foreach ($this->rows($sourceData["custom_{$key}_items"] ?? null) as $index => $item) {
                $id = ++$nextId;
                $name = $this->rowName($item['particular'] ?? null, $label.' custom item '.($index + 1));
                $targets[] = $this->target("custom-{$key}-particular-{$id}", "{$name} — particular");
                $targets[] = $this->target("custom-{$key}-amount-{$id}", "{$name} — amount");
            }
        }

        foreach (['mooe' => 'MOOE', 'co' => 'capital outlay', 'project' => 'project'] as $key => $label) {
            if (filled($sourceData["{$key}_total_override"] ?? null)) {
                $targets[] = $this->target("{$key}-total-override", "Manual {$label} total");
            }
        }

        return [
            ...$targets,
            $this->target('level-of-call', 'Research Office — level of call'),
            $this->target('approval-body', 'Research Office — approving body'),
            $this->target('resolution-number', 'Research Office — resolution number'),
            $this->target('resolution-year', 'Research Office — resolution year'),
            $this->target('certified-by', 'Certified correct — name'),
            $this->target('certified-role', 'Certified correct — role'),
        ];
    }

    /** @param array<string, mixed> $sourceData @return list<array{value: string, label: string}> */
    private function expenseBreakdownTargets(array $sourceData): array
    {
        $targets = [];
        $fields = [
            'category' => 'expense type',
            'account' => 'account',
            'sub-account' => 'sub-account',
            'particulars' => 'particulars',
            'unit' => 'unit',
            'quantity' => 'quantity',
            'unit-cost' => 'unit cost',
            'details' => 'description / specifications',
            'purpose' => 'purpose in the project',
        ];

        foreach ($this->rows($sourceData['items'] ?? null) as $index => $item) {
            $id = $index + 1;
            $name = $this->rowName($item['particulars'] ?? null, 'Expense item '.$id);
            $account = collect(config('expense_breakdown.accounts.'.($item['category'] ?? 'mooe'), []))
                ->firstWhere('label', $item['account'] ?? null);
            $isContingency = ($account['is_contingency'] ?? false) === true;

            foreach ($fields as $anchor => $label) {
                if ($isContingency && in_array($anchor, ['particulars', 'unit', 'quantity', 'details'], true)) {
                    continue;
                }

                $targets[] = $this->target("expense-{$anchor}-{$id}", "{$name} — {$label}");
            }
        }

        return $targets;
    }

    /** @param array<string, mixed> $sourceData @return list<array{value: string, label: string}> */
    private function curriculumVitaeTargets(array $sourceData): array
    {
        $targets = [];
        $nextId = 0;
        $people = $this->rows($sourceData['people'] ?? null);
        $people = $people === [] ? [[]] : $people;
        $personalFields = [
            'last_name' => 'Last name',
            'first_name' => 'First name',
            'middle_name' => 'Middle name',
            'agency' => 'Agency',
            'birthday' => 'Birthday',
            'street' => 'Street',
            'barangay' => 'Barangay',
            'municipality' => 'Municipality',
            'province' => 'Province',
            'landline' => 'Landline number',
            'cellphone' => 'Cellphone number',
            'email' => 'Email address',
            'gender' => 'Gender',
        ];

        foreach ($people as $personIndex => $person) {
            $personId = ++$nextId;
            $personName = $this->personName($person, $personIndex + 1);
            $targets[] = $this->target("cv-{$personId}-personal", "{$personName} — personal information");

            foreach ($personalFields as $field => $label) {
                $targets[] = $this->target("cv-{$personId}-{$field}", "{$personName} — {$label}");
            }

            foreach (config('curriculum_vitae.sections', []) as $sectionKey => $section) {
                $targets[] = $this->target("cv-{$personId}-{$sectionKey}", "{$personName} — {$section['label']}");
                $rows = $this->rows($person[$sectionKey] ?? null);
                $rowCount = max(count($rows), (int) ($section['default_rows'] ?? 0));

                for ($rowIndex = 0; $rowIndex < $rowCount; $rowIndex++) {
                    $rowId = ++$nextId;
                    $row = $rows[$rowIndex] ?? [];

                    foreach ($section['fields'] ?? [] as $field) {
                        $fieldKey = $field['key'];
                        if (blank($row[$fieldKey] ?? null)) {
                            continue;
                        }

                        $targets[] = $this->target(
                            "cv-{$personId}-{$sectionKey}-{$rowId}-{$fieldKey}",
                            "{$personName} — {$section['label']} ".($rowIndex + 1).' — '.$field['label'],
                        );
                    }
                }
            }
        }

        return $targets;
    }

    /** @return array{value: string, label: string} */
    private function target(string $value, string $label): array
    {
        return compact('value', 'label');
    }

    private function rowName(mixed $value, string $fallback): string
    {
        $name = is_scalar($value) ? Str::squish(strip_tags((string) $value)) : '';

        return $name === '' ? $fallback : Str::limit($name, 64);
    }

    /** @return list<array<string, mixed>> */
    private function rows(mixed $rows): array
    {
        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    /** @param array<string, mixed> $person */
    private function personName(array $person, int $number): string
    {
        $name = Str::squish(implode(' ', array_filter([
            $person['first_name'] ?? null,
            $person['middle_name'] ?? null,
            $person['last_name'] ?? null,
        ])));

        return $name === '' ? "CV {$number}" : Str::limit($name, 64);
    }
}
