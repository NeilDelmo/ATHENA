<?php

use App\Models\ProjectDocument;
use App\Models\ProposalVersionFile;
use App\Models\TopicProposal;
use App\Models\User;
use App\Services\ProjectDocumentLibrary;
use App\Services\ProposalSignatureWorkflow;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();
    Storage::fake('local');
    foreach (['research_head', 'faculty'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }
    $this->head = User::factory()->create(['name' => 'Research Head']);
    $this->head->assignRole('research_head');
    $this->faculty = User::factory()->create();
    $this->faculty->assignRole('faculty');
    $this->topic = TopicProposal::create([
        'user_id' => $this->faculty->id,
        'title' => 'Coastal Research Review Record',
        'status' => TopicProposal::STATUS_READY_FOR_SIGNATURE,
        'review_stage' => 'lrec',
    ]);
    $this->version = $this->topic->versions()->create([
        'submitted_by' => $this->faculty->id,
        'version_number' => 2,
        'submission_type' => 'revision',
        'file_path' => 'packages/proposal.pdf',
        'original_filename' => 'proposal.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 100,
        'checksum' => str_repeat('a', 64),
        'title' => $this->topic->title,
    ]);
    foreach (ProposalSignatureWorkflow::REQUIRED_DOCUMENT_TYPES as $position => $type) {
        $path = 'packages/'.$type.'.pdf';
        Storage::disk('local')->put($path, '%PDF-1.4 original');
        $this->version->files()->create([
            'document_type' => $type,
            'position' => $position,
            'file_path' => $path,
            'original_filename' => $type.'.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 20,
        ]);
    }
    foreach ([
        ['revision_requested', 'initial', 'Clarify the methodology.', now()->subDays(2)],
        ['gad_review', 'initial', null, now()->subDay()],
        ['ready_for_signature', 'lrec', 'Committee clearance recorded.', now()],
        ['head_upload', 'initial', 'Internal upload event.', now()],
    ] as [$decision, $stage, $comment, $createdAt]) {
        $review = $this->topic->reviews()->create([
            'reviewer_id' => $this->head->id,
            'decision' => $decision,
            'review_stage' => $stage,
            'comment' => $comment,
        ]);
        $review->forceFill(['created_at' => $createdAt])->save();
    }
});

