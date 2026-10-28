<?php

use App\Exceptions\LiteratureSynthesisException;
use App\Models\ProposalDraftLiteratureSource;
use App\Services\LiteratureDocumentTextExtractor;
use App\Services\LiteratureFullTextPreviewService;
use App\Services\LiteratureSynthesisEvidenceResolver;
use App\Services\LiteratureSynthesisService;
use App\Support\LiteratureFullTextToken;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    Storage::fake('local');
    Http::preventStrayRequests();
    Process::preventStrayProcesses();
    Storage::disk('local')->put('source.pdf', '%PDF-1.7 test source');
    $this->path = Storage::disk('local')->path('source.pdf');
});

function fakeWholePaperExtraction(?int $total, array $pages, int $exitCode = 0): void
{
    Process::fake(fn (PendingProcess $process) => Process::result(
        output: Str::contains(implode(' ', $process->command), 'pdfinfo') ? ($total === null ? 'Page count unavailable' : 'Pages: '.$total."\n") : implode("\f", $pages)."\f",
        exitCode: $exitCode,
    ));
}

test('abstract-only evidence is rejected by the resolver and synthesis service before any provider request', function () {
    $abstract = str_repeat('Indexed abstract content. ', 30);
    expect(fn () => app(LiteratureSynthesisEvidenceResolver::class)->resolve(['abstract' => $abstract], 'abstract'))
        ->toThrow(ValidationException::class, 'Abstract-only drafting is disabled');
    expect(fn () => app(LiteratureSynthesisService::class)->synthesize(['title' => 'Indexed source', 'abstract' => $abstract, 'evidence_basis' => 'abstract']))
        ->toThrow(LiteratureSynthesisException::class, 'source-paper text');
    Http::assertNothingSent();
});

test('the shared PDF extractor includes late conclusions beyond page forty without truncating paper text', function () {
    $pages = array_fill(0, 50, str_repeat('Methods and findings form part of the full paper. ', 15));
    $pages[49] = 'Late conclusion: village differences remained after the monitoring period. '.str_repeat('This is the final discussion. ', 20);
    fakeWholePaperExtraction(50, $pages);

    $document = app(LiteratureDocumentTextExtractor::class)->extract($this->path);
    $linked = new ProposalDraftLiteratureSource(['evidence_document' => $document]);
    $evidence = app(LiteratureSynthesisEvidenceResolver::class)->resolve(['description' => 'Indexed abstract.'], 'full_text', $linked);

    expect($document['coverage']['complete'])->toBeTrue()
        ->and($document['coverage']['limited'])->toBeFalse()
        ->and($document['coverage']['total_pages'])->toBe(50)
        ->and($document['pages'])->toHaveCount(50)
        ->and($evidence['evidence_text'])->toContain('Late conclusion: village differences remained')
        ->and(Str::length($evidence['evidence_text']))->toBeGreaterThan(30000)
        ->and($evidence['notice'])->toContain('all extractable text')
        ->and($evidence['coverage']['origin'])->toBe('uploaded_pdf');
    Process::assertRan(fn (PendingProcess $process): bool => in_array('-l', $process->command, true) && in_array('200', $process->command, true) && ! in_array('-nopgbrk', $process->command, true));
});

test('public PDF extraction uses the same whole-paper check without the old thirty-thousand-character cutoff', function () {
    $pages = array_fill(0, 50, str_repeat('Whole paper methods, observations, and results. ', 16));
    $pages[49] .= ' The final conclusion is beyond page forty.';
    fakeWholePaperExtraction(50, $pages);
    $url = 'https://93.184.216.34/source.pdf';
    Http::fake([$url => Http::response('%PDF-1.7 source', 200, ['Content-Type' => 'application/pdf'])]);
    config(['literature.full_text.temporary_directory' => Storage::disk('local')->path('public-temporary')]);
    $token = LiteratureFullTextToken::issue(['url' => $url, 'provider' => 'OpenAlex']);

    $evidence = app(LiteratureSynthesisEvidenceResolver::class)->resolve(['full_text_token' => $token], 'full_text');

    expect($evidence['coverage']['complete'])->toBeTrue()
        ->and($evidence['coverage']['total_pages'])->toBe(50)
        ->and(Str::length($evidence['evidence_text']))->toBeGreaterThan(30000)
        ->and($evidence['evidence_text'])->toContain('final conclusion is beyond page forty')
        ->and(Storage::disk('local')->allFiles())->toBe(['source.pdf']);
    Http::assertSentCount(1);
});

test('partial scanned unknown and oversized PDFs remain readable but cannot generate a whole-paper draft', function (string $case, string $reason) {
    $text = str_repeat('Readable source content remains available for the reader. ', 15);
    match ($case) {
        'unknown' => fakeWholePaperExtraction(null, [$text]),
        'scan' => fakeWholePaperExtraction(2, [$text, '']),
        'missing' => fakeWholePaperExtraction(2, [$text]),
        'pages' => fakeWholePaperExtraction(201, array_fill(0, 200, 'Readable source page.')),
        'characters' => fakeWholePaperExtraction(1, [str_repeat('z', 120001)]),
        'failure' => fakeWholePaperExtraction(1, [], 1),
    };
    $document = app(LiteratureDocumentTextExtractor::class)->extract($this->path);
    $source = new ProposalDraftLiteratureSource(['evidence_document' => $document]);

    expect($document['coverage']['complete'])->toBeFalse()
        ->and($document['coverage']['limited'])->toBeTrue()
        ->and($document['coverage']['reason'])->toContain($reason)
        ->and(fn () => app(LiteratureSynthesisEvidenceResolver::class)->resolve([], 'full_text', $source))
        ->toThrow(ValidationException::class)
        ->and(app(LiteratureSynthesisEvidenceResolver::class)->canReadPaper([], $source))->toBeFalse();
    Http::assertNothingSent();
})->with([
    ['unknown', 'page count'], ['scan', 'no readable text'], ['missing', 'Not every PDF page'],
    ['pages', '200-page'], ['characters', '120000-character'], ['failure', 'extraction failed'],
]);

test('old saved partial PDF metadata must be re-extracted instead of accepted for having enough characters', function () {
    $source = new ProposalDraftLiteratureSource(['evidence_document' => ['pages' => [['number' => 1, 'text' => str_repeat('Existing saved evidence. ', 30)]], 'coverage' => ['total_pages' => 1, 'extracted_pages' => 1, 'limited' => false]]]);

    expect(fn () => app(LiteratureSynthesisEvidenceResolver::class)->resolve([], 'full_text', $source))->toThrow(ValidationException::class, 'Reattach it in Sources');
    Process::assertNothingRan();
    Http::assertNothingSent();
});

test('non PDF source previews with unknown complete coverage cannot be used for whole-paper generation', function () {
    $preview = Mockery::mock(LiteratureFullTextPreviewService::class);
    $preview->shouldReceive('preview')->once()->andReturn(['preview_text' => str_repeat('Methods and results are visible. ', 30), 'coverage' => ['complete' => false, 'reason' => 'HTML coverage is unknown.']]);

    expect(fn () => (new LiteratureSynthesisEvidenceResolver($preview))->resolve(['full_text_token' => 'server-token'], 'full_text'))
        ->toThrow(ValidationException::class, 'HTML coverage is unknown');
});
