<?php

use App\Models\CollegeQuarterlyReport;
use App\Models\CollegeQuarterlyReportSubmission;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();
    $this->travelTo(now()->setDate(2026, 10, 15)->setTime(9, 0));
    foreach (['research_head', 'research_coordinator', 'faculty', 'research_secretary'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }
    $this->coordinator = User::factory()->create(['college' => User::COLLEGES['CICS']]);
    $this->coordinator->assignRole('research_coordinator');
    $this->head = User::factory()->create();
    $this->head->assignRole('research_head');
    $this->reportInput = ['reporting_year' => 2026, 'reporting_quarter' => 3, 'intent' => 'submit', 'other_accomplishments' => 'Original submitted narrative', 'form_complete' => '1'];
    $this->asCoordinator = fn () => $this->actingAs($this->coordinator)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_OFFICE]);
    $this->asHead = fn () => $this->actingAs($this->head)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD]);
    ($this->asCoordinator)();
});

test('research head receives submitted college reports while unfinished drafts stay private', function () {
    $this->post(route('research_coordinator.reports.store'), $this->reportInput)->assertSessionHasNoErrors();
    CollegeQuarterlyReport::factory()->create(['college' => 'Private unfinished college', 'reporting_year' => 2026, 'reporting_quarter' => 3]);
    ($this->asHead)();
    $this->get(route('research_head.quarterly-reports.index', ['year' => 2026, 'college' => $this->coordinator->college, 'quarter' => 3]))->assertOk()
        ->assertSeeText($this->coordinator->college)->assertSeeText('Return for correction')
        ->assertDontSeeText('Private unfinished college');
});

test('returning a submission preserves its snapshot and lets the coordinator correct and resubmit it', function () {
    Storage::fake('local');
    $this->post(route('research_coordinator.reports.store'), [...$this->reportInput,
        'evidence_images' => [UploadedFile::fake()->image('original.png')], 'evidence_captions' => ['Original photo'],
    ])->assertSessionHasNoErrors();
    $report = CollegeQuarterlyReport::sole();
    $submission = $report->submissions()->sole();
    $original = $submission->snapshot;
    $originalPath = $original['evidence'][0]['path'];
    $this->get(route('research_coordinator.reports.print', ['year' => 2026, 'quarter' => 3]))
        ->assertOk()->assertSee(route('college-quarterly-submissions.evidence', ['submission' => $submission, 'index' => 0]), false);
    ($this->asHead)();
    $this->patch(route('research_head.quarterly-reports.return', $report), [
        'submission_id' => $submission->id, 'reason' => 'Please include the missing September activity.',
    ])->assertSessionHasNoErrors();
    expect($report->fresh()->submitted_at)->toBeNull()
        ->and($submission->fresh()->snapshot)->toEqual($original)
        ->and($submission->fresh()->returned_by)->toBe($this->head->id)
        ->and($submission->fresh()->return_reason)->toBe('Please include the missing September activity.');
    ($this->asCoordinator)();
    $this->get(route('research_coordinator.reports.index', ['year' => 2026, 'quarter' => 3]))
        ->assertOk()->assertSeeText('Returned for correction')->assertSeeText('Please include the missing September activity.')
        ->assertSeeText('Resubmit quarterly report')->assertSee('Original submitted narrative');
    $this->post(route('research_coordinator.reports.store'), [...$this->reportInput,
        'intent' => 'save', 'other_accomplishments' => 'Corrected narrative', 'remove_evidence' => [hash('sha256', $originalPath)],
    ])->assertSessionHasNoErrors();
    Storage::disk('local')->assertExists($originalPath);
    $this->get(route('college-quarterly-submissions.print', $submission))->assertOk()
        ->assertSeeText('Original submitted narrative')->assertDontSeeText('Corrected narrative');
    $this->get(route('college-quarterly-submissions.evidence', ['submission' => $submission, 'index' => 0]))->assertOk();
    $this->post(route('research_coordinator.reports.store'), [...$this->reportInput, 'other_accomplishments' => 'Corrected narrative'])
        ->assertSessionHasNoErrors();
    $report->refresh();
    expect($report->submitted_at)->not->toBeNull()
        ->and($report->submissions()->count())->toBe(2)
        ->and($report->latestSubmission->version_number)->toBe(2)
        ->and($report->latestSubmission->returned_at)->toBeNull()
        ->and($submission->fresh()->snapshot)->toEqual($original);
    $this->get(route('research_coordinator.reports.index', ['year' => 2026, 'quarter' => 3]))->assertOk()
        ->assertDontSeeText('Returned for correction')->assertSeeText('Submission history')->assertSeeText('Corrected narrative');
    $this->get(route('college-quarterly-submissions.download', $submission))->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    $this->postJson(route('research_coordinator.reports.store'), [...$this->reportInput, 'intent' => 'save'])
        ->assertUnprocessable()->assertJsonValidationErrors('intent');
});

