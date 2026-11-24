<?php

use App\Actions\ArchiveProposalDraftDocumentHistory;
use App\Models\ProposalDraft;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class);

test('staged proposal figures remain available after submission cleanup', function () {
    Storage::fake('local');
    $disk = Storage::disk('local');
    $draft = (new ProposalDraft)->forceFill(['id' => 100, 'user_id' => 42]);
    $staged = $draft->storageDirectory().'/detailed-proposal/methodology-images/diagram.png';
    $earlier = 'proposal-packages/42/previous/figures/diagram.png';
    $disk->put($staged, 'New diagram');
    $disk->put($earlier, 'Earlier diagram');
    $source = ['methodology_images' => [
        ['id' => 'new-diagram', 'path' => $staged, 'caption' => 'Study flow'],
        ['id' => 'earlier-diagram', 'path' => $earlier, 'caption' => 'Old study flow'],
    ]];

    $archive = app(ArchiveProposalDraftDocumentHistory::class);
    $package = 'proposal-packages/42/current';
    $archived = $archive->archiveFigureSources($draft, $source, $package);
    $archivedPath = $archived['methodology_images'][0]['path'];

    expect($archivedPath)->toStartWith($package.'/figures/')
        ->and($archived['methodology_images'][0])->toMatchArray(['id' => 'new-diagram', 'caption' => 'Study flow'])
        ->and($archived['methodology_images'][1])->toBe($source['methodology_images'][1])
        ->and($archive->archiveFigureSources($draft, $source, $package))->toBe($archived)
        ->and($disk->allFiles($package))->toBe([$archivedPath]);

    $disk->deleteDirectory($draft->storageDirectory());
    expect($disk->get($archivedPath))->toBe('New diagram')
        ->and($disk->get($earlier))->toBe('Earlier diagram');
});
