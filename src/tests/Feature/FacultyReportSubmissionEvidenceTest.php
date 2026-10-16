<?php

use App\Models\ProjectNarrativeReport;
use App\Models\ProjectProgressReport;
use App\Models\TopicProposal;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 7, 1));
    Notification::fake();
    Storage::fake('local');
    Role::findOrCreate('faculty_researcher', 'web');
    Role::findOrCreate('research_head', 'web');
    $this->researcher = User::factory()->create();
    $this->researcher->assignRole('faculty_researcher');
    $this->topic = TopicProposal::create([
        'user_id' => $this->researcher->id, 'title' => 'Faculty submission evidence project',
        'status' => 'approved', 'project_status' => 'ongoing', 'estimated_duration_months' => 6,
        'notice_to_proceed_issued_at' => '2026-01-01',
        'notice_to_proceed_data' => ['approved_start_date' => '2026-01-01', 'approved_duration_months' => 6],
    ]);
    $this->proof = function (string $kind): array {
        $upload = UploadedFile::fake()->image('completed-work.png');
        $path = $upload->store(($kind === 'monitoring' ? 'progress-reports' : 'narrative-progress-reports').'/'.$this->topic->id.'/evidence', 'local');

        return ['id' => (string) Str::uuid(), 'path' => $path, 'name' => 'completed-work.png',
            'mime_type' => $upload->getMimeType(), 'size' => $upload->getSize(),
            'checksum' => hash_file('sha256', $upload->getRealPath())];
    };
    $this->preparedReport = function (string $kind, array $rows): ProjectProgressReport|ProjectNarrativeReport {
        $pdf = 'prepared/'.$kind.'.pdf';
        Storage::disk('local')->put($pdf, '%PDF-1.7 Prepared faculty report');
        $base = ['topic_id' => $this->topic->id, 'submitted_by' => $this->researcher->id,
            'reporting_date' => '2026-03-31', 'reporting_quarter' => 1, 'version_number' => 1,
            'period_start' => '2026-01-01', 'period_end' => '2026-03-31',
            'submission_status' => 'prepared', 'official_pdf_path' => $pdf, 'prepared_at' => now()];

        return $kind === 'monitoring'
            ? ProjectProgressReport::create([...$base, 'reporting_year' => 2026, 'progress_percentage' => 30,
                'accomplishments' => 'Completed interviews.', 'work_plan' => $rows])
            : ProjectNarrativeReport::create([...$base, 'report_type' => 'progress', 'submission_date' => '2026-07-01',
                'researchers' => $this->researcher->name, 'implementation_start' => '2026-01-01', 'implementation_end' => '2026-06-30',
                'budget' => 1000, 'funding_agency' => 'University', 'accomplishment_summary' => 'Completed interviews.',
                'introduction' => 'Background.', 'rationale' => 'Rationale.', 'objectives' => 'Collect responses.',
                'methodology' => 'Interviews.', 'results_discussion' => 'Results.', 'photos' => [], 'accomplishments' => $rows]);
    };
    $this->submissionRoute = fn (string $kind, ProjectProgressReport|ProjectNarrativeReport $report): string => route(
        $kind === 'monitoring' ? 'project-progress.submit-prepared' : 'project-narrative-reports.submit-prepared', [$this->topic, $report],
    );
});

