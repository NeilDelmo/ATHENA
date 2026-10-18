<?php

use App\Models\ProjectDocument;
use App\Models\ProjectNarrativeReport;
use App\Models\ProjectProgressReport;
use App\Models\TopicProposal;
use App\Models\User;
use App\Services\ProjectDocumentLibrary;
use App\Services\ProjectReportEvidence;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Storage::fake('local');
    $this->withoutVite();
    foreach (['faculty_researcher', 'research_head', 'research_coordinator'] as $role) {
        Role::findOrCreate($role, 'web');
    }
    $this->researcher = User::factory()->create(['college' => 'College of Engineering']);
    $this->researcher->assignRole('faculty_researcher');
    $this->coordinator = User::factory()->create(['college' => 'College of Engineering']);
    $this->coordinator->assignRole('research_coordinator');
    $this->topic = TopicProposal::create([
        'user_id' => $this->researcher->id, 'title' => 'Interview study',
        'status' => 'approved', 'project_status' => 'ongoing',
        'notice_to_proceed_issued_at' => '2026-02-15',
    ]);
    $this->proof = function (string $kind, string $name = 'proof.png'): array {
        $prefix = $kind === 'monitoring' ? 'progress-reports' : 'narrative-progress-reports';
        $path = $prefix.'/'.$this->topic->id.'/evidence/'.Str::uuid().'/'.$name;
        $upload = UploadedFile::fake()->image('proof.png');
        $contents = str_ends_with($name, '.docx')
            ? file_get_contents(resource_path('documents/BatStateU-REC-RES-03-Monitoring-Tool.docx'))
            : file_get_contents($upload->getRealPath());
        Storage::disk('local')->put($path, $contents);

        return ['id' => (string) Str::uuid(), 'path' => $path, 'name' => $name,
            'mime_type' => Storage::disk('local')->mimeType($path), 'size' => strlen($contents), 'checksum' => hash('sha256', $contents)];
    };
    $this->report = function (string $kind, array $files, array $changes = []): ProjectProgressReport|ProjectNarrativeReport {
        $base = ['topic_id' => $this->topic->id, 'submitted_by' => $this->researcher->id,
            'reporting_date' => '2026-05-14', 'reporting_quarter' => 1,
            'period_start' => '2026-02-15', 'period_end' => '2026-05-14',
            'version_number' => 1, 'submission_status' => 'submitted', 'submitted_at' => now()];

        return $kind === 'monitoring'
            ? ProjectProgressReport::create([...$base, 'reporting_year' => 2026, 'progress_percentage' => 40,
                'accomplishments' => 'Completed interviews.', 'work_plan' => [['activity' => 'Conduct interviews', 'evidence' => $files]], ...$changes])
            : ProjectNarrativeReport::create([...$base, 'report_type' => 'progress', 'submission_date' => '2026-05-14',
                'researchers' => $this->researcher->name, 'implementation_start' => '2026-02-15', 'implementation_end' => '2026-08-14',
                'budget' => 1000, 'funding_agency' => 'University', 'accomplishment_summary' => 'Completed interviews.',
                'introduction' => 'Research background.', 'rationale' => 'Study rationale.', 'objectives' => 'Collect responses.',
                'methodology' => 'Interviews.', 'results_discussion' => 'Results.', 'photos' => [],
                'accomplishments' => [['objective' => 'Collect responses', 'evidence' => $files]], ...$changes]);
    };
});

