<?php

namespace App\Support;

use App\Models\ResearchCall;
use App\Models\ResearchCallDeadlineDismissal;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ResearchCallDeadlineNotice
{
    public const LOOKAHEAD_DAYS = 7;

    public const ENDED_LOOKBACK_DAYS = 7;

    public function forUser(?User $user): ?ResearchCall
    {
        if (! $this->isEligibleUser($user)) {
            return null;
        }

        $today = now(config('app.timezone'))->toDateString();
        $dismissalsTable = (new ResearchCallDeadlineDismissal)->getTable();
        $researchCallsTable = (new ResearchCall)->getTable();

        $approachingCall = ResearchCall::query()
            ->acceptingSubmissions()
            ->where('closes_at', '<=', now()->addDays(self::LOOKAHEAD_DAYS))
            ->whereDoesntHave('deadlineDismissals', function (Builder $query) use ($user, $today, $dismissalsTable, $researchCallsTable): void {
                $query
                    ->where('user_id', $user->id)
                    ->whereDate('dismissed_on', $today)
                    ->whereColumn("{$dismissalsTable}.deadline_at", "{$researchCallsTable}.closes_at");
            })
            ->oldest('closes_at')
            ->first(['id', 'title', 'closes_at']);

        if ($approachingCall instanceof ResearchCall) {
            return $approachingCall;
        }

        if (! $user->isUsingWorkspace(User::WORKSPACE_FACULTY)) {
            return null;
        }

        return ResearchCall::query()
            ->where('status', 'open')
            ->where('closes_at', '<', now())
            ->where('closes_at', '>=', now()->subDays(self::ENDED_LOOKBACK_DAYS))
            ->whereDoesntHave(
                'topics',
                fn (Builder $query): Builder => $query->accessibleTo($user),
            )
            ->whereDoesntHave('deadlineDismissals', function (Builder $query) use ($user, $today, $dismissalsTable, $researchCallsTable): void {
                $query
                    ->where('user_id', $user->id)
                    ->whereDate('dismissed_on', $today)
                    ->whereColumn("{$dismissalsTable}.deadline_at", "{$researchCallsTable}.closes_at");
            })
            ->latest('closes_at')
            ->first(['id', 'title', 'closes_at', 'status']);
    }

    public function canBeDismissedBy(?User $user, ResearchCall $researchCall): bool
    {
        if (! $this->isEligibleUser($user)) {
            return false;
        }

        if ($researchCall->isAcceptingSubmissions()) {
            return $researchCall->closes_at->lessThanOrEqualTo(now()->addDays(self::LOOKAHEAD_DAYS));
        }

        return $user->isUsingWorkspace(User::WORKSPACE_FACULTY)
            && $researchCall->status === 'open'
            && $researchCall->closes_at->isPast()
            && $researchCall->closes_at->greaterThanOrEqualTo(now()->subDays(self::ENDED_LOOKBACK_DAYS))
            && ! $researchCall->topics()->accessibleTo($user)->exists();
    }

    public function dismissForToday(User $user, ResearchCall $researchCall): void
    {
        ResearchCallDeadlineDismissal::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'research_call_id' => $researchCall->id,
            ],
            [
                'dismissed_on' => now(config('app.timezone'))->toDateString(),
                'deadline_at' => $researchCall->closes_at,
            ],
        );
    }

    private function isEligibleUser(?User $user): bool
    {
        return $user?->isUsingWorkspace([
            User::WORKSPACE_FACULTY,
            User::WORKSPACE_FACULTY_RESEARCHER,
            User::WORKSPACE_RESEARCH_HEAD,
        ]) ?? false;
    }
}
