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
        bool $forceCheckpoint = false,
        bool $preserveCurrentWorkingCopy = false,
    ): ProposalDraftDocument {
        return DB::transaction(function () use ($draft, $actor, $documentType, $position, $expectedVersion, $attributes, $changeNote, $action, $restoredFrom, $forceCheckpoint, $preserveCurrentWorkingCopy): ProposalDraftDocument {
            $lockedDraft = ProposalDraft::query()
                ->whereKey($draft->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $lockedDraft->ensureEditable();
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
                    'document_version' => 'A teammate saved a newer version of this paper. Check the recovery copy status, then load the latest saved work to compare your entries.',
                ]);
            }

            if ($document && ! $document->versions()->exists()) {
                $capturedVersion = $this->recordDocumentVersion->handle(
                    $document,
                    null,
                    action: ProposalDraftDocumentVersion::ACTION_CAPTURED,
                );
                $currentVersion = max($currentVersion, $capturedVersion->version_number);
            }

            $safeAttributes = Arr::except($attributes, [
                'proposal_draft_id',
                'document_type',
                'position',
                'lock_version',
            ]);
            $sourceChanged = array_key_exists('source_data', $safeAttributes)
                && ($document === null || ! $this->diff->isEquivalent($document, [
                    'source_data' => $safeAttributes['source_data'],
                ]));

            if ($sourceChanged && ! array_key_exists('file_path', $safeAttributes)) {
                $safeAttributes = [
                    ...$safeAttributes,
                    'file_path' => null,
                    'original_filename' => null,
                    'mime_type' => null,
                    'file_size' => null,
                    'checksum' => null,
                ];
            }

            if ($document && $this->diff->isEquivalent($document, $safeAttributes)) {
                if ($document->lock_version !== $currentVersion) {
                    $document->update(['lock_version' => $currentVersion]);
                }

                return $document->refresh();
            }

            if ($document && $preserveCurrentWorkingCopy
                && ! $this->recordDocumentVersion->hasRecoveryPointForWorkingDraft($document)) {
                $this->recordDocumentVersion->handle(
                    $document,
                    $actor,
                    action: ProposalDraftDocumentVersion::ACTION_PRE_RESTORE,
                );
            }

            if ($document) {
                $document->update([
                    ...$safeAttributes,
                    'lock_version' => $currentVersion + 1,
                ]);

                $savedDocument = $document->refresh();

                if ($sourceChanged) {
                    $this->invalidateOtherPreparedFiles($lockedDraft, $savedDocument);
                }

                $this->recordRecoveryPoint(
                    $savedDocument,
                    $actor,
                    $changeNote,
                    $action,
                    $restoredFrom,
                    $forceCheckpoint,
                );

                return $savedDocument;
            }

            $savedDocument = $lockedDraft->documents()->create([
                ...$safeAttributes,
                'document_type' => $documentType,
                'position' => $position,
                'lock_version' => $currentVersion + 1,
            ]);

            if ($sourceChanged) {
                $this->invalidateOtherPreparedFiles($lockedDraft, $savedDocument);
            }

            $this->recordRecoveryPoint(
                $savedDocument,
                $actor,
                $changeNote,
                $action,
                $restoredFrom,
                $forceCheckpoint,
            );

            return $savedDocument;
        }, 3);
    }

    private function recordRecoveryPoint(
        ProposalDraftDocument $document,
        User $actor,
        ?string $changeNote,
        string $action,
        ?ProposalDraftDocumentVersion $restoredFrom,
        bool $forceCheckpoint,
    ): void {
        if (! $forceCheckpoint) {
            $this->recordDocumentVersion->captureAutomaticRecoveryPoint($document, $actor);

            return;
        }

        $this->recordDocumentVersion->handle(
            $document,
            $actor,
            $changeNote,
            $action,
            $restoredFrom,
        );
    }

    private function invalidateOtherPreparedFiles(
        ProposalDraft $draft,
        ProposalDraftDocument $savedDocument,
    ): void {
        $dependentTypes = match ($savedDocument->document_type) {
            'detailed_proposal' => ['work_plan'],
            'work_plan' => ['detailed_proposal'],
            'expense_breakdown' => ['line_item_budget', 'detailed_proposal'],
            'line_item_budget' => ['detailed_proposal', 'initial_screening_form', 'curriculum_vitae'],
            default => [],
        };
        if ($dependentTypes === []) {
            return;
        }
        ProposalDraftDocument::query()
            ->where('proposal_draft_id', $draft->id)
            ->whereIn('document_type', $dependentTypes)
            ->whereKeyNot($savedDocument->id)
            ->whereNotNull('source_data')
            ->update([
                'file_path' => null,
                'original_filename' => null,
                'mime_type' => null,
                'file_size' => null,
                'checksum' => null,
            ]);
    }
}
