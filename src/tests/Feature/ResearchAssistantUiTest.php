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
        ->assertSee('RRL Finder')
        ->assertSee('Search related literature')
        ->assertSee('Semantic Scholar + Crossref + OpenAlex')
        ->assertSee('From year')
        ->assertSee('Open access only')
        ->assertSee('Ask Athena about results')
        ->assertSee('Grounded assistance:')
        ->assertSee('Matching approved ATHENA knowledge')
        ->assertSee('Grounded with ATHENA knowledge')
        ->assertSee('Conference Finder')
        ->assertSee('HTML scraping')
        ->assertSee('Relevance ranked')
        ->assertSee('Find conferences for publication')
        ->assertSee('local and international venues')
        ->assertSee('Scraped source: WikiCFP')
        ->assertSee('Connecting to the conference source')
        ->assertSee('Reading public listings')
        ->assertSee('This can take a few seconds')
        ->assertSee('Ask Athena')
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
        ->assertJsonPath('results.1.title', 'Participatory Coastal Resource Monitoring')
        ->assertJsonPath('results.1.description', 'Local communities can improve coastal resource monitoring when protocols are simple and repeatable.')
        ->assertJsonPath('results.1.authors', 'Ana Reyes, Mark Dela Cruz')
        ->assertJsonPath('results.1.year', 2022)
        ->assertJsonPath('results.1.venue', 'Journal of Coastal Research')
        ->assertJsonPath('results.1.doi', '10.5678/coastal')
        ->assertJsonPath('results.1.source', 'Crossref')
        ->assertJsonPath('results.1.citation_count', 7)
        ->assertJsonPath('results.2.title', 'OpenAlex Records for Community Monitoring')
        ->assertJsonPath('results.2.description', 'OpenAlex indexes community monitoring studies')
        ->assertJsonPath('results.2.authors', 'Joanna Lee, Rafael Torres')
        ->assertJsonPath('results.2.year', 2023)
        ->assertJsonPath('results.2.venue', 'Open Research Index')
        ->assertJsonPath('results.2.doi', '10.2468/openalex')
        ->assertJsonPath('results.2.source', 'OpenAlex')
        ->assertJsonPath('results.2.citation_count', 25)
        ->assertJsonPath('results.2.is_open_access', true)
        ->assertJsonPath('results.2.type', 'article');

    Http::assertSentCount(3);
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
        'api.semanticscholar.org/graph/v1/paper/search*' => Http::response([], 500),
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
        ->assertJsonPath('failed_sources.0', 'Semantic Scholar');
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

test('conference scraper query must be specific enough', function () {
    Http::fake();

    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');

    $response = $this->actingAs($faculty)
        ->postJson(route('research-support.conference-search'), [
            'query' => 'ai',
        ]);

    expect($response->getStatusCode())->toBe(422)
        ->and($response->json('errors.query.0'))->toBe('The query field must be at least 3 characters.');

    Http::assertNothingSent();
});

test('faculty can scrape conference listings for publication venues', function () {
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
    $faculty->assignRole('faculty');

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
    $faculty->assignRole('faculty');

    $this->actingAs($faculty)
        ->postJson(route('research-support.conference-search'), [
            'query' => 'educational technology assessment',
        ])
        ->assertStatus(503)
        ->assertJsonPath('results', [])
        ->assertJsonPath('failed_sources.0', 'WikiCFP');
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
