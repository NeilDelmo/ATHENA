<?php

use App\Models\ProjectMonitoringDraft;
use App\Models\ProjectNarrativeReportDraft;
use App\Models\ProposalDraft;
use App\Models\ProposalVersionFile;
use App\Models\ResearchAssistantConversation;
use App\Models\ResearchCall;
use App\Models\ResearchCategory;
use App\Models\TopicProposal;
use App\Models\User;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'faculty']);
    Role::firstOrCreate(['name' => 'faculty_researcher']);
    Role::firstOrCreate(['name' => 'research_head']);
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

/** @return list<int> */
function assistantContextIds(TestResponse $response): array
{
    $matched = preg_match(
        "/window\\.athenaResearchAssistantContexts = JSON\\.parse\\('([^']*)'\\);/",
        $response->getContent(),
        $matches,
    );

    expect($matched)->toBe(1);

    $serializedContexts = json_decode('"'.$matches[1].'"', flags: JSON_THROW_ON_ERROR);
    $contexts = json_decode($serializedContexts, true, flags: JSON_THROW_ON_ERROR);

    return collect($contexts)
        ->pluck('id')
        ->map(fn (mixed $id): int => (int) $id)
        ->values()
        ->all();
}

function assistantDocxContents(string $text): string
{
    $temporaryPath = tempnam(sys_get_temp_dir(), 'athena-docx-');
    $archive = new ZipArchive;
    $escapedText = htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');

    expect($temporaryPath)->not->toBeFalse();
    expect($archive->open($temporaryPath, ZipArchive::CREATE | ZipArchive::OVERWRITE))->toBeTrue();

    $archive->addFromString('[Content_Types].xml', <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
            <Default Extension="xml" ContentType="application/xml"/>
        </Types>
        XML);
    $archive->addFromString('word/document.xml', <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
            <w:body><w:p><w:r><w:t>{$escapedText}</w:t></w:r></w:p></w:body>
        </w:document>
        XML);
    $archive->close();
    $contents = file_get_contents($temporaryPath);
    unlink($temporaryPath);

    expect($contents)->not->toBeFalse();

    return $contents;
}

test('faculty and faculty researchers can open the research help facility', function (string $role) {
    $researcher = User::factory()->create();
    $researcher->assignRole($role);

    $response = $this->actingAs($researcher)
        ->get(route('research-support.index'))
        ->assertOk()
        ->assertSee('Research Support')
        ->assertDontSee('AI Research Assistant')
        ->assertSee('Literature Search and Source Organizer')
        ->assertSee('role="tablist"', false)
        ->assertSee('role="tab"', false)
        ->assertSee('activeResearchTool', false)
        ->assertSee("x-show=\"activeResearchTool === 'rrl'\"", false)
        ->assertDontSee('Academic metadata')
        ->assertDontSee('Live metadata sources')
        ->assertSee('Search filters')
        ->assertSee('Published from')
        ->assertSee('Open-access papers only')
        ->assertSee('Your first action is to search')
        ->assertSee('Find new papers')
        ->assertSee('Saved library')
        ->assertSee('Search papers')
        ->assertSee('Abstract excerpt')
        ->assertSee('Show full abstract')
        ->assertSee('Show less')
        ->assertSee('Paper details')
        ->assertSee('Add to a Detailed Proposal')
        ->assertDontSee('No scrolling required')
        ->assertSee('Evidence-ranked')
        ->assertSee('Add research context for more precise results')
        ->assertSee('data-rrl-workspace', false)
        ->assertSee('data-rrl-results-table', false)
        ->assertSee('data-rrl-paper-details', false)
        ->assertSee('Analyze results')
        ->assertSee('Context-aware assistance:')
        ->assertSee('A page action states which saved ATHENA record it uses.')
        ->assertSee('Sources')
        ->assertSee('<details x-show="Array.isArray(message.sources)', false)
        ->assertDontSee('Grounded with ATHENA knowledge')
        ->assertSee('Ask ATHENA')
        ->assertSee('Expand to full workspace')
        ->assertSee('Back to Research Support')
        ->assertSee('Collapse to side panel')
        ->assertSee('Chat history')
        ->assertSee('Search history')
        ->assertSee('Chats are saved to your ATHENA account.')
        ->assertSee('Analyze document')
        ->assertSee('Only the selected PDF or DOCX is sent to Athena for this request.')
        ->assertSee('data-research-assistant-documents-url="'.route('research-support.documents').'"', false)
        ->assertDontSee('Research prompt groups')
        ->assertDontSee('Planning')
        ->assertDontSee('Methods')
        ->assertDontSee('Revision')
        ->assertDontSee('Writing')
        ->assertDontSee('Refine my research question')
        ->assertDontSee('Ask about research questions, methodology, writing, or proposal revisions.')
        ->assertSee('data-assistant-full-workspace', false)
        ->assertSee('data-assistant-workspace', false)
        ->assertSee('openWorkspace()', false)
        ->assertSee('collapseWorkspace()', false)
        ->assertSee('workspaceOpen', false);

    if ($role === 'faculty_researcher') {
        $response
            ->assertSee("x-show=\"activeResearchTool === 'turnitin'\"", false)
            ->assertSee('aria-controls="turnitin"', false)
            ->assertSee('Original research.')
            ->assertSee('Confident submission.')
            ->assertSee('Request a similarity check')
            ->assertSee('href="'.route('similarity-checks.index').'"', false)
            ->assertSee('Read the report guide')
            ->assertSee('href="https://www.turnitin.com/"', false)
            ->assertSee('target="_blank"', false)
            ->assertSee('rel="noopener noreferrer"', false)
            ->assertSee('Institutional access may be required')
            ->assertSee("x-show=\"activeResearchTool === 'journal'\"", false)
            ->assertSee('Journal Finder')
            ->assertSee('Find a journal for your paper')
            ->assertSee('related indexed articles')
            ->assertDontSee('Scraped source: WikiCFP');
    } else {
        $response
            ->assertSee("x-show=\"activeResearchTool === 'turnitin'\"", false)
            ->assertSee('aria-controls="turnitin"', false)
            ->assertSee('href="https://www.turnitin.com/"', false)
            ->assertDontSee("x-show=\"activeResearchTool === 'journal'\"", false)
            ->assertDontSee('Journal Finder');
    }
})->with(['faculty', 'faculty_researcher']);

test('research heads can open the assistant workspace without faculty researcher tools', function (string $role) {
    $this->withoutVite();

    $user = User::factory()->create();
    $user->assignRole($role);

    $this->actingAs($user)
        ->get(route('research-support.index'))
        ->assertOk()
        ->assertSee('Research Support')
        ->assertDontSee('Visit Turnitin')
        ->assertDontSee('href="https://www.turnitin.com/"', false)
        ->assertDontSee('Journal Finder')
        ->assertSee('Ask ATHENA')
        ->assertDontSee('Literature Search and Source Organizer');
})->with(['research_head']);

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
        ->assertSee('Make a revision plan')
        ->assertSee('Uses saved reviewer comments for this proposal')
        ->assertSee('Summarize project progress')
        ->assertSee('Uses saved monitoring tools, progress reports, and remarks')
        ->assertSee('Context-aware freshwater research');
});

test('proposal draft editors expose the current paper and proposal to athena', function () {
    $this->withoutVite();

    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');
    $topic = createAssistantTopicFor($faculty, [
        'title' => 'Paper-aware proposal',
    ]);
    $draft = ProposalDraft::create([
        'user_id' => $faculty->id,
        'research_call_id' => $topic->research_call_id,
        'topic_id' => $topic->id,
        'project_title' => $topic->title,
        'duration_months' => 12,
        'planned_start' => now()->startOfMonth(),
        'planned_end' => now()->startOfMonth()->addMonths(11)->endOfMonth(),
        'project_leader' => $faculty->name,
    ]);

    $this->actingAs($faculty)
        ->get(route('faculty.proposal-drafts.details.edit', $draft))
        ->assertOk()
        ->assertSee('data-research-assistant-paper-slug="project-details"', false)
        ->assertSee('data-research-assistant-paper-label="Project Details"', false)
        ->assertSee('data-research-assistant-proposal-draft-id="'.$draft->id.'"', false)
        ->assertSee('window.athenaResearchAssistantActiveContextId = '.$topic->id, false)
        ->assertSee('Paper help')
        ->assertSee('Live context')
        ->assertSee('Review saved paper')
        ->assertSee('Uses saved proposal data and current-paper rules')
        ->assertSee('paperContextLabel()', false);

    $this->actingAs($faculty)
        ->get(route('faculty.proposal-drafts.detailed-proposal.edit', $draft))
        ->assertOk()
        ->assertSee('Check methods and evidence')
        ->assertSee('Uses saved detailed-proposal values and paper relationships');
});

test('research heads can launch athena with an authorized proposal selected', function () {
    $this->withoutVite();

    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');
    $researchHead = User::factory()->create();
    $researchHead->assignRole('research_head');
    $topic = createAssistantTopicFor($faculty, [
        'title' => 'Research Head context proposal',
    ]);

    $this->withSession([
        User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD,
    ])->actingAs($researchHead)
        ->get(route('topics.show', $topic))
        ->assertOk()
        ->assertSee('window.athenaResearchAssistantActiveContextId = '.$topic->id, false)
        ->assertSee('Research Head context proposal')
        ->assertSee('Check proposal next steps')
        ->assertSee('Make a revision plan');
});

