<?php

use App\Models\ProjectConference;
use App\Models\ProjectJournalSubmission;
use App\Models\ResearcherProfile;
use App\Models\ResearchPublication;
use App\Models\TopicCollaborator;
use App\Models\TopicProposal;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Http::preventStrayRequests();
    Cache::flush();
    foreach (['faculty_researcher', 'research_head'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }
    $this->researcher = User::factory()->create();
    $this->researcher->assignRole('faculty_researcher');
    $this->topic = TopicProposal::create([
        'user_id' => $this->researcher->id, 'title' => 'Coastal monitoring study',
        'status' => 'approved', 'project_status' => 'completed',
        'notice_to_proceed_issued_at' => now()->subYear(),
    ]);
    $this->actingAs($this->researcher)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => 'faculty_researcher']);
});

function disseminationWork(string $author = 'A123'): array
{
    return [
        'id' => 'https://openalex.org/W456', 'display_name' => 'Coastal monitoring findings',
        'doi' => 'https://doi.org/10.1234/coastal', 'publication_year' => 2026,
        'type' => 'article', 'primary_location' => ['source' => ['display_name' => 'Coastal Research']],
        'authorships' => [['author' => ['id' => 'https://openalex.org/'.$author, 'display_name' => 'Researcher']]],
    ];
}

function journalRecommendationWork(
    string $workId,
    string $title,
    string $sourceId,
    string $journal,
    bool $openAccess = false,
): array {
    return [
        'id' => 'https://openalex.org/'.$workId,
        'display_name' => $title,
        'publication_year' => now()->year - 1,
        'type' => 'article',
        'doi' => 'https://doi.org/10.1234/'.strtolower($workId),
        'topics' => [['display_name' => 'Coastal monitoring']],
        'primary_location' => [
            'landing_page_url' => 'https://example.org/articles/'.$workId,
            'source' => [
                'id' => 'https://openalex.org/'.$sourceId,
                'display_name' => $journal,
                'type' => 'journal',
                'host_organization_name' => 'Coastal Science Press',
                'issn_l' => '1234-5678',
                'is_oa' => $openAccess,
                'is_in_doaj' => $openAccess,
                'works_count' => 850,
                'cited_by_count' => 4200,
                'homepage_url' => 'https://journals.example.org/'.$sourceId,
            ],
        ],
    ];
}

test('completed projects retain dissemination access and preserve archived monitoring', function () {
    $this->get(route('research.dissemination.show', $this->topic))
        ->assertOk()
        ->assertSee('Journal Finder')
        ->assertSee('Journal submission tracker')
        ->assertDontSee('Add a missing publication manually')
        ->assertSee('Add to project shortlist')
        ->assertSee('id="conferences"', false)
        ->assertSee('Research reporting is complete');
    $this->post(route('research.dissemination.conferences.store', $this->topic), [
        'title' => 'Coastal Science 2027', 'url' => 'https://example.org/coastal-2027', 'status' => 'shortlisted',
    ])->assertSessionHasNoErrors();
    $conference = ProjectConference::firstOrFail();
    $this->patch(route('research.dissemination.conferences.update', [$this->topic, $conference]), [
        'title' => $conference->title, 'url' => $conference->url, 'status' => 'presented',
        'submitted_on' => now()->subDays(10)->toDateString(), 'accepted_on' => now()->subDays(5)->toDateString(),
        'presented_on' => now()->toDateString(),
    ])->assertSessionHasNoErrors();
    expect($conference->fresh()->status)->toBe('presented')
        ->and($this->topic->fresh()->project_status)->toBe('completed')
        ->and($this->topic->fresh()->isMonitoringAvailable())->toBeFalse()
        ->and(ResearchPublication::count())->toBe(0);
    $this->post(route('project-progress.store', $this->topic))->assertForbidden();
});

