<?php

use App\Exceptions\LiteratureFullTextPreviewException;
use App\Services\LiteratureFullTextPreviewService;
use App\Support\LiteratureFullTextToken;
use GuzzleHttp\Psr7\FnStream;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    Http::preventStrayRequests();
    Storage::fake('local');
    config(['literature.full_text.temporary_directory' => Storage::disk('local')->path('preview-temporary')]);
    $this->sourceUrl = 'https://93.184.216.34/paper';
    $this->sourceToken = LiteratureFullTextToken::issue(['url' => $this->sourceUrl, 'provider' => 'OpenAlex']);
});

test('public PDF previews verify every page and remove the transient source file', function () {
    $extracted = str_repeat('The paper describes its methods and reports monitoring outcomes. ', 20);
    Http::fake([$this->sourceUrl => Http::response('%PDF-1.7 source bytes', 200, ['Content-Type' => 'application/pdf'])]);
    Process::fake(fn (PendingProcess $process) => Process::result(output: Str::contains(implode(' ', $process->command), 'pdfinfo') ? "Pages: 1\n" : $extracted."\f"));

    $preview = app(LiteratureFullTextPreviewService::class)->preview($this->sourceToken);

    expect($preview['preview_text'])->toBe(Str::squish($extracted))
        ->and($preview['coverage']['character_limit'])->toBe(120000)
        ->and($preview['coverage']['page_limit'])->toBe(200)
        ->and($preview['coverage']['total_pages'])->toBe(1)
        ->and($preview['coverage']['extracted_pages'])->toBe(1)
        ->and($preview['coverage']['complete'])->toBeTrue()
        ->and($preview['coverage']['partial_or_unknown'])->toBeFalse()
        ->and($preview['notice'])->toContain('All 1 PDF pages', 'not stored')
        ->and(Storage::disk('local')->allFiles())->toBe([]);
    Process::assertRan(fn ($process): bool => in_array('-l', $process->command, true) && in_array('200', $process->command, true));
    Http::assertSentCount(1);
});

