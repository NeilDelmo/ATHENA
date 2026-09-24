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
        ->assertSee('data-monitoring-action-dock-fixed', false)
        ->assertSee('fixed inset-x-4 bottom-4', false)
        ->assertDontSee('More figures (4–30)');
    if (getenv('TERMINAL_REPORT_QA_PATH')) {
        file_put_contents(getenv('TERMINAL_REPORT_QA_PATH').'.html', $form->getContent());
    }
    $this->post(route('project-narrative-reports.preview', $this->topic), ($this->terminalPayload)())
        ->assertOk()->assertSee('IV. Abstract')->assertSee('Conclusions')->assertSee('Bibliography')->assertSee('81.89%')
        ->assertSee('Table 1. Evaluation findings')->assertDontSee('BatStateU-REC-RES-02');
});

test('terminal cover poster is previewed stored and embedded in the official document', function () {
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
        ->and($restored->source_data['reuse_photo_1'])->toBe($source->id.':0');
    $payload['reuse_photo_1'] = '999999:0';
    $this->postJson(route('project-narrative-reports.preview', $this->topic), $payload)->assertUnprocessable()->assertJsonValidationErrors('reuse_photo_1');
});

test('terminal final preparation waits for all monitoring reports but allows drafting', function () {
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

test('terminal revisions reuse final content while preserving old versions and removing deleted tables', function () {
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
