<?php

use App\Contracts\DocumentPdfConverter;
use App\Models\ProposalVersionFile;
use App\Services\LibreOfficeDocumentPdfConverter;
use App\Services\ProposalPackageService;
use App\Services\ProposalRevisionSectionMap;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

test('attachment preparation batches conversions and leaves revision indexing for reviewers', function () {
    Storage::fake('local');
    app()->bind(DocumentPdfConverter::class, LibreOfficeDocumentPdfConverter::class);
    Process::preventStrayProcesses();
    Process::fake(function (PendingProcess $process) {
        $outputIndex = array_search('--outdir', $process->command, true);
        expect($outputIndex)->not->toBeFalse();
        $directory = $process->command[$outputIndex + 1];
        foreach ([...glob($directory.'/*.docx'), ...glob($directory.'/*.xlsx')] as $source) {
            File::put($directory.'/'.pathinfo($source, PATHINFO_FILENAME).'.pdf', "%PDF-1.7\n".File::get($source));
        }

        return Process::result();
    });

    $papers = [];
    foreach (['work-plan', 'line-item-budget', 'expense-breakdown', 'curriculum-vitae', 'gad-checklist', 'initial-screening-form'] as $slug) {
        $papers[$slug] = ['contents' => $slug, 'source_data' => ['project_title' => 'Batch Project']];
    }
    $files = app(ProposalPackageService::class)->storeGeneratedPapers($papers, 'batch-test', 'Batch Project');

    expect($files)->toHaveCount(6);
    foreach ($files as $file) {
        $slug = str_replace('_', '-', $file['document_type']);
        $suffix = $slug === 'expense-breakdown' ? 'estimated-expense-breakdown' : $slug;
        expect($file['original_filename'])->toBe('batch-project-'.$suffix.'.pdf')
            ->and($file['mime_type'])->toBe('application/pdf')
            ->and($file['source_data'])->toBe(['project_title' => 'Batch Project'])
            ->and(Storage::disk('local')->get($file['file_path']))->toBe("%PDF-1.7\n".$slug)
            ->and($file['checksum'])->toBe(hash('sha256', "%PDF-1.7\n".$slug));
    }
    Process::assertRanTimes(fn (PendingProcess $process): bool => true, 2);
});

test('a batch storage failure removes files stored earlier in the batch', function () {
    $this->mock(DocumentPdfConverter::class, function ($mock) {
        $mock->shouldReceive('convertDocx')->twice()->andReturn('%PDF-1.7 example');
    });
    $disk = Mockery::mock();
    $disk->shouldReceive('put')->once()->ordered()->andReturn(true);
    $disk->shouldReceive('put')->once()->ordered()->andReturn(false);
    $disk->shouldReceive('delete')->once()->withArgs(fn (array $paths): bool => count($paths) === 1 && str_contains($paths[0], '/work-plan/'))->andReturn(true);
    Storage::shouldReceive('disk')->with('local')->andReturn($disk);

    expect(fn () => app(ProposalPackageService::class)->storeGeneratedPapers([
        'work-plan' => ['contents' => 'Work plan', 'source_data' => []],
        'line-item-budget' => ['contents' => 'Budget', 'source_data' => []],
    ], 'batch-failure', 'Batch Project'))->toThrow(RuntimeException::class, 'could not be stored');
});

test('batched official PDFs preserve section coordinates and benchmark the preparation work', function () {
    if (getenv('ATHENA_PDF_BENCHMARK') !== '1') {
        $this->markTestSkipped('Set ATHENA_PDF_BENCHMARK=1 to run real LibreOffice conversion and timing checks.');
    }

    $documents = [];
    foreach (['work_plan', 'line_item_budget', 'expense_breakdown', 'curriculum_vitae', 'gad_checklist', 'initial_screening_form', 'detailed_proposal'] as $type) {
        $documents[$type] = [
            'contents' => File::get(config($type.'.template_path')),
            'format' => $type === ProposalVersionFile::TYPE_EXPENSE_BREAKDOWN ? 'xlsx' : 'docx',
        ];
    }
    $converter = app(LibreOfficeDocumentPdfConverter::class);
    $sectionMap = app(ProposalRevisionSectionMap::class);
    $startedAt = hrtime(true);
    $originalRegions = [];
    foreach ($documents as $type => $document) {
        try {
            $pdf = $document['format'] === 'xlsx'
                ? $converter->convertXlsx($document['contents'])
                : $converter->convertDocx($document['contents']);
        } catch (RuntimeException $exception) {
            throw new RuntimeException($type.': '.($exception->getPrevious()?->getMessage() ?? $exception->getMessage()), previous: $exception);
        }
        $originalRegions[$type] = $sectionMap->fromPdf($pdf, $type);
    }
    $sequentialSeconds = (hrtime(true) - $startedAt) / 1e9;

    $startedAt = hrtime(true);
    $detailed = $documents[ProposalVersionFile::TYPE_DETAILED_PROPOSAL];
    unset($documents[ProposalVersionFile::TYPE_DETAILED_PROPOSAL]);
    $pdfs = $converter->convertDocuments($documents);
    $pdfs[ProposalVersionFile::TYPE_DETAILED_PROPOSAL] = $converter->convertDocx($detailed['contents']);
    $batchSeconds = (hrtime(true) - $startedAt) / 1e9;

    expect($pdfs)->toHaveCount(7);
    foreach ($pdfs as $type => $pdf) {
        expect($pdf)->toStartWith('%PDF-')
            ->and($sectionMap->fromPdf($pdf, $type))->toBe($originalRegions[$type]);
    }

    fwrite(STDERR, sprintf("\nPDF benchmark: sequential %.2fs; batched %.2fs; reduction %.1f%%.\n", $sequentialSeconds, $batchSeconds, 100 * (1 - $batchSeconds / $sequentialSeconds)));
})->group('pdf-performance');
