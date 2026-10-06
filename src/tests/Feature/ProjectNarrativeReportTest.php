<?php

use App\Contracts\DocumentPdfConverter;
use App\Models\ProjectNarrativeReport;
use App\Models\ProjectNarrativeReportDraft;
use App\Models\ProposalSignatory;
use App\Models\ResearchCall;
use App\Models\TopicProposal;
use App\Models\User;
use App\Notifications\ProposalActivityNotification;
use App\Services\MonitoringQuarterService;
use App\Support\ProgressReportData;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Notification::fake();
    Storage::fake('local');
    $this->pdfConverter = new class implements DocumentPdfConverter
    {
        public string $sourceDocument = '';

        public int $conversionCount = 0;

        public function convertDocx(string $contents): string
        {
            $this->conversionCount++;
            $this->sourceDocument = $contents;

            return "%PDF-1.7\nGenerated progress report PDF";
        }

        public function convertXlsx(string $contents): string
        {
            return "%PDF-1.7\nGenerated spreadsheet PDF";
        }
    };
    app()->instance(DocumentPdfConverter::class, $this->pdfConverter);

    foreach (['faculty_researcher', 'research_head'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }

    $this->researcher = User::factory()->create();
    $this->researcher->assignRole('faculty_researcher');
    $this->head = User::factory()->create();
    $this->head->assignRole('research_head');
    $call = ResearchCall::create([
        'title' => 'Narrative Progress Test Call',
        'academic_year' => '2026-2027',
        'opens_at' => now()->subMonth(),
        'closes_at' => now()->addMonth(),
        'status' => 'open',
    ]);
    $this->topic = TopicProposal::create([
        'user_id' => $this->researcher->id,
        'research_call_id' => $call->id,
        'title' => 'Coastal Community Research',
        'estimated_budget' => 150000,
        'estimated_duration_months' => 12,
        'status' => 'approved',
        'project_status' => 'ongoing',
        'notice_to_proceed_issued_by' => $this->head->id,
        'notice_to_proceed_issued_at' => now()->subMonths(3),
    ]);

    $this->progressReportPayload = fn (array $overrides = []): array => array_replace([
        'reporting_date' => app(MonitoringQuarterService::class)->projectPeriods($this->topic)->first()['end']->toDateString(),
        'submission_date' => now()->toDateString(),
        'tracking_number' => 'PR-2026-001',
        'researchers' => $this->researcher->name."\nJuan Dela Cruz",
        'implementation_start' => now()->subMonths(2)->toDateString(),
        'implementation_end' => now()->addMonths(10)->toDateString(),
        'funding_agency' => 'Batangas State University',
        'accomplishments' => [[
            'objective' => 'Complete the first coastal survey.',
            'target' => 'One survey and one stakeholder consultation.',
            'actual' => 'Completed the first coastal survey and stakeholder consultation.',
        ]],
        'introduction' => 'This monitoring period covered the initial implementation activities.',
        'rationale' => 'Baseline coastal data is needed to guide evidence-based community planning.',
        'objectives' => 'Assess coastal conditions and document community practices.',
        'methodology' => 'The team conducted interviews and site observations.',
        'results_discussion' => 'Initial findings show strong community participation.',
        'photo_1' => UploadedFile::fake()->image('coastal-survey.jpg', 1600, 900),
        'photo_caption_1' => 'The research team conducting the first coastal survey.',
        'photo_section_1' => 'methodology',
        'prepared_by_date_signed' => now()->toDateString(),
    ], $overrides);
});

test('Progress Reports use the Monitoring Tool periods including a shorter final quarter', function () {
    $this->topic->update([
        'notice_to_proceed_issued_at' => '2026-01-15',
        'notice_to_proceed_data' => ['approved_start_date' => '2026-01-15', 'approved_end_date' => '2026-08-20'],
    ]);
    $this->travelTo(CarbonImmutable::parse('2026-10-02'));
    $periods = app(MonitoringQuarterService::class)->projectPeriods($this->topic);
    $final = $periods->last();
    expect($periods)->toHaveCount(3)->and($final['start']->toDateString())->toBe('2026-07-15')->and($final['end']->toDateString())->toBe('2026-08-20');
    $payload = ($this->progressReportPayload)(['reporting_date' => $final['end']->toDateString()]);
    $this->actingAs($this->researcher)->get(route('project-narrative-reports.create', $this->topic))
        ->assertOk()->assertSee('Reporting quarter')->assertSee('Q3 · Jul 15, 2026');
    $this->post(route('project-narrative-reports.preview', $this->topic), $payload)->assertOk()->assertSee('Q3 · Jul 15, 2026');
    $this->post(route('project-narrative-reports.prepare', $this->topic), $payload)->assertSessionHasNoErrors();
    $report = ProjectNarrativeReport::sole();
    expect($report->reporting_quarter)->toBe(3)->and($report->period_start->toDateString())->toBe('2026-07-15')
        ->and($report->period_end->toDateString())->toBe('2026-08-20')->and($report->version_number)->toBe(1);
    $this->get(route('project-narrative-reports.create', $this->topic))->assertOk()->assertSee($report->reporting_period_label)->assertSee('Submit to Research Head');
    $path = tempnam(sys_get_temp_dir(), 'quarterly-progress-');
    file_put_contents($path, $this->pdfConverter->sourceDocument);
    $archive = new ZipArchive;
    try {
        expect($archive->open($path))->toBeTrue();
        $documentText = html_entity_decode(strip_tags($archive->getFromName('word/document.xml')));
        expect($documentText)->toContain('Reporting period: '.$report->reporting_period_label);
    } finally {
        $archive->close();
        unlink($path);
    }
    $this->post(route('project-narrative-reports.submit-prepared', [$this->topic, $report]))->assertSessionHasNoErrors();
    $this->get(route('topics.show', $this->topic))->assertOk()->assertSee('data-progress-quarter="3"', false)->assertSee($final['period']);
    $this->get(route('project-narrative-reports.show', $report))->assertOk()->assertSee($report->reporting_period_label);
});

