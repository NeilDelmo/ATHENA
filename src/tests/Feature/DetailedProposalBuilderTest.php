<?php

use App\Contracts\DocumentPdfConverter;
use App\Models\LiteratureSource;
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
        ->assertSee('proposal-preview-workspace', false)
        ->assertSee('proposal-edit-pane', false)
        ->assertSee('data-proposal-official-form-source', false)
        ->assertSee('data-proposal-preview-floating', false)
        ->assertSee('id="proposal-preview-panel"', false)
        ->assertSee('origin-bottom-right', false)
        ->assertSee('@click="closeProposalPreview()"', false)
        ->assertSee('Refresh preview')
        ->assertSee('Full screen')
        ->assertSee('Zoom')
        ->assertSee('leader@g.batstate-u.edu.ph')
        ->assertSee('x-ref="introductionSection"', false)
        ->assertSee('Sources for this proposal')
        ->assertSee('Open literature workspace')
        ->assertSee('Literature workspace')
        ->assertSee('Find literature')
        ->assertSee('Saved sources')
        ->assertSee('Support this passage with literature')
        ->assertSee('Support with source')
        ->assertSee('connect it to claims throughout the proposal')
        ->assertSee('fixed bottom-4 right-4 z-40', false)
        ->assertSee('data-literature-search-loading', false)
        ->assertSee('Searching verified literature')
        ->assertSee('ATHENA is checking academic indexes and ranking possible matches.')
        ->assertSee('Connection-aware RRL draft')
        ->assertSee('Connection preview')
        ->assertSee('Keep standalone')
        ->assertSee('data-literature-connection-preview', false)
        ->assertSee('Insert connected paragraph')
        ->assertDontSee('Literature Assistant')
        ->assertDontSee('Proposal-aware search')
        ->assertSee('Add output')
        ->assertDontSee('+ Add output')
        ->assertDontSee('Quantity and unit are optional.')
        ->assertDontSee('Quantity is optional for qualitative outcomes such as social and economic impact.')
        ->assertSee('College of Informatics and Computing Sciences')
        ->assertSee('BatStateU The NEU ARASOF-Nasugbu Campus')
        ->assertSee('From your profile')
        ->assertSee('Leave blank if not applicable')
        ->assertSee('III. Sustainable Development Goal')
        ->assertSee('SDG17:')
        ->assertSee('XIII. Duties and Responsibilities of Each Member')
        ->assertSee('Add Research Design visual')
        ->assertSee('Images belong to Research Design only')
        ->assertSee('Write each method heading in your own words')
        ->assertSee('Write this method heading')
        ->assertSee('Add method group')
        ->assertSee('Add method')
        ->assertSee('Related Studies and Literature')
        ->assertSee('Responsibility %')
        ->assertSee('Search workspace members')
        ->assertSee('No available workspace member matches your search.')
        ->assertSee('Proposal workspace')
        ->assertSee('Members already on the project staff list are hidden.')
        ->assertSee('External team member')
        ->assertSee('No project staff added yet.')
        ->assertSee('Names follow the official uppercase format.')
        ->assertSee('Leave blank when no professional title applies.')
        ->assertSee('Changes also update Project Details and the prepared-by name.')
        ->assertSee('Asst Prof.')
        ->assertSee('Enter the external staff member&rsquo;s optional professional title, name, email, and 11-digit contact number manually.', false)
        ->assertSee('Signature names')
        ->assertSee('Choose signatories')
        ->assertSee('Download exact Word file')
        ->assertDontSee('Ctrl + S')
        ->assertSee('Changes save automatically.')
        ->assertSee('data-detailed-proposal-autosave="true"', false)
        ->assertSee('data-detailed-proposal-autosave-form', false)
        ->assertSee('data-detailed-proposal-completion-status', false);

    expect($response->getContent())
        ->toContain('recheckCompletion: false')
        ->toContain('detailedProposalStarted: false')
        ->toContain('detailedProposalComplete: false');

    expect($response->getContent())
        ->toContain('id="proponent-department" name="proponent_department" type="text" maxlength="255"')
        ->not->toContain('id="proponent-department" name="proponent_department" type="text" required')
        ->toContain('id="proponent-college" name="proponent_college" type="text" required')
        ->not->toContain('id="checked-verified-by-name" name="checked_verified_by_name" type="text" maxlength="255"')
        ->not->toContain('id="recommending-approval-name" name="recommending_approval_name" type="text" maxlength="255"')
        ->not->toContain('id="approved-by-name" name="approved_by_name" type="text" maxlength="255"')
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

