<?php

use App\Models\ProposalDraft;
use App\Models\ProposalVersionFile;
use App\Models\ResearchCall;
use App\Models\TopicProposal;
use App\Models\User;
use App\Notifications\ProposalActivityNotification;
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

test('the proposal hub presents project details and the six code-owned required papers', function () {
    $draft = ($this->createDraft)();

    $response = $this->actingAs($this->faculty)
        ->get(route('faculty.proposal-drafts.show', $draft))
        ->assertOk()
        ->assertSee('Project Details')
        ->assertSeeInOrder([
            'Detailed Research Proposal',
            'Attachment A: Work Plan',
            'Attachment B: Line-Item Budget',
            'Estimated Expense Breakdown',
            'Attachment C: Curriculum Vitae',
            'GAD Generic Checklist',
        ]);

    expect(substr_count($response->getContent(), 'Not started'))->toBeGreaterThanOrEqual(6);
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
        ->assertSee('Review Proposal Package')
        ->assertSee('Ready to submit')
        ->assertSee('Preview Work Plan')
        ->assertSee('Preview CV Package')
        ->assertSee('Download Word file')
        ->assertSee('Submit Proposal Package');
});

test('project details are validated once and reused by the Work Plan workflow', function () {
    $draft = ($this->createDraft)();

    $this->actingAs($this->faculty)
        ->put(route('faculty.proposal-drafts.details.update', $draft), ($this->projectDetails)([
            'duration_months' => 13,
            'planned_end' => '2026-07-31',
        ]))
        ->assertSessionHasErrors(['duration_months', 'planned_end']);

    $this->actingAs($this->faculty)
        ->put(route('faculty.proposal-drafts.details.update', $draft), ($this->projectDetails)())
        ->assertRedirect(route('faculty.proposal-drafts.details.edit', $draft))
        ->assertSessionHas('success', 'Project details saved.');

    $this->actingAs($this->faculty)
        ->put(route('faculty.proposal-drafts.details.update', $draft), ($this->projectDetails)(['draft_version' => 1, 'exit_after_save' => '1']))
        ->assertRedirect(route('faculty.proposal-drafts.show', $draft));

    $draft->refresh();
    expect($draft->project_title)->toBe('Coastal Habitat Restoration')
        ->and($draft->duration_months)->toBe(12)
        ->and($draft->planned_start->toDateString())->toBe('2026-08-01')
        ->and($draft->planned_end->toDateString())->toBe('2027-07-31')
        ->and($draft->project_leader)->toBe('Faculty Owner');

    $this->actingAs($this->faculty)
        ->get(route('faculty.proposal-drafts.work-plan.edit', $draft))
        ->assertOk()
        ->assertSee('Coastal Habitat Restoration')
        ->assertSee('Faculty Owner');
});

