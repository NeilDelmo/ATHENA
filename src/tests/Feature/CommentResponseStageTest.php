<?php

use App\Contracts\DocumentPdfConverter;
use App\Models\ProposalFileAnnotation;
use App\Models\ProposalVersionFile;
use App\Models\TopicProposal;
use App\Models\User;
use App\Services\CommentResponseFeedback;
use App\Services\CommentResponseFormDocumentService;
use Illuminate\Support\Facades\Blade;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();
    Role::firstOrCreate(['name' => 'research_head']);
    $this->head = User::factory()->create();
    $this->head->assignRole('research_head');
    $this->faculty = User::factory()->create();
    $this->topic = TopicProposal::create(['user_id' => $this->faculty->id, 'title' => 'Original title', 'status' => 'pending']);
    $this->version = $this->topic->versions()->create([
        'submitted_by' => $this->faculty->id, 'version_number' => 1, 'submission_type' => 'initial', 'title' => 'Original title',
        'file_path' => 'stage-test.pdf', 'original_filename' => 'stage-test.pdf', 'mime_type' => 'application/pdf',
        'file_size' => 100, 'checksum' => str_repeat('a', 64),
    ]);
    $this->makeFile = fn (string $type, array $attributes = []) => $this->version->files()->create([
        'document_type' => $type, 'position' => (int) $this->version->files()->where('document_type', $type)->max('position') + 1, 'file_path' => 'stage-test.pdf', 'original_filename' => 'stage-test.pdf',
        'mime_type' => 'application/pdf', 'file_size' => 100, 'checksum' => str_repeat('b', 64), ...$attributes,
    ]);
    $this->file = ($this->makeFile)(ProposalVersionFile::TYPE_WORK_PLAN);
    $this->makeAnnotation = fn (string $comment) => $this->file->annotations()->create([
        'reviewer_id' => $this->head->id, 'annotation_type' => ProposalFileAnnotation::TYPE_AREA, 'page_number' => 1,
        'rectangles' => [['x' => .1, 'y' => .2, 'width' => .3, 'height' => .1]], 'comment' => $comment,
    ]);
    $this->feedback = app(CommentResponseFeedback::class);
});

afterEach(function () {
    $this->travelBack();
});

