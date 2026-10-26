<?php

use App\Contracts\DocumentPdfConverter;
use App\Models\ProjectNarrativeReport;
use App\Models\ProjectNarrativeReportDraft;
use App\Models\ResearchCall;
use App\Models\TopicProposal;
use App\Models\User;
use App\Services\LibreOfficeDocumentPdfConverter;
use App\Services\MonitoringQuarterService;
use App\Support\TerminalReportData;
use App\Support\TerminalReportRules;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
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
        'notice_to_proceed_issued_at' => now()->subMonths(13),
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

    $this->withoutVite();
    $this->topic->update(['estimated_budget' => 50000]);
    foreach (app(MonitoringQuarterService::class)->projectPeriods($this->topic) as $period) {
        $this->topic->progressReports()->create([
            'submitted_by' => $this->researcher->id, 'reporting_date' => $period['end'],
            'reporting_year' => $period['year'], 'reporting_quarter' => $period['quarter'],
            'period_start' => $period['start'], 'period_end' => $period['end'],
            'progress_percentage' => 100, 'accomplishments' => 'Fieldwork completed.',
            'issues' => '', 'work_plan' => [], 'budget_utilization' => [], 'review_status' => 'reviewed',
        ]);
    }
    $this->completeQuarterlyProgress = function (): void {
        foreach (app(MonitoringQuarterService::class)->projectPeriods($this->topic) as $period) {
            $existing = $this->topic->narrativeReports()->where('report_type', 'progress')->where('reporting_quarter', $period['quarter'])->latest('id')->first();
            if ($existing !== null) {
                $existing->update(['submission_status' => 'submitted', 'review_status' => 'reviewed']);

                continue;
            }
            $payload = ($this->progressReportPayload)();
            unset($payload['photo_1'], $payload['photo_caption_1'], $payload['photo_section_1']);
            $this->topic->narrativeReports()->create([
                ...$payload, 'submitted_by' => $this->researcher->id, 'report_type' => 'progress',
                'reporting_date' => $period['end'], 'reporting_quarter' => $period['quarter'],
                'period_start' => $period['start'], 'period_end' => $period['end'], 'budget' => 50000,
                'accomplishment_summary' => 'Quarterly coastal fieldwork completed.', 'photos' => [],
                'submission_status' => 'submitted', 'review_status' => 'reviewed',
            ]);
        }
    };
    $this->terminalPayload = function (array $overrides = []): array {
        $payload = ($this->progressReportPayload)([
            'report_type' => 'terminal',
            'implementation_start' => now()->subMonths(12)->toDateString(),
            'implementation_end' => now()->subMonth()->toDateString(),
            'terminal_data' => [
                'abstract' => implode(' ', array_fill(0, 210, 'research')),
                'literature_review' => '<p>Earlier studies support this research.</p>',
                'conclusions' => '<p>The objectives were achieved.</p>',
                'recommendations' => '<p>Continue the program.</p>',
                'bibliography' => '<p>Researcher. (2025). Coastal study.</p>',
                'total_expenditure' => 40947,
                'collaborating_agency' => 'Partner College',
                'authors' => [['name' => $this->researcher->name, 'role' => 'Project Leader', 'rank' => 'Assistant Professor', 'campus' => 'Nasugbu', 'college' => 'CICS', 'date_signed' => null]],
                'signatories' => collect(TerminalReportRules::SIGNATORY_ROLES)->map(fn ($role) => ['name' => 'Reviewer '.$role[1], 'date_signed' => null])->all(),
                'tables' => [['caption' => 'Evaluation findings', 'section' => 'results_discussion', 'after_paragraph' => 1, 'headers' => ['Measure', 'Result'], 'rows' => [['Participation', 'High']]]],
            ],
        ]);
        unset($payload['photo_1'], $payload['photo_caption_1'], $payload['photo_section_1']);

        return array_replace_recursive($payload, $overrides);
    };
});

test('narrative PDF preparation cannot erase a newer private draft', function (string $type) {
    if ($type === 'terminal') {
        ($this->completeQuarterlyProgress)();
    }
    $payload = $type === 'terminal' ? ($this->terminalPayload)() : [...($this->progressReportPayload)(), 'report_type' => 'progress'];
    unset($payload['photo_1'], $payload['photo_caption_1'], $payload['photo_section_1']);
    $this->actingAs($this->researcher)->postJson(route('project-narrative-reports.draft', $this->topic), [
        ...$payload, 'draft_version' => 0, 'introduction' => 'Newest saved narrative',
    ])->assertSuccessful();
    $draft = ProjectNarrativeReportDraft::sole();
    $url = route('project-narrative-reports.create', ['topic' => $this->topic, 'report_type' => $type, 'reporting_date' => $payload['reporting_date']]);
    $this->withCookie(config('session.cookie'), session()->getId());
    $this->from($url)->post(route('project-narrative-reports.prepare', $this->topic), [...$payload, 'draft_version' => 0, 'draft_id' => 0])
        ->assertSessionHasErrors('draft_version', null, 'narrativeProgress');

    expect(ProjectNarrativeReportDraft::sole()->source_data)->toBe($draft->source_data)
        ->and(ProjectNarrativeReport::prepared()->count())->toBe(0);
    $response = $this->get($url)->assertSuccessful()->assertSee($payload['introduction']);
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    expect($xpath->query('//input[@name="draft_version"]')->item(0)->getAttribute('value'))->toBe('0')
        ->and($xpath->query('//input[@name="draft_id"]')->item(0)->getAttribute('value'))->toBe('0');
})->with(['progress', 'terminal']);

