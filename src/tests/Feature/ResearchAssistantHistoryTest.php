<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

test('assistant history endpoints return JSON when the session has expired', function (string $method, string $routeName, array $parameters) {
    $this->json($method, route($routeName, $parameters))
        ->assertUnauthorized()
        ->assertJsonPath('message', 'Unauthenticated.');
})->with([
    'history list' => ['GET', 'research-support.history', []],
    'saved conversation' => ['GET', 'research-support.history.show', ['conversation' => 1]],
    'save conversation' => ['POST', 'research-support.history.save', []],
]);

test('invalid assistant history searches return JSON validation errors', function () {
    $this->actingAs(User::factory()->create())
        ->getJson(route('research-support.history', ['query' => str_repeat('a', 101)]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('query');
});

test('long assistant replies remain complete when saving and reopening a conversation', function () {
    Role::firstOrCreate(['name' => 'faculty']);
    $researcher = User::factory()->create();
    $researcher->assignRole('faculty');
    $reply = str_repeat('Detailed proposal guidance. ', 400);
    $messages = [
        ['role' => 'user', 'content' => 'Explain how to improve my proposal.'],
        ['role' => 'assistant', 'content' => trim($reply)],
    ];

    $saved = $this->actingAs($researcher)
        ->postJson(route('research-support.history.save'), ['messages' => $messages])
        ->assertOk()
        ->assertJsonPath('conversation.messages.1.content', trim($reply));

    $this->getJson(route('research-support.history.show', $saved->json('conversation.id')))
        ->assertOk()
        ->assertJsonPath('conversation.messages.1.content', trim($reply));
});

test('chat and history endpoints keep their separate message size limits', function (string $endpoint, int $maximumLength) {
    Role::firstOrCreate(['name' => 'faculty']);
    $researcher = User::factory()->create();
    $researcher->assignRole('faculty');

    $this->actingAs($researcher)
        ->postJson(route('research-support.'.$endpoint), [
            'messages' => [
                ['role' => 'user', 'content' => 'Explain my proposal.'],
                ['role' => 'assistant', 'content' => str_repeat('a', $maximumLength + 1)],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('messages.1.content');

    $this->assertDatabaseCount('research_assistant_conversations', 0);
})->with([
    'saved conversation' => ['history.save', 32000],
    'AI request' => ['chat', 8000],
]);

test('non-null saved reviews still require their findings and limitations', function (string $missingField) {
    $review = [
        'record_basis' => 'private_saved_draft',
        'reviewed_at' => '2026-10-16T08:00:00Z',
        'papers' => [[
            'reference' => 'Work Plan',
            'title' => 'Saved work plan',
            'coverage' => 'saved_structured_fields',
            'omitted_fields' => 0,
            'truncated_fields' => [],
        ]],
        'findings' => [],
        'limitations' => [],
    ];
    unset($review[$missingField]);

    $this->actingAs(User::factory()->create())
        ->postJson(route('research-support.history.save'), [
            'messages' => [
                ['role' => 'user', 'content' => 'Review my work plan.', 'review' => null],
                ['role' => 'assistant', 'content' => 'Review completed.', 'review' => $review],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('messages.1.review.'.$missingField);
})->with(['findings', 'limitations']);

test('plain assistant conversations survive a fresh session in the same workspace', function () {
    Role::firstOrCreate(['name' => 'faculty']);
    Role::firstOrCreate(['name' => 'research_head']);
    $researcher = User::factory()->create();
    $researcher->assignRole(['faculty', 'research_head']);
    $messages = [
        ['role' => 'user', 'content' => 'How can I improve my mangrove sampling plan?', 'sources' => []],
        ['role' => 'assistant', 'content' => 'Define the population and sampling frame.', 'sources' => []],
        ['role' => 'user', 'content' => 'How should I select the coastal stations?', 'sources' => []],
        ['role' => 'assistant', 'content' => 'Stratify the stations by coastal habitat.', 'sources' => []],
    ];

    $saved = $this->actingAs($researcher)
        ->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY])
        ->postJson(route('research-support.history.save'), [
            'id' => null,
            'messages' => array_map(fn (array $message): array => [
                ...$message,
                'review' => null,
                'literature' => null,
                'proposal_changes' => null,
                'document_reading' => null,
            ], $messages),
            'context' => null,
        ])
        ->assertOk()
        ->assertJsonPath('conversation.context.workspace', User::WORKSPACE_FACULTY);

    $conversationId = $saved->json('conversation.id');
    $this->app['session']->invalidate();
    $this->actingAsGuest();

    $this->actingAs($researcher->fresh())
        ->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY])
        ->getJson(route('research-support.history'))
        ->assertOk()
        ->assertJsonCount(1, 'conversations')
        ->assertJsonPath('conversations.0.id', $conversationId);

    $this->getJson(route('research-support.history.show', $conversationId))
        ->assertOk()
        ->assertJsonPath('conversation.messages', $messages);
});
