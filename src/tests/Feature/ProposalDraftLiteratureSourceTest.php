<?php

use App\Models\LiteratureCollection;
use App\Models\LiteratureSource;
use App\Models\ProposalDraft;
use App\Models\ResearchCall;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'faculty']);
    Role::firstOrCreate(['name' => 'faculty_researcher']);

    $this->faculty = User::factory()->create(['name' => 'Source Library Owner']);
    $this->faculty->assignRole('faculty');
    $this->researchCall = ResearchCall::create([
        'title' => 'Literature Source Research Call',
        'academic_year' => '2026-2027',
        'opens_at' => now()->subDay(),
        'closes_at' => now()->addMonth(),
        'status' => 'open',
    ]);
    $this->draft = ProposalDraft::create([
        'user_id' => $this->faculty->id,
        'research_call_id' => $this->researchCall->id,
        'project_title' => 'Community Mangrove Monitoring',
        'duration_months' => 12,
        'planned_start' => now()->addMonth()->toDateString(),
        'planned_end' => now()->addMonths(13)->toDateString(),
        'project_leader' => $this->faculty->name,
    ]);
    $this->sourcePayload = [
        'title' => 'Community Participation in Mangrove Monitoring',
        'description' => 'Community participation improved the continuity of local monitoring activities.',
        'authors' => 'Maria Santos, Luis Cruz',
        'year' => 2024,
        'venue' => 'Journal of Coastal Research',
        'doi' => '10.1234/mangrove.2024',
        'url' => 'https://doi.org/10.1234/mangrove.2024',
        'source' => 'OpenAlex',
        'citation_count' => 18,
        'is_open_access' => true,
        'type' => 'article',
    ];

    $this->withoutVite();
});

test('faculty share one canonical literature record instead of creating duplicate papers', function () {
    $route = route('research-support.literature-library.store');

    $this->actingAs($this->faculty)
        ->postJson($route, $this->sourcePayload)
        ->assertCreated()
        ->assertJsonPath('already_saved', false)
        ->assertJsonPath('source.title', 'Community Participation in Mangrove Monitoring')
        ->assertJsonPath('source.added_by_name', 'Source Library Owner');

    $otherFaculty = User::factory()->create(['name' => 'Second Faculty']);
    $otherFaculty->assignRole('faculty');

    $this->actingAs($otherFaculty)
        ->postJson($route, [
            ...$this->sourcePayload,
            'doi' => 'https://doi.org/10.1234/MANGROVE.2024',
            'description' => null,
            'citation_count' => 21,
        ])
        ->assertOk()
        ->assertJsonPath('already_saved', true)
        ->assertJsonPath('source.added_by_name', 'Source Library Owner');

    expect(LiteratureSource::query()->count())->toBe(1)
        ->and(LiteratureSource::query()->sole()->citation_count)->toBe(21)
        ->and(LiteratureSource::query()->sole()->abstract)
        ->toBe('Community participation improved the continuity of local monitoring activities.');
});

test('faculty can search canonical shared literature records before linking one to a proposal', function () {
    $this->actingAs($this->faculty)
        ->postJson(route('research-support.literature-library.store'), $this->sourcePayload)
        ->assertCreated();

    $this->actingAs($this->faculty)
        ->getJson(route('research-support.literature-library.index', ['query' => 'mangrove']))
        ->assertOk()
        ->assertJsonCount(1, 'sources')
        ->assertJsonPath('sources.0.title', 'Community Participation in Mangrove Monitoring');
});

test('an externally found source is saved as a verifiable library record before it is used in a proposal', function () {
    $sourceId = $this->actingAs($this->faculty)
        ->postJson(route('research-support.literature-library.store'), [
            'title' => 'Library Automation Adoption in Higher Education',
            'authors' => 'Ada Reyes, Ben Cruz',
            'year' => 2025,
            'venue' => 'Journal of Computing Education',
            'doi' => '10.5555/library.automation.2025',
            'url' => 'https://doi.org/10.5555/library.automation.2025',
            'source' => 'External: Google Scholar',
            'type' => 'external record',
        ])
        ->assertCreated()
        ->assertJsonPath('source.source', 'External: Google Scholar')
        ->json('source.id');

    $source = LiteratureSource::query()->findOrFail($sourceId);

    $this->actingAs($this->faculty)
        ->postJson(route('faculty.proposal-drafts.literature-sources.store', [$this->draft, $source]))
        ->assertCreated()
        ->assertJsonPath('source.rrl_draft_status', 'none');

    expect($this->draft->literatureSources()->sole()->rrl_note)->toBeNull();
});

