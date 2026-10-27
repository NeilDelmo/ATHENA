<?php

use App\Models\CollegeQuarterlyReport;
use App\Models\ProjectProgressReport;
use App\Models\ProposalDraft;
use App\Models\ProposalVersion;
use App\Models\ProposalVersionFile;
use App\Models\TopicProposal;
use App\Models\User;
use App\Notifications\ProposalActivityNotification;
use App\Services\AccessibleRecordSearch;
use App\Services\CollegeQuarterlyReportData;
use App\Services\CollegeQuarterlyReportFacultyEvidence;
use App\Services\ProjectReportReviewService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

function routingProposalCollege(TopicProposal $topic, mixed $college, int $versionNumber = 1): ProposalVersionFile
{
    $version = $topic->versions()->create([
        'submitted_by' => $topic->user_id, 'version_number' => $versionNumber,
        'submission_type' => $versionNumber === 1 ? 'initial' : 'revision',
        'title' => $topic->title, 'file_path' => 'proposal.pdf', 'original_filename' => 'proposal.pdf',
    ]);

    return $version->files()->create([
        'document_type' => ProposalVersionFile::TYPE_DETAILED_PROPOSAL,
        'file_path' => 'proposal.pdf', 'original_filename' => 'proposal.pdf',
        'source_data' => ['proponent_college' => $college],
    ]);
}

beforeEach(function () {
    $this->withoutVite();
    $this->travelTo(now()->setDate(2026, 10, 27)->setTime(12, 0));
    Notification::fake();
    Storage::fake('local');
    foreach (['faculty_researcher', 'research_coordinator', 'research_secretary'] as $role) {
        Role::findOrCreate($role, 'web');
    }
    $this->leader = User::factory()->create(['college' => User::COLLEGES['CTE']]);
    $this->leader->assignRole('faculty_researcher');
    foreach (['proposal' => 'CICS', 'profile' => 'CTE'] as $source => $college) {
        $coordinator = User::factory()->create(['college' => User::COLLEGES[$college]]);
        $coordinator->assignRole('research_coordinator');
        $this->{$source.'Coordinator'} = $coordinator;
        $secretary = User::factory()->create(['college' => User::COLLEGES[$college]]);
        $secretary->assignRole('research_secretary');
        $this->{$source.'Secretary'} = $secretary;
    }
    $this->topic = TopicProposal::create([
        'user_id' => $this->leader->id, 'title' => 'Research routed by its submitted proposal',
        'status' => 'approved', 'project_status' => 'ongoing', 'estimated_duration_months' => 12,
        'notice_to_proceed_issued_at' => '2026-01-01',
        'notice_to_proceed_data' => ['approved_start_date' => '2026-01-01', 'approved_end_date' => '2026-12-31'],
    ]);
    $this->collegeFile = routingProposalCollege($this->topic, User::COLLEGES['CICS']);
});

test('submitted proposal college controls secretary recipients queues and report authorization', function () {
    $report = ProjectProgressReport::create([
        'topic_id' => $this->topic->id, 'submitted_by' => $this->leader->id, 'reporting_date' => '2026-09-30',
        'progress_percentage' => 30, 'accomplishments' => 'Fieldwork completed',
        'submission_status' => 'submitted', 'review_stage' => 'secretary', 'review_status' => 'pending',
    ]);
    $service = app(ProjectReportReviewService::class);
    $service->notifySecretarySubmission($report);
    Notification::assertSentTo($this->proposalSecretary, ProposalActivityNotification::class);
    Notification::assertNotSentTo($this->profileSecretary, ProposalActivityNotification::class);

    foreach (['proposal' => true, 'profile' => false] as $source => $allowed) {
        $secretary = $this->{$source.'Secretary'};
        $this->actingAs($secretary)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_SECRETARY]);
        expect($this->topic->canReviewImplementationReports($secretary))->toBe($allowed)
            ->and(TopicProposal::query()->forImplementationReportReview($secretary)->whereKey($this->topic)->exists())->toBe($allowed)
            ->and($secretary->can('view', $this->topic))->toBe($allowed);
    }
});

