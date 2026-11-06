<?php

use App\Services\LibreOfficePdfWorker;
use App\Services\RmsQuarterlyReportData;
use App\Services\RmsQuarterlyReportDocument;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Process\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    if (PHP_OS_FAMILY !== 'Linux' || getenv('ATHENA_PDF_BENCHMARK') !== '1') {
        $this->markTestSkipped('Set ATHENA_PDF_BENCHMARK=1 on Linux to test real LibreOffice processes and exports.');
    }
    Process::swap(new Factory);
    config(['cache.default' => 'array', 'document_pdf.timeout_seconds' => 120]);
});

/** @return array{pid: int, group: int, session: int} */
function nativeOfficeIdentity(string $pipe): array
{
    $result = Process::run(['ps', '-C', 'soffice.bin', '-o', 'pid=,pgid=,sid=,args='])->throw();
    foreach (explode("\n", $result->output()) as $line) {
        if (str_contains($line, '--accept=pipe,name='.$pipe.';urp;')) {
            [$pid, $group, $session] = preg_split('/\s+/', trim($line), 4);

            return ['pid' => (int) $pid, 'group' => (int) $group, 'session' => (int) $session];
        }
    }
    throw new RuntimeException('The native office process was not found for '.$pipe);
}

function nativeOfficeGroupStopped(int $group): bool
{
    $deadline = microtime(true) + 3;
    do {
        $running = false;
        $result = Process::run(['ps', '-e', '-o', 'pgid=,stat='])->throw();
        foreach (explode("\n", trim($result->output())) as $line) {
            [$processGroup, $state] = preg_split('/\s+/', trim($line));
            if ((int) $processGroup === $group && ! str_starts_with($state, 'Z')) {
                $running = true;
                break;
            }
        }
        if (! $running) {
            return true;
        }
        usleep(10000);
    } while (microtime(true) < $deadline);

    return false;
}

test('native worker restarts remove stalled office groups and preserve unrelated office workers', function () {
    $root = storage_path('framework/testing/native-worker-'.Str::random(8));
    $unrelated = new LibreOfficePdfWorker;
    $worker = new LibreOfficePdfWorker;
    try {
        config(['document_pdf.temporary_directory' => $root.'/unrelated']);
        expect($unrelated->start())->toBeTrue();
        $unrelatedPipe = Cache::get(LibreOfficePdfWorker::cacheKey())['pipe'];
        $unrelatedOffice = nativeOfficeIdentity($unrelatedPipe);
        $python = explode("\0", File::get('/proc/'.$unrelatedOffice['group'].'/cmdline'))[0];

        config(['document_pdf.temporary_directory' => $root.'/managed']);
        expect($worker->start())->toBeTrue();
        foreach (range(1, 2) as $attempt) {
            $pipe = Cache::get(LibreOfficePdfWorker::cacheKey())['pipe'];
            $office = nativeOfficeIdentity($pipe);
            expect($office['group'])->toBe($office['session'])
                ->not->toBe($unrelatedOffice['group'])
                ->and(File::get('/proc/'.$office['group'].'/cmdline'))->toContain('libreoffice-worker.py');

            // Suspend only this verified worker to reproduce a genuinely stalled UNO conversion.
            expect(posix_kill($office['pid'], SIGSTOP))->toBeTrue();
            config(['document_pdf.timeout_seconds' => 1]);
            expect(fn () => $worker->convert([config('work_plan.template_path')], $root, 'pdf'))
                ->toThrow(ProcessTimedOutException::class);
            expect(Cache::get(LibreOfficePdfWorker::cacheKey().':restart'))->toBe($pipe);

            config(['document_pdf.timeout_seconds' => 120]);
            expect($worker->start())->toBeTrue()
                ->and(Cache::get(LibreOfficePdfWorker::cacheKey())['pipe'])->not->toBe($pipe)
                ->and(nativeOfficeGroupStopped($office['group']))->toBeTrue()
                ->and(nativeOfficeIdentity($unrelatedPipe))->toBe($unrelatedOffice);
        }
        $office = nativeOfficeIdentity(Cache::get(LibreOfficePdfWorker::cacheKey())['pipe']);
        $worker->stop();
        expect(nativeOfficeGroupStopped($office['group']))->toBeTrue();

        $result = Process::timeout(120)->run([
            $python, base_path('scripts/convert-office-pdf.py'),
            $unrelatedPipe, $root, 'pdf', config('work_plan.template_path'),
        ]);
        expect($result->successful())->toBeTrue()
            ->and(File::get($root.'/'.pathinfo(config('work_plan.template_path'), PATHINFO_FILENAME).'.pdf'))->toStartWith('%PDF-');
        $unrelated->stop();
        expect(nativeOfficeGroupStopped($unrelatedOffice['group']))->toBeTrue();
    } finally {
        $worker->stop();
        $unrelated->stop();
        File::deleteDirectory($root);
    }
});

test('native worker converts a 4500 record RMS workbook with the last record on every PDF sheet', function () {
    $root = storage_path('framework/testing/native-rms-'.Str::random(8));
    $worker = new LibreOfficePdfWorker;
    config(['document_pdf.temporary_directory' => $root]);
    File::ensureDirectoryExists($root);
    try {
        foreach ([500, 1500] as $records) {
            $report = [];
            foreach (RmsQuarterlyReportData::FIELDS as $section => $fields) {
                foreach (range(1, $records) as $number) {
                    $values = array_fill_keys(array_keys($fields), $section.' '.$number);
                    $values['date'] = '2026-04-08';
                    $report[$section][] = ['values' => $values];
                }
            }
            $start = hrtime(true);
            $contents = app(RmsQuarterlyReportDocument::class)->generate($report, ['year' => 2026, 'quarter' => 2, 'campus' => 'Nasugbu']);
            fwrite(STDERR, sprintf("\nRMS generation: %d rows per sheet, %.3fs.\n", $records, (hrtime(true) - $start) / 1e9));
            expect($contents)->toStartWith('PK');
        }
        File::put($root.'/rms.xlsx', $contents);
        expect($worker->start())->toBeTrue();
        $office = nativeOfficeIdentity(Cache::get(LibreOfficePdfWorker::cacheKey())['pipe']);
        $start = hrtime(true);
        expect($worker->convert([$root.'/rms.xlsx'], $root, 'pdf')->successful())->toBeTrue();
        fwrite(STDERR, sprintf("\nRMS PDF conversion: %.3fs.\n", (hrtime(true) - $start) / 1e9));
        expect(File::get($root.'/rms.pdf'))->toStartWith('%PDF-');
        $text = Process::timeout(120)->run(['pdftotext', '-layout', $root.'/rms.pdf', '-'])->throw()->output();
        $text = preg_replace('/\s+/', '', $text);
        foreach (array_keys(RmsQuarterlyReportData::FIELDS) as $section) {
            expect($text)->toContain($section.'1500');
        }
        $worker->stop();
        expect(nativeOfficeGroupStopped($office['group']))->toBeTrue();
    } finally {
        $worker->stop();
        File::deleteDirectory($root);
    }
});
