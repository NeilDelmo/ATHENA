<?php

namespace App\Actions;

use App\Models\ProposalDraft;
use App\Models\ProposalDraftDocument;
use App\Models\ProposalDraftDocumentVersion;
use App\Models\User;
use App\Support\ProposalDocumentVersionDiff;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveProposalDraftDocument
{
    public function __construct(
        private readonly RecordProposalDraftDocumentVersion $recordDocumentVersion,
        private readonly ProposalDocumentVersionDiff $diff,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function handle(
        ProposalDraft $draft,
        User $actor,
        string $documentType,
        int $position,
        int $expectedVersion,
        array $attributes,
        ?string $changeNote = null,
        string $action = 'saved',
        ?ProposalDraftDocumentVersion $restoredFrom = null,
    ): ProposalDraftDocument {
        return DB::transaction(function () use ($draft, $actor, $documentType, $position, $expectedVersion, $attributes, $changeNote, $action, $restoredFrom): ProposalDraftDocument {
            $lockedDraft = ProposalDraft::query()
                ->whereKey($draft->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $document = ProposalDraftDocument::query()
                ->where('proposal_draft_id', $lockedDraft->getKey())
                ->where('document_type', $documentType)
                ->where('position', $position)
                ->lockForUpdate()
                ->first();
            $currentVersion = $lockedDraft->currentDocumentVersion(
                $documentType,
                $position,
                $document,
            );

            if ($currentVersion !== $expectedVersion) {
                throw ValidationException::withMessages([
                    'document_version' => 'A teammate saved a newer version of this paper. Copy any unsaved text you need, then reload the page before editing again.',
                ]);
            }

            if ($document && ! $document->versions()->exists()) {
                $this->recordDocumentVersion->handle($document, null, action: 'captured');
            }

            $safeAttributes = Arr::except($attributes, [
                'proposal_draft_id',
                'document_type',
                'position',
                'lock_version',
            ]);

            if ($document && $this->diff->isEquivalent($document, $safeAttributes)) {
                if ($document->lock_version !== $currentVersion) {
                    $document->update(['lock_version' => $currentVersion]);
                }

                return $document->refresh();
            }

            if ($document) {
                $document->update([
                    ...$safeAttributes,
                    'lock_version' => $currentVersion + 1,
                ]);

                $savedDocument = $document->refresh();
                $this->recordDocumentVersion->handle(
                    $savedDocument,
                    $actor,
                    $changeNote,
                    $action,
                    $restoredFrom,
                );

                return $savedDocument;
            }

            $savedDocument = $lockedDraft->documents()->create([
                ...$safeAttributes,
                'document_type' => $documentType,
                'position' => $position,
                'lock_version' => $currentVersion + 1,
            ]);

            $this->recordDocumentVersion->handle(
                $savedDocument,
                $actor,
                $changeNote,
                $action,
                $restoredFrom,
            );

            return $savedDocument;
        }, 3);
    }
}
