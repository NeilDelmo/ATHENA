<?php

use App\Services\LibreOfficeDocumentPdfConverter;
use App\Services\LibreOfficePdfWorker;
use App\Services\LibreOfficeProcess;
use Illuminate\Process\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\InputStream;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    if (PHP_OS_FAMILY !== 'Linux' || ! function_exists('posix_kill')) {
        $this->markTestSkipped('Process groups require Linux and POSIX signals.');
    }
    $this->python = (new ExecutableFinder)->find('python3');
    if ($this->python === null) {
        $this->markTestSkipped('A Python interpreter is needed for the process fixture.');
    }
    Process::swap(new Factory);
    $this->root = sys_get_temp_dir().'/athena-cold-office-'.bin2hex(random_bytes(6));
    File::ensureDirectoryExists($this->root);
    $this->fixture = $this->root.'/office';
    File::put($this->fixture, '#!'.$this->python."\n".<<<'PYTHON'
import subprocess
import sys
import os
import signal
import time
from pathlib import Path

root = Path(__file__).parent
mode = (root / 'mode').read_text()
child = subprocess.Popen([sys.executable, '-c', 'import time; time.sleep(20)' if mode in ('timeout', 'paused timeout', 'early exit') else 'print("office child output", flush=True)'])
(root / 'child.pid').write_text(str(child.pid))
print('office launcher output', flush=True)
print('office error output', file=sys.stderr, flush=True)
if mode == 'paused timeout':
    supervisor = os.getppid()
    handle = os.pidfd_open(supervisor)
    resumer = subprocess.Popen([sys.executable, '-c', 'import signal, sys, time; time.sleep(3); signal.pidfd_send_signal(int(sys.argv[1]), signal.SIGCONT)', str(handle)],
                              pass_fds=(handle,), start_new_session=True, stdin=subprocess.DEVNULL, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    (root / 'resumer.pid').write_text(str(resumer.pid))
    os.close(handle)
    time.sleep(0.05)
    os.kill(supervisor, signal.SIGSTOP)
if mode != 'early exit':
    child.wait()
sys.exit(7 if mode == 'failure' else 0)
PYTHON);
    chmod($this->fixture, 0700);
    config(['cache.default' => 'array', 'document_pdf.libreoffice_binary' => $this->fixture,
        'document_pdf.libreoffice_python_binary' => $this->python, 'document_pdf.timeout_seconds' => 1]);
    Cache::forget(LibreOfficePdfWorker::cacheKey());
});

afterEach(function () {
    if (isset($this->root)) {
        foreach (['child.pid', 'resumer.pid'] as $name) {
            $pidPath = $this->root.'/'.$name;
            if (is_file($pidPath)) {
                $pid = (int) File::get($pidPath);
                if (coldOfficeFixtureRunning($pid)) {
                    posix_kill($pid, SIGKILL);
                }
            }
        }
        File::deleteDirectory($this->root);
    }
});

test('cold conversion cleans descendants when its supervisor is paused across the timeout', function () {
    File::put($this->root.'/mode', 'paused timeout');
    $input = new InputStream;
    $unrelated = Process::forever()->input($input)->start([$this->python, '-c', 'import sys; sys.stdin.buffer.read()']);
    try {
        expect(fn () => app(LibreOfficeDocumentPdfConverter::class)->convertDocx('fixture document'))
            ->toThrow(RuntimeException::class, 'PDF conversion is unavailable');
        expect(coldOfficeFixtureRunning((int) File::get($this->root.'/child.pid')))->toBeFalse()
            ->and($unrelated->running())->toBeTrue()
            ->and(Cache::lock(LibreOfficePdfWorker::cacheKey().':export')->isLocked())->toBeFalse();
    } finally {
        $input->close();
        $unrelated->wait();
    }
});

function coldOfficeFixtureRunning(int $pid): bool
{
    $status = @file_get_contents('/proc/'.$pid.'/status');

    return $status !== false && preg_match('/^State:\s+Z/m', $status) !== 1;
}