test('the faculty proof source uses the latest submitted report and calendar quarter', function (string $kind) {
    $old = ($this->proof)($kind, 'old.png');
    ($this->report)($kind, [$old]);
    $current = ($this->proof)($kind, 'current.png');
    ($this->report)($kind, [$current, $current], ['version_number' => 2]);
    ($this->report)($kind, [($this->proof)($kind, 'prepared.png')], ['version_number' => 3, 'submission_status' => 'prepared']);
    ($this->report)($kind, [($this->proof)($kind, 'other-quarter.png')], ['reporting_date' => '2026-08-14', 'reporting_quarter' => 2]);
    if ($kind === 'narrative') {
        ($this->report)($kind, [($this->proof)($kind, 'terminal.png')], ['report_type' => 'terminal']);
    }
    $service = app(ProjectReportEvidence::class);
    $entries = $service->forProject($this->topic, CarbonImmutable::parse('2026-04-01'), CarbonImmutable::parse('2026-06-30'));

    expect($entries)->toHaveCount(1)
        ->and($entries[0])->toMatchArray(['path' => $current['path'], 'original_filename' => 'current.png', 'source' => 'faculty_report',
            'project_id' => $this->topic->id, 'mime_type' => 'image/png', 'reporting_date' => '2026-05-14'])
        ->and($entries[0]['caption'])->toContain('Interview study', 'current.png', $kind === 'monitoring' ? 'Conduct interviews' : 'Collect responses')
        ->and($entries[0]['source_key'])->toMatch('/^[a-f0-9]{64}$/')
        ->and($entries[0]['url'])->toBe(route($kind === 'monitoring' ? 'project-progress.evidence' : 'project-narrative-reports.evidence', [$this->topic, $current['id']]).'?preview=1')
        ->and($service->forProject($this->topic, CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-03-31')))->toBe([]);
})->with(['monitoring', 'narrative']);

test('the faculty proof source rejects missing altered unsafe or mismatched source files', function (string $kind) {
    $valid = ($this->proof)($kind);
    $wrongSource = ($this->proof)($kind === 'monitoring' ? 'narrative' : 'monitoring');
    $altered = ($this->proof)($kind, 'altered.png');
    $replacement = UploadedFile::fake()->image('replacement.png', 20, 20);
    Storage::disk('local')->put($altered['path'], file_get_contents($replacement->getRealPath()));
    $missing = [...$valid, 'path' => dirname($valid['path']).'/missing.png'];
    $unsafe = [...$valid, 'path' => dirname($valid['path']).'/../proof.png'];
    ($this->report)($kind, [$valid, $wrongSource, $altered, $missing, $unsafe]);

    expect(array_column(app(ProjectReportEvidence::class)->forProject($this->topic), 'path'))->toBe([$valid['path']]);
})->with(['monitoring', 'narrative']);

test('report evidence reaches the head only after office secretary forwarding', function (string $kind) {
    $head = User::factory()->create();
    $head->assignRole('research_head');
    $proof = ($this->proof)($kind);
    $report = ($this->report)($kind, [$proof], ['review_stage' => 'secretary']);
    $this->actingAs($head)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD]);
    $service = app(ProjectReportEvidence::class);

    expect($service->forProject($this->topic, viewer: $head))->toBe([])
        ->and(app(ProjectDocumentLibrary::class)->build($this->topic, $head)['documents']->where('category', ProjectDocument::CATEGORY_ACCOMPLISHMENT_EVIDENCE))->toHaveCount(0);
    $this->get(route($kind === 'monitoring' ? 'project-progress.evidence' : 'project-narrative-reports.evidence', [$this->topic, $proof['id']]))->assertNotFound();

    $this->topic->update(['user_id' => $head->id]);
    $this->get(route($kind === 'monitoring' ? 'project-progress.evidence' : 'project-narrative-reports.evidence', [$this->topic, $proof['id']]))->assertNotFound();

    $report->update(['review_stage' => 'head', 'forwarded_at' => now()]);
    $this->topic->unsetRelation('progressReports')->unsetRelation('narrativeReports');
    expect($service->forProject($this->topic, viewer: $head))->toHaveCount(1);
    $this->get(route($kind === 'monitoring' ? 'project-progress.evidence' : 'project-narrative-reports.evidence', [$this->topic, $proof['id']]))->assertOk();
})->with(['monitoring', 'narrative']);

test('project files show submitted images and download only office evidence', function () {
    $image = ($this->proof)('monitoring');
    $office = ($this->proof)('narrative', 'responses.docx');
    ($this->report)('monitoring', [$image]);
    ($this->report)('narrative', [$office]);
    ($this->report)('monitoring', [($this->proof)('monitoring', 'private.png')], ['reporting_date' => '2026-08-14', 'reporting_quarter' => 2, 'submission_status' => 'prepared']);
    $documents = app(ProjectDocumentLibrary::class)->build($this->topic, $this->researcher)['documents']->where('category', ProjectDocument::CATEGORY_ACCOMPLISHMENT_EVIDENCE);

    expect($documents)->toHaveCount(2);
    $photo = $documents->firstWhere('filename', 'proof.png');
    $document = $documents->firstWhere('filename', 'responses.docx');
    expect($photo['preview_kind'])->toBe('image')->and($photo['thumbnail_url'])->toBe($photo['view_url'])
        ->and($document['view_url'])->toBeNull()->and($document['download_url'])->toContain($office['id']);
});

test('a new report awaiting office review does not hide proof already forwarded in an earlier quarter', function (string $kind) {
    $proof = ($this->proof)($kind, 'already-forwarded.png');
    ($this->report)($kind, [$proof], ['review_stage' => 'head']);
    ($this->report)($kind, [$proof], [
        'review_stage' => 'secretary', 'reporting_date' => '2026-08-14', 'reporting_quarter' => 2,
        'period_start' => '2026-05-15', 'period_end' => '2026-08-14',
    ]);
    $head = User::factory()->create();
    $head->assignRole('research_head');
    $this->actingAs($head)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD])
        ->get(route($kind === 'monitoring' ? 'project-progress.evidence' : 'project-narrative-reports.evidence', [$this->topic, $proof['id']]))
        ->assertOk()->assertDownload('already-forwarded.png');
})->with(['monitoring', 'narrative']);

test('submitted faculty proof is available to project file viewers while prepared proof stays private', function (string $kind) {
    $submitted = ($this->proof)($kind, 'shared.png');
    $prepared = ($this->proof)($kind, 'private.png');
    ($this->report)($kind, [$submitted, $prepared], ['submission_status' => 'prepared']);
    ($this->report)($kind, [$submitted], ['version_number' => 2]);
    $route = $kind === 'monitoring' ? 'project-progress.evidence' : 'project-narrative-reports.evidence';
    $sharedUrl = route($route, [$this->topic, $submitted['id']]);
    $privateUrl = route($route, [$this->topic, $prepared['id']]);

    $this->actingAs($this->coordinator)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_OFFICE])
        ->get($sharedUrl)->assertSuccessful()->assertDownload('shared.png');
    $this->get($sharedUrl.'?preview=1')->assertSuccessful()->assertHeader('Content-Type', 'image/png')
        ->assertHeader('Content-Disposition', 'inline; filename=shared.png');
    $this->get($privateUrl)->assertForbidden();
    $this->coordinator->update(['college' => 'Another college']);
    $this->get($sharedUrl)->assertForbidden();
    $this->actingAs($this->researcher)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY_RESEARCHER])
        ->get($privateUrl)->assertSuccessful()->assertDownload('private.png');
})->with(['monitoring', 'narrative']);
