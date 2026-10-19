<?php

namespace App\Services;

use App\Models\ProjectNarrativeReport;
use App\Models\ProjectProgressReport;
use App\Models\ProposalVersion;
use App\Models\ProposalVersionFile;
use App\Models\TopicProposal;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TopicLiveStatus
{
    /** @return array<string, mixed> */
    public function forTopic(TopicProposal $topic, User $user): array
    {
        $topic->loadMissing(['latestVersion.files' => fn (HasMany $query) => $query
            ->whereIn('document_type', [ProposalVersionFile::TYPE_GAD_CHECKLIST, ProposalVersionFile::TYPE_HEAD_UPLOAD])
            ->select('id', 'proposal_version_id', 'document_type', 'source_version_file_id', 'source_data')]);
        $proposal = $this->proposal($topic, $topic->latestVersion, $user);
        $reports = [];
        $researcher = $user->isUsingWorkspace(User::WORKSPACE_FACULTY_RESEARCHER) && $topic->isAccessibleTo($user);
        $head = $user->isUsingWorkspace(User::WORKSPACE_RESEARCH_HEAD);
        if ($topic->hasIssuedNoticeToProceed() && ($researcher || $head || $topic->canReviewImplementationReports($user))) {
            foreach ([ProjectProgressReport::class, ProjectNarrativeReport::class] as $model) {
                $query = $model::query()->whereBelongsTo($topic, 'topic')
                    ->where(fn (Builder $query) => $query->where('submission_status', 'submitted')
                        ->when($researcher, fn (Builder $query) => $query->orWhere('submission_status', 'prepared')))
                    ->when($head, fn (Builder $query) => $query->visibleToResearchHead())->orderBy('id');
                $currentIds = $model::query()->whereBelongsTo($topic, 'topic')->latestSubmittedVersion()->pluck('id');
                $columns = ['id', 'topic_id', 'submitted_by', 'reporting_date', 'reporting_quarter', 'version_number', 'submission_status', 'review_status', 'review_stage'];
                $columns = [...$columns, ...($model === ProjectProgressReport::class ? ['supersedes_report_id'] : ['report_type'])];
                foreach ($query->get($columns) as $report) {
                    $kind = $report instanceof ProjectProgressReport ? 'monitoring' : $report->report_type;
                    $key = self::reportKey($report);
                    $current = $currentIds->contains($report->id);
                    $scope = self::reportScope($topic, $report);
                    $label = $report->isPrepared() ? 'PDF prepared' : $report->review_status_label;
                    $actions = [];
                    if ($current && $topic->isMonitoringAvailable()) {
                        $name = $kind === 'monitoring' ? 'Monitoring Tool' : ($kind === 'terminal' ? 'Terminal Report' : 'Progress Report');
                        if (($head && $report->isSubmitted() && $report->review_status === 'pending')
                            || ($topic->canReviewImplementationReports($user) && $report->isAwaitingSecretaryReview())) {
                            $actions[] = ['label' => 'Review '.$name, 'url' => route('topics.show', ['topic' => $topic, 'live_status' => substr($scope, 0, 12)]).'#'.($kind === 'monitoring' ? 'monitoring-tool-' : 'narrative-report-').$report->id];
                        } elseif ($researcher && ($report->isPrepared() || $report->review_status === 'revision_requested')) {
                            $parameters = ['topic' => $topic];
                            if ($kind !== 'monitoring' || $report->isPrepared()) {
                                $parameters['reporting_date'] = $report->reporting_date?->toDateString();
                            }
                            if ($kind === 'monitoring') {
                                $parameters['revise_monitoring_report'] = $report->isPrepared() ? $report->supersedes_report_id : $report->id;
                            } else {
                                $parameters['report_type'] = $kind;
                            }
                            if (! $report->isPrepared() || $report->submitted_by === $user->id || $kind !== 'monitoring') {
                                $actions[] = ['label' => ($report->isPrepared() ? 'Review prepared ' : 'Correct ').$name, 'url' => route($kind === 'monitoring' ? 'project-progress.create' : 'project-narrative-reports.create', $parameters)];
                            }
                        }
                    }
                    $url = $kind === 'monitoring' ? route('topics.show', ['topic' => $topic, 'live_status' => substr($scope, 0, 12)]).'#monitoring-tool-'.$report->id : route('project-narrative-reports.show', $report);
                    if ($report->isPrepared()) {
                        $url = $actions[0]['url'] ?? route('topics.show', $topic).'#quarterly-reports';
                    }
                    $reports[$key] = [
                        'kind' => $kind, 'quarter' => $report->reporting_quarter,
                        'url' => $url,
                        'scope' => $scope, 'current' => $current, 'label' => $label, 'status' => $report->review_status,
                        'html' => view('topics.partials.live-report-status', ['report' => $report, 'label' => $label, 'compact' => false])->render(),
                        'compact_html' => view('topics.partials.live-report-status', ['report' => $report, 'label' => $label, 'compact' => true])->render(),
                        'actions' => $actions,
                    ];
                }
            }
        }

        return ['account_id' => $user->id, 'workspace' => $user->activeWorkspace(), 'proposal' => $proposal, 'reports' => $reports];
    }

    /** @return array{proposal: string, reports: array<string, string>} */
    public function snapshot(TopicProposal $topic, User $user, ?ProposalVersion $version): array
    {
        if (! $topic->hasIssuedNoticeToProceed() || ! ($user->isUsingWorkspace(User::WORKSPACE_RESEARCH_HEAD)
            || ($user->isUsingWorkspace(User::WORKSPACE_FACULTY_RESEARCHER) && $topic->isAccessibleTo($user))
            || $topic->canReviewImplementationReports($user))) {
            return ['proposal' => self::proposalScope($topic, $version), 'reports' => []];
        }
        $reports = collect($topic->getRelation('progressReports'))->merge($topic->getRelation('narrativeReports'));
        if ($user->isUsingWorkspace(User::WORKSPACE_FACULTY_RESEARCHER)) {
            $reports = $reports->merge($topic->getRelation('preparedProgressReports'))
                ->merge($topic->getRelation('preparedNarrativeReports'));
        }

        return ['proposal' => self::proposalScope($topic, $version), 'reports' => $reports
            ->filter(fn (ProjectProgressReport|ProjectNarrativeReport $report): bool => ! $user->isUsingWorkspace(User::WORKSPACE_RESEARCH_HEAD) || $report->isAvailableToResearchHead())
            ->mapWithKeys(fn (ProjectProgressReport|ProjectNarrativeReport $report): array => [self::reportKey($report) => self::reportScope($topic, $report)])->sortKeys()->all()];
    }

    public static function reportKey(ProjectProgressReport|ProjectNarrativeReport $report): string
    {
        return ($report instanceof ProjectProgressReport ? 'monitoring' : $report->report_type).':'.$report->id;
    }

    public static function reportScope(TopicProposal $topic, ProjectProgressReport|ProjectNarrativeReport $report): string
    {
        return hash('sha256', json_encode([$topic->status, $topic->project_status, $report->id, $report->review_status, $report->review_stage, $report->submission_status], JSON_THROW_ON_ERROR));
    }

    public static function proposalScope(TopicProposal $topic, ?ProposalVersion $version): string
    {
        return hash('sha256', json_encode([$topic->status, $topic->project_status, $topic->review_stage, $version?->id, $topic->workflowStatusLabel($version), $topic->research_head_viewed_version_id, $topic->notice_to_proceed_issued_at?->toDateTimeString()], JSON_THROW_ON_ERROR));
    }

    /** @return array<string, mixed> */
    private function proposal(TopicProposal $topic, ?ProposalVersion $version, User $user): array
    {
        $scope = self::proposalScope($topic, $version);
        $label = $topic->isCompletedProject() ? 'Completed - archived' : ($topic->isAwaitingNoticeToProceed() ? 'Final signing' : $topic->workflowStatusLabel($version));
        $class = match ($topic->status) {
            'approved' => $topic->isAwaitingNoticeToProceed() ? 'bg-amber-100 text-amber-800' : 'bg-gray-950 text-white dark:bg-white dark:text-gray-950',
            'ready_for_signature' => 'bg-red-50 text-red-800',
            'rejected' => 'bg-red-50 text-red-700',
            'revision_requested' => 'bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-300',
            'resubmitted', 'expert_review', 'for_final_decision' => 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200',
            default => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200',
        };
        if ($topic->isCompletedProject()) {
            $class = 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200';
        }
        $url = route('topics.show', ['topic' => $topic, 'live_status' => substr($scope, 0, 12)]);
        $actions = [['label' => 'Open current project', 'url' => $url]];
        if ($user->isUsingWorkspace(User::WORKSPACE_FACULTY) && $topic->user_id === $user->id && $topic->status === 'revision_requested') {
            $actions = [['label' => 'Revise proposal', 'url' => route('faculty.topics.revision', $topic)]];
        }

        return ['scope' => $scope, 'status' => $topic->status, 'label' => $label, 'class' => 'rounded-full px-3 py-1.5 text-sm font-black '.$class, 'actions' => $actions];
    }
}
