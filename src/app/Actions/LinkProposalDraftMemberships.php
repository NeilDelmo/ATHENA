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
            ->whereHas('draft')
            ->whereNull('user_id')
            ->where('email', mb_strtolower(trim($user->email)))
            ->get();

        foreach ($memberships as $membership) {
            DB::transaction(function () use ($membership, $user): void {
                $draft = $membership->draft()->lockForUpdate()->first();
                $unlinked = $draft?->members()->whereNull('user_id')->lockForUpdate()->find($membership->getKey());
                if ($unlinked === null) {
                    return;
                }

                $existing = $draft->members()
                    ->where('user_id', $user->getKey())
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    $unlinked->delete();

                    return;
                }

                $unlinked->update([
                    'user_id' => $user->getKey(),
                    'name' => $user->name,
                    'email' => mb_strtolower(trim($user->email)),
                ]);
            }, 3);
        }

        return $memberships->count();
    }
}
