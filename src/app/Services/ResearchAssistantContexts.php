<?php

namespace App\Services;

use App\Models\TopicProposal;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

class ResearchAssistantContexts
{
    /** @return Collection<int, array<string, mixed>> */
    public function forUser(User $user, ?TopicProposal $activeTopic = null): Collection
    {
        $topics = TopicProposal::query()
            ->when(! $user->isUsingWorkspace(User::WORKSPACE_RESEARCH_HEAD), fn ($query) => $query->accessibleTo($user))
            ->when($user->isUsingWorkspace(User::WORKSPACE_FACULTY_RESEARCHER), fn ($query) => $query->visibleInResearcherWorkspace())
            ->with($this->relations())
            ->latest()
            ->get(['id', 'user_id', 'title', 'status', 'project_status', 'notice_to_proceed_issued_at', 'research_category_id', 'research_call_id']);

        if ($activeTopic !== null) {
            $topics->prepend($topics->firstWhere('id', $activeTopic->getKey()) ?? $activeTopic);
        }

        return $topics->unique(fn (TopicProposal $topic): int => $topic->getKey())
            ->values()->map(fn (TopicProposal $topic): array => $this->summary($topic, $user));
    }

    /** @return array<string, mixed> */
    public function summary(TopicProposal $topic, User $user): array
    {
        $topic->loadMissing($this->relations());

        return [
            'id' => $topic->id,
            'label' => $topic->title,
            'status' => str_replace('_', ' ', $topic->status),
            'dissemination' => $user->isUsingWorkspace(User::WORKSPACE_FACULTY_RESEARCHER)
                && $topic->isDisseminationAvailable() ? [
                    'journal_search_url' => route('research.dissemination.journals.search', $topic),
                    'journal_save_url' => route('research.dissemination.journal-submissions.store', $topic),
                    'conference_search_url' => route('research.dissemination.conferences.search', $topic),
                    'conference_save_url' => route('research.dissemination.conferences.store', $topic),
                    'journal_project_url' => route('research-support.index', ['topic' => $topic->id]).'#journal-submissions',
                    'conference_project_url' => route('research.dissemination.show', $topic).'#conferences',
                ] : null,
            'meta' => collect([
                $topic->category?->name,
                $topic->researchCall?->academic_year,
                $topic->latestVersion ? 'v'.$topic->latestVersion->version_number : null,
            ])->filter()->join(' · '),
        ];
    }

    /** @return array<string|int, mixed> */
    private function relations(): array
    {
        return [
            'category:id,name',
            'researchCall:id,academic_year',
            'latestVersion' => fn (HasOne $relation): HasOne => $relation->select(
                $relation->getRelated()->qualifyColumns(['id', 'topic_id', 'version_number']),
            ),
        ];
    }
}
