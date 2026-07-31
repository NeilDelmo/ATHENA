<?php

use App\Models\ResearchKnowledgeEntry;
use App\Models\User;
use App\Services\ResearchKnowledgeService;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'research_head']);
    Role::firstOrCreate(['name' => 'faculty']);
});

test('research heads can feed approved guidance into the athena knowledge base', function () {
    $researchHead = User::factory()->create();
    $researchHead->assignRole('research_head');

    $this->actingAs($researchHead)
        ->post(route('research_head.assistant-knowledge.store'), [
            'title' => 'Institutional ethics clearance process',
            'category' => 'ethics',
            'content' => 'Faculty researchers must secure ethics clearance before collecting identifiable participant data.',
            'source_url' => 'https://example.edu/research-ethics',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $entry = ResearchKnowledgeEntry::query()->sole();

    expect($entry->title)->toBe('Institutional ethics clearance process')
        ->and($entry->is_active)->toBeTrue()
        ->and($entry->created_by)->toBe($researchHead->id);

    $this->actingAs($researchHead)
        ->get(route('research_head.assistant-knowledge.index'))
        ->assertOk()
        ->assertSee('Built-in proposal paper field guidance');
});

test('faculty cannot manage the athena knowledge base', function () {
    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');

    $this->actingAs($faculty)
        ->get(route('research_head.assistant-knowledge.index'))
        ->assertForbidden();
});

test('athena retrieves matching approved knowledge and discloses its sources', function () {
    config([
        'services.gemini.key' => 'test-key',
        'services.gemini.model' => 'gemini-3.5-flash',
        'services.gemini.base_url' => 'https://generativelanguage.googleapis.com/v1beta/openai',
    ]);

    $researchHead = User::factory()->create();
    ResearchKnowledgeEntry::create([
        'title' => 'Institutional ethics clearance process',
        'category' => 'ethics',
        'content' => 'Faculty researchers must secure ethics clearance before collecting identifiable participant data.',
        'source_url' => 'https://example.edu/research-ethics',
        'is_active' => true,
        'created_by' => $researchHead->id,
    ]);
    ResearchKnowledgeEntry::create([
        'title' => 'Archived ethics instructions',
        'category' => 'ethics',
        'content' => 'This archived instruction must never be supplied to the model.',
        'is_active' => false,
        'created_by' => $researchHead->id,
    ]);

    Http::fake([
        'generativelanguage.googleapis.com/v1beta/openai/chat/completions' => Http::response([
            'choices' => [[
                'message' => ['content' => 'Secure clearance before data collection [ATHENA 1].'],
            ]],
            'usage' => ['prompt_tokens' => 80, 'completion_tokens' => 10],
        ]),
    ]);

    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');

    $this->actingAs($faculty)
        ->postJson(route('research-support.chat'), [
            'messages' => [[
                'role' => 'user',
                'content' => 'What is the ethics clearance process before participant data collection?',
            ]],
        ])
        ->assertOk()
        ->assertJsonPath('reply', 'Secure clearance before data collection [ATHENA 1].')
        ->assertJsonPath('sources.0.reference', 'ATHENA 1')
        ->assertJsonPath('sources.0.title', 'Institutional ethics clearance process')
        ->assertJsonPath('sources.0.url', 'https://example.edu/research-ethics');

    Http::assertSent(function ($request): bool {
        $prompt = collect($request['messages'])->pluck('content')->join("\n");

        return str_contains($prompt, 'Faculty researchers must secure ethics clearance')
            && str_contains($prompt, 'cite the supporting reference inline')
            && ! str_contains($prompt, 'archived instruction');
    });
});

test('the built-in paper field guide explains expense units with practical examples', function () {
    $sources = app(ResearchKnowledgeService::class)->retrieve(
        'What does unit mean in the expense budget breakdown?',
    );

    expect($sources)->not->toBeEmpty()
        ->and($sources[0]['title'])->toBe('Estimated Expense Breakdown — Unit')
        ->and($sources[0]['type'])->toBe('ATHENA paper field guide')
        ->and($sources[0]['content'])->toContain('how one expense item is counted or measured')
        ->and($sources[0]['content'])->toContain('10 reams at PHP 250 per ream')
        ->and($sources[0]['content'])->toContain('Entering PHP or a peso amount as the unit')
        ->and($sources[0]['content'])->toContain('Item Total = Quantity × Unit Cost');
});

test('focused paper context resolves vague questions to a trusted field definition', function () {
    $sources = app(ResearchKnowledgeService::class)->retrieve(
        'What should I put here?',
        'expense-breakdown',
        'items[2][unit]',
    );

    expect($sources)->not->toBeEmpty()
        ->and($sources[0]['title'])->toBe('Estimated Expense Breakdown — Unit');
});

test('focused field context does not override an unrelated research question', function () {
    $sources = app(ResearchKnowledgeService::class)->retrieve(
        'How should I select participants for purposive sampling?',
        'expense-breakdown',
        'items[2][unit]',
    );

    expect(collect($sources)->pluck('title'))
        ->not->toContain('Estimated Expense Breakdown — Unit');
});

test('athena retrieves structured application relationships for value provenance questions', function () {
    $sources = app(ResearchKnowledgeService::class)->retrieve(
        'Where do the Detailed Research Proposal budget values come from?',
        'detailed-proposal',
    );
    $relationship = collect($sources)
        ->firstWhere('type', 'ATHENA proposal paper relationship');

    expect($relationship)->not->toBeNull()
        ->and($relationship['content'])->toContain('Attachment B: Line-Item Budget')
        ->and($relationship['content'])->toContain('latest saved Attachment B')
        ->and($relationship['content'])->toContain('previously downloaded PDF or Word file');
});

test('athena sends application-aware field guidance to the model and discloses it', function () {
    config([
        'services.gemini.key' => 'test-key',
        'services.gemini.model' => 'gemini-3.5-flash',
        'services.gemini.base_url' => 'https://generativelanguage.googleapis.com/v1beta/openai',
    ]);

    Http::fake([
        'generativelanguage.googleapis.com/v1beta/openai/chat/completions' => Http::response([
            'choices' => [[
                'message' => ['content' => "Use “ream” when the quantity counts reams of paper [ATHENA 1].\n\n**Grounded with ATHENA knowledge**\n**ATHENA 1 · Estimated Expense Breakdown — Unit**"],
            ]],
        ]),
    ]);

    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');

    $this->actingAs($faculty)
        ->postJson(route('research-support.chat'), [
            'context' => [
                'paper_slug' => 'expense-breakdown',
                'field' => 'items[0][unit]',
            ],
            'messages' => [[
                'role' => 'user',
                'content' => 'What should I put here?',
            ]],
        ])
        ->assertOk()
        ->assertJsonPath('reply', 'Use “ream” when the quantity counts reams of paper [ATHENA 1].')
        ->assertJsonPath('sources.0.title', 'Estimated Expense Breakdown — Unit')
        ->assertJsonPath('sources.0.type', 'ATHENA paper field guide');

    Http::assertSent(function ($request): bool {
        $prompt = collect($request['messages'])->pluck('content')->join("\n");

        return str_contains($prompt, 'application-aware research and proposal workflow assistant')
            && str_contains($prompt, 'how one expense item is counted or measured')
            && str_contains($prompt, 'Quantity = 10')
            && str_contains($prompt, 'answer directly from the matching field guide')
            && str_contains($prompt, 'Do not append a source list');
    });
});

test('every registered proposal paper has maintained field guidance', function () {
    $paperGuides = collect(config('proposal_field_guidance.papers'));
    $registeredPaperSlugs = collect(config('proposal_papers'))->keys();
    $relationships = collect(config('proposal_field_guidance.relationships'));
    $metadataDefaults = collect(config('proposal_field_guidance.field_metadata_defaults'));

    expect($paperGuides->keys())->toContain(...$registeredPaperSlugs)
        ->and($paperGuides->every(fn (array $paper): bool => filled($paper['purpose'] ?? null)))
        ->toBeTrue()
        ->and($paperGuides->every(fn (array $paper): bool => collect($paper['fields'] ?? [])
            ->isNotEmpty()
            && collect($paper['fields'])->every(fn (array $field): bool => filled($field['guidance'] ?? null))))
        ->toBeTrue()
        ->and($relationships)->not->toBeEmpty()
        ->and($relationships->every(fn (array $relationship): bool => collect($relationship['papers'] ?? [])
            ->isNotEmpty()
            && collect($relationship['papers'])->every(
                fn (string $paperSlug): bool => $paperGuides->has($paperSlug),
            )))
        ->toBeTrue()
        ->and($metadataDefaults->keys())->toContain(
            'examples',
            'rules',
            'related_fields',
            'common_mistakes',
            'calculations',
        )
        ->and($metadataDefaults->every(fn (array $defaults): bool => collect($defaults)->filter()->isNotEmpty()))
        ->toBeTrue();
});
