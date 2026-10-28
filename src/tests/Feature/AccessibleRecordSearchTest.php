<?php

use App\Models\ProjectDocument;
use App\Models\ProjectNarrativeReport;
use App\Models\ProjectProgressReport;
use App\Models\ProposalDraft;
use App\Models\ProposalDraftDocument;
use App\Models\ProposalDraftMember;
use App\Models\ProposalVersion;
use App\Models\ProposalVersionFile;
use App\Models\ResearchManual;
use App\Models\TopicCollaborator;
use App\Models\TopicProposal;
use App\Models\User;
use App\Services\AccessibleRecordSearch;
use App\Services\ResearchManualLibrary;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Role;

function searchProject(User $owner, array $attributes = []): TopicProposal
{
    return TopicProposal::create([
        'user_id' => $owner->id, 'title' => 'Mangrove research', 'description' => 'Coastal resilience',
        'status' => 'approved', 'project_status' => 'ongoing', 'notice_to_proceed_issued_at' => now(),
        ...$attributes,
    ]);
}

function searchVersion(TopicProposal $topic, int $number = 1, array $attributes = []): ProposalVersion
{
    return $topic->versions()->create([
        'submitted_by' => $topic->user_id, 'version_number' => $number, 'submission_type' => 'initial',
        'title' => $topic->title, 'file_path' => 'private/proposal-'.$number.'.pdf',
        'original_filename' => 'mangrove-package-'.$number.'.pdf', 'estimated_budget' => 1000, 'estimated_duration_months' => 12,
        ...$attributes,
    ]);
}

function searchMonitoring(TopicProposal $topic, array $attributes = []): ProjectProgressReport
{
    return ProjectProgressReport::create([
        'topic_id' => $topic->id, 'submitted_by' => $topic->user_id, 'reporting_date' => now(),
        'progress_percentage' => 25, 'accomplishments' => 'Field work completed', 'tracking_number' => 'SEARCH-MONITORING',
        'official_pdf_filename' => 'mangrove-monitoring.pdf', 'version_number' => 2,
        ...$attributes,
    ]);
}

function searchNarrative(TopicProposal $topic, array $attributes = []): ProjectNarrativeReport
{
    return ProjectNarrativeReport::create([
        'topic_id' => $topic->id, 'submitted_by' => $topic->user_id, 'report_type' => 'progress',
        'submission_date' => now(), 'researchers' => 'Research team', 'implementation_start' => now()->subMonth(),
        'implementation_end' => now()->addMonth(), 'budget' => 1000, 'funding_agency' => 'University',
        'accomplishment_summary' => 'Coastal work', 'introduction' => 'Introduction', 'objectives' => 'Objectives',
        'methodology' => 'Methods', 'results_discussion' => 'Results', 'photos' => [],
        'tracking_number' => 'SEARCH-NARRATIVE', 'official_pdf_filename' => 'mangrove-progress.pdf', 'version_number' => 3,
        ...$attributes,
    ]);
}

beforeEach(function () {
    foreach (['faculty', 'faculty_researcher', 'research_head', 'research_coordinator', 'research_secretary'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }
    $this->withoutVite();
    $this->mock(ResearchManualLibrary::class, function ($mock) {
        $mock->shouldReceive('active')->andReturnNull();
        $mock->shouldReceive('activeIndex')->andReturnNull()->byDefault();
    });
    $this->owner = User::factory()->create(['college' => 'CICS']);
    $this->owner->assignRole(['faculty', 'faculty_researcher']);
    $this->actingAs($this->owner)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY]);
});