test('detailed proposal citations link selected RRL text to one proposal library source and synchronize its reference', function () {
    $sourceId = $this->actingAs($this->faculty)
        ->postJson(route('research-support.literature-library.store'), [
            'title' => 'Participatory Coastal Monitoring',
            'authors' => 'Maria Santos',
            'description' => 'This study examines how local participation can strengthen the continuity of coastal monitoring activities.',
            'year' => 2025,
            'venue' => 'Coastal Research Journal',
            'doi' => '10.5555/coastal.monitoring.2025',
            'url' => 'https://doi.org/10.5555/coastal.monitoring.2025',
            'source' => 'OpenAlex',
            'type' => 'article',
        ])
        ->assertCreated()
        ->json('source.id');
    $source = LiteratureSource::query()->findOrFail($sourceId);
    $sourceLinkId = $this->actingAs($this->faculty)
        ->postJson(route('faculty.proposal-drafts.literature-sources.store', [$this->draft, $source]))
        ->assertCreated()
        ->json('source.id');
    $citation = [
        'id' => 'citation-1',
        'source_link_id' => $sourceLinkId,
        'literature_source_id' => $sourceId,
        'field' => 'related_literature',
        'selected_text' => 'Participatory coastal monitoring can improve the continuity of local research activities.',
        'locator' => 'p. 14',
        'created_at' => now()->toIso8601String(),
    ];
    $payload = ($this->payload)([
        'related_literature' => '<p>Participatory coastal monitoring can <strong>improve</strong> the continuity of local research activities.<span data-proposal-citation="'.$sourceLinkId.'"> [1]</span></p>',
        'literature_citations' => json_encode([$citation], JSON_THROW_ON_ERROR),
        'references' => '<p>[1] M. Santos, “Participatory Coastal Monitoring,” Coastal Research Journal, 2025.</p>',
    ]);

    $this->actingAs($this->faculty)
        ->put(route('faculty.proposal-drafts.detailed-proposal.update', $this->draft), $payload)
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $sourceData = $this->draft->documents()
        ->where('document_type', config('proposal_papers.detailed-proposal.document_type'))
        ->value('source_data');

    expect(json_decode($sourceData['literature_citations'], true, flags: JSON_THROW_ON_ERROR))
        ->toMatchArray([$citation])
        ->and($sourceData['related_literature'])
        ->toContain('<strong>improve</strong>')
        ->toContain('data-proposal-citation="'.$sourceLinkId.'"')
        ->toContain('[1]')
        ->and($sourceData['references'])
        ->toContain('“Participatory Coastal Monitoring,”');

    $this->actingAs($this->faculty)
        ->post(route('faculty.proposal-drafts.detailed-proposal.preview', $this->draft), $payload)
        ->assertOk()
        ->assertSee('[1]')
        ->assertSee('Participatory Coastal Monitoring');

    $documentResponse = $this->actingAs($this->faculty)
        ->post(route('faculty.proposal-drafts.detailed-proposal.download', $this->draft), $payload)
        ->assertOk();
    $temporaryPath = tempnam(sys_get_temp_dir(), 'detailed-proposal-citation-');
    file_put_contents($temporaryPath, $documentResponse->streamedContent());
    $archive = new ZipArchive;

    try {
        expect($archive->open($temporaryPath))->toBeTrue()
            ->and($archive->getFromName('word/document.xml'))
            ->toContain('[1]');
    } finally {
        $archive->close();
        unlink($temporaryPath);
    }

    $this->actingAs($this->faculty)
        ->get(route('faculty.proposal-drafts.detailed-proposal.edit', $this->draft))
        ->assertOk()
        ->assertSee('Review evidence')
        ->assertSee('Save source')
        ->assertSee('Add to Section XI')
        ->assertDontSee('Add reference only')
        ->assertSee('proposal-cite-selection', false)
        ->assertSee('literature_citations', false);
});