test('journal finder ranks venues from related articles without creating publication records', function () {
    Http::fake([
        'api.openalex.org/works*' => Http::response([
            'results' => [
                journalRecommendationWork('W101', 'Community coastal monitoring systems', 'S100', 'Journal of Coastal Research', true),
                journalRecommendationWork('W102', 'Coastal water quality monitoring', 'S100', 'Journal of Coastal Research', true),
                journalRecommendationWork('W103', 'Citizen science for coastal communities', 'S200', 'Marine Community Studies'),
            ],
        ]),
    ]);

    $response = $this->postJson(route('research.dissemination.journals.search', $this->topic), [
        'query' => 'community coastal monitoring',
        'context' => 'Citizen science and coastal water quality',
        'open_access' => false,
        'recent_years' => 5,
    ]);

    $response->assertOk()
        ->assertJsonPath('results.0.name', 'Journal of Coastal Research')
        ->assertJsonPath('results.0.evidence_count', 2)
        ->assertJsonPath('results.0.is_in_doaj', true)
        ->assertJsonPath('source', 'OpenAlex');

    $this->postJson(route('research-support.journal-search'), [
        'query' => 'community coastal monitoring',
        'context' => 'Citizen science and coastal water quality',
        'open_access' => false,
        'recent_years' => 5,
    ])->assertOk()->assertJsonPath('results.0.name', 'Journal of Coastal Research');

    expect(ResearchPublication::count())->toBe(0)
        ->and(ProjectConference::count())->toBe(0);

    Http::assertSent(fn ($request): bool => str_starts_with($request->url(), 'https://api.openalex.org/works')
        && str_contains((string) $request['filter'], 'type:article')
        && isset($request['search'])
        && str_contains((string) $request['search'], 'community coastal monitoring'));
});

test('dissemination prevents unrelated users cross-project writes and head access', function () {
    $conference = ProjectConference::factory()->create(['topic_id' => $this->topic->id, 'added_by' => $this->researcher->id]);
    $second = TopicProposal::create(['user_id' => $this->researcher->id, 'title' => 'Other project', 'status' => 'approved', 'project_status' => 'completed']);
    $this->patch(route('research.dissemination.conferences.update', [$second, $conference]), [])->assertForbidden();
    $outsider = User::factory()->create();
    $outsider->assignRole('faculty_researcher');
    $this->actingAs($outsider)->get(route('research.dissemination.show', $this->topic))->assertForbidden();
    $this->postJson(route('research.dissemination.journals.search', $this->topic), ['query' => 'coastal monitoring'])->assertForbidden();
    $this->postJson(route('research.dissemination.authors', $this->topic), ['query' => 'Researcher'])->assertForbidden();
    $head = User::factory()->create();
    $head->assignRole('research_head');
    $this->actingAs($head)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => 'research_head'])
        ->get(route('research.dissemination.show', $this->topic))->assertForbidden();
    $this->post(route('research.dissemination.conferences.store', $this->topic), [])->assertForbidden();
});

test('journal finder is hidden and forbidden in office workspaces even for the project owner', function (string $role, string $workspace) {
    Role::firstOrCreate(['name' => $role]);
    $this->researcher->assignRole($role);
    $this->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => $workspace]);

    if ($workspace === User::WORKSPACE_RESEARCH_HEAD) {
        $this->get(route('topics.show', $this->topic))->assertOk()->assertDontSee('Find journals');
    }

    $this->get(route('research-support.index'))->assertForbidden()->assertDontSee('Journal Finder');
    $this->get(route('research.dissemination.show', $this->topic))->assertForbidden();
    $this->postJson(route('research.dissemination.journals.search', $this->topic), ['query' => 'coastal monitoring'])->assertForbidden();
    $this->postJson(route('research-support.journal-search'), ['query' => 'coastal monitoring'])->assertForbidden();
    Http::assertNothingSent();

    $this->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY_RESEARCHER]);
    $this->get(route('topics.show', $this->topic))->assertOk()->assertSee('Find journals');
    $this->get(route('research.dissemination.show', $this->topic))->assertOk()->assertSee('Journal Finder');
})->with([
    'Research Head' => ['research_head', User::WORKSPACE_RESEARCH_HEAD],
    'Research Office' => ['research_coordinator', User::WORKSPACE_RESEARCH_OFFICE],
]);

test('authorized researchers retain journal discovery during monitoring and after completion', function (string $status) {
    $this->topic->update(['project_status' => $status]);
    $this->get(route('topics.show', $this->topic))->assertOk()->assertSee('Find journals');
    $this->get(route('research.dissemination.show', $this->topic))->assertOk()->assertSee('Journal Finder');
})->with(['ongoing', 'delayed', 'completed']);

