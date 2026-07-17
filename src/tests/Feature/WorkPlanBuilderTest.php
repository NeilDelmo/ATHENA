<?php

use App\Models\ProposalDraft;
use App\Models\ResearchCall;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['faculty', 'faculty_researcher', 'research_head'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }

    $this->faculty = User::factory()->create(['name' => 'Faculty Project Leader']);
    $this->faculty->assignRole('faculty');
    $this->head = User::factory()->create();
    $this->head->assignRole('research_head');
    $this->withoutVite();

    $this->validWorkPlan = fn (): array => [
        'title' => 'Coastal Research Work Plan',
        'project_title' => 'Community-led Coastal Habitat Restoration',
        'total_duration_months' => 12,
        'planned_start' => '2026-08-01',
        'planned_end' => '2027-07-31',
        'entries' => [
            [
                'objective' => 'Document the baseline habitat condition',
                'expected_output' => 'Validated baseline habitat profile',
                'activity' => "Conduct field survey\nComplete community mapping",
                'months' => [1, 2, 3],
            ],
        ],
        'prepared_by' => 'Faculty Project Leader',
    ];
});

test('faculty members see the proposal workflow with an automatic Work Plan requirement', function () {
    $researchCall = ResearchCall::create([
        'title' => 'Open Work Plan Call',
        'academic_year' => '2026-2027',
        'opens_at' => now()->subDay(),
        'closes_at' => now()->addMonth(),
        'max_active_research_per_faculty' => 2,
        'status' => 'open',
        'created_by' => $this->head->id,
    ]);
    $draft = ProposalDraft::create([
        'user_id' => $this->faculty->id,
        'research_call_id' => $researchCall->id,
        'project_title' => 'Community-led Coastal Habitat Restoration',
        'duration_months' => 12,
        'planned_start' => '2026-08-01',
        'planned_end' => '2027-07-31',
        'project_leader' => 'Faculty Project Leader',
    ]);

    $this->actingAs($this->faculty)
        ->get(route('faculty.topics.create'))
        ->assertRedirect(route('faculty.proposal-drafts.index'));

    $this->actingAs($this->faculty)
        ->get(route('faculty.proposal-drafts.work-plan.edit', $draft))
        ->assertOk()
        ->assertSee('Attachment A: Work Plan')
        ->assertSee('Objectives and Gantt schedule')
        ->assertSee('Add another objective')
        ->assertSee('Each month can belong to only one objective.')
        ->assertSee('automatically expands each row')
        ->assertSee('DJOANNA MARIE V. SALAC')
        ->assertSee('Head, Research')
        ->assertSee('Download Word file')
        ->assertDontSee('type="file"', false);
});

test('the Work Plan preview and download are limited to faculty roles', function () {
    $this->post(route('faculty.work-plans.preview'), ($this->validWorkPlan)())
        ->assertUnauthorized();

    $this->actingAs($this->head)
        ->post(route('faculty.work-plans.preview'), ($this->validWorkPlan)())
        ->assertForbidden();

    $this->actingAs($this->head)
        ->post(route('faculty.work-plans.download'), ($this->validWorkPlan)())
        ->assertForbidden();
});