test('a complete detailed proposal autosave is promoted immediately', function () {
    $payload = ($this->payload)(['save_as_draft' => '1']);

    $this->actingAs($this->faculty)
        ->putJson(route('faculty.proposal-drafts.detailed-proposal.update', $this->draft), $payload)
        ->assertOk()
        ->assertJsonPath('saved_as_draft', false);

    $document = $this->draft->documents()
        ->where('document_type', ProposalVersionFile::TYPE_DETAILED_PROPOSAL)
        ->sole();

    expect($document->completed_at)->not->toBeNull();

    $this->actingAs($this->faculty)
        ->get(route('faculty.proposal-drafts.detailed-proposal.edit', $this->draft))
        ->assertOk()
        ->assertSee('Complete')
        ->assertSee('recheckCompletion: false', false);
});

test('autosave keeps a newly added collaborator when their required proposal details are incomplete', function () {
    $completePayload = ($this->payload)(['save_as_draft' => '1']);

    $this->actingAs($this->faculty)
        ->putJson(route('faculty.proposal-drafts.detailed-proposal.update', $this->draft), $completePayload)
        ->assertOk()
        ->assertJsonPath('document_version', 1)
        ->assertJsonPath('saved_as_draft', false);

    $collaborator = User::factory()->create([
        'name' => 'New Proposal Collaborator',
        'email' => 'new.collaborator@g.batstate-u.edu.ph',
        'contact_number' => null,
    ]);
    $this->draft->members()->create([
        'user_id' => $collaborator->id,
        'name' => $collaborator->name,
        'email' => $collaborator->email,
        'accepted_at' => now(),
    ]);
    $payload = ($this->payload)([
        'document_version' => 1,
        'save_as_draft' => '1',
        'staff' => [
            ...$completePayload['staff'],
            [
                'title' => '',
                'name' => $collaborator->name,
                'email' => $collaborator->email,
                'contact' => '',
            ],
        ],
        'responsibilities' => [
            ...$completePayload['responsibilities'],
            [
                'name' => $collaborator->name,
                'percentage' => '',
                'duties' => '',
            ],
        ],
    ]);

    $this->actingAs($this->faculty)
        ->putJson(route('faculty.proposal-drafts.detailed-proposal.update', $this->draft), $payload)
        ->assertOk()
        ->assertJsonPath('document_version', 2)
        ->assertJsonPath('saved_as_draft', true);

    $document = $this->draft->documents()
        ->where('document_type', ProposalVersionFile::TYPE_DETAILED_PROPOSAL)
        ->sole();
    $storedCollaborator = collect($document->source_data['staff'])->firstWhere('email', $collaborator->email);
    $storedResponsibility = collect($document->source_data['responsibilities'])->firstWhere('name', $collaborator->name);

    expect($document->completed_at)->toBeNull()
        ->and($storedCollaborator)->toMatchArray([
            'title' => '',
            'name' => $collaborator->name,
            'email' => $collaborator->email,
            'contact' => '',
        ])
        ->and($storedResponsibility)->toMatchArray([
            'name' => $collaborator->name,
            'percentage' => '',
            'duties' => '',
        ]);
});

