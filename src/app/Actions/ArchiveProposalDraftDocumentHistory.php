<?php

namespace App\Actions;

use App\Models\ProposalDraft;
use App\Models\ProposalDraftDocumentVersion;
use App\Models\TopicProposal;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ArchiveProposalDraftDocumentHistory
{
    public function handle(
        ProposalDraft $draft,
        TopicProposal $topic,
        string $permanentDirectory,
    ): void {
        $versions = ProposalDraftDocumentVersion::query()
            ->whereBelongsTo($draft, 'draft')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($versions as $version) {
            $archivedPath = $this->archiveFile($draft, $version, $permanentDirectory);

            $version->update([
                'proposal_draft_id' => null,
                'proposal_draft_document_id' => null,
                'topic_id' => $topic->getKey(),
                'file_path' => $archivedPath,
                'source_data' => is_array($version->source_data)
                    ? $this->archiveFigureSources($draft, $version->source_data, $permanentDirectory)
                    : $version->source_data,
                'is_current' => false,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $source
     * @return array<string, mixed>
     */
    public function archiveFigureSources(ProposalDraft $draft, array $source, string $permanentDirectory): array
    {
        if (! is_array($source['methodology_images'] ?? null)) {
            return $source;
        }

        $disk = Storage::disk('local');
        $prefix = $draft->storageDirectory().'/detailed-proposal/methodology-images/';

        foreach ($source['methodology_images'] as $index => $image) {
            $path = is_array($image) ? ($image['path'] ?? null) : null;
            if (! is_string($path) || ! Str::startsWith($path, $prefix)
                || str_contains(substr($path, strlen($prefix)), '/') || str_contains($path, '\\')
                || ! $disk->exists($path)) {
                continue;
            }

            $archivedPath = $permanentDirectory.'/figures/'.hash('sha256', $path).'.'.pathinfo($path, PATHINFO_EXTENSION);
            if (! $disk->exists($archivedPath) && ! $disk->copy($path, $archivedPath)) {
                throw ValidationException::withMessages([
                    'history' => 'A saved proposal figure could not be archived. Please try submitting again.',
                ]);
            }

            $source['methodology_images'][$index]['path'] = $archivedPath;
        }

        return $source;
    }

    private function archiveFile(
        ProposalDraft $draft,
        ProposalDraftDocumentVersion $version,
        string $permanentDirectory,
    ): ?string {
        if (! $version->hasStoredFile()) {
            return null;
        }

        $sourcePath = $version->file_path;

        // Older revision replacements deleted recovery PDFs while retaining their saved form data.
        if (
            $version->action !== ProposalDraftDocumentVersion::ACTION_SUBMITTED
            && filled($version->source_data)
            && Str::startsWith($sourcePath, $draft->storageDirectory().'/')
            && ! Storage::disk('local')->exists($sourcePath)
        ) {
            return null;
        }

        if (
            ! Str::startsWith($sourcePath, $draft->storageDirectory().'/')
            || ! Storage::disk('local')->exists($sourcePath)
        ) {
            throw ValidationException::withMessages([
                'history' => 'A saved paper version could not be archived. Please try turning in the proposal again.',
            ]);
        }

        $extension = strtolower(pathinfo($version->original_filename, PATHINFO_EXTENSION))
            ?: strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION))
            ?: 'pdf';
        $archivedPath = $permanentDirectory
            .'/draft-history/'
            .$version->document_type
            .'/'.Str::uuid().'.'.$extension;

        if (! Storage::disk('local')->copy($sourcePath, $archivedPath)) {
            throw ValidationException::withMessages([
                'history' => 'A saved paper version could not be archived. Please try turning in the proposal again.',
            ]);
        }

        return $archivedPath;
    }
}
