<?php

use App\Models\ProjectNarrativeReport;
use App\Models\ResearchCall;
use App\Models\TopicProposal;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Notification::fake();
    Storage::fake('local');

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
        'notice_to_proceed_issued_at' => now(),
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

test('only the project owner with a Notice to Proceed can submit the official progress report', function () {
    $this->actingAs($this->researcher)
        ->post(route('project-narrative-reports.store', $this->topic), ($this->progressReportPayload)())
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $report = ProjectNarrativeReport::firstOrFail();
    expect($report->topic_id)->toBe($this->topic->id)
        ->and($report->budget)->toBe('150000.00')
        ->and($report->photos)->toHaveCount(1)
        ->and($report->review_status)->toBe(ProjectNarrativeReport::STATUS_PENDING);
    Storage::disk('local')->assertExists($report->photos[0]['path']);

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

test('the faculty monitoring page shows the separate progress report form', function () {
    $this->actingAs($this->researcher)
        ->get(route('research.show', $this->topic))
        ->assertOk()
        ->assertSee('Submit progress report')
        ->assertSee('VI. Summary of Accomplishment for the Monitoring Period')
        ->assertSee('Target accomplishment')
        ->assertSee('VIII. Rationale')
        ->assertSee('X. Results and Discussion')
        ->assertSee('Figures and photo documentation required');
});

test('the owner and Research Head can download the official report and its photo', function () {
    $this->actingAs($this->researcher)
        ->post(route('project-narrative-reports.store', $this->topic), ($this->progressReportPayload)())
        ->assertSessionHasNoErrors();

    $report = ProjectNarrativeReport::firstOrFail();
    $documentResponse = $this->actingAs($this->researcher)
        ->get(route('project-narrative-reports.download', $report))
        ->assertOk()
        ->assertDownload('coastal-community-research-progress-report.docx');
    $this->actingAs($this->head)
        ->get(route('project-narrative-reports.download', $report))
        ->assertOk();
    $this->actingAs($this->head)
        ->get(route('project-narrative-reports.photos.download', [$report, 0]))
        ->assertOk()
        ->assertDownload('coastal-survey.jpg');

    $generatedPath = tempnam(sys_get_temp_dir(), 'progress-report-test-');
    expect($generatedPath)->not->toBeFalse();
    file_put_contents($generatedPath, $documentResponse->streamedContent());

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

test('the Research Head can request a progress report revision only with remarks', function () {
    $this->actingAs($this->researcher)
        ->post(route('project-narrative-reports.store', $this->topic), ($this->progressReportPayload)())
        ->assertSessionHasNoErrors();

    $report = ProjectNarrativeReport::firstOrFail();
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
        ->and($report->fresh()->reviewed_by)->toBe($this->head->id);
});