test('automatic college research and faculty proof remain with the submitted college when the leader changes colleges', function () {
    $image = UploadedFile::fake()->image('proof.png');
    $path = $image->store('progress-reports/'.$this->topic->id.'/evidence', 'local');
    ProjectProgressReport::create([
        'topic_id' => $this->topic->id, 'submitted_by' => $this->leader->id,
        'reporting_date' => '2026-09-30', 'reporting_year' => 2026, 'reporting_quarter' => 3,
        'version_number' => 1, 'progress_percentage' => 30, 'submitted_at' => now(),
        'review_stage' => 'head', 'accomplishments' => 'Fieldwork completed',
        'work_plan' => [['activity' => 'Fieldwork', 'evidence' => [[
            'id' => (string) Str::uuid(), 'path' => $path, 'name' => 'proof.png', 'mime_type' => 'image/png',
            'size' => $image->getSize(), 'checksum' => hash_file('sha256', $image->getRealPath()),
        ]]]],
    ]);
    $data = app(CollegeQuarterlyReportData::class);
    $proof = app(CollegeQuarterlyReportFacultyEvidence::class);
    expect(array_column($data->build($this->proposalCoordinator, 2026, 3)['research'], 0))->toBe([$this->topic->title])
        ->and($data->build($this->profileCoordinator, 2026, 3)['research'])->toBeEmpty()
        ->and(array_column($proof->forQuarter($this->proposalCoordinator, 2026, 3), 'path'))->toBe([$path])
        ->and($proof->forQuarter($this->profileCoordinator, 2026, 3))->toBeEmpty();
});

test('outcome notifications lists and record access use the submitted proposal college', function () {
    $this->topic->update(['project_status' => 'completed']);
    $this->actingAs($this->leader)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY_RESEARCHER])
        ->put(route('research-outcomes.projects.update', $this->topic), [
            'labels' => ['commercialized', 'utilization'], 'lock_version' => 0,
        ])->assertSessionHasNoErrors();
    Notification::assertSentTo($this->proposalCoordinator, ProposalActivityNotification::class);
    Notification::assertNotSentTo($this->profileCoordinator, ProposalActivityNotification::class);

    $this->actingAs($this->proposalCoordinator)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_OFFICE]);
    $this->get(route('research-outcomes.index'))->assertOk()->assertSee($this->topic->title);
    $this->get(route('research-outcomes.projects.show', $this->topic))->assertOk()->assertSee(User::COLLEGES['CICS']);
    $this->actingAs($this->profileCoordinator);
    $this->get(route('research-outcomes.index'))->assertOk()->assertDontSee($this->topic->title);
    $this->get(route('research-outcomes.projects.show', $this->topic))->assertForbidden();
});

test('college routing uses the latest recorded submitted college and ignores unsubmitted drafts', function () {
    routingProposalCollege($this->topic, User::COLLEGES['CAS'], 2);
    $draft = ProposalDraft::create(['topic_id' => $this->topic->id, 'user_id' => $this->leader->id, 'project_title' => $this->topic->title, 'project_leader' => $this->leader->name]);
    $draft->documents()->create(['document_type' => ProposalVersionFile::TYPE_DETAILED_PROPOSAL, 'source_data' => ['proponent_college' => User::COLLEGES['CICS']]]);

    expect($this->topic->projectCollege())->toBe(User::COLLEGES['CAS'])
        ->and(TopicProposal::query()->forCollege(User::COLLEGES['CAS'])->whereKey($this->topic)->exists())->toBeTrue()
        ->and(TopicProposal::query()->forCollege(User::COLLEGES['CICS'])->whereKey($this->topic)->exists())->toBeFalse();
});

test('blank newer snapshots do not replace an already recorded proposal college', function (mixed $college) {
    routingProposalCollege($this->topic, $college, 2);
    expect($this->topic->projectCollege())->toBe(User::COLLEGES['CICS'])
        ->and(TopicProposal::query()->forCollege(User::COLLEGES['CICS'])->whereKey($this->topic)->exists())->toBeTrue();
})->with([null, '', '   ']);

test('college abbreviations and full names identify the same submitted college', function (string $college) {
    $this->collegeFile->update(['source_data' => ['proponent_college' => $college]]);
    expect($this->topic->projectCollege())->toBe(User::COLLEGES['CICS'])
        ->and(TopicProposal::query()->forCollege('CICS')->whereKey($this->topic)->exists())->toBeTrue()
        ->and(TopicProposal::query()->forCollege(User::COLLEGES['CICS'])->whereKey($this->topic)->exists())->toBeTrue()
        ->and(TopicProposal::query()->forCollege(null)->whereKey($this->topic)->exists())->toBeFalse();
})->with(['CICS', User::COLLEGES['CICS'], '  cics  ']);

test('legacy projects with no recorded proposal college retain their existing profile college routing', function () {
    $this->collegeFile->update(['source_data' => []]);
    expect($this->topic->projectCollege())->toBe(User::COLLEGES['CTE'])
        ->and(TopicProposal::query()->forCollege(User::COLLEGES['CTE'])->whereKey($this->topic)->exists())->toBeTrue();
});

