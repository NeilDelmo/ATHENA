<?php

use App\Contracts\DocumentPdfConverter;
use App\Models\ProjectProgressReport;
use App\Models\TopicProposal;
use App\Models\User;
use App\Services\MonitoringEvidenceService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 7, 1));
    Storage::fake('local');
    Notification::fake();
    $this->withoutVite();
    Role::findOrCreate('faculty_researcher', 'web');
    Role::findOrCreate('research_head', 'web');
    $this->researcher = User::factory()->create();
    $this->researcher->assignRole('faculty_researcher');
    $this->head = User::factory()->create();
    $this->head->assignRole('research_head');
    $this->topic = TopicProposal::create([
        'title' => 'Evidence monitoring project',
        'user_id' => $this->researcher->id, 'status' => 'approved', 'project_status' => 'ongoing',
        'estimated_budget' => 50000, 'estimated_duration_months' => 6,
        'notice_to_proceed_issued_at' => '2026-01-01', 'notice_to_proceed_issued_by' => $this->head->id,
        'notice_to_proceed_data' => ['approved_start_date' => '2026-01-01', 'approved_duration_months' => 6],
    ]);
    $version = $this->topic->versions()->create([
        'submitted_by' => $this->researcher->id, 'version_number' => 1,
        'submission_type' => 'initial', 'title' => $this->topic->title,
        'file_path' => 'proposal.pdf', 'original_filename' => 'proposal.pdf', 'mime_type' => 'application/pdf',
        'file_size' => 100, 'checksum' => hash('sha256', 'proposal'),
    ]);
    $this->plan = $version->files()->create([
        'document_type' => 'work_plan', 'position' => 0, 'file_path' => 'work-plan.pdf',
        'original_filename' => 'work-plan.pdf', 'mime_type' => 'application/pdf', 'file_size' => 100,
        'checksum' => hash('sha256', 'work-plan'), 'source_data' => ['total_duration_months' => 6, 'entries' => [
            ['objective' => 'Collect responses', 'activity' => 'Conduct interviews', 'expected_output' => '20 interviews', 'months' => [1, 2, 3]],
            ['objective' => 'Analyze results', 'activity' => 'Write findings', 'expected_output' => 'Evaluation report', 'months' => [4, 5, 6]],
        ]],
    ]);
    $this->payload = fn (array $changes = []): array => array_replace_recursive([
        'progress_mode' => 'evidence', 'draft_version' => 0, 'reporting_date' => '2026-03-31',
        'work_plan' => [['source_work_plan_index' => 0, 'completed_units' => 12, 'actual_accomplishment' => 'Interviewed 12 participants.', 'accomplished_percentage' => 99]],
        'budget_utilization' => array_map(fn (string $type): array => ['type' => $type, 'amount_requested' => 0, 'actual_amount' => 0], ['Purchase Request', 'Cash Advance', 'Request of Payment']),
    ], $changes);
    $this->mock(DocumentPdfConverter::class)->shouldReceive('convertDocx')->andReturnUsing(function (string $contents): string {
        $this->generatedDocx = $contents;

        return '%PDF-1.4 monitoring evidence test';
    });
});

test('activity targets distinguish quantities from a single output', function (string $description, int $units) {
    expect(app(MonitoringEvidenceService::class)->target($description)['target_units'])->toBe($units);
})->with([
    ['20 interviews', 20], ['Dataset containing 150 responses', 150], ['Evaluation report', 1],
    ['2026 evaluation report', 1], ['2 workshops and 30 participants', 1],
]);

test('evidence computes partial weighted progress and survives saving and reloading', function () {
    $payload = ($this->payload)(['activity_evidence' => ['plan-0' => [UploadedFile::fake()->image('interviews.png')]]]);
    $response = $this->actingAs($this->researcher)->postJson(route('project-progress.draft', $this->topic), $payload)
        ->assertSuccessful()->assertJsonPath('work_plan.0.accomplished_percentage', '30.00')
        ->assertJsonPath('work_plan.0.target_units', 20)->assertJsonPath('work_plan.0.completed_units', 12);
    $file = $response->json('work_plan.0.evidence.0');
    Storage::disk('local')->assertExists($file['path']);
    $this->get(route('project-progress.evidence', ['topic' => $this->topic, 'evidence' => $file['id']]))->assertSuccessful();
    $page = $this->get(route('project-progress.create', ['topic' => $this->topic, 'reporting_date' => '2026-03-31']))
        ->assertSuccessful()->assertSee('interviews.png')->assertSee('Calculated completion')->assertDontSee('Activity Completion (%)');
    $fixture = getenv('ATHENA_MONITORING_EVIDENCE_FIXTURE');
    if ($fixture) {
        $document = new DOMDocument;
        @$document->loadHTML(preg_replace('/(?<=\s)@([a-z][\w.-]*)\s*=/', 'x-on:$1=', $page->getContent()));
        $workspace = (new DOMXPath($document))->query('//*[@data-monitoring-paper-workspace]')->item(0);
        file_put_contents($fixture, $document->saveHTML($workspace));
    }

    $this->postJson(route('project-progress.draft', $this->topic), ($this->payload)([
        'draft_version' => 1, 'work_plan' => [['evidence_ids' => [$file['id']], 'target_units' => 1, 'percent_weight' => 100]],
    ]))->assertSuccessful()->assertJsonPath('work_plan.0.accomplished_percentage', '30.00')
        ->assertJsonPath('draft_version', 1);
    $this->postJson(route('project-progress.draft', $this->topic), ($this->payload)(['draft_version' => 1]))
        ->assertSuccessful()->assertJsonPath('work_plan.0.accomplished_percentage', '0.00')
        ->assertJsonCount(0, 'work_plan.0.evidence');
    Storage::disk('local')->assertMissing($file['path']);
});

