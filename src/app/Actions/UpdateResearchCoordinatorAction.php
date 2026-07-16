<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class UpdateResearchCoordinatorAction
{
    public function handle(User $member, string $action): void
    {
        $coordinatorRole = Role::findOrCreate('research_coordinator');

        DB::transaction(function () use ($action, $coordinatorRole, $member) {
            if ($action === 'remove') {
                $member->removeRole($coordinatorRole);

                return;
            }

            User::role($coordinatorRole)
                ->where('college', $member->college)
                ->whereKeyNot($member->getKey())
                ->lockForUpdate()
                ->get()
                ->each->removeRole($coordinatorRole);

            $member->assignRole($coordinatorRole);
        });
    }
}