test('comment stages follow their creation history through Research Head GAD co evaluator and LREC', function () {
    $this->travel(1)->minutes();
    ($this->makeAnnotation)('Research Head scope comment');
    $gad = ($this->makeFile)(ProposalVersionFile::TYPE_GAD_CHECKLIST);
    $this->travel(1)->hours();
    $this->topic->update(['status' => TopicProposal::STATUS_GAD_REVIEW, 'review_stage' => 'gad']);
    ($this->makeAnnotation)('GAD disaggregation comment');
    $this->travel(1)->hours();
    ($this->makeFile)(ProposalVersionFile::TYPE_HEAD_UPLOAD, [
        'source_version_file_id' => $gad->id,
        'source_data' => ['purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_GAD_ASSESSMENT, 'target_document_type' => ProposalVersionFile::TYPE_GAD_CHECKLIST, 'gad_signature_confirmed' => true, 'gad_outcome' => 'passed'],
    ]);
    ($this->makeAnnotation)('Co-Evaluator stage methodology comment');
    $initialScreening = ($this->makeFile)(ProposalVersionFile::TYPE_INITIAL_SCREENING_FORM);
    ($this->makeFile)(ProposalVersionFile::TYPE_HEAD_UPLOAD, [
        'source_version_file_id' => $initialScreening->id,
        'source_data' => ['purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_EVALUATION, 'target_document_type' => ProposalVersionFile::TYPE_INITIAL_SCREENING_FORM, 'co_evaluator_name' => 'Dr. Santos', 'narrative_evaluation' => 'Earlier co-evaluator narrative'],
    ]);
    $this->travel(1)->hours();
    $this->topic->update(['status' => TopicProposal::STATUS_LREC_REVIEW, 'review_stage' => 'lrec']);
    ($this->makeAnnotation)('LREC presentation comment');

    $draft = $this->feedback->draftRows($this->version->fresh());
    expect(array_column($draft, 'stage'))->toBe(['research_head', 'gad', 'co_evaluator', 'lrec']);

    $review = $this->topic->reviews()->create([
        'reviewer_id' => $this->head->id, 'decision' => 'revision_requested', 'review_stage' => 'lrec',
        'committee_comments' => [['reviewer' => 'Committee member', 'comment' => 'LREC committee instruction']],
    ]);
    $revision = $review->fileRevisions()->create(['proposal_version_file_id' => $this->file->id, 'document_type' => $this->file->document_type, 'original_filename' => $this->file->original_filename]);
    $this->file->annotations()->update(['topic_review_file_revision_id' => $revision->id]);
    $rows = $this->feedback->rows($review);
    expect($rows)->toHaveCount(5)
        ->and($this->feedback->stagesForRows($rows))->toBe(['research_head', 'gad', 'co_evaluator', 'lrec'])
        ->and($this->feedback->rowsForSource($review, CommentResponseFeedback::FORM_CO_EVALUATOR))->toBe([])
        ->and($rows[0]['stage'])->toBe('lrec')
        ->and($this->version->files()->count())->toBe(5)
        ->and($this->file->annotations()->count())->toBe(4);
});

test('a historical GAD form uses the reviewed version and assessment at the time of the review', function () {
    $gad = ($this->makeFile)(ProposalVersionFile::TYPE_GAD_CHECKLIST);
    ($this->makeFile)(ProposalVersionFile::TYPE_DETAILED_PROPOSAL, ['source_data' => ['project_leader' => 'Original project leader']]);
    $this->travel(1)->hours();
    $this->topic->update(['status' => TopicProposal::STATUS_GAD_REVIEW, 'review_stage' => 'gad']);
    $review = $this->topic->reviews()->create(['reviewer_id' => $this->head->id, 'decision' => 'revision_requested', 'review_stage' => 'gad']);
    $review->fileRevisions()->create(['proposal_version_file_id' => $gad->id, 'document_type' => $gad->document_type, 'original_filename' => $gad->original_filename, 'revision_note' => 'Disaggregate participants by sex.']);
    $this->travel(1)->hours();
    ($this->makeFile)(ProposalVersionFile::TYPE_HEAD_UPLOAD, [
        'source_version_file_id' => $gad->id,
        'source_data' => ['purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_GAD_ASSESSMENT, 'target_document_type' => ProposalVersionFile::TYPE_GAD_CHECKLIST, 'gad_signature_confirmed' => true, 'gad_outcome' => 'passed'],
    ]);
    $screening = ($this->makeFile)(ProposalVersionFile::TYPE_INITIAL_SCREENING_FORM);
    ($this->makeFile)(ProposalVersionFile::TYPE_HEAD_UPLOAD, [
        'source_version_file_id' => $screening->id,
        'source_data' => ['purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_EVALUATION, 'target_document_type' => ProposalVersionFile::TYPE_INITIAL_SCREENING_FORM, 'narrative_evaluation' => 'Later narrative must not leak into the GAD form.'],
    ]);
    $attributes = $this->version->only($this->version->getFillable());
    $this->topic->versions()->create([...$attributes, 'version_number' => 2, 'title' => 'New version title']);
    $this->topic->update(['title' => 'New version title', 'status' => TopicProposal::STATUS_LREC_REVIEW, 'review_stage' => 'lrec']);

    expect($this->feedback->reviewStage($review))->toBe('gad')
        ->and($this->feedback->rowsForSource($review, CommentResponseFeedback::FORM_CO_EVALUATOR))->toBe([]);
    $this->mock(CommentResponseFormDocumentService::class)->shouldReceive('generate')->once()->withArgs(fn (array $data): bool => $data['project_title'] === 'Original title' && $data['project_leader'] === 'Original project leader'
        && $data['evaluation_stages'] === ['gad'] && $data['feedback'][0]['stage'] === 'gad')->andReturn('docx');
    $this->mock(DocumentPdfConverter::class)->shouldReceive('convertDocx')->with('docx')->andReturn('%PDF-1.7 stage test');
    $this->actingAs($this->head)->get(route('research_head.topics.comment-response-form.pdf', ['topic' => $this->topic, 'review' => $review->id]))->assertOk();
});

test('co evaluator feedback keeps its source and submitted Faculty response after the topic reaches LREC', function () {
    $screening = ($this->makeFile)(ProposalVersionFile::TYPE_INITIAL_SCREENING_FORM);
    $evaluation = ($this->makeFile)(ProposalVersionFile::TYPE_HEAD_UPLOAD, [
        'source_version_file_id' => $screening->id,
        'source_data' => ['purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_EVALUATION, 'target_document_type' => ProposalVersionFile::TYPE_INITIAL_SCREENING_FORM, 'co_evaluator_name' => 'Dr. Santos', 'narrative_evaluation' => 'Clarify recruitment.'],
    ]);
    $review = $this->topic->reviews()->create([
        'reviewer_id' => $this->head->id, 'decision' => 'revision_requested', 'review_stage' => 'gad',
        'feedback_responses' => ['co_evaluator_narrative_'.$evaluation->id => ['response' => 'Revised recruitment.', 'remarks' => 'Page 3']],
    ]);
    $review->fileRevisions()->create(['proposal_version_file_id' => $this->file->id, 'document_type' => $this->file->document_type, 'original_filename' => $this->file->original_filename]);
    $this->travel(1)->hours();
    $this->topic->update(['status' => TopicProposal::STATUS_LREC_REVIEW, 'review_stage' => 'lrec']);
    $row = $this->feedback->rowsForSource($review, CommentResponseFeedback::FORM_CO_EVALUATOR)[0];
    expect($row['stage'])->toBe('co_evaluator')->and($row['response'])->toBe('Revised recruitment.')->and($row['remarks'])->toBe('Page 3');
});

test('a review without file revisions uses the version that existed when it was recorded', function () {
    $screening = ($this->makeFile)(ProposalVersionFile::TYPE_INITIAL_SCREENING_FORM);
    ($this->makeFile)(ProposalVersionFile::TYPE_HEAD_UPLOAD, [
        'source_version_file_id' => $screening->id,
        'source_data' => ['purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_EVALUATION, 'target_document_type' => ProposalVersionFile::TYPE_INITIAL_SCREENING_FORM, 'narrative_evaluation' => 'Original narrative'],
    ]);
    $review = $this->topic->reviews()->create(['reviewer_id' => $this->head->id, 'decision' => 'revision_requested', 'review_stage' => 'gad']);
    $this->travel(1)->hours();
    $newVersion = $this->topic->versions()->create([...$this->version->only($this->version->getFillable()), 'version_number' => 2]);
    $newVersion->files()->create($this->file->only(['document_type', 'position', 'file_path', 'original_filename', 'mime_type', 'file_size', 'checksum']));

    expect($this->feedback->reviewedVersion($review)?->id)->toBe($this->version->id)
        ->and($this->feedback->rowsForSource($review, CommentResponseFeedback::FORM_CO_EVALUATOR)[0]['comment'])->toBe('Original narrative');
});

test('the form indicator visibly identifies included stages without relying only on color', function (array $stages) {
    $html = Blade::render('<x-comment-response-stages :stages="$stages" />', ['stages' => $stages]);
    $document = new DOMDocument;
    @$document->loadHTML($html);
    $xpath = new DOMXPath($document);
    expect($xpath->query('//*[@data-feedback-stage]')->length)->toBe(4)
        ->and($xpath->query('//*[@data-stage-active="true"]')->length)->toBe(count($stages));
    foreach ($stages as $stage) {
        $item = $xpath->query('//*[@data-feedback-stage="'.$stage.'"]')->item(0);
        expect($item->getAttribute('data-stage-active'))->toBe('true')
            ->and($item->getAttribute('class'))->toContain('bg-red-100', 'border-red-700')
            ->and($item->textContent)->toContain('Feedback included');
    }
})->with([
    'Research Head' => [['research_head']], 'GAD' => [['gad']], 'Co-Evaluator' => [['co_evaluator']], 'LREC' => [['lrec']], 'Mixed origins' => [['research_head', 'lrec']],
]);