test('narrative forms ignore validation state belonging to another report type', function (string $type) {
    $payload = $type === 'terminal' ? ($this->terminalPayload)() : [...($this->progressReportPayload)(), 'report_type' => 'progress'];
    $payload['introduction'] = 'Saved narrative content';
    unset($payload['photo_1'], $payload['photo_caption_1'], $payload['photo_section_1']);
    $this->actingAs($this->researcher)->postJson(route('project-narrative-reports.draft', $this->topic), [...$payload, 'draft_version' => 0])
        ->assertSuccessful();
    $draft = ProjectNarrativeReportDraft::sole();
    $response = $this->withSession(['_old_input' => [
        'report_type' => $type === 'terminal' ? 'progress' : 'terminal', 'draft_id' => 999, 'draft_version' => 999,
        'introduction' => 'Other report introduction', 'researchers' => 'Other report researcher',
        'tracking_number' => 'Other report tracking number', 'submission_date' => '2099-01-01',
        'implementation_start' => '2099-01-01', 'implementation_end' => '2099-01-01', 'prepared_by_date_signed' => '2099-01-01',
        'accomplishments' => [['objective' => 'Other report objective', 'actual' => 'Other report accomplishment']],
        'figures' => [['caption' => 'Other report figure']], 'terminal_data' => ['abstract' => 'Other report abstract'],
    ]])->get(route('project-narrative-reports.create', ['topic' => $this->topic, 'report_type' => $type, 'reporting_date' => $payload['reporting_date']]))
        ->assertSuccessful()->assertSee('Saved narrative content')->assertDontSee('Other report')->assertDontSee('2099-01-01');
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    expect($xpath->query('//input[@name="report_type"]')->item(0)->getAttribute('value'))->toBe($type)
        ->and($xpath->query('//input[@name="draft_version"]')->item(0)->getAttribute('value'))->toBe('1')
        ->and($xpath->query('//input[@name="draft_id"]')->item(0)->getAttribute('value'))->toBe((string) $draft->id);
})->with(['progress', 'terminal']);

test('stale narrative tabs cannot overwrite drafts restored after discarding a PDF', function (string $type) {
    if ($type === 'terminal') {
        ($this->completeQuarterlyProgress)();
    }
    $payload = $type === 'terminal' ? ($this->terminalPayload)() : [...($this->progressReportPayload)(), 'report_type' => 'progress'];
    unset($payload['photo_1'], $payload['photo_caption_1'], $payload['photo_section_1']);
    $this->actingAs($this->researcher)->postJson(route('project-narrative-reports.draft', $this->topic), [...$payload, 'draft_version' => 0])
        ->assertSuccessful();
    $original = ProjectNarrativeReportDraft::sole();
    $stale = [...$payload, 'draft_version' => 1, 'draft_id' => $original->id];
    $this->post(route('project-narrative-reports.prepare', $this->topic), $stale)->assertSessionHasNoErrors();
    $report = ProjectNarrativeReport::prepared()->sole();
    $this->delete(route('project-narrative-reports.discard-prepared', [$this->topic, $report]))->assertSessionHasNoErrors();
    $restored = ProjectNarrativeReportDraft::sole();
    expect($restored->id)->not->toBe($original->id)->and($restored->lock_version)->toBe(1);

    $this->postJson(route('project-narrative-reports.draft', $this->topic), [...$stale, 'introduction' => 'Older tab'])
        ->assertUnprocessable()->assertJsonValidationErrors('draft_version');
    $this->post(route('project-narrative-reports.prepare', $this->topic), $stale)
        ->assertSessionHasErrors('draft_version', null, 'narrativeProgress');
    expect(ProjectNarrativeReportDraft::sole()->source_data)->toBe($restored->source_data)
        ->and(ProjectNarrativeReport::prepared()->count())->toBe(0);
})->with(['progress', 'terminal']);

test('a failed narrative draft removal keeps the draft and cleans up its prepared PDF', function (string $type) {
    if ($type === 'terminal') {
        ($this->completeQuarterlyProgress)();
    }
    $payload = $type === 'terminal' ? ($this->terminalPayload)() : [...($this->progressReportPayload)(), 'report_type' => 'progress'];
    unset($payload['photo_1'], $payload['photo_caption_1'], $payload['photo_section_1']);
    $this->actingAs($this->researcher)->postJson(route('project-narrative-reports.draft', $this->topic), [...$payload, 'draft_version' => 0])
        ->assertSuccessful();
    $draft = ProjectNarrativeReportDraft::sole();
    Event::listen('eloquent.deleting: '.ProjectNarrativeReportDraft::class, function (ProjectNarrativeReportDraft $deleting) use ($draft): void {
        if ($deleting->id === $draft->id) {
            throw new RuntimeException('Simulated draft removal failure');
        }
    });
    $this->post(route('project-narrative-reports.prepare', $this->topic), [...$payload, 'draft_version' => 1, 'draft_id' => $draft->id])
        ->assertSessionHasErrors('preparation', null, 'narrativeProgress');

    expect(ProjectNarrativeReportDraft::sole()->source_data)->toBe($draft->source_data)
        ->and(ProjectNarrativeReport::prepared()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles('narrative-progress-reports/'.$this->topic->id.'/prepared'))->toBe([]);
})->with(['progress', 'terminal']);

