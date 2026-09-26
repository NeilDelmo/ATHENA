<?php

use App\Contracts\DocumentPdfConverter;
use App\Livewire\ResearchHeadProposalFileChecklist;
use App\Models\ProposalFileAnnotation;
use App\Models\ProposalFileReviewCheck;
use App\Models\ProposalVersion;
use App\Models\ProposalVersionFile;
use App\Models\ResearchCall;
use App\Models\ResearchCategory;
use App\Models\TopicProposal;
use App\Models\User;
use App\Notifications\ProposalActivityNotification;
use App\Services\CommentResponseFeedback;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

function createTopicReviewSubmission(TopicProposal $topic, User $faculty): ProposalVersion
{
    $path = 'proposals/topic-review-'.$topic->id.'.pdf';
    Storage::disk('local')->put($path, 'submitted proposal');

    $version = $topic->versions()->create([
        'submitted_by' => $faculty->id,
        'version_number' => 1,
        'submission_type' => 'initial',
        'file_path' => $path,
        'original_filename' => 'submitted-proposal.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 18,
        'checksum' => hash('sha256', 'submitted proposal'),
        'title' => $topic->title,
        'estimated_budget' => $topic->estimated_budget,
        'estimated_duration_months' => $topic->estimated_duration_months,
    ]);

    $version->files()->create([
        'document_type' => ProposalVersionFile::TYPE_DETAILED_PROPOSAL,
        'position' => 0,
        'file_path' => $path,
        'original_filename' => 'submitted-proposal.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 18,
        'checksum' => hash('sha256', 'submitted proposal'),
        'is_carried_forward' => false,
    ]);

    return $version;
}

beforeEach(function () {
    Role::firstOrCreate(['name' => 'faculty']);
    Role::firstOrCreate(['name' => 'faculty_researcher']);
    Role::firstOrCreate(['name' => 'research_head']);

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

test('a research head can request a revision with highlighted comments', function () {
    Storage::fake('local');
    $head = User::factory()->create();
    $head->assignRole('research_head');

    $faculty = User::factory()->create();
    $faculty->assignRole(['faculty', 'faculty_researcher', 'research_head']);

    $topic = TopicProposal::create([
        'user_id' => $faculty->id,
        'title' => 'Original proposal',
        'estimated_budget' => 10000,
        'initial_file_path' => 'proposals/original.pdf',
        'status' => 'pending',
    ]);
    $version = createTopicReviewSubmission($topic, $faculty);
    $file = $version->files()->sole();
    $otherTopic = TopicProposal::create([
        'user_id' => $faculty->id,
        'title' => 'Another proposal awaiting review',
        'estimated_budget' => 12000,
        'initial_file_path' => 'proposals/another.pdf',
        'status' => 'pending',
    ]);
    $head->notify(new ProposalActivityNotification(
        title: 'New proposal submitted',
        message: 'Original proposal is ready for review.',
        url: route('topics.show', $topic),
        topicId: $topic->id,
        workspace: User::WORKSPACE_RESEARCH_HEAD,
        sidebarArea: ProposalActivityNotification::SIDEBAR_AREA_PROPOSAL_SUBMISSIONS,
    ));
    $head->notify(new ProposalActivityNotification(
        title: 'New proposal submitted',
        message: 'Another proposal is ready for review.',
        url: route('topics.show', $otherTopic),
        topicId: $otherTopic->id,
        workspace: User::WORKSPACE_RESEARCH_HEAD,
        sidebarArea: ProposalActivityNotification::SIDEBAR_AREA_PROPOSAL_SUBMISSIONS,
    ));
    $topicNotification = $head->notifications()->firstWhere('data->topic_id', $topic->id);
    $otherTopicNotification = $head->notifications()->firstWhere('data->topic_id', $otherTopic->id);
    $file->annotations()->create([
        'reviewer_id' => $head->id,
        'annotation_type' => ProposalFileAnnotation::TYPE_AREA,
        'page_number' => 1,
        'rectangles' => [['x' => 0.1, 'y' => 0.2, 'width' => 0.3, 'height' => 0.1]],
        'comment' => 'Clarify the methodology in this passage.',
    ]);

    $response = $this->actingAs($head)->patch("/research-head/topics/{$topic->id}/status", [
        'status' => 'revision_requested',
        'revision_file_ids' => [$file->id],
    ]);

    $response->assertRedirect(route('research_head.dashboard'));
    expect($topic->fresh()->status)->toBe('revision_requested');

    $this->actingAs($head)
        ->from(route('topics.show', $topic))
        ->patch(route('research_head.topics.updateStatus', $topic), [
            'status' => 'revision_requested',
            'revision_file_ids' => [$file->id],
        ])
        ->assertRedirect(route('topics.show', $topic))
        ->assertSessionHasErrors([
            'status' => 'A revision round is already open. Wait for the faculty member to submit the current revision before recording another decision.',
        ]);

    expect($topic->reviews()->count())->toBe(1)
        ->and($faculty->notifications()->count())->toBe(1);

    $this->actingAs($head)
        ->get(route('topics.show', $topic))
        ->assertOk()
        ->assertSee('Waiting for the faculty revision')
        ->assertSee('This revision request is locked while the faculty member works.')
        ->assertDontSee('Record the Research Head decision');

    expect($topic->fresh()->status)->toBe('revision_requested');
    expect($topicNotification->fresh()->read_at)->not->toBeNull()
        ->and($otherTopicNotification->fresh()->read_at)->toBeNull();
    expect($topic->latestVersion->files()
        ->where('document_type', ProposalVersionFile::TYPE_HEAD_UPLOAD)
        ->count())->toBe(0);

    $notification = $faculty->notifications()->sole();
    expect($notification->data['workspace'])->toBe([
        User::WORKSPACE_FACULTY_RESEARCHER,
        User::WORKSPACE_FACULTY,
    ]);

    $this->withSession([
        User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD,
    ])->actingAs($faculty)
        ->getJson(route('notifications.index'))
        ->assertOk()
        ->assertJsonCount(0, 'notifications')
        ->assertJsonPath('unread_count', 0);

    $this->withSession([
        User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY,
    ])->actingAs($faculty)
        ->getJson(route('notifications.index'))
        ->assertOk()
        ->assertJsonCount(1, 'notifications')
        ->assertJsonPath('notifications.0.data.title', 'Revision requested')
        ->assertJsonPath('unread_count', 1);

    $this->withSession([
        User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY_RESEARCHER,
    ])->actingAs($faculty)
        ->getJson(route('notifications.index'))
        ->assertOk()
        ->assertJsonCount(1, 'notifications')
        ->assertJsonPath('notifications.0.data.title', 'Revision requested')
        ->assertJsonPath('unread_count', 1);

    $this->assertDatabaseHas('topic_reviews', [
        'topic_id' => $topic->id,
        'reviewer_id' => $head->id,
        'decision' => 'revision_requested',
        'comment' => null,
    ]);
});

test('the Research Head must record and confirm a rejection reason before rejecting a proposal', function () {
    Storage::fake('local');
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
    createTopicReviewSubmission($topic, $faculty);

    $response = $this->actingAs($head)->from('/research-head/dashboard')->patch(
        "/research-head/topics/{$topic->id}/status",
        [
            'status' => 'rejected',
        ],
    );

    $response->assertRedirect(route('research_head.dashboard'))
        ->assertSessionHasErrors(['rejection_reason', 'rejection_confirmed']);

    expect($topic->fresh()->status)->toBe('pending')
        ->and($topic->reviews()->count())->toBe(0);

    $response = $this->actingAs($head)->from('/research-head/dashboard')->patch(
        "/research-head/topics/{$topic->id}/status",
        [
            'status' => 'rejected',
            'rejection_reason' => 'The proposal does not meet the research call requirements.',
            'rejection_confirmed' => '1',
        ],
    );

    $response->assertRedirect(route('research_head.dashboard'))
        ->assertSessionHas('success', 'Proposal rejected.');

    $rejectionReview = $topic->reviews()->where('decision', 'rejected')->sole();

    expect($topic->fresh()->status)->toBe('rejected')
        ->and($rejectionReview->comment)->toBe('The proposal does not meet the research call requirements.');

    $this->actingAs($head)
        ->get(route('topics.show', $topic))
        ->assertOk()
        ->assertSee('Rejection reason')
        ->assertSee('The proposal does not meet the research call requirements.');
});

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
        ->and($topic->latestVersion->checksum)->toHaveLength(64)
        ->and($topic->latestVersion->files->contains('document_type', ProposalVersionFile::TYPE_COMMENT_RESPONSE))->toBeFalse();

    Storage::disk('local')->assertExists('proposals/original.pdf');
    Storage::disk('local')->assertExists($topic->latestVersion->file_path);

    $this->actingAs($faculty)
        ->get(route('topics.versions.download', [$topic, $originalVersion]))
        ->assertOk()
        ->assertDownload('original.pdf');
});