test('single-file papers can be uploaded downloaded replaced and removed privately', function () {
    $draft = ($this->createDraft)();

    $this->actingAs($this->faculty)
        ->put(route('faculty.proposal-drafts.papers.update', [$draft, 'gad-checklist']), [
            'document_version' => 0,
            'documents' => [UploadedFile::fake()->create('first-proposal.pdf', 100, 'application/pdf')],
        ])
        ->assertRedirect(route('faculty.proposal-drafts.papers.edit', [$draft, 'gad-checklist']))
        ->assertSessionHas('success', 'GAD Generic Checklist saved.');

    $first = $draft->documents()->sole();
    Storage::disk('local')->assertExists($first->file_path);

    $this->actingAs($this->faculty)
        ->get(route('faculty.proposal-drafts.papers.download', [$draft, 'gad-checklist', $first]))
        ->assertDownload('first-proposal.pdf');

    $this->actingAs($this->faculty)
        ->put(route('faculty.proposal-drafts.papers.update', [$draft, 'gad-checklist']), [
            'document_version' => 1,
            'documents' => [UploadedFile::fake()->create('replacement-proposal.docx', 120, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')],
            'exit_after_save' => '1',
        ])
        ->assertRedirect(route('faculty.proposal-drafts.show', $draft));

    $replacement = $draft->documents()->sole();
    expect($replacement->id)->toBe($first->id)
        ->and($replacement->original_filename)->toBe('replacement-proposal.docx')
        ->and($replacement->checksum)->toHaveLength(64)
        ->and($replacement->completed_at)->not->toBeNull();
    Storage::disk('local')->assertMissing($first->file_path);
    Storage::disk('local')->assertExists($replacement->file_path);

    $this->actingAs($this->faculty)
        ->delete(route('faculty.proposal-drafts.papers.remove', [$draft, 'gad-checklist', $replacement]))
        ->assertRedirect();

    $this->assertDatabaseMissing('proposal_draft_documents', ['id' => $replacement->id]);
    Storage::disk('local')->assertMissing($replacement->file_path);
});

test('paper uploads enforce file types and the 25 MB limit', function () {
    $draft = ($this->createDraft)();

    $this->actingAs($this->faculty)
        ->put(route('faculty.proposal-drafts.papers.update', [$draft, 'expense-breakdown']), [
            'documents' => [UploadedFile::fake()->create('expenses.pdf', 10, 'application/pdf')],
        ])
        ->assertSessionHasErrors('documents.0');

    $this->actingAs($this->faculty)
        ->put(route('faculty.proposal-drafts.papers.update', [$draft, 'gad-checklist']), [
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

test('final submission creates one immutable package then rejects a duplicate request', function () {
    Notification::fake();
    $draft = ($this->completeDraft)(($this->createDraft)());
    $draftId = $draft->id;
    $draftDocumentIds = $draft->documents->pluck('id')->all();
    $stagedPaths = $draft->documents->pluck('file_path')->filter()->values();

    $this->actingAs($this->faculty)
        ->post(route('faculty.proposal-drafts.submit', $draft))
        ->assertRedirect(route('faculty.dashboard'));

    $topic = TopicProposal::query()->sole();
    $version = $topic->versions()->with('files')->sole();
    $workPlan = $version->files->firstWhere('document_type', ProposalVersionFile::TYPE_WORK_PLAN);

    expect($topic->user_id)->toBe($this->faculty->id)
        ->and($topic->research_call_id)->toBe($this->call->id)
        ->and($topic->title)->toBe('Coastal Habitat Restoration')
        ->and($topic->status)->toBe('pending')
        ->and($topic->estimated_duration_months)->toBe(12)
        ->and($version->version_number)->toBe(1)
        ->and($version->submission_type)->toBe('initial')
        ->and($version->files)->toHaveCount(6)
        ->and($version->files->pluck('document_type')->sort()->values()->all())->toBe(collect([
            ProposalVersionFile::TYPE_DETAILED_PROPOSAL,
            ProposalVersionFile::TYPE_WORK_PLAN,
            ProposalVersionFile::TYPE_LINE_ITEM_BUDGET,
            ProposalVersionFile::TYPE_EXPENSE_BREAKDOWN,
            ProposalVersionFile::TYPE_CURRICULUM_VITAE,
            ProposalVersionFile::TYPE_GAD_CHECKLIST,
        ])->sort()->values()->all())
        ->and($version->files->every(fn (ProposalVersionFile $file): bool => strlen((string) $file->checksum) === 64))->toBeTrue()
        ->and($workPlan->source_data['project_title'])->toBe('Coastal Habitat Restoration')
        ->and($workPlan->source_data['total_duration_months'])->toBe(12)
        ->and($workPlan->source_data['prepared_by'])->toBe('Faculty Owner');

    $version->files->each(
        fn (ProposalVersionFile $file) => Storage::disk('local')->assertExists($file->file_path),
    );
    $stagedPaths->each(
        fn (string $path) => Storage::disk('local')->assertMissing($path),
    );
    $this->assertDatabaseMissing('proposal_drafts', ['id' => $draftId]);
    foreach ($draftDocumentIds as $draftDocumentId) {
        $this->assertDatabaseMissing('proposal_draft_documents', ['id' => $draftDocumentId]);
    }

    Notification::assertSentToTimes($this->head, ProposalActivityNotification::class, 1);

    $this->actingAs($this->faculty)
        ->post(route('faculty.proposal-drafts.submit', $draftId))
        ->assertNotFound();

    expect(TopicProposal::query()->count())->toBe(1)
        ->and($topic->versions()->count())->toBe(1);
    Notification::assertSentToTimes($this->head, ProposalActivityNotification::class, 1);
});
