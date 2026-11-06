<?php

namespace App\Services;

use Illuminate\Contracts\Process\InvokedProcess;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Throwable;

class LibreOfficePdfWorker
{
    private ?InvokedProcess $process = null;

    private ?string $pipe = null;

    private ?string $profile = null;

    /** @var resource|null */
    private mixed $lock = null;

    public static function cacheKey(): string
    {
        return 'document-pdf:worker:'.hash('sha256', base_path().'|'.php_uname('n'));
    }

    public function start(): bool
    {
        if ($this->pipe !== null && Cache::pull(self::cacheKey().':restart') === $this->pipe) {
            $this->stop();
        }
        if ($this->process?->running()) {
            $this->heartbeat();

            return true;
        }
        $this->stop();
        $python = $this->python();
        if ($python === null) {
            return false;
        }
        $directory = rtrim(config('document_pdf.temporary_directory'), '/\\').'/worker';
        File::ensureDirectoryExists($directory, 0700);
        $this->lock = fopen($directory.'/worker.lock', 'c');
        if ($this->lock === false || ! flock($this->lock, LOCK_EX | LOCK_NB)) {
            $this->stop();

            return false;
        }
        // LibreOffice's nested registry paths must fit within Windows path limits.
        $this->profile = $directory.'/'.Str::random(12);
        File::ensureDirectoryExists($this->profile, 0700);
        $this->pipe = 'athena-pdf-'.Str::uuid();
        try {
            $this->process = Process::forever()->start([
                app(LibreOfficeProcess::class)->binary(), '--headless', '--nologo', '--nodefault', '--norestore',
                '-env:UserInstallation=file:///'.ltrim(str_replace('\\', '/', $this->profile), '/'),
                '--accept=pipe,name='.$this->pipe.';urp;',
            ]);
            $templates = array_map(fn (string $type): string => config($type.'.template_path'), [
                'work_plan', 'line_item_budget', 'expense_breakdown', 'curriculum_vitae',
                'gad_checklist', 'initial_screening_form', 'detailed_proposal',
            ]);
            $result = $this->export($python, $this->pipe, $templates, $this->profile, 'pdf', starting: true);
            if ($result->failed()) {
                throw new RuntimeException('The PDF worker could not initialize: export exit '.$result->exitCode().'; '
                    .$result->errorOutput().'; office running '.($this->process->running() ? 'yes' : 'no').'; '
                    .$this->process->output().'; '.$this->process->errorOutput());
            }
            $this->heartbeat();

            return true;
        } catch (Throwable $exception) {
            $this->stop();

            throw $exception;
        }
    }

    public function heartbeat(): void
    {
        if ($this->process?->running()) {
            Cache::put(self::cacheKey(), ['pipe' => $this->pipe, 'updated_at' => now()->timestamp], 120);
        }
    }

    /** @param list<string> $sources */
    public function convert(array $sources, string $directory, string $filter): ?ProcessResult
    {
        $worker = Cache::get(self::cacheKey());
        $python = $this->python();
        if ($python === null || ! is_array($worker) || ($worker['updated_at'] ?? 0) < now()->timestamp - 90) {
            return null;
        }

        return Cache::lock(self::cacheKey().':export', config('document_pdf.timeout_seconds') + 10)
            ->block(config('document_pdf.timeout_seconds'), function () use ($python, $worker, $sources, $directory, $filter): ?ProcessResult {
                try {
                    $result = $this->export($python, $worker['pipe'], $sources, $directory, $filter);
                } catch (Throwable $exception) {
                    $this->restart($worker['pipe']);

                    throw $exception;
                }
                if ($result->failed()) {
                    $this->restart($worker['pipe']);
                }

                return $result->exitCode() === 75 ? null : $result;
            });
    }

    private function restart(string $pipe): void
    {
        if ((Cache::get(self::cacheKey())['pipe'] ?? null) === $pipe) {
            Cache::forget(self::cacheKey());
            Cache::put(self::cacheKey().':restart', $pipe, 120);
        }
    }

    public function stop(): void
    {
        try {
            try {
                if ($this->pipe !== null && (Cache::get(self::cacheKey())['pipe'] ?? null) === $this->pipe) {
                    Cache::forget(self::cacheKey());
                }
            } finally {
                if ($this->process?->running()) {
                    $this->process->signal(9);
                    $this->process->wait();
                }
                if ($this->profile !== null) {
                    File::deleteDirectory($this->profile);
                }
            }
        } finally {
            $this->process = null;
            $this->pipe = null;
            $this->profile = null;
            if (is_resource($this->lock)) {
                flock($this->lock, LOCK_UN);
                fclose($this->lock);
            }
            $this->lock = null;
        }
    }

    private function python(): ?string
    {
        $binary = app(LibreOfficeProcess::class)->binary();
        $binary = (new ExecutableFinder)->find($binary) ?? $binary;
        $python = config('document_pdf.libreoffice_python_binary')
            ?? dirname($binary).DIRECTORY_SEPARATOR.(PHP_OS_FAMILY === 'Windows' ? 'python.exe' : 'python');

        return is_file($python) ? $python : null;
    }

    /** @param list<string> $sources */
    private function export(string $python, string $pipe, array $sources, string $directory, string $filter, bool $starting = false): ProcessResult
    {
        $command = [
            $python, base_path('scripts/convert-office-pdf.py'), $pipe, $directory, $filter, ...$sources,
        ];
        if ($starting) {
            $command[] = '--connect-timeout=20';
        }

        return Process::timeout(config('document_pdf.timeout_seconds'))->run($command);
    }
}
