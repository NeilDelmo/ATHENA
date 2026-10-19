<?php

namespace App\Console\Commands;

use App\Services\ImagePreviewService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('image-previews:prune')]
#[Description('Remove image previews whose originals were deleted or whose cache is older than seven days')]
class PruneImagePreviews extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ImagePreviewService $previews): int
    {
        $this->info('Removed '.$previews->prune().' cached image previews.');

        return self::SUCCESS;
    }
}
