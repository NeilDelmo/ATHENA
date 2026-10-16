<?php

use App\Models\ProjectConference;
use App\Models\ProjectJournalSubmission;
use App\Models\ProjectProgressReport;
use App\Models\ProposalVersionFile;
use App\Models\ResearchPublication;
use App\Models\TopicCollaborator;
use App\Models\TopicProposal;
use App\Models\User;
use App\Services\CollegeQuarterlyReportData;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 15)->setTime(12, 0));
    $this->coordinator = User::factory()->create(['college' => 'Science']);
    $this->owner = User::factory()->create(['name' => 'Project Owner', 'college' => 'Science']);
    $this->project = fn (array $attributes = []) => TopicProposal::create([
        'user_id' => $this->owner->id,
        'title' => 'Coastal monitoring research',
        'status' => 'approved',
        'project_status' => 'ongoing',
        'notice_to_proceed_issued_at' => '2026-01-01',
        'notice_to_proceed_data' => ['approved_start_date' => '2026-01-01', 'approved_end_date' => '2026-12-31'],
        'estimated_duration_months' => 12,
        ...$attributes,
    ]);
});

test('only faculty research is automatic with the approved duration and project personnel roles', function (string $documentType, string $leaderField) {
    $secretary = User::factory()->create(['name' => 'Project Secretary']);
    $topic = ($this->project)([
        'research_secretary_id' => $secretary->id,
        'estimated_duration_months' => 6,
        'notice_to_proceed_data' => [
            'approved_start_date' => '2026-01-01', 'approved_end_date' => '2026-12-31',
            'approved_duration_months' => 12,
        ],
    ]);
    $version = $topic->versions()->create([
        'submitted_by' => $this->owner->id, 'version_number' => 1,
        'submission_type' => 'initial', 'title' => $topic->title,
        'file_path' => 'proposal.pdf', 'original_filename' => 'proposal.pdf',
    ]);
    $version->files()->create([
        'document_type' => $documentType,
        'file_path' => 'proposal.pdf', 'original_filename' => 'proposal.pdf',
        'source_data' => [
            $leaderField => 'Approved Project Leader',
            'staff' => [['display_name' => 'Approved Staff Member'], ['title' => 'Dr.', 'name' => $secretary->name]],
        ],
    ]);
    $member = User::factory()->create(['name' => 'Accepted Collaborator', 'college' => 'Arts']);
    TopicCollaborator::factory()->create([
        'topic_id' => $topic->id, 'user_id' => $member->id, 'name' => $member->name,
        'accepted_at' => '2026-03-01',
    ]);
    TopicCollaborator::factory()->create(['topic_id' => $topic->id, 'name' => 'Invited Collaborator']);
    TopicCollaborator::factory()->create([
        'topic_id' => $topic->id, 'user_id' => $secretary->id, 'name' => $secretary->name,
        'project_role' => 'secretary', 'accepted_at' => '2026-03-01',
    ]);
    ProjectConference::factory()->create([
        'topic_id' => $topic->id, 'added_by' => $this->owner->id, 'title' => 'Coastal Science Forum',
        'status' => 'presented', 'presented_on' => '2026-06-30', 'location' => 'Batangas',
    ]);
    ProjectJournalSubmission::factory()->create([
        'topic_id' => $topic->id, 'added_by' => $this->owner->id,
        'journal_name' => 'Marine Science Journal', 'manuscript_title' => 'Coastal findings',
        'status' => 'published', 'published_on' => '2026-04-01',
    ]);

    $data = app(CollegeQuarterlyReportData::class)->build($this->coordinator, 2026, 2);
    $personnel = "Approved Project Leader (Project Leader)\nApproved Staff Member (Project Staff)\nProject Owner\nAccepted Collaborator";

    expect($data['presentations'])->toBeEmpty()
        ->and($data['publications'])->toBeEmpty()
        ->and($data['research'])->toBe([[$topic->title, '12 months', $personnel, 'Ongoing (current status)']]);
})->with([
    'detailed proposal' => [ProposalVersionFile::TYPE_DETAILED_PROPOSAL, 'project_leader_display'],
    'line item budget' => [ProposalVersionFile::TYPE_LINE_ITEM_BUDGET, 'project_leader'],
]);

