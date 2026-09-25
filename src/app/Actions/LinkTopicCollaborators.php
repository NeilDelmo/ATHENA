<?php

namespace App\Actions;

use App\Models\TopicCollaborator;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class LinkTopicCollaborators
{
    public function handle(User $user): int
    {
        if ($user->email_verified_at === null) {
            return 0;
        }

        $email = mb_strtolower(trim($user->email));

        return DB::transaction(function () use ($email, $user): int {
            $collaborators = TopicCollaborator::query()
                ->whereNull('user_id')
                ->where('email', $email)
                ->whereNotNull('accepted_at')
                ->with('topic')
                ->lockForUpdate()
                ->get();

            if ($collaborators->isEmpty()) {
                return 0;
            }

            $researcherRole = Role::findOrCreate(User::WORKSPACE_FACULTY_RESEARCHER, 'web');
            $promoteUser = false;

            foreach ($collaborators as $collaborator) {
                $collaborator->update([
                    'user_id' => $user->getKey(),
                    'name' => $user->name,
                    'email' => $email,
                ]);

                if ($collaborator->isProjectSecretary()) {
                    $collaborator->topic?->update(['research_secretary_id' => $user->getKey()]);
                }

                $promoteUser = $promoteUser || $collaborator->topic?->hasIssuedNoticeToProceed();
            }

            if ($promoteUser) {
                $user->assignRole($researcherRole);
            }

            return $collaborators->count();
        }, 3);
    }
}