test('detailed proposal structures and numbers objectives and quantified expected outputs', function () {
    $payload = ($this->payload)([
        'general_objective' => 'Improve the management of the campus library collection.',
        'specific_objectives' => [
            ['description' => 'Implement an automated book cataloging module.'],
            ['description' => 'Create circulation and user management features.'],
        ],
        'expected_outputs' => [
            'publication' => [[
                'quantity' => 1,
                'unit' => 'publication',
                'description' => 'in a peer-reviewed computing journal.',
            ]],
            'social_impact' => [[
                'quantity' => null,
                'unit' => '',
                'description' => 'Improved access to academic literature across campus.',
            ]],
        ],
    ]);

    $this->actingAs($this->faculty)
        ->put(route('faculty.proposal-drafts.detailed-proposal.update', $this->draft), $payload)
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $sourceData = $this->draft->documents()
        ->where('document_type', config('proposal_papers.detailed-proposal.document_type'))
        ->value('source_data');

    expect($sourceData['general_objective'])->toBe('<p>Improve the management of the campus library collection.</p>')
        ->and($sourceData['specific_objectives'])->toMatchArray([
            ['description' => 'Implement an automated book cataloging module.'],
            ['description' => 'Create circulation and user management features.'],
        ])
        ->and($sourceData['expected_outputs']['publication'][0]['quantity'])->toBeNull()
        ->and($sourceData['expected_outputs']['publication'][0]['description'])->toBe('One (1) publication in a peer-reviewed computing journal.')
        ->and($sourceData['expected_outputs']['social_impact'][0]['quantity'])->toBeNull();

    $preview = $this->actingAs($this->faculty)
        ->post(route('faculty.proposal-drafts.detailed-proposal.preview', $this->draft), $payload)
        ->assertOk();

    $preview
        ->assertSee('General Objective:')
        ->assertSee('Implement an automated book cataloging module.')
        ->assertSee('One (1) publication')
        ->assertSee('Improved access to academic literature across campus.');

    $documentResponse = $this->actingAs($this->faculty)
        ->post(route('faculty.proposal-drafts.detailed-proposal.download', $this->draft), $payload)
        ->assertOk();
    $temporaryPath = tempnam(sys_get_temp_dir(), 'structured-detailed-proposal-');
    file_put_contents($temporaryPath, $documentResponse->streamedContent());
    $archive = new ZipArchive;

    try {
        expect($archive->open($temporaryPath))->toBeTrue();
        $documentXml = $archive->getFromName('word/document.xml');
        $document = new DOMDocument;
        $document->loadXML($documentXml, LIBXML_NONET);
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $objectivesText = (string) $xpath->evaluate('string(//w:body/w:tbl[1]/w:tr[20])');
        $expectedOutputText = (string) $xpath->evaluate('string(//w:body/w:tbl[1]/w:tr[21])');

        expect($objectivesText)
            ->toContain('1. Implement an automated book cataloging module.')
            ->toContain('2. Create circulation and user management features.');
        expect($expectedOutputText)
            ->toContain('One (1) publication');
    } finally {
        $archive->close();

        if (is_file($temporaryPath)) {
            unlink($temporaryPath);
        }
    }
});

