<?php

namespace Database\Factories;

use App\Models\ProjectJournalSubmission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectJournalSubmission>
 */
class ProjectJournalSubmissionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'added_by' => User::factory(),
            'fingerprint' => hash('sha256', fake()->unique()->uuid()),
            'journal_name' => fake()->words(3, true).' Journal',
            'manuscript_title' => fake()->sentence(),
            'status' => 'shortlisted',
        ];
    }
}
