<?php

namespace App\Support;

use App\Models\ProposalDraftDocument;
use App\Models\ProposalDraftDocumentVersion;
use Illuminate\Support\Number;
use Illuminate\Support\Str;

class ProposalDocumentVersionDiff
{
    public function __construct(
        private readonly ProposalPaperCatalog $catalog,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function isEquivalent(ProposalDraftDocument $document, array $attributes): bool
    {
        foreach ([
            'source_data',
            'original_filename',
            'mime_type',
            'file_size',
            'checksum',
        ] as $attribute) {
            $incomingValue = array_key_exists($attribute, $attributes)
                ? $attributes[$attribute]
                : $document->getAttribute($attribute);
            $storedValue = $document->getAttribute($attribute);

            if ($attribute === 'source_data') {
                if ($this->canonicalize($storedValue) !== $this->canonicalize($incomingValue)) {
                    return false;
                }

                continue;
            }

            if ($storedValue !== $incomingValue) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<array{field: string, label: string, before: string, after: string}>
     */
    public function changes(
        ?ProposalDraftDocumentVersion $previous,
        ProposalDraftDocument $document,
    ): array {
        if ($previous === null) {
            return [];
        }

        $changes = [];

        if ($previous->original_filename !== $document->original_filename) {
            $changes[] = $this->change(
                'original_filename',
                'File name',
                $previous->original_filename,
                $document->original_filename,
            );
        }

        if ($previous->checksum !== $document->checksum) {
            $changes[] = [
                'field' => 'checksum',
                'label' => 'File contents',
                'before' => $previous->checksum ? 'Previous PDF contents' : 'No uploaded PDF',
                'after' => $document->checksum ? 'Updated PDF contents' : 'No uploaded PDF',
            ];
        }

        if ($previous->file_size !== $document->file_size) {
            $changes[] = [
                'field' => 'file_size',
                'label' => 'File size',
                'before' => $previous->file_size ? Number::fileSize($previous->file_size) : 'Not available',
                'after' => $document->file_size ? Number::fileSize($document->file_size) : 'Not available',
            ];
        }

        $previousSource = $previous->source_data ?? [];
        $currentSource = $document->source_data ?? [];
        $sourceKeys = collect([...array_keys($previousSource), ...array_keys($currentSource)])
            ->unique()
            ->values();

        foreach ($sourceKeys as $key) {
            $before = $previousSource[$key] ?? null;
            $after = $currentSource[$key] ?? null;

            if ($before === $after) {
                continue;
            }

            $changes[] = $this->change(
                'source_data.'.$key,
                $this->fieldLabel($key),
                $before,
                $after,
            );
        }

        return $changes;
    }

    /**
     * @param  list<array{field: string, label: string, before: string, after: string}>  $changes
     */
    public function summary(
        ProposalDraftDocument $document,
        ?ProposalDraftDocumentVersion $previous,
        array $changes,
        string $action,
        ?ProposalDraftDocumentVersion $restoredFrom,
    ): string {
        $label = $this->catalog->label($document->document_type)
            ?? Str::headline($document->document_type);

        if ($action === 'restored' && $restoredFrom !== null) {
            return 'Restored '.$label.' from version '.$restoredFrom->version_number.'.';
        }

        if ($action === 'captured') {
            return 'Captured the existing '.$label.' as version history.';
        }

        if ($action === 'removed') {
            return 'Removed '.$label.' from the proposal draft.';
        }

        if (filled($document->file_path)) {
            return $previous === null
                ? 'Uploaded '.$document->original_filename.'.'
                : 'Replaced '.$label.' with '.$document->original_filename.'.';
        }

        if ($previous === null) {
            return 'Completed '.$label.'.';
        }

        $changedFieldCount = count($changes);

        return 'Updated '.$label.' ('.$changedFieldCount.' '.Str::plural('field', $changedFieldCount).' changed).';
    }

    /** @return array{field: string, label: string, before: string, after: string} */
    private function change(string $field, string $label, mixed $before, mixed $after): array
    {
        return [
            'field' => $field,
            'label' => $label,
            'before' => $this->displayValue($before, false),
            'after' => $this->displayValue($after, true),
        ];
    }

    private function displayValue(mixed $value, bool $updated): string
    {
        if ($value === null || $value === '') {
            return 'Not provided';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_array($value)) {
            if ($value === []) {
                return 'None';
            }

            if (array_is_list($value) && collect($value)->every(fn (mixed $item): bool => is_scalar($item))) {
                return Str::limit(collect($value)->join(', '), 160);
            }

            if (! array_is_list($value) && collect($value)->every(fn (mixed $item): bool => is_scalar($item) || $item === null)) {
                return Str::limit(collect($value)
                    ->map(fn (mixed $item, string|int $key): string => Str::headline((string) $key).': '.($item ?? 'None'))
                    ->join('; '), 160);
            }

            return $updated
                ? 'Updated content ('.count($value).' '.Str::plural('item', count($value)).')'
                : 'Previous content ('.count($value).' '.Str::plural('item', count($value)).')';
        }

        return Str::limit(Str::squish((string) $value), 160);
    }

    private function fieldLabel(string $field): string
    {
        return match ($field) {
            'sdgs' => 'Sustainable Development Goals',
            'leader_email' => 'Project leader email',
            'leader_contact' => 'Project leader contact',
            'staff' => 'Project staff',
            'executive_brief' => 'Executive brief',
            'expected_outputs' => 'Expected outputs',
            'related_literature' => 'Related literature',
            'custom_mooe_items' => 'Custom MOOE items',
            'custom_co_items' => 'Custom capital-outlay items',
            'people' => 'Curriculum vitae entries',
            'entries' => 'Work-plan entries',
            'items' => 'Estimated expense items',
            default => Str::headline($field),
        };
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->canonicalize($item), $value);
        }

        ksort($value);

        return collect($value)
            ->map(fn (mixed $item): mixed => $this->canonicalize($item))
            ->all();
    }
}
