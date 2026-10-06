<?php

use App\Contracts\DocumentPdfConverter;
use App\Livewire\ResearchHeadProposalFileChecklist;
use App\Models\ProposalDraft;
use App\Models\ProposalFileAnnotation;
use App\Models\ProposalFileReviewCheck;
use App\Models\ProposalSignatory;
use App\Models\ProposalVersion;
use App\Models\ProposalVersionFile;
use App\Models\ResearchCall;
use App\Models\ResearchCategory;
use App\Models\TopicProposal;
use App\Models\User;
use App\Notifications\ProposalActivityNotification;
use App\Services\CommentResponseFeedback;
use App\Services\ProposalFormVerifier;
use App\Services\ProposalSignatureWorkflow;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

function createTopicReviewSubmission(TopicProposal $topic, User $faculty, bool $complete = false): ProposalVersion
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

    if ($complete) {
        foreach (array_diff(ProposalSignatureWorkflow::REQUIRED_DOCUMENT_TYPES, [ProposalVersionFile::TYPE_DETAILED_PROPOSAL]) as $type) {
            $version->files()->create([
                'document_type' => $type, 'position' => 0, 'file_path' => $path,
                'original_filename' => $type.'.pdf', 'mime_type' => 'application/pdf',
            ]);
        }
    }

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

