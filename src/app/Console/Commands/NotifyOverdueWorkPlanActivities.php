<?php

namespace App\Console\Commands;

use App\Actions\NotifyOverdueWorkPlanActivities as NotifyActivities;
use App\Models\ProposalVersionFile;
use App\Models\TopicProposal;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('projects:notify-overdue-objectives')]
#[Description('Notify project leaders and Research Heads about unfinished work after the approved Work Plan target date')]
class NotifyOverdueWorkPlanActivities extends Command
{
    public function handle(NotifyActivities $notifyActivities): int
    {
        $sent = 0;
        $researchHeads = User::role('research_head')->get();
        TopicProposal::query()->activeProject()->with([
            'user',
            'latestVersion.files' => fn ($query) => $query->where('document_type', ProposalVersionFile::TYPE_WORK_PLAN),
            'progressReports' => fn ($query) => $query->select([
                'id', 'topic_id', 'reporting_date', 'period_start', 'version_number', 'submission_status', 'work_plan',
            ]),
        ])->eachById(function (TopicProposal $topic) use ($notifyActivities, $researchHeads, &$sent): void {
            $sent += $notifyActivities->handle($topic, $researchHeads);
        });
        $this->info("Sent {$sent} overdue Work Plan notification(s).");

        return self::SUCCESS;
    }
}
