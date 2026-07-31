<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
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
        $expectedOutputs = is_array($validated['expected_outputs'] ?? null)
            ? $validated['expected_outputs']
            : [];
        $methodology = is_array($validated['methodology'] ?? null)
            ? $validated['methodology']
            : [];
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
            'objectives' => self::narrative($validated['objectives'] ?? ''),
            'expected_outputs' => collect(config('detailed_proposal.expected_outputs'))
                ->mapWithKeys(fn (string $label, string $key): array => [
                    $key => self::narrative($expectedOutputs[$key] ?? ''),
                ])
                ->all(),
            'introduction' => self::narrative($validated['introduction'] ?? ''),
            'related_literature' => self::narrative($validated['related_literature'] ?? ''),
            'methodology' => [
                'research_design' => self::narrative($methodology['research_design'] ?? ''),
                'specific_methods' => self::narrative($methodology['specific_methods'] ?? ''),
                'data_analysis' => self::narrative($methodology['data_analysis'] ?? ''),
            ],
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
        $value = str_replace(["\r\n", "\r"], "\n", self::validXml((string) $value));

        return trim((string) preg_replace('/[ \t]+$/m', '', $value));
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