test('more evidence does not increase completion and identical files are stored once', function () {
    $this->actingAs($this->researcher)->postJson(route('project-progress.draft', $this->topic), ($this->payload)([
        'activity_evidence' => ['plan-0' => [UploadedFile::fake()->image('first.png'), UploadedFile::fake()->image('same.png')]],
    ]))->assertSuccessful()->assertJsonPath('work_plan.0.accomplished_percentage', '30.00')->assertJsonCount(1, 'work_plan.0.evidence');
});

test('missing proof earns zero and forged evidence or progress cannot be submitted', function () {
    $this->actingAs($this->researcher)->postJson(route('project-progress.draft', $this->topic), ($this->payload)())
        ->assertSuccessful()->assertJsonPath('work_plan.0.accomplished_percentage', '0.00');
    $this->postJson(route('project-progress.draft', $this->topic), ($this->payload)([
        'draft_version' => 1, 'work_plan' => [['evidence_ids' => [(string) Str::uuid()]]],
    ]))->assertUnprocessable()->assertJsonValidationErrors('work_plan.0.evidence_ids.0');
    $this->postJson(route('project-progress.draft', $this->topic), ($this->payload)([
        'draft_version' => 1, 'work_plan' => [['completed_units' => 21]],
    ]))->assertUnprocessable()->assertJsonValidationErrors('work_plan.0.completed_units');
});

test('unsupported oversized or unlinked evidence is rejected without storing files', function (array $files, string $error) {
    $this->actingAs($this->researcher)->postJson(route('project-progress.draft', $this->topic), ($this->payload)(['activity_evidence' => $files]))
        ->assertUnprocessable()->assertJsonValidationErrors($error);
    expect(Storage::disk('local')->allFiles('progress-reports'))->toBe([]);
})->with([
    fn () => [['plan-0' => [UploadedFile::fake()->create('program.exe', 1)]], 'activity_evidence.plan-0.0'],
    fn () => [['plan-0' => [UploadedFile::fake()->create('large.pdf', 10241, 'application/pdf')]], 'activity_evidence.plan-0.0'],
    fn () => [['plan-1' => [UploadedFile::fake()->image('wrong-quarter.png')]], 'activity_evidence'],
]);

test('stale drafts clean up the new upload and keep the existing evidence', function () {
    $route = route('project-progress.draft', $this->topic);
    $first = $this->actingAs($this->researcher)->postJson($route, ($this->payload)([
        'activity_evidence' => ['plan-0' => [UploadedFile::fake()->image('saved.png')]],
    ]))->assertSuccessful();
    $paths = Storage::disk('local')->allFiles('progress-reports');
    $this->postJson($route, ($this->payload)([
        'work_plan' => [['evidence_ids' => [$first->json('work_plan.0.evidence.0.id')]]],
        'activity_evidence' => ['plan-0' => [UploadedFile::fake()->image('stale.png', 20, 20)]],
    ]))->assertUnprocessable()->assertJsonValidationErrors('draft_version');
    expect(Storage::disk('local')->allFiles('progress-reports'))->toBe($paths);
});

test('Q2 carries Q1 progress into the prepared official document and keeps evidence private', function () {
    $this->actingAs($this->researcher)->post(route('project-progress.prepare', $this->topic), ($this->payload)([
        'activity_evidence' => ['plan-0' => [UploadedFile::fake()->image('q1.png')]],
    ]))->assertSessionHasNoErrors()->assertRedirect();
    $q1 = ProjectProgressReport::query()->firstOrFail();
    expect($q1->progress_percentage)->toBe(30)->and($q1->work_plan[0]['accomplished_percentage'])->toBe('30.00');
    $this->post(route('project-progress.submit-prepared', ['topic' => $this->topic, 'report' => $q1]))->assertSessionHasNoErrors();

    $this->post(route('project-progress.prepare', $this->topic), ($this->payload)([
        'reporting_date' => '2026-06-30', 'work_plan' => [['source_work_plan_index' => 1, 'actual_accomplishment' => 'Completed evaluation report.']],
        'activity_evidence' => ['plan-1' => [UploadedFile::fake()->image('q2.png')]],
    ]))->assertSessionHasNoErrors()->assertRedirect();
    $q2 = ProjectProgressReport::query()->latest('id')->firstOrFail();
    expect($q2->progress_percentage)->toBe(80)->and($q2->work_plan[0]['accomplished_percentage'])->toBe('50.00');
    $path = tempnam(sys_get_temp_dir(), 'evidence-docx-');
    try {
        file_put_contents($path, $this->generatedDocx);
        $zip = new ZipArchive;
        expect($zip->open($path))->toBeTrue();
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        expect(strip_tags($xml))->toContain('80%');
    } finally {
        unlink($path);
    }
    $file = $q2->work_plan[0]['evidence'][0];
    $url = route('project-progress.evidence', ['topic' => $this->topic, 'evidence' => $file['id']]);
    $this->get($url)->assertSuccessful();
    $other = User::factory()->create();
    $other->assignRole('faculty_researcher');
    $this->actingAs($other)->get($url)->assertForbidden();
});