test('the current authorized record stays selected when it is older than the recent context limit', function (string $access) {
    $this->withoutVite();

    $owner = User::factory()->create();
    $owner->assignRole('faculty');
    $viewer = $owner;
    $workspace = User::WORKSPACE_FACULTY;
    $status = 'revision_requested';

    if ($access === 'collaborator draft') {
        $viewer = User::factory()->create();
        $viewer->assignRole('faculty');
    } elseif ($access === 'faculty researcher') {
        $viewer = User::factory()->create();
        $viewer->assignRole('faculty_researcher');
        $owner = $viewer;
        $workspace = User::WORKSPACE_FACULTY_RESEARCHER;
        $status = 'approved';
    } elseif ($access === 'research head') {
        $viewer = User::factory()->create();
        $viewer->assignRole('research_head');
        $workspace = User::WORKSPACE_RESEARCH_HEAD;
    }

    $currentTopic = createAssistantTopicFor($owner, [
        'title' => 'Older current context for '.$access,
        'status' => $status,
    ]);
    $currentTopic->forceFill([
        'created_at' => now()->subDays(2),
        'updated_at' => now()->subDays(2),
    ])->saveQuietly();

    $route = route(
        $access === 'faculty researcher' ? 'research.show' : 'topics.show',
        $currentTopic,
    );

    if ($access === 'collaborator draft') {
        $currentTopic->collaborators()->create([
            'user_id' => $viewer->id,
            'name' => $viewer->name,
            'email' => $viewer->email,
            'accepted_at' => now(),
        ]);
        $draft = ProposalDraft::create([
            'user_id' => $owner->id,
            'research_call_id' => $currentTopic->research_call_id,
            'topic_id' => $currentTopic->id,
            'project_title' => $currentTopic->title,
            'duration_months' => 12,
            'planned_start' => now()->startOfMonth(),
            'planned_end' => now()->startOfMonth()->addMonths(11)->endOfMonth(),
            'project_leader' => $owner->name,
        ]);
        $draft->members()->create([
            'user_id' => $viewer->id,
            'name' => $viewer->name,
            'email' => $viewer->email,
            'accepted_at' => now(),
        ]);
        $route = route('faculty.proposal-drafts.details.edit', $draft);
    }

    foreach (range(1, 8) as $number) {
        createAssistantTopicFor($access === 'research head' ? $owner : $viewer, [
            'title' => "Newer context {$number} for {$access}",
            'status' => $status,
        ]);
    }

    $response = $this->withSession([
        User::ACTIVE_WORKSPACE_SESSION_KEY => $workspace,
    ])->actingAs($viewer)
        ->get($route)
        ->assertOk()
        ->assertSee('window.athenaResearchAssistantActiveContextId = '.$currentTopic->id, false);

    $contextIds = assistantContextIds($response);

    expect($contextIds)
        ->toHaveCount(8)
        ->and($contextIds[0])->toBe($currentTopic->id)
        ->and($contextIds)->toContain($currentTopic->id);
})->with([
    'proposal owner' => 'owner',
    'proposal collaborator editing a draft' => 'collaborator draft',
    'faculty researcher project' => 'faculty researcher',
    'research head proposal' => 'research head',
]);

test('authenticated users can receive a gemini research response', function (string $role) {
    config([
        'services.gemini.key' => 'test-key',
        'services.gemini.model' => 'gemini-3.5-flash',
        'services.gemini.base_url' => 'https://generativelanguage.googleapis.com/v1beta/openai',
    ]);

    Http::fake([
        'generativelanguage.googleapis.com/v1beta/openai/chat/completions' => Http::response([
            'choices' => [[
                'message' => ['content' => 'Start by defining your population and measurable variables.'],
            ]],
            'usage' => ['prompt_tokens' => 20, 'completion_tokens' => 12],
        ]),
    ]);

    $researcher = User::factory()->create(['name' => 'Athena Researcher']);
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
        ->assertJsonPath('model', 'gemini-3.5-flash');

    Http::assertSent(fn ($request) => $request->url() === 'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions'
        && $request['max_completion_tokens'] === 1400
        && $request['messages'][0]['role'] === 'system'
        && str_contains($request['messages'][0]['content'], 'Display name: "Athena Researcher"')
        && str_contains($request['messages'][0]['content'], 'Athena role(s): '.str_replace('_', ' ', $role))
        && str_contains($request['messages'][0]['content'], 'Proposal-paper changes save automatically.'));
})->with(['faculty', 'faculty_researcher', 'research_head']);

test('assistant recognizes the authenticated account without relying on the AI provider', function (string $question) {
    Http::preventStrayRequests();

    $researcher = User::factory()->create(['name' => 'Athena Researcher']);
    $researcher->assignRole('faculty_researcher');

    $this->actingAs($researcher)
        ->postJson(route('research-support.chat'), [
            'messages' => [[
                'role' => 'user',
                'content' => $question,
            ]],
        ])
        ->assertOk()
        ->assertJsonPath('reply', "You're Athena Researcher, signed in to ATHENA as Faculty Researcher.")
        ->assertJsonPath('model', 'athena-account-context')
        ->assertJsonPath('sources', []);

    Http::assertNothingSent();
})->with([
    'Who am I?',
    'Do you know me?',
    'What is my name?',
    'Which account am I using?',
]);

test('users can explicitly analyze one selected PDF or DOCX', function (string $format) {
    config([
        'services.gemini.key' => 'test-key',
        'services.gemini.model' => 'gemini-3.5-flash',
        'services.gemini.base_url' => 'https://generativelanguage.googleapis.com/v1beta/openai',
    ]);
    Storage::fake('local');
    Process::preventStrayProcesses();

    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');
    $topic = createAssistantTopicFor($faculty, ['title' => 'Explicit document analysis']);
    $uniqueDocumentText = 'SELECTED DOCUMENT EVIDENCE: quarterly water sampling requires twelve verified stations.';
    $path = 'proposals/assistant-selected-document.'.$format;
    $contents = $format === 'docx'
        ? assistantDocxContents($uniqueDocumentText)
        : "%PDF-1.7\nselected document fixture";

    Storage::disk('local')->put($path, $contents);
    $topic->latestVersion->files()->create([
        'document_type' => ProposalVersionFile::TYPE_DETAILED_PROPOSAL,
        'position' => 0,
        'file_path' => $path,
        'original_filename' => 'selected-proposal.'.$format,
        'mime_type' => $format === 'pdf'
            ? 'application/pdf'
            : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'file_size' => strlen($contents),
        'checksum' => hash('sha256', $contents),
    ]);

    if ($format === 'pdf') {
        Process::fake([
            '*' => Process::result(output: $uniqueDocumentText),
        ]);
    }

    $documents = $this->actingAs($faculty)
        ->getJson(route('research-support.documents', ['topic_id' => $topic->id]))
        ->assertOk()
        ->assertJsonCount(1, 'documents')
        ->assertJsonPath('documents.0.format', strtoupper($format));
    $documentToken = $documents->json('documents.0.token');

    Http::fake([
        'generativelanguage.googleapis.com/v1beta/openai/chat/completions' => Http::response([
            'choices' => [[
                'message' => ['content' => 'The selected document uses quarterly sampling across twelve stations.'],
            ]],
        ]),
    ]);

    $this->actingAs($faculty)
        ->postJson(route('research-support.chat'), [
            'messages' => [[
                'role' => 'user',
                'content' => 'Analyze the selected document.',
            ]],
            'context' => ['topic_id' => $topic->id],
            'action' => [
                'type' => 'analyze_document',
                'document_token' => $documentToken,
            ],
        ])
        ->assertOk()
        ->assertJsonPath('reply', 'The selected document uses quarterly sampling across twelve stations.');

    Http::assertSent(function ($request) use ($uniqueDocumentText): bool {
        $prompt = collect($request['messages'])->pluck('content')->join("\n");

        return $request['max_completion_tokens'] === 1400
            && str_contains($prompt, 'ATHENA explicitly selected document')
            && str_contains($prompt, $uniqueDocumentText);
    });

    if ($format === 'pdf') {
        Process::assertRan(fn (PendingProcess $process): bool => is_array($process->command)
            && in_array('-l', $process->command, true)
            && in_array('40', $process->command, true));
    }
})->with([
    'PDF document' => 'pdf',
    'DOCX document' => 'docx',
]);