test('search requires authentication and validates bounded input without exposing records on an empty query', function () {
    $this->get(route('search.index'))->assertOk()->assertSee('What are you looking for?')->assertViewHas('results', null)
        ->assertHeader('Cache-Control', 'no-store, private');
    $this->get(route('search.index', ['q' => ['bad']]))->assertRedirect()->assertSessionHasErrors('q');
    $this->get(route('search.index', ['q' => str_repeat('a', 121), 'category' => 'users', 'page' => 1001]))
        ->assertRedirect()->assertSessionHasErrors(['q', 'category', 'page']);
    session()->forget('errors');
    $this->get(route('search.index', ['q' => '   ']))->assertOk()->assertViewHas('results', null);
    auth()->logout();
    $this->get(route('search.index'))->assertRedirect(route('login'));
});

test('proposal drafts and staged filenames are visible only to owners and accepted members in the faculty workspace', function () {
    $draft = ProposalDraft::create(['user_id' => $this->owner->id, 'project_title' => 'Mangrove draft']);
    $draft->documents()->create(['document_type' => 'detailed_proposal', 'original_filename' => 'mangrove-working.pdf', 'lock_version' => 4]);
    $member = User::factory()->create();
    $member->assignRole('faculty');
    $invitation = ProposalDraftMember::factory()->create(['proposal_draft_id' => $draft->id, 'user_id' => $member->id, 'email' => $member->email, 'accepted_at' => null]);
    $stranger = User::factory()->create();
    $stranger->assignRole('faculty');

    expect(app(AccessibleRecordSearch::class)->search($this->owner, 'Mangrove')->total())->toBe(2);
    expect(app(AccessibleRecordSearch::class)->search($member, 'Mangrove')->total())->toBe(0);
    $invitation->update(['accepted_at' => now()]);
    expect(app(AccessibleRecordSearch::class)->search($member, 'Mangrove'))->toHaveCount(2);
    expect(app(AccessibleRecordSearch::class)->search($stranger, 'Mangrove')->total())->toBe(0);
    $this->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY_RESEARCHER]);
    expect(app(AccessibleRecordSearch::class)->search($this->owner, 'Mangrove')->total())->toBe(0);
});

test('search follows project collaborators and active workspace rather than the union of assigned roles', function () {
    $topic = searchProject($this->owner, ['status' => 'pending', 'notice_to_proceed_issued_at' => null]);
    $member = User::factory()->create();
    $member->assignRole(['faculty', 'faculty_researcher', 'research_head']);
    TopicCollaborator::factory()->create(['topic_id' => $topic->id, 'user_id' => $member->id, 'accepted_at' => now()]);
    searchProject(User::factory()->create(), ['title' => 'Mangrove confidential']);

    $this->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY]);
    expect(app(AccessibleRecordSearch::class)->search($member, 'Mangrove'))->toHaveCount(1);
    $this->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY_RESEARCHER]);
    expect(app(AccessibleRecordSearch::class)->search($member, 'Mangrove')->total())->toBe(0);
    $topic->update(['status' => 'approved']);
    expect(app(AccessibleRecordSearch::class)->search($member, 'Mangrove')->total())->toBe(0);
    $topic->update(['notice_to_proceed_issued_at' => now()]);
    expect(app(AccessibleRecordSearch::class)->search($member, 'Mangrove'))->toHaveCount(1);
    $this->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD]);
    expect(app(AccessibleRecordSearch::class)->search($member, 'Mangrove'))->toHaveCount(2);
});

