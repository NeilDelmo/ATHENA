<?php

namespace App\Services;

use App\Models\ProjectNarrativeReport;
use App\Models\ProjectProgressReport;
use App\Models\ProposalDraft;
use App\Models\ProposalDraftMember;
use App\Models\ProposalVersionFile;
use App\Models\TopicProposal;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Gate;

class WorkspaceActionItems
{
    /** @return list<array{key: string, label: string, title: string, url: string}> */
    public function for(User $user): array
    {
        $items = [];
        $workspace = $user->activeWorkspace();

        if ($workspace === User::WORKSPACE_FACULTY) {
            foreach (ProposalDraftMember::query()->whereBelongsTo($user)->whereNull('accepted_at')
                ->whereHas('draft', fn (Builder $query): Builder => $query->where('status', ProposalDraft::STATUS_DRAFT))
                ->with('draft')->oldest()->get() as $membership) {
                if (Gate::forUser($user)->allows('accept', $membership)) {
                    $items[] = $this->item('invitation:'.$membership->id, 'Review project staff invitation', $membership->draft->project_title, route('notifications.proposal-invitations.show', $membership));
                }
            }
            foreach (TopicProposal::query()->whereBelongsTo($user)->where('status', 'revision_requested')->oldest('status_started_at')->get() as $topic) {
                $items[] = $this->item('proposal:'.$topic->id, 'Address proposal revisions', $topic->title, route('faculty.topics.revision', $topic));
            }
        }

        if ($workspace === User::WORKSPACE_RESEARCH_HEAD) {
            $topics = TopicProposal::query()->whereIn('status', ['pending', 'resubmitted', 'expert_review', 'for_final_decision', TopicProposal::STATUS_GAD_REVIEW, TopicProposal::STATUS_READY_FOR_SIGNATURE])
                ->with('latestVersion')->oldest('status_started_at')->get();
            $topics->where('status', TopicProposal::STATUS_READY_FOR_SIGNATURE)->load('latestVersion.files');
            foreach ($topics as $topic) {
                if ($topic->latestVersion === null || ($topic->status === TopicProposal::STATUS_READY_FOR_SIGNATURE && ! app(ProposalSignatureWorkflow::class)->isComplete($topic->latestVersion))) {
                    continue;
                }
                $label = match ($topic->status) {
                    TopicProposal::STATUS_GAD_REVIEW => 'Record or review assessment results',
                    TopicProposal::STATUS_READY_FOR_SIGNATURE => 'Review completed signed papers',
                    default => 'Review submitted proposal',
                };
                $items[] = $this->item('proposal:'.$topic->id, $label, $topic->title, route('topics.show', $topic).'#proposal-review');
            }
        }

        if (in_array($workspace, [User::WORKSPACE_RESEARCH_OFFICE, User::WORKSPACE_RESEARCH_SECRETARY], true)) {
            $topics = TopicProposal::query()->where(function (Builder $query) use ($user, $workspace): void {
                $query->accessibleForDocumentRelease($user)->awaitingDocumentRelease();
                if ($workspace === User::WORKSPACE_RESEARCH_OFFICE && filled($user->college)) {
                    $query->orWhere(fn (Builder $lrec): Builder => $lrec->whereHas('user', fn (Builder $owner): Builder => $owner->where('college', $user->college))
                        ->where('review_stage', 'lrec')->whereIn('status', [TopicProposal::STATUS_LREC_QUEUED, TopicProposal::STATUS_LREC_REVIEW]));
                }
            })->with('user')->oldest('status_started_at')->get();
            foreach ($topics as $topic) {
                $label = match (true) {
                    Gate::forUser($user)->allows('startLrecReview', $topic) => 'Start the queued LREC review',
                    Gate::forUser($user)->allows('recordLrecFeedback', $topic) => 'Record LREC feedback or outcome',
                    Gate::forUser($user)->allows('manageNoticeToProceed', $topic) => 'Prepare signed papers and document release',
                    default => null,
                };
                if ($label !== null) {
                    $items[] = $this->item('proposal:'.$topic->id, $label, $topic->title, route('topics.show', $topic).'#proposal-review');
                }
            }
        }

        if (in_array($workspace, [User::WORKSPACE_FACULTY_RESEARCHER, User::WORKSPACE_RESEARCH_HEAD, User::WORKSPACE_RESEARCH_SECRETARY], true)) {
            foreach ([ProjectProgressReport::class, ProjectNarrativeReport::class] as $model) {
                $query = $model::query()->whereHas('topic', fn (Builder $topic): Builder => $topic->monitoringAvailable())
                    ->where(fn (Builder $report): Builder => $report->where('submission_status', 'prepared')->orWhereIn('review_status', ['pending', 'revision_requested']))
                    ->with('topic:id,title')->oldest();
                $query->select(['id', 'topic_id', 'reporting_date', 'submission_status', 'review_stage', 'review_status']);
                if ($model === ProjectNarrativeReport::class) {
                    $query->addSelect('report_type');
                } else {
                    $query->addSelect('supersedes_report_id');
                }
                if ($model === ProjectProgressReport::class) {
                    $query->latestSubmittedVersion();
                } else {
                    $query->where(fn (Builder $report): Builder => $report->where(fn (Builder $progress): Builder => $progress->where('report_type', 'progress')->latestSubmittedVersion())
                        ->orWhere(fn (Builder $terminal): Builder => $terminal->where('report_type', 'terminal')->whereIn('id', ProjectNarrativeReport::query()->where('report_type', 'terminal')->selectRaw('MAX(id)')->groupBy('topic_id'))));
                }
                if ($workspace === User::WORKSPACE_FACULTY_RESEARCHER) {
                    $query->whereHas('topic', fn (Builder $topic): Builder => $topic->whereBelongsTo($user));
                } elseif ($workspace === User::WORKSPACE_RESEARCH_SECRETARY) {
                    $query->whereHas('topic', fn (Builder $topic): Builder => $topic->forImplementationReportReview($user));
                }

                foreach ($query->get() as $report) {
                    $label = match (true) {
                        $workspace === User::WORKSPACE_FACULTY_RESEARCHER && $report->isPrepared() => 'Review and submit prepared report',
                        $workspace === User::WORKSPACE_FACULTY_RESEARCHER && $report->review_status === 'revision_requested' => 'Correct the returned report',
                        $workspace === User::WORKSPACE_RESEARCH_SECRETARY && $report->isAwaitingSecretaryReview() => 'Check report before forwarding',
                        $workspace === User::WORKSPACE_RESEARCH_HEAD && $report->isSubmitted() && $report->review_status === 'pending' && $report->isAvailableToResearchHead() => 'Review the submitted report',
                        default => null,
                    };
                    if ($label === null) {
                        continue;
                    }
                    $isMonitoring = $report instanceof ProjectProgressReport;
                    $parameters = ['topic' => $report->topic_id, 'reporting_date' => $report->reporting_date?->toDateString()];
                    if ($isMonitoring && ($report->supersedes_report_id || $report->review_status === 'revision_requested')) {
                        $parameters['revise_monitoring_report'] = $report->supersedes_report_id ?? $report->id;
                    }
                    if (! $isMonitoring) {
                        $parameters['report_type'] = $report->report_type;
                    }
                    $url = $workspace === User::WORKSPACE_FACULTY_RESEARCHER
                        ? route($isMonitoring ? 'project-progress.create' : 'project-narrative-reports.create', $parameters)
                        : route('topics.show', $report->topic_id).'#'.($isMonitoring ? 'monitoring-tool-'.$report->id : 'project-monitoring');
                    $items[] = $this->item(($isMonitoring ? 'monitoring:' : 'narrative:').$report->id, $label, $report->topic->title.' · '.($isMonitoring ? 'Monitoring Tool' : $report->report_label), $url);
                }
            }
        }

        if (in_array($workspace, [User::WORKSPACE_FACULTY_RESEARCHER, User::WORKSPACE_RESEARCH_HEAD], true)) {
            $topics = TopicProposal::query()->monitoringAvailable()
                ->when($workspace === User::WORKSPACE_FACULTY_RESEARCHER, fn (Builder $query): Builder => $query->whereBelongsTo($user))
                ->with([
                    'latestVersion.files' => fn (HasMany $query): HasMany => $query->where('document_type', ProposalVersionFile::TYPE_WORK_PLAN)
                        ->select('id', 'proposal_version_id', 'document_type', 'source_data'),
                    'progressReports' => fn (HasMany $query): HasMany => $query->select('id', 'topic_id', 'reporting_date', 'period_start', 'version_number', 'submission_status', 'work_plan'),
                ])->oldest()->get();
            foreach ($topics as $topic) {
                foreach (app(ApprovedWorkPlanMonitoringService::class)->overdueActivities($topic) as $activity) {
                    $items[] = $this->item('overdue:'.$topic->id.':'.$activity['source_work_plan_index'].':'.$activity['target_completion_date'],
                        $workspace === User::WORKSPACE_RESEARCH_HEAD ? 'Review overdue Work Plan target' : 'Address overdue Work Plan target',
                        $topic->title.' · '.$activity['objective'], route('topics.show', $topic).'#project-monitoring');
                }
            }
        }

        return $items;
    }

    /** @return array{key: string, label: string, title: string, url: string} */
    private function item(string $key, string $label, string $title, string $url): array
    {
        return compact('key', 'label', 'title', 'url');
    }
}