test('presentations and publications remain manual regardless of dissemination dates or status', function () {
    $topic = ($this->project)();
    foreach (['2026-03-31', '2026-04-01', '2026-06-30', '2026-07-01'] as $date) {
        ProjectConference::factory()->create([
            'topic_id' => $topic->id, 'added_by' => $this->owner->id,
            'title' => 'Forum '.$date, 'status' => 'presented', 'presented_on' => $date,
            'created_at' => '2026-10-01',
        ]);
        ProjectJournalSubmission::factory()->create([
            'topic_id' => $topic->id, 'added_by' => $this->owner->id,
            'manuscript_title' => 'Paper '.$date, 'status' => 'published', 'published_on' => $date,
            'created_at' => '2025-01-01',
        ]);
    }
    ProjectConference::factory()->create([
        'topic_id' => $topic->id, 'added_by' => $this->owner->id,
        'status' => 'accepted', 'presented_on' => '2026-05-01',
    ]);
    ProjectJournalSubmission::factory()->create([
        'topic_id' => $topic->id, 'added_by' => $this->owner->id,
        'status' => 'accepted', 'published_on' => '2026-05-01',
    ]);
    ProjectJournalSubmission::factory()->create([
        'topic_id' => $topic->id, 'added_by' => $this->owner->id,
        'status' => 'published', 'published_on' => null, 'created_at' => '2026-05-01',
    ]);

    $data = app(CollegeQuarterlyReportData::class)->build($this->coordinator, 2026, 2);

    expect($data['presentations'])->toBeEmpty()
        ->and($data['publications'])->toBeEmpty()
        ->and($data['research'])->toHaveCount(1);
});

test('automatic research requires an approved same college project with a notice issued by quarter end', function () {
    $outsideOwner = User::factory()->create(['college' => 'Arts']);
    foreach ([
        ['title' => 'Other college', 'user_id' => $outsideOwner->id],
        ['title' => 'Pending approval', 'status' => 'pending'],
        ['title' => 'No issued notice', 'notice_to_proceed_issued_at' => null],
        ['title' => 'Future notice', 'notice_to_proceed_issued_at' => '2026-07-01'],
    ] as $attributes) {
        $topic = ($this->project)($attributes);
        TopicCollaborator::factory()->create([
            'topic_id' => $topic->id, 'user_id' => $this->owner->id,
            'accepted_at' => '2026-01-01',
        ]);
        ProjectConference::factory()->create([
            'topic_id' => $topic->id, 'added_by' => $topic->user_id,
            'status' => 'presented', 'presented_on' => '2026-05-01',
        ]);
        ProjectJournalSubmission::factory()->create([
            'topic_id' => $topic->id, 'added_by' => $topic->user_id,
            'status' => 'published', 'published_on' => '2026-05-01',
        ]);
        $publication = ResearchPublication::factory()->create(['user_id' => $topic->user_id, 'year' => 2026]);
        $topic->publications()->attach($publication);
    }

    expect(app(CollegeQuarterlyReportData::class)->build($this->coordinator, 2026, 2))->toBe([
        'presentations' => [], 'publications' => [], 'research' => [], 'undated_publications' => 0,
    ]);
    $included = ($this->project)(['notice_to_proceed_issued_at' => '2026-06-30 23:59:59']);

    expect(array_column(app(CollegeQuarterlyReportData::class)->build($this->coordinator, 2026, 2)['research'], 0))->toBe([$included->title]);
    $this->coordinator->update(['college' => null]);
    ($this->project)();

    expect(app(CollegeQuarterlyReportData::class)->build($this->coordinator, 2026, 2)['research'])->toBeEmpty();
});

