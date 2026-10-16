<?php

namespace Database\Factories;

use App\Models\CollegeQuarterlyReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CollegeQuarterlyReport>
 */
class CollegeQuarterlyReportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'college' => fake()->unique()->company(),
            'reporting_year' => now()->year,
            'reporting_quarter' => now()->quarter,
            'prepared_by' => User::factory(),
        ];
    }
}
