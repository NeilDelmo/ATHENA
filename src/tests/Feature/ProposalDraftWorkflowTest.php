<?php

use App\Actions\RecordProposalDraftDocumentVersion;
use App\Actions\SaveProposalDraftDocument;
use App\Actions\SubmitProposalDraft;
use App\Contracts\DocumentPdfConverter;
use App\Livewire\ProposalDraftReviewPackage;
use App\Models\LiteratureSource;
use App\Models\ProjectProgressReport;
use App\Models\ProposalDraft;
use App\Models\ProposalDraftDocumentVersion;
use App\Models\ProposalFileAnnotation;
use App\Models\ProposalVersionFile;
use App\Models\ResearchCall;
use App\Models\TopicProposal;
use App\Models\User;
use App\Notifications\ProposalActivityNotification;
use App\Services\NoticeToProceedDataService;
use App\Support\InitialScreeningSubmissionOrder;
use App\Support\ProposalDraftReadiness;
use App\Support\ProposalPaperCatalog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
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

        public function convertXlsx(string $contents): string
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

    $this->expenseBreakdown = fn (array $overrides = []): array => [
        'document_version' => 0,
        'items' => [[
            'category' => 'mooe',
            'account' => 'Communication Expenses',
            'sub_account' => 'Telephone Expenses',
            'particulars' => 'Prepaid Card',
            'details' => 'Prepaid Call Card',
            'purpose' => 'For project communication',
            'unit' => 'pc',
            'quantity' => 12,
            'unit_cost' => 300,
        ]],
        ...$overrides,
    ];

    $this->detailedProposal = fn (): array => [
        'research_agenda' => 'Environment and Climate Change',
        'sdgs' => [13, 14, 17],
        'leader_email' => $this->faculty->email,
        'leader_contact' => '09171234567',
        'staff' => [],
        'proponent_department' => 'Research Department',
        'proponent_college' => 'College of Arts and Sciences',
        'proponent_campus' => 'ARASOF-Nasugbu',
        'cooperating_agency' => '',
        'executive_brief' => 'This project restores priority coastal habitats through evidence-based community action.',
        'rationale' => 'Coastal habitat degradation threatens biodiversity and local livelihoods.',
        'general_objective' => 'Document baseline conditions and validate a community restoration model.',
        'specific_objectives' => [[
            'description' => 'Document baseline conditions and validate a community restoration model.',
        ]],
        'expected_outputs' => [
            'publication' => [['description' => 'One peer-reviewed publication']],
            'patent' => [],
            'product' => [['description' => 'Validated restoration model']],
            'people_service' => [['description' => 'Community training']],
            'place_partnership' => [['description' => 'University-LGU partnership']],
            'policy' => [['description' => 'Restoration protocol']],
            'social_impact' => [['description' => 'Improved participation']],
            'economic_impact' => [['description' => 'Protected livelihoods']],
        ],
        'introduction' => 'The project responds to coastal habitat degradation through community research.',
        'related_literature' => 'Recent literature supports participatory coastal habitat restoration.',
        'methodology' => [
            'research_design' => 'The project uses a mixed-method design.',
            'specific_methods' => 'The team will conduct surveys, transects, and stakeholder workshops.',
            'data_analysis' => 'Data will be analyzed with descriptive statistics and thematic analysis.',
        ],
        'responsibilities' => [[
            'name' => 'Faculty Owner',
            'percentage' => 100,
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
                    'line-item-budget' => [
                        'amounts' => [
                            'telephone_expenses' => 3600,
                        ],
                    ],
                    'expense-breakdown' => ($this->expenseBreakdown)(),
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

        $draft = $draft->fresh(['documents', 'researchCall']);
        app(SubmitProposalDraft::class)->prepare($draft, $this->faculty);

        return $draft->fresh(['documents', 'researchCall']);
    };
});

test('faculty create proposal drafts without selecting a research call', function () {
    $this->actingAs($this->faculty)->get(route('faculty.proposal-drafts.create'))
        ->assertOk()->assertSee('Project Title')->assertDontSee('name="research_call_id"', false);

    foreach (['First Coastal Study', 'Second Coastal Study'] as $title) {
        $this->post(route('faculty.proposal-drafts.store'), ['project_title' => $title])
            ->assertSessionHasNoErrors();
    }
    expect($this->faculty->proposalDrafts()->count())->toBe(2)
        ->and($this->faculty->proposalDrafts()->pluck('research_call_id')->unique()->all())->toBe([null]);
    $this->get(route('faculty.proposal-drafts.index'))->assertOk()->assertSee('First Coastal Study');
});

test('faculty can start a proposal when no research calls are open', function () {
    $this->call->update(['status' => 'closed']);
    $this->actingAs($this->faculty)->get(route('faculty.proposal-drafts.create'))
        ->assertOk()->assertDontSee('Proposal submissions are closed')->assertSee('Create draft and continue');
    $this->post(route('faculty.proposal-drafts.store'), ['project_title' => 'Anytime Proposal'])
        ->assertSessionHasNoErrors();
    expect($this->faculty->proposalDrafts()->sole()->research_call_id)->toBeNull();
});

test('research call dates and stale selections do not restrict proposal creation', function () {
    foreach ([now()->addMonth(), now()->subMonth()] as $opening) {
        $this->call->update(['opens_at' => $opening, 'closes_at' => $opening->copy()->addDay()]);
        $this->actingAs($this->faculty)->post(route('faculty.proposal-drafts.store'), [
            'project_title' => 'Independent '.$opening->toDateString(),
            'research_call_id' => $this->call->id,
        ])->assertSessionHasNoErrors();
    }
    expect($this->faculty->proposalDrafts()->count())->toBe(2)
        ->and($this->faculty->proposalDrafts()->whereNotNull('research_call_id')->exists())->toBeFalse();
});

test('faculty can track submitted proposal statuses from the proposal workspace', function () {
    ($this->createDraft)(['project_title' => 'Coastal Draft Package']);
    $revisionProposal = TopicProposal::create([
        'user_id' => $this->faculty->id,
        'research_call_id' => $this->call->id,
        'title' => 'Revised Coastal Study',
        'status' => 'revision_requested',
    ]);
    $approvedProposal = TopicProposal::create([
        'user_id' => $this->faculty->id,
        'research_call_id' => $this->call->id,
        'title' => 'Approved Mangrove Study',
        'status' => 'approved',
    ]);
    TopicProposal::create([
        'user_id' => $this->otherFaculty->id,
        'research_call_id' => $this->call->id,
        'title' => 'Another Faculty Proposal',
        'status' => 'pending',
    ]);

    $this->actingAs($this->faculty)
        ->get(route('faculty.proposal-drafts.index'))
        ->assertOk()
        ->assertSee('data-proposal-workspace-tabs', false)
        ->assertSee('role="tablist"', false)
        ->assertSee('Drafts')
        ->assertSee('Submitted')
        ->assertSee('x-show="activeWorkspaceTab === \'drafts\'"', false)
        ->assertSee('x-show="activeWorkspaceTab === \'submitted\'"', false)
        ->assertSee('submitted-page', false)
        ->assertSee('Coastal Draft Package')
        ->assertSee('Revised Coastal Study')
        ->assertSee('Revision required')
        ->assertSee('data-revision-action-required', false)
        ->assertSee('Research Head feedback is waiting for your response.')
        ->assertSee(route('faculty.topics.revision', $revisionProposal), false)
        ->assertSee('Approved Mangrove Study')
        ->assertSee('Approved project')
        ->assertSee(route('topics.show', $approvedProposal), false)
        ->assertDontSee('Another Faculty Proposal');

    $this->actingAs($this->faculty)
        ->get(route('topics.show', $revisionProposal))
        ->assertOk()
        ->assertSee('data-faculty-revision-summary', false)
        ->assertSee('Open revision workspace')
        ->assertDontSee('Faculty action required')
        ->assertDontSee('What happens next')
        ->assertDontSee('Revision requested â€” your action is required')
        ->assertDontSee('id="submit-revision"', false)
        ->assertSee("activeTopicTab: 'review'", false);

    $this->get(route('faculty.topics.revision', $revisionProposal))
        ->assertOk()
        ->assertSee('data-faculty-revision-required', false)
        ->assertSee('Prepare the corrected proposal package')
        ->assertSee('id="submit-revision"', false);

    $this->actingAs($this->otherFaculty)
        ->get(route('faculty.topics.revision', $revisionProposal))
        ->assertForbidden();

    $this->actingAs($this->faculty)
        ->get(route('faculty.topics.revision', $approvedProposal))
        ->assertRedirect(route('topics.show', $approvedProposal));
});