test('final faculty submission rejects missing deleted or invalid saved proof despite posted evidence', function (string $kind, string $failure) {
    $file = ($this->proof)($kind);
    $proof = match ($failure) {
        'missing' => [],
        'deleted' => [$file],
        'wrong source' => [($this->proof)($kind === 'monitoring' ? 'narrative' : 'monitoring')],
        'wrong project' => [[...$file, 'path' => str_replace('/'.$this->topic->id.'/evidence/', '/999999/evidence/', $file['path'])]],
        'altered' => [$file],
    };
    if ($failure === 'deleted') {
        Storage::disk('local')->delete($file['path']);
    } elseif ($failure === 'wrong project') {
        Storage::disk('local')->copy($file['path'], $proof[0]['path']);
    } elseif ($failure === 'altered') {
        Storage::disk('local')->put($file['path'], 'Changed contents');
    }
    $rows = [$kind === 'monitoring'
        ? ['activity' => 'Conduct interviews', 'accomplished_percentage' => 30, 'completed_units' => 12, 'evidence' => $proof]
        : ['objective' => 'Collect responses', 'actual' => 'Completed twelve interviews.', 'evidence' => $proof]];
    $report = ($this->preparedReport)($kind, $rows);
    $postedProof = ($this->proof)($kind);
    $response = $this->actingAs($this->researcher)->post(($this->submissionRoute)($kind, $report), [
        'work_plan' => [['accomplished_percentage' => 0, 'completed_units' => 0, 'evidence' => [$postedProof]]],
        'accomplishments' => [['actual' => '', 'evidence' => [$postedProof]]],
    ])->assertRedirect();
    if ($kind === 'narrative') {
        $response->assertSessionHasErrorsIn('narrativeProgress', 'preparation');
    } else {
        $response->assertSessionHasErrors('preparation');
    }
    expect($report->fresh()->isPrepared())->toBeTrue()->and($report->fresh()->submitted_at)->toBeNull();
    Notification::assertNothingSent();
})->with(['monitoring', 'narrative'])->with(['missing', 'deleted', 'wrong source', 'wrong project', 'altered']);

test('final faculty submission requires proof for every claimed row', function (string $kind) {
    $file = ($this->proof)($kind);
    $rows = $kind === 'monitoring'
        ? [['activity' => 'First activity', 'accomplished_percentage' => 30, 'evidence' => [$file]],
            ['activity' => 'Second activity', 'accomplished_percentage' => 0, 'completed_units' => 1, 'evidence' => []]]
        : [['objective' => 'First objective', 'actual' => 'Completed interviews.', 'evidence' => [$file]],
            ['objective' => 'Second objective', 'actual' => 'Completed analysis.', 'evidence' => []]];
    $report = ($this->preparedReport)($kind, $rows);
    $response = $this->actingAs($this->researcher)->post(($this->submissionRoute)($kind, $report));
    if ($kind === 'narrative') {
        $response->assertSessionHasErrorsIn('narrativeProgress', 'preparation');
    } else {
        $response->assertSessionHasErrors('preparation');
    }
    expect($report->fresh()->isPrepared())->toBeTrue();
})->with(['monitoring', 'narrative']);

test('final faculty submission accepts saved proof and permits rows with no claimed progress', function (string $kind) {
    $file = ($this->proof)($kind);
    $rows = $kind === 'monitoring'
        ? [['activity' => 'Completed activity', 'accomplished_percentage' => 30, 'completed_units' => 12, 'evidence' => [$file]],
            ['activity' => 'Future activity', 'accomplished_percentage' => 0, 'completed_units' => 0, 'evidence' => []]]
        : [['objective' => 'Completed objective', 'actual' => 'Completed interviews.', 'evidence' => [$file]],
            ['objective' => 'Future objective', 'actual' => '  ', 'evidence' => []]];
    $report = ($this->preparedReport)($kind, $rows);
    $this->actingAs($this->researcher)->post(($this->submissionRoute)($kind, $report))->assertSessionHasNoErrors();
    expect($report->fresh()->isSubmitted())->toBeTrue()->and($report->fresh()->submitted_at)->not->toBeNull();
})->with(['monitoring', 'narrative']);

test('incomplete prepared faculty reports can always be discarded without proof', function (string $kind) {
    $rows = [$kind === 'monitoring'
        ? ['activity' => 'Conduct interviews', 'accomplished_percentage' => 30, 'completed_units' => 12, 'evidence' => []]
        : ['objective' => 'Collect responses', 'actual' => 'Completed interviews.', 'evidence' => []]];
    $report = ($this->preparedReport)($kind, $rows);
    if ($kind === 'monitoring') {
        $this->topic->update(['research_secretary_id' => $this->researcher->id]);
    }
    $this->actingAs($this->researcher)->delete(route(
        $kind === 'monitoring' ? 'project-progress.discard-prepared' : 'project-narrative-reports.discard-prepared', [$this->topic, $report],
    ))->assertSessionHasNoErrors();
    $this->assertModelMissing($report);
})->with(['monitoring', 'narrative']);
