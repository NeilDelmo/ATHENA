<?php

namespace App\Support;

use App\Models\ProposalVersionFile;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ProposalRevisionFileScope
{
    /** @return array<string, list<string>> */
    private function inputFields(): array
    {
        return [
            ProposalVersionFile::TYPE_DETAILED_PROPOSAL => ['detailed_proposal', 'document'],
            ProposalVersionFile::TYPE_WORK_PLAN => ['work_plan'],
            ProposalVersionFile::TYPE_LINE_ITEM_BUDGET => ['line_item_budget'],
            ProposalVersionFile::TYPE_EXPENSE_BREAKDOWN => ['expense_breakdown'],
            ProposalVersionFile::TYPE_CURRICULUM_VITAE => ['curricula_vitae'],
            ProposalVersionFile::TYPE_GAD_CHECKLIST => ['gad_checklist'],
        ];
    }

    /**
     * @param  Collection<int, string>  $requestedDocumentTypes
     * @return array<string, string>
     */
    public function unexpectedUploadErrors(Request $request, Collection $requestedDocumentTypes): array
    {
        return collect($this->inputFields())
            ->reject(fn (array $fields, string $documentType): bool => $requestedDocumentTypes->contains($documentType))
            ->flatten()
            ->filter(fn (string $field): bool => $request->hasFile($field))
            ->mapWithKeys(fn (string $field): array => [
                $field => 'Only files marked for revision by the Research Head can be replaced in this submission.',
            ])
            ->all();
    }

    /**
     * @template TKey of array-key
     * @template TValue
     *
     * @param  Collection<TKey, TValue>  $stagedFiles
     * @param  Collection<int, string>  $requestedDocumentTypes
     * @return Collection<TKey, TValue>
     */
    public function requestedStagedFiles(Collection $stagedFiles, Collection $requestedDocumentTypes): Collection
    {
        return $stagedFiles->filter(
            fn (mixed $stagedFile, string|int $documentType): bool => $requestedDocumentTypes->contains((string) $documentType),
        );
    }

    /**
     * @param  Collection<int, string>  $requestedDocumentTypes
     * @return Collection<string, string>
     */
    public function noChangeResponses(Request $request, Collection $requestedDocumentTypes): Collection
    {
        $resolutions = $request->input('revision_resolutions', []);

        return $requestedDocumentTypes
            ->filter(fn (string $documentType): bool => data_get($resolutions, $documentType.'.action') === 'no_change')
            ->mapWithKeys(function (string $documentType) use ($resolutions): array {
                $response = trim((string) data_get($resolutions, $documentType.'.explanation'));

                return $response === '' ? [] : [$documentType => $response];
            });
    }

    /**
     * @param  Collection<int, object>  $pendingRevisions
     * @param  Collection<string, object>  $stagedFiles
     * @return array<string, string>
     */
    public function unresolvedErrors(Request $request, Collection $pendingRevisions, Collection $stagedFiles): array
    {
        $resolutions = $request->input('revision_resolutions', []);

        return $pendingRevisions
            ->groupBy('document_type')
            ->mapWithKeys(function (Collection $revisions, string $documentType) use ($request, $stagedFiles, $resolutions): array {
                $noChangeSelected = data_get($resolutions, $documentType.'.action') === 'no_change';
                $response = trim((string) data_get($resolutions, $documentType.'.explanation'));

                if ($noChangeSelected) {
                    if ($this->hasManualUpload($request, $documentType)) {
                        return [$this->primaryInput($documentType) => 'Remove the replacement upload when choosing no file change needed.'];
                    }

                    return $response === ''
                        ? ['revision_resolutions.'.$documentType.'.explanation' => 'Explain why this comment does not require a file change.']
                        : [];
                }

                if ($this->hasManualUpload($request, $documentType)) {
                    return [];
                }

                $stagedFile = $stagedFiles->get($documentType);
                if (! $stagedFile) {
                    return [$this->primaryInput($documentType) => $this->unresolvedMessage($documentType)];
                }

                $unchanged = $revisions->contains(function ($revision) use ($stagedFile): bool {
                    $originalSource = is_array($revision->file?->source_data) ? $revision->file->source_data : [];
                    $revisedSource = is_array($stagedFile->source_data) ? $stagedFile->source_data : [];

                    return $originalSource === $revisedSource;
                });

                return $unchanged ? [$this->primaryInput($documentType) => $this->unresolvedMessage($documentType)] : [];
            })
            ->all();
    }

    /**
     * @param  Collection<int, object>  $pendingRevisions
     * @param  array<int, array<string, mixed>>  $replacementFiles
     * @return array<string, string>
     */
    public function unchangedReplacementErrors(Collection $pendingRevisions, array $replacementFiles): array
    {
        $replacements = collect($replacementFiles);

        return $pendingRevisions
            ->filter(function ($revision) use ($replacements): bool {
                if (blank($revision->file?->checksum)) {
                    return false;
                }

                $candidate = $replacements
                    ->where('document_type', $revision->document_type)
                    ->first(fn (array $file): bool => (int) ($file['position'] ?? 0) === (int) ($revision->file?->position ?? 0));

                return $candidate
                    && filled($candidate['checksum'] ?? null)
                    && hash_equals((string) $revision->file->checksum, (string) $candidate['checksum']);
            })
            ->mapWithKeys(fn ($revision): array => [
                $this->primaryInput($revision->document_type) => $this->unchangedUploadMessage($revision->document_type),
            ])
            ->all();
    }

    private function hasManualUpload(Request $request, string $documentType): bool
    {
        return collect($this->inputFields()[$documentType] ?? [])
            ->contains(fn (string $field): bool => $request->hasFile($field));
    }

    private function primaryInput(string $documentType): string
    {
        return $this->inputFields()[$documentType][0] ?? 'document';
    }

    private function unresolvedMessage(string $documentType): string
    {
        return 'Modify or replace the '.$this->documentLabel($documentType).', or explain why no file change is needed.';
    }

    private function unchangedUploadMessage(string $documentType): string
    {
        return 'The uploaded '.$this->documentLabel($documentType).' is identical to the returned file. Make a change, or choose no file change needed and explain why.';
    }

    private function documentLabel(string $documentType): string
    {
        return match ($documentType) {
            ProposalVersionFile::TYPE_DETAILED_PROPOSAL => 'detailed proposal',
            ProposalVersionFile::TYPE_WORK_PLAN => 'work plan',
            ProposalVersionFile::TYPE_LINE_ITEM_BUDGET => 'line-item budget',
            ProposalVersionFile::TYPE_EXPENSE_BREAKDOWN => 'expense breakdown',
            ProposalVersionFile::TYPE_CURRICULUM_VITAE => 'curriculum vitae file',
            ProposalVersionFile::TYPE_GAD_CHECKLIST => 'GAD checklist',
            default => str($documentType)->replace('_', ' ')->lower()->toString(),
        };
    }
}
