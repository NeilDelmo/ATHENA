<?php

use App\Actions\CreateProposalRevisionDraft;
use App\Actions\RecordProposalDraftDocumentVersion;
use App\Actions\SaveProposalDraftDetails;
use App\Actions\SaveProposalDraftDocument;
use App\Actions\SubmitProposalDraft;
use App\Contracts\DocumentPdfConverter;
use App\Jobs\PrepareProposalDraftPdfs;
use App\Livewire\ProposalDraftReviewPackage;
use App\Models\LiteratureSource;
use App\Models\ProjectProgressReport;
use App\Models\ProposalDraft;
use App\Models\ProposalDraftDocument;
use App\Models\ProposalDraftDocumentVersion;
use App\Models\ProposalFileAnnotation;
use App\Models\ProposalSignatory;
use App\Models\ProposalVersionFile;
use App\Models\ResearchCall;
use App\Models\ResearchManual;
use App\Models\TopicProposal;
use App\Models\User;
use App\Notifications\ProposalActivityNotification;
use App\Services\ApprovedWorkPlanMonitoringService;
use App\Services\CommentResponseFeedback;
use App\Services\LibreOfficeDocumentPdfConverter;
use App\Services\MonitoringQuarterService;
use App\Services\NoticeToProceedDataService;
use App\Services\ProposalPdfPreparation;
use App\Services\ProposalSignatureWorkflow;
use App\Support\InitialScreeningSubmissionOrder;
use App\Support\ProposalDraftReadiness;
use App\Support\ProposalPaperCatalog;
use App\Support\ResearchHeadScreeningData;
use Illuminate\Http\UploadedFile;
use Illuminate\Process\PendingProcess;
use Illuminate\Queue\Events\JobPopping;
use Illuminate\Queue\WorkerOptions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;

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

    $this->completeDraft = function (ProposalDraft $draft, bool $prepare = true): ProposalDraft {
        $draft->update(($this->projectDetails)());
        $draft->update(['signatory_selections' => [
            'comment_response_head' => ['name' => 'Selected Research Head'],
            'comment_response_vice_chancellor' => ['name' => 'Selected Vice Chancellor'],
        ]]);

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
        if ($prepare) {
            app(SubmitProposalDraft::class)->prepare($draft, $this->faculty);
        }

        return $draft->fresh(['documents', 'researchCall']);
    };

    $this->prepareExpenseLayoutDraft = function (): ProposalDraft {
        $draft = ($this->completeDraft)(($this->createDraft)(), prepare: false);
        foreach (app(ProposalPaperCatalog::class)->all()->except('expense-breakdown') as $paper) {
            $path = $draft->storageDirectory().'/prepared/fixtures/'.$paper['slug'].'/paper.pdf';
            Storage::disk('local')->put($path, "%PDF-1.7\n".$paper['slug']);
            $draft->documents()->updateOrCreate([
                'document_type' => $paper['document_type'],
                'position' => 0,
            ], [
                'file_path' => $path,
                'original_filename' => $paper['slug'].'.pdf',
                'mime_type' => 'application/pdf',
                'completed_at' => now(),
            ]);
        }
        app(SubmitProposalDraft::class)->prepare($draft, $this->faculty, onlyMissing: true);

        return $draft->fresh(['documents', 'researchCall']);
    };
});

test('submitting a prepared draft preserves its manual edition after a newer edition becomes current', function () {
    Notification::fake();
    $previous = ResearchManual::factory()->create();
    $current = ResearchManual::factory()->create();
    $draft = ($this->completeDraft)(($this->createDraft)(['research_manual_id' => $previous->id]));
    $indexPath = Storage::disk('local')->path('research-manual/current.json');
    File::ensureDirectoryExists(dirname($indexPath));
    File::put($indexPath, json_encode(['schema_version' => 1, 'version' => $current->version,
        'title' => $current->title, 'page_count' => 1, 'unindexed_pages' => [],
        'chunks' => [['id' => 'policy', 'section' => 'Policy', 'page_start' => 1, 'page_end' => 1, 'content' => 'Current research policy.']]], JSON_THROW_ON_ERROR));
    config(['research_assistant.manual.enabled' => true, 'research_assistant.manual.index_path' => $indexPath]);

    $topic = app(SubmitProposalDraft::class)->handle($draft, $this->faculty);

    expect($topic->research_manual_id)->toBe($previous->id);
});

test('faculty update an unseen submitted package repeatedly without replacing its previous versions', function () {
    Notification::fake();
    $this->call->update(['max_active_research_per_faculty' => 1]);
    $draft = ($this->completeDraft)(($this->createDraft)());
    $topic = app(SubmitProposalDraft::class)->handle($draft, $this->faculty);
    $originalFiles = $topic->latestVersion->files->mapWithKeys(fn ($file): array => [$file->file_path => Storage::disk('local')->get($file->file_path)]);
    $this->actingAs($this->faculty)->get(route('topics.show', $topic))
        ->assertOk()->assertSee('Update submitted proposal')->assertSee('submit Version 2');

    for ($number = 2; $number <= 3; $number++) {
        $this->get(route('faculty.topics.edit-package', $topic))->assertRedirect();
        $workingDraft = $topic->revisionDraft()->with('documents')->firstOrFail();
        $this->get(route('faculty.topics.edit-package', $topic))->assertRedirect(route('faculty.proposal-drafts.show', $workingDraft));
        expect($topic->revisionDraft()->count())->toBe(1)->and($workingDraft->documents)->toHaveCount(7);
        $this->get(route('faculty.proposal-drafts.show', $workingDraft))->assertOk()->assertSee('These changes are private')->assertSee('Review and Turn In');
        $this->get(route('faculty.proposal-drafts.review', $workingDraft))->assertOk()->assertSee('Submit Version '.$number);
        $document = $workingDraft->documents->firstWhere('document_type', ProposalVersionFile::TYPE_DETAILED_PROPOSAL);
        $this->get(route('faculty.proposal-drafts.detailed-proposal.edit', $workingDraft))->assertOk();
        $this->put(route('faculty.proposal-drafts.submission-files.replace', [$workingDraft, 'detailed-proposal']), [
            'file' => UploadedFile::fake()->createWithContent('updated.pdf', "%PDF-1.7\nUpdated package ".$number),
            'document_version' => $document->lock_version,
        ])->assertSessionHasNoErrors();
        $this->post(route('faculty.proposal-drafts.submit', $workingDraft))->assertSessionHasNoErrors()->assertRedirect(route('faculty.dashboard'))
            ->assertSessionHas('success', 'Proposal submitted successfully with seven PDFs and sent to the Research Head.');
        $topic->refresh();
        expect($topic->versions()->count())->toBe($number)
            ->and($topic->latestVersion->version_number)->toBe($number)
            ->and($topic->latestVersion->submission_type)->toBe('update')
            ->and($topic->latestVersion->change_summary)->toBe('Faculty updated the proposal before Research Head review.')
            ->and($topic->status)->toBe('pending')
            ->and($this->faculty->proposals()->count())->toBe(1);
        $latestPaper = $topic->latestVersion->files->firstWhere('document_type', ProposalVersionFile::TYPE_DETAILED_PROPOSAL);
        expect(Storage::disk('local')->get($latestPaper->file_path))->toBe("%PDF-1.7\nUpdated package ".$number);
        foreach ($originalFiles as $path => $contents) {
            expect(Storage::disk('local')->get($path))->toBe($contents);
        }
        $originalFiles = $originalFiles->merge($topic->latestVersion->files->mapWithKeys(fn ($file): array => [$file->file_path => Storage::disk('local')->get($file->file_path)]));
    }
    Notification::assertSentTo($this->head, ProposalActivityNotification::class, fn ($notification): bool => $notification->title === 'Proposal submission updated');
    $this->get(route('topics.show', $topic))->assertSee('Submission update before review')
        ->assertDontSee('Package update before review')->assertDontSee('Latest submitted package');
    $this->actingAs($this->head)->get(route('research_head.received-submissions.index', ['type' => 'update']))
        ->assertOk()->assertSee('Submission update')->assertViewHas('submissions', fn ($submissions): bool => $submissions->count() === 2);
});

test('edited proposal fields regenerate PDFs for Version 3 before Research Head review', function () {
    Notification::fake();
    Queue::fake();
    $topic = app(SubmitProposalDraft::class)->handle(($this->completeDraft)(($this->createDraft)()), $this->faculty);
    $preparation = app(ProposalPdfPreparation::class);

    for ($number = 2; $number <= 3; $number++) {
        $submitted = $topic->fresh()->latestVersion->files;
        $previousContents = $submitted->mapWithKeys(fn ($file): array => [$file->file_path => Storage::disk('local')->get($file->file_path)]);
        $draft = app(CreateProposalRevisionDraft::class)->handle($topic->fresh(), $this->faculty);
        $document = $draft->documents->firstWhere('document_type', ProposalVersionFile::TYPE_DETAILED_PROPOSAL);
        $before = $draft->documents->keyBy('document_type')->map->file_path;

        $this->actingAs($this->faculty)
            ->putJson(route('faculty.proposal-drafts.detailed-proposal.update', $draft), [
                ...$document->source_data,
                'project_leader' => $draft->project_leader,
                'draft_version' => $draft->lock_version,
                'document_version' => $document->lock_version,
                'related_literature' => 'Updated research evidence for Version '.$number.'.',
            ])->assertOk()->assertJsonPath('saved_as_draft', false)->assertJsonPath('completion_errors', []);
        $draft->refresh()->load('documents');
        expect(app(ProposalDraftReadiness::class)->errors($draft))->toBe([])
            ->and($preparation->status($draft)['prepared'])->toBe(6);
        $this->postJson(route('faculty.proposal-drafts.submission-files.prepare', $draft))
            ->assertAccepted()->assertJsonPath('preparation.status', 'queued');
        $preparation->run($draft->id);
        $draft->refresh()->load('documents');
        expect($preparation->status($draft)['status'])->toBe('ready')
            ->and($preparation->status($draft)['prepared'])->toBe(7);
        foreach ($draft->documents as $current) {
            if ($current->document_type !== ProposalVersionFile::TYPE_DETAILED_PROPOSAL) {
                expect($current->file_path)->toBe($before[$current->document_type]);
            }
        }
        foreach ($previousContents as $path => $contents) {
            expect(Storage::disk('local')->get($path))->toBe($contents);
        }
        $this->post(route('faculty.proposal-drafts.submit', $draft))->assertSessionHasNoErrors()->assertRedirect(route('faculty.dashboard'));
        expect($topic->fresh()->latestVersion->version_number)->toBe($number)
            ->and($topic->fresh()->latestVersion->files)->toHaveCount(7);
    }
});

test('version two submission preserves saved form history when an older recovery PDF is missing', function () {
    Notification::fake();
    $topic = app(SubmitProposalDraft::class)->handle(($this->completeDraft)(($this->createDraft)()), $this->faculty);
    $originalFiles = $topic->latestVersion->files->mapWithKeys(fn ($file): array => [$file->file_path => Storage::disk('local')->get($file->file_path)]);
    $draft = app(CreateProposalRevisionDraft::class)->handle($topic, $this->faculty);
    $document = $draft->documents->firstWhere('document_type', ProposalVersionFile::TYPE_DETAILED_PROPOSAL);
    $history = app(RecordProposalDraftDocumentVersion::class)->handle($document, $this->faculty);
    $savedSource = $history->source_data;
    Storage::disk('local')->delete($history->file_path);
    app(SubmitProposalDraft::class)->prepare($draft, $this->faculty);
    $preparedFiles = $draft->fresh()->documents->mapWithKeys(fn ($file): array => [$file->document_type => Storage::disk('local')->get($file->file_path)]);

    $this->actingAs($this->faculty)
        ->post(route('faculty.proposal-drafts.submit', $draft))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('faculty.dashboard'));

    $history->refresh();
    expect($topic->versions()->count())->toBe(2)
        ->and($history->topic_id)->toBe($topic->id)
        ->and($history->proposal_draft_id)->toBeNull()
        ->and($history->file_path)->toBeNull()
        ->and($history->source_data)->toBe($savedSource);
    foreach ($topic->fresh()->latestVersion->files as $file) {
        expect(Storage::disk('local')->get($file->file_path))->toBe($preparedFiles[$file->document_type]);
    }
    foreach ($originalFiles as $path => $contents) {
        expect(Storage::disk('local')->get($path))->toBe($contents);
    }
});

test('submission still rejects unsafe or unrecoverable history files', function (bool $unsafePath) {
    Notification::fake();
    $draft = ($this->completeDraft)(($this->createDraft)());
    $document = $draft->documents->firstWhere('document_type', ProposalVersionFile::TYPE_DETAILED_PROPOSAL);
    $history = app(RecordProposalDraftDocumentVersion::class)->handle($document, $this->faculty);
    $history->update([
        'file_path' => $unsafePath ? 'proposal-drafts/another-owner/private.pdf' : $draft->storageDirectory().'/missing.pdf',
        'source_data' => $unsafePath ? $document->source_data : null,
    ]);
    Storage::disk('local')->put('proposal-drafts/another-owner/private.pdf', '%PDF-1.7 private');

    $this->actingAs($this->faculty)
        ->post(route('faculty.proposal-drafts.submit', $draft))
        ->assertSessionHasErrors('history');

    expect($draft->fresh()->status)->toBe(ProposalDraft::STATUS_DRAFT)
        ->and(TopicProposal::query()->count())->toBe(0)
        ->and($history->fresh()->proposal_draft_id)->toBe($draft->id);
    Storage::disk('local')->assertExists($document->file_path);
    Storage::disk('local')->assertDirectoryEmpty('proposal-packages/'.$this->faculty->id);
})->with(['outside the draft' => true, 'missing without saved form data' => false]);

test('replacing a revision PDF retains the earlier file referenced by recovery history', function () {
    Notification::fake();
    $topic = app(SubmitProposalDraft::class)->handle(($this->completeDraft)(($this->createDraft)()), $this->faculty);
    $topic->update(['status' => 'revision_requested']);
    $draft = app(CreateProposalRevisionDraft::class)->handle($topic, $this->faculty);
    $document = $draft->documents->firstWhere('document_type', ProposalVersionFile::TYPE_DETAILED_PROPOSAL);

    foreach (['First revision', 'Second revision'] as $contents) {
        $this->actingAs($this->faculty)->postJson(route('faculty.proposal-drafts.revision-files.store', $draft), [
            'document_type' => $document->document_type,
            'document_version' => $document->fresh()->lock_version,
            'file' => UploadedFile::fake()->createWithContent('revision.pdf', "%PDF-1.7\n".$contents),
        ])->assertOk();
    }

    $versions = $draft->documentVersions()->whereNotNull('file_path')->reorder('id')->get();
    expect($versions)->toHaveCount(2);
    expect(Storage::disk('local')->get($versions->first()->file_path))->toBe("%PDF-1.7\nFirst revision")
        ->and(Storage::disk('local')->get($versions->last()->file_path))->toBe("%PDF-1.7\nSecond revision");
});

