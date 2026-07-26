<?php

namespace Database\Factories;

use App\Models\ResearchAssistantConversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResearchAssistantConversation>
 */
class ResearchAssistantConversationFactory extends Factory
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
            'title' => fake()->sentence(5),
            'messages' => [
                [
                    'role' => 'user',
                    'content' => fake()->sentence(),
                    'sources' => [],
                ],
                [
                    'role' => 'assistant',
                    'content' => fake()->paragraph(),
                    'sources' => [],
                ],
            ],
            'context' => null,
        ];
    }
}