test('narrative preparation rechecks project membership after PDF conversion', function (string $type) {
    if ($type === 'terminal') {
        ($this->completeQuarterlyProgress)();
    }
    $payload = $type === 'terminal' ? ($this->terminalPayload)() : [...($this->progressReportPayload)(), 'report_type' => 'progress'];
    unset($payload['photo_1'], $payload['photo_caption_1'], $payload['photo_section_1']);
    $member = User::factory()->create();
    $member->assignRole('faculty_researcher');
    $collaborator = $this->topic->collaborators()->create([
        'user_id' => $member->id, 'name' => $member->name, 'email' => $member->email, 'accepted_at' => now(),
    ]);
    $this->mock(DocumentPdfConverter::class)->shouldReceive('convertDocx')->once()
        ->andReturnUsing(function (string $contents) use ($collaborator): string {
            $collaborator->delete();

            return '%PDF-1.4 removed project member';
        });
    $this->actingAs($member)->post(route('project-narrative-reports.prepare', $this->topic), $payload)
        ->assertSessionHasErrors('preparation', null, 'narrativeProgress');

    expect(ProjectNarrativeReport::prepared()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles('narrative-progress-reports/'.$this->topic->id.'/prepared'))->toBe([]);
})->with(['progress', 'terminal']);

test('a failed prepared narrative deletion does not leave restored draft images', function (string $type) {
    if ($type === 'terminal') {
        ($this->completeQuarterlyProgress)();
    }
    $payload = $type === 'terminal' ? ($this->terminalPayload)() : [...($this->progressReportPayload)(), 'report_type' => 'progress'];
    $payload['photo_1'] = UploadedFile::fake()->image('report-figure.jpg');
    $payload['photo_caption_1'] = 'Report figure';
    $payload['photo_section_1'] = 'methodology';
    $this->actingAs($this->researcher)->post(route('project-narrative-reports.prepare', $this->topic), $payload)
        ->assertSessionHasNoErrors();
    $report = ProjectNarrativeReport::prepared()->sole();
    $paths = Storage::disk('local')->allFiles('narrative-progress-reports/'.$this->topic->id);
    Event::listen('eloquent.deleting: '.ProjectNarrativeReport::class, function (ProjectNarrativeReport $deleting) use ($report): void {
        if ($deleting->id === $report->id) {
            throw new RuntimeException('Simulated prepared report deletion failure');
        }
    });

    $this->delete(route('project-narrative-reports.discard-prepared', [$this->topic, $report]))->assertInternalServerError();
    expect($report->fresh()->isPrepared())->toBeTrue()
        ->and(ProjectNarrativeReportDraft::count())->toBe(0)
        ->and(Storage::disk('local')->allFiles('narrative-progress-reports/'.$this->topic->id))->toBe($paths);
})->with(['progress', 'terminal']);

function terminalDocumentXml(string $contents): string
{
    $path = tempnam(sys_get_temp_dir(), 'terminal-test-');
    file_put_contents($path, $contents);
    $zip = new ZipArchive;
    $zip->open($path);
    $xml = $zip->getFromName('word/document.xml');
    $zip->close();
    unlink($path);

    return $xml;
}

test('terminal signatories default to the supplied institutional officers and preserve the official roles', function () {
    ($this->completeQuarterlyProgress)();
    $names = TerminalReportData::defaultSignatoryNames();
    $form = $this->actingAs($this->researcher)
        ->get(route('project-narrative-reports.create', ['topic' => $this->topic, 'report_type' => 'terminal']))
        ->assertOk();
    foreach ($names as $name) {
        $form->assertSee($name);
    }
    $payload = ($this->terminalPayload)([
        'terminal_data' => ['signatories' => ['reviewed_head' => ['name' => 'Dr. CARMINA L. CAUREZ']]],
    ]);
    $preview = $this->post(route('project-narrative-reports.preview', $this->topic), $payload)->assertOk();
    foreach ($names as $name) {
        $preview->assertSee($name);
    }
    $preview->assertDontSee('Dr. CARMINA L. CAUREZ');
    $this->post(route('project-narrative-reports.prepare', $this->topic), $payload)->assertSessionHasNoErrors();
    $report = ProjectNarrativeReport::where('report_type', 'terminal')->sole();
    $document = new DOMDocument;
    $document->loadXML(terminalDocumentXml($this->pdfConverter->sourceDocument));
    foreach (TerminalReportRules::SIGNATORY_ROLES as $key => [$group, $role]) {
        expect($report->terminal_data['signatories'][$key]['name'])->toBe($names[$key])
            ->and($document->textContent)->toContain($names[$key], $role);
    }
    expect($document->textContent)->not->toContain('Dr. CARMINA L. CAUREZ');
});

