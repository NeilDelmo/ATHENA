<?php

use App\Models\ProposalDraft;
use App\Models\ProposalVersionFile;
use App\Models\ResearchCall;
use App\Models\User;
use Illuminate\Http\UploadedFile;
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
        'leader_title' => 'Asst Prof.',
        'leader_email' => 'leader@g.batstate-u.edu.ph',
        'leader_contact' => '09171234567',
        'staff' => [[
            'title' => 'Dr.',
            'name' => 'Research Staff Member',
            'email' => 'staff@g.batstate-u.edu.ph',
            'contact' => '09187654321',
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
        'introduction' => 'Community coastal monitoring benefits from an integrated local research approach.',
        'related_literature' => 'Recent coastal monitoring studies demonstrate the value of participatory data collection.',
        'methodology' => [
            'research_design' => 'The study uses a sequential mixed-method research design.',
            'specific_methods' => 'Researchers will conduct surveys, interviews, and coastal transect observations.',
            'data_analysis' => 'Quantitative results will use descriptive statistics and qualitative data will use thematic analysis.',
        ],
        'responsibilities' => [
            ['name' => 'Faculty Project Leader', 'percentage' => 60, 'duties' => 'Leads the project, assures research quality, and coordinates reporting.'],
            ['name' => 'Research Staff Member', 'percentage' => 40, 'duties' => 'Coordinates field data collection and prepares the validated dataset.'],
        ],
        'checked_verified_by_name' => 'Juan Dela Cruz',
        'recommending_approval_name' => 'Maria Santos',
        'approved_by_name' => 'Pedro Reyes',
        'references' => "Author, A. (2025). Participatory coastal monitoring. Research Journal, 1(1), 1-10.\nAuthor, B. (2024). Community environmental data. Coastal Studies, 2(1), 20-30.",
        ...$overrides,
    ];

    Storage::fake('local');
    $this->withoutVite();
});

test('the detailed proposal editor uses the official sections and account defaults', function () {
    $response = $this->actingAs($this->faculty)
        ->get(route('faculty.proposal-drafts.detailed-proposal.edit', $this->draft))
        ->assertOk()
        ->assertSee('BatStateU-FO-RES-02 Rev. 04')
        ->assertSee('leader@g.batstate-u.edu.ph')
        ->assertSee('College of Informatics and Computing Sciences')
        ->assertSee('BatStateU The NEU ARASOF-Nasugbu Campus')
        ->assertSee('From your profile')
        ->assertSee('Leave blank if not applicable')
        ->assertSee('III. Sustainable Development Goal')
        ->assertSee('SDG17:')
        ->assertSee('XIII. Duties and Responsibilities of Each Member')
        ->assertSee('Add Research Design visual')
        ->assertSee('Images belong to Research Design only')
        ->assertSee('Related Studies and Literature')
        ->assertSee('Responsibility %')
        ->assertSee('Search workspace members')
        ->assertSee('No available workspace member matches your search.')
        ->assertSee('Names follow the official uppercase format.')
        ->assertSee('Leave blank when no professional title applies.')
        ->assertSee('Changes also update Project Details and the prepared-by name.')
        ->assertSee('Asst Prof.')
        ->assertSee('Enter the external staff member&rsquo;s optional professional title, name, email, and 11-digit contact number manually.', false)
        ->assertSee('Approval Signatory Names')
        ->assertSee('Faculty may enter the three names shown in the approval blocks.')
        ->assertSee('Download exact Word file')
        ->assertSee('Ctrl + S');

    expect($response->getContent())
        ->toContain('id="proponent-department" name="proponent_department" type="text" maxlength="255"')
        ->not->toContain('id="proponent-department" name="proponent_department" type="text" required')
        ->toContain('id="proponent-college" name="proponent_college" type="text" required')
        ->toContain('id="checked-verified-by-name" name="checked_verified_by_name" type="text" maxlength="255"')
        ->toContain('id="recommending-approval-name" name="recommending_approval_name" type="text" maxlength="255"')
        ->toContain('id="approved-by-name" name="approved_by_name" type="text" maxlength="255"')
        ->toContain('id="leader-title" name="leader_title" type="text" maxlength="50"')
        ->toContain('id="leader-name" name="project_leader" type="text" required maxlength="120"')
        ->toContain('x-model="projectLeader" x-on:change="syncProjectLeader()"')
        ->not->toContain('id="leader-name" type="text" value="Faculty Project Leader" readonly')
        ->toContain('block h-11 w-full')
        ->toContain(':name="`staff[${index}][title]`"')
        ->toContain('method="POST" enctype="multipart/form-data"')
        ->toContain('id="leader-contact" name="leader_contact" type="tel" required maxlength="11" inputmode="numeric" pattern="[0-9]{11}"')
        ->toContain('placeholder="09XXXXXXXXX"');
});

