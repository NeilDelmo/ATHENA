<?php

use App\Contracts\DocumentPdfConverter;
use App\Models\ProposalFileAnnotation;
use App\Models\ProposalVersionFile;
use App\Models\TopicProposal;
use App\Models\User;
use App\Services\CommentResponseFeedback;
use App\Services\CommentResponseFormDocumentService;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
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

test('Comment Response PDF caching preserves binary bytes in the database cache', function () {
    config(['cache.default' => 'database']);
    Role::firstOrCreate(['name' => 'faculty']);
    $this->faculty->assignRole('faculty');
    $this->topic->update(['status' => 'revision_requested']);
    $review = $this->topic->reviews()->create([
        'reviewer_id' => $this->head->id, 'decision' => 'revision_requested', 'comment' => 'Clarify the methods.',
    ]);
    $binaryPdf = "%PDF-1.7\n\x00\x9c\xed\xff\x80";
    $this->mock(CommentResponseFormDocumentService::class)->shouldReceive('generate')->once()->andReturn('document');
    $this->mock(DocumentPdfConverter::class)->shouldReceive('convertDocx')->once()->with('document')->andReturn($binaryPdf);
    $url = route('faculty.topics.comment-response-form.pdf', ['topic' => $this->topic, 'review' => $review]);

    $this->actingAs($this->faculty)->get($url)->assertSuccessful()->assertContent($binaryPdf);
    $this->get($url)->assertSuccessful()->assertContent($binaryPdf);
    $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
});

test('Comment Response previews show current feedback without document generation or PDF conversion', function () {
    Role::firstOrCreate(['name' => 'faculty']);
    $this->faculty->assignRole('faculty');
    $this->topic->update(['status' => 'revision_requested']);
    $review = $this->topic->reviews()->create([
        'reviewer_id' => $this->head->id, 'decision' => 'revision_requested', 'comment' => 'Explain <script>the methods</script>.',
    ]);
    $this->mock(CommentResponseFormDocumentService::class)->shouldNotReceive('generate');
    $this->mock(DocumentPdfConverter::class)->shouldNotReceive('convertDocx', 'convertXlsx');
    $query = ['topic' => $this->topic, 'review' => $review, 'embedded' => 1];

    $this->actingAs($this->faculty)->get(route('faculty.topics.comment-response-form.preview', $query))
        ->assertSuccessful()->assertHeader('content-type', 'text/html; charset=UTF-8')
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertSee('Original title')->assertSee('paper-preview-embedded')
        ->assertSee('Explain <script>the methods</script>.')->assertDontSee('<script>the methods</script>', false);

    $review->update(['comment' => 'Clarify the sample size.', 'feedback_responses' => [
        'overall' => ['response' => 'Added the sampling calculation.', 'remarks' => 'Page 2, paragraph 1'],
    ]]);
    $this->get(route('faculty.topics.comment-response-form.preview', $query))->assertSuccessful()
        ->assertSee('Clarify the sample size.')->assertSee('Added the sampling calculation.')
        ->assertSee('Page 2, paragraph 1')->assertDontSee('the methods');
    $this->get(route('faculty.topics.comment-response-form.preview', [...$query, 'source' => 'co_evaluator']))->assertSuccessful()
        ->assertDontSee('Clarify the sample size.')->assertDontSee('Added the sampling calculation.');
    $this->get(route('faculty.topics.comment-response-form.preview', [...$query, 'source' => 'unknown']))->assertNotFound();
    $this->get(route('faculty.topics.comment-response-form.preview', [...$query, 'review' => $review->id + 1000]))->assertNotFound();
    $this->actingAs(User::factory()->create())->get(route('faculty.topics.comment-response-form.preview', $query))->assertForbidden();
});

