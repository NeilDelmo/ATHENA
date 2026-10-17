<?php

namespace App\Services;

use App\Exceptions\LiteratureFullTextPreviewException;
use App\Support\LiteratureFullTextToken;
use DOMDocument;
use DOMNode;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class LiteratureFullTextPreviewService
{
    public function __construct(private LiteratureDocumentTextExtractor $extractor) {}

    /** @return array{preview_text: string, evidence_basis: string, source_url: string, content_type: string, provider: string, coverage: array<string, mixed>, notice: string} */
    public function preview(string $sourceToken): array
    {
        $source = LiteratureFullTextToken::decode($sourceToken);
        [$response, $resolvedUrl, $body] = $this->fetch($source['url']);
        $contentType = Str::lower(Str::before((string) $response->header('Content-Type'), ';'));

        if (in_array($contentType, ['text/html', 'application/xhtml+xml'], true) && $this->looksLikeAuthenticationPage($body, $resolvedUrl)) {
            throw new LiteratureFullTextPreviewException('The source redirected to a sign-in or access-control page. ATHENA will not bypass authentication or institutional restrictions.', 403);
        }

        $isPdf = $contentType === 'application/pdf' || Str::startsWith($body, '%PDF-');
        $pdf = $isPdf ? $this->pdfText($body) : null;
        $text = match (true) {
            $isPdf => collect($pdf['pages'])->pluck('text')->join("\n\n"),
            in_array($contentType, ['text/html', 'application/xhtml+xml'], true) => $this->htmlText($body),
            $contentType === 'text/plain' => Str::squish($body),
            default => throw new LiteratureFullTextPreviewException('This open-access source does not provide a supported PDF, HTML, or text document.', 415),
        };

        $text = Str::squish($text);
        $characterLimit = (int) config('literature.full_text.maximum_characters');
        $truncated = ! $isPdf && Str::length($text) > $characterLimit;
        if (! $isPdf) {
            $text = Str::limit($text, $characterLimit, '');
        }

        if (Str::length($text) < 500) {
            throw new LiteratureFullTextPreviewException(($pdf['coverage']['reason'] ?? 'The open-access file did not contain enough readable text.').' Attach a readable source PDF before generating from the whole paper.');
        }

        return [
            'preview_text' => $text,
            'evidence_basis' => 'full_text',
            'source_url' => $resolvedUrl,
            'content_type' => $contentType,
            'provider' => $source['provider'],
            'coverage' => $pdf['coverage'] ?? [
                'characters' => Str::length($text),
                'character_limit' => $characterLimit,
                'page_limit' => null,
                'total_pages' => null,
                'truncated' => $truncated,
                'partial_or_unknown' => true,
                'complete' => false,
                'limited' => true,
                'reason' => 'The complete paper coverage of this non-PDF source cannot be verified.',
            ],
            'notice' => ($pdf['notice'] ?? 'Extracted source text from a provider-designated open-access location. Full-paper coverage is unknown; extraction does not establish that every section was retrieved. '
                .($truncated ? 'The extracted text was shortened to '.$characterLimit.' characters. ' : '')
                .'Check the original paper for missing sections, tables, figures, and extraction errors. ')
                .' The original file and extracted text were not stored.',
        ];
    }

    /** @return array{Response, string, string} */
    private function fetch(string $initialUrl): array
    {
        $url = $initialUrl;
        $maximumRedirects = (int) config('literature.full_text.maximum_redirects');

        for ($redirects = 0; $redirects <= $maximumRedirects; $redirects++) {
            $this->ensurePublicUrl($url);

            try {
                $response = Http::withoutRedirecting()
                    ->withOptions(['stream' => true, 'read_timeout' => (int) config('literature.full_text.timeout')])
                    ->withHeaders(['User-Agent' => 'ATHENA Literature Preview ('.config('app.url').')'])
                    ->accept('application/pdf,text/html,application/xhtml+xml,text/plain;q=0.9')
                    ->connectTimeout((int) config('literature.full_text.connect_timeout'))
                    ->timeout((int) config('literature.full_text.timeout'))
                    ->get($url);
            } catch (ConnectionException) {
                throw new LiteratureFullTextPreviewException('The open-access source could not be reached. Attach a complete readable source PDF to generate from the whole paper.', 502);
            }

            try {
                $contentLength = (int) $response->header('Content-Length');

                if ($contentLength > (int) config('literature.full_text.maximum_bytes')) {
                    throw new LiteratureFullTextPreviewException('The open-access file is too large to preview safely.', 413);
                }

                if (! in_array($response->status(), [301, 302, 303, 307, 308], true)) {
                    if ($response->failed()) {
                        throw new LiteratureFullTextPreviewException('The open-access source rejected the preview request. Attach a complete readable source PDF to generate from the whole paper.', 502);
                    }

                    return [$response, $url, $this->readBody($response)];
                }

                $location = $response->header('Location');

                if (! is_string($location) || $location === '' || $redirects === $maximumRedirects) {
                    throw new LiteratureFullTextPreviewException('The open-access source redirected too many times. Attach a complete readable source PDF to generate from the whole paper.', 502);
                }

                $url = $this->resolveRedirect($url, $location);
            } finally {
                $response->close();
            }
        }

        throw new LiteratureFullTextPreviewException('The open-access source could not be loaded.', 502);
    }

    private function readBody(Response $response): string
    {
        $stream = $response->toPsrResponse()->getBody();
        $maximumBytes = (int) config('literature.full_text.maximum_bytes');
        $deadline = microtime(true) + (int) config('literature.full_text.timeout');
        $body = '';

        try {
            while (! $stream->eof()) {
                if (microtime(true) >= $deadline) {
                    throw new LiteratureFullTextPreviewException('The open-access source could not be read within the time limit. Attach a readable source PDF instead.', 502);
                }

                $chunk = $stream->read(min(8192, $maximumBytes - strlen($body) + 1));

                if ($chunk === '' && ! $stream->eof()) {
                    throw new LiteratureFullTextPreviewException('The open-access source could not be read completely. Attach a readable source PDF instead.', 502);
                }

                $body .= $chunk;

                if (strlen($body) > $maximumBytes) {
                    throw new LiteratureFullTextPreviewException('The open-access file is too large to preview safely.', 413);
                }
            }
        } catch (LiteratureFullTextPreviewException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new LiteratureFullTextPreviewException('The open-access source could not be read completely. Attach a readable source PDF instead.', 502);
        }

        return $body;
    }

    private function ensurePublicUrl(string $url): void
    {
        $parts = parse_url($url);
        $scheme = Str::lower((string) ($parts['scheme'] ?? ''));
        $host = trim((string) ($parts['host'] ?? ''));

        if (! in_array($scheme, ['http', 'https'], true) || $host === '' || isset($parts['user']) || isset($parts['pass'])) {
            throw new LiteratureFullTextPreviewException('The full-text source URL is not safe to retrieve.');
        }

        $addresses = filter_var($host, FILTER_VALIDATE_IP)
            ? [$host]
            : collect(dns_get_record($host, DNS_A | DNS_AAAA) ?: [])
                ->flatMap(fn (array $record): array => array_values(array_filter([
                    $record['ip'] ?? null,
                    $record['ipv6'] ?? null,
                ], 'is_string')))
                ->all();

        if ($addresses === [] || collect($addresses)->contains(
            fn (string $address): bool => filter_var(
                $address,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
            ) === false,
        )) {
            throw new LiteratureFullTextPreviewException('The full-text source URL does not resolve to a public internet address.');
        }
    }

    private function resolveRedirect(string $currentUrl, string $location): string
    {
        if (preg_match('/^https?:\/\//i', $location) === 1) {
            return $location;
        }

        $parts = parse_url($currentUrl);
        $origin = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '');

        if (isset($parts['port'])) {
            $origin .= ':'.$parts['port'];
        }

        if (Str::startsWith($location, '//')) {
            return ($parts['scheme'] ?? 'https').':'.$location;
        }

        if (Str::startsWith($location, '/')) {
            return $origin.$location;
        }

        $directory = Str::beforeLast((string) ($parts['path'] ?? '/'), '/');

        return $origin.$directory.'/'.$location;
    }

    /** @return array{pages: list<array{number: int, text: string}>, coverage: array<string, mixed>, notice: string} */
    private function pdfText(string $contents): array
    {
        $temporaryDirectory = (string) config('literature.full_text.temporary_directory');
        File::ensureDirectoryExists($temporaryDirectory);
        $inputPath = tempnam($temporaryDirectory, 'athena-literature-');

        if ($inputPath === false) {
            throw new LiteratureFullTextPreviewException('The open-access PDF could not be prepared for preview.', 500);
        }

        try {
            if (File::put($inputPath, $contents) !== strlen($contents)) {
                throw new LiteratureFullTextPreviewException('The open-access PDF could not be prepared for preview.', 500);
            }

            return $this->extractor->extract($inputPath);
        } catch (LiteratureFullTextPreviewException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new LiteratureFullTextPreviewException('PDF text extraction is unavailable on this server. Attach a readable source PDF before generating from the whole paper.', 503);
        } finally {
            File::delete($inputPath);
        }
    }

    private function htmlText(string $html): string
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        foreach (['script', 'style', 'noscript', 'nav', 'form', 'svg', 'header', 'footer'] as $tag) {
            $nodes = iterator_to_array($document->getElementsByTagName($tag));

            foreach ($nodes as $node) {
                if ($node instanceof DOMNode) {
                    $node->parentNode?->removeChild($node);
                }
            }
        }

        foreach (iterator_to_array($document->getElementsByTagName('*')) as $node) {
            if (preg_match('/(?:^|[\s_-])abstract(?:[\s_-]|$)/i', $node->getAttribute('id').' '.$node->getAttribute('class')) === 1) {
                $node->parentNode?->removeChild($node);
            }
        }

        $sections = [];

        foreach (['h1', 'h2', 'h3', 'h4', 'h5', 'h6'] as $tag) {
            foreach ($document->getElementsByTagName($tag) as $heading) {
                $name = Str::of($heading->textContent)->squish()->lower()
                    ->replaceMatches('/^(?:\d+(?:\.\d+)*|[ivxlcdm]+)[.)]?\s+/iu', '')
                    ->trim(' .:')->toString();

                if (in_array($name, ['methods', 'methodology', 'research methodology', 'materials and methods', 'materials & methods', 'research design', 'results', 'findings', 'results and discussion', 'results & discussion', 'discussion', 'conclusion', 'conclusions'], true)) {
                    $sections[] = $name;
                }
            }
        }

        $text = (string) ($document->getElementsByTagName('body')->item(0)?->textContent ?? '');

        if (count(array_unique($sections)) < 2 || Str::length(Str::squish($text)) < 500) {
            throw new LiteratureFullTextPreviewException('The public page does not expose enough recognizable paper body text. Attach the source PDF before generating from the paper.');
        }

        return $text;
    }

    private function looksLikeAuthenticationPage(string $html, string $url): bool
    {
        $normalized = Str::lower(Str::limit(strip_tags($html), 8000, ''));
        $path = Str::lower((string) parse_url($url, PHP_URL_PATH));
        $hasPasswordInput = preg_match('/<input[^>]+type=["\']?password/i', $html) === 1;
        $hasLoginLanguage = Str::contains($normalized, [
            'sign in to access',
            'log in to access',
            'institutional login',
            'access through your institution',
            'purchase this article',
            'subscribe to read',
        ]);

        return $hasPasswordInput || ($hasLoginLanguage && Str::contains($path, ['login', 'signin', 'auth', 'access']));
    }
}