test('shared collections act like collaborative playlists', function () {
    $collectionId = $this->actingAs($this->faculty)
        ->postJson(route('research-support.literature-collections.store'), [
            'name' => 'Environment',
        ])
        ->assertCreated()
        ->assertJsonPath('already_exists', false)
        ->json('collection.id');

    $otherFaculty = User::factory()->create(['name' => 'Collection Collaborator']);
    $otherFaculty->assignRole('faculty');

    $this->actingAs($otherFaculty)
        ->postJson(route('research-support.literature-collections.store'), [
            'name' => 'environment',
        ])
        ->assertOk()
        ->assertJsonPath('already_exists', true);

    $this->actingAs($otherFaculty)
        ->postJson(route('research-support.literature-library.store'), [
            ...$this->sourcePayload,
            'collection_id' => $collectionId,
        ])
        ->assertCreated()
        ->assertJsonPath('source.collections.0.name', 'Environment');

    expect(LiteratureCollection::query()->count())->toBe(1)
        ->and(LiteratureCollection::query()->sole()->sources()->count())->toBe(1);
});

test('the same shared paper has distinct links in separate proposal drafts', function () {
    $sourceId = $this->actingAs($this->faculty)
        ->postJson(route('research-support.literature-library.store'), $this->sourcePayload)
        ->assertCreated()
        ->json('source.id');
    $source = LiteratureSource::query()->findOrFail($sourceId);
    $secondDraft = ProposalDraft::create([
        'user_id' => $this->faculty->id,
        'research_call_id' => $this->researchCall->id,
        'project_title' => 'A Separate Environmental Proposal',
        'duration_months' => 10,
        'planned_start' => now()->addMonths(2)->toDateString(),
        'planned_end' => now()->addYear()->toDateString(),
        'project_leader' => $this->faculty->name,
    ]);

    $firstLinkId = $this->actingAs($this->faculty)
        ->postJson(route('faculty.proposal-drafts.literature-sources.store', [$this->draft, $source]))
        ->assertCreated()
        ->assertJsonPath('already_linked', false)
        ->json('source.id');
    $secondLinkId = $this->actingAs($this->faculty)
        ->postJson(route('faculty.proposal-drafts.literature-sources.store', [$secondDraft, $source]))
        ->assertCreated()
        ->json('source.id');

    $this->actingAs($this->faculty)
        ->postJson(route('faculty.proposal-drafts.literature-sources.store', [$this->draft, $source]))
        ->assertOk()
        ->assertJsonPath('already_linked', true);

    $firstLink = $this->draft->literatureSources()->findOrFail($firstLinkId);
    $secondLink = $secondDraft->literatureSources()->findOrFail($secondLinkId);
    $firstLink->update(['rrl_note' => 'Draft-specific synthesis for the first proposal.']);

    expect(LiteratureSource::query()->count())->toBe(1)
        ->and($this->draft->literatureSources()->count())->toBe(1)
        ->and($secondDraft->literatureSources()->count())->toBe(1)
        ->and($firstLink->id)->not->toBe($secondLink->id)
        ->and($firstLink->literature_source_id)->toBe($secondLink->literature_source_id)
        ->and($secondLink->fresh()->rrl_note)->not->toBe('Draft-specific synthesis for the first proposal.');
});

test('proposal literature links retain the proposal context that led to the paper', function () {
    $sourceId = $this->actingAs($this->faculty)
        ->postJson(route('research-support.literature-library.store'), $this->sourcePayload)
        ->assertCreated()
        ->json('source.id');
    $source = LiteratureSource::query()->findOrFail($sourceId);

    $this->actingAs($this->faculty)
        ->postJson(route('faculty.proposal-drafts.literature-sources.store', [$this->draft, $source]), [
            'research_context' => ['Project title', 'Specific objectives'],
        ])
        ->assertCreated()
        ->assertJsonPath('source.research_context', ['Project title', 'Specific objectives']);

    expect($this->draft->literatureSources()->sole()->research_context)
        ->toBe(['Project title', 'Specific objectives']);
});

