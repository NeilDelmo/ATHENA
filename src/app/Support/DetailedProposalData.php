<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Number;
use Illuminate\Support\Str;

class DetailedProposalData
{
    /**
     * @param  array<string, mixed>  $validated
     * @param  array{mooe_total?: float|int, co_total?: float|int}  $budgetTotals
     * @return array<string, mixed>
     */
    public static function fromValidated(array $validated, array $budgetTotals = []): array
    {
        $objectives = self::objectives(
            $validated['general_objective'] ?? '',
            $validated['specific_objectives'] ?? null,
            $validated['objectives'] ?? '',
        );
        $expectedOutputs = self::expectedOutputs($validated['expected_outputs'] ?? []);
        $methodology = is_array($validated['methodology'] ?? null)
            ? $validated['methodology']
            : [];
        $specificMethodObjectives = self::specificMethodObjectives(
            $validated['specific_method_objectives'] ?? [],
        );
        $specificMethods = self::specificMethodsNarrative(
            $specificMethodObjectives,
            $methodology['specific_methods'] ?? '',
        );
        $methodologyImages = self::methodologyImages($validated['methodology_images'] ?? []);
        $projectLeader = self::text($validated['project_leader'] ?? '');
        $leaderTitle = self::text($validated['leader_title'] ?? '');
        $staff = self::rows($validated['staff'] ?? [], ['title', 'name', 'email', 'contact']);

        return [
            'project_title' => self::text($validated['project_title'] ?? ''),
            'research_agenda' => self::text($validated['research_agenda'] ?? ''),
            'sdgs' => collect($validated['sdgs'] ?? [])
                ->map(fn (mixed $sdg): int => (int) $sdg)
                ->unique()
                ->sort()
                ->values()
                ->all(),
            'project_leader' => $projectLeader,
            'leader_title' => $leaderTitle,
            'project_leader_display' => self::titledName($leaderTitle, $projectLeader),
            'leader_email' => self::text($validated['leader_email'] ?? ''),
            'leader_contact' => self::text($validated['leader_contact'] ?? ''),
            'staff' => collect($staff)
                ->map(fn (array $member): array => [
                    ...$member,
                    'display_name' => self::titledName($member['title'], $member['name']),
                ])
                ->all(),
            'proponent_agency' => (string) config('detailed_proposal.proponent_agency'),
            'proponent_department' => self::text($validated['proponent_department'] ?? ''),
            'proponent_college' => self::text($validated['proponent_college'] ?? ''),
            'proponent_campus' => self::text($validated['proponent_campus'] ?? ''),
            'cooperating_agency' => self::text($validated['cooperating_agency'] ?? ''),
            'executive_brief' => self::narrative($validated['executive_brief'] ?? ''),
            'rationale' => self::narrative($validated['rationale'] ?? ''),
            'general_objective' => $objectives['general_objective'],
            'specific_objectives' => $objectives['specific_objectives'],
            'expected_outputs' => $expectedOutputs,
            'introduction' => self::narrative($validated['introduction'] ?? ''),
            'related_literature' => self::narrative($validated['related_literature'] ?? ''),
            'methodology' => [
                'research_design' => self::narrative($methodology['research_design'] ?? ''),
                'specific_methods' => $specificMethods,
                'data_analysis' => self::narrative($methodology['data_analysis'] ?? ''),
            ],
            'specific_method_objectives' => $specificMethodObjectives,
            'methodology_images' => $methodologyImages,
            'responsibilities' => self::rows($validated['responsibilities'] ?? [], ['name', 'percentage', 'duties'], true),
            'checked_verified_by_name' => self::text($validated['checked_verified_by_name'] ?? ''),
            'recommending_approval_name' => self::text($validated['recommending_approval_name'] ?? ''),
            'approved_by_name' => self::text($validated['approved_by_name'] ?? ''),
            'mooe_total' => round((float) ($budgetTotals['mooe_total'] ?? 0), 2),
            'co_total' => round((float) ($budgetTotals['co_total'] ?? 0), 2),
            'references' => self::narrative($validated['references'] ?? ''),
        ];
    }

    private static function text(mixed $value): string
    {
        return trim(self::validXml((string) $value));
    }

    private static function narrative(mixed $value): string
    {
        return app(ProposalRichText::class)->sanitize(self::validXml((string) $value));
    }

