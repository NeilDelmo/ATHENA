<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'faculty']);
    $user = User::factory()->create();
    $user->assignRole('faculty');
    $this->actingAs($user);
    config([
        'services.ai.provider' => 'openrouter',
        'services.openrouter.key' => 'test-key',
        'services.gemini.key' => null,
        'research_assistant.manual.enabled' => false,
    ]);
    Http::preventStrayRequests();
});

test('chat explains an exhausted AI budget without exposing provider details', function (string $limit, string $expected) {
    Http::fake(['*/chat/completions' => Http::response([
        'error' => ['message' => 'PRIVATE_PROVIDER_DETAILS', 'metadata' => ['limit_source' => $limit]],
    ], 402)]);

    $this->postJson(route('research-support.chat'), [
        'messages' => [['role' => 'user', 'content' => 'Hello, give me a short greeting.']],
    ])->assertStatus(503)
        ->assertJsonFragment(['message' => $expected])
        ->assertDontSee('PRIVATE_PROVIDER_DETAILS');
})->with([
    ['openrouter_credits', 'The AI service has insufficient credits for this request. Ask the administrator to review the credit balance or reduce the request size.'],
    ['openrouter_key_limit', 'The AI API key has reached its spending limit. Ask the administrator to review the limit before retrying.'],
    ['openrouter_in_flight_budget', 'The AI spending allowance is temporarily occupied by other requests. Wait a moment, then retry.'],
]);
