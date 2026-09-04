<?php

namespace Database\Factories;

use App\Models\HarvestedLiteratureSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HarvestedLiteratureSource>
 */
class HarvestedLiteratureSourceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $url = 'https://repository.example.org/handle/1234/'.fake()->unique()->numberBetween(1, 1000000);

        return [
            'repository_key' => 'test-repository',
            'url' => $url,
            'url_hash' => fn (array $attributes): string => hash('sha256', $attributes['url']),
            'title' => 'Library management research',
            'authors' => fake()->name(),
            'abstract' => 'This research examines library management and information retrieval in academic institutions.',
            'publication_year' => 2024,
            'metadata' => ['method' => 'html_metadata', 'type' => 'Journal article'],
            'status' => 'ready',
            'harvested_at' => now(),
            'next_harvest_at' => now()->addDays(30),
        ];
    }
}
