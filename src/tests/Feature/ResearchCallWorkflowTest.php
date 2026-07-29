<?php

use App\Models\ProposalTemplate;
use App\Models\ProposalVersionFile;
use App\Models\ResearchCall;
use App\Models\ResearchCategory;
use App\Models\TopicProposal;
use App\Models\User;
use App\Notifications\ResearchCallPublishedNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['faculty', 'faculty_researcher', 'research_head'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }

    $this->head = User::factory()->create();
    $this->head->assignRole('research_head');
    $this->faculty = User::factory()->create();
    $this->faculty->assignRole('faculty');
    $this->category = ResearchCategory::create(['name' => 'Environment']);
    $this->call = ResearchCall::create([
        'title' => 'Open Institutional Call',
        'academic_year' => '2026-2027',
        'opens_at' => now()->subDay(),
        'closes_at' => now()->addMonth(),
        'max_active_research_per_faculty' => 2,
        'maximum_budget' => 100000,
        'status' => 'open',
        'created_by' => $this->head->id,
    ]);
    $this->call->categories()->attach($this->category);
    Storage::fake('local');
});

test('the proposal workflow generates and stores Attachment A with the submitted package', function () {
    $this->actingAs($this->faculty)
        ->get(route('faculty.topics.create'))
        ->assertRedirect(route('faculty.proposal-drafts.index'));

    $payload = fn (string $projectTitle) => [
        'research_call_id' => $this->call->id,
        'title' => 'Major Activities and Work Plan',
        'project_title' => $projectTitle,
        'total_duration_months' => 12,
        'planned_start' => '2026-08-01',
        'planned_end' => '2027-07-31',
        'entries' => [[
            'objective' => 'Complete the approved research activities',
            'expected_output' => 'Completed research outputs',
            'activity' => 'Conduct the scheduled research activities',
            'months' => [1, 2, 3],
        ]],
        'prepared_by' => $this->faculty->name,
        'detailed_proposal' => UploadedFile::fake()->create($projectTitle.'-proposal.pdf', 100, 'application/pdf'),
        'line_item_budget' => UploadedFile::fake()->create($projectTitle.'-budget.docx', 50, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        'expense_breakdown' => UploadedFile::fake()->create($projectTitle.'-expenses.xlsx', 50, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
        'curricula_vitae' => [UploadedFile::fake()->create($projectTitle.'-cv.pdf', 50, 'application/pdf')],
        'gad_checklist' => UploadedFile::fake()->create($projectTitle.'-gad.docx', 50, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
    ];

    $incompletePayload = $payload('Missing GAD checklist');
    unset($incompletePayload['gad_checklist']);
    $this->actingAs($this->faculty)
        ->post('/faculty/topics', $incompletePayload)
        ->assertSessionHasErrors('gad_checklist', null, 'submission');

    $this->actingAs($this->faculty)->post('/faculty/topics', $payload('First'))->assertRedirect(route('faculty.dashboard'));
    $this->actingAs($this->faculty)->post('/faculty/topics', $payload('Second'))->assertRedirect(route('faculty.dashboard'));
    $this->actingAs($this->faculty)->post('/faculty/topics', $payload('Third'))->assertRedirect(route('faculty.dashboard'));

    expect($this->faculty->proposals()->count())->toBe(3)
        ->and($this->head->notifications()->count())->toBe(3);

    $firstProposal = $this->faculty->proposals()->oldest()->firstOrFail();
    $generatedWorkPlan = $firstProposal->latestVersion->files->firstWhere('document_type', 'work_plan');

    expect($firstProposal->versions()->count())->toBe(1)
        ->and($firstProposal->latestVersion->version_number)->toBe(1)
        ->and($firstProposal->latestVersion->submission_type)->toBe('initial')
        ->and($firstProposal->latestVersion->estimated_duration_months)->toBe(12)
        ->and($firstProposal->latestVersion->checksum)->toHaveLength(64)
        ->and($firstProposal->latestVersion->files()->count())->toBe(6)
        ->and($generatedWorkPlan)->not->toBeNull()
        ->and($generatedWorkPlan->original_filename)->toBe('first-work-plan.pdf')
        ->and($generatedWorkPlan->mime_type)->toBe('application/pdf')
        ->and($generatedWorkPlan->checksum)->toHaveLength(64);

    $firstProposal->latestVersion->files->each(
        fn ($file) => Storage::disk('local')->assertExists($file->file_path),
    );

    $this->actingAs($this->faculty)
        ->get(route('faculty.dashboard'))
        ->assertOk()
        ->assertSee(route('faculty.proposal-drafts.index'), false)
        ->assertDontSee('submitProposalModal');
});

test('research heads can close and reopen calls while faculty cannot change call status', function () {
    $this->actingAs($this->head)
        ->get(route('research-calls.index'))
        ->assertOk()
        ->assertSee('Close early')
        ->assertSee('Submission starts')
        ->assertSee('Submission ends')
        ->assertSee('data-research-call-image-dropzone', false)
        ->assertSee('data-research-call-image-preview', false)
        ->assertSee('Drag and drop poster')
        ->assertSee('Ctrl+V also works');

    $this->actingAs($this->head)
        ->patch(route('research-calls.update-status', $this->call), ['status' => 'closed'])
        ->assertRedirect()
        ->assertSessionHas('success', 'Research call closed. New proposal submissions are no longer accepted.');

    expect($this->call->fresh()->status)->toBe('closed')
        ->and($this->call->fresh()->isAcceptingSubmissions())->toBeFalse();

    $this->actingAs($this->faculty)
        ->get(route('faculty.proposal-drafts.create'))
        ->assertOk()
        ->assertDontSee($this->call->title);

    $this->actingAs($this->faculty)
        ->patch(route('research-calls.update-status', $this->call), ['status' => 'open'])
        ->assertForbidden();

    $this->actingAs($this->head)
        ->patch(route('research-calls.update-status', $this->call), ['status' => 'open'])
        ->assertRedirect();

    expect($this->call->fresh()->status)->toBe('open');
});

test('faculty and faculty researchers are notified when an open research call is posted', function () {
    $facultyResearcher = User::factory()->create();
    $facultyResearcher->assignRole('faculty_researcher');
    Notification::fake();

    $this->actingAs($this->head)
        ->post(route('research-calls.store'), [
            'title' => 'Newly Posted Institutional Call',
            'academic_year' => '2026-2027',
            'opens_at' => now()->subMinute()->format('Y-m-d H:i:s'),
            'closes_at' => now()->addMonth()->format('Y-m-d H:i:s'),
            'max_active_research_per_faculty' => 2,
            'maximum_budget' => 150000,
            'categories' => 'Environment',
            'status' => 'open',
        ])
        ->assertRedirect(route('research-calls.index'));

    $call = ResearchCall::query()->where('title', 'Newly Posted Institutional Call')->firstOrFail();

    foreach ([$this->faculty, $facultyResearcher] as $recipient) {
        Notification::assertSentTo(
            $recipient,
            ResearchCallPublishedNotification::class,
            fn (ResearchCallPublishedNotification $notification): bool => $notification->researchCallId === $call->id
                && $notification->url === route('faculty.dashboard'),
        );
    }

    Notification::assertNotSentTo($this->head, ResearchCallPublishedNotification::class);
});

test('published research calls follow their configured start and end dates automatically', function () {
    $this->call->update([
        'opens_at' => now()->addWeek(),
        'closes_at' => now()->addMonth(),
        'status' => 'open',
    ]);

    expect($this->call->fresh()->lifecycleStatus())->toBe('scheduled')
        ->and($this->call->fresh()->isAcceptingSubmissions())->toBeFalse();

    $this->call->update([
        'opens_at' => now()->subMonth(),
        'closes_at' => now()->subDay(),
    ]);

    expect($this->call->fresh()->lifecycleStatus())->toBe('ended')
        ->and($this->call->fresh()->isAcceptingSubmissions())->toBeFalse();

    $this->actingAs($this->head)
        ->get(route('research-calls.index'))
        ->assertOk()
        ->assertSee('The submission period ended automatically.');
});

test('research call budgets cannot exceed the PHP 150000 institutional ceiling', function () {
    $payload = [
        'title' => 'Budget-controlled call',
        'academic_year' => '2027-2028',
        'opens_at' => now()->addDay()->format('Y-m-d H:i:s'),
        'closes_at' => now()->addMonth()->format('Y-m-d H:i:s'),
        'max_active_research_per_faculty' => 2,
        'categories' => 'Technology',
        'status' => 'draft',
    ];

    $this->actingAs($this->head)
        ->post(route('research-calls.store'), [...$payload, 'maximum_budget' => 150000.01])
        ->assertSessionHasErrors('maximum_budget');

    $this->actingAs($this->head)
        ->post(route('research-calls.store'), [...$payload, 'maximum_budget' => 150000])
        ->assertRedirect(route('research-calls.index'));

    expect(ResearchCall::where('title', 'Budget-controlled call')->value('maximum_budget'))->toBe('150000.00');
});

test('research heads can read a research-call poster into blank form fields', function () {
    config([
        'services.gemini.key' => 'test-key',
        'services.gemini.model' => 'gemini-3.5-flash',
        'services.gemini.base_url' => 'https://generativelanguage.googleapis.com/v1beta/openai',
    ]);

    Http::fake([
        'generativelanguage.googleapis.com/v1beta/openai/chat/completions' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'title' => 'Call for Proposals for August 2026 Implementation',
                        'academic_year' => null,
                        'term' => 'August 2026 Implementation',
                        'description' => "The research proposals must be:\n- Aligned with the BatStateU research agenda\n- Cross disciplinary or interdisciplinary",
                        'opens_at' => '2026-02-05T00:00',
                        'closes_at' => '2026-03-02T23:59',
                        'maximum_budget' => 150000,
                        'categories' => ['Cross-disciplinary', 'Product Development'],
                        'initial_evaluation_start_date' => '2026-03-03',
                        'initial_evaluation_end_date' => '2026-03-10',
                        'paper_revisions_start_date' => '2026-03-11',
                        'paper_revisions_end_date' => '2026-03-20',
                        'lrec_start_date' => '2026-04-10',
                        'lrec_end_date' => null,
                        'implementation_start_date' => '2026-08-01',
                        'implementation_end_date' => null,
                    ]),
                ],
            ]],
        ]),
    ]);

    $response = $this->actingAs($this->head)
        ->post(route('research-calls.extract-image'), [
            'reference_image' => UploadedFile::fake()->image('research-call.jpg'),
        ])
        ->assertOk();

    $response->assertJsonPath('fields.title', 'Call for Proposals for August 2026 Implementation')
        ->assertJsonPath('fields.description', "The research proposals must be:\n- Aligned with the BatStateU research agenda\n- Cross disciplinary or interdisciplinary")
        ->assertJsonPath('fields.closes_at', '2026-03-02T23:59')
        ->assertJsonPath('fields.initial_evaluation_start_date', '2026-03-03')
        ->assertJsonPath('fields.paper_revisions_end_date', '2026-03-20')
        ->assertJsonPath('fields.lrec_start_date', '2026-04-10')
        ->assertJsonPath('fields.implementation_start_date', '2026-08-01');

    Http::assertSent(fn ($request): bool => $request['messages'][1]['content'][1]['type'] === 'image_url'
        && str_starts_with($request['messages'][1]['content'][1]['image_url']['url'], 'data:image/jpeg;base64,'));
});

