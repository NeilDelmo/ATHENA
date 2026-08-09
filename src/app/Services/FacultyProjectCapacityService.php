<?php

namespace App\Services;

use App\Models\TopicProposal;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class FacultyProjectCapacityService
{
    public function ensureAvailableFor(TopicProposal $topic): void
    {
        User::query()
            ->whereKey($topic->user_id)
            ->lockForUpdate()
            ->firstOrFail();

        $occupiedSlots = TopicProposal::query()
            ->where('user_id', $topic->user_id)
            ->whereKeyNot($topic->getKey())
            ->occupiesCapacity()
            ->count();
        $configuredLimit = (int) ($topic->researchCall()
            ->value('max_active_research_per_faculty') ?? TopicProposal::MAX_CONCURRENT_APPROVED_PROJECTS);
        $effectiveLimit = min(
            max($configuredLimit, 1),
            TopicProposal::MAX_CONCURRENT_APPROVED_PROJECTS,
        );

        if ($occupiedSlots >= $effectiveLimit) {
            throw ValidationException::withMessages([
                'status' => "This faculty member already has the maximum of {$effectiveLimit} concurrent approved research projects allowed for this call. Complete an active project before approving another one.",
            ]);
        }
    }
}
