<?php

namespace App\Services;

use App\Models\ProjectNarrativeReport;
use App\Models\ProjectProgressReport;
use App\Models\TopicProposal;
use App\Models\User;
use App\Notifications\ProposalActivityNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProjectReportReviewService
{
    public function reviewBySecretary(ProjectProgressReport|ProjectNarrativeReport $report, User $user, string $decision, ?string $remarks): void
    {
        $report = DB::transaction(function () use ($report, $user, $decision, $remarks): ProjectProgressReport|ProjectNarrativeReport {
            $topic = TopicProposal::query()->whereKey($report->topic_id)->lockForUpdate()->firstOrFail();
            $report = $report->newQuery()->whereKey($report->id)->lockForUpdate()->firstOrFail();
            $report->setRelation('topic', $topic);
            abort_unless($topic->canReviewImplementationReports($user), 403);
            $this->ensureReviewable($report);
            if (! $report->isAwaitingSecretaryReview()) {
                throw ValidationException::withMessages(['review' => 'This report is no longer awaiting Research Office Secretary review. Reload the project.']);
            }
            if ($decision === 'forward' && ! $this->hasAvailablePdf($report)) {
                throw ValidationException::withMessages(['review' => 'The submitted report PDF is unavailable. Request a corrected submission before forwarding it.']);
            }
            $report->update([
                'review_stage' => $decision === 'forward' ? 'head' : 'secretary',
                'review_status' => $decision === 'forward' ? 'pending' : 'revision_requested',
                'secretary_remarks' => $remarks,
                'secretary_reviewed_by' => $user->id,
                'secretary_reviewed_at' => now(),
                'forwarded_at' => $decision === 'forward' ? now() : $report->forwarded_at,
            ]);

            return $report;
        });

        if ($decision === 'forward') {
            $this->notify(User::role(User::WORKSPACE_RESEARCH_HEAD)->get(), $report, 'Report forwarded for Research Head review', 'The Research Office Secretary checked and forwarded this report.', User::WORKSPACE_RESEARCH_HEAD);
        } else {
            $this->notify(new Collection([$report->topic->user]), $report, 'Report corrections requested', 'The Research Office Secretary requested corrections to this report.', User::WORKSPACE_FACULTY_RESEARCHER, 'warning');
        }
    }

    public function reviewByHead(ProjectProgressReport|ProjectNarrativeReport $report, User $user, string $status, ?string $remarks): void
    {
        abort_unless($user->isUsingWorkspace(User::WORKSPACE_RESEARCH_HEAD), 403);
        $signedCopyPath = null;
        $report = DB::transaction(function () use ($report, $user, $status, $remarks, &$signedCopyPath): ProjectProgressReport|ProjectNarrativeReport {
            $topic = TopicProposal::query()->whereKey($report->topic_id)->lockForUpdate()->firstOrFail();
            $report = $report->newQuery()->whereKey($report->id)->lockForUpdate()->firstOrFail();
            $report->setRelation('topic', $topic);
            $this->ensureReviewable($report);
            abort_unless($report->isAvailableToResearchHead(), 403);
            $reopeningTerminal = $report instanceof ProjectNarrativeReport && $report->report_type === 'terminal'
                && $report->review_status === 'reviewed' && $status === 'revision_requested';
            if ($report->review_status !== 'pending' && ! $reopeningTerminal) {
                throw ValidationException::withMessages(['review' => 'This report has already been reviewed. Reload the project.']);
            }
            if ($status === 'reviewed' && filled($report->official_pdf_path) && ! $this->hasAvailablePdf($report)) {
                throw ValidationException::withMessages(['review' => 'The submitted report PDF is unavailable or changed. Request a corrected submission before accepting it.']);
            }
            $reviewAttributes = [
                'review_stage' => $status === 'reviewed' ? 'head' : 'secretary',
                'review_status' => $status === 'reviewed' ? 'reviewed' : 'pending',
                'research_head_remarks' => $remarks,
                'reviewed_by' => $user->id,
                'reviewed_at' => now(),
            ];
            if ($status === 'revision_requested' && $report instanceof ProjectNarrativeReport && $report->hasSignedCopy()) {
                $terminalData = $report->terminal_data;
                $signedCopyPath = $report->signedCopy()['path'];
                unset($terminalData['signed_copy']);
                $reviewAttributes['terminal_data'] = $terminalData;
            }
            $report->update($reviewAttributes);

            return $report;
        });

        if (filled($signedCopyPath)) {
            Storage::disk('local')->delete($signedCopyPath);
        }

        if ($status === 'revision_requested') {
            $this->notify($this->officeSecretaries($report->topic), $report, 'Research Head returned report concerns', 'The Research Head returned concerns to the Research Office Secretary for review.', User::WORKSPACE_RESEARCH_SECRETARY, 'warning');
        } else {
            $this->notify(new Collection([$report->topic->user]), $report, 'Report accepted by Research Head', 'The Research Head accepted this report.', User::WORKSPACE_FACULTY_RESEARCHER, 'success');
        }
    }

    public function notifySecretarySubmission(ProjectProgressReport|ProjectNarrativeReport $report): void
    {
        $this->notify($this->officeSecretaries($report->topic), $report, 'Report submitted for Research Office Secretary review', 'A report is ready for checking before it is forwarded to the Research Head.', User::WORKSPACE_RESEARCH_SECRETARY);
    }

    public function officeSecretaries(TopicProposal $topic): Collection
    {
        $college = $topic->projectCollege();

        return filled($college)
            ? User::role(User::WORKSPACE_RESEARCH_SECRETARY)->whereIn('college', TopicProposal::collegeAliases($college))->get()
            : new Collection;
    }

    private function ensureReviewable(ProjectProgressReport|ProjectNarrativeReport $report): void
    {
        abort_unless($report->topic->isMonitoringAvailable() && $report->topic()->withIssuedNotice()->exists() && $report->isSubmitted(), 404);
        if (! $report->isLatestSubmittedVersion()) {
            throw ValidationException::withMessages(['review' => 'A newer version of this report is already in progress. Review the latest submission.']);
        }
    }

    public function hasAvailablePdf(ProjectProgressReport|ProjectNarrativeReport $report): bool
    {
        if (filled($report->official_pdf_path)) {
            return filled($report->official_pdf_filename) && filled($report->official_pdf_checksum)
                && Storage::disk('local')->exists($report->official_pdf_path)
                && Storage::disk('local')->size($report->official_pdf_path) > 0
                && hash_equals($report->official_pdf_checksum, hash_file('sha256', Storage::disk('local')->path($report->official_pdf_path)));
        }

        return $report instanceof ProjectNarrativeReport || is_array($report->work_plan) && is_array($report->budget_utilization);
    }

    public function hasAvailableSignedCopy(ProjectNarrativeReport $report): bool
    {
        $signedCopy = $report->signedCopy();

        return $report->hasSignedCopy() && is_string($signedCopy['checksum'] ?? null)
            && Storage::disk('local')->exists($signedCopy['path'])
            && Storage::disk('local')->size($signedCopy['path']) > 0
            && hash_equals($signedCopy['checksum'], hash_file('sha256', Storage::disk('local')->path($signedCopy['path'])));
    }

    private function notify(Collection $recipients, ProjectProgressReport|ProjectNarrativeReport $report, string $title, string $message, string $workspace, string $level = 'info'): void
    {
        $anchor = $report instanceof ProjectProgressReport ? 'monitoring-tool-'.$report->id : 'project-monitoring';
        $recipients->each->notify(new ProposalActivityNotification(
            title: $title,
            message: $message.' Project: '.$report->topic->title,
            url: route('topics.show', $report->topic).'#'.$anchor,
            level: $level,
            topicId: $report->topic_id,
            workspace: $workspace,
            sidebarArea: $workspace === User::WORKSPACE_FACULTY_RESEARCHER
                ? ProposalActivityNotification::SIDEBAR_AREA_MY_PROJECTS
                : ProposalActivityNotification::SIDEBAR_AREA_PROJECT_MONITORING,
        ));
    }
}