test('Progress Reports reject missing future and out-of-project quarters', function () {
    $this->actingAs($this->researcher);
    $periods = app(MonitoringQuarterService::class)->projectPeriods($this->topic);
    foreach ([null, '2000-01-01', $periods->last()['end']->toDateString()] as $date) {
        $this->post(route('project-narrative-reports.prepare', $this->topic), ($this->progressReportPayload)(['reporting_date' => $date]))
            ->assertSessionHasErrorsIn('narrativeProgress', 'reporting_date');
    }
    $this->post(route('project-narrative-reports.prepare', $this->topic), ($this->progressReportPayload)(['submission_date' => $periods->first()['end']->toDateString()]))
        ->assertSessionHasErrorsIn('narrativeProgress', 'submission_date');
    expect(ProjectNarrativeReport::count())->toBe(0)->and($this->pdfConverter->conversionCount)->toBe(0);
});

test('only corrections create a replacement Progress Report for an already submitted quarter', function () {
    $this->actingAs($this->researcher)->post(route('project-narrative-reports.prepare', $this->topic), ($this->progressReportPayload)())->assertSessionHasNoErrors();
    $original = ProjectNarrativeReport::sole();
    $this->post(route('project-narrative-reports.prepare', $this->topic), ($this->progressReportPayload)())->assertSessionHasErrorsIn('narrativeProgress');
    $this->post(route('project-narrative-reports.submit-prepared', [$this->topic, $original]))->assertSessionHasNoErrors();
    $this->post(route('project-narrative-reports.prepare', $this->topic), ($this->progressReportPayload)())->assertSessionHasErrorsIn('narrativeProgress', 'reporting_date');
    $original->update(['review_status' => 'revision_requested']);
    $this->post(route('project-narrative-reports.prepare', $this->topic), ($this->progressReportPayload)())->assertSessionHasNoErrors();
    $replacement = ProjectNarrativeReport::latest('id')->first();
    expect($replacement->version_number)->toBe(2)->and($replacement->reporting_quarter)->toBe($original->reporting_quarter)
        ->and($original->fresh()->isSubmitted())->toBeTrue()->and($original->fresh()->official_pdf_checksum)->toBe($original->official_pdf_checksum);
});

test('accepted collaborators share prepared Progress Reports but only the project leader submits them', function () {
    $collaborator = User::factory()->create();
    $collaborator->assignRole('faculty_researcher');
    $this->topic->collaborators()->create(['user_id' => $collaborator->id, 'name' => $collaborator->name, 'email' => $collaborator->email, 'accepted_at' => now()]);
    $this->actingAs($collaborator)->post(route('project-narrative-reports.prepare', $this->topic), ($this->progressReportPayload)())->assertSessionHasNoErrors();
    $report = ProjectNarrativeReport::sole();
    $path = tempnam(sys_get_temp_dir(), 'shared-progress-');
    file_put_contents($path, $this->pdfConverter->sourceDocument);
    $archive = new ZipArchive;
    try {
        expect($archive->open($path))->toBeTrue();
        $documentText = html_entity_decode(strip_tags($archive->getFromName('word/document.xml')));
        expect($documentText)->toContain(strtoupper($this->researcher->name))->not->toContain(strtoupper($collaborator->name));
    } finally {
        $archive->close();
        unlink($path);
    }
    $this->get(route('project-narrative-reports.create', $this->topic))->assertOk()->assertSee('Only the project leader can submit this report.')->assertDontSee('Submit to Research Head');
    $this->get(route('project-narrative-reports.view', $report))->assertOk();
    $this->post(route('project-narrative-reports.submit-prepared', [$this->topic, $report]))->assertForbidden();
    expect($report->fresh()->isPrepared())->toBeTrue();
    $this->actingAs($this->researcher)->get(route('project-narrative-reports.create', $this->topic))->assertOk()->assertSee('Submit to Research Head');
    $this->get(route('project-narrative-reports.view', $report))->assertOk();
    $this->post(route('project-narrative-reports.submit-prepared', [$this->topic, $report]))->assertSessionHasNoErrors();
    expect($report->fresh()->isSubmitted())->toBeTrue()->and($report->fresh()->submitted_by)->toBe($this->researcher->id);
});

