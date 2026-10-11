<?php

namespace App\Support;

use App\Models\ProposalDraft;
use App\Models\TopicProposal;
use App\Services\ResearchDashboardAnalytics;
use Illuminate\Support\Collection;

class DashboardAssistantSuggestions
{
    /**
     * @param  Collection<int, TopicProposal>  $topics
     * @param  Collection<int, ProposalDraft>  $drafts
     * @param  Collection<int, array{completed: int, total: int, percentage: int}>  $draftProgress
     * @return Collection<int, array<string, mixed>>
     */
    public function forFaculty(Collection $topics, Collection $drafts, Collection $draftProgress): Collection
    {
        $topicSuggestions = $this->forTopics($topics);
        $draftSuggestions = $drafts->whereNull('topic_id')->where('status', ProposalDraft::STATUS_DRAFT)
            ->filter(fn (ProposalDraft $draft): bool => ($draftProgress[$draft->id]['completed'] ?? 0) < ($draftProgress[$draft->id]['total'] ?? 0))
            ->map(fn (ProposalDraft $draft): array => [
                'label' => 'Check draft consistency',
                'target_label' => $draft->project_title ?: 'Untitled proposal',
                'evidence' => 'Draft in preparation · '.$draftProgress[$draft->id]['completed'].'/'.$draftProgress[$draft->id]['total'].' papers ready.',
                'prompt' => 'Review this proposal draft for consistency between its objectives, methods, work plan, and budget. Identify missing evidence and actionable corrections.',
                'context' => ['proposal_draft_id' => $draft->id, 'workflow_scope' => 'review'],
                'action' => ['type' => 'review_proposal', 'review_focus' => 'consistency'],
            ]);

        return $topicSuggestions->where('action.review_focus', 'revision_response')
            ->concat($draftSuggestions)
            ->concat($topicSuggestions->where('action.review_focus', 'consistency'))
            ->take(3)->values();
    }

    /**
     * @param  Collection<int, TopicProposal>  $topics
     * @return Collection<int, array<string, mixed>>
     */
    public function forResearchHead(Collection $topics): Collection
    {
        return $this->forTopics($topics)->take(3)->values();
    }

    /**
     * @param  Collection<int, TopicProposal>  $topics
     * @return Collection<int, array{
     *     label: string, target_label: string, evidence: string, prompt: string,
     *     context: array{topic_id: int, workflow_scope: string},
     *     action: array{type: string, review_focus: string}
     * }>
     */
    private function forTopics(Collection $topics): Collection
    {
        return $topics->whereIn('status', array_diff(array_keys(ResearchDashboardAnalytics::STAGES), ['ready_for_signature']))
            ->sortBy(fn (TopicProposal $topic): int => in_array($topic->status, ['revision_requested', 'resubmitted'], true) ? 0 : 1)
            ->map(function (TopicProposal $topic): array {
                $isRevision = in_array($topic->status, ['revision_requested', 'resubmitted'], true);

                return [
                    'label' => $isRevision ? 'Check revision responses' : 'Check proposal consistency',
                    'target_label' => $topic->title,
                    'evidence' => $topic->status === 'resubmitted' ? 'Resubmitted proposal awaiting review.' : ResearchDashboardAnalytics::STAGES[$topic->status].'.',
                    'prompt' => $isRevision
                        ? 'Review this proposal against the recorded reviewer comments and revision responses. Identify which comments are addressed, unresolved, or unsupported by the available evidence.'
                        : 'Review this proposal for consistency between its objectives, methods, work plan, and budget. Identify evidence-backed issues and actionable corrections.',
                    'context' => ['topic_id' => $topic->id, 'workflow_scope' => 'review'],
                    'action' => ['type' => 'review_proposal', 'review_focus' => $isRevision ? 'revision_response' : 'consistency'],
                ];
            })->values();
    }
}