test('research-call extraction recovers fields when the vision model returns poster text', function () {
    config([
        'services.gemini.key' => 'test-key',
        'services.gemini.model' => 'gemini-3.5-flash',
        'services.gemini.base_url' => 'https://generativelanguage.googleapis.com/v1beta/openai',
    ]);

    Http::fake([
        'generativelanguage.googleapis.com/v1beta/openai/chat/completions' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => <<<'POSTER'
CALL FOR PROPOSALS
FOR AUGUST 2026 IMPLEMENTATION

THE RESEARCH PROPOSALS MUST BE:
Aligned with the BatStateU The NEU research agenda
With Budget Requirement of lower that Php 150,000.00
Cross disciplinary or interdisciplinary research projects

IMPORTANT DATES
FEBRUARY 5, 2026 - MARCH 2, 2026 Deadline of Submission
MARCH 3-10, 2026 Initial Evaluation
MARCH 11-20, 2026 Paper Revisions based on the Initial Screening
APRIL 10, 2026 Tentative Local Research Evaluation (LREC)
AUGUST 2026 Implementation
POSTER,
                ],
            ]],
        ]),
    ]);

    $response = $this->actingAs($this->head)
        ->post(route('research-calls.extract-image'), [
            'reference_image' => UploadedFile::fake()->image('research-call.jpg'),
        ])
        ->assertOk();

    $response->assertJsonPath('fields.title', 'CALL FOR PROPOSALS FOR AUGUST 2026 IMPLEMENTATION')
        ->assertJsonPath('fields.maximum_budget', 150000)
        ->assertJsonPath('fields.opens_at', '2026-02-05T00:00')
        ->assertJsonPath('fields.closes_at', '2026-03-02T23:59')
        ->assertJsonPath('fields.initial_evaluation_start_date', '2026-03-03')
        ->assertJsonPath('fields.initial_evaluation_end_date', '2026-03-10')
        ->assertJsonPath('fields.paper_revisions_start_date', '2026-03-11')
        ->assertJsonPath('fields.paper_revisions_end_date', '2026-03-20')
        ->assertJsonPath('fields.lrec_start_date', '2026-04-10')
        ->assertJsonPath('fields.implementation_start_date', '2026-08-01')
        ->assertJsonPath('fields.description', 'THE RESEARCH PROPOSALS MUST BE: Aligned with the BatStateU The NEU research agenda With Budget Requirement of lower that Php 150,000.00 Cross disciplinary or interdisciplinary research projects');
});

