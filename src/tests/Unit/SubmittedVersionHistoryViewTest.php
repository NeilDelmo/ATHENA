<?php

use App\Models\ProposalVersion;
use App\Models\TopicProposal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class);

test('history labels distinguish submitted snapshots from working edits', function () {
    DB::connection()->beforeExecuting(function (): never {
        throw new RuntimeException('Version history view test must not access the database.');
    });

    $topic = (new TopicProposal)->forceFill(['id' => 3, 'title' => 'Fruit Drop Detection']);
    $version = (new ProposalVersion)->forceFill([
        'id' => 7,
        'topic_id' => 3,
        'version_number' => 1,
        'submission_type' => 'initial',
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
            'Only packages sent for review appear here.',
            'Working edits are excluded until submission.',
            '1 submitted version',
            'Initial submission',
            'Latest submitted',
        )
        ->not->toContain('Proposal version history', '>Latest</span>');
});
