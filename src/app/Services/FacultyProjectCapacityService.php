<?php

namespace App\Services;

use App\Models\ProposalDraft;
use App\Models\ResearchCall;
use App\Models\TopicProposal;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class FacultyProjectCapacityService
{
    /**
     * @return array{approved: int, pending: int, limit: int}
     */
    public function workloadFor(User $faculty, ?ResearchCall $researchCall = null): array
    {
        $participatingTopics = $this->participatingTopicsQuery($faculty);

        return [
            'approved' => (clone $participatingTopics)->occupiesCapacity()->count(),
            'pending' => (clone $participatingTopics)->awaitingApproval()->count(),
            'limit' => $this->limitFor($researchCall),
        ];
    }

    public function warningForAdditionalParticipation(User $faculty, ?ResearchCall $researchCall = null): ?string
    {
        $workload = $this->workloadFor($faculty, $researchCall);

        if (($workload['approved'] + $workload['pending']) < $workload['limit']) {
            return null;
        }

        return 'Potential workload conflict: '.$faculty->name
            .' is currently participating in '.$workload['approved'].' approved active '
            .str('research project')->plural($workload['approved'])
            .' and has '.$workload['pending'].' '
            .str('proposal')->plural($workload['pending']).' pending approval. '
            .'Approving additional proposals may exceed the maximum allowed active research participation of '
            .$workload['limit'].'.';
    }

    public function ensureAvailableFor(TopicProposal $topic): void
    {
        $participants = $this->participantsFor($topic, lockForUpdate: true);
        $researchCall = $topic->researchCall()->first();
        $limit = $this->limitFor($researchCall);
        $blockedParticipants = [];

        foreach ($participants as $participant) {
            $occupiedSlots = $this->participatingTopicsQuery($participant, $topic)
                ->occupiesCapacity()
                ->count();

            if ($occupiedSlots >= $limit) {
                $blockedParticipants[] = 'Approval cannot continue. '.$participant->name
                    .' is already participating in the maximum of '.$limit
                    .' active approved '.str('research project')->plural($limit).'.';
            }
        }

        if ($blockedParticipants !== []) {
            throw ValidationException::withMessages(['status' => $blockedParticipants]);
        }
    }

    public function ensureSubmissionAvailableFor(ProposalDraft $draft): void
    {
        $draft->loadMissing(['members', 'researchCall']);
        $participants = $this->participantsForDraft($draft, lockForUpdate: true);
        $limit = $this->limitFor($draft->researchCall);
        $blockedParticipants = [];

        foreach ($participants as $participant) {
            $occupiedSlots = $this->participatingTopicsQuery($participant)
                ->occupiesSubmissionCapacity()
                ->count();

            if ($occupiedSlots >= $limit) {
                $blockedParticipants[] = 'Submission cannot continue. '.$participant->name
                    .' is already participating in the maximum of '.$limit
                    .' submitted or active research '.str('project')->plural($limit)
                    .'. A slot becomes available when a proposal is rejected or an approved project is completed.';
            }
        }

        if ($blockedParticipants !== []) {
            throw ValidationException::withMessages(['status' => $blockedParticipants]);
        }
    }

    /**
     * @return Collection<int, User>
     */
    private function participantsFor(TopicProposal $topic, bool $lockForUpdate = false): Collection
    {
        $topic->loadMissing('collaborators');

        return $this->participatingUsers(
            $topic->user_id,
            $topic->collaborators,
            $lockForUpdate,
        );
    }

    /**
     * @return Collection<int, User>
     */
    private function participantsForDraft(ProposalDraft $draft, bool $lockForUpdate = false): Collection
    {
        return $this->participatingUsers(
            $draft->user_id,
            $draft->members,
            $lockForUpdate,
        );
    }

    /**
     * @param  Collection<int, mixed>  $members
     * @return Collection<int, User>
     */
    private function participatingUsers(int $ownerId, Collection $members, bool $lockForUpdate): Collection
    {
        $acceptedMembers = $members->whereNotNull('accepted_at');

        $participantIds = $acceptedMembers
            ->pluck('user_id')
            ->filter()
            ->push($ownerId)
            ->unique()
            ->sort()
            ->values();
        $unlinkedEmails = $acceptedMembers
            ->whereNull('user_id')
            ->pluck('email')
            ->filter()
            ->map(fn (string $email): string => mb_strtolower(trim($email)))
            ->unique()
            ->values();

        $query = User::query()
            ->where(function (Builder $participants) use ($participantIds, $unlinkedEmails): void {
                $participants->whereKey($participantIds->all());

                if ($unlinkedEmails->isNotEmpty()) {
                    $participants->orWhere(function (Builder $matchedEmail) use ($unlinkedEmails): void {
                        $matchedEmail
                            ->whereNotNull('email_verified_at')
                            ->whereIn('email', $unlinkedEmails->all());
                    });
                }
            })
            ->orderBy('id');

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->get();
    }

    private function participatingTopicsQuery(User $faculty, ?TopicProposal $except = null): Builder
    {
        return TopicProposal::query()
            ->when(
                $except !== null,
                fn (Builder $topics): Builder => $topics->whereKeyNot($except->getKey()),
            )
            ->where(function (Builder $participatingTopics) use ($faculty): void {
                $participatingTopics
                    ->where('user_id', $faculty->getKey())
                    ->orWhereHas(
                        'collaborators',
                        fn (Builder $collaborators): Builder => $collaborators->forUser($faculty),
                    );
            });
    }

    private function limitFor(?ResearchCall $researchCall): int
    {
        $configuredLimit = (int) ($researchCall?->max_active_research_per_faculty
            ?? TopicProposal::MAX_CONCURRENT_APPROVED_PROJECTS);

        return min(
            max($configuredLimit, 1),
            TopicProposal::MAX_CONCURRENT_APPROVED_PROJECTS,
        );
    }
}
