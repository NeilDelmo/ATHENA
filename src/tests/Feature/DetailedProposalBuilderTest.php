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
    $this->faculty = User::factory()->create([
        'name' => 'Faculty Project Leader',
        'email' => 'leader@g.batstate-u.edu.ph',
        'college' => 'College of Informatics and Computing Sciences',
    ]);
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
        'document_version' => 0,
        'research_agenda' => 'Environment, Natural Resources, and Climate Change',
        'sdgs' => [1, 10, 17],
        'leader_email' => 'leader@g.batstate-u.edu.ph',
        'leader_contact' => '+63 917 123 4567',
        'staff' => [[
            'name' => 'Research Staff Member',
            'email' => 'staff@g.batstate-u.edu.ph',
            'contact' => '+63 918 765 4321',
        ]],
        'proponent_department' => 'Department of Computing Sciences',
        'proponent_college' => 'College of Informatics and Computing Sciences',
        'proponent_campus' => 'ARASOF-Nasugbu',
        'cooperating_agency' => 'Municipality of Nasugbu',
        'executive_brief' => "This project develops a community-led coastal monitoring system.\nIt combines field observation and local knowledge.",
        'rationale' => 'Coastal communities require timely, reliable environmental information for local decisions.',
        'objectives' => "1. Establish a baseline coastal profile.\n2. Develop and validate the monitoring workflow.",
        'expected_outputs' => [
            'publication' => 'One peer-reviewed journal article',
            'patent' => '',
            'product' => 'Coastal monitoring dashboard',
            'people_service' => 'Training for community monitors',
            'place_partnership' => 'University-LGU partnership',
            'policy' => 'Local monitoring protocol',
            'social_impact' => 'Improved community participation',
            'economic_impact' => 'Reduced monitoring costs',
        ],
        'related_literature' => 'Recent coastal monitoring studies demonstrate the value of participatory data collection.',
        'methodology' => [
            'research_design' => 'The study uses a sequential mixed-method research design.',
            'specific_methods' => 'Researchers will conduct surveys, interviews, and coastal transect observations.',
            'data_analysis' => 'Quantitative results will use descriptive statistics and qualitative data will use thematic analysis.',
        ],
        'responsibilities' => [
            ['name' => 'Faculty Project Leader', 'duties' => 'Leads the project, assures research quality, and coordinates reporting.'],
            ['name' => 'Research Staff Member', 'duties' => 'Coordinates field data collection and prepares the validated dataset.'],
        ],
        'references' => "Author, A. (2025). Participatory coastal monitoring. Research Journal, 1(1), 1-10.\nAuthor, B. (2024). Community environmental data. Coastal Studies, 2(1), 20-30.",
        ...$overrides,
    ];

    Storage::fake('local');
    $this->withoutVite();
});

test('the detailed proposal editor uses the official sections and pulls the leader account email', function () {
    $this->actingAs($this->faculty)
        ->get(route('faculty.proposal-drafts.detailed-proposal.edit', $this->draft))
        ->assertOk()
        ->assertSee('BatStateU-FO-RES-02 Rev. 04')
        ->assertSee('leader@g.batstate-u.edu.ph')
        ->assertSee('III. Sustainable Development Goal')
        ->assertSee('SDG17:')
        ->assertSee('XIII. Duties and Responsibilities of Each Member')
        ->assertSee('Download exact Word file')
        ->assertSee('Ctrl + S');
});

