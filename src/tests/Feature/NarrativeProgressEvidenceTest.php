<?php

use App\Contracts\DocumentPdfConverter;
use App\Models\ProjectNarrativeReport;
use App\Models\ProjectNarrativeReportDraft;
use App\Models\TopicProposal;
use App\Models\User;
use App\Support\ProgressReportData;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 7, 1));
    $this->withoutVite();
    Notification::fake();
    Storage::fake('local');
    Role::findOrCreate('faculty_researcher', 'web');
    Role::findOrCreate('research_head', 'web');
    $this->researcher = User::factory()->create();
    $this->researcher->assignRole('faculty_researcher');
    $this->head = User::factory()->create();
    $this->head->assignRole('research_head');
    $this->topic = TopicProposal::create([
        'user_id' => $this->researcher->id, 'title' => 'Narrative evidence project',
        'status' => 'approved', 'project_status' => 'ongoing',
        'estimated_budget' => 50000, 'estimated_duration_months' => 6,
        'notice_to_proceed_issued_at' => '2026-01-01', 'notice_to_proceed_issued_by' => $this->head->id,
        'notice_to_proceed_data' => ['approved_start_date' => '2026-01-01', 'approved_duration_months' => 6],
    ]);
    $this->evidenceKey = 'row-00000000-0000-4000-8000-000000000001';
    $this->payload = fn (array $changes = []): array => array_replace_recursive([
        'draft_version' => 0, 'report_type' => 'progress', 'reporting_date' => '2026-03-31',
        'submission_date' => '2026-07-01', 'researchers' => $this->researcher->name,
        'implementation_start' => '2026-01-01', 'implementation_end' => '2026-06-30',
        'funding_agency' => 'Batangas State University', 'accomplishments' => [[
            'evidence_key' => $this->evidenceKey, 'objective' => 'Collect research responses',
            'target' => '20 interviews', 'actual' => 'Interviewed 12 participants.',
        ]],
        'introduction' => 'Research project background.', 'rationale' => 'Interviews inform the study.',
        'objectives' => 'Collect research responses.', 'methodology' => 'Conduct participant interviews.',
        'results_discussion' => 'Twelve interviews were completed.',
    ], $changes);
    $this->pdfConverter = new class implements DocumentPdfConverter
    {
        public string $sourceDocument = '';

        public bool $fail = false;

        public function convertDocx(string $contents): string
        {
            $this->sourceDocument = $contents;
            if ($this->fail) {
                throw new RuntimeException('Simulated PDF converter failure.');
            }

            return '%PDF-1.7 Narrative progress report';
        }

        public function convertXlsx(string $contents): string
        {
            return '%PDF-1.7 Spreadsheet';
        }
    };
    app()->instance(DocumentPdfConverter::class, $this->pdfConverter);
});

test('narrative accomplishment evidence uploads, survives reloading, and can be removed', function () {
    $response = $this->actingAs($this->researcher)->postJson(route('project-narrative-reports.draft', $this->topic), ($this->payload)([
        'accomplishment_evidence' => [$this->evidenceKey => [UploadedFile::fake()->image('interviews.png')]],
    ]))->assertSuccessful()->assertJsonPath('draft_version', 1)
        ->assertJsonPath('accomplishments.0.evidence_key', $this->evidenceKey)
        ->assertJsonPath('accomplishments.0.evidence.0.name', 'interviews.png');
    $file = $response->json('accomplishments.0.evidence.0');
    Storage::disk('local')->assertExists($file['path']);
    expect(ProjectNarrativeReportDraft::query()->sole()->source_data['accomplishments'][0]['evidence'][0]['id'])->toBe($file['id']);
    $url = route('project-narrative-reports.evidence', ['topic' => $this->topic, 'evidence' => $file['id']]);
    $this->get($url)->assertSuccessful()->assertDownload('interviews.png');
    $page = $this->get(route('project-narrative-reports.create', ['topic' => $this->topic, 'reporting_date' => '2026-03-31']))
        ->assertSuccessful()->assertSee('interviews.png');
    if ($fixture = getenv('ATHENA_NARRATIVE_EVIDENCE_FIXTURE')) {
        $document = new DOMDocument;
        @$document->loadHTML(preg_replace('/(?<=\s)@([a-z][\w.-]*)\s*=/', 'x-on:$1=', $page->getContent()));
        $workspace = (new DOMXPath($document))->query('//*[@data-monitoring-paper-workspace]')->item(0);
        file_put_contents($fixture, $document->saveHTML($workspace));
    }

    $this->postJson(route('project-narrative-reports.draft', $this->topic), ($this->payload)([
        'draft_version' => 1, 'accomplishments' => [['evidence_ids' => [$file['id']]]],
    ]))->assertSuccessful()->assertJsonCount(1, 'accomplishments.0.evidence');
    $version = ProjectNarrativeReportDraft::query()->sole()->lock_version;
    $this->postJson(route('project-narrative-reports.draft', $this->topic), ($this->payload)([
        'draft_version' => $version, 'accomplishments' => [['evidence_ids' => []]],
    ]))->assertSuccessful()->assertJsonCount(0, 'accomplishments.0.evidence');
    Storage::disk('local')->assertMissing($file['path']);
    $this->get($url)->assertNotFound();
});