test('unchanged Comment Response PDFs reuse conversion while changed feedback and forms stay current', function () {
    Role::firstOrCreate(['name' => 'faculty']);
    $this->faculty->assignRole('faculty');
    Cache::store('array')->flush();
    config(['cache.default' => 'array']);
    $this->topic->update(['status' => 'revision_requested']);
    $review = $this->topic->reviews()->create([
        'reviewer_id' => $this->head->id, 'decision' => 'revision_requested', 'comment' => 'Clarify the methods.',
    ]);
    $this->mock(CommentResponseFormDocumentService::class)->shouldReceive('generate')->times(7)
        ->andReturnUsing(fn (array $data): string => json_encode($data, JSON_THROW_ON_ERROR));
    $this->mock(DocumentPdfConverter::class)->shouldReceive('convertDocx')->times(7)
        ->andReturnUsing(fn (string $contents): string => '%PDF-'.hash('sha256', $contents));
    $url = route('faculty.topics.comment-response-form.pdf', ['topic' => $this->topic, 'review' => $review]);
    $this->actingAs($this->faculty);
    $first = $this->get($url)->assertOk()->assertHeader('Cache-Control', 'no-store, private')->getContent();
    expect($this->get($url)->assertOk()->getContent())->toBe($first);

    $review->update(['comment' => 'Clarify the sample size.']);
    $updated = $this->get($url)->assertOk()->getContent();
    expect($updated)->not->toBe($first);
    $review->update(['feedback_responses' => ['overall' => ['response' => 'Updated the sample size.', 'remarks' => 'Page 2, paragraph 1']]]);
    $responded = $this->get($url)->assertOk()->getContent();
    expect($responded)->not->toBe($updated);
    expect($this->get($url)->assertOk()->getContent())->toBe($responded);
    $this->get($url.'&source=co_evaluator')->assertOk();

    config(['work_plan.verifier.name' => 'Updated Research Head']);
    expect($this->get($url)->assertOk()->getContent())->not->toBe($responded);
    $template = tempnam(sys_get_temp_dir(), 'athena-preview-template-');
    try {
        file_put_contents($template, 'Updated official template');
        config(['comment_response_form.template_path' => $template]);
        $this->get($url)->assertOk();
        $this->get($url)->assertOk();
        $this->travel(61)->minutes();
        $this->get($url)->assertOk();
    } finally {
        unlink($template);
    }
    $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
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
    @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
    $xpath = new DOMXPath($document);
    $expected = [count(array_diff($stages, ['lrec'])) > 0, in_array('lrec', $stages, true)];
    expect($xpath->query('//*[@data-evaluation-level]')->length)->toBe(2)
        ->and($xpath->query('//*[@data-stage-active="true"]')->length)->toBe(count(array_filter($expected)));
    foreach ($expected as $index => $active) {
        $item = $xpath->query('//*[@data-evaluation-level="'.$index.'"]')->item(0);
        expect($item->getAttribute('data-stage-active'))->toBe($active ? 'true' : 'false')
            ->and($xpath->query('./span[@aria-hidden="true"]', $item)->item(0)->getAttribute('class'))->toContain($active ? 'bg-black' : 'bg-white')
            ->and($item->textContent)->toContain($active ? ' — Selected' : ' — Not selected');
    }
})->with([
    'Research Head' => [['research_head']], 'GAD' => [['gad']], 'Co-Evaluator' => [['co_evaluator']], 'LREC' => [['lrec']], 'Mixed origins' => [['research_head', 'lrec']],
]);

