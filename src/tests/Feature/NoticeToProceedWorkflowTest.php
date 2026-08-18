<?php

use App\Contracts\DocumentPdfConverter;
use App\Models\ProposalVersionFile;
use App\Models\ResearchCall;
use App\Models\TopicProposal;
use App\Models\User;
use App\Notifications\ProposalActivityNotification;
use App\Services\NoticeToProceedDataService;
use Illuminate\Http\UploadedFile;
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

test('the signed Notice to Proceed promotes the faculty member and opens monitoring', function () {
    expect($this->faculty->hasRole('faculty_researcher'))->toBeFalse()
        ->and($this->topic->isMonitoringAvailable())->toBeFalse();

    $this->withSession([
        User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY,
    ])->actingAs($this->faculty)
        ->get(route('topics.show', $this->topic))
        ->assertOk()
        ->assertSee('Approved - awaiting notice')
        ->assertSee('Review the details and prepare the unsigned PDF for signature.')
        ->assertDontSee('Project monitoring');

    $this->withSession([
        User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD,
    ])->actingAs($this->head)
        ->get(route('topics.show', $this->topic))
        ->assertOk()
        ->assertSee('Preview notice')
        ->assertSee('x-ref="previewFrame"', false)
        ->assertSee('Open preview')
        ->assertSee('Save notice details')
        ->assertSee('data-notice-to-proceed-autosave-form', false)
        ->assertSee('Faculty Owner')
        ->assertSee('75,000.00')
        ->assertSee('Previewing does not release anything. Faculty access and project monitoring remain locked until the signed PDF is uploaded.');

    $payload = app(NoticeToProceedDataService::class)->defaults($this->topic);
    $payload['resolution_number'] = '01';

    $this->withSession([
        User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD,
    ])->actingAs($this->head)
        ->post(route('research_head.topics.notice-to-proceed.store', $this->topic), $payload)
        ->assertRedirect(route('topics.show', $this->topic).'#notice-to-proceed')
        ->assertSessionHas('success', 'Notice details saved. Download the unsigned PDF, obtain the required signatures, then upload the signed copy to release it to the faculty researcher.');

    $this->topic->refresh();
    $this->faculty->refresh();

    expect($this->topic->notice_to_proceed_issued_at)->toBeNull()
        ->and($this->topic->notice_to_proceed_data['researcher_names'])->toBe(['Faculty Owner'])
        ->and($this->topic->notice_to_proceed_data['approved_budget'])->toBe('75000.00')
        ->and($this->topic->notice_to_proceed_data['approved_start_date'])->toBe('2026-09-01')
        ->and($this->topic->isMonitoringAvailable())->toBeFalse()
        ->and($this->faculty->hasRole('faculty_researcher'))->toBeFalse();

    expect(Storage::disk('local')->allFiles('notices-to-proceed'))->toBeEmpty();

    $this->withSession([
        User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY,
    ])->actingAs($this->faculty)
        ->get(route('topics.notice-to-proceed.download', $this->topic))
        ->assertNotFound();

    $this->withSession([
        User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD,
    ])->actingAs($this->head)
        ->get(route('topics.show', $this->topic))
        ->assertOk()
        ->assertSee('Download unsigned PDF')
        ->assertSee('Signed Notice to Proceed PDF')
        ->assertSee('Drop signed notice to proceed pdf here')
        ->assertSee('Release signed PDF')
        ->assertDontSee('Awaiting signed PDF')
        ->assertDontSee('Research Office')
        ->assertDontSee('Notice release workflow')
        ->assertDontSee('Complete signatures offline')
        ->assertDontSee('Upload and release the signed copy')
        ->assertDontSee('Choose the signed PDF')
        ->assertDontSee('Upload signed PDF and release');

    $this->withSession([
        User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD,
    ])->actingAs($this->head)
        ->get(route('research_head.topics.notice-to-proceed.download-unsigned', $this->topic))
        ->assertDownload('unsigned-notice-to-proceed-approved-coastal-research.pdf');

    expect(Storage::disk('local')->allFiles('notices-to-proceed'))->toBeEmpty();

    $this->withSession([
        User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD,
    ])->actingAs($this->head)
        ->post(route('research_head.topics.notice-to-proceed.upload-signed', $this->topic), [
            'signed_notice_to_proceed' => UploadedFile::fake()->create('signed-notice-to-proceed.pdf', 125, 'application/pdf'),
        ])
        ->assertRedirect(route('topics.show', $this->topic).'#notice-to-proceed')
        ->assertSessionHas('success', 'Signed Notice to Proceed uploaded and issued. Faculty Researcher access and project monitoring are now open.');

    $this->topic->refresh();
    $this->faculty->refresh();

    expect($this->topic->notice_to_proceed_issued_by)->toBe($this->head->id)
        ->and($this->topic->notice_to_proceed_original_filename)->toBe('signed-notice-to-proceed-approved-coastal-research.pdf')
        ->and($this->topic->project_status)->toBe('ongoing')
        ->and($this->topic->isMonitoringAvailable())->toBeTrue()
        ->and($this->faculty->hasRole('faculty_researcher'))->toBeTrue()
        ->and($this->faculty->notifications()->firstOrFail()->data['title'])->toBe('Signed Notice to Proceed issued')
        ->and($this->faculty->notifications()->firstOrFail()->data['url'])->toBe(route('topics.show', $this->topic).'#project-monitoring');

    Storage::disk('local')->assertExists($this->topic->notice_to_proceed_path);

    $this->withSession([
        User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY,
    ])->actingAs($this->faculty)
        ->get(route('topics.notice-to-proceed.download', $this->topic))
        ->assertDownload('signed-notice-to-proceed-approved-coastal-research.pdf');

    $this->withSession([
        User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY_RESEARCHER,
    ])->actingAs($this->faculty)
        ->get(route('research.show', $this->topic))
        ->assertOk()
        ->assertSee('Official project record')
        ->assertSee('Download PDF')
        ->assertSee('View notice details')
        ->assertSee('Project monitoring');
});

