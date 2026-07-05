<?php

use App\Models\ProposalTemplate;
use App\Models\ResearchCall;
use App\Models\ResearchCategory;
use App\Models\TopicProposal;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['faculty', 'faculty_researcher', 'research_head', 'expert'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }

    $this->head = User::factory()->create();
    $this->head->assignRole('research_head');
    $this->faculty = User::factory()->create();
    $this->faculty->assignRole('faculty');
    $this->expert = User::factory()->create();
    $this->expert->assignRole('expert');
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

test('faculty submissions capture call category budget and duration without an application limit', function () {
    $payload = fn (string $title) => [
        'research_call_id' => $this->call->id,
        'research_category_id' => $this->category->id,
        'title' => $title,
        'description' => 'An environmental research proposal.',
        'estimated_budget' => 50000,
        'estimated_duration_months' => 12,
        'detailed_proposal' => UploadedFile::fake()->create($title.'-proposal.pdf', 100, 'application/pdf'),
        'work_plan' => UploadedFile::fake()->create($title.'-work-plan.docx', 50, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        'line_item_budget' => UploadedFile::fake()->create($title.'-budget.docx', 50, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        'expense_breakdown' => UploadedFile::fake()->create($title.'-expenses.xlsx', 50, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
        'curricula_vitae' => [UploadedFile::fake()->create($title.'-cv.pdf', 50, 'application/pdf')],
    ];

    $this->actingAs($this->faculty)->post('/faculty/topics', $payload('First'))->assertRedirect(route('faculty.dashboard'));
    $this->actingAs($this->faculty)->post('/faculty/topics', $payload('Second'))->assertRedirect(route('faculty.dashboard'));
    $this->actingAs($this->faculty)->post('/faculty/topics', $payload('Third'))->assertRedirect(route('faculty.dashboard'));

    expect($this->faculty->proposals()->count())->toBe(3)
        ->and($this->faculty->proposals()->first()->estimated_duration_months)->toBe(12)
        ->and($this->head->notifications()->count())->toBe(3);

    $firstProposal = $this->faculty->proposals()->oldest()->firstOrFail();
    expect($firstProposal->versions()->count())->toBe(1)
        ->and($firstProposal->latestVersion->version_number)->toBe(1)
        ->and($firstProposal->latestVersion->submission_type)->toBe('initial')
        ->and($firstProposal->latestVersion->checksum)->toHaveLength(64)
        ->and($firstProposal->latestVersion->files()->count())->toBe(5);

    $firstProposal->latestVersion->files->each(
        fn ($file) => Storage::disk('local')->assertExists($file->file_path),
    );
});

test('faculty research workload is limited to two approved projects per academic year', function () {
    $createProposal = function (ResearchCall $call, string $title, string $status): TopicProposal {
        return TopicProposal::create([
            'user_id' => $this->faculty->id,
            'research_call_id' => $call->id,
            'research_category_id' => $this->category->id,
            'title' => $title,
            'estimated_budget' => 50000,
            'estimated_duration_months' => 12,
            'status' => $status,
        ]);
    };

    $createProposal($this->call, 'Approved project one', 'approved');
    $createProposal($this->call, 'Approved project two', 'approved');
    $thirdProposal = $createProposal($this->call, 'Third project in the same year', 'pending');

    $this->actingAs($this->head)
        ->patch(route('research_head.topics.updateStatus', $thirdProposal), [
            'status' => 'approved',
            'signed_approval' => UploadedFile::fake()->create('third-approval.pdf', 100, 'application/pdf'),
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
            'signed_approval' => UploadedFile::fake()->create('next-year-approval.pdf', 100, 'application/pdf'),
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
    ])->assertRedirect(route('faculty.dashboard'));

    $topic = $this->faculty->proposals()->firstOrFail();
    $firstVersion = $topic->latestVersion()->with('files')->firstOrFail();
    $originalDetailedProposal = $firstVersion->files->firstWhere('document_type', 'detailed_proposal');
    $originalWorkPlan = $firstVersion->files->firstWhere('document_type', 'work_plan');
    $topic->update(['status' => 'revision_requested']);

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
        ->and($secondVersion->files)->toHaveCount(5)
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

    $this->actingAs($this->faculty)
        ->get(route('faculty.dashboard'))
        ->assertOk()
        ->assertSee('Test Work Plan')
        ->assertSee(route('proposal-templates.download', 'test-work-plan'));

    $this->actingAs($this->faculty)
        ->get(route('proposal-templates.download', 'test-work-plan'))
        ->assertDownload('test-work-plan.docx');

    $this->actingAs($this->faculty)
        ->get(route('proposal-templates.download', 'not-configured'))
        ->assertNotFound();

    $this->actingAs($this->head)
        ->get(route('proposal-templates.download', 'test-work-plan'))
        ->assertDownload('test-work-plan.docx');
});

test('expert recommendations return a proposal to the research head for a signed final approval', function () {
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

    $this->actingAs($this->head)->patch("/research-head/topics/{$topic->id}/status", [
        'status' => 'expert_review',
        'expert_ids' => [$this->expert->id],
        'comment' => 'Please evaluate the environmental need and feasibility.',
    ])->assertRedirect(route('research_head.dashboard'));

    $assignment = $topic->expertAssignments()->firstOrFail();
    expect($topic->fresh()->status)->toBe('expert_review')
        ->and($this->expert->notifications()->count())->toBe(1);

    $this->actingAs($this->expert)->patch("/expert/assignments/{$assignment->id}", [
        'recommendation' => 'recommend_approval',
        'comment' => 'The project addresses a documented coastal need and is feasible.',
    ])->assertRedirect();

    expect($topic->fresh()->status)->toBe('for_final_decision')
        ->and($this->head->notifications()->count())->toBe(1);

    $this->actingAs($this->head)->patch("/research-head/topics/{$topic->id}/status", [
        'status' => 'approved',
        'comment' => 'Approved based on the completed expert review.',
        'signed_approval' => UploadedFile::fake()->create('signed-approval.pdf', 100, 'application/pdf'),
    ])->assertRedirect(route('research_head.dashboard'));

    $topic->refresh();
    expect($topic->status)->toBe('approved')
        ->and($topic->signed_approval_path)->not->toBeNull()
        ->and($this->faculty->fresh()->hasRole('faculty_researcher'))->toBeTrue();
    Storage::disk('local')->assertExists($topic->signed_approval_path);
});