test('accepted collaborators can prepare a shared Terminal Report but only the project leader submits it', function () {
    ($this->completeQuarterlyProgress)();
    $collaborator = User::factory()->create();
    $collaborator->assignRole('faculty_researcher');
    $this->topic->collaborators()->create([
        'user_id' => $collaborator->id, 'name' => $collaborator->name,
        'email' => $collaborator->email, 'accepted_at' => now(),
    ]);
    $this->actingAs($collaborator)->post(route('project-narrative-reports.prepare', $this->topic), ($this->terminalPayload)())
        ->assertSessionHasNoErrors();
    $report = ProjectNarrativeReport::where('report_type', 'terminal')->sole();
    $this->get(route('project-narrative-reports.create', [$this->topic, 'report_type' => 'terminal']))
        ->assertOk()->assertSee('Only the project leader can submit this report.')->assertDontSee('Submit to Research Head');
    $this->post(route('project-narrative-reports.submit-prepared', [$this->topic, $report]))->assertForbidden();
    expect($report->fresh()->isPrepared())->toBeTrue();
    $this->actingAs($this->researcher)->get(route('project-narrative-reports.view', $report))->assertOk();
    $this->post(route('project-narrative-reports.submit-prepared', [$this->topic, $report]))->assertSessionHasNoErrors();
    expect($report->fresh()->isSubmitted())->toBeTrue()->and($report->fresh()->submitted_by)->toBe($this->researcher->id);
});

test('a researcher can preview a terminal report before project completion while official preparation stays locked', function () {
    $this->travelTo('2026-02-01');
    $this->topic->progressReports()->delete();
    $this->topic->update([
        'notice_to_proceed_issued_at' => '2026-01-01',
        'notice_to_proceed_data' => ['approved_start_date' => '2026-01-01', 'approved_end_date' => '2026-12-31', 'approved_duration_months' => 12],
    ]);
    $payload = ($this->terminalPayload)(['implementation_start' => '2026-01-01', 'implementation_end' => '2026-12-31']);
    $this->actingAs($this->researcher)->get(route('project-narrative-reports.create', ['topic' => $this->topic, 'report_type' => 'terminal']))
        ->assertSuccessful()->assertSee('data-proposal-preview-toggle', false)->assertSee('submissionOpen: false', false);
    $this->postJson(route('project-narrative-reports.preview', $this->topic), $payload)
        ->assertSuccessful()->assertSee('TERMINAL REPORT')->assertSee('IV. Abstract')->assertSee('The objectives were achieved.');
    $this->postJson(route('project-narrative-reports.prepare', $this->topic), $payload)->assertForbidden();
    $this->postJson(route('project-narrative-reports.preview', $this->topic), [...$payload, 'implementation_end' => '2025-12-31'])
        ->assertUnprocessable()->assertJsonValidationErrors('implementation_end');
    $this->postJson(route('project-narrative-reports.preview', $this->topic), [...$payload, 'implementation_end' => '2027-01-01'])
        ->assertUnprocessable()->assertJsonValidationErrors('implementation_end');
    expect(ProjectNarrativeReport::query()->count())->toBe(0)
        ->and($this->pdfConverter->conversionCount)->toBe(0)
        ->and(Storage::disk('local')->allFiles('narrative-progress-reports'))->toBe([]);
});

test('terminal form and preview use final report sections without mandatory images', function () {
    if (getenv('TERMINAL_REPORT_QA_PATH')) {
        $this->withVite();
    }
    $form = $this->actingAs($this->researcher)->get(route('project-narrative-reports.create', ['topic' => $this->topic, 'report_type' => 'terminal']))
        ->assertOk()
        ->assertSee('BatStateU-REC-RES-04')
        ->assertSee('Add figure')
        ->assertSee('Create table')
        ->assertSee('How the final report is assembled')
        ->assertSee('Approved objectives and final outcomes')
        ->assertSee('Quarterly records combined below')
        ->assertSee('Add project poster')
        ->assertSee('Final total expenditure')
        ->assertSee('data-approved-work-plan-objectives', false)
        ->assertSee('data-terminal-cover-image', false)
        ->assertSee('data-terminal-table-builder', false)
        ->assertSee('data-monitoring-writing-toolbar', false)
        ->assertSee('data-proposal-workspace-toolbar', false)
        ->assertDontSee('data-monitoring-action-dock-fixed', false)
        ->assertDontSee('More figures (4–30)');
    if (getenv('TERMINAL_REPORT_QA_PATH')) {
        file_put_contents(getenv('TERMINAL_REPORT_QA_PATH').'.html', $form->getContent());
    }
    $this->post(route('project-narrative-reports.preview', $this->topic), ($this->terminalPayload)())
        ->assertOk()->assertSee('IV. Abstract')->assertSee('Conclusions')->assertSee('Bibliography')->assertSee('81.89%')
        ->assertSee('Table 1. Evaluation findings')->assertDontSee('BatStateU-REC-RES-02');
});