test('shared journal finder retains manuscript context and project submission tracking without external requests', function () {
    $this->topic->narrativeReports()->create([
        'submitted_by' => $this->researcher->id,
        'report_type' => 'terminal',
        'submission_date' => now(),
        'researchers' => $this->researcher->name,
        'implementation_start' => now()->subYear()->toDateString(),
        'implementation_end' => now()->toDateString(),
        'budget' => 10000,
        'funding_agency' => 'University',
        'accomplishment_summary' => 'Monitoring completed.',
        'introduction' => 'Community coastal monitoring.',
        'objectives' => 'Assess water quality.',
        'methodology' => 'Community sampling.',
        'results_discussion' => 'Monitoring improved.',
        'photos' => [],
        'terminal_data' => ['abstract' => '<p>Community-led coastal water quality monitoring.</p>'],
    ]);
    $submission = ProjectJournalSubmission::factory()->create([
        'topic_id' => $this->topic->id,
        'added_by' => $this->researcher->id,
        'journal_name' => 'Coastal Science Journal',
        'manuscript_title' => $this->topic->title,
        'status' => 'shortlisted',
    ]);

    $this->get(route('research-support.index', ['topic' => $this->topic->id]))
        ->assertOk()
        ->assertViewHas('journalTopic', fn (?TopicProposal $topic): bool => $topic?->is($this->topic) ?? false)
        ->assertViewHas('projectAbstract', 'Community-led coastal water quality monitoring.')
        ->assertSee($this->topic->title)
        ->assertSee(route('research.dissemination.journal-submissions.store', $this->topic))
        ->assertSee(route('research.dissemination.journal-submissions.update', [$this->topic, $submission]))
        ->assertSee('Track this journal')
        ->assertSee('href="#journal-submissions"', false)
        ->assertSee(route('research.dissemination.show', $this->topic).'#conferences')
        ->assertSee('Coastal Science Journal');

    Http::assertNothingSent();
});

test('shared journal finder denies unrelated or ineligible project context', function () {
    $outsider = User::factory()->create();
    $outsider->assignRole('faculty_researcher');
    $this->actingAs($outsider)
        ->get(route('research-support.index', ['topic' => $this->topic->id]))
        ->assertForbidden();

    $this->actingAs($this->researcher);
    $this->topic->update(['status' => 'pending', 'project_status' => 'not_started', 'notice_to_proceed_issued_at' => null]);
    $this->get(route('research-support.index', ['topic' => $this->topic->id]))->assertForbidden();
    $this->get(route('research-support.index', ['topic' => ['invalid']]))->assertRedirect()->assertSessionHasErrors('topic');
    Http::assertNothingSent();
});

test('the generic sidebar journal finder remains available without project context', function () {
    $this->get(route('research-support.index'))
        ->assertOk()
        ->assertViewHas('journalTopic', null)
        ->assertSee('Recommend journals')
        ->assertDontSee('Journal submission tracker');
    Http::assertNothingSent();
});

test('conference validation errors do not open or label the journal submission form', function () {
    $url = route('research.dissemination.show', $this->topic);
    $this->from($url)->post(route('research.dissemination.conferences.store', $this->topic), [
        'conference_id' => 'new', 'status' => 'accepted', 'url' => 'https://example.org/conference',
    ])->assertRedirect($url)->assertSessionHasErrors('title');

    $response = $this->get($url)->assertOk();
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    $tracker = $xpath->query('//section[@id="journal-submissions"]')->item(0);
    $stage = $xpath->query('//section[@id="journal-submissions"]//select[@name="status"]/option[@selected]')->item(0);

    expect($tracker->textContent)->not->toContain('Please correct these details:');
    expect($stage->getAttribute('value'))->toBe('shortlisted');
});

