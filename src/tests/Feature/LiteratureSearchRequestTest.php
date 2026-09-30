<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    config(['literature.web_harvest.enabled' => false]);
    Http::preventStrayRequests();
    Role::firstOrCreate(['name' => 'faculty_researcher']);
    $this->researcher = User::factory()->create();
    $this->researcher->assignRole('faculty_researcher');
});

test('proposal search sends selected context to semantic retrieval while applying year and access filters', function () {
    config(['services.openalex.semantic_search' => true]);
    Http::fake([
        'api.semanticscholar.org/*' => Http::response(['data' => []]),
        'api.crossref.org/*' => Http::response(['message' => ['items' => []]]),
        'api.openalex.org/*' => Http::response(['results' => []]),
        'www.ebi.ac.uk/*' => Http::response(['resultList' => ['result' => []]]),
        'api.ies.ed.gov/*' => Http::response(['response' => ['docs' => []]]),
        'doaj.org/*' => Http::response(['results' => []]),
        'export.arxiv.org/*' => Http::response('<feed xmlns="http://www.w3.org/2005/Atom"/>'),
    ]);

    $this->actingAs($this->researcher)->postJson(route('research-support.literature-search'), [
        'query' => 'diabetes prevention',
        'context' => 'We evaluate community lifestyle interventions to prevent diabetes among rural adults.',
        'year_from' => 2022,
        'year_to' => 2026,
        'min_citations' => 5,
        'open_access' => true,
    ])->assertOk()->assertJsonPath('search_keywords', ['diabetes', 'prevention']);

    Http::assertSent(fn ($request): bool => isset($request['search.semantic'])
        && str_contains($request['search.semantic'], 'rural adults')
        && str_contains($request['filter'], 'publication_year:2022-2026')
        && str_contains($request['filter'], 'is_oa:true')
        && ! str_contains($request['filter'], 'cited_by_count')
        && ! isset($request['search'])
        && (int) $request['per_page'] === 50);
});

test('proposal search rejects oversized context and invalid filter ranges without calling indexes', function () {
    Http::fake();

    $this->actingAs($this->researcher)->postJson(route('research-support.literature-search'), [
        'query' => 'diabetes prevention',
        'context' => str_repeat('a', 6001),
        'year_from' => 2025,
        'year_to' => 2020,
    ])->assertUnprocessable()->assertJsonValidationErrors(['context', 'year_from']);

    Http::assertNothingSent();
});

test('proposal search rejects unauthorized research heads before contacting indexes', function () {
    Role::firstOrCreate(['name' => 'research_head']);
    $head = User::factory()->create();
    $head->assignRole('research_head');
    Http::fake();

    $this->actingAs($head)->postJson(route('research-support.literature-search'), [
        'query' => 'diabetes prevention',
        'context' => 'Community interventions for rural adults.',
    ])->assertForbidden();

    Http::assertNothingSent();
});