test('structured detailed proposal data saves, resumes, and observes optimistic locking', function () {
    $payload = ($this->payload)();

    $this->actingAs($this->faculty)
        ->put(route('faculty.proposal-drafts.detailed-proposal.update', $this->draft), $payload)
        ->assertRedirect(route('faculty.proposal-drafts.detailed-proposal.edit', $this->draft))
        ->assertSessionHas('success', 'Detailed Research Proposal saved.');

    $document = $this->draft->documents()
        ->where('document_type', ProposalVersionFile::TYPE_DETAILED_PROPOSAL)
        ->sole();

    expect($document->completed_at)->not->toBeNull()
        ->and($document->source_data['sdgs'])->toBe([1, 10, 17])
        ->and($document->source_data['staff'][0]['email'])->toBe('staff@g.batstate-u.edu.ph')
        ->and($document->source_data)->not->toHaveKeys(['project_title', 'project_leader']);

    $this->actingAs($this->faculty)
        ->get(route('faculty.proposal-drafts.detailed-proposal.edit', $this->draft))
        ->assertOk()
        ->assertSee('Municipality of Nasugbu')
        ->assertSee('Coastal monitoring dashboard');

    $stalePayload = $payload;
    $stalePayload['executive_brief'] = 'A stale overwrite.';

    $this->actingAs($this->faculty)
        ->put(route('faculty.proposal-drafts.detailed-proposal.update', $this->draft), $stalePayload)
        ->assertSessionHasErrors('document_version');
});