test('uploaded document contents are not transmitted during ordinary chat', function () {
    config([
        'services.gemini.key' => 'test-key',
        'services.gemini.model' => 'gemini-3.5-flash',
        'services.gemini.base_url' => 'https://generativelanguage.googleapis.com/v1beta/openai',
    ]);
    Storage::fake('local');

    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');
    $topic = createAssistantTopicFor($faculty);
    $uniqueDocumentText = 'PRIVATE DOCUMENT BODY THAT MUST NOT BE SENT AUTOMATICALLY';
    $contents = assistantDocxContents($uniqueDocumentText);
    $path = 'proposals/private-proposal.docx';
    Storage::disk('local')->put($path, $contents);
    $topic->latestVersion->files()->create([
        'document_type' => ProposalVersionFile::TYPE_DETAILED_PROPOSAL,
        'position' => 0,
        'file_path' => $path,
        'original_filename' => 'private-proposal.docx',
        'file_size' => strlen($contents),
    ]);
    Http::fake([
        'generativelanguage.googleapis.com/v1beta/openai/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => 'Your saved proposal context is available.']]],
        ]),
    ]);

    $this->actingAs($faculty)
        ->postJson(route('research-support.chat'), [
            'messages' => [['role' => 'user', 'content' => 'What is the current proposal status?']],
            'context' => ['topic_id' => $topic->id],
        ])
        ->assertOk();

    Http::assertSent(function ($request) use ($uniqueDocumentText): bool {
        $prompt = collect($request['messages'])->pluck('content')->join("\n");

        return $request['max_completion_tokens'] === 1400
            && ! str_contains($prompt, $uniqueDocumentText)
            && ! str_contains($prompt, 'ATHENA explicitly selected document');
    });
});

test('document analysis tokens are reauthorized for the current account', function () {
    config([
        'services.gemini.key' => 'test-key',
        'services.gemini.model' => 'gemini-3.5-flash',
        'services.gemini.base_url' => 'https://generativelanguage.googleapis.com/v1beta/openai',
    ]);
    Storage::fake('local');

    $owner = User::factory()->create();
    $owner->assignRole('faculty');
    $topic = createAssistantTopicFor($owner);
    $contents = assistantDocxContents('Owner-only proposal document with enough readable research details.');
    $path = 'proposals/owner-only.docx';
    Storage::disk('local')->put($path, $contents);
    $topic->latestVersion->files()->create([
        'document_type' => ProposalVersionFile::TYPE_DETAILED_PROPOSAL,
        'position' => 0,
        'file_path' => $path,
        'original_filename' => 'owner-only.docx',
        'file_size' => strlen($contents),
    ]);
    $documentToken = $this->actingAs($owner)
        ->getJson(route('research-support.documents', ['topic_id' => $topic->id]))
        ->assertOk()
        ->json('documents.0.token');

    $otherFaculty = User::factory()->create();
    $otherFaculty->assignRole('faculty');
    Http::preventStrayRequests();

    $this->actingAs($otherFaculty)
        ->postJson(route('research-support.chat'), [
            'messages' => [['role' => 'user', 'content' => 'Analyze this document.']],
            'action' => [
                'type' => 'analyze_document',
                'document_token' => $documentToken,
            ],
        ])
        ->assertForbidden()
        ->assertJsonPath('message', 'That document is unavailable for your account.');

    Http::assertNothingSent();
});

test('athena carries a compact memory beyond the eight recent messages without replacing full history', function () {
    config([
        'services.gemini.key' => 'test-key',
        'services.gemini.model' => 'gemini-3.5-flash',
        'services.gemini.base_url' => 'https://generativelanguage.googleapis.com/v1beta/openai',
    ]);

    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');
    $savedMessages = [
        ['role' => 'user', 'content' => 'Remember our decision: use quarterly sampling and keep the twelve-station design.'],
        ['role' => 'assistant', 'content' => 'I will keep that decision in mind.'],
        ['role' => 'user', 'content' => 'Draft the background.'],
        ['role' => 'assistant', 'content' => 'Here is a background outline.'],
        ['role' => 'user', 'content' => 'Keep it concise.'],
        ['role' => 'assistant', 'content' => 'Understood.'],
        ['role' => 'user', 'content' => 'Now check the objectives.'],
        ['role' => 'assistant', 'content' => 'The objectives are measurable.'],
        ['role' => 'user', 'content' => 'What earlier sampling decision did we make?'],
    ];
    $conversation = ResearchAssistantConversation::factory()->create([
        'user_id' => $faculty->id,
        'messages' => $savedMessages,
        'summary' => null,
        'summarized_message_count' => 0,
    ]);
    $requestNumber = 0;

    Http::fake(function () use (&$requestNumber) {
        $requestNumber++;

        return $requestNumber === 1
            ? Http::response([
                'choices' => [['message' => ['content' => 'Decisions and corrections: Use quarterly sampling with a twelve-station design.']]],
            ])
            : Http::response([
                'choices' => [['message' => ['content' => 'You chose quarterly sampling across twelve stations.']]],
            ]);
    });

    $this->actingAs($faculty)
        ->postJson(route('research-support.chat'), [
            'conversation_id' => $conversation->id,
            'messages' => array_slice($savedMessages, -8),
        ])
        ->assertOk()
        ->assertJsonPath('reply', 'You chose quarterly sampling across twelve stations.');

    Http::assertSentCount(2);
    Http::assertSent(function ($request): bool {
        $prompt = collect($request['messages'])->pluck('content')->join("\n");

        return $request['max_completion_tokens'] === 1400
            && str_contains($prompt, 'ATHENA earlier conversation memory')
            && str_contains($prompt, 'quarterly sampling with a twelve-station design');
    });

    $conversation->refresh();

    expect($conversation->summary)
        ->toBe('Decisions and corrections: Use quarterly sampling with a twelve-station design.')
        ->and($conversation->summarized_message_count)->toBe(1)
        ->and($conversation->messages)->toHaveCount(9)
        ->and($conversation->messages)->toBe($savedMessages);
});

test('assistant accepts a compacted research-results prompt longer than the manual composer limit', function () {
    config([
        'services.gemini.key' => 'test-key',
        'services.gemini.model' => 'gemini-3.5-flash',
        'services.gemini.base_url' => 'https://generativelanguage.googleapis.com/v1beta/openai',
    ]);

    Http::fake([
        'generativelanguage.googleapis.com/v1beta/openai/chat/completions' => Http::response([
            'choices' => [[
                'message' => ['content' => 'Group the studies by theme and method.'],
            ]],
        ]),
    ]);

    $researcher = User::factory()->create();
    $researcher->assignRole('faculty');
    $generatedPrompt = str_repeat('Research result context. ', 120);

    expect(strlen($generatedPrompt))->toBeGreaterThan(2000);

    $this->actingAs($researcher)
        ->postJson(route('research-support.chat'), [
            'messages' => [['role' => 'user', 'content' => $generatedPrompt]],
        ])
        ->assertOk()
        ->assertJsonPath('reply', 'Group the studies by theme and method.');
});

test('assistant reports a provider connection failure without crashing', function () {
    config([
        'services.gemini.key' => 'test-key',
        'services.gemini.model' => 'gemini-3.5-flash',
        'services.gemini.base_url' => 'https://generativelanguage.googleapis.com/v1beta/openai',
    ]);

    Http::fake([
        'generativelanguage.googleapis.com/v1beta/openai/chat/completions' => Http::failedConnection('Provider unavailable'),
    ]);

    $user = User::factory()->create();
    $user->assignRole('research_head');

    $this->actingAs($user)
        ->postJson(route('research-support.chat'), [
            'messages' => [['role' => 'user', 'content' => 'Help me plan a study.']],
        ])
        ->assertServiceUnavailable()
        ->assertJsonPath('message', 'The research assistant could not be reached. Please try again.');
});

test('assistant rejects a malformed successful provider response', function () {
    config([
        'services.gemini.key' => 'test-key',
        'services.gemini.model' => 'gemini-3.5-flash',
        'services.gemini.base_url' => 'https://generativelanguage.googleapis.com/v1beta/openai',
    ]);

    Http::fake([
        'generativelanguage.googleapis.com/v1beta/openai/chat/completions' => Http::response(['choices' => []]),
    ]);

    $user = User::factory()->create();
    $user->assignRole('faculty');

    $this->actingAs($user)
        ->postJson(route('research-support.chat'), [
            'messages' => [['role' => 'user', 'content' => 'Help me plan a study.']],
        ])
        ->assertStatus(502)
        ->assertJsonPath('message', 'The assistant returned an empty response. Please try rephrasing your question.');
});

