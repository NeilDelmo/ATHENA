<?php

use App\Livewire\ResearchHeadDashboard;
use App\Models\ProposalDraft;
use App\Models\ProposalFileAnnotation;
use App\Models\ProposalVersionFile;
use App\Models\TopicProposal;
use App\Models\User;
use App\Services\CommentResponseFeedback;
use App\Services\ProjectDocumentLibrary;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

test('the proposal hub defers its closed review package while the review page remains ready', function () {
    $browserFixture = getenv('ATHENA_REVIEW_BROWSER_FIXTURE');
    if (! $browserFixture) {
        $this->withoutVite();
    }
    Role::firstOrCreate(['name' => 'faculty']);
    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');
    $draft = ProposalDraft::create(['user_id' => $faculty->id, 'project_title' => 'Large saved proposal']);
    $draft->documents()->create([
        'document_type' => ProposalVersionFile::TYPE_WORK_PLAN,
        'source_data' => ['entries' => array_fill(0, 100, [
            'objective' => str_repeat('Saved work plan narrative. ', 100),
            'expected_output' => 'Field report',
            'activity' => 'Conduct the survey',
            'months' => [1, 2, 3],
        ])],
    ]);
    $this->actingAs($faculty);

    DB::enableQueryLog();
    DB::flushQueryLog();
    $started = hrtime(true);
    $response = $this->get(route('faculty.proposal-drafts.show', $draft));
    $queries = collect(DB::getQueryLog());
    DB::disableQueryLog();
    if (getenv('ATHENA_OTHER_PAGE_BENCHMARK') === '1') {
        fwrite(STDERR, sprintf("Proposal hub: %.1f ms, %d queries, %d bytes\n", (hrtime(true) - $started) / 1_000_000, $queries->count(), strlen($response->getContent())));
    }

    $response->assertOk()->assertSee('Large saved proposal');
    expect(str_contains($response->getContent(), 'review-papers-heading'))->toBeFalse();
    expect($queries->filter(fn (array $query): bool => str_starts_with($query['query'], 'select * from `proposal_draft_documents`')))->toHaveCount(1);

    $dom = new DOMDocument;
    @$dom->loadHTML($response->getContent());
    $placeholder = null;
    foreach ($dom->getElementsByTagName('div') as $element) {
        if ($element->hasAttribute('wire:snapshot') && $element->hasAttribute('x-intersect')) {
            $placeholder = $element;
            break;
        }
    }
    expect($placeholder)->not->toBeNull();
    preg_match("/__lazyLoad\('([^']+)'\)/", $placeholder->getAttribute('x-intersect'), $mountParams);
    $loadedReview = $this->postJson(Livewire::getUpdateUri(), ['components' => [[
        'snapshot' => $placeholder->getAttribute('wire:snapshot'),
        'updates' => [],
        'calls' => [['method' => '__lazyLoad', 'params' => [$mountParams[1]]]],
    ]]], ['X-Livewire' => 'true'])->assertOk();
    expect(str_contains($loadedReview->json('components.0.effects.html'), 'review-papers-heading'))->toBeTrue();

    if ($browserFixture) {
        file_put_contents($browserFixture, json_encode(['html' => $response->getContent(), 'loaded' => $loadedReview->json()], JSON_THROW_ON_ERROR));
    }
    $this->get(route('faculty.proposal-drafts.review', $draft))->assertOk()->assertSee('review-papers-heading', false)->assertSee('Conduct the survey');
});

