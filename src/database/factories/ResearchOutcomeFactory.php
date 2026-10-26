<?php

namespace Database\Factories;

use App\Models\ResearchOutcome;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResearchOutcome>
 */
class ResearchOutcomeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'leader_id' => User::factory(),
            'title' => fake()->sentence(),
            'funding_source' => 'unfunded',
            'labels' => [],
        ];
    }
}
