<?php

use App\Models\ProposalDraft;
use App\Models\ResearchAssistantConversation;
use App\Models\ResearchCall;
use App\Models\TopicProposal;
use App\Models\User;
use App\Services\ResearchAssistantConversationMemoryService;
use Illuminate\Support\Facades\Http;
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

function retentionAccount(): User
{
    Role::firstOrCreate(['name' => 'faculty']);
    $user = User::factory()->create();
    $user->assignRole('faculty');

    return $user;
}

function retentionProposal(User $user, string $title = 'Coastal monitoring'): ProposalDraft
{
    $call = ResearchCall::create(['title' => 'Chat retention fixture', 'academic_year' => '2026-2027', 'opens_at' => now()->subDay(), 'closes_at' => now()->addMonth(), 'status' => 'open']);

    return ProposalDraft::create(['user_id' => $user->id, 'research_call_id' => $call->id, 'project_title' => $title, 'duration_months' => 12, 'project_leader' => $user->name]);
}

test('automatic resume selects the current projects private chat and honors a selected older chat', function () {
    $user = retentionAccount();
    Role::firstOrCreate(['name' => 'research_head']);
    $user->assignRole('research_head');
    $first = retentionProposal($user);
    $second = retentionProposal($user, 'A different project');
    $old = ResearchAssistantConversation::factory()->for($user)->create(['context' => ['proposal_draft_id' => $first->id, 'workspace' => 'faculty'], 'updated_at' => now()->subDays(2)]);
    $latest = ResearchAssistantConversation::factory()->for($user)->create(['context' => ['proposal_draft_id' => $first->id, 'workspace' => 'faculty'], 'updated_at' => now()->subDay()]);
    $otherProject = ResearchAssistantConversation::factory()->for($user)->create(['context' => ['proposal_draft_id' => $second->id, 'workspace' => 'faculty']]);
    ResearchAssistantConversation::factory()->for($user)->create(['context' => ['proposal_draft_id' => $first->id, 'workspace' => 'research_head']]);
    ResearchAssistantConversation::factory()->create(['context' => ['proposal_draft_id' => $first->id, 'workspace' => 'faculty']]);
    $this->actingAs($user)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => 'faculty']);
    $scope = ['resume' => 1, 'context' => ['proposal_draft_id' => $first->id]];
    $this->getJson(route('research-support.history', $scope))->assertSuccessful()
        ->assertJsonPath('conversation.id', $latest->id)->assertJsonPath('account_id', $user->id)->assertJsonPath('workspace', 'faculty')
        ->assertJsonPath('conversation.context_label', $first->project_title)->assertJsonCount(2, 'conversation.messages');
    $this->getJson(route('research-support.history', [...$scope, 'conversation_id' => $old->id]))->assertSuccessful()->assertJsonPath('conversation.id', $old->id);
    $this->getJson(route('research-support.history', [...$scope, 'conversation_id' => $otherProject->id]))->assertSuccessful()->assertJsonPath('conversation.id', $latest->id);
});

test('project chats follow a proposal draft when its submitted project becomes available', function () {
    $user = retentionAccount();
    $draft = retentionProposal($user);
    $chat = ResearchAssistantConversation::factory()->for($user)->create(['context' => ['proposal_draft_id' => $draft->id, 'workspace' => 'faculty']]);
    $topic = TopicProposal::create(['user_id' => $user->id, 'title' => $draft->project_title, 'status' => 'revision_requested']);
    $draft->update(['topic_id' => $topic->id]);
    $this->actingAs($user)->getJson(route('research-support.history', ['resume' => 1, 'context' => ['topic_id' => $topic->id]]))
        ->assertSuccessful()->assertJsonPath('conversation.id', $chat->id);
});

test('automatic resume keeps unrelated projects empty and refuses an inaccessible project', function () {
    $user = retentionAccount();
    $draft = retentionProposal($user);
    ResearchAssistantConversation::factory()->for($user)->create(['context' => ['workspace' => 'faculty']]);
    $this->actingAs($user)->getJson(route('research-support.history', ['resume' => 1, 'context' => ['proposal_draft_id' => $draft->id]]))
        ->assertSuccessful()->assertJsonPath('conversation', null);
    $private = retentionProposal(retentionAccount());
    $this->getJson(route('research-support.history', ['resume' => 1, 'context' => ['proposal_draft_id' => $private->id]]))->assertForbidden();
});

