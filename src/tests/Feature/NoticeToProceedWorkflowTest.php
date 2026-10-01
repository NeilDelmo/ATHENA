<?php

use App\Contracts\DocumentPdfConverter;
use App\Models\ProposalVersionFile;
use App\Models\ResearchCall;
use App\Models\TopicProposal;
use App\Models\User;
use App\Notifications\ProposalActivityNotification;
use App\Services\NoticeToProceedDataService;
use App\Services\ProposalSignatureWorkflow;
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

test('the notice form keeps editable details visible above the signed upload', function (bool $prepared) {
    completeSignedProposalPackage($this->topic, $this->faculty, $this->head);
    if ($prepared) {
        $this->topic->update(['notice_to_proceed_data' => app(NoticeToProceedDataService::class)->defaults($this->topic)]);
    }

    $response = $this->actingAs($this->head)
        ->withSession(['active_workspace' => 'research_head'])
        ->get(route('topics.show', $this->topic));

    $response->assertOk()
        ->assertSee('Project staff')
        ->assertSee('Add project staff')
        ->assertDontSee('Add researcher')
        ->assertDontSee('Researcher names')
        ->assertSee('Save notice details')
        ->assertDontSee('Save corrected details')
        ->assertDontSee('Request paper revision');
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    $section = $xpath->query('//section[@id="notice-to-proceed"]')->item(0);

    expect($xpath->query('.//details', $section)->length)->toBe(0)
        ->and($xpath->query('.//section[@data-notice-signatories]', $section)->length)->toBe(1);
    if ($prepared) {
        $response->assertSeeInOrder(['data-notice-to-proceed-autosave-form', 'name="approved_budget"', 'name="verifying_officer_name"', 'data-notice-signed-upload'], false);
        expect($xpath->query('.//section[@data-notice-signed-upload]//button[@type="submit"]/svg', $section)->length)->toBe(1);
        expect($xpath->query('.//a[span="Download unsigned PDF"]/svg', $section)->length)->toBe(1);
    }
    foreach (['.//button[@data-notice-to-proceed-preview-button]', './/form[@data-notice-to-proceed-autosave-form]//button[@type="submit"]'] as $action) {
        expect($xpath->query($action.'/svg', $section)->length)->toBe(1);
    }
    expect($section)->not->toBeNull()
        ->and($section->getAttribute('class'))->toContain('ntp-workspace');
    expect($xpath->query('.//*[contains(@class, "bg-gray-950") or contains(@class, "bg-gradient-to-l")]', $section)->length)->toBe(0);
    expect($xpath->query('.//h4', $section)->item(0)->textContent)->toBe('Project details');
    expect($xpath->query('.//h4', $section)->item(1)->textContent)->toBe('Approval record');
    expect($xpath->query('.//h4', $section)->item(2)->textContent)->toBe('Schedule and budget');

    foreach (['campus_line', 'project_title', 'notice_date', 'resolution_number', 'resolution_year', 'approved_start_date', 'approved_end_date', 'approved_duration_months', 'approved_budget', 'issuing_officer_name', 'verifying_officer_name'] as $field) {
        expect($xpath->query('.//form[@data-notice-to-proceed-autosave-form]//*[@name="'.$field.'"]', $section)->length)->toBe(1);
    }

    $previewButton = $xpath->query('.//button[@data-notice-to-proceed-preview-button]', $section)->item(0);
    $previewPanel = $xpath->query('.//*[@id="'.$previewButton->getAttribute('aria-controls').'"]', $section)->item(0);
    expect($previewPanel)->not->toBeNull()
        ->and($previewPanel->getAttribute('role'))->toBe('dialog')
        ->and($xpath->query('.//*[@data-proposal-preview-floating]', $section)->length)->toBe(1)
        ->and($xpath->query('.//iframe[@*[name()="x-bind:srcdoc" and .="previewHtml"]]', $previewPanel)->length)->toBe(1)
        ->and($xpath->query('.//*[@x-ref="previewSection"]', $section)->length)->toBe(0);

    if (getenv('ATHENA_EXPORT_NOTICE_LAYOUT') === '1') {
        file_put_contents(storage_path('framework/testing/notice-form-layout.html'), $response->getContent());
    }
})->with(['new notice' => false, 'saved notice' => true]);