test('a reviewed rrl paragraph is stored only on its proposal link', function () {
    $sourceId = $this->actingAs($this->faculty)
        ->postJson(route('research-support.literature-library.store'), $this->sourcePayload)
        ->assertCreated()
        ->json('source.id');
    $source = LiteratureSource::query()->findOrFail($sourceId);
    $reviewedParagraph = 'Santos and Cruz (2024) examined community participation in mangrove monitoring and found that sustained local involvement supported more continuous monitoring activities. Their findings indicate that involving community members may strengthen the consistency of environmental observation programs.';

    $this->actingAs($this->faculty)
        ->postJson(route('faculty.proposal-drafts.literature-sources.store', [$this->draft, $source]), [
            'rrl_note' => $reviewedParagraph,
        ])
        ->assertCreated()
        ->assertJsonPath('source.rrl_note', $reviewedParagraph);

    expect($this->draft->literatureSources()->sole()->rrl_note)->toBe($reviewedParagraph)
        ->and($source->fresh()->rrlNoteDraft())->toBe('');
});

test('faculty can generate an abstract only rrl draft without retrieving full text', function () {
    config([
        'services.gemini.key' => 'test-key',
        'services.gemini.model' => 'gemini-test-model',
        'services.gemini.base_url' => 'https://generativelanguage.googleapis.com/v1beta/openai',
        'services.gemini.rrl_reasoning_effort' => 'low',
        'services.gemini.rrl_max_completion_tokens' => 2048,
        'services.gemini.rrl_retry_max_completion_tokens' => 4096,
    ]);

    $completeParagraph = 'Santos and Cruz examined how community participation influenced the continuity of mangrove monitoring activities. The study focused on local involvement in environmental observation and considered whether sustained participation helped monitoring efforts continue over time. The reported results showed that consistent community engagement was associated with more regular observation activities and stronger local stewardship. These findings suggest that monitoring programs may benefit when residents have continuing roles in collecting and maintaining environmental information. For a project concerned with community based resource management, the study provides relevant evidence that participation can support operational continuity and shared responsibility. However, the evidence describes an association rather than proving that participation alone caused the improved monitoring outcomes. The source therefore supports cautious consideration of participatory approaches when designing local monitoring processes, assigning responsibilities, and planning activities intended to remain active beyond initial implementation.';

    Http::fake([
        'generativelanguage.googleapis.com/v1beta/openai/chat/completions' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => "Draft: {$completeParagraph} DOI: 10.1234/paywalled-record https://example.test/full-paper",
                ],
                'finish_reason' => 'stop',
            ]],
        ]),
    ]);

    $abstract = 'This study examined how community participation influenced the continuity of mangrove monitoring activities. Results showed that sustained local involvement supported more consistent environmental observation and strengthened local stewardship.';

    $response = $this->actingAs($this->faculty)
        ->postJson(route('research-support.literature-synthesis'), [
            'title' => 'Community Participation in Mangrove Monitoring',
            'authors' => 'Maria Santos, Luis Cruz',
            'year' => 2024,
            'abstract' => $abstract,
            'is_open_access' => false,
        ])
        ->assertOk()
        ->assertJsonPath('basis', 'abstract')
        ->assertJsonPath('notice', 'Drafted only from the indexed abstract. No restricted or paywalled full text was accessed.')
        ->assertJsonMissingPath('doi')
        ->assertJsonMissingPath('url')
        ->assertJson(fn ($json) => $json
            ->whereType('synthesis', 'string')
            ->where('basis', 'abstract')
            ->etc());

    $synthesis = $response->json('synthesis');

    expect($synthesis)
        ->not->toContain('Draft:')
        ->not->toContain('10.1234')
        ->not->toContain('http');

    Http::assertSent(fn ($request) => $request->url() === 'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions'
        && $request['model'] === 'gemini-test-model'
        && $request['reasoning_effort'] === 'low'
        && $request['max_completion_tokens'] === 2048
        && ! isset($request['temperature'])
        && str_contains($request['messages'][0]['content'], 'Use only claims explicitly supported by the supplied evidence')
        && str_contains($request['messages'][1]['content'], $abstract));
    Http::assertSentCount(1);
});

