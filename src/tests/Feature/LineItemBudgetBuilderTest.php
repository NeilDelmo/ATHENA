<?php

use App\Models\ProposalDraft;
use App\Models\ProposalVersionFile;
use App\Models\ResearchCall;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['faculty', 'research_head'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }

    $head = User::factory()->create();
    $head->assignRole('research_head');
    $this->faculty = User::factory()->create(['name' => 'Faculty Project Leader']);
    $this->faculty->assignRole('faculty');
    $call = ResearchCall::create([
        'title' => 'Open Research Call',
        'academic_year' => '2026-2027',
        'opens_at' => now()->subDay(),
        'closes_at' => now()->addMonth(),
        'max_active_research_per_faculty' => 2,
        'maximum_budget' => 100000,
        'status' => 'open',
        'created_by' => $head->id,
    ]);
    $this->draft = ProposalDraft::create([
        'user_id' => $this->faculty->id,
        'research_call_id' => $call->id,
        'project_title' => 'Community Coastal Research',
        'duration_months' => 12,
        'planned_start' => '2026-08-01',
        'planned_end' => '2027-07-31',
        'project_leader' => 'Faculty Project Leader',
    ]);
    $this->payload = fn (array $overrides = []): array => [
        'leader_campus' => 'Pablo Borbon Campus',
        'leader_college' => 'College of Arts and Sciences',
        'staff' => [
            ['name' => 'Researcher One', 'campus' => 'Alangilan Campus', 'college' => 'College of Engineering'],
            ['name' => 'Researcher Two', 'campus' => '', 'college' => 'College of Informatics'],
        ],
        'amounts' => [
            'travelling_expenses' => '10000.00',
            'contingency' => '1000.00',
            'ict_equipment' => '3000.00',
        ],
        'custom_mooe_items' => [['particular' => 'Community consultation supplies', 'amount' => '5000.00']],
        'custom_co_items' => [['particular' => 'Field measurement device', 'amount' => '2000.00']],
        'level_of_call' => 'constituent_campus',
        'approval_body' => 'lrec',
        'resolution_number' => '1',
        'resolution_year' => '2026',
        ...$overrides,
    ];

    Storage::fake('local');
    $this->withoutVite();
});

test('the line item budget saves optional structured inputs and resumes them', function () {
    $payload = ($this->payload)([
        'mooe_total_override' => '17000.00',
        'co_total_override' => '6000.00',
        'project_total_override' => '23000.00',
    ]);

    $this->actingAs($this->faculty)
        ->put(route('faculty.proposal-drafts.line-item-budget.update', $this->draft), $payload)
        ->assertRedirect(route('faculty.proposal-drafts.show', $this->draft));

    $document = $this->draft->documents()
        ->where('document_type', ProposalVersionFile::TYPE_LINE_ITEM_BUDGET)
        ->sole();

    expect($document->completed_at)->not->toBeNull()
        ->and($document->source_data['staff'])->toHaveCount(2)
        ->and($document->source_data['amounts']['travelling_expenses'])->toBe('10000.00')
        ->and($document->source_data['project_total_override'])->toBe('23000.00')
        ->and($document->source_data)->not->toHaveKeys(['project_title', 'planned_start', 'planned_end', 'project_leader']);

    $this->actingAs($this->faculty)
        ->get(route('faculty.proposal-drafts.line-item-budget.edit', $this->draft))
        ->assertOk()
        ->assertSee('Researcher One')
        ->assertSee('Community consultation supplies')
        ->assertSee('Program Title stays empty')
        ->assertSee('ARASOF-Nasugbu')
        ->assertSee('value="CICS"', false)
        ->assertSee('value="CTE"', false)
        ->assertSee('value="CABEIHM"', false)
        ->assertSee('value="CCJE"', false)
        ->assertSee('value="CAS"', false)
        ->assertSee('value="CHS"', false)
        ->assertDontSee('College of Informatics and Computing Sciences');
});

test('empty optional fields are accepted while totals remain automatic', function () {
    $this->draft->update(['project_leader' => 'SHEENA LEI DELMO']);

    $this->actingAs($this->faculty)
        ->post(route('faculty.proposal-drafts.line-item-budget.preview', $this->draft), [])
        ->assertOk()
        ->assertSee('Community Coastal Research')
        ->assertSee('ARASOF-Nasugbu')
        ->assertSee('Sheena Lei Delmo')
        ->assertDontSee('SHEENA LEI DELMO')
        ->assertSee('0.00')
        ->assertSee('DJOANNA MARIE V. SALAC')
        ->assertSee('Head, Research');
});