test('the review workspace keeps its floating back link outside the tab panels', function (string $role, string $backRoute) {
    $this->withoutVite();
    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');
    $viewer = $role === 'faculty' ? $faculty : User::factory()->create();
    if ($role !== 'faculty') {
        $viewer->assignRole($role);
    }
    $topic = TopicProposal::create([
        'user_id' => $faculty->id,
        'title' => 'Proposal with review navigation',
        'status' => 'pending',
    ]);

    $response = $this->actingAs($viewer)->get(route('topics.show', $topic))
        ->assertSuccessful()
        ->assertSee('id="proposal-review-tab"', false);
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    $links = $xpath->query('//*[@data-topic-workspace]//a[@data-fixed-back-link]');

    expect($links->length)->toBe(1)
        ->and($links->item(0)->getAttribute('href'))->toBe(route($backRoute))
        ->and($links->item(0)->getAttribute('x-ref'))->toBe('workspaceBackLink')
        ->and($xpath->query('ancestor::*[@role="tabpanel"]', $links->item(0))->length)->toBe(0);
})->with([
    'faculty review' => ['faculty', 'faculty.submissions'],
    'research head review' => ['research_head', 'research_head.proposal-submissions.index'],
]);

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
    expect($notification->data['workspace'])->toBe(User::WORKSPACE_FACULTY);

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
        ->assertJsonCount(0, 'notifications')
        ->assertJsonPath('unread_count', 0);

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
    $returnedFile = $originalVersion->files()->create([
        'document_type' => ProposalVersionFile::TYPE_DETAILED_PROPOSAL, 'position' => 0,
        'file_path' => 'proposals/original.pdf', 'original_filename' => 'original.pdf',
        'mime_type' => 'application/pdf', 'checksum' => hash('sha256', 'original document'),
    ]);
    $review = $topic->reviews()->create(['reviewer_id' => $faculty->id, 'decision' => 'revision_requested']);
    $review->fileRevisions()->create([
        'proposal_version_file_id' => $returnedFile->id, 'document_type' => $returnedFile->document_type,
        'original_filename' => $returnedFile->original_filename,
    ]);

    $response = $this->actingAs($faculty)->patch("/faculty/topics/{$topic->id}/resubmit", [
        'title' => 'Revised proposal',
        'description' => 'Updated methodology',
        'estimated_budget' => 8500,
        'estimated_duration_months' => 10,
        'document' => UploadedFile::fake()->create('revised-proposal.pdf', 100, 'application/pdf'),
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect(route('faculty.dashboard'));

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

test('faculty replies and structured locations are saved and exported in separate comment response fields', function (array $answer, string $expectedRemarks, string $stage) {
    Storage::fake('local');
    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');
    $head = User::factory()->create();
    $head->assignRole('research_head');
    $topic = TopicProposal::create([
        'user_id' => $faculty->id,
        'title' => 'Response field test',
        'estimated_budget' => 10000,
        'status' => 'revision_requested',
    ]);
    createTopicReviewSubmission($topic, $faculty);
    $review = $topic->reviews()->create([
        'reviewer_id' => $head->id,
        'decision' => 'revision_requested',
        'comment' => 'Explain the recruitment schedule.',
        'review_stage' => $stage,
    ]);

    $this->actingAs($faculty)->patch(route('faculty.topics.resubmit', $topic), [
        'title' => $topic->title,
        'estimated_budget' => 10000,
        'estimated_duration_months' => 12,
        'feedback_review_id' => $review->id,
        'feedback_responses' => ['overall' => $answer],
    ])
        ->assertRedirect(route('faculty.dashboard'))
        ->assertSessionHasNoErrors();

    $row = app(CommentResponseFeedback::class)->rows($review->fresh())[0];
    expect($topic->fresh()->status)->toBe('resubmitted')
        ->and($review->fresh()->feedback_responses['overall']['response'])->toBe($answer['response'])
        ->and($row['comment'])->toBe('Explain the recruitment schedule.')
        ->and($row['response'])->toBe($answer['response'])
        ->and($row['remarks'])->toBe($expectedRemarks)
        ->and($row['no_change'])->toBe((bool) $answer['no_change'])
        ->and($row['page'])->toBe($answer['no_change'] ? null : $answer['page'])
        ->and($row['paragraph'])->toBe($answer['no_change'] ? null : $answer['paragraph']);

    $download = $this->get(route('faculty.topics.comment-response-form.download', ['topic' => $topic, 'review' => $review->id]))->assertOk();
    $path = tempnam(sys_get_temp_dir(), 'athena-response-location-');
    file_put_contents($path, $download->streamedContent());
    $archive = new ZipArchive;
    try {
        expect($archive->open($path))->toBeTrue();
        $document = new DOMDocument;
        expect($document->loadXML($archive->getFromName('word/document.xml'), LIBXML_NONET))->toBeTrue();
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $cells = $xpath->query('//w:tbl[w:tr/w:tc//w:t[text()="COMMENTS AND SUGGESTIONS"]]/w:tr[2]/w:tc');
        expect($cells->item(2)->textContent)->toBe($answer['response'])
            ->and($cells->item(3)->textContent)->toBe($expectedRemarks);
    } finally {
        $archive->close();
        unlink($path);
    }
})->with([
    'Research Head revision with a location' => [['response' => 'Added the recruitment schedule.', 'no_change' => 0, 'page' => 4, 'paragraph' => 2, 'remarks' => 'sample sample'], 'Page 4, paragraph 2', 'research_head'],
    'Research Head reply without a change' => [['response' => 'The existing schedule already covers participant recruitment.', 'no_change' => 1, 'page' => 'invalid', 'paragraph' => -1], 'No change made', 'research_head'],
    'LREC revision with a location' => [['response' => 'Added the recruitment schedule.', 'no_change' => 0, 'page' => 4, 'paragraph' => 2], 'Page 4, paragraph 2', 'lrec'],
    'LREC reply without a change' => [['response' => 'The existing schedule already covers participant recruitment.', 'no_change' => 1], 'No change made', 'lrec'],
]);

test('faculty revision rejects missing or invalid comment response locations', function (array $answer, string $field) {
    Storage::fake('local');
    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');
    $head = User::factory()->create();
    $head->assignRole('research_head');
    $topic = TopicProposal::create(['user_id' => $faculty->id, 'title' => 'Response location validation', 'estimated_budget' => 10000, 'status' => 'revision_requested']);
    createTopicReviewSubmission($topic, $faculty);
    $review = $topic->reviews()->create(['reviewer_id' => $head->id, 'decision' => 'revision_requested', 'comment' => 'Clarify recruitment.']);

    $this->actingAs($faculty)->from(route('faculty.topics.revision', $topic))->patch(route('faculty.topics.resubmit', $topic), [
        'title' => $topic->title, 'estimated_budget' => 10000, 'estimated_duration_months' => 12,
        'feedback_review_id' => $review->id,
        'feedback_responses' => ['overall' => $answer],
    ])->assertSessionHasErrorsIn('resubmission', 'feedback_responses.overall.'.$field)
        ->assertSessionHasInput('feedback_responses.overall', $answer);

    expect($topic->fresh()->status)->toBe('revision_requested')
        ->and($topic->versions()->count())->toBe(1)
        ->and($review->fresh()->feedback_responses)->toBeNull();
})->with([
    'Missing page' => [['response' => 'Updated recruitment.', 'no_change' => 0, 'paragraph' => 2], 'page'],
    'Missing paragraph' => [['response' => 'Updated recruitment.', 'no_change' => 0, 'page' => 4], 'paragraph'],
    'Text remarks cannot replace a location' => [['response' => 'Updated recruitment.', 'no_change' => 0, 'remarks' => 'sample sample'], 'page'],
    'Zero page' => [['response' => 'Updated recruitment.', 'no_change' => 0, 'page' => 0, 'paragraph' => 2], 'page'],
    'Negative paragraph' => [['response' => 'Updated recruitment.', 'no_change' => 0, 'page' => 4, 'paragraph' => -2], 'paragraph'],
    'Fractional paragraph' => [['response' => 'Updated recruitment.', 'no_change' => 0, 'page' => 4, 'paragraph' => 2.5], 'paragraph'],
    'Text page' => [['response' => 'Updated recruitment.', 'no_change' => 0, 'page' => 'sample', 'paragraph' => 2], 'page'],
    'No change still needs an explanation' => [['response' => '', 'no_change' => 1], 'response'],
    'Invalid no change flag' => [['response' => 'Updated recruitment.', 'no_change' => 'yes', 'page' => 4, 'paragraph' => 2], 'no_change'],
]);

test('a no change response does not bypass location validation for another comment', function () {
    Storage::fake('local');
    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');
    $head = User::factory()->create();
    $head->assignRole('research_head');
    $topic = TopicProposal::create(['user_id' => $faculty->id, 'title' => 'Independent response locations', 'estimated_budget' => 10000, 'status' => 'revision_requested']);
    $version = createTopicReviewSubmission($topic, $faculty);
    $file = $version->files()->sole();
    $review = $topic->reviews()->create(['reviewer_id' => $head->id, 'decision' => 'revision_requested', 'comment' => 'Confirm the project budget.']);
    $revision = $review->fileRevisions()->create([
        'proposal_version_file_id' => $file->id, 'document_type' => $file->document_type,
        'original_filename' => $file->original_filename, 'revision_note' => 'Clarify recruitment.',
    ]);

    $response = $this->actingAs($faculty)->patch(route('faculty.topics.resubmit', $topic), [
        'title' => $topic->title, 'estimated_budget' => 10000, 'estimated_duration_months' => 12,
        'feedback_review_id' => $review->id,
        'feedback_responses' => [
            'overall' => ['response' => 'The existing budget covers the requirements.', 'no_change' => 1],
            'file_'.$revision->id => ['response' => 'Revised recruitment.', 'no_change' => 0, 'page' => 4],
        ],
    ])->assertSessionHasErrorsIn('resubmission', 'feedback_responses.file_'.$revision->id.'.paragraph');

    expect(array_keys($response->getSession()->get('errors')->getBag('resubmission')->getMessages()))
        ->toBe(['feedback_responses.file_'.$revision->id.'.paragraph'])
        ->and($topic->fresh()->status)->toBe('revision_requested');
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

    $commentsSignatories = [
        'comment_response_head' => ['name' => 'Revision Research Head'],
        'comment_response_vice_chancellor' => ['name' => 'Revision Vice Chancellor'],
    ];
    $draft = $topic->revisionDraft()->create([
        'user_id' => $faculty->id,
        'research_call_id' => $this->researchCall->id,
        'project_title' => $topic->title,
        'duration_months' => 12,
        'project_leader' => $faculty->name,
        'status' => 'draft',
        'signatory_selections' => $commentsSignatories,
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
            'feedback_review_id' => $review->id,
            'feedback_responses' => collect(app(CommentResponseFeedback::class)->rows($review))
                ->mapWithKeys(fn (array $row): array => [$row['key'] => ['response' => 'Addressed in the revised proposal.', 'no_change' => $row['location'] === 'Detailed Research Proposal' ? 1 : 0, 'page' => 4, 'paragraph' => 2]])->all(),
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
    $noChangeRevision = $review->fileRevisions()->where('document_type', ProposalVersionFile::TYPE_DETAILED_PROPOSAL)->sole();
    expect($noChangeRevision->resolution_type)->toBe('no_file_change')
        ->and($noChangeRevision->faculty_response)->toBe('Addressed in the revised proposal.')
        ->and($review->fresh()->feedback_responses['file_'.$noChangeRevision->id]['response'])->toBe($noChangeRevision->faculty_response);
    expect($topic->latestVersion->files->firstWhere('document_type', ProposalVersionFile::TYPE_DETAILED_PROPOSAL)
        ->source_data['comment_response_signatory_selections'])->toBe(array_intersect_key(
            $draft->resolvedSignatorySelections(), ProposalSignatory::FIELDS['comment_response_form'],
        ));
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

test('a research head can confirm completed signing while Notice to Proceed release remains pending', function () {
    Storage::fake('local');
    $head = User::factory()->create();
    $head->assignRole('research_head');

    $faculty = User::factory()->create(['college' => User::COLLEGES['CICS']]);
    $faculty->assignRole('faculty');
    Role::firstOrCreate(['name' => 'research_coordinator']);
    $office = User::factory()->create(['college' => User::COLLEGES['CICS']]);
    $office->assignRole('research_coordinator');
    $this->mock(ProposalFormVerifier::class)->shouldReceive('check')->andReturn([
        'status' => 'matched', 'method' => 'test_fixture', 'message' => 'Form and project title matched', 'reason' => null,
    ]);

    $topic = TopicProposal::create([
        'user_id' => $faculty->id,
        'title' => 'Revised proposal',
        'estimated_budget' => 8500,
        'initial_file_path' => 'proposals/original.pdf',
        'final_file_path' => 'proposals/revisions/revised.pdf',
        'status' => TopicProposal::STATUS_LREC_REVIEW,
        'review_stage' => 'lrec',
    ]);
    $version = createTopicReviewSubmission($topic, $faculty, true);

    $topic->reviews()->create([
        'reviewer_id' => $head->id,
        'decision' => 'revision_requested',
        'comment' => 'Make a small methodology revision.',
    ]);

    $response = $this->actingAs($head)
        ->from(route('research_head.dashboard'))
        ->patch("/research-head/topics/{$topic->id}/status", [
            'status' => TopicProposal::STATUS_READY_FOR_SIGNATURE,
            'lrec_clearance_confirmed' => true,
        ]);

    $response->assertRedirect(route('research_head.dashboard'))->assertSessionHasNoErrors();

    foreach ($version->files()->get() as $signatureFile) {
        $this->actingAs($office)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_OFFICE])
            ->post(route('topics.head-uploads.store', $topic), [
                'source_file_id' => $signatureFile->id,
                'review_file' => UploadedFile::fake()->create('signed-proposal.pdf', 100, 'application/pdf'),
                'purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SIGNED,
            ])->assertSessionHasNoErrors();
    }

    $this->actingAs($head)
        ->patch(route('research_head.topics.finalizeApproval', $topic))
        ->assertSessionHasNoErrors();

    expect($topic->fresh()->status)->toBe(TopicProposal::STATUS_READY_FOR_SIGNATURE)
        ->and(app(ProposalSignatureWorkflow::class)->isComplete($version->fresh()))->toBeTrue()
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
        ->assertSee('Rejected')
        ->assertSee('View Decision History (2)')
        ->assertSee('decisionHistoryOpen: false', false)
        ->assertDontSee('View history')
        ->assertSeeInOrder(['Newest rejection reason.', 'Older revision request.']);

    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);

    expect($xpath->query('//nav//button[@data-decision-history-toggle][@aria-controls="decision-history-list"][@aria-expanded="false"]')->length)->toBe(1)
        ->and($xpath->query('//button[@data-decision-history-toggle][@x-show="activeTopicTab === \'history\'"]')->length)->toBe(1)
        ->and($xpath->query('//button[@data-review-workflow-toggle]')->length)->toBe(0)
        ->and($xpath->query('//*[@data-visible-proposal-workflow][not(@x-show)][not(@x-cloak)]')->length)->toBe(1)
        ->and($xpath->query('//section[@data-decision-history][@data-initially-open="false"][@x-show="decisionHistoryOpen"][@x-cloak]')->length)->toBe(1)
        ->and($xpath->query('//section[@data-decision-history]//button[@aria-controls="decision-history-list"]')->length)->toBe(0)
        ->and($xpath->query('//section[@data-decision-history]//*[@data-decision-history-list]//li')->length)->toBe(2)
        ->and($xpath->query('//*[@id="proposal-review-tab"]//*[@data-decision-history]')->length)->toBe(0)
        ->and($xpath->query('//*[@id="version-history-tab"]//*[@data-decision-history]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-decision-history]//a[contains(@href, "comment-response-form/pdf")][@target="_blank"]')->length)->toBe(0)
        ->and($xpath->query('//*[@data-history-comment-response-preview]//template[@x-if="show"]//*[@data-pdf-annotation-config]')->length)->toBe(1);
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
        'status' => 'for_final_decision', 'review_stage' => 'lrec',
    ]);
    $version = createTopicReviewSubmission($topic, $faculty, true);
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
            'lrec_clearance_confirmed' => true,
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
        ->assertSee('Open revision workspace')
        ->assertDontSee('data-revision-proposal-details-button', false);

    $this->actingAs($faculty)
        ->get(route('faculty.topics.revision', $topic))
        ->assertOk()
        ->assertSee('Submit revision')
        ->assertSee('data-revision-proposal-details-button', false)
        ->assertSee('aria-controls="proposal-details-fields"', false)
        ->assertSee('data-initially-open="true"', false)
        ->assertDontSee('<summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-gray-700', false)
        ->assertDontSee('data-topic-file-dropzone', false)
        ->assertSee('data-confirm-title="Submit this revision to the Research Head?"', false);

    $this->actingAs($head)
        ->get('/research-head/dashboard')
        ->assertOk()
        ->assertSee('data-research-head-overview', false);

    $this->actingAs($head)
        ->get(route('topics.show', $topic))
        ->assertOk()
        ->assertSee('Please tighten the literature review.')
        ->assertSee('Waiting for the faculty revision');
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
        ->assertViewHas('displayProjectCost', 43210.5);
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

    $preview = $this->actingAs($faculty)
        ->get(route('faculty.topics.comment-response-form.preview', $topic))
        ->assertOk()
        ->assertHeader('content-type', 'text/html; charset=UTF-8')
        ->assertHeader('x-content-type-options', 'nosniff')
        ->assertSee('Coastal Habitat Restoration')
        ->assertSee('Dr. Aurora Reyes')
        ->assertSee('MATRIX ON THE ACTIONS MADE FOR THE COMMENTS AND SUGGESTIONS');

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
            ->toContain('Bea Santos')
            ->toContain('Carlos Lim')
            ->toContain('Initial Screening')
            ->toContain('Local Research Evaluation')
            ->toContain('COMMENTS AND SUGGESTIONS')
            ->toContain('ACTION AND RESPONSE')
            ->toContain('REMARKS')
            ->toContain('Research Head/ RDES Head')
            ->toContain('Vice Chancellor for Research, Development and Extension Services');
        expect($footerDom->textContent)
            ->not->toContain('Comment-Response Form | Coastal Habitat Restoration')
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

        $title = $xpath->query('/w:document/w:body/w:p[.//w:t[contains(., "Coastal Habitat Restoration")]]')->item(0);
        expect($title?->textContent)->toBe('TITLE OF RESEARCH PROPOSAL: Coastal Habitat Restoration')
            ->and($xpath->query('.//w:tab | .//w:u', $title)->length)->toBe(0);

        foreach ($xpath->query('/w:document/w:body/w:tbl[2]/w:tr[position() > 1]/w:tc[position() > 1]') as $responseCell) {
            expect(trim($responseCell->textContent))->toBe('');
        }

        for ($index = 0; $index < $template->numFiles; $index++) {
            $entry = $template->statIndex($index);
            $name = $entry['name'];

            if (! in_array($name, ['word/document.xml', 'word/header1.xml', 'word/footer1.xml', 'word/settings.xml'], true)) {
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
    $annotation = $detailedProposal->annotations()->create([
        'reviewer_id' => $head->id,
        'topic_review_file_revision_id' => $fileRevision->id,
        'feedback_source' => ProposalFileAnnotation::SOURCE_HEAD,
        'annotation_type' => ProposalFileAnnotation::TYPE_AREA,
        'page_number' => 2,
        'rectangles' => [['x' => 0.1, 'y' => 0.2, 'width' => 0.3, 'height' => 0.1]],
        'comment' => 'Clarify the participant recruitment timeline.',
    ]);
    $review->update(['feedback_responses' => [
        'annotation_'.$annotation->id => ['response' => 'Added the recruitment schedule.', 'remarks' => 'Page 4, paragraph 2'],
        'co_evaluator_narrative_'.$evaluation->id => ['response' => 'Defined the sampling frame.', 'remarks' => 'Page 5, paragraph 1'],
    ]]);

    $headQuery = ['topic' => $topic, 'source' => CommentResponseFeedback::FORM_RESEARCH_HEAD, 'review' => $review->id];
    $coEvaluatorQuery = ['topic' => $topic, 'source' => CommentResponseFeedback::FORM_CO_EVALUATOR, 'review' => $review->id];

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

    $this->actingAs($faculty)
        ->get(route('faculty.topics.comment-response-form.preview', $headQuery))
        ->assertOk()
        ->assertHeader('content-type', 'text/html; charset=UTF-8')
        ->assertSee('Clarify the participant recruitment timeline.')
        ->assertSee('Added the recruitment schedule.')
        ->assertDontSee('Defined the sampling frame.');
    $this->get(route('faculty.topics.comment-response-form.preview', $coEvaluatorQuery))
        ->assertOk()
        ->assertHeader('content-type', 'text/html; charset=UTF-8')
        ->assertSee('Defined the sampling frame.')
        ->assertDontSee('Clarify the participant recruitment timeline.');
    $headDownload = $this->get(route('faculty.topics.comment-response-form.download', $headQuery))
        ->assertOk()
        ->assertDownload('mangrove-recovery-study-research-head-comment-response-form.docx');
    $coEvaluatorDownload = $this->get(route('faculty.topics.comment-response-form.download', $coEvaluatorQuery))
        ->assertOk()
        ->assertDownload('mangrove-recovery-study-co-evaluator-comment-response-form.docx');

    $documentText = static function ($response): string {
        $temporaryPath = tempnam(sys_get_temp_dir(), 'athena-comment-response-source-test-');
        expect($temporaryPath)->not->toBeFalse();
        file_put_contents($temporaryPath, $response->streamedContent());
        $archive = new ZipArchive;

        try {
            expect($archive->open($temporaryPath))->toBeTrue();
            $documentXml = $archive->getFromName('word/document.xml');
            expect($documentXml)->not->toBeFalse();
            $document = new DOMDocument;
            expect($document->loadXML($documentXml, LIBXML_NONET))->toBeTrue();

            return $document->textContent;
        } finally {
            $archive->close();
            unlink($temporaryPath);
        }
    };

    expect($documentText($headDownload))
        ->toContain('Clarify the participant recruitment timeline.', 'Added the recruitment schedule.', 'Page 4, paragraph 2')
        ->not->toContain('The methodology needs a clearer sampling frame.', 'Detailed Research Proposal', 'Page 2')
        ->and($documentText($coEvaluatorDownload))
        ->toContain('The methodology needs a clearer sampling frame.', 'Defined the sampling frame.', 'Page 5, paragraph 1')
        ->not->toContain('Dr. Maria Santos', 'Initial Screening Form · Narrative Evaluation')
        ->not->toContain('Clarify the participant recruitment timeline.');

    $revisionPage = $this->get(route('faculty.topics.revision', $topic))
        ->assertOk()
        ->assertSee('Research Head Comment Response paper')
        ->assertSee('Co-evaluator Comment Response paper')
        ->assertSee('2. Revise and respond')
        ->assertSee('Your response')
        ->assertSee('The page and paragraph will be added automatically')
        ->assertDontSee('Response details')
        ->assertDontSee('Explanation only')
        ->assertSee('Added the recruitment schedule.')
        ->assertSee('Defined the sampling frame.')
        ->assertDontSee('3. Action and Response')
        ->assertSee('data-comment-response-source="research_head"', false)
        ->assertSee('data-comment-response-source="co_evaluator"', false);

    $revisionDocument = new DOMDocument;
    @$revisionDocument->loadHTML($revisionPage->getContent());
    $revisionXPath = new DOMXPath($revisionDocument);
    foreach ([CommentResponseFeedback::FORM_RESEARCH_HEAD, CommentResponseFeedback::FORM_CO_EVALUATOR] as $source) {
        $expectedLocation = $source === CommentResponseFeedback::FORM_RESEARCH_HEAD
            ? ['page' => '4', 'paragraph' => '2']
            : ['page' => '5', 'paragraph' => '1'];
        $group = '//section[@data-comment-response-source="'.$source.'"]';
        expect($revisionXPath->query($group.'//*[@data-comment-response-paper-open][@tabindex="0"]')->length)->toBe(1)
            ->and($revisionXPath->query($group.'//*[@data-comment-response-preview]')->length)->toBe(1)
            ->and($revisionXPath->query($group.'//button[@data-comment-response-preview]')->length)->toBe(0)
            ->and($revisionXPath->query('//div[@data-revision-response-source="'.$source.'"]//textarea[@required]')->length)->toBeGreaterThan(0);
        $responseGroup = '//div[@data-revision-response-source="'.$source.'"]';
        $responseFields = $revisionXPath->query($responseGroup.'//textarea[contains(@name, "[response]")]');
        foreach (['page', 'paragraph'] as $locationField) {
            $locationFields = $revisionXPath->query($responseGroup.'//input[contains(@name, "['.$locationField.']")]');
            expect($locationFields->length)->toBe($responseFields->length);
            foreach ($locationFields as $field) {
                expect($field->hasAttribute('required'))->toBeFalse()
                    ->and($field->getAttribute('type'))->toBe('hidden')
                    ->and($field->getAttribute('value'))->toBe($expectedLocation[$locationField]);
            }
        }
        expect($revisionXPath->query($responseGroup.'//textarea[contains(@name, "[remarks]")]')->length)->toBe(0)
            ->and($revisionXPath->query($responseGroup.'//input[@data-comment-response-no-change]')->length)->toBe($responseFields->length);
    }

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
        ->assertSee('Awaiting NTP')
        ->assertDontSee('Another faculty research');

    $this->actingAs($faculty)
        ->get("/research/{$ownTopic->id}")
        ->assertOk()
        ->assertSee('Final signing')
        ->assertSee('PHP 14,500.00')
        ->assertSee('Project')
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
        ->assertSee('Resources');

    $this->actingAs($faculty)
        ->get('/research-support')
        ->assertOk()
        ->assertSee('Resources');

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

test('submitted paper history keeps earlier versions available after a revision', function () {
    Storage::fake('local');

    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');
    $outsider = User::factory()->create();
    $outsider->assignRole('faculty');

    $topic = TopicProposal::create([
        'user_id' => $faculty->id,
        'title' => 'Coastal study with revisions',
        'estimated_budget' => 20000,
        'status' => 'resubmitted',
    ]);
    $initialVersion = createTopicReviewSubmission($topic, $faculty);
    $initialFile = $initialVersion->files()->sole();

    $revisedPath = 'proposals/revised-coastal-study.pdf';
    Storage::disk('local')->put($revisedPath, 'revised proposal');
    $revisedVersion = $topic->versions()->create([
        'submitted_by' => $faculty->id,
        'version_number' => 2,
        'submission_type' => 'revision',
        'file_path' => $revisedPath,
        'original_filename' => 'revised-coastal-study.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => strlen('revised proposal'),
        'checksum' => hash('sha256', 'revised proposal'),
        'title' => $topic->title,
        'estimated_budget' => $topic->estimated_budget,
        'estimated_duration_months' => $topic->estimated_duration_months,
    ]);
    $revisedFile = $revisedVersion->files()->create([
        'document_type' => ProposalVersionFile::TYPE_DETAILED_PROPOSAL,
        'position' => 0,
        'file_path' => $revisedPath,
        'original_filename' => 'revised-coastal-study.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => strlen('revised proposal'),
        'checksum' => hash('sha256', 'revised proposal'),
        'is_carried_forward' => false,
    ]);
    $assessmentFile = $revisedVersion->files()->create([
        'document_type' => ProposalVersionFile::TYPE_GAD_CHECKLIST,
        'position' => 1,
        'file_path' => $revisedPath,
        'original_filename' => 'gad-checklist.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => strlen('revised proposal'),
        'is_carried_forward' => true,
    ]);

    $page = $this->actingAs($faculty)->get(route('topics.show', $topic))->assertOk();
    $dom = new DOMDocument;
    @$dom->loadHTML($page->getContent());
    $xpath = new DOMXPath($dom);
    $history = $xpath->query('//*[@id="version-history-tab"]//h3[normalize-space()="Submitted proposal versions"]/ancestor::section[1]//article');

    expect($history->length)->toBe(2)
        ->and($history->item(0)->textContent)->toContain('Version 2', 'revised-coastal-study.pdf', 'gad-checklist.pdf')
        ->and($history->item(1)->textContent)->toContain('Version 1', 'submitted-proposal.pdf');
    expect($xpath->query('./details', $history->item(0))->item(0)->hasAttribute('open'))->toBeFalse()
        ->and($xpath->query('./details', $history->item(1))->item(0)->hasAttribute('open'))->toBeFalse()
        ->and($xpath->query('.//*[@data-version-file-group="Proposal papers"]//li', $history->item(0))->length)->toBe(1)
        ->and($xpath->query('.//*[@data-version-file-group="Assessment forms"]//li', $history->item(0))->length)->toBe(1);

    $this->get(route('topics.versions.files.download', [$topic, $initialVersion, $initialFile]))
        ->assertDownload('submitted-proposal.pdf');
    $this->get(route('topics.versions.files.download', [$topic, $revisedVersion, $revisedFile]))
        ->assertDownload('revised-coastal-study.pdf');
    $this->get(route('topics.versions.files.download', [$topic, $revisedVersion, $assessmentFile]))
        ->assertDownload('gad-checklist.pdf');
    $this->get(route('topics.versions.files.download', [$topic, $revisedVersion, $initialFile]))
        ->assertNotFound();
    $this->actingAs($outsider)
        ->get(route('topics.versions.files.download', [$topic, $initialVersion, $initialFile]))
        ->assertForbidden();
});

test('the proposal workspace is complete role-aware and private', function () {
    $this->withoutVite();
    Storage::fake('local');

    $faculty = User::factory()->create(['college' => User::COLLEGES['CICS']]);
    $faculty->assignRole('faculty');
    Role::firstOrCreate(['name' => 'research_coordinator']);
    $office = User::factory()->create(['college' => User::COLLEGES['CICS']]);
    $office->assignRole('research_coordinator');
    $this->mock(ProposalFormVerifier::class)->shouldReceive('check')->andReturn([
        'status' => 'matched', 'method' => 'test_fixture', 'message' => 'Form and project title matched', 'reason' => null,
    ]);
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
        ->assertSee('Project')
        ->assertSee('Research details')
        ->assertSee('Decision history')
        ->assertDontSee('Research Head documents')
        ->assertSee('Submitted version comparison')
        ->assertSee('Submitted proposal versions')
        ->assertDontSee('Proposal package checklist');

    $this->actingAs($head)
        ->get(route('topics.show', $topic))
        ->assertOk()
        ->assertSee('Project')
        ->assertSee('7/7 files available')
        ->assertDontSee('id="notice-to-proceed-tab-button"', false)
        ->assertSee('Detailed Research Proposal')
        ->assertSee('Initial Screening Form')
        ->assertSee('View')
        ->assertSee('Download')
        ->assertSee('Latest submission')
        ->assertSee('Open the project folder to view them in separate categories alongside signed papers')
        ->assertDontSee('Review latest package')
        ->assertSee('data-latest-review-version="1"', false)
        ->assertSee('Record the Research Head decision')
        ->assertSee('Record the Research Head decision')
        ->assertSee('data-latest-review-version', false)
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
            'feedback_review_id' => $fileRevision->topic_review_id,
            'feedback_responses' => collect(app(CommentResponseFeedback::class)->rows($fileRevision->review))->mapWithKeys(fn (array $row): array => [$row['key'] => ['response' => 'Extended the schedule through the second year.', 'no_change' => 0, 'page' => 4, 'paragraph' => 2]])->all(),
        ])->assertSessionHasNoErrors(null, 'resubmission');

    expect($topic->fresh()->status)->toBe('resubmitted')
        ->and($fileRevision->fresh()->resolved_at)->not->toBeNull()
        ->and($fileRevision->fresh()->resolutionFile?->original_filename)->toBe('work-plan-v2.docx');

    $latestVersion = $topic->latestVersion()->with('files')->firstOrFail();
    $topic->update(['status' => TopicProposal::STATUS_LREC_REVIEW, 'review_stage' => 'lrec']);
    $signatureFileIds = $latestVersion->files
        ->whereIn('document_type', [
            ProposalVersionFile::TYPE_DETAILED_PROPOSAL,
            ProposalVersionFile::TYPE_WORK_PLAN,
            ProposalVersionFile::TYPE_LINE_ITEM_BUDGET,
            ProposalVersionFile::TYPE_GAD_CHECKLIST,
            ProposalVersionFile::TYPE_INITIAL_SCREENING_FORM,
        ])
        ->pluck('id')
        ->all();

    $this->actingAs($head)
        ->patch(route('research_head.topics.updateStatus', $topic), [
            'status' => TopicProposal::STATUS_READY_FOR_SIGNATURE,
            'lrec_clearance_confirmed' => '1',
            'signature_file_ids' => $signatureFileIds,
            'evaluation_document' => UploadedFile::fake()->create('final-evaluation.pdf', 100, 'application/pdf'),
        ])
        ->assertRedirect(route('research_head.dashboard'));

    foreach ([
        ProposalVersionFile::TYPE_DETAILED_PROPOSAL,
        ProposalVersionFile::TYPE_WORK_PLAN,
        ProposalVersionFile::TYPE_LINE_ITEM_BUDGET,
        ProposalVersionFile::TYPE_GAD_CHECKLIST,
        ProposalVersionFile::TYPE_INITIAL_SCREENING_FORM,
    ] as $documentType) {
        $sourceFile = $latestVersion->files->firstWhere('document_type', $documentType);

        $this->actingAs($office)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_OFFICE])
            ->post(route('topics.head-uploads.store', $topic), [
                'source_file_id' => $sourceFile->id,
                'review_file' => UploadedFile::fake()->create("signed-{$documentType}.pdf", 100, 'application/pdf'),
                'purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SIGNED,
            ])
            ->assertRedirect(route('topics.show', $topic).'#notice-to-proceed');
    }

    $this->actingAs($head)
        ->patch(route('research_head.topics.finalizeApproval', $topic))
        ->assertRedirect(route('topics.show', $topic).'#notice-to-proceed');

    expect($topic->fresh()->status)->toBe(TopicProposal::STATUS_READY_FOR_SIGNATURE)
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

test('opening a submitted paper for review automatically adds a quiet cue beside its title', function () {
    Storage::fake('local');

    $head = User::factory()->create();
    $head->assignRole('research_head');
    $otherHead = User::factory()->create();
    $otherHead->assignRole('research_head');
    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');

    $topic = TopicProposal::create([
        'user_id' => $faculty->id,
        'title' => 'Review cue proposal',
        'estimated_budget' => 25000,
        'status' => 'pending',
    ]);
    $version = createTopicReviewSubmission($topic, $faculty);
    $detailedProposal = $version->files()->sole();
    $workPlan = $version->files()->create([
        'document_type' => ProposalVersionFile::TYPE_WORK_PLAN,
        'position' => 0,
        'file_path' => 'proposals/review-cue-work-plan.pdf',
        'original_filename' => 'review-cue-work-plan.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 9,
        'checksum' => hash('sha256', 'work plan'),
        'is_carried_forward' => false,
    ]);
    Storage::disk('local')->put($workPlan->file_path, 'work plan');

    $annotationUrl = route('topics.versions.files.annotations.index', [$topic, $version, $detailedProposal]);

    $this->actingAs($head)
        ->get(route('topics.show', $topic).'?decision=revision_requested')
        ->assertOk()
        ->assertDontSee('data-review-progress', false)
        ->assertSee('openedForReview: false', false)
        ->assertSee($annotationUrl.'?decision=revision_requested', false)
        ->assertSee('@click="openedForReview = true"', false)
        ->assertSee('x-show="openedForReview"', false)
        ->assertDontSee('data-paper-review-toggle', false)
        ->assertSee('data-paper-reviewed-cue', false);

    expect(ProposalFileReviewCheck::query()->count())->toBe(0);

    $this->get($annotationUrl.'?decision=revision_requested')->assertOk();

    $this->assertDatabaseHas('proposal_file_review_checks', [
        'proposal_version_file_id' => $detailedProposal->id,
        'reviewer_id' => $head->id,
    ]);

    $this->get(route('topics.show', $topic))
        ->assertOk()
        ->assertSee('openedForReview: true', false)
        ->assertSee('openedForReview: false', false)
        ->assertSee('data-paper-reviewed-cue', false)
        ->assertSee('Opened for review');

    $this->get($annotationUrl)->assertOk();

    expect(ProposalFileReviewCheck::query()->count())->toBe(1);

    $this->actingAs($otherHead)
        ->get(route('topics.show', $topic))
        ->assertOk()
        ->assertSee('openedForReview: false', false)
        ->assertSee('x-show="openedForReview"', false);

    $this->actingAs($faculty)
        ->get($annotationUrl)
        ->assertNotFound();

    expect(ProposalFileReviewCheck::query()->count())->toBe(1)
        ->and($topic->fresh()->status)->toBe('pending')
        ->and(ProposalFileAnnotation::query()->count())->toBe(0);
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
        ->assertSee('Latest submission')
        ->assertDontSee('Review latest package')
        ->assertSee('data-latest-review-version', false);

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
        ->assertSee('data-latest-review-version', false)
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
        ->assertRedirect(route('topics.show', $topic).'#proposal-review')
        ->assertSessionHas('topic_tab', 'review')
        ->assertSessionHas('success', 'Revision request sent to the faculty member.');

    expect($topic->fresh()->status)->toBe('revision_requested')
        ->and($topic->reviews()->latest()->firstOrFail()->fileRevisions()->sole()->proposal_version_file_id)
        ->toBe($latestFile->id);

    $this->get(route('topics.show', $topic))
        ->assertOk()
        ->assertSee('data-topic-success', false)
        ->assertSee('bg-emerald-50', false)
        ->assertSee('Revision request sent')
        ->assertSee('Latest submission')
        ->assertSee('data-read-only-review="true"', false)
        ->assertSee('data-file-review-card="'.$latestFile->id.'"', false)
        ->assertSee('data-review-and-highlight', false)
        ->assertSee('title="Revision request already sent"', false)
        ->assertSee('1 comment')
        ->assertDontSee('data-research-head-file-workspace', false);

    $this->get(route('topics.head-uploads.index', $topic))
        ->assertOk()
        ->assertSee('The revision request has been sent. Submitted papers and comments remain available for reference.')
        ->assertDontSee('Review the submitted papers and save comments where changes are needed.');
});

test('comments form reviewer names use configured officers across legacy selections and directory roles', function (string $scenario, array $expectedNames) {
    foreach (['faculty', 'research_head'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }
    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');
    $head = User::factory()->create();
    $head->assignRole('research_head');
    $topic = TopicProposal::create(['user_id' => $faculty->id, 'title' => 'Comment Signatures', 'estimated_duration_months' => 12, 'status' => 'revision_requested']);
    $version = createTopicReviewSubmission($topic, $faculty);
    $review = $topic->reviews()->create(['reviewer_id' => $head->id, 'decision' => 'revision_requested', 'comment' => 'Clarify the scope.']);
    $keys = ['comment_response_head', 'comment_response_vice_chancellor'];
    $expectedNames = array_map(fn (string $key): string => ProposalSignatory::defaultSelections()[$key]['name'], $keys);
    $selections = [];
    foreach ($keys as $index => $key) {
        ProposalSignatory::create(['role_key' => $key, 'name' => 'Directory Signer '.($index + 1), 'position' => 'Role', 'active' => $scenario !== 'inactive']);
        $selections[$key] = ['id' => $index + 1, 'name' => 'Selected Signer '.($index + 1), 'position' => 'Role'];
        if ($scenario === 'ambiguous') {
            ProposalSignatory::create(['role_key' => $key, 'name' => 'Other Signer '.($index + 1), 'position' => 'Role', 'active' => true]);
        }
    }
    if ($scenario === 'submitted') {
        $version->files()->first()->update(['source_data' => ['comment_response_signatory_selections' => $selections]]);
    }
    if ($scenario === 'revision') {
        ProposalDraft::create(['user_id' => $faculty->id, 'topic_id' => $topic->id, 'project_title' => $topic->title, 'signatory_selections' => $selections]);
    }
    $download = $this->actingAs($faculty)->get(route('faculty.topics.comment-response-form.download', ['topic' => $topic, 'review' => $review->id]))->assertOk();
    $path = tempnam(sys_get_temp_dir(), 'comments-signatures-');
    file_put_contents($path, $download->streamedContent());
    $archive = new ZipArchive;
    try {
        expect($archive->open($path))->toBeTrue();
        $document = new DOMDocument;
        $document->loadXML($archive->getFromName('word/document.xml'), LIBXML_NONET);
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $names = $xpath->query('/w:document/w:body/w:tbl[.//w:t[contains(., "Research Head/ RDES Head")]]/w:tr[1]/w:tc/w:p[1]');
        expect([$names->item(0)->textContent, $names->item(1)->textContent])->toBe($expectedNames);
        foreach ($names as $paragraph) {
            expect($xpath->query('./w:r/w:rPr/w:b', $paragraph)->length)->toBe(1);
        }
    } finally {
        $archive->close();
        unlink($path);
    }
})->with([
    'Existing proposal with directory roles' => ['directory', ['Directory Signer 1', 'Directory Signer 2']],
    'Submitted names stay frozen' => ['submitted', ['Selected Signer 1', 'Selected Signer 2']],
    'Revision draft selections reflect in preview' => ['revision', ['Selected Signer 1', 'Selected Signer 2']],
    'Multiple directory entries require a selection' => ['ambiguous', ['', '']],
    'Inactive signatories are excluded' => ['inactive', ['', '']],
]);