test('approved narrative objectives retain their evidence when client labels are changed', function () {
    $version = $this->topic->versions()->create([
        'submitted_by' => $this->researcher->id, 'version_number' => 1,
        'submission_type' => 'initial', 'title' => $this->topic->title,
        'file_path' => 'proposal.pdf', 'original_filename' => 'proposal.pdf', 'mime_type' => 'application/pdf',
        'file_size' => 100, 'checksum' => hash('sha256', 'proposal'),
    ]);
    $version->files()->create([
        'document_type' => 'work_plan', 'position' => 0, 'file_path' => 'work-plan.pdf',
        'original_filename' => 'work-plan.pdf', 'mime_type' => 'application/pdf', 'file_size' => 100,
        'checksum' => hash('sha256', 'work-plan'), 'source_data' => ['total_duration_months' => 6, 'entries' => [
            ['objective' => 'Collect approved responses', 'activity' => 'Conduct interviews', 'expected_output' => '20 approved interviews', 'months' => [1, 2, 3]],
        ]],
    ]);
    $key = app(ProgressReportData::class)->defaults($this->topic->fresh())['accomplishments'][0]['evidence_key'];
    $this->actingAs($this->researcher)->postJson(route('project-narrative-reports.draft', $this->topic), ($this->payload)([
        'accomplishments' => [['evidence_key' => $key, 'objective' => 'Forged objective', 'target' => 'Forged target']],
        'accomplishment_evidence' => [$key => [UploadedFile::fake()->image('approved-response.png')]],
    ]))->assertSuccessful()->assertJsonPath('accomplishments.0.objective', 'Collect approved responses')
        ->assertJsonPath('accomplishments.0.target', '20 approved interviews')
        ->assertJsonPath('accomplishments.0.evidence.0.name', 'approved-response.png');
});

test('narrative evidence rejects unsupported oversized and unlinked uploads', function (string $kind, string $error) {
    $file = match ($kind) {
        'unsupported' => UploadedFile::fake()->create('program.exe', 1),
        'oversized' => UploadedFile::fake()->create('large.pdf', 10241, 'application/pdf'),
        default => UploadedFile::fake()->image('unlinked.png'),
    };
    $key = $kind === 'unlinked' ? 'row-00000000-0000-4000-8000-000000000099' : $this->evidenceKey;
    $this->actingAs($this->researcher)->postJson(route('project-narrative-reports.draft', $this->topic), ($this->payload)([
        'accomplishment_evidence' => [$key => [$file]],
    ]))->assertUnprocessable()->assertJsonValidationErrors($error);
    expect(Storage::disk('local')->allFiles('narrative-progress-reports'))->toBe([]);
})->with([
    ['unsupported', 'accomplishment_evidence.row-00000000-0000-4000-8000-000000000001.0'],
    ['oversized', 'accomplishment_evidence.row-00000000-0000-4000-8000-000000000001.0'],
    ['unlinked', 'accomplishment_evidence'],
]);

