<?php

use App\Models\ProposalVersionFile;
use App\Models\ResearchCall;
use App\Models\ResearchCategory;
use App\Models\TopicProposal;
use App\Models\TopicReview;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'faculty']);
    Role::firstOrCreate(['name' => 'faculty_researcher']);
    Role::firstOrCreate(['name' => 'research_head']);
    Role::firstOrCreate(['name' => 'expert']);

    $this->category = ResearchCategory::create(['name' => 'Environment']);
    $this->researchCall = ResearchCall::create([
        'title' => 'Test Research Call',
        'academic_year' => '2026-2027',
        'opens_at' => now()->subDay(),
        'closes_at' => now()->addMonth(),
        'max_active_research_per_faculty' => 2,
        'status' => 'open',
    ]);
    $this->researchCall->categories()->attach($this->category);

    TopicProposal::creating(function (TopicProposal $topic) {
        $topic->research_call_id ??= $this->researchCall->id;
        $topic->research_category_id ??= $this->category->id;
        $topic->estimated_duration_months ??= 12;
    });
});

test('a research head can request a revision with comments', function () {
    $head = User::factory()->create();
    $head->assignRole('research_head');

    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');

    $topic = TopicProposal::create([
        'user_id' => $faculty->id,
        'title' => 'Original proposal',
        'estimated_budget' => 10000,
        'initial_file_path' => 'proposals/original.pdf',
        'status' => 'pending',
    ]);

    $response = $this->actingAs($head)->patch("/research-head/topics/{$topic->id}/status", [
        'status' => 'revision_requested',
        'comment' => 'Clarify the methodology and reduce the travel budget.',
    ]);

    $response->assertRedirect(route('research_head.dashboard'));
    expect($topic->fresh()->status)->toBe('revision_requested');

    $this->assertDatabaseHas('topic_reviews', [
        'topic_id' => $topic->id,
        'reviewer_id' => $head->id,
        'decision' => 'revision_requested',
        'comment' => 'Clarify the methodology and reduce the travel budget.',
    ]);
});

test('revision and rejection decisions require review comments', function (string $decision) {
    $head = User::factory()->create();
    $head->assignRole('research_head');

    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');

    $topic = TopicProposal::create([
        'user_id' => $faculty->id,
        'title' => 'Proposal requiring feedback',
        'estimated_budget' => 5000,
        'initial_file_path' => 'proposals/original.pdf',
        'status' => 'pending',
    ]);

    $response = $this->actingAs($head)->from('/research-head/dashboard')->patch(
        "/research-head/topics/{$topic->id}/status",
        ['status' => $decision],
    );

    $response->assertRedirect(route('research_head.dashboard'));
    $response->assertSessionHasErrors('comment');
    expect($topic->fresh()->status)->toBe('pending');
    expect(TopicReview::count())->toBe(0);
})->with(['revision_requested', 'rejected']);

