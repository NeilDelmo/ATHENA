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
    $this->payload = fn (): array => [
        'people' => [
            [
                'last_name' => 'Leader',
                'first_name' => 'Faculty',
                'middle_name' => 'Project',
                'agency' => 'Batangas State University',
                'gender' => 'female',
                'birthday' => '1990-06-15',
                'street' => 'Rizal Street',
                'barangay' => 'Barangay 1',
                'municipality' => 'Nasugbu',
                'province' => 'Batangas',
                'landline' => '043-000-0000',
                'cellphone' => '9123456789',
                'email' => 'faculty@example.test',
                'academic_background' => [
                    ['degree' => 'BS Computer Science', 'major_field' => 'Computer Science', 'sector' => 'Higher Education', 'learning_institution' => 'BatStateU', 'status' => 'Graduated', 'year_start' => '2008', 'year_end' => '2012', 'thesis' => 'Coastal data platform'],
                    ['degree' => 'MS Information Technology', 'major_field' => 'Information Technology', 'sector' => 'Higher Education', 'learning_institution' => 'BatStateU', 'status' => 'Graduated', 'year_start' => '2014', 'year_end' => '2016', 'thesis' => 'Community information systems'],
                ],
                'employment' => [[
                    'agency' => 'Batangas State University',
                    'plantilla_position' => 'Instructor',
                    'appointment_status' => 'Permanent',
                    'start_date' => '2017-01-01',
                    'end_date' => '',
                    'monthly_salary' => '45000',
                ]],
                'projects' => [[
                    'title' => 'Community Coastal Research',
                    'designation' => 'Project Leader',
                    'sector' => 'Environment',
                    'current_status' => 'Ongoing',
                    'year_from' => '2026',
                    'year_to' => '2027',
                ]],
                'publications' => [[
                    'title' => 'Community Coastal Information Systems',
                    'year_published' => '2025',
                    'place' => 'Batangas',
                    'publication_group' => 'Institutional Journal',
                    'authoring_type' => 'Lead author',
                ]],
            ],
            ['last_name' => 'One', 'first_name' => 'Researcher'],
            ['last_name' => 'Two', 'first_name' => 'Researcher'],
        ],
    ];

    Storage::fake('local');
    $this->withoutVite();
});

test('the first CV draft is seeded from the project leader and Attachment B project staff', function () {
    $this->draft->documents()->create([
        'document_type' => ProposalVersionFile::TYPE_LINE_ITEM_BUDGET,
        'position' => 0,
        'source_data' => [
            'staff' => [
                ['name' => 'Researcher One'],
                ['name' => 'Researcher Two'],
            ],
        ],
        'completed_at' => now(),
    ]);

    $this->actingAs($this->faculty)
        ->get(route('faculty.proposal-drafts.curriculum-vitae.edit', $this->draft))
        ->assertOk()
        ->assertSee('Faculty')
        ->assertSee('One')
        ->assertSee('Two')
        ->assertSee('Add another member')
        ->assertSee('Choose a month or type a year to jump directly.')
        ->assertDontSee('type="date"', false)
        ->assertSee('Every member begins with a new official CV block.');
});

test('multiple team CVs save as one completed generated paper and resume with all rows', function () {
    $this->actingAs($this->faculty)
        ->put(route('faculty.proposal-drafts.curriculum-vitae.update', $this->draft), ($this->payload)())
        ->assertRedirect(route('faculty.proposal-drafts.curriculum-vitae.edit', $this->draft))
        ->assertSessionHas('success', 'Attachment C: Curriculum Vitae saved.');

    $document = $this->draft->documents()
        ->where('document_type', ProposalVersionFile::TYPE_CURRICULUM_VITAE)
        ->sole();

    expect($document->completed_at)->not->toBeNull()
        ->and($document->file_path)->toBeNull()
        ->and($document->source_data['people'])->toHaveCount(3)
        ->and($document->source_data['people'][0]['academic_background'])->toHaveCount(2);

    $this->actingAs($this->faculty)
        ->get(route('faculty.proposal-drafts.curriculum-vitae.edit', $this->draft))
        ->assertOk()
        ->assertSee('Community Coastal Information Systems')
        ->assertSee('Two');
});