test('faculty revision submission uses the topic revision draft when the browser omits its identifier', function () {
    Storage::fake('local');

    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');
    $head = User::factory()->create();
    $head->assignRole('research_head');

    $topic = TopicProposal::create([
        'user_id' => $faculty->id,
        'title' => 'Generated revision package',
        'description' => 'Original description',
        'estimated_budget' => 3000,
        'status' => 'revision_requested',
    ]);
    $version = $topic->versions()->create([
        'submitted_by' => $faculty->id,
        'version_number' => 1,
        'submission_type' => 'initial',
        'file_path' => 'proposals/original-proposal.pdf',
        'original_filename' => 'original-proposal.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 17,
        'checksum' => hash('sha256', 'original proposal'),
        'title' => $topic->title,
        'description' => $topic->description,
        'estimated_budget' => $topic->estimated_budget,
        'estimated_duration_months' => $topic->estimated_duration_months,
    ]);
    $originalFiles = collect([
        ProposalVersionFile::TYPE_DETAILED_PROPOSAL => ['path' => 'proposals/original-proposal.pdf', 'source' => ['summary' => 'Original']],
        ProposalVersionFile::TYPE_LINE_ITEM_BUDGET => ['path' => 'proposals/original-budget.pdf', 'source' => ['amounts' => ['telephone_expenses' => 3000]]],
        ProposalVersionFile::TYPE_WORK_PLAN => ['path' => 'proposals/original-work-plan.docx', 'source' => ['entries' => [['activity' => 'Original activity']]]],
    ])->map(function (array $file, string $documentType) use ($version): ProposalVersionFile {
        Storage::disk('local')->put($file['path'], $documentType);

        return $version->files()->create([
            'document_type' => $documentType,
            'position' => 0,
            'file_path' => $file['path'],
            'original_filename' => basename($file['path']),
            'mime_type' => str_ends_with($file['path'], '.pdf') ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'file_size' => strlen($documentType),
            'checksum' => hash('sha256', $documentType),
            'source_data' => $file['source'],
            'is_carried_forward' => false,
        ]);
    });
    $review = $topic->reviews()->create([
        'reviewer_id' => $head->id,
        'decision' => 'revision_requested',
    ]);
    $originalFiles->each(fn (ProposalVersionFile $file) => $review->fileRevisions()->create([
        'proposal_version_file_id' => $file->id,
        'document_type' => $file->document_type,
        'original_filename' => $file->original_filename,
        'revision_note' => 'Update this paper.',
    ]));

    $draft = $topic->revisionDraft()->create([
        'user_id' => $faculty->id,
        'research_call_id' => $this->researchCall->id,
        'project_title' => $topic->title,
        'duration_months' => 12,
        'project_leader' => $faculty->name,
        'status' => 'draft',
    ]);
    foreach ([
        ProposalVersionFile::TYPE_LINE_ITEM_BUDGET => ['path' => 'proposal-drafts/revision/revised-budget.pdf', 'source' => ['amounts' => ['telephone_expenses' => 3500]]],
        ProposalVersionFile::TYPE_WORK_PLAN => ['path' => 'proposal-drafts/revision/revised-work-plan.docx', 'source' => ['entries' => [['activity' => 'Revised activity']]]],
    ] as $documentType => $file) {
        Storage::disk('local')->put($file['path'], $documentType.' revised');
        $draft->documents()->create([
            'document_type' => $documentType,
            'position' => 0,
            'source_data' => $file['source'],
            'file_path' => $file['path'],
            'original_filename' => basename($file['path']),
            'mime_type' => str_ends_with($file['path'], '.pdf') ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'file_size' => Storage::disk('local')->size($file['path']),
            'checksum' => hash('sha256', $documentType.' revised'),
            'completed_at' => now(),
        ]);
    }

    $response = $this->actingAs($faculty)
        ->from(route('topics.show', $topic))
        ->patch(route('faculty.topics.resubmit', $topic), [
            'title' => $topic->title,
            'description' => $topic->description,
            'estimated_budget' => 3500,
            'estimated_duration_months' => 12,
            'redirect_to' => 'topic',
            'revision_resolutions' => [
                ProposalVersionFile::TYPE_DETAILED_PROPOSAL => [
                    'action' => 'no_change',
                    'explanation' => 'The detailed proposal already addresses the comment.',
                ],
            ],
        ]);

    $response->assertRedirect(route('topics.show', $topic));
    expect($response->getSession()->get('errors', []))->toBe([]);

    expect($topic->fresh()->status)->toBe('resubmitted')
        ->and($topic->latestVersion->files->firstWhere('document_type', ProposalVersionFile::TYPE_LINE_ITEM_BUDGET)?->source_data)
        ->toBe(['amounts' => ['telephone_expenses' => 3500]])
        ->and($review->fileRevisions()->whereNull('resolved_at')->count())->toBe(0);
});

