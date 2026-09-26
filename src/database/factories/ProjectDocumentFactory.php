<?php

namespace Database\Factories;

use App\Models\ProjectDocument;
use App\Models\TopicProposal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectDocument>
 */
class ProjectDocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $filename = fake()->slug(3).'.pdf';

        return [
            'topic_id' => fn (): int => TopicProposal::query()->value('id'),
            'uploaded_by' => User::factory(),
            'category' => ProjectDocument::CATEGORY_SUPPORTING_FILES,
            'title' => fake()->sentence(3),
            'note' => fake()->optional()->sentence(),
            'file_path' => 'project-documents/'.$filename,
            'original_filename' => $filename,
            'mime_type' => 'application/pdf',
            'file_size' => fake()->numberBetween(10_000, 2_000_000),
            'checksum' => hash('sha256', $filename),
        ];
    }
}