test('users can attach their own proposal context to a chat request', function () {
    config([
        'services.gemini.key' => 'test-key',
        'services.gemini.model' => 'gemini-3.5-flash',
        'services.gemini.base_url' => 'https://generativelanguage.googleapis.com/v1beta/openai',
    ]);

    Http::fake([
        'generativelanguage.googleapis.com/v1beta/openai/chat/completions' => Http::response([
            'choices' => [[
                'message' => ['content' => 'Use the reviewer comment as the revision plan anchor.'],
            ]],
        ]),
    ]);

    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');
    $topic = createAssistantTopicFor($faculty);
    $reviewer = User::factory()->create(['name' => 'Dr. Reviewer']);
    $review = $topic->reviews()->create([
        'reviewer_id' => $reviewer->id,
        'decision' => 'revision_requested',
        'comment' => 'Clarify the sampling frame and target respondents.',
    ]);
    $versionFile = $topic->latestVersion->files()->create([
        'document_type' => ProposalVersionFile::TYPE_DETAILED_PROPOSAL,
        'position' => 0,
        'file_path' => 'proposals/detailed-proposal.pdf',
        'original_filename' => 'detailed-proposal.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 100,
        'checksum' => str_repeat('c', 64),
    ]);
    $fileRevision = $review->fileRevisions()->create([
        'proposal_version_file_id' => $versionFile->id,
        'document_type' => ProposalVersionFile::TYPE_DETAILED_PROPOSAL,
        'original_filename' => 'detailed-proposal.pdf',
        'revision_note' => 'Replace the unsupported sample-size claim.',
    ]);
    $versionFile->annotations()->create([
        'reviewer_id' => $reviewer->id,
        'topic_review_file_revision_id' => $fileRevision->id,
        'annotation_type' => 'text',
        'page_number' => 4,
        'selected_text' => 'A sample of 20 is sufficient.',
        'rectangles' => [],
        'comment' => 'Provide a defensible sample-size basis.',
    ]);
    $topic->progressReports()->create([
        'submitted_by' => $faculty->id,
        'reporting_date' => now()->toDateString(),
        'progress_percentage' => 45,
        'accomplishments' => 'Completed the first round of coastal observations.',
        'issues' => 'Field visits were delayed by severe weather.',
        'review_status' => 'revision_requested',
        'research_head_remarks' => 'Add the revised fieldwork schedule.',
    ]);

    $this->actingAs($faculty)
        ->postJson(route('research-support.chat'), [
            'context' => [
                'topic_id' => $topic->id,
                'workflow_scope' => 'review',
            ],
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
            && str_contains($message['content'], 'Replace the unsupported sample-size claim.')
            && str_contains($message['content'], 'A sample of 20 is sufficient.')
            && str_contains($message['content'], 'Provide a defensible sample-size basis.')
            && str_contains($message['content'], 'unresolved_required_revisions')
            && ! str_contains($message['content'], 'Completed the first round of coastal observations')
            && ! str_contains($message['content'], 'Add the revised fieldwork schedule')
    ));
});

test('assistant receives Notice to Proceed and post-approval reporting context', function () {
    config([
        'services.gemini.key' => 'test-key',
        'services.gemini.model' => 'gemini-3.5-flash',
        'services.gemini.base_url' => 'https://generativelanguage.googleapis.com/v1beta/openai',
    ]);

    Http::fake([
        'generativelanguage.googleapis.com/v1beta/openai/chat/completions' => Http::response([
            'choices' => [[
                'message' => ['content' => 'The signed notice and reporting records are available in context.'],
            ]],
        ]),
    ]);

    $faculty = User::factory()->create(['name' => 'Project Owner']);
    $faculty->assignRole('faculty_researcher');
    $researchHead = User::factory()->create(['name' => 'Research Head']);
    $researchHead->assignRole('research_head');
    $topic = createAssistantTopicFor($faculty, [
        'title' => 'Post-approval coastal project',
        'status' => 'approved',
    ]);
    $topic->update([
        'signed_approval_path' => 'approvals/signed.pdf',
        'notice_to_proceed_issued_by' => $researchHead->id,
        'notice_to_proceed_issued_at' => now(),
        'notice_to_proceed_data' => [
            'notice_date' => '2026-08-20',
            'researcher_names' => 'Project Owner and Research Partner',
            'campus_line' => 'ARASOF-Nasugbu Campus',
            'project_title' => 'Post-approval coastal project',
            'resolution_number' => 'LREC-2026-014',
            'resolution_year' => '2026',
            'approved_start_date' => '2026-09-01',
            'approved_end_date' => '2027-08-31',
            'approved_duration_months' => 12,
            'approved_budget' => '75000.00',
            'issuing_officer_name' => 'Dr. Issuing Officer',
            'issuing_officer_title' => 'Vice Chancellor',
            'issuing_officer_committee_role' => 'LREC Vice Chair',
            'verifying_officer_name' => 'Dr. Verifying Officer',
            'verifying_officer_title' => 'Research Director',
            'verifying_officer_committee_role' => 'LREC Chair',
        ],
        'project_status' => TopicProposal::PROJECT_STATUS_ONGOING,
    ]);
    $topic->progressReports()->create([
        'submitted_by' => $faculty->id,
        'reporting_date' => '2026-12-31',
        'tracking_number' => 'PM-2026-001',
        'progress_percentage' => 55,
        'accomplishments' => 'Completed the baseline shoreline survey.',
        'issues' => 'Two field visits were rescheduled.',
        'work_plan' => [['activity' => 'Coastal survey', 'status' => 'completed']],
        'budget_utilization' => [['item' => 'Field supplies', 'amount' => 12500]],
        'research_head_remarks' => 'Continue documenting the rescheduled visits.',
    ]);
    $topic->narrativeReports()->create([
        'submitted_by' => $faculty->id,
        'submission_date' => '2027-01-05',
        'tracking_number' => 'NR-2027-001',
        'researchers' => $faculty->name,
        'implementation_start' => '2026-09-01',
        'implementation_end' => '2026-12-31',
        'budget' => '75000.00',
        'funding_agency' => 'Batangas State University',
        'accomplishment_summary' => 'Baseline monitoring was completed.',
        'introduction' => 'The project monitors coastal habitat recovery.',
        'objectives' => 'Measure shoreline habitat conditions.',
        'methodology' => 'Quarterly transect observations were conducted.',
        'results_discussion' => 'Initial observations show improving vegetation cover.',
        'photos' => [],
        'research_head_remarks' => 'Connect the results to the approved objectives.',
    ]);
    ProjectMonitoringDraft::create([
        'topic_id' => $topic->id,
        'user_id' => $faculty->id,
        'source_key' => 'new',
        'source_data' => [
            'reporting_date' => '2027-03-31',
            'tracking_number' => 'PRIVATE-MONITORING-DRAFT',
            'work_plan' => [['activity' => 'Validate the shoreline dataset']],
        ],
    ]);
    ProjectNarrativeReportDraft::create([
        'topic_id' => $topic->id,
        'user_id' => $faculty->id,
        'source_data' => [
            'submission_date' => '2027-04-05',
            'tracking_number' => 'PRIVATE-NARRATIVE-DRAFT',
            'accomplishment_summary' => 'Draft summary awaiting final field validation.',
        ],
    ]);
    $otherUser = User::factory()->create();
    ProjectMonitoringDraft::create([
        'topic_id' => $topic->id,
        'user_id' => $otherUser->id,
        'source_key' => 'new',
        'source_data' => ['tracking_number' => 'OTHER-USERS-PRIVATE-DRAFT'],
    ]);

    $this->withSession([
        User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY_RESEARCHER,
    ])->actingAs($faculty)
        ->postJson(route('research-support.chat'), [
            'context' => [
                'topic_id' => $topic->id,
                'workflow_scope' => 'notice',
                'form' => [
                    'section' => 'Notice details',
                    'values' => [[
                        'field' => 'resolution_number',
                        'label' => 'LREC Resolution Number',
                        'value' => 'LREC-2026-015 unsaved correction',
                    ]],
                ],
            ],
            'messages' => [[
                'role' => 'user',
                'content' => 'Summarize what happened after this proposal was approved.',
            ]],
        ])
        ->assertOk()
        ->assertJsonPath('reply', 'The signed notice and reporting records are available in context.');

    Http::assertSent(function ($request): bool {
        $prompt = collect($request['messages'])->pluck('content')->join("\n");

        return str_contains($prompt, 'LREC-2026-014')
            && str_contains($prompt, 'LREC-2026-015 unsaved correction')
            && str_contains($prompt, 'Dr. Issuing Officer')
            && str_contains($prompt, 'LREC Chair')
            && str_contains($prompt, '2026-09-01')
            && str_contains($prompt, '75000.00')
            && str_contains($prompt, 'PM-2026-001')
            && str_contains($prompt, 'Coastal survey')
            && str_contains($prompt, 'Field supplies')
            && str_contains($prompt, 'NR-2027-001')
            && str_contains($prompt, 'Quarterly transect observations were conducted.')
            && str_contains($prompt, 'Initial observations show improving vegetation cover.')
            && str_contains($prompt, 'Continue documenting the rescheduled visits.')
            && str_contains($prompt, 'Connect the results to the approved objectives.')
            && str_contains($prompt, 'PRIVATE-MONITORING-DRAFT')
            && str_contains($prompt, 'PRIVATE-NARRATIVE-DRAFT')
            && ! str_contains($prompt, 'OTHER-USERS-PRIVATE-DRAFT');
    });
});

