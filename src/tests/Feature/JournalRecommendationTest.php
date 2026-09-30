<?php

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Http::preventStrayRequests();
    Cache::flush();
    config([
        'services.scopus.key' => null, 'services.openalex.semantic_search' => true,
        'services.scopus.source_list_path' => storage_path('framework/testing/no-scopus-source-list.json'),
    ]);
    Role::firstOrCreate(['name' => 'faculty_researcher']);
    $user = User::factory()->create();
    $user->assignRole('faculty_researcher');
    $this->actingAs($user)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => 'faculty_researcher']);
});

function healthJournalWork(string $id, string $source = 'S101', string $issn = '1234-5678', string $title = 'Diabetes prevention in community health'): array
{
    return [
        'id' => 'https://openalex.org/'.$id, 'display_name' => $title, 'type' => 'article',
        'publication_year' => now()->year - 1, 'doi' => 'https://doi.org/10.1234/'.$id,
        'topics' => [['display_name' => 'Public health and diabetes']],
        'primary_location' => ['source' => [
            'id' => 'https://openalex.org/'.$source, 'display_name' => 'Health Journal '.$source,
            'type' => 'journal', 'issn_l' => $issn, 'works_count' => 200, 'is_oa' => true,
            'homepage_url' => 'https://example.org/'.$source,
        ]],
    ];
}

test('abstract-only searches use short keyword and semantic queries and deduplicate article evidence', function () {
    $abstract = 'This study investigates diabetes prevention in community health. Diabetes screening improves community health through education and nutrition.';
    Http::fake(['api.openalex.org/works*' => Http::response(['results' => [healthJournalWork('W101'), healthJournalWork('W102')]])]);
    $response = $this->postJson(route('research-support.journal-search'), ['context' => $abstract]);
    $response->assertOk()->assertJsonPath('related_articles', 2)->assertJsonPath('results.0.evidence_count', 2)
        ->assertJsonPath('results.0.scopus.status', 'unverified')->assertJsonPath('search_count', 3);
    Http::assertSent(fn ($request): bool => isset($request['search.semantic']) && $request['search.semantic'] === $abstract);
    Http::assertSent(fn ($request): bool => isset($request['search']) && strlen($request['search']) < strlen($abstract) && str_contains($request['search'], 'diabetes'));
    Http::assertSentCount(3);
    $this->postJson(route('research-support.journal-search'), ['context' => $abstract])->assertOk();
    Http::assertSentCount(3);
});

test('irrelevant popular journals and unsafe links are excluded from health recommendations', function () {
    $irrelevant = healthJournalWork('W200', 'S200', title: 'Mining and mechanical engineering');
    $irrelevant['topics'] = [['display_name' => 'Mineral extraction']];
    $irrelevant['primary_location']['source']['works_count'] = 9999999;
    $relevant = healthJournalWork('W101');
    $relevant['primary_location']['source']['homepage_url'] = 'javascript:alert(1)';
    $relevant['doi'] = 'javascript:alert(1)';
    Http::fake([
        'api.openalex.org/works*' => Http::response(['results' => [$irrelevant, $relevant]]),
        'api.openalex.org/sources*' => Http::response(['results' => [['id' => 'https://openalex.org/S101', 'homepage_url' => 'javascript:alert(1)']]]),
    ]);
    $this->postJson(route('research-support.journal-search'), ['query' => 'diabetes community health'])
        ->assertOk()->assertJsonCount(1, 'results')->assertJsonPath('results.0.id', 'S101')
        ->assertJsonPath('results.0.homepage_url', null)->assertJsonPath('results.0.sample_articles.0.url', null);
});

test('full journal metadata supplies official website links missing from article records', function () {
    $work = healthJournalWork('W101');
    unset($work['primary_location']['source']['homepage_url']);
    Http::fake([
        'api.openalex.org/works*' => Http::response(['results' => [$work]]),
        'api.openalex.org/sources*' => Http::response(['results' => [
            ['id' => 'https://openalex.org/S101', 'homepage_url' => 'https://journal.example.org/submit', 'works_count' => 500],
        ]]),
    ]);
    $this->postJson(route('research-support.journal-search'), ['query' => 'diabetes health'])
        ->assertOk()->assertJsonPath('results.0.homepage_url', 'https://journal.example.org/submit');
});

