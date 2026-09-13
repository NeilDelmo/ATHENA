<?php

use App\Contracts\DocumentPdfConverter;
use App\Models\ProjectNarrativeReport;
use App\Models\ProjectNarrativeReportDraft;
use App\Models\ResearchCall;
use App\Models\TopicProposal;
use App\Models\User;
use App\Notifications\ProposalActivityNotification;
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

test('the progress report requires structured accomplishments and a captioned figure', function () {
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
            'photo_1',
            'photo_caption_1',
            'photo_section_1',
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
        ->assertSee('Quarterly reporting schedule')
        ->assertSee('Open progress report')
        ->assertSee(route('project-narrative-reports.create', $this->topic), false)
        ->assertDontSee('data-narrative-progress-autosave-form', false);

    $this->actingAs($this->researcher)
        ->get(route('project-narrative-reports.create', $this->topic))
        ->assertOk()
        ->assertSee('Report project accomplishments')
        ->assertSee('Prepare official PDF')
        ->assertSee('Preview progress report')
        ->assertSee('Exit monitoring')
        ->assertSee('data-paper-cancel-exit', false)
        ->assertSee('fixed bottom-4 right-4', false)
        ->assertSee('x-ref="previewFrame"', false)
        ->assertSee('VI. Summary of Accomplishment for the Monitoring Period')
        ->assertSee('Target accomplishment')
        ->assertSee('VIII. Rationale')
        ->assertSee('X. Results and Discussion')
        ->assertSee('Figures and photo documentation required')
        ->assertSee('Changes save privately as a draft.')
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
    $this->actingAs($this->head)
        ->get(route('project-narrative-reports.download', $report))
        ->assertForbidden();

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
            ->and($documentXml)->not->toContain('DJOANNA MARIE V. SALAC')
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
    $this->actingAs($other)->get(route('project-narrative-reports.photos.download', [$report, 0]))->assertForbidden();
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
        ->assertSee('Submit to Research Head');
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
        ->and($report->fresh()->review_status_label)->toBe('Corrections requested')
        ->and($report->fresh()->reviewed_by)->toBe($this->head->id)
        ->and($notification->fresh()->read_at)->not->toBeNull();
    Notification::assertSentTo(
        $this->researcher,
        ProposalActivityNotification::class,
        fn (ProposalActivityNotification $notification): bool => $notification->title === 'Progress report corrections requested',
    );
});
