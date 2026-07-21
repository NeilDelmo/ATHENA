<?php

namespace App\Support;

class DetailedProposalData
{
    /**
     * @param  array<string, mixed>  $validated
     * @param  array{mooe_total?: float|int, co_total?: float|int}  $budgetTotals
     * @return array<string, mixed>
     */
    public static function fromValidated(array $validated, array $budgetTotals = []): array
    {
        return [
            'project_title' => self::text($validated['project_title']),
            'research_agenda' => self::text($validated['research_agenda']),
            'sdgs' => collect($validated['sdgs'])
                ->map(fn (mixed $sdg): int => (int) $sdg)
                ->unique()
                ->sort()
                ->values()
                ->all(),
            'project_leader' => self::text($validated['project_leader']),
            'leader_email' => self::text($validated['leader_email']),
            'leader_contact' => self::text($validated['leader_contact']),
            'staff' => self::rows($validated['staff'] ?? [], ['name', 'email', 'contact']),
            'proponent_agency' => (string) config('detailed_proposal.proponent_agency'),
            'proponent_department' => self::text($validated['proponent_department'] ?? ''),
            'proponent_college' => self::text($validated['proponent_college']),
            'proponent_campus' => self::text($validated['proponent_campus']),
            'cooperating_agency' => self::text($validated['cooperating_agency'] ?? ''),
            'executive_brief' => self::narrative($validated['executive_brief']),
            'rationale' => self::narrative($validated['rationale']),
            'objectives' => self::narrative($validated['objectives']),
            'expected_outputs' => collect(config('detailed_proposal.expected_outputs'))
                ->mapWithKeys(fn (string $label, string $key): array => [
                    $key => self::narrative($validated['expected_outputs'][$key] ?? ''),
                ])
                ->all(),
            'related_literature' => self::narrative($validated['related_literature']),
            'methodology' => [
                'research_design' => self::narrative($validated['methodology']['research_design']),
                'specific_methods' => self::narrative($validated['methodology']['specific_methods']),
                'data_analysis' => self::narrative($validated['methodology']['data_analysis']),
            ],
            'responsibilities' => self::rows($validated['responsibilities'], ['name', 'duties'], true),
            'mooe_total' => round((float) ($budgetTotals['mooe_total'] ?? 0), 2),
            'co_total' => round((float) ($budgetTotals['co_total'] ?? 0), 2),
            'references' => self::narrative($validated['references']),
        ];
    }

    private static function text(mixed $value): string
    {
        return trim(self::validXml((string) $value));
    }

    private static function narrative(mixed $value): string
    {
        $value = str_replace(["\r\n", "\r"], "\n", self::validXml((string) $value));

        return trim((string) preg_replace('/[ \t]+$/m', '', $value));
    }

    /**
     * @param  array<int, mixed>  $rows
     * @param  list<string>  $fields
     * @return list<array<string, string>>
     */
    private static function rows(array $rows, array $fields, bool $narrative = false): array
    {
        return collect($rows)
            ->filter(fn (mixed $row): bool => is_array($row))
            ->map(fn (array $row): array => collect($fields)->mapWithKeys(
                fn (string $field): array => [
                    $field => $narrative && $field === 'duties'
                        ? self::narrative($row[$field] ?? '')
                        : self::text($row[$field] ?? ''),
                ],
            )->all())
            ->filter(fn (array $row): bool => collect($row)->contains(fn (string $value): bool => $value !== ''))
            ->values()
            ->all();
    }

    private static function validXml(string $value): string
    {
        return (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
    }
}