test('results distinguish prior proposal versions filenames metadata and assigned manual editions', function () {
    $manual = ResearchManual::factory()->create(['edition' => '2023', 'activated_at' => now()]);
    $topic = searchProject($this->owner, ['research_manual_id' => $manual->id]);
    $version = searchVersion($topic, 1, ['title' => 'Original coastal proposal']);
    searchVersion($topic, 2);
    $file = $version->files()->create(['document_type' => 'work_plan', 'file_path' => 'private/fieldwork.pdf', 'original_filename' => 'coastal-work-plan.pdf']);
    $document = ProjectDocument::factory()->create(['topic_id' => $topic->id, 'title' => 'Field itinerary', 'original_filename' => 'transport.pdf', 'note' => 'Boat allocation']);

    $original = app(AccessibleRecordSearch::class)->search($this->owner, 'Original coastal')->sole();
    expect($original['location'])->toContain('Version 1');
    expect($original['edition'])->toBe('Manual edition 2023');
    expect($original['url'])->toBe(route('topics.versions.download', [$topic, $version]));
    $match = app(AccessibleRecordSearch::class)->search($this->owner, 'coastal-work-plan', 'files')->sole();
    expect($match['url'])->toBe(route('topics.versions.files.download', [$topic, $version, $file]));
    expect($match['context'])->toBe($topic->title);
    expect(app(AccessibleRecordSearch::class)->search($this->owner, 'work plan', 'files')->sole()['url'])->toBe($match['url']);
    $metadata = app(AccessibleRecordSearch::class)->search($this->owner, 'Boat allocation', 'files')->sole();
    expect($metadata['url'])->toBe(route('topics.documents.download', [$topic, $document]));
    expect($metadata['location'])->toContain('transport.pdf');
});

test('unreleased and superseded signed filenames do not leak to faculty while signing officers retain access', function () {
    $topic = searchProject($this->owner, ['status' => TopicProposal::STATUS_READY_FOR_SIGNATURE, 'notice_to_proceed_issued_at' => null]);
    $version = searchVersion($topic);
    $file = $version->files()->create(['document_type' => ProposalVersionFile::TYPE_HEAD_UPLOAD, 'file_path' => 'private/signed.pdf', 'original_filename' => 'SecretSignatures.pdf', 'source_data' => ['purpose' => 'signed']]);
    expect(app(AccessibleRecordSearch::class)->search($this->owner, 'SecretSignatures')->total())->toBe(0);
    $secretary = User::factory()->create(['college' => 'CICS']);
    $secretary->assignRole('research_secretary');
    $this->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_SECRETARY]);
    expect(app(AccessibleRecordSearch::class)->search($secretary, 'SecretSignatures'))->toHaveCount(1);
    $assignedSecretary = User::factory()->create(['college' => 'CTE']);
    $assignedSecretary->assignRole('research_secretary');
    $topic->update(['research_secretary_id' => $assignedSecretary->id]);
    expect(app(AccessibleRecordSearch::class)->search($assignedSecretary, 'SecretSignatures'))->toHaveCount(1);
    $this->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY]);
    $topic->update(['status' => 'approved', 'notice_to_proceed_issued_at' => now()]);
    expect(app(AccessibleRecordSearch::class)->search($this->owner, 'SecretSignatures')->total())->toBe(1);
    $file->update(['superseded_at' => now()]);
    expect(app(AccessibleRecordSearch::class)->search($this->owner, 'SecretSignatures')->total())->toBe(0);
});

test('report metadata respects secretary forwarding college and terminal visibility limits', function () {
    $topic = searchProject($this->owner);
    $monitoring = searchMonitoring($topic, ['review_stage' => 'secretary']);
    $narrative = searchNarrative($topic, ['review_stage' => 'secretary']);
    searchMonitoring($topic, ['submission_status' => 'prepared', 'official_pdf_filename' => 'SECRET-PREPARED.pdf']);
    $head = User::factory()->create();
    $head->assignRole('research_head');
    $secretary = User::factory()->create(['college' => 'CICS']);
    $secretary->assignRole('research_secretary');
    $otherSecretary = User::factory()->create(['college' => 'CTE']);
    $otherSecretary->assignRole('research_secretary');

    $this->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD]);
    expect(app(AccessibleRecordSearch::class)->search($head, 'SEARCH-', 'reports')->total())->toBe(0);
    expect(app(AccessibleRecordSearch::class)->search($head, 'Monitoring', 'reports')->total())->toBe(0);
    $monitoring->update(['review_stage' => 'head']);
    $narrative->update(['review_stage' => 'head']);
    expect(app(AccessibleRecordSearch::class)->search($head, 'SEARCH-', 'reports'))->toHaveCount(2);
    expect(app(AccessibleRecordSearch::class)->search($head, 'SECRET-PREPARED', 'reports')->total())->toBe(0);
    $this->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_SECRETARY]);
    expect(app(AccessibleRecordSearch::class)->search($secretary, 'SEARCH-', 'reports'))->toHaveCount(2);
    expect(app(AccessibleRecordSearch::class)->search($otherSecretary, 'SEARCH-', 'reports')->total())->toBe(0);
    $narrative->update(['report_type' => 'terminal']);
    expect(app(AccessibleRecordSearch::class)->search($secretary, 'SEARCH-NARRATIVE', 'reports')->total())->toBe(0);
});

