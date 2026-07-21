<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Spatie\Permission\Models\Role;

class UpdateResearchCoordinatorAction
{
    public function handle(User $member, string $action): void
    {
        if ($action === 'assign' && blank($member->college)) {
            throw new InvalidArgumentException('A college is required before assigning a Research Coordinator.');
        }

        $coordinatorRole = Role::findOrCreate('research_coordinator');

        DB::transaction(function () use ($action, $coordinatorRole, $member) {
            if ($action === 'remove') {
                $member->removeRole($coordinatorRole);

                return;
            }

            User::query()
                ->select('id')
                ->where('college', $member->college)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

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
