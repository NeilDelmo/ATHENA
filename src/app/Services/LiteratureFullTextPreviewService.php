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
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Throwable;

class LiteratureFullTextPreviewService
{
    /** @return array{preview_text: string, evidence_basis: string, source_url: string, content_type: string, provider: string, notice: string} */
    public function preview(string $sourceToken): array
    {
        $source = LiteratureFullTextToken::decode($sourceToken);
        [$response, $resolvedUrl] = $this->fetch($source['url']);
        $contentType = Str::lower(Str::before((string) $response->header('Content-Type'), ';'));
        $body = $response->body();

        if (strlen($body) > (int) config('literature.full_text.maximum_bytes')) {
            throw new LiteratureFullTextPreviewException('The open-access file is too large to preview safely.', 413);
        }

        if (in_array($contentType, ['text/html', 'application/xhtml+xml'], true) && $this->looksLikeAuthenticationPage($body, $resolvedUrl)) {
            throw new LiteratureFullTextPreviewException('The source redirected to a sign-in or access-control page. ATHENA will not bypass authentication or institutional restrictions.', 403);
        }

        $text = match (true) {
            $contentType === 'application/pdf' || Str::startsWith($body, '%PDF-') => $this->pdfText($body),
            in_array($contentType, ['text/html', 'application/xhtml+xml'], true) => $this->htmlText($body),
            $contentType === 'text/plain' => Str::squish($body),
            default => throw new LiteratureFullTextPreviewException('This open-access source does not provide a supported PDF, HTML, or text document.', 415),
        };

        $text = Str::limit(Str::squish($text), (int) config('literature.full_text.maximum_characters'), '');

        if (Str::length($text) < 500) {
            throw new LiteratureFullTextPreviewException('The open-access file did not contain enough readable text. ATHENA will continue using the indexed abstract.');
        }

        return [
            'preview_text' => $text,
            'evidence_basis' => 'full_text',
            'source_url' => $resolvedUrl,
            'content_type' => $contentType,
            'provider' => $source['provider'],
            'notice' => 'Loaded transiently from a provider-designated open-access source. The original file and extracted text were not stored.',
        ];
    }

    /** @return array{Response, string} */
    private function fetch(string $initialUrl): array
    {
        $url = $initialUrl;
        $maximumRedirects = (int) config('literature.full_text.maximum_redirects');

        for ($redirects = 0; $redirects <= $maximumRedirects; $redirects++) {
            $this->ensurePublicUrl($url);

            try {
                $response = Http::withoutRedirecting()
                    ->withHeaders(['User-Agent' => 'ATHENA Literature Preview ('.config('app.url').')'])
                    ->accept('application/pdf,text/html,application/xhtml+xml,text/plain;q=0.9')
                    ->connectTimeout((int) config('literature.full_text.connect_timeout'))
                    ->timeout((int) config('literature.full_text.timeout'))
                    ->get($url);
            } catch (ConnectionException) {
                throw new LiteratureFullTextPreviewException('The open-access source could not be reached. ATHENA will continue using the indexed abstract.', 502);
            }

            $contentLength = (int) $response->header('Content-Length');

            if ($contentLength > (int) config('literature.full_text.maximum_bytes')) {
                throw new LiteratureFullTextPreviewException('The open-access file is too large to preview safely.', 413);
            }

            if (! in_array($response->status(), [301, 302, 303, 307, 308], true)) {
                if ($response->failed()) {
                    throw new LiteratureFullTextPreviewException('The open-access source rejected the preview request. ATHENA will continue using the indexed abstract.', 502);
                }

                return [$response, $url];
            }

            $location = $response->header('Location');

            if (! is_string($location) || $location === '' || $redirects === $maximumRedirects) {
                throw new LiteratureFullTextPreviewException('The open-access source redirected too many times. ATHENA will continue using the indexed abstract.', 502);
            }

            $url = $this->resolveRedirect($url, $location);
        }

        throw new LiteratureFullTextPreviewException('The open-access source could not be loaded.', 502);
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

    private function pdfText(string $contents): string
    {
        $temporaryDirectory = (string) config('literature.full_text.temporary_directory');
        File::ensureDirectoryExists($temporaryDirectory);
        $inputPath = tempnam($temporaryDirectory, 'athena-literature-');

        if ($inputPath === false || File::put($inputPath, $contents) === false) {
            throw new LiteratureFullTextPreviewException('The open-access PDF could not be prepared for preview.', 500);
        }

        try {
            $result = Process::timeout((int) config('literature.full_text.timeout'))->run([
                (string) config('literature.full_text.pdftotext_binary'),
                '-f',
                '1',
                '-l',
                (string) config('literature.full_text.maximum_pdf_pages'),
                '-layout',
                '-nopgbrk',
                $inputPath,
                '-',
            ]);

            if ($result->failed()) {
                throw new LiteratureFullTextPreviewException('The open-access PDF could not be converted to readable text. ATHENA will continue using the indexed abstract.');
            }

            return $result->output();
        } catch (LiteratureFullTextPreviewException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new LiteratureFullTextPreviewException('PDF text extraction is unavailable on this server. ATHENA will continue using the indexed abstract.', 503);
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

        return (string) $document->textContent;
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
