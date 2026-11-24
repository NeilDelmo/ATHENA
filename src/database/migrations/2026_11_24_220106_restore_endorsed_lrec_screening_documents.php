<?php

use App\Models\ProposalVersionFile;
use App\Models\TopicProposal;
use App\Services\ProposalPackageService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $packages = app(ProposalPackageService::class);

        TopicProposal::query()
            ->where('review_stage', 'lrec')
            ->whereIn('status', ['resubmitted', TopicProposal::STATUS_LREC_REVIEW, TopicProposal::STATUS_READY_FOR_SIGNATURE, 'approved'])
            ->whereNull('notice_to_proceed_issued_at')
            ->select('id')
            ->eachById(function (TopicProposal $candidate) use ($packages): void {
                DB::transaction(function () use ($candidate, $packages): void {
                    $topic = TopicProposal::query()->lockForUpdate()->find($candidate->id);
                    if ($topic === null || $topic->review_stage !== 'lrec' || $topic->notice_to_proceed_issued_at !== null) {
                        return;
                    }

                    $version = $topic->latestVersion()->first();
                    if ($version?->submission_type !== 'revision') {
                        return;
                    }

                    $previousVersion = $topic->versions()->where('version_number', $version->version_number - 1)->first();
                    if ($previousVersion === null) {
                        return;
                    }

                    $resolvedLrecRevision = $topic->reviews()
                        ->where('review_stage', 'lrec')
                        ->where('decision', 'revision_requested')
                        ->whereHas('fileRevisions', fn ($revisions) => $revisions
                            ->whereNotNull('resolved_at')
                            ->whereHas('file', fn ($file) => $file->where('proposal_version_id', $previousVersion->id))
                            ->whereHas('resolutionFile', fn ($file) => $file->where('proposal_version_id', $version->id)))
                        ->exists();

                    if ($resolvedLrecRevision) {
                        $packages->carryForwardAssessments($previousVersion, $version, [ProposalVersionFile::HEAD_UPLOAD_PURPOSE_EVALUATION]);
                    }
                });
            });
    }

    public function down(): void
    {
        // Preserve the recovered assessment and its source reference.
    }
};
