<?php

use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Process\Factory;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

uses(TestCase::class);

test('recovery watch checks the office every second and reconciles drafts once per minute', function () {
    $root = sys_get_temp_dir().'/athena-recovery-watch-'.bin2hex(random_bytes(6));
    File::ensureDirectoryExists($root);
    $script = $root.'/watch.php';
    File::put($script, '<?php'."\n".'require '.var_export(base_path('vendor/autoload.php'), true).';'."\n"
        .'$app = require '.var_export(base_path('bootstrap/app.php'), true).';'."\n"
        .'$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();'."\n"
        .'$path = '.var_export($root.'/state.json', true).';'."\n".<<<'PHP'
$state = ['starts' => 0, 'reconciles' => 0, 'stops' => 0];
$persist = function () use (&$state, $path): void { file_put_contents($path, json_encode($state, JSON_THROW_ON_ERROR)); };
$office = Mockery::mock(App\Services\LibreOfficePdfWorker::class);
$office->shouldReceive('start')->andReturnUsing(function () use (&$state, $persist): bool { $state['starts']++; $persist(); return true; });
$office->shouldReceive('stop')->andReturnUsing(function () use (&$state, $persist): void { $state['stops']++; $persist(); });
$preparation = Mockery::mock(App\Services\ProposalPdfPreparation::class);
$preparation->shouldReceive('reconcile')->andReturnUsing(function () use (&$state, $persist): int { $state['reconciles']++; $persist(); return 0; });
$command = Mockery::mock(App\Console\Commands\RecoverProposalPdfs::class)->makePartial();
$command->shouldReceive('option')->with('watch')->andReturn(true);
$command->shouldReceive('info')->andReturnNull();
Illuminate\Support\Carbon::setTestNow('2026-11-04 00:00:00');
Illuminate\Support\Sleep::fake(syncWithCarbon: true);
Illuminate\Support\Sleep::whenFakingSleep(function () use (&$state): void {
    if ($state['starts'] >= 61) {
        throw new RuntimeException('Fixture watch complete.');
    }
});
try {
    $command->handle($preparation, $office);
} catch (RuntimeException $exception) {
    if ($exception->getMessage() !== 'Fixture watch complete.') { throw $exception; }
}
PHP);
    Process::swap(new Factory);
    try {
        try {
            Process::timeout(3)->env(['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => ''])->run([PHP_BINARY, $script]);
        } catch (ProcessTimedOutException) {
            // The regression used native sleep(60), which cannot be advanced by the fixture clock.
        }
        expect(json_decode(File::get($root.'/state.json'), true, flags: JSON_THROW_ON_ERROR))
            ->toBe(['starts' => 61, 'reconciles' => 2, 'stops' => 1]);
    } finally {
        File::deleteDirectory($root);
    }
});