test('correction requests require a reason and can only return the reviewed submission once', function () {
    $this->post(route('research_coordinator.reports.store'), $this->reportInput)->assertSessionHasNoErrors();
    $report = CollegeQuarterlyReport::sole();
    $submission = $report->latestSubmission;
    ($this->asHead)();
    $url = route('research_head.quarterly-reports.return', $report);
    foreach (['', ' ', str_repeat('x', 5001)] as $reason) {
        $this->patchJson($url, ['submission_id' => $submission->id, 'reason' => $reason])
            ->assertUnprocessable()->assertJsonValidationErrors('reason');
    }
    expect($report->fresh()->submitted_at)->not->toBeNull();
    $this->patch($url, ['submission_id' => $submission->id, 'reason' => 'Correct the narrative.'])->assertSessionHasNoErrors();
    $this->patchJson($url, ['submission_id' => $submission->id, 'reason' => 'Duplicate return'])
        ->assertUnprocessable()->assertJsonValidationErrors('submission_id');
    ($this->asCoordinator)();
    $this->post(route('research_coordinator.reports.store'), $this->reportInput)->assertSessionHasNoErrors();
    ($this->asHead)();
    $this->patchJson($url, ['submission_id' => $submission->id, 'reason' => 'Stale review page'])
        ->assertUnprocessable()->assertJsonValidationErrors('submission_id');
    expect($report->fresh()->submitted_at)->not->toBeNull()
        ->and($report->latestSubmission->returned_at)->toBeNull();
});

test('only the research head can return college reports', function (string $role, string $workspace) {
    $this->post(route('research_coordinator.reports.store'), $this->reportInput)->assertSessionHasNoErrors();
    $report = CollegeQuarterlyReport::sole();
    $user = $role === 'research_coordinator' ? $this->coordinator : User::factory()->create();
    $user->assignRole($role);
    $this->actingAs($user)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => $workspace]);
    $this->getJson(route('research_head.quarterly-reports.index'))->assertForbidden();
    $this->patchJson(route('research_head.quarterly-reports.return', $report), ['submission_id' => $report->latestSubmission->id, 'reason' => 'Unauthorized return'])
        ->assertForbidden();
    expect($report->fresh()->submitted_at)->not->toBeNull();
})->with([
    'coordinator' => ['research_coordinator', User::WORKSPACE_RESEARCH_OFFICE],
    'faculty' => ['faculty', User::WORKSPACE_FACULTY],
    'secretary' => ['research_secretary', User::WORKSPACE_RESEARCH_SECRETARY],
]);

test('submission history and its photos are limited to the college coordinator and research head', function () {
    Storage::fake('local');
    $this->post(route('research_coordinator.reports.store'), [...$this->reportInput,
        'evidence_images' => [UploadedFile::fake()->image('private.png')],
    ])->assertSessionHasNoErrors();
    $submission = CollegeQuarterlyReportSubmission::sole();
    $urls = [route('college-quarterly-submissions.print', $submission), route('college-quarterly-submissions.download', $submission),
        route('college-quarterly-submissions.evidence', ['submission' => $submission, 'index' => 0])];
    ($this->asHead)();
    foreach ($urls as $url) {
        $this->get($url)->assertOk();
    }
    $other = User::factory()->create(['college' => User::COLLEGES['CTE']]);
    $other->assignRole('research_coordinator');
    $this->actingAs($other)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_OFFICE]);
    foreach ($urls as $url) {
        $this->getJson($url)->assertForbidden();
    }
    $other->assignRole('faculty');
    $this->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY]);
    foreach ($urls as $url) {
        $this->getJson($url)->assertForbidden();
    }
});

