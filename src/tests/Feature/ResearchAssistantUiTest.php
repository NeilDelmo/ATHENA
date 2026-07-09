<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'faculty']);
    Role::firstOrCreate(['name' => 'faculty_researcher']);
});

test('faculty and faculty researchers can open the research help facility', function (string $role) {
    $researcher = User::factory()->create();
    $researcher->assignRole($role);

    $this->actingAs($researcher)
        ->get(route('research-support.index'))
        ->assertOk()
        ->assertSee('Research Help Facility')
        ->assertSee('AI Research Assistant')
        ->assertSee('Ask Athena')
        ->assertSee('Privacy:');
})->with(['faculty', 'faculty_researcher']);

test('faculty and faculty researchers can receive a groq research response', function (string $role) {
    config([
        'services.groq.key' => 'test-key',
        'services.groq.model' => 'openai/gpt-oss-120b',
        'services.groq.base_url' => 'https://api.groq.com/openai/v1',
    ]);

    Http::fake([
        'api.groq.com/openai/v1/chat/completions' => Http::response([
            'choices' => [[
                'message' => ['content' => 'Start by defining your population and measurable variables.'],
            ]],
            'usage' => ['prompt_tokens' => 20, 'completion_tokens' => 12],
        ]),
    ]);

    $researcher = User::factory()->create();
    $researcher->assignRole($role);

    $this->actingAs($researcher)
        ->postJson(route('research-support.chat'), [
            'messages' => [[
                'role' => 'user',
                'content' => 'How do I refine my research question?',
            ]],
        ])
        ->assertOk()
        ->assertJsonPath('reply', 'Start by defining your population and measurable variables.')
        ->assertJsonPath('model', 'openai/gpt-oss-120b');

    Http::assertSent(fn ($request) => $request->url() === 'https://api.groq.com/openai/v1/chat/completions'
        && $request['max_completion_tokens'] === 700
        && $request['messages'][0]['role'] === 'system');
})->with(['faculty', 'faculty_researcher']);

test('chat requests require a final user message', function () {
    config(['services.groq.key' => 'test-key']);

    $researcher = User::factory()->create();
    $researcher->assignRole('faculty_researcher');

    $response = $this->actingAs($researcher)
        ->postJson(route('research-support.chat'), [
            'messages' => [[
                'role' => 'assistant',
                'content' => 'Previous response',
            ]],
        ]);

    expect($response->status())->toBe(422)
        ->and($response->json('errors.messages.0'))->toBe('The conversation must end with a user message.');
});

test('assistant launcher is rendered for faculty and faculty researchers', function () {
    $researcher = User::factory()->create();
    $researcher->assignRole('faculty_researcher');

    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');

    $this->actingAs($researcher)
        ->get(route('faculty.dashboard'))
        ->assertOk()
        ->assertSee('Open Athena AI research assistant');

    $this->actingAs($faculty)
        ->get(route('faculty.dashboard'))
        ->assertOk()
        ->assertSee('Open Athena AI research assistant');
});