test('the owner and collaborators review an independent draft without a call picker', function () {
    $draft = ($this->createDraft)(['research_call_id' => null]);
    $draft->members()->create([
        'user_id' => $this->otherFaculty->id, 'name' => $this->otherFaculty->name,
        'email' => $this->otherFaculty->email, 'accepted_at' => now(),
    ]);
    foreach ([$this->faculty, $this->otherFaculty] as $user) {
        $this->actingAs($user)->get(route('faculty.proposal-drafts.show', $draft))
            ->assertOk()->assertDontSee('Choose research call');
        $this->get(route('faculty.proposal-drafts.review', $draft))
            ->assertOk()->assertDontSee('name="research_call_id"', false);
    }
});

test('rapid repeated create requests reuse the first matching proposal draft', function () {
    $payload = [
        'project_title' => 'Duplicate Click Study',
        'research_call_id' => $this->call->id,
    ];

    $firstResponse = $this->actingAs($this->faculty)
        ->post(route('faculty.proposal-drafts.store'), $payload);
    $proposalDraft = $this->faculty->proposalDrafts()->sole();

    $firstResponse
        ->assertRedirect(route('faculty.proposal-drafts.show', $proposalDraft))
        ->assertSessionHas('success', 'Proposal draft created. Complete the project details in the workspace.');

    $this->actingAs($this->faculty)
        ->post(route('faculty.proposal-drafts.store'), $payload)
        ->assertRedirect(route('faculty.proposal-drafts.show', $proposalDraft))
        ->assertSessionHas('success', 'That draft was already created, so ATHENA opened the existing copy instead.');

    expect($this->faculty->proposalDrafts()->count())->toBe(1);
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
        fn () => $this->get(route('faculty.proposal-drafts.gad-checklist.show', $draft)),
        fn () => $this->get(route('faculty.proposal-drafts.gad-checklist.preview', $draft)),
        fn () => $this->get(route('faculty.proposal-drafts.gad-checklist.download', $draft)),
        fn () => $this->get(route('faculty.proposal-drafts.initial-screening-form.show', $draft)),
        fn () => $this->get(route('faculty.proposal-drafts.initial-screening-form.preview', $draft)),
        fn () => $this->get(route('faculty.proposal-drafts.initial-screening-form.download', $draft)),
        fn () => $this->get(route('faculty.proposal-drafts.review', $draft)),
        fn () => $this->post(route('faculty.proposal-drafts.submission-files.prepare', $draft)),
        fn () => $this->get(route('faculty.proposal-drafts.submission-files.download', [$draft, 'detailed-proposal'])),
        fn () => $this->put(route('faculty.proposal-drafts.submission-files.replace', [$draft, 'detailed-proposal']), [
            'document_version' => $document->lock_version,
            'file' => UploadedFile::fake()->create('corrected.pdf', 10, 'application/pdf'),
        ]),
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
        ->assertSee('Required PDF attachments')
        ->assertSee('Project team')
        ->assertSee('Changes save automatically.')
        ->assertSee('Workspace overview')
        ->assertSee('data-workspace-palette="red-black-white"', false)
        ->assertSee('proposal-review', false)
        ->assertSee('open-modal', false)
        ->assertSee('close-modal', false)
        ->assertSee('max-h-[calc(100vh-3rem)]', false)
        ->assertSee('Project team')
        ->assertDontSee('<a href="'.route('faculty.proposal-drafts.details.edit', $draft), false)
        ->assertSee('name="project_title"', false)
        ->assertSee('name="project_leader"', false)
        ->assertSee('role="tab"', false)
        ->assertSee('activeProposalTab', false)
        ->assertSee('lg:grid-cols-[minmax(0,1fr)_18rem]', false)
        ->assertDontSee('data-paper-shortcuts-trigger', false)
        ->assertDontSee('data-paper-shortcuts-dropdown', false)
        ->assertSee('data-project-details-autosave="true"', false)
        ->assertSee('data-project-details-autosave-form', false)
        ->assertSee('aria-labelledby="project-details-heading" class="overflow-visible', false)
        ->assertDontSee('aria-labelledby="project-details-heading" class="overflow-hidden', false)
        ->assertDontSee('Upload PDF')
        ->assertSee('>Open Research Proposal</a>', false)
        ->assertSee('>Open Work Plan</a>', false)
        ->assertSee('>Open Line-Item Budget</a>', false)
        ->assertSee('>Open Expense Breakdown</a>', false)
        ->assertSee('>Open Curriculum Vitae</a>', false)
        ->assertSee('>Open GAD Checklist</a>', false)
        ->assertSee('>Open Initial Screening Form</a>', false)
        ->assertDontSee('>Open paper</a>', false)
        ->assertDontSee('>Edit paper</a>', false)
        ->assertDontSee('>Preview paper</a>', false)
        ->assertDontSee('>Update Project Details</button>', false)
        ->assertSeeInOrder([
            'Detailed Research Proposal',
            'Attachment A: Work Plan',
            'Estimated Expense Breakdown',
            'Attachment B: Line-Item Budget',
            'Attachment C: Curriculum Vitae',
            'GAD Generic Checklist',
            'Initial Screening Form',
        ]);

    expect(substr_count($response->getContent(), 'Not started'))->toBeGreaterThanOrEqual(5);

    $workspaceView = file_get_contents(resource_path('views/faculty/proposal-drafts/show.blade.php'));

    expect($workspaceView)
        ->toContain('data-open-collaborator-modal')
        ->toContain('data-collaborator-invitation-modal')
        ->toContain('<x-modal name="proposal-collaborator-invitation"')
        ->not->toContain('lg:grid-cols-[1fr_1fr_auto] lg:items-end');
});

test('upload-only papers use the protected editor exit and one upload and exit action', function () {
    config()->set('proposal_papers.expense-breakdown.mode', 'upload');
    config()->set('proposal_papers.expense-breakdown.accepted_extensions', ['pdf']);
    config()->set('proposal_papers.expense-breakdown.accepted_mime_types', ['application/pdf']);
    config()->set('proposal_papers.expense-breakdown.max_kilobytes', 25600);
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
        ->assertSee('Exit editor')
        ->assertSee('Upload PDF and exit')
        ->assertDontSee('data-paper-shortcuts-trigger', false)
        ->assertSee('data-paper-editor', false)
        ->assertSee('data-paper-form', false)
        ->assertSee('data-paper-cancel-exit', false)
        ->assertSee('data-paper-save-exit', false)
        ->assertDontSee('data-paper-discard', false)
        ->assertDontSee('<button data-paper-save type="submit"', false);

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
        ->assertSee('Replace PDF and exit');
});

test('paper and review pages render saved files and final readiness actions', function () {
    $draft = ($this->completeDraft)(($this->createDraft)());

    $this->actingAs($this->faculty)
        ->get(route('faculty.proposal-drafts.detailed-proposal.edit', $draft))
        ->assertOk()
        ->assertSee('Detailed Research Proposal')
        ->assertSee('Environment and Climate Change')
        ->assertSee('Exit editor')
        ->assertSee('Changes save automatically.')
        ->assertDontSee('Save and stay')
        ->assertDontSee('Ctrl + S')
        ->assertDontSee('Ctrl + Enter')
        ->assertSee('data-paper-submit-status', false)
        ->assertSee('data-detailed-proposal-autosave="true"', false)
        ->assertDontSee('data-paper-save-exit', false);

    $reviewResponse = $this->actingAs($this->faculty)
        ->get(route('faculty.proposal-drafts.review', $draft))
        ->assertOk()
        ->assertSee('Review and Turn In')
        ->assertSee('Ready to turn in')
        ->assertSee('Preview Detailed Proposal')
        ->assertSee('Preview Work Plan')
        ->assertSee('Preview CV Package')
        ->assertSee('Project team')
        ->assertSee('seven reviewed PDFs are ready')
        ->assertSee('Turn in proposal');

    expect($reviewResponse->getContent())
        ->toContain('href="'.route('faculty.proposal-drafts.expense-breakdown.edit', $draft).'"')
        ->not->toContain('href="'.route('faculty.proposal-drafts.papers.edit', [$draft, 'expense-breakdown']).'"');

    $this->actingAs($this->faculty)
        ->post(route('faculty.proposal-drafts.detailed-proposal.preview', $draft))
        ->assertOk()
        ->assertSee('DETAILED RESEARCH PROPOSAL')
        ->assertSee($draft->project_title);
});

