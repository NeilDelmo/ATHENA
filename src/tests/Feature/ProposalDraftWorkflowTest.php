<?php

use App\Actions\RecordProposalDraftDocumentVersion;
use App\Contracts\DocumentPdfConverter;
use App\Models\ProposalDraft;
use App\Models\ProposalDraftDocumentVersion;
use App\Models\ProposalVersionFile;
use App\Models\ResearchCall;
use App\Models\TopicProposal;
use App\Models\User;
use App\Notifications\ProposalActivityNotification;
use App\Support\ProposalDraftReadiness;
use App\Support\ProposalPaperCatalog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['faculty', 'faculty_researcher', 'research_head'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }

    $this->head = User::factory()->create(['name' => 'Research Head']);
    $this->head->assignRole('research_head');
    $this->faculty = User::factory()->create(['name' => 'Faculty Owner']);
    $this->faculty->assignRole('faculty');
    $this->otherFaculty = User::factory()->create(['name' => 'Another Faculty Member']);
    $this->otherFaculty->assignRole('faculty');
    $this->call = ResearchCall::create([
        'title' => 'Open Institutional Research Call',
        'academic_year' => '2026-2027',
        'opens_at' => now()->subDay(),
        'closes_at' => now()->addMonth(),
        'max_active_research_per_faculty' => 2,
        'maximum_budget' => 100000,
        'status' => 'open',
        'created_by' => $this->head->id,
    ]);

    Storage::fake('local');
    $this->withoutVite();
    app()->instance(DocumentPdfConverter::class, new class implements DocumentPdfConverter
    {
        public function convertDocx(string $contents): string
        {
            return "%PDF-1.7\n".hash('sha256', $contents);
        }
    });

    $this->createDraft = function (array $overrides = []): ProposalDraft {
        return ProposalDraft::create([
            'user_id' => $this->faculty->id,
            'research_call_id' => $this->call->id,
            'project_title' => 'Coastal Habitat Restoration',
            ...$overrides,
        ]);
    };

    $this->projectDetails = fn (array $overrides = []): array => [
        'draft_version' => 0,
        'project_title' => 'Coastal Habitat Restoration',
        'duration_months' => 12,
        'planned_start' => '2026-08-01',
        'planned_end' => '2027-07-31',
        'project_leader' => 'Faculty Owner',
        ...$overrides,
    ];

    $this->workPlan = fn (array $overrides = []): array => [
        'document_version' => 0,
        'entries' => [[
            'objective' => 'Document the baseline habitat condition',
            'expected_output' => 'Validated baseline habitat profile',
            'activity' => "Conduct field survey\nComplete community mapping",
            'months' => [1, 2, 3],
        ]],
        ...$overrides,
    ];

    $this->detailedProposal = fn (): array => [
        'research_agenda' => 'Environment and Climate Change',
        'sdgs' => [13, 14, 17],
        'leader_email' => $this->faculty->email,
        'leader_contact' => '+63 917 123 4567',
        'staff' => [],
        'proponent_department' => 'Research Department',
        'proponent_college' => 'College of Arts and Sciences',
        'proponent_campus' => 'ARASOF-Nasugbu',
        'cooperating_agency' => '',
        'executive_brief' => 'This project restores priority coastal habitats through evidence-based community action.',
        'rationale' => 'Coastal habitat degradation threatens biodiversity and local livelihoods.',
        'objectives' => 'Document baseline conditions and validate a community restoration model.',
        'expected_outputs' => [
            'publication' => 'One peer-reviewed publication',
            'patent' => '',
            'product' => 'Validated restoration model',
            'people_service' => 'Community training',
            'place_partnership' => 'University-LGU partnership',
            'policy' => 'Restoration protocol',
            'social_impact' => 'Improved participation',
            'economic_impact' => 'Protected livelihoods',
        ],
        'related_literature' => 'Recent literature supports participatory coastal habitat restoration.',
        'methodology' => [
            'research_design' => 'The project uses a mixed-method design.',
            'specific_methods' => 'The team will conduct surveys, transects, and stakeholder workshops.',
            'data_analysis' => 'Data will be analyzed with descriptive statistics and thematic analysis.',
        ],
        'responsibilities' => [[
            'name' => 'Faculty Owner',
            'duties' => 'Leads implementation, quality assurance, and reporting.',
        ]],
        'references' => 'Author, A. (2025). Coastal habitat restoration. Research Journal, 1(1), 1-10.',
    ];

    $this->completeDraft = function (ProposalDraft $draft): ProposalDraft {
        $draft->update(($this->projectDetails)());

        foreach (app(ProposalPaperCatalog::class)->all() as $paper) {
            if ($paper['mode'] === 'automatic') {
                continue;
            }

            if ($paper['mode'] === 'generated') {
                $sourceData = match ($paper['slug']) {
                    'detailed-proposal' => ($this->detailedProposal)(),
                    'work-plan' => ($this->workPlan)(),
                    'curriculum-vitae' => [
                        'people' => [[
                            'last_name' => 'Owner',
                            'first_name' => 'Faculty',
                            'middle_name' => '',
                            'agency' => '',
                            'gender' => '',
                            'birthday' => '',
                            'street' => '',
                            'barangay' => '',
                            'municipality' => '',
                            'province' => '',
                            'landline' => '',
                            'cellphone' => '',
                            'email' => '',
                            ...collect(array_keys(config('curriculum_vitae.sections')))
                                ->mapWithKeys(fn (string $key): array => [$key => []])
                                ->all(),
                        ]],
                    ],
                    'gad-checklist' => [
                        'project_title' => $draft->project_title,
                        'project_leader' => $draft->project_leader,
                    ],
                    default => [],
                };
                $draft->documents()->create([
                    'document_type' => $paper['document_type'],
                    'position' => 0,
                    'source_data' => $sourceData,
                    'completed_at' => now(),
                ]);

                continue;
            }

            $extension = $paper['accepted_extensions'][0];
            $path = $draft->storageDirectory().'/'.$paper['slug'].'/'.$paper['slug'].'.'.$extension;
            $contents = 'staged '.$paper['slug'].' contents';
            Storage::disk('local')->put($path, $contents);
            $draft->documents()->create([
                'document_type' => $paper['document_type'],
                'position' => 0,
                'file_path' => $path,
                'original_filename' => $paper['slug'].'.'.$extension,
                'mime_type' => $paper['accepted_mime_types'][0],
                'file_size' => strlen($contents),
                'checksum' => hash('sha256', $contents),
                'completed_at' => now(),
            ]);
        }

        return $draft->fresh(['documents', 'researchCall']);
    };
});