    /**
     * @return array{general_objective: string, specific_objectives: list<array{description: string}>}
     */
    private static function objectives(mixed $generalObjective, mixed $specificObjectives, mixed $legacyObjectives): array
    {
        $general = self::narrative($generalObjective);
        $objectiveRows = is_array($specificObjectives)
            ? collect($specificObjectives)->map(
                fn (mixed $objective): string => self::plainText(is_array($objective) ? ($objective['description'] ?? '') : ''),
            )
            : collect(preg_split('/\R+/u', (string) $legacyObjectives) ?: [])
                ->map(fn (string $objective): string => self::plainText($objective));
        $section = self::plainText($general) === '' ? null : 'specific';
        $specific = [];

        foreach ($objectiveRows as $objective) {
            $objective = preg_replace('/^\s*(?:\d+[.)]|[-\x{2022}])\s*/u', '', $objective) ?: '';

            if ($objective === '') {
                continue;
            }

            if (preg_match('/^general objectives?:?$/iu', $objective) === 1) {
                $section = 'general';

                continue;
            }

            if (preg_match('/^specific objectives?:?$/iu', $objective) === 1) {
                $section = 'specific';

                continue;
            }

            if ($section === 'general' && self::plainText($general) === '') {
                $general = self::narrative($objective);

                continue;
            }

            $specific[] = ['description' => $objective];
        }

        return [
            'general_objective' => $general,
            'specific_objectives' => collect($specific)
                ->filter(fn (array $objective): bool => $objective['description'] !== '')
                ->values()
                ->all(),
        ];
    }

    /**
     * @return list<array{description: string}>
     */
    private static function specificObjectives(mixed $specificObjectives, mixed $legacyObjectives): array
    {
        $objectives = collect(is_array($specificObjectives) ? $specificObjectives : [])
            ->map(fn (mixed $objective): array => [
                'description' => self::narrative(is_array($objective) ? ($objective['description'] ?? '') : ''),
            ])
            ->filter(fn (array $objective): bool => $objective['description'] !== '')
            ->values();

        if ($objectives->isNotEmpty()) {
            return $objectives->all();
        }

        return collect(preg_split('/\R+/u', self::plainText((string) $legacyObjectives)) ?: [])
            ->map(fn (string $objective): string => preg_replace('/^\s*(?:\d+[.)]|[-â€¢])\s*/u', '', $objective) ?: '')
            ->map(fn (string $objective): array => ['description' => self::narrative($objective)])
            ->filter(fn (array $objective): bool => $objective['description'] !== '')
            ->values()
            ->all();
    }

    /**
     * @return array<string, list<array{quantity: ?int, unit: string, description: string}>>
     */
    private static function expectedOutputs(mixed $outputs): array
    {
        $outputs = is_array($outputs) ? $outputs : [];

        return collect(config('detailed_proposal.expected_outputs'))
            ->mapWithKeys(function (string $label, string $key) use ($outputs): array {
                $value = $outputs[$key] ?? [];
                $entries = is_string($value)
                    ? [['quantity' => null, 'unit' => '', 'description' => $value]]
                    : (is_array($value) ? $value : []);

                return [$key => collect($entries)
                    ->filter(fn (mixed $entry): bool => is_array($entry))
                    ->map(fn (array $entry): array => [
                        'quantity' => null,
                        'unit' => '',
                        'description' => self::expectedOutputDescription($entry),
                    ])
                    ->filter(fn (array $entry): bool => $entry['description'] !== '')
                    ->values()
                    ->all()];
            })
            ->all();
    }