test('terminal cover poster is previewed stored and embedded in the official document', function () {
    ($this->completeQuarterlyProgress)();
    $caption = 'Community coastal mapping project poster';
    $previewPayload = ($this->terminalPayload)([
        'cover_image' => UploadedFile::fake()->image('project-poster.jpg', 1600, 900),
        'cover_image_caption' => $caption,
    ]);

    $this->actingAs($this->researcher)
        ->post(route('project-narrative-reports.preview', $this->topic), $previewPayload)
        ->assertOk()
        ->assertSee('data-preview-file-input="cover_image"', false)
        ->assertSee($caption);

    $preparePayload = ($this->terminalPayload)([
        'cover_image' => UploadedFile::fake()->image('project-poster.jpg', 1600, 900),
        'cover_image_caption' => $caption,
    ]);
    $this->post(route('project-narrative-reports.prepare', $this->topic), $preparePayload)
        ->assertSessionHasNoErrors();

    $report = ProjectNarrativeReport::where('report_type', 'terminal')->firstOrFail();
    expect($report->photos)->toHaveCount(1)
        ->and($report->photos[0]['section'])->toBe('cover')
        ->and($report->photos[0]['caption'])->toBe($caption);
    Storage::disk('local')->assertExists($report->photos[0]['path']);

    $xml = terminalDocumentXml($this->pdfConverter->sourceDocument);
    expect($xml)->toContain('<w:drawing')
        ->and(html_entity_decode(strip_tags($xml)))->toContain($caption)
        ->not->toContain('Figure 1. '.$caption);
});

test('terminal preparation snapshots all sections and downloads the exact stored PDF', function () {
    ($this->completeQuarterlyProgress)();
    $this->actingAs($this->researcher)->post(route('project-narrative-reports.prepare', $this->topic), ($this->terminalPayload)())
        ->assertSessionHasNoErrors()->assertRedirect();
    $report = ProjectNarrativeReport::where('report_type', 'terminal')->firstOrFail();
    expect($report->photos)->toBe([])
        ->and($report->terminal_data['project_title'])->toBe($this->topic->title)
        ->and($report->terminal_data['total_expenditure'])->toEqual(40947)
        ->and($report->terminal_data['authors'][0]['campus'])->toBe('Nasugbu')
        ->and($report->terminal_data['source_monitoring_report_ids'])->toHaveCount(4);
    $xml = terminalDocumentXml($this->pdfConverter->sourceDocument);
    foreach (['BatStateU-REC-RES-04', 'IV. Abstract', 'Conclusions', 'Recommendations', 'Bibliography', '81.89%', 'Evaluation findings', 'Assistant Director, Research/Center Head', 'Checked and verified by:'] as $text) {
        expect(html_entity_decode(strip_tags($xml)))->toContain($text);
    }
    expect($xml)->not->toContain('PROGRESS REPORT ON RESEARCH PROJECT');
    $this->topic->update(['title' => 'Changed after preparation', 'estimated_budget' => 999]);
    $this->get(route('project-narrative-reports.download', $report))->assertOk()->assertDownload();
    expect($this->pdfConverter->conversionCount)->toBe(1);
    $this->post(route('project-narrative-reports.submit-prepared', [$this->topic, $report]))->assertSessionHasNoErrors();
    expect($report->fresh()->isSubmitted())->toBeTrue();
    // Keep an inspectable fixture for the actual configured PDF converter and visual QA.
    if (getenv('TERMINAL_REPORT_QA_PATH')) {
        file_put_contents(getenv('TERMINAL_REPORT_QA_PATH'), $this->pdfConverter->sourceDocument);
        $pdf = app(LibreOfficeDocumentPdfConverter::class)->convertDocx($this->pdfConverter->sourceDocument);
        expect($pdf)->toStartWith('%PDF-');
        file_put_contents(getenv('TERMINAL_REPORT_QA_PATH').'.pdf', $pdf);
    }
});

test('terminal draft autosaves poster and figures for reload preview preparation and discard', function () {
    ($this->completeQuarterlyProgress)();
    $payload = ($this->terminalPayload)([
        'cover_image' => UploadedFile::fake()->image('saved-poster.png'), 'cover_image_caption' => 'Saved project poster',
        'photo_1' => UploadedFile::fake()->image('saved-result.png'), 'photo_caption_1' => 'Saved final result', 'photo_section_1' => 'results_discussion', 'draft_version' => 0,
    ]);
    $response = $this->actingAs($this->researcher)->postJson(route('project-narrative-reports.draft', $this->topic), $payload)->assertSuccessful();
    $cover = $response->json('images.cover_image');
    $figure = $response->json('images.photo_1');
    expect($cover['id'] ?? null)->not->toBeNull()->and($figure['id'] ?? null)->not->toBeNull();
    $this->get($cover['preview_url'])->assertSuccessful();
    $this->get(route('project-narrative-reports.create', [$this->topic, 'report_type' => 'terminal']))->assertSuccessful()->assertSee($cover['id'])->assertSee($figure['id']);
    unset($payload['cover_image'], $payload['photo_1'], $payload['draft_version']);
    $payload['saved_cover_image'] = $cover['id'];
    $payload['saved_photo_1'] = $figure['id'];
    $this->post(route('project-narrative-reports.preview', $this->topic), $payload)->assertSuccessful()->assertSee($cover['preview_url'], false)->assertSee('Saved final result');
    $this->post(route('project-narrative-reports.prepare', $this->topic), $payload)->assertRedirect()->assertSessionHasNoErrors();
    $report = ProjectNarrativeReport::where('report_type', 'terminal')->sole();
    expect($report->photos)->toHaveCount(2)->and($report->photos[0]['section'])->toBe('cover');
    Storage::disk('local')->assertExists(array_column($report->photos, 'path'));
    $this->delete(route('project-narrative-reports.discard-prepared', [$this->topic, $report]))->assertRedirect()->assertSessionHasNoErrors();
    $draft = ProjectNarrativeReportDraft::where('report_type', 'terminal')->sole();
    expect($draft->source_data['saved_images'])->toHaveCount(2);
    Storage::disk('local')->assertExists(array_column($draft->source_data['saved_images'], 'path'));
});

