<?php

namespace Database\Factories;

use App\Models\TopicCollaborator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TopicCollaborator>
 */
class TopicCollaboratorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'accepted_at' => null,
            'project_role' => null,
        ];
    }
}