test('detailed proposal supports any number of separately worded specific-method groups', function () {
    $payload = ($this->payload)([
        'specific_objectives' => [
            ['description' => 'Implement an automated book cataloging module.'],
        ],
        'specific_method_objectives' => [
            [
                'heading' => 'To build the cataloging and search module.',
                'methods' => [
                    ['description' => 'Develop an indexed search engine for title, author, ISBN, and shelf-location queries.'],
                    ['description' => 'Integrate barcode and QR-code scanning for inventory and circulation workflows.'],
                ]],
            [
                'heading' => 'To establish the circulation and user-management module.',
                'methods' => [
                    ['description' => 'Build role-based profiles with loan limits, histories, and holds.'],
                    ['description' => 'Test borrowing, returns, renewals, and overdue fine scenarios.'],
                ]],
        ],
        'methodology' => [
            'research_design' => 'The project uses an iterative system-development design.',
            'specific_methods' => 'This hidden value is replaced by the structured method groups.',
            'data_analysis' => 'The team will summarize functional and usability test results.',
        ],
    ]);

    $this->actingAs($this->faculty)
        ->put(route('faculty.proposal-drafts.detailed-proposal.update', $this->draft), $payload)
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $sourceData = $this->draft->documents()
        ->where('document_type', config('proposal_papers.detailed-proposal.document_type'))
        ->value('source_data');

    expect($sourceData['specific_method_objectives'])->toMatchArray([
        [
            'heading' => 'To build the cataloging and search module.',
            'methods' => [
                ['description' => 'Develop an indexed search engine for title, author, ISBN, and shelf-location queries.'],
                ['description' => 'Integrate barcode and QR-code scanning for inventory and circulation workflows.'],
            ]],
        [
            'heading' => 'To establish the circulation and user-management module.',
            'methods' => [
                ['description' => 'Build role-based profiles with loan limits, histories, and holds.'],
                ['description' => 'Test borrowing, returns, renewals, and overdue fine scenarios.'],
            ]],
    ])
        ->and($sourceData['methodology']['specific_methods'])
        ->toContain('<strong>A. To build the cataloging and search module.</strong>')
        ->toContain('<strong>B. To establish the circulation and user-management module.</strong>')
        ->toContain('<ol><li>Develop an indexed search engine')
        ->toContain('<li>Test borrowing, returns, renewals, and overdue fine scenarios.</li>');

    $this->actingAs($this->faculty)
        ->post(route('faculty.proposal-drafts.detailed-proposal.preview', $this->draft), $payload)
        ->assertOk()
        ->assertSee('A. To build the cataloging and search module.')
        ->assertSee('Develop an indexed search engine for title, author, ISBN, and shelf-location queries.')
        ->assertSee('B. To establish the circulation and user-management module.')
        ->assertSee('Test borrowing, returns, renewals, and overdue fine scenarios.');

    $documentResponse = $this->actingAs($this->faculty)
        ->post(route('faculty.proposal-drafts.detailed-proposal.download', $this->draft), $payload)
        ->assertOk();
    $temporaryPath = tempnam(sys_get_temp_dir(), 'specific-method-objectives-');
    file_put_contents($temporaryPath, $documentResponse->streamedContent());
    $archive = new ZipArchive;

    try {
        expect($archive->open($temporaryPath))->toBeTrue();
        $document = new DOMDocument;
        $document->loadXML($archive->getFromName('word/document.xml'), LIBXML_NONET);
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $methodologyText = (string) $xpath->evaluate('string(//w:body/w:tbl[1]/w:tr[23])');

        expect($methodologyText)
            ->toContain('A. To build the cataloging and search module.')
            ->toContain('1. Develop an indexed search engine for title, author, ISBN, and shelf-location queries.')
            ->toContain('B. To establish the circulation and user-management module.')
            ->toContain('2. Test borrowing, returns, renewals, and overdue fine scenarios.');
    } finally {
        $archive->close();

        if (is_file($temporaryPath)) {
            unlink($temporaryPath);
        }
    }
});

test('legacy specific-method text remains available when a structured method group has not been saved yet', function () {
    $legacyMethods = '<p><strong>A. Implement an automated book cataloging module.</strong></p><ol><li>Develop an indexed search engine.</li><li>Test cataloging workflows.</li></ol>';
    $payload = ($this->payload)([
        'specific_objectives' => [
            ['description' => 'Implement an automated book cataloging module.'],
        ],
        'methodology' => [
            'research_design' => 'The project uses an iterative system-development design.',
            'specific_methods' => $legacyMethods,
            'data_analysis' => 'The team will summarize functional and usability test results.',
        ],
    ]);

    $this->actingAs($this->faculty)
        ->put(route('faculty.proposal-drafts.detailed-proposal.update', $this->draft), $payload)
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $sourceData = $this->draft->documents()
        ->where('document_type', config('proposal_papers.detailed-proposal.document_type'))
        ->value('source_data');

    expect($sourceData['specific_method_objectives'])->toBe([])
        ->and($sourceData['methodology']['specific_methods'])
        ->toContain('<strong>A. Implement an automated book cataloging module.</strong>')
        ->toContain('<li>Develop an indexed search engine.</li>')
        ->toContain('<li>Test cataloging workflows.</li>');
});