test('research heads can save workflow dates and a reference poster with a research call', function () {
    $poster = UploadedFile::fake()->image('call-poster.jpg');

    $this->actingAs($this->head)
        ->post(route('research-calls.store'), [
            'title' => 'August 2026 Implementation Call',
            'academic_year' => '2026-2027',
            'term' => 'August 2026 Implementation',
            'description' => 'The research proposals must be aligned with the research agenda.',
            'reference_image' => $poster,
            'opens_at' => '2026-02-05 00:00:00',
            'closes_at' => '2026-03-02 23:59:00',
            'initial_evaluation_start_date' => '2026-03-03',
            'initial_evaluation_end_date' => '2026-03-10',
            'paper_revisions_start_date' => '2026-03-11',
            'paper_revisions_end_date' => '2026-03-20',
            'lrec_start_date' => '2026-04-10',
            'implementation_start_date' => '2026-08-01',
            'max_active_research_per_faculty' => 2,
            'maximum_budget' => 150000,
            'categories' => 'Cross-disciplinary, Product Development',
            'status' => 'draft',
        ])
        ->assertRedirect(route('research-calls.index'));

    $call = ResearchCall::query()->where('title', 'August 2026 Implementation Call')->firstOrFail();

    expect($call->description)->toContain('research proposals must be')
        ->and($call->initial_evaluation_start_date->format('Y-m-d'))->toBe('2026-03-03')
        ->and($call->initial_evaluation_end_date->format('Y-m-d'))->toBe('2026-03-10')
        ->and($call->paper_revisions_end_date->format('Y-m-d'))->toBe('2026-03-20')
        ->and($call->lrec_start_date->format('Y-m-d'))->toBe('2026-04-10')
        ->and($call->lrec_end_date)->toBeNull()
        ->and($call->implementation_start_date->format('Y-m-d'))->toBe('2026-08-01')
        ->and($call->reference_image_path)->not->toBeNull();

    Storage::disk('local')->assertExists($call->reference_image_path);

    $this->actingAs($this->faculty)
        ->get(route('research-calls.reference-image', $call))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg');
});

