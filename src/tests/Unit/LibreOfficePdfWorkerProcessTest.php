<?php

use App\Services\LibreOfficePdfWorker;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\InputStream;
use Tests\TestCase;

uses(TestCase::class);

test('worker shutdown cleans its processes without stopping unrelated processes', function (string $shutdown) {
    if (PHP_OS_FAMILY === 'Windows') {
        $this->markTestSkipped('POSIX launchers can exit before their office child; Windows uses soffice.com.');
    }
    $python = (new ExecutableFinder)->find('python3');
    if ($python === null) {
        $this->markTestSkipped('A Python interpreter is needed for the process fixture.');
    }
    $root = sys_get_temp_dir().'/athena-worker-'.bin2hex(random_bytes(6));
    File::ensureDirectoryExists($root);
    if ($shutdown === 'live launcher') {
        File::put($root.'/keep-launcher', '');
    }
    $fixture = $root.'/office';
    File::put($fixture, '#!'.$python."\n".<<<'PYTHON'
import json
import os
import socket
import subprocess
import sys
import time
from pathlib import Path
from urllib.parse import unquote, urlparse

if sys.argv[1] == 'child':
    while not Path(__file__).with_name('keep-launcher').exists() and os.getppid() == int(sys.argv[3]):
        time.sleep(0.01)
    with socket.socket() as server:
        server.bind(('127.0.0.1', 0))
        server.listen()
        ready = Path(sys.argv[2])
        temporary = ready.with_suffix('.tmp')
        temporary.write_text(json.dumps(server.getsockname()[1]))
        temporary.replace(ready)
        while True:
            connection, _ = server.accept()
            with connection:
                if connection.recv(16) == b'stop':
                    break
else:
    profile = next(arg.split('=', 1)[1] for arg in sys.argv if arg.startswith('-env:UserInstallation='))
    path = unquote(urlparse(profile).path)
    subprocess.Popen([sys.executable, __file__, 'child', str(Path(path) / 'fixture.json'), str(os.getpid())],
                     stdin=subprocess.DEVNULL, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    if Path(__file__).with_name('keep-launcher').exists():
        time.sleep(3600)
PYTHON);
    chmod($fixture, 0700);
    config(['cache.default' => 'array', 'document_pdf.libreoffice_binary' => $fixture,
        'document_pdf.libreoffice_python_binary' => $python, 'document_pdf.temporary_directory' => $root]);
    $port = null;
    $ports = [];
    $worker = new LibreOfficePdfWorker;
    $unrelatedInput = new InputStream;
    $unrelated = Process::forever()->input($unrelatedInput)->start([$python, '-c', 'import sys; sys.stdin.read()']);
    Process::fake(['*convert-office-pdf.py*' => function (PendingProcess $process) use ($root, &$port, &$ports, $shutdown) {
        if (! in_array('--connect-timeout=20', $process->command, true)) {
            throw new RuntimeException('The fixture export timed out.');
        }
        $deadline = microtime(true) + 5;
        do {
            $files = glob($root.'/worker/*/fixture.json');
            if ($files !== []) {
                $port = (int) File::get($files[0]);
                $ports[] = $port;

                return Process::result(exitCode: $shutdown === 'startup failure' ? 1 : 0);
            }
            usleep(10000);
        } while (microtime(true) < $deadline);
        $this->fail('The office fixture did not start.');
    }]);
    $isListening = function (int $port): bool {
        $connection = @stream_socket_client('tcp://127.0.0.1:'.$port, $errorCode, $error, 0.1);
        if ($connection === false) {
            return false;
        }
        fclose($connection);

        return true;
    };
    try {
        if ($shutdown === 'startup failure') {
            expect(fn () => $worker->start())->toThrow(RuntimeException::class, 'could not initialize');
        } else {
            expect($worker->start())->toBeTrue();
            expect($isListening($port))->toBeTrue();
            if ($shutdown === 'restart') {
                $oldPort = $port;
                $pipe = Cache::get(LibreOfficePdfWorker::cacheKey())['pipe'];
                expect(fn () => $worker->convert([$fixture], $root, 'pdf'))
                    ->toThrow(RuntimeException::class, 'The fixture export timed out.');
                expect(Cache::get(LibreOfficePdfWorker::cacheKey().':restart'))->toBe($pipe);
                expect($worker->start())->toBeTrue()
                    ->and($port)->not->toBe($oldPort)
                    ->and($isListening($oldPort))->toBeFalse()
                    ->and($isListening($port))->toBeTrue();
            }
            $worker->stop();
        }
        $deadline = microtime(true) + 3;
        while ($isListening($port) && microtime(true) < $deadline) {
            usleep(10000);
        }
        expect($isListening($port))->toBeFalse()
            ->and($unrelated->running())->toBeTrue()
            ->and(Cache::has(LibreOfficePdfWorker::cacheKey()))->toBeFalse()
            ->and(glob($root.'/worker/*', GLOB_ONLYDIR))->toBe([]);
        $lock = fopen($root.'/worker/worker.lock', 'c');
        try {
            expect(flock($lock, LOCK_EX | LOCK_NB))->toBeTrue();
        } finally {
            fclose($lock);
        }
    } finally {
        foreach ($ports as $fixturePort) {
            $connection = @stream_socket_client('tcp://127.0.0.1:'.$fixturePort, $errorCode, $error, 0.1);
            if ($connection !== false) {
                fwrite($connection, 'stop');
                fclose($connection);
            }
        }
        $worker->stop();
        $unrelatedInput->close();
        $unrelated->wait();
        File::deleteDirectory($root);
    }
})->with(['normal shutdown', 'live launcher', 'startup failure', 'restart']);