test('cold conversion propagates output and exit status while cleaning descendants', function (string $mode, int $exitCode) {
    File::put($this->root.'/mode', $mode);
    $result = app(LibreOfficeProcess::class)->runMany([$this->root.'/source.docx'], $this->root, $this->root.'/profile', 'pdf');
    expect($result->exitCode())->toBe($exitCode)
        ->and($result->output())->toContain('office launcher output')
        ->and($result->errorOutput())->toContain('office error output')
        ->and(coldOfficeFixtureRunning((int) File::get($this->root.'/child.pid')))->toBeFalse()
        ->and(Cache::lock(LibreOfficePdfWorker::cacheKey().':export')->isLocked())->toBeFalse();
})->with(['normal' => ['normal', 0], 'failure' => ['failure', 7], 'early launcher exit' => ['early exit', 0]]);

test('cold conversion timeout kills its descendants and preserves unrelated processes', function () {
    File::put($this->root.'/mode', 'timeout');
    $input = new InputStream;
    $unrelated = Process::forever()->input($input)->start([$this->python, '-c', 'import sys; sys.stdin.buffer.read()']);
    try {
        $result = app(LibreOfficeProcess::class)->runMany([$this->root.'/source.docx'], $this->root, $this->root.'/profile', 'pdf');
        expect($result->exitCode())->toBe(124)->and($result->failed())->toBeTrue();
        expect(coldOfficeFixtureRunning((int) File::get($this->root.'/child.pid')))->toBeFalse()
            ->and($unrelated->running())->toBeTrue()
            ->and(Cache::lock(LibreOfficePdfWorker::cacheKey().':export')->isLocked())->toBeFalse();
    } finally {
        $input->close();
        $unrelated->wait();
    }
});

test('cold conversion remains available without any Python executable', function () {
    File::put($this->root.'/mode', 'normal');
    config(['document_pdf.libreoffice_python_binary' => null]);
    $path = getenv('PATH');
    putenv('PATH=');
    try {
        $result = app(LibreOfficeProcess::class)->runMany([$this->root.'/source.docx'], $this->root, $this->root.'/profile', 'pdf');
        expect($result->successful())->toBeTrue()
            ->and($result->output())->toContain('office child output');
    } finally {
        putenv($path === false ? 'PATH' : 'PATH='.$path);
    }
});

test('one-shot office supervisor cleans descendants when its PHP control pipe closes', function () {
    File::put($this->root.'/mode', 'timeout');
    $input = new InputStream;
    $process = Process::timeout(5)->input($input)->start([$this->python, base_path('scripts/libreoffice-worker.py'), '--once', '5', $this->fixture]);
    try {
        $deadline = microtime(true) + 3;
        while (! is_file($this->root.'/child.pid') && microtime(true) < $deadline) {
            $process->running();
            usleep(10000);
        }
        expect(is_file($this->root.'/child.pid'))->toBeTrue();
        $input->close();
        $process->wait();
        expect(coldOfficeFixtureRunning((int) File::get($this->root.'/child.pid')))->toBeFalse();
    } finally {
        $input->close();
        if ($process->running()) {
            $process->signal(SIGTERM);
            $process->wait();
        }
    }
});

test('real cold office conversion waits for the completed PDF', function () {
    if (getenv('ATHENA_PDF_BENCHMARK') !== '1') {
        $this->markTestSkipped('Set ATHENA_PDF_BENCHMARK=1 to test real LibreOffice exports.');
    }
    $binary = (new ExecutableFinder)->find('soffice');
    if ($binary === null) {
        $this->markTestSkipped('LibreOffice is required for a real cold export.');
    }
    config(['document_pdf.libreoffice_binary' => $binary, 'document_pdf.timeout_seconds' => 120]);
    File::ensureDirectoryExists($this->root.'/profile');
    $source = config('work_plan.template_path');
    $result = app(LibreOfficeProcess::class)->runMany([$source], $this->root, $this->root.'/profile', 'pdf:writer_pdf_Export');
    $pdf = $this->root.'/'.pathinfo($source, PATHINFO_FILENAME).'.pdf';
    expect($result->successful())->toBeTrue()
        ->and(is_file($pdf))->toBeTrue()
        ->and(file_get_contents($pdf, false, null, 0, 5))->toBe('%PDF-');
});