test('the preview repeats the complete official CV for every team member', function () {
    $response = $this->actingAs($this->faculty)
        ->post(route('faculty.proposal-drafts.curriculum-vitae.preview', $this->draft), ($this->payload)())
        ->assertOk();

    expect(substr_count($response->getContent(), 'Attachment C-BatStateU-FO-RES-02'))->toBe(3)
        ->and(substr_count($response->getContent(), 'CURRICULUM VITAE'))->toBe(3);

    $document = new DOMDocument;
    $document->loadHTML($response->getContent(), LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($document);
    $nameLabels = $xpath->query('(//table[contains(concat(" ", normalize-space(@class), " "), " cv-name-table ")])[1]//tr[contains(concat(" ", normalize-space(@class), " "), " cv-label-row ")]/th');
    $nameCells = $xpath->query('(//table[contains(concat(" ", normalize-space(@class), " "), " cv-name-table ")])[1]//tr[contains(concat(" ", normalize-space(@class), " "), " cv-value-row ")]/td');
    $yearTaken = $xpath->query('(//th[contains(normalize-space(.), "Year Taken")])[1]')->item(0);
    $scholarshipGrants = $xpath->query('(//th[contains(normalize-space(.), "Scholarship Grants")])[1]')->item(0);
    $appointmentDate = $xpath->query('(//th[contains(normalize-space(.), "Date of Appointment")])[1]')->item(0);
    $projectYear = $xpath->query('(//table[contains(concat(" ", normalize-space(@class), " "), " cv-projects-table ")])[1]//th[starts-with(normalize-space(.), "Year")]')->item(0);
    $stylesheet = file_get_contents(resource_path('css/curriculum-vitae-print.css'));

    expect($nameLabels)->toHaveCount(3)
        ->and(trim($nameLabels->item(0)->textContent))->toBe('Last Name')
        ->and(trim($nameLabels->item(1)->textContent))->toBe('First Name')
        ->and(trim($nameLabels->item(2)->textContent))->toBe('Middle Name')
        ->and($nameCells)->toHaveCount(3)
        ->and(trim($nameCells->item(0)->textContent))->toBe('Leader')
        ->and(trim($nameCells->item(1)->textContent))->toBe('Faculty')
        ->and(trim($nameCells->item(2)->textContent))->toBe('Project')
        ->and($yearTaken->getAttribute('colspan'))->toBe('2')
        ->and($scholarshipGrants->getAttribute('colspan'))->toBe('4')
        ->and($appointmentDate->getAttribute('colspan'))->toBe('2')
        ->and($projectYear->getAttribute('colspan'))->toBe('2')
        ->and($stylesheet)->not->toContain('font-style: italic')
        ->and($stylesheet)->toContain('.cv-table .cv-borderless-columns > * + * { border-left-width: 0; }')
        ->and($stylesheet)->toContain('.cv-name-table .cv-value-row td { font-weight: 700; text-align: center; }')
        ->and($stylesheet)->toContain('margin: 1in 0.509375in;')
        ->and($stylesheet)->toContain('.cv-projects-table { break-before: page; page-break-before: always; }')
        ->and($stylesheet)->toContain('.cv-name-table col:nth-child(1) { width: 36.12736%; }')
        ->and($stylesheet)->toContain('.cv-publications-table col:nth-child(4) { width: 33.41688%; }');

    $response
        ->assertDontSee('RESIDENTIAL ADDRESS')
        ->assertSee('Degree Earned')
        ->assertSee('(from highest to lowest)')
        ->assertSee('Status of Appointment')
        ->assertSee('(permanent, temporary, contractual, casual, emergency)')
        ->assertSee('R&amp;D RELATED PUBLICATIONS (for the last 3 years)', false)
        ->assertSeeInOrder(['Faculty', 'Researcher', 'Researcher']);
});

test('the generated Word file preserves the official form and adds one complete block per team member', function () {
    $response = $this->actingAs($this->faculty)
        ->post(route('faculty.proposal-drafts.curriculum-vitae.download', $this->draft), ($this->payload)())
        ->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')
        ->assertDownload('community-coastal-research-curriculum-vitae.docx');

    $temporaryPath = tempnam(sys_get_temp_dir(), 'curriculum-vitae-test-');
    file_put_contents($temporaryPath, $response->streamedContent());
    $archive = new ZipArchive;
    $templateArchive = new ZipArchive;

    try {
        expect($archive->open($temporaryPath))->toBeTrue();
        expect($templateArchive->open(config('curriculum_vitae.template_path')))->toBeTrue()
            ->and($archive->numFiles)->toBe($templateArchive->numFiles);

        for ($index = 0; $index < $templateArchive->numFiles; $index++) {
            $partName = $templateArchive->getNameIndex($index);

            if ($partName !== 'word/document.xml') {
                expect($archive->getFromName($partName))->toBe($templateArchive->getFromName($partName));
            }
        }

        $documentXml = $archive->getFromName('word/document.xml');
        $document = new DOMDocument;
        $document->loadXML($documentXml, LIBXML_NONET);
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $xpath->registerNamespace('w14', 'http://schemas.microsoft.com/office/word/2010/wordml');
        $contentControlIds = [];

        foreach ($xpath->query('//w:sdtPr/w:id') as $contentControlId) {
            $contentControlIds[] = $xpath->evaluate('string(@w:val)', $contentControlId);
        }

        $nameLabelCells = $xpath->query('//w:body/w:tbl[1]/w:tr[3]/w:tc');
        $nameCells = $xpath->query('//w:body/w:tbl[1]/w:tr[4]/w:tc');
        $nameParagraph = $xpath->query('//w:body/w:tbl[1]/w:tr[4]/w:tc[1]/w:p[1]')->item(0);
        $nameParts = [];

        foreach ($xpath->query('./w:r/w:t', $nameParagraph) as $namePart) {
            $nameParts[] = $namePart->textContent;
        }

        expect($xpath->query('//w:body/w:tbl')->length)->toBe(6)
            ->and($xpath->query('//w:br[@w:type="page"]')->length)->toBe(2)
            ->and($xpath->query('//w14:checked[@w14:val="1"]')->length)->toBe(1)
            ->and(substr_count($documentXml, '☒'))->toBe(1)
            ->and($contentControlIds)->toHaveCount(6)
            ->and(array_unique($contentControlIds))->toHaveCount(6)
            ->and($xpath->evaluate('string(//w:sectPr/w:pgSz/@w:w)'))->toBe('12242')
            ->and($xpath->evaluate('string(//w:sectPr/w:pgSz/@w:h)'))->toBe('18722')
            ->and($xpath->evaluate('string(//w:t[.="Community Coastal Information Systems"]/../w:rPr/w:rFonts/@w:ascii)'))->toBe('Times New Roman')
            ->and($xpath->evaluate('string(//w:t[.="Community Coastal Information Systems"]/../w:rPr/w:sz/@w:val)'))->toBe('18')
            ->and($nameParts)->toBe(['Leader', 'Faculty', 'Project'])
            ->and($nameLabelCells)->toHaveCount(3)
            ->and($xpath->query('//w:body/w:tbl[1]/w:tr[3]//w:i')->length)->toBe(0)
            ->and($xpath->evaluate('string(//w:body/w:tbl[1]/w:tr[3]/w:tc[1]/w:tcPr/w:tcBorders/w:right/@w:val)'))->toBe('nil')
            ->and($xpath->evaluate('string(//w:body/w:tbl[1]/w:tr[3]/w:tc[2]/w:tcPr/w:tcBorders/w:left/@w:val)'))->toBe('nil')
            ->and($xpath->evaluate('string(//w:body/w:tbl[1]/w:tr[3]/w:tc[2]/w:tcPr/w:tcBorders/w:right/@w:val)'))->toBe('nil')
            ->and($xpath->evaluate('string(//w:body/w:tbl[1]/w:tr[3]/w:tc[3]/w:tcPr/w:tcBorders/w:left/@w:val)'))->toBe('nil')
            ->and($nameCells)->toHaveCount(1)
            ->and($xpath->evaluate('string(//w:body/w:tbl[1]/w:tr[4]/w:tc[1]/w:tcPr/w:gridSpan/@w:val)'))->toBe('28')
            ->and($xpath->evaluate('string(//w:body/w:tbl[1]/w:tr[4]/w:tc[1]/w:tcPr/w:tcBorders/w:top/@w:val)'))->toBe('nil')
            ->and($xpath->query('./w:r/w:tab', $nameParagraph)->length)->toBe(3)
            ->and($xpath->query('./w:r[w:t]/w:rPr/w:b', $nameParagraph)->length)->toBe(3)
            ->and($xpath->query('./w:pPr/w:tabs/w:tab[@w:val="center"]', $nameParagraph)->length)->toBe(3)
            ->and($xpath->evaluate('string(./w:pPr/w:tabs/w:tab[1]/@w:pos)', $nameParagraph))->toBe('1946')
            ->and($xpath->evaluate('string(./w:pPr/w:tabs/w:tab[2]/@w:pos)', $nameParagraph))->toBe('5441')
            ->and($xpath->evaluate('string(./w:pPr/w:tabs/w:tab[3]/@w:pos)', $nameParagraph))->toBe('8881')
            ->and(substr_count($documentXml, 'Attachment C-BatStateU-FO-RES-02'))->toBe(3)
            ->and(substr_count($documentXml, 'CURRICULUM VITAE'))->toBe(3)
            ->and($documentXml)->toContain('Community Coastal Information Systems')
            ->and($documentXml)->toContain('Researcher')
            ->and($documentXml)->toContain('Two');
    } finally {
        $archive->close();
        $templateArchive->close();

        if (is_file($temporaryPath)) {
            unlink($temporaryPath);
        }
    }
});

test('every team member requires a first and last name and the package limit is enforced', function () {
    $payload = ($this->payload)();
    $payload['people'][1]['first_name'] = '';

    $this->actingAs($this->faculty)
        ->put(route('faculty.proposal-drafts.curriculum-vitae.update', $this->draft), $payload)
        ->assertSessionHasErrors('people.1.first_name');

    $payload['people'] = collect(range(1, 51))
        ->map(fn (int $number): array => ['last_name' => "Member {$number}", 'first_name' => 'Researcher'])
        ->all();

    $this->actingAs($this->faculty)
        ->put(route('faculty.proposal-drafts.curriculum-vitae.update', $this->draft), $payload)
        ->assertSessionHasErrors('people');
});
