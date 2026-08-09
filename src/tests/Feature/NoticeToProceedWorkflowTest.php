<?php

use App\Contracts\DocumentPdfConverter;
use App\Models\ProposalVersionFile;
use App\Models\ResearchCall;
use App\Models\TopicProposal;
use App\Models\User;
use App\Services\NoticeToProceedDataService;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['faculty', 'faculty_researcher', 'research_head'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }

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

    $this->head = User::factory()->create(['name' => 'Research Head']);
    $this->head->assignRole('research_head');
    $this->faculty = User::factory()->create(['name' => 'Faculty Owner']);
    $this->faculty->assignRole('faculty');
    $this->call = ResearchCall::create([
        'title' => 'Notice Workflow Call',
        'academic_year' => '2026-2027',
        'opens_at' => now()->subMonth(),
        'closes_at' => now()->addMonth(),
        'implementation_start_date' => '2026-09-01',
        'implementation_end_date' => '2027-08-31',
        'max_active_research_per_faculty' => 2,
        'status' => 'open',
        'created_by' => $this->head->id,
    ]);
    $this->topic = TopicProposal::create([
        'user_id' => $this->faculty->id,
        'research_call_id' => $this->call->id,
        'title' => 'Approved Coastal Research',
        'estimated_budget' => 75000,
        'estimated_duration_months' => 12,
        'status' => 'approved',
        'project_status' => null,
    ]);
});

test('issuing the Notice to Proceed promotes the faculty member and opens monitoring', function () {
    expect($this->faculty->hasRole('faculty_researcher'))->toBeFalse()
        ->and($this->topic->isMonitoringAvailable())->toBeFalse();

    $this->withSession([
        User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY,
    ])->actingAs($this->faculty)
        ->get(route('topics.show', $this->topic))
        ->assertOk()
        ->assertSee('Approved - awaiting notice')
        ->assertSee('Ready to generate')
        ->assertDontSee('Project monitoring');

    $this->withSession([
        User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD,
    ])->actingAs($this->head)
        ->get(route('topics.show', $this->topic))
        ->assertOk()
        ->assertSee('Generate notice and open monitoring')
        ->assertSee('Faculty Owner')
        ->assertSee('75,000.00')
        ->assertSee('No PDF upload is needed.');

    $payload = app(NoticeToProceedDataService::class)->defaults($this->topic);
    $payload['resolution_number'] = '01';

    $this->withSession([
        User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD,
    ])->actingAs($this->head)
        ->post(route('research_head.topics.notice-to-proceed.store', $this->topic), $payload)
        ->assertRedirect(route('topics.show', $this->topic).'#notice-to-proceed')
        ->assertSessionHas('success', 'Notice to Proceed generated and issued. Faculty Researcher access and project monitoring are now open.');

    $this->topic->refresh();
    $this->faculty->refresh();

    expect($this->topic->notice_to_proceed_issued_by)->toBe($this->head->id)
        ->and($this->topic->notice_to_proceed_original_filename)->toBe('notice-to-proceed-approved-coastal-research.pdf')
        ->and($this->topic->notice_to_proceed_data['researcher_names'])->toBe(['Faculty Owner'])
        ->and($this->topic->notice_to_proceed_data['approved_budget'])->toBe('75000.00')
        ->and($this->topic->notice_to_proceed_data['approved_start_date'])->toBe('2026-09-01')
        ->and($this->topic->project_status)->toBe('ongoing')
        ->and($this->topic->isMonitoringAvailable())->toBeTrue()
        ->and($this->faculty->hasRole('faculty_researcher'))->toBeTrue()
        ->and($this->faculty->notifications()->firstOrFail()->data['title'])->toBe('Notice to Proceed issued');

    Storage::disk('local')->assertExists($this->topic->notice_to_proceed_path);

    $this->withSession([
        User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY,
    ])->actingAs($this->faculty)
        ->get(route('topics.notice-to-proceed.download', $this->topic))
        ->assertDownload('notice-to-proceed-approved-coastal-research.pdf');

    $this->withSession([
        User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY_RESEARCHER,
    ])->actingAs($this->faculty)
        ->get(route('research.show', $this->topic))
        ->assertOk()
        ->assertSee('Project monitoring');
});