test('narrative evidence cannot be read or reused outside its private project draft', function () {
    $saved = $this->actingAs($this->researcher)->postJson(route('project-narrative-reports.draft', $this->topic), ($this->payload)([
        'accomplishment_evidence' => [$this->evidenceKey => [UploadedFile::fake()->image('private.png')]],
    ]))->assertSuccessful();
    $file = $saved->json('accomplishments.0.evidence.0');
    $url = route('project-narrative-reports.evidence', ['topic' => $this->topic, 'evidence' => $file['id']]);
    $other = User::factory()->create();
    $other->assignRole('faculty_researcher');
    $this->actingAs($other)->get($url)->assertNotFound();
    $this->actingAs($this->head)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD])->get($url)->assertNotFound();
    $this->actingAs($this->researcher)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY_RESEARCHER]);
    $otherTopic = $this->topic->replicate();
    $otherTopic->title = 'Another evidence project';
    $otherTopic->save();
    $this->postJson(route('project-narrative-reports.draft', $otherTopic), ($this->payload)([
        'accomplishments' => [['evidence_ids' => [$file['id']]]],
    ]))->assertUnprocessable()->assertJsonValidationErrors('accomplishments.0.evidence_ids.0');
    $this->get(route('project-narrative-reports.evidence', ['topic' => $otherTopic, 'evidence' => $file['id']]))->assertNotFound();
    Storage::disk('local')->assertExists($file['path']);
});

test('client supplied narrative evidence paths cannot attach a private file', function () {
    Storage::disk('local')->put('private-secret.pdf', 'Private contents');
    $this->actingAs($this->researcher)->postJson(route('project-narrative-reports.draft', $this->topic), ($this->payload)([
        'accomplishments' => [['evidence' => [['id' => (string) Str::uuid(), 'path' => 'private-secret.pdf', 'name' => 'private.pdf']]]],
    ]))->assertSuccessful()->assertJsonCount(0, 'accomplishments.0.evidence');
    Storage::disk('local')->assertExists('private-secret.pdf');
});

test('a stale narrative draft removes new uploads and keeps saved evidence', function () {
    $route = route('project-narrative-reports.draft', $this->topic);
    $saved = $this->actingAs($this->researcher)->postJson($route, ($this->payload)([
        'accomplishment_evidence' => [$this->evidenceKey => [UploadedFile::fake()->image('saved.png')]],
    ]))->assertSuccessful();
    $paths = Storage::disk('local')->allFiles('narrative-progress-reports');
    $this->postJson($route, ($this->payload)([
        'accomplishments' => [['evidence_ids' => [$saved->json('accomplishments.0.evidence.0.id')]]],
        'accomplishment_evidence' => [$this->evidenceKey => [UploadedFile::fake()->image('stale.png', 20, 20)]],
    ]))->assertUnprocessable()->assertJsonValidationErrors('draft_version');
    expect(Storage::disk('local')->allFiles('narrative-progress-reports'))->toBe($paths);
});

