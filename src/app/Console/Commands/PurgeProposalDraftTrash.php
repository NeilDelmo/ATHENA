<?php

namespace App\Console\Commands;

use App\Actions\PurgeProposalDraft;
use App\Models\ProposalDraft;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('proposal-drafts:purge')]
#[Description('Permanently remove standalone proposal drafts after their 30-day recovery period')]
class PurgeProposalDraftTrash extends Command
{
    public function handle(PurgeProposalDraft $purgeDraft): int
    {
        $purged = 0;
        // shortcut: retain linked official workspaces until a separate archival policy covers their history.
        ProposalDraft::onlyTrashed()->where('status', ProposalDraft::STATUS_DRAFT)
            ->whereNull('topic_id')->where('deleted_at', '<=', now()->subDays(ProposalDraft::TRASH_RETENTION_DAYS))
            ->chunkById(100, function ($drafts) use (&$purged, $purgeDraft): void {
                foreach ($drafts as $candidate) {
                    $purged += DB::transaction(function () use ($candidate, $purgeDraft): int {
                        $draft = ProposalDraft::onlyTrashed()->where('status', ProposalDraft::STATUS_DRAFT)
                            ->whereNull('topic_id')->where('deleted_at', '<=', now()->subDays(ProposalDraft::TRASH_RETENTION_DAYS))
                            ->lockForUpdate()->find($candidate->id);
                        if ($draft === null || $draft->isLinkedToSubmission()) {
                            return 0;
                        }

                        $purgeDraft->handle($draft);

                        return 1;
                    });
                }
            });
        $this->info("Purged {$purged} expired proposal drafts.");

        return self::SUCCESS;
    }
}