test('research head status lists avoid loading faculty paper contents and preserve GAD outcomes', function () {
    Role::firstOrCreate(['name' => 'research_head']);
    $head = User::factory()->create();
    $head->assignRole('research_head');
    $faculty = User::factory()->create();
    foreach (['passed' => true, 'conditional_pass' => true, 'unsigned' => false] as $outcome => $signed) {
        $topic = TopicProposal::create([
            'user_id' => $faculty->id, 'title' => 'GAD outcome '.$outcome,
            'status' => TopicProposal::STATUS_GAD_REVIEW, 'review_stage' => 'gad',
        ]);
        $version = $topic->versions()->create([
            'submitted_by' => $faculty->id, 'version_number' => 1, 'submission_type' => 'initial',
            'title' => $topic->title, 'file_path' => 'proposal.pdf',
            'original_filename' => 'proposal.pdf', 'mime_type' => 'application/pdf', 'file_size' => 100,
        ]);
        $gad = $version->files()->create([
            'document_type' => ProposalVersionFile::TYPE_GAD_CHECKLIST, 'file_path' => 'gad.pdf',
            'original_filename' => 'gad.pdf', 'mime_type' => 'application/pdf', 'file_size' => 100,
        ]);
        $version->files()->create([
            'document_type' => ProposalVersionFile::TYPE_HEAD_UPLOAD, 'file_path' => 'assessment.pdf',
            'original_filename' => 'assessment.pdf', 'mime_type' => 'application/pdf', 'file_size' => 100,
            'source_version_file_id' => $gad->id,
            'source_data' => ['purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_GAD_ASSESSMENT,
                'target_document_type' => ProposalVersionFile::TYPE_GAD_CHECKLIST,
                'gad_signature_confirmed' => $signed, 'gad_score' => 15,
                'gad_outcome' => $outcome === 'unsigned' ? 'passed' : $outcome],
        ]);
        $version->files()->create([
            'document_type' => ProposalVersionFile::TYPE_DETAILED_PROPOSAL, 'file_path' => 'detailed.pdf',
            'original_filename' => 'detailed.pdf', 'mime_type' => 'application/pdf', 'file_size' => 100,
            'source_data' => ['rationale' => str_repeat('Large paper contents outside the status list. ', 10000)],
        ]);
    }

    Livewire::actingAs($head)->test(ResearchHeadDashboard::class)
        ->assertViewHas('topics', function ($topics): bool {
            foreach ($topics as $topic) {
                expect($topic->latestVersion->files_count)->toBe(2)
                    ->and($topic->latestVersion->files->pluck('document_type')->all())->not->toContain(ProposalVersionFile::TYPE_DETAILED_PROPOSAL);
                expect($topic->workflowStatusLabel($topic->latestVersion))->toBe(
                    $topic->title === 'GAD outcome passed' ? 'Co-evaluator review' : 'GAD assessment',
                );
            }

            return $topics->count() === 3;
        });
});

test('project folders reuse loaded proposal versions across their feedback history', function () {
    Storage::fake('local');
    Role::firstOrCreate(['name' => 'faculty']);
    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');
    $this->actingAs($faculty);
    $topic = TopicProposal::create(['user_id' => $faculty->id, 'title' => 'Multiple revision rounds', 'status' => 'revision_requested']);
    $reviewVersionIds = [];
    foreach (range(1, 5) as $number) {
        $path = 'history/version-'.$number.'.pdf';
        Storage::disk('local')->put($path, '%PDF-1.7');
        $version = $topic->versions()->create([
            'submitted_by' => $faculty->id, 'version_number' => $number, 'submission_type' => $number === 1 ? 'initial' : 'revision',
            'title' => 'Version '.$number, 'file_path' => $path, 'original_filename' => 'proposal.pdf', 'mime_type' => 'application/pdf', 'file_size' => 8,
        ]);
        $file = $version->files()->create([
            'document_type' => ProposalVersionFile::TYPE_DETAILED_PROPOSAL,
            'file_path' => $path, 'original_filename' => 'proposal.pdf', 'mime_type' => 'application/pdf', 'file_size' => 8,
        ]);
        $review = $topic->reviews()->create(['reviewer_id' => $faculty->id, 'decision' => 'revision_requested', 'comment' => 'Review of version '.$number]);
        $revision = $review->fileRevisions()->create(['proposal_version_file_id' => $file->id, 'document_type' => $file->document_type, 'original_filename' => 'proposal.pdf']);
        $file->annotations()->create([
            'topic_review_file_revision_id' => $revision->id, 'reviewer_id' => $faculty->id,
            'annotation_type' => ProposalFileAnnotation::TYPE_AREA, 'page_number' => 1, 'rectangles' => [], 'comment' => 'Saved highlight '.$number,
        ]);
        $reviewVersionIds[$review->id] = $version->id;
    }
    $topic = $topic->fresh()->load(['versions.files', 'reviews.fileRevisions.file', 'reviews.fileRevisions.annotations.reviewer', 'reviews.reviewer', 'stageTransitions']);

    DB::enableQueryLog();
    DB::flushQueryLog();
    $library = app(ProjectDocumentLibrary::class)->build($topic, $faculty);
    foreach ($topic->reviews as $review) {
        expect(app(CommentResponseFeedback::class)->reviewedVersion($review)->id)->toBe($reviewVersionIds[$review->id]);
        expect(app(CommentResponseFeedback::class)->rows($review))->toHaveCount(2);
    }
    $queries = collect(DB::getQueryLog());
    DB::disableQueryLog();

    expect($library['documents']->where('generated_comment_response', true))->toHaveCount(5)
        ->and($queries->filter(fn (array $query): bool => str_contains($query['query'], 'proposal_versions') || str_contains($query['query'], 'proposal_version_files')))->toHaveCount(0);
});