test('narrative proof stays outside paper previews and generated documents and remains available for review', function () {
    $saved = $this->actingAs($this->researcher)->postJson(route('project-narrative-reports.draft', $this->topic), ($this->payload)([
        'accomplishment_evidence' => [$this->evidenceKey => [UploadedFile::fake()->image('proof-only.png')]],
    ]))->assertSuccessful();
    $file = $saved->json('accomplishments.0.evidence.0');
    $payload = ($this->payload)(['accomplishments' => [['evidence_ids' => [$file['id']]]]]);
    $this->post(route('project-narrative-reports.preview', $this->topic), $payload)
        ->assertSuccessful()->assertSee('Interviewed 12 participants.')
        ->assertDontSee('proof-only.png')->assertDontSee($file['id'])->assertDontSee($file['path']);
    $this->post(route('project-narrative-reports.prepare', $this->topic), $payload)->assertSessionHasNoErrors()->assertRedirect();
    $report = ProjectNarrativeReport::query()->sole();
    expect($report->photos)->toBe([])
        ->and($report->accomplishments[0]['evidence'][0]['id'])->toBe($file['id'])
        ->and(ProjectNarrativeReportDraft::query()->count())->toBe(0);
    $path = tempnam(sys_get_temp_dir(), 'narrative-evidence-docx-');
    $zip = new ZipArchive;
    $archiveIsOpen = false;
    try {
        file_put_contents($path, $this->pdfConverter->sourceDocument);
        expect($zip->open($path))->toBeTrue();
        $archiveIsOpen = true;
        $xml = $zip->getFromName('word/document.xml');
        expect(strip_tags($xml))->toContain('Interviewed 12 participants.')
            ->not->toContain('proof-only.png', $file['id'], $file['path']);
        $mediaChecksums = collect(range(0, $zip->numFiles - 1))->map(fn (int $index): string => $zip->getNameIndex($index))
            ->filter(fn (string $name): bool => str_starts_with($name, 'word/media/'))
            ->map(fn (string $name): string => hash('sha256', $zip->getFromName($name)))->all();
        expect($mediaChecksums)->not->toContain($file['checksum']);
    } finally {
        if ($archiveIsOpen) {
            $zip->close();
        }
        unlink($path);
    }
    $this->get(route('project-narrative-reports.download', $report))->assertSuccessful()->assertDownload($report->official_pdf_filename)
        ->assertStreamedContent('%PDF-1.7 Narrative progress report');
    $url = route('project-narrative-reports.evidence', ['topic' => $this->topic, 'evidence' => $file['id']]);
    $this->actingAs($this->head)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD])->get($url)->assertForbidden();
    $other = User::factory()->create();
    $other->assignRole('faculty_researcher');
    $this->actingAs($other)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY_RESEARCHER])->get($url)->assertForbidden();
    $this->actingAs($this->researcher);
    $this->post(route('project-narrative-reports.submit-prepared', ['topic' => $this->topic, 'report' => $report]))->assertSessionHasNoErrors();
    $this->actingAs($this->head)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD])
        ->get($url)->assertSuccessful()->assertDownload('proof-only.png');
    $this->get(route('project-narrative-reports.show', $report))->assertSuccessful()->assertSee('proof-only.png');
    $this->actingAs($other)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY_RESEARCHER])->get($url)->assertForbidden();
});

test('failed narrative PDF preparation removes new evidence while keeping draft evidence', function () {
    Exceptions::fake();
    $saved = $this->actingAs($this->researcher)->postJson(route('project-narrative-reports.draft', $this->topic), ($this->payload)([
        'accomplishment_evidence' => [$this->evidenceKey => [UploadedFile::fake()->image('draft.png')]],
    ]))->assertSuccessful();
    $paths = Storage::disk('local')->allFiles('narrative-progress-reports');
    $this->pdfConverter->fail = true;
    $this->post(route('project-narrative-reports.prepare', $this->topic), ($this->payload)([
        'accomplishments' => [['evidence_ids' => [$saved->json('accomplishments.0.evidence.0.id')]]],
        'accomplishment_evidence' => [$this->evidenceKey => [UploadedFile::fake()->image('new.png', 20, 20)]],
    ]))->assertSessionHasErrors('preparation', errorBag: 'narrativeProgress');
    expect(ProjectNarrativeReport::query()->count())->toBe(0)
        ->and(ProjectNarrativeReportDraft::query()->count())->toBe(1)
        ->and(Storage::disk('local')->allFiles('narrative-progress-reports'))->toBe($paths);
    Exceptions::assertReported(RuntimeException::class);
});

test('discarding a prepared narrative report deletes its unreferenced evidence', function () {
    $this->actingAs($this->researcher)->post(route('project-narrative-reports.prepare', $this->topic), ($this->payload)([
        'accomplishment_evidence' => [$this->evidenceKey => [UploadedFile::fake()->image('discard.png')]],
    ]))->assertSessionHasNoErrors();
    $report = ProjectNarrativeReport::query()->sole();
    $file = $report->accomplishments[0]['evidence'][0];
    Storage::disk('local')->assertExists($file['path']);
    $this->delete(route('project-narrative-reports.discard-prepared', ['topic' => $this->topic, 'report' => $report]))->assertSessionHasNoErrors();
    Storage::disk('local')->assertMissing($file['path']);
    $this->get(route('project-narrative-reports.evidence', ['topic' => $this->topic, 'evidence' => $file['id']]))->assertNotFound();
});

