<?php

use App\Models\CollegeQuarterlyReport;
use App\Models\ProjectNarrativeReport;
use App\Models\TopicProposal;
use App\Models\User;
use App\Services\CollegeQuarterlyReportDocument;
use App\Services\DetailedProposalDocumentService;
use App\Services\DetailedProposalMethodologyImageService;
use App\Services\ProgressReportDocumentService;
use App\Support\DetailedProposalData;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    Storage::set('local', Storage::fake('report-image-optimization'));
});

/** @return array<string, string> */
function optimizedReportParts(string $contents): array
{
    $path = tempnam(sys_get_temp_dir(), 'report-image-');
    file_put_contents($path, $contents);
    $archive = new ZipArchive;
    try {
        expect($archive->open($path, ZipArchive::CHECKCONS))->toBeTrue();
        $parts = [];
        for ($index = 0; $index < $archive->numFiles; $index++) {
            $parts[$archive->getNameIndex($index)] = $archive->getFromIndex($index);
        }

        return $parts;
    } finally {
        $archive->close();
        unlink($path);
    }
}

/** @return array<string, mixed> */
function optimizedProposalData(string $path): array
{
    return DetailedProposalData::fromValidated([
        'project_title' => 'Image report',
        'methodology_images' => array_map(fn (int $number): array => [
            'id' => 'figure-'.$number, 'section' => 'research_design', 'alignment' => 'center', 'size' => 'large',
            'caption' => 'RETAINEDFIGURE'.$number, 'stored_path' => $path, 'mime_type' => 'image/jpeg',
            'original_filename' => 'photo.jpg',
        ], [1, 2]),
    ]);
}

test('report exporters embed bounded photo copies and retain every caption and original', function (string $kind) {
    $disk = Storage::disk('local');
    $path = 'photo.jpg';
    $disk->put($path, UploadedFile::fake()->image('photo.jpg', 3000, 2000)->getContent());
    $original = $disk->get($path);
    $user = new User(['name' => 'Researcher', 'college' => User::COLLEGES['CICS']]);

    if ($kind === 'proposal') {
        $contents = app(DetailedProposalDocumentService::class)->generate(optimizedProposalData($path));
        $prefix = 'word/media/methodology-';
    } elseif ($kind === 'quarterly') {
        $report = new CollegeQuarterlyReport(['college' => $user->college, 'evidence' => [
            ['path' => $path, 'caption' => 'RETAINEDFIGURE1'], ['path' => $path, 'caption' => 'RETAINEDFIGURE2'],
        ]]);
        $report->setRelation('preparedBy', null);
        $contents = app(CollegeQuarterlyReportDocument::class)->generate($user, [
            'quarter' => 2, 'start' => Carbon::parse('2026-04-01'), 'end' => Carbon::parse('2026-06-30'),
        ], ['presentations' => [], 'publications' => [], 'research' => []], $report);
        $prefix = 'word/media/quarterly-evidence-';
    } else {
        $topic = new TopicProposal(['title' => 'Image report', 'estimated_duration_months' => 12]);
        $topic->setRelation('user', $user);
        $report = new ProjectNarrativeReport([
            'report_type' => $kind, 'submission_date' => '2026-06-30', 'researchers' => 'Researcher',
            'implementation_start' => '2026-01-01', 'implementation_end' => '2026-12-31',
            'budget' => 10000, 'funding_agency' => 'University', 'accomplishments' => [],
            'introduction' => 'Introduction', 'rationale' => 'Rationale', 'objectives' => 'Objective',
            'methodology' => 'Method', 'results_discussion' => 'Results',
            'photos' => [
                ['path' => $path, 'caption' => 'RETAINEDFIGURE1', 'section' => $kind === 'terminal' ? 'cover' : 'methodology'],
                ['path' => $path, 'caption' => 'RETAINEDFIGURE2', 'section' => 'results_discussion'],
            ],
        ]);
        $report->setRelation('topic', $topic);
        $report->setRelation('submitter', $user);
        $contents = app(ProgressReportDocumentService::class)->generate($report);
        $prefix = 'word/media/progress-figure-';
    }

    $parts = optimizedReportParts($contents);
    $media = array_filter($parts, fn (string $name): bool => str_starts_with($name, $prefix), ARRAY_FILTER_USE_KEY);
    expect($media)->toHaveCount(2)
        ->and($parts['word/document.xml'])->toContain('RETAINEDFIGURE1', 'RETAINEDFIGURE2')
        ->and($disk->get($path))->toBe($original);
    foreach ($media as $image) {
        [$width, $height, $type] = getimagesizefromstring($image);
        expect(max($width, $height))->toBeLessThanOrEqual(1600)
            ->and($type)->toBe(IMAGETYPE_JPEG);
    }
})->with(['progress', 'terminal', 'quarterly', 'proposal']);

test('proposal image data URLs use reduced copies for both fresh and saved photos', function (bool $uploaded) {
    $photo = UploadedFile::fake()->image('photo.jpg', 3000, 2000);
    $original = $photo->getContent();
    Storage::disk('local')->put('photo.jpg', $original);
    $image = $uploaded ? ['image' => $photo] : ['stored_path' => 'photo.jpg', 'mime_type' => 'image/jpeg'];

    $url = app(DetailedProposalMethodologyImageService::class)->dataUrl($image);
    $contents = base64_decode(explode(',', $url, 2)[1], true);
    [$width, $height] = getimagesizefromstring($contents);
    expect($url)->toStartWith('data:image/jpeg;base64,')
        ->and(max($width, $height))->toBeLessThanOrEqual(1600)
        ->and(Storage::disk('local')->get('photo.jpg'))->toBe($original)
        ->and($photo->getContent())->toBe($original);
})->with(['uploaded' => true, 'saved' => false]);

test('missing or corrupt saved proposal figures cannot silently disappear from successful exports', function (bool $exists) {
    if ($exists) {
        Storage::disk('local')->put('unavailable.jpg', 'corrupt image');
    }
    expect(fn () => app(DetailedProposalDocumentService::class)->generate(optimizedProposalData('unavailable.jpg')))
        ->toThrow(RuntimeException::class);
})->with(['missing' => false, 'corrupt' => true]);