test('activities spanning Q1 and Q2 carry evidence and are counted once', function () {
    $this->plan->update(['source_data' => ['total_duration_months' => 6, 'entries' => [
        ['objective' => 'Collect responses', 'activity' => 'Conduct interviews', 'expected_output' => '20 interviews', 'months' => [1, 2, 3, 4, 5, 6]],
    ]]]);
    $this->actingAs($this->researcher)->post(route('project-progress.prepare', $this->topic), ($this->payload)([
        'activity_evidence' => ['plan-0' => [UploadedFile::fake()->image('q1.png')]],
    ]))->assertSessionHasNoErrors();
    $q1 = ProjectProgressReport::query()->firstOrFail();
    $this->post(route('project-progress.submit-prepared', ['topic' => $this->topic, 'report' => $q1]))->assertSessionHasNoErrors();
    $page = $this->get(route('project-progress.create', ['topic' => $this->topic, 'reporting_date' => '2026-06-30']))->assertSuccessful();
    expect($page->viewData('initialWorkPlanRows')[0]['completed_units'])->toBe(12);
    $this->post(route('project-progress.prepare', $this->topic), ($this->payload)([
        'reporting_date' => '2026-06-30', 'work_plan' => [['completed_units' => 20, 'evidence_ids' => [$q1->work_plan[0]['evidence'][0]['id']]]],
        'activity_evidence' => ['plan-0' => [UploadedFile::fake()->image('q2.png', 20, 20)]],
    ]))->assertSessionHasNoErrors();
    expect(ProjectProgressReport::query()->latest('id')->firstOrFail()->progress_percentage)->toBe(100);
});

test('removing the automatic mode flag cannot bypass evidence calculation for an approved plan', function () {
    $payload = ($this->payload)();
    unset($payload['progress_mode']);
    $this->actingAs($this->researcher)->postJson(route('project-progress.draft', $this->topic), $payload)
        ->assertSuccessful()->assertJsonPath('work_plan.0.accomplished_percentage', '0.00');
});

test('invalid reporting dates return validation errors while preparing evidence', function () {
    $this->actingAs($this->researcher)->postJson(route('project-progress.draft', $this->topic), ($this->payload)(['reporting_date' => 'broken']))
        ->assertUnprocessable()->assertJsonValidationErrors('reporting_date');
});

test('unapproved legacy form inputs cannot inject evidence file paths', function () {
    $this->plan->delete();
    $payload = ($this->payload)(['work_plan' => [['physical_target' => '20 interviews', 'percent_weight' => 50, 'evidence' => [[
        'id' => (string) Str::uuid(), 'path' => 'private-file', 'name' => 'private.pdf',
    ]]]]]);
    unset($payload['progress_mode']);
    $this->actingAs($this->researcher)->postJson(route('project-progress.draft', $this->topic), $payload)
        ->assertSuccessful()->assertJsonCount(0, 'work_plan.0.evidence')->assertJsonPath('work_plan.0.accomplished_percentage', '0.00');
});

test('eleven evidenced activities can reach 100 percent without floating point validation errors', function () {
    $entries = [];
    $rows = [];
    $files = [];
    foreach (range(0, 10) as $index) {
        $entries[] = ['objective' => 'Objective '.$index, 'activity' => 'Activity '.$index, 'expected_output' => 'Completed output '.$index, 'months' => [1]];
        $rows[] = ['source_work_plan_index' => $index, 'actual_accomplishment' => 'Completed output '.$index];
        $files['plan-'.$index] = [UploadedFile::fake()->image('proof-'.$index.'.png')];
    }
    $this->plan->update(['source_data' => ['total_duration_months' => 6, 'entries' => $entries]]);
    $payload = ($this->payload)();
    $payload['work_plan'] = $rows;
    $payload['activity_evidence'] = $files;
    $this->actingAs($this->researcher)->post(route('project-progress.prepare', $this->topic), $payload)
        ->assertSessionHasNoErrors()->assertRedirect();
    expect(ProjectProgressReport::query()->firstOrFail()->progress_percentage)->toBe(100);
});