    /**
     * @return list<array{heading: string, methods: list<array{description: string}>}>
     */
    private static function specificMethodObjectives(mixed $value): array
    {
        return collect(is_array($value) ? $value : [])
            ->filter(fn (mixed $group): bool => is_array($group))
            ->map(fn (array $group): array => [
                'heading' => self::plainText((string) ($group['heading'] ?? '')),
                'methods' => collect(is_array($group['methods'] ?? null) ? $group['methods'] : [])
                    ->filter(fn (mixed $method): bool => is_array($method))
                    ->map(fn (array $method): array => [
                        'description' => self::plainText((string) ($method['description'] ?? '')),
                    ])
                    ->filter(fn (array $method): bool => $method['description'] !== '')
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<array{heading: string, methods: list<array{description: string}>}>  $specificMethodObjectives
     */
    private static function specificMethodsNarrative(
        array $specificMethodObjectives,
        mixed $legacyValue,
    ): string {
        $hasStructuredMethods = collect($specificMethodObjectives)
            ->contains(fn (array $group): bool => $group['methods'] !== []);

        if (! $hasStructuredMethods) {
            return self::narrative((string) $legacyValue);
        }

        $html = collect($specificMethodObjectives)
            ->map(function (array $group, int $index): string {
                $headingDescription = $group['heading'];
                $methods = $group['methods'];

                if ($headingDescription === '' || $methods === []) {
                    return '';
                }

                $heading = htmlspecialchars(self::alphabeticLabel($index).'. '.$headingDescription, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $items = collect($methods)
                    ->map(function (array $method): string {
                        $description = htmlspecialchars($method['description'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

                        return '<li>'.str_replace(["\r\n", "\r", "\n"], '<br>', $description).'</li>';
                    })
                    ->implode('');

                return '<p><strong>'.$heading.'</strong></p><ol>'.$items.'</ol>';
            })
            ->filter()
            ->implode('');

        return self::narrative($html);
    }

    private static function alphabeticLabel(int $index): string
    {
        $value = $index + 1;
        $label = '';

        while ($value > 0) {
            $value--;
            $label = chr(65 + ($value % 26)).$label;
            $value = intdiv($value, 26);
        }

        return $label;
    }

    /** @param array<string, mixed> $entry */
    private static function expectedOutputDescription(array $entry): string
    {
        $description = self::plainText($entry['description'] ?? '');
        $unit = self::text($entry['unit'] ?? '');
        $quantity = filled($entry['quantity'] ?? null) ? (int) $entry['quantity'] : null;

        if ($quantity === null) {
            return trim($unit.' '.$description);
        }

        if (str_contains($description, '('.number_format($quantity).')')) {
            return $description;
        }

        $quantityLabel = ucfirst(Number::spell($quantity, 'en')).' ('.number_format($quantity).')';

        return trim($quantityLabel.' '.$unit.' '.$description);
    }

    private static function plainText(string $value): string
    {
        $value = preg_replace('/<(?:br\s*\/?>|\/p|\/div|\/li)>/iu', "\n", self::validXml($value)) ?? $value;

        return trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    }

    private static function titledName(mixed $title, mixed $name): string
    {
        return trim(self::text($title).' '.Str::upper(self::text($name)));
    }

    /**
     * @return list<array{id: string, section: string, alignment: string, size: string, caption: string, stored_path: ?string, mime_type: ?string, original_filename: ?string, image: ?UploadedFile}>
     */
    private static function methodologyImages(mixed $images): array
    {
        $sections = ['research_design'];

        return collect($images)
            ->filter(fn (mixed $image): bool => is_array($image))
            ->filter(fn (array $image): bool => in_array($image['section'] ?? null, $sections, true))
            ->map(function (array $image): array {
                $uploadedImage = $image['image'] ?? null;
                $storedPath = $image['stored_path'] ?? null;

                return [
                    'id' => filled($image['id'] ?? null) ? self::text($image['id']) : Str::uuid()->toString(),
                    'section' => self::text($image['section'] ?? ''),
                    'alignment' => in_array($image['alignment'] ?? null, ['left', 'center', 'right'], true)
                        ? $image['alignment']
                        : 'center',
                    'size' => in_array($image['size'] ?? null, ['small', 'medium', 'large'], true)
                        ? $image['size']
                        : 'medium',
                    'caption' => self::text($image['caption'] ?? ''),
                    'stored_path' => is_string($storedPath) ? $storedPath : null,
                    'mime_type' => is_string($image['mime_type'] ?? null) ? $image['mime_type'] : null,
                    'original_filename' => is_string($image['original_filename'] ?? null) ? $image['original_filename'] : null,
                    'image' => $uploadedImage instanceof UploadedFile ? $uploadedImage : null,
                ];
            })
            ->filter(fn (array $image): bool => $image['image'] instanceof UploadedFile || filled($image['stored_path']))
            ->values()
            ->all();
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