test('faculty can create and resume multiple proposal drafts through the compatibility entry point', function () {
    $this->actingAs($this->faculty)
        ->get(route('faculty.topics.create'))
        ->assertRedirect(route('faculty.proposal-drafts.index'));

    $this->actingAs($this->faculty)
        ->get(route('faculty.proposal-drafts.index'))
        ->assertOk();

    $this->actingAs($this->faculty)
        ->get(route('faculty.proposal-drafts.create'))
        ->assertOk()
        ->assertSee($this->call->title);

    foreach (['First Coastal Study', 'Second Coastal Study'] as $projectTitle) {
        $this->actingAs($this->faculty)
            ->post(route('faculty.proposal-drafts.store'), [
                'research_call_id' => $this->call->id,
                'project_title' => $projectTitle,
            ])
            ->assertRedirect();
    }

    expect($this->faculty->proposalDrafts()->count())->toBe(2);

    $draft = $this->faculty->proposalDrafts()->where('project_title', 'First Coastal Study')->firstOrFail();

    $this->actingAs($this->faculty)
        ->get(route('faculty.proposal-drafts.index'))
        ->assertOk()
        ->assertSee('First Coastal Study')
        ->assertSee('Second Coastal Study')
        ->assertSee(route('faculty.proposal-drafts.show', $draft), false);

    auth()->logout();

    $this->actingAs($this->faculty->fresh())
        ->get(route('faculty.proposal-drafts.show', $draft))
        ->assertOk()
        ->assertSee('First Coastal Study');
});

test('deleting a draft removes its records and private staged directory', function () {
    $draft = ($this->createDraft)();
    $path = $draft->storageDirectory().'/detailed-proposal/draft.pdf';
    Storage::disk('local')->put($path, 'draft contents');
    $document = $draft->documents()->create([
        'document_type' => ProposalVersionFile::TYPE_DETAILED_PROPOSAL,
        'position' => 0,
        'file_path' => $path,
        'original_filename' => 'draft.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 14,
        'checksum' => hash('sha256', 'draft contents'),
        'completed_at' => now(),
    ]);

    $this->actingAs($this->faculty)
        ->delete(route('faculty.proposal-drafts.destroy', $draft))
        ->assertRedirect(route('faculty.proposal-drafts.index'));

    $this->assertDatabaseMissing('proposal_drafts', ['id' => $draft->id]);
    $this->assertDatabaseMissing('proposal_draft_documents', ['id' => $document->id]);
    Storage::disk('local')->assertMissing($path);
    expect(Storage::disk('local')->allFiles($draft->storageDirectory()))->toBeEmpty();
});

