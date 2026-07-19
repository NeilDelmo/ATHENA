<?php

namespace App\Actions;

use App\Models\ProposalDraft;
use App\Models\ProposalDraftDocument;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveProposalDraftDocument
{
    public function __construct(
        private readonly RecordProposalDraftDocumentVersion $recordDocumentVersion,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function handle(
        ProposalDraft $draft,
        User $actor,
        string $documentType,
        int $position,
        int $expectedVersion,
        array $attributes,
    ): ProposalDraftDocument {
        return DB::transaction(function () use ($draft, $actor, $documentType, $position, $expectedVersion, $attributes): ProposalDraftDocument {
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
            $currentVersion = $document?->lock_version ?? 0;

            if ($currentVersion !== $expectedVersion) {
                throw ValidationException::withMessages([
                    'document_version' => 'A teammate saved a newer version of this paper. Copy any unsaved text you need, then reload the page before editing again.',
                ]);
            }

            if ($document && ! $document->versions()->exists()) {
                $this->recordDocumentVersion->handle($document, null);
            }

            $safeAttributes = Arr::except($attributes, [
                'proposal_draft_id',
                'document_type',
                'position',
                'lock_version',
            ]);

            if ($document) {
                $document->update([
                    ...$safeAttributes,
                    'lock_version' => $currentVersion + 1,
                ]);

                $savedDocument = $document->refresh();
                $this->recordDocumentVersion->handle($savedDocument, $actor);

                return $savedDocument;
            }

            $savedDocument = $lockedDraft->documents()->create([
                ...$safeAttributes,
                'document_type' => $documentType,
                'position' => $position,
                'lock_version' => 1,
            ]);

            $this->recordDocumentVersion->handle($savedDocument, $actor);

            return $savedDocument;
        }, 3);
    }
}
