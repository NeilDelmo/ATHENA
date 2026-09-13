<?php

use App\Models\ProjectConference;
use App\Models\ResearcherProfile;
use App\Models\ResearchPublication;
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

test('completed projects retain dissemination access and preserve archived monitoring', function () {
    $this->get(route('research.dissemination.show', $this->topic))
        ->assertOk()->assertSee('Conferences &amp; Publications', false)->assertSee('Research reporting is complete');
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

test('dissemination prevents unrelated users cross-project writes and head mutations', function () {
    $conference = ProjectConference::factory()->create(['topic_id' => $this->topic->id, 'added_by' => $this->researcher->id]);
    $second = TopicProposal::create(['user_id' => $this->researcher->id, 'title' => 'Other project', 'status' => 'approved', 'project_status' => 'completed']);
    $this->patch(route('research.dissemination.conferences.update', [$second, $conference]), [])->assertForbidden();
    $outsider = User::factory()->create();
    $outsider->assignRole('faculty_researcher');
    $this->actingAs($outsider)->get(route('research.dissemination.show', $this->topic))->assertForbidden();
    $this->postJson(route('research.dissemination.authors', $this->topic), ['query' => 'Researcher'])->assertForbidden();
    $head = User::factory()->create();
    $head->assignRole('research_head');
    $this->actingAs($head)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => 'research_head'])
        ->get(route('research.dissemination.show', $this->topic))->assertOk()->assertSee('Research Head view');
    $this->post(route('research.dissemination.conferences.store', $this->topic), [])->assertForbidden();
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