test('a project owner prepares an official progress-report PDF before submitting it to the Research Head', function () {
    $this->actingAs($this->researcher)
        ->post(route('project-narrative-reports.store', $this->topic), ($this->progressReportPayload)())
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $report = ProjectNarrativeReport::firstOrFail();
    expect($report->topic_id)->toBe($this->topic->id)
        ->and($report->budget)->toBe('150000.00')
        ->and($report->photos)->toHaveCount(1)
        ->and($report->review_status)->toBe(ProjectNarrativeReport::STATUS_PENDING)
        ->and($report->submission_status)->toBe(ProjectNarrativeReport::SUBMISSION_STATUS_PREPARED);
    Storage::disk('local')->assertExists($report->photos[0]['path']);
    Storage::disk('local')->assertExists($report->official_pdf_path);
    expect($this->pdfConverter->conversionCount)->toBe(1);
    $previewResponse = $this->get(route('project-narrative-reports.view', $report))
        ->assertSuccessful()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
    expect($previewResponse->headers->get('Content-Disposition'))->toStartWith('inline')
        ->and($previewResponse->streamedContent())->toBe(Storage::disk('local')->get($report->official_pdf_path));
    Notification::assertNothingSent();

    $this->actingAs($this->researcher)
        ->post(route('project-narrative-reports.submit-prepared', [$this->topic, $report]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($report->fresh()->submission_status)->toBe(ProjectNarrativeReport::SUBMISSION_STATUS_SUBMITTED)
        ->and($this->pdfConverter->conversionCount)->toBe(1);
    Notification::assertSentTo(
        $this->head,
        ProposalActivityNotification::class,
        fn (ProposalActivityNotification $notification): bool => $notification->title === 'Progress report submitted',
    );

    $this->topic->update(['notice_to_proceed_issued_at' => null]);
    $this->actingAs($this->researcher)
        ->post(route('project-narrative-reports.store', $this->topic), ($this->progressReportPayload)())
        ->assertForbidden();
});

test('the progress report requires structured accomplishments', function () {
    $payload = ($this->progressReportPayload)([
        'accomplishments' => [],
        'photo_1' => null,
        'photo_caption_1' => '',
        'photo_section_1' => '',
    ]);

    $this->actingAs($this->researcher)
        ->post(route('project-narrative-reports.store', $this->topic), $payload)
        ->assertSessionHasErrorsIn('narrativeProgress', [
            'accomplishments',
        ]);

    expect(ProjectNarrativeReport::count())->toBe(0);
});

test('completed projects cannot preview or submit progress reports', function () {
    $this->topic->update(['project_status' => TopicProposal::PROJECT_STATUS_COMPLETED]);

    $this->actingAs($this->researcher)
        ->post(route('project-narrative-reports.preview', $this->topic), ($this->progressReportPayload)())
        ->assertForbidden();
    $this->actingAs($this->researcher)
        ->post(route('project-narrative-reports.store', $this->topic), ($this->progressReportPayload)())
        ->assertForbidden();

    expect(ProjectNarrativeReport::count())->toBe(0);
});

test('the faculty monitoring page opens the progress report in a focused form page', function () {
    $this->actingAs($this->researcher)
        ->get(route('research.show', $this->topic))
        ->assertOk()
        ->assertSee('Monitoring Tool')
        ->assertSee('Progress Report')
        ->assertSee('Start report')
        ->assertSee(route('project-narrative-reports.create', $this->topic), false)
        ->assertDontSee('data-narrative-progress-autosave-form', false);

    $this->actingAs($this->researcher)
        ->get(route('project-narrative-reports.create', $this->topic))
        ->assertOk()
        ->assertSee('Report project accomplishments')
        ->assertSee('Prepare official PDF')
        ->assertSee('Preview paper')
        ->assertSee('Exit monitoring')
        ->assertSee('data-paper-cancel-exit', false)
        ->assertSee('data-monitoring-writing-toolbar', false)
        ->assertSee('data-proposal-workspace-toolbar', false)
        ->assertDontSee('data-monitoring-action-dock-fixed', false)
        ->assertSee('x-ref="previewFrame"', false)
        ->assertSee('VI. Summary of Accomplishment for the Monitoring Period')
        ->assertSee('Target accomplishment')
        ->assertSee('VIII. Rationale')
        ->assertSee('X. Results and Discussion')
        ->assertSee('Add figure')
        ->assertSee('Changes and accomplishment evidence save privately as a draft.')
        ->assertSee('Evidence stays out of the report preview and downloaded PDF.')
        ->assertSee('data-progress-evidence', false)
        ->assertSee('data-narrative-progress-autosave-form', false);
});

test('a progress report form auto-saves a private draft without preparing an official PDF', function () {
    $payload = [
        ...($this->progressReportPayload)([
            'photo_1' => null,
        ]),
        'draft_version' => 0,
    ];

    $this->actingAs($this->researcher)
        ->postJson(route('project-narrative-reports.draft', $this->topic), $payload)
        ->assertSuccessful()
        ->assertJsonPath('draft_version', 1);

    $draft = ProjectNarrativeReportDraft::sole();

    expect($draft->topic_id)->toBe($this->topic->id)
        ->and($draft->user_id)->toBe($this->researcher->id)
        ->and($draft->source_data['tracking_number'])->toBe('PR-2026-001')
        ->and(ProjectNarrativeReport::count())->toBe(0)
        ->and($this->pdfConverter->conversionCount)->toBe(0);

    $this->actingAs($this->researcher)
        ->postJson(route('project-narrative-reports.draft', $this->topic), [
            ...$payload,
            'draft_version' => 1,
        ])
        ->assertSuccessful()
        ->assertJsonPath('draft_version', 1);

    expect($draft->fresh()->lock_version)->toBe(1);

    $this->actingAs($this->researcher)
        ->postJson(route('project-narrative-reports.draft', $this->topic), [
            ...$payload,
            'tracking_number' => 'STALE-CHANGE',
            'draft_version' => 0,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('draft_version');

    expect($draft->fresh()->source_data['tracking_number'])->toBe('PR-2026-001')
        ->and($draft->fresh()->lock_version)->toBe(1);
});

test('a researcher can preview the filled progress report without submitting it', function () {
    $this->actingAs($this->researcher)
        ->post(route('project-narrative-reports.preview', $this->topic), ($this->progressReportPayload)())
        ->assertOk()
        ->assertSee('PROGRESS REPORT')
        ->assertSee('Coastal Community Research')
        ->assertSee('Completed the first coastal survey')
        ->assertSee('Figure 1. The research team conducting the first coastal survey.')
        ->assertSee('data-preview-file-input="photo_1"', false);

    expect(ProjectNarrativeReport::count())->toBe(0);
});

test('a researcher can preview a progress quarter before it ends while official preparation stays locked', function () {
    $this->travelTo('2026-02-01');
    $this->topic->update([
        'notice_to_proceed_issued_at' => '2026-01-01',
        'notice_to_proceed_data' => ['approved_start_date' => '2026-01-01', 'approved_end_date' => '2026-12-31', 'approved_duration_months' => 12],
    ]);
    $payload = ($this->progressReportPayload)([
        'reporting_date' => '2026-03-31', 'implementation_start' => '2026-01-01', 'implementation_end' => '2026-12-31',
    ]);
    $this->actingAs($this->researcher)->get(route('project-narrative-reports.create', ['topic' => $this->topic, 'reporting_date' => '2026-03-31']))
        ->assertSuccessful()->assertSee('data-proposal-preview-toggle', false)->assertSee('submissionOpen: false', false);
    $this->postJson(route('project-narrative-reports.preview', $this->topic), $payload)
        ->assertSuccessful()->assertSee('PROGRESS REPORT')->assertSee('Completed the first coastal survey');
    $this->postJson(route('project-narrative-reports.prepare', $this->topic), $payload)->assertForbidden();
    $this->postJson(route('project-narrative-reports.preview', $this->topic), [...$payload, 'reporting_date' => '2027-03-31'])
        ->assertUnprocessable()->assertJsonValidationErrors('reporting_date');
    expect(ProjectNarrativeReport::query()->count())->toBe(0)
        ->and($this->pdfConverter->conversionCount)->toBe(0)
        ->and(Storage::disk('local')->allFiles('narrative-progress-reports'))->toBe([]);
});

test('the owner and Research Head can download the official report as a PDF and its photo', function () {
    $this->actingAs($this->researcher)
        ->post(route('project-narrative-reports.store', $this->topic), ($this->progressReportPayload)())
        ->assertSessionHasNoErrors();

    $report = ProjectNarrativeReport::firstOrFail();
    $documentResponse = $this->actingAs($this->researcher)
        ->get(route('project-narrative-reports.download', $report))
        ->assertOk()
        ->assertDownload('coastal-community-research-progress-report.pdf');
    expect($documentResponse->streamedContent())->toStartWith('%PDF-');
    $sourceDocument = $this->pdfConverter->sourceDocument;
    expect($this->pdfConverter->conversionCount)->toBe(1);
    $ownerPhoto = $this->get(route('project-narrative-reports.photos.view', [$report, 0]))
        ->assertSuccessful()
        ->assertHeader('Content-Type', 'image/jpeg')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
    expect($ownerPhoto->headers->get('Content-Disposition'))->toStartWith('inline')
        ->and($ownerPhoto->streamedContent())->toBe(Storage::disk('local')->get($report->photos[0]['path']));
    $this->actingAs($this->head)
        ->get(route('project-narrative-reports.download', $report))
        ->assertForbidden();
    $this->get(route('project-narrative-reports.photos.view', [$report, 0]))->assertForbidden();
    $this->get(route('project-narrative-reports.view', $report))->assertForbidden();

    $this->actingAs($this->researcher)
        ->post(route('project-narrative-reports.submit-prepared', [$this->topic, $report]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->actingAs($this->head)
        ->get(route('project-narrative-reports.download', $report))
        ->assertOk();
    $this->actingAs($this->head)
        ->get(route('project-narrative-reports.photos.download', [$report, 0]))
        ->assertOk()
        ->assertDownload('coastal-survey.jpg');
    expect($this->pdfConverter->conversionCount)->toBe(1);

    foreach ([$this->researcher, $this->head] as $viewer) {
        $pdfPreview = $this->actingAs($viewer)->get(route('project-narrative-reports.view', $report))
            ->assertSuccessful()->assertHeader('Content-Type', 'application/pdf');
        expect($pdfPreview->headers->get('Content-Disposition'))->toStartWith('inline')
            ->and($pdfPreview->streamedContent())->toStartWith('%PDF-');
        $photoResponse = $this->actingAs($viewer)->get(route('project-narrative-reports.photos.view', [$report, 0]))
            ->assertSuccessful()->assertHeader('Content-Type', 'image/jpeg');
        expect($photoResponse->headers->get('Content-Disposition'))->toStartWith('inline')
            ->and($photoResponse->streamedContent())->toBe(Storage::disk('local')->get($report->photos[0]['path']));
    }
    $this->get(route('project-narrative-reports.photos.view', [$report, 999]))->assertNotFound();
    $photos = $report->photos;
    unset($photos[0]['original_name']);
    $report->update(['photos' => $photos]);
    $this->get(route('project-narrative-reports.photos.download', [$report, 0]))
        ->assertSuccessful()->assertDownload(basename($photos[0]['path']));
    Storage::disk('local')->put('progress-reports/not-an-image.html', '<html>Not an image</html>');
    $report->update(['photos' => [...$photos, ['path' => 'progress-reports/not-an-image.html', 'caption' => 'Invalid attachment']]]);
    $this->get(route('project-narrative-reports.photos.view', [$report, 1]))->assertUnsupportedMediaType();

    $generatedPath = tempnam(sys_get_temp_dir(), 'progress-report-test-');
    expect($generatedPath)->not->toBeFalse();
    file_put_contents($generatedPath, $sourceDocument);

    $template = new ZipArchive;
    $generated = new ZipArchive;

    try {
        expect($template->open(resource_path('documents/BatStateU-REC-RES-02-Progress-Report.docx')))->toBeTrue()
            ->and($generated->open($generatedPath))->toBeTrue();

        $documentXml = $generated->getFromName('word/document.xml');
        $footerXml = $generated->getFromName('word/footer1.xml');
        $relationshipsXml = $generated->getFromName('word/_rels/document.xml.rels');
        expect($documentXml)->toContain('Coastal Community Research')
            ->and($documentXml)->toContain('Completed the first coastal survey')
            ->and($documentXml)->toContain('One survey and one stakeholder consultation')
            ->and($documentXml)->toContain('Baseline coastal data')
            ->and($documentXml)->toContain('Figure 1. The research team conducting the first coastal survey.')
            ->and($documentXml)->toContain('P 150,000.00')
            ->and($documentXml)->toContain(ProposalSignatory::defaultSelections()['verified_by']['name'])
            ->and($documentXml)->toContain(ProposalSignatory::defaultSelections()['recommending_approval_name']['name'])
            ->and($relationshipsXml)->toContain('media/progress-figure-1.jpg')
            ->and($generated->getFromName('word/media/progress-figure-1.jpg'))
            ->toBe(Storage::disk('local')->get($report->photos[0]['path']))
            ->and($footerXml)->toContain('PR-2026-001')
            ->and($footerXml)->toContain('PAGE')
            ->and($footerXml)->toContain('NUMPAGES');

        for ($index = 0; $index < $template->numFiles; $index++) {
            $name = $template->getNameIndex($index);

            if (in_array($name, [
                '[Content_Types].xml',
                'word/document.xml',
                'word/_rels/document.xml.rels',
                'word/footer1.xml',
            ], true)) {
                continue;
            }

            expect(hash('sha256', $generated->getFromName($name)))
                ->toBe(hash('sha256', $template->getFromName($name)));
        }
    } finally {
        $template->close();
        $generated->close();
        unlink($generatedPath);
    }
});

test('an unrelated faculty researcher cannot download progress report files', function () {
    $this->actingAs($this->researcher)
        ->post(route('project-narrative-reports.store', $this->topic), ($this->progressReportPayload)())
        ->assertSessionHasNoErrors();

    $other = User::factory()->create();
    $other->assignRole('faculty_researcher');
    $report = ProjectNarrativeReport::firstOrFail();

    $this->actingAs($other)->get(route('project-narrative-reports.download', $report))->assertForbidden();
    $this->get(route('project-narrative-reports.view', $report))->assertForbidden();
    $this->actingAs($other)->get(route('project-narrative-reports.photos.download', [$report, 0]))->assertForbidden();
    $this->get(route('project-narrative-reports.photos.view', [$report, 0]))->assertForbidden();
    auth()->logout();
    $this->get(route('project-narrative-reports.view', $report))->assertRedirect(route('login'));
    $this->get(route('project-narrative-reports.photos.view', [$report, 0]))->assertRedirect(route('login'));
});

test('a researcher can discard a prepared progress report and its stored files', function () {
    $this->actingAs($this->researcher)
        ->post(route('project-narrative-reports.prepare', $this->topic), ($this->progressReportPayload)())
        ->assertSessionHasNoErrors();

    $report = ProjectNarrativeReport::firstOrFail();
    $pdfPath = $report->official_pdf_path;
    $photoPath = $report->photos[0]['path'];
    Storage::disk('local')->assertExists([$pdfPath, $photoPath]);

    $this->actingAs($this->researcher)
        ->get(route('project-narrative-reports.create', $this->topic))
        ->assertOk()
        ->assertSee('Progress report PDF prepared')
        ->assertSee('Submit to Research Head')
        ->assertSee('data-monitoring-action-dock-fixed', false);
    $this->actingAs($this->researcher)
        ->delete(route('project-narrative-reports.discard-prepared', [$this->topic, $report]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->assertModelMissing($report);
    Storage::disk('local')->assertMissing([$pdfPath, $photoPath]);
});

test('the Research Head can request progress report corrections only with remarks', function () {
    $this->actingAs($this->researcher)
        ->post(route('project-narrative-reports.store', $this->topic), ($this->progressReportPayload)())
        ->assertSessionHasNoErrors();

    $report = ProjectNarrativeReport::firstOrFail();
    $this->actingAs($this->researcher)
        ->post(route('project-narrative-reports.submit-prepared', [$this->topic, $report]))
        ->assertSessionHasNoErrors();
    $notification = $this->head->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => ProposalActivityNotification::class,
        'data' => [
            'title' => 'Progress report submitted',
            'message' => 'A progress report is ready for review.',
            'url' => route('research_head.projects.index'),
            'topic_id' => $this->topic->id,
            'workspace' => User::WORKSPACE_RESEARCH_HEAD,
            'sidebar_area' => ProposalActivityNotification::SIDEBAR_AREA_PROJECT_MONITORING,
        ],
    ]);
    $this->actingAs($this->head)
        ->patch(route('research_head.narrative-progress-reports.review', $report), [
            'review_status' => ProjectNarrativeReport::STATUS_REVISION_REQUESTED,
        ])
        ->assertSessionHasErrors('research_head_remarks');

    $this->actingAs($this->head)
        ->patch(route('research_head.narrative-progress-reports.review', $report), [
            'review_status' => ProjectNarrativeReport::STATUS_REVISION_REQUESTED,
            'research_head_remarks' => 'Clarify the results and replace the first photograph.',
        ])
        ->assertRedirect();

    expect($report->fresh()->review_status)->toBe(ProjectNarrativeReport::STATUS_REVISION_REQUESTED)
        ->and($report->fresh()->review_status_label)->toBe('Corrections requested by Research Head')
        ->and($report->fresh()->reviewed_by)->toBe($this->head->id)
        ->and($notification->fresh()->read_at)->not->toBeNull();
    Notification::assertSentTo(
        $this->researcher,
        ProposalActivityNotification::class,
        fn (ProposalActivityNotification $notification): bool => $notification->title === 'Progress report corrections requested',
    );
});

test('progress defaults reuse approved proposal narratives and work plan outputs without truncating objectives', function () {
    $version = $this->topic->versions()->create([
        'submitted_by' => $this->researcher->id, 'version_number' => 1, 'submission_type' => 'initial',
        'file_path' => 'proposal.pdf', 'original_filename' => 'proposal.pdf', 'mime_type' => 'application/pdf',
        'file_size' => 100, 'checksum' => hash('sha256', 'proposal'), 'title' => $this->topic->title,
        'estimated_budget' => 150000, 'estimated_duration_months' => 12,
    ]);
    $sources = [
        'detailed_proposal' => [
            'project_leader_display' => 'Dr. Approved Leader', 'staff' => [['display_name' => 'Approved Staff']],
            'introduction' => '<p>Approved introduction.</p><p>Second background paragraph.</p>',
            'rationale' => '<p>Approved rationale.</p>', 'general_objective' => '<p>General project objective.</p>',
            'specific_objectives' => [['description' => 'Specific proposal objective.']],
            'related_literature' => 'RRL must stay out of the progress report.',
            'methodology' => ['research_design' => '<p>Approved research design.</p>', 'specific_methods' => '<p>Approved method.</p>', 'data_analysis' => '<p>Approved analysis.</p>'],
        ],
        'work_plan' => [
            'planned_start' => '2026-01-01', 'planned_end' => '2026-12-31',
            'entries' => collect(range(1, 9))->map(fn ($index) => [
                'objective' => 'Approved objective '.$index, 'activity' => 'Planned activity '.$index,
                'expected_output' => 'Expected output '.$index, 'months' => [1, 2, 3],
            ])->push(['objective' => 'Approved objective 1', 'activity' => 'Second activity', 'expected_output' => 'Second expected output', 'months' => [4]])->all(),
        ],
    ];
    foreach ($sources as $type => $source) {
        $version->files()->create([
            'document_type' => $type, 'position' => 1, 'file_path' => $type.'.pdf',
            'original_filename' => $type.'.pdf', 'mime_type' => 'application/pdf', 'file_size' => 100,
            'checksum' => hash('sha256', $type), 'source_data' => $source,
        ]);
    }
    $defaults = app(ProgressReportData::class)->defaults($this->topic->fresh());
    expect($defaults['accomplishments'])->toHaveCount(9)
        ->and($defaults['accomplishments'][0]['target'])->toBe("Expected output 1\nSecond expected output")
        ->and($defaults['accomplishments'][0]['activities'])->toBe("Planned activity 1\nSecond activity")
        ->and($defaults['accomplishments'][0]['actual'])->toBe('')
        ->and($defaults['introduction'])->toBe("Approved introduction.\n\nSecond background paragraph.")
        ->and($defaults['objectives'])->toContain('General project objective.', 'Specific proposal objective.')
        ->and($defaults['methodology'])->toContain('Approved research design.', 'Approved method.', 'Approved analysis.')
        ->and($defaults['researchers'])->toBe("Dr. Approved Leader\nApproved Staff")
        ->and($defaults['implementation_end'])->toBe('2026-12-31')
        ->and($defaults['results_discussion'])->toBe('');

    $formResponse = $this->actingAs($this->researcher)->get(route('project-narrative-reports.create', $this->topic));
    $formResponse->assertSuccessful()->assertSee('Approved objective 9')->assertSee('Approved introduction.')
        ->assertSee('Second expected output')->assertDontSee('RRL must stay out');
    ProjectNarrativeReportDraft::create([
        'topic_id' => $this->topic->id, 'user_id' => $this->researcher->id, 'report_type' => 'progress', 'lock_version' => 1,
        'source_data' => ['introduction' => 'My revised period introduction.', 'methodology' => null, 'results_discussion' => 'Period-specific results.',
            'accomplishments' => [['objective' => 'Approved objective 1', 'target' => 'Edited expected output', 'actual' => 'Period progress.']],
        ],
    ]);
    $form = $this->get(route('project-narrative-reports.create', $this->topic))
        ->assertSuccessful()->assertSee('My revised period introduction.')->assertSee('Period-specific results.')
        ->assertDontSee('Approved research design.')->assertSee('Planned activity 1')->assertSee('Expected output 1')->assertDontSee('Edited expected output');
    $document = new DOMDocument;
    @$document->loadHTML($form->getContent());
    $xpath = new DOMXPath($document);
    foreach (['row.objective', 'row.target'] as $model) {
        expect($xpath->query('//textarea[@x-model="'.$model.'"]')->item(0)->hasAttribute('readonly'))->toBeTrue();
    }
    expect($xpath->query('//textarea[@x-model="row.actual"]')->item(0)->hasAttribute('readonly'))->toBeFalse()
        ->and($xpath->query('//textarea[@name="results_discussion"]')->item(0)->hasAttribute('readonly'))->toBeFalse()
        ->and($xpath->query('//textarea[@name="objectives"]')->item(0)->hasAttribute('readonly'))->toBeTrue();
});

test('progress reports allow no figures but validate each supplied figure and its caption', function () {
    $payload = ($this->progressReportPayload)(['photo_1' => null, 'photo_caption_1' => null, 'photo_section_1' => null]);
    $this->actingAs($this->researcher)->post(route('project-narrative-reports.preview', $this->topic), $payload)
        ->assertSuccessful()->assertDontSee('Figure 1.');
    $this->post(route('project-narrative-reports.prepare', $this->topic), $payload)
        ->assertSessionHasNoErrors();
    expect(ProjectNarrativeReport::sole()->photos)->toBe([]);
    $this->post(route('project-narrative-reports.preview', $this->topic), [
        ...$payload, 'figures' => [['image' => UploadedFile::fake()->image('diagram.png'), 'section' => 'methodology', 'caption' => '']],
    ])->assertSessionHasErrorsIn('narrativeProgress', ['figures.0.caption']);
    $this->post(route('project-narrative-reports.preview', $this->topic), [
        ...$payload, 'figures' => [['image' => UploadedFile::fake()->create('bad.pdf', 10, 'application/pdf'), 'section' => 'methodology', 'caption' => 'Invalid']],
    ])->assertSessionHasErrorsIn('narrativeProgress', ['figures.0.image']);
});

test('repeatable figures preserve more than ten images and paragraph placement in preview and generated document', function () {
    $payload = ($this->progressReportPayload)([
        'photo_1' => null, 'photo_caption_1' => null, 'photo_section_1' => null,
        'methodology' => "First methods paragraph.\n\nSecond methods paragraph.",
        'results_discussion' => "First results paragraph.\n\nSecond results paragraph.",
        'figures' => collect(range(1, 12))->map(fn ($number) => [
            'image' => UploadedFile::fake()->image('figure-'.$number.'.png', 800, 600),
            'caption' => 'Evidence '.$number, 'section' => $number === 1 ? 'methodology' : 'results_discussion',
            'after_paragraph' => $number < 3 ? 1 : 0,
        ])->all(),
    ]);
    $response = $this->actingAs($this->researcher)->post(route('project-narrative-reports.preview', $this->topic), $payload)
        ->assertSuccessful()->assertSee('Figure 12. Evidence 12')->assertSee('data-preview-file-input="figures[11][image]"', false);
    $html = $response->getContent();
    expect(strpos($html, 'First methods paragraph.'))->toBeLessThan(strpos($html, 'Figure 1. Evidence 1'))
        ->and(strpos($html, 'Figure 1. Evidence 1'))->toBeLessThan(strpos($html, 'Second methods paragraph.'));
    $this->post(route('project-narrative-reports.prepare', $this->topic), $payload)->assertSessionHasNoErrors();
    $report = ProjectNarrativeReport::sole();
    expect($report->photos)->toHaveCount(12)
        ->and($report->photos[0]['after_paragraph'])->toBe(1)
        ->and($report->photos[11]['caption'])->toBe('Evidence 12');
    $path = tempnam(sys_get_temp_dir(), 'progress-figures-');
    file_put_contents($path, $this->pdfConverter->sourceDocument);
    $archive = new ZipArchive;
    try {
        expect($archive->open($path))->toBeTrue();
        $xml = $archive->getFromName('word/document.xml');
        $plain = html_entity_decode(strip_tags($xml));
        expect($plain)->toContain('Figure 12. Evidence 12')
            ->and(strpos($plain, 'Figure 1. Evidence 1'))->toBeLessThan(strpos($plain, 'Second methods paragraph.'))
            ->and($archive->getFromName('word/media/progress-figure-12.png'))->not->toBeFalse();
    } finally {
        $archive->close();
        unlink($path);
    }
});

test('submitted report pages allow project readers and deny outsiders, guests, and prepared reports', function () {
    $report = ProjectNarrativeReport::create([
        'topic_id' => $this->topic->id, 'submitted_by' => $this->researcher->id,
        'report_type' => 'progress', 'submission_date' => now(), 'researchers' => $this->researcher->name,
        'implementation_start' => now()->subMonths(3), 'implementation_end' => now(),
        'funding_agency' => 'University', 'budget' => 150000,
        'introduction' => 'Submitted narrative.', 'objectives' => 'Study coastal conditions.',
        'methodology' => 'Site observations.', 'results_discussion' => 'Documented observations.',
        'accomplishment_summary' => 'Fieldwork completed.', 'photos' => [],
    ]);
    $this->actingAs($this->researcher)->get(route('project-narrative-reports.show', $report))
        ->assertSuccessful()->assertSee('Submitted narrative.')->assertSee('Back to monitoring')
        ->assertSee('Preview PDF')->assertDontSee('data-narrative-report-review', false);
    foreach (range(1, 3) as $month) {
        $additional = $report->replicate();
        $additional->fill(['submission_date' => now()->subMonths($month)])->save();
    }
    $listResponse = $this->get(route('topics.show', $this->topic))->assertSuccessful()
        ->assertDontSee('Submitted narrative.')->assertDontSee('data-narrative-pdf-preview-modal', false);
    expect(substr_count($listResponse->getContent(), 'data-narrative-report-summary'))->toBe(4);
    if (getenv('ATHENA_EXPORT_REPORT_LAYOUT') === '1') {
        $directory = storage_path('framework/testing');
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
        file_put_contents($directory.'/compact-report-list.html', $listResponse->getContent());
    }
    $this->actingAs($this->head)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD])
        ->get(route('project-narrative-reports.show', $report))->assertSuccessful()->assertSee('data-narrative-report-review', false);
    if (getenv('ATHENA_EXPORT_REPORT_LAYOUT') === '1') {
        file_put_contents(storage_path('framework/testing/report-reader.html'), $this->get(route('project-narrative-reports.show', $report))->getContent());
    }
    $outsider = User::factory()->create();
    $outsider->assignRole('faculty_researcher');
    $this->actingAs($outsider)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY_RESEARCHER])
        ->get(route('project-narrative-reports.show', $report))->assertForbidden();
    $report->update(['submission_status' => 'prepared']);
    $this->actingAs($this->researcher)->get(route('project-narrative-reports.show', $report))->assertNotFound();
    auth()->logout();
    $this->get(route('project-narrative-reports.show', $report))->assertRedirect(route('login'));
});

test('progress draft retains arbitrary figure captions and long reused narratives without saving file uploads', function () {
    $payload = ($this->progressReportPayload)([
        'photo_1' => null, 'methodology' => str_repeat('Approved methodology text. ', 300),
        'figures' => collect(range(1, 14))->map(fn ($index) => ['caption' => 'Diagram '.$index, 'section' => 'methodology', 'after_paragraph' => $index])->all(),
    ]);
    $this->actingAs($this->researcher)->postJson(route('project-narrative-reports.draft', $this->topic), [...$payload, 'draft_version' => 0])
        ->assertSuccessful();
    $draft = ProjectNarrativeReportDraft::sole();
    expect($draft->source_data['figures'])->toHaveCount(14)
        ->and($draft->source_data['figures'][13]['caption'])->toBe('Diagram 14')
        ->and($draft->source_data['methodology'])->toBe(trim($payload['methodology']));
    $this->get(route('project-narrative-reports.create', $this->topic))->assertSuccessful()->assertSee('Diagram 14');
});
