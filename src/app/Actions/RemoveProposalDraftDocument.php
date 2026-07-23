<?php

namespace App\Actions;

use App\Models\ProposalDraft;
use App\Models\ProposalDraftDocument;
use App\Models\ProposalDraftDocumentVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RemoveProposalDraftDocument
{
    public function __construct(
        private readonly RecordProposalDraftDocumentVersion $recordDocumentVersion,
    ) {}

    public function handle(
        ProposalDraft $draft,
        ProposalDraftDocument $document,
        User $actor,
        int $expectedVersion,
    ): void {
        DB::transaction(function () use ($draft, $document, $actor, $expectedVersion): void {
            $lockedDraft = ProposalDraft::query()
                ->whereKey($draft->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $lockedDocument = ProposalDraftDocument::query()
                ->whereKey($document->getKey())
                ->whereBelongsTo($draft, 'draft')
                ->lockForUpdate()
                ->firstOrFail();
            $currentVersion = $lockedDraft->currentDocumentVersion(
                $lockedDocument->document_type,
                $lockedDocument->position,
                $lockedDocument,
            );

            if ($currentVersion !== $expectedVersion) {
                throw ValidationException::withMessages([
                    'document_version' => 'A teammate saved a newer version of this paper. Your removal was stopped; reload the latest version before removing it.',
                ]);
            }

            $this->recordDocumentVersion->handle(
                $lockedDocument,
                $actor,
                action: 'removed',
            );

            ProposalDraftDocumentVersion::query()
                ->where('proposal_draft_id', $lockedDocument->proposal_draft_id)
                ->where('document_type', $lockedDocument->document_type)
                ->where('position', $lockedDocument->position)
                ->where('is_current', true)
                ->update(['is_current' => false]);
            $lockedDocument->delete();
        }, 3);
    }
}
