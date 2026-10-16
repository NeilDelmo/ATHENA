<?php

namespace Database\Factories;

use App\Models\RmsQuarterlyReport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RmsQuarterlyReport>
 */
class RmsQuarterlyReportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reporting_year' => now()->year,
            'reporting_quarter' => now()->quarter,
            'campus' => 'ARASOF-Nasugbu',
            'signatories' => [],
            'row_details' => [],
        ];
    }
}