test('detailed proposal contact numbers must contain exactly 11 digits', function () {
    $payload = ($this->payload)([
        'leader_contact' => '0917123456',
        'staff' => [[
            'name' => 'Research Staff Member',
            'email' => 'staff@g.batstate-u.edu.ph',
            'contact' => '0918765432A',
        ]],
    ]);

    $response = $this->actingAs($this->faculty)
        ->put(route('faculty.proposal-drafts.detailed-proposal.update', $this->draft), $payload)
        ->assertRedirect();

    $errors = $response->getSession()->get('errors')['default']['messages'];

    expect(array_keys($errors))
        ->toContain('leader_contact')
        ->toContain('staff.0.contact');
});

test('the college is restored from the signed in user while department may remain blank', function () {
    $payload = ($this->payload)([
        'proponent_department' => '',
        'proponent_college' => '',
    ]);

    $this->actingAs($this->faculty)
        ->put(route('faculty.proposal-drafts.detailed-proposal.update', $this->draft), $payload)
        ->assertRedirect(route('faculty.proposal-drafts.detailed-proposal.edit', $this->draft))
        ->assertSessionHasNoErrors();

    $document = $this->draft->documents()
        ->where('document_type', ProposalVersionFile::TYPE_DETAILED_PROPOSAL)
        ->sole();

    expect($document->source_data['proponent_department'])->toBe('')
        ->and($document->source_data['proponent_college'])->toBe($this->faculty->college);
});

test('the leader contact number is restored from the signed in user when left blank', function () {
    $this->faculty->forceFill(['contact_number' => '09170000001'])->save();
    $payload = ($this->payload)(['leader_contact' => '']);

    $this->actingAs($this->faculty)
        ->put(route('faculty.proposal-drafts.detailed-proposal.update', $this->draft), $payload)
        ->assertRedirect(route('faculty.proposal-drafts.detailed-proposal.edit', $this->draft))
        ->assertSessionHasNoErrors();

    $document = $this->draft->documents()
        ->where('document_type', ProposalVersionFile::TYPE_DETAILED_PROPOSAL)
        ->sole();

    expect($document->source_data['leader_contact'])->toBe('09170000001');
});

test('the detailed proposal editor previews the saved profile contact number for the leader', function () {
    $this->faculty->forceFill(['contact_number' => '09170000002'])->save();

    $this->actingAs($this->faculty)
        ->get(route('faculty.proposal-drafts.detailed-proposal.edit', $this->draft))
        ->assertOk()
        ->assertSee('\u0022leader_contact\u0022:\u002209170000002\u0022', false);
});

