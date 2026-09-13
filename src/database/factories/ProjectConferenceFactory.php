<?php

namespace Database\Factories;

use App\Models\ProjectConference;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectConference>
 */
class ProjectConferenceFactory extends Factory
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
            'title' => fake()->sentence(),
            'url' => fake()->url(),
            'fingerprint' => hash('sha256', fake()->uuid()),
            'status' => 'shortlisted',
        ];
    }
}