test('faculty research workload is limited to two approved projects per academic year', function () {
    $createProposal = function (ResearchCall $call, string $title, string $status): TopicProposal {
        $topic = TopicProposal::create([
            'user_id' => $this->faculty->id,
            'research_call_id' => $call->id,
            'research_category_id' => $this->category->id,
            'title' => $title,
            'estimated_budget' => 50000,
            'estimated_duration_months' => 12,
            'status' => $status,
        ]);

        $path = 'proposals/workload-'.$topic->id.'.pdf';
        Storage::disk('local')->put($path, 'proposal');
        $topic->versions()->create([
            'submitted_by' => $this->faculty->id,
            'version_number' => 1,
            'submission_type' => 'initial',
            'file_path' => $path,
            'original_filename' => 'proposal.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 8,
            'checksum' => hash('sha256', 'proposal'),
            'title' => $title,
            'estimated_budget' => 50000,
            'estimated_duration_months' => 12,
        ]);

        return $topic;
    };

    $createProposal($this->call, 'Approved project one', 'approved');
    $createProposal($this->call, 'Approved project two', 'approved');
    $thirdProposal = $createProposal($this->call, 'Third project in the same year', 'pending');

    $this->actingAs($this->head)
        ->patch(route('research_head.topics.updateStatus', $thirdProposal), [
            'status' => 'approved',
            'evaluation_document' => UploadedFile::fake()->create('third-evaluation.pdf', 100, 'application/pdf'),
        ])
        ->assertSessionHasErrors('status');

    expect($thirdProposal->fresh()->status)->toBe('pending');

    $nextYearCall = ResearchCall::create([
        'title' => 'Next Academic Year Call',
        'academic_year' => '2027-2028',
        'opens_at' => now()->subDay(),
        'closes_at' => now()->addMonth(),
        'max_active_research_per_faculty' => 2,
        'status' => 'open',
        'created_by' => $this->head->id,
    ]);
    $nextYearCall->categories()->attach($this->category);
    $nextYearProposal = $createProposal($nextYearCall, 'Project for the next academic year', 'pending');

    $this->actingAs($this->head)
        ->patch(route('research_head.topics.updateStatus', $nextYearProposal), [
            'status' => 'approved',
            'evaluation_document' => UploadedFile::fake()->create('next-year-evaluation.pdf', 100, 'application/pdf'),
        ])
        ->assertRedirect(route('research_head.dashboard'));

    expect($nextYearProposal->fresh()->status)->toBe('approved');
});