test('a partial provider failure keeps usable evidence and reports incomplete searching', function () {
    Http::fake(fn ($request) => isset($request['search.semantic'])
        ? Http::response([], 503)
        : Http::response(['results' => [healthJournalWork('W101')]]));
    $this->postJson(route('research-support.journal-search'), ['context' => 'Diabetes prevention and community health through local nutrition programs.'])
        ->assertOk()->assertJsonCount(1, 'results')->assertJsonPath('warnings.0', 'Some article searches were unavailable. These matches use the searches that completed.');
});

test('a complete provider outage is not reported as an empty successful search', function () {
    Http::fake(['api.openalex.org/works*' => Http::response([], 429)]);
    $this->postJson(route('research-support.journal-search'), ['query' => 'diabetes health'])
        ->assertUnprocessable()->assertJsonValidationErrors('journal_search');
});

test('Scopus preferences require a matching official ISSN record and never treat a publisher as indexing proof', function () {
    config(['services.scopus.key' => 'test-key']);
    $works = [
        healthJournalWork('W101', 'S101', '1111-1111'),
        healthJournalWork('W102', 'S102', '2222-2222'),
        healthJournalWork('W103', 'S102', '2222-2222'),
    ];
    Http::fake([
        'api.openalex.org/works*' => Http::response(['results' => $works]),
        'api.elsevier.com/content/serial/title/issn/1111-1111*' => Http::response(['serial-metadata-response' => ['entry' => [[
            'prism:issn' => '11111111', 'prism:aggregationType' => 'journal', 'source-id' => '12345',
        ]]]]),
        'api.elsevier.com/content/serial/title/issn/2222-2222*' => Http::response(['serial-metadata-response' => ['entry' => [[
            'prism:issn' => '9999-9999', 'prism:aggregationType' => 'journal', 'source-id' => '99999',
        ]]]]),
    ]);
    $this->postJson(route('research-support.journal-search'), ['query' => 'diabetes health', 'indexing' => 'prefer_scopus'])
        ->assertOk()->assertJsonPath('results.0.id', 'S101')->assertJsonPath('results.0.scopus.status', 'listed')
        ->assertJsonPath('results.0.scopus.url', 'https://www.scopus.com/sourceid/12345')
        ->assertJsonPath('results.1.scopus.status', 'not_found');
    $this->postJson(route('research-support.journal-search'), ['query' => 'diabetes health', 'indexing' => 'scopus_only'])
        ->assertOk()->assertJsonCount(1, 'results')->assertJsonPath('results.0.id', 'S101');
    Http::assertSent(fn ($request): bool => str_contains($request->url(), 'api.elsevier.com') && $request->hasHeader('X-ELS-APIKey', 'test-key'));
    Http::assertSentCount(4);
});

test('Scopus only search reports missing credentials and provider outages honestly', function () {
    $this->postJson(route('research-support.journal-search'), ['query' => 'diabetes health', 'indexing' => 'scopus_only'])
        ->assertUnprocessable()->assertJsonValidationErrors('indexing');
    Http::assertNothingSent();
    config(['services.scopus.key' => 'test-key']);
    Http::fake([
        'api.openalex.org/works*' => Http::response(['results' => [healthJournalWork('W101')]]),
        'api.elsevier.com/*' => Http::response([], 403),
    ]);
    $this->postJson(route('research-support.journal-search'), ['query' => 'diabetes health', 'indexing' => 'scopus_only'])
        ->assertUnprocessable()->assertJsonValidationErrors('indexing');
});

test('journal search validates inputs and distinguishes open access from article access', function () {
    $this->postJson(route('research-support.journal-search'), [])->assertUnprocessable()->assertJsonValidationErrors(['query', 'context']);
    $this->postJson(route('research-support.journal-search'), ['query' => 'health', 'indexing' => 'fake'])->assertJsonValidationErrors('indexing');
    $closed = healthJournalWork('W102', 'S102');
    $closed['primary_location']['source']['is_oa'] = false;
    $closed['open_access'] = ['is_oa' => true];
    Http::fake(['api.openalex.org/works*' => Http::response(['results' => [healthJournalWork('W101'), $closed]])]);
    $this->postJson(route('research-support.journal-search'), ['query' => 'diabetes health', 'open_access' => true])
        ->assertOk()->assertJsonCount(1, 'results')->assertJsonPath('results.0.id', 'S101');
});
