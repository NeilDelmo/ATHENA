<?php

namespace Database\Factories;

use App\Models\ProposalDraftMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProposalDraftMember>
 */
class ProposalDraftMemberFactory extends Factory
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
        ];
    }
}
