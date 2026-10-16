<?php

namespace Database\Factories;

use App\Models\CollegeQuarterlyReport;
use App\Models\CollegeQuarterlyReportSubmission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CollegeQuarterlyReportSubmission>
 */
class CollegeQuarterlyReportSubmissionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'report_id' => CollegeQuarterlyReport::factory(), 'version_number' => 1, 'submitted_by' => User::factory(),
            'snapshot' => ['presentations' => [], 'publications' => [], 'research' => [], 'undated_publications' => 0],
            'submitted_at' => now(),
        ];
    }
}