test('generated checkbox marks reach the Research Head through submitted updates and requested revisions', function (bool $revision) {
    Notification::fake();
    Process::fake(['*pdf-section-coordinates*' => Process::result(output: '<document/>')]);
    $converter = new class implements DocumentPdfConverter
    {
        public array $docxByPdf = [];

        public function convertDocx(string $contents): string
        {
            $pdf = "%PDF-1.7\n".hash('sha256', $contents);
            $this->docxByPdf[$pdf] = $contents;

            return $pdf;
        }

        public function convertXlsx(string $contents): string
        {
            return "%PDF-1.7\n".hash('sha256', $contents);
        }
    };
    app()->instance(DocumentPdfConverter::class, $converter);
    $topic = app(SubmitProposalDraft::class)->handle(($this->completeDraft)(($this->createDraft)()), $this->faculty);
    $originalVersion = $topic->latestVersion;
    $originalPaper = $originalVersion->files->firstWhere('document_type', ProposalVersionFile::TYPE_DETAILED_PROPOSAL);
    $originalContents = Storage::disk('local')->get($originalPaper->file_path);
    $review = null;
    if ($revision) {
        $originalPaper->annotations()->create([
            'reviewer_id' => $this->head->id, 'annotation_type' => 'area', 'page_number' => 1,
            'rectangles' => [['x' => .1, 'y' => .2, 'width' => .3, 'height' => .1]],
            'comment' => 'Clarify the project summary.',
        ]);
        $this->actingAs($this->head)->patch(route('research_head.topics.updateStatus', $topic), [
            'status' => 'revision_requested', 'revision_file_ids' => [$originalPaper->id],
            'revision_file_notes' => [$originalPaper->id => 'Clarify the project summary.'],
            'evaluation_document' => UploadedFile::fake()->create('evaluation.pdf', 100, 'application/pdf'),
        ])->assertSessionHasNoErrors();
        $review = $topic->reviews()->where('decision', 'revision_requested')->latest('id')->firstOrFail();
    }
    $topic->refresh();
    $workingDraft = app(CreateProposalRevisionDraft::class)->handle($topic, $this->faculty);
    $this->actingAs($this->faculty);
    $level = $revision ? 'constituent_campus' : 'central_agency';
    if (! $revision) {
        $budget = $workingDraft->documents->firstWhere('document_type', ProposalVersionFile::TYPE_LINE_ITEM_BUDGET);
        $this->putJson(route('faculty.proposal-drafts.line-item-budget.update', $workingDraft), [
            'document_version' => $budget->lock_version, 'level_of_call' => $level,
        ])->assertOk();
    }
    $document = $workingDraft->documents()->where('document_type', ProposalVersionFile::TYPE_DETAILED_PROPOSAL)->sole();
    $this->putJson(route('faculty.proposal-drafts.detailed-proposal.update', $workingDraft), [
        ...$document->source_data, 'executive_brief' => 'Updated project summary for the Research Head.',
        'project_leader' => $workingDraft->project_leader, 'document_version' => $document->lock_version,
        'draft_version' => $workingDraft->lock_version,
    ])->assertOk();
    $document->refresh();
    $this->post(route('faculty.proposal-drafts.detailed-proposal.preview', $workingDraft))
        ->assertOk()->assertSeeText('☒ Complete Documents')->assertSeeText('☒ Initial Screening Form')
        ->assertSeeText('☒ '.($revision ? 'Constituent Campus' : 'Central Agency'));
    if ($revision) {
        $download = $this->post(route('faculty.proposal-drafts.detailed-proposal.download', $workingDraft), [], ['X-Revision-PDF' => '1'])->assertOk();
        $expectedPdf = $download->streamedContent();
        $this->postJson(route('faculty.proposal-drafts.revision-files.store', $workingDraft), [
            'document_type' => ProposalVersionFile::TYPE_DETAILED_PROPOSAL, 'document_version' => $document->lock_version,
            'file' => UploadedFile::fake()->createWithContent('revised-proposal.pdf', $expectedPdf),
        ])->assertOk();
        $responses = collect(app(CommentResponseFeedback::class)->rows($review))->mapWithKeys(fn (array $row): array => [$row['key'] => [
            'response' => 'Clarified the project summary.', 'no_change' => false, 'page' => 1, 'paragraph' => 2,
        ]])->all();
        $this->patch(route('faculty.topics.resubmit', $topic), [
            'revision_draft_id' => $workingDraft->id, 'title' => $topic->title, 'estimated_budget' => 3600,
            'estimated_duration_months' => 12, 'feedback_review_id' => $review->id, 'feedback_responses' => $responses,
        ])->assertSessionHasNoErrors()->assertRedirect(route('faculty.dashboard'));
        expect($review->fileRevisions()->whereNull('resolved_at')->count())->toBe(0);
        Notification::assertSentTo($this->head, ProposalActivityNotification::class, fn ($notification): bool => $notification->title === 'Proposal revision submitted');
    } else {
        $this->post(route('faculty.proposal-drafts.submission-files.prepare', $workingDraft))->assertSessionHasNoErrors();
        $prepared = $document->fresh();
        $expectedPdf = Storage::disk('local')->get($prepared->file_path);
        $this->post(route('faculty.proposal-drafts.submit', $workingDraft))->assertSessionHasNoErrors()->assertRedirect(route('faculty.dashboard'));
        Notification::assertSentTo($this->head, ProposalActivityNotification::class, fn ($notification): bool => $notification->title === 'Proposal submission updated');
    }
    $latest = $topic->latestVersion()->with('files')->firstOrFail();
    $paper = $latest->files->firstWhere('document_type', ProposalVersionFile::TYPE_DETAILED_PROPOSAL);
    expect($latest->version_number)->toBe(2)->and($latest->submission_type)->toBe($revision ? 'revision' : 'update')
        ->and($latest->files)->toHaveCount(7)->and($topic->fresh()->status)->toBe($revision ? 'resubmitted' : 'pending')
        ->and(Storage::disk('local')->get($paper->file_path))->toBe($expectedPdf)
        ->and(Storage::disk('local')->get($originalPaper->file_path))->toBe($originalContents);
    $path = tempnam(sys_get_temp_dir(), 'athena-submitted-checkboxes-');
    file_put_contents($path, $converter->docxByPdf[$expectedPdf]);
    $archive = new ZipArchive;
    try {
        expect($archive->open($path))->toBeTrue();
        $xml = new DOMDocument;
        $xml->loadXML($archive->getFromName('word/document.xml'), LIBXML_NONET);
        $xpath = new DOMXPath($xml);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $xpath->registerNamespace('w14', 'http://schemas.microsoft.com/office/word/2010/wordml');
        foreach (['Complete Documents' => true, 'Initial Screening Form' => true, 'Constituent Campus' => $level === 'constituent_campus', 'Central Agency' => $level === 'central_agency'] as $label => $selected) {
            $checkbox = $xpath->query('//w:p[contains(string(.), "'.$label.'")]//w14:checkbox')->item(0);
            expect($checkbox)->not->toBeNull()
                ->and($xpath->evaluate('string(./w14:checked/@w14:val)', $checkbox))->toBe($selected ? '1' : '0')
                ->and($xpath->evaluate('string(ancestor::w:sdt[1]/w:sdtContent//w:t)', $checkbox))->toBe($selected ? '☒' : '☐');
        }
        foreach ([ProposalVersionFile::TYPE_LINE_ITEM_BUDGET => 0, ProposalVersionFile::TYPE_INITIAL_SCREENING_FORM => 3] as $type => $offset) {
            $relatedPaper = $latest->files->firstWhere('document_type', $type);
            $relatedPdf = Storage::disk('local')->get($relatedPaper->file_path);
            $archive->close();
            file_put_contents($path, $converter->docxByPdf[$relatedPdf]);
            expect($archive->open($path))->toBeTrue();
            $xml->loadXML($archive->getFromName('word/document.xml'), LIBXML_NONET);
            $xpath = new DOMXPath($xml);
            $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
            foreach (['central_agency', 'constituent_campus'] as $index => $value) {
                $checkbox = $xpath->query('//w:checkBox')->item($offset + $index);
                expect($xpath->evaluate('string(./w:checked/@w:val)', $checkbox))->toBe($level === $value ? '1' : '0')
                    ->and($xpath->evaluate('string(./w:default/@w:val)', $checkbox))->toBe($level === $value ? '1' : '0');
            }
        }
    } finally {
        $archive->close();
        unlink($path);
    }
    $this->actingAs($this->head)->get(route('topics.versions.files.view', [$topic, $latest, $paper]))
        ->assertOk()->assertHeader('Content-Type', 'application/pdf');
})->with(['update submitted proposal' => false, 'requested revision' => true]);

test('opening a submitted proposal locks the update workspace and save endpoints', function () {
    Notification::fake();
    $topic = app(SubmitProposalDraft::class)->handle(($this->completeDraft)(($this->createDraft)()), $this->faculty);
    $originalVersion = $topic->latestVersion;
    $originalFiles = $originalVersion->files->mapWithKeys(fn ($file): array => [$file->file_path => Storage::disk('local')->get($file->file_path)]);
    $workingDraft = app(CreateProposalRevisionDraft::class)->handle($topic, $this->faculty);
    $this->actingAs($this->head)->get(route('topics.show', $topic))->assertOk();
    expect($topic->fresh()->canUpdateBeforeReview())->toBeFalse();
    $this->actingAs($this->faculty)->get(route('topics.show', $topic))->assertDontSee('data-proposal-package-update', false);
    $this->get(route('faculty.topics.edit-package', $topic))->assertForbidden();
    $this->get(route('faculty.proposal-drafts.detailed-proposal.edit', $workingDraft))->assertForbidden();
    $this->putJson(route('faculty.proposal-drafts.details.update', $workingDraft), ($this->projectDetails)([
        'project_title' => 'Private updated proposal title',
    ]))->assertForbidden();
    $this->post(route('faculty.proposal-drafts.members.store', $workingDraft), ['name' => 'New Member', 'email' => 'member@g.batstate-u.edu.ph'])->assertForbidden();
    $this->get(route('faculty.proposal-drafts.show', $workingDraft))->assertOk()
        ->assertSee('Review working draft')->assertDontSee('data-proposal-package-prepare', false)
        ->assertSee('data-proposal-editing-locked', false)
        ->assertDontSee('data-project-details-autosave-form', false)
        ->assertDontSee('Choose replacement PDF')
        ->assertDontSee('data-proposal-package-submit', false);
    $this->post(route('faculty.proposal-drafts.submission-files.prepare', $workingDraft))->assertForbidden();
    $document = $workingDraft->documents()->where('document_type', ProposalVersionFile::TYPE_DETAILED_PROPOSAL)->sole();
    $this->put(route('faculty.proposal-drafts.submission-files.replace', [$workingDraft, 'detailed-proposal']), [
        'file' => UploadedFile::fake()->createWithContent('private-replacement.pdf', "%PDF-1.7\nPrivate replacement"),
        'document_version' => $document->lock_version,
    ])->assertForbidden();
    $this->post(route('faculty.proposal-drafts.submit', $workingDraft))->assertForbidden();
    $this->get(route('faculty.proposal-drafts.show', $workingDraft))->assertOk()
        ->assertSee('Editing and submission are locked until revisions are requested.')
        ->assertDontSee('data-proposal-package-submit', false);
    expect($topic->versions()->count())->toBe(1)
        ->and($topic->fresh()->title)->toBe($originalVersion->title)
        ->and($topic->collaborators()->count())->toBe(0)
        ->and($workingDraft->fresh()->project_title)->toBe($originalVersion->title)
        ->and($workingDraft->isEditable())->toBeFalse()
        ->and($document->fresh()->original_filename)->not->toBe('private-replacement.pdf');
    foreach ($originalFiles as $path => $contents) {
        expect(Storage::disk('local')->get($path))->toBe($contents);
    }
});

test('pre-review updates are owner-only and unavailable in later review stages', function () {
    Notification::fake();
    $topic = app(SubmitProposalDraft::class)->handle(($this->completeDraft)(($this->createDraft)()), $this->faculty);
    $this->actingAs($this->otherFaculty)->get(route('faculty.topics.edit-package', $topic))->assertForbidden();
    $this->actingAs($this->head)->get(route('faculty.topics.edit-package', $topic))->assertForbidden();
    $topic->update(['status' => TopicProposal::STATUS_GAD_REVIEW]);
    $this->actingAs($this->faculty)->get(route('faculty.topics.edit-package', $topic))->assertForbidden();
    expect($topic->revisionDraft()->exists())->toBeFalse();
});

test('submitted papers stay locked throughout review and completion', function (string $status, ?string $projectStatus) {
    Notification::fake();
    $topic = app(SubmitProposalDraft::class)->handle(($this->completeDraft)(($this->createDraft)()), $this->faculty);
    $workingDraft = app(CreateProposalRevisionDraft::class)->handle($topic, $this->faculty);
    $topic->markLatestVersionViewedByResearchHead();
    $topic->update(['status' => $status, 'project_status' => $projectStatus]);
    $this->actingAs($this->faculty)->get(route('faculty.topics.edit-package', $topic))->assertForbidden();
    expect($workingDraft->fresh()->isEditable())->toBeFalse();
    $this->get(route('faculty.proposal-drafts.detailed-proposal.edit', $workingDraft))->assertForbidden();
    $this->get(route('faculty.proposal-drafts.work-plan.edit', $workingDraft))->assertForbidden();
    $this->get(route('faculty.proposal-drafts.line-item-budget.edit', $workingDraft))->assertForbidden();
    $this->get(route('faculty.proposal-drafts.expense-breakdown.edit', $workingDraft))->assertForbidden();
    $this->get(route('faculty.proposal-drafts.curriculum-vitae.edit', $workingDraft))->assertForbidden();
    $this->post(route('faculty.proposal-drafts.submit', $workingDraft))->assertForbidden();
    expect($topic->fresh()->status)->toBe($status)->and($topic->versions()->count())->toBe(1);
})->with([
    'Research Head review' => ['pending', null],
    'GAD review' => [TopicProposal::STATUS_GAD_REVIEW, null],
    'co-evaluator review' => ['expert_review', null],
    'LREC review' => [TopicProposal::STATUS_LREC_REVIEW, null],
    'signing' => [TopicProposal::STATUS_READY_FOR_SIGNATURE, null],
    'monitoring' => ['approved', 'ongoing'],
    'completed' => ['approved', 'completed'],
    'rejected' => ['rejected', null],
]);