test('legacy objective headings and plain output fields never expose rich text markup', function () {
    $payload = ($this->payload)([
        'general_objective' => '',
        'specific_objectives' => [
            ['description' => '<p>General Objective:</p>'],
            ['description' => '<p>Design and deploy a web-based Book Management System.</p>'],
            ['description' => '<p>Specific Objectives:</p>'],
            ['description' => '<p>Implement automated cataloging.</p>'],
            ['description' => '<p>Create circulation management.</p>'],
        ],
        'expected_outputs' => [
            'publication' => [[
                'quantity' => 1,
                'unit' => 'publication',
                'description' => '<p>in a peer-reviewed technology journal.</p>',
            ]],
        ],
    ]);

    $this->actingAs($this->faculty)
        ->put(route('faculty.proposal-drafts.detailed-proposal.update', $this->draft), $payload)
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $sourceData = $this->draft->documents()
        ->where('document_type', config('proposal_papers.detailed-proposal.document_type'))
        ->value('source_data');

    expect($sourceData['general_objective'])->toBe('<p>Design and deploy a web-based Book Management System.</p>')
        ->and($sourceData['specific_objectives'])->toMatchArray([
            ['description' => 'Implement automated cataloging.'],
            ['description' => 'Create circulation management.'],
        ])
        ->and($sourceData['expected_outputs']['publication'][0]['description'])->toBe('One (1) publication in a peer-reviewed technology journal.');
});