test('office search observes college and review routing even for users with faculty roles', function () {
    $office = User::factory()->create(['college' => 'CICS']);
    $office->assignRole(['research_coordinator', 'faculty']);
    searchProject($this->owner, ['title' => 'Mangrove LREC', 'status' => 'lrec_queued', 'review_stage' => 'lrec', 'notice_to_proceed_issued_at' => null]);
    searchProject($this->owner, ['title' => 'Mangrove pending', 'status' => 'pending', 'notice_to_proceed_issued_at' => null]);
    searchProject(User::factory()->create(['college' => 'CTE']), ['title' => 'Mangrove other college', 'status' => 'lrec_queued', 'review_stage' => 'lrec', 'notice_to_proceed_issued_at' => null]);
    $this->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_OFFICE]);
    expect(app(AccessibleRecordSearch::class)->search($office, 'Mangrove')->pluck('context')->all())->toBe(['Mangrove LREC']);
});

test('manual search includes activated editions and hides staged editions outside the active head workspace', function () {
    $active = ResearchManual::factory()->create(['title' => 'Mangrove Manual', 'edition' => '2023', 'activated_at' => now()]);
    $staged = ResearchManual::factory()->create(['title' => 'Mangrove Candidate', 'edition' => '2026']);
    $head = User::factory()->create();
    $head->assignRole('research_head');
    $this->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY]);
    expect(app(AccessibleRecordSearch::class)->search($head, 'Mangrove', 'manuals')->total())->toBe(1);
    expect(app(AccessibleRecordSearch::class)->search($this->owner, '2023', 'manuals')->sole()['url'])->toBe(route('research-manuals.read', $active->version));
    $this->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD]);
    $results = app(AccessibleRecordSearch::class)->search($head, 'Mangrove', 'manuals');
    expect($results->total())->toBe(2);
    expect($results->first()['url'])->toBe(route('research-manuals.read', $staged->version));
    expect($results->first()['context'])->toContain('Staged edition');
});

test('the current legacy manual remains searchable without importing it into the database', function () {
    $version = str_repeat('a', 64);
    $this->mock(ResearchManualLibrary::class, function ($mock) use ($version) {
        $mock->shouldReceive('activeIndex')->andReturn(['title' => 'Legacy Research Manual', 'edition' => '2023', 'version' => $version]);
    });
    $result = app(AccessibleRecordSearch::class)->search($this->owner, '2023', 'manuals')->sole();
    expect($result['url'])->toBe(route('research-manuals.read', $version));
    expect($result['edition'])->toBe('Manual edition 2023');
    expect(ResearchManual::count())->toBe(0);
});

test('search treats wildcard SQL and Unicode input as literal text and safely escapes rendered results', function () {
    searchProject($this->owner, ['title' => 'José 100%_! coast <script>alert(1)</script>']);
    searchProject($this->owner, ['title' => 'Jose 100XX coast']);
    expect(app(AccessibleRecordSearch::class)->search($this->owner, '%_!')->total())->toBe(1);
    expect(app(AccessibleRecordSearch::class)->search($this->owner, "' OR 1=1 --")->total())->toBe(0);
    $this->get(route('search.index', ['q' => 'José', 'category' => 'projects']))->assertOk()
        ->assertSee('José')->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
});

