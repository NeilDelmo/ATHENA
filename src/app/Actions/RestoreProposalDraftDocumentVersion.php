<?php

namespace App\Actions;

use App\Models\ProposalDraft;
use App\Models\ProposalDraftDocument;
use App\Models\ProposalDraftDocumentVersion;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class RestoreProposalDraftDocumentVersion
{
    public function __construct(
        private readonly SaveProposalDraftDocument $saveDocument,
    ) {}

    /** @return array{document: ProposalDraftDocument, version_created: bool} */
    public function handle(
        ProposalDraft $draft,
        ProposalDraftDocumentVersion $version,
        User $actor,
        int $expectedDocumentVersion,
        ?string $changeNote,
    ): array {
        if ($version->proposal_draft_id !== $draft->getKey() || $version->topic_id !== null) {
            abort(404);
        }

        $currentDocument = $draft->documents()
            ->where('document_type', $version->document_type)
            ->where('position', $version->position)
            ->first();
        $currentLockVersion = $currentDocument?->lock_version ?? 0;
        $restoredPath = null;

        if ($version->hasStoredFile()) {
            if (! Str::startsWith($version->file_path, $draft->storageDirectory().'/')
                || ! Storage::disk('local')->exists($version->file_path)) {
                throw ValidationException::withMessages([
                    'version' => 'The stored file for this version is no longer available.',
                ]);
            }

            $extension = Str::lower(pathinfo($version->file_path, PATHINFO_EXTENSION));
            $restoredPath = $draft->storageDirectory().'/'.$version->document_type.'/'.Str::uuid().'.'.$extension;

            if (! Storage::disk('local')->copy($version->file_path, $restoredPath)) {
                throw ValidationException::withMessages([
                    'version' => 'The selected file version could not be restored.',
                ]);
            }
        }

        try {
            $document = $this->saveDocument->handle(
                $draft,
                $actor,
                $version->document_type,
                $version->position,
                $expectedDocumentVersion,
                [
                    'source_data' => $version->source_data,
                    'file_path' => $restoredPath,
                    'original_filename' => $version->original_filename,
                    'mime_type' => $version->mime_type,
                    'file_size' => $version->file_size,
                    'checksum' => $version->checksum,
                    'completed_at' => $version->completed_at,
                ],
                $changeNote,
                'restored',
                $version,
            );
        } catch (Throwable $exception) {
            if ($restoredPath !== null) {
                Storage::disk('local')->delete($restoredPath);
            }

            throw $exception;
        }

        $versionCreated = $currentDocument === null
            || $document->lock_version !== $currentLockVersion;

        if (! $versionCreated && $restoredPath !== null) {
            Storage::disk('local')->delete($restoredPath);
        }

        return [
            'document' => $document,
            'version_created' => $versionCreated,
        ];
    }
}