test('current co evaluator comments appear in form previews before a revision decision', function (string $source) {
    $this->topic->update(['status' => TopicProposal::STATUS_GAD_REVIEW, 'review_stage' => 'gad']);
    $gad = ($this->makeFile)(ProposalVersionFile::TYPE_GAD_CHECKLIST);
    ($this->makeFile)(ProposalVersionFile::TYPE_HEAD_UPLOAD, [
        'source_version_file_id' => $gad->id,
        'source_data' => ['purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_GAD_ASSESSMENT, 'target_document_type' => ProposalVersionFile::TYPE_GAD_CHECKLIST, 'gad_signature_confirmed' => true, 'gad_outcome' => 'passed'],
    ]);
    $screening = ($this->makeFile)(ProposalVersionFile::TYPE_INITIAL_SCREENING_FORM);
    $evaluationData = ['purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_EVALUATION, 'target_document_type' => ProposalVersionFile::TYPE_INITIAL_SCREENING_FORM];
    ($this->makeFile)(ProposalVersionFile::TYPE_HEAD_UPLOAD, [
        'source_version_file_id' => $screening->id, 'superseded_at' => now(),
        'source_data' => [...$evaluationData, 'narrative_evaluation' => 'Superseded narrative'],
    ]);
    $evaluation = ($this->makeFile)(ProposalVersionFile::TYPE_HEAD_UPLOAD, [
        'source_version_file_id' => $screening->id,
        'source_data' => [...$evaluationData, 'co_evaluator_name' => 'Dr. Santos', 'narrative_evaluation' => "Clarify recruitment.\nExplain the sample size."],
    ]);
    ($this->makeAnnotation)('Research Head scope comment');
    $rows = $source === CommentResponseFeedback::FORM_CO_EVALUATOR
        ? $this->feedback->draftCoEvaluatorRows($this->version->fresh())
        : $this->feedback->draftRows($this->version->fresh());
    expect(array_column($rows, 'comment'))->toBe($source === CommentResponseFeedback::FORM_CO_EVALUATOR
        ? ["Clarify recruitment.\nExplain the sample size."]
        : ['Research Head scope comment', "Clarify recruitment.\nExplain the sample size."]);
    $last = $rows[array_key_last($rows)];
    expect($last['key'])->toBe('co_evaluator_narrative_'.$evaluation->id)
        ->and($last['reviewer'])->toBe('Co-evaluator')
        ->and($last['response'])->toBe('')->and($last['remarks'])->toBe('');
    $this->mock(CommentResponseFormDocumentService::class)->shouldReceive('generate')->once()
        ->withArgs(fn (array $data): bool => $data['feedback'] === $rows && in_array('co_evaluator', $data['evaluation_stages'], true))
        ->andReturn('docx');
    $this->mock(DocumentPdfConverter::class)->shouldReceive('convertDocx')->with('docx')->andReturn('%PDF-1.7 co evaluator preview');
    $query = ['topic' => $this->topic, 'draft_version' => $this->version->id, 'source' => $source];
    $this->actingAs($this->head)->get(route('research_head.topics.comment-response-form.pdf', $query))
        ->assertSuccessful()->assertHeader('Cache-Control', 'no-store, private')->assertContent('%PDF-1.7 co evaluator preview');
    $this->actingAs($this->faculty)->get(route('faculty.topics.comment-response-form.pdf', $query))->assertForbidden();
    $this->actingAs($this->head)->get(route('research_head.topics.comment-response-form.pdf', [...$query, 'draft_version' => $this->version->id + 1000]))->assertNotFound();
    expect($this->topic->reviews()->count())->toBe(0);
})->with([CommentResponseFeedback::FORM_RESEARCH_HEAD, CommentResponseFeedback::FORM_CO_EVALUATOR]);

test('existing no change replies have a completed Remarks column', function () {
    $review = $this->topic->reviews()->create([
        'reviewer_id' => $this->head->id, 'decision' => 'revision_requested', 'review_stage' => 'research_head',
        'comment' => 'Explain recruitment.',
        'feedback_responses' => ['overall' => ['response' => 'The existing recruitment section covers this.', 'no_change' => true, 'remarks' => '']],
    ]);

    $row = $this->feedback->rowsForSource($review, CommentResponseFeedback::FORM_RESEARCH_HEAD)[0];
    expect($row['remarks'])->toBe('No change made')->and($row['response'])->toBe('The existing recruitment section covers this.');
});