test('the preview mirrors the official bordered form layout', function () {
    $response = $this->actingAs($this->faculty)
        ->post(route('faculty.proposal-drafts.detailed-proposal.preview', $this->draft), ($this->payload)())
        ->assertOk()
        ->assertSee('detailed-proposal-table')
        ->assertSee('images/batstateu-logo.png')
        ->assertSee('Reference No.: BatStateU-FO-RES-02')
        ->assertSee('Effectivity Date: August 22, 2023')
        ->assertSee('Revision No.: 04')
        ->assertSee('DETAILED RESEARCH PROPOSAL')
        ->assertSee('I. Research Project Title:')
        ->assertSee('Community Coastal Research')
        ->assertSee('II. BatStateU Research Agenda:')
        ->assertSee('III. Sustainable Development Goal:')
        ->assertSee('SDG17: Partnerships for the Goals')
        ->assertSee('IV. Project Leader:')
        ->assertSee('Project Staff (s):')
        ->assertSee('Asst Prof. FACULTY PROJECT LEADER')
        ->assertSee('Dr. RESEARCH STAFF MEMBER')
        ->assertSee('staff@g.batstate-u.edu.ph')
        ->assertSee('V. Proponent Agency:')
        ->assertSee('VI. Cooperating Agency:')
        ->assertSee('Municipality of Nasugbu')
        ->assertSee('VII. Executive Brief:')
        ->assertSee('VIII. Rationale:')
        ->assertSee('IX. Objectives of the Project:')
        ->assertSee('X. Expected Output of the Project:')
        ->assertSee('One peer-reviewed journal article')
        ->assertSee('XI. Introduction:')
        ->assertSee('Related Studies and Literature:')
        ->assertSee('XII. Methodology:')
        ->assertSee('XIII. Duties and Responsibilities of each member:')
        ->assertSee('FACULTY PROJECT LEADER (60%)')
        ->assertSee('XIV. Major Activities/Workplan (Gantt Chart):')
        ->assertSee('See attached Form A')
        ->assertSee('XV. Line-Item Budget:')
        ->assertSee('See attached Form B')
        ->assertSee('Maintenance and Operating Expenses')
        ->assertSee('Capital Outlay and Equipment')
        ->assertSee('XVI. References:')
        ->assertSee('XVII. Curriculum Vitae:')
        ->assertSee('See attached Form C')
        ->assertSee('Data Privacy Act of 2012')
        ->assertSee('To be accomplished by the Research Office')
        ->assertSee('To be accomplished by the Researcher/s')
        ->assertSee('Head, Research Office')
        ->assertSee('Vice Chancellor for Research Development and Extension Services')
        ->assertSee('JUAN DELA CRUZ')
        ->assertSee('MARIA SANTOS')
        ->assertSee('PEDRO REYES')
        ->assertSee('Tracking No.')
        ->assertSee('Page 1 of 1')
        ->assertSee('detailed-proposal-page-number');

    $content = $response->getContent();

    expect(substr_count($content, '☒'))->toBe(3)
        ->and(substr_count($content, '☐'))->toBe(18)
        ->and(substr_count($content, 'Php 0.00'))->toBe(2)
        ->and($content)->not->toContain('Batangas State University, The National Engineering University')
        ->and($content)->not->toContain('Vice President/Vice Chancellor for Research Development and Extension Services');

    expect(file_get_contents(resource_path('css/detailed-proposal-print.css')))
        ->toContain('margin-left: 0.55in')
        ->toContain('detailed-proposal-note-indented { padding-left: 0.3in; }')
        ->toContain('detailed-proposal-note-detail { padding-left: 0.55in; }');
});

