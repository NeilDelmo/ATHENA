<?php

namespace App\Actions;

use App\Models\TopicCollaborator;
use App\Models\TopicProposal;
use App\Models\User;
use Spatie\Permission\Models\Role;

class PromoteTopicTeam
{
    /**
     * Enable the researcher workspace for this project's team.
     *
     * The role only unlocks the shared workspace routes. Topic collaborators
     * remain the source of truth for which project records each person may see.
     */
    public function handle(TopicProposal $topic): void
    {
        $facultyRole = Role::findOrCreate(User::WORKSPACE_FACULTY, 'web');
        $facultyResearcherRole = Role::findOrCreate(User::WORKSPACE_FACULTY_RESEARCHER, 'web');

        $topic->user()->firstOrFail()->assignRole([
            $facultyRole,
            $facultyResearcherRole,
        ]);

        $topic->collaborators()
            ->whereNotNull('accepted_at')
            ->with('user')
            ->get()
            ->each(function (TopicCollaborator $collaborator) use ($facultyResearcherRole, $topic): void {
                $user = $collaborator->user;

                if (! $user && filled($collaborator->email)) {
                    $user = User::query()
                        ->where('email', mb_strtolower(trim($collaborator->email)))
                        ->whereNotNull('email_verified_at')
                        ->first();

                    if ($user) {
                        $collaborator->update([
                            'user_id' => $user->getKey(),
                            'name' => $user->name,
                            'email' => mb_strtolower(trim($user->email)),
                        ]);
                    }
                }

                $user?->assignRole($facultyResearcherRole);

                if ($user && $collaborator->isProjectSecretary()) {
                    $topic->update(['research_secretary_id' => $user->getKey()]);
                }
            });
    }
}
