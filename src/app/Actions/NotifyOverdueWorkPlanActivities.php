<?php

namespace App\Actions;

use App\Models\TopicProposal;
use App\Models\User;
use App\Notifications\ProposalActivityNotification;
use App\Services\ApprovedWorkPlanMonitoringService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NotifyOverdueWorkPlanActivities
{
    public function __construct(private readonly ApprovedWorkPlanMonitoringService $workPlan) {}

    /** @param Collection<int, User>|null $researchHeads */
    public function handle(TopicProposal $topic, ?Collection $researchHeads = null): int
    {
        return DB::transaction(function () use ($topic, $researchHeads): int {
            $locked = TopicProposal::query()->lockForUpdate()->find($topic->id);
            if ($locked === null || ! $locked->isMonitoringAvailable()) {
                return 0;
            }
            $locked->setRelations($topic->getRelations());
            $locked->loadMissing('user');
            $leader = $locked->user;
            if ($leader === null) {
                return 0;
            }
            $recipients = collect([[
                'user' => $leader,
                'workspace' => User::WORKSPACE_FACULTY_RESEARCHER,
                'sidebar_area' => ProposalActivityNotification::SIDEBAR_AREA_MY_PROJECTS,
            ]])->concat(($researchHeads ?? User::role('research_head')->get())->map(fn (User $head): array => [
                'user' => $head,
                'workspace' => User::WORKSPACE_RESEARCH_HEAD,
                'sidebar_area' => ProposalActivityNotification::SIDEBAR_AREA_PROJECT_MONITORING,
            ]));

            $sent = 0;
            foreach ($this->workPlan->overdueActivities($locked) as $activity) {
                $key = $locked->id.':'.$activity['source_work_plan_index'].':'.$activity['target_completion_date'];
                $deadline = CarbonImmutable::parse($activity['target_completion_date'])->format('M j, Y');
                $progress = $activity['progress_recorded']
                    ? $activity['completion_percentage'].'% complete in submitted monitoring.'
                    : 'No submitted progress has been recorded.';
                foreach ($recipients as $recipient) {
                    $user = $recipient['user'];
                    $workspace = $recipient['workspace'];
                    if ($user->notifications()->where('data->action_data->work_plan_overdue_key', $key)
                        ->where('data->workspace', $workspace)->exists()) {
                        continue;
                    }
                    $isResearchHead = $workspace === User::WORKSPACE_RESEARCH_HEAD;
                    $message = $isResearchHead
                        ? 'Project: '.$locked->title."\nFaculty researcher: ".$leader->name
                            ."\nObjective: ".Str::limit($activity['objective'], 180)
                            ."\nActivity: ".Str::limit($activity['activity'], 180)
                            ."\nTarget date: ".$deadline."\nOfficial progress: ".$progress
                        : Str::limit($activity['objective'], 180).' — '.Str::limit($activity['activity'], 180)
                            .' was due '.$deadline.'. '.$progress;
                    $user->notify((new ProposalActivityNotification(
                        title: $isResearchHead ? 'Faculty Work Plan target overdue' : 'Work Plan target overdue',
                        message: $message,
                        url: route('topics.show', $locked).'#project-monitoring',
                        level: 'warning',
                        topicId: $locked->id,
                        actionData: ['work_plan_overdue_key' => $key],
                        workspace: $workspace,
                        sidebarArea: $recipient['sidebar_area'],
                    ))->afterCommit());
                    $sent++;
                }
            }

            return $sent;
        }, 3);
    }
}
