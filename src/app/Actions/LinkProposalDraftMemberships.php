<?php

namespace App\Actions;

use App\Models\ProposalDraftMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LinkProposalDraftMemberships
{
    public function handle(User $user): int
    {
        if ($user->email_verified_at === null) {
            return 0;
        }

        $memberships = ProposalDraftMember::query()
            ->whereNull('user_id')
            ->where('email', mb_strtolower(trim($user->email)))
            ->get();

        foreach ($memberships as $membership) {
            DB::transaction(function () use ($membership, $user): void {
                $existing = ProposalDraftMember::query()
                    ->where('proposal_draft_id', $membership->proposal_draft_id)
                    ->where('user_id', $user->getKey())
                    ->whereKeyNot($membership->getKey())
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    $membership->delete();

                    return;
                }

                $membership->update([
                    'user_id' => $user->getKey(),
                    'name' => $user->name,
                    'email' => mb_strtolower(trim($user->email)),
                ]);
            }, 3);
        }

        return $memberships->count();
    }
}
