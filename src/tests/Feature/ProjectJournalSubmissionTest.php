<?php

use App\Models\ProjectJournalSubmission;
use App\Models\ResearchPublication;
use App\Models\TopicProposal;
use App\Models\User;
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