test('project journal discovery requires an accepted collaboration', function () {
    $collaborator = User::factory()->create();
    $collaborator->assignRole('faculty_researcher');
    $invitation = TopicCollaborator::factory()->create([
        'topic_id' => $this->topic->id,
        'user_id' => $collaborator->id,
        'email' => $collaborator->email,
    ]);
    $this->actingAs($collaborator);
    $this->get(route('research.dissemination.show', $this->topic))->assertForbidden();
    $this->postJson(route('research.dissemination.journals.search', $this->topic), ['query' => 'coastal monitoring'])->assertForbidden();
    Http::assertNothingSent();

    $invitation->update(['accepted_at' => now()]);
    Http::fake(['api.openalex.org/works*' => Http::response(['results' => []])]);
    $this->get(route('topics.show', $this->topic))->assertOk()->assertSee('Find journals');
    $this->get(route('research.dissemination.show', $this->topic))->assertOk()->assertSee('Journal Finder');
    $this->postJson(route('research.dissemination.journals.search', $this->topic), ['query' => 'coastal monitoring', 'indexing' => 'any'])->assertOk();
});

test('author confirmation and import use canonical records and prevent duplicate imports', function () {
    Http::fake([
        'api.openalex.org/authors/A123*' => Http::response(['id' => 'https://openalex.org/A123', 'display_name' => 'Researcher', 'last_known_institutions' => [['display_name' => 'Batangas State University']]]),
        'api.openalex.org/works/W456*' => Http::response(disseminationWork()),
        'api.openalex.org/works?*' => Http::response(['results' => [disseminationWork()], 'meta' => ['count' => 1]]),
    ]);
    $this->postJson(route('research.dissemination.profile', $this->topic), ['author_id' => 'A123', 'confirmed' => false])->assertUnprocessable();
    expect(ResearcherProfile::count())->toBe(0);
    $this->postJson(route('research.dissemination.profile', $this->topic), ['author_id' => 'A123', 'confirmed' => true])->assertOk();
    $this->postJson(route('research.dissemination.papers', $this->topic), [])->assertOk()->assertJsonPath('results.0.title', 'Coastal monitoring findings');
    expect(ResearchPublication::count())->toBe(0);
    $this->postJson(route('research.dissemination.import', $this->topic), ['work_id' => 'W456', 'confirmed' => false])->assertUnprocessable();
    foreach (range(1, 2) as $attempt) {
        $this->postJson(route('research.dissemination.import', $this->topic), ['work_id' => 'W456', 'confirmed' => true, 'title' => 'Forged title'])
            ->assertOk();
    }
    expect(ResearchPublication::count())->toBe(1)
        ->and($this->topic->publications()->count())->toBe(1)
        ->and(ResearchPublication::first()->title)->toBe('Coastal monitoring findings');
    Http::assertSentCount(3);
});

test('papers from another author cannot be silently imported and provider failures remain errors', function () {
    ResearcherProfile::factory()->create(['user_id' => $this->researcher->id, 'openalex_id' => 'A123']);
    Http::fake(['api.openalex.org/works/W456*' => Http::response(disseminationWork('A999'))]);
    $this->postJson(route('research.dissemination.import', $this->topic), ['work_id' => 'W456', 'confirmed' => true])->assertUnprocessable();
    expect(ResearchPublication::count())->toBe(0);
    Http::fake(['api.openalex.org/authors?*' => Http::response([], 429), 'www.wikicfp.com/*' => Http::response('', 503)]);
    $this->postJson(route('research.dissemination.authors', $this->topic), ['query' => 'Researcher'])->assertUnprocessable()->assertJsonValidationErrors('discovery');
    $this->postJson(route('research.dissemination.conferences.search', $this->topic), ['query' => 'coastal'])->assertStatus(503);
});