test('issuing the Notice to Proceed promotes every accepted linked collaborator into the shared researcher workspace', function () {
    $collaborator = User::factory()->create(['name' => 'Collaborating Researcher']);
    $collaborator->assignRole('faculty');
    $this->topic->collaborators()->create([
        'user_id' => $collaborator->id,
        'name' => $collaborator->name,
        'email' => $collaborator->email,
        'accepted_at' => now(),
    ]);
    $emailMatchedCollaborator = User::factory()->create([
        'name' => 'Email Matched Researcher',
        'email' => 'email.matched@g.batstate-u.edu.ph',
    ]);
    $emailMatchedCollaborator->assignRole('faculty');
    $this->topic->collaborators()->create([
        'user_id' => null,
        'name' => 'Invited Researcher',
        'email' => $emailMatchedCollaborator->email,
        'accepted_at' => now(),
    ]);
    $unacceptedCollaborator = User::factory()->create(['name' => 'Unaccepted Researcher']);
    $unacceptedCollaborator->assignRole('faculty');
    $this->topic->collaborators()->create([
        'user_id' => $unacceptedCollaborator->id,
        'name' => $unacceptedCollaborator->name,
        'email' => $unacceptedCollaborator->email,
        'accepted_at' => null,
    ]);

    expect($collaborator->hasRole('faculty_researcher'))->toBeFalse();
    expect($emailMatchedCollaborator->hasRole('faculty_researcher'))->toBeFalse();

    $payload = app(NoticeToProceedDataService::class)->defaults($this->topic);
    $payload['resolution_number'] = '01';

    $this->withSession([
        User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD,
    ])->actingAs($this->head)
        ->post(route('research_head.topics.notice-to-proceed.store', $this->topic), $payload)
        ->assertRedirect(route('topics.show', $this->topic).'#notice-to-proceed')
        ->assertSessionHasNoErrors();

    $this->withSession([
        User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD,
    ])->actingAs($this->head)
        ->post(route('research_head.topics.notice-to-proceed.upload-signed', $this->topic), [
            'signed_notice_to_proceed' => UploadedFile::fake()->create('signed-notice-to-proceed.pdf', 125, 'application/pdf'),
        ])
        ->assertRedirect(route('topics.show', $this->topic).'#notice-to-proceed')
        ->assertSessionHasNoErrors();

    $collaborator->refresh();
    $emailMatchedCollaborator->refresh();

    expect($collaborator->hasRole('faculty_researcher'))->toBeTrue()
        ->and($emailMatchedCollaborator->hasRole('faculty_researcher'))->toBeTrue()
        ->and($unacceptedCollaborator->fresh()->hasRole('faculty_researcher'))->toBeFalse()
        ->and($this->topic->collaborators()->where('email', $emailMatchedCollaborator->email)->sole()->user_id)
        ->toBe($emailMatchedCollaborator->id)
        ->and($this->faculty->notifications()->where('data->title', 'Signed Notice to Proceed issued')->sole()->data['sidebar_area'])
        ->toBe(ProposalActivityNotification::SIDEBAR_AREA_MY_PROJECTS)
        ->and($collaborator->notifications()->where('data->title', 'Signed Notice to Proceed issued')->count())
        ->toBe(1)
        ->and($emailMatchedCollaborator->notifications()->where('data->title', 'Signed Notice to Proceed issued')->count())
        ->toBe(1)
        ->and($unacceptedCollaborator->notifications()->where('data->title', 'Signed Notice to Proceed issued')->count())
        ->toBe(0);

    $this->withSession([
        User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY_RESEARCHER,
    ])->actingAs($collaborator)
        ->get(route('research.show', $this->topic))
        ->assertOk()
        ->assertSee('Approved Coastal Research')
        ->assertSee('Project team')
        ->assertSee('Collaborating Researcher')
        ->assertSee('Email Matched Researcher');
});

