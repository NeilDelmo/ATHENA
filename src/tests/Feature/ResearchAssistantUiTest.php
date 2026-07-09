<?php

use App\Models\ResearchCall;
use App\Models\ResearchCategory;
use App\Models\TopicProposal;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'faculty']);
    Role::firstOrCreate(['name' => 'faculty_researcher']);
});

function createAssistantTopicFor(User $user, array $overrides = []): TopicProposal
{
    $category = ResearchCategory::create(['name' => 'Assistant Context '.uniqid()]);
    $call = ResearchCall::create([
        'title' => 'Faculty Research Call',
        'academic_year' => '2026-2027',
        'opens_at' => now()->subDay(),
        'closes_at' => now()->addMonth(),
        'status' => 'open',
    ]);
    $call->categories()->attach($category);

    $topic = TopicProposal::create([
        'user_id' => $user->id,
        'research_call_id' => $call->id,
        'research_category_id' => $category->id,
        'title' => $overrides['title'] ?? 'Community-based mangrove monitoring',
        'description' => $overrides['description'] ?? 'A study on local coastal stewardship practices.',
        'estimated_budget' => $overrides['estimated_budget'] ?? 24000,
        'estimated_duration_months' => $overrides['estimated_duration_months'] ?? 10,
        'status' => $overrides['status'] ?? 'revision_requested',
    ]);

    $topic->versions()->create([
        'submitted_by' => $user->id,
        'version_number' => 1,
        'submission_type' => 'initial',
        'file_path' => 'proposals/context.pdf',
        'original_filename' => 'context.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 100,
        'checksum' => str_repeat('b', 64),
        'title' => $topic->title,
        'description' => $topic->description,
        'estimated_budget' => $topic->estimated_budget,
        'estimated_duration_months' => $topic->estimated_duration_months,
    ]);

    return $topic;
}

test('faculty and faculty researchers can open the research help facility', function (string $role) {
    $researcher = User::factory()->create();
    $researcher->assignRole($role);

    $this->actingAs($researcher)
        ->get(route('research-support.index'))
        ->assertOk()
        ->assertSee('Research Help Facility')
        ->assertSee('AI Research Assistant')
        ->assertSee('Ask Athena')
        ->assertSee('Privacy:')
        ->assertSee('Research prompt groups')
        ->assertSee('Copy chat')
        ->assertSee('Export .txt');
})->with(['faculty', 'faculty_researcher']);

test('proposal owners can launch athena with the current proposal selected', function () {
    $this->withoutVite();

    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');
    $topic = createAssistantTopicFor($faculty, [
        'title' => 'Context-aware freshwater research',
    ]);

    $this->actingAs($faculty)
        ->get(route('topics.show', $topic))
        ->assertOk()
        ->assertSee('Ask Athena about this proposal')
        ->assertSee('openWithContext('.$topic->id, false)
        ->assertSee('window.athenaResearchAssistantActiveContextId = '.$topic->id, false)
        ->assertSee('Context-aware freshwater research');
});

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

test('users can attach their own proposal context to a chat request', function () {
    config([
        'services.groq.key' => 'test-key',
        'services.groq.model' => 'openai/gpt-oss-120b',
        'services.groq.base_url' => 'https://api.groq.com/openai/v1',
    ]);

    Http::fake([
        'api.groq.com/openai/v1/chat/completions' => Http::response([
            'choices' => [[
                'message' => ['content' => 'Use the reviewer comment as the revision plan anchor.'],
            ]],
        ]),
    ]);

    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');
    $topic = createAssistantTopicFor($faculty);
    $reviewer = User::factory()->create(['name' => 'Dr. Reviewer']);
    $topic->reviews()->create([
        'reviewer_id' => $reviewer->id,
        'decision' => 'revision_requested',
        'comment' => 'Clarify the sampling frame and target respondents.',
    ]);

    $this->actingAs($faculty)
        ->postJson(route('research-support.chat'), [
            'context' => ['topic_id' => $topic->id],
            'messages' => [[
                'role' => 'user',
                'content' => 'Help me plan my revisions.',
            ]],
        ])
        ->assertOk()
        ->assertJsonPath('reply', 'Use the reviewer comment as the revision plan anchor.');

    Http::assertSent(fn ($request) => collect($request['messages'])->contains(
        fn (array $message) => $message['role'] === 'system'
            && str_contains($message['content'], 'Community-based mangrove monitoring')
            && str_contains($message['content'], 'Clarify the sampling frame')
    ));
});

test('users cannot attach another faculty member proposal as context', function () {
    config(['services.groq.key' => 'test-key']);
    Http::fake();

    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');
    $otherFaculty = User::factory()->create();
    $otherFaculty->assignRole('faculty');
    $otherTopic = createAssistantTopicFor($otherFaculty);

    $this->actingAs($faculty)
        ->postJson(route('research-support.chat'), [
            'context' => ['topic_id' => $otherTopic->id],
            'messages' => [[
                'role' => 'user',
                'content' => 'Can you use this proposal?',
            ]],
        ])
        ->assertForbidden()
        ->assertJsonPath('message', 'That proposal context is unavailable for your account.');

    Http::assertNothingSent();
});

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
