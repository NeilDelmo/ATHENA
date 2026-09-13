<?php

namespace Database\Factories;

use App\Models\ResearchPublication;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResearchPublication>
 */
class ResearchPublicationFactory extends Factory
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
            'title' => fake()->sentence(),
            'authors' => fake()->name(),
            'year' => now()->year,
            'fingerprint' => hash('sha256', fake()->uuid()),
            'source' => 'Researcher entry',
            'confirmed_at' => now(),
        ];
    }
}