test('assistant receives project completion status completed reports and remaining record items', function () {
    config([
        'services.gemini.key' => 'test-key',
        'services.gemini.model' => 'gemini-3.5-flash',
        'services.gemini.base_url' => 'https://generativelanguage.googleapis.com/v1beta/openai',
    ]);

    Http::fake([
        'generativelanguage.googleapis.com/v1beta/openai/chat/completions' => Http::response([
            'choices' => [[
                'message' => ['content' => 'The completion record is available.'],
            ]],
        ]),
    ]);

    $faculty = User::factory()->create();
    $faculty->assignRole('faculty_researcher');
    $topic = createAssistantTopicFor($faculty, [
        'title' => 'Completed shoreline project',
        'status' => 'approved',
    ]);
    $topic->update([
        'project_status' => TopicProposal::PROJECT_STATUS_COMPLETED,
        'notice_to_proceed_issued_at' => now()->subYear(),
        'notice_to_proceed_data' => ['resolution_number' => 'LREC-COMPLETE-01'],
    ]);
    $topic->progressReports()->create([
        'submitted_by' => $faculty->id,
        'reporting_date' => '2027-06-30',
        'tracking_number' => 'COMPLETED-MONITORING-01',
        'progress_percentage' => 100,
        'accomplishments' => 'All shoreline stations were assessed.',
        'review_status' => 'reviewed',
        'research_head_remarks' => 'Monitoring report accepted.',
    ]);
    $topic->narrativeReports()->create([
        'submitted_by' => $faculty->id,
        'submission_date' => '2027-07-05',
        'tracking_number' => 'NARRATIVE-REVISION-01',
        'researchers' => $faculty->name,
        'implementation_start' => '2026-08-01',
        'implementation_end' => '2027-06-30',
        'budget' => '75000.00',
        'funding_agency' => 'Batangas State University',
        'accomplishment_summary' => 'The field work is complete.',
        'introduction' => 'Completion narrative.',
        'objectives' => 'Assess shoreline conditions.',
        'methodology' => 'Repeated field observations.',
        'results_discussion' => 'All stations were assessed.',
        'photos' => [],
        'review_status' => 'revision_requested',
        'research_head_remarks' => 'Add the dissemination outcome.',
    ]);

    $this->withSession([
        User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY_RESEARCHER,
    ])->actingAs($faculty)
        ->postJson(route('research-support.chat'), [
            'context' => [
                'topic_id' => $topic->id,
                'workflow_scope' => 'completion',
            ],
            'messages' => [[
                'role' => 'user',
                'content' => 'What is the final status and what record items remain?',
            ]],
        ])
        ->assertOk()
        ->assertJsonPath('reply', 'The completion record is available.');

    Http::assertSent(function ($request): bool {
        $prompt = collect($request['messages'])->pluck('content')->join("\n");

        return str_contains($prompt, 'project_completion')
            && str_contains($prompt, '"project_status": "completed"')
            && str_contains($prompt, '"monitoring_reviewed": 1')
            && str_contains($prompt, 'COMPLETED-MONITORING-01')
            && str_contains($prompt, 'Monitoring report accepted.')
            && str_contains($prompt, 'NARRATIVE-REVISION-01')
            && str_contains($prompt, 'One or more submitted reports still have a revision request.')
            && str_contains($prompt, 'do not describe it as an institutional requirement');
    });
});

test('research heads can use authorized faculty proposals as assistant context', function () {
    config([
        'services.gemini.key' => 'test-key',
        'services.gemini.model' => 'gemini-3.5-flash',
        'services.gemini.base_url' => 'https://generativelanguage.googleapis.com/v1beta/openai',
    ]);

    Http::fake([
        'generativelanguage.googleapis.com/v1beta/openai/chat/completions' => Http::response([
            'choices' => [[
                'message' => ['content' => 'I can use the authorized proposal record.'],
            ]],
        ]),
    ]);

    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');
    $researchHead = User::factory()->create();
    $researchHead->assignRole('research_head');
    $topic = createAssistantTopicFor($faculty, [
        'title' => 'Faculty proposal visible to Research Head',
    ]);

    $this->withSession([
        User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD,
    ])->actingAs($researchHead)
        ->postJson(route('research-support.chat'), [
            'context' => ['topic_id' => $topic->id],
            'messages' => [[
                'role' => 'user',
                'content' => 'What is the current proposal status?',
            ]],
        ])
        ->assertOk()
        ->assertJsonPath('reply', 'I can use the authorized proposal record.');

    Http::assertSent(fn ($request): bool => collect($request['messages'])
        ->pluck('content')
        ->contains(fn (string $message): bool => str_contains($message, 'Faculty proposal visible to Research Head')));
});

test('assistant prioritizes the focused saved field even when it appears late in a large paper', function () {
    config([
        'services.gemini.key' => 'test-key',
        'services.gemini.model' => 'gemini-3.5-flash',
        'services.gemini.base_url' => 'https://generativelanguage.googleapis.com/v1beta/openai',
    ]);

    Http::fake([
        'generativelanguage.googleapis.com/v1beta/openai/chat/completions' => Http::response([
            'choices' => [[
                'message' => ['content' => 'I can read the focused saved methodology.'],
            ]],
        ]),
    ]);

    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');
    $topic = createAssistantTopicFor($faculty);
    $draft = ProposalDraft::create([
        'user_id' => $faculty->id,
        'research_call_id' => $topic->research_call_id,
        'topic_id' => $topic->id,
        'project_title' => 'Large detailed proposal',
        'duration_months' => 12,
        'planned_start' => '2026-08-01',
        'planned_end' => '2027-07-31',
        'project_leader' => $faculty->name,
    ]);
    $draft->documents()->create([
        'document_type' => ProposalVersionFile::TYPE_DETAILED_PROPOSAL,
        'position' => 0,
        'source_data' => [
            'early_values' => collect(range(1, 40))
                ->mapWithKeys(fn (int $number): array => ['field_'.$number => 'Value '.$number])
                ->all(),
            'methodology' => 'Late saved methodology using quarterly field observations.',
        ],
        'completed_at' => now(),
    ]);

    $this->actingAs($faculty)
        ->postJson(route('research-support.chat'), [
            'context' => [
                'proposal_draft_id' => $draft->id,
                'paper_slug' => 'detailed-proposal',
                'field' => 'methodology',
            ],
            'messages' => [[
                'role' => 'user',
                'content' => 'Review the saved methodology field.',
            ]],
        ])
        ->assertOk()
        ->assertJsonPath('reply', 'I can read the focused saved methodology.');

    Http::assertSent(function ($request): bool {
        $prompt = collect($request['messages'])->pluck('content')->join("\n");

        return str_contains($prompt, '"field": "methodology"')
            && str_contains($prompt, 'Late saved methodology using quarterly field observations.');
    });
});

test('athena receives a safe application context packet with live row values and saved budget consistency', function () {
    config([
        'services.gemini.key' => 'test-key',
        'services.gemini.model' => 'gemini-3.5-flash',
        'services.gemini.base_url' => 'https://generativelanguage.googleapis.com/v1beta/openai',
    ]);

    Http::fake([
        'generativelanguage.googleapis.com/v1beta/openai/chat/completions' => Http::response([
            'choices' => [[
                'message' => ['content' => 'Use ream as the unit, then reconcile the saved MOOE totals.'],
            ]],
        ]),
    ]);

    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');
    $topic = createAssistantTopicFor($faculty);
    $draft = ProposalDraft::create([
        'user_id' => $faculty->id,
        'research_call_id' => $topic->research_call_id,
        'topic_id' => $topic->id,
        'project_title' => 'Context packet budget study',
        'duration_months' => 12,
        'planned_start' => '2026-08-01',
        'planned_end' => '2027-07-31',
        'project_leader' => $faculty->name,
    ]);
    $draft->documents()->create([
        'document_type' => ProposalVersionFile::TYPE_LINE_ITEM_BUDGET,
        'position' => 0,
        'source_data' => [
            'amounts' => ['telephone_expenses' => 4200],
        ],
        'completed_at' => now(),
    ]);
    $draft->documents()->create([
        'document_type' => ProposalVersionFile::TYPE_EXPENSE_BREAKDOWN,
        'position' => 0,
        'source_data' => [
            'items' => [[
                'category' => 'mooe',
                'account' => 'Communication Expenses',
                'sub_account' => 'Telephone Expenses',
                'particulars' => 'Bond Paper',
                'details' => 'A4 paper for questionnaires',
                'purpose' => 'Printing research instruments',
                'unit' => 'ream',
                'quantity' => 12,
                'unit_cost' => 300,
            ]],
        ],
        'completed_at' => now(),
    ]);

    $this->actingAs($faculty)
        ->postJson(route('research-support.chat'), [
            'context' => [
                'proposal_draft_id' => $draft->id,
                'paper_slug' => 'expense-breakdown',
                'field' => 'items[0][unit]',
                'form' => [
                    'section' => 'Expense items / MOOE',
                    'row' => 'Items row 1 — Particular/s: Bond Paper',
                    'values' => [
                        ['field' => 'items[0][particulars]', 'label' => 'Particular/s', 'value' => 'Bond Paper'],
                        ['field' => 'items[0][unit]', 'label' => 'Unit', 'value' => 'ream'],
                        ['field' => 'items[0][quantity]', 'label' => 'Quantity', 'value' => '10'],
                        ['field' => 'items[0][unit_cost]', 'label' => 'Unit Cost', 'value' => '250'],
                        ['field' => 'leader_email', 'label' => 'Leader email', 'value' => 'person@example.edu'],
                    ],
                    'constraints' => ['A value is required by the current browser form.'],
                    'validation' => ['Quantity: Please fill out this field.'],
                ],
            ],
            'messages' => [[
                'role' => 'user',
                'content' => 'Review my saved proposal package. What should I put here, is it ready, and why do my totals differ?',
            ]],
        ])
        ->assertOk()
        ->assertJsonPath('reply', 'Use ream as the unit, then reconcile the saved MOOE totals.');

    Http::assertSent(function ($request): bool {
        $prompt = collect($request['messages'])->pluck('content')->join("\n");

        return str_contains($prompt, 'ATHENA application context packet')
            && str_contains($prompt, 'Context packet budget study')
            && str_contains($prompt, 'saved_current_paper')
            && str_contains($prompt, 'saved_connected_papers')
            && str_contains($prompt, 'proposal_readiness')
            && str_contains($prompt, 'papers_needing_attention')
            && str_contains($prompt, 'Saved paper values are saved data')
            && str_contains($prompt, '"current_section": "Expense items / MOOE"')
            && str_contains($prompt, 'Bond Paper')
            && str_contains($prompt, '"consistent": true')
            && str_contains($prompt, '"line_item_budget": 3600')
            && str_contains($prompt, '"expense_breakdown": 3600')
            && str_contains($prompt, '"difference": 0')
            && str_contains($prompt, '[redacted sensitive value]')
            && str_contains($prompt, 'unsaved, stale, incomplete, or user-edited')
            && ! str_contains($prompt, 'person@example.edu');
    });
});