test('project details are validated once and reused by the Work Plan workflow', function () {
    $draft = ($this->createDraft)();

    $projectDetailsResponse = $this->actingAs($this->faculty)
        ->get(route('faculty.proposal-drafts.details.edit', $draft))
        ->assertOk()
        ->assertSee('proposalDraftProjectDetails({', false)
        ->assertSee('x-model.number="durationMonths"', false)
        ->assertSee('x-model="plannedStart"', false)
        ->assertSee('x-model="plannedEnd"', false)
        ->assertSee('Changes save automatically.')
        ->assertSee('data-project-details-autosave="true"', false)
        ->assertSee('data-project-details-autosave-form', false)
        ->assertSee('Only today and future dates can be selected.')
        ->assertSee('Automatically calculated from the total duration and planned start.');

    expect(substr_count($projectDetailsResponse->getContent(), now()->toDateString()))
        ->toBeGreaterThanOrEqual(2);

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
        ->putJson(route('faculty.proposal-drafts.details.update', $draft), ($this->projectDetails)([
            'draft_version' => 1,
            'project_title' => 'Automatically Saved Project Details',
            'duration_months' => 18,
            'planned_end' => '2028-01-31',
        ]))
        ->assertOk()
        ->assertJsonPath('message', 'Project details saved.')
        ->assertJsonPath('draft_version', 2);

    $this->actingAs($this->faculty)
        ->putJson(route('faculty.proposal-drafts.details.update', $draft), ($this->projectDetails)([
            'draft_version' => 2,
            'project_title' => 'Automatically Saved Project Details',
            'duration_months' => 18,
            'planned_end' => '2028-01-31',
        ]))
        ->assertOk()
        ->assertJsonPath('draft_version', 2);

    $this->actingAs($this->faculty)
        ->put(route('faculty.proposal-drafts.details.update', $draft), ($this->projectDetails)([
            'draft_version' => 2,
            'project_title' => 'Automatically Saved Project Details',
            'duration_months' => 18,
            'planned_end' => '2028-01-31',
            'exit_after_save' => '1',
        ]))
        ->assertRedirect(route('faculty.proposal-drafts.show', $draft));

    $draft->refresh();
    expect($draft->project_title)->toBe('Automatically Saved Project Details')
        ->and($draft->duration_months)->toBe(18)
        ->and($draft->planned_start->toDateString())->toBe('2026-08-01')
        ->and($draft->planned_end->toDateString())->toBe('2028-01-31')
        ->and($draft->project_leader)->toBe('Faculty Owner');

    $this->actingAs($this->faculty)
        ->get(route('faculty.proposal-drafts.work-plan.edit', $draft))
        ->assertOk()
        ->assertSee('Automatically Saved Project Details')
        ->assertSee('Faculty Owner')
        ->assertSee('Each 12-month block becomes a matching Attachment A year sheet.');
});

