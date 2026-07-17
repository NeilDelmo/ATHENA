<?php

use App\Models\ProposalDraft;
use App\Models\ProposalVersionFile;
use App\Models\ResearchCall;
use App\Models\User;
use App\Support\CurriculumVitaeData;
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
                    ['degree' => 'Doctor of Information Technology', 'major_field' => 'Database Technologies', 'sector' => '', 'learning_institution' => 'AMA University', 'status' => 'On going', 'year_start' => '2019', 'year_end' => 'e', 'thesis' => ''],
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
        ->assertSee('Every member begins with a new official CV block.')
        ->assertSee('<option value="Graduated">Graduated</option>', false)
        ->assertSee('<option value="Ongoing">Ongoing</option>', false)
        ->assertSee('<option value="Dropped">Dropped</option>', false)
        ->assertSee('<option value="Terminated">Terminated</option>', false)
        ->assertSee('Ongoing studies automatically end in Present.')
        ->assertSee('data-paper-submit-status', false)
        ->assertSee('data-paper-submit-message', false)
        ->assertSee('Please keep this page open while ATHENA updates the paper.')
        ->assertSee('aria-live="assertive"', false)
        ->assertSee('Ctrl + S')
        ->assertSee('Save and exit')
        ->assertSee('data-paper-form', false);
});