test('users cannot attach an inaccessible proposal draft to an assistant request', function () {
    config([
        'services.gemini.key' => 'test-key',
        'services.gemini.model' => 'gemini-3.5-flash',
        'services.gemini.base_url' => 'https://generativelanguage.googleapis.com/v1beta/openai',
    ]);
    Http::fake();

    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');
    $otherFaculty = User::factory()->create();
    $otherFaculty->assignRole('faculty');
    $otherTopic = createAssistantTopicFor($otherFaculty);
    $otherDraft = ProposalDraft::create([
        'user_id' => $otherFaculty->id,
        'research_call_id' => $otherTopic->research_call_id,
        'topic_id' => $otherTopic->id,
        'project_title' => 'Private draft',
    ]);

    $this->actingAs($faculty)
        ->postJson(route('research-support.chat'), [
            'context' => ['proposal_draft_id' => $otherDraft->id],
            'messages' => [[
                'role' => 'user',
                'content' => 'Explain this draft.',
            ]],
        ])
        ->assertForbidden()
        ->assertJsonPath('message', 'That proposal draft context is unavailable for your account.');

    Http::assertNothingSent();
});

test('users cannot attach another faculty member proposal as context', function () {
    config(['services.gemini.key' => 'test-key']);
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

test('unauthorized roles cannot search related literature', function () {
    Http::fake();

    Role::firstOrCreate(['name' => 'research_head']);
    $researchHead = User::factory()->create();
    $researchHead->assignRole('research_head');

    $this->actingAs($researchHead)
        ->postJson(route('research-support.literature-search'), [
            'query' => 'community mangrove monitoring',
        ])
        ->assertForbidden();

    Http::assertNothingSent();
});

test('literature search query must be specific enough', function () {
    Http::fake();

    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');

    $response = $this->actingAs($faculty)
        ->postJson(route('research-support.literature-search'), [
            'query' => 'ai',
        ]);

    expect($response->getStatusCode())->toBe(422)
        ->and($response->json('errors.query.0'))->toBe('The query field must be at least 3 characters.');

    Http::assertNothingSent();
});

test('faculty can search related literature from academic metadata providers', function () {
    Http::fake([
        'api.semanticscholar.org/graph/v1/paper/search*' => Http::response([
            'data' => [[
                'title' => 'Community Mangrove Stewardship and Coastal Monitoring',
                'abstract' => 'This study reviews community participation in mangrove monitoring programs.',
                'authors' => [
                    ['name' => 'Maria Santos'],
                    ['name' => 'Luis Cruz'],
                ],
                'year' => 2024,
                'venue' => 'Environmental Monitoring Journal',
                'url' => 'https://www.semanticscholar.org/paper/example',
                'externalIds' => ['DOI' => '10.1234/mangrove'],
                'citationCount' => 12,
            ]],
        ]),
        'api.crossref.org/works*' => Http::response([
            'message' => [
                'items' => [[
                    'title' => ['Participatory Coastal Resource Monitoring'],
                    'abstract' => '<jats:p>Local communities can improve coastal resource monitoring when protocols are simple and repeatable.</jats:p>',
                    'author' => [
                        ['given' => 'Ana', 'family' => 'Reyes'],
                        ['given' => 'Mark', 'family' => 'Dela Cruz'],
                    ],
                    'published-print' => ['date-parts' => [[2022, 5, 1]]],
                    'container-title' => ['Journal of Coastal Research'],
                    'DOI' => '10.5678/coastal',
                    'URL' => 'https://doi.org/10.5678/coastal',
                    'is-referenced-by-count' => 7,
                ]],
            ],
        ]),
        'api.openalex.org/works*' => Http::response([
            'results' => [[
                'display_name' => 'OpenAlex Records for Community Monitoring',
                'abstract_inverted_index' => [
                    'OpenAlex' => [0],
                    'indexes' => [1],
                    'community' => [2],
                    'monitoring' => [3],
                    'studies' => [4],
                ],
                'authorships' => [
                    ['author' => ['display_name' => 'Joanna Lee']],
                    ['author' => ['display_name' => 'Rafael Torres']],
                ],
                'publication_year' => 2023,
                'primary_location' => [
                    'source' => ['display_name' => 'Open Research Index'],
                    'landing_page_url' => 'https://openalex.org/W123',
                ],
                'doi' => 'https://doi.org/10.2468/openalex',
                'id' => 'https://openalex.org/W123',
                'cited_by_count' => 25,
                'open_access' => ['is_oa' => true],
                'type' => 'article',
            ]],
        ]),
    ]);

    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');

    $this->actingAs($faculty)
        ->postJson(route('research-support.literature-search'), [
            'query' => 'community mangrove monitoring',
        ])
        ->assertOk()
        ->assertJsonCount(3, 'results')
        ->assertJsonPath('results.0.title', 'Community Mangrove Stewardship and Coastal Monitoring')
        ->assertJsonPath('results.0.description', 'This study reviews community participation in mangrove monitoring programs.')
        ->assertJsonPath('results.0.authors', 'Maria Santos, Luis Cruz')
        ->assertJsonPath('results.0.year', 2024)
        ->assertJsonPath('results.0.venue', 'Environmental Monitoring Journal')
        ->assertJsonPath('results.0.doi', '10.1234/mangrove')
        ->assertJsonPath('results.0.source', 'Semantic Scholar')
        ->assertJsonPath('results.0.citation_count', 12)
        ->assertJsonPath('results.0.relevance_label', 'Strong match')
        ->assertJsonPath('results.0.matched_terms.0', 'community')
        ->assertJsonPath('results.1.title', 'OpenAlex Records for Community Monitoring')
        ->assertJsonPath('results.1.description', 'OpenAlex indexes community monitoring studies')
        ->assertJsonPath('results.1.authors', 'Joanna Lee, Rafael Torres')
        ->assertJsonPath('results.1.year', 2023)
        ->assertJsonPath('results.1.venue', 'Open Research Index')
        ->assertJsonPath('results.1.doi', '10.2468/openalex')
        ->assertJsonPath('results.1.source', 'OpenAlex')
        ->assertJsonPath('results.1.citation_count', 25)
        ->assertJsonPath('results.1.is_open_access', true)
        ->assertJsonPath('results.1.type', 'article')
        ->assertJsonPath('results.2.title', 'Participatory Coastal Resource Monitoring')
        ->assertJsonPath('results.2.description', 'Local communities can improve coastal resource monitoring when protocols are simple and repeatable.')
        ->assertJsonPath('results.2.authors', 'Ana Reyes, Mark Dela Cruz')
        ->assertJsonPath('results.2.year', 2022)
        ->assertJsonPath('results.2.venue', 'Journal of Coastal Research')
        ->assertJsonPath('results.2.doi', '10.5678/coastal')
        ->assertJsonPath('results.2.source', 'Crossref')
        ->assertJsonPath('results.2.citation_count', 7)
        ->assertJson(fn ($json) => $json
            ->whereType('results.0.relevance_score', 'integer')
            ->whereType('results.1.relevance_score', 'integer')
            ->whereType('results.2.relevance_score', 'integer')
            ->etc());

    Http::assertSentCount(3);
});

test('literature search requests wider provider pools and returns up to fifty useful records', function () {
    config()->set('services.semantic_scholar.key', 'semantic-scholar-test-key');

    $papers = collect(range(1, 60))
        ->map(fn (int $number): array => [
            'title' => "Community monitoring evidence study {$number}",
            'abstract' => 'Community monitoring evidence for local environmental programs.',
            'authors' => [['name' => "Researcher {$number}"]],
            'year' => 2024,
            'venue' => 'Community Research Journal',
            'url' => "https://www.semanticscholar.org/paper/{$number}",
            'externalIds' => ['DOI' => "10.1000/community.{$number}"],
            'citationCount' => $number,
        ])
        ->all();

    Http::fake([
        'api.semanticscholar.org/graph/v1/paper/search*' => Http::response(['data' => $papers]),
        'api.crossref.org/works*' => Http::response(['message' => ['items' => []]]),
        'api.openalex.org/works*' => Http::response(['results' => []]),
    ]);

    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');

    $this->actingAs($faculty)
        ->postJson(route('research-support.literature-search'), [
            'query' => 'community monitoring evidence',
        ])
        ->assertOk()
        ->assertJsonCount(50, 'results');

    Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://api.semanticscholar.org/graph/v1/paper/search')
        && str_contains($request->url(), 'limit=60')
        && $request->hasHeader('x-api-key', 'semantic-scholar-test-key'));
    Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://api.crossref.org/works')
        && str_contains($request->url(), 'rows=60'));
    Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://api.openalex.org/works')
        && str_contains($request->url(), 'per_page=60'));
});