test('a Research Head can preview a Notice to Proceed without issuing it', function () {
    $payload = app(NoticeToProceedDataService::class)->defaults($this->topic);
    $payload['resolution_number'] = '01';

    $this->withSession([
        User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD,
    ])->actingAs($this->head)
        ->post(route('research_head.topics.notice-to-proceed.preview', $this->topic), $payload)
        ->assertOk()
        ->assertHeader('content-type', 'text/html; charset=UTF-8')
        ->assertSee('data-notice-to-proceed-sheet', false)
        ->assertSee('Local Research Evaluation Committee (LREC) Resolution No. 01, S. '.now()->year);

    expect($this->topic->fresh()->notice_to_proceed_issued_at)->toBeNull()
        ->and($this->faculty->fresh()->hasRole('faculty_researcher'))->toBeFalse()
        ->and(Storage::disk('local')->allFiles('notices-to-proceed'))->toBeEmpty();
});

test('Notice to Proceed autosave persists the LREC resolution number', function () {
    $payload = app(NoticeToProceedDataService::class)->defaults($this->topic);
    $payload['resolution_number'] = 'LREC-2026-014';

    $this->withSession([
        User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD,
    ])->actingAs($this->head)
        ->postJson(route('research_head.topics.notice-to-proceed.store', $this->topic), $payload)
        ->assertOk()
        ->assertJson([
            'saved' => true,
            'message' => 'Notice details saved.',
        ]);

    expect($this->topic->fresh()->notice_to_proceed_data['resolution_number'])
        ->toBe('LREC-2026-014');

    $this->withSession([
        User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD,
    ])->actingAs($this->head)
        ->get(route('topics.show', $this->topic))
        ->assertOk()
        ->assertSee('value="LREC-2026-014"', false);
});

test('a signed Notice to Proceed cannot be uploaded until its unsigned notice is prepared', function () {
    $this->withSession([
        User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD,
    ])->actingAs($this->head)
        ->from(route('topics.show', $this->topic))
        ->post(route('research_head.topics.notice-to-proceed.upload-signed', $this->topic), [
            'signed_notice_to_proceed' => UploadedFile::fake()->create('signed-notice-to-proceed.pdf', 125, 'application/pdf'),
        ])
        ->assertRedirect(route('topics.show', $this->topic))
        ->assertSessionHasErrors('signed_notice_to_proceed');

    expect($this->topic->fresh()->notice_to_proceed_issued_at)->toBeNull()
        ->and($this->faculty->fresh()->hasRole('faculty_researcher'))->toBeFalse()
        ->and(Storage::disk('local')->allFiles('notices-to-proceed'))->toBeEmpty();
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

test('a notice cannot be prepared before proposal approval', function () {
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