test('faculty can revise and resubmit a proposal after feedback', function () {
    Storage::fake('local');

    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');

    Storage::disk('local')->put('proposals/original.pdf', 'original document');

    $topic = TopicProposal::create([
        'user_id' => $faculty->id,
        'title' => 'Original proposal',
        'description' => 'Original description',
        'estimated_budget' => 10000,
        'initial_file_path' => 'proposals/original.pdf',
        'status' => 'revision_requested',
    ]);

    $originalVersion = $topic->versions()->create([
        'submitted_by' => $faculty->id,
        'version_number' => 1,
        'submission_type' => 'initial',
        'file_path' => 'proposals/original.pdf',
        'original_filename' => 'original.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 17,
        'checksum' => hash('sha256', 'original document'),
        'title' => 'Original proposal',
        'description' => 'Original description',
        'estimated_budget' => 10000,
        'estimated_duration_months' => 12,
    ]);

    $response = $this->actingAs($faculty)->patch("/faculty/topics/{$topic->id}/resubmit", [
        'title' => 'Revised proposal',
        'description' => 'Updated methodology',
        'estimated_budget' => 8500,
        'estimated_duration_months' => 10,
        'document' => UploadedFile::fake()->create('revised-proposal.pdf', 100, 'application/pdf'),
        'comment_response' => UploadedFile::fake()->create('comment-response.docx', 50, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
    ]);

    $response->assertRedirect(route('faculty.dashboard'));

    $topic->refresh();

    expect($topic->status)->toBe('resubmitted')
        ->and($topic->title)->toBe('Revised proposal')
        ->and($topic->estimated_budget)->toBe('8500.00')
        ->and($topic->versions()->count())->toBe(2)
        ->and($topic->latestVersion->version_number)->toBe(2)
        ->and($topic->latestVersion->title)->toBe('Revised proposal')
        ->and($topic->latestVersion->estimated_budget)->toBe('8500.00')
        ->and($topic->latestVersion->checksum)->toHaveLength(64);

    Storage::disk('local')->assertExists('proposals/original.pdf');
    Storage::disk('local')->assertExists($topic->latestVersion->file_path);

    $this->actingAs($faculty)
        ->get(route('topics.versions.download', [$topic, $originalVersion]))
        ->assertOk()
        ->assertDownload('original.pdf');
});

test('a research head cannot approve a resubmitted proposal before Initial Screening', function () {
    Storage::fake('local');
    $head = User::factory()->create();
    $head->assignRole('research_head');

    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');

    $topic = TopicProposal::create([
        'user_id' => $faculty->id,
        'title' => 'Revised proposal',
        'estimated_budget' => 8500,
        'initial_file_path' => 'proposals/original.pdf',
        'final_file_path' => 'proposals/revisions/revised.pdf',
        'status' => 'resubmitted',
    ]);

    $topic->reviews()->create([
        'reviewer_id' => $head->id,
        'decision' => 'revision_requested',
        'comment' => 'Make a small methodology revision.',
    ]);

    $response = $this->actingAs($head)
        ->from(route('research_head.dashboard'))
        ->patch("/research-head/topics/{$topic->id}/status", [
            'status' => 'approved',
            'comment' => 'The requested changes have been addressed.',
            'signed_approval' => UploadedFile::fake()->create('signed-approval.pdf', 100, 'application/pdf'),
        ]);

    $response->assertRedirect(route('research_head.dashboard'));
    $response->assertSessionHasErrors('status');

    expect($topic->fresh()->status)->toBe('resubmitted')
        ->and($topic->reviews()->count())->toBe(1)
        ->and($faculty->fresh()->hasRole('faculty_researcher'))->toBeFalse();
});

test('a proposal with unresolved co-evaluator comments cannot receive final approval', function () {
    Storage::fake('local');
    $head = User::factory()->create();
    $head->assignRole('research_head');
    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');
    $expert = User::factory()->create();
    $expert->assignRole('expert');

    $topic = TopicProposal::create([
        'user_id' => $faculty->id,
        'title' => 'Proposal with screening comments',
        'status' => 'for_final_decision',
    ]);
    $topic->expertAssignments()->create([
        'expert_id' => $expert->id,
        'assigned_by' => $head->id,
        'status' => 'completed',
        'recommendation' => 'recommend_revision',
        'comment' => 'Revise the methodology before final evaluation.',
        'reviewed_at' => now(),
    ]);

    $this->actingAs($head)
        ->patch(route('research_head.topics.updateStatus', $topic), [
            'status' => 'approved',
            'signed_approval' => UploadedFile::fake()->create('signed-approval.pdf', 100, 'application/pdf'),
        ])
        ->assertSessionHasErrors('status');

    expect($topic->fresh()->status)->toBe('for_final_decision')
        ->and($faculty->fresh()->hasRole('faculty_researcher'))->toBeFalse();
});

test('a rejected proposal remains final', function () {
    Storage::fake('local');
    $head = User::factory()->create();
    $head->assignRole('research_head');

    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');

    $topic = TopicProposal::create([
        'user_id' => $faculty->id,
        'title' => 'Rejected proposal',
        'estimated_budget' => 25000,
        'initial_file_path' => 'proposals/original.pdf',
        'status' => 'rejected',
    ]);

    $response = $this->actingAs($head)->from('/research-head/dashboard')->patch(
        "/research-head/topics/{$topic->id}/status",
        [
            'status' => 'approved',
            'signed_approval' => UploadedFile::fake()->create('signed-approval.pdf', 100, 'application/pdf'),
        ],
    );

    $response->assertRedirect(route('research_head.dashboard'));
    $response->assertSessionHasErrors('status');
    expect($topic->fresh()->status)->toBe('rejected');
});

test('review feedback and revision controls are visible on both dashboards', function () {
    $this->withoutVite();

    $head = User::factory()->create();
    $head->assignRole('research_head');

    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');

    $topic = TopicProposal::create([
        'user_id' => $faculty->id,
        'title' => 'Proposal awaiting revision',
        'estimated_budget' => 12000,
        'initial_file_path' => 'proposals/original.pdf',
        'status' => 'revision_requested',
    ]);

    $topic->reviews()->create([
        'reviewer_id' => $head->id,
        'decision' => 'revision_requested',
        'comment' => 'Please tighten the literature review.',
    ]);

    $this->actingAs($faculty)
        ->get('/faculty/dashboard')
        ->assertOk()
        ->assertSee('Please tighten the literature review.')
        ->assertSee('Revise and resubmit proposal')
        ->assertSee('Auto-filled Comment-Response Form')
        ->assertSee(route('faculty.topics.comment-response-form.preview', $topic), false)
        ->assertSee(route('faculty.topics.comment-response-form.download', $topic), false);

    $this->actingAs($faculty)
        ->get(route('topics.show', $topic))
        ->assertOk()
        ->assertSee('Auto-filled Comment-Response Form')
        ->assertSee('Completed comment-response form');

    $this->actingAs($head)
        ->get('/research-head/dashboard')
        ->assertOk()
        ->assertSee('Please tighten the literature review.')
        ->assertSee('Waiting for faculty revision');
});

test('faculty can preview and download an auto-filled official Comment-Response Form during revision', function () {
    $this->withoutVite();

    $faculty = User::factory()->create([
        'name' => 'Dr. Aurora Reyes',
        'college' => User::COLLEGES['CICS'],
    ]);
    $faculty->assignRole('faculty');

    $topic = TopicProposal::create([
        'user_id' => $faculty->id,
        'title' => 'Coastal Habitat Restoration',
        'estimated_budget' => 12000,
        'initial_file_path' => 'proposals/original.pdf',
        'status' => 'revision_requested',
    ]);
    $version = $topic->versions()->create([
        'submitted_by' => $faculty->id,
        'version_number' => 1,
        'submission_type' => 'initial',
        'file_path' => 'proposals/original.pdf',
        'original_filename' => 'original.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 100,
        'checksum' => str_repeat('a', 64),
        'title' => $topic->title,
        'estimated_budget' => 12000,
        'estimated_duration_months' => 12,
    ]);
    $version->files()->createMany([
        [
            'document_type' => ProposalVersionFile::TYPE_DETAILED_PROPOSAL,
            'position' => 0,
            'file_path' => 'proposals/detailed.docx',
            'original_filename' => 'detailed.docx',
            'source_data' => [
                'project_leader' => 'Dr. Aurora Reyes',
                'proponent_campus' => 'Alangilan',
                'proponent_college' => User::COLLEGES['CICS'],
                'proponent_department' => 'Department of Computing Sciences',
                'staff' => [
                    ['name' => 'Bea Santos', 'email' => 'bea@example.test', 'contact' => '09170000001'],
                    ['name' => 'Carlos Lim', 'email' => 'carlos@example.test', 'contact' => '09170000002'],
                ],
            ],
        ],
        [
            'document_type' => ProposalVersionFile::TYPE_LINE_ITEM_BUDGET,
            'position' => 0,
            'file_path' => 'proposals/budget.docx',
            'original_filename' => 'budget.docx',
            'source_data' => [
                'project_leader' => 'Dr. Aurora Reyes',
                'leader_campus' => 'Alangilan',
                'leader_college' => User::COLLEGES['CICS'],
                'staff' => [
                    ['name' => 'Bea Santos', 'campus' => 'Alangilan', 'college' => User::COLLEGES['CICS']],
                    ['name' => 'Carlos Lim', 'campus' => 'Lipa', 'college' => User::COLLEGES['CTE']],
                ],
            ],
        ],
    ]);

    $this->actingAs($faculty)
        ->get(route('faculty.topics.comment-response-form.preview', $topic))
        ->assertOk()
        ->assertSee('BatStateU Comment-Response Form')
        ->assertSee('Coastal Habitat Restoration')
        ->assertSee('Dr. Aurora Reyes')
        ->assertSee('Alangilan')
        ->assertSee('CICS')
        ->assertSee('Department of Computing Sciences')
        ->assertSee('Bea Santos')
        ->assertSee('Carlos Lim');

    $download = $this->actingAs($faculty)
        ->get(route('faculty.topics.comment-response-form.download', $topic))
        ->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')
        ->assertDownload('coastal-habitat-restoration-comment-response-form.docx');

    $temporaryPath = tempnam(sys_get_temp_dir(), 'athena-comment-response-test-');
    expect($temporaryPath)->not->toBeFalse();
    file_put_contents($temporaryPath, $download->streamedContent());

    $generated = new ZipArchive;
    $template = new ZipArchive;

    try {
        expect($generated->open($temporaryPath))->toBeTrue()
            ->and($template->open(config('comment_response_form.template_path')))->toBeTrue();

        $documentXml = $generated->getFromName('word/document.xml');
        $footerXml = $generated->getFromName('word/footer1.xml');
        expect($documentXml)->not->toBeFalse()
            ->and($footerXml)->not->toBeFalse();

        $documentDom = new DOMDocument;
        $footerDom = new DOMDocument;
        expect($documentDom->loadXML($documentXml, LIBXML_NONET))->toBeTrue()
            ->and($footerDom->loadXML($footerXml, LIBXML_NONET))->toBeTrue();

        expect($documentDom->textContent)
            ->toContain('Coastal Habitat Restoration')
            ->toContain('Dr. Aurora Reyes')
            ->toContain('Alangilan')
            ->toContain('CICS')
            ->toContain('Department of Computing Sciences')
            ->toContain('Bea Santos')
            ->toContain('Carlos Lim')
            ->toContain('Initial Screening')
            ->toContain('Evaluation by the Local Research Evaluation Committee (LREC)')
            ->toContain('COMMENTS AND SUGGESTIONS')
            ->toContain('ACTION AND RESPONSE')
            ->toContain('REMARKS')
            ->toContain('Research Head/ RDES Head')
            ->toContain('Vice Chancellor for Research, Development and Extension Services');
        expect($footerDom->textContent)
            ->toContain('Comment-Response Form | Coastal Habitat Restoration')
            ->not->toContain('insert the research proposal title here');

        $footerXpath = new DOMXPath($footerDom);
        $footerXpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        expect($footerXpath->query('//w:p[.//w:instrText[contains(., "PAGE")]]//w:t[text() = "1"]')->length)->toBe(2);

        $xpath = new DOMXPath($documentDom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        foreach ($xpath->query('/w:document/w:body/w:tbl[3]/w:tr[position() > 1]/w:tc[position() > 1]') as $responseCell) {
            expect(trim($responseCell->textContent))->toBe('');
        }

        for ($index = 0; $index < $template->numFiles; $index++) {
            $entry = $template->statIndex($index);
            $name = $entry['name'];

            if (! in_array($name, ['word/document.xml', 'word/footer1.xml'], true)) {
                expect($generated->getFromName($name))->toBe($template->getFromName($name));
            }
        }
    } finally {
        $generated->close();
        $template->close();
        unlink($temporaryPath);
    }
});

test('Comment-Response Form generation is private to the revision owner', function () {
    $owner = User::factory()->create();
    $owner->assignRole('faculty');
    $otherFaculty = User::factory()->create();
    $otherFaculty->assignRole('faculty');

    $revision = TopicProposal::create([
        'user_id' => $owner->id,
        'title' => 'Private revision',
        'estimated_budget' => 12000,
        'status' => 'revision_requested',
    ]);
    $pending = TopicProposal::create([
        'user_id' => $owner->id,
        'title' => 'No revision requested',
        'estimated_budget' => 12000,
        'status' => 'pending',
    ]);

    $this->actingAs($otherFaculty)
        ->get(route('faculty.topics.comment-response-form.preview', $revision))
        ->assertForbidden();
    $this->actingAs($otherFaculty)
        ->get(route('faculty.topics.comment-response-form.download', $revision))
        ->assertForbidden();
    $this->actingAs($owner)
        ->get(route('faculty.topics.comment-response-form.preview', $pending))
        ->assertForbidden();
});

test('faculty researchers can browse and open only their own research records', function () {
    $this->withoutVite();

    $faculty = User::factory()->create();
    $faculty->assignRole(['faculty', 'faculty_researcher']);

    $otherFaculty = User::factory()->create();
    $otherFaculty->assignRole(['faculty', 'faculty_researcher']);

    $ownTopic = TopicProposal::create([
        'user_id' => $faculty->id,
        'title' => 'My catalogued research',
        'description' => 'A visible research record.',
        'estimated_budget' => 14500,
        'initial_file_path' => 'proposals/own.pdf',
        'status' => 'revision_requested',
    ]);

    $ownTopic->versions()->create([
        'submitted_by' => $faculty->id,
        'version_number' => 1,
        'submission_type' => 'initial',
        'file_path' => 'proposals/own.pdf',
        'original_filename' => 'own.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 100,
        'checksum' => str_repeat('a', 64),
        'title' => $ownTopic->title,
        'description' => $ownTopic->description,
        'estimated_budget' => $ownTopic->estimated_budget,
        'estimated_duration_months' => $ownTopic->estimated_duration_months,
    ]);

    $otherTopic = TopicProposal::create([
        'user_id' => $otherFaculty->id,
        'title' => 'Another faculty research',
        'estimated_budget' => 9000,
        'initial_file_path' => 'proposals/other.pdf',
        'status' => 'pending',
    ]);

    $this->actingAs($faculty)
        ->get('/research')
        ->assertOk()
        ->assertSee('My catalogued research')
        ->assertDontSee('Another faculty research');

    $this->actingAs($faculty)
        ->get("/research/{$ownTopic->id}")
        ->assertOk()
        ->assertSee('revision requested')
        ->assertSee('PHP 14,500.00')
        ->assertSee('Proposal version history')
        ->assertSee('Version 1');

    $this->actingAs($faculty)
        ->get("/research/{$otherTopic->id}")
        ->assertForbidden();
});

test('the research catalog is unavailable to regular faculty and the research head', function () {
    $head = User::factory()->create();
    $head->assignRole('research_head');

    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');

    $this->actingAs($head)
        ->get('/research')
        ->assertForbidden();

    $this->actingAs($faculty)
        ->get('/research')
        ->assertForbidden();
});

test('research support is available to authenticated users', function () {
    $this->withoutVite();

    $researcher = User::factory()->create();
    $researcher->assignRole(['faculty', 'faculty_researcher']);

    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');

    $head = User::factory()->create();
    $head->assignRole('research_head');

    $this->actingAs($researcher)
        ->get('/research-support')
        ->assertOk()
        ->assertSee('Research Help Facility');

    $this->actingAs($faculty)
        ->get('/research-support')
        ->assertOk()
        ->assertSee('Research Help Facility');

    $this->actingAs($head)
        ->get('/research-support')
        ->assertOk();
});

test('proposal versions are downloadable only by authorized topic participants', function () {
    Storage::fake('local');

    $owner = User::factory()->create();
    $owner->assignRole('faculty');
    $otherFaculty = User::factory()->create();
    $otherFaculty->assignRole('faculty');
    $head = User::factory()->create();
    $head->assignRole('research_head');

    $topic = TopicProposal::create([
        'user_id' => $owner->id,
        'title' => 'Audited proposal',
        'estimated_budget' => 20000,
        'initial_file_path' => 'proposals/audited.pdf',
        'status' => 'pending',
    ]);

    Storage::disk('local')->put('proposals/audited.pdf', 'audited document');
    $version = $topic->versions()->create([
        'submitted_by' => $owner->id,
        'version_number' => 1,
        'submission_type' => 'initial',
        'file_path' => 'proposals/audited.pdf',
        'original_filename' => 'audited-proposal.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 16,
        'checksum' => hash('sha256', 'audited document'),
        'title' => $topic->title,
        'estimated_budget' => $topic->estimated_budget,
        'estimated_duration_months' => $topic->estimated_duration_months,
    ]);
    $packageFile = $version->files()->create([
        'document_type' => 'detailed_proposal',
        'position' => 0,
        'file_path' => 'proposals/audited.pdf',
        'original_filename' => 'audited-proposal.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 16,
        'checksum' => hash('sha256', 'audited document'),
        'is_carried_forward' => false,
    ]);

    $this->actingAs($owner)
        ->get(route('topics.versions.download', [$topic, $version]))
        ->assertDownload('audited-proposal.pdf');

    $this->actingAs($head)
        ->get(route('topics.versions.download', [$topic, $version]))
        ->assertDownload('audited-proposal.pdf');

    $this->actingAs($head)
        ->get(route('topics.versions.files.download', [$topic, $version, $packageFile]))
        ->assertDownload('audited-proposal.pdf');

    $inlineResponse = $this->actingAs($head)
        ->get(route('topics.versions.files.view', [$topic, $version, $packageFile]))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertHeader('x-content-type-options', 'nosniff');

    expect($inlineResponse->headers->get('content-disposition'))
        ->toContain('inline')
        ->toContain('audited-proposal.pdf');

    $this->actingAs($otherFaculty)
        ->get(route('topics.versions.download', [$topic, $version]))
        ->assertForbidden();

    $this->actingAs($otherFaculty)
        ->get(route('topics.versions.files.download', [$topic, $version, $packageFile]))
        ->assertForbidden();

    $this->actingAs($otherFaculty)
        ->get(route('topics.versions.files.view', [$topic, $version, $packageFile]))
        ->assertForbidden();
});

test('the proposal workspace is complete role-aware and private', function () {
    $this->withoutVite();
    Storage::fake('local');

    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');
    $head = User::factory()->create();
    $head->assignRole('research_head');
    $expert = User::factory()->create();
    $expert->assignRole('expert');
    $outsider = User::factory()->create();
    $outsider->assignRole('faculty');

    $topic = TopicProposal::create([
        'user_id' => $faculty->id,
        'title' => 'Workspace proposal',
        'description' => 'A complete package for review.',
        'estimated_budget' => 30000,
        'status' => 'pending',
    ]);

    $version = $topic->versions()->create([
        'submitted_by' => $faculty->id,
        'version_number' => 1,
        'submission_type' => 'initial',
        'file_path' => 'packages/proposal.pdf',
        'original_filename' => 'proposal.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 100,
        'checksum' => str_repeat('a', 64),
        'title' => $topic->title,
        'description' => $topic->description,
        'estimated_budget' => $topic->estimated_budget,
        'estimated_duration_months' => $topic->estimated_duration_months,
    ]);

    foreach ([
        'detailed_proposal' => 'proposal.pdf',
        'work_plan' => 'work-plan.docx',
        'line_item_budget' => 'budget.docx',
        'expense_breakdown' => 'expenses.xlsx',
        'curriculum_vitae' => 'cv.pdf',
        'gad_checklist' => 'gad-checklist.docx',
        'initial_screening_form' => 'initial-screening-form.docx',
    ] as $type => $filename) {
        $path = 'packages/'.$filename;
        Storage::disk('local')->put($path, $type);
        $version->files()->create([
            'document_type' => $type,
            'position' => 0,
            'file_path' => $path,
            'original_filename' => $filename,
            'file_size' => strlen($type),
            'checksum' => hash('sha256', $type),
            'is_carried_forward' => false,
        ]);
    }

    $topic->expertAssignments()->create([
        'expert_id' => $expert->id,
        'assigned_by' => $head->id,
        'status' => 'pending',
    ]);

    $this->actingAs($faculty)
        ->get(route('topics.show', $topic))
        ->assertOk()
        ->assertSee('Proposal package checklist')
        ->assertSee('7/7 complete');

    $this->actingAs($head)
        ->get(route('topics.show', $topic))
        ->assertOk()
        ->assertSee('Submitted proposal files')
        ->assertSee('7/7 PDFs available')
        ->assertSee('Detailed Research Proposal')
        ->assertSee('Initial Screening Form')
        ->assertSee('View PDF')
        ->assertSee('Download PDF')
        ->assertSee('Research Head action');

    $this->actingAs($expert)
        ->get(route('topics.show', $topic))
        ->assertOk()
        ->assertSee('Co-evaluator recommendation');

    $this->actingAs($outsider)
        ->get(route('topics.show', $topic))
        ->assertForbidden();

    $workPlanFile = $version->files()->where('document_type', 'work_plan')->firstOrFail();

    $this->actingAs($head)
        ->from(route('topics.show', $topic))
        ->patch(route('research_head.topics.updateStatus', $topic), [
            'status' => 'revision_requested',
            'comment' => 'Please revise the package.',
        ])
        ->assertSessionHasErrors('revision_file_ids');

    $this->actingAs($head)
        ->patch(route('research_head.topics.updateStatus', $topic), [
            'status' => 'revision_requested',
            'comment' => 'Please clarify the implementation schedule.',
            'revision_file_ids' => [$workPlanFile->id],
            'revision_file_notes' => [$workPlanFile->id => 'Extend the activities through the second year.'],
            'redirect_to' => 'topic',
        ])
        ->assertRedirect(route('topics.show', $topic));

    $fileRevision = $topic->reviews()->latest()->firstOrFail()->fileRevisions()->firstOrFail();

    expect($faculty->notifications()->firstOrFail()->data['url'])->toBe(route('topics.show', $topic))
        ->and($fileRevision->proposal_version_file_id)->toBe($workPlanFile->id)
        ->and($fileRevision->revision_note)->toContain('second year')
        ->and($fileRevision->resolved_at)->toBeNull();

    $this->actingAs($faculty)
        ->from(route('topics.show', $topic))
        ->patch(route('faculty.topics.resubmit', $topic), [
            'title' => $topic->title,
            'description' => $topic->description,
            'estimated_budget' => $topic->estimated_budget,
            'estimated_duration_months' => 18,
            'change_summary' => 'Updated the implementation schedule.',
            'comment_response' => UploadedFile::fake()->create('comment-response.docx', 50, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ])
        ->assertSessionHasErrors('work_plan', null, 'resubmission');

    $this->actingAs($faculty)
        ->patch(route('faculty.topics.resubmit', $topic), [
            'title' => $topic->title,
            'description' => $topic->description,
            'estimated_budget' => $topic->estimated_budget,
            'estimated_duration_months' => 18,
            'change_summary' => 'Updated the implementation schedule.',
            'work_plan' => UploadedFile::fake()->create('work-plan-v2.docx', 60, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
            'comment_response' => UploadedFile::fake()->create('comment-response.docx', 50, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ])
        ->assertRedirect(route('faculty.dashboard'));

    expect($topic->fresh()->status)->toBe('resubmitted')
        ->and($fileRevision->fresh()->resolved_at)->not->toBeNull()
        ->and($fileRevision->fresh()->resolutionFile?->original_filename)->toBe('work-plan-v2.docx');

    $this->actingAs($head)
        ->from(route('research_head.dashboard'))
        ->patch(route('research_head.topics.updateStatus', $topic), [
            'status' => 'expert_review',
            'expert_ids' => [$expert->id],
        ])
        ->assertRedirect(route('research_head.dashboard'))
        ->assertSessionHasNoErrors();

    $screening = $topic->expertAssignments()->latest('id')->firstOrFail();
    $this->actingAs($expert)
        ->patch(route('expert.assignments.submit', $screening), [
            'recommendation' => 'recommend_approval',
            'comment' => 'The revised work plan addresses the Initial Screening comments.',
        ])
        ->assertRedirect();

    $this->actingAs($head)
        ->patch(route('research_head.topics.updateStatus', $topic), [
            'status' => 'approved',
            'signed_approval' => UploadedFile::fake()->create('signed-approval.pdf', 100, 'application/pdf'),
        ])
        ->assertRedirect(route('research_head.dashboard'));

    expect($topic->fresh()->status)->toBe('approved')
        ->and($faculty->fresh()->hasRole('faculty_researcher'))->toBeTrue();
});