test('terminal and progress drafts remain separate and preserve arrays and long narratives', function () {
    $this->actingAs($this->researcher)->postJson(route('project-narrative-reports.draft', $this->topic), ['draft_version' => 0, 'report_type' => 'progress', 'introduction' => 'Progress draft'])->assertOk();
    $payload = ($this->terminalPayload)();
    $payload['draft_version'] = 0;
    $payload['methodology'] = str_repeat('Actual research methods. ', 500);
    $this->postJson(route('project-narrative-reports.draft', $this->topic), $payload)->assertOk();
    expect(ProjectNarrativeReportDraft::count())->toBe(2);
    $terminal = ProjectNarrativeReportDraft::where('report_type', 'terminal')->firstOrFail();
    expect($terminal->source_data['terminal_data']['tables'][0]['rows'])->toBe([['Participation', 'High']])
        ->and(mb_strlen($terminal->source_data['methodology']))->toBeGreaterThan(5000);
    $this->postJson(route('project-narrative-reports.draft', $this->topic), $payload)->assertUnprocessable()->assertJsonValidationErrors('draft_version');
    $this->get(route('project-narrative-reports.create', ['topic' => $this->topic, 'report_type' => 'progress']))->assertOk()->assertSee('Progress draft')->assertDontSee('Final total expenditure');
});

test('terminal validates abstract length dates table shape and substantive narratives', function (string $field, mixed $value) {
    $payload = ($this->terminalPayload)();
    data_set($payload, $field, $value);
    $this->actingAs($this->researcher)->postJson(route('project-narrative-reports.preview', $this->topic), $payload)->assertUnprocessable();
    expect(ProjectNarrativeReport::count())->toBe(0);
})->with([
    'short abstract' => ['terminal_data.abstract', 'Too short'],
    'long abstract' => ['terminal_data.abstract', str_repeat('word ', 251)],
    'negative expenditure' => ['terminal_data.total_expenditure', -1],
    'future completion' => ['implementation_end', '2099-01-01'],
    'empty formatted result' => ['results_discussion', '<p><br></p>'],
    'uneven table' => ['terminal_data.tables.0.rows', [['One cell']]],
    'forged snapshot' => ['terminal_data.approved_budget', 1],
]);