test('DOI lookup supports confirmed imports and manual entries deduplicate without losing project links', function () {
    Http::fake(['api.openalex.org/works?*' => Http::response(['results' => [disseminationWork()], 'meta' => ['count' => 1]])]);
    $this->postJson(route('research.dissemination.doi', $this->topic), ['doi' => 'https://doi.org/10.1234/COASTAL'])->assertOk()->assertJsonPath('paper.doi', '10.1234/coastal');
    $this->postJson(route('research.dissemination.import', $this->topic), ['work_id' => 'W456', 'lookup_doi' => '10.1234/coastal', 'confirmed' => true])->assertOk();
    $this->post(route('research.dissemination.manual', $this->topic), [
        'title' => 'Same paper', 'authors' => 'Researcher', 'year' => 2026, 'type' => 'journal-article',
        'doi' => 'https://doi.org/10.1234/COASTAL', 'confirmed' => true,
    ])->assertSessionHasNoErrors();
    expect(ResearchPublication::count())->toBe(1);
    $publication = ResearchPublication::firstOrFail();
    $other = TopicProposal::create(['user_id' => $this->researcher->id, 'title' => 'Related research', 'status' => 'approved', 'project_status' => 'completed']);
    $this->post(route('research.dissemination.link', $other), ['publication_id' => $publication->id])->assertSessionHasNoErrors();
    $this->delete(route('research.dissemination.unlink', [$this->topic, $publication]))->assertSessionHasNoErrors();
    expect($this->topic->publications()->count())->toBe(0)->and($other->publications()->count())->toBe(1);
    $this->assertModelExists($publication);
});

test('conference discovery parses real table rows keeps deadlines distinct and saves source snapshots', function () {
    Http::fake(['www.wikicfp.com/*' => Http::response(<<<'HTML'
        <table>
        <tr><td><a href="/cfp/servlet/event.showcfp?eventid=123">COAST 2099</a></td><td>Coastal monitoring conference</td></tr>
        <tr><td>Jul 21, 2099 - Jul 22, 2099</td><td>Manila, Philippines</td><td>May 15, 2099</td></tr>
        <tr><td><a href="/cfp/servlet/event.showcfp?eventid=124">Coastal monitoring workshop</a></td><td>Where: Singapore When: Sep 2, 2099</td></tr>
        <tr><td><a href="/cfp/servlet/event.showcfp?eventid=125">Coastal monitoring old event</a></td><td>Where: Manila When: Jan 2, 2020 Deadline: Dec 1, 2019</td></tr>
        </table>
        HTML)]);
    $data = $this->postJson(route('research.dissemination.conferences.search', $this->topic), ['query' => 'coastal monitoring', 'open_only' => true])
        ->assertOk()->assertJsonCount(2, 'results')->json('results');
    expect($data[0]['submission_deadline'])->toBe('2099-05-15')
        ->and($data[0]['event_starts_on'])->toBe('2099-07-21')
        ->and($data[1]['deadline_status'])->toBe('unknown');
    $this->postJson(route('research.dissemination.conferences.search', $this->topic), ['query' => 'coastal monitoring', 'scope' => 'local'])->assertOk();
    Http::assertSentCount(1);
    foreach (range(1, 2) as $attempt) {
        $this->post(route('research.dissemination.conferences.store', $this->topic), [
            'candidate_key' => $data[0]['candidate_key'], 'title' => 'Forged result',
            'url' => 'https://example.org/forged', 'status' => 'shortlisted',
        ])->assertSessionHasNoErrors();
    }
    expect(ProjectConference::count())->toBe(1)
        ->and(ProjectConference::first()->title)->toContain('Coastal monitoring conference')
        ->and(ProjectConference::first()->source)->toBe('WikiCFP');
});

test('dissemination rejects unsafe links unconfirmed records and inconsistent tracking dates', function () {
    $this->postJson(route('research.dissemination.doi', $this->topic), ['doi' => ['invalid']])->assertUnprocessable()->assertJsonValidationErrors('doi');
    $this->postJson(route('research.dissemination.profile', $this->topic), ['scholar_url' => 'https://attacker.example/citations?user=123'])->assertUnprocessable();
    $this->post(route('research.dissemination.manual', $this->topic), ['title' => 'Paper', 'authors' => 'Researcher', 'year' => 2026, 'type' => 'other', 'url' => 'javascript:alert(1)', 'confirmed' => true])->assertSessionHasErrors('url');
    $this->post(route('research.dissemination.conferences.store', $this->topic), [
        'title' => 'Conference', 'url' => 'https://example.org/conference', 'status' => 'accepted',
        'submitted_on' => now()->toDateString(), 'accepted_on' => now()->subDay()->toDateString(),
    ])->assertSessionHasErrors('accepted_on');
    expect(ProjectConference::count())->toBe(0)->and(ResearchPublication::count())->toBe(0);
});