test('an approved paper remains outside monitoring until its notice is issued', function () {
    $this->faculty->assignRole('faculty_researcher');

    $this->withSession([
        User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY_RESEARCHER,
    ])->actingAs($this->faculty)
        ->post(route('project-progress.store', $this->topic), [
            'reporting_date' => now()->toDateString(),
            'progress_percentage' => 10,
            'accomplishments' => 'This must remain locked.',
        ])
        ->assertForbidden();

    $this->withSession([
        User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD,
    ])->actingAs($this->head)
        ->get(route('research_head.projects.index'))
        ->assertOk()
        ->assertDontSee($this->topic->title);
});

test('notice defaults come from the latest approved proposal papers', function () {
    $version = $this->topic->versions()->create([
        'submitted_by' => $this->faculty->id,
        'version_number' => 1,
        'submission_type' => 'initial',
        'file_path' => 'proposal.pdf',
        'original_filename' => 'proposal.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 100,
        'checksum' => str_repeat('a', 64),
        'title' => 'Development of an Online College Research Journal Management System',
        'estimated_budget' => 75000,
        'estimated_duration_months' => 12,
    ]);

    $version->files()->createMany([
        [
            'document_type' => ProposalVersionFile::TYPE_DETAILED_PROPOSAL,
            'file_path' => 'detailed-proposal.docx',
            'original_filename' => 'detailed-proposal.docx',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'source_data' => [
                'project_leader_display' => 'Asst. Prof. D. IOANNA MARIE V. SALAC',
                'staff' => [
                    ['display_name' => 'Dr. FROILAN G. DESTREZA'],
                    ['title' => 'Mr.', 'name' => 'OLIVER M. HERNANDEZ'],
                ],
            ],
        ],
        [
            'document_type' => ProposalVersionFile::TYPE_LINE_ITEM_BUDGET,
            'file_path' => 'line-item-budget.docx',
            'original_filename' => 'line-item-budget.docx',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'source_data' => [
                'project_leader' => 'Duplicate leader must not be added',
                'staff' => [['name' => 'Duplicate staff must not be added']],
                'planned_start' => 'July 7, 2025',
                'planned_end' => 'July 6, 2026',
                'project_total' => 147128,
                'computed_project_total' => 147128,
                'resolution_number' => '01',
                'resolution_year' => 2025,
            ],
        ],
    ]);

    $defaults = app(NoticeToProceedDataService::class)->defaults($this->topic->fresh());

    expect($defaults['researcher_names'])->toBe([
        'Asst. Prof. D. IOANNA MARIE V. SALAC',
        'Dr. FROILAN G. DESTREZA',
        'Mr. OLIVER M. HERNANDEZ',
    ])->and($defaults['project_title'])->toBe('Development of an Online College Research Journal Management System')
        ->and($defaults['approved_start_date'])->toBe('2025-07-07')
        ->and($defaults['approved_end_date'])->toBe('2026-07-06')
        ->and($defaults['approved_budget'])->toBe('147128.00')
        ->and($defaults['resolution_number'])->toBe('01')
        ->and($defaults['resolution_year'])->toBe(2025);
});

test('a notice cannot be issued before proposal approval', function () {
    $this->topic->update(['status' => 'pending']);
    $payload = app(NoticeToProceedDataService::class)->defaults($this->topic);
    $payload['resolution_number'] = '01';

    $this->actingAs($this->head)
        ->from(route('topics.show', $this->topic))
        ->post(route('research_head.topics.notice-to-proceed.store', $this->topic), $payload)
        ->assertRedirect(route('topics.show', $this->topic))
        ->assertSessionHasErrors('notice_to_proceed');

    expect($this->topic->fresh()->notice_to_proceed_issued_at)->toBeNull()
        ->and($this->faculty->fresh()->hasRole('faculty_researcher'))->toBeFalse()
        ->and(Storage::disk('local')->allFiles('notices-to-proceed'))->toBeEmpty();
});
