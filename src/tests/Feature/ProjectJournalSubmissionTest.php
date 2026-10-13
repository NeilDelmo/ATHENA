<?php

use App\Models\ProjectConference;
use App\Models\ProjectJournalSubmission;
use App\Models\ResearchPublication;
use App\Models\TopicProposal;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['faculty_researcher', 'research_head'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }
    $this->researcher = User::factory()->create();
    $this->researcher->assignRole('faculty_researcher');
    $this->topic = TopicProposal::create([
        'user_id' => $this->researcher->id, 'title' => 'Community health and diabetes',
        'status' => 'approved', 'project_status' => 'completed',
    ]);
    $this->actingAs($this->researcher)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => 'faculty_researcher']);
    $this->entry = ['journal_name' => 'Community Health Journal', 'manuscript_title' => $this->topic->title, 'status' => 'shortlisted'];
});

test('researchers can track review acceptance and publication after terminal reporting without marking submissions as publications', function () {
    $this->post(route('research.dissemination.journal-submissions.store', $this->topic), $this->entry)
        ->assertSessionHasNoErrors()->assertRedirect(route('research.dissemination.show', $this->topic).'#journal-submissions');
    $entry = ProjectJournalSubmission::firstOrFail();
    $dates = ['submitted_on' => now()->subDays(10)->toDateString(), 'accepted_on' => now()->subDays(2)->toDateString()];
    foreach (['submitted', 'under_review', 'revision_requested', 'accepted'] as $stage) {
        $this->patch(route('research.dissemination.journal-submissions.update', [$this->topic, $entry]), array_replace($this->entry, $dates, ['status' => $stage]))
            ->assertSessionHasNoErrors();
        expect($entry->fresh()->status)->toBe($stage)->and(ResearchPublication::count())->toBe(0);
    }
    $this->get(route('research.dissemination.show', $this->topic))->assertOk()->assertDontSee('Check Google Scholar');
    $this->patch(route('research.dissemination.journal-submissions.update', [$this->topic, $entry]), array_replace($this->entry, $dates, [
        'status' => 'published', 'published_on' => now()->toDateString(), 'publication_url' => 'https://doi.org/10.1234/health',
    ]))->assertSessionHasNoErrors();
    expect($entry->fresh()->status)->toBe('published')->and($this->topic->fresh()->project_status)->toBe('completed');
    $this->get(route('research.dissemination.show', $this->topic))->assertOk()->assertSee('Read published article')->assertSee('Check Google Scholar');
});

test('publication requires dated acceptance and a safe article link with chronological dates', function () {
    $url = route('research.dissemination.journal-submissions.store', $this->topic);
    $this->post($url, array_replace($this->entry, ['status' => 'published']))
        ->assertSessionHasErrors(['submitted_on', 'accepted_on', 'published_on', 'publication_url']);
    $this->post($url, array_replace($this->entry, [
        'status' => 'published', 'submitted_on' => now()->toDateString(),
        'accepted_on' => now()->subDays(5)->toDateString(), 'published_on' => now()->addDay()->toDateString(),
        'publication_url' => 'javascript:alert(1)',
    ]))->assertSessionHasErrors(['accepted_on', 'published_on', 'publication_url']);
    expect(ProjectJournalSubmission::count())->toBe(0);
});

test('duplicate entries cannot reset an existing manuscript stage', function () {
    $url = route('research.dissemination.journal-submissions.store', $this->topic);
    $this->post($url, $this->entry)->assertSessionHasNoErrors();
    $this->post($url, array_replace($this->entry, ['journal_name' => '  community   health journal  ']))
        ->assertSessionHasErrors('fingerprint');
    expect(ProjectJournalSubmission::count())->toBe(1);
});

test('unrelated researchers cross-project edits and research head access are denied', function () {
    $entry = ProjectJournalSubmission::factory()->create(['topic_id' => $this->topic->id, 'added_by' => $this->researcher->id]);
    $other = TopicProposal::create(['user_id' => $this->researcher->id, 'title' => 'Other', 'status' => 'approved', 'project_status' => 'completed']);
    $this->patch(route('research.dissemination.journal-submissions.update', [$other, $entry]), $this->entry)->assertForbidden();
    $outsider = User::factory()->create();
    $outsider->assignRole('faculty_researcher');
    $this->actingAs($outsider)->post(route('research.dissemination.journal-submissions.store', $this->topic), $this->entry)->assertForbidden();
    $head = User::factory()->create();
    $head->assignRole('research_head');
    $this->actingAs($head)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => 'research_head']);
    $this->get(route('research.dissemination.show', $this->topic))->assertForbidden();
    $this->post(route('research.dissemination.journal-submissions.store', $this->topic), $this->entry)->assertForbidden();
    $this->patch(route('research.dissemination.journal-submissions.update', [$this->topic, $entry]), $this->entry)->assertForbidden();
});