test('coordinator activity links are validated against the proposal college rather than the leaders current profile', function () {
    $payload = [
        'reporting_year' => 2026, 'reporting_quarter' => 3, 'intent' => 'save', 'form_complete' => '1',
        'draft_token' => (new CollegeQuarterlyReport)->draftToken(),
        'presentations' => [[
            'id' => (string) Str::uuid(), 'research_title' => $this->topic->title, 'project_id' => $this->topic->id,
            'event' => 'Research Forum', 'date' => '2026-09-20', 'place' => 'Nasugbu', 'personnel' => 'Project leader',
        ]],
    ];
    $this->actingAs($this->proposalCoordinator)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_OFFICE])
        ->post(route('research_coordinator.reports.store'), $payload)->assertSessionHasNoErrors();
    $this->actingAs($this->profileCoordinator)
        ->post(route('research_coordinator.reports.store'), $payload)->assertSessionHasErrors('presentations.0.project_id');
});

test('LREC queues and access use the same submitted college as implementation reports', function () {
    $this->topic->update(['status' => TopicProposal::STATUS_LREC_QUEUED, 'review_stage' => 'lrec', 'notice_to_proceed_issued_at' => null]);
    $this->actingAs($this->proposalCoordinator)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_OFFICE]);
    $this->get(route('research_coordinator.dashboard'))->assertOk()->assertSee($this->topic->title);
    expect($this->proposalCoordinator->can('view', $this->topic))->toBeTrue();
    $this->actingAs($this->profileCoordinator);
    $this->get(route('research_coordinator.dashboard'))->assertOk()->assertDontSee($this->topic->title);
    expect($this->profileCoordinator->can('view', $this->topic))->toBeFalse();
});

test('repeated project college checks share one lookup without dirtying the model and refresh reads a new submission', function () {
    DB::flushQueryLog();
    DB::enableQueryLog();
    try {
        $first = $this->topic->projectCollege();
        $second = $this->topic->projectCollege();
        $queries = DB::getQueryLog();
    } finally {
        DB::disableQueryLog();
    }
    expect($first)->toBe(User::COLLEGES['CICS'])->and($second)->toBe($first)
        ->and($queries)->toHaveCount(1)->and($this->topic->isDirty())->toBeFalse();
    $this->topic->update(['description' => 'Saving after resolving the project college']);
    routingProposalCollege($this->topic, User::COLLEGES['CAS'], 2);
    expect($this->topic->refresh()->projectCollege())->toBe(User::COLLEGES['CAS']);
});

test('college scoped search projects college values without adding a query per result', function () {
    foreach (range(1, 12) as $number) {
        $topic = TopicProposal::create([
            'user_id' => $this->leader->id, 'title' => 'ProposalCollegeSearch '.$number,
            'status' => 'approved', 'project_status' => 'ongoing', 'notice_to_proceed_issued_at' => '2026-01-01',
        ]);
        routingProposalCollege($topic, User::COLLEGES['CICS']);
    }
    $this->actingAs($this->proposalCoordinator)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_OFFICE]);
    DB::flushQueryLog();
    DB::enableQueryLog();
    try {
        $results = app(AccessibleRecordSearch::class)->search($this->proposalCoordinator, 'ProposalCollegeSearch', 'projects');
        $queryCount = count(DB::getQueryLog());
    } finally {
        DB::disableQueryLog();
    }
    expect($results)->toHaveCount(20)->and($queryCount)->toBeLessThan(15);
});

test('office file search resolves proposal colleges without hydrating private version bodies', function (string $workspace) {
    $this->collegeFile->update(['original_filename' => 'RoutingCollegeFile.pdf']);
    $this->collegeFile->version->forceFill([
        'description' => str_repeat('Version narrative. ', 1000),
        'change_summary' => 'Private revision details', 'research_head_screening' => ['private' => 'Screening details'],
    ])->save();
    $viewer = $workspace === User::WORKSPACE_RESEARCH_OFFICE ? $this->proposalCoordinator : $this->proposalSecretary;
    $this->actingAs($viewer)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => $workspace]);
    $versions = [];
    Event::listen('eloquent.retrieved: '.ProposalVersion::class, function (ProposalVersion $version) use (&$versions): void {
        $versions[] = $version->getAttributes();
    });
    try {
        $results = app(AccessibleRecordSearch::class)->search($viewer, 'RoutingCollegeFile', 'files');
    } finally {
        Event::forget('eloquent.retrieved: '.ProposalVersion::class);
    }
    expect($results)->toHaveCount(1)->and($versions)->toHaveCount(1)
        ->and($versions[0])->not->toHaveKeys(['description', 'change_summary', 'research_head_screening']);
})->with([User::WORKSPACE_RESEARCH_OFFICE, User::WORKSPACE_RESEARCH_SECRETARY]);