test('narrative evidence uploads require a reporting quarter', function () {
    $this->actingAs($this->researcher)->postJson(route('project-narrative-reports.draft', $this->topic), ($this->payload)([
        'reporting_date' => null,
        'accomplishment_evidence' => [$this->evidenceKey => [UploadedFile::fake()->image('unassigned.png')]],
    ]))->assertUnprocessable()->assertJsonValidationErrors('reporting_date');
    expect(Storage::disk('local')->allFiles('narrative-progress-reports'))->toBe([]);
});

test('clearing a draft quarter requires removing evidence and then cleans up its saved file', function () {
    $route = route('project-narrative-reports.draft', $this->topic);
    $saved = $this->actingAs($this->researcher)->postJson($route, ($this->payload)([
        'accomplishment_evidence' => [$this->evidenceKey => [UploadedFile::fake()->image('assigned.png')]],
    ]))->assertSuccessful();
    $file = $saved->json('accomplishments.0.evidence.0');
    $this->postJson($route, ($this->payload)([
        'draft_version' => 1, 'reporting_date' => null, 'accomplishments' => [['evidence_ids' => [$file['id']]]],
    ]))->assertUnprocessable()->assertJsonValidationErrors('reporting_date');
    Storage::disk('local')->assertExists($file['path']);
    $this->postJson($route, ($this->payload)([
        'draft_version' => 1, 'reporting_date' => null, 'accomplishments' => [['evidence_ids' => []]],
    ]))->assertSuccessful()->assertJsonCount(0, 'accomplishments.0.evidence');
    Storage::disk('local')->assertMissing($file['path']);
});

test('narrative corrections restore supporting evidence and keep proof referenced by historical submissions', function () {
    $this->actingAs($this->researcher)->post(route('project-narrative-reports.prepare', $this->topic), ($this->payload)([
        'accomplishment_evidence' => [$this->evidenceKey => [UploadedFile::fake()->image('original-proof.png')]],
    ]))->assertSessionHasNoErrors();
    $original = ProjectNarrativeReport::query()->sole();
    $file = $original->accomplishments[0]['evidence'][0];
    $this->post(route('project-narrative-reports.submit-prepared', ['topic' => $this->topic, 'report' => $original]))->assertSessionHasNoErrors();
    $original->update(['review_status' => ProjectNarrativeReport::STATUS_REVISION_REQUESTED]);
    $this->get(route('project-narrative-reports.create', ['topic' => $this->topic, 'reporting_date' => '2026-03-31']))
        ->assertSuccessful()->assertSee('original-proof.png')->assertSee('Interviewed 12 participants.');
    $payload = ($this->payload)(['accomplishments' => [['evidence_ids' => [$file['id']], 'actual' => 'Updated interview results.']]]);
    $this->postJson(route('project-narrative-reports.draft', $this->topic), $payload)
        ->assertSuccessful()->assertJsonPath('accomplishments.0.evidence.0.id', $file['id']);
    $this->post(route('project-narrative-reports.prepare', $this->topic), $payload)->assertSessionHasNoErrors();
    $replacement = ProjectNarrativeReport::query()->latest('id')->firstOrFail();
    expect($replacement->version_number)->toBe(2)
        ->and($replacement->accomplishments[0]['actual'])->toBe('Updated interview results.')
        ->and($replacement->accomplishments[0]['evidence'][0]['id'])->toBe($file['id']);
    $this->delete(route('project-narrative-reports.discard-prepared', ['topic' => $this->topic, 'report' => $replacement]))->assertSessionHasNoErrors();
    Storage::disk('local')->assertExists($file['path']);
    $this->get(route('project-narrative-reports.evidence', ['topic' => $this->topic, 'evidence' => $file['id']]))
        ->assertSuccessful()->assertDownload('original-proof.png');
});