test('every draft paper and submission endpoint is protected from another owner', function () {
    $draft = ($this->completeDraft)(($this->createDraft)());
    $document = $draft->documents->firstWhere('document_type', ProposalVersionFile::TYPE_DETAILED_PROPOSAL);
    $workPlan = ($this->workPlan)();

    $requests = [
        fn () => $this->get(route('faculty.proposal-drafts.show', $draft)),
        fn () => $this->get(route('faculty.proposal-drafts.details.edit', $draft)),
        fn () => $this->put(route('faculty.proposal-drafts.details.update', $draft), ($this->projectDetails)()),
        fn () => $this->get(route('faculty.proposal-drafts.papers.edit', [$draft, 'detailed-proposal'])),
        fn () => $this->put(route('faculty.proposal-drafts.papers.update', [$draft, 'detailed-proposal']), [
            'documents' => [UploadedFile::fake()->create('replacement.pdf', 10, 'application/pdf')],
        ]),
        fn () => $this->get(route('faculty.proposal-drafts.papers.download', [$draft, 'detailed-proposal', $document])),
        fn () => $this->delete(route('faculty.proposal-drafts.papers.remove', [$draft, 'detailed-proposal', $document])),
        fn () => $this->get(route('faculty.proposal-drafts.work-plan.edit', $draft)),
        fn () => $this->put(route('faculty.proposal-drafts.work-plan.update', $draft), $workPlan),
        fn () => $this->post(route('faculty.proposal-drafts.work-plan.preview', $draft), $workPlan),
        fn () => $this->post(route('faculty.proposal-drafts.work-plan.download', $draft), $workPlan),
        fn () => $this->get(route('faculty.proposal-drafts.line-item-budget.edit', $draft)),
        fn () => $this->put(route('faculty.proposal-drafts.line-item-budget.update', $draft), []),
        fn () => $this->post(route('faculty.proposal-drafts.line-item-budget.preview', $draft), []),
        fn () => $this->post(route('faculty.proposal-drafts.line-item-budget.download', $draft), []),
        fn () => $this->get(route('faculty.proposal-drafts.curriculum-vitae.edit', $draft)),
        fn () => $this->put(route('faculty.proposal-drafts.curriculum-vitae.update', $draft), []),
        fn () => $this->post(route('faculty.proposal-drafts.curriculum-vitae.preview', $draft), []),
        fn () => $this->post(route('faculty.proposal-drafts.curriculum-vitae.download', $draft), []),
        fn () => $this->get(route('faculty.proposal-drafts.gad-checklist.edit', $draft)),
        fn () => $this->put(route('faculty.proposal-drafts.gad-checklist.update', $draft), ['document_version' => 1]),
        fn () => $this->post(route('faculty.proposal-drafts.gad-checklist.preview', $draft)),
        fn () => $this->post(route('faculty.proposal-drafts.gad-checklist.download', $draft)),
        fn () => $this->get(route('faculty.proposal-drafts.initial-screening-form.show', $draft)),
        fn () => $this->get(route('faculty.proposal-drafts.initial-screening-form.preview', $draft)),
        fn () => $this->get(route('faculty.proposal-drafts.initial-screening-form.download', $draft)),
        fn () => $this->get(route('faculty.proposal-drafts.review', $draft)),
        fn () => $this->post(route('faculty.proposal-drafts.submit', $draft)),
        fn () => $this->delete(route('faculty.proposal-drafts.destroy', $draft)),
    ];

    $this->actingAs($this->otherFaculty);

    foreach ($requests as $request) {
        $request()->assertForbidden();
    }

    expect(ProposalDraft::find($draft->id))->not->toBeNull()
        ->and(TopicProposal::query()->count())->toBe(0);
    Storage::disk('local')->assertExists($document->file_path);
});

test('the proposal hub presents project details and the seven code-owned required papers', function () {
    $draft = ($this->createDraft)();

    $response = $this->actingAs($this->faculty)
        ->get(route('faculty.proposal-drafts.show', $draft))
        ->assertOk()
        ->assertSee('Project Details')
        ->assertSee('Upload PDF')
        ->assertSee('Review automatic paper')
        ->assertSee('Preview paper')
        ->assertSeeInOrder([
            'Detailed Research Proposal',
            'Attachment A: Work Plan',
            'Attachment B: Line-Item Budget',
            'Estimated Expense Breakdown',
            'Attachment C: Curriculum Vitae',
            'GAD Generic Checklist',
            'Initial Screening Form',
        ]);

    expect(substr_count($response->getContent(), 'Not started'))->toBeGreaterThanOrEqual(6);
});

test('upload-only papers use a dedicated upload layout without editor shortcuts', function () {
    $draft = ($this->createDraft)();

    $this->actingAs($this->faculty)
        ->get(route('faculty.proposal-drafts.papers.edit', [$draft, 'expense-breakdown']))
        ->assertOk()
        ->assertSee('PDF upload')
        ->assertSee('Upload the completed PDF')
        ->assertSee('export or save it as a PDF')
        ->assertSee('Choose completed PDF')
        ->assertSee('How this paper works')
        ->assertDontSee('Editor shortcuts')
        ->assertDontSee('Ctrl + S')
        ->assertDontSee('data-paper-editor', false)
        ->assertDontSee('Discard changes')
        ->assertDontSee('Save changes');

    $this->actingAs($this->faculty)
        ->put(route('faculty.proposal-drafts.papers.update', [$draft, 'expense-breakdown']), [
            'document_version' => 0,
            'documents' => [UploadedFile::fake()->create('completed-expenses.pdf', 100, 'application/pdf')],
        ])
        ->assertRedirect(route('faculty.proposal-drafts.papers.edit', [$draft, 'expense-breakdown']));

    $this->actingAs($this->faculty)
        ->get(route('faculty.proposal-drafts.papers.edit', [$draft, 'expense-breakdown']))
        ->assertOk()
        ->assertSee('Attached')
        ->assertSee('completed-expenses.pdf')
        ->assertSee('Replace the uploaded PDF')
        ->assertSee('Replace PDF');
});