test('a Research Head may request another revision only after receiving the faculty resubmission', function () {
    Storage::fake('local');
    $head = User::factory()->create();
    $head->assignRole('research_head');
    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');

    $topic = TopicProposal::create([
        'user_id' => $faculty->id,
        'title' => 'Resubmitted proposal',
        'estimated_budget' => 10000,
        'status' => 'resubmitted',
    ]);
    $version = createTopicReviewSubmission($topic, $faculty);
    $file = $version->files()->sole();
    $file->annotations()->create([
        'reviewer_id' => $head->id,
        'annotation_type' => ProposalFileAnnotation::TYPE_AREA,
        'page_number' => 1,
        'rectangles' => [['x' => 0.1, 'y' => 0.2, 'width' => 0.3, 'height' => 0.1]],
        'comment' => 'Clarify the new methodology.',
    ]);

    $this->actingAs($head)
        ->patch(route('research_head.topics.updateStatus', $topic), [
            'status' => 'revision_requested',
            'revision_file_ids' => [$file->id],
        ])
        ->assertRedirect(route('research_head.dashboard'))
        ->assertSessionHasNoErrors();

    expect($topic->fresh()->status)->toBe('revision_requested')
        ->and($topic->reviews()->where('decision', 'revision_requested')->count())->toBe(1)
        ->and($faculty->notifications()->where('data->title', 'Revision requested')->count())->toBe(1);
});

test('a research head can finalize approval for a resubmitted proposal after signing', function () {
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
    $version = createTopicReviewSubmission($topic, $faculty);
    $signatureFile = $version->files()->sole();

    $topic->reviews()->create([
        'reviewer_id' => $head->id,
        'decision' => 'revision_requested',
        'comment' => 'Make a small methodology revision.',
    ]);

    $response = $this->actingAs($head)
        ->from(route('research_head.dashboard'))
        ->patch("/research-head/topics/{$topic->id}/status", [
            'status' => TopicProposal::STATUS_READY_FOR_SIGNATURE,
            'signature_file_ids' => [$signatureFile->id],
        ]);

    $response->assertRedirect(route('research_head.dashboard'))->assertSessionHasNoErrors();

    $this->actingAs($head)
        ->post(route('topics.head-uploads.store', $topic), [
            'source_file_id' => $signatureFile->id,
            'review_file' => UploadedFile::fake()->create('signed-proposal.pdf', 100, 'application/pdf'),
            'purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SIGNED,
        ])
        ->assertSessionHasNoErrors();

    $this->actingAs($head)
        ->patch(route('research_head.topics.finalizeApproval', $topic))
        ->assertSessionHasNoErrors();

    expect($topic->fresh()->status)->toBe('approved')
        ->and($topic->reviews()->count())->toBe(4)
        ->and($topic->fresh()->project_status)->toBeNull()
        ->and($faculty->fresh()->hasRole('faculty_researcher'))->toBeFalse();
});

test('decision history is collapsed and organized newest first', function () {
    $head = User::factory()->create();
    $head->assignRole('research_head');

    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');

    $topic = TopicProposal::create([
        'user_id' => $faculty->id,
        'title' => 'Proposal with several decisions',
        'status' => 'rejected',
    ]);

    $olderReview = $topic->reviews()->create([
        'reviewer_id' => $head->id,
        'decision' => 'revision_requested',
        'comment' => 'Older revision request.',
    ]);
    $olderReview->forceFill([
        'created_at' => now()->subWeek(),
        'updated_at' => now()->subWeek(),
    ])->save();

    $topic->reviews()->create([
        'reviewer_id' => $head->id,
        'decision' => 'rejected',
        'comment' => 'Newest rejection reason.',
    ]);
    $topic->reviews()->create([
        'reviewer_id' => $head->id,
        'decision' => 'head_upload',
        'comment' => 'Administrative upload record.',
    ]);

    $response = $this->actingAs($faculty)
        ->get(route('topics.show', $topic))
        ->assertOk()
        ->assertSee('data-decision-history', false)
        ->assertSee('2 decisions')
        ->assertSee('Latest:')
        ->assertSee('Rejected')
        ->assertSee('View history')
        ->assertSeeInOrder(['Newest rejection reason.', 'Older revision request.']);

    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);

    expect($xpath->query('//section[@data-decision-history][@data-initially-open="false"]//button[@aria-controls="decision-history-list"]')->length)->toBe(1)
        ->and($xpath->query('//section[@data-decision-history]//*[@data-decision-history-list]//li')->length)->toBe(2);
});

