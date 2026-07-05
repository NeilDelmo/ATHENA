<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use App\Models\User;
use App\Models\TopicProposal;
use App\Models\ResearchCall;
use App\Models\ResearchCategory;
use Illuminate\Support\Facades\Hash;

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
        $expertRole = Role::firstOrCreate(['name' => 'expert']);

        $researchHeadEmail = '23-78498@g.batstate-u.edu.ph';

        $head = User::firstOrCreate(['email' => $researchHeadEmail], [
            'name' => 'Research Head',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
        $head->syncRoles([$researchHeadRole]);

        $faculty = User::firstOrCreate(['email' => 'faculty@example.com'], [
            'name' => 'Demo Faculty',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
        $faculty->syncRoles([$facultyRole]);

        $expert = User::firstOrCreate(['email' => 'expert@example.com'], [
            'name' => 'Environmental Subject Expert',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
        $expert->syncRoles([$expertRole]);

        $categories = collect(['Environment', 'Education', 'Technology', 'Health'])
            ->map(fn (string $name) => ResearchCategory::firstOrCreate(['name' => $name]));

        $call = ResearchCall::firstOrCreate(['title' => 'Institutional Research Call 2026'], [
            'academic_year' => '2026-2027',
            'term' => 'First Semester',
            'description' => 'Prototype institutional call for faculty research proposals.',
            'opens_at' => now()->subWeek(),
            'closes_at' => now()->addMonths(2),
            'max_active_research_per_faculty' => 2,
            'maximum_budget' => 500000,
            'status' => 'open',
            'created_by' => $head->id,
        ]);
        $call->categories()->sync($categories->pluck('id'));

        User::where('email', '!=', $researchHeadEmail)
            ->get()
            ->each(function (User $user) use ($facultyRole) {
                if (! $user->roles()->exists()) {
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
