<?php

namespace Database\Factories;

use App\Models\ResearchAnnualTarget;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResearchAnnualTarget>
 */
class ResearchAnnualTargetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'academic_year' => fake()->unique()->numerify('AY-####'),
            'starts_on' => now()->startOfYear()->toDateString(),
            'ends_on' => now()->endOfYear()->toDateString(),
            'projects_target' => 10,
            'publications_target' => 5,
            'faculty_target' => 20,
        ];
    }
}
