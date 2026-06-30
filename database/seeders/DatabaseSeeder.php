<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use App\Models\User;
use App\Models\TopicProposal;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $researchHeadRole = Role::firstOrCreate(['name' => 'research_head']);
        $facultyRole = Role::firstOrCreate(['name' => 'faculty']);
        $facultyResearcherRole = Role::firstOrCreate(['name' => 'faculty_researcher']);

        $researchHeadEmail = '23-78498@g.batstate-u.edu.ph';

        $head = User::where('email', $researchHeadEmail)->first();

        if ($head) {
            $head->syncRoles([$researchHeadRole]);
        }

        User::where('email', '!=', $researchHeadEmail)
            ->get()
            ->each(function (User $user) use ($facultyRole) {
                if (! $user->hasRole('faculty')) {
                    $user->assignRole($facultyRole);
                }
            });

        TopicProposal::with('user')
            ->where('status', 'approved')
            ->get()
            ->each(function (TopicProposal $topic) use ($facultyRole, $facultyResearcherRole) {
                $topic->user?->assignRole([$facultyRole, $facultyResearcherRole]);
            });
    }
}