test('terminal expenditure cannot exceed the approved project budget', function () {
    $this->topic->update(['estimated_budget' => 8600]);
    $payload = ($this->terminalPayload)([
        'terminal_data' => ['total_expenditure' => 15000],
    ]);

    $this->actingAs($this->researcher)
        ->postJson(route('project-narrative-reports.preview', $this->topic), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('terminal_data.total_expenditure')
        ->assertJsonFragment([
            'The final total expenditure may not exceed the approved project budget.',
        ]);

    $this->actingAs($this->researcher)
        ->postJson(route('project-narrative-reports.draft', $this->topic), [
            ...$payload,
            'draft_version' => 0,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('terminal_data.total_expenditure');

    expect(ProjectNarrativeReport::count())->toBe(0)
        ->and(ProjectNarrativeReportDraft::count())->toBe(0);
});

test('terminal reuses only same-project evidence and keeps source files when discarded', function () {
    $this->actingAs($this->researcher)->post(route('project-narrative-reports.prepare', $this->topic), ($this->progressReportPayload)())->assertSessionHasNoErrors();
    $source = ProjectNarrativeReport::firstOrFail();
    $source->update(['submission_status' => 'submitted', 'submitted_at' => now()]);
    ($this->completeQuarterlyProgress)();
    $payload = ($this->terminalPayload)(['reuse_photo_1' => $source->id.':0', 'photo_caption_1' => 'Reused fieldwork', 'photo_section_1' => 'methodology', 'photo_after_paragraph_1' => 1]);
    $this->post(route('project-narrative-reports.preview', $this->topic), $payload)->assertOk()->assertSee('Reused fieldwork');
    $this->post(route('project-narrative-reports.prepare', $this->topic), $payload)->assertSessionHasNoErrors();
    $terminal = ProjectNarrativeReport::where('report_type', 'terminal')->firstOrFail();
    expect($terminal->photos[0]['path'])->not->toBe($source->photos[0]['path']);
    $this->delete(route('project-narrative-reports.discard-prepared', [$this->topic, $terminal]))->assertSessionHasNoErrors();
    Storage::disk('local')->assertExists($source->photos[0]['path']);
    Storage::disk('local')->assertMissing($terminal->photos[0]['path']);
    $restored = ProjectNarrativeReportDraft::where('report_type', 'terminal')->firstOrFail();
    expect($restored->source_data['terminal_data']['conclusions'])->toContain('The objectives were achieved.')
        ->and($restored->source_data['saved_photo_1'])->not->toBeNull();
    $payload['reuse_photo_1'] = '999999:0';
    $this->postJson(route('project-narrative-reports.preview', $this->topic), $payload)->assertUnprocessable()->assertJsonValidationErrors('reuse_photo_1');
});

test('terminal final preparation waits for all monitoring reports but allows drafting', function () {
    ($this->completeQuarterlyProgress)();
    $this->topic->progressReports()->first()->delete();
    $this->actingAs($this->researcher)->post(route('project-narrative-reports.prepare', $this->topic), ($this->terminalPayload)())->assertSessionHasErrors('preparation', null, 'narrativeProgress');
    $this->postJson(route('project-narrative-reports.draft', $this->topic), ['draft_version' => 0, 'report_type' => 'terminal', 'terminal_data' => ['abstract' => 'An unfinished draft.']])->assertOk();
});

test('other faculty cannot read write or preview a project terminal report', function () {
    $other = User::factory()->create();
    $other->assignRole('faculty_researcher');
    $this->actingAs($other)->postJson(route('project-narrative-reports.preview', $this->topic), ($this->terminalPayload)())->assertForbidden();
    $this->postJson(route('project-narrative-reports.draft', $this->topic), ['draft_version' => 0, 'report_type' => 'terminal'])->assertForbidden();
});

test('terminal preparation and submission require each submitted Progress Report without outstanding corrections', function (string $state) {
    ($this->completeQuarterlyProgress)();
    $progress = $this->topic->narrativeReports()->where('report_type', 'progress')->firstOrFail();
    $this->actingAs($this->researcher)->post(route('project-narrative-reports.prepare', $this->topic), ($this->terminalPayload)())->assertSessionHasNoErrors();
    $terminal = ProjectNarrativeReport::where('report_type', 'terminal')->sole();
    if ($state === 'missing') {
        $progress->delete();
    } elseif ($state === 'prepared') {
        $progress->update(['submission_status' => 'prepared']);
    } else {
        $progress->update(['review_status' => 'revision_requested']);
    }
    $this->post(route('project-narrative-reports.submit-prepared', [$this->topic, $terminal]))->assertForbidden();
    expect($terminal->fresh()->isPrepared())->toBeTrue();
    $this->delete(route('project-narrative-reports.discard-prepared', [$this->topic, $terminal]))->assertSessionHasNoErrors();
    $this->post(route('project-narrative-reports.prepare', $this->topic), ($this->terminalPayload)())->assertSessionHasErrorsIn('narrativeProgress', 'preparation');
    $this->get(route('project-narrative-reports.create', ['topic' => $this->topic, 'report_type' => 'terminal']))
        ->assertOk()->assertSee('Before preparing the official copy, complete Progress Report');
})->with(['missing', 'prepared', 'revision_requested']);

test('terminal revisions reuse final content while preserving old versions and removing deleted tables', function () {
    ($this->completeQuarterlyProgress)();
    $payload = ($this->terminalPayload)();
    $this->actingAs($this->researcher)->post(route('project-narrative-reports.prepare', $this->topic), $payload)->assertSessionHasNoErrors();
    $first = ProjectNarrativeReport::where('report_type', 'terminal')->firstOrFail();
    $this->post(route('project-narrative-reports.submit-prepared', [$this->topic, $first]))->assertSessionHasNoErrors();
    $first->update(['review_status' => 'revision_requested']);
    $defaults = app(TerminalReportData::class)->defaults($this->topic->fresh());
    expect($defaults['terminal_data']['conclusions'])->toContain('The objectives were achieved.')
        ->and($defaults['terminal_data']['supersedes_report_id'])->toBe($first->id)
        ->and($defaults['terminal_data']['authors'][0]['date_signed'])->toBe('');
    unset($payload['terminal_data']['tables']);
    $this->postJson(route('project-narrative-reports.draft', $this->topic), [...$payload, 'draft_version' => 0])->assertOk();
    expect(ProjectNarrativeReportDraft::firstOrFail()->source_data['terminal_data']['tables'])->toBe([]);
    $this->post(route('project-narrative-reports.prepare', $this->topic), $payload)->assertSessionHasNoErrors();
    $second = ProjectNarrativeReport::where('report_type', 'terminal')->latest('id')->firstOrFail();
    expect($second->id)->not->toBe($first->id)
        ->and($second->terminal_data['version_number'])->toBe(2)
        ->and($second->terminal_data['tables'])->toBe([])
        ->and($first->fresh()->terminal_data['tables'])->toHaveCount(1);
    Storage::disk('local')->assertExists($first->official_pdf_path);
});

test('only requested corrections permit a replacement Terminal Report', function (string $status) {
    ($this->completeQuarterlyProgress)();
    $this->actingAs($this->researcher)->post(route('project-narrative-reports.prepare', $this->topic), ($this->terminalPayload)())->assertSessionHasNoErrors();
    $first = ProjectNarrativeReport::where('report_type', 'terminal')->sole();
    $first->update(['submission_status' => 'submitted', 'review_status' => $status]);
    $this->post(route('project-narrative-reports.prepare', $this->topic), ($this->terminalPayload)())
        ->assertSessionHasErrorsIn('narrativeProgress', 'preparation');
    expect(ProjectNarrativeReport::where('report_type', 'terminal')->count())->toBe(1);
    Storage::disk('local')->assertExists($first->official_pdf_path);
})->with(['pending', 'reviewed']);

test('terminal report shows no utilization percentage for an unfunded project', function () {
    $this->topic->update(['estimated_budget' => 0]);
    $this->actingAs($this->researcher)->post(route('project-narrative-reports.preview', $this->topic), ($this->terminalPayload)(['terminal_data' => ['total_expenditure' => 0]]))
        ->assertOk()->assertSee('N/A');
});

test('terminal defaults carry approved proposal information and work plan targets', function () {
    $version = $this->topic->versions()->create([
        'submitted_by' => $this->researcher->id, 'version_number' => 1, 'submission_type' => 'initial',
        'file_path' => 'proposal.pdf', 'original_filename' => 'proposal.pdf', 'mime_type' => 'application/pdf',
        'file_size' => 100, 'checksum' => hash('sha256', 'proposal'), 'title' => $this->topic->title,
        'estimated_budget' => 42000, 'estimated_duration_months' => 12,
    ]);
    foreach ([
        'detailed_proposal' => ['project_title' => 'Approved research title', 'project_leader_display' => 'Approved project leader', 'proponent_campus' => 'Nasugbu', 'proponent_college' => 'CICS', 'staff' => [['display_name' => 'Approved research staff']], 'introduction' => 'Approved introduction', 'rationale' => 'Approved rationale', 'related_literature' => 'Approved literature', 'references' => 'Approved reference', 'methodology' => ['research_design' => 'Approved methods']],
        'work_plan' => ['planned_start' => '2025-08-08', 'planned_end' => '2026-08-07', 'total_duration_months' => 12, 'entries' => [['objective' => 'Approved objective', 'activity' => 'Complete approved fieldwork', 'expected_output' => 'Approved target']]],
    ] as $type => $source) {
        $version->files()->create(['document_type' => $type, 'position' => 1, 'file_path' => $type.'.pdf', 'original_filename' => $type.'.pdf', 'mime_type' => 'application/pdf', 'file_size' => 100, 'checksum' => hash('sha256', $type), 'source_data' => $source]);
    }
    $monitoringReports = $this->topic->progressReports()->orderBy('reporting_date')->take(2)->get();
    $monitoringReports[0]->update(['work_plan' => [[
        'source_work_plan_index' => 0,
        'objective' => 'Approved objective',
        'activity' => 'Complete approved fieldwork',
        'actual_accomplishment' => 'Completed the baseline fieldwork.',
    ]]]);
    $monitoringReports[1]->update(['work_plan' => [[
        'source_work_plan_index' => 0,
        'objective' => 'Approved objective',
        'activity' => 'Complete approved fieldwork',
        'actual_accomplishment' => 'Validated the final fieldwork dataset.',
    ]]]);

    $terminalData = app(TerminalReportData::class);
    $defaults = $terminalData->defaults($this->topic->fresh());
    $normalized = $terminalData->normalize($this->topic->fresh(), [
        'report_type' => 'terminal',
        'accomplishments' => [[
            'objective' => 'Forged replacement objective',
            'target' => 'Forged replacement target',
            'actual' => 'Final verified outcome.',
        ]],
    ]);

    expect($defaults['terminal_data']['project_title'])->toBe('Approved research title')
        ->and($defaults['terminal_data']['approved_budget'])->toEqual(42000)
        ->and($defaults['terminal_data']['authors'][1]['name'])->toBe('Approved research staff')
        ->and($defaults['terminal_data']['source_proposal_version_id'])->toBe($version->id)
        ->and($defaults['terminal_data']['approved_start'])->toBe('2025-08-08')
        ->and($defaults['objectives_from_work_plan'])->toBeTrue()
        ->and($defaults['accomplishments'][0]['target'])->toBe('Approved target')
        ->and($defaults['accomplishments'][0]['actual'])->toContain('Completed the baseline fieldwork.')
        ->and($defaults['accomplishments'][0]['actual'])->toContain('Validated the final fieldwork dataset.')
        ->and($normalized['accomplishments'][0]['objective'])->toBe('Approved objective')
        ->and($normalized['accomplishments'][0]['target'])->toBe('Approved target')
        ->and($normalized['accomplishments'][0]['actual'])->toBe('Final verified outcome.')
        ->and($defaults['introduction'])->toBe('Approved introduction')
        ->and($defaults['methodology'])->toBe('Approved methods')
        ->and($defaults['implementation_end'])->toBe('');
});