test('detailed proposal preserves only approved semantic formatting', function () {
    $payload = ($this->payload)([
        'executive_brief' => '<p><strong>Important</strong> <span style="font-family: Comic Sans; color: red">library result</span><script>alert(1)</script></p>',
    ]);

    $this->actingAs($this->faculty)
        ->put(route('faculty.proposal-drafts.detailed-proposal.update', $this->draft), $payload)
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $sourceData = $this->draft->documents()
        ->where('document_type', config('proposal_papers.detailed-proposal.document_type'))
        ->value('source_data');

    expect($sourceData['executive_brief'])
        ->toBe('<p><strong>Important</strong> library result</p>')
        ->not->toContain('font-family')
        ->not->toContain('script');

    $documentResponse = $this->actingAs($this->faculty)
        ->post(route('faculty.proposal-drafts.detailed-proposal.download', $this->draft), $payload)
        ->assertOk();
    $temporaryPath = tempnam(sys_get_temp_dir(), 'formatted-detailed-proposal-');
    file_put_contents($temporaryPath, $documentResponse->streamedContent());
    $archive = new ZipArchive;

    try {
        expect($archive->open($temporaryPath))->toBeTrue();
        $document = new DOMDocument;
        $document->loadXML($archive->getFromName('word/document.xml'), LIBXML_NONET);
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $importantRun = $xpath->query('//w:r[w:t = "Important"]')->item(0);

        expect($importantRun)->not->toBeNull()
            ->and($xpath->query('w:rPr/w:b', $importantRun)->length)->toBe(1)
            ->and($xpath->evaluate('string(w:rPr/w:rFonts/@w:ascii)', $importantRun))->toBe('Times New Roman');
    } finally {
        $archive->close();

        if (is_file($temporaryPath)) {
            unlink($temporaryPath);
        }
    }
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
        ->toContain('detailed-proposal-note-detail { padding-left: 0.55in; }')
        ->toContain('zoom: 1 !important;');
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

test('detailed proposal autosave promotes complete content without duplicating unchanged versions', function () {
    $payload = ($this->payload)(['save_as_draft' => '1']);

    $this->actingAs($this->faculty)
        ->putJson(route('faculty.proposal-drafts.detailed-proposal.update', $this->draft), $payload)
        ->assertOk()
        ->assertJsonPath('message', 'Detailed Research Proposal saved.')
        ->assertJsonPath('document_version', 1)
        ->assertJsonPath('draft_version', 0)
        ->assertJsonPath('saved_as_draft', false);

    $document = $this->draft->documents()
        ->where('document_type', ProposalVersionFile::TYPE_DETAILED_PROPOSAL)
        ->sole();

    expect($document->completed_at)->not->toBeNull()
        ->and($document->lock_version)->toBe(1);

    $document->update([
        'file_path' => 'proposal-drafts/revision/detailed-proposal.docx',
        'original_filename' => 'detailed-proposal.docx',
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'file_size' => 1024,
        'checksum' => hash('sha256', 'detailed proposal'),
    ]);

    $this->actingAs($this->faculty)
        ->putJson(route('faculty.proposal-drafts.detailed-proposal.update', $this->draft), [
            ...$payload,
            'document_version' => 1,
        ])
        ->assertOk()
        ->assertJsonPath('document_version', 1);

    expect($document->fresh()->file_path)->toBe('proposal-drafts/revision/detailed-proposal.docx');
});

test('detailed proposal autosave retains the literature assistant research trail', function () {
    $history = [[
        'id' => 'book-management-search-1',
        'query' => 'web based book management system library circulation',
        'context' => ['Project title', 'Specific objectives'],
        'result_count' => 12,
        'searched_at' => now()->toIso8601String(),
    ]];
    $payload = ($this->payload)([
        'save_as_draft' => '1',
        'literature_research_history' => json_encode($history, JSON_THROW_ON_ERROR),
    ]);

    $this->actingAs($this->faculty)
        ->putJson(route('faculty.proposal-drafts.detailed-proposal.update', $this->draft), $payload)
        ->assertOk();

    $document = $this->draft->documents()
        ->where('document_type', config('proposal_papers.detailed-proposal.document_type'))
        ->sole();

    expect(json_decode($document->source_data['literature_research_history'], true, 512, JSON_THROW_ON_ERROR))
        ->toBe($history);
});

test('detailed proposal autosave retains structured literature citations', function () {
    $sourceId = $this->actingAs($this->faculty)
        ->postJson(route('research-support.literature-library.store'), [
            'title' => 'Community Coastal Evidence',
            'authors' => 'R. Cruz',
            'description' => 'Community involvement supports the continuity of coastal monitoring practices.',
            'year' => 2025,
            'venue' => 'Coastal Evidence Review',
            'doi' => '10.5555/coastal.evidence.2025',
            'url' => 'https://doi.org/10.5555/coastal.evidence.2025',
            'source' => 'OpenAlex',
            'type' => 'article',
        ])
        ->assertCreated()
        ->json('source.id');
    $source = LiteratureSource::query()->findOrFail($sourceId);
    $sourceLinkId = $this->actingAs($this->faculty)
        ->postJson(route('faculty.proposal-drafts.literature-sources.store', [$this->draft, $source]))
        ->assertCreated()
        ->json('source.id');
    $citation = [
        'id' => 'autosave-citation',
        'source_link_id' => $sourceLinkId,
        'literature_source_id' => $sourceId,
        'field' => 'related_literature',
        'selected_text' => 'Community involvement supports the continuity of coastal monitoring practices.',
        'locator' => '',
        'created_at' => now()->toIso8601String(),
    ];
    $introductionCitation = [
        ...$citation,
        'id' => 'introduction-citation',
        'field' => 'introduction',
        'selected_text' => 'Coastal monitoring benefits from sustained community participation.',
        'locator' => 'p. 14',
    ];
    $submittedCitations = [
        $citation,
        [...$citation, 'id' => 'duplicate-autosave-citation'],
        $introductionCitation,
    ];

    $this->actingAs($this->faculty)
        ->putJson(route('faculty.proposal-drafts.detailed-proposal.update', $this->draft), ($this->payload)([
            'save_as_draft' => '1',
            'introduction' => '<p>Coastal monitoring benefits from sustained community participation.<span data-proposal-citation="'.$sourceLinkId.'"> [1]</span></p>',
            'related_literature' => '<p>Community involvement supports the continuity of coastal monitoring practices.<span data-proposal-citation="'.$sourceLinkId.'"> [1]</span></p>',
            'literature_citations' => json_encode($submittedCitations, JSON_THROW_ON_ERROR),
        ]))
        ->assertOk()
        ->assertJsonPath('saved_as_draft', false);

    $document = $this->draft->documents()
        ->where('document_type', config('proposal_papers.detailed-proposal.document_type'))
        ->sole();

    $storedCitations = json_decode($document->source_data['literature_citations'], true, flags: JSON_THROW_ON_ERROR);

    expect($storedCitations)
        ->toHaveCount(2)
        ->and($storedCitations[0])
        ->toMatchArray($citation)
        ->and($storedCitations[1])
        ->toMatchArray($introductionCitation);
});

test('detailed proposal autosave preserves legacy reference-only content', function () {
    $sourceId = $this->actingAs($this->faculty)
        ->postJson(route('research-support.literature-library.store'), [
            'title' => 'Digital Library Processing',
            'authors' => 'R. Cruz',
            'description' => 'Digital systems can support consistent library processing and record retrieval.',
            'year' => 2025,
            'venue' => 'Library Systems Review',
            'doi' => '10.5555/digital.library.2025',
            'url' => 'https://doi.org/10.5555/digital.library.2025',
            'source' => 'OpenAlex',
            'type' => 'article',
        ])
        ->assertCreated()
        ->json('source.id');
    $source = LiteratureSource::query()->findOrFail($sourceId);
    $sourceLinkId = $this->actingAs($this->faculty)
        ->postJson(route('faculty.proposal-drafts.literature-sources.store', [$this->draft, $source]))
        ->assertCreated()
        ->json('source.id');
    $reference = '<p>[1] R. Cruz, “Digital Library Processing,” Library Systems Review, 2025.</p>';
    $citation = [[
        'id' => 'reference-only-citation',
        'source_link_id' => $sourceLinkId,
        'literature_source_id' => $sourceId,
        'field' => 'references',
        'selected_text' => '',
        'locator' => '',
        'created_at' => now()->toIso8601String(),
    ]];

    $this->actingAs($this->faculty)
        ->putJson(route('faculty.proposal-drafts.detailed-proposal.update', $this->draft), ($this->payload)([
            'save_as_draft' => '1',
            'references' => $reference,
            'literature_citations' => json_encode($citation, JSON_THROW_ON_ERROR),
        ]))
        ->assertOk()
        ->assertJsonPath('saved_as_draft', false);

    $document = $this->draft->documents()
        ->where('document_type', config('proposal_papers.detailed-proposal.document_type'))
        ->sole();

    expect($document->source_data['references'])
        ->toBe($reference)
        ->and(json_decode($document->source_data['literature_citations'], true, flags: JSON_THROW_ON_ERROR))
        ->toMatchArray($citation);
});

test('detailed proposal auto-save synchronizes saved methodology images', function () {
    $payload = ($this->payload)([
        'save_as_draft' => '1',
        'methodology_images_present' => '1',
        'methodology_images' => [[
            'client_id' => 'methodology-image-client-1',
            'section' => 'research_design',
            'alignment' => 'center',
            'size' => 'medium',
            'caption' => 'Community coastal monitoring workflow',
            'image' => UploadedFile::fake()->image('coastal-workflow.png', 1200, 800),
        ]],
    ]);

    $response = $this->actingAs($this->faculty)
        ->put(route('faculty.proposal-drafts.detailed-proposal.update', $this->draft), $payload, [
            'Accept' => 'application/json',
        ])
        ->assertOk()
        ->assertJsonPath('methodology_images.0.client_id', 'methodology-image-client-1');

    $document = $this->draft->documents()
        ->where('document_type', ProposalVersionFile::TYPE_DETAILED_PROPOSAL)
        ->sole();
    $image = $document->source_data['methodology_images'][0];

    $response->assertJsonPath('methodology_images.0.id', $image['id'])
        ->assertJsonPath(
            'methodology_images.0.url',
            route('faculty.proposal-drafts.detailed-proposal.methodology-images.show', [$this->draft, $image['id']]),
        );
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

test('revision preparation converts the Detailed Research Proposal to PDF before staging', function () {
    $pdfConverter = new class implements DocumentPdfConverter
    {
        public ?string $receivedDocx = null;

        public function convertDocx(string $contents): string
        {
            $this->receivedDocx = $contents;

            return "%PDF-1.7\nconverted detailed proposal";
        }

        public function convertXlsx(string $contents): string
        {
            throw new LogicException('An XLSX conversion was not expected.');
        }
    };
    app()->instance(DocumentPdfConverter::class, $pdfConverter);

    $this->actingAs($this->faculty)
        ->withHeader('X-Revision-PDF', '1')
        ->postJson(route('faculty.proposal-drafts.detailed-proposal.download', $this->draft), ($this->payload)())
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertDownload('community-coastal-research-detailed-research-proposal.pdf')
        ->assertStreamedContent("%PDF-1.7\nconverted detailed proposal");

    expect($pdfConverter->receivedDocx)->toStartWith('PK');
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
