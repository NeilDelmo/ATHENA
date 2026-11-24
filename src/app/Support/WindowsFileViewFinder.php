<?php

namespace App\Support;

use Illuminate\View\FileViewFinder;

class WindowsFileViewFinder extends FileViewFinder
{
    protected function findInPaths($name, $paths): string
    {
        $path = parent::findInPaths($name, $paths);

        // Match the real paths used by the Blade cache command on Windows.
        return realpath($path) ?: $path;
    }
}