test('saved pre-review edits reopen on a revision request and become Version 2', function () {
    Notification::fake();
    Process::fake(['*pdf-section-coordinates*' => Process::result(output: '<document/>')]);
    $topic = app(SubmitProposalDraft::class)->handle(($this->completeDraft)(($this->createDraft)()), $this->faculty);
    $originalFile = $topic->latestVersion->files->firstWhere('document_type', ProposalVersionFile::TYPE_DETAILED_PROPOSAL);
    $originalContents = Storage::disk('local')->get($originalFile->file_path);
    $draft = app(CreateProposalRevisionDraft::class)->handle($topic, $this->faculty);
    $document = $draft->documents->firstWhere('document_type', ProposalVersionFile::TYPE_DETAILED_PROPOSAL);
    $this->actingAs($this->faculty)->putJson(route('faculty.proposal-drafts.detailed-proposal.update', $draft), [
        ...$document->source_data,
        'related_literature' => 'Research evidence saved privately before review begins.',
        'project_leader' => $draft->project_leader,
        'document_version' => $document->lock_version,
        'draft_version' => $draft->lock_version,
    ])->assertOk();
    $this->post(route('faculty.proposal-drafts.submission-files.prepare', $draft))->assertSessionHasNoErrors();
    $topic->markLatestVersionViewedByResearchHead();
    expect($draft->fresh()->isEditable())->toBeFalse();
    $originalFile->annotations()->create([
        'reviewer_id' => $this->head->id,
        'annotation_type' => ProposalFileAnnotation::TYPE_AREA,
        'page_number' => 1,
        'rectangles' => [['x' => 0.1, 'y' => 0.2, 'width' => 0.3, 'height' => 0.1]],
        'comment' => 'Expand the literature review.',
    ]);
    $this->actingAs($this->head)->patch(route('research_head.topics.updateStatus', $topic), [
        'status' => 'revision_requested',
        'revision_file_ids' => [$originalFile->id],
        'revision_file_notes' => [$originalFile->id => 'Expand the literature review.'],
        'evaluation_document' => UploadedFile::fake()->create('evaluation.pdf', 100, 'application/pdf'),
    ])->assertSessionHasNoErrors();
    $topic->refresh();
    $this->actingAs($this->faculty)->get(route('faculty.proposal-drafts.revision', $topic))->assertRedirect();
    expect($draft->fresh()->isEditable())->toBeTrue();
    expect($topic->revisionDraft()->sole()->id)->toBe($draft->id)
        ->and($document->fresh()->source_data['related_literature'])->toContain('saved privately');
    $review = $topic->reviews()->where('decision', 'revision_requested')->latest('id')->firstOrFail();
    $feedbackRows = app(CommentResponseFeedback::class)->rows($review);
    $this->patch(route('faculty.topics.resubmit', $topic), [
        'revision_draft_id' => $draft->id,
        'title' => $topic->title,
        'estimated_budget' => 3600,
        'estimated_duration_months' => $topic->estimated_duration_months,
        'change_summary' => 'Expanded the literature review.',
        'feedback_review_id' => $review->id,
        'feedback_responses' => collect($feedbackRows)->mapWithKeys(fn (array $row): array => [$row['key'] => [
            'response' => 'Expanded the literature review in the working draft.',
            'no_change' => false,
            'page' => 1,
            'paragraph' => 2,
        ]])->all(),
    ])->assertSessionHasNoErrors()->assertRedirect(route('faculty.dashboard'));
    $topic->refresh();
    expect($topic->latestVersion->version_number)->toBe(2)
        ->and($topic->latestVersion->files->firstWhere('document_type', ProposalVersionFile::TYPE_DETAILED_PROPOSAL)->source_data['related_literature'])->toContain('saved privately')
        ->and(Storage::disk('local')->get($originalFile->file_path))->toBe($originalContents)
        ->and($topic->revisionDraft()->exists())->toBeFalse();
});

test('an unseen submitted package accepts team additions and detailed proposal edits before the next turn in', function () {
    Notification::fake();
    $topic = app(SubmitProposalDraft::class)->handle(($this->completeDraft)(($this->createDraft)()), $this->faculty);
    $originalIntroduction = $topic->latestVersion->files->firstWhere('document_type', 'detailed_proposal')->source_data['introduction'];
    $workingDraft = app(CreateProposalRevisionDraft::class)->handle($topic, $this->faculty);
    $this->actingAs($this->faculty)->post(route('faculty.proposal-drafts.members.store', $workingDraft), [
        'name' => 'New Research Member', 'email' => 'new.research.member@g.batstate-u.edu.ph',
    ])->assertSessionHasNoErrors();
    $document = $workingDraft->documents->firstWhere('document_type', 'detailed_proposal');
    $this->putJson(route('faculty.proposal-drafts.detailed-proposal.update', $workingDraft), [
        ...$document->source_data, 'introduction' => 'Updated introduction before the first Research Head review.',
        'project_leader' => $workingDraft->project_leader, 'document_version' => $document->lock_version,
        'draft_version' => $workingDraft->lock_version,
    ])->assertOk();
    expect($topic->latestVersion->files->firstWhere('document_type', 'detailed_proposal')->source_data['introduction'])->toBe($originalIntroduction)
        ->and($topic->collaborators()->count())->toBe(0);
    $this->post(route('faculty.proposal-drafts.submission-files.prepare', $workingDraft))->assertSessionHasNoErrors();
    $this->post(route('faculty.proposal-drafts.submit', $workingDraft))->assertSessionHasNoErrors();
    expect($topic->fresh()->latestVersion->version_number)->toBe(2)
        ->and($topic->fresh()->latestVersion->files->firstWhere('document_type', 'detailed_proposal')->source_data['introduction'])->toContain('Updated introduction')
        ->and($topic->collaborators()->where('email', 'new.research.member@g.batstate-u.edu.ph')->exists())->toBeTrue();
});

test('stale working draft models cannot save prepare or submit after review begins', function () {
    Notification::fake();
    $topic = app(SubmitProposalDraft::class)->handle(($this->completeDraft)(($this->createDraft)()), $this->faculty);
    $workingDraft = app(CreateProposalRevisionDraft::class)->handle($topic, $this->faculty);
    $document = $workingDraft->documents->firstWhere('document_type', 'detailed_proposal');
    $topic->markLatestVersionViewedByResearchHead();
    foreach ([
        fn () => app(SaveProposalDraftDocument::class)->handle($workingDraft, $this->faculty, 'detailed_proposal', 0, $document->lock_version, ['source_data' => ['introduction' => 'Stale edit']]),
        fn () => app(SaveProposalDraftDetails::class)->handle($workingDraft, $workingDraft->lock_version, ['project_title' => 'Stale title']),
        fn () => app(SubmitProposalDraft::class)->prepare($workingDraft, $this->faculty),
        fn () => app(SubmitProposalDraft::class)->handle($workingDraft, $this->faculty),
    ] as $save) {
        try {
            $save();
            $this->fail('Review must lock the submitted proposal and its update workspace.');
        } catch (HttpException $exception) {
            expect($exception->getStatusCode())->toBe(403);
        }
    }
    expect($topic->versions()->count())->toBe(1)
        ->and($topic->fresh()->title)->not->toBe('Stale title')
        ->and($document->fresh()->source_data['introduction'])->not->toBe('Stale edit');
});

test('direct Research Head document access closes pre-review editing', function (string $entry) {
    Notification::fake();
    $topic = app(SubmitProposalDraft::class)->handle(($this->completeDraft)(($this->createDraft)()), $this->faculty);
    $version = $topic->latestVersion;
    $file = $version->files->firstWhere('document_type', 'detailed_proposal');
    $url = match ($entry) {
        'document' => route('topics.versions.files.download', [$topic, $version, $file]),
        'screening' => route('research_head.topics.initial-screening-form.edit', [$topic, $version]),
    };
    $this->actingAs($this->head)->get($url)->assertOk();
    expect($topic->fresh()->research_head_viewed_version_id)->toBe($version->id)
        ->and($topic->fresh()->canUpdateBeforeReview())->toBeFalse();
    $this->actingAs($this->faculty)->get(route('faculty.topics.edit-package', $topic))->assertForbidden();
    expect($topic->revisionDraft()->exists())->toBeFalse();
})->with(['document', 'screening']);