test('search paginates before hydrating records and preserves category and query links', function () {
    foreach (range(1, 23) as $number) {
        searchProject($this->owner, ['title' => $number === 23 ? 'Mangrove <img src=x onerror=alert(1)> '.str_repeat('LongResearchProject', 7) : 'Mangrove project '.$number]);
    }
    $retrievedTopicIds = [];
    Event::listen('eloquent.retrieved: '.TopicProposal::class, function (TopicProposal $topic) use (&$retrievedTopicIds): void {
        $retrievedTopicIds[] = $topic->id;
    });
    DB::flushQueryLog();
    DB::enableQueryLog();
    try {
        $results = app(AccessibleRecordSearch::class)->search($this->owner, 'Mangrove', 'projects');
        $queries = collect(DB::getQueryLog())->pluck('query');
    } finally {
        DB::disableQueryLog();
        Event::forget('eloquent.retrieved: '.TopicProposal::class);
    }
    expect($results->total())->toBe(23);
    expect($results->count())->toBe(20);
    expect($queries->contains(fn (string $sql): bool => str_contains($sql, 'limit 20')))->toBeTrue();
    expect($retrievedTopicIds)->toHaveCount(20);
    $response = $this->get(route('search.index', ['q' => 'Mangrove', 'category' => 'projects']));
    $response->assertOk()->assertSee('q=Mangrove&amp;category=projects&amp;page=2', false);
    $this->get(route('search.index', ['q' => 'Mangrove', 'category' => 'projects', 'page' => 2]))
        ->assertOk()->assertViewHas('results', fn ($results): bool => $results->count() === 3);

    if (getenv('ATHENA_EXPORT_SEARCH') === '1') {
        File::ensureDirectoryExists(storage_path('framework/testing'));
        File::put(storage_path('framework/testing/search-results.html'), $response->getContent());
    }
});

