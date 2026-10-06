<?php

use App\Models\ProposalVersion;
use App\Models\TopicProposal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class);

test('history labels distinguish submitted snapshots from working edits', function (string $submissionType, string $label) {
    DB::connection()->beforeExecuting(function (): never {
        throw new RuntimeException('Version history view test must not access the database.');
    });

    $topic = (new TopicProposal)->forceFill(['id' => 3, 'title' => 'Fruit Drop Detection']);
    $version = (new ProposalVersion)->forceFill([
        'id' => 7,
        'topic_id' => 3,
        'version_number' => 1,
        'submission_type' => $submissionType,
        'title' => 'Fruit Drop Detection',
        'estimated_budget' => 3000,
        'estimated_duration_months' => 12,
        'created_at' => Carbon::parse('2026-08-28 20:05:00'),
        'original_filename' => 'proposal.pdf',
    ]);
    $version->setRelation('submitter', null);
    $version->setRelation('files', collect());
    $topic->setRelation('versions', collect([$version]));

    $html = view('topics.partials.version-history', [
        'topic' => $topic,
        'expanded' => true,
    ])->render();

    expect($html)
        ->toContain(
            'Submitted proposal versions',
            'Proposals sent for review. Working edits appear after submission.',
            '1 version',
            $label,
            '>Latest</span>',
            'data-submitted-version="1"',
        )
        ->not->toContain('Proposal version history', 'data-version-screening-form', 'package');
})->with([
    'initial' => ['initial', 'Initial submission'],
    'update' => ['update', 'Submission update before review'],
    'revision' => ['revision', 'Revision submission'],
]);
