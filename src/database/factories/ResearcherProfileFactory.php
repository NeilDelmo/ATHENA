<?php

namespace Database\Factories;

use App\Models\ResearcherProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResearcherProfile>
 */
class ResearcherProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'openalex_id' => 'A'.fake()->unique()->numberBetween(100000, 999999),
            'display_name' => fake()->name(),
            'confirmed_at' => now(),
        ];
    }
}