test('completed reviews expose a dated timeline and accurate signing checklist', function () {
    $source = $this->version->files()->where('document_type', ProposalVersionFile::TYPE_WORK_PLAN)->sole();
    Storage::disk('local')->put('signed/work-plan.pdf', '%PDF-1.4 signed');
    $this->version->files()->create([
        'document_type' => ProposalVersionFile::TYPE_HEAD_UPLOAD,
        'source_version_file_id' => $source->id,
        'file_path' => 'signed/work-plan.pdf',
        'original_filename' => 'signed-work-plan.pdf',
        'mime_type' => 'application/pdf',
        'source_data' => ['purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SIGNED],
    ]);
    $response = $this->actingAs($this->head)->get(route('topics.show', $this->topic))->assertOk();
    $dom = new DOMDocument;
    @$dom->loadHTML($response->getContent());
    $xpath = new DOMXPath($dom);
    $summary = $xpath->query('//*[@id="proposal-review-tab"]//*[@data-proposal-review-summary]')->item(0);

    expect($summary)->not->toBeNull()
        ->and($summary->textContent)->toContain('Review complete', 'Version 2', '3 decisions', '1 requested', 'Clarify the methodology.', 'Committee clearance recorded.')
        ->not->toContain('Internal upload event.')
        ->and($xpath->query('.//*[@data-review-timeline]/li', $summary)->length)->toBe(3)
        ->and($xpath->query('.//*[@data-review-timeline]/li[1]', $summary)->item(0)->textContent)->toContain('Cleared for signing')
        ->and($xpath->query('.//*[@data-review-timeline]//h4[contains(@class, "text-base")]', $summary)->length)->toBe(3)
        ->and($xpath->query('.//*[@data-review-timeline]//time[contains(@class, "text-sm")]', $summary)->length)->toBe(3)
        ->and($xpath->query('.//*[@data-review-signature-count]', $summary)->item(0)->textContent)->toContain('1 of 5')
        ->and($xpath->query('.//*[@data-review-signature-checklist]/li', $summary)->length)->toBe(5)
        ->and($xpath->query('.//*[@data-review-signature-checklist]/li[contains(., "Signed copy uploaded")]', $summary)->length)->toBe(1);
    $response->assertSee("setTopicTab('notice', 'notice-to-proceed')", false)
        ->assertSee("setTopicTab('history', 'version-history')", false);
});

test('released proposals retain review history and link to released documents', function () {
    $this->topic->update(['status' => 'approved', 'notice_to_proceed_issued_at' => now()]);
    $this->actingAs($this->head)->get(route('topics.show', $this->topic))
        ->assertOk()
        ->assertSee('data-proposal-review-summary', false)
        ->assertSee('Proposal released')
        ->assertSee('View released documents');
});

test('faculty views do not inherit the Research Head signing summary', function () {
    $this->actingAs($this->faculty)->get(route('topics.show', $this->topic))
        ->assertOk()
        ->assertDontSee('data-proposal-review-summary', false)
        ->assertDontSee('Continue to signing');
});

test('accepted assessments appear as signed papers while final signing requests only three uploads', function () {
    $workflow = app(ProposalSignatureWorkflow::class);
    $assessmentFiles = collect();
    foreach ([
        ProposalVersionFile::TYPE_GAD_CHECKLIST => ['purpose' => 'gad_assessment', 'gad_signature_confirmed' => true, 'gad_outcome' => 'passed', 'gad_score' => 10],
        ProposalVersionFile::TYPE_INITIAL_SCREENING_FORM => ['purpose' => 'evaluation', 'narrative_evaluation' => 'Recommended for endorsement.', 'recommended_action' => 'for_endorsement'],
    ] as $type => $data) {
        $source = $this->version->files()->where('document_type', $type)->sole();
        $path = 'assessments/'.$type.'.pdf';
        Storage::disk('local')->put($path, '%PDF-1.4 completed');
        $assessmentFiles->push($this->version->files()->create([
            'document_type' => ProposalVersionFile::TYPE_HEAD_UPLOAD,
            'position' => 100 + $source->id,
            'source_version_file_id' => $source->id,
            'original_filename' => 'completed-'.$type.'.pdf',
            'file_path' => $path,
            'mime_type' => 'application/pdf',
            'source_data' => ['target_document_type' => $type, ...$data],
        ]));
    }
    expect($workflow->signedSourceFileIds($this->version->fresh()))->toHaveCount(2)
        ->and($workflow->missingRequiredFiles($this->version->fresh()))->toHaveCount(3)
        ->and($workflow->isComplete($this->version->fresh()))->toBeFalse();
    $response = $this->actingAs($this->head)->get(route('topics.show', $this->topic))->assertOk();
    if (getenv('ATHENA_EXPORT_SIGNING_LAYOUT') === '1') {
        File::ensureDirectoryExists(storage_path('framework/testing'));
        file_put_contents(storage_path('framework/testing/signed-upload-layout.html'), $response->getContent());
    }
    $dom = new DOMDocument;
    @$dom->loadHTML($response->getContent());
    $xpath = new DOMXPath($dom);
    expect($xpath->query('//*[@data-signing-document]')->length)->toBe(3)
        ->and($xpath->query('//*[@data-signing-document][contains(., "Awaiting signed copy")]')->length)->toBe(3)
        ->and($xpath->query('//*[@data-signing-document]//*[@data-signed-paper-input]')->length)->toBe(3)
        ->and($xpath->query('//*[@data-signed-batch-input]')->length)->toBe(0)
        ->and($xpath->query('//*[@data-signed-dropzone]')->length)->toBe(3)
        ->and($xpath->query('//*[@data-signed-preview-modal]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-signing-document]//a[@target="_blank"]')->length)->toBe(0)
        ->and($xpath->query('//*[@data-signing-document]//form')->length)->toBe(0)
        ->and($xpath->query('//*[@data-signing-document]//*[@data-signed-copy-preview]')->length)->toBe(0);
    $response->assertSee('View signed papers')
        ->assertSee('Files save automatically.')
        ->assertDontSee('Upload PDF')
        ->assertDontSee('Replace signed PDF');
    $library = app(ProjectDocumentLibrary::class)->build($this->topic->fresh(), $this->head);
    $signedDocuments = $library['documents']->where('category', ProjectDocument::CATEGORY_SIGNED_PAPERS);
    expect($signedDocuments)->toHaveCount(2)
        ->and($signedDocuments->where('official', true))->toHaveCount(2);
    foreach ($assessmentFiles as $file) {
        $response->assertSee(route('topics.versions.files.view', [$this->topic, $this->version, $file]));
        expect($signedDocuments->where('key', 'proposal-version-file-'.$file->id))->toHaveCount(1)
            ->and($library['documents']->where('filename', $file->original_filename))->toHaveCount(1);
    }
    $this->patch(route('research_head.topics.finalizeApproval', $this->topic))->assertSessionHasErrors('status');
    foreach ($workflow->missingRequiredFiles($this->version->fresh()) as $source) {
        $path = 'signed/'.$source->id.'.pdf';
        Storage::disk('local')->put($path, '%PDF-1.4 signed');
        $this->version->files()->create([
            'document_type' => ProposalVersionFile::TYPE_HEAD_UPLOAD,
            'position' => 200 + $source->id,
            'source_version_file_id' => $source->id,
            'original_filename' => 'signed-'.$source->id.'.pdf',
            'file_path' => $path,
            'mime_type' => 'application/pdf',
            'source_data' => ['purpose' => 'signed'],
        ]);
    }
    expect($workflow->isComplete($this->version->fresh()))->toBeTrue();
    $library = app(ProjectDocumentLibrary::class)->build($this->topic->fresh(), $this->head);
    expect($library['documents']->where('category', ProjectDocument::CATEGORY_SIGNED_PAPERS))->toHaveCount(5);
    $this->patch(route('research_head.topics.finalizeApproval', $this->topic))->assertSessionHasNoErrors();
});

test('unusable or incomplete earlier assessments do not satisfy signing', function (string $type, array $overrides, bool $missingFile, bool $superseded, bool $wrongSource) {
    $source = $this->version->files()->where('document_type', $type)->sole();
    $path = 'assessments/completed.pdf';
    if (! $missingFile) {
        Storage::disk('local')->put($path, '%PDF-1.4 completed');
    }
    $this->version->files()->create([
        'document_type' => ProposalVersionFile::TYPE_HEAD_UPLOAD,
        'source_version_file_id' => $wrongSource ? null : $source->id,
        'original_filename' => 'completed.pdf',
        'file_path' => $path,
        'mime_type' => 'application/pdf',
        'superseded_at' => $superseded ? now() : null,
        'source_data' => array_replace([
            'target_document_type' => $type,
            'purpose' => $type === 'gad_checklist' ? 'gad_assessment' : 'evaluation',
            'gad_signature_confirmed' => true,
            'gad_outcome' => 'passed',
            'narrative_evaluation' => 'Endorsement recorded.',
            'recommended_action' => 'for_endorsement',
        ], $overrides),
    ]);
    expect(app(ProposalSignatureWorkflow::class)->signedSourceFileIds($this->version->fresh()))->toBeEmpty();
    $library = app(ProjectDocumentLibrary::class)->build($this->topic->fresh(), $this->head);
    expect($library['documents']->where('category', ProjectDocument::CATEGORY_SIGNED_PAPERS))->toBeEmpty();
})->with([
    'unconfirmed GAD signature' => ['gad_checklist', ['gad_signature_confirmed' => false], false, false, false],
    'GAD returned for revision' => ['gad_checklist', ['gad_outcome' => 'returned'], false, false, false],
    'screening requests revision' => ['initial_screening_form', ['recommended_action' => 'minor_revision'], false, false, false],
    'screening has no evaluation' => ['initial_screening_form', ['narrative_evaluation' => null], false, false, false],
    'missing assessment PDF' => ['gad_checklist', [], true, false, false],
    'superseded assessment' => ['initial_screening_form', [], false, true, false],
    'assessment is not linked to the current paper' => ['gad_checklist', [], false, false, true],
]);