test('projects without dissemination access cannot create journal submission records', function () {
    $this->topic->update(['status' => 'pending', 'project_status' => 'not_started']);
    $this->post(route('research.dissemination.journal-submissions.store', $this->topic), $this->entry)->assertForbidden();
});

test('malformed journal fields receive validation errors instead of a server error', function () {
    $this->post(route('research.dissemination.journal-submissions.store', $this->topic), [
        'journal_name' => ['invalid'], 'manuscript_title' => ['invalid'], 'status' => 'shortlisted',
    ])->assertSessionHasErrors(['journal_name', 'manuscript_title']);
    expect(ProjectJournalSubmission::count())->toBe(0);
});

test('confirmed assistant journal saves return the saved identifier and enforce duplicate validation', function () {
    $url = route('research.dissemination.journal-submissions.store', $this->topic);
    $this->postJson($url, $this->entry)->assertCreated()
        ->assertJsonPath('id', ProjectJournalSubmission::firstOrFail()->id);
    $this->postJson($url, $this->entry)->assertUnprocessable()->assertJsonValidationErrors('fingerprint');
    expect($this->topic->journalSubmissions()->count())->toBe(1);
});

test('assistant conference saves use the user and project bound candidate and report existing entries', function () {
    $key = str_repeat('a', 64);
    Cache::put('conference-candidate:'.$this->researcher->id.':'.$this->topic->id.':'.$key, [
        'title' => 'Verified coastal conference', 'url' => 'https://www.wikicfp.com/cfp/servlet/event.showcfp?eventid=123',
        'source' => 'WikiCFP', 'source_checked_at' => now()->toIso8601String(),
    ], now()->addHours(2));
    $payload = ['candidate_key' => $key, 'title' => 'Forged title', 'url' => 'https://example.org/forged', 'status' => 'shortlisted'];
    $url = route('research.dissemination.conferences.store', $this->topic);
    $this->postJson($url, $payload)->assertCreated()->assertJsonPath('id', ProjectConference::firstOrFail()->id);
    expect(ProjectConference::firstOrFail()->title)->toBe('Verified coastal conference');
    $this->postJson($url, $payload)->assertOk()->assertJsonPath('id', ProjectConference::firstOrFail()->id);
    expect($this->topic->conferences()->count())->toBe(1);
    Cache::forget('conference-candidate:'.$this->researcher->id.':'.$this->topic->id.':'.$key);
    $this->postJson($url, $payload)->assertUnprocessable()->assertJsonValidationErrors('conference');
});

test('assistant venue saves cannot write another researchers project', function () {
    $outsider = User::factory()->create();
    $outsider->assignRole('faculty_researcher');
    $this->actingAs($outsider);
    $this->postJson(route('research.dissemination.journal-submissions.store', $this->topic), $this->entry)->assertForbidden();
    $this->postJson(route('research.dissemination.conferences.store', $this->topic), [
        'title' => 'Conference', 'url' => 'https://example.org/cfp', 'status' => 'shortlisted',
    ])->assertForbidden();
    expect($this->topic->journalSubmissions()->count())->toBe(0)->and($this->topic->conferences()->count())->toBe(0);
});

test('shared finder journal forms return to the authorized project tracker after adding and editing', function () {
    $destination = route('research-support.index', ['topic' => $this->topic->id]).'#journal-submissions';
    $this->post(route('research.dissemination.journal-submissions.store', $this->topic), $this->entry + ['return_to_finder' => true])
        ->assertSessionHasNoErrors()->assertRedirect($destination);
    $submission = ProjectJournalSubmission::firstOrFail();
    $this->patch(route('research.dissemination.journal-submissions.update', [$this->topic, $submission]), $this->entry + ['return_to_finder' => true])
        ->assertSessionHasNoErrors()->assertRedirect($destination);
});

test('saved assistant conference shortlist entries are visible in the authorized project workspace', function () {
    $this->postJson(route('research.dissemination.conferences.store', $this->topic), [
        'title' => 'Coastal conference for the project', 'url' => 'https://example.org/coastal-cfp', 'status' => 'shortlisted',
    ])->assertCreated();
    $this->get(route('research.dissemination.show', $this->topic))->assertOk()
        ->assertSee('Coastal conference for the project')->assertSee('id="conferences"', false);
});
