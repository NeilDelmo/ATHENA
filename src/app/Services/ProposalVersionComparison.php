<?php

namespace App\Services;

use App\Models\ProposalVersion;
use App\Models\ProposalVersionFile;
use App\Support\CurriculumVitaeRules;
use App\Support\DetailedProposalRules;
use App\Support\ExpenseBreakdownRules;
use App\Support\LineItemBudgetRules;
use App\Support\WorkPlanRules;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ProposalVersionComparison
{
    /** @return list<array<string, mixed>> */
    public function compare(?ProposalVersion $previous, ?ProposalVersion $latest): array
    {
        if ($previous === null || $latest === null) {
            return [];
        }

        $beforeFiles = $previous->files->keyBy(fn (ProposalVersionFile $file): string => $file->document_type.':'.$file->position);
        $afterFiles = $latest->files->keyBy(fn (ProposalVersionFile $file): string => $file->document_type.':'.$file->position);
        $papers = [];

        foreach ($beforeFiles->keys()->merge($afterFiles->keys())->unique() as $key) {
            $beforeFile = $beforeFiles->get($key);
            $afterFile = $afterFiles->get($key);
            $file = $afterFile ?? $beforeFile;
            $rules = $this->paperRules($file->document_type);

            if ($rules === []) {
                continue;
            }

            $before = $this->sourceData($beforeFile, $previous);
            $after = $this->sourceData($afterFile, $latest);
            $structured = ($beforeFile === null || is_array($before) && $before !== [])
                && ($afterFile === null || is_array($after) && $after !== []);
            $sections = [];

            if ($structured) {
                $fields = array_unique(array_map(fn (string $field): string => explode('.', $field)[0], array_keys($rules)));

                foreach (array_diff($fields, ['methodology_images', 'literature_research_history', 'literature_citations']) as $field) {
                    // Missing identity metadata in older snapshots does not establish a deletion.
                    if ($beforeFile !== null && $afterFile !== null
                        && in_array($field, ['project_title', 'project_leader'], true)
                        && (! array_key_exists($field, $before) || ! array_key_exists($field, $after))) {
                        continue;
                    }
                    $beforeText = $this->text($before[$field] ?? null);
                    $afterText = $this->text($after[$field] ?? null);

                    if ($beforeText === $afterText) {
                        continue;
                    }

                    $sections[] = [
                        'field' => $field,
                        'label' => Str::headline($field),
                        'before' => $this->paragraphs($beforeText, $afterText),
                        'after' => $this->paragraphs($afterText, $beforeText),
                    ];
                }
            }

            $figures = $structured ? $this->figures($before['methodology_images'] ?? [], $after['methodology_images'] ?? []) : [];
            $fileChanged = $beforeFile === null || $afterFile === null || $beforeFile->checksum !== $afterFile->checksum;

            if ($sections !== [] || $figures !== [] || $fileChanged) {
                $papers[] = [
                    'label' => $file->label(), 'mode' => $structured ? 'structured' : 'file',
                    'file_changed' => $fileChanged, 'sections' => $sections, 'figures' => $figures,
                ];
            }
        }

        return $papers;
    }

    /** @return array<string, mixed>|null */
    private function sourceData(?ProposalVersionFile $file, ProposalVersion $version): ?array
    {
        $source = $file?->source_data;
        if (! is_array($source)) {
            return null;
        }
        if ($file->document_type !== ProposalVersionFile::TYPE_DETAILED_PROPOSAL || $source === []) {
            return $source;
        }
        if (! array_key_exists('project_title', $source) && $version->title !== null) {
            $source['project_title'] = $version->title;
        }
        if (is_array($source['responsibilities'] ?? null)) {
            $source['responsibilities'] = array_map(
                fn (mixed $row): mixed => is_array($row) ? Arr::except($row, ['percentage_fixed']) : $row,
                $source['responsibilities'],
            );
        }

        return $source;
    }

    /** @return array<string, mixed> */
    private function paperRules(string $type): array
    {
        return match ($type) {
            ProposalVersionFile::TYPE_DETAILED_PROPOSAL => DetailedProposalRules::rules(),
            ProposalVersionFile::TYPE_WORK_PLAN => WorkPlanRules::rules(),
            ProposalVersionFile::TYPE_LINE_ITEM_BUDGET => LineItemBudgetRules::rules(),
            ProposalVersionFile::TYPE_EXPENSE_BREAKDOWN => ExpenseBreakdownRules::rules(),
            ProposalVersionFile::TYPE_CURRICULUM_VITAE => CurriculumVitaeRules::rules(),
            default => [],
        };
    }

    private function text(mixed $value, int $depth = 0): string
    {
        if (is_array($value)) {
            if ($depth > 8) {
                return 'Nested content';
            }

            $lines = [];
            foreach ($value as $key => $item) {
                $text = $this->text($item, $depth + 1);
                if ($text !== '') {
                    $lines[] = (is_int($key) ? ($key + 1).'. ' : Str::headline($key).': ').$text;
                }
            }

            return implode("\n", $lines);
        }

        if (! is_scalar($value)) {
            return '';
        }

        $text = preg_replace('/<\s*(?:br\s*\/?|\/p|\/div|\/li|\/h[1-6])\s*>/i', "\n", (string) $value);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return trim(preg_replace('/[\t \x{00a0}]+/u', ' ', $text) ?? $text);
    }

    /** @return list<array{text: string, changed: bool}> */
    private function paragraphs(string $text, string $other): array
    {
        $paragraphs = preg_split('/\R+/u', $text, flags: PREG_SPLIT_NO_EMPTY) ?: [];
        $remaining = array_count_values(preg_split('/\R+/u', $other, flags: PREG_SPLIT_NO_EMPTY) ?: []);

        return array_map(function (string $paragraph) use (&$remaining): array {
            $changed = ($remaining[$paragraph] ?? 0) === 0;
            $remaining[$paragraph] = max(0, ($remaining[$paragraph] ?? 0) - 1);

            return ['text' => $paragraph, 'changed' => $changed];
        }, $paragraphs);
    }

    /** @return list<array{status: string, before: string, after: string}> */
    private function figures(mixed $before, mixed $after): array
    {
        $normalize = fn (mixed $images) => collect(is_array($images) ? $images : [])
            ->filter(fn (mixed $image): bool => is_array($image))->values()->mapWithKeys(fn (array $image, int $index): array => [
                (string) ($image['id'] ?? 'legacy-'.$index) => [
                    'identity' => Arr::only($image, ['path', 'stored_path', 'caption', 'section', 'size', 'alignment', 'original_filename']),
                    'text' => 'Figure '.($index + 1).': '.($image['caption'] ?? 'Untitled figure').' · '.Str::headline($image['section'] ?? 'methodology'),
                ],
            ]);
        $before = $normalize($before);
        $after = $normalize($after);
        $changes = [];

        foreach ($before->keys()->merge($after->keys())->unique() as $key) {
            $old = $before->get($key);
            $new = $after->get($key);
            if ($old === $new) {
                continue;
            }
            $changes[] = ['status' => $old === null ? 'added' : ($new === null ? 'removed' : 'updated'), 'before' => $old['text'] ?? '', 'after' => $new['text'] ?? ''];
        }

        return $changes;
    }
}