test('the Work Plan validates the fixed Year 1 layout and dynamic objectives', function () {
    $payload = ($this->validWorkPlan)();
    $payload['total_duration_months'] = 13;
    $payload['planned_end'] = '2026-07-31';
    $payload['entries'][0]['months'] = [13];

    $this->actingAs($this->faculty)
        ->postJson(route('faculty.work-plans.preview'), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'total_duration_months',
            'planned_end',
            'entries.0.months.0',
        ]);

    $payload = ($this->validWorkPlan)();
    $payload['total_duration_months'] = 3;
    $payload['entries'][0]['months'] = [4];

    $this->actingAs($this->faculty)
        ->postJson(route('faculty.work-plans.preview'), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('entries.0.months.0');

    $payload = ($this->validWorkPlan)();
    $payload['entries'] = array_fill(0, config('work_plan.max_objectives') + 1, $payload['entries'][0]);

    $this->actingAs($this->faculty)
        ->postJson(route('faculty.work-plans.preview'), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('entries');
});

test('the Gantt schedule assigns each month to only one objective', function () {
    $payload = ($this->validWorkPlan)();
    $payload['entries'][] = [
        'objective' => 'Develop the implementation model',
        'expected_output' => 'Validated implementation model',
        'activity' => 'Develop and validate the model',
        'months' => [3, 4, 5],
    ];

    $this->actingAs($this->faculty)
        ->postJson(route('faculty.work-plans.preview'), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('entries.1.months')
        ->assertJsonFragment([
            'M3 is already assigned to Objective 1. Each month can be assigned to only one objective.',
        ]);

    $payload['entries'][1]['months'] = [4, 5, 6];

    $this->actingAs($this->faculty)
        ->post(route('faculty.work-plans.preview'), $payload)
        ->assertOk();
});

test('the Gantt schedule rejects a repeated month within one objective', function () {
    $payload = ($this->validWorkPlan)();
    $payload['entries'][0]['months'] = [1, 1, 2];

    $this->actingAs($this->faculty)
        ->postJson(route('faculty.work-plans.preview'), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('entries.0.months');
});

test('the number of objectives cannot exceed the number of available project months', function () {
    $payload = ($this->validWorkPlan)();
    $payload['total_duration_months'] = 2;
    $payload['entries'] = collect(range(1, 3))
        ->map(fn (int $number): array => [
            'objective' => 'Objective '.$number,
            'expected_output' => 'Output '.$number,
            'activity' => 'Activity '.$number,
            'months' => [min($number, 2)],
        ])
        ->all();

    $this->actingAs($this->faculty)
        ->postJson(route('faculty.work-plans.preview'), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('entries');
});

test('the preview expands objective rows, shades Gantt months, and fixes the verifier', function () {
    $payload = ($this->validWorkPlan)();
    $payload['project_title'] = 'Coastal <script>alert(1)</script> Project';
    $payload['prepared_date'] = '2026-07-30';
    $payload['verified_date'] = '2026-08-02';
    $payload['entries'] = collect(range(1, 7))
        ->map(fn (int $number): array => [
            'objective' => 'Objective '.$number,
            'expected_output' => 'Output '.$number,
            'activity' => 'Activity '.$number,
            'months' => [$number],
        ])
        ->all();

    $response = $this->actingAs($this->faculty)
        ->post(route('faculty.work-plans.preview'), $payload, ['Accept' => 'application/json'])
        ->assertOk()
        ->assertSee('Attachment A-BatStateU-FO-RES-02')
        ->assertSee('MAJOR ACTIVITIES/WORK PLAN')
        ->assertDontSee('Coastal Research Work Plan')
        ->assertSee('Coastal &lt;script&gt;alert(1)&lt;/script&gt; Project', false)
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertSee('DJOANNA MARIE V. SALAC')
        ->assertSee('Head, Research')
        ->assertDontSee('July 30, 2026')
        ->assertDontSee('August 2, 2026')
        ->assertDontSee('Director, Research / Head, Research')
        ->assertDontSee('>NAME<', false)
        ->assertDontSee('work-plan-signature-date"><span', false)
        ->assertDontSee('work-plan-signature-label', false);

    expect(substr_count($response->getContent(), 'data-work-plan-entry-row'))->toBe(7)
        ->and(substr_count($response->getContent(), 'class="work-plan-objective-cell"'))->toBe(7)
        ->and(substr_count($response->getContent(), 'class="work-plan-output-cell"'))->toBe(7)
        ->and(substr_count($response->getContent(), 'data-scheduled-month'))->toBe(7)
        ->and(substr_count($response->getContent(), 'data-signature-line'))->toBe(2)
        ->and(substr_count($response->getContent(), 'data-signature-name'))->toBe(2)
        ->and(substr_count($response->getContent(), 'data-signature-date'))->toBe(2)
        ->and(substr_count($response->getContent(), 'data-work-plan-metadata-value'))->toBe(3)
        ->and(substr_count($response->getContent(), 'data-work-plan-title-value'))->toBe(1)
        ->and(substr_count($response->getContent(), 'data-work-plan-project-title'))->toBe(1)
        ->and($response->getContent())->not->toContain('>X<');
});

test('the Word download patches only the official template body', function () {
    $payload = ($this->validWorkPlan)();
    $payload['entries'] = collect(range(1, 7))
        ->map(fn (int $number): array => [
            'objective' => 'Objective '.$number,
            'expected_output' => 'Output '.$number,
            'activity' => "Activity {$number}A\nActivity {$number}B",
            'months' => [$number],
        ])
        ->all();

    $response = $this->actingAs($this->faculty)
        ->post(route('faculty.work-plans.download'), $payload)
        ->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')
        ->assertDownload('community-led-coastal-habitat-restoration-work-plan.docx');

    $temporaryPath = tempnam(sys_get_temp_dir(), 'work-plan-test-');
    file_put_contents($temporaryPath, $response->streamedContent());

    try {
        $generatedArchive = new ZipArchive;
        $templateArchive = new ZipArchive;

        expect($generatedArchive->open($temporaryPath))->toBeTrue()
            ->and($templateArchive->open(config('work_plan.template_path')))->toBeTrue();

        $documentXml = $generatedArchive->getFromName('word/document.xml');
        $document = new DOMDocument;
        $document->loadXML($documentXml, LIBXML_NONET);
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $titleCells = $xpath->query('(//w:body/w:tbl[1]/w:tr)[1]/w:tc');
        $projectTitleCells = $xpath->query('(//w:body/w:tbl[1]/w:tr)[2]/w:tc');
        $metadataCells = $xpath->query('(//w:body/w:tbl[1]/w:tr)[3]/w:tc');
        $durationParagraphs = $xpath->query('./w:p', $metadataCells->item(0));
        $plannedStartParagraphs = $xpath->query('./w:p', $metadataCells->item(1));
        $plannedEndParagraphs = $xpath->query('./w:p', $metadataCells->item(2));
        $firstEntryCells = $xpath->query('(//w:body/w:tbl[1]/w:tr)[6]/w:tc');
        $signatureCells = $xpath->query('(//w:body/w:tbl[1]/w:tr)[last()]/w:tc');
        $preparedParagraphs = $xpath->query('./w:p', $signatureCells->item(0));
        $verifiedParagraphs = $xpath->query('./w:p', $signatureCells->item(1));
        $paragraphText = fn (DOMNode $paragraph): string => trim((string) $xpath->evaluate('string(.)', $paragraph));
        $hasDirectFormatting = fn (DOMNode $paragraph, string $format): bool => $xpath->query("./w:r/w:rPr/w:{$format}", $paragraph)->length > 0;

        expect($xpath->query('//w:body/w:tbl[1]/w:tr')->length)->toBe(13)
            ->and($xpath->query('(//w:body/w:tbl[1]/w:tr)[position() >= 6 and position() <= 12]/w:trPr/w:trHeight')->length)->toBe(0)
            ->and(trim((string) $xpath->evaluate('string(.)', $titleCells->item(1))))->toBe('')
            ->and(trim((string) $xpath->evaluate('string(.)', $projectTitleCells->item(1))))->toBe('Community-led Coastal Habitat Restoration')
            ->and($xpath->evaluate('string(./w:p/w:pPr/w:jc/@w:val)', $projectTitleCells->item(1)))->toBe('center')
            ->and($xpath->evaluate('string(./w:tcPr/w:vAlign/@w:val)', $projectTitleCells->item(1)))->toBe('center')
            ->and($documentXml)->not->toContain('Coastal Research Work Plan')
            ->and($documentXml)->toContain('Community-led Coastal Habitat Restoration')
            ->and($documentXml)->toContain('Faculty Project Leader')
            ->and($documentXml)->toContain('DJOANNA MARIE V. SALAC')
            ->and($documentXml)->toContain('Head, Research')
            ->and(substr_count($documentXml, 'w:fill="E7E6E6"'))->toBe(7)
            ->and($metadataCells->length)->toBe(3)
            ->and($paragraphText($durationParagraphs->item(1)))->toBe('12 months')
            ->and($paragraphText($plannedStartParagraphs->item(1)))->toBe('August 1, 2026')
            ->and($paragraphText($plannedEndParagraphs->item(1)))->toBe('July 31, 2027')
            ->and($hasDirectFormatting($durationParagraphs->item(1), 'b'))->toBeFalse()
            ->and($hasDirectFormatting($durationParagraphs->item(1), 'u'))->toBeFalse()
            ->and($hasDirectFormatting($durationParagraphs->item(1), 'sz'))->toBeFalse()
            ->and($hasDirectFormatting($plannedStartParagraphs->item(1), 'b'))->toBeFalse()
            ->and($hasDirectFormatting($plannedStartParagraphs->item(1), 'u'))->toBeFalse()
            ->and($hasDirectFormatting($plannedStartParagraphs->item(1), 'sz'))->toBeFalse()
            ->and($hasDirectFormatting($plannedEndParagraphs->item(1), 'b'))->toBeFalse()
            ->and($hasDirectFormatting($plannedEndParagraphs->item(1), 'u'))->toBeFalse()
            ->and($hasDirectFormatting($plannedEndParagraphs->item(1), 'sz'))->toBeFalse()
            ->and($xpath->evaluate('string(./w:p/w:pPr/w:jc/@w:val)', $firstEntryCells->item(0)))->toBe('center')
            ->and($xpath->evaluate('string(./w:p/w:pPr/w:jc/@w:val)', $firstEntryCells->item(1)))->toBe('center')
            ->and($xpath->evaluate('string(./w:p/w:pPr/w:jc/@w:val)', $firstEntryCells->item(2)))->toBe('left')
            ->and($signatureCells->length)->toBe(2)
            ->and($paragraphText($preparedParagraphs->item(3)))->toMatch('/^_{10,}$/')
            ->and($paragraphText($preparedParagraphs->item(4)))->toBe('Faculty Project Leader')
            ->and($paragraphText($preparedParagraphs->item(5)))->toBe('Project Leader')
            ->and($paragraphText($preparedParagraphs->item(6)))->toBe('Date Signed:')
            ->and($hasDirectFormatting($preparedParagraphs->item(6), 'b'))->toBeFalse()
            ->and($hasDirectFormatting($preparedParagraphs->item(6), 'u'))->toBeFalse()
            ->and($hasDirectFormatting($preparedParagraphs->item(6), 'sz'))->toBeFalse()
            ->and($paragraphText($verifiedParagraphs->item(3)))->toMatch('/^_{10,}$/')
            ->and($paragraphText($verifiedParagraphs->item(4)))->toBe('DJOANNA MARIE V. SALAC')
            ->and($paragraphText($verifiedParagraphs->item(5)))->toBe('Head, Research')
            ->and($paragraphText($verifiedParagraphs->item(6)))->toBe('Date Signed:')
            ->and($hasDirectFormatting($verifiedParagraphs->item(6), 'b'))->toBeFalse()
            ->and($hasDirectFormatting($verifiedParagraphs->item(6), 'u'))->toBeFalse()
            ->and($hasDirectFormatting($verifiedParagraphs->item(6), 'sz'))->toBeFalse()
            ->and($documentXml)->not->toContain('>NAME<');

        for ($index = 0; $index < $templateArchive->numFiles; $index++) {
            $partName = $templateArchive->getNameIndex($index);

            if ($partName === 'word/document.xml') {
                continue;
            }

            expect(hash('sha256', $generatedArchive->getFromName($partName)))
                ->toBe(hash('sha256', $templateArchive->getFromName($partName)));
        }

        $generatedArchive->close();
        $templateArchive->close();
    } finally {
        if (is_file($temporaryPath)) {
            unlink($temporaryPath);
        }
    }
});