test('the generated Word file preserves every unrelated official package part and fills the exact form', function () {
    $response = $this->actingAs($this->faculty)
        ->post(route('faculty.proposal-drafts.detailed-proposal.download', $this->draft), ($this->payload)())
        ->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')
        ->assertDownload('community-coastal-research-detailed-research-proposal.docx');

    $temporaryPath = tempnam(sys_get_temp_dir(), 'detailed-proposal-test-');
    file_put_contents($temporaryPath, $response->streamedContent());
    $generatedArchive = new ZipArchive;
    $templateArchive = new ZipArchive;

    try {
        expect($generatedArchive->open($temporaryPath))->toBeTrue()
            ->and($templateArchive->open(config('detailed_proposal.template_path')))->toBeTrue();

        $documentXml = $generatedArchive->getFromName('word/document.xml');
        $settingsXml = $generatedArchive->getFromName('word/settings.xml');
        $document = new DOMDocument;
        $document->loadXML($documentXml, LIBXML_NONET);
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $xpath->registerNamespace('w14', 'http://schemas.microsoft.com/office/word/2010/wordml');
        $rows = $xpath->query('//w:body/w:tbl[1]/w:tr');
        $checkboxes = $xpath->query('//w:body/w:tbl[1]/w:tr[position() >= 6 and position() <= 14]//w14:checkbox');
        $checkedStates = [];
        $checkboxGlyphs = [];

        foreach ($checkboxes as $checkbox) {
            $checkedStates[] = (string) $xpath->evaluate('string(./w14:checked/@w14:val)', $checkbox);
            $checkboxGlyphs[] = (string) $xpath->evaluate('string(ancestor::w:sdt[1]/w:sdtContent//w:t)', $checkbox);
        }

        $rowText = fn (int $index): string => trim((string) $xpath->evaluate('string(.)', $rows->item($index)));
        $pageSize = $xpath->query('//w:body/w:sectPr/w:pgSz')->item(0);
        $projectTitleParagraph = $xpath->query('./w:tc/w:p', $rows->item(2))->item(0);
        $projectTitleRun = $xpath->query('.//w:r[w:t[contains(., "Community Coastal Research")]]', $projectTitleParagraph)->item(0);
        $leaderEmailParagraph = $xpath->query('./w:tc/w:p[3]', $rows->item(14))->item(0);
        $expectedOutputParagraph = $xpath->query('./w:tc/w:p[2]', $rows->item(20))->item(0);
        $methodologyHeading = $xpath->query('./w:tc/w:p[2]', $rows->item(22))->item(0);
        $responsibilityHeading = $xpath->query('./w:tc/w:p[2]', $rows->item(23))->item(0);
        $budgetValueParagraph = $xpath->query('./w:tc[2]/w:p', $rows->item(26))->item(0);
        $preparedDepartmentParagraph = $xpath->query('./w:tc[2]/w:p', $rows->item(31))->item(0);
        $officialPartNames = [];

        for ($index = 0; $index < $templateArchive->numFiles; $index++) {
            $officialPartNames[] = $templateArchive->getNameIndex($index);
        }

        expect($rows->length)->toBe(40)
            ->and($checkboxes->length)->toBe(17)
            ->and($checkedStates)->toBe(['1', '1', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '1', '0'])
            ->and($checkboxGlyphs)->toBe(['☒', '☒', '☐', '☐', '☐', '☐', '☐', '☐', '☐', '☐', '☐', '☐', '☐', '☐', '☐', '☒', '☐'])
            ->and($rowText(0))->toContain('Reference No.: BatStateU-FO-RES-02')
            ->and($rowText(2))->toContain('Community Coastal Research')
            ->and($rowText(14))->toContain('Faculty Project Leader')
            ->and($rowText(14))->toContain('Research Staff Member')
            ->and($rowText(15))->toContain('Batangas State University, The National Engineering University')
            ->and($rowText(17))->toContain('community-led coastal monitoring system')
            ->and($rowText(20))->toContain('One peer-reviewed journal article')
            ->and($rowText(22))->toContain('sequential mixed-method research design')
            ->and($rowText(23))->toContain('Coordinates field data collection')
            ->and($rowText(26))->toContain('Php 0.00')
            ->and($rowText(31))->toContain('Faculty Project Leader')
            ->and($rowText(35))->toContain('To be accomplished by the Research Office')
            ->and($xpath->query('.//w:br', $projectTitleParagraph)->length)->toBe(1)
            ->and($xpath->evaluate('string(w:rPr/w:sz/@w:val)', $projectTitleRun))->toBe('22')
            ->and($xpath->query('./w:rPr/w:b', $projectTitleRun)->length)->toBe(1)
            ->and($xpath->query('.//w:b', $leaderEmailParagraph)->length)->toBe(0)
            ->and($xpath->evaluate('string(.//w:r[1]/w:rPr/w:sz/@w:val)', $leaderEmailParagraph))->toBe('22')
            ->and($xpath->query('.//w:b', $expectedOutputParagraph)->length)->toBe(0)
            ->and($xpath->query('.//w:b', $methodologyHeading)->length)->toBe(0)
            ->and($xpath->query('.//w:i', $responsibilityHeading)->length)->toBe(1)
            ->and($xpath->query('.//w:b', $responsibilityHeading)->length)->toBe(0)
            ->and($xpath->evaluate('string(.)', $responsibilityHeading))->toContain('Project Leader: Faculty Project Leader')
            ->and($xpath->query('.//w:b', $budgetValueParagraph)->length)->toBe(0)
            ->and($xpath->evaluate('string(w:pPr/w:jc/@w:val)', $budgetValueParagraph))->toBe('')
            ->and($xpath->query('.//w:b', $preparedDepartmentParagraph)->length)->toBe(0)
            ->and($xpath->evaluate('string(@w:w)', $pageSize))->toBe('12242')
            ->and($xpath->evaluate('string(@w:h)', $pageSize))->toBe('18722')
            ->and($settingsXml)->toContain('w:updateFields')
            ->and($settingsXml)->toMatch('/w:updateFields[^>]+w:val="true"/');

        foreach ($officialPartNames as $partName) {
            if (in_array($partName, ['word/document.xml', 'word/settings.xml'], true)) {
                continue;
            }

            expect(hash('sha256', $generatedArchive->getFromName($partName)))
                ->toBe(hash('sha256', $templateArchive->getFromName($partName)));
        }
    } finally {
        $generatedArchive->close();
        $templateArchive->close();

        if (is_file($temporaryPath)) {
            unlink($temporaryPath);
        }
    }
});

test('detailed proposal validation requires an SDG, a complete methodology, and at least one expected output', function () {
    $payload = ($this->payload)([
        'sdgs' => [],
        'expected_outputs' => array_fill_keys(array_keys(config('detailed_proposal.expected_outputs')), ''),
        'methodology' => [
            'research_design' => '',
            'specific_methods' => '',
            'data_analysis' => '',
        ],
    ]);

    $this->actingAs($this->faculty)
        ->put(route('faculty.proposal-drafts.detailed-proposal.update', $this->draft), $payload)
        ->assertSessionHasErrors([
            'sdgs',
            'expected_outputs',
            'methodology.research_design',
            'methodology.specific_methods',
            'methodology.data_analysis',
        ]);
});
