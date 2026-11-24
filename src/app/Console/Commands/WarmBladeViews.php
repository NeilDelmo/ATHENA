<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Foundation\Console\ViewCacheCommand;
use Symfony\Component\Finder\SplFileInfo;

#[Signature('view:warm')]
#[Description('Compile missing and changed Blade templates without clearing the view cache')]
class WarmBladeViews extends ViewCacheCommand
{
    public function handle(): int
    {
        $compiler = $this->laravel['view']->getEngineResolver()->resolve('blade')->getCompiler();
        $compiledCount = 0;

        foreach ($this->paths() as $path) {
            $startedAt = time();
            $expired = $this->bladeFilesIn([$path])->filter(
                fn (SplFileInfo $file): bool => $compiler->isExpired($file->getRealPath()),
            );
            $this->compileViews($expired);

            foreach ($expired as $file) {
                $source = $file->getRealPath();
                $compiled = $compiler->getCompiledPath($source);
                clearstatcache();

                // Blade keeps the old timestamp if recompiling produces identical PHP.
                // Keep edits made during warming eligible for the next compilation.
                if (is_file($compiled) && filemtime($source) < $startedAt && $compiler->isExpired($source)) {
                    touch($compiled, $startedAt);
                }
            }

            $compiledCount += $expired->count();
        }

        $this->components->info("Compiled {$compiledCount} Blade templates.");

        return self::SUCCESS;
    }
}