test('rrl synthesis retries a token limited response with a larger completion budget', function () {
    config([
        'services.gemini.key' => 'test-key',
        'services.gemini.model' => 'gemini-3.5-flash',
        'services.gemini.base_url' => 'https://generativelanguage.googleapis.com/v1beta/openai',
        'services.gemini.rrl_reasoning_effort' => 'low',
        'services.gemini.rrl_max_completion_tokens' => 2048,
        'services.gemini.rrl_retry_max_completion_tokens' => 4096,
    ]);

    $completeParagraph = 'Santos and Cruz examined how community participation influenced the continuity of mangrove monitoring activities. The study focused on local involvement in environmental observation and considered whether sustained participation helped monitoring efforts continue over time. The reported results showed that consistent community engagement was associated with more regular observation activities and stronger local stewardship. These findings suggest that monitoring programs may benefit when residents have continuing roles in collecting and maintaining environmental information. For a project concerned with community based resource management, the study provides relevant evidence that participation can support operational continuity and shared responsibility. However, the evidence describes an association rather than proving that participation alone caused the improved monitoring outcomes. The source therefore supports cautious consideration of participatory approaches when designing local monitoring processes, assigning responsibilities, and planning activities intended to remain active beyond initial implementation.';

    Http::fake([
        'generativelanguage.googleapis.com/v1beta/openai/chat/completions' => Http::sequence()
            ->push([
                'choices' => [[
                    'message' => ['content' => 'Community participation was associated with stronger local monitoring continuity, but the response ended before completing its explanation'],
                    'finish_reason' => 'length',
                ]],
            ])
            ->push([
                'choices' => [[
                    'message' => ['content' => $completeParagraph],
                    'finish_reason' => 'stop',
                ]],
            ]),
    ]);

    $abstract = 'This study examined how community participation influenced the continuity of mangrove monitoring activities. Results showed that sustained local involvement supported more consistent environmental observation and strengthened local stewardship.';

    $this->actingAs($this->faculty)
        ->postJson(route('research-support.literature-synthesis'), [
            'title' => 'Community Participation in Mangrove Monitoring',
            'authors' => 'Maria Santos, Luis Cruz',
            'year' => 2024,
            'abstract' => $abstract,
            'is_open_access' => false,
        ])
        ->assertOk()
        ->assertJsonPath('synthesis', $completeParagraph)
        ->assertJsonPath('basis', 'abstract');

    $requests = Http::recorded()->map(fn (array $recording) => $recording[0]);

    expect($requests)->toHaveCount(2)
        ->and($requests[0]['reasoning_effort'])->toBe('low')
        ->and($requests[0]['max_completion_tokens'])->toBe(2048)
        ->and($requests[1]['reasoning_effort'])->toBe('low')
        ->and($requests[1]['max_completion_tokens'])->toBe(4096)
        ->and($requests[1]['messages'][1]['content'])->toContain('This is a retry. Ensure the paragraph is complete');
});

test('rrl synthesis refuses records without a usable abstract', function () {
    Http::fake();

    $response = $this->actingAs($this->faculty)
        ->postJson(route('research-support.literature-synthesis'), [
            'title' => 'Metadata Only Paper',
            'authors' => 'A. Researcher',
            'year' => 2025,
            'abstract' => 'No abstract available.',
            'is_open_access' => false,
        ]);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors('abstract');

    Http::assertNothingSent();
});

test('proposal collaborators can link shared papers but unrelated faculty cannot', function () {
    $sourceId = $this->actingAs($this->faculty)
        ->postJson(route('research-support.literature-library.store'), $this->sourcePayload)
        ->assertCreated()
        ->json('source.id');
    $source = LiteratureSource::query()->findOrFail($sourceId);
    $collaborator = User::factory()->create();
    $collaborator->assignRole('faculty');
    $this->draft->members()->create([
        'user_id' => $collaborator->id,
        'name' => $collaborator->name,
        'email' => $collaborator->email,
        'accepted_at' => now(),
    ]);

    $this->actingAs($collaborator)
        ->postJson(route('faculty.proposal-drafts.literature-sources.store', [$this->draft, $source]))
        ->assertCreated();

    $unrelatedFaculty = User::factory()->create();
    $unrelatedFaculty->assignRole('faculty');

    $this->actingAs($unrelatedFaculty)
        ->postJson(route('faculty.proposal-drafts.literature-sources.store', [$this->draft, $source]))
        ->assertForbidden();
});

