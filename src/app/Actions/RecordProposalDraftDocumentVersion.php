<?php

namespace App\Actions;

use App\Models\ProposalDraftDocument;
use App\Models\ProposalDraftDocumentVersion;
use App\Models\User;
use App\Support\ProposalDocumentVersionDiff;

class RecordProposalDraftDocumentVersion
{
    public function __construct(
        private readonly ProposalDocumentVersionDiff $diff,
        private readonly PruneProposalDraftDocumentRecoveryHistory $pruneRecoveryHistory,
    ) {}

    public function captureAutomaticRecoveryPoint(
        ProposalDraftDocument $document,
        ?User $actor,
    ): ?ProposalDraftDocumentVersion {
        $latestCheckpoint = ProposalDraftDocumentVersion::query()
            ->where('proposal_draft_id', $document->proposal_draft_id)
            ->where('document_type', $document->document_type)
            ->where('position', $document->position)
            ->whereIn('action', [
                ProposalDraftDocumentVersion::ACTION_CHECKPOINT,
                ProposalDraftDocumentVersion::ACTION_SAVED,
            ])
            ->latest('created_at')
            ->first();
        $interval = max(1, (int) config('proposal_recovery.checkpoint_interval_minutes', 30));

        if ($latestCheckpoint?->created_at?->gt(now()->subMinutes($interval))) {
            return null;
        }

        return $this->handle(
            $document,
            $actor,
            action: ProposalDraftDocumentVersion::ACTION_CHECKPOINT,
        );
    }

    public function hasRecoveryPointForWorkingDraft(ProposalDraftDocument $document): bool
    {
        return ProposalDraftDocumentVersion::query()
            ->where('proposal_draft_id', $document->proposal_draft_id)
            ->where('proposal_draft_document_id', $document->getKey())
            ->where('document_type', $document->document_type)
            ->where('position', $document->position)
            ->where('version_number', $document->lock_version)
            ->exists();
    }

    public function handle(
        ProposalDraftDocument $document,
        ?User $actor,
        ?string $changeNote = null,
        string $action = 'saved',
        ?ProposalDraftDocumentVersion $restoredFrom = null,
    ): ProposalDraftDocumentVersion {
        $versions = ProposalDraftDocumentVersion::query()
            ->where('proposal_draft_id', $document->proposal_draft_id)
            ->where('document_type', $document->document_type)
            ->where('position', $document->position);
        $previous = (clone $versions)
            ->where('is_current', true)
            ->latest('version_number')
            ->first();
        $nextVersionNumber = max(
            ((int) $versions->max('version_number')) + 1,
            $document->lock_version,
        );
        $changes = $this->diff->changes($previous, $document);

        (clone $versions)->where('is_current', true)->update(['is_current' => false]);

        $version = ProposalDraftDocumentVersion::create([
            'proposal_draft_id' => $document->proposal_draft_id,
            'proposal_draft_document_id' => $document->getKey(),
            'created_by' => $actor?->getKey(),
            'document_type' => $document->document_type,
            'position' => $document->position,
            'version_number' => $nextVersionNumber,
            'is_current' => true,
            'action' => $action,
            'change_note' => filled($changeNote) ? trim($changeNote) : null,
            'change_summary' => $this->diff->summary(
                $document,
                $previous,
                $changes,
                $action,
                $restoredFrom,
            ),
            'changes' => $changes,
            'restored_from_version_id' => $restoredFrom?->getKey(),
            'source_data' => $document->source_data,
            'file_path' => $document->file_path,
            'original_filename' => $document->original_filename,
            'mime_type' => $document->mime_type,
            'file_size' => $document->file_size,
            'checksum' => $document->checksum,
            'completed_at' => $document->completed_at,
        ]);

        $this->pruneRecoveryHistory->handle($document);

        return $version;
    }
}