test('search results omit bodies from every hydrated record and parent while retaining searchable metadata', function () {
    $body = str_repeat('Saved research narrative. ', 500);
    $manual = ResearchManual::factory()->create([
        'title' => 'Mangrove research manual', 'edition' => '2026', 'activated_at' => now(),
        'guidance_check_results' => ['guidance' => $body], 'unindexed_pages' => range(1, 100),
    ]);
    $topic = searchProject($this->owner, [
        'research_manual_id' => $manual->id, 'description' => 'Topic searchable passage '.$body,
        'notice_to_proceed_data' => ['notes' => $body],
    ]);
    $version = searchVersion($topic, 1, [
        'description' => 'Version searchable passage '.$body, 'change_summary' => $body,
    ]);
    $version->forceFill(['research_head_screening' => ['narrative' => $body]])->save();
    $file = $version->files()->create([
        'document_type' => 'work_plan', 'file_path' => 'private/work-plan.pdf',
        'original_filename' => 'mangrove-work-plan.pdf', 'source_data' => ['narrative' => $body],
    ]);
    $draft = ProposalDraft::create([
        'user_id' => $this->owner->id, 'research_manual_id' => $manual->id,
        'project_title' => 'Mangrove working draft', 'project_leader' => 'Draft searchable leader',
        'signatory_selections' => ['notes' => $body],
    ]);
    $draft->forceFill(['pdf_preparation_state' => ['notes' => $body]])->save();
    $draft->documents()->create([
        'document_type' => 'detailed_proposal', 'original_filename' => 'mangrove-draft.pdf',
        'lock_version' => 4, 'source_data' => ['narrative' => $body],
    ]);
    $monitoring = searchMonitoring($topic, [
        'reporting_year' => 2026, 'reporting_quarter' => 2, 'accomplishments' => $body,
        'work_plan' => [['activity' => $body]], 'budget_utilization' => [['purpose' => $body]],
    ]);
    $narrative = searchNarrative($topic, [
        'reporting_quarter' => 2, 'period_start' => '2026-04-01', 'period_end' => '2026-06-30',
        'introduction' => $body, 'photos' => [['caption' => $body]], 'terminal_data' => ['abstract' => $body],
    ]);
    $document = ProjectDocument::factory()->create([
        'topic_id' => $topic->id, 'title' => 'Mangrove field itinerary',
        'original_filename' => 'field-itinerary.pdf', 'category' => ProjectDocument::CATEGORY_SUPPORTING_FILES,
        'note' => 'Document searchable note '.$body,
    ]);
    $attributes = [];
    $models = [TopicProposal::class, ProposalDraft::class, ProposalVersion::class, ProjectDocument::class, ResearchManual::class,
        ProposalDraftDocument::class, ProposalVersionFile::class, ProjectProgressReport::class, ProjectNarrativeReport::class];
    foreach ($models as $model) {
        Event::listen('eloquent.retrieved: '.$model, function (Model $record) use (&$attributes): void {
            $attributes[$record::class][] = $record->getAttributes();
        });
    }
    try {
        $results = app(AccessibleRecordSearch::class)->search($this->owner, 'Mangrove');
    } finally {
        foreach ($models as $model) {
            Event::forget('eloquent.retrieved: '.$model);
        }
    }

    if (getenv('ATHENA_PERFORMANCE_BASELINE') === '1') {
        fwrite(STDERR, 'Search result hydrated bytes: '.strlen(json_encode($attributes, JSON_THROW_ON_ERROR)).PHP_EOL);
    }
    expect($results)->toHaveCount(9)
        ->and($attributes)->toHaveCount(9)
        ->and($results->firstWhere('url', route('topics.show', $topic))['title'])->toBe($topic->title)
        ->and($results->firstWhere('url', route('topics.versions.download', [$topic, $version]))['location'])->toBe('Submitted proposals · Version 1')
        ->and($results->firstWhere('url', route('topics.versions.files.download', [$topic, $version, $file]))['title'])->toBe('mangrove-work-plan.pdf')
        ->and($results->firstWhere('url', route('faculty.proposal-drafts.show', $draft))['context'])->toBe('Mangrove working draft')
        ->and($results->firstWhere('url', route('topics.documents.download', [$topic, $document]))['location'])->toBe('Project files · Supporting files · field-itinerary.pdf')
        ->and($results->firstWhere('url', route('research-manuals.read', $manual->version))['context'])->toBe('Edition 2026 · Activated edition')
        ->and($results->firstWhere('url', route('project-progress.monitoring-tool.view', $monitoring))['title'])->toBe('Monitoring Tool · Q2 · 2026')
        ->and($results->firstWhere('url', route('project-narrative-reports.show', $narrative))['title'])->toBe('Progress report · Q2 · Apr 1, 2026 – Jun 30, 2026');
    expect($results->every(fn (array $result): bool => $result['edition'] === 'Manual edition 2026'))->toBeTrue();
    foreach ($attributes as $records) {
        foreach ($records as $recordAttributes) {
            expect($recordAttributes)->not->toHaveKeys([
                'source_data', 'description', 'notice_to_proceed_data', 'signatory_selections', 'pdf_preparation_state',
                'change_summary', 'research_head_screening', 'note', 'guidance_check_results', 'unindexed_pages',
                'accomplishments', 'work_plan', 'budget_utilization', 'introduction', 'photos', 'terminal_data',
            ]);
        }
    }

    expect(app(AccessibleRecordSearch::class)->search($this->owner, 'Topic searchable passage')->sole()['url'])->toBe(route('topics.show', $topic));
    expect(app(AccessibleRecordSearch::class)->search($this->owner, 'Version searchable passage')->sole()['url'])->toBe(route('topics.versions.download', [$topic, $version]));
    expect(app(AccessibleRecordSearch::class)->search($this->owner, 'Draft searchable leader')->sole()['url'])->toBe(route('faculty.proposal-drafts.show', $draft));
    expect(app(AccessibleRecordSearch::class)->search($this->owner, 'Document searchable note')->sole()['url'])->toBe(route('topics.documents.download', [$topic, $document]));
});