test('paper and review pages render saved files and final readiness actions', function () {
    $draft = ($this->completeDraft)(($this->createDraft)());

    $this->actingAs($this->faculty)
        ->get(route('faculty.proposal-drafts.detailed-proposal.edit', $draft))
        ->assertOk()
        ->assertSee('Detailed Research Proposal')
        ->assertSee('Environment and Climate Change')
        ->assertSee('Save changes')
        ->assertSee('Ctrl + S')
        ->assertSee('Ctrl + Enter')
        ->assertSee('data-paper-submit-status', false)
        ->assertSee('data-paper-save-exit', false);

    $this->actingAs($this->faculty)
        ->get(route('faculty.proposal-drafts.review', $draft))
        ->assertOk()
        ->assertSee('Review and Turn In')
        ->assertSee('Ready to turn in')
        ->assertSee('Preview Work Plan')
        ->assertSee('Preview CV Package')
        ->assertSee('seven immutable PDF attachments')
        ->assertSee('Turn in proposal');
});

test('project details are validated once and reused by the Work Plan workflow', function () {
    $draft = ($this->createDraft)();

    $this->actingAs($this->faculty)
        ->put(route('faculty.proposal-drafts.details.update', $draft), ($this->projectDetails)([
            'duration_months' => 121,
            'planned_end' => '2026-07-31',
        ]))
        ->assertSessionHasErrors(['duration_months', 'planned_end']);

    $this->actingAs($this->faculty)
        ->put(route('faculty.proposal-drafts.details.update', $draft), ($this->projectDetails)([
            'duration_months' => 18,
            'planned_end' => '2028-01-31',
        ]))
        ->assertRedirect(route('faculty.proposal-drafts.details.edit', $draft))
        ->assertSessionHas('success', 'Project details saved.');

    $this->actingAs($this->faculty)
        ->put(route('faculty.proposal-drafts.details.update', $draft), ($this->projectDetails)([
            'draft_version' => 1,
            'duration_months' => 18,
            'planned_end' => '2028-01-31',
            'exit_after_save' => '1',
        ]))
        ->assertRedirect(route('faculty.proposal-drafts.show', $draft));

    $draft->refresh();
    expect($draft->project_title)->toBe('Coastal Habitat Restoration')
        ->and($draft->duration_months)->toBe(18)
        ->and($draft->planned_start->toDateString())->toBe('2026-08-01')
        ->and($draft->planned_end->toDateString())->toBe('2028-01-31')
        ->and($draft->project_leader)->toBe('Faculty Owner');

    $this->actingAs($this->faculty)
        ->get(route('faculty.proposal-drafts.work-plan.edit', $draft))
        ->assertOk()
        ->assertSee('Coastal Habitat Restoration')
        ->assertSee('Faculty Owner')
        ->assertSee('Each 12-month block becomes a matching Attachment A year sheet.');
});