test('an incomplete detailed proposal can be previewed but not downloaded', function () {
    $this->actingAs($this->faculty)
        ->postJson(route('faculty.proposal-drafts.detailed-proposal.preview', $this->draft), [])
        ->assertOk()
        ->assertSee('DETAILED RESEARCH PROPOSAL');

    $this->actingAs($this->faculty)
        ->post(route('faculty.proposal-drafts.detailed-proposal.download', $this->draft), [])
        ->assertSessionHasErrors();
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
        ->and($document->source_data['leader_title'])->toBe('Asst Prof.')
        ->and($document->source_data['staff'][0]['title'])->toBe('Dr.')
        ->and($document->source_data['staff'][0]['email'])->toBe('staff@g.batstate-u.edu.ph')
        ->and($document->source_data['checked_verified_by_name'])->toBe('Juan Dela Cruz')
        ->and($document->source_data['recommending_approval_name'])->toBe('Maria Santos')
        ->and($document->source_data['approved_by_name'])->toBe('Pedro Reyes')
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

test('the project leader can be edited from the detailed proposal and stays in shared project details', function () {
    $payload = ($this->payload)([
        'draft_version' => 0,
        'project_leader' => 'Updated Project Leader',
    ]);

    $this->actingAs($this->faculty)
        ->put(route('faculty.proposal-drafts.detailed-proposal.update', $this->draft), $payload)
        ->assertRedirect(route('faculty.proposal-drafts.detailed-proposal.edit', $this->draft))
        ->assertSessionHas('success', 'Detailed Research Proposal saved.');

    $this->draft->refresh();
    $document = $this->draft->documents()
        ->where('document_type', ProposalVersionFile::TYPE_DETAILED_PROPOSAL)
        ->sole();

    expect($this->draft->project_leader)->toBe('Updated Project Leader')
        ->and($this->draft->lock_version)->toBe(1)
        ->and($document->source_data)->not->toHaveKey('project_leader');
});

test('methodology visuals can be saved, positioned, previewed, and included in the Word document', function () {
    $payload = ($this->payload)([
        'methodology_images_present' => '1',
        'methodology_images' => [[
            'section' => 'research_design',
            'alignment' => 'center',
            'size' => 'medium',
            'caption' => 'Community coastal monitoring workflow',
            'image' => UploadedFile::fake()->image('coastal-workflow.png', 1200, 800),
        ]],
    ]);

    $this->actingAs($this->faculty)
        ->put(route('faculty.proposal-drafts.detailed-proposal.update', $this->draft), $payload)
        ->assertRedirect(route('faculty.proposal-drafts.detailed-proposal.edit', $this->draft))
        ->assertSessionHasNoErrors();

    $document = $this->draft->documents()
        ->where('document_type', ProposalVersionFile::TYPE_DETAILED_PROPOSAL)
        ->sole();
    $image = $document->source_data['methodology_images'][0];

    expect($image)
        ->toMatchArray([
            'section' => 'research_design',
            'alignment' => 'center',
            'size' => 'medium',
            'caption' => 'Community coastal monitoring workflow',
        ]);
    Storage::disk('local')->assertExists($image['path']);

    $this->actingAs($this->faculty)
        ->get(route('faculty.proposal-drafts.detailed-proposal.methodology-images.show', [$this->draft, $image['id']]))
        ->assertOk()
        ->assertHeader('content-type', 'image/png');

    $otherFaculty = User::factory()->create();
    $otherFaculty->assignRole('faculty');
    $this->actingAs($otherFaculty)
        ->get(route('faculty.proposal-drafts.detailed-proposal.methodology-images.show', [$this->draft, $image['id']]))
        ->assertForbidden();

    $preview = $this->actingAs($this->faculty)
        ->post(route('faculty.proposal-drafts.detailed-proposal.preview', $this->draft), ($this->payload)())
        ->assertOk()
        ->assertSee('Figure 1. Community coastal monitoring workflow')
        ->assertSee('data:image/png;base64,', false);
    $previewContent = $preview->getContent();

    expect(strpos($previewContent, 'XII. Methodology:'))
        ->toBeLessThan(strpos($previewContent, 'Research Design'))
        ->and(strpos($previewContent, 'Research Design'))
        ->toBeLessThan(strpos($previewContent, 'data:image/png;base64,'))
        ->and(strpos($previewContent, 'data:image/png;base64,'))
        ->toBeLessThan(strpos($previewContent, 'Figure 1. Community coastal monitoring workflow'))
        ->and(strpos($previewContent, 'Figure 1. Community coastal monitoring workflow'))
        ->toBeLessThan(strpos($previewContent, 'The study uses a sequential mixed-method research design.'));

    $response = $this->actingAs($this->faculty)
        ->post(route('faculty.proposal-drafts.detailed-proposal.download', $this->draft), ($this->payload)())
        ->assertOk();
    $temporaryPath = tempnam(sys_get_temp_dir(), 'detailed-proposal-image-test-');
    file_put_contents($temporaryPath, $response->streamedContent());
    $archive = new ZipArchive;

    try {
        expect($archive->open($temporaryPath))->toBeTrue();

        $documentXml = $archive->getFromName('word/document.xml');
        $document = new DOMDocument;
        $document->loadXML($documentXml, LIBXML_NONET);
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $xpath->registerNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');
        $xpath->registerNamespace('pic', 'http://schemas.openxmlformats.org/drawingml/2006/picture');
        $xpath->registerNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        $embeddedImage = $xpath->query('//w:body/w:tbl[1]/w:tr[23]//a:blip')->item(0);
        $imageOutline = $xpath->query('ancestor::pic:pic/pic:spPr/a:ln', $embeddedImage)->item(0);
        $imageParagraph = $xpath->query('//w:body/w:tbl[1]/w:tr[23]//w:p[w:r/w:drawing]')->item(0);
        $figureTitleParagraph = $xpath->query('following-sibling::w:p[1]', $imageParagraph)->item(0);

        expect($archive->getFromName('word/media/methodology-'.$image['id'].'-1.png'))->not->toBeFalse()
            ->and($embeddedImage)->not->toBeNull()
            ->and($xpath->evaluate('string(@r:embed)', $embeddedImage))->toStartWith('rId')
            ->and($xpath->evaluate('string(@w)', $imageOutline))->toBe('12700')
            ->and($xpath->evaluate('string(a:solidFill/a:srgbClr/@val)', $imageOutline))->toBe('000000')
            ->and($xpath->evaluate('string(w:pPr/w:jc/@w:val)', $imageParagraph))->toBe('center')
            ->and(trim($xpath->evaluate('string(preceding-sibling::w:p[1])', $imageParagraph)))->toBe('Research Design')
            ->and(trim($xpath->evaluate('string(.)', $figureTitleParagraph)))->toBe('Figure 1. Community coastal monitoring workflow')
            ->and(trim($xpath->evaluate('string(following-sibling::w:p[1])', $figureTitleParagraph)))->toBe('The study uses a sequential mixed-method research design.');
    } finally {
        $archive->close();

        if (is_file($temporaryPath)) {
            unlink($temporaryPath);
        }
    }
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
        $xpath->registerNamespace('wp', 'http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing');
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
        $headerCells = $xpath->query('./w:tc', $rows->item(0));
        $headerReferenceParagraph = $xpath->query('./w:p', $headerCells->item(1))->item(0);
        $headerEffectivityParagraph = $xpath->query('./w:p', $headerCells->item(2))->item(0);
        $headerLogoExtent = $xpath->query('.//wp:extent', $headerCells->item(0))->item(0);
        $projectTitleParagraph = $xpath->query('./w:tc/w:p', $rows->item(2))->item(0);
        $projectTitleRun = $xpath->query('.//w:r[w:t[contains(., "Community Coastal Research")]]', $projectTitleParagraph)->item(0);
        $leaderEmailParagraph = $xpath->query('./w:tc/w:p[3]', $rows->item(14))->item(0);
        $expectedOutputParagraph = $xpath->query('./w:tc/w:p[2]', $rows->item(20))->item(0);
        $methodologySectionHeading = $xpath->query('./w:tc/w:p[1]', $rows->item(22))->item(0);
        $methodologyHeading = $xpath->query('./w:tc/w:p[2]', $rows->item(22))->item(0);
        $responsibilityHeading = $xpath->query('./w:tc/w:p[2]', $rows->item(23))->item(0);
        $budgetValueParagraph = $xpath->query('./w:tc[2]/w:p', $rows->item(26))->item(0);
        $preparedNameParagraph = $xpath->query('./w:tc[1]/w:p[5]', $rows->item(31))->item(0);
        $preparedSignatureLine = $xpath->query('./w:tc[1]/w:p[4]', $rows->item(31))->item(0);
        $preparedDepartmentParagraph = $xpath->query('./w:tc[2]/w:p', $rows->item(31))->item(0);
        $sdgNoteRun = $xpath->query('.//w:r[w:t[contains(., "Check all applicable SDG")]]', $rows->item(4))->item(0);
        $expectedOutputNoteRun = $xpath->query('.//w:r[w:t[contains(., "based on expanded 6Ps")]]', $rows->item(20))->item(0);
        $checkedNameParagraph = $xpath->query('./w:tc[1]/w:p[normalize-space(.) = "JUAN DELA CRUZ"]', $rows->item(38))->item(0);
        $recommendingNameParagraph = $xpath->query('./w:tc[2]/w:p[normalize-space(.) = "MARIA SANTOS"]', $rows->item(38))->item(0);
        $approvedNameParagraph = $xpath->query('./w:tc[1]/w:p[normalize-space(.) = "PEDRO REYES"]', $rows->item(39))->item(0);
        $notesHeadingParagraph = $xpath->query('//w:body/w:p[normalize-space(.) = "Notes: The Signatories funded by:"]')->item(0);
        $researchCouncilParagraph = $xpath->query('//w:body/w:p[normalize-space(.) = "Approval through Research Council"]')->item(0);
        $researchCouncilSignatoriesParagraph = $xpath->query('//w:body/w:p[normalize-space(.) = "Director, Research; Vice President for RDES: & University President"]')->item(0);
        $localCommitteeParagraph = $xpath->query('//w:body/w:p[normalize-space(.) = "Approval through Local Research Evaluation Committee"]')->item(0);
        $localCommitteeSignatoriesParagraph = $xpath->query('//w:body/w:p[normalize-space(.) = "Head, Research/Head Research & Extension; Vice Chancellor for RDES; & Vice President for RDES"]')->item(0);
        $officialPartNames = [];

        for ($index = 0; $index < $templateArchive->numFiles; $index++) {
            $officialPartNames[] = $templateArchive->getNameIndex($index);
        }

        expect($rows->length)->toBe(40)
            ->and($checkboxes->length)->toBe(17)
            ->and($checkedStates)->toBe(['1', '1', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '1', '0'])
            ->and($checkboxGlyphs)->toBe(['☒', '☒', '☐', '☐', '☐', '☐', '☐', '☐', '☐', '☐', '☐', '☐', '☐', '☐', '☐', '☒', '☐'])
            ->and($rowText(0))->toContain('Reference No.: BatStateU-FO-RES-02')
            ->and($xpath->query('./w:tcPr/w:noWrap', $headerCells->item(1))->length)->toBe(1)
            ->and($xpath->query('./w:tcPr/w:noWrap', $headerCells->item(2))->length)->toBe(1)
            ->and($xpath->evaluate('string(.//w:r[1]/w:rPr/w:sz/@w:val)', $headerReferenceParagraph))->toBe('18')
            ->and($xpath->evaluate('string(.//w:r[1]/w:rPr/w:sz/@w:val)', $headerEffectivityParagraph))->toBe('18')
            ->and($xpath->evaluate('string(@cx)', $headerLogoExtent))->toBe('411480')
            ->and($rowText(2))->toContain('Community Coastal Research')
            ->and($rowText(14))->toContain('Asst Prof. FACULTY PROJECT LEADER')
            ->and($rowText(14))->toContain('Dr. RESEARCH STAFF MEMBER')
            ->and($rowText(15))->not->toContain('Batangas State University')
            ->and($rowText(17))->toContain('community-led coastal monitoring system')
            ->and($rowText(20))->toContain('One peer-reviewed journal article')
            ->and($rowText(21))->toContain('XI. Introduction:')
            ->and($rowText(21))->toContain('Related Studies and Literature:')
            ->and($rowText(22))->toContain('sequential mixed-method research design')
            ->and($rowText(23))->toContain('Coordinates field data collection')
            ->and($rowText(23))->toContain('FACULTY PROJECT LEADER (60%)')
            ->and($rowText(26))->toContain('Php 0.00')
            ->and($rowText(31))->toContain('FACULTY PROJECT LEADER')
            ->and($rowText(35))->toContain('To be accomplished by the Research Office')
            ->and($rowText(38))->toContain('Head, Research Office')
            ->and($rowText(38))->toContain('Vice Chancellor for Research Development and Extension Services')
            ->and($rowText(38))->toContain('JUAN DELA CRUZ')
            ->and($rowText(38))->toContain('MARIA SANTOS')
            ->and($rowText(39))->toContain('PEDRO REYES')
            ->and($rowText(38))->not->toContain('Vice President/Vice Chancellor')
            ->and($xpath->query('./w:trPr/w:cantSplit', $rows->item(39))->length)->toBe(1)
            ->and($xpath->evaluate('string(w:pPr/w:ind/@w:left)', $notesHeadingParagraph))->toBe('0')
            ->and($xpath->evaluate('string(w:pPr/w:ind/@w:start)', $localCommitteeSignatoriesParagraph))->toBe('0')
            ->and($xpath->query('w:pPr/w:tabs', $notesHeadingParagraph)->length)->toBe(0)
            ->and($xpath->evaluate('string(w:pPr/w:jc/@w:val)', $notesHeadingParagraph))->toBe('left')
            ->and($xpath->evaluate('string(w:pPr/w:ind/@w:hanging)', $notesHeadingParagraph))->toBe('')
            ->and($xpath->evaluate('string(w:pPr/w:ind/@w:firstLine)', $localCommitteeParagraph))->toBe('')
            ->and($xpath->query('.//w:r/w:tab', $notesHeadingParagraph)->length)->toBe(3)
            ->and($xpath->query('.//w:r/w:tab', $researchCouncilParagraph)->length)->toBe(4)
            ->and($xpath->query('.//w:r/w:tab', $researchCouncilSignatoriesParagraph)->length)->toBe(5)
            ->and($xpath->query('.//w:r/w:tab', $localCommitteeParagraph)->length)->toBe(4)
            ->and($xpath->query('.//w:r/w:tab', $localCommitteeSignatoriesParagraph)->length)->toBe(5)
            ->and($xpath->query('.//w:br', $projectTitleParagraph)->length)->toBe(1)
            ->and($xpath->evaluate('string(w:rPr/w:sz/@w:val)', $projectTitleRun))->toBe('22')
            ->and($xpath->query('./w:rPr/w:b', $projectTitleRun)->length)->toBe(1)
            ->and($xpath->query('.//w:b', $leaderEmailParagraph)->length)->toBe(0)
            ->and($xpath->evaluate('string(.//w:r[1]/w:rPr/w:sz/@w:val)', $leaderEmailParagraph))->toBe('22')
            ->and($xpath->query('.//w:b', $expectedOutputParagraph)->length)->toBe(0)
            ->and($xpath->query('./w:pPr/w:keepNext', $methodologySectionHeading)->length)->toBe(1)
            ->and($xpath->query('.//w:b', $methodologyHeading)->length)->toBe(0)
            ->and($xpath->query('./w:pPr/w:keepNext', $methodologyHeading)->length)->toBe(1)
            ->and($xpath->evaluate('string(w:pPr/w:numPr/w:numId/@w:val)', $methodologyHeading))->toBe('8')
            ->and($xpath->query('.//w:i', $responsibilityHeading)->length)->toBeGreaterThan(0)
            ->and($xpath->query('.//w:b', $responsibilityHeading)->length)->toBe(1)
            ->and($xpath->evaluate('string(.)', $responsibilityHeading))->toContain('Project Leader: FACULTY PROJECT LEADER (60%)')
            ->and($xpath->query('.//w:b', $budgetValueParagraph)->length)->toBe(0)
            ->and($xpath->evaluate('string(w:pPr/w:jc/@w:val)', $budgetValueParagraph))->toBe('')
            ->and(trim($xpath->evaluate('string(.)', $preparedSignatureLine)))->toBe('________________________________')
            ->and($xpath->query('.//w:u', $preparedNameParagraph)->length)->toBe(0)
            ->and($xpath->query('.//w:b', $preparedNameParagraph)->length)->toBe(1)
            ->and($xpath->query('.//w:b', $preparedDepartmentParagraph)->length)->toBe(0)
            ->and($xpath->query('.//w:i', $sdgNoteRun)->length)->toBe(0)
            ->and($xpath->query('.//w:i', $expectedOutputNoteRun)->length)->toBe(0)
            ->and($xpath->query('.//w:b', $checkedNameParagraph)->length)->toBe(1)
            ->and($xpath->query('.//w:b', $recommendingNameParagraph)->length)->toBe(1)
            ->and($xpath->query('.//w:b', $approvedNameParagraph)->length)->toBe(1)
            ->and($xpath->evaluate('string(@w:w)', $pageSize))->toBe('12242')
            ->and($xpath->evaluate('string(@w:h)', $pageSize))->toBe('18722')
            ->and($settingsXml)->toContain('w:updateFields')
            ->and($settingsXml)->toMatch('/w:updateFields[^>]+w:val="true"/');

        foreach (['word/footer1.xml', 'word/footer2.xml', 'word/footer3.xml'] as $footerPartName) {
            $footer = new DOMDocument;
            $footer->loadXML($generatedArchive->getFromName($footerPartName), LIBXML_NONET);
            $footerXPath = new DOMXPath($footer);
            $footerXPath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

            expect($footerXPath->query('//w:instrText[normalize-space(.) = "PAGE"]')->length)->toBe(1)
                ->and($footerXPath->query('//w:instrText[normalize-space(.) = "NUMPAGES"]')->length)->toBe(1);
        }

        foreach ($officialPartNames as $partName) {
            if (in_array($partName, [
                'word/document.xml',
                'word/footer1.xml',
                'word/footer2.xml',
                'word/footer3.xml',
                'word/settings.xml',
            ], true)) {
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

test('data analysis is optional and omitted from both previews when blank', function () {
    $payload = ($this->payload)([
        'methodology' => [
            'research_design' => 'The study uses a sequential mixed-method research design.',
            'specific_methods' => 'Researchers will conduct surveys, interviews, and coastal transect observations.',
            'data_analysis' => '',
        ],
    ]);

    $this->actingAs($this->faculty)
        ->post(route('faculty.proposal-drafts.detailed-proposal.preview', $this->draft), $payload)
        ->assertOk()
        ->assertDontSee('Data Analysis');

    $response = $this->actingAs($this->faculty)
        ->post(route('faculty.proposal-drafts.detailed-proposal.download', $this->draft), $payload)
        ->assertOk();
    $temporaryPath = tempnam(sys_get_temp_dir(), 'detailed-proposal-optional-analysis-test-');
    file_put_contents($temporaryPath, $response->streamedContent());
    $archive = new ZipArchive;

    try {
        expect($archive->open($temporaryPath))->toBeTrue();

        $documentXml = $archive->getFromName('word/document.xml');
        $document = new DOMDocument;
        $document->loadXML($documentXml, LIBXML_NONET);
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $methodologyRow = $xpath->query('//w:body/w:tbl[1]/w:tr[23]')->item(0);

        expect($xpath->evaluate('string(.)', $methodologyRow))->not->toContain('Data Analysis');
    } finally {
        $archive->close();

        if (is_file($temporaryPath)) {
            unlink($temporaryPath);
        }
    }
});

test('detailed proposal validation requires an SDG, introduction, essential methodology, and at least one expected output', function () {
    $payload = ($this->payload)([
        'sdgs' => [],
        'introduction' => '',
        'expected_outputs' => array_fill_keys(array_keys(config('detailed_proposal.expected_outputs')), ''),
        'methodology' => [
            'research_design' => '',
            'specific_methods' => '',
            'data_analysis' => '',
        ],
        'methodology_images_present' => '1',
        'methodology_images' => [[
            'section' => 'specific_methods',
            'alignment' => 'center',
            'size' => 'medium',
            'caption' => '',
            'image' => UploadedFile::fake()->image('untitled-framework.png', 1200, 800),
        ]],
        'responsibilities' => [[
            'name' => 'Faculty Project Leader',
            'percentage' => '',
            'duties' => 'Leads the project.',
        ]],
    ]);

    $response = $this->actingAs($this->faculty)
        ->put(route('faculty.proposal-drafts.detailed-proposal.update', $this->draft), $payload);

    $response
        ->assertSessionHasErrors([
            'sdgs',
            'introduction',
            'expected_outputs',
            'methodology.research_design',
            'methodology.specific_methods',
            'methodology_images.0.section',
            'methodology_images.0.caption',
            'responsibilities.0.percentage',
        ]);

    $errors = $response->getSession()->get('errors')->getBag('default')->messages();

    expect(array_keys($errors))->not->toContain('methodology.data_analysis');
});