test('new sessions can resume saved chats without a browser conversation pointer', function () {
    $user = retentionAccount();
    $draft = retentionProposal($user);
    $saved = $this->actingAs($user)->postJson(route('research-support.history.save'), [
        'messages' => [['role' => 'user', 'content' => 'Remember the mangrove monitoring topic.'], ['role' => 'assistant', 'content' => 'We are discussing community mangrove monitoring.']],
        'context' => ['proposal_draft_id' => $draft->id],
    ])->assertSuccessful()->json('conversation');
    $this->app['session']->invalidate();
    $this->actingAs($user->fresh())->getJson(route('research-support.history', ['resume' => 1, 'context' => ['proposal_draft_id' => $draft->id]]))
        ->assertSuccessful()->assertJsonPath('conversation.id', $saved['id'])->assertJsonPath('conversation.messages.1.content', 'We are discussing community mangrove monitoring.');
});

test('concurrent chat edits are saved separately instead of overwriting the other tab', function () {
    $this->actingAs(retentionAccount());
    $messages = [['role' => 'user', 'content' => 'Discuss my research.'], ['role' => 'assistant', 'content' => 'What topic?']];
    $base = $this->postJson(route('research-support.history.save'), ['messages' => $messages])->assertSuccessful()->json('conversation');
    expect($base['version'] ?? null)->toBeString()->toHaveLength(64);
    $first = [...$messages, ['role' => 'user', 'content' => 'Mangrove monitoring.']];
    $second = [...$messages, ['role' => 'user', 'content' => 'Duck farming.']];
    $this->postJson(route('research-support.history.save'), ['id' => $base['id'], 'version' => $base['version'], 'messages' => $first])->assertSuccessful()->assertJsonPath('conversation.id', $base['id']);
    $fork = $this->postJson(route('research-support.history.save'), ['id' => $base['id'], 'version' => $base['version'], 'messages' => $second])->assertSuccessful()
        ->assertJsonPath('forked_from', $base['id'])->json('conversation');
    expect($fork['id'])->not->toBe($base['id']);
    $this->getJson(route('research-support.history.show', $base['id']))->assertSuccessful()->assertJsonPath('conversation.messages.2.content', 'Mangrove monitoring.');
    $this->getJson(route('research-support.history.show', $fork['id']))->assertSuccessful()->assertJsonPath('conversation.messages.2.content', 'Duck farming.');
});

test('closing an unchanged stale tab never truncates a chat or creates a duplicate', function () {
    $this->actingAs(retentionAccount());
    $messages = [['role' => 'user', 'content' => 'First question.']];
    $base = $this->postJson(route('research-support.history.save'), ['messages' => $messages])->assertSuccessful()->json('conversation');
    expect($base['version'] ?? null)->toBeString();
    $this->postJson(route('research-support.history.save'), ['id' => $base['id'], 'version' => $base['version'], 'messages' => [...$messages, ['role' => 'assistant', 'content' => 'Saved answer.']]])->assertSuccessful();
    $this->postJson(route('research-support.history.save'), ['id' => $base['id'], 'version' => $base['version'], 'messages' => $messages])->assertSuccessful()
        ->assertJsonPath('stale', true)->assertJsonCount(2, 'conversation.messages')->assertJsonPath('conversation.id', $base['id']);
    $this->assertDatabaseCount('research_assistant_conversations', 1);
});

test('saving a chat cannot silently reassign its conversation to another project', function () {
    $user = retentionAccount();
    $first = retentionProposal($user);
    $second = retentionProposal($user, 'Another proposal');
    $this->actingAs($user);
    $messages = [['role' => 'user', 'content' => 'Help with the first proposal.']];
    $base = $this->postJson(route('research-support.history.save'), ['messages' => $messages, 'context' => ['proposal_draft_id' => $first->id]])->assertSuccessful()->json('conversation');
    $this->postJson(route('research-support.history.save'), ['id' => $base['id'], 'version' => $base['version'] ?? null, 'messages' => $messages, 'context' => ['proposal_draft_id' => $second->id]])
        ->assertUnprocessable();
    $this->getJson(route('research-support.history.show', $base['id']))->assertSuccessful()->assertJsonPath('conversation.context.proposal_draft_id', $first->id);
});

