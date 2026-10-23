<?php

use App\Models\ProjectConference;
use App\Models\ProjectJournalSubmission;
use App\Models\ProjectProgressReport;
use App\Models\ProposalVersion;
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

test('quarterly research loads only the latest summary records for each project', function () {
    foreach (['Alpha', 'Beta'] as $name) {
        $topic = ($this->project)(['title' => $name, 'estimated_duration_months' => null]);
        foreach (range(1, 3) as $number) {
            $version = $topic->versions()->create([
                'submitted_by' => $this->owner->id, 'version_number' => $number,
                'submission_type' => $number === 1 ? 'initial' : 'revision', 'title' => $name,
                'estimated_duration_months' => $number + 5,
                'file_path' => 'proposal.pdf', 'original_filename' => 'proposal.pdf',
            ]);
            foreach ([
                ProposalVersionFile::TYPE_DETAILED_PROPOSAL => ['project_leader_display' => $name.' leader '.$number],
                ProposalVersionFile::TYPE_LINE_ITEM_BUDGET => ['staff' => [['display_name' => $name.' staff '.$number]]],
                ProposalVersionFile::TYPE_WORK_PLAN => ['planned_start' => '2026-01-01', 'planned_end' => '2026-12-31'],
                ProposalVersionFile::TYPE_GAD_CHECKLIST => ['unrelated_contents' => str_repeat('x', 65536)],
            ] as $type => $source) {
                $version->files()->create([
                    'document_type' => $type, 'file_path' => 'proposal.pdf', 'original_filename' => 'proposal.pdf',
                    'source_data' => $source,
                ]);
            }
        }
        foreach ([
            ['reporting_date' => '2026-03-31', 'version_number' => 5, 'progress_percentage' => 20],
            ['reporting_date' => '2026-06-30', 'version_number' => 1, 'progress_percentage' => 30],
            ['reporting_date' => '2026-06-30', 'version_number' => 2, 'progress_percentage' => 40],
            ['reporting_date' => '2026-06-30', 'version_number' => 2, 'progress_percentage' => 50],
            ['reporting_date' => '2026-06-30', 'version_number' => 3, 'progress_percentage' => 100, 'submission_status' => 'prepared'],
            ['reporting_date' => '2026-09-30', 'version_number' => 1, 'progress_percentage' => 100],
        ] as $attributes) {
            ProjectProgressReport::create([
                'topic_id' => $topic->id, 'submitted_by' => $this->owner->id,
                'accomplishments' => str_repeat('Long report content. ', 1000),
                'work_plan' => [['activity' => str_repeat('Work plan. ', 1000)]], ...$attributes,
            ]);
        }
    }

    $versions = [];
    $files = [];
    $reports = [];
    ProposalVersion::retrieved(function (ProposalVersion $version) use (&$versions): void {
        $versions[] = $version->id;
    });
    ProposalVersionFile::retrieved(function (ProposalVersionFile $file) use (&$files): void {
        $files[] = $file->getAttributes();
    });
    ProjectProgressReport::retrieved(function (ProjectProgressReport $report) use (&$reports): void {
        $reports[] = $report->getAttributes();
    });

    $data = app(CollegeQuarterlyReportData::class)->build($this->coordinator, 2026, 2);

    expect($data['research'])->toBe([
        ['Alpha', '8 months', "Alpha leader 3 (Project Leader)\nAlpha staff 3 (Project Staff)\nProject Owner", 'Ongoing (50% reported)'],
        ['Beta', '8 months', "Beta leader 3 (Project Leader)\nBeta staff 3 (Project Staff)\nProject Owner", 'Ongoing (50% reported)'],
    ]);
    if (getenv('ATHENA_PERFORMANCE_BASELINE') === '1') {
        fwrite(STDERR, sprintf('Quarterly summary loaded: %d versions, %d files, %d reports, %d payload bytes'.PHP_EOL,
            count($versions), count($files), count($reports), strlen(json_encode([$files, $reports], JSON_THROW_ON_ERROR))));
    }
    expect($versions)->toHaveCount(2)
        ->and($files)->toHaveCount(6)
        ->and($reports)->toHaveCount(2);
    foreach ($reports as $report) {
        expect($report)->not->toHaveKeys(['accomplishments', 'work_plan', 'budget_utilization']);
    }
});

test('automatic college research progress waits for office secretary forwarding', function () {
    $topic = ($this->project)();
    ProjectProgressReport::create([
        'topic_id' => $topic->id, 'submitted_by' => $this->owner->id,
        'reporting_date' => '2026-03-31', 'version_number' => 1,
        'progress_percentage' => 20, 'accomplishments' => 'Previous forwarded progress',
        'review_stage' => 'head',
    ]);
    $pending = ProjectProgressReport::create([
        'topic_id' => $topic->id, 'submitted_by' => $this->owner->id,
        'reporting_date' => '2026-06-30', 'version_number' => 1,
        'progress_percentage' => 80, 'accomplishments' => 'Awaiting office review',
        'review_stage' => 'secretary',
    ]);
    $service = app(CollegeQuarterlyReportData::class);

    expect($service->build($this->coordinator, 2026, 2)['research'][0][3])->toBe('Ongoing (20% reported)');

    $pending->update(['review_stage' => 'head', 'forwarded_at' => now()]);

    expect($service->build($this->coordinator, 2026, 2)['research'][0][3])->toBe('Ongoing (80% reported)');
});

test('automatic college research uses the latest visible revision within the latest reporting period', function (string $stage, string $submissionStatus, int $expectedProgress) {
    $topic = ($this->project)();
    $original = ProjectProgressReport::create([
        'topic_id' => $topic->id, 'submitted_by' => $this->owner->id,
        'reporting_date' => '2026-06-30', 'reporting_year' => 2026, 'reporting_quarter' => 2,
        'period_start' => '2026-04-01', 'period_end' => '2026-06-30', 'version_number' => 1,
        'progress_percentage' => 75, 'accomplishments' => 'Original quarterly progress',
        'review_stage' => 'head', 'review_status' => 'revision_requested',
    ]);
    ProjectProgressReport::create([
        'topic_id' => $topic->id, 'submitted_by' => $this->owner->id,
        'reporting_date' => '2026-06-25', 'reporting_year' => 2026, 'reporting_quarter' => 2,
        'period_start' => '2026-04-01', 'period_end' => '2026-06-30', 'version_number' => 2,
        'progress_percentage' => 90, 'accomplishments' => 'Corrected quarterly progress',
        'supersedes_report_id' => $original->id, 'submission_status' => $submissionStatus,
        'review_stage' => $stage,
    ]);
    ProjectProgressReport::create([
        'topic_id' => $topic->id, 'submitted_by' => $this->owner->id,
        'reporting_date' => '2026-03-31', 'reporting_year' => 2026, 'reporting_quarter' => 1,
        'period_start' => '2026-01-01', 'period_end' => '2026-03-31', 'version_number' => 4,
        'progress_percentage' => 30, 'accomplishments' => 'Earlier quarter with more revisions',
        'review_stage' => 'head',
    ]);

    expect(app(CollegeQuarterlyReportData::class)->build($this->coordinator, 2026, 2)['research'][0][3])
        ->toBe('Ongoing ('.$expectedProgress.'% reported)');
})->with([
    'forwarded correction' => ['head', 'submitted', 90],
    'prepared correction' => ['head', 'prepared', 75],
    'awaiting office review' => ['secretary', 'submitted', 75],
]);