test('literature search recognizes related word forms instead of dropping relevant records', function () {
    Http::fake([
        'api.semanticscholar.org/graph/v1/paper/search*' => Http::response([
            'data' => [[
                'title' => 'Participatory Mangrove Monitoring in Coastal Communities',
                'abstract' => 'Local participation supports long-term community monitoring.',
                'authors' => [['name' => 'Maria Santos']],
                'year' => 2024,
                'venue' => 'Coastal Research Journal',
                'url' => 'https://www.semanticscholar.org/paper/word-family',
                'externalIds' => ['DOI' => '10.1000/word-family'],
                'citationCount' => 8,
            ]],
        ]),
        'api.crossref.org/works*' => Http::response(['message' => ['items' => []]]),
        'api.openalex.org/works*' => Http::response(['results' => []]),
    ]);

    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');

    $this->actingAs($faculty)
        ->postJson(route('research-support.literature-search'), [
            'query' => 'participation mangrove monitor',
        ])
        ->assertOk()
        ->assertJsonCount(1, 'results')
        ->assertJsonPath('results.0.title', 'Participatory Mangrove Monitoring in Coastal Communities')
        ->assertJsonPath('results.0.matched_terms.0', 'participation')
        ->assertJsonPath('results.0.matched_terms.2', 'monitor');
});

test('literature search merges duplicate provider metadata into the strongest record', function () {
    $indexedAbstract = str_repeat('Community mangrove monitoring evidence supports sustained local observation. ', 18);

    Http::fake([
        'api.semanticscholar.org/graph/v1/paper/search*' => Http::response([
            'data' => [[
                'title' => 'Community Mangrove Monitoring',
                'abstract' => $indexedAbstract,
                'authors' => [['name' => 'Maria Santos']],
                'year' => 2024,
                'venue' => '',
                'url' => 'https://www.semanticscholar.org/paper/merged',
                'externalIds' => ['DOI' => '10.1000/merged'],
                'citationCount' => 5,
            ]],
        ]),
        'api.crossref.org/works*' => Http::response([
            'message' => ['items' => [[
                'title' => ['Community Mangrove Monitoring'],
                'author' => [['given' => 'Maria', 'family' => 'Santos']],
                'published-online' => ['date-parts' => [[2024]]],
                'container-title' => ['Coastal Research Journal'],
                'DOI' => '10.1000/merged',
                'URL' => 'https://doi.org/10.1000/merged',
                'is-referenced-by-count' => 24,
            ]]],
        ]),
        'api.openalex.org/works*' => Http::response(['results' => []]),
    ]);

    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');

    $response = $this->actingAs($faculty)
        ->postJson(route('research-support.literature-search'), [
            'query' => 'community mangrove monitoring',
        ])
        ->assertOk()
        ->assertJsonCount(1, 'results')
        ->assertJsonPath('results.0.description', trim($indexedAbstract))
        ->assertJsonPath('results.0.venue', 'Coastal Research Journal')
        ->assertJsonPath('results.0.citation_count', 24);

    expect(strlen($response->json('results.0.description')))->toBeGreaterThan(700);
});

test('literature search stops broad one-word queries before returning misleading matches', function () {
    Http::fake();

    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');

    $response = $this->actingAs($faculty)
        ->postJson(route('research-support.literature-search'), ['query' => 'athena'])
        ->assertOk()
        ->assertJsonCount(0, 'results')
        ->assertJsonPath('query_guidance.is_broad', true)
        ->assertJsonPath('query_guidance.term_count', 1);

    expect($response->json('query_guidance.message'))->toContain('too broad');

    Http::assertNothingSent();
});

test('literature search applies filters to normalized provider results', function () {
    Http::fake([
        'api.semanticscholar.org/graph/v1/paper/search*' => Http::response([
            'data' => [[
                'title' => 'Older closed literature result',
                'abstract' => 'This result should be filtered out by citation count.',
                'authors' => [['name' => 'Filtered Author']],
                'year' => 2022,
                'venue' => 'Filtered Journal',
                'url' => 'https://www.semanticscholar.org/paper/filtered',
                'externalIds' => ['DOI' => '10.1000/filtered'],
                'citationCount' => 2,
                'openAccessPdf' => ['url' => 'https://example.test/filtered.pdf'],
                'publicationTypes' => ['JournalArticle'],
            ]],
        ]),
        'api.crossref.org/works*' => Http::response([
            'message' => [
                'items' => [[
                    'title' => ['Closed access result with enough citations'],
                    'author' => [['given' => 'Closed', 'family' => 'Author']],
                    'published-online' => ['date-parts' => [[2022]]],
                    'container-title' => ['Closed Journal'],
                    'DOI' => '10.1000/closed',
                    'URL' => 'https://doi.org/10.1000/closed',
                    'is-referenced-by-count' => 12,
                ]],
            ],
        ]),
        'api.openalex.org/works*' => Http::response([
            'results' => [[
                'display_name' => 'Open access community monitoring review',
                'abstract_inverted_index' => [
                    'Relevant' => [0],
                    'open' => [1],
                    'access' => [2],
                    'review' => [3],
                ],
                'authorships' => [['author' => ['display_name' => 'Open Author']]],
                'publication_year' => 2022,
                'primary_location' => [
                    'source' => ['display_name' => 'Open Journal'],
                    'landing_page_url' => 'https://openalex.org/W456',
                ],
                'doi' => 'https://doi.org/10.1000/open',
                'id' => 'https://openalex.org/W456',
                'cited_by_count' => 14,
                'open_access' => ['is_oa' => true],
                'type' => 'article',
            ], [
                'display_name' => 'Too old open access result',
                'abstract_inverted_index' => ['Too' => [0], 'old' => [1]],
                'authorships' => [['author' => ['display_name' => 'Old Author']]],
                'publication_year' => 2018,
                'primary_location' => ['source' => ['display_name' => 'Archive Journal']],
                'doi' => 'https://doi.org/10.1000/old',
                'id' => 'https://openalex.org/W789',
                'cited_by_count' => 40,
                'open_access' => ['is_oa' => true],
                'type' => 'article',
            ]],
        ]),
    ]);

    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');

    $this->actingAs($faculty)
        ->postJson(route('research-support.literature-search'), [
            'query' => 'community monitoring',
            'year_from' => 2020,
            'year_to' => 2024,
            'min_citations' => 5,
            'open_access' => true,
        ])
        ->assertOk()
        ->assertJsonCount(1, 'results')
        ->assertJsonPath('results.0.title', 'Open access community monitoring review')
        ->assertJsonPath('results.0.source', 'OpenAlex')
        ->assertJsonPath('results.0.is_open_access', true)
        ->assertJsonPath('results.0.citation_count', 14);

    Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://api.openalex.org/works')
        && str_contains(urldecode($request->url()), 'publication_year:2020-2024')
        && str_contains(urldecode($request->url()), 'cited_by_count:>4')
        && str_contains(urldecode($request->url()), 'is_oa:true'));
    Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://api.semanticscholar.org/graph/v1/paper/search')
        && str_contains(urldecode($request->url()), 'year=2020-2024')
        && str_contains(urldecode($request->url()), 'openAccessPdf=true'));
    Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://api.crossref.org/works')
        && str_contains(urldecode($request->url()), 'from-pub-date:2020-01-01')
        && str_contains(urldecode($request->url()), 'until-pub-date:2024-12-31'));
});

test('literature search rejects an invalid year range', function () {
    Http::fake();

    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');

    $response = $this->actingAs($faculty)
        ->postJson(route('research-support.literature-search'), [
            'query' => 'community monitoring',
            'year_from' => 2025,
            'year_to' => 2020,
        ]);

    expect($response->getStatusCode())->toBe(422)
        ->and($response->json('errors.year_from.0'))->toBe('The starting year must be before or equal to the ending year.');

    Http::assertNothingSent();
});

test('literature search returns available results when one provider fails', function () {
    Http::fake([
        'api.semanticscholar.org/graph/v1/paper/search*' => Http::response([], 429),
        'api.crossref.org/works*' => Http::response([
            'message' => [
                'items' => [[
                    'title' => ['Faculty research mentoring practices'],
                    'author' => [['given' => 'Nora', 'family' => 'Garcia']],
                    'published-online' => ['date-parts' => [[2021]]],
                    'container-title' => ['Higher Education Studies'],
                    'DOI' => '10.9999/mentoring',
                    'URL' => 'https://doi.org/10.9999/mentoring',
                    'is-referenced-by-count' => 3,
                ]],
            ],
        ]),
        'api.openalex.org/works*' => Http::response(['results' => []]),
    ]);

    $researcher = User::factory()->create();
    $researcher->assignRole('faculty_researcher');

    $this->actingAs($researcher)
        ->postJson(route('research-support.literature-search'), [
            'query' => 'faculty research mentoring',
        ])
        ->assertOk()
        ->assertJsonCount(1, 'results')
        ->assertJsonPath('results.0.title', 'Faculty research mentoring practices')
        ->assertJsonPath('results.0.description', 'No description available from source.')
        ->assertJsonPath('failed_sources.0', 'Semantic Scholar')
        ->assertJsonPath('provider_notice', 'Showing 1 result from Crossref and OpenAlex. Semantic Scholar temporarily rate-limited this search.');
});

