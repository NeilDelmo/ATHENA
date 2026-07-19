<?php

namespace App\Actions;

use App\Models\ProposalDraftDocument;
use App\Models\ProposalDraftDocumentVersion;
use App\Models\User;

class RecordProposalDraftDocumentVersion
{
    public function handle(
        ProposalDraftDocument $document,
        ?User $actor,
    ): ProposalDraftDocumentVersion {
        $versions = ProposalDraftDocumentVersion::query()
            ->where('proposal_draft_id', $document->proposal_draft_id)
            ->where('document_type', $document->document_type)
            ->where('position', $document->position);
        $nextVersionNumber = ((int) $versions->max('version_number')) + 1;

        (clone $versions)->where('is_current', true)->update(['is_current' => false]);

        return ProposalDraftDocumentVersion::create([
            'proposal_draft_id' => $document->proposal_draft_id,
            'proposal_draft_document_id' => $document->getKey(),
            'created_by' => $actor?->getKey(),
            'document_type' => $document->document_type,
            'position' => $document->position,
            'version_number' => $nextVersionNumber,
            'is_current' => true,
            'source_data' => $document->source_data,
            'file_path' => $document->file_path,
            'original_filename' => $document->original_filename,
            'mime_type' => $document->mime_type,
            'file_size' => $document->file_size,
            'checksum' => $document->checksum,
            'completed_at' => $document->completed_at,
        ]);
    }
}
