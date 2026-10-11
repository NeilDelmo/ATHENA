<?php

use App\Contracts\DocumentPdfConverter;
use App\Models\ProjectNarrativeReport;
use App\Models\TopicProposal;
use App\Models\User;
use Database\Seeders\TerminalPosterUiDemoSeeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->posterImage = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jKJ0AAAAASUVORK5CYII=');
    File::shouldReceive('get')->with(public_path('images/sample-maintenance-poster.png'))->andReturn($this->posterImage);
    $owner = User::factory()->create();
    $this->posterTopic = TopicProposal::create([
        'user_id' => $owner->id,
        'title' => 'Predictive Maintenance Dashboard for University Laboratory Equipment',
        'description' => '[lifecycle-demo:completed-published-second] UI demonstration project',
        'status' => 'approved', 'project_status' => 'completed',
    ]);
    Storage::disk('local')->put('original-evidence.png', $this->posterImage);
    Storage::disk('local')->put('original-terminal.pdf', '%PDF original terminal');
    $this->posterReport = ProjectNarrativeReport::create([
        'topic_id' => $this->posterTopic->id, 'submitted_by' => $owner->id, 'report_type' => 'terminal',
        'tracking_number' => 'LIFE-'.$this->posterTopic->id.'-TERMINAL', 'submission_date' => '2026-07-01',
        'researchers' => $owner->name, 'implementation_start' => '2025-10-01', 'implementation_end' => '2026-06-30',
        'budget' => 25000, 'funding_agency' => 'Batangas State University',
        'accomplishment_summary' => 'UI DEMONSTRATION DATA: Sample research outcomes.',
        'introduction' => 'Sample introduction', 'rationale' => 'Sample rationale',
        'objectives' => 'Sample objectives', 'methodology' => 'Sample methods', 'results_discussion' => 'Sample results',
        'photos' => [['path' => 'original-evidence.png', 'caption' => 'Existing evidence', 'section' => 'results_discussion', 'after_paragraph' => 1]],
        'terminal_data' => ['signed_copy' => ['path' => 'original-terminal.pdf', 'checksum' => hash('sha256', '%PDF original terminal'), 'demo' => true]],
        'official_pdf_path' => 'original-terminal.pdf', 'official_pdf_filename' => 'original-terminal.pdf',
        'official_pdf_checksum' => hash('sha256', '%PDF original terminal'), 'official_pdf_size' => 22,
        'review_status' => 'reviewed', 'reviewed_by' => $owner->id, 'reviewed_at' => now()->subDay(),
    ]);
    $this->posterReport->refresh();
});

test('terminal poster sample adds a private cover once and regenerates the report without changing the signed original', function () {
    $original = $this->posterReport->getAttributes();
    $signedCopy = $this->posterReport->signedCopy();
    $this->mock(DocumentPdfConverter::class)->shouldReceive('convertDocx')->once()->withArgs(function (string $docx): bool {
        $path = tempnam(sys_get_temp_dir(), 'athena-poster-test-');
        file_put_contents($path, $docx);
        $archive = new ZipArchive;
        expect($archive->open($path))->toBeTrue();
        $xml = $archive->getFromName('word/document.xml');
        expect($xml)->toContain('SAMPLE POSTER')->toContain('I. Cover Page');
        $media = [];
        for ($index = 0; $index < $archive->numFiles; $index++) {
            if (str_starts_with($archive->getNameIndex($index), 'word/media/')) {
                $media[] = $archive->getFromIndex($index);
            }
        }
        expect(collect($media)->filter(fn (string $image): bool => $image === $this->posterImage))->toHaveCount(2);
        $archive->close();
        unlink($path);

        return true;
    })->andReturn('%PDF sample with poster');

    $this->seed(TerminalPosterUiDemoSeeder::class);
    $this->posterReport->refresh();
    $poster = collect($this->posterReport->photos)->firstWhere('section', 'cover');
    expect($this->posterReport->photos)->toHaveCount(2)
        ->and($this->posterReport->photos[0]['path'])->toBe('original-evidence.png')
        ->and($poster['caption'])->toContain('SAMPLE POSTER')
        ->and(Storage::disk('local')->get($poster['path']))->toBe($this->posterImage)
        ->and(Storage::disk('local')->get($this->posterReport->official_pdf_path))->toBe('%PDF sample with poster')
        ->and($this->posterReport->official_pdf_path)->not->toBe('original-terminal.pdf')
        ->and($this->posterReport->signedCopy())->toBe($signedCopy)
        ->and(Storage::disk('local')->get('original-terminal.pdf'))->toBe('%PDF original terminal')
        ->and($this->posterTopic->fresh()->project_status)->toBe('completed');
    foreach (['review_status', 'reviewed_by', 'reviewed_at', 'submitted_at'] as $field) {
        expect($this->posterReport->getAttributes()[$field])->toBe($original[$field]);
    }
    $backup = 'post-approval-ui-demo/'.$this->posterTopic->id.'/before-poster-sample.json';
    $backupData = Storage::disk('local')->get($backup);
    expect(json_decode($backupData, true)['photos'])->toHaveCount(1);
    $afterFirstRun = $this->posterReport->getAttributes();
    $this->seed(TerminalPosterUiDemoSeeder::class);
    expect($this->posterReport->fresh()->getAttributes())->toBe($afterFirstRun)
        ->and(Storage::disk('local')->get($backup))->toBe($backupData);
});