test('a saved project chat cannot provide memory to a different project request', function () {
    $faculty = retentionAccount();
    $first = retentionProposal($faculty, 'First project');
    $second = retentionProposal($faculty, 'Second project');
    $saved = $this->actingAs($faculty)->postJson(route('research-support.history.save'), [
        'context' => ['proposal_draft_id' => $first->id],
        'messages' => [['role' => 'user', 'content' => 'Remember this project decision.']],
    ])->assertOk()->json('conversation');
    Http::fake();
    $this->postJson(route('research-support.chat'), [
        'conversation_id' => $saved['id'], 'context' => ['proposal_draft_id' => $second->id],
        'messages' => [['role' => 'user', 'content' => 'What decision did we make?']],
    ])->assertUnprocessable();
    Http::assertNothingSent();
});

test('earlier RRL paragraph edits are included in bounded conversation memory', function () {
    $faculty = retentionAccount();
    $conversation = ResearchAssistantConversation::factory()->create([
        'user_id' => $faculty->id,
        'messages' => [
            ['role' => 'assistant', 'content' => 'Review this draft.', 'literature' => [
                'kind' => 'draft', 'drafts' => [['paragraph' => 'Earlier edited RRL paragraph about coastal flood forecasting (Rivera, 2025).']],
            ]],
            ...array_fill(0, 8, ['role' => 'user', 'content' => 'Continue the discussion.']),
        ],
    ]);
    Http::fake(fn () => Http::response([
        'choices' => [['message' => ['content' => 'Discussing coastal flood forecasting.']]],
    ]));
    config(['services.openrouter.key' => 'test', 'services.openrouter.base_url' => 'https://memory.test/v1']);
    app(ResearchAssistantConversationMemoryService::class)->promptContext($conversation);
    Http::assertSent(fn ($request) => str_contains($request->body(), 'Earlier edited RRL paragraph')
        && str_contains($request->body(), 'not source evidence'));
});

test('new chat requests cannot save or send an old accounts transcript after a session changes', function (string $route, array $scope) {
    $faculty = retentionAccount();
    Http::fake();
    $this->actingAs($faculty)->postJson(route($route), [
        ...$scope, 'messages' => [['role' => 'user', 'content' => 'Private text from the earlier page.']],
    ])->assertConflict()->assertJsonPath('message', 'Your account or workspace changed. Reload this page before continuing.');
    Http::assertNothingSent();
    expect($faculty->researchAssistantConversations()->count())->toBe(0);
})->with([
    'different account saving' => ['research-support.history.save', ['account_id' => 999999]],
    'different account chatting' => ['research-support.chat', ['account_id' => 999999]],
    'different workspace saving' => ['research-support.history.save', ['workspace' => 'research_head']],
    'different workspace chatting' => ['research-support.chat', ['workspace' => 'research_head']],
]);

test('RRL draft references reading failures and proposal choices survive private history', function () {
    Role::firstOrCreate(['name' => 'faculty']);
    $user = User::factory()->create();
    $user->assignRole('faculty');
    $failure = ['source_token' => str_repeat('a', 64), 'title' => 'Public study', 'reason' => 'The publisher rejected the PDF request.'];
    $packets = [
        ['kind' => 'choose_proposal', 'intent' => 'draft', 'request' => 'Draft the RRL from complete papers.', 'proposals' => [['id' => 42, 'title' => 'Gamified studying']], 'workspace_required' => false],
        ['kind' => 'results', 'proposal_draft_id' => 42, 'topic' => 'Gamified studying', 'intent' => 'draft', 'results' => [], 'read_failures' => [$failure]],
        ['kind' => 'draft', 'proposal_draft_id' => 42, 'topic' => 'Gamified studying', 'drafts' => [['paragraph' => 'An edited review paragraph.', 'reference' => '[1] A. Researcher, Public study, 2025.']], 'read_failures' => [$failure]],
    ];
    foreach ($packets as $packet) {
        $saved = $this->actingAs($user)->postJson(route('research-support.history.save'), ['messages' => [
            ['role' => 'user', 'content' => 'Help with the RRL.'], ['role' => 'assistant', 'content' => 'Review this result.', 'literature' => $packet],
        ]])->assertSuccessful();
        $this->getJson(route('research-support.history.show', $saved->json('conversation.id')))->assertSuccessful()
            ->assertJsonPath('conversation.messages.1.literature', fn (array $savedPacket): bool => $savedPacket == $packet);
    }
});