test('CV sections use the official default row counts', function () {
    $expectedCounts = [
        'academic_background' => 4,
        'scholarships' => 5,
        'employment' => 5,
        'specializations' => 4,
        'awards' => 3,
        'projects' => 5,
        'publications' => 5,
        'presentations' => 5,
    ];
    $configuredCounts = collect(config('curriculum_vitae.sections'))
        ->mapWithKeys(fn (array $section, string $key): array => [$key => $section['default_rows']])
        ->all();
    $normalized = CurriculumVitaeData::fromValidated([
        'people' => [['last_name' => 'Researcher', 'first_name' => 'Faculty']],
    ]);
    $normalizedCounts = collect($expectedCounts)
        ->mapWithKeys(fn (int $count, string $key): array => [$key => count($normalized['people'][0][$key])])
        ->all();

    expect($configuredCounts)->toBe($expectedCounts)
        ->and($normalizedCounts)->toBe($expectedCounts);
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
        ->and($document->source_data['people'][0]['academic_background'])->toHaveCount(3)
        ->and($document->source_data['people'][0]['academic_background'][2]['status'])->toBe('Ongoing')
        ->and($document->source_data['people'][0]['academic_background'][2]['year_end'])->toBeNull();

    $this->actingAs($this->faculty)
        ->get(route('faculty.proposal-drafts.curriculum-vitae.edit', $this->draft))
        ->assertOk()
        ->assertSee('Community Coastal Information Systems')
        ->assertSee('Two');

    $saveAndExitPayload = ($this->payload)();
    $saveAndExitPayload['exit_after_save'] = '1';

    $this->actingAs($this->faculty)
        ->put(route('faculty.proposal-drafts.curriculum-vitae.update', $this->draft), $saveAndExitPayload)
        ->assertRedirect(route('faculty.proposal-drafts.show', $this->draft));
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
    $agencyOutput = $xpath->query('(//table[contains(concat(" ", normalize-space(@class), " "), " cv-personal-details-table ")])[1]//td[1]/strong')->item(0);
    $genderCell = $xpath->query('(//table[contains(concat(" ", normalize-space(@class), " "), " cv-personal-details-table ")])[1]//td[2]')->item(0);
    $birthdayOutput = $xpath->query('(//table[contains(concat(" ", normalize-space(@class), " "), " cv-personal-details-table ")])[1]//td[3]/strong')->item(0);
    $addressValueCells = $xpath->query('(//table[contains(concat(" ", normalize-space(@class), " "), " cv-address-table ")])[1]//tr[contains(concat(" ", normalize-space(@class), " "), " cv-address-value-row ")]/td');
    $addressLabelCells = $xpath->query('(//table[contains(concat(" ", normalize-space(@class), " "), " cv-address-table ")])[1]//tr[contains(concat(" ", normalize-space(@class), " "), " cv-address-label-row ")]/th');
    $contactOutputs = $xpath->query('(//table[contains(concat(" ", normalize-space(@class), " "), " cv-contact-table ")])[1]//strong');
    $ongoingAcademicCells = $xpath->query('(//table[contains(concat(" ", normalize-space(@class), " "), " cv-academic-table ")])[1]//tbody/tr[3]/td');
    $defaultSectionRowCounts = [
        'academic_background' => $xpath->query('(//table[contains(concat(" ", normalize-space(@class), " "), " cv-academic-table ")])[1]//tbody/tr')->length,
        'scholarships' => $xpath->query('(//table[contains(concat(" ", normalize-space(@class), " "), " cv-scholarship-table ")])[1]//tbody/tr')->length,
        'employment' => $xpath->query('(//table[contains(concat(" ", normalize-space(@class), " "), " cv-employment-table ")])[1]//tbody/tr')->length,
        'specializations' => $xpath->query('(//table[contains(concat(" ", normalize-space(@class), " "), " cv-specialization-table ")])[1]//tbody/tr')->length,
        'awards' => $xpath->query('(//table[contains(concat(" ", normalize-space(@class), " "), " cv-awards-table ")])[1]//tbody/tr')->length,
        'projects' => $xpath->query('(//table[contains(concat(" ", normalize-space(@class), " "), " cv-projects-table ")])[1]//tbody/tr')->length,
        'publications' => $xpath->query('(//table[contains(concat(" ", normalize-space(@class), " "), " cv-publications-table ")])[1]//tbody/tr')->length,
        'presentations' => $xpath->query('(//table[contains(concat(" ", normalize-space(@class), " "), " cv-presentations-table ")])[1]//tbody/tr')->length,
    ];
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
        ->and(trim($agencyOutput->textContent))->toBe('Batangas State University')
        ->and($genderCell->textContent)->toContain('■ Female')
        ->and(trim($birthdayOutput->textContent))->toBe('06/15/1990')
        ->and($addressValueCells)->toHaveCount(4)
        ->and(array_map(fn (DOMNode $cell): string => trim($cell->textContent), iterator_to_array($addressValueCells)))->toBe(['Rizal Street', 'Barangay 1', 'Nasugbu', 'Batangas'])
        ->and(array_map(fn (DOMNode $cell): string => trim($cell->textContent), iterator_to_array($addressLabelCells)))->toBe(['Street', 'Barangay', 'Municipality', 'Province'])
        ->and($contactOutputs)->toHaveCount(3)
        ->and(array_map(fn (DOMNode $cell): string => trim($cell->textContent), iterator_to_array($contactOutputs)))->toBe(['043-000-0000', '(+63) 9123456789', 'faculty@example.test'])
        ->and($ongoingAcademicCells)->toHaveCount(8)
        ->and(trim($ongoingAcademicCells->item(4)->textContent))->toBe('Ongoing')
        ->and(trim($ongoingAcademicCells->item(5)->textContent))->toBe('2019')
        ->and(trim($ongoingAcademicCells->item(6)->textContent))->toBe('Present')
        ->and($defaultSectionRowCounts)->toBe([
            'academic_background' => 4,
            'scholarships' => 5,
            'employment' => 5,
            'specializations' => 4,
            'awards' => 3,
            'projects' => 5,
            'publications' => 5,
            'presentations' => 5,
        ])
        ->and($yearTaken->getAttribute('colspan'))->toBe('2')
        ->and($scholarshipGrants->getAttribute('colspan'))->toBe('4')
        ->and($appointmentDate->getAttribute('colspan'))->toBe('2')
        ->and($projectYear->getAttribute('colspan'))->toBe('2')
        ->and($stylesheet)->not->toContain('font-style: italic')
        ->and($stylesheet)->toContain('.cv-table .cv-borderless-columns > * + * { border-left-width: 0; }')
        ->and($stylesheet)->toContain('.cv-name-table .cv-value-row td { font-weight: 700; text-align: center; }')
        ->and($stylesheet)->toContain('border-bottom: 0.5pt solid #c0c0c0;')
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
        ->assertSee('Birthday (mm/dd/yyyy):&nbsp;&nbsp;&nbsp;<strong', false)
        ->assertSee('■ Female')
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
            ->and($xpath->query('//w:body/w:tbl[1]/w:tr')->length)->toBe(51)
            ->and($xpath->query('//w:body/w:tbl[2]/w:tr')->length)->toBe(14)
            ->and($xpath->query('//w:br[@w:type="page"]')->length)->toBe(2)
            ->and($xpath->query('//w14:checked[@w14:val="1"]')->length)->toBe(1)
            ->and($xpath->query('//w14:checkedState[@w14:val="25A0"]')->length)->toBe(6)
            ->and(substr_count($documentXml, '■'))->toBe(1)
            ->and(substr_count($documentXml, '☒'))->toBe(0)
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
            ->and($xpath->query('//w:body/w:tbl[1]/w:tr[5]/w:tc[1]//w:r[w:t[contains(., "Batangas State University")]]/w:rPr/w:b')->length)->toBe(1)
            ->and($xpath->evaluate('string(//w:body/w:tbl[1]/w:tr[5]/w:tc[3]//w:r[w:rPr/w:b]/w:t)'))->toBe('   06/15/1990')
            ->and($xpath->query('//w:body/w:tbl[1]/w:tr[7]//w:r[w:t]/w:rPr/w:b')->length)->toBe(4)
            ->and($xpath->evaluate('string(//w:body/w:tbl[1]/w:tr[7]/w:tc[1])'))->toBe('Rizal Street')
            ->and($xpath->evaluate('string(//w:body/w:tbl[1]/w:tr[8]/w:tc[1])'))->toBe('Street')
            ->and($xpath->evaluate('string(//w:body/w:tbl[1]/w:tr[7]/w:tc[1]/w:tcPr/w:tcBorders/w:bottom/@w:val)'))->toBe('single')
            ->and($xpath->evaluate('string(//w:body/w:tbl[1]/w:tr[8]/w:tc[1]/w:tcPr/w:tcBorders/w:top/@w:val)'))->toBe('single')
            ->and($xpath->query('//w:body/w:tbl[1]/w:tr[9]//w:r[w:rPr/w:b and w:t]')->length)->toBe(3)
            ->and(substr_count($documentXml, 'Attachment C-BatStateU-FO-RES-02'))->toBe(3)
            ->and(substr_count($documentXml, 'CURRICULUM VITAE'))->toBe(3)
            ->and($documentXml)->toContain('Community Coastal Information Systems')
            ->and($documentXml)->toContain('Doctor of Information Technology')
            ->and($xpath->query('//w:t[.="Present"]')->length)->toBe(1)
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

test('academic status and completed study end years reject unsupported values', function () {
    $payload = ($this->payload)();
    $payload['people'][0]['academic_background'][0]['status'] = 'Still studying';

    $this->actingAs($this->faculty)
        ->put(route('faculty.proposal-drafts.curriculum-vitae.update', $this->draft), $payload)
        ->assertSessionHasErrors('people.0.academic_background.0.status');

    $payload = ($this->payload)();
    $payload['people'][0]['academic_background'][0]['year_end'] = 'e';

    $this->actingAs($this->faculty)
        ->put(route('faculty.proposal-drafts.curriculum-vitae.update', $this->draft), $payload)
        ->assertSessionHasErrors('people.0.academic_background.0.year_end');
});