test('terminal poster sample preserves an existing cover', function () {
    $this->posterReport->update(['photos' => [['path' => 'original-evidence.png', 'caption' => 'Existing poster', 'section' => 'cover']]]);
    $original = $this->posterReport->fresh()->getAttributes();
    $this->mock(DocumentPdfConverter::class)->shouldNotReceive('convertDocx');
    $this->seed(TerminalPosterUiDemoSeeder::class);
    expect($this->posterReport->fresh()->getAttributes())->toBe($original)
        ->and(Storage::disk('local')->allFiles())->toHaveCount(2);
});

test('terminal poster sample rejects real or inconsistent records', function (string $field, mixed $value) {
    if (in_array($field, ['description', 'project_status', 'title'], true)) {
        $this->posterTopic->update([$field => $value]);
    } else {
        $this->posterReport->update([$field => $value]);
    }
    $original = $this->posterReport->fresh()->getAttributes();
    $this->mock(DocumentPdfConverter::class)->shouldNotReceive('convertDocx');
    expect(fn () => $this->seed(TerminalPosterUiDemoSeeder::class))->toThrow(Exception::class);
    expect($this->posterReport->fresh()->getAttributes())->toBe($original);
})->with([
    'actual project' => ['description', 'An actual completed research project'],
    'different title' => ['title', 'An unrelated completed project'],
    'ongoing project' => ['project_status', 'ongoing'],
    'real report' => ['accomplishment_summary', 'Actual research results'],
    'different tracking number' => ['tracking_number', 'REAL-TERMINAL'],
    'actual signed copy' => ['terminal_data', ['signed_copy' => ['path' => 'original-terminal.pdf', 'demo' => false]]],
]);

test('terminal poster sample keeps the saved report intact if PDF conversion fails', function () {
    $original = $this->posterReport->getAttributes();
    $this->mock(DocumentPdfConverter::class)->shouldReceive('convertDocx')->once()->andThrow(new RuntimeException('Conversion failed'));
    expect(fn () => $this->seed(TerminalPosterUiDemoSeeder::class))->toThrow(RuntimeException::class, 'Conversion failed');
    expect($this->posterReport->fresh()->getAttributes())->toBe($original)
        ->and(Storage::disk('local')->get('original-terminal.pdf'))->toBe('%PDF original terminal');
});

test('terminal poster sample never runs in production', function () {
    app()->detectEnvironment(fn (): string => 'production');
    try {
        expect(fn () => app(TerminalPosterUiDemoSeeder::class)->run())->toThrow(RuntimeException::class, 'production');
    } finally {
        app()->detectEnvironment(fn (): string => 'testing');
    }
});