test('the GAD checklist is automatic and preserves every page of the supplied Box 7a document', function () {
    $draft = ($this->createDraft)();

    $waitingItem = app(ProposalDraftReadiness::class)
        ->checklist($draft)
        ->get('gad-checklist');

    expect($waitingItem['complete'])->toBeFalse()
        ->and($waitingItem['status'])->toBe('Waiting for project details')
        ->and($waitingItem['documents'])->toBeEmpty();

    $draft->update(($this->projectDetails)());

    $automaticItem = app(ProposalDraftReadiness::class)
        ->checklist($draft->fresh())
        ->get('gad-checklist');

    expect($automaticItem['complete'])->toBeTrue()
        ->and($automaticItem['documents'])->toBeEmpty();

    $this->actingAs($this->faculty)
        ->get(route('faculty.proposal-drafts.gad-checklist.show', $draft))
        ->assertOk()
        ->assertSee('No form fields to answer')
        ->assertSee('Auto-filled from shared project information')
        ->assertSee('Coastal Habitat Restoration')
        ->assertSee('Faculty Owner')
        ->assertSee('There are no answers to enter')
        ->assertDontSee('Mark paper ready')
        ->assertDontSee('data-paper-shortcuts-trigger', false)
        ->assertDontSee('data-paper-editor', false)
        ->assertDontSee('data-paper-save-exit', false)
        ->assertDontSee('data-paper-save', false)
        ->assertDontSee('name="project_title"', false)
        ->assertDontSee('name="project_leader"', false);

    $preview = $this->actingAs($this->faculty)
        ->get(route('faculty.proposal-drafts.gad-checklist.preview', $draft))
        ->assertOk()
        ->assertSee('Coastal Habitat Restoration')
        ->assertSee('Faculty Owner')
        ->assertSee('TOTAL GAD SCORE FOR PROJECT DEVELOPMENT STAGE')
        ->assertDontSee('12.32')
        ->assertDontSee('<td class="gad-mark">', false)
        ->assertSee('Guide for accomplishing Box 7a');

    expect(substr_count($preview->getContent(), 'aria-label="Box 7a GAD Generic Checklist page'))->toBe(7);

    $download = $this->actingAs($this->faculty)
        ->get(route('faculty.proposal-drafts.gad-checklist.download', $draft))
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
            ->toContain('TOTAL GAD SCORE FOR THE PROJECT IDENTIFICATION AND DESIGN STAGES')
            ->not->toContain('12.32')
            ->not->toContain('7.32')
            ->not->toContain('0.99')
            ->not->toContain('1.33');

        $documentXpath = new DOMXPath($documentDom);
        $documentXpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $projectLeaderSignature = $documentXpath->query('//w:p[normalize-space(.) = "Faculty Owner"]')->item(0);

        $answerMarks = 0;

        foreach ($documentXpath->query('//w:t') as $textNode) {
            if (in_array(trim($textNode->textContent), ['X', 'x'], true)) {
                $answerMarks++;
            }
        }

        expect($answerMarks)->toBe(0)
            ->and($documentXpath->query('./w:r/w:rPr/w:u[@w:val = "single"]', $projectLeaderSignature)->length)->toBe(1);

        $gadFooterXml = $generated->getFromName('word/footer1.xml');
        $gadSettingsXml = $generated->getFromName('word/settings.xml');
        $gadFooterDom = new DOMDocument;
        $gadFooterDom->loadXML($gadFooterXml, LIBXML_NONET);
        $gadFooterXPath = new DOMXPath($gadFooterDom);
        $gadFooterXPath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $gadSettingsDom = new DOMDocument;
        $gadSettingsDom->loadXML($gadSettingsXml, LIBXML_NONET);
        $gadSettingsXPath = new DOMXPath($gadSettingsDom);
        $gadSettingsXPath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        expect($gadFooterXPath->query('//w:instrText[starts-with(normalize-space(.), "PAGE")]')->length)->toBe(1)
            ->and($gadSettingsXPath->query('/w:settings/w:updateFields[@w:val = "true"]')->length)->toBe(1);

        foreach (['word/footer1.xml', 'word/footnotes.xml', 'word/numbering.xml', 'word/styles.xml', 'word/media/image1.png'] as $preservedPart) {
            expect($generated->getFromName($preservedPart))->toBe($template->getFromName($preservedPart));
        }
    } finally {
        $generated->close();
        $template->close();
        unlink($temporaryPath);
    }

    expect($draft->documents()
        ->where('document_type', ProposalVersionFile::TYPE_GAD_CHECKLIST)
        ->count())->toBe(0);
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
        ->assertSee('The Research Head handles any evaluation outside the system')
        ->assertDontSee('data-paper-shortcuts-trigger', false)
        ->assertDontSee('data-paper-editor', false)
        ->assertDontSee('data-paper-save', false)
        ->assertDontSee('name="project_title"', false)
        ->assertDontSee('name="project_leader"', false);

    $this->actingAs($this->faculty)
        ->get(route('faculty.proposal-drafts.initial-screening-form.preview', $draft))
        ->assertOk()
        ->assertSee('BatStateU Initial Screening Form')
        ->assertSee('Coastal Habitat Restoration')
        ->assertSee('Faculty Owner');

    $previewCss = file_get_contents(resource_path('css/initial-screening-form-print.css'));

    expect($previewCss)
        ->toMatch('/\.initial-screening-project-title\s*\{[^}]*left:\s*2\.35in;/s')
        ->toMatch('/\.initial-screening-project-leader\s*\{[^}]*left:\s*1\.8in;/s');

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

        $documentXPath = new DOMXPath($documentDom);
        $documentXPath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        expect($documentXPath->query('//w:p[contains(string(.), "First Submission")]//w:checkBox/w:checked[@w:val = "1"]')->length)->toBe(1)
            ->and($documentXPath->query('//w:p[contains(string(.), "Revised with Minor Changes")]//w:checkBox/w:checked[@w:val = "1"]')->length)->toBe(0)
            ->and($documentXPath->query('//w:p[contains(string(.), "Revised with Major Changes")]//w:checkBox/w:checked[@w:val = "1"]')->length)->toBe(0)
            ->and($documentXPath->query('//w:p[normalize-space(.) = "NAME"]/w:r/w:rPr/w:u[@w:val = "single"]')->length)->toBe(3)
            ->and($documentXPath->query('//w:p[normalize-space(.) = "NAME"]/preceding-sibling::w:p[1][not(normalize-space(.))]')->length)->toBe(3)
            ->and($draft->fresh()->topic_id)->toBeNull()
            ->and(app(InitialScreeningSubmissionOrder::class)->forDraft($draft->fresh()))->toBe(InitialScreeningSubmissionOrder::FIRST_SUBMISSION);

        $footerXml = $generated->getFromName('word/footer1.xml');
        $settingsXml = $generated->getFromName('word/settings.xml');
        $footerDom = new DOMDocument;
        $footerDom->loadXML($footerXml, LIBXML_NONET);
        $footerXPath = new DOMXPath($footerDom);
        $footerXPath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $settingsDom = new DOMDocument;
        $settingsDom->loadXML($settingsXml, LIBXML_NONET);
        $settingsXPath = new DOMXPath($settingsDom);
        $settingsXPath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        expect($footerXPath->query('//w:instrText[normalize-space(.) = "PAGE"]')->length)->toBe(1)
            ->and($footerXPath->query('//w:instrText[normalize-space(.) = "NUMPAGES"]')->length)->toBe(1)
            ->and($settingsXPath->query('/w:settings/w:updateFields[@w:val = "true"]')->length)->toBe(1);

        for ($index = 0; $index < $template->numFiles; $index++) {
            $entry = $template->statIndex($index);
            $name = $entry['name'];

            if (! in_array($name, ['word/document.xml', 'word/settings.xml'], true)) {
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
    config()->set('proposal_papers.expense-breakdown.mode', 'upload');
    config()->set('proposal_papers.expense-breakdown.accepted_extensions', ['pdf']);
    config()->set('proposal_papers.expense-breakdown.accepted_mime_types', ['application/pdf']);
    config()->set('proposal_papers.expense-breakdown.max_kilobytes', 25600);
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
        ->delete(route('faculty.proposal-drafts.papers.remove', [$draft, 'expense-breakdown', $replacement]), [
            'document_version' => 2,
        ])
        ->assertRedirect();

    $this->assertDatabaseMissing('proposal_draft_documents', ['id' => $replacement->id]);
    Storage::disk('local')->assertExists($replacement->file_path);
});

test('paper uploads enforce file types and the 25 MB limit', function () {
    config()->set('proposal_papers.expense-breakdown.mode', 'upload');
    config()->set('proposal_papers.expense-breakdown.accepted_extensions', ['pdf']);
    config()->set('proposal_papers.expense-breakdown.accepted_mime_types', ['application/pdf']);
    config()->set('proposal_papers.expense-breakdown.max_kilobytes', 25600);
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

test('valid saved detailed proposal content is complete even when a legacy completion flag is missing', function () {
    $draft = ($this->createDraft)();
    $draft->update(($this->projectDetails)());
    $draft->documents()->create([
        'document_type' => ProposalVersionFile::TYPE_DETAILED_PROPOSAL,
        'position' => 0,
        'source_data' => ($this->detailedProposal)(),
        'completed_at' => null,
    ]);

    $item = app(ProposalDraftReadiness::class)
        ->checklist($draft->fresh(['documents']))
        ->get('detailed-proposal');

    expect($item['complete'])->toBeTrue()
        ->and($item['status'])->toBe('Complete');

    $this->actingAs($this->faculty)
        ->get(route('faculty.proposal-drafts.show', $draft))
        ->assertOk()
        ->assertSee('Detailed Research Proposal')
        ->assertSee('Complete');
});

test('generated papers can save partial source data as in-progress drafts', function () {
    $draft = ($this->createDraft)();
    $draft->update(($this->projectDetails)());

    $draftSaves = [
        [
            'route' => 'faculty.proposal-drafts.detailed-proposal.update',
            'document_type' => ProposalVersionFile::TYPE_DETAILED_PROPOSAL,
            'message' => 'Detailed Research Proposal saved as a draft.',
            'payload' => [
                'document_version' => 0,
                'save_as_draft' => '1',
                'research_agenda' => 'Environment and Climate Change',
            ],
        ],
        [
            'route' => 'faculty.proposal-drafts.work-plan.update',
            'document_type' => ProposalVersionFile::TYPE_WORK_PLAN,
            'message' => 'Attachment A: Work Plan saved as a draft.',
            'payload' => [
                'document_version' => 0,
                'save_as_draft' => '1',
                'entries' => [[
                    'objective' => 'Start the first objective',
                    'expected_output' => '',
                    'activity' => '',
                    'months' => [],
                ]],
            ],
        ],
        [
            'route' => 'faculty.proposal-drafts.line-item-budget.update',
            'document_type' => ProposalVersionFile::TYPE_LINE_ITEM_BUDGET,
            'message' => 'Attachment B: Line-Item Budget saved as a draft.',
            'payload' => [
                'document_version' => 0,
                'save_as_draft' => '1',
                'leader_college' => 'College of Arts and Sciences',
            ],
        ],
        [
            'route' => 'faculty.proposal-drafts.expense-breakdown.update',
            'document_type' => ProposalVersionFile::TYPE_EXPENSE_BREAKDOWN,
            'message' => 'Estimated Expense Breakdown saved as a draft.',
            'payload' => [
                'document_version' => 0,
                'save_as_draft' => '1',
                'items' => [[
                    'category' => 'mooe',
                    'account' => '',
                    'sub_account' => '',
                    'particulars' => '',
                    'details' => '',
                    'purpose' => '',
                    'unit' => '',
                    'quantity' => '',
                    'unit_cost' => '',
                ]],
            ],
        ],
        [
            'route' => 'faculty.proposal-drafts.curriculum-vitae.update',
            'document_type' => ProposalVersionFile::TYPE_CURRICULUM_VITAE,
            'message' => 'Attachment C: Curriculum Vitae saved as a draft.',
            'payload' => [
                'document_version' => 0,
                'save_as_draft' => '1',
                'people' => [[
                    'last_name' => 'Owner',
                ]],
            ],
        ],
    ];

    foreach ($draftSaves as $draftSave) {
        $this->actingAs($this->faculty)
            ->put(route($draftSave['route'], $draft), [
                ...$draftSave['payload'],
                'exit_after_save' => '1',
            ])
            ->assertRedirect(route('faculty.proposal-drafts.show', $draft))
            ->assertSessionHas('success', $draftSave['message']);

        $document = $draft->documents()
            ->where('document_type', $draftSave['document_type'])
            ->sole();

        expect($document->completed_at)->toBeNull()
            ->and($document->source_data)->not->toBeEmpty();
    }

    $draft->refresh();

    foreach ($draftSaves as $draftSave) {
        expect(app(ProposalDraftReadiness::class)
            ->checklist($draft)
            ->get(collect(app(ProposalPaperCatalog::class)->all())
                ->firstWhere('document_type', $draftSave['document_type'])['slug'])['status'])
            ->toBe('In progress');
    }

    expect(ProposalDraftDocumentVersion::query()
        ->where('document_type', ProposalVersionFile::TYPE_WORK_PLAN)
        ->latest('version_number')
        ->value('change_summary'))
        ->toBe('Saved Attachment A: Work Plan as a draft.');

    $this->actingAs($this->faculty)
        ->postJson(route('faculty.proposal-drafts.work-plan.preview', $draft), [
            'entries' => [[
                'objective' => 'Partial objective',
                'expected_output' => '',
                'activity' => '',
                'months' => [],
            ]],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'entries.0.expected_output',
            'entries.0.activity',
            'entries.0.months',
        ]);
});

test('incomplete drafts stay blocked but completed drafts can be submitted after a call closes', function () {
    $incomplete = ($this->createDraft)();
    $this->actingAs($this->faculty)->post(route('faculty.proposal-drafts.submit', $incomplete))
        ->assertSessionHasErrors(['project_details', 'papers.detailed-proposal']);
    expect($incomplete->fresh())->not->toBeNull();
    $complete = ($this->completeDraft)(($this->createDraft)(['project_title' => 'Anytime Complete']));
    $this->call->update(['closes_at' => now()->subSecond()]);
    $this->post(route('faculty.proposal-drafts.submit', $complete))
        ->assertRedirect(route('faculty.dashboard'))->assertSessionHasNoErrors();
    expect(TopicProposal::query()->sole()->research_call_id)->toBe($this->call->id);
});

test('budget mismatches are identified in the interface and prevent final submission', function () {
    $draft = ($this->completeDraft)(($this->createDraft)());
    $lineItemBudget = $draft->documents
        ->firstWhere('document_type', ProposalVersionFile::TYPE_LINE_ITEM_BUDGET);
    $lineItemBudget->update([
        'source_data' => [
            'amounts' => [
                'telephone_expenses' => 4200,
            ],
            'custom_mooe_items' => [[
                'particular' => 'Attachment B-only adjustment',
                'amount' => 600,
            ]],
        ],
    ]);

    $this->actingAs($this->faculty)
        ->get(route('faculty.proposal-drafts.show', $draft))
        ->assertOk()
        ->assertSee('Budget totals do not match')
        ->assertSee('Submission blocked')
        ->assertSee('5 of 7 required PDF attachments ready')
        ->assertSeeTextInOrder([
            'Attachment B: Line-Item Budget',
            'Needs attention',
            'Estimated Expense Breakdown',
            'Needs attention',
        ])
        ->assertSee('MOOE')
        ->assertSee('Total Project Cost')
        ->assertSee('Php 4,200.00')
        ->assertSee('Php 3,600.00');

    $this->actingAs($this->faculty)
        ->get(route('faculty.proposal-drafts.line-item-budget.edit', $draft))
        ->assertOk()
        ->assertSee('Budget totals do not match')
        ->assertSee('Needs attention');

    $this->actingAs($this->faculty)
        ->get(route('faculty.proposal-drafts.expense-breakdown.edit', $draft))
        ->assertOk()
        ->assertSee('Budget totals do not match')
        ->assertSee('Needs attention');

    $checklist = app(ProposalDraftReadiness::class)->checklist($draft->fresh());

    expect($checklist['line-item-budget']['complete'])->toBeTrue()
        ->and($checklist['line-item-budget']['needs_attention'])->toBeTrue()
        ->and($checklist['line-item-budget']['status'])->toBe('Needs attention')
        ->and($checklist['expense-breakdown']['complete'])->toBeTrue()
        ->and($checklist['expense-breakdown']['needs_attention'])->toBeTrue()
        ->and($checklist['expense-breakdown']['status'])->toBe('Needs attention');

    $this->actingAs($this->faculty)
        ->post(route('faculty.proposal-drafts.submit', $draft))
        ->assertSessionHasErrors([
            'budget_consistency.mooe_total',
            'budget_consistency.project_total',
        ]);

    expect(ProposalDraft::find($draft->id))->not->toBeNull()
        ->and(TopicProposal::query()->count())->toBe(0);

    $lineItemBudget->update([
        'source_data' => [
            'amounts' => [
                'telephone_expenses' => 3600,
            ],
            'custom_mooe_items' => [],
        ],
    ]);
    $resolvedChecklist = app(ProposalDraftReadiness::class)->checklist($draft->fresh());

    expect($resolvedChecklist['line-item-budget']['complete'])->toBeTrue()
        ->and($resolvedChecklist['line-item-budget']['needs_attention'])->toBeFalse()
        ->and($resolvedChecklist['line-item-budget']['status'])->toBe('Complete')
        ->and($resolvedChecklist['expense-breakdown']['needs_attention'])->toBeFalse()
        ->and($resolvedChecklist['expense-breakdown']['status'])->toBe('Complete');

    $this->actingAs($this->faculty)
        ->get(route('faculty.proposal-drafts.show', $draft))
        ->assertOk()
        ->assertDontSee('Budget totals do not match')
        ->assertDontSee('Needs attention');
});

test('a PDF conversion failure keeps the complete draft available for another preparation attempt', function () {
    $draft = ($this->completeDraft)(($this->createDraft)());
    $draft->documents()->update([
        'file_path' => null,
        'original_filename' => null,
        'mime_type' => null,
        'file_size' => null,
        'checksum' => null,
    ]);
    app()->instance(DocumentPdfConverter::class, new class implements DocumentPdfConverter
    {
        public function convertDocx(string $contents): string
        {
            throw new RuntimeException('LibreOffice is unavailable.');
        }

        public function convertXlsx(string $contents): string
        {
            throw new RuntimeException('LibreOffice is unavailable.');
        }
    });

    $this->actingAs($this->faculty)
        ->post(route('faculty.proposal-drafts.submission-files.prepare', $draft))
        ->assertRedirect()
        ->assertSessionHasErrors('preparation');

    expect(ProposalDraft::find($draft->id))->not->toBeNull()
        ->and(TopicProposal::query()->count())->toBe(0);
    expect(Storage::disk('local')->allFiles($draft->storageDirectory()))->not->toBeEmpty();
});

test('submission PDFs can be prepared from expense items containing previously computed fields', function () {
    $expenseBreakdown = ($this->expenseBreakdown)();
    $expenseBreakdown['items'][0]['total_cost'] = 3600;
    $expenseBreakdown['items'][0]['is_contingency'] = false;
    $this->expenseBreakdown = fn (): array => $expenseBreakdown;

    $draft = ($this->completeDraft)(($this->createDraft)());
    $savedExpenseBreakdown = $draft->documents
        ->firstWhere('document_type', ProposalVersionFile::TYPE_EXPENSE_BREAKDOWN);

    expect(app(ProposalDraftReadiness::class)->submissionFilesArePrepared($draft))->toBeTrue()
        ->and($savedExpenseBreakdown->source_data['items'][0])->not->toHaveKeys([
            'total_cost',
            'is_contingency',
        ]);
});

test('submission PDFs can be prepared from CV values using the official display format', function () {
    $draft = ($this->completeDraft)(($this->createDraft)());
    $curriculumVitae = $draft->documents
        ->firstWhere('document_type', ProposalVersionFile::TYPE_CURRICULUM_VITAE);
    $sourceData = $curriculumVitae->source_data;
    $sourceData['people'][0]['gender'] = 'Female';
    $sourceData['people'][0]['birthday'] = '06/12/1995';

    app(SaveProposalDraftDocument::class)->handle(
        $draft,
        $this->faculty,
        ProposalVersionFile::TYPE_CURRICULUM_VITAE,
        0,
        $curriculumVitae->lock_version,
        [
            'source_data' => $sourceData,
            'completed_at' => now(),
        ],
    );

    $this->actingAs($this->faculty)
        ->post(route('faculty.proposal-drafts.submission-files.prepare', $draft))
        ->assertRedirect(route('faculty.proposal-drafts.show', $draft))
        ->assertSessionHasNoErrors();

    $savedCurriculumVitae = $curriculumVitae->fresh();

    expect(app(ProposalDraftReadiness::class)->submissionFilesArePrepared($draft->fresh()))->toBeTrue()
        ->and($savedCurriculumVitae->source_data['people'][0]['gender'])->toBe('female')
        ->and($savedCurriculumVitae->source_data['people'][0]['birthday'])->toBe('1995-06-12');
});

test('Livewire prepares the PDF package without leaving the review modal', function () {
    $draft = ($this->completeDraft)(($this->createDraft)());
    $draft->documents()->update([
        'file_path' => null,
        'original_filename' => null,
        'mime_type' => null,
        'file_size' => null,
        'checksum' => null,
    ]);

    Livewire::actingAs($this->faculty)
        ->test(ProposalDraftReviewPackage::class, [
            'proposalDraft' => $draft,
            'inModal' => true,
        ])
        ->assertSee('Prepare seven PDFs')
        ->call('prepare')
        ->assertHasNoErrors()
        ->assertNoRedirect()
        ->assertSet('statusMessage', 'Seven PDF attachments prepared. Review or replace them before turning in.')
        ->assertSee('PDF package prepared')
        ->assertSee('Turn in proposal');

    expect(app(ProposalDraftReadiness::class)->submissionFilesArePrepared($draft->fresh()))->toBeTrue();
});

test('Livewire turns in a prepared package and navigates to the dashboard', function () {
    Notification::fake();
    $draft = ($this->completeDraft)(($this->createDraft)());

    Livewire::actingAs($this->faculty)
        ->test(ProposalDraftReviewPackage::class, ['proposalDraft' => $draft])
        ->assertSee('Turn in proposal')
        ->call('turnIn')
        ->assertHasNoErrors()
        ->assertRedirect(route('faculty.dashboard'));

    expect(ProposalDraft::find($draft->id))->toBeNull()
        ->and(TopicProposal::query()->count())->toBe(1);
});

test('Turn in is blocked until the complete proposal has a prepared PDF package', function () {
    $draft = ($this->completeDraft)(($this->createDraft)());
    $draft->documents()->update([
        'file_path' => null,
        'original_filename' => null,
        'mime_type' => null,
        'file_size' => null,
        'checksum' => null,
    ]);

    $this->actingAs($this->faculty)
        ->post(route('faculty.proposal-drafts.submit', $draft))
        ->assertRedirect()
        ->assertSessionHasErrors('submission_files');

    expect(ProposalDraft::find($draft->id))->not->toBeNull()
        ->and(TopicProposal::query()->count())->toBe(0);
});

test('faculty prepares reviews replaces and refreshes the seven submission PDFs before Turn in', function () {
    $draft = ($this->completeDraft)(($this->createDraft)());

    expect($draft->documents)->toHaveCount(7)
        ->and($draft->documents->every(fn ($document): bool => $document->mime_type === 'application/pdf'))
        ->toBeTrue()
        ->and(app(ProposalDraftReadiness::class)->submissionFilesArePrepared($draft))->toBeTrue();

    $this->actingAs($this->faculty)
        ->get(route('faculty.proposal-drafts.show', $draft))
        ->assertOk()
        ->assertSee('Prepared PDF ready')
        ->assertSee('Download prepared PDF')
        ->assertSee('Choose replacement PDF')
        ->assertSee('The seven reviewed PDFs are ready');

    $workspace = $this->actingAs($this->faculty)
        ->get(route('faculty.proposal-drafts.show', $draft));

    expect(substr_count($workspace->getContent(), 'Choose replacement PDF'))->toBe(7);

    $expenseBreakdown = $draft->documents
        ->firstWhere('document_type', ProposalVersionFile::TYPE_EXPENSE_BREAKDOWN);

    $this->actingAs($this->faculty)
        ->get(route('faculty.proposal-drafts.submission-files.download', [$draft, 'expense-breakdown']))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertDownload($expenseBreakdown->original_filename);

    $detailedProposal = $draft->documents
        ->firstWhere('document_type', ProposalVersionFile::TYPE_DETAILED_PROPOSAL);
    $replacement = UploadedFile::fake()->createWithContent(
        'faculty-corrected-proposal.pdf',
        "%PDF-1.7\nfaculty corrected contents",
    );

    $this->actingAs($this->faculty)
        ->put(route('faculty.proposal-drafts.submission-files.replace', [$draft, 'detailed-proposal']), [
            'document_version' => $detailedProposal->lock_version,
            'file' => $replacement,
        ])
        ->assertRedirect(route('faculty.proposal-drafts.show', $draft))
        ->assertSessionHasNoErrors();

    $detailedProposal->refresh();

    expect($detailedProposal->original_filename)->toBe('faculty-corrected-proposal.pdf')
        ->and($detailedProposal->mime_type)->toBe('application/pdf')
        ->and(Storage::disk('local')->get($detailedProposal->file_path))->toContain('faculty corrected contents')
        ->and(app(ProposalDraftReadiness::class)->submissionFilesArePrepared($draft->fresh()))->toBeTrue();

    $updatedSource = $expenseBreakdown->source_data;
    $updatedSource['items'][0]['purpose'] = 'Updated purpose after reviewing the prepared PDF.';
    $updatedSource['items'][0]['unit_cost'] = 500;
    app(SaveProposalDraftDocument::class)->handle(
        $draft,
        $this->faculty,
        ProposalVersionFile::TYPE_EXPENSE_BREAKDOWN,
        0,
        $expenseBreakdown->lock_version,
        [
            'source_data' => $updatedSource,
            'completed_at' => now(),
        ],
    );

    expect(app(ProposalDraftReadiness::class)->submissionFilesArePrepared($draft->fresh()))->toBeFalse()
        ->and($draft->documents()->whereNotNull('file_path')->count())->toBe(0);

    $this->actingAs($this->faculty)
        ->post(route('faculty.proposal-drafts.submission-files.prepare', $draft))
        ->assertRedirect(route('faculty.proposal-drafts.show', $draft))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'Seven PDF attachments prepared. Review or replace them before turning in.');

    $refreshedLineItemBudget = $draft->documents()
        ->where('document_type', ProposalVersionFile::TYPE_LINE_ITEM_BUDGET)
        ->sole();

    expect(app(ProposalDraftReadiness::class)->submissionFilesArePrepared($draft->fresh()))->toBeTrue()
        ->and($refreshedLineItemBudget->source_data['amounts']['telephone_expenses'])->toEqual(6000.0)
        ->and($refreshedLineItemBudget->source_data['co_total'])->toEqual(0.0)
        ->and($refreshedLineItemBudget->source_data['project_total'])->toEqual(6000.0);
});

test('a prepared third proposal remains a draft until a submission slot becomes available', function () {
    Notification::fake();
    $draft = ($this->completeDraft)(($this->createDraft)([
        'project_title' => 'Third Coastal Research Proposal',
    ]));
    $firstProposal = TopicProposal::create([
        'user_id' => $this->faculty->id,
        'research_call_id' => $this->call->id,
        'title' => 'First Submitted Proposal',
        'status' => 'pending',
    ]);
    TopicProposal::create([
        'user_id' => $this->faculty->id,
        'research_call_id' => $this->call->id,
        'title' => 'Second Submitted Proposal',
        'status' => 'revision_requested',
    ]);
    $stagedPaths = $draft->documents->pluck('file_path')->filter()->values();

    $this->actingAs($this->faculty)
        ->post(route('faculty.proposal-drafts.submit', $draft))
        ->assertRedirect()
        ->assertSessionHasErrors([
            'status' => 'Submission cannot continue. Faculty Owner is already participating in the maximum of 2 submitted or active research projects. A slot becomes available when a proposal is rejected or an approved project is completed.',
        ]);

    expect($draft->fresh()->status)->toBe(ProposalDraft::STATUS_DRAFT)
        ->and(TopicProposal::query()->count())->toBe(2);
    $stagedPaths->each(fn (string $path) => Storage::disk('local')->assertExists($path));

    $firstProposal->update(['status' => 'rejected']);

    $this->actingAs($this->faculty)
        ->post(route('faculty.proposal-drafts.submit', $draft))
        ->assertRedirect(route('faculty.dashboard'))
        ->assertSessionHasNoErrors();

    expect(TopicProposal::query()->where('status', 'pending')->count())->toBe(1)
        ->and(ProposalDraft::query()->whereKey($draft->id)->exists())->toBeFalse();
});

test('final submission creates one immutable package then rejects a duplicate request', function () {
    Notification::fake();
    $draft = ($this->completeDraft)(($this->createDraft)());
    $draft->members()->create([
        'user_id' => $this->otherFaculty->id,
        'name' => $this->otherFaculty->name,
        'email' => $this->otherFaculty->email,
        'accepted_at' => now(),
    ]);
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
    $lineItemBudget = $version->files->firstWhere('document_type', ProposalVersionFile::TYPE_LINE_ITEM_BUDGET);
    $expenseBreakdown = $version->files->firstWhere('document_type', ProposalVersionFile::TYPE_EXPENSE_BREAKDOWN);
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
        ->and($lineItemBudget->source_data['mooe_total'])->toBe(3600)
        ->and($lineItemBudget->source_data['co_total'])->toBe(0)
        ->and($lineItemBudget->source_data['project_total'])->toBe(3600)
        ->and($expenseBreakdown->source_data['project_title'])->toBe('Coastal Habitat Restoration')
        ->and($expenseBreakdown->source_data['items'][0]['account'])->toBe('Communication Expenses')
        ->and($expenseBreakdown->mime_type)->toBe('application/pdf')
        ->and($expenseBreakdown->original_filename)->toBe('coastal-habitat-restoration-estimated-expense-breakdown.pdf')
        ->and($gadChecklist->source_data['project_title'])->toBe('Coastal Habitat Restoration')
        ->and($gadChecklist->source_data['project_leader'])->toBe('Faculty Owner')
        ->and($initialScreeningForm->source_data['project_title'])->toBe('Coastal Habitat Restoration')
        ->and($initialScreeningForm->source_data['project_leader'])->toBe('Faculty Owner');

    expect($topic->collaborators()->sole()->user_id)->toBe($this->otherFaculty->id);

    $version->files->each(function (ProposalVersionFile $file): void {
        Storage::disk('local')->assertExists($file->file_path);

        expect(Storage::disk('local')->get($file->file_path))->toStartWith('%PDF-');
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

    expect($archivedHistory)->toHaveCount(14)
        ->and($archivedHistory->every(fn (ProposalDraftDocumentVersion $history): bool => $history->proposal_draft_id === null))->toBeTrue()
        ->and($archivedHistory->every(fn (ProposalDraftDocumentVersion $history): bool => $history->proposal_draft_document_id === null))->toBeTrue()
        ->and($archivedHistory->every(fn (ProposalDraftDocumentVersion $history): bool => $history->is_current === false))->toBeTrue()
        ->and($archivedFileVersion)->not->toBeNull()
        ->and($archivedHistory->where('action', ProposalDraftDocumentVersion::ACTION_SUBMITTED))->toHaveCount(7);

    $this->actingAs($this->faculty)
        ->get(route('topics.show', $topic))
        ->assertOk()
        ->assertSee('Draft history (14)');
    $this->actingAs($this->faculty)
        ->get(route('topics.draft-history.index', $topic))
        ->assertOk()
        ->assertSee('Submitted draft record')
        ->assertSee('Ready for Turn in.');
    $this->actingAs($this->head)
        ->get(route('topics.draft-history.index', $topic))
        ->assertOk();
    $this->actingAs($this->otherFaculty)
        ->get(route('topics.draft-history.index', $topic))
        ->assertOk();

    Notification::assertSentToTimes($this->head, ProposalActivityNotification::class, 1);
    Notification::assertSentTo(
        $this->otherFaculty,
        ProposalActivityNotification::class,
        fn (ProposalActivityNotification $notification): bool => $notification->title === 'Proposal submitted for review'
            && $notification->workspace === null,
    );

    $this->actingAs($this->faculty)
        ->post(route('faculty.proposal-drafts.submit', $draftId))
        ->assertNotFound();

    expect(TopicProposal::query()->count())->toBe(1)
        ->and($topic->versions()->count())->toBe(1);
    Notification::assertSentToTimes($this->head, ProposalActivityNotification::class, 1);
});

test('an rrl backed proposal completes submission revision approval notice and monitoring', function () {
    Notification::fake();
    $draft = ($this->completeDraft)(($this->createDraft)([
        'project_title' => 'Community Mangrove Monitoring Lifecycle',
    ]));
    $reviewedRrlParagraph = 'Santos and Cruz (2024) examined community participation in mangrove monitoring and found that sustained local involvement supported more consistent environmental observation. Their findings indicate that community participation may strengthen monitoring continuity and local stewardship.';

    $sourceId = $this->actingAs($this->faculty)
        ->postJson(route('research-support.literature-library.store'), [
            'title' => 'Community Participation in Mangrove Monitoring',
            'description' => 'This study examined how community participation influenced the continuity of mangrove monitoring activities and local stewardship.',
            'authors' => 'Maria Santos, Luis Cruz',
            'year' => 2024,
            'venue' => 'Journal of Coastal Research',
            'doi' => '10.1234/mangrove.lifecycle',
            'url' => 'https://doi.org/10.1234/mangrove.lifecycle',
            'source' => 'OpenAlex',
            'citation_count' => 18,
            'is_open_access' => false,
            'type' => 'article',
        ])
        ->assertCreated()
        ->json('source.id');
    $source = LiteratureSource::query()->findOrFail($sourceId);
    $proposalSourceId = $this->actingAs($this->faculty)
        ->postJson(route('faculty.proposal-drafts.literature-sources.store', [$draft, $source]), [
            'rrl_note' => $reviewedRrlParagraph,
        ])
        ->assertCreated()
        ->assertJsonPath('source.rrl_note', $reviewedRrlParagraph)
        ->json('source.id');

    $this->actingAs($this->faculty)
        ->get(route('faculty.proposal-drafts.detailed-proposal.edit', [
            'proposalDraft' => $draft,
            'literature_source' => $proposalSourceId,
            'apply_to' => 'both',
        ]))
        ->assertOk()
        ->assertViewHas('initialLiteratureSourceId', $proposalSourceId)
        ->assertViewHas('initialLiteratureAction', 'both');

    $detailedProposalDocument = $draft->documents()
        ->where('document_type', ProposalVersionFile::TYPE_DETAILED_PROPOSAL)
        ->sole();
    $reference = $draft->literatureSources()->findOrFail($proposalSourceId)->referenceDraft();
    $detailedProposalPayload = [
        ...$detailedProposalDocument->source_data,
        'project_leader' => $draft->project_leader,
        'document_version' => $detailedProposalDocument->lock_version,
        'draft_version' => $draft->lock_version,
        'related_literature' => $detailedProposalDocument->source_data['related_literature']."\n\n".$reviewedRrlParagraph,
        'references' => $detailedProposalDocument->source_data['references']."\n".$reference,
        'change_note' => 'Added a reviewed source from the shared literature library.',
    ];

    $this->actingAs($this->faculty)
        ->put(route('faculty.proposal-drafts.detailed-proposal.update', $draft), $detailedProposalPayload)
        ->assertRedirect(route('faculty.proposal-drafts.detailed-proposal.edit', $draft))
        ->assertSessionHasNoErrors();

    app(SubmitProposalDraft::class)->prepare($draft->fresh(), $this->faculty);

    $this->actingAs($this->faculty)
        ->post(route('faculty.proposal-drafts.submit', $draft))
        ->assertRedirect(route('faculty.dashboard'));

    $topic = TopicProposal::query()->sole();
    $initialVersion = $topic->versions()->with('files')->sole();
    $submittedDetailedProposal = $initialVersion->files
        ->firstWhere('document_type', ProposalVersionFile::TYPE_DETAILED_PROPOSAL);

    expect($topic->status)->toBe('pending')
        ->and($submittedDetailedProposal->source_data['related_literature'])->toContain($reviewedRrlParagraph)
        ->and($submittedDetailedProposal->source_data['references'])->toContain('10.1234/mangrove.lifecycle');
    Notification::assertSentTo(
        $this->head,
        ProposalActivityNotification::class,
        fn (ProposalActivityNotification $notification): bool => $notification->title === 'New proposal submitted',
    );

    $annotation = $submittedDetailedProposal->annotations()->create([
        'reviewer_id' => $this->head->id,
        'annotation_type' => ProposalFileAnnotation::TYPE_AREA,
        'page_number' => 1,
        'rectangles' => [['x' => 0.15, 'y' => 0.25, 'width' => 0.45, 'height' => 0.08]],
        'comment' => 'Clarify how the reviewed literature supports the monitoring method.',
    ]);

    $this->actingAs($this->head)
        ->patch(route('research_head.topics.updateStatus', $topic), [
            'status' => 'revision_requested',
            'comment' => 'Clarify the connection between the RRL and the proposed monitoring method.',
            'revision_file_ids' => [$submittedDetailedProposal->id],
            'revision_file_notes' => [
                $submittedDetailedProposal->id => 'Revise the detailed proposal while retaining the verified source.',
            ],
            'evaluation_document' => UploadedFile::fake()->create('initial-evaluation.pdf', 100, 'application/pdf'),
        ])
        ->assertRedirect(route('research_head.dashboard'))
        ->assertSessionHasNoErrors();

    expect($topic->fresh()->status)->toBe('revision_requested')
        ->and($annotation->fresh()->topic_review_file_revision_id)->not->toBeNull();
    Notification::assertSentTo(
        $this->faculty,
        ProposalActivityNotification::class,
        fn (ProposalActivityNotification $notification): bool => $notification->title === 'Revision requested',
    );

    $this->actingAs($this->faculty)
        ->patch(route('faculty.topics.resubmit', $topic), [
            'title' => $topic->title,
            'description' => 'Revised to connect the reviewed literature to the monitoring methodology.',
            'estimated_budget' => $topic->estimated_budget ?: 3600,
            'estimated_duration_months' => $topic->estimated_duration_months,
            'change_summary' => 'Clarified the RRL-to-methodology connection while retaining the verified source.',
            'detailed_proposal' => UploadedFile::fake()->create('detailed-proposal-v2.pdf', 120, 'application/pdf'),
        ])
        ->assertRedirect(route('faculty.dashboard'))
        ->assertSessionHasNoErrors();

    expect($topic->fresh()->status)->toBe('resubmitted')
        ->and($topic->versions()->count())->toBe(2)
        ->and($topic->reviews()->latest()->firstOrFail()->fileRevisions()->sole()->resolved_at)->not->toBeNull();
    Notification::assertSentTo(
        $this->head,
        ProposalActivityNotification::class,
        fn (ProposalActivityNotification $notification): bool => $notification->title === 'Proposal revision submitted',
    );

    $revisedVersion = $topic->latestVersion()->with('files')->firstOrFail();
    $revisedDetailedProposal = $revisedVersion->files
        ->firstWhere('document_type', ProposalVersionFile::TYPE_DETAILED_PROPOSAL);

    $this->actingAs($this->head)
        ->patch(route('research_head.topics.updateStatus', $topic), [
            'status' => TopicProposal::STATUS_READY_FOR_SIGNATURE,
            'signature_file_ids' => [$revisedDetailedProposal->id],
            'evaluation_document' => UploadedFile::fake()->create('final-evaluation.pdf', 100, 'application/pdf'),
        ])
        ->assertRedirect(route('research_head.dashboard'))
        ->assertSessionHasNoErrors();

    $this->actingAs($this->head)
        ->post(route('topics.head-uploads.store', $topic), [
            'source_file_id' => $revisedDetailedProposal->id,
            'review_file' => UploadedFile::fake()->create('signed-detailed-proposal.pdf', 100, 'application/pdf'),
            'purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SIGNED,
        ])
        ->assertRedirect(route('topics.show', $topic).'#proposal-review')
        ->assertSessionHasNoErrors();

    $this->actingAs($this->head)
        ->patch(route('research_head.topics.finalizeApproval', $topic))
        ->assertRedirect(route('topics.show', $topic).'#proposal-review')
        ->assertSessionHasNoErrors();

    expect($topic->fresh()->status)->toBe('approved')
        ->and($topic->fresh()->isMonitoringAvailable())->toBeFalse()
        ->and($this->faculty->fresh()->hasRole('faculty_researcher'))->toBeFalse();
    expect(Notification::sent($this->faculty, ProposalActivityNotification::class)
        ->map(fn (ProposalActivityNotification $notification): string => $notification->title)
        ->all())->toContain('Signed documents ready');

    $notice = app(NoticeToProceedDataService::class)->defaults($topic->fresh());
    $notice['resolution_number'] = $notice['resolution_number'] ?: '01';

    $this->actingAs($this->head)
        ->post(route('research_head.topics.notice-to-proceed.store', $topic), $notice)
        ->assertRedirect(route('topics.show', $topic).'#notice-to-proceed')
        ->assertSessionHasNoErrors();

    $this->actingAs($this->head)
        ->post(route('research_head.topics.notice-to-proceed.upload-signed', $topic), [
            'signed_notice_to_proceed' => UploadedFile::fake()->create('signed-notice-to-proceed.pdf', 125, 'application/pdf'),
        ])
        ->assertRedirect(route('topics.show', $topic).'#notice-to-proceed')
        ->assertSessionHasNoErrors();

    $topic->refresh();
    $this->faculty->refresh();

    expect($topic->isMonitoringAvailable())->toBeTrue()
        ->and($topic->project_status)->toBe('ongoing')
        ->and($this->faculty->hasRole('faculty_researcher'))->toBeTrue();
    Notification::assertSentTo(
        $this->faculty,
        ProposalActivityNotification::class,
        fn (ProposalActivityNotification $notification): bool => $notification->title === 'Signed Notice to Proceed issued',
    );

    $monitoringPayload = [
        'reporting_date' => now()->toDateString(),
        'tracking_number' => 'LIFECYCLE-2026-001',
        'work_plan' => [[
            'activity' => 'Conduct community mangrove monitoring',
            'percent_weight' => 100,
            'physical_target' => 'Complete the first monitoring cycle',
            'target_completion_date' => now()->addMonth()->toDateString(),
            'actual_accomplishment' => 'Completed the baseline monitoring cycle',
            'accomplished_percentage' => 25,
            'findings' => 'Community monitors completed the planned observations.',
        ]],
        'budget_utilization' => [
            ['type' => 'Purchase Request', 'details' => 'Monitoring supplies', 'amount_requested' => 1000, 'actual_amount' => 900, 'remarks' => 'Delivered'],
            ['type' => 'Cash Advance', 'details' => '', 'amount_requested' => 0, 'actual_amount' => 0, 'remarks' => ''],
            ['type' => 'Request of Payment', 'details' => '', 'amount_requested' => 0, 'actual_amount' => 0, 'remarks' => ''],
        ],
        'prepared_by_date_signed' => now()->toDateString(),
    ];

    $this->withSession([
        User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY_RESEARCHER,
    ])->actingAs($this->faculty)
        ->get(route('research.show', $topic))
        ->assertOk()
        ->assertSee('Project monitoring');

    $headNotificationCountBeforePreparation = Notification::sent($this->head, ProposalActivityNotification::class)->count();

    $this->withSession([
        User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY_RESEARCHER,
    ])->actingAs($this->faculty)
        ->post(route('project-progress.store', $topic), $monitoringPayload)
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $report = ProjectProgressReport::query()->sole();

    expect($report->topic_id)->toBe($topic->id)
        ->and($report->review_status)->toBe('pending')
        ->and($report->progress_percentage)->toBe(25)
        ->and($report->isPrepared())->toBeTrue();
    expect(Notification::sent($this->head, ProposalActivityNotification::class)->count())
        ->toBe($headNotificationCountBeforePreparation);

    $this->withSession([
        User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY_RESEARCHER,
    ])->actingAs($this->faculty)
        ->post(route('project-progress.submit-prepared', [$topic, $report]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    Notification::assertSentTo(
        $this->head,
        ProposalActivityNotification::class,
        fn (ProposalActivityNotification $notification): bool => $notification->title === 'New '.$report->quarter_label.' Monitoring Tool Submitted'
            && $notification->url === route('topics.show', $topic).'#monitoring-tool-'.$report->id,
    );

    $this->actingAs($this->head)
        ->get(route('research_head.projects.index'))
        ->assertOk()
        ->assertSee($topic->title)
        ->assertSee('25%')
        ->assertSee('1 awaiting review');
});

test('an independent proposal can be prepared submitted reviewed and revised', function () {
    Notification::fake();
    $this->call->update(['status' => 'closed']);
    $draft = ($this->completeDraft)(($this->createDraft)(['research_call_id' => null]));
    expect(app(ProposalDraftReadiness::class)->isReady($draft))->toBeTrue();
    $this->actingAs($this->faculty)->post(route('faculty.proposal-drafts.submit', $draft))
        ->assertRedirect(route('faculty.dashboard'))->assertSessionHasNoErrors();
    $topic = TopicProposal::query()->sole();
    expect($topic->research_call_id)->toBeNull()
        ->and($topic->latestVersion->files)->toHaveCount(7);
    $this->get(route('faculty.dashboard'))->assertOk();
    $topic->update(['status' => 'revision_requested']);
    $this->get(route('faculty.proposal-drafts.revision', $topic))->assertRedirect();
    expect($topic->revisionDraft()->firstOrFail()->research_call_id)->toBeNull();
});
