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
                'is_current' => false,
            ]);
        }
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