test('faculty create proposal drafts without selecting a research call', function () {
    $this->actingAs($this->faculty)->get(route('faculty.proposal-drafts.create'))
        ->assertOk()->assertSee('Project Title')->assertDontSee('name="research_call_id"', false);

    foreach (['First Coastal Study', 'Second Coastal Study'] as $title) {
        $this->post(route('faculty.proposal-drafts.store'), ['project_title' => $title])
            ->assertSessionHasNoErrors();
    }
    expect($this->faculty->proposalDrafts()->count())->toBe(2)
        ->and($this->faculty->proposalDrafts()->pluck('research_call_id')->unique()->all())->toBe([null])
        ->and($this->faculty->proposalDrafts()->pluck('project_leader')->unique()->all())->toBe(['Faculty Owner']);
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
        ->assertDontSee('data-proposal-workspace-navigation', false)
        ->assertSee('data-faculty-drafts', false)
        ->assertSee('Coastal Draft Package')
        ->assertSee('papers ready')
        ->assertSee(route('faculty.submissions'), false)
        ->assertDontSee('data-submitted-proposal=', false)
        ->assertDontSee('Another Faculty Proposal');

    $this->get(route('faculty.submissions'))
        ->assertOk()
        ->assertDontSee('data-proposal-workspace-navigation', false)
        ->assertSee('data-faculty-submissions', false)
        ->assertSee(route('faculty.proposal-drafts.index'), false)
        ->assertSee('Revised Coastal Study')
        ->assertSee('data-revision-action-required', false)
        ->assertSee(route('faculty.topics.revision', $revisionProposal), false)
        ->assertSee('Approved Mangrove Study')
        ->assertSee('Final signing')
        ->assertSee(route('topics.show', $approvedProposal), false)
        ->assertDontSee('data-proposal-draft=', false)
        ->assertDontSee('Another Faculty Proposal');

    $this->actingAs($this->faculty)
        ->get(route('topics.show', $revisionProposal))
        ->assertOk()
        ->assertSee('data-faculty-revision-summary', false)
        ->assertSee('Revise proposal')
        ->assertDontSee('Faculty action required')
        ->assertDontSee('What happens next')
        ->assertDontSee('Revision requested â€” your action is required')
        ->assertDontSee('id="submit-revision"', false)
        ->assertSee("activeTopicTab: 'review'", false);

    $this->get(route('faculty.topics.revision', $revisionProposal))
        ->assertOk()
        ->assertSee('data-faculty-revision-required', false)
        ->assertSee('Prepare the corrected project')
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
            ->assertOk()->assertDontSee('Choose research call')
            ->assertSee('bg-brand-wash', false)->assertSee('text-brand', false)
            ->assertDontSee('bg-blue-', false)->assertDontSee('border-blue-', false)->assertDontSee('text-blue-', false);
        $this->get(route('faculty.proposal-drafts.review', $draft))
            ->assertOk()->assertDontSee('name="research_call_id"', false)
            ->assertSee('bg-brand-wash', false)->assertSee('dark:text-rose-200', false)
            ->assertDontSee('bg-blue-', false)->assertDontSee('border-blue-', false)->assertDontSee('text-blue-', false);
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

test('deleting a draft retains its records and private staged directory for recovery', function () {
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

    $this->assertSoftDeleted($draft);
    $this->assertModelExists($document);
    Storage::disk('local')->assertExists($path);
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

test('the proposal hub separates editable papers from automatically included assessment forms', function () {
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
        ->assertDontSee('Save protection is on.')
        ->assertDontSee('Choose a workspace member or type a name.')
        ->assertDontSee('Prepared by')
        ->assertSee('aria-labelledby="project-details-heading" class="overflow-visible', false)
        ->assertDontSee('aria-labelledby="project-details-heading" class="overflow-hidden', false)
        ->assertDontSee('Upload PDF')
        ->assertSee('>Open Research Proposal</a>', false)
        ->assertSee('>Open Work Plan</a>', false)
        ->assertSee('>Open Line-Item Budget</a>', false)
        ->assertSee('>Open Expense Breakdown</a>', false)
        ->assertSee('>Open Curriculum Vitae</a>', false)
        ->assertSee('data-automatic-assessment-forms', false)
        ->assertSee('Assessment forms added automatically')
        ->assertSee('No faculty answers or file uploads are needed.')
        ->assertSee('Preview form')
        ->assertSee('proposalAssessmentPreview()', false)
        ->assertSee('assessment-form-preview-panel')
        ->assertDontSee('Optional preview')
        ->assertDontSee('>Open GAD Checklist</a>', false)
        ->assertDontSee('>Open Initial Screening Form</a>', false)
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

    $dom = new DOMDocument;
    @$dom->loadHTML($response->getContent());
    $xpath = new DOMXPath($dom);
    expect($xpath->query('//*[@id="required-pdf-attachments-tab"]//article')->length)->toBe(5)
        ->and($xpath->query('//*[@id="required-pdf-attachments-tab"]//a[normalize-space(.)="Open paper"]')->length)->toBe(0)
        ->and($xpath->query('//*[@data-automatic-assessment-forms]//button[@data-assessment-preview-trigger]')->length)->toBe(2)
        ->and($xpath->query('//*[@id="review-papers-heading"]/ancestor::section[1]//article')->length)->toBe(0)
        ->and($xpath->query('//*[@data-review-assessment-forms]//li')->length)->toBe(0);

    $response->assertSee('Loading proposal review');

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

test('automatic PDFs debounce saved changes without creating duplicate queued work', function () {
    Cache::flush();
    Queue::fake();
    $draft = ($this->completeDraft)(($this->createDraft)());
    $draft->documents()->where('document_type', 'curriculum_vitae')->update(['file_path' => null]);
    $preparation = app(ProposalPdfPreparation::class);
    $preparation->request($draft->id);
    $preparation->request($draft->id);
    Queue::assertPushed(PrepareProposalDraftPdfs::class, 1);
    expect($preparation->status($draft->fresh())['status'])->toBe('queued');
});

test('automatic PDFs regenerate affected files without changing editor versions or retaining obsolete working PDFs', function () {
    Cache::flush();
    Queue::fake();
    $draft = ($this->completeDraft)(($this->createDraft)());
    $before = $draft->documents->keyBy('document_type');
    $cv = $before['curriculum_vitae'];
    $cvVersion = $cv->lock_version;
    $oldCvPath = $cv->file_path;
    $cv->update(['file_path' => null]);
    $preparation = app(ProposalPdfPreparation::class);
    $preparation->run($draft->id);
    $current = $draft->fresh()->documents->keyBy('document_type');
    expect($current['curriculum_vitae']->file_path)->not->toBe($oldCvPath)
        ->and($current['curriculum_vitae']->lock_version)->toBe($cvVersion)
        ->and($current['work_plan']->file_path)->toBe($before['work_plan']->file_path)
        ->and($preparation->status($draft->fresh())['status'])->toBe('ready');
    Storage::disk('local')->assertMissing($oldCvPath);
    expect(Storage::disk('local')->allFiles($draft->storageDirectory().'/prepared'))->toHaveCount(7);
    $currentPaths = $current->pluck('file_path')->all();
    $preparation->run($draft->id);
    expect($draft->fresh()->documents->pluck('file_path')->all())->toEqualCanonicalizing($currentPaths);
});

test('expense PDF layout changes regenerate only the outdated system PDF', function (?int $layoutVersion) {
    Cache::flush();
    Queue::fake();
    config(['expense_breakdown.pdf_layout_version' => 2]);
    $draft = ($this->prepareExpenseLayoutDraft)();
    $before = $draft->documents->keyBy('document_type');
    $expense = $before['expense_breakdown'];
    $source = $expense->source_data;
    unset($source['pdf_layout_version']);
    if ($layoutVersion !== null) {
        $source['pdf_layout_version'] = $layoutVersion;
    }
    $expense->updateQuietly(['source_data' => $source]);
    $draft->refresh()->load('documents');
    $preparation = app(ProposalPdfPreparation::class);

    expect(app(ProposalDraftReadiness::class)->submissionFilesArePrepared($draft))->toBeFalse()
        ->and($preparation->status($draft)['prepared'])->toBe(6);
    $url = route('faculty.proposal-drafts.submission-files.download', [$draft, 'expense-breakdown']);
    $this->actingAs($this->faculty)->get($url)->assertNotFound();
    $this->get(route('faculty.proposal-drafts.review', $draft))
        ->assertOk()->assertSee('Preview Estimated Expense Breakdown')->assertDontSee($url, false);
    $this->postJson(route('faculty.proposal-drafts.submission-files.prepare', $draft))
        ->assertAccepted()->assertJsonPath('preparation.status', 'queued');
    $preparation->run($draft->id);
    $current = $draft->fresh()->documents->keyBy('document_type');

    expect($current['expense_breakdown']->file_path)->not->toBe($expense->file_path)
        ->and($current['expense_breakdown']->source_data['pdf_layout_version'])->toBe(2)
        ->and($current->pluck('lock_version', 'id')->all())->toBe($before->pluck('lock_version', 'id')->all())
        ->and($preparation->status($draft->fresh())['status'])->toBe('ready');
    foreach ($before as $type => $document) {
        if ($type !== 'expense_breakdown') {
            expect($current[$type]->file_path)->toBe($document->file_path);
        }
    }
})->with(['legacy PDF' => null, 'previous layout' => 1]);

test('expense PDF layout changes preserve manual uploads and locked draft files', function (string $state) {
    Queue::fake();
    config(['expense_breakdown.pdf_layout_version' => 2]);
    $draft = ($this->prepareExpenseLayoutDraft)();
    $expense = $draft->documents->firstWhere('document_type', 'expense_breakdown');
    $path = match ($state) {
        'manual replacement' => $draft->storageDirectory().'/prepared/manual/expense-breakdown/replacement.pdf',
        'uploaded paper' => $draft->storageDirectory().'/expense-breakdown/uploaded.pdf',
        default => $expense->file_path,
    };
    $contents = "%PDF-1.7\nPreserved expense paper";
    Storage::disk('local')->put($path, $contents);
    $expense->updateQuietly([
        'file_path' => $path,
        'source_data' => [...$expense->source_data, 'pdf_layout_version' => 1],
    ]);
    if ($state === 'locked draft') {
        $draft->updateQuietly(['status' => ProposalDraft::STATUS_SUBMITTING]);
    }
    $draft->refresh()->load('documents');

    expect(app(ProposalDraftReadiness::class)->submissionFilesArePrepared($draft))->toBeTrue();
    app(ProposalPdfPreparation::class)->run($draft->id);
    expect($expense->fresh()->file_path)->toBe($path)
        ->and(Storage::disk('local')->get($path))->toBe($contents);
    $download = $this->actingAs($this->faculty)
        ->get(route('faculty.proposal-drafts.submission-files.download', [$draft, 'expense-breakdown']))
        ->assertOk()->assertDownload($expense->original_filename);
    expect($download->streamedContent())->toBe($contents);
})->with(['manual replacement', 'uploaded paper', 'locked draft']);

test('expense PDF layout metadata keeps the current review preview and download identical', function () {
    Queue::fake();
    config(['expense_breakdown.pdf_layout_version' => 2]);
    $draft = ($this->prepareExpenseLayoutDraft)();
    $expense = $draft->documents->firstWhere('document_type', 'expense_breakdown');
    $preparation = app(ProposalPdfPreparation::class);
    expect($expense->source_data['pdf_layout_version'] ?? null)->toBe(2)
        ->and($preparation->status($draft)['prepared'])->toBe(7)
        ->and($preparation->status($draft)['status'])->toBe('ready');
    $preparation->run($draft->id);
    expect($expense->fresh()->file_path)->toBe($expense->file_path);

    $url = route('faculty.proposal-drafts.submission-files.download', [$draft, 'expense-breakdown']);
    $review = $this->actingAs($this->faculty)->get(route('faculty.proposal-drafts.review', $draft))->assertOk();
    $dom = new DOMDocument;
    @$dom->loadHTML(str_replace('@click=', 'data-click=', $review->getContent()));
    $xpath = new DOMXPath($dom);
    $article = $xpath->query('//article[.//h4[normalize-space(.)="Estimated Expense Breakdown"]]')->item(0);
    expect($article)->not->toBeNull()
        ->and($xpath->query('.//a[@href="'.$url.'"]', $article)->length)->toBe(1);
    $preview = $xpath->query('.//button[normalize-space(.)="Preview prepared PDF"]', $article)->item(0);
    expect($preview)->not->toBeNull();
    preg_match("/JSON\\.parse\\('(.*)'\\)/", $preview->getAttribute('data-click'), $matches);
    $previewOptions = json_decode(json_decode('"'.$matches[1].'"', flags: JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
    expect($previewOptions['previewUrl'])->toBe($url);

    $previewResponse = $this->get($url)->assertOk();
    $downloadResponse = $this->get($url)->assertDownload($expense->original_filename);
    expect($previewResponse->streamedContent())->toBe(Storage::disk('local')->get($expense->file_path))
        ->and($downloadResponse->streamedContent())->toBe($previewResponse->streamedContent());
});

test('PDF preparation retries temporary failures within a durable limit and preserves recovery and submitted files', function () {
    Cache::flush();
    Queue::fake();
    $draft = ($this->completeDraft)(($this->createDraft)());
    $workPlan = $draft->documents->firstWhere('document_type', 'work_plan');
    app(RecordProposalDraftDocumentVersion::class)->handle($workPlan, $this->faculty);
    $protected = $workPlan->file_path;
    Storage::disk('local')->put('proposal-packages/submitted-history.pdf', '%PDF submitted');
    $draft->documents()->update(['file_path' => null]);
    app()->instance(DocumentPdfConverter::class, new class implements DocumentPdfConverter
    {
        public function convertDocx(string $contents): string
        {
            throw new RuntimeException('Conversion failed');
        }

        public function convertXlsx(string $contents): string
        {
            throw new RuntimeException('Conversion failed');
        }
    });
    $preparation = app(ProposalPdfPreparation::class);
    expect(fn () => $preparation->run($draft->id))->toThrow(RuntimeException::class);
    expect($preparation->status($draft->fresh())['status'])->toBe('retrying');
    Cache::flush();
    expect(fn () => $preparation->run($draft->id))->toThrow(RuntimeException::class);
    $preparation->run($draft->id);
    expect($preparation->status($draft->fresh())['status'])->toBe('failed')
        ->and($draft->fresh()->pdf_preparation_state['attempts'])->toBe(3);
    $preparation->run($draft->id);
    $preparation->reconcile();
    expect($draft->fresh()->pdf_preparation_state['attempts'])->toBe(3);
    Storage::disk('local')->assertExists($protected);
    Storage::disk('local')->assertExists('proposal-packages/submitted-history.pdf');
    $this->actingAs($this->otherFaculty)->postJson(route('faculty.proposal-drafts.submission-files.prepare', $draft))->assertForbidden();
});

test('PDF recovery keeps durable queued and expired reserved jobs and resumes them without changing editor versions', function () {
    $realQueue = Queue::getFacadeRoot();
    Queue::fake();
    $draft = ($this->completeDraft)(($this->createDraft)());
    $draft->documents()->where('document_type', 'curriculum_vitae')->update(['file_path' => null]);
    $before = $draft->fresh();
    $versions = $before->documents->pluck('lock_version', 'id')->all();
    DB::table('proposal_drafts')->where('id', $draft->id)->update(['pdf_preparation_state' => null]);
    Queue::swap($realQueue);
    $preparation = app(ProposalPdfPreparation::class);
    $preparation->request($draft->id);
    $runId = $draft->fresh()->pdf_preparation_state['run_id'];
    $this->travel(6)->seconds();
    $job = Queue::connection('proposal-pdfs')->pop('proposal-pdfs');
    expect($job)->not->toBeNull();
    $this->travel(11)->minutes();
    Cache::flush();
    $preparation->reconcile();
    expect($draft->fresh()->pdf_preparation_state['run_id'])->toBe($runId)
        ->and(DB::table('jobs')->where('queue', 'proposal-pdfs')->where('payload', 'like', '%'.$runId.'%')->count())->toBe(1)
        ->and($preparation->status($draft->fresh())['status'])->toBe('stalled');

    $recovered = Queue::connection('proposal-pdfs')->pop('proposal-pdfs');
    expect($recovered->getJobId())->toBe($job->getJobId());
    $recovered->fire();
    $after = $draft->fresh();
    expect($preparation->status($after)['status'])->toBe('ready')
        ->and($after->lock_version)->toBe($before->lock_version)
        ->and($after->updated_at->toISOString())->toBe($before->updated_at->toISOString())
        ->and($after->documents->pluck('lock_version', 'id')->all())->toBe($versions);
});

test('PDF recovery replaces missing jobs within a bounded limit and only explicit retry resets exhaustion', function () {
    Queue::fake();
    $draft = ($this->completeDraft)(($this->createDraft)());
    $draft->documents()->where('document_type', 'curriculum_vitae')->update(['file_path' => null]);
    $preparation = app(ProposalPdfPreparation::class);
    $preparation->request($draft->id);
    $obsoleteRun = $draft->fresh()->pdf_preparation_state['run_id'];
    foreach (range(1, 4) as $recovery) {
        $this->travel(11)->minutes();
        Cache::flush();
        $preparation->reconcile();
    }
    Queue::assertPushed(PrepareProposalDraftPdfs::class, 4);
    expect($preparation->status($draft->fresh())['status'])->toBe('failed');
    $preparation->run($draft->id, $obsoleteRun);
    $this->actingAs($this->faculty)->postJson(route('faculty.proposal-drafts.submission-files.prepare', $draft))
        ->assertAccepted()->assertJsonPath('preparation.status', 'failed');
    Queue::assertPushed(PrepareProposalDraftPdfs::class, 4);
    $this->postJson(route('faculty.proposal-drafts.submission-files.prepare', $draft), ['retry' => true])
        ->assertAccepted()->assertJsonPath('preparation.status', 'queued');
    Queue::assertPushed(PrepareProposalDraftPdfs::class, 5);
    expect($draft->fresh()->pdf_preparation_state['recoveries'])->toBe(0);
});

test('the PDF worker backs off a temporary conversion failure and automatically finishes the same generation', function () {
    $realQueue = Queue::getFacadeRoot();
    Queue::fake();
    $draft = ($this->completeDraft)(($this->createDraft)());
    $draft->documents()->where('document_type', 'curriculum_vitae')->update(['file_path' => null]);
    DB::table('proposal_drafts')->where('id', $draft->id)->update(['pdf_preparation_state' => null]);
    Queue::swap($realQueue);
    app()->instance(DocumentPdfConverter::class, new class implements DocumentPdfConverter
    {
        private bool $interrupted = false;

        public function convertDocx(string $contents): string
        {
            if (! $this->interrupted) {
                $this->interrupted = true;
                throw new RuntimeException('Temporary converter interruption');
            }

            return "%PDF-1.7\n".hash('sha256', $contents);
        }

        public function convertXlsx(string $contents): string
        {
            return $this->convertDocx($contents);
        }
    });
    $preparation = app(ProposalPdfPreparation::class);
    $preparation->request($draft->id);
    $runId = $draft->fresh()->pdf_preparation_state['run_id'];
    $originalJobId = DB::table('jobs')->where('queue', 'proposal-pdfs')->value('id');
    $this->travel(31)->minutes();
    $worker = app('queue.worker');
    $options = new WorkerOptions(sleep: 0, maxTries: 0);
    $worker->runNextJob('proposal-pdfs', 'proposal-pdfs', $options);
    $retry = DB::table('jobs')->where('queue', 'proposal-pdfs')->first();
    expect($preparation->status($draft->fresh())['status'])->toBe('retrying')
        ->and($retry->available_at)->toBe(now()->timestamp + 30)
        ->and($retry->id)->not->toBe($originalJobId);
    $this->travel(11)->minutes();
    Cache::flush();
    $preparation->reconcile();
    expect($draft->fresh()->pdf_preparation_state['run_id'])->toBe($runId)
        ->and(DB::table('jobs')->where('queue', 'proposal-pdfs')->count())->toBe(1);
    $worker->runNextJob('proposal-pdfs', 'proposal-pdfs', $options);
    expect($preparation->status($draft->fresh())['status'])->toBe('ready')
        ->and($draft->fresh()->pdf_preparation_state['attempts'])->toBe(2)
        ->and(DB::table('jobs')->where('queue', 'proposal-pdfs')->count())->toBe(0);
});

test('PDF validation failure is terminal until saved content changes', function () {
    Queue::fake();
    $draft = ($this->completeDraft)(($this->createDraft)());
    $document = $draft->documents->firstWhere('document_type', 'curriculum_vitae');
    $document->updateQuietly(['file_path' => null]);
    $preparation = app(ProposalPdfPreparation::class);
    $preparation->request($draft->id);
    $submit = Mockery::mock(SubmitProposalDraft::class);
    $submit->shouldReceive('prepare')->once()->andThrow(ValidationException::withMessages(['paper' => 'Invalid paper content.']));
    app()->instance(SubmitProposalDraft::class, $submit);
    $preparation->run($draft->id);
    $this->travel(11)->minutes();
    Cache::flush();
    $preparation->reconcile();
    expect($preparation->status($draft->fresh())['status'])->toBe('failed');
    Queue::assertPushed(PrepareProposalDraftPdfs::class, 1);
    $document->updateQuietly(['lock_version' => $document->lock_version + 1]);
    $preparation->request($draft->id);
    expect($preparation->status($draft->fresh())['status'])->toBe('queued');
    Queue::assertPushed(PrepareProposalDraftPdfs::class, 2);
});

test('PDF generation lock contention releases the job without spending a conversion attempt', function () {
    Queue::fake();
    $draft = ($this->completeDraft)(($this->createDraft)());
    $draft->documents()->where('document_type', 'curriculum_vitae')->update(['file_path' => null]);
    $preparation = app(ProposalPdfPreparation::class);
    $preparation->request($draft->id);
    $runId = $draft->fresh()->pdf_preparation_state['run_id'];
    $this->travel(6)->seconds();
    $lock = Cache::lock('proposal-pdf-generation:'.$draft->id, 600);
    expect($lock->get())->toBeTrue();
    try {
        $job = (new PrepareProposalDraftPdfs($draft->id, $runId))->withFakeQueueInteractions();
        $job->handle($preparation);
        $job->assertReleased(15);
        expect($draft->fresh()->pdf_preparation_state['attempts'])->toBe(0)
            ->and($preparation->status($draft->fresh())['status'])->toBe('retrying');
    } finally {
        $lock->release();
    }
});

test('a missing working PDF cannot be reported ready from persisted preparation state', function () {
    Queue::fake();
    $draft = ($this->completeDraft)(($this->createDraft)());
    $preparation = app(ProposalPdfPreparation::class);
    $preparation->run($draft->id);
    Storage::disk('local')->delete($draft->documents->firstWhere('document_type', 'curriculum_vitae')->file_path);
    expect($preparation->status($draft->fresh())['status'])->toBe('needed');
});

test('only the dedicated PDF worker records its real polling heartbeat', function () {
    Cache::forget('proposal-pdfs:worker-heartbeat');
    event(new JobPopping('database', 'default'));
    expect(Cache::has('proposal-pdfs:worker-heartbeat'))->toBeFalse();
    event(new JobPopping('proposal-pdfs', 'proposal-pdfs'));
    expect(Cache::get('proposal-pdfs:worker-heartbeat'))->toBe(now()->timestamp);
});

test('an interrupted third PDF conversion cannot spend another attempt when its reserved job resumes', function () {
    Queue::fake();
    $draft = ($this->completeDraft)(($this->createDraft)());
    $draft->documents()->where('document_type', 'curriculum_vitae')->update(['file_path' => null]);
    $preparation = app(ProposalPdfPreparation::class);
    $preparation->request($draft->id);
    $state = [...$draft->fresh()->pdf_preparation_state, 'status' => 'preparing', 'attempts' => 3];
    DB::table('proposal_drafts')->where('id', $draft->id)->update(['pdf_preparation_state' => json_encode($state, JSON_THROW_ON_ERROR)]);
    $preparation->run($draft->id, $state['run_id']);
    expect($preparation->status($draft->fresh())['status'])->toBe('failed')
        ->and($draft->fresh()->pdf_preparation_state['attempts'])->toBe(3)
        ->and($draft->fresh()->documents->firstWhere('document_type', 'curriculum_vitae')->file_path)->toBeNull();
});

test('cleanup failure cannot delete committed prepared PDFs', function () {
    Queue::fake();
    $draft = ($this->createDraft)();
    $cleanup = Mockery::mock(ProposalPdfPreparation::class, [app(ProposalDraftReadiness::class)])->makePartial();
    $cleanup->shouldReceive('prune')->andThrow(new RuntimeException('Storage listing unavailable'));
    app()->instance(ProposalPdfPreparation::class, $cleanup);
    $draft = ($this->completeDraft)($draft);
    expect(app(ProposalDraftReadiness::class)->submissionFilesArePrepared($draft->fresh()))->toBeTrue();
    foreach ($draft->fresh()->documents as $document) {
        Storage::disk('local')->assertExists($document->file_path);
    }
});

test('PDF cleanup preserves manual replacements that are waiting to be saved', function () {
    Queue::fake();
    $draft = ($this->completeDraft)(($this->createDraft)());
    $directory = $draft->storageDirectory().'/prepared';
    $replacement = $directory.'/manual/detailed-proposal/upload-in-progress.pdf';
    $obsolete = $directory.'/obsolete-generation/detailed-proposal.pdf';
    Storage::disk('local')->put($replacement, '%PDF manual replacement');
    Storage::disk('local')->put($obsolete, '%PDF obsolete generation');

    app(ProposalPdfPreparation::class)->prune($draft);

    Storage::disk('local')->assertExists($replacement);
    Storage::disk('local')->assertMissing($obsolete);
    foreach ($draft->documents as $document) {
        Storage::disk('local')->assertExists($document->file_path);
    }
});

test('editing a CV invalidates its working PDF without rebuilding unrelated papers', function () {
    Queue::fake();
    $draft = ($this->completeDraft)(($this->createDraft)());
    $paths = $draft->documents->pluck('file_path', 'document_type')->all();
    $document = $draft->documents->firstWhere('document_type', 'curriculum_vitae');
    $source = $document->source_data;
    $source['people'][0]['agency'] = 'Updated agency';
    app(SaveProposalDraftDocument::class)->handle($draft, $this->faculty, 'curriculum_vitae', 0, $document->lock_version, [
        'source_data' => $source, 'completed_at' => now(),
    ]);
    $current = $draft->fresh()->documents->keyBy('document_type');
    expect($current['curriculum_vitae']->file_path)->toBeNull()
        ->and($current['work_plan']->file_path)->toBe($paths['work_plan'])
        ->and($current['line_item_budget']->file_path)->toBe($paths['line_item_budget']);
    app(ProposalPdfPreparation::class)->run($draft->id);
    expect($current['curriculum_vitae']->fresh()->file_path)->not->toBeNull();
});

test('an edit made during PDF conversion wins and the obsolete conversion is removed', function () {
    Queue::fake();
    $draft = ($this->completeDraft)(($this->createDraft)());
    $document = $draft->documents->firstWhere('document_type', 'curriculum_vitae');
    $source = $document->source_data;
    $source['people'][0]['agency'] = 'First edit';
    app(SaveProposalDraftDocument::class)->handle($draft, $this->faculty, 'curriculum_vitae', 0, $document->lock_version, ['source_data' => $source]);
    app()->instance(DocumentPdfConverter::class, new class($document) implements DocumentPdfConverter
    {
        private bool $edited = false;

        public function __construct(private ProposalDraftDocument $document) {}

        public function convertDocx(string $contents): string
        {
            if (! $this->edited) {
                $this->edited = true;
                $latest = $this->document->fresh();
                $source = $latest->source_data;
                $source['people'][0]['agency'] = 'Newest edit during preparation';
                $latest->update(['source_data' => $source, 'lock_version' => $latest->lock_version + 1]);
            }

            return "%PDF-1.7\n".hash('sha256', $contents);
        }

        public function convertXlsx(string $contents): string
        {
            return $this->convertDocx($contents);
        }
    });
    app(ProposalPdfPreparation::class)->run($draft->id);
    expect($document->fresh()->source_data['people'][0]['agency'])->toBe('Newest edit during preparation')
        ->and($document->fresh()->file_path)->toBeNull();
    app(ProposalPdfPreparation::class)->run($draft->id);
    expect($document->fresh()->source_data['people'][0]['agency'])->toBe('Newest edit during preparation')
        ->and($document->fresh()->file_path)->not->toBeNull();
});

test('submission readiness guides only authorized editors to precise missing fields', function () {
    $draft = ($this->createDraft)();
    $response = $this->actingAs($this->faculty)
        ->getJson(route('faculty.proposal-drafts.review', $draft))
        ->assertOk()
        ->assertJsonPath('status', 'incomplete')
        ->assertJsonPath('can_guide', true);
    $duration = collect($response->json('blockers'))->firstWhere('field', 'duration_months');
    expect($duration['paper'])->toBe('details')
        ->and($duration['message'])->toContain('duration')
        ->and($duration['url'])->toBe(route('faculty.proposal-drafts.show', [
            'proposalDraft' => $draft, 'proposal_requirement' => 'duration_months',
        ]).'#project-details');

    $this->actingAs($this->otherFaculty)->getJson(route('faculty.proposal-drafts.review', $draft))->assertForbidden();

    $draft->update(['status' => ProposalDraft::STATUS_SUBMITTING]);
    $locked = $this->actingAs($this->faculty)->getJson(route('faculty.proposal-drafts.review', $draft))
        ->assertOk()->assertJsonPath('can_guide', false);
    expect(collect($locked->json('blockers'))->every(fn (array $blocker): bool => $blocker['url'] === null))->toBeTrue();
});

test('submission readiness separates PDF preparation and office requirements from missing content', function () {
    $draft = ($this->completeDraft)(($this->createDraft)());
    $this->actingAs($this->faculty)
        ->getJson(route('faculty.proposal-drafts.review', $draft))
        ->assertOk()->assertJsonPath('status', 'ready')->assertJsonPath('ready_to_submit', true)
        ->assertJsonCount(0, 'blockers');

    $draft->documents()->update(['file_path' => null, 'mime_type' => null]);
    $this->getJson(route('faculty.proposal-drafts.review', $draft))
        ->assertOk()->assertJsonPath('status', 'prepare')->assertJsonPath('ready_to_prepare', true)
        ->assertJsonPath('ready_to_submit', false)->assertJsonCount(0, 'blockers');

    $draft->update(['signatory_selections' => []]);
    ProposalSignatory::query()->delete();
    config(['work_plan.verifier.name' => '', 'notice_to_proceed.issuing_officer.name' => '']);
    $office = $this->getJson(route('faculty.proposal-drafts.review', $draft))
        ->assertOk()->assertJsonPath('status', 'office')->assertJsonPath('ready_to_prepare', false);
    expect(collect($office->json('blockers'))->every(fn (array $blocker): bool => $blocker['owner'] === 'research_office' && $blocker['url'] === null))->toBeTrue();
});

test('automatic submission guidance is connected to the hub and every paper editor', function () {
    $draft = ($this->createDraft)();
    $this->actingAs($this->faculty)->get(route('faculty.proposal-drafts.show', $draft))
        ->assertOk()->assertSee('data-proposal-readiness-continue', false)
        ->assertSee('data-proposal-paper="details"', false)
        ->assertSee('Complete these fields')
        ->assertDontSee('Open section');

    foreach (['detailed-proposal', 'work-plan', 'line-item-budget', 'expense-breakdown', 'curriculum-vitae'] as $paper) {
        $this->get(route('faculty.proposal-drafts.'.$paper.'.edit', ['proposalDraft' => $draft, 'proposal_requirement' => '__paper__']))
            ->assertOk()->assertSee('data-proposal-paper="'.$paper.'"', false)
            ->assertSee('data-proposal-readiness-label', false);
    }
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
        ->assertSee('Preview prepared PDF')
        ->assertSee('Project team')
        ->assertSee('Five proposal papers and two automatic assessment forms are ready')
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

test('review previews all seven proposal papers in the shared viewer without opening tabs', function (bool $prepared) {
    $draft = ($this->completeDraft)(($this->createDraft)());
    if (! $prepared) {
        $draft->documents()->update(['file_path' => null, 'mime_type' => null]);
    }
    $response = $this->actingAs($this->faculty)->get(route('faculty.proposal-drafts.review', $draft))->assertOk();
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    expect($xpath->query('//*[@data-proposal-review-package and @x-data="proposalAssessmentPreview()"]')->length)->toBe(1)
        ->and($xpath->query('//*[@id="review-paper-preview-panel"]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-proposal-review-package]//form[@target="_blank"]')->length)->toBe(0)
        ->and($xpath->query('//*[@data-review-assessment-forms]//button[@data-assessment-preview-trigger]')->length)->toBe(2);
    if ($prepared) {
        expect($xpath->query('//*[@data-proposal-review-package]//button[normalize-space(.)="Preview prepared PDF"]')->length)->toBe(5);
    } else {
        expect($xpath->query('//*[@data-proposal-review-package]//form[@data-preview-label]')->length)->toBe(5);
        expect(substr_count($response->getContent(), '@submit.prevent="openPaperPreview($el, $event.submitter)"'))->toBe(5);
    }
    $response->assertSee('Preview GAD Generic Checklist')->assertSee('Preview Initial Screening Form');
})->with(['saved forms' => false, 'prepared PDFs' => true]);

test('draft attachment previews use stored prepared PDFs without changing required paper actions', function (string $state) {
    $draft = ($this->completeDraft)(($this->createDraft)());
    $document = $draft->documents->firstWhere('document_type', ProposalVersionFile::TYPE_DETAILED_PROPOSAL);

    if ($state === 'saved forms') {
        $draft->documents()->update(['file_path' => null, 'mime_type' => null]);
    } elseif ($state === 'missing PDF') {
        Storage::disk('local')->delete($document->file_path);
    } elseif ($state === 'wrong format') {
        $document->update(['mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']);
    }

    $response = $this->actingAs($this->faculty)->get(route('faculty.proposal-drafts.show', $draft))->assertOk();
    $index = $this->get(route('faculty.proposal-drafts.index'))->assertOk();

    if ($state === 'prepared PDFs' && getenv('ATHENA_EXPORT_PROPOSAL_CONSISTENCY') === '1') {
        File::ensureDirectoryExists(storage_path('framework/testing'));
        File::put(storage_path('framework/testing/proposal-consistency-draft.html'), $response->getContent());
        File::put(storage_path('framework/testing/proposal-consistency-drafts.html'), $index->getContent());
    }

    $dom = new DOMDocument;
    @$dom->loadHTML($response->getContent());
    $xpath = new DOMXPath($dom);

    expect($xpath->query('//*[@data-editable-proposal-papers]//article[@data-proposal-paper]')->length)->toBe(5)
        ->and($xpath->query('//*[@data-editable-proposal-papers]//*[@data-document-file-icon]')->length)->toBe(5)
        ->and($xpath->query('//*[@data-editable-proposal-papers]//a[starts-with(@aria-label, "Open ")]')->length)->toBe(5)
        ->and($xpath->query('//*[@data-editable-proposal-papers]//button[@data-proposal-paper-preview-trigger]')->length)->toBe($state === 'prepared PDFs' ? 5 : 0)
        ->and($xpath->query('//*[@id="draft-paper-preview-panel"]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-automatic-assessment-forms]//button[@data-assessment-preview-trigger]')->length)->toBe(2)
        ->and($xpath->query('//*[@data-automatic-assessment-forms]//*[@data-document-file-icon]')->length)->toBe(2);

    $index
        ->assertSee('data-document-file-icon', false)
        ->assertSee('Resume')
        ->assertSee('Delete');

    if ($state === 'prepared PDFs') {
        $previewUrl = route('faculty.proposal-drafts.submission-files.download', [$draft, 'detailed-proposal']);
        $response->assertSee('Prepared PDF');
        $this->get($previewUrl)->assertDownload($document->original_filename)->assertHeader('Content-Type', 'application/pdf');
    } else {
        $response->assertSee('Submission PDFs not fully prepared')->assertDontSee('Preview PDF');
    }
})->with(['prepared PDFs', 'saved forms', 'missing PDF', 'wrong format']);

test('prepared draft previews preserve collaborator access and locked editing', function () {
    $draft = ($this->completeDraft)(($this->createDraft)());
    $draft->members()->create([
        'user_id' => $this->otherFaculty->id,
        'name' => $this->otherFaculty->name,
        'email' => $this->otherFaculty->email,
        'accepted_at' => now(),
    ]);
    $draft->update(['status' => ProposalDraft::STATUS_SUBMITTING]);
    $previewUrl = route('faculty.proposal-drafts.submission-files.download', [$draft, 'detailed-proposal']);

    $this->actingAs($this->otherFaculty)->get(route('faculty.proposal-drafts.show', $draft))
        ->assertOk()
        ->assertSee('data-proposal-paper-preview-trigger', false)
        ->assertSee('Editing locked')
        ->assertDontSee('>Open Research Proposal</a>', false);
    $this->get($previewUrl)->assertOk();
    $this->actingAs($this->head)->get($previewUrl)->assertForbidden();
});

test('proposal dates use only their linked published implementation schedule', function (string $callState, bool $hasSchedule) {
    $this->call->update([
        'status' => $callState === 'unlinked' ? 'open' : $callState,
        'implementation_start_date' => '2027-02-01',
        'implementation_end_date' => '2027-08-01',
        'initial_evaluation_start_date' => '2027-01-11',
        'paper_revisions_end_date' => '2027-01-25',
    ]);
    $draft = ($this->createDraft)([
        'research_call_id' => $callState === 'unlinked' ? null : $this->call->id,
    ]);

    foreach (['faculty.proposal-drafts.show', 'faculty.proposal-drafts.details.edit'] as $route) {
        $response = $this->actingAs($this->faculty)->get(route($route, $draft))->assertOk();
        if ($hasSchedule) {
            $response->assertSee('2027-02-01')->assertSee('2027-08-01')
                ->assertSee('Implementation begins')->assertSee('Implementation ends');
        } else {
            $response->assertDontSee('2027-02-01')->assertDontSee('2027-08-01')
                ->assertDontSee('Implementation begins')->assertDontSee('Implementation ends');
        }
        $response->assertDontSee('2027-01-11')->assertDontSee('2027-01-25');
    }

    $this->putJson(route('faculty.proposal-drafts.details.update', $draft), ($this->projectDetails)([
        'planned_start' => '2027-01-01',
        'planned_end' => '2028-01-01',
    ]))->assertOk()->assertJsonPath('project_details_complete', true);
    expect($draft->fresh()->planned_start->toDateString())->toBe('2027-01-01');
})->with([
    'published schedule' => ['open', true],
    'closed linked call' => ['closed', true],
    'unpublished schedule' => ['draft', false],
    'unlinked draft' => ['unlinked', false],
]);

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
        ->assertJsonPath('draft_version', 2)
        ->assertJsonPath('project_details_complete', true);

    $this->actingAs($this->faculty)
        ->putJson(route('faculty.proposal-drafts.details.update', $draft), ($this->projectDetails)([
            'draft_version' => 2,
            'project_title' => 'Automatically Saved Project Details',
            'duration_months' => 18,
            'planned_end' => '2028-01-31',
        ]))
        ->assertOk()
        ->assertJsonPath('draft_version', 2)
        ->assertJsonPath('project_details_complete', true);

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
        ->assertDontSee('Project Leader / Prepared by')
        ->assertSee('Each 12-month block becomes a matching Attachment A year sheet.');

    $this->get(route('faculty.proposal-drafts.show', $draft))->assertOk()
        ->assertDontSee('Save protection is on.')
        ->assertDontSee('Choose a workspace member or type a name.')
        ->assertDontSee('Prepared by');
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
        ->assertSee('Coastal Habitat Restoration')
        ->assertSee('Added automatically to your submission.')
        ->assertSee('proposalAssessmentPreview(', false)
        ->assertSee('gad-checklist-preview-panel')
        ->assertSee('Download Word')
        ->assertDontSee('data-proposal-preview-drag-handle', false)
        ->assertDontSee('moved, resized')
        ->assertDontSee('Open full preview')
        ->assertDontSee('Download Word file')
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
        ->assertSee('Coastal Habitat Restoration')
        ->assertSee('Added automatically to your submission.')
        ->assertSee('Assessment is completed during review.')
        ->assertSee('proposalAssessmentPreview(', false)
        ->assertSee('initial-screening-form-preview-panel')
        ->assertSee('Download Word')
        ->assertDontSee('data-proposal-preview-drag-handle', false)
        ->assertDontSee('moved, resized')
        ->assertDontSee('Open full preview')
        ->assertDontSee('Download Word file')
        ->assertDontSee('data-paper-shortcuts-trigger', false)
        ->assertDontSee('data-paper-editor', false)
        ->assertDontSee('data-paper-save', false)
        ->assertDontSee('name="project_title"', false)
        ->assertDontSee('name="project_leader"', false);

    $this->actingAs($this->faculty)
        ->get(route('faculty.proposal-drafts.initial-screening-form.preview', $draft))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertSee('%PDF-1.7');

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
            ->and($documentXPath->query('//w:p[normalize-space(.) = "NAME"]')->length)->toBe(0)
            ->and($draft->fresh()->topic_id)->toBeNull()
            ->and(app(InitialScreeningSubmissionOrder::class)->forDraft($draft->fresh()))->toBe(InitialScreeningSubmissionOrder::FIRST_SUBMISSION);

        foreach (['screening_head', 'screening_center', 'screening_verifier'] as $field) {
            $name = mb_strtoupper($draft->signatoryFields('initial_screening_form')[$field]);
            expect($documentDom->textContent)->toContain($name)
                ->and($documentXPath->query('//w:p[normalize-space(.) = "'.$name.'"]/w:r/w:rPr/w:u[@w:val = "single"]')->length)->toBe(1)
                ->and($documentXPath->query('//w:p[normalize-space(.) = "'.$name.'"]/preceding-sibling::w:p[1][not(normalize-space(.))]')->length)->toBe(1);
        }

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
    $proposal = $draft->documents()->create([
        'document_type' => ProposalVersionFile::TYPE_DETAILED_PROPOSAL,
        'position' => 0,
        'source_data' => ['specific_objectives' => [['description' => 'Document the baseline habitat condition']]],
    ]);
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
    $proposal->update(['source_data' => ['specific_objectives' => [
        ['description' => 'Document the baseline habitat condition'],
        ['description' => 'Validate the restoration approach'],
    ]]]);

    $this->actingAs($this->faculty)
        ->put(route('faculty.proposal-drafts.work-plan.update', $draft), $overlappingWorkPlan)
        ->assertSessionHasErrors('entries.1.months');

    $workPlan = ($this->workPlan)();
    $proposal->update(['source_data' => ['specific_objectives' => [['description' => 'Document the baseline habitat condition']]]]);

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

test('initial submission uses default comments form signatories when a selection is missing', function (string $missingRole) {
    $draft = ($this->completeDraft)(($this->createDraft)());
    $selections = $draft->signatory_selections;
    unset($selections[$missingRole]);
    $draft->update(['signatory_selections' => $selections]);

    expect(app(ProposalDraftReadiness::class)->isReady($draft->fresh()))->toBeTrue();
    $this->actingAs($this->faculty)
        ->post(route('faculty.proposal-drafts.submit', $draft))
        ->assertSessionHasNoErrors();
    expect(TopicProposal::query()->count())->toBe(1);
    $selections = TopicProposal::query()->sole()->latestVersion->files
        ->firstWhere('document_type', 'detailed_proposal')->source_data['comment_response_signatory_selections'];
    expect($selections['comment_response_head']['name'])->toBe('Asst. Prof. DJOANNA MARIE V. SALAC')
        ->and($selections['comment_response_vice_chancellor']['name'])->toBe('Dr. FROILAN G. DESTREZA');
})->with(['comment_response_head', 'comment_response_vice_chancellor']);

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
        ->assertSee('3 of 5 proposal papers ready')
        ->assertSeeTextInOrder([
            'Estimated Expense Breakdown',
            'Needs attention',
            'Attachment B: Line-Item Budget',
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

test('the project leader prepares all seven PDFs with three converter startups', function () {
    $draft = ($this->completeDraft)(($this->createDraft)());
    app()->bind(DocumentPdfConverter::class, LibreOfficeDocumentPdfConverter::class);
    Process::preventStrayProcesses();
    Process::fake(function (PendingProcess $process) {
        $outputIndex = array_search('--outdir', $process->command, true);
        expect($outputIndex)->not->toBeFalse();
        $directory = $process->command[$outputIndex + 1];
        foreach ([...glob($directory.'/*.docx'), ...glob($directory.'/*.xlsx')] as $source) {
            File::put($directory.'/'.pathinfo($source, PATHINFO_FILENAME).'.pdf', "%PDF-1.7\n".hash_file('sha256', $source));
        }

        return Process::result();
    });

    $this->actingAs($this->faculty)->post(route('faculty.proposal-drafts.submission-files.prepare', $draft))
        ->assertRedirect()->assertSessionHasNoErrors();
    $draft->refresh()->load('documents');

    expect($draft->documents)->toHaveCount(7)
        ->and(app(ProposalDraftReadiness::class)->submissionFilesArePrepared($draft))->toBeTrue()
        ->and($draft->documents->firstWhere('document_type', ProposalVersionFile::TYPE_DETAILED_PROPOSAL)->source_data['document_checklist'])
        ->toBe(['complete_documents' => true, 'initial_screening_form' => true]);
    Process::assertRanTimes(fn (PendingProcess $process): bool => true, 3);
});

test('batch preparation discards new PDFs if a teammate changes the draft during conversion', function (bool $changeProjectDetails) {
    $draft = ($this->completeDraft)(($this->createDraft)());
    $originalPaths = $draft->documents->pluck('file_path', 'id')->all();
    $originalFiles = Storage::disk('local')->allFiles($draft->storageDirectory());
    $calls = 0;
    app()->bind(DocumentPdfConverter::class, LibreOfficeDocumentPdfConverter::class);
    Process::preventStrayProcesses();
    Process::fake(function (PendingProcess $process) use ($draft, $changeProjectDetails, &$calls) {
        if ($calls++ === 0) {
            if ($changeProjectDetails) {
                $draft->fresh()->increment('lock_version');
            } else {
                $draft->documents()->where('document_type', ProposalVersionFile::TYPE_WORK_PLAN)->increment('lock_version');
            }
        }
        $outputIndex = array_search('--outdir', $process->command, true);
        $directory = $process->command[$outputIndex + 1];
        foreach ([...glob($directory.'/*.docx'), ...glob($directory.'/*.xlsx')] as $source) {
            File::put($directory.'/'.pathinfo($source, PATHINFO_FILENAME).'.pdf', '%PDF-1.7 new paper');
        }

        return Process::result();
    });

    expect(fn () => app(SubmitProposalDraft::class)->prepare($draft->fresh(), $this->faculty))
        ->toThrow(ValidationException::class)
        ->and($draft->documents()->pluck('file_path', 'id')->all())->toBe($originalPaths)
        ->and(Storage::disk('local')->allFiles($draft->storageDirectory()))->toBe($originalFiles);
})->with(['project details' => true, 'proposal paper' => false]);

test('prepared proposal checklist verifies its PDFs and uses the campus call default', function () {
    $draft = ($this->completeDraft)(($this->createDraft)());
    $proposal = $draft->documents->firstWhere('document_type', ProposalVersionFile::TYPE_DETAILED_PROPOSAL);
    expect($proposal->source_data['document_checklist'])->toBe([
        'complete_documents' => true, 'initial_screening_form' => true,
    ]);
    $screening = $draft->documents->firstWhere('document_type', ProposalVersionFile::TYPE_INITIAL_SCREENING_FORM);
    expect($screening->source_data['level_of_call'])->toBe('constituent_campus');

    $this->actingAs($this->faculty)
        ->post(route('faculty.proposal-drafts.detailed-proposal.preview', $draft), [
            'document_version' => $proposal->lock_version,
        ])->assertOk()->assertSeeText('☒ Complete Documents')->assertSeeText('☒ Initial Screening Form')
        ->assertSeeText('☒ Constituent Campus');

    $workPlan = $draft->documents->firstWhere('document_type', ProposalVersionFile::TYPE_WORK_PLAN);
    Storage::disk('local')->delete($workPlan->file_path);
    expect(app(ProposalDraftReadiness::class)->detailedProposalChecklist($draft))->toBe([
        'complete_documents' => false, 'initial_screening_form' => true,
    ]);
    Storage::disk('local')->delete($screening->file_path);
    expect(app(ProposalDraftReadiness::class)->detailedProposalChecklist($draft))->toBe([
        'complete_documents' => false, 'initial_screening_form' => false,
    ]);

    app(SubmitProposalDraft::class)->prepare($draft->fresh(), $this->faculty);
    $topic = app(SubmitProposalDraft::class)->handle($draft->fresh(), $this->faculty);
    $submittedProposal = $topic->versions()->sole()->files()
        ->where('document_type', ProposalVersionFile::TYPE_DETAILED_PROPOSAL)->sole();
    expect($submittedProposal->source_data['document_checklist'])->toBe([
        'complete_documents' => true, 'initial_screening_form' => true,
    ])->and(Storage::disk('local')->exists($submittedProposal->file_path))->toBeTrue();
});

test('prepared Work Plan objectives follow the latest Detailed Proposal wording', function () {
    $draft = ($this->completeDraft)(($this->createDraft)());
    $proposal = $draft->documents->firstWhere('document_type', ProposalVersionFile::TYPE_DETAILED_PROPOSAL);
    $objective = str_repeat('Assess coastal habitat recovery. ', 20);
    $proposal->update(['source_data' => [
        ...$proposal->source_data,
        'specific_objectives' => [['description' => trim($objective)]],
    ]]);
    app(SubmitProposalDraft::class)->prepare($draft->fresh(), $this->faculty);
    $workPlan = $draft->documents()->where('document_type', ProposalVersionFile::TYPE_WORK_PLAN)->sole();
    expect($workPlan->source_data['entries'][0]['objective'])->toBe(trim($objective))
        ->and($workPlan->source_data['entries'][0]['activity'])->toBe("Conduct field survey\nComplete community mapping");
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

test('the review modal refreshes when automatic PDFs finish for initial and unseen updates', function (bool $isUpdate) {
    Notification::fake();
    Queue::fake();
    $draft = ($this->completeDraft)(($this->createDraft)());
    if ($isUpdate) {
        $topic = app(SubmitProposalDraft::class)->handle($draft, $this->faculty);
        $draft = app(CreateProposalRevisionDraft::class)->handle($topic, $this->faculty);
    }
    $draft->documents()->update([
        'file_path' => null,
        'original_filename' => null,
        'mime_type' => null,
        'file_size' => null,
        'checksum' => null,
    ]);

    $component = Livewire::actingAs($this->faculty)
        ->test(ProposalDraftReviewPackage::class, [
            'proposalDraft' => $draft,
            'inModal' => true,
        ])
        ->assertSee('Submission PDFs update automatically')
        ->assertDontSee('Prepare seven PDFs');

    $this->actingAs($this->faculty)
        ->postJson(route('faculty.proposal-drafts.submission-files.prepare', $draft))
        ->assertAccepted()
        ->assertJsonPath('preparation.status', 'queued');
    Queue::assertPushed(PrepareProposalDraftPdfs::class);
    app(ProposalPdfPreparation::class)->run($draft->id);

    $component->call('$refresh')
        ->assertHasNoErrors()
        ->assertNoRedirect()
        ->assertSee('Submission PDFs are up to date.')
        ->assertSee('Ready to turn in');

    if ($isUpdate) {
        $component->assertSee('Submit Version 2');
        expect($topic->versions()->count())->toBe(1);
    } else {
        $component->assertSee('Turn in proposal');
    }

    expect(app(ProposalDraftReadiness::class)->submissionFilesArePrepared($draft->fresh()))->toBeTrue();
})->with(['initial draft' => false, 'unseen submission update' => true]);

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
        ->and(TopicProposal::query()->count())->toBe(1)
        ->and(session('success'))->toBe('Proposal submitted successfully with seven PDFs and sent to the Research Head.');
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

test('faculty reviews five proposal papers while two generated assessment forms remain read only', function () {
    Process::fake(['*pdf-section-coordinates*' => Process::result(output: '<document/>')]);

    $draft = ($this->completeDraft)(($this->createDraft)());

    expect($draft->documents)->toHaveCount(7)
        ->and($draft->documents->every(fn ($document): bool => $document->mime_type === 'application/pdf'))
        ->toBeTrue()
        ->and(app(ProposalDraftReadiness::class)->submissionFilesArePrepared($draft))->toBeTrue();

    $this->actingAs($this->faculty)
        ->get(route('faculty.proposal-drafts.show', $draft))
        ->assertOk()
        ->assertSee('Loading proposal review')
        ->assertDontSee('Choose replacement PDF');

    $this->get(route('faculty.proposal-drafts.review', $draft))
        ->assertOk()
        ->assertSee('Prepared PDF ready')
        ->assertSee('Download prepared PDF')
        ->assertSee('Choose replacement PDF')
        ->assertSee('Five proposal papers and two automatic assessment forms are ready')
        ->assertSee('data-review-assessment-forms', false)
        ->assertSee('no faculty answers or replacement uploads are needed');

    $workspace = $this->actingAs($this->faculty)
        ->get(route('faculty.proposal-drafts.review', $draft));

    expect(substr_count($workspace->getContent(), 'Choose replacement PDF'))->toBe(5);

    foreach (['gad-checklist', 'initial-screening-form'] as $automaticForm) {
        $automaticDocument = $draft->documents->firstWhere(
            'document_type',
            app(ProposalPaperCatalog::class)->find($automaticForm)['document_type'],
        );
        $this->actingAs($this->faculty)
            ->put(route('faculty.proposal-drafts.submission-files.replace', [$draft, $automaticForm]), [
                'document_version' => $automaticDocument->lock_version,
                'file' => UploadedFile::fake()->create('replacement.pdf', 10, 'application/pdf'),
            ])
            ->assertNotFound();
        expect($automaticDocument->fresh()->original_filename)->toBe($automaticDocument->original_filename);
    }

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
        ->and($draft->documents()->whereNull('file_path')->pluck('document_type')->all())
        ->toEqualCanonicalizing([
            ProposalVersionFile::TYPE_EXPENSE_BREAKDOWN,
            ProposalVersionFile::TYPE_LINE_ITEM_BUDGET,
            ProposalVersionFile::TYPE_DETAILED_PROPOSAL,
        ])
        ->and($draft->documents()->whereNotNull('file_path')->count())->toBe(4);

    $this->actingAs($this->faculty)
        ->post(route('faculty.proposal-drafts.submission-files.prepare', $draft))
        ->assertRedirect(route('faculty.proposal-drafts.show', $draft))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'Seven PDFs prepared. Review the five proposal papers; the two assessment forms are included automatically.');

    $refreshedLineItemBudget = $draft->documents()
        ->where('document_type', ProposalVersionFile::TYPE_LINE_ITEM_BUDGET)
        ->sole();

    expect(app(ProposalDraftReadiness::class)->submissionFilesArePrepared($draft->fresh()))->toBeTrue()
        ->and($refreshedLineItemBudget->source_data['amounts']['telephone_expenses'])->toEqual(6000.0)
        ->and($refreshedLineItemBudget->source_data['co_total'])->toEqual(0.0)
        ->and($refreshedLineItemBudget->source_data['project_total'])->toEqual(6000.0);
});

test('a previously staged manual assessment form must be regenerated before Turn in', function () {
    $draft = ($this->completeDraft)(($this->createDraft)());
    $form = $draft->documents()
        ->where('document_type', ProposalVersionFile::TYPE_GAD_CHECKLIST)
        ->sole();
    $manualPath = $draft->storageDirectory().'/prepared/manual/gad-checklist/older-replacement.pdf';
    Storage::disk('local')->put($manualPath, '%PDF-1.7 older manual form');
    $form->update(['file_path' => $manualPath]);

    expect(app(ProposalDraftReadiness::class)->submissionFilesArePrepared($draft->fresh()))->toBeFalse();

    $this->actingAs($this->faculty)
        ->post(route('faculty.proposal-drafts.submit', $draft))
        ->assertSessionHasErrors('submission_files');

    $this->post(route('faculty.proposal-drafts.submission-files.prepare', $draft))
        ->assertSessionHasNoErrors();

    expect(app(ProposalDraftReadiness::class)->submissionFilesArePrepared($draft->fresh()))->toBeTrue()
        ->and($form->fresh()->file_path)->not->toBe($manualPath);
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
    $commentsSignatories = array_intersect_key(ProposalSignatory::defaultSelections(), ProposalSignatory::FIELDS['comment_response_form']);
    $draft->update(['signatory_selections' => $commentsSignatories]);
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
    expect($version->files->firstWhere('document_type', ProposalVersionFile::TYPE_DETAILED_PROPOSAL)
        ->source_data['comment_response_signatory_selections'])->toBe($commentsSignatories);
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
        ->assertDontSee('Recovery history')
        ->assertDontSee('Recovery is automatic')
        ->assertDontSee('Restore this recovery point')
        ->assertSee('data-submitted-record-preservation', false)
        ->assertSee('max-w-7xl space-y-6', false)
        ->assertSee('lg:grid-cols-[14rem_minmax(0,1fr)]', false)
        ->assertSee('Ready for Turn in.');
    $this->actingAs($this->faculty)
        ->get(route('topics.draft-history.index', [$topic, 'paper' => 'work-plan']))
        ->assertOk()
        ->assertViewHas('versions', fn ($filteredVersions): bool => $filteredVersions->count() === 2
            && $filteredVersions->every(fn (ProposalDraftDocumentVersion $history): bool => $history->document_type === ProposalVersionFile::TYPE_WORK_PLAN));
    $this->actingAs($this->faculty)
        ->get(route('topics.draft-history.download', [$topic, $archivedFileVersion]))
        ->assertDownload($archivedFileVersion->original_filename);
    $this->actingAs($this->faculty)
        ->get(route('topics.draft-history.index', [$topic, 'paper' => 'not-a-paper']))
        ->assertNotFound();
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
            && $notification->workspace === User::WORKSPACE_FACULTY
            && $notification->sidebarArea === ProposalActivityNotification::SIDEBAR_AREA_SUBMITTED_PROPOSALS
            && $notification->url === route('topics.show', $topic),
    );

    $this->actingAs($this->faculty)
        ->post(route('faculty.proposal-drafts.submit', $draftId))
        ->assertNotFound();

    expect(TopicProposal::query()->count())->toBe(1)
        ->and($topic->versions()->count())->toBe(1);
    Notification::assertSentToTimes($this->head, ProposalActivityNotification::class, 1);

    $topic->update(['status' => 'revision_requested']);
    $revisionDraft = app(CreateProposalRevisionDraft::class)->handle($topic, $this->faculty);
    expect($revisionDraft->signatory_selections)->toBe($commentsSignatories);
});

test('revision drafts reuse the latest submitted paper fields without a research call', function () {
    $topic = TopicProposal::create([
        'user_id' => $this->faculty->id,
        'title' => 'Coastal survey revision',
        'estimated_duration_months' => 12,
        'status' => 'revision_requested',
    ]);
    $version = $topic->versions()->create([
        'submitted_by' => $this->faculty->id,
        'version_number' => 2,
        'submission_type' => 'revision',
        'file_path' => 'proposals/coastal-survey.pdf',
        'original_filename' => 'coastal-survey.pdf',
        'title' => $topic->title,
    ]);
    $version->files()->create([
        'document_type' => ProposalVersionFile::TYPE_DETAILED_PROPOSAL,
        'position' => 0,
        'file_path' => 'proposals/coastal-survey.pdf',
        'original_filename' => 'coastal-survey.pdf',
        'source_data' => ['project_leader' => 'Faculty Owner', 'rationale' => 'Latest submitted rationale'],
    ]);
    $version->files()->create([
        'document_type' => ProposalVersionFile::TYPE_WORK_PLAN,
        'position' => 0,
        'file_path' => 'proposals/coastal-work-plan.pdf',
        'original_filename' => 'coastal-work-plan.pdf',
        'source_data' => ['entries' => [['objective' => 'Latest submitted objective']]],
    ]);
    ProposalDraftDocumentVersion::create([
        'topic_id' => $topic->id,
        'document_type' => ProposalVersionFile::TYPE_WORK_PLAN,
        'position' => 0,
        'version_number' => 1,
        'source_data' => ['entries' => [['objective' => 'Older draft objective']]],
    ]);

    $draft = app(CreateProposalRevisionDraft::class)->handle($topic, $this->faculty);

    expect($draft->research_call_id)->toBeNull()
        ->and($draft->documents->firstWhere('document_type', ProposalVersionFile::TYPE_DETAILED_PROPOSAL)?->source_data['rationale'])->toBe('Latest submitted rationale')
        ->and($draft->documents->firstWhere('document_type', ProposalVersionFile::TYPE_WORK_PLAN)?->source_data['entries'][0]['objective'])->toBe('Latest submitted objective');
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

    $review = $topic->reviews()->where('decision', 'revision_requested')->latest('id')->firstOrFail();
    $this->actingAs($this->faculty)
        ->patch(route('faculty.topics.resubmit', $topic), [
            'title' => $topic->title,
            'description' => 'Revised to connect the reviewed literature to the monitoring methodology.',
            'estimated_budget' => $topic->estimated_budget ?: 3600,
            'estimated_duration_months' => $topic->estimated_duration_months,
            'change_summary' => 'Clarified the RRL-to-methodology connection while retaining the verified source.',
            'feedback_review_id' => $review->id,
            'feedback_responses' => collect(app(CommentResponseFeedback::class)->rows($review))->mapWithKeys(fn (array $row): array => [$row['key'] => [
                'response' => 'Clarified the connection between the reviewed literature and monitoring methodology.',
                'no_change' => false, 'page' => 1, 'paragraph' => 2,
            ]])->all(),
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

    $topic->update(['status' => TopicProposal::STATUS_LREC_REVIEW, 'review_stage' => 'lrec']);
    foreach ([
        ProposalVersionFile::TYPE_GAD_CHECKLIST => ['purpose' => 'gad_assessment', 'gad_signature_confirmed' => true, 'gad_outcome' => 'passed', 'gad_score' => 12],
        ProposalVersionFile::TYPE_INITIAL_SCREENING_FORM => ['purpose' => 'evaluation', 'narrative_evaluation' => 'Recommended for endorsement.', 'recommended_action' => 'for_endorsement'],
    ] as $type => $assessment) {
        $source = $revisedVersion->files->firstWhere('document_type', $type);
        $path = 'assessments/'.$type.'.pdf';
        Storage::disk('local')->put($path, '%PDF-1.7 completed assessment');
        $revisedVersion->files()->create([
            'document_type' => ProposalVersionFile::TYPE_HEAD_UPLOAD,
            'source_version_file_id' => $source->id,
            'position' => $source->id,
            'file_path' => $path,
            'original_filename' => 'completed-'.$type.'.pdf',
            'mime_type' => 'application/pdf',
            'source_data' => ['target_document_type' => $type, ...$assessment],
        ]);
    }
    $this->actingAs($this->head)
        ->patch(route('research_head.topics.updateStatus', $topic), [
            'status' => TopicProposal::STATUS_READY_FOR_SIGNATURE,
            'lrec_clearance_confirmed' => true,
        ])
        ->assertRedirect(route('research_head.dashboard'))
        ->assertSessionHasNoErrors();

    Role::firstOrCreate(['name' => 'research_secretary']);
    $this->faculty->update(['college' => User::COLLEGES['CAS']]);
    $office = User::factory()->create(['college' => User::COLLEGES['CAS']]);
    $office->assignRole('research_secretary');
    $this->actingAs($office)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_SECRETARY]);
    foreach (['detailed_proposal', 'work_plan', 'line_item_budget'] as $type) {
        $formText = 'Research Project Title: '.$topic->title.' '.match ($type) {
            'detailed_proposal' => 'Research Agenda: Environment BatStateU-FO-RES-02 Detailed Research Proposal Project Leader Proponent Agency Sustainable Development Goal Research Agenda',
            'work_plan' => 'Total Duration: 12 BatStateU-FO-RES-02 Major Activities Work Plan Planned Start Planned End Expected Output',
            'line_item_budget' => 'Project Leader: Faculty Owner BatStateU-FO-RES-02 Line Item Budget Particulars Amount Maintenance and Other Operating Expenses Capital Outlays',
        };
        Process::fake(['*' => $formText.' Prepared by: Faculty Owner Checked and verified by: Research Head Approved by the Research Council']);
        $this->postJson(route('topics.head-uploads.store', $topic), [
            'source_file_id' => $revisedVersion->files->firstWhere('document_type', $type)->id,
            'review_file' => UploadedFile::fake()->create('signed-'.$type.'.pdf', 100, 'application/pdf'),
            'purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SIGNED,
        ])->assertOk();
    }

    expect($topic->fresh()->status)->toBe(TopicProposal::STATUS_READY_FOR_SIGNATURE)
        ->and($topic->fresh()->isMonitoringAvailable())->toBeFalse()
        ->and($this->faculty->fresh()->hasRole('faculty_researcher'))->toBeFalse();

    $notice = app(NoticeToProceedDataService::class)->defaults($topic->fresh());
    $notice['resolution_number'] = $notice['resolution_number'] ?: '01';

    $this->post(route('topics.notice-to-proceed.store', $topic), $notice)
        ->assertRedirect(route('topics.show', $topic).'#notice-to-proceed')
        ->assertSessionHasNoErrors();

    $this->post(route('topics.notice-to-proceed.upload-signed', $topic), [
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
        fn (ProposalActivityNotification $notification): bool => $notification->title === 'Signed papers and Notice to Proceed released',
    );

    $period = app(MonitoringQuarterService::class)->projectPeriods($topic)->first();
    $this->travelTo($period['end']);
    Role::firstOrCreate(['name' => 'research_secretary']);
    $secretary = User::factory()->create(['college' => $this->faculty->college]);
    $secretary->assignRole('research_secretary');
    $monitoringPayload = [
        'reporting_date' => now()->toDateString(),
        'tracking_number' => 'LIFECYCLE-2026-001',
        'work_plan' => array_map(fn (array $row): array => [...$row,
            'actual_accomplishment' => 'Completed the baseline monitoring cycle',
            'accomplished_percentage' => (float) $row['percent_weight'] * .25,
            'findings' => 'Community monitors completed the planned observations.',
        ], app(ApprovedWorkPlanMonitoringService::class)->defaultsForDate($topic, now())),
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
        ->and($report->progress_percentage)->toBe(0)
        ->and($report->isPrepared())->toBeTrue();
    expect(Notification::sent($this->head, ProposalActivityNotification::class)->count())
        ->toBe($headNotificationCountBeforePreparation);

    $proofUpload = UploadedFile::fake()->image('baseline-monitoring-proof.png');
    $proofPath = $proofUpload->store('progress-reports/'.$topic->id.'/evidence', 'local');
    $proof = ['id' => (string) Str::uuid(), 'path' => $proofPath, 'name' => 'baseline-monitoring-proof.png',
        'mime_type' => $proofUpload->getMimeType(), 'size' => $proofUpload->getSize(), 'checksum' => hash_file('sha256', $proofUpload->getRealPath())];
    $report->update(['work_plan' => [[...$report->work_plan[0], 'evidence' => [$proof]]]]);

    $this->withSession([
        User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY_RESEARCHER,
    ])->actingAs($this->faculty)
        ->post(route('project-progress.submit-prepared', [$topic, $report]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    Notification::assertSentTo(
        $secretary,
        ProposalActivityNotification::class,
        fn (ProposalActivityNotification $notification): bool => $notification->title === 'Report submitted for Research Office Secretary review'
            && $notification->url === route('topics.show', $topic).'#monitoring-tool-'.$report->id,
    );
    $this->actingAs($secretary)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_SECRETARY])
        ->patch(route('research_secretary.monitoring-reports.review', $report), ['decision' => 'forward'])
        ->assertSessionHasNoErrors();

    $this->actingAs($this->head)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD])
        ->get(route('research_head.projects.index'))
        ->assertOk()
        ->assertSee($topic->title)
        ->assertSee('0%');
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

test('a proposal completes repeated highlighted revisions co evaluator and LREC revisions then signed document release', function () {
    Notification::fake();
    $extractedText = '';
    Process::fake(function (PendingProcess $process) use (&$extractedText) {
        $output = str_contains(implode(' ', $process->command), 'pdf-section-coordinates')
            ? '<document><page width="600" height="800"><box xMin="0" xMax="600" yMin="0" yMax="800"/><line xMin="20" xMax="300" yMin="20" yMax="40"><word>VII. Executive Brief</word></line></page></document>'
            : $extractedText;

        return Process::result(output: $output);
    });
    Role::firstOrCreate(['name' => 'research_coordinator']);
    $college = User::COLLEGES['CAS'];
    $this->faculty->update(['college' => $college]);
    $office = User::factory()->create(['college' => $college]);
    $office->assignRole('research_coordinator');
    $draft = ($this->completeDraft)(($this->createDraft)());
    $as = fn (User $user, string $workspace) => $this->actingAs($user)->withSession([
        User::ACTIVE_WORKSPACE_SESSION_KEY => $workspace,
    ]);
    $as($this->faculty, User::WORKSPACE_FACULTY)
        ->post(route('faculty.proposal-drafts.submit', $draft))
        ->assertRedirect(route('faculty.dashboard'))->assertSessionHasNoErrors();
    $topic = TopicProposal::query()->sole();
    $version = fn () => $topic->latestVersion()->with('files')->firstOrFail();
    $paper = fn (string $type) => $version()->files->firstWhere('document_type', $type);
    expect($topic->status)->toBe('pending')->and($version()->files)->toHaveCount(7);
    Notification::assertSentTo($this->head, ProposalActivityNotification::class);

    $saveScreening = function (string $recommendation) use ($as, $topic, $version): void {
        $current = $version();
        $as($this->head, User::WORKSPACE_RESEARCH_HEAD)
            ->put(route('research_head.topics.initial-screening-form.update', [$topic, $current]), [
                'order_of_submission' => $current->version_number === 1 ? 'first_submission' : 'revised_with_minor_changes',
                'level_of_call' => 'constituent_campus', 'requested_budget' => 3600,
                'duration_months' => 12, 'researcher_count' => 1,
                'documents' => collect(ResearchHeadScreeningData::DOCUMENTS)
                    ->mapWithKeys(fn (string $label, string $type): array => [$type => ['attached' => true, 'pages' => 1]])->all(),
                'scores' => ['documents' => 30, 'alignment' => 15, 'content' => 40],
                'recommended_action' => $recommendation,
                'narrative_evaluation' => 'Review completed for this submitted version.',
            ])->assertSessionHasNoErrors();
        $this->get(route('research_head.topics.initial-screening-form.download', [$topic, $current]))->assertOk();
        expect($current->fresh()->research_head_screening['recommended_action'])->toBe($recommendation);
    };
    $requestHighlightedRevision = function (string $comment) use ($as, $topic, $version, $paper): void {
        $current = $version();
        $file = $paper(ProposalVersionFile::TYPE_DETAILED_PROPOSAL);
        $as($this->head, User::WORKSPACE_RESEARCH_HEAD)
            ->postJson(route('topics.versions.files.annotations.store', [$topic, $current, $file]), [
                'annotation_type' => 'area', 'page_number' => 1, 'editor_target' => 'executive-brief',
                'rectangles' => [['x' => .1, 'y' => .2, 'width' => .3, 'height' => .1]],
                'comment' => $comment,
            ])->assertCreated();
        $this->patch(route('research_head.topics.updateStatus', $topic), ['status' => 'revision_requested'])
            ->assertSessionHasNoErrors();
        expect($topic->fresh()->status)->toBe('revision_requested');
        $this->patch(route('research_head.topics.updateStatus', $topic), ['status' => 'revision_requested'])
            ->assertSessionHasErrors('status');
        $this->flushSession();
    };
    $submitRevision = function (int $round, string $expectedStatus, bool $noChange = false) use ($as, $topic, $version): void {
        $review = $topic->reviews()->where('decision', 'revision_requested')->latest('id')->firstOrFail();
        $feedback = app(CommentResponseFeedback::class);
        $rows = $feedback->rows($review);
        expect($rows)->not->toBeEmpty();
        $as($this->faculty, User::WORKSPACE_FACULTY)
            ->get(route('faculty.topics.revision', $topic))->assertOk()->assertSee($rows[0]['comment']);
        $this->get(route('faculty.topics.comment-response-form.pdf', ['topic' => $topic, 'review' => $review]))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $payload = [
            'title' => $topic->title, 'estimated_budget' => 3600, 'estimated_duration_months' => 12,
            'feedback_review_id' => $review->id, 'redirect_to' => 'topic',
            'feedback_responses' => collect($rows)->mapWithKeys(fn (array $row): array => [$row['key'] => [
                'response' => $noChange ? 'The existing summary addresses this comment.' : 'Clarified the summary in revision '.$round.'.',
                'no_change' => $noChange, 'page' => 1, 'paragraph' => 2,
            ]])->all(),
        ];
        $revisionDraft = null;
        if ($noChange) {
            $payload['revision_resolutions'] = [ProposalVersionFile::TYPE_DETAILED_PROPOSAL => [
                'action' => 'no_change', 'explanation' => 'The existing summary addresses this comment.',
            ]];
        } else {
            $this->get(route('faculty.proposal-drafts.revision', ['topic' => $topic, 'document_type' => 'detailed_proposal']))
                ->assertRedirect();
            $revisionDraft = $topic->revisionDraft()->with('documents')->firstOrFail();
            $document = $revisionDraft->documents->firstWhere('document_type', 'detailed_proposal');
            $this->put(route('faculty.proposal-drafts.detailed-proposal.update', $revisionDraft), [
                ...$document->source_data, 'project_leader' => $revisionDraft->project_leader,
                'document_version' => $document->lock_version, 'draft_version' => $revisionDraft->lock_version,
                'executive_brief' => 'Revised summary '.$round.'. The research will assess coastal habitats using community surveys and field observations.',
            ])->assertSessionHasNoErrors();
            $document->refresh();
            $prepared = $this->withHeader('X-Revision-PDF', '1')
                ->post(route('faculty.proposal-drafts.detailed-proposal.download', $revisionDraft), [
                    ...$document->source_data, 'project_leader' => $revisionDraft->project_leader,
                    'document_version' => $document->lock_version, 'draft_version' => $revisionDraft->lock_version,
                ])->assertOk();
            $this->flushHeaders();
            $this->postJson(route('faculty.proposal-drafts.revision-files.store', $revisionDraft), [
                'document_type' => 'detailed_proposal', 'document_version' => $document->lock_version,
                'file' => UploadedFile::fake()->createWithContent('revised-proposal.pdf', $prepared->streamedContent()),
            ])->assertOk();
        }
        $history = $revisionDraft?->documentVersions()->get() ?? collect();
        $previous = $version();
        $this->patch(route('faculty.topics.resubmit', $topic), $payload)->assertSessionHasNoErrors();
        expect($topic->fresh()->status)->toBe($expectedStatus)
            ->and($version()->version_number)->toBe($previous->version_number + 1)
            ->and($review->fileRevisions()->whereNull('resolved_at')->count())->toBe(0);
        if (! $noChange) {
            expect($version()->files->firstWhere('document_type', ProposalVersionFile::TYPE_DETAILED_PROPOSAL)->source_data['executive_brief'])
                ->toContain('Revised summary '.$round.'.');
        }
        if ($revisionDraft) {
            expect(ProposalDraft::withTrashed()->whereKey($revisionDraft->id)->exists())->toBeFalse();
            Storage::disk('local')->assertDirectoryEmpty($revisionDraft->storageDirectory());
            foreach ($history as $savedVersion) {
                $savedVersion->refresh();
                expect($savedVersion->topic_id)->toBe($topic->id)->and($savedVersion->proposal_draft_id)->toBeNull();
                if ($savedVersion->hasStoredFile()) {
                    Storage::disk('local')->assertExists($savedVersion->file_path);
                }
            }
        }
        foreach ($feedback->rows($review->fresh()) as $row) {
            expect($row['comment'])->not->toBeEmpty()
                ->and($row['response'])->toBe($payload['feedback_responses'][$row['key']]['response'])
                ->and($row['remarks'])->toBe($noChange ? 'No change made' : 'Page 1, paragraph 2')
                ->and($row['no_change'])->toBe($noChange);
        }
        $this->get(route('faculty.topics.comment-response-form.download', ['topic' => $topic, 'review' => $review]))->assertOk();
        $as($this->head, User::WORKSPACE_RESEARCH_HEAD)->get(route('topics.show', $topic))->assertOk();
        $this->patch(route('faculty.topics.resubmit', $topic), $payload)->assertForbidden();
        $this->flushSession();
    };

    $saveScreening('minor_revision');
    $requestHighlightedRevision('Clarify the project summary and intended beneficiaries.');
    $submitRevision(1, 'resubmitted');
    $requestHighlightedRevision('Confirm that the revised summary covers the intended scope.');
    $submitRevision(2, 'resubmitted', true);
    $saveScreening('for_endorsement');
    $as($this->head, User::WORKSPACE_RESEARCH_HEAD)
        ->patch(route('research_head.topics.updateStatus', $topic), [
            'status' => TopicProposal::STATUS_GAD_REVIEW, 'research_head_clearance_confirmed' => true,
        ])->assertSessionHasNoErrors();

    $uploadAssessment = function (string $type, string $recommendation = 'for_endorsement') use ($as, $topic, $paper, &$extractedText): void {
        $gad = $type === ProposalVersionFile::TYPE_GAD_CHECKLIST;
        $extractedText = $gad
            ? 'Research Project Title: '.$topic->title.' Assessment of Gender-Responsiveness Box 7a Generic Checklist Involvement of women and men Collection of sex-disaggregated data Conduct of gender analysis TOTAL GAD SCORE FOR THE PROJECT IDENTIFICATION AND DESIGN STAGES 12.32 Checked and verified by: Verifier'
            : 'BatStateU-FO-RES-03 INITIAL SCREENING FORM Research Project Title: '.$topic->title.' Project Leader: Faculty Owner Order of Submission Checklist of Submitted Documents Level of Call Narrative Evaluation: Explain how participants will be recruited. Prepared by: Co-Evaluator';
        $as($this->head, User::WORKSPACE_RESEARCH_HEAD)->post(route('topics.head-uploads.store', $topic), [
            'source_file_id' => $paper($type)->id, 'review_file' => UploadedFile::fake()->create('completed-assessment.pdf', 100, 'application/pdf'),
            ...($gad ? ['purpose' => 'gad_assessment', 'gad_signature_confirmed' => true] : [
                'purpose' => 'evaluation', 'co_evaluator_name' => 'Dr. Co Evaluator', 'recommended_action' => $recommendation,
            ]),
        ])->assertSessionHasNoErrors();
    };
    $uploadAssessment('gad_checklist');
    $uploadAssessment('initial_screening_form', 'minor_revision');
    $requestHighlightedRevision('Clarify participant recruitment in the summary.');
    $coReview = $topic->reviews()->where('decision', 'revision_requested')->latest('id')->firstOrFail();
    expect(app(CommentResponseFeedback::class)->rowsForSource($coReview, 'co_evaluator')[0]['comment'])
        ->toBe('Explain how participants will be recruited.');
    $submitRevision(3, TopicProposal::STATUS_GAD_REVIEW);
    expect($version()->hasPassingGadAssessment())->toBeFalse()
        ->and($version()->files->where('document_type', ProposalVersionFile::TYPE_HEAD_UPLOAD))->toBeEmpty();
    $uploadAssessment('gad_checklist');
    $uploadAssessment('initial_screening_form');
    $as($this->head, User::WORKSPACE_RESEARCH_HEAD)
        ->patch(route('research_head.topics.updateStatus', $topic), [
            'status' => TopicProposal::STATUS_LREC_QUEUED, 'initial_clearance_confirmed' => true,
        ])->assertSessionHasNoErrors();
    $as($office, User::WORKSPACE_RESEARCH_OFFICE)->post(route('research_coordinator.topics.lrec-review.start', $topic))->assertSessionHasNoErrors();
    expect($topic->fresh()->status)->toBe(TopicProposal::STATUS_LREC_REVIEW);
    $this->post(route('research_coordinator.topics.lrec-feedback.store', $topic), [
        'committee_comments' => [['location' => 'Detailed Proposal, Summary', 'comment' => 'Clarify the expected community benefits.']],
    ])->assertSessionHasNoErrors();
    $submitRevision(4, TopicProposal::STATUS_LREC_REVIEW);
    expect($version()->hasPassingGadAssessment())->toBeTrue();
    expect(app(ProposalSignatureWorkflow::class)->signedCopiesBySource($version()))->toHaveCount(2);
    $as($this->head, User::WORKSPACE_RESEARCH_HEAD)
        ->patch(route('research_head.topics.updateStatus', $topic), [
            'status' => TopicProposal::STATUS_READY_FOR_SIGNATURE, 'lrec_clearance_confirmed' => true,
        ])->assertSessionHasNoErrors();
    expect($topic->fresh()->isMonitoringAvailable())->toBeFalse();
    Role::firstOrCreate(['name' => 'research_secretary']);
    $office->assignRole('research_secretary');
    $as($office, User::WORKSPACE_RESEARCH_SECRETARY)->get(route('topics.show', $topic))->assertOk()->assertSee('Upload the required signed PDFs');
    foreach (['detailed_proposal', 'work_plan', 'line_item_budget'] as $type) {
        $extractedText = 'Research Project Title: '.$topic->title.' '.match ($type) {
            'detailed_proposal' => 'Research Agenda: Environment BatStateU-FO-RES-02 Detailed Research Proposal Project Leader Proponent Agency Sustainable Development Goal Research Agenda',
            'work_plan' => 'Total Duration: 12 BatStateU-FO-RES-02 Major Activities Work Plan Planned Start Planned End Expected Output',
            'line_item_budget' => 'Project Leader: Faculty Owner BatStateU-FO-RES-02 Line Item Budget Particulars Amount Maintenance and Other Operating Expenses Capital Outlays',
        };
        $extractedText .= ' Prepared by: Faculty Owner Checked and verified by: Research Head Project Leader and Staff Approved by the Research Council';
        $this->postJson(route('topics.head-uploads.store', $topic), [
            'source_file_id' => $paper($type)->id, 'purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SIGNED,
            'review_file' => UploadedFile::fake()->create('signed-'.$type.'.pdf', 100, 'application/pdf'),
        ])->assertOk()->assertJsonPath('source_file_id', $paper($type)->id);
    }
    expect(app(ProposalSignatureWorkflow::class)->isComplete($version()))->toBeTrue();
    $notice = app(NoticeToProceedDataService::class)->defaults($topic->fresh());
    $notice['resolution_number'] = '01';
    $this->post(route('topics.notice-to-proceed.store', $topic), $notice)->assertSessionHasNoErrors();
    $this->get(route('topics.notice-to-proceed.download-unsigned', $topic))->assertOk();
    $this->post(route('topics.notice-to-proceed.upload-signed', $topic), [
        'signed_notice_to_proceed' => UploadedFile::fake()->create('signed-notice.pdf', 100, 'application/pdf'),
    ])->assertSessionHasNoErrors();
    $topic->refresh();
    expect($topic->fresh()->status)->toBe('approved')
        ->and($topic->hasIssuedNoticeToProceed())->toBeTrue()
        ->and($topic->isMonitoringAvailable())->toBeTrue()
        ->and($topic->reviews()->where('decision', 'documents_released')->count())->toBe(1)
        ->and($this->faculty->fresh()->hasRole('faculty_researcher'))->toBeTrue();
    $as($this->faculty, User::WORKSPACE_FACULTY_RESEARCHER)
        ->get(route('topics.notice-to-proceed.download', $topic))->assertOk();
    foreach (app(ProposalSignatureWorkflow::class)->signedCopiesBySource($version()) as $signed) {
        $this->get(route('topics.versions.files.download', [$topic, $version(), $signed]))->assertOk();
    }
    $this->get(route('topics.show', $topic))->assertOk()->assertSee('data-released-notice-summary', false)
        ->assertDontSee('Released documents')->assertSee('Project monitoring');
});