test('literature search reports unavailable when every provider fails', function () {
    Http::fake([
        'api.semanticscholar.org/graph/v1/paper/search*' => Http::response([], 500),
        'api.crossref.org/works*' => Http::response([], 503),
        'api.openalex.org/works*' => Http::response([], 500),
    ]);

    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');

    $this->actingAs($faculty)
        ->postJson(route('research-support.literature-search'), [
            'query' => 'faculty research mentoring',
        ])
        ->assertStatus(503)
        ->assertJsonPath('results', [])
        ->assertJsonPath('failed_sources.0', 'Semantic Scholar')
        ->assertJsonPath('failed_sources.1', 'Crossref')
        ->assertJsonPath('failed_sources.2', 'OpenAlex');
});

test('unauthorized roles cannot scrape conference listings', function () {
    Http::fake();

    Role::firstOrCreate(['name' => 'research_head']);
    $researchHead = User::factory()->create();
    $researchHead->assignRole('research_head');

    $this->actingAs($researchHead)
        ->postJson(route('research-support.conference-search'), [
            'query' => 'educational technology',
        ])
        ->assertForbidden();

    Http::assertNothingSent();
});

test('regular faculty cannot scrape conference listings before becoming faculty researchers', function () {
    Http::fake();

    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');

    $this->actingAs($faculty)
        ->postJson(route('research-support.conference-search'), [
            'query' => 'educational technology',
        ])
        ->assertForbidden();

    Http::assertNothingSent();
});

test('conference scraper query must be specific enough', function () {
    Http::fake();

    $faculty = User::factory()->create();
    $faculty->assignRole('faculty_researcher');

    $response = $this->actingAs($faculty)
        ->postJson(route('research-support.conference-search'), [
            'query' => 'ai',
        ]);

    expect($response->getStatusCode())->toBe(422)
        ->and($response->json('errors.query.0'))->toBe('The query field must be at least 3 characters.');

    Http::assertNothingSent();
});

test('faculty researchers can scrape conference listings for publication venues', function () {
    Http::fake([
        'www.wikicfp.com/cfp/servlet/tool.search*' => Http::response(<<<'HTML'
            <html>
                <body>
                    <table>
                        <tr>
                            <td><a href="/cfp/servlet/event.showcfp?eventid=123&copyownerid=456">ICET 2027: International Conference on Educational Technology Assessment</a></td>
                            <td>Where: Manila, Philippines When: Jul 21, 2027 Submission Deadline: Jan 15, 2027</td>
                        </tr>
                        <tr>
                            <td><a href="/cfp/servlet/event.showcfp?eventid=789">AIED 2027: Educational Technology and Artificial Intelligence in Education</a></td>
                            <td>Location: Singapore Event Date: Aug 11, 2027 Deadline: Feb 20, 2027</td>
                        </tr>
                        <tr>
                            <td><a href="/cfp/servlet/event.showcfp?eventid=999">NURSING 2027: Clinical Practice Symposium</a></td>
                            <td>Location: Tokyo, Japan Event Date: Sep 5, 2027 Deadline: Mar 12, 2027</td>
                        </tr>
                    </table>
                </body>
            </html>
            HTML),
    ]);

    $faculty = User::factory()->create();
    $faculty->assignRole('faculty_researcher');

    $this->actingAs($faculty)
        ->postJson(route('research-support.conference-search'), [
            'query' => 'educational technology assessment',
        ])
        ->assertOk()
        ->assertJsonCount(2, 'results')
        ->assertJsonPath('results.0.title', 'ICET 2027: International Conference on Educational Technology Assessment')
        ->assertJsonPath('results.0.location', 'Manila, Philippines')
        ->assertJsonPath('results.0.scope', 'local')
        ->assertJsonPath('results.0.scope_label', 'Local')
        ->assertJsonPath('results.0.relevance_score', 100)
        ->assertJsonPath('results.0.relevance_label', 'Highly relevant')
        ->assertJsonPath('results.0.matched_keywords.0', 'educational')
        ->assertJsonPath('results.0.matched_keywords.1', 'technology')
        ->assertJsonPath('results.0.matched_keywords.2', 'assessment')
        ->assertJsonPath('results.0.deadline', 'Jan 15, 2027')
        ->assertJsonPath('results.0.event_date', 'Jul 21, 2027')
        ->assertJsonPath('results.0.source', 'WikiCFP')
        ->assertJsonPath('results.0.url', 'http://www.wikicfp.com/cfp/servlet/event.showcfp?eventid=123&copyownerid=456')
        ->assertJsonPath('results.1.title', 'AIED 2027: Educational Technology and Artificial Intelligence in Education')
        ->assertJsonPath('results.1.location', 'Singapore')
        ->assertJsonPath('results.1.scope', 'international')
        ->assertJsonPath('results.1.scope_label', 'International')
        ->assertJsonPath('results.1.relevance_score', 67)
        ->assertJsonPath('results.1.deadline', 'Feb 20, 2027')
        ->assertJsonPath('results.1.event_date', 'Aug 11, 2027')
        ->assertJsonPath('results.1.source', 'WikiCFP')
        ->assertJsonMissing(['title' => 'NURSING 2027: Clinical Practice Symposium']);

    Http::assertSent(fn ($request) => str_starts_with($request->url(), 'http://www.wikicfp.com/cfp/servlet/tool.search')
        && str_contains(urldecode($request->url()), 'q=educational technology assessment')
        && str_contains($request->url(), 'year=t'));
});

test('conference scraper reports unavailable when the source fails', function () {
    Http::fake([
        'www.wikicfp.com/cfp/servlet/tool.search*' => Http::response('', 503),
    ]);

    $faculty = User::factory()->create();
    $faculty->assignRole('faculty_researcher');

    $this->actingAs($faculty)
        ->postJson(route('research-support.conference-search'), [
            'query' => 'educational technology assessment',
        ])
        ->assertStatus(503)
        ->assertJsonPath('results', [])
        ->assertJsonPath('failed_sources.0', 'WikiCFP');
});

test('chat requests require a final user message', function () {
    config(['services.gemini.key' => 'test-key']);

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

test('users can save, search, and reopen their assistant chat history', function () {
    $researcher = User::factory()->create();
    $researcher->assignRole('faculty');

    $saveResponse = $this->actingAs($researcher)
        ->postJson(route('research-support.history.save'), [
            'messages' => [
                ['role' => 'user', 'content' => 'How can I improve my mangrove sampling plan?', 'sources' => []],
                ['role' => 'assistant', 'content' => 'Define the population, sampling frame, and selection procedure.', 'sources' => []],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('conversation.title', 'How can I improve my mangrove sampling plan?');

    $conversationId = $saveResponse->json('conversation.id');

    $this->actingAs($researcher)
        ->getJson(route('research-support.history', ['query' => 'mangrove sampling']))
        ->assertOk()
        ->assertJsonPath('conversations.0.id', $conversationId)
        ->assertJsonPath('conversations.0.preview', 'How can I improve my mangrove sampling plan?');

    $this->actingAs($researcher)
        ->getJson(route('research-support.history.show', $conversationId))
        ->assertOk()
        ->assertJsonPath('conversation.messages.1.content', 'Define the population, sampling frame, and selection procedure.');
});

test('assistant history is private to its owner', function () {
    $owner = User::factory()->create();
    $owner->assignRole('faculty');
    $conversation = ResearchAssistantConversation::factory()->create([
        'user_id' => $owner->id,
        'title' => 'Private coastal study',
    ]);
    $otherUser = User::factory()->create();
    $otherUser->assignRole('faculty');

    $this->actingAs($otherUser)
        ->getJson(route('research-support.history'))
        ->assertOk()
        ->assertJsonCount(0, 'conversations');

    $this->actingAs($otherUser)
        ->getJson(route('research-support.history.show', $conversation))
        ->assertNotFound();
});

test('assistant launcher is rendered for every authenticated role', function (string $role) {
    $this->withoutVite();

    $user = User::factory()->create();
    $user->assignRole($role);

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('Open Athena AI research assistant')
        ->assertSee('aria-controls="research-assistant-panel"', false)
        ->assertSee('id="research-assistant-panel"', false)
        ->assertSee('$store.researchAssistant.toggleDrawer', false)
        ->assertSeeInOrder(['<body', 'x-data', 'data-app-shell'], false);
})->with(['faculty', 'faculty_researcher', 'research_head']);

test('guests cannot send assistant messages', function () {
    Http::fake();

    $this->postJson(route('research-support.chat'), [
        'messages' => [[
            'role' => 'user',
            'content' => 'Help me plan a study.',
        ]],
    ])->assertUnauthorized();

    Http::assertNothingSent();
});