test('public text previews report character truncation without claiming complete paper coverage', function () {
    $text = str_repeat('The methods and results remain source evidence. ', 800);
    Http::fake([$this->sourceUrl => Http::response($text, 200, ['Content-Type' => 'text/plain'])]);

    $preview = app(LiteratureFullTextPreviewService::class)->preview($this->sourceToken);

    expect(Str::length($preview['preview_text']))->toBeLessThanOrEqual(30000)
        ->and($preview['coverage']['characters'])->toBe(Str::length($preview['preview_text']))
        ->and($preview['coverage']['character_limit'])->toBe(30000)
        ->and($preview['coverage']['page_limit'])->toBeNull()
        ->and($preview['coverage']['truncated'])->toBeTrue()
        ->and($preview['coverage']['partial_or_unknown'])->toBeTrue()
        ->and($preview['notice'])->toContain('shortened to 30000 characters', 'Full-paper coverage is unknown')
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

test('public HTML pages containing only abstracts cannot be used as paper body evidence', function (string $content) {
    Http::fake([$this->sourceUrl => Http::response('<html><body>'.$content.'</body></html>', 200, ['Content-Type' => 'text/html'])]);

    expect(fn () => app(LiteratureFullTextPreviewService::class)->preview($this->sourceToken))
        ->toThrow(LiteratureFullTextPreviewException::class, 'does not expose enough recognizable paper body text');
    expect(Storage::disk('local')->allFiles())->toBe([]);
})->with([
    'long abstract landing page' => '<h1>Abstract</h1><p>'.str_repeat('This abstract reports associations in community monitoring. ', 20).'</p>',
    'structured abstract headings' => '<section class="abstract"><h2>Methods</h2><p>'.str_repeat('The abstract describes methods. ', 20).'</p><h2>Results</h2><p>'.str_repeat('The abstract mentions results. ', 20).'</p></section>',
    'abstract ID' => '<section id="abstract"><h2>Methods</h2><h2>Results</h2><p>'.str_repeat('An abstract remains preliminary information. ', 20).'</p></section>',
    'short body with long abstract' => '<section class="abstract">'.str_repeat('Abstract content. ', 80).'</section><h2>Methods</h2><p>Short description.</p><h2>Results</h2><p>Short findings.</p>',
]);

test('public HTML paper sections can be extracted while retaining unknown coverage and transient privacy', function () {
    $abstract = str_repeat('This abstract reports associations in community monitoring. ', 20);
    $methods = str_repeat('The methods explain how community monitoring records were compared. ', 10);
    $results = str_repeat('The results describe differences in reporting between coastal villages. ', 10);
    Http::fake([$this->sourceUrl => Http::response('<html><body><nav>Navigation must be omitted.</nav><script>Injected instructions.</script><header>Page heading.</header><main><section class="abstract"><h1>Abstract</h1><p>'.$abstract.'</p></section><h2>2. Materials and Methods</h2><p>'.$methods.'</p><h2>III. Results</h2><p>'.$results.'</p></main><footer>Footer omitted.</footer></body></html>', 200, ['Content-Type' => 'text/html'])]);

    $preview = app(LiteratureFullTextPreviewService::class)->preview($this->sourceToken);

    expect($preview['preview_text'])->toContain(Str::squish($methods), Str::squish($results))
        ->not->toContain('Abstract', 'Navigation must be omitted', 'Injected instructions', 'Page heading', 'Footer omitted')
        ->and($preview['coverage']['partial_or_unknown'])->toBeTrue()
        ->and($preview['coverage']['truncated'])->toBeFalse()
        ->and($preview['coverage']['page_limit'])->toBeNull()
        ->and($preview['notice'])->toContain('does not establish that every section was retrieved', 'Check the original paper', 'not stored')
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

test('remote preview reads stop at the byte limit even when the response length is absent or false', function (array $headers) {
    config(['literature.full_text.maximum_bytes' => 1024]);
    $underlying = Utils::streamFor(str_repeat('Source text. ', 300));
    $readBytes = 0;
    $closed = false;
    $stream = FnStream::decorate($underlying, [
        '__toString' => function () use ($underlying, &$readBytes): string {
            $text = (string) $underlying;
            $readBytes += strlen($text);

            return $text;
        },
        'read' => function (int $length) use ($underlying, &$readBytes): string {
            $text = $underlying->read($length);
            $readBytes += strlen($text);

            return $text;
        },
        'close' => function () use ($underlying, &$closed): void {
            $closed = true;
            $underlying->close();
        },
    ]);
    Http::fake(function ($request, array $options) use ($stream, $headers) {
        expect($options['stream'] ?? false)->toBeTrue()
            ->and($options['read_timeout'] ?? null)->toBe(18);

        return Http::response($stream, 200, ['Content-Type' => 'text/plain', ...$headers]);
    });

    expect(fn () => app(LiteratureFullTextPreviewService::class)->preview($this->sourceToken))
        ->toThrow(LiteratureFullTextPreviewException::class, 'too large');
    expect($readBytes)->toBeLessThanOrEqual(1025)
        ->and($closed)->toBeTrue();
})->with([
    'missing content length' => [[]],
    'false small content length' => [['Content-Length' => '1']],
]);

test('oversized advertised downloads are closed without reading their body', function () {
    config(['literature.full_text.maximum_bytes' => 1024]);
    $underlying = Utils::streamFor('An oversized source response.');
    $closed = false;
    $stream = FnStream::decorate($underlying, [
        'read' => fn (): string => throw new RuntimeException('The rejected body must not be read.'),
        'close' => function () use ($underlying, &$closed): void {
            $closed = true;
            $underlying->close();
        },
    ]);
    Http::fake([$this->sourceUrl => Http::response($stream, 200, ['Content-Length' => '1025'])]);

    expect(fn () => app(LiteratureFullTextPreviewService::class)->preview($this->sourceToken))
        ->toThrow(LiteratureFullTextPreviewException::class, 'too large');
    expect($closed)->toBeTrue();
});

test('redirect and successful preview response bodies are closed', function () {
    $redirect = Utils::streamFor('Redirect bodies are not paper content.');
    $paper = Utils::streamFor(str_repeat('The source paper methods and results are readable. ', 20));
    Http::fake([
        $this->sourceUrl => Http::response($redirect, 302, ['Location' => '/paper.txt']),
        'https://93.184.216.34/paper.txt' => Http::response($paper, 200, ['Content-Type' => 'text/plain']),
    ]);

    $preview = app(LiteratureFullTextPreviewService::class)->preview($this->sourceToken);

    expect($preview['preview_text'])->toContain('methods and results are readable')
        ->and($redirect->isReadable())->toBeFalse()
        ->and($paper->isReadable())->toBeFalse();
    Http::assertSentCount(2);
});

test('public PDF previews follow five publisher redirects before extracting the whole paper', function () {
    $extracted = str_repeat('The paper describes its methods and reports monitoring outcomes. ', 20);
    $responses = Http::fakeSequence();
    for ($redirect = 1; $redirect <= 5; $redirect++) {
        $responses->push('', 302, ['Location' => '/paper-'.$redirect]);
    }
    $responses->push('%PDF-1.7 source bytes', 200, ['Content-Type' => 'application/pdf']);
    Process::fake(fn (PendingProcess $process) => Process::result(output: Str::contains(implode(' ', $process->command), 'pdfinfo') ? "Pages: 1\n" : $extracted."\f"));

    $preview = app(LiteratureFullTextPreviewService::class)->preview($this->sourceToken);

    expect($preview['source_url'])->toBe('https://93.184.216.34/paper-5')
        ->and($preview['preview_text'])->toBe(Str::squish($extracted))
        ->and($preview['coverage']['complete'])->toBeTrue();
    Http::assertSentCount(6);
});

test('publisher redirect loops stop at the configured limit', function () {
    Http::fake([$this->sourceUrl => Http::response('', 302, ['Location' => $this->sourceUrl])]);

    expect(fn () => app(LiteratureFullTextPreviewService::class)->preview($this->sourceToken))
        ->toThrow(LiteratureFullTextPreviewException::class, 'redirected too many times');
    Http::assertSentCount((int) config('literature.full_text.maximum_redirects') + 1);
});

test('remote previews accept a source exactly at the byte limit and close its stream', function () {
    config(['literature.full_text.maximum_bytes' => 1024]);
    $stream = Utils::streamFor(str_repeat('a', 1024));
    Http::fake([$this->sourceUrl => Http::response($stream, 200, ['Content-Type' => 'text/plain'])]);

    $preview = app(LiteratureFullTextPreviewService::class)->preview($this->sourceToken);

    expect($preview['preview_text'])->toHaveLength(1024)
        ->and($stream->isReadable())->toBeFalse();
});

test('stream read failures close the response and provide a useful preview error', function (bool $emptyRead) {
    $underlying = Utils::streamFor('Unavailable remote body.');
    $stream = FnStream::decorate($underlying, [
        'read' => fn (): string => $emptyRead ? '' : throw new RuntimeException('The remote read timed out.'),
    ]);
    Http::fake([$this->sourceUrl => Http::response($stream, 200, ['Content-Type' => 'text/plain'])]);

    expect(fn () => app(LiteratureFullTextPreviewService::class)->preview($this->sourceToken))
        ->toThrow(LiteratureFullTextPreviewException::class, 'could not be read');
    expect($underlying->isReadable())->toBeFalse();
})->with(['read throws' => false, 'read stalls before EOF' => true]);

test('failed temporary PDF writes remove the created file', function (bool $throws) {
    Http::fake([$this->sourceUrl => Http::response('%PDF-1.7 source bytes', 200, ['Content-Type' => 'application/pdf'])]);
    $write = File::partialMock()->shouldReceive('put')->once();
    if ($throws) {
        $write->andThrow(new RuntimeException('Temporary storage is unavailable.'));
    } else {
        $write->andReturn(false);
    }

    expect(fn () => app(LiteratureFullTextPreviewService::class)->preview($this->sourceToken))
        ->toThrow(LiteratureFullTextPreviewException::class);
    expect(Storage::disk('local')->allFiles())->toBe([]);
})->with(['write returns false' => false, 'write throws' => true]);
