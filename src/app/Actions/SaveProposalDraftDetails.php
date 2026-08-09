<?php

namespace App\Actions;

use App\Models\ProposalDraft;
use App\Models\ProposalDraftDocument;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveProposalDraftDetails
{
    /** @param array<string, mixed> $attributes */
    public function handle(ProposalDraft $draft, int $expectedVersion, array $attributes): ProposalDraft
    {
        return DB::transaction(function () use ($draft, $expectedVersion, $attributes): ProposalDraft {
            $lockedDraft = ProposalDraft::query()
                ->whereKey($draft->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedDraft->lock_version !== $expectedVersion) {
                throw ValidationException::withMessages([
                    'draft_version' => 'A teammate saved newer project details. Copy any unsaved text you need, then reload the page before editing again.',
                ]);
            }

            $lockedDraft->fill([
                ...Arr::only($attributes, [
                    'project_title',
                    'duration_months',
                    'planned_start',
                    'planned_end',
                    'project_leader',
                ]),
                'lock_version' => $lockedDraft->lock_version + 1,
            ]);
            $detailsChanged = $lockedDraft->isDirty([
                'project_title',
                'duration_months',
                'planned_start',
                'planned_end',
                'project_leader',
            ]);
            $lockedDraft->save();

            if ($detailsChanged) {
                ProposalDraftDocument::query()
                    ->where('proposal_draft_id', $lockedDraft->id)
                    ->whereNotNull('source_data')
                    ->update([
                        'file_path' => null,
                        'original_filename' => null,
                        'mime_type' => null,
                        'file_size' => null,
                        'checksum' => null,
                    ]);
            }

            return $lockedDraft->refresh();
        }, 3);
    }
}
