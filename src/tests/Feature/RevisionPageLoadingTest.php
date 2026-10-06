<?php

use App\Models\ProposalVersionFile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

test('revision pages load paper editors only when opened', function () {
    Role::firstOrCreate(['name' => 'faculty']);
    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');
    $topic = $faculty->proposals()->create(['title' => 'Revision loading regression', 'status' => 'revision_requested']);
    $version = $topic->versions()->create([
        'submitted_by' => $faculty->id,
        'version_number' => 1,
        'submission_type' => 'initial',
        'title' => $topic->title,
        'file_path' => 'revision-loading/submitted.pdf',
        'original_filename' => 'submitted.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 100,
    ]);
    $review = $topic->reviews()->create(['reviewer_id' => $faculty->id, 'decision' => 'revision_requested', 'comment' => 'Clarify the proposed methods and schedule.']);
    foreach ([ProposalVersionFile::TYPE_DETAILED_PROPOSAL, ProposalVersionFile::TYPE_WORK_PLAN] as $type) {
        $file = $version->files()->create([
            'document_type' => $type,
            'file_path' => 'revision-loading/'.$type.'.pdf',
            'original_filename' => $type.'.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 100,
        ]);
        $fileRevision = $review->fileRevisions()->create([
            'proposal_version_file_id' => $file->id,
            'document_type' => $type,
            'original_filename' => $file->original_filename,
            'revision_note' => 'Update this paper.',
        ]);
        if ($type === ProposalVersionFile::TYPE_DETAILED_PROPOSAL) {
            $file->annotations()->create([
                'reviewer_id' => $faculty->id,
                'topic_review_file_revision_id' => $fileRevision->id,
                'annotation_type' => 'text',
                'page_number' => 1,
                'rectangles' => [],
                'selected_text' => 'Proposed methods',
                'comment' => 'Clarify the sampling method.',
                'editor_target' => 'section-methodology',
            ]);
        }
    }

    DB::enableQueryLog();
    DB::flushQueryLog();
    $started = hrtime(true);
    $response = $this->actingAs($faculty)->get(route('faculty.topics.revision', $topic));
    $elapsed = (hrtime(true) - $started) / 1_000_000;
    $queries = collect(DB::getQueryLog());
    DB::disableQueryLog();
    $response->assertOk()->assertSee('Clarify the proposed methods and schedule.');
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    expect($xpath->query('//iframe[@data-revision-editor-frame]')->length)->toBe(2)
        ->and($xpath->query('//iframe[@data-revision-editor-frame and @src]')->length)->toBe(0)
        ->and($xpath->query('//iframe[@data-revision-editor-frame and @data-revision-editor-src]')->length)->toBe(2);
    $this->assertDatabaseCount('proposal_drafts', 0);

    if (getenv('ATHENA_REVISION_BENCHMARK') === '1') {
        fwrite(STDERR, sprintf("Revision page: %.1f ms, %d queries, %.1f ms querying, %d bytes\n", $elapsed, $queries->count(), $queries->sum('time'), strlen($response->getContent())));
        foreach (range(1, 3) as $sample) {
            $started = hrtime(true);
            $this->get(route('faculty.topics.revision', $topic))->assertOk();
            fwrite(STDERR, sprintf("Revision page repeat %d: %.1f ms\n", $sample, (hrtime(true) - $started) / 1_000_000));
        }
    }
    $fixturePath = getenv('ATHENA_REVISION_BROWSER_FIXTURE');
    if (is_string($fixturePath) && $fixturePath !== '') {
        file_put_contents($fixturePath, $response->getContent());
    }
});
