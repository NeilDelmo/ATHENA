<?php

namespace App\Observers;

use App\Models\ProposalDraft;
use App\Models\ProposalDraftDocument;
use App\Services\ProposalPdfPreparation;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProposalPdfPreparationObserver
{
    public function saved(ProposalDraft|ProposalDraftDocument $model): void
    {
        if ($model instanceof ProposalDraft && ! $model->wasChanged(['project_title', 'project_leader', 'duration_months', 'planned_start', 'planned_end', 'lock_version', 'signatory_selections'])) {
            return;
        }
        if ($model instanceof ProposalDraftDocument && ! $model->wasRecentlyCreated && ! $model->wasChanged(['source_data', 'completed_at', 'file_path'])) {
            return;
        }
        $this->schedule($model);
    }

    public function deleted(ProposalDraft|ProposalDraftDocument $model): void
    {
        if ($model instanceof ProposalDraftDocument) {
            $this->schedule($model);
        }
    }

    private function schedule(ProposalDraft|ProposalDraftDocument $model): void
    {
        $id = $model instanceof ProposalDraft ? $model->id : $model->proposal_draft_id;
        DB::afterCommit(function () use ($id): void {
            try {
                app(ProposalPdfPreparation::class)->request($id);
            } catch (Throwable $exception) {
                report($exception);
            }
        });
    }
}
