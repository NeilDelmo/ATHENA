<?php

namespace App\Console\Commands;

use App\Actions\ProcessResearchCallOpeningNotifications as ProcessNotifications;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('research-calls:process-opening-notifications')]
#[Description('Send due Research Call opening reminders and faculty availability notifications')]
class ProcessResearchCallOpeningNotifications extends Command
{
    public function handle(ProcessNotifications $processNotifications): int
    {
        $processed = $processNotifications->handle();

        if ($processed > 0) {
            $this->info("Processed {$processed} research call opening notification(s).");
        }

        return self::SUCCESS;
    }
}