test('the GAD checklist preserves the supplied seven-page document and fills shared project details automatically', function () {
    $draft = ($this->createDraft)();
    $draft->update(($this->projectDetails)());

    $this->actingAs($this->faculty)
        ->get(route('faculty.proposal-drafts.gad-checklist.edit', $draft))
        ->assertOk()
        ->assertSee('No form fields to answer')
        ->assertSee('Auto-filled from shared project information')
        ->assertSee('Coastal Habitat Restoration')
        ->assertSee('Faculty Owner')
        ->assertSee('There are no answers to enter')
        ->assertSee('Mark paper ready')
        ->assertDontSee('Editor shortcuts')
        ->assertDontSee('data-paper-editor', false)
        ->assertDontSee('Save and exit')
        ->assertDontSee('name="project_title"', false)
        ->assertDontSee('name="project_leader"', false);

    $this->actingAs($this->faculty)
        ->put(route('faculty.proposal-drafts.gad-checklist.update', $draft), [
            'document_version' => 0,
            'project_title' => 'Ignored request title',
            'project_leader' => 'Ignored request leader',
        ])
        ->assertRedirect(route('faculty.proposal-drafts.gad-checklist.edit', $draft))
        ->assertSessionHas('success', 'GAD Generic Checklist saved.');

    $document = $draft->documents()
        ->where('document_type', ProposalVersionFile::TYPE_GAD_CHECKLIST)
        ->sole();

    expect($document->source_data)->toBe([
        'project_title' => 'Coastal Habitat Restoration',
        'project_leader' => 'Faculty Owner',
    ])->and($document->completed_at)->not->toBeNull();

    $this->actingAs($this->faculty)
        ->get(route('faculty.proposal-drafts.gad-checklist.edit', $draft))
        ->assertOk()
        ->assertSee('Automatic paper marked ready')
        ->assertDontSee('Mark paper ready');

    $preview = $this->actingAs($this->faculty)
        ->post(route('faculty.proposal-drafts.gad-checklist.preview', $draft), [
            'project_title' => 'Ignored preview title',
            'project_leader' => 'Ignored preview leader',
        ])
        ->assertOk()
        ->assertSee('Coastal Habitat Restoration')
        ->assertSee('Faculty Owner')
        ->assertSee('12.32')
        ->assertSee('Guide for accomplishing Box 7a')
        ->assertDontSee('Ignored preview title')
        ->assertDontSee('Ignored preview leader');

    expect(substr_count($preview->getContent(), 'aria-label="Box 7a GAD Generic Checklist page'))->toBe(7);

    $download = $this->actingAs($this->faculty)
        ->post(route('faculty.proposal-drafts.gad-checklist.download', $draft))
        ->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')
        ->assertDownload('coastal-habitat-restoration-gad-checklist.docx');

    $temporaryPath = tempnam(sys_get_temp_dir(), 'athena-gad-test-');
    expect($temporaryPath)->not->toBeFalse();
    file_put_contents($temporaryPath, $download->streamedContent());

    $generated = new ZipArchive;
    $template = new ZipArchive;

    try {
        expect($generated->open($temporaryPath))->toBeTrue()
            ->and($template->open(config('gad_checklist.template_path')))->toBeTrue();

        $documentXml = $generated->getFromName('word/document.xml');
        expect($documentXml)->not->toBeFalse();

        $documentDom = new DOMDocument;
        expect($documentDom->loadXML($documentXml, LIBXML_NONET))->toBeTrue();
        $documentText = $documentDom->textContent;

        expect($documentText)->toContain('Research Project Title:')
            ->toContain('Coastal Habitat Restoration')
            ->toContain('Faculty Owner')
            ->toContain('12.32')
            ->not->toContain('Ignored request title')
            ->not->toContain('Ignored request leader');

        foreach (['word/footer1.xml', 'word/footnotes.xml', 'word/numbering.xml', 'word/styles.xml', 'word/media/image1.png'] as $preservedPart) {
            expect($generated->getFromName($preservedPart))->toBe($template->getFromName($preservedPart));
        }
    } finally {
        $generated->close();
        $template->close();
        unlink($temporaryPath);
    }
});

test('the Initial Screening Form is automatic and preserves every evaluator-owned field', function () {
    $draft = ($this->createDraft)();

    $waitingItem = app(ProposalDraftReadiness::class)
        ->checklist($draft)
        ->get('initial-screening-form');

    expect($waitingItem['complete'])->toBeFalse()
        ->and($waitingItem['status'])->toBe('Waiting for project details');

    $draft->update(($this->projectDetails)());

    $automaticItem = app(ProposalDraftReadiness::class)
        ->checklist($draft->fresh())
        ->get('initial-screening-form');

    expect($automaticItem['complete'])->toBeTrue()
        ->and($automaticItem['documents'])->toBeEmpty();

    $this->actingAs($this->faculty)
        ->get(route('faculty.proposal-drafts.initial-screening-form.show', $draft))
        ->assertOk()
        ->assertSee('No faculty screening answers required')
        ->assertSee('Coastal Habitat Restoration')
        ->assertSee('Faculty Owner')
        ->assertSee('The Research/RDES Head and assigned central co-evaluator complete')
        ->assertDontSee('Editor shortcuts')
        ->assertDontSee('data-paper-editor', false)
        ->assertDontSee('Save changes')
        ->assertDontSee('name="project_title"', false)
        ->assertDontSee('name="project_leader"', false);

    $this->actingAs($this->faculty)
        ->get(route('faculty.proposal-drafts.initial-screening-form.preview', $draft))
        ->assertOk()
        ->assertSee('BatStateU Initial Screening Form')
        ->assertSee('Coastal Habitat Restoration')
        ->assertSee('Faculty Owner');

    $download = $this->actingAs($this->faculty)
        ->get(route('faculty.proposal-drafts.initial-screening-form.download', $draft))
        ->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')
        ->assertDownload('coastal-habitat-restoration-initial-screening-form.docx');

    $temporaryPath = tempnam(sys_get_temp_dir(), 'athena-screening-test-');
    expect($temporaryPath)->not->toBeFalse();
    file_put_contents($temporaryPath, $download->streamedContent());

    $generated = new ZipArchive;
    $template = new ZipArchive;

    try {
        expect($generated->open($temporaryPath))->toBeTrue()
            ->and($template->open(config('initial_screening_form.template_path')))->toBeTrue();

        $documentXml = $generated->getFromName('word/document.xml');
        expect($documentXml)->not->toBeFalse();

        $documentDom = new DOMDocument;
        expect($documentDom->loadXML($documentXml, LIBXML_NONET))->toBeTrue();

        expect($documentDom->textContent)
            ->toContain('Research Project Title: Coastal Habitat Restoration')
            ->toContain('Project Leader: Faculty Owner')
            ->toContain('Checklist of Submitted Documents:')
            ->toContain('Recommended Action')
            ->toContain('Narrative Evaluation:')
            ->toContain('Head, Research/ Head, Research and Extension')
            ->toContain('Center Head/ Assistant Director for Research')
            ->toContain('Director, Research/ Vice Chancellor for RDES');

        for ($index = 0; $index < $template->numFiles; $index++) {
            $entry = $template->statIndex($index);
            $name = $entry['name'];

            if ($name !== 'word/document.xml') {
                expect($generated->getFromName($name))->toBe($template->getFromName($name));
            }
        }
    } finally {
        $generated->close();
        $template->close();
        unlink($temporaryPath);
    }
});

