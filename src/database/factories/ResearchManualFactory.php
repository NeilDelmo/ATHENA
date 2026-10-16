<?php

namespace Database\Factories;

use App\Models\ResearchManual;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ResearchManual>
 */
class ResearchManualFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => 'Research Manual',
            'edition' => (string) fake()->year(),
            'effective_date' => now()->startOfYear(),
            'version' => hash('sha256', (string) Str::uuid()),
            'directory' => 'research-manual/editions/'.Str::uuid(),
            'page_count' => 1,
            'unindexed_pages' => [],
        ];
    }
}