test('college report history loads version metadata while archived documents retain their snapshots', function (string $viewer) {
    $this->post(route('research_coordinator.reports.store'), $this->reportInput)->assertSessionHasNoErrors();
    $report = CollegeQuarterlyReport::sole();
    $original = $report->submissions()->sole();
    $snapshot = [...$original->snapshot,
        'other_accomplishments' => 'Frozen original narrative '.str_repeat('Archived report contents outside the version list. ', 3000),
    ];
    $original->update(['snapshot' => $snapshot, 'returned_by' => $this->head->id, 'returned_at' => now()->subDay(), 'return_reason' => 'Add the missing September activity.']);
    $latest = CollegeQuarterlyReportSubmission::factory()->create([
        'report_id' => $report->id, 'submitted_by' => $this->coordinator->id, 'version_number' => 2,
        'snapshot' => $snapshot, 'returned_by' => $this->head->id, 'returned_at' => now(),
        'return_reason' => 'Clarify the corrected publication date.',
    ]);
    $report->update(['submitted_at' => null]);
    $retrieved = [];
    Event::listen('eloquent.retrieved: '.CollegeQuarterlyReportSubmission::class, function (CollegeQuarterlyReportSubmission $submission) use (&$retrieved): void {
        $retrieved[] = $submission->getAttributes();
    });
    try {
        if ($viewer === 'coordinator') {
            ($this->asCoordinator)();
            $response = $this->get(route('research_coordinator.reports.index', ['year' => 2026, 'quarter' => 3]));
        } else {
            ($this->asHead)();
            $response = $this->get(route('research_head.quarterly-reports.index', ['year' => 2026, 'college' => $this->coordinator->college, 'quarter' => 3]));
        }
        $response->assertOk()->assertSeeText('Clarify the corrected publication date.')
            ->assertSee(route('college-quarterly-submissions.print', $original), false)
            ->assertSee(route('college-quarterly-submissions.download', $original), false)
            ->assertSee(route('college-quarterly-submissions.print', $latest), false)
            ->assertSee(route('college-quarterly-submissions.download', $latest), false);
    } finally {
        Event::forget('eloquent.retrieved: '.CollegeQuarterlyReportSubmission::class);
    }
    if (getenv('ATHENA_PERFORMANCE_BASELINE') === '1') {
        fwrite(STDERR, 'College report '.$viewer.' history bytes: '.strlen(json_encode($retrieved, JSON_THROW_ON_ERROR)).PHP_EOL);
    }
    expect($retrieved)->not->toBeEmpty();
    foreach ($retrieved as $attributes) {
        expect($attributes)->not->toHaveKey('snapshot');
    }
    if ($viewer === 'coordinator') {
        expect($response->viewData('report')->preparedBy->name)->toBe($this->coordinator->name)
            ->and($response->viewData('report')->preparedBy->getAttributes())->not->toHaveKeys(['email', 'password']);
    } else {
        $listedReport = $response->viewData('reports')->sole();
        expect($listedReport->latestSubmission->submittedBy->name)->toBe($this->coordinator->name)
            ->and($listedReport->getAttributes())->not->toHaveKeys([
                'snapshot', 'presentations', 'publications', 'seminars', 'manual_research', 'ip_assets', 'evidence', 'other_accomplishments',
            ]);
    }

    $this->get(route('college-quarterly-submissions.print', $original))->assertOk()->assertSeeText('Frozen original narrative');
    $this->get(route('college-quarterly-submissions.download', $original))->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    expect($original->fresh()->snapshot)->toEqual($snapshot);
})->with(['coordinator', 'head']);

test('college quarter folders batch latest submission metadata and show the latest return status', function () {
    foreach (range(1, 4) as $quarter) {
        $report = CollegeQuarterlyReport::factory()->create([
            'college' => $this->coordinator->college, 'prepared_by' => $this->coordinator->id,
            'reporting_year' => 2026, 'reporting_quarter' => $quarter,
        ]);
        foreach ([1, 2] as $version) {
            CollegeQuarterlyReportSubmission::factory()->create([
                'report_id' => $report->id, 'submitted_by' => $this->coordinator->id, 'version_number' => $version,
                'returned_at' => $version === 1 || $quarter === 3 ? now() : null,
                'snapshot' => ['other_accomplishments' => str_repeat('Archived report outside quarter folder metadata. ', 1000)],
            ]);
        }
    }
    ($this->asHead)();
    $preventedLazyLoading = Model::preventsLazyLoading();
    Model::preventLazyLoading();
    try {
        $response = $this->get(route('research_head.quarterly-reports.index', ['year' => 2026, 'college' => $this->coordinator->college]));
    } finally {
        Model::preventLazyLoading($preventedLazyLoading);
    }
    $response->assertOk()->assertSeeText('January–March · Submitted')->assertSeeText('April–June · Submitted')
        ->assertSeeText('July–September · Returned for correction')->assertSeeText('October–December · Submitted');
    $collegeReports = $response->viewData('collegeReports');
    expect($collegeReports)->toHaveCount(4);
    foreach ($collegeReports as $report) {
        expect($report->relationLoaded('latestSubmission'))->toBeTrue()
            ->and($report->latestSubmission->getAttributes())->toHaveKeys(['id', 'report_id', 'returned_at'])
            ->not->toHaveKeys(['snapshot', 'return_reason', 'version_number']);
    }
});