test('single-file papers can be uploaded downloaded replaced and removed privately', function () {
    $draft = ($this->createDraft)();

    $this->actingAs($this->faculty)
        ->put(route('faculty.proposal-drafts.papers.update', [$draft, 'expense-breakdown']), [
            'document_version' => 0,
            'documents' => [UploadedFile::fake()->create('first-expenses.pdf', 100, 'application/pdf')],
        ])
        ->assertRedirect(route('faculty.proposal-drafts.papers.edit', [$draft, 'expense-breakdown']))
        ->assertSessionHas('success', 'Estimated Expense Breakdown saved.');

    $first = $draft->documents()->sole();
    Storage::disk('local')->assertExists($first->file_path);

    $this->actingAs($this->faculty)
        ->get(route('faculty.proposal-drafts.papers.download', [$draft, 'expense-breakdown', $first]))
        ->assertDownload('first-expenses.pdf');

    $this->actingAs($this->faculty)
        ->put(route('faculty.proposal-drafts.papers.update', [$draft, 'expense-breakdown']), [
            'document_version' => 1,
            'documents' => [UploadedFile::fake()->create('replacement-expenses.pdf', 120, 'application/pdf')],
            'exit_after_save' => '1',
        ])
        ->assertRedirect(route('faculty.proposal-drafts.show', $draft));

    $replacement = $draft->documents()->sole();
    expect($replacement->id)->toBe($first->id)
        ->and($replacement->original_filename)->toBe('replacement-expenses.pdf')
        ->and($replacement->checksum)->toHaveLength(64)
        ->and($replacement->completed_at)->not->toBeNull();
    Storage::disk('local')->assertExists($first->file_path);
    Storage::disk('local')->assertExists($replacement->file_path);

    $this->actingAs($this->faculty)
        ->delete(route('faculty.proposal-drafts.papers.remove', [$draft, 'expense-breakdown', $replacement]))
        ->assertRedirect();

    $this->assertDatabaseMissing('proposal_draft_documents', ['id' => $replacement->id]);
    Storage::disk('local')->assertExists($replacement->file_path);
});