test('the generated Word file preserves the official structure and fills dynamic rows', function () {
    $this->draft->update(['project_leader' => 'SHEENA LEI DELMO']);

    $response = $this->actingAs($this->faculty)
        ->post(route('faculty.proposal-drafts.line-item-budget.download', $this->draft), ($this->payload)())
        ->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')
        ->assertDownload('community-coastal-research-line-item-budget.docx');

    $temporaryPath = tempnam(sys_get_temp_dir(), 'line-budget-test-');
    file_put_contents($temporaryPath, $response->streamedContent());
    $archive = new ZipArchive;

    try {
        expect($archive->open($temporaryPath))->toBeTrue();
        $documentXml = $archive->getFromName('word/document.xml');
        $footerXml = $archive->getFromName('word/footer1.xml');
        $settingsXml = $archive->getFromName('word/settings.xml');
        $document = new DOMDocument;
        $document->loadXML($documentXml, LIBXML_NONET);
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $rows = $xpath->query('//w:body/w:tbl[1]/w:tr');
        $tableGridWidths = [];

        foreach ($xpath->query('//w:body/w:tbl[1]/w:tblGrid/w:gridCol') as $gridColumn) {
            $tableGridWidths[] = (string) $xpath->evaluate('string(@w:w)', $gridColumn);
        }

        $rowText = fn (int $index): string => trim((string) $xpath->evaluate('string(.)', $rows->item($index)));
        $findRow = function (string $text) use ($rows, $xpath): DOMNode {
            foreach ($rows as $row) {
                if (str_contains((string) $xpath->evaluate('string(.)', $row), $text)) {
                    return $row;
                }
            }

            throw new RuntimeException("Missing row {$text}");
        };

        expect($rows->length)->toBe(48)
            ->and($tableGridWidths)->toBe(['461', '463', '625', '4168', '2176', '2313'])
            ->and($rowText(0))->toBe('Program Title:')
            ->and($rowText(1))->toContain('Community Coastal Research')
            ->and($xpath->evaluate('string((//w:body/w:tbl[1]/w:tr)[2]/w:tc[2]/w:p/w:pPr/w:jc/@w:val)'))->toBe('left')
            ->and($xpath->query('(//w:body/w:tbl[1]/w:tr)[2]/w:tc[2]//w:b')->length)->toBe(0)
            ->and($xpath->query('(//w:body/w:tbl[1]/w:tr)[3]/w:tc[2]//w:b')->length)->toBeGreaterThan(0)
            ->and($xpath->query('(//w:body/w:tbl[1]/w:tr)[3]/w:tc[3]//w:b')->length)->toBeGreaterThan(0)
            ->and($xpath->query('(//w:body/w:tbl[1]/w:tr)[3]/w:tc[4]//w:b')->length)->toBeGreaterThan(0)
            ->and($documentXml)->toContain('Sheena Lei Delmo')
            ->and($documentXml)->not->toContain('SHEENA LEI DELMO')
            ->and($documentXml)->toContain('Researcher One')
            ->and($documentXml)->toContain('Researcher Two')
            ->and($documentXml)->toContain('August 1, 2026 - July 31, 2027')
            ->and($documentXml)->toContain('Community consultation supplies')
            ->and($documentXml)->toContain('Field measurement device')
            ->and(trim((string) $xpath->evaluate('string(.)', $findRow('Total for Maintenance'))))->toContain('16,000.00')
            ->and(trim((string) $xpath->evaluate('string(.)', $findRow('Total for Capital'))))->toContain('5,000.00')
            ->and(trim((string) $xpath->evaluate('string(.)', $findRow('TOTAL PROJECT COST'))))->toContain('21,000.00')
            ->and($documentXml)->toContain('DJOANNA MARIE V. SALAC')
            ->and($documentXml)->toContain('Head, Research')
            ->and($documentXml)->not->toContain('Vice President for Research, Development and Extension Services')
            ->and($documentXml)->not->toContain('Vice Chairperson, Research Council **')
            ->and($documentXml)->toContain('Approved by the Local Research Evaluation Committee as per LREC Resolution No. 1, S. 2026')
            ->and(substr_count($documentXml, 'w:default w:val="1"'))->toBe(1)
            ->and($footerXml)->toContain('Community Coastal Research')
            ->and($settingsXml)->toContain('updateFields');

    } finally {
        $archive->close();

        if (is_file($temporaryPath)) {
            unlink($temporaryPath);
        }
    }
});

test('contingency and the research call budget ceiling are validated', function () {
    $this->actingAs($this->faculty)
        ->post(route('faculty.proposal-drafts.line-item-budget.preview', $this->draft), ($this->payload)([
            'amounts' => ['travelling_expenses' => 1000, 'contingency' => 1000],
        ]))
        ->assertSessionHasErrors('amounts.contingency');

    $this->actingAs($this->faculty)
        ->post(route('faculty.proposal-drafts.line-item-budget.preview', $this->draft), ($this->payload)([
            'project_total_override' => 100001,
        ]))
        ->assertSessionHasErrors('project_total_override');
});
