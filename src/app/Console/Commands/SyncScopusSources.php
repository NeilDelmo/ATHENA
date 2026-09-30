<?php

namespace App\Console\Commands;

use App\Services\ScopusSourceRegistry;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

#[Signature('journals:sync-scopus {--file= : Import an already downloaded official XLSX} {--url= : Official Elsevier source-list download URL}')]
#[Description('Import the official Scopus source list, including inactive and discontinued journals')]
class SyncScopusSources extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ScopusSourceRegistry $registry): int
    {
        $path = $this->option('file');
        $download = null;
        try {
            $url = $this->option('url') ?: config('services.scopus.source_list_url');
            if (! $url && ! $path) {
                $page = Http::connectTimeout(5)->timeout(15)->get('https://www.elsevier.com/products/scopus/content');
                if (! $page->successful() || ! preg_match('~(?:https:)?//(?:downloads|assets)\.ctfassets\.net/[^"<>\\\\\s]+/ext_list[^"<>\\\\\s]*\.xlsx~i', $page->body(), $match)) {
                    $this->error('The latest source-list link could not be found. Use --url with the official download from Elsevier’s Scopus content page.');

                    return self::FAILURE;
                }
                $url = Str::startsWith($match[0], '//') ? 'https:'.$match[0] : $match[0];
            }
            $url ??= 'https://www.elsevier.com/products/scopus/content';
            $officialDownload = is_string($url) && parse_url($url, PHP_URL_SCHEME) === 'https'
                && in_array(parse_url($url, PHP_URL_HOST), ['downloads.ctfassets.net', 'assets.ctfassets.net'], true);
            if (! $officialDownload && ! ($path && $url === 'https://www.elsevier.com/products/scopus/content')) {
                $this->error('Use the official source-list XLSX download linked from Elsevier’s Scopus content page.');

                return self::FAILURE;
            }
            if (! $path) {
                $path = $download = storage_path('app/private/journals/scopus-source-list-'.Str::uuid().'.download');
                File::ensureDirectoryExists(dirname($path));
                $response = Http::connectTimeout(5)->timeout(60)->withOptions(['sink' => $path])->get($url);
                if (! $response->successful()) {
                    $this->error('The official workbook could not be downloaded. The existing registry was preserved.');

                    return self::FAILURE;
                }
            }
            $metadata = $registry->import($path, $url);
            if ($download && ! File::move($download, storage_path('app/private/journals/scopus-source-list.xlsx'))) {
                $this->warn('The registry was updated, but the downloaded workbook could not be archived.');
            }
            $this->info('Imported '.$metadata['edition'].'. Coverage is verified as of '.$metadata['as_of'].'.');
            $this->line('Refresh using the current source list linked at https://www.elsevier.com/products/scopus/content');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } finally {
            if ($download && File::exists($download)) {
                File::delete($download);
            }
        }
    }
}
