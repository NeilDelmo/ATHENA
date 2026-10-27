<?php

namespace App\Services;

use App\Models\ProposalVersionFile;
use App\Models\TopicCollaborator;
use App\Models\TopicProposal;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CollegeQuarterlyReportData
{
    public function __construct(
        private readonly MonitoringQuarterService $quarters,
        private readonly NoticeToProceedDataService $noticeData,
    ) {}

    /**
     * @return array{presentations: list<list<string>>, publications: list<list<string>>, research: list<list<string>>, undated_publications: int}
     */
    public function build(User $coordinator, int $year, int $quarter): array
    {
        $data = ['presentations' => [], 'publications' => [], 'research' => [], 'undated_publications' => 0];
        if (blank($coordinator->college)) {
            return $data;
        }

        $period = $this->quarters->forYearAndQuarter($year, $quarter);
        $projects = TopicProposal::query()->withIssuedNotice()
            ->where('notice_to_proceed_issued_at', '<=', $period['end'])
            ->forCollege($coordinator->college)
            ->with([
                'user', 'researchSecretary', 'collaborators.user', 'researchCall',
                'versions' => fn (HasMany $versions): HasMany => $versions->reorder()->orderByDesc('version_number')->limit(1),
                'versions.files' => fn (HasMany $files): HasMany => $files
                    ->whereIn('document_type', [ProposalVersionFile::TYPE_DETAILED_PROPOSAL, ProposalVersionFile::TYPE_LINE_ITEM_BUDGET, ProposalVersionFile::TYPE_WORK_PLAN])
                    ->select(['id', 'proposal_version_id', 'document_type', 'source_data']),
                'progressReports' => fn (HasMany $reports): HasMany => $reports->submitted()->visibleToResearchHead()->where('reporting_date', '<=', $period['end']->toDateString())
                    ->select(['id', 'topic_id', 'progress_percentage'])
                    ->reorder()->orderByRaw('COALESCE(period_end, reporting_date) DESC')->orderByDesc('version_number')->orderByDesc('id')->limit(1),
            ])->orderBy('title')->orderBy('id')->get();

        foreach ($projects as $project) {
            $window = $this->quarters->reportingWindow($project);
            if ($window['start']->greaterThan($period['end']) || $window['end']?->lessThan($period['start'])) {
                continue;
            }
            $notice = $this->noticeData->defaults($project);
            $months = (int) ($notice['approved_duration_months'] ?? 0);
            $latest = $project->progressReports->first();
            $status = $latest
                ? ($latest->progress_percentage >= 100 ? 'Completion pending' : 'Ongoing').' ('.$latest->progress_percentage.'% reported)'
                : Str::headline($project->project_status ?? TopicProposal::PROJECT_STATUS_ONGOING).' (current status)';
            $data['research'][] = [
                $project->title,
                $months > 0 ? $months.' '.Str::plural('month', $months) : '',
                $this->personnel($project, $notice['researcher_names']), $status,
            ];
        }

        return $data;
    }

    /** @param list<string> $approvedNames */
    private function personnel(TopicProposal $project, array $approvedNames): string
    {
        $secretaries = $project->collaborators->filter(fn (TopicCollaborator $collaborator): bool => $collaborator->isProjectSecretary());
        $secretaryNames = $secretaries->pluck('name')->merge($secretaries->pluck('user.name'))
            ->push($project->researchSecretary?->name)->filter()->map(fn (string $name): string => Str::lower(Str::squish($name)));
        $files = $project->versions->sortByDesc('version_number')->first()?->files ?? collect();
        $proposal = $files->firstWhere('document_type', ProposalVersionFile::TYPE_DETAILED_PROPOSAL)?->source_data ?? [];
        $budget = $files->firstWhere('document_type', ProposalVersionFile::TYPE_LINE_ITEM_BUDGET)?->source_data ?? [];
        $leader = trim((string) ($proposal['project_leader_display'] ?? ''));
        if ($leader === '') {
            $leader = trim((string) ($budget['project_leader_display'] ?? $budget['project_leader'] ?? ''));
        }
        $roles = $leader !== '' ? [Str::lower(Str::squish($leader)) => 'Project Leader'] : [];
        foreach (($proposal['staff'] ?? []) ?: ($budget['staff'] ?? []) as $member) {
            if (! is_array($member)) {
                continue;
            }
            $name = trim((string) ($member['display_name'] ?? ''));
            if ($name === '') {
                $name = trim(($member['title'] ?? '').' '.($member['name'] ?? ''));
            }
            if ($secretaryNames->contains(Str::lower(Str::squish($member['name'] ?? '')))) {
                $secretaryNames->push(Str::lower(Str::squish($name)));
            }
            if ($name !== '') {
                $roles[Str::lower(Str::squish($name))] ??= 'Project Staff';
            }
        }

        return collect($approvedNames)->push($project->user?->name)
            ->merge($project->collaborators->filter(fn (TopicCollaborator $collaborator): bool => $collaborator->accepted_at !== null
                && ! $collaborator->isProjectSecretary())->map(fn (TopicCollaborator $collaborator): string => $collaborator->user?->name ?? $collaborator->name))
            ->filter()->map(fn (string $name): string => trim($name))
            ->reject(fn (string $name): bool => $secretaryNames->contains(Str::lower(Str::squish($name))))
            ->unique(fn (string $name): string => Str::lower(Str::squish($name)))
            ->map(fn (string $name): string => isset($roles[Str::lower(Str::squish($name))]) ? $name.' ('.$roles[Str::lower(Str::squish($name))].')' : $name)
            ->implode("\n");
    }
}
