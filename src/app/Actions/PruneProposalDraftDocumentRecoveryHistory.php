<?php

namespace App\Actions;

use App\Models\ProposalDraftDocument;
use App\Models\ProposalDraftDocumentVersion;

class PruneProposalDraftDocumentRecoveryHistory
{
    public function handle(ProposalDraftDocument $document): void
    {
        $automaticCheckpoints = ProposalDraftDocumentVersion::query()
            ->where('proposal_draft_id', $document->proposal_draft_id)
            ->where('document_type', $document->document_type)
            ->where('position', $document->position)
            ->whereNull('file_path')
            ->whereIn('action', [
                ProposalDraftDocumentVersion::ACTION_CHECKPOINT,
                ProposalDraftDocumentVersion::ACTION_SAVED,
            ])
            ->latest('id')
            ->get(['id']);
        $limit = max(1, (int) config('proposal_recovery.automatic_checkpoint_limit', 24));

        if ($automaticCheckpoints->count() <= $limit + 1) {
            return;
        }

        $keepIds = $automaticCheckpoints
            ->take($limit)
            ->pluck('id')
            ->push($automaticCheckpoints->last()->id)
            ->unique()
            ->all();

        ProposalDraftDocumentVersion::query()
            ->where('proposal_draft_id', $document->proposal_draft_id)
            ->where('document_type', $document->document_type)
            ->where('position', $document->position)
            ->whereNull('file_path')
            ->whereIn('action', [
                ProposalDraftDocumentVersion::ACTION_CHECKPOINT,
                ProposalDraftDocumentVersion::ACTION_SAVED,
            ])
            ->whereKeyNot($keepIds)
            ->delete();
    }
}