test('legacy review records do not block the Research Head from starting final signing', function () {
    Storage::fake('local');
    $head = User::factory()->create();
    $head->assignRole('research_head');
    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');
    $legacyReviewer = User::factory()->create();

    $topic = TopicProposal::create([
        'user_id' => $faculty->id,
        'title' => 'Proposal with screening comments',
        'status' => 'for_final_decision',
    ]);
    $version = createTopicReviewSubmission($topic, $faculty);
    $signatureFile = $version->files()->sole();
    $topic->expertAssignments()->create([
        'expert_id' => $legacyReviewer->id,
        'assigned_by' => $head->id,
        'status' => 'completed',
        'recommendation' => 'recommend_revision',
        'comment' => 'Revise the methodology before final evaluation.',
        'reviewed_at' => now(),
    ]);

    $this->actingAs($head)
        ->patch(route('research_head.topics.updateStatus', $topic), [
            'status' => TopicProposal::STATUS_READY_FOR_SIGNATURE,
            'signature_file_ids' => [$signatureFile->id],
        ])
        ->assertSessionHasNoErrors();

    expect($topic->fresh()->status)->toBe(TopicProposal::STATUS_READY_FOR_SIGNATURE)
        ->and($topic->fresh()->project_status)->toBeNull()
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
    createTopicReviewSubmission($topic, $faculty);

    $response = $this->actingAs($head)->from('/research-head/dashboard')->patch(
        "/research-head/topics/{$topic->id}/status",
        [
            'status' => 'approved',
            'evaluation_document' => UploadedFile::fake()->create('completed-evaluation.pdf', 100, 'application/pdf'),
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
    $version = $topic->versions()->create([
        'submitted_by' => $faculty->id,
        'version_number' => 1,
        'submission_type' => 'initial',
        'file_path' => 'proposals/original.pdf',
        'original_filename' => 'original.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 100,
        'title' => $topic->title,
        'estimated_budget' => $topic->estimated_budget,
        'estimated_duration_months' => 12,
    ]);
    $version->files()->create([
        'document_type' => ProposalVersionFile::TYPE_COMMENT_RESPONSE,
        'file_path' => 'proposals/presentation-comment-response.docx',
        'original_filename' => 'presentation-comment-response.docx',
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'file_size' => 50,
    ]);

    $this->actingAs($faculty)
        ->get('/faculty/dashboard')
        ->assertOk()
        ->assertSee('Please tighten the literature review.')
        ->assertSee('Revise and resubmit proposal')
        ->assertDontSee('Auto-filled Comment-Response Form')
        ->assertDontSee('Completed comment-response form');

    $this->actingAs($faculty)
        ->get(route('topics.show', $topic))
        ->assertOk()
        ->assertDontSee('Auto-filled Comment-Response Form')
        ->assertDontSee('Completed comment-response form')
        ->assertDontSee('presentation-comment-response.docx')
        ->assertSee('Decision history')
        ->assertSee('Submit revision')
        ->assertSee('data-revision-proposal-details-button', false)
        ->assertSee('aria-controls="proposal-details-fields"', false)
        ->assertSee('data-initially-open="false"', false)
        ->assertDontSee('<summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-gray-700', false)
        ->assertSee('data-topic-file-dropzone="detailed_proposal"', false)
        ->assertSee('data-topic-file-dropzone="curricula_vitae"', false)
        ->assertSee('Choose or drop replacement file')
        ->assertSee('Choose or drop replacement files')
        ->assertSee('data-confirm-title="Submit this revision to the Research Head?"', false);

    $this->actingAs($head)
        ->get('/research-head/dashboard')
        ->assertOk()
        ->assertSee('Please tighten the literature review.')
        ->assertSee('Waiting for faculty revision');
});

test('research details reads total project cost from the line-item budget attachment', function () {
    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');

    $topic = TopicProposal::create([
        'user_id' => $faculty->id,
        'title' => 'Budgeted coastal habitat restoration',
        'estimated_budget' => 0,
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
        'estimated_duration_months' => 12,
    ]);
    $version->files()->create([
        'document_type' => ProposalVersionFile::TYPE_LINE_ITEM_BUDGET,
        'position' => 0,
        'file_path' => 'proposal-packages/budget.xlsx',
        'original_filename' => 'budget.xlsx',
        'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'file_size' => 100,
        'checksum' => str_repeat('b', 64),
        'source_data' => ['project_total' => 43210.5],
        'is_carried_forward' => false,
    ]);

    $this->actingAs($faculty)
        ->get(route('topics.show', $topic))
        ->assertOk()
        ->assertSee('PHP 43,210.50')
        ->assertSee('value="43210.5"', false);
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

    app()->instance(DocumentPdfConverter::class, new class implements DocumentPdfConverter
    {
        public function convertDocx(string $contents): string
        {
            return "%PDF-1.7\ngenerated comment-response form";
        }

        public function convertXlsx(string $contents): string
        {
            throw new LogicException('An XLSX conversion was not expected.');
        }
    });

    $pdf = $this->actingAs($faculty)
        ->get(route('faculty.topics.comment-response-form.pdf', $topic))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertHeader('x-content-type-options', 'nosniff')
        ->assertContent("%PDF-1.7\ngenerated comment-response form");

    expect($pdf->headers->get('content-disposition'))
        ->toContain('inline')
        ->toContain('coastal-habitat-restoration-research-head-comment-response-form.pdf');

    $download = $this->actingAs($faculty)
        ->get(route('faculty.topics.comment-response-form.download', $topic))
        ->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')
        ->assertDownload('coastal-habitat-restoration-research-head-comment-response-form.docx');

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
        $settingsDom = new DOMDocument;
        $settingsDom->loadXML($generated->getFromName('word/settings.xml'), LIBXML_NONET);
        $settingsXpath = new DOMXPath($settingsDom);
        $settingsXpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        expect($footerXpath->query('//w:p[.//w:instrText[contains(., "PAGE")]]//w:t[text() = "1"]')->length)->toBe(2)
            ->and($settingsXpath->query('/w:settings/w:updateFields[@w:val = "true"]')->length)->toBe(1);

        $xpath = new DOMXPath($documentDom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        foreach ($xpath->query('/w:document/w:body/w:tbl[3]/w:tr[position() > 1]/w:tc[position() > 1]') as $responseCell) {
            expect(trim($responseCell->textContent))->toBe('');
        }

        for ($index = 0; $index < $template->numFiles; $index++) {
            $entry = $template->statIndex($index);
            $name = $entry['name'];

            if (! in_array($name, ['word/document.xml', 'word/footer1.xml', 'word/settings.xml'], true)) {
                expect($generated->getFromName($name))->toBe($template->getFromName($name));
            }
        }
    } finally {
        $generated->close();
        $template->close();
        unlink($temporaryPath);
    }
});

test('Research Head and co evaluator feedback generate separate Comment-Response Forms', function () {
    $this->withoutVite();
    $head = User::factory()->create(['name' => 'Prof. Neil Delmo']);
    $head->assignRole('research_head');
    $faculty = User::factory()->create(['name' => 'Dr. Aurora Reyes']);
    $faculty->assignRole('faculty');
    $topic = TopicProposal::create([
        'user_id' => $faculty->id,
        'title' => 'Mangrove Recovery Study',
        'estimated_budget' => 25000,
        'estimated_duration_months' => 12,
        'status' => 'revision_requested',
    ]);
    $version = $topic->versions()->create([
        'submitted_by' => $faculty->id,
        'version_number' => 1,
        'submission_type' => 'initial',
        'file_path' => 'proposals/mangrove.pdf',
        'original_filename' => 'mangrove.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 100,
        'checksum' => str_repeat('c', 64),
        'title' => $topic->title,
        'estimated_budget' => 25000,
        'estimated_duration_months' => 12,
    ]);
    $detailedProposal = $version->files()->create([
        'document_type' => ProposalVersionFile::TYPE_DETAILED_PROPOSAL,
        'position' => 0,
        'file_path' => 'proposals/mangrove-detailed.pdf',
        'original_filename' => 'mangrove-detailed.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 100,
        'checksum' => str_repeat('d', 64),
        'source_data' => ['project_leader' => $faculty->name],
    ]);
    $initialScreening = $version->files()->create([
        'document_type' => ProposalVersionFile::TYPE_INITIAL_SCREENING_FORM,
        'position' => 0,
        'file_path' => 'proposals/initial-screening.docx',
        'original_filename' => 'initial-screening.docx',
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'file_size' => 100,
        'checksum' => str_repeat('e', 64),
    ]);
    $evaluation = $version->files()->create([
        'source_version_file_id' => $initialScreening->id,
        'document_type' => ProposalVersionFile::TYPE_HEAD_UPLOAD,
        'position' => 0,
        'file_path' => 'proposals/completed-initial-screening.docx',
        'original_filename' => 'completed-initial-screening.docx',
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'file_size' => 100,
        'checksum' => str_repeat('f', 64),
        'source_data' => [
            'purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_EVALUATION,
            'target_document_type' => ProposalVersionFile::TYPE_INITIAL_SCREENING_FORM,
            'co_evaluator_name' => 'Dr. Maria Santos',
            'narrative_evaluation' => 'The methodology needs a clearer sampling frame.',
        ],
        'uploaded_by' => $head->id,
    ]);
    $review = $topic->reviews()->create([
        'reviewer_id' => $head->id,
        'decision' => 'revision_requested',
    ]);
    $fileRevision = $review->fileRevisions()->create([
        'proposal_version_file_id' => $detailedProposal->id,
        'document_type' => $detailedProposal->document_type,
        'original_filename' => $detailedProposal->original_filename,
    ]);
    $detailedProposal->annotations()->create([
        'reviewer_id' => $head->id,
        'topic_review_file_revision_id' => $fileRevision->id,
        'feedback_source' => ProposalFileAnnotation::SOURCE_HEAD,
        'annotation_type' => ProposalFileAnnotation::TYPE_AREA,
        'page_number' => 2,
        'rectangles' => [['x' => 0.1, 'y' => 0.2, 'width' => 0.3, 'height' => 0.1]],
        'comment' => 'Clarify the participant recruitment timeline.',
    ]);

    $headQuery = ['topic' => $topic, 'source' => CommentResponseFeedback::FORM_RESEARCH_HEAD, 'review' => $review->id];
    $coEvaluatorQuery = ['topic' => $topic, 'source' => CommentResponseFeedback::FORM_CO_EVALUATOR, 'review' => $review->id];

    $this->actingAs($faculty)
        ->get(route('faculty.topics.comment-response-form.preview', $headQuery))
        ->assertOk()
        ->assertSee('Research Head Comment-Response Form')
        ->assertSee('Clarify the participant recruitment timeline.')
        ->assertDontSee('The methodology needs a clearer sampling frame.');
    $this->get(route('faculty.topics.comment-response-form.preview', $coEvaluatorQuery))
        ->assertOk()
        ->assertSee('Co-evaluator Comment-Response Form')
        ->assertSee('Dr. Maria Santos')
        ->assertSee('The methodology needs a clearer sampling frame.')
        ->assertDontSee('Clarify the participant recruitment timeline.');
    $this->get(route('faculty.topics.comment-response-form.download', $headQuery))
        ->assertOk()
        ->assertDownload('mangrove-recovery-study-research-head-comment-response-form.docx');
    $this->get(route('faculty.topics.comment-response-form.download', $coEvaluatorQuery))
        ->assertOk()
        ->assertDownload('mangrove-recovery-study-co-evaluator-comment-response-form.docx');
    $this->get(route('topics.show', $topic))
        ->assertOk()
        ->assertSee('Research Head Comment-Response Form')
        ->assertSee('Co-evaluator Comment-Response Form')
        ->assertSee('data-comment-response-source="research_head"', false)
        ->assertSee('data-comment-response-source="co_evaluator"', false);

    expect($evaluation->source_data['narrative_evaluation'])->toBe('The methodology needs a clearer sampling frame.');
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

test('faculty researchers can browse and open only their own approved research records', function () {
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
        'status' => 'approved',
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
        ->assertSee('Awaiting Notice to Proceed')
        ->assertDontSee('Another faculty research');

    $this->actingAs($faculty)
        ->get("/research/{$ownTopic->id}")
        ->assertOk()
        ->assertSee('Approved - awaiting notice')
        ->assertSee('PHP 14,500.00')
        ->assertSee('Proposal package')
        ->assertSee('Decision history')
        ->assertSee('id="notice-to-proceed-tab-button"', false)
        ->assertSee('id="notice-to-proceed-tab"', false)
        ->assertSee('@click="setTopicTab(\'notice\', \'notice-to-proceed\')"', false)
        ->assertSee('Submitted version comparison')
        ->assertSee('Submitted proposal versions')
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
    Storage::disk('local')->put('proposals/work-plan.docx', 'work plan document');
    $wordPackageFile = $version->files()->create([
        'document_type' => ProposalVersionFile::TYPE_WORK_PLAN,
        'position' => 0,
        'file_path' => 'proposals/work-plan.docx',
        'original_filename' => 'work-plan.docx',
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'file_size' => 18,
        'checksum' => hash('sha256', 'work plan document'),
        'is_carried_forward' => false,
    ]);
    $pdfConverter = new class implements DocumentPdfConverter
    {
        public ?string $receivedContents = null;

        public function convertDocx(string $contents): string
        {
            $this->receivedContents = $contents;

            return "%PDF-1.7\nconverted work plan";
        }

        public function convertXlsx(string $contents): string
        {
            throw new LogicException('An XLSX conversion was not expected.');
        }
    };
    app()->instance(DocumentPdfConverter::class, $pdfConverter);

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

    $this->withoutVite();
    $workspace = $this->actingAs($head)
        ->get(route('topics.show', $topic))
        ->assertOk();
    $workspaceDom = new DOMDocument;
    @$workspaceDom->loadHTML($workspace->getContent());
    $workspaceXpath = new DOMXPath($workspaceDom);
    $wordViewUrl = route('topics.versions.files.view', [$topic, $version, $wordPackageFile]);

    expect($workspaceXpath->query('//a[@href="'.$wordViewUrl.'" and normalize-space()="Preview PDF"]')->length)
        ->toBe(1);

    $wordPreview = $this->actingAs($head)
        ->get(route('topics.versions.files.view', [$topic, $version, $wordPackageFile]))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertHeader('x-content-type-options', 'nosniff')
        ->assertStreamedContent("%PDF-1.7\nconverted work plan");

    expect($wordPreview->headers->get('content-disposition'))
        ->toContain('inline')
        ->toContain('work-plan.pdf')
        ->and($pdfConverter->receivedContents)->toBe('work plan document');

    $this->actingAs($otherFaculty)
        ->get(route('topics.versions.download', [$topic, $version]))
        ->assertForbidden();

    $this->actingAs($otherFaculty)
        ->get(route('topics.versions.files.download', [$topic, $version, $packageFile]))
        ->assertForbidden();

    $this->actingAs($otherFaculty)
        ->get(route('topics.versions.files.view', [$topic, $version, $packageFile]))
        ->assertForbidden();

    $this->actingAs($otherFaculty)
        ->get(route('topics.versions.files.view', [$topic, $version, $wordPackageFile]))
        ->assertForbidden();
});

test('the proposal workspace is complete role-aware and private', function () {
    $this->withoutVite();
    Storage::fake('local');

    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');
    $head = User::factory()->create();
    $head->assignRole('research_head');
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
        'work_plan' => 'work-plan.pdf',
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
            'mime_type' => Str::endsWith($filename, '.pdf') ? 'application/pdf' : null,
            'file_size' => strlen($type),
            'checksum' => hash('sha256', $type),
            'is_carried_forward' => false,
        ]);
    }

    $this->actingAs($faculty)
        ->get(route('topics.show', $topic))
        ->assertOk()
        ->assertSee('Proposal package')
        ->assertSee('Research details')
        ->assertSee('Decision history')
        ->assertDontSee('Research Head documents')
        ->assertSee('Submitted version comparison')
        ->assertSee('Submitted proposal versions')
        ->assertDontSee('Proposal package checklist');

    $this->actingAs($head)
        ->get(route('topics.show', $topic))
        ->assertOk()
        ->assertSee('Proposal package')
        ->assertSee('7/7 files available')
        ->assertDontSee('id="notice-to-proceed-tab-button"', false)
        ->assertSee('Detailed Research Proposal')
        ->assertSee('Initial Screening Form')
        ->assertSee('View')
        ->assertSee('Download')
        ->assertSee('Latest submitted package')
        ->assertSee('Open the project folder to view these files together with signed papers')
        ->assertDontSee('Review latest package')
        ->assertSee('data-latest-review-version="1"', false)
        ->assertSee('Reviewing Version 1 &mdash; latest submitted package', false)
        ->assertSee('Record the Research Head decision')
        ->assertSee('Submitted documents')
        ->assertDontSee('Your paper review checklist')
        ->assertDontSee('One clear review process')
        ->assertDontSee('Review faculty files')
        ->assertDontSee('No revision')
        ->assertSee('Needs revision')
        ->assertSee('Mark for revision')
        ->assertDontSee('Annotate before revision')
        ->assertSee('aria-label="Review Detailed Research Proposal"', false)
        ->assertSee('data-revision-file-list', false)
        ->assertSee('More options for Detailed Research Proposal')
        ->assertSee('documents marked for revision.')
        ->assertSee('Preview PDF')
        ->assertSee('data-review-and-highlight', false)
        ->assertSee('?decision=revision_requested', false)
        ->assertSee('id="file-review-card-', false)
        ->assertSee('window.location.hash.startsWith(\'#file-review-card-\')', false)
        ->assertDontSee('openAnnotationModal', false)
        ->assertDontSee('Which papers need a signed final PDF?')
        ->assertDontSee('Continue to final signing')
        ->assertSee('data-review-decision-options', false)
        ->assertDontSee('Nothing is selected automatically.')
        ->assertDontSee('Record note (optional)')
        ->assertDontSee('Completed evaluation document')
        ->assertDontSee('Decision notes')
        ->assertSee('Send revision request');

    $this->actingAs($head)
        ->get(route('topics.show', $topic).'?decision=revision_requested')
        ->assertOk()
        ->assertSee("decision: 'revision_requested'", false);

    $this->actingAs($outsider)
        ->get(route('topics.show', $topic))
        ->assertForbidden();

    $workPlanFile = $version->files()->where('document_type', 'work_plan')->firstOrFail();

    $firstAnnotation = $workPlanFile->annotations()->create([
        'reviewer_id' => $head->id,
        'annotation_type' => ProposalFileAnnotation::TYPE_AREA,
        'page_number' => 2,
        'rectangles' => [['x' => 0.12, 'y' => 0.3, 'width' => 0.5, 'height' => 0.08]],
        'comment' => 'Extend these activities through the second year.',
    ]);

    $this->actingAs($head)
        ->patch(route('research_head.topics.updateStatus', $topic), [
            'status' => 'revision_requested',
            'comment' => 'Please clarify the implementation schedule.',
            'revision_file_ids' => [$workPlanFile->id],
            'revision_file_notes' => [$workPlanFile->id => 'Extend the activities through the second year.'],
            'redirect_to' => 'topic',
            'evaluation_document' => UploadedFile::fake()->create('completed-evaluation.pdf', 100, 'application/pdf'),
        ]);

    $fileRevision = $topic->reviews()->latest()->firstOrFail()->fileRevisions()->firstOrFail();

    $expectedNotificationUrl = route('faculty.topics.revision', ['topic' => $topic, 'revision_annotation' => $firstAnnotation->id]);

    expect($faculty->notifications()->firstOrFail()->data['url'])->toBe($expectedNotificationUrl)
        ->and($fileRevision->proposal_version_file_id)->toBe($workPlanFile->id)
        ->and($fileRevision->revision_note)->toContain('second year')
        ->and($fileRevision->resolved_at)->toBeNull();

    $this->actingAs($faculty)
        ->get(route('topics.show', $topic))
        ->assertOk();

    $this->actingAs($faculty)
        ->patch(route('faculty.topics.resubmit', $topic), [
            'title' => $topic->title,
            'description' => $topic->description,
            'estimated_budget' => $topic->estimated_budget,
            'estimated_duration_months' => 18,
            'change_summary' => 'Updated the implementation schedule.',
        ]);

    $this->actingAs($faculty)
        ->patch(route('faculty.topics.resubmit', $topic), [
            'title' => $topic->title,
            'description' => $topic->description,
            'estimated_budget' => $topic->estimated_budget,
            'estimated_duration_months' => 18,
            'change_summary' => 'Updated the implementation schedule.',
            'work_plan' => UploadedFile::fake()->create('work-plan-v2.docx', 60, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ]);

    expect($topic->fresh()->status)->toBe('resubmitted')
        ->and($fileRevision->fresh()->resolved_at)->not->toBeNull()
        ->and($fileRevision->fresh()->resolutionFile?->original_filename)->toBe('work-plan-v2.docx');

    $latestVersion = $topic->latestVersion()->with('files')->firstOrFail();
    $signatureFileIds = $latestVersion->files
        ->whereIn('document_type', [
            ProposalVersionFile::TYPE_DETAILED_PROPOSAL,
            ProposalVersionFile::TYPE_WORK_PLAN,
            ProposalVersionFile::TYPE_LINE_ITEM_BUDGET,
            ProposalVersionFile::TYPE_GAD_CHECKLIST,
        ])
        ->pluck('id')
        ->all();

    $this->actingAs($head)
        ->patch(route('research_head.topics.updateStatus', $topic), [
            'status' => TopicProposal::STATUS_READY_FOR_SIGNATURE,
            'signature_file_ids' => $signatureFileIds,
            'evaluation_document' => UploadedFile::fake()->create('final-evaluation.pdf', 100, 'application/pdf'),
        ])
        ->assertRedirect(route('research_head.dashboard'));

    foreach ([
        ProposalVersionFile::TYPE_DETAILED_PROPOSAL,
        ProposalVersionFile::TYPE_WORK_PLAN,
        ProposalVersionFile::TYPE_LINE_ITEM_BUDGET,
        ProposalVersionFile::TYPE_GAD_CHECKLIST,
    ] as $documentType) {
        $sourceFile = $latestVersion->files->firstWhere('document_type', $documentType);

        $this->actingAs($head)
            ->post(route('topics.head-uploads.store', $topic), [
                'source_file_id' => $sourceFile->id,
                'review_file' => UploadedFile::fake()->create("signed-{$documentType}.pdf", 100, 'application/pdf'),
                'purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SIGNED,
            ])
            ->assertRedirect(route('topics.show', $topic).'#proposal-review');
    }

    $this->actingAs($head)
        ->patch(route('research_head.topics.finalizeApproval', $topic))
        ->assertRedirect(route('topics.show', $topic).'#proposal-review');

    expect($topic->fresh()->status)->toBe('approved')
        ->and($topic->fresh()->project_status)->toBeNull()
        ->and($faculty->fresh()->hasRole('faculty_researcher'))->toBeFalse();
});

test('research heads can persist an independent reviewed-paper checklist', function () {
    Storage::fake('local');

    $head = User::factory()->create();
    $head->assignRole('research_head');
    $otherHead = User::factory()->create();
    $otherHead->assignRole('research_head');
    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');

    $topic = TopicProposal::create([
        'user_id' => $faculty->id,
        'title' => 'Checklist proposal',
        'estimated_budget' => 25000,
        'initial_file_path' => 'proposals/checklist.pdf',
        'status' => 'pending',
    ]);
    $version = createTopicReviewSubmission($topic, $faculty);
    $detailedProposal = $version->files()->sole();
    $workPlanPath = 'proposals/checklist-work-plan.pdf';
    Storage::disk('local')->put($workPlanPath, 'submitted work plan');
    $workPlan = $version->files()->create([
        'document_type' => ProposalVersionFile::TYPE_WORK_PLAN,
        'position' => 0,
        'file_path' => $workPlanPath,
        'original_filename' => 'checklist-work-plan.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 19,
        'checksum' => hash('sha256', 'submitted work plan'),
        'is_carried_forward' => false,
    ]);
    $head->notify(new ProposalActivityNotification(
        title: 'New proposal submitted',
        message: 'Checklist proposal is ready for review.',
        url: route('topics.show', $topic),
        topicId: $topic->id,
        workspace: User::WORKSPACE_RESEARCH_HEAD,
        sidebarArea: ProposalActivityNotification::SIDEBAR_AREA_PROPOSAL_SUBMISSIONS,
    ));
    $notification = $head->notifications()->sole();

    $checklist = Livewire::actingAs($head)
        ->test(ResearchHeadProposalFileChecklist::class, [
            'topic' => $topic,
            'version' => $version,
        ])
        ->assertSee('0 of 2 reviewed')
        ->call('toggle', $detailedProposal->id)
        ->assertHasNoErrors()
        ->assertSee('1 of 2 reviewed');

    $reviewCheck = ProposalFileReviewCheck::query()->sole();

    expect($reviewCheck->proposal_version_file_id)->toBe($detailedProposal->id)
        ->and($reviewCheck->reviewer_id)->toBe($head->id)
        ->and($reviewCheck->reviewed_at)->not->toBeNull()
        ->and($notification->fresh()->read_at)->toBeNull();

    Livewire::actingAs($head)
        ->test(ResearchHeadProposalFileChecklist::class, [
            'topic' => $topic,
            'version' => $version,
        ])
        ->assertSee('1 of 2 reviewed');

    Livewire::actingAs($otherHead)
        ->test(ResearchHeadProposalFileChecklist::class, [
            'topic' => $topic,
            'version' => $version,
        ])
        ->assertSee('0 of 2 reviewed');

    Livewire::actingAs($head);
    $checklist
        ->call('toggle', $workPlan->id)
        ->assertHasNoErrors()
        ->assertSee('2 of 2 reviewed')
        ->assertSee('Review complete')
        ->call('toggle', $detailedProposal->id)
        ->assertHasNoErrors()
        ->assertSee('1 of 2 reviewed');

    expect(ProposalFileReviewCheck::query()->count())->toBe(1)
        ->and(ProposalFileReviewCheck::query()->sole()->proposal_version_file_id)->toBe($workPlan->id)
        ->and($notification->fresh()->read_at)->toBeNull();
});

test('paper review checklist component is limited to research heads and files in the displayed version', function () {
    Storage::fake('local');

    $head = User::factory()->create();
    $head->assignRole('research_head');
    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');

    $topic = TopicProposal::create([
        'user_id' => $faculty->id,
        'title' => 'Scoped checklist proposal',
        'estimated_budget' => 18000,
        'initial_file_path' => 'proposals/scoped-checklist.pdf',
        'status' => 'pending',
    ]);
    $version = createTopicReviewSubmission($topic, $faculty);

    $otherTopic = TopicProposal::create([
        'user_id' => $faculty->id,
        'title' => 'Another checklist proposal',
        'estimated_budget' => 22000,
        'initial_file_path' => 'proposals/another-checklist.pdf',
        'status' => 'pending',
    ]);
    $otherVersion = createTopicReviewSubmission($otherTopic, $faculty);
    $otherFile = $otherVersion->files()->sole();

    Livewire::actingAs($faculty)
        ->test(ResearchHeadProposalFileChecklist::class, [
            'topic' => $topic,
            'version' => $version,
        ])
        ->assertForbidden();

    Livewire::actingAs($head)
        ->test(ResearchHeadProposalFileChecklist::class, [
            'topic' => $topic,
            'version' => $version,
        ])
        ->call('toggle', $otherFile->id)
        ->assertNotFound();

    expect(ProposalFileReviewCheck::query()->count())->toBe(0);

    $this->actingAs($head)
        ->get(route('topics.show', $topic))
        ->assertOk()
        ->assertDontSee('Your paper review checklist')
        ->assertSee('Latest submitted package')
        ->assertDontSee('Review latest package')
        ->assertSee('Submitted documents');

    $this->actingAs($faculty)
        ->get(route('topics.show', $topic))
        ->assertOk()
        ->assertDontSee('Your paper review checklist');
});

test('research heads review and request changes only against the latest resubmitted version', function () {
    Storage::fake('local');

    $head = User::factory()->create();
    $head->assignRole('research_head');
    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');

    $topic = TopicProposal::create([
        'user_id' => $faculty->id,
        'title' => 'Latest revision proposal',
        'estimated_budget' => 18000,
        'initial_file_path' => 'proposals/latest-revision-v1.pdf',
        'status' => 'resubmitted',
    ]);
    $originalVersion = createTopicReviewSubmission($topic, $faculty);
    $originalFile = $originalVersion->files()->sole();

    $revisedPath = 'proposals/latest-revision-v2.pdf';
    Storage::disk('local')->put($revisedPath, 'latest revised proposal');
    $latestVersion = $topic->versions()->create([
        'submitted_by' => $faculty->id,
        'version_number' => 2,
        'submission_type' => 'revision',
        'file_path' => $revisedPath,
        'original_filename' => 'latest-revision-v2.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 23,
        'checksum' => hash('sha256', 'latest revised proposal'),
        'title' => $topic->title,
        'estimated_budget' => $topic->estimated_budget,
        'estimated_duration_months' => $topic->estimated_duration_months,
    ]);
    $latestFile = $latestVersion->files()->create([
        'document_type' => ProposalVersionFile::TYPE_DETAILED_PROPOSAL,
        'position' => 0,
        'file_path' => $revisedPath,
        'original_filename' => 'latest-revision-v2.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 23,
        'checksum' => hash('sha256', 'latest revised proposal'),
        'is_carried_forward' => false,
    ]);
    $latestFile->annotations()->create([
        'reviewer_id' => $head->id,
        'annotation_type' => ProposalFileAnnotation::TYPE_AREA,
        'page_number' => 1,
        'rectangles' => [['x' => 0.1, 'y' => 0.2, 'width' => 0.3, 'height' => 0.1]],
        'comment' => 'Clarify this part of the revised methodology.',
    ]);

    $this->actingAs($head)
        ->get(route('topics.show', $topic))
        ->assertOk()
        ->assertSee('data-latest-review-version="2"', false)
        ->assertSee('data-latest-review-version-id="'.$latestVersion->id.'"', false)
        ->assertSee('Reviewing Version 2 &mdash; latest submitted package', false)
        ->assertSee('data-file-review-card="'.$latestFile->id.'"', false)
        ->assertDontSee('data-file-review-card="'.$originalFile->id.'"', false);

    $this->actingAs($head)
        ->from(route('topics.show', $topic))
        ->patch(route('research_head.topics.updateStatus', $topic), [
            'status' => 'revision_requested',
            'revision_file_ids' => [$originalFile->id],
            'redirect_to' => 'topic',
        ])
        ->assertSessionHasErrors([
            'revision_file_ids' => 'Every selected file must belong to the latest proposal version.',
        ]);

    expect($topic->fresh()->status)->toBe('resubmitted');

    $this->actingAs($head)
        ->patch(route('research_head.topics.updateStatus', $topic), [
            'status' => 'revision_requested',
            'revision_file_ids' => [$latestFile->id],
            'redirect_to' => 'topic',
        ])
        ->assertRedirect(route('topics.show', $topic))
        ->assertSessionHas('success', 'Revision requested; highlighted comments and file-specific instructions were shared with the faculty member.');

    expect($topic->fresh()->status)->toBe('revision_requested')
        ->and($topic->reviews()->latest()->firstOrFail()->fileRevisions()->sole()->proposal_version_file_id)
        ->toBe($latestFile->id);
});