test('a revision snapshots the package and carries forward unchanged files', function () {
    $this->actingAs($this->faculty)->post('/faculty/topics', [
        'research_call_id' => $this->call->id,
        'research_category_id' => $this->category->id,
        'title' => 'Versioned package',
        'description' => 'Initial package.',
        'estimated_budget' => 50000,
        'estimated_duration_months' => 12,
        'detailed_proposal' => UploadedFile::fake()->create('proposal.pdf', 100, 'application/pdf'),
        'work_plan' => UploadedFile::fake()->create('work-plan.docx', 50, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        'line_item_budget' => UploadedFile::fake()->create('budget.docx', 50, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        'expense_breakdown' => UploadedFile::fake()->create('expenses.xlsx', 50, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
        'curricula_vitae' => [UploadedFile::fake()->create('leader-cv.pdf', 50, 'application/pdf')],
        'gad_checklist' => UploadedFile::fake()->create('gad-checklist.docx', 50, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
    ])->assertRedirect(route('faculty.dashboard'));

    $topic = $this->faculty->proposals()->firstOrFail();
    $firstVersion = $topic->latestVersion()->with('files')->firstOrFail();
    $originalDetailedProposal = $firstVersion->files->firstWhere('document_type', 'detailed_proposal');
    $originalWorkPlan = $firstVersion->files->firstWhere('document_type', 'work_plan');
    $topic->update(['status' => 'revision_requested']);

    $this->actingAs($this->faculty)->patch(route('faculty.topics.resubmit', $topic), [
        'title' => 'Versioned package - over budget',
        'estimated_budget' => 100000.01,
        'estimated_duration_months' => 14,
    ])->assertSessionHasErrors('estimated_budget', null, 'resubmission');

    expect($topic->fresh()->versions()->count())->toBe(1);

    $this->actingAs($this->faculty)->patch(route('faculty.topics.resubmit', $topic), [
        'title' => 'Versioned package - revised',
        'description' => 'Updated schedule.',
        'estimated_budget' => 50000,
        'estimated_duration_months' => 14,
        'change_summary' => 'Extended the schedule and replaced the work plan.',
        'work_plan' => UploadedFile::fake()->create('work-plan-v2.docx', 60, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
    ])->assertRedirect(route('faculty.dashboard'));

    $secondVersion = $topic->fresh()->latestVersion()->with('files')->firstOrFail();
    $revisedWorkPlan = $secondVersion->files->firstWhere('document_type', 'work_plan');
    $carriedDetailedProposal = $secondVersion->files->firstWhere('document_type', 'detailed_proposal');

    expect($secondVersion->version_number)->toBe(2)
        ->and($secondVersion->change_summary)->toBe('Extended the schedule and replaced the work plan.')
        ->and($secondVersion->files)->toHaveCount(6)
        ->and($revisedWorkPlan->is_carried_forward)->toBeFalse()
        ->and($revisedWorkPlan->file_path)->not->toBe($originalWorkPlan->file_path)
        ->and($carriedDetailedProposal->is_carried_forward)->toBeTrue()
        ->and($carriedDetailedProposal->source_version_file_id)->toBe($originalDetailedProposal->id)
        ->and($carriedDetailedProposal->file_path)->toBe($originalDetailedProposal->file_path);

    $this->actingAs($this->faculty)
        ->get(route('topics.versions.files.download', [$topic, $secondVersion, $revisedWorkPlan]))
        ->assertDownload('work-plan-v2.docx');
});

test('faculty can securely download configured proposal templates', function () {
    ProposalTemplate::create([
        'slug' => 'test-work-plan',
        'name' => 'Test Work Plan',
        'description' => 'A test-only proposal template',
        'file_path' => 'proposals/templates/test-work-plan.docx',
        'original_filename' => 'test-work-plan.docx',
        'is_active' => true,
    ]);

    Storage::disk('local')->put('proposals/templates/test-work-plan.docx', 'template contents');
    $samplePath = config('proposal_samples.detailed-proposal.path');
    Storage::disk('local')->put($samplePath, '%PDF-1.4 sample contents');

    $this->actingAs($this->faculty)
        ->get(route('faculty.proposal-drafts.index'))
        ->assertOk()
        ->assertDontSee('Test Work Plan');

    $this->actingAs($this->faculty)
        ->get(route('proposal-templates.download', 'test-work-plan'))
        ->assertDownload('test-work-plan.docx');

    $this->actingAs($this->faculty)
        ->get(route('proposal-templates.download', 'not-configured'))
        ->assertNotFound();

    $this->actingAs($this->head)
        ->get(route('proposal-templates.download', 'test-work-plan'))
        ->assertDownload('test-work-plan.docx');

    $this->actingAs($this->faculty)
        ->get(route('proposal-samples.show', 'detailed-proposal'))
        ->assertOk()
        ->assertHeader('content-disposition', 'inline; filename="Online Journal - Detailed Research Proposal.pdf"');

    auth()->logout();

    $this->get(route('proposal-samples.show', 'detailed-proposal'))
        ->assertRedirect(route('login'));
});

test('research head records the final decision with external evaluation proof', function () {
    $topic = TopicProposal::create([
        'user_id' => $this->faculty->id,
        'research_call_id' => $this->call->id,
        'research_category_id' => $this->category->id,
        'title' => 'Coastal habitat restoration',
        'estimated_budget' => 75000,
        'estimated_duration_months' => 18,
        'initial_file_path' => 'proposals/coastal.pdf',
        'status' => 'pending',
    ]);
    $path = 'proposals/coastal.pdf';
    Storage::disk('local')->put($path, 'coastal proposal');
    $version = $topic->versions()->create([
        'submitted_by' => $this->faculty->id,
        'version_number' => 1,
        'submission_type' => 'initial',
        'file_path' => $path,
        'original_filename' => 'coastal.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 16,
        'checksum' => hash('sha256', 'coastal proposal'),
        'title' => $topic->title,
        'estimated_budget' => 75000,
        'estimated_duration_months' => 18,
    ]);

    $this->actingAs($this->head)->patch("/research-head/topics/{$topic->id}/status", [
        'status' => 'approved',
        'comment' => 'Approved based on the completed external evaluation.',
        'evaluation_document' => UploadedFile::fake()->create('completed-evaluation.pdf', 100, 'application/pdf'),
    ])->assertRedirect(route('research_head.dashboard'));

    $evaluationDocument = $version->files()
        ->where('document_type', ProposalVersionFile::TYPE_HEAD_UPLOAD)
        ->sole();
    $topic->refresh();
    expect($topic->status)->toBe('approved')
        ->and($evaluationDocument->source_data['purpose'])->toBe(ProposalVersionFile::HEAD_UPLOAD_PURPOSE_EVALUATION)
        ->and($this->faculty->fresh()->hasRole('faculty_researcher'))->toBeTrue();
    Storage::disk('local')->assertExists($evaluationDocument->file_path);

    $this->actingAs($this->faculty)
        ->get(route('topics.versions.files.download', [$topic, $version, $evaluationDocument]))
        ->assertDownload('completed-evaluation.pdf');
});
