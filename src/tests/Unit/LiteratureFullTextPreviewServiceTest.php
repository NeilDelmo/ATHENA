<?php

use App\Exceptions\LiteratureFullTextPreviewException;
use App\Services\LiteratureFullTextPreviewService;
use App\Support\LiteratureFullTextToken;
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

test('public PDF previews disclose unknown coverage and remove the transient source file', function () {
    $extracted = str_repeat('The paper describes its methods and reports monitoring outcomes. ', 20);
    Http::fake([$this->sourceUrl => Http::response('%PDF-1.7 source bytes', 200, ['Content-Type' => 'application/pdf'])]);
    Process::fake(['*' => Process::result(output: $extracted)]);

    $preview = app(LiteratureFullTextPreviewService::class)->preview($this->sourceToken);

    expect($preview['preview_text'])->toBe(Str::squish($extracted))
        ->and($preview['coverage'])->toBe([
            'characters' => Str::length(Str::squish($extracted)),
            'character_limit' => 30000,
            'page_limit' => 40,
            'total_pages' => null,
            'truncated' => false,
            'partial_or_unknown' => true,
        ])
        ->and($preview['notice'])->toContain('Full-paper coverage is unknown', 'at most 40 pages', 'not stored')
        ->and(Storage::disk('local')->allFiles())->toBe([]);
    Process::assertRan(fn ($process): bool => in_array('-l', $process->command, true) && in_array('40', $process->command, true));
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