test('the signed Notice to Proceed promotes the faculty member and opens monitoring', function () {
    completeSignedProposalPackage($this->topic, $this->faculty, $this->head);

    expect($this->faculty->hasRole('faculty_researcher'))->toBeFalse()
        ->and($this->topic->isMonitoringAvailable())->toBeFalse();

    $this->withSession([
        User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY,
    ])->actingAs($this->faculty)
        ->get(route('topics.show', $this->topic))
        ->assertOk()
        ->assertSee('Final signing')
        ->assertDontSee('Manage signatory names')
        ->assertSee('Reviews are complete. The research office is collecting signed papers and the signed Notice to Proceed. They will be released together.')
        ->assertDontSee('Project monitoring');

    $this->withSession([
        User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD,
    ])->actingAs($this->head)
        ->get(route('topics.show', $this->topic))
        ->assertOk()
        ->assertSee('Manage signatory names')
        ->assertSeeInOrder(['id="notice-to-proceed-tab"', 'Manage signatory names'], false)
        ->assertSee(route('signatories.index'), false)
        ->assertSee('Preview notice')
        ->assertSee('x-ref="previewFrame"', false)
        ->assertSee('Refresh preview')
        ->assertSee('Full screen')
        ->assertSee('Document zoom controls')
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
        ->assertSee('Release papers and Notice to Proceed')
        ->assertDontSee('Awaiting signed PDF')
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
        ->assertSessionHas('success', 'Signed papers and Notice to Proceed released together. Faculty Researcher access and project monitoring are now open.');

    $this->topic->refresh();
    $this->faculty->refresh();

    expect($this->topic->notice_to_proceed_issued_by)->toBe($this->head->id)
        ->and($this->topic->notice_to_proceed_original_filename)->toBe('signed-notice-to-proceed-approved-coastal-research.pdf')
        ->and($this->topic->project_status)->toBe('ongoing')
        ->and($this->topic->isMonitoringAvailable())->toBeTrue()
        ->and($this->faculty->hasRole('faculty_researcher'))->toBeTrue()
        ->and($this->faculty->notifications()->firstOrFail()->data['title'])->toBe('Signed papers and Notice to Proceed released')
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
        ->get(route('topics.show', $this->topic))
        ->assertOk()
        ->assertSee('Download signed PDF')
        ->assertSee('Released documents')
        ->assertSee('Project monitoring');
});

test('issuing the Notice to Proceed promotes every accepted linked collaborator into the shared researcher workspace', function () {
    completeSignedProposalPackage($this->topic, $this->faculty, $this->head);

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
        ->and($this->faculty->notifications()->where('data->title', 'Signed papers and Notice to Proceed released')->sole()->data['sidebar_area'])
        ->toBe(ProposalActivityNotification::SIDEBAR_AREA_MY_PROJECTS)
        ->and($collaborator->notifications()->where('data->title', 'Signed papers and Notice to Proceed released')->count())
        ->toBe(1)
        ->and($emailMatchedCollaborator->notifications()->where('data->title', 'Signed papers and Notice to Proceed released')->count())
        ->toBe(1)
        ->and($unacceptedCollaborator->notifications()->where('data->title', 'Signed papers and Notice to Proceed released')->count())
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
        ->assertSee('notice-to-proceed-republic', false)
        ->assertSee('notice-to-proceed-address', false)
        ->assertSee('notice-to-proceed-footer', false)
        ->assertSee('Number of Hours to be Rendered Weekly in the Conduct of Research')
        ->assertSee('Leading Innovations. Transforming Lives. Building the Nation.')
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

    $payload['resolution_number'] = 'LREC-2026-015';

    $this->withSession([
        User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD,
    ])->actingAs($this->head)
        ->postJson(route('research_head.topics.notice-to-proceed.store', $this->topic), $payload)
        ->assertOk()
        ->assertJsonPath('saved', true);

    expect($this->topic->fresh()->notice_to_proceed_data['resolution_number'])
        ->toBe('LREC-2026-015');

    $this->withSession([
        User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD,
    ])->actingAs($this->head)
        ->get(route('topics.show', $this->topic))
        ->assertOk()
        ->assertSee('value="LREC-2026-015"', false);
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
        ->assertSee('No projects found')
        ->assertSee('Projects appear here after the final papers are completed and the Notice to Proceed is issued.')
        ->assertDontSee(route('topics.show', $this->topic).'#project-monitoring', false);
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

function completeSignedProposalPackage(TopicProposal $topic, User $faculty, User $head): void
{
    $version = $topic->versions()->create([
        'submitted_by' => $faculty->id,
        'version_number' => 1,
        'submission_type' => 'initial',
        'file_path' => 'proposal-packages/final-package.pdf',
        'original_filename' => 'final-package.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 100,
        'checksum' => str_repeat('a', 64),
        'title' => $topic->title,
        'estimated_budget' => $topic->estimated_budget,
        'estimated_duration_months' => $topic->estimated_duration_months,
    ]);

    foreach (ProposalSignatureWorkflow::REQUIRED_DOCUMENT_TYPES as $position => $documentType) {
        $sourcePath = "proposal-packages/{$documentType}.pdf";
        $signedPath = "head-uploads/signed-{$documentType}.pdf";
        Storage::disk('local')->put($sourcePath, '%PDF-1.4 original');
        Storage::disk('local')->put($signedPath, '%PDF-1.4 signed');

        $source = $version->files()->create([
            'document_type' => $documentType,
            'position' => $position,
            'file_path' => $sourcePath,
            'original_filename' => "{$documentType}.pdf",
            'mime_type' => 'application/pdf',
            'file_size' => 100,
            'checksum' => hash('sha256', "original-{$documentType}"),
            'is_carried_forward' => false,
        ]);

        $version->files()->create([
            'source_version_file_id' => $source->id,
            'document_type' => ProposalVersionFile::TYPE_HEAD_UPLOAD,
            'position' => 100 + $position,
            'file_path' => $signedPath,
            'original_filename' => "signed-{$documentType}.pdf",
            'mime_type' => 'application/pdf',
            'file_size' => 100,
            'checksum' => hash('sha256', "signed-{$documentType}"),
            'uploaded_by' => $head->id,
            'source_data' => [
                'purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SIGNED,
                'target_document_type' => $documentType,
            ],
        ]);
    }

    $topic->update(['status' => TopicProposal::STATUS_READY_FOR_SIGNATURE]);
}
