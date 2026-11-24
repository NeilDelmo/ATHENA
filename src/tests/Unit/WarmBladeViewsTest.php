<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\Engines\CompilerEngine;
use Illuminate\View\FileViewFinder;
use Tests\TestCase;

uses(TestCase::class);

test('warming Blade views compiles missing and changed templates without clearing the live cache', function () {
    $directory = storage_path('framework/testing/view-warm-'.Str::uuid());
    $sources = $directory.'/sources';
    $compiled = $directory.'/compiled';
    File::ensureDirectoryExists($sources);
    File::ensureDirectoryExists($compiled);

    $finder = new FileViewFinder(app('files'), [$sources]);
    app('view')->setFinder($finder);
    $compiler = new BladeCompiler(app('files'), $compiled);
    app('view.engine.resolver')->register('blade', fn () => new CompilerEngine($compiler));
    config(['view.compiled' => $compiled]);

    try {
        File::put($sources.'/ready.blade.php', 'Ready');
        File::put($sources.'/updated.blade.php', 'Updated {{ $name }}');
        File::put($sources.'/new.blade.php', 'New {{ $name }}');
        File::put($sources.'/touched.blade.php', 'Unchanged');
        $ready = realpath($sources.'/ready.blade.php');
        $updated = realpath($sources.'/updated.blade.php');
        $touched = realpath($sources.'/touched.blade.php');
        $readyCompiled = $compiler->getCompiledPath($ready);
        $updatedCompiled = $compiler->getCompiledPath($updated);
        $touchedCompiled = $compiler->getCompiledPath($touched);
        $lastCompiled = time() - 30;
        touch($ready, $lastCompiled - 30);
        touch($updated, $lastCompiled + 10);
        touch($sources.'/new.blade.php', $lastCompiled + 10);
        File::put($readyCompiled, 'Ready');
        touch($readyCompiled, $lastCompiled);
        File::put($updatedCompiled, 'Old contents');
        touch($updatedCompiled, $lastCompiled);
        $compiler->compile($touched);
        touch($touchedCompiled, $lastCompiled);
        touch($touched, $lastCompiled + 10);
        File::put($compiled.'/another-worker.php', 'Keep this cached PHP file');

        $this->artisan('view:warm')->assertSuccessful();

        clearstatcache();
        expect(filemtime($readyCompiled))->toBe($lastCompiled)
            ->and(File::get($updatedCompiled))->toContain('Updated', 'echo')
            ->and(File::get($compiler->getCompiledPath(realpath($sources.'/new.blade.php'))))->toContain('New', 'echo')
            ->and($compiler->isExpired($touched))->toBeFalse()
            ->and(File::get($compiled.'/another-worker.php'))->toBe('Keep this cached PHP file');

        $this->artisan('view:warm')->expectsOutputToContain('Compiled 0 Blade templates.')->assertSuccessful();
    } finally {
        File::deleteDirectory($directory);
    }
});