test('automatic research requires an overlapping implementation window including quarter boundaries', function () {
    $ended = ($this->project)([
        'title' => 'Finished implementation', 'project_status' => 'completed',
        'notice_to_proceed_data' => ['approved_start_date' => '2026-01-01', 'approved_end_date' => '2026-03-31'],
    ]);
    ($this->project)([
        'title' => 'Starts next quarter',
        'notice_to_proceed_data' => ['approved_start_date' => '2026-07-01', 'approved_end_date' => '2027-06-30'],
    ]);
    ($this->project)([
        'title' => 'Ends on quarter start',
        'notice_to_proceed_data' => ['approved_start_date' => '2026-01-01', 'approved_end_date' => '2026-04-01'],
    ]);
    ($this->project)([
        'title' => 'Starts on quarter end',
        'notice_to_proceed_data' => ['approved_start_date' => '2026-06-30', 'approved_end_date' => '2027-06-29'],
    ]);
    ProjectConference::factory()->create([
        'topic_id' => $ended->id, 'added_by' => $this->owner->id,
        'status' => 'presented', 'presented_on' => '2026-05-01',
    ]);
    ProjectJournalSubmission::factory()->create([
        'topic_id' => $ended->id, 'added_by' => $this->owner->id,
        'status' => 'published', 'published_on' => '2026-05-01',
    ]);

    $data = app(CollegeQuarterlyReportData::class)->build($this->coordinator, 2026, 2);

    expect(array_column($data['research'], 0))->toBe(['Ends on quarter start', 'Starts on quarter end'])
        ->and($data['presentations'])->toBeEmpty()
        ->and($data['publications'])->toBeEmpty();
});

test('year only publications do not fill the manual section or produce an automatic warning', function () {
    $topic = ($this->project)();
    $other = ($this->project)(['title' => 'Second project']);
    $undated = ResearchPublication::factory()->create(['user_id' => $this->owner->id, 'year' => 2026, 'confirmed_at' => '2026-05-01']);
    $topic->publications()->attach($undated);
    $other->publications()->attach($undated);
    $priorYear = ResearchPublication::factory()->create(['user_id' => $this->owner->id, 'year' => 2025]);
    $topic->publications()->attach($priorYear);
    $dated = ResearchPublication::factory()->create(['user_id' => $this->owner->id, 'year' => 2026, 'title' => 'Dated findings']);
    $topic->publications()->attach($dated);
    ProjectJournalSubmission::factory()->create([
        'topic_id' => $topic->id, 'added_by' => $this->owner->id,
        'manuscript_title' => 'Dated findings', 'status' => 'published', 'published_on' => '2026-02-01',
    ]);

    $data = app(CollegeQuarterlyReportData::class)->build($this->coordinator, 2026, 2);

    expect($data['publications'])->toBeEmpty()
        ->and($data['undated_publications'])->toBe(0)
        ->and($data['research'])->toHaveCount(2);
});

test('research duration uses notice defaults without replacing months with a date span', function (array $noticeData, ?int $estimatedMonths, string $duration) {
    ($this->project)([
        'estimated_duration_months' => $estimatedMonths,
        'notice_to_proceed_data' => [
            'approved_start_date' => '2026-01-01', 'approved_end_date' => '2026-12-31',
            ...$noticeData,
        ],
    ]);

    expect(app(CollegeQuarterlyReportData::class)->build($this->coordinator, 2026, 2)['research'][0][1])->toBe($duration);
})->with([
    'approved duration' => [['approved_duration_months' => 18], 12, '18 months'],
    'default duration' => [[], 12, '12 months'],
    'one month' => [['approved_duration_months' => 1], 12, '1 month'],
    'missing duration' => [[], null, ''],
]);

test('research status uses the latest period dated submitted monitoring version and identifies current status fallbacks', function () {
    $topic = ($this->project)(['project_status' => 'completed']);
    foreach ([
        ['reporting_date' => '2026-03-31', 'progress_percentage' => 20, 'version_number' => 3],
        ['reporting_date' => '2026-06-30', 'progress_percentage' => 40, 'version_number' => 1],
        ['reporting_date' => '2026-06-30', 'progress_percentage' => 50, 'version_number' => 2],
        ['reporting_date' => '2026-06-30', 'progress_percentage' => 100, 'version_number' => 4, 'submission_status' => 'prepared'],
        ['reporting_date' => '2026-09-30', 'progress_percentage' => 100],
    ] as $attributes) {
        ProjectProgressReport::create([
            'topic_id' => $topic->id, 'submitted_by' => $this->owner->id,
            'accomplishments' => 'Recorded progress', ...$attributes,
        ]);
    }

    $service = app(CollegeQuarterlyReportData::class);

    expect($service->build($this->coordinator, 2026, 2)['research'][0][3])->toBe('Ongoing (50% reported)')
        ->and($service->build($this->coordinator, 2026, 3)['research'][0][3])->toBe('Completion pending (100% reported)');
    $topic->progressReports()->delete();

    expect($service->build($this->coordinator, 2026, 2)['research'][0][3])->toBe('Completed (current status)');
});