test('the research support page exposes shared papers collections and optional proposal linking', function () {
    $collectionId = $this->actingAs($this->faculty)
        ->postJson(route('research-support.literature-collections.store'), ['name' => 'Environment'])
        ->assertCreated()
        ->json('collection.id');
    $this->actingAs($this->faculty)
        ->postJson(route('research-support.literature-library.store'), [
            ...$this->sourcePayload,
            'collection_id' => $collectionId,
        ])
        ->assertCreated();

    $this->actingAs($this->faculty)
        ->get(route('research-support.index'))
        ->assertOk()
        ->assertSee('Save the selected paper')
        ->assertSee('Browse the shared library')
        ->assertSee('Community Participation in Mangrove Monitoring')
        ->assertSee('Environment')
        ->assertSee('Use with proposal')
        ->assertSee('Create shared collection')
        ->assertSee('Prepare the RRL paragraph')
        ->assertSee('Abstract only')
        ->assertSee('Editable RRL paragraph')
        ->assertSee('data-literature-synthesis-url', false)
        ->assertSee('Section XI')
        ->assertSee('Section XVI')
        ->assertSee('Prepare RRL + reference')
        ->assertSee('data-literature-library-save-url', false)
        ->assertSee('data-literature-attach-url-template', false)
        ->assertDontSee('Proposal literature library')
        ->assertDontSee('Conference Finder');
});

test('only papers linked to a proposal appear in that detailed proposal', function () {
    $sourceId = $this->actingAs($this->faculty)
        ->postJson(route('research-support.literature-library.store'), $this->sourcePayload)
        ->assertCreated()
        ->json('source.id');
    $source = LiteratureSource::query()->findOrFail($sourceId);

    $this->actingAs($this->faculty)
        ->get(route('faculty.proposal-drafts.detailed-proposal.edit', $this->draft))
        ->assertOk()
        ->assertDontSee('Community Participation in Mangrove Monitoring');

    $this->actingAs($this->faculty)
        ->postJson(route('faculty.proposal-drafts.literature-sources.store', [$this->draft, $source]))
        ->assertCreated();

    $this->actingAs($this->faculty)
        ->get(route('faculty.proposal-drafts.detailed-proposal.edit', $this->draft))
        ->assertOk()
        ->assertSee('Literature linked to this proposal')
        ->assertSee('Literature Assistant')
        ->assertSee('Search related literature')
        ->assertSee('Community Participation in Mangrove Monitoring')
        ->assertSee('Review RRL')
        ->assertSee('Add to Section XVI')
        ->assertSee('Reference options')
        ->assertDontSee('Use both');
});

test('a shared paper can be staged directly in the selected detailed proposal', function () {
    $sourceId = $this->actingAs($this->faculty)
        ->postJson(route('research-support.literature-library.store'), $this->sourcePayload)
        ->assertCreated()
        ->json('source.id');
    $source = LiteratureSource::query()->findOrFail($sourceId);
    $proposalLinkId = $this->actingAs($this->faculty)
        ->postJson(route('faculty.proposal-drafts.literature-sources.store', [$this->draft, $source]))
        ->assertCreated()
        ->json('source.id');

    $url = route('faculty.proposal-drafts.detailed-proposal.edit', $this->draft).'?'.http_build_query([
        'literature_source' => $proposalLinkId,
        'apply_to' => 'both',
    ]);

    $response = $this->actingAs($this->faculty)
        ->get($url)
        ->assertOk()
        ->assertViewHas('initialLiteratureSourceId', $proposalLinkId)
        ->assertViewHas('initialLiteratureAction', 'both')
        ->assertSee('Community Participation in Mangrove Monitoring');

    expect($response->viewData('literatureSources'))->toHaveCount(1);
});

test('staged literature actions focus the matching detailed proposal field', function () {
    $appJavaScript = file_get_contents(resource_path('js/app.js'));

    expect($appJavaScript)
        ->toContain('this.focusLiteratureDestination(action);')
        ->toContain("const fieldId = action === 'reference' ? 'references' : 'related-literature';")
        ->toContain('const focusTarget = field._semanticEditor instanceof HTMLElement ? field._semanticEditor : field;')
        ->toContain("const destination = focusTarget.closest('section') || focusTarget;")
        ->toContain('const destinationTop = destination.getBoundingClientRect().top + window.scrollY - 144;')
        ->toContain("window.scrollTo({ top: Math.max(0, destinationTop), behavior: 'smooth' });")
        ->toContain('focusTarget.focus({ preventScroll: true });');
});