test('paper uploads enforce file types and the 25 MB limit', function () {
    $draft = ($this->createDraft)();

    $this->actingAs($this->faculty)
        ->put(route('faculty.proposal-drafts.papers.update', [$draft, 'expense-breakdown']), [
            'document_version' => 0,
            'documents' => [UploadedFile::fake()->create('expenses.xlsx', 10, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')],
        ])
        ->assertSessionHasErrors('documents.0');

    $this->actingAs($this->faculty)
        ->put(route('faculty.proposal-drafts.papers.update', [$draft, 'expense-breakdown']), [
            'document_version' => 0,
            'documents' => [UploadedFile::fake()->create('oversized.pdf', 25601, 'application/pdf')],
        ])
        ->assertSessionHasErrors('documents.0');

});

test('the nested Work Plan saves source data resumes previews and downloads using shared details', function () {
    $draft = ($this->createDraft)();
    $draft->update(($this->projectDetails)(['duration_months' => 3, 'planned_end' => '2026-10-31']));
    $invalidWorkPlan = ($this->workPlan)();
    $invalidWorkPlan['entries'][0]['months'] = [4];

    $this->actingAs($this->faculty)
        ->put(route('faculty.proposal-drafts.work-plan.update', $draft), $invalidWorkPlan)
        ->assertSessionHasErrors('entries.0.months.0');

    $overlappingWorkPlan = ($this->workPlan)();
    $overlappingWorkPlan['entries'][] = [
        'objective' => 'Validate the restoration approach',
        'expected_output' => 'Validated restoration approach',
        'activity' => 'Validate the approach with community partners',
        'months' => [3],
    ];

    $this->actingAs($this->faculty)
        ->put(route('faculty.proposal-drafts.work-plan.update', $draft), $overlappingWorkPlan)
        ->assertSessionHasErrors('entries.1.months');

    $workPlan = ($this->workPlan)();

    $this->actingAs($this->faculty)
        ->put(route('faculty.proposal-drafts.work-plan.update', $draft), $workPlan)
        ->assertRedirect(route('faculty.proposal-drafts.work-plan.edit', $draft))
        ->assertSessionHas('success', 'Attachment A: Work Plan saved.');

    $saveAndExitWorkPlan = [...$workPlan, 'document_version' => 1, 'exit_after_save' => '1'];

    $this->actingAs($this->faculty)
        ->put(route('faculty.proposal-drafts.work-plan.update', $draft), $saveAndExitWorkPlan)
        ->assertRedirect(route('faculty.proposal-drafts.show', $draft));

    $document = $draft->documents()
        ->where('document_type', ProposalVersionFile::TYPE_WORK_PLAN)
        ->sole();

    expect($document->completed_at)->not->toBeNull()
        ->and($document->source_data['entries'][0]['months'])->toBe([1, 2, 3])
        ->and($document->source_data)->not->toHaveKeys([
            'title',
            'project_title',
            'total_duration_months',
            'planned_start',
            'planned_end',
            'prepared_by',
            'prepared_date',
            'verified_date',
        ]);

    $this->actingAs($this->faculty)
        ->get(route('faculty.proposal-drafts.work-plan.edit', $draft))
        ->assertOk()
        ->assertSee('Document the baseline habitat condition');

    $this->actingAs($this->faculty)
        ->post(route('faculty.proposal-drafts.work-plan.preview', $draft), $workPlan, ['Accept' => 'application/json'])
        ->assertOk()
        ->assertSee('Coastal Habitat Restoration')
        ->assertSee('Faculty Owner')
        ->assertSee('DJOANNA MARIE V. SALAC');

    $this->actingAs($this->faculty)
        ->post(route('faculty.proposal-drafts.work-plan.download', $draft), $workPlan)
        ->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')
        ->assertDownload('coastal-habitat-restoration-work-plan.docx');
});

test('incomplete and closed-call drafts remain available and cannot be submitted', function () {
    $incompleteDraft = ($this->createDraft)();

    $this->actingAs($this->faculty)
        ->post(route('faculty.proposal-drafts.submit', $incompleteDraft))
        ->assertSessionHasErrors(['project_details', 'papers.detailed-proposal']);

    expect(ProposalDraft::find($incompleteDraft->id))->not->toBeNull()
        ->and(TopicProposal::query()->count())->toBe(0);

    $completeDraft = ($this->completeDraft)(($this->createDraft)([
        'project_title' => 'Draft for a Closing Call',
    ]));
    $this->call->update(['status' => 'closed']);

    $this->actingAs($this->faculty)
        ->post(route('faculty.proposal-drafts.submit', $completeDraft))
        ->assertSessionHasErrors('research_call');

    expect(ProposalDraft::find($completeDraft->id))->not->toBeNull()
        ->and(TopicProposal::query()->count())->toBe(0);
    expect(Storage::disk('local')->allFiles($completeDraft->storageDirectory()))->not->toBeEmpty();
});

test('a PDF conversion failure keeps the complete draft available for another Turn in attempt', function () {
    $draft = ($this->completeDraft)(($this->createDraft)());
    app()->instance(DocumentPdfConverter::class, new class implements DocumentPdfConverter
    {
        public function convertDocx(string $contents): string
        {
            throw new RuntimeException('LibreOffice is unavailable.');
        }
    });

    $this->actingAs($this->faculty)
        ->post(route('faculty.proposal-drafts.submit', $draft))
        ->assertRedirect()
        ->assertSessionHasErrors('submission');

    expect(ProposalDraft::find($draft->id))->not->toBeNull()
        ->and(TopicProposal::query()->count())->toBe(0);
    expect(Storage::disk('local')->allFiles($draft->storageDirectory()))->not->toBeEmpty();
});

test('final submission creates one immutable package then rejects a duplicate request', function () {
    Notification::fake();
    $draft = ($this->completeDraft)(($this->createDraft)());
    $draft->documents->each(
        fn ($document) => app(RecordProposalDraftDocumentVersion::class)
            ->handle($document, $this->faculty, 'Ready for Turn in.'),
    );
    $draftId = $draft->id;
    $draftDocumentIds = $draft->documents->pluck('id')->all();
    $stagedPaths = $draft->documents->pluck('file_path')->filter()->values();

    $this->actingAs($this->faculty)
        ->post(route('faculty.proposal-drafts.submit', $draft))
        ->assertRedirect(route('faculty.dashboard'));

    $topic = TopicProposal::query()->sole();
    $version = $topic->versions()->with('files')->sole();
    $workPlan = $version->files->firstWhere('document_type', ProposalVersionFile::TYPE_WORK_PLAN);
    $gadChecklist = $version->files->firstWhere('document_type', ProposalVersionFile::TYPE_GAD_CHECKLIST);
    $initialScreeningForm = $version->files->firstWhere('document_type', ProposalVersionFile::TYPE_INITIAL_SCREENING_FORM);

    expect($topic->user_id)->toBe($this->faculty->id)
        ->and($topic->research_call_id)->toBe($this->call->id)
        ->and($topic->title)->toBe('Coastal Habitat Restoration')
        ->and($topic->status)->toBe('pending')
        ->and($topic->estimated_duration_months)->toBe(12)
        ->and($version->version_number)->toBe(1)
        ->and($version->submission_type)->toBe('initial')
        ->and($version->files)->toHaveCount(7)
        ->and($version->files->pluck('document_type')->sort()->values()->all())->toBe(collect([
            ProposalVersionFile::TYPE_DETAILED_PROPOSAL,
            ProposalVersionFile::TYPE_WORK_PLAN,
            ProposalVersionFile::TYPE_LINE_ITEM_BUDGET,
            ProposalVersionFile::TYPE_EXPENSE_BREAKDOWN,
            ProposalVersionFile::TYPE_CURRICULUM_VITAE,
            ProposalVersionFile::TYPE_GAD_CHECKLIST,
            ProposalVersionFile::TYPE_INITIAL_SCREENING_FORM,
        ])->sort()->values()->all())
        ->and($version->files->every(fn (ProposalVersionFile $file): bool => $file->mime_type === 'application/pdf'))->toBeTrue()
        ->and($version->files->every(fn (ProposalVersionFile $file): bool => str_ends_with($file->original_filename, '.pdf')))->toBeTrue()
        ->and($version->files->every(fn (ProposalVersionFile $file): bool => strlen((string) $file->checksum) === 64))->toBeTrue()
        ->and($workPlan->source_data['project_title'])->toBe('Coastal Habitat Restoration')
        ->and($workPlan->source_data['total_duration_months'])->toBe(12)
        ->and($workPlan->source_data['prepared_by'])->toBe('Faculty Owner')
        ->and($gadChecklist->source_data['project_title'])->toBe('Coastal Habitat Restoration')
        ->and($gadChecklist->source_data['project_leader'])->toBe('Faculty Owner')
        ->and($initialScreeningForm->source_data['project_title'])->toBe('Coastal Habitat Restoration')
        ->and($initialScreeningForm->source_data['project_leader'])->toBe('Faculty Owner');

    $version->files->each(function (ProposalVersionFile $file): void {
        Storage::disk('local')->assertExists($file->file_path);

        if ($file->document_type !== ProposalVersionFile::TYPE_EXPENSE_BREAKDOWN) {
            expect(Storage::disk('local')->get($file->file_path))->toStartWith('%PDF-');
        }
    });
    $stagedPaths->each(
        fn (string $path) => Storage::disk('local')->assertMissing($path),
    );
    $this->assertDatabaseMissing('proposal_drafts', ['id' => $draftId]);
    foreach ($draftDocumentIds as $draftDocumentId) {
        $this->assertDatabaseMissing('proposal_draft_documents', ['id' => $draftDocumentId]);
    }

    $archivedHistory = ProposalDraftDocumentVersion::query()
        ->where('topic_id', $topic->id)
        ->get();
    $archivedFileVersion = $archivedHistory->first(
        fn (ProposalDraftDocumentVersion $history): bool => $history->hasStoredFile(),
    );

    expect($archivedHistory)->toHaveCount(6)
        ->and($archivedHistory->every(fn (ProposalDraftDocumentVersion $history): bool => $history->proposal_draft_id === null))->toBeTrue()
        ->and($archivedHistory->every(fn (ProposalDraftDocumentVersion $history): bool => $history->proposal_draft_document_id === null))->toBeTrue()
        ->and($archivedHistory->every(fn (ProposalDraftDocumentVersion $history): bool => $history->is_current === false))->toBeTrue()
        ->and($archivedFileVersion)->not->toBeNull();
    Storage::disk('local')->assertExists($archivedFileVersion->file_path);

    $this->actingAs($this->faculty)
        ->get(route('topics.show', $topic))
        ->assertOk()
        ->assertSee('Draft history (6)');
    $this->actingAs($this->faculty)
        ->get(route('topics.draft-history.index', $topic))
        ->assertOk()
        ->assertSee('Archived draft history')
        ->assertSee('Ready for Turn in.');
    $this->actingAs($this->faculty)
        ->get(route('topics.draft-history.download', [$topic, $archivedFileVersion]))
        ->assertDownload($archivedFileVersion->original_filename);
    $this->actingAs($this->head)
        ->get(route('topics.draft-history.index', $topic))
        ->assertOk();
    $this->actingAs($this->otherFaculty)
        ->get(route('topics.draft-history.index', $topic))
        ->assertForbidden();

    Notification::assertSentToTimes($this->head, ProposalActivityNotification::class, 1);

    $this->actingAs($this->faculty)
        ->post(route('faculty.proposal-drafts.submit', $draftId))
        ->assertNotFound();

    expect(TopicProposal::query()->count())->toBe(1)
        ->and($topic->versions()->count())->toBe(1);
    Notification::assertSentToTimes($this->head, ProposalActivityNotification::class, 1);
});
